<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager class
 *
 * Handles settings storage and retrieval via AJAX.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core
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
	private const OPTION_NAME = 'ghl_crm_settings';

	/**
	 * Network-wide settings option name
	 *
	 * @var string
	 */
	private const NETWORK_OPTION_NAME = 'ghl_crm_network_settings';

	/**
	 * Connection verification option name
	 *
	 * @var string
	 */
	private const VERIFICATION_OPTION_NAME = 'ghl_crm_connection_verified';

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
		// Register AJAX handlers
		add_action( 'wp_ajax_ghl_crm_save_settings', [ $this, 'save_settings' ] );
		add_action( 'wp_ajax_ghl_crm_get_settings', [ $this, 'get_settings' ] );
		add_action( 'wp_ajax_ghl_crm_test_connection', [ $this, 'test_connection' ] );
		add_action( 'wp_ajax_ghl_crm_save_field_mapping', [ $this, 'save_field_mapping' ] );
		add_action( 'wp_ajax_ghl_crm_get_tags', [ $this, 'get_tags' ] );
		add_action( 'wp_ajax_ghl_crm_get_custom_fields', [ $this, 'get_custom_fields' ] );
		add_action( 'wp_ajax_ghl_crm_manual_queue_trigger', [ $this, 'manual_queue_trigger' ] );
		add_action( 'wp_ajax_ghl_crm_clear_cache', [ $this, 'clear_cache' ] );
		add_action( 'wp_ajax_ghl_crm_reset_settings', [ $this, 'reset_settings' ] );
		add_action( 'wp_ajax_ghl_crm_system_health_check', [ $this, 'system_health_check' ] );
	}

	/**
	 * Save settings via AJAX
	 * Universal handler for all settings tabs
	 *
	 * @return void
	 */
	public function save_settings(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to save settings.', 'ghl-crm-integration' ),
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

			// Sanitize based on value type
			if ( is_array( $value ) ) {
				// Special handling for role_tags nested structure
				if ( $key === 'role_tags' ) {
					$new_settings[ $key ] = $this->sanitize_role_tags( $value );
				} else {
					// Handle other arrays (checkboxes, multi-selects, etc.)
					$new_settings[ $key ] = $this->sanitize_array_recursive( $value );
				}
			} else {
				// Check if this is an empty array marker from JavaScript
				if ( $value === '__EMPTY_ARRAY__' ) {
					$new_settings[ $key ] = [];
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

		// Validate critical fields only if user is actively trying to set up manual API connection
		// Don't validate on imports or when only location_id is present
		$is_setting_manual_api = isset( $new_settings['api_token'] ) && ! empty( $new_settings['api_token'] );
		
		if ( $is_setting_manual_api ) {
			// User is trying to set manual API token - require location_id too
			if ( empty( $settings['location_id'] ) ) {
				wp_send_json_error(
					[
						'message' => __( 'Location ID is required when setting API Token.', 'ghl-crm-integration' ),
					],
					400
				);
			}
		}

		// Save settings (multisite aware)
		$saved = $this->save_site_settings( $settings );

		// If credentials changed, invalidate verification
		if ( $credentials_changed && $saved ) {
			$this->mark_connection_unverified();
		}

		if ( $saved ) {
			$response_data = [
				'message'  => __( 'Settings saved successfully!', 'ghl-crm-integration' ),
				'settings' => $this->get_settings_array(),
			];

			// Add warning if credentials changed
			if ( $credentials_changed ) {
				$response_data['warning'] = __( 'API credentials changed. Please test your connection to verify.', 'ghl-crm-integration' );
			}

			wp_send_json_success( $response_data );
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Failed to save settings. Please try again.', 'ghl-crm-integration' ),
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
		$current_settings = $this->get_settings_array();
		
		// Merge new settings with current settings
		$settings = array_merge(
			$current_settings,
			$new_settings,
			[
				'updated_at' => current_time( 'mysql' ),
				'site_id'    => get_current_blog_id(),
			]
		);

		// Validate API credentials if they're being set (but allow clearing for disconnect)
		if ( isset( $new_settings['api_token'] ) || isset( $new_settings['location_id'] ) ) {
			// Only validate if at least one is not empty (i.e., user is trying to set credentials)
			$is_setting_credentials = ! empty( $new_settings['api_token'] ) || ! empty( $new_settings['location_id'] );
			
			if ( $is_setting_credentials && ( empty( $settings['api_token'] ) || empty( $settings['location_id'] ) ) ) {
				return [
					'success' => false,
					'message' => __( 'API Token and Location ID are required.', 'ghl-crm-integration' ),
				];
			}
		}

		// Save settings (multisite aware)
		$saved = $this->save_site_settings( $settings );

		if ( $saved ) {
			// Mark connection as unverified if credentials changed
			if ( isset( $new_settings['api_token'] ) || isset( $new_settings['location_id'] ) ) {
				$this->mark_connection_unverified();
			}

			return [
				'success' => true,
				'message' => __( 'Settings saved successfully!', 'ghl-crm-integration' ),
			];
		}

		return [
			'success' => false,
			'message' => __( 'Failed to save settings. Please try again.', 'ghl-crm-integration' ),
		];
	}

	/**
	 * Get settings via AJAX
	 *
	 * @return void
	 */
	public function get_settings(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to view settings.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		wp_send_json_success(
			[
				'settings' => $this->get_settings_array(),
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
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to test connection.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		// Get settings
		$settings = $this->get_settings_array();

		if ( empty( $settings['api_token'] ) || empty( $settings['location_id'] ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Please save your API credentials first.', 'ghl-crm-integration' ),
				],
				400
			);
		}

		// Test the connection
		$api_url = 'https://services.leadconnectorhq.com/locations/' . $settings['location_id'];

		$response = wp_remote_get(
			$api_url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $settings['api_token'],
					'Version'       => $settings['api_version'],
					'Content-Type'  => 'application/json',
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'Connection failed: %s', 'ghl-crm-integration' ),
						$response->get_error_message()
					),
				],
				500
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code === 200 ) {
			// Mark connection as verified
			$this->mark_connection_verified();

			wp_send_json_success(
				[
					'message'       => __( 'Connection successful! Your API credentials are working.', 'ghl-crm-integration' ),
					'location_name' => isset( $body['location']['name'] ) ? $body['location']['name'] : '',
					'status_code'   => $status_code,
				]
			);
		} else {
			// Mark connection as not verified
			$this->mark_connection_unverified();

			wp_send_json_error(
				[
					'message'     => sprintf(
						/* translators: %d: HTTP status code */
						__( 'Connection failed with status code: %d', 'ghl-crm-integration' ),
						$status_code
					),
					'details'     => $body,
					'status_code' => $status_code,
				],
				$status_code
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
		check_ajax_referer( 'ghl_crm_field_mapping_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to save field mapping.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		// Get field mapping data from POST
		$field_mappings = isset( $_POST['field_mappings'] ) && is_array( $_POST['field_mappings'] )
			? $_POST['field_mappings']
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

		// Save settings
		$saved = $this->save_site_settings( $current_settings );

		if ( $saved ) {
			wp_send_json_success(
				[
					'message' => __( 'Field mapping saved successfully!', 'ghl-crm-integration' ),
					'count'   => count( $sanitized_mappings ),
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Failed to save field mapping. Please try again.', 'ghl-crm-integration' ),
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
			$sanitized_config['auto_apply'] = isset( $config['auto_apply'] ) && $config['auto_apply'] === '1';
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
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$settings = get_option( self::OPTION_NAME, [] );
			restore_current_blog();
		} else {
			$settings = get_option( self::OPTION_NAME, [] );
		}

		// Return with defaults
		return wp_parse_args(
			$settings,
			[
				'api_token'                     => '',
				'location_id'                   => '',
				'api_version'                   => '2021-07-28',
				'oauth_access_token'            => '',
				'oauth_refresh_token'           => '',
				'oauth_expires_at'              => 0,
				'oauth_connected_at'            => '',
				'enable_user_sync'              => false,
				'user_sync_actions'             => [],
				'delete_contact_on_user_delete' => false,
				'user_field_mapping'            => [],
				'updated_at'                    => '',
				'site_id'                       => get_current_blog_id(),
			]
		);
	}

	/**
	 * Save site settings (multisite aware)
	 *
	 * @param array    $settings Settings array.
	 * @param int|null $site_id  Optional. Site ID for multisite.
	 * @return bool
	 */
	private function save_site_settings( array $settings, ?int $site_id = null ): bool {
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$saved = update_option( self::OPTION_NAME, $settings, true );
			restore_current_blog();
			return $saved;
		}

		return update_option( self::OPTION_NAME, $settings, true );
	}

	/**
	 * Get network-wide settings
	 *
	 * @return array
	 */
	public function get_network_settings(): array {
		if ( ! is_multisite() ) {
			return [];
		}

		$settings = get_site_option( self::NETWORK_OPTION_NAME, [] );

		return wp_parse_args(
			$settings,
			[
				'enable_network_wide' => false,
				'default_api_version' => '2021-07-28',
			]
		);
	}

	/**
	 * Save network-wide settings
	 *
	 * @param array $settings Network settings array.
	 * @return bool
	 */
	public function save_network_settings( array $settings ): bool {
		if ( ! is_multisite() ) {
			return false;
		}

		return update_site_option( self::NETWORK_OPTION_NAME, $settings );
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
		$settings = $this->get_settings_array( $site_id );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Check if multisite is enabled
	 *
	 * @return bool
	 */
	public function is_multisite(): bool {
		return is_multisite();
	}

	/**
	 * Check if API connection is verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	public function is_connection_verified( ?int $site_id = null ): bool {
		// Get current settings
		$settings = $this->get_settings_array( $site_id );

		// Check if basic credentials exist
		if ( empty( $settings['api_token'] ) || empty( $settings['location_id'] ) ) {
			return false;
		}

		// Check verification status
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$verified = get_option( self::VERIFICATION_OPTION_NAME, false );
			restore_current_blog();
			return (bool) $verified;
		}

		return (bool) get_option( self::VERIFICATION_OPTION_NAME, false );
	}

	/**
	 * Mark connection as verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	private function mark_connection_verified( ?int $site_id = null ): bool {
		$verification_data = [
			'verified'    => true,
			'verified_at' => current_time( 'mysql' ),
			'site_id'     => $site_id ?? get_current_blog_id(),
		];

		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$saved = update_option( self::VERIFICATION_OPTION_NAME, $verification_data, true );
			restore_current_blog();
			return $saved;
		}

		return update_option( self::VERIFICATION_OPTION_NAME, $verification_data, true );
	}

	/**
	 * Mark connection as not verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	private function mark_connection_unverified( ?int $site_id = null ): bool {
		if ( is_multisite() && null !== $site_id && $site_id !== get_current_blog_id() ) {
			switch_to_blog( $site_id );
			$deleted = delete_option( self::VERIFICATION_OPTION_NAME );
			restore_current_blog();
			return $deleted;
		}

		return delete_option( self::VERIFICATION_OPTION_NAME );
	}

	/**
	 * Get tags from GoHighLevel location
	 * AJAX handler
	 *
	 * @return void
	 */
	public function get_tags(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access tags.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		try {
			$client      = \GHL_CRM\API\Client\Client::get_instance();
			$settings    = $this->get_settings_array();
			$location_id = $settings['location_id'] ?? '';

			if ( empty( $location_id ) ) {
				wp_send_json_error(
					[
						'message' => __( 'No location ID configured. Please connect to GoHighLevel first.', 'ghl-crm-integration' ),
					],
					400
				);
				return;
			}

			// Fetch tags from GoHighLevel
			$response = $client->get( 'locations/' . $location_id . '/tags' );

			if ( isset( $response['tags'] ) && is_array( $response['tags'] ) ) {
				wp_send_json_success(
					[
						'tags'    => $response['tags'],
						'message' => __( 'Tags loaded successfully.', 'ghl-crm-integration' ),
					]
				);
			} else {
				wp_send_json_success(
					[
						'tags'    => [],
						'message' => __( 'No tags found in this location.', 'ghl-crm-integration' ),
					]
				);
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to fetch tags: %s', 'ghl-crm-integration' ),
						$e->getMessage()
					),
				],
				500
			);
		}
	}

	/**
	 * Get custom fields from GoHighLevel location
	 *
	 * @return void
	 */
	public function get_custom_fields(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_field_mapping_nonce', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this data.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		try {
			$settings = $this->get_settings_array();
			$location_id = $settings['location_id'] ?? '';

			if ( empty( $location_id ) ) {
				wp_send_json_error(
					[
						'message' => __( 'Location ID not configured. Please save your settings first.', 'ghl-crm-integration' ),
					],
					400
				);
			}

			$client = \GHL_CRM\API\Client\Client::get_instance();
			
			// Fetch custom fields from GHL API
			// Endpoint: GET /locations/{locationId}/customFields (no hyphen)
			// Pass empty array to prevent auto-adding locationId as query param
			$response = $client->get( 'locations/' . $location_id . '/customFields', [] );

			if ( empty( $response['customFields'] ) ) {
				// Return standard fields if no custom fields found
				wp_send_json_success(
					[
						'fields' => $this->get_standard_ghl_fields(),
						'message' => __( 'No custom fields found. Showing standard fields only.', 'ghl-crm-integration' ),
					]
				);
				return;
			}

			// Combine standard fields + custom fields
			$all_fields = $this->get_standard_ghl_fields();
			
			foreach ( $response['customFields'] as $field ) {
				$field_id = $field['id'] ?? '';
				$field_name = $field['name'] ?? '';
				
				if ( $field_id && $field_name ) {
					$all_fields[ 'custom.' . $field_id ] = $field_name . ' (Custom)';
				}
			}

			wp_send_json_success(
				[
					'fields' => $all_fields,
					'count' => count( $response['customFields'] ),
				]
			);

		} catch ( \Exception $e ) {
			error_log( 'GHL CRM: Failed to fetch custom fields - ' . $e->getMessage() );
			error_log( 'GHL CRM: Exception trace: ' . $e->getTraceAsString() );
			
			// Get detailed debug info
			$debug_info = [
				'exception_message' => $e->getMessage(),
				'exception_file' => $e->getFile(),
				'exception_line' => $e->getLine(),
				'location_id' => $location_id ?? 'not set',
				'has_oauth' => ! empty( $settings['oauth_access_token'] ),
				'has_api_token' => ! empty( $settings['api_token'] ),
				'endpoint' => 'locations/' . ( $location_id ?? '{locationId}' ) . '/customFields',
			];			error_log( 'GHL CRM: Debug info: ' . print_r( $debug_info, true ) );
			
			// Fallback to standard fields
			wp_send_json_success(
				[
					'fields' => $this->get_standard_ghl_fields(),
					'message' => __( 'Could not fetch custom fields. Showing standard fields only.', 'ghl-crm-integration' ),
					'error' => $e->getMessage(),
					'debug' => $debug_info,
				]
			);
		}
	}

	/**
	 * Get standard GoHighLevel contact fields
	 *
	 * @return array Standard field mappings
	 */
	private function get_standard_ghl_fields(): array {
		return [
			''            => __( '— Do Not Sync —', 'ghl-crm-integration' ),
			'firstName'   => __( 'First Name', 'ghl-crm-integration' ),
			'lastName'    => __( 'Last Name', 'ghl-crm-integration' ),
			'name'        => __( 'Full Name', 'ghl-crm-integration' ),
			'email'       => __( 'Email', 'ghl-crm-integration' ),
			'phone'       => __( 'Phone', 'ghl-crm-integration' ),
			'address1'    => __( 'Address Line 1', 'ghl-crm-integration' ),
			'city'        => __( 'City', 'ghl-crm-integration' ),
			'state'       => __( 'State', 'ghl-crm-integration' ),
			'country'     => __( 'Country', 'ghl-crm-integration' ),
			'postalCode'  => __( 'Postal Code', 'ghl-crm-integration' ),
			'website'     => __( 'Website', 'ghl-crm-integration' ),
			'timezone'    => __( 'Timezone', 'ghl-crm-integration' ),
			'companyName' => __( 'Company Name', 'ghl-crm-integration' ),
			'source'      => __( 'Source', 'ghl-crm-integration' ),
			'dateOfBirth' => __( 'Date of Birth', 'ghl-crm-integration' ),
		];
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
	 * Manual queue trigger AJAX handler
	 *
	 * @return void
	 */
	public function manual_queue_trigger(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_manual_queue', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to perform this action.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		error_log( '🔧 GHL CRM: Manual queue trigger initiated by user' );

		try {
			// Get queue manager
			$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();

			// Get count before processing
			global $wpdb;
			$table_name      = $wpdb->prefix . 'ghl_sync_queue';
			$current_site_id = get_current_blog_id();
			
			$pending_before = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' AND site_id = %d",
					$current_site_id
				)
			);

			error_log( "📊 GHL CRM: Pending items before processing: {$pending_before}" );

			// Manually trigger queue processing
			$queue_manager->process_queue();

			// Get count after processing
			$pending_after = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' AND site_id = %d",
					$current_site_id
				)
			);

			$processed = $pending_before - $pending_after;

			error_log( "✅ GHL CRM: Manual trigger complete. Processed: {$processed}, Remaining: {$pending_after}" );

			wp_send_json_success(
				[
					'message'   => sprintf(
						/* translators: %d: number of items processed */
						__( 'Queue processed successfully. Processed %d items.', 'ghl-crm-integration' ),
						$processed
					),
					'processed' => $processed,
					'remaining' => $pending_after,
					'before'    => $pending_before,
				]
			);

		} catch ( \Exception $e ) {
			error_log( '❌ GHL CRM: Manual queue trigger error: ' . $e->getMessage() );
			error_log( 'Stack trace: ' . $e->getTraceAsString() );

			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to process queue: %s', 'ghl-crm-integration' ),
						$e->getMessage()
					),
				],
				500
			);
		} catch ( \Error $err ) {
			error_log( '❌ GHL CRM: Manual queue trigger fatal error: ' . $err->getMessage() );
			error_log( 'Stack trace: ' . $err->getTraceAsString() );

			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'A fatal error occurred while processing the queue: %s', 'ghl-crm-integration' ),
						$err->getMessage()
					),
				],
				500
			);
		} catch ( \Throwable $throwable ) {
			error_log( '❌ GHL CRM: Manual queue trigger throwable: ' . $throwable->getMessage() );
			error_log( 'Stack trace: ' . $throwable->getTraceAsString() );

			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'An unexpected error occurred while processing the queue: %s', 'ghl-crm-integration' ),
						$throwable->getMessage()
					),
				],
				500
			);
		}
	}

	/**
	 * Clear cache via AJAX
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to clear cache.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		// Clear all GHL CRM transients
		global $wpdb;
		$site_id = get_current_blog_id();
		
		// Delete contact cache transients
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_ghl_contact_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ghl_contact_' ) . '%'
			)
		);

		// Delete rate limit transients
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_ghl_rate_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ghl_rate_' ) . '%'
			)
		);

		// Clear object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		wp_send_json_success(
			[
				'message' => __( 'Cache cleared successfully!', 'ghl-crm-integration' ),
			]
		);
	}

	/**
	 * Reset settings to defaults via AJAX
	 * Preserves OAuth connection and manual API connection credentials
	 *
	 * @return void
	 */
	public function reset_settings(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to reset settings.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		// Get current settings to preserve connection credentials
		$current_settings = $this->get_settings_array();
		
		// Preserve ALL connection-related settings (OAuth + Manual API)
		$preserved_credentials = [
			// OAuth credentials
			'oauth_access_token'   => $current_settings['oauth_access_token'] ?? '',
			'oauth_refresh_token'  => $current_settings['oauth_refresh_token'] ?? '',
			'oauth_expires_at'     => $current_settings['oauth_expires_at'] ?? '',
			'oauth_token_type'     => $current_settings['oauth_token_type'] ?? '',
			'oauth_location_id'    => $current_settings['oauth_location_id'] ?? '',
			'oauth_company_id'     => $current_settings['oauth_company_id'] ?? '',
			'oauth_user_type'      => $current_settings['oauth_user_type'] ?? '',
			'oauth_connected_at'   => $current_settings['oauth_connected_at'] ?? '',
			// Manual API connection credentials
			'api_token'            => $current_settings['api_token'] ?? '',
			'location_id'          => $current_settings['location_id'] ?? '',
			'api_version'          => $current_settings['api_version'] ?? '2021-07-28',
		];

		// Default settings (everything except credentials)
		$default_settings = [
			'cache_duration'                => 3600,
			'batch_size'                    => 50,
			'log_retention_days'            => 30,
			'enable_user_sync'              => false,
			'user_sync_actions'             => [],
			'delete_contact_on_user_delete' => false,
			'user_field_mapping'            => [],
			'role_tags'                     => [],
			'restrictions_enabled'          => true,
			'updated_at'                    => current_time( 'mysql' ),
			'site_id'                       => get_current_blog_id(),
		];

		// Merge: defaults first, then preserved credentials (credentials take priority)
		$settings = array_merge( $default_settings, $preserved_credentials );

		// Use multisite-aware save method instead of direct update_option
		$saved = $this->save_site_settings( $settings );

		if ( $saved ) {
			wp_send_json_success(
				[
					'message'  => __( 'Settings reset to defaults successfully! Your API connection has been preserved.', 'ghl-crm-integration' ),
					'settings' => $settings,
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Failed to reset settings. Please try again.', 'ghl-crm-integration' ),
				],
				500
			);
		}
	}

	/**
	 * System health check via AJAX
	 * Runs comprehensive diagnostics on the plugin and server environment
	 *
	 * @return void
	 */
	public function system_health_check(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to run system diagnostics.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		$checks = [];

		// 1. Check WordPress Environment
		$checks['wordpress'] = [
			'label'  => __( 'WordPress Environment', 'ghl-crm-integration' ),
			'status' => 'success',
			'items'  => [
				[
					'label'  => __( 'WordPress Version', 'ghl-crm-integration' ),
					'value'  => get_bloginfo( 'version' ),
					'status' => version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ? 'success' : 'warning',
				],
				[
					'label'  => __( 'PHP Version', 'ghl-crm-integration' ),
					'value'  => PHP_VERSION,
					'status' => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'success' : 'error',
				],
				[
					'label'  => __( 'Multisite', 'ghl-crm-integration' ),
					'value'  => is_multisite() ? __( 'Yes', 'ghl-crm-integration' ) : __( 'No', 'ghl-crm-integration' ),
					'status' => 'success',
				],
			],
		];

		// 2. Check Database Tables
		global $wpdb;
		$table_prefix      = $wpdb->prefix;
		$required_tables   = [
			'ghl_sync_queue',
			'ghl_sync_log',
		];
		$tables_status     = [];
		$tables_all_exist  = true;

		foreach ( $required_tables as $table ) {
			$table_name  = $table_prefix . $table;
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
			
			$tables_status[] = [
				'label'  => $table_name,
				'value'  => $table_exists ? __( 'Exists', 'ghl-crm-integration' ) : __( 'Missing', 'ghl-crm-integration' ),
				'status' => $table_exists ? 'success' : 'error',
			];

			if ( ! $table_exists ) {
				$tables_all_exist = false;
			}
		}

		$checks['database'] = [
			'label'  => __( 'Database Tables', 'ghl-crm-integration' ),
			'status' => $tables_all_exist ? 'success' : 'error',
			'items'  => $tables_status,
		];

		// 3. Check API Connection
		$settings         = $this->get_settings_array();
		$has_oauth        = ! empty( $settings['oauth_access_token'] );
		$has_manual_api   = ! empty( $settings['api_token'] ) && ! empty( $settings['location_id'] );
		$has_any_connection = $has_oauth || $has_manual_api;
		$is_verified      = $this->is_connection_verified();

		$checks['api_connection'] = [
			'label'  => __( 'API Connection', 'ghl-crm-integration' ),
			'status' => $has_any_connection ? ( $is_verified ? 'success' : 'warning' ) : 'error',
			'items'  => [
				[
					'label'  => __( 'Connection Type', 'ghl-crm-integration' ),
					'value'  => $has_oauth ? __( 'OAuth', 'ghl-crm-integration' ) : ( $has_manual_api ? __( 'Manual API', 'ghl-crm-integration' ) : __( 'Not Connected', 'ghl-crm-integration' ) ),
					'status' => $has_any_connection ? 'success' : 'error',
				],
				[
					'label'  => __( 'Connection Verified', 'ghl-crm-integration' ),
					'value'  => $is_verified ? __( 'Yes', 'ghl-crm-integration' ) : __( 'No', 'ghl-crm-integration' ),
					'status' => $is_verified ? 'success' : 'warning',
				],
				[
					'label'  => __( 'Location ID', 'ghl-crm-integration' ),
					'value'  => ! empty( $settings['location_id'] ) ? substr( $settings['location_id'], 0, 10 ) . '...' : __( 'Not Set', 'ghl-crm-integration' ),
					'status' => ! empty( $settings['location_id'] ) ? 'success' : 'error',
				],
			],
		];

		// 4. Check PHP Extensions
		$required_extensions = [
			'curl'    => __( 'cURL', 'ghl-crm-integration' ),
			'json'    => __( 'JSON', 'ghl-crm-integration' ),
			'mbstring' => __( 'Multibyte String', 'ghl-crm-integration' ),
		];
		$extensions_status   = [];
		$all_extensions_ok   = true;

		foreach ( $required_extensions as $ext => $label ) {
			$loaded = extension_loaded( $ext );
			$extensions_status[] = [
				'label'  => $label,
				'value'  => $loaded ? __( 'Loaded', 'ghl-crm-integration' ) : __( 'Missing', 'ghl-crm-integration' ),
				'status' => $loaded ? 'success' : 'error',
			];

			if ( ! $loaded ) {
				$all_extensions_ok = false;
			}
		}

		$checks['php_extensions'] = [
			'label'  => __( 'PHP Extensions', 'ghl-crm-integration' ),
			'status' => $all_extensions_ok ? 'success' : 'error',
			'items'  => $extensions_status,
		];

		// 5. Check Queue Status
		$queue_table    = $wpdb->prefix . 'ghl_sync_queue';
		$current_site_id = get_current_blog_id();
		
		$pending_count  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE status = 'pending' AND site_id = %d",
				$current_site_id
			)
		);
		
		$failed_count   = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE status = 'failed' AND site_id = %d",
				$current_site_id
			)
		);
		
		$processing_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE status = 'processing' AND site_id = %d",
				$current_site_id
			)
		);

		$checks['sync_queue'] = [
			'label'  => __( 'Sync Queue', 'ghl-crm-integration' ),
			'status' => $failed_count > 10 ? 'warning' : 'success',
			'items'  => [
				[
					'label'  => __( 'Pending Items', 'ghl-crm-integration' ),
					'value'  => $pending_count,
					'status' => 'success',
				],
				[
					'label'  => __( 'Processing Items', 'ghl-crm-integration' ),
					'value'  => $processing_count,
					'status' => $processing_count > 0 ? 'success' : 'info',
				],
				[
					'label'  => __( 'Failed Items', 'ghl-crm-integration' ),
					'value'  => $failed_count,
					'status' => $failed_count > 10 ? 'warning' : ( $failed_count > 0 ? 'info' : 'success' ),
				],
			],
		];

		// 6. Check File Permissions
		$upload_dir      = wp_upload_dir();
		$upload_writable = wp_is_writable( $upload_dir['basedir'] );
		$plugin_dir      = GHL_CRM_PATH;
		$plugin_readable = is_readable( $plugin_dir );

		$checks['file_permissions'] = [
			'label'  => __( 'File Permissions', 'ghl-crm-integration' ),
			'status' => ( $upload_writable && $plugin_readable ) ? 'success' : 'warning',
			'items'  => [
				[
					'label'  => __( 'Upload Directory', 'ghl-crm-integration' ),
					'value'  => $upload_writable ? __( 'Writable', 'ghl-crm-integration' ) : __( 'Not Writable', 'ghl-crm-integration' ),
					'status' => $upload_writable ? 'success' : 'error',
				],
				[
					'label'  => __( 'Plugin Directory', 'ghl-crm-integration' ),
					'value'  => $plugin_readable ? __( 'Readable', 'ghl-crm-integration' ) : __( 'Not Readable', 'ghl-crm-integration' ),
					'status' => $plugin_readable ? 'success' : 'error',
				],
			],
		];

		// 7. Check Memory & Performance
		$memory_limit       = ini_get( 'memory_limit' );
		$max_execution_time = ini_get( 'max_execution_time' );
		
		$checks['performance'] = [
			'label'  => __( 'Performance Settings', 'ghl-crm-integration' ),
			'status' => 'success',
			'items'  => [
				[
					'label'  => __( 'PHP Memory Limit', 'ghl-crm-integration' ),
					'value'  => $memory_limit,
					'status' => 'info',
				],
				[
					'label'  => __( 'Max Execution Time', 'ghl-crm-integration' ),
					'value'  => $max_execution_time . 's',
					'status' => 'info',
				],
				[
					'label'  => __( 'Cache Duration', 'ghl-crm-integration' ),
					'value'  => $settings['cache_duration'] . 's',
					'status' => 'info',
				],
				[
					'label'  => __( 'Batch Size', 'ghl-crm-integration' ),
					'value'  => $settings['batch_size'],
					'status' => 'info',
				],
			],
		];

		// Calculate overall status
		$overall_status = 'success';
		foreach ( $checks as $check ) {
			if ( $check['status'] === 'error' ) {
				$overall_status = 'error';
				break;
			} elseif ( $check['status'] === 'warning' && $overall_status !== 'error' ) {
				$overall_status = 'warning';
			}
		}

		wp_send_json_success(
			[
				'overall_status' => $overall_status,
				'checks'         => $checks,
				'timestamp'      => current_time( 'mysql' ),
				'message'        => $overall_status === 'success' 
					? __( 'All system checks passed!', 'ghl-crm-integration' )
					: ( $overall_status === 'warning' 
						? __( 'System checks passed with warnings.', 'ghl-crm-integration' )
						: __( 'Some system checks failed. Please review the details.', 'ghl-crm-integration' ) ),
			]
		);
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
