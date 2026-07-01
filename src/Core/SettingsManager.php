<?php
declare(strict_types=1);

namespace Syncly\Core;

use Syncly\Core\Settings\AjaxHandler;
use Syncly\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager class
 *
 * Handles settings storage and retrieval via AJAX.
 *
 * @package    Syncly
 * @subpackage Syncly/Core
 */
class SettingsManager {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings option name (per-site)
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'syncly_settings';

	/**
	 * Network-wide settings option name
	 *
	 * @var string
	 */
	private const NETWORK_OPTION_NAME = 'syncly_network_settings';

	/**
	 * Connection verification option name
	 *
	 * @var string
	 */
	private const VERIFICATION_OPTION_NAME = 'syncly_connection_verified';

	/**
	 * Settings whose selected GHL tags/fields belong to a specific location.
	 *
	 * Stored as {setting_key}_{location_id}; hydrated back to {setting_key} for callers.
	 *
	 * @var array<int, string>
	 */
	private const LOCATION_SCOPED_SETTING_KEYS = [
		'user_field_mapping',
		'role_tags',
		'global_tags',
		'user_register_tags',
		'restrictions_allowed_tags',
		'family_parent_tag',
		'login_last_login_field_id',
		'login_count_field_id',
		'login_first_login_tags',
		'login_every_login_tags',
		'login_milestone_tags',
		'login_inactivity_tags',
		'login_active_tags',
		'login_tag_redirects',
		'wc_customer_tag',
		'wc_abandoned_cart_tag',
		'wc_abandoned_cart_recovery_tag',
	];

	/**
	 * Standalone options containing GHL location-specific object/field mappings.
	 *
	 * @var array<int, string>
	 */
	private const LOCATION_SCOPED_OPTION_NAMES = [
		'syncly_custom_object_mappings',
	];

	/**
	 * Get class instance | singleton pattern
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check if sync logging is enabled
	 *
	 * @return bool
	 */
	public static function is_sync_logging_enabled(): bool {
		$instance = self::get_instance();
		$settings = $instance->get_settings_array();
		return ! empty( $settings['enable_sync_logging'] );
	}

	/**
	 * Private constructor to prevent direct creation
	 */
	private function __construct() {
		// Constructor is empty, hooks are initialized via init()
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		// Core settings AJAX handlers (remain in SettingsManager).
		add_action( 'wp_ajax_syncly_save_settings', [ $this, 'save_settings' ] );
		add_action( 'wp_ajax_syncly_get_settings', [ $this, 'get_settings' ] );
		add_action( 'wp_ajax_syncly_test_connection', [ $this, 'test_connection' ] );
		add_action( 'wp_ajax_syncly_save_field_mapping', [ $this, 'save_field_mapping' ] );
		add_action( 'wp_ajax_syncly_preview_user_sync', [ $this, 'preview_user_sync' ] );
		add_action( 'wp_ajax_syncly_oauth_reconnect', [ $this, 'oauth_reconnect' ] );
		add_action( 'wp_ajax_syncly_refresh_access_token', [ $this, 'ajax_refresh_access_token' ] );
		add_action( 'wp_ajax_syncly_save_wizard_settings', [ $this, 'handle_save_wizard_settings' ] );
		add_action( 'wp_ajax_syncly_bulk_sync_users', [ $this, 'handle_bulk_sync_users' ] );
		add_action( 'wp_ajax_syncly_bulk_import_from_ghl', [ $this, 'handle_bulk_import_from_ghl' ] );

		// Integrations AJAX handlers (delegated to AjaxHandler).
		add_action( 'wp_ajax_syncly_save_integrations', [ $this, 'handle_save_integrations' ] );
		add_action( 'wp_ajax_syncly_get_pipelines', [ $this, 'handle_get_pipelines' ] );
		add_action( 'wp_ajax_syncly_get_pipeline_stages', [ $this, 'handle_get_pipeline_stages' ] );
		add_action( 'wp_ajax_syncly_search_products', [ $this, 'handle_search_products' ] );

		// Sync Logs AJAX handlers (delegated to AjaxHandler).
		add_action( 'wp_ajax_syncly_get_logs', [ $this, 'handle_get_logs' ] );
		add_action( 'wp_ajax_syncly_delete_old_logs', [ $this, 'handle_delete_old_logs' ] );
		add_action( 'wp_ajax_syncly_clear_all_logs', [ $this, 'handle_clear_all_logs' ] );
		add_action( 'wp_ajax_syncly_save_logs_per_page', [ $this, 'handle_save_logs_per_page' ] );

		// Field Mapping Suggestions (delegated to AjaxHandler).
		add_action( 'wp_ajax_syncly_get_field_suggestions', [ $this, 'handle_get_field_suggestions' ] );

		// Purge caches when connection status changes (location switch, OAuth, disconnect).
		add_action( 'syncly_connection_status_changed', [ $this, 'purge_location_caches' ] );
	}

	/**
	 * Save settings via AJAX
	 * Universal handler for all settings tabs
	 *
	 * @return void
	 */
	public function save_settings(): void {
		try {
			// Verify nonce
			check_ajax_referer( 'syncly_settings_nonce', 'nonce' );

			// Check permissions
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error(
					[
						'message' => __( 'You do not have permission to save settings.', 'syncly' ),
					],
					403
				);
			}

			// Get current settings to merge with new data
			$current_settings = $this->get_settings_array();

			// Prepare new settings array
			$new_settings = [];

			// Check if API credentials are being changed
			$credentials_changed = false;

			// Process all POST data dynamically
			foreach ( $_POST as $key => $value ) {
				// Skip WordPress and plugin internal fields
				if ( in_array( $key, [ 'action', 'nonce', '_wp_http_referer' ], true ) ) {
					continue;
				}

				// Sanitize the key itself — reject malformed or unexpected keys.
				$key = sanitize_key( $key );
				if ( '' === $key ) {
					continue;
				}

				// Convert empty array marker before type checks so location-scoped tag keys are handled correctly
				if ( '__EMPTY_ARRAY__' === $value ) {
					$value = [];
				}

				// Sanitize based on value type
				if ( is_array( $value ) ) {
					if ( 'role_tags' === $key ) {
						$new_settings[ $key ] = $this->sanitize_role_tags( $value );
					} else {
						$new_settings[ $key ] = $this->sanitize_array_recursive( $value );
					}
				} else {
					if ( 'restrictions_denied_message' === $key ) {
						$new_settings[ $key ] = wp_kses_post( wp_unslash( (string) $value ) );
						continue;
					}

					// Handle URL fields with proper sanitization
					if ( 'ghl_white_label_domain' === $key ) {
						$new_settings[ $key ] = ! empty( $value ) ? esc_url_raw( wp_unslash( $value ) ) : '';
					} else {
						// Handle scalar values
						$new_settings[ $key ] = sanitize_text_field( wp_unslash( $value ) );
					}

					// Check if API credentials changed
					if ( in_array( $key, [ 'api_token', 'location_id' ], true ) ) {
						if ( isset( $current_settings[ $key ] ) && $current_settings[ $key ] !== $new_settings[ $key ] ) {
							$credentials_changed = true;
						}
					}
				}
			}

			// Merge with current settings to preserve unmodified fields
			$settings = array_merge(
				$current_settings,
				$new_settings,
				[
					'updated_at' => current_time( 'mysql' ),
					'site_id'    => get_current_blog_id(),
				]
			);

			$settings = $this->prepare_location_scoped_settings_for_storage( $settings );

			// Validate critical fields only if user is actively trying to set up manual API connection
			// Don't validate on imports or when only location_id is present
			$is_setting_manual_api = isset( $new_settings['api_token'] ) && ! empty( $new_settings['api_token'] );

			if ( $is_setting_manual_api ) {
				// User is trying to set manual API token - require location_id too
				if ( empty( $settings['location_id'] ) ) {
					wp_send_json_error(
						[
							'message' => __( 'Location ID is required when setting API Token.', 'syncly' ),
						],
						400
					);
				}
			}

			// Save settings (multisite aware) - use repository
			$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
			$saved      = $repository->save_site_settings( $settings );

			// If credentials changed, invalidate verification and purge caches
			if ( $credentials_changed && $saved ) {
				$this->mark_connection_unverified();
				$this->purge_location_caches();
			}

			if ( ! $saved ) {
				throw new \Exception( __( 'Failed to save settings. Please try again.', 'syncly' ) );
			}

				// Build response settings and include location-specific tag configurations
				$response_settings = $this->get_settings_array();
				$location_id       = $response_settings['location_id'] ?? $response_settings['oauth_location_id'] ?? '';

			if ( ! empty( $location_id ) ) {
				// Preserve legacy response shape for role/global/register tag settings.
				$response_settings[ "role_tags_{$location_id}" ]          = $this->get_location_role_tags( $location_id );
				$response_settings[ "global_tags_{$location_id}" ]        = $this->get_location_global_tags( $location_id );
				$response_settings[ "user_register_tags_{$location_id}" ] = $this->get_location_register_tags( $location_id );

				unset( $response_settings['role_tags'], $response_settings['global_tags'], $response_settings['user_register_tags'] );
			}

				$response_data = [
					'message'  => __( 'Settings saved successfully!', 'syncly' ),
					'settings' => $response_settings,
				];

				// Add warning if credentials changed
				if ( $credentials_changed ) {
					$response_data['warning'] = __( 'API credentials changed. Please test your connection to verify.', 'syncly' );
				}

				wp_send_json_success( $response_data );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message'       => sprintf(
						/* translators: %s: error message */
						__( 'An error occurred while saving settings: %s', 'syncly' ),
						$e->getMessage()
					),
					'error_details' => [
						'file' => $e->getFile(),
						'line' => $e->getLine(),
						'err'  => $e->getCode(),
					],
				],
				500
			);
		} catch ( \Error $e ) {
			wp_send_json_error(
				[
					'message'       => sprintf(
						/* translators: %s: error message */
						__( 'A fatal error occurred while saving settings: %s', 'syncly' ),
						$e->getMessage()
					),
					'error_details' => [
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					],
				],
				500
			);
		}
	}

	/**
	 * Save manual connection settings programmatically
	 *
	 * This method is used for internal/programmatic settings updates
	 * and does NOT require nonce verification. Used by MenuManager for manual API key connections.
	 *
	 * @param array $new_settings Settings array to merge with existing settings.
	 * @return array Result array with 'success' boolean and 'message' string.
	 */
	public function save_manual_connection_settings( array $new_settings ): array {
		$connection_manager = \Syncly\API\ConnectionManager::get_instance();
		return $connection_manager->save_manual_connection_settings( $new_settings );
	}

	/**
	 * Get settings via AJAX
	 *
	 * @return void
	 */
	public function get_settings(): void {
		// Verify nonce
		check_ajax_referer( 'syncly_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to view settings.', 'syncly' ),
				],
				403
			);
		}

		// Check connection status
		$settings      = $this->get_settings_array();
		$oauth_handler = new \Syncly\API\OAuth\OAuthHandler();
		$oauth_status  = $oauth_handler->get_connection_status();
		$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );

		wp_send_json_success(
			[
				'settings'     => $settings,
				'is_connected' => $is_connected,
			]
		);
	}

	/**
	 * Test API connection
	 *
	 * @return void
	 */
	public function test_connection(): void {
		// Verify nonce
		check_ajax_referer( 'syncly_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to test connection.', 'syncly' ),
				],
				403
			);
		}

		// Use ConnectionManager to test connection
		$connection_manager = \Syncly\API\ConnectionManager::get_instance();
		$result             = $connection_manager->test_connection();

		// Return AJAX response based on result
		if ( $result['success'] ) {
			wp_send_json_success(
				[
					'message'       => $result['message'],
					'location_name' => $result['location_name'] ?? '',
					'status_code'   => $result['status_code'] ?? 200,
				]
			);
		} else {
			wp_send_json_error(
				[
					'message'     => $result['message'],
					'details'     => $result['details'] ?? null,
					'status_code' => $result['status_code'] ?? 500,
				],
				$result['code'] ?? 500
			);
		}
	}

	/**
	 * AJAX: Force-refresh the OAuth access token.
	 *
	 * Calls Client::refresh_access_token() directly so the admin can
	 * manually recover a stale token without going through the full
	 * OAuth re-authorization flow.
	 *
	 * @return void
	 */
	public function ajax_refresh_access_token(): void {
		check_ajax_referer( 'syncly_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'syncly' ) ], 403 );
		}

		try {
			$client  = \Syncly\API\Client\Client::get_instance();
			$result  = $client->refresh_access_token();
			$expires = isset( $result['expires_in'] ) ? human_time_diff( time(), time() + (int) $result['expires_in'] ) : '24 hours';

			wp_send_json_success(
				[
					'message' => sprintf(
						/* translators: %s: token validity duration */
						__( 'Access token refreshed successfully. Valid for %s.', 'syncly' ),
						$expires
					),
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Handle OAuth reconnect request
	 *
	 * @return void
	 */
	public function oauth_reconnect(): void {
		// Verify nonce
		check_ajax_referer( 'syncly_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to reconnect OAuth.', 'syncly' ),
				],
				403
			);
		}

		try {
			// Get OAuth authorization URL
			$oauth_handler = new \Syncly\API\OAuth\OAuthHandler();
			$auth_url      = $oauth_handler->get_authorization_url();

			wp_send_json_success(
				[
					'message'      => __( 'Redirecting to GoHighLevel for reconnection...', 'syncly' ),
					'redirect_url' => $auth_url,
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'Reconnection failed: %s', 'syncly' ),
						$e->getMessage()
					),
				],
				500
			);
		}
	}


	/**
	 * Save field mapping via AJAX
	 *
	 * @return void
	 */
	public function save_field_mapping(): void {
		// Verify nonce
		check_ajax_referer( 'syncly_field_mapping_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to save field mapping.', 'syncly' ),
				],
				403
			);
		}

		// Get field mapping data from POST
		$field_mappings = isset( $_POST['field_mappings'] ) && is_array( $_POST['field_mappings'] )
			? wp_unslash( $_POST['field_mappings'] )
			: [];

		// Process and sanitize field mappings
		$sanitized_mappings = [];
		foreach ( $field_mappings as $wp_field => $mapping_data ) {
			$wp_field = sanitize_text_field( $wp_field );

			if ( is_array( $mapping_data ) ) {
				$sanitized_mappings[ $wp_field ] = [
					'ghl_field' => isset( $mapping_data['ghl_field'] ) ? sanitize_text_field( $mapping_data['ghl_field'] ) : '',
					'direction' => isset( $mapping_data['direction'] ) ? sanitize_text_field( $mapping_data['direction'] ) : 'both',
				];
			}
		}

		// Get current settings
		$current_settings = $this->get_settings_array();

		// Update field mapping
		$current_settings['user_field_mapping'] = $sanitized_mappings;
		$current_settings['updated_at']         = current_time( 'mysql' );
		$current_settings                       = $this->prepare_location_scoped_settings_for_storage( $current_settings );

		// Save settings
		$saved = $this->save_site_settings( $current_settings );

		if ( $saved ) {
			wp_send_json_success(
				[
					'message' => __( 'Field mapping saved successfully!', 'syncly' ),
					'count'   => count( $sanitized_mappings ),
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Failed to save field mapping. Please try again.', 'syncly' ),
				],
				500
			);
		}
	}

	/**
	 * Sanitize array recursively
	 *
	 * @param array $array Array to sanitize
	 * @return array Sanitized array
	 */
	private function sanitize_array_recursive( array $array ): array {
		$sanitized = [];
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_array_recursive( $value );
			} elseif ( $value === '__EMPTY_ARRAY__' ) {
				$sanitized[ $key ] = [];
			} else {
				$sanitized[ $key ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}
		return $sanitized;
	}

	/**
	 * Sanitize role_tags structure
	 *
	 * @param array $role_tags Role tags array from POST
	 * @return array Sanitized role tags structure
	 */
	private function sanitize_role_tags( array $role_tags ): array {
		$sanitized = [];

		foreach ( $role_tags as $role => $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}

			$sanitized_config = [];

			// Sanitize role key (if present)
			if ( isset( $config['role'] ) ) {
				$sanitized_config['role'] = sanitize_text_field( wp_unslash( $config['role'] ) );
			}

			// Sanitize tags (can be array from Select2 or string)
			if ( isset( $config['tags'] ) ) {
				if ( is_array( $config['tags'] ) ) {
					// Handle Select2 array format
					$sanitized_config['tags'] = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $config['tags'] ) );
				} elseif ( $config['tags'] === '__EMPTY_ARRAY__' ) {
					$sanitized_config['tags'] = [];
				} else {
					// Handle string format
					$sanitized_config['tags'] = sanitize_text_field( wp_unslash( $config['tags'] ) );
				}
			} else {
				$sanitized_config['tags'] = [];
			}

			// Sanitize checkboxes
			$sanitized_config['auto_apply']       = isset( $config['auto_apply'] ) && $config['auto_apply'] === '1';
			$sanitized_config['remove_on_change'] = isset( $config['remove_on_change'] ) && $config['remove_on_change'] === '1';

			$sanitized[ sanitize_text_field( wp_unslash( $role ) ) ] = $sanitized_config;
		}

		return $sanitized;
	}

	/**
	 * Get settings as array (multisite aware)
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return array
	 */
	public function get_settings_array( ?int $site_id = null ): array {
		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
		return $this->hydrate_location_scoped_settings( $repository->get_settings_array( $site_id ) );
	}

	/**
	 * Save site settings (multisite aware)
	 *
	 * @param array    $settings Settings array.
	 * @param int|null $site_id  Optional. Site ID for multisite.
	 * @return bool
	 */
	public function save_site_settings( array $settings, ?int $site_id = null ): bool {
		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
		return $repository->save_site_settings( $this->prepare_location_scoped_settings_for_storage( $settings ), $site_id );
	}

	/**
	 * Get network-wide settings
	 *
	 * @return array
	 */
	public function get_network_settings(): array {
		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
		return $repository->get_network_settings();
	}

	/**
	 * Save network-wide settings
	 *
	 * @param array $settings Network settings array.
	 * @return bool
	 */
	public function save_network_settings( array $settings ): bool {
		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
		return $repository->save_network_settings( $settings );
	}

	/**
	 * Get a single setting value
	 *
	 * @param string   $key     Setting key.
	 * @param mixed    $default Default value if key doesn't exist.
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return mixed
	 */
	public function get_setting( string $key, $default = '', ?int $site_id = null ) {
		if ( $this->is_location_scoped_setting_key( $key ) ) {
			$settings = $this->get_settings_array( $site_id );
			return $settings[ $key ] ?? $default;
		}

		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
		return $repository->get_setting( $key, $default, $site_id );
	}

	/**
	 * Update a single setting value
	 *
	 * @param string   $key     Setting key.
	 * @param mixed    $value   Setting value.
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool True on success, false on failure.
	 */
	public function update_setting( string $key, $value, ?int $site_id = null ): bool {
		if ( $this->is_location_scoped_setting_key( $key ) ) {
			$settings         = $this->get_settings_array( $site_id );
			$settings[ $key ] = $value;
			return $this->save_site_settings( $settings, $site_id );
		}

		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
		return $repository->update_setting( $key, $value, $site_id );
	}

	/**
	 * Get the location ID from a settings array without going through hydrated getters.
	 *
	 * @param array $settings Settings array.
	 * @return string Location ID or empty string.
	 */
	private function get_location_id_from_settings( array $settings ): string {
		return (string) ( $settings['location_id'] ?? $settings['oauth_location_id'] ?? '' );
	}

	/**
	 * Determine whether a setting key should be scoped to the active GHL location.
	 *
	 * @param string $key Setting key.
	 * @return bool True when the key is location-scoped.
	 */
	private function is_location_scoped_setting_key( string $key ): bool {
		return in_array( $key, self::LOCATION_SCOPED_SETTING_KEYS, true );
	}

	/**
	 * Determine whether an option should be scoped to the active GHL location.
	 *
	 * @param string $option_name Option name.
	 * @return bool True when the option is location-scoped.
	 */
	private function is_location_scoped_option_name( string $option_name ): bool {
		return in_array( $option_name, self::LOCATION_SCOPED_OPTION_NAMES, true );
	}

	/**
	 * Hydrate location-scoped stored keys back onto their base keys for consumers.
	 *
	 * @param array $settings Raw settings from storage.
	 * @return array Settings with current-location aliases populated.
	 */
	private function hydrate_location_scoped_settings( array $settings ): array {
		$location_id = $this->get_location_id_from_settings( $settings );

		if ( '' === $location_id ) {
			return $settings;
		}

		foreach ( self::LOCATION_SCOPED_SETTING_KEYS as $key ) {
			$scoped_key = TagManager::scoped_meta_key( $key, $location_id );
			if ( array_key_exists( $scoped_key, $settings ) ) {
				$settings[ $key ] = $settings[ $scoped_key ];
			}
		}

		return $settings;
	}

	/**
	 * Move current-location selector settings from base keys to location-scoped keys before saving.
	 *
	 * @param array $settings Settings about to be stored.
	 * @return array Settings prepared for storage.
	 */
	public function prepare_location_scoped_settings_for_storage( array $settings ): array {
		$location_id = $this->get_location_id_from_settings( $settings );

		if ( '' === $location_id ) {
			return $settings;
		}

		foreach ( self::LOCATION_SCOPED_SETTING_KEYS as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$settings[ TagManager::scoped_meta_key( $key, $location_id ) ] = $settings[ $key ];
				unset( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Delete a single setting value
	 *
	 * @param string   $key     Setting key.
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool True on success, false on failure.
	 */
	public function delete_setting( string $key, ?int $site_id = null ): bool {
		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
		return $repository->delete_setting( $key, $site_id );
	}

	/**
	 * Get option (multisite-aware wrapper)
	 *
	 * @param string   $option_name Option name
	 * @param mixed    $default     Default value
	 * @param int|null $site_id     Optional. Site ID for multisite.
	 * @return mixed Option value
	 */
	public function get_option( string $option_name, $default = false, ?int $site_id = null ) {
		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();

		if ( $this->is_location_scoped_option_name( $option_name ) ) {
			$settings    = $this->get_settings_array( $site_id );
			$location_id = $this->get_location_id_from_settings( $settings );

			if ( '' !== $location_id ) {
				$missing = new \stdClass();
				$value   = $repository->get_option( TagManager::scoped_meta_key( $option_name, $location_id ), $missing, $site_id );

				if ( $value !== $missing ) {
					return $value;
				}
			}
		}

		return $repository->get_option( $option_name, $default, $site_id );
	}

	/**
	 * Update option (multisite-aware wrapper)
	 *
	 * @param string   $option_name Option name
	 * @param mixed    $value       Option value
	 * @param int|null $site_id     Optional. Site ID for multisite.
	 * @return bool True on success, false on failure
	 */
	public function update_option( string $option_name, $value, ?int $site_id = null ): bool {
		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();

		if ( $this->is_location_scoped_option_name( $option_name ) ) {
			$settings    = $this->get_settings_array( $site_id );
			$location_id = $this->get_location_id_from_settings( $settings );

			if ( '' !== $location_id ) {
				$option_name = TagManager::scoped_meta_key( $option_name, $location_id );
			}
		}

		return $repository->update_option( $option_name, $value, $site_id );
	}

	/**
	 * Check if multisite is enabled
	 *
	 * @return bool
	 */
	public function is_multisite(): bool {
		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
		return $repository->is_multisite();
	}

	/**
	 * Check if API connection is verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	public function is_connection_verified( ?int $site_id = null ): bool {
		$connection_manager = \Syncly\API\ConnectionManager::get_instance();
		return $connection_manager->is_connection_verified( $site_id );
	}

	/**
	 * Mark connection as verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	private function mark_connection_verified( ?int $site_id = null ): bool {
		$connection_manager = \Syncly\API\ConnectionManager::get_instance();
		return $connection_manager->mark_connection_verified( $site_id );
	}

	/**
	 * Mark connection as not verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	private function mark_connection_unverified( ?int $site_id = null ): bool {
		$connection_manager = \Syncly\API\ConnectionManager::get_instance();
		return $connection_manager->mark_connection_unverified( $site_id );
	}

	/**
	 * Get GHL fields with transient caching.
	 *
	 * Delegates to MetadataService. Kept for backward compatibility
	 * (called from field-mapping.php template).
	 *
	 * @param bool $force_refresh Whether to bypass the cache and fetch fresh data.
	 * @return array{fields: array<string, string>, fieldTypes: array<string, string>, count: int}
	 */
	public function get_syncly_fields_cached( bool $force_refresh = false ): array {
		return Settings\MetadataService::get_instance()->get_syncly_fields_cached( $force_refresh );
	}

	/**
	 * Get all sites' settings (for network admin)
	 *
	 * @return array
	 */
	public function get_all_sites_settings(): array {
		if ( ! is_multisite() ) {
			return [ get_current_blog_id() => $this->get_settings_array() ];
		}

		$sites = get_sites(
			[
				'number' => 999,
			]
		);

		$all_settings = [];

		foreach ( $sites as $site ) {
			$all_settings[ $site->blog_id ] = $this->get_settings_array( (int) $site->blog_id );
		}

		return $all_settings;
	}

	/**
	 * Handle save integrations AJAX request
	 * Delegates to AjaxHandler for business logic
	 *
	 * @return void
	 */
	public function handle_save_integrations(): void {
		// Verify nonce
		check_ajax_referer( 'syncly_admin', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to save integrations settings.', 'syncly' ),
				],
				403
			);
		}

		// Delegate to AjaxHandler
		AjaxHandler::save_integrations();
	}

	/**
	 * Handle save wizard settings AJAX request
	 * Wrapper method for AjaxHandler::save_wizard_settings()
	 *
	 * @return void
	 */
	public function handle_save_wizard_settings(): void {
		AjaxHandler::save_wizard_settings();
	}

	/**
	 * Handle get pipelines AJAX request
	 *
	 * @return void
	 */
	public function handle_get_pipelines(): void {
		// Verify nonce
		check_ajax_referer( 'syncly_admin', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'syncly' ) ], 403 );
		}

		// Delegate to AjaxHandler
		AjaxHandler::get_pipelines();
	}

	/**
	 * Handle get pipeline stages AJAX request
	 *
	 * @return void
	 */
	public function handle_get_pipeline_stages(): void {
		// Verify nonce
		check_ajax_referer( 'syncly_admin', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'syncly' ) ], 403 );
		}

		// Delegate to AjaxHandler
		AjaxHandler::get_pipeline_stages();
	}

	/**
	 * Handle search products AJAX request
	 *
	 * @return void
	 */
	public function handle_search_products(): void {
		// Verify nonce
		check_ajax_referer( 'syncly_admin', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'syncly' ) ], 403 );
		}

		// Delegate to AjaxHandler
		AjaxHandler::search_products();
	}

	/**
	 * Handle get logs AJAX request
	 *
	 * @return void
	 */
	public function handle_get_logs(): void {
		// Delegate to AjaxHandler (nonce and permissions checked there)
		AjaxHandler::get_logs();
	}

	/**
	 * Handle delete old logs AJAX request
	 *
	 * @return void
	 */
	public function handle_delete_old_logs(): void {
		// Delegate to AjaxHandler (nonce and permissions checked there)
		AjaxHandler::delete_old_logs();
	}

	/**
	 * Handle clear all logs AJAX request
	 *
	 * @return void
	 */
	public function handle_clear_all_logs(): void {
		// Delegate to AjaxHandler (nonce and permissions checked there)
		AjaxHandler::clear_all_logs();
	}

	/**
	 * Handle save logs per-page AJAX request
	 *
	 * @return void
	 */
	public function handle_save_logs_per_page(): void {
		// Delegate to AjaxHandler (nonce and permissions checked there)
		AjaxHandler::save_logs_per_page();
	}

	/**
	 * Handle bulk sync users AJAX request
	 *
	 * @return void
	 */
	public function handle_bulk_sync_users(): void {
		// Delegate to AjaxHandler (nonce and permissions checked there)
		AjaxHandler::bulk_sync_users();
	}

	/**
	 * Handle bulk import from GHL AJAX request
	 *
	 * @return void
	 */
	public function handle_bulk_import_from_ghl(): void {
		// Delegate to AjaxHandler (nonce and permissions checked there)
		AjaxHandler::bulk_import_from_ghl();
	}

	/**
	 * Handle get field suggestions AJAX request
	 *
	 * @return void
	 */
	public function handle_get_field_suggestions(): void {
		// Delegate to AjaxHandler (nonce and permissions checked there)
		AjaxHandler::get_field_suggestions();
	}

	/**
	 * Preview user sync (dry-run)
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function preview_user_sync(): void {
		check_ajax_referer( 'syncly_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'You do not have permission to preview syncs.', 'syncly' ) ],
				403
			);
		}

		$user_identifier = isset( $_POST['user_identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['user_identifier'] ) ) : '';

		if ( empty( $user_identifier ) ) {
			wp_send_json_error( [ 'message' => __( 'Please provide a username or email.', 'syncly' ) ] );
		}

		// Try to find user by email or username
		$user = get_user_by( 'email', $user_identifier );
		if ( ! $user ) {
			$user = get_user_by( 'login', $user_identifier );
		}

		if ( ! $user ) {
			wp_send_json_error( [ 'message' => __( 'User not found.', 'syncly' ) ] );
		}

		/**
		 * Delegate sync preview to Pro add-on.
		 *
		 * @since 1.0.0
		 *
		 * @param array|null $preview_data Null by default; Pro returns preview data array.
		 * @param int        $user_id      WordPress user ID to preview.
		 */
		$preview_data = apply_filters( 'syncly_preview_user_sync_result', null, $user->ID );

		if ( null !== $preview_data ) {
			if ( isset( $preview_data['error'] ) ) {
				wp_send_json_error( [ 'message' => $preview_data['error'] ] );
			}
			wp_send_json_success( $preview_data );
		}

		wp_send_json_success(
			[
				'action'          => 'update',
				'user_name'       => $user->display_name,
				'user_email'      => $user->user_email,
				'fields_to_sync'  => [
					[ 'ghl_field' => 'email', 'wp_value' => $user->user_email, 'ghl_value' => '', 'status' => 'preview' ],
					[ 'ghl_field' => 'first_name', 'wp_value' => get_user_meta( $user->ID, 'first_name', true ), 'ghl_value' => '', 'status' => 'preview' ],
					[ 'ghl_field' => 'last_name', 'wp_value' => get_user_meta( $user->ID, 'last_name', true ), 'ghl_value' => '', 'status' => 'preview' ],
				],
				'fields_changed' => [],
				'tags_to_add'    => [],
				'warnings'       => [ __( 'Preview generated locally without writing data to GoHighLevel.', 'syncly' ) ],
			]
		);
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Get location-specific role tags configuration
	 *
	 * @param string|null $location_id Optional location ID. Uses current location if not provided.
	 * @return array Role tags configuration for the location
	 */
	public function get_location_role_tags( ?string $location_id = null ): array {
		if ( null === $location_id ) {
			$location_id = $this->get_setting( 'location_id' ) ?: $this->get_setting( 'oauth_location_id' );
		}

		if ( empty( $location_id ) ) {
			return [];
		}

		$settings = $this->get_settings_array();
		$key      = "role_tags_{$location_id}";
		$value    = $settings[ $key ] ?? ( $settings['role_tags'] ?? [] );

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Get location-specific global tags configuration
	 *
	 * @param string|null $location_id Optional location ID. Uses current location if not provided.
	 * @return array Global tags for the location
	 */
	public function get_location_global_tags( ?string $location_id = null ): array {
		if ( null === $location_id ) {
			$location_id = $this->get_setting( 'location_id' ) ?: $this->get_setting( 'oauth_location_id' );
		}

		if ( empty( $location_id ) ) {
			return [];
		}

		$settings = $this->get_settings_array();
		$key      = "global_tags_{$location_id}";
		$value    = $settings[ $key ] ?? ( $settings['global_tags'] ?? [] );

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Get location-specific registration tags configuration
	 *
	 * @param string|null $location_id Optional location ID. Uses current location if not provided.
	 * @return array Registration tags for the location
	 */
	public function get_location_register_tags( ?string $location_id = null ): array {
		if ( null === $location_id ) {
			$location_id = $this->get_setting( 'location_id' ) ?: $this->get_setting( 'oauth_location_id' );
		}

		if ( empty( $location_id ) ) {
			return [];
		}

		$settings = $this->get_settings_array();
		$key      = "user_register_tags_{$location_id}";
		$value    = $settings[ $key ] ?? ( $settings['user_register_tags'] ?? [] );

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Save location-specific tag configuration
	 *
	 * @param string $tag_type Tag type (role_tags, global_tags, user_register_tags)
	 * @param mixed  $value    Tag configuration value
	 * @return array|null      Array with ['key' => string, 'value' => mixed] or null when no location
	 */
	private function save_location_specific_tags( string $tag_type, $value ): ?array {
		$location_id = $this->get_setting( 'location_id' ) ?: $this->get_setting( 'oauth_location_id' );

		if ( empty( $location_id ) ) {
			return null;
		}

		$key = "{$tag_type}_{$location_id}";

		// Sanitize based on tag type
		if ( 'role_tags' === $tag_type && is_array( $value ) ) {
			$sanitized_value = $this->sanitize_role_tags( $value );
		} elseif ( is_array( $value ) ) {
			$sanitized_value = $this->sanitize_array_recursive( $value );
		} else {
			$sanitized_value = sanitize_text_field( (string) $value );
		}

		return [
			'key'   => $key,
			'value' => $sanitized_value,
		];
	}

	/**
	 * Purge all location-specific caches.
	 *
	 * Called automatically when location_id changes (via settings save or connection status change)
	 * to ensure the UI always reflects the current location's data.
	 *
	 * @return void
	 */
	public function purge_location_caches(): void {
		global $wpdb;
		$site_id = get_current_blog_id();

		// 1. Delete ALL tag transients for this site (covers old + new location)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing plugin transient rows directly.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE (option_name LIKE %s OR option_name LIKE %s)
				AND option_name LIKE %s",
				$wpdb->esc_like( '_transient_syncly_tags_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_syncly_tags_' ) . '%',
				'%' . $wpdb->esc_like( '_site_' . (string) $site_id )
			)
		);

		// 2. Delete contact cache transients (contain location ID in the hash key)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing plugin transient rows directly.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_ghl_contact_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ghl_contact_' ) . '%'
			)
		);

		// 3. Clear WordPress object cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		// 4. Reset TagManager in-memory cache if it's already instantiated
		if ( class_exists( '\Syncly\Sync\TagManager' ) ) {
			try {
				$tag_manager = TagManager::get_instance();
				$tag_manager->refresh_cache();
			} catch ( \Throwable $e ) {
				// TagManager may not be fully initialized yet during early hooks — safe to ignore.
			}
		}
	}

	/**
	 * Prevent unserializing
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
