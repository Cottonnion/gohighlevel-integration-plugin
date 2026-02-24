<?php
/**
 * Connection Manager
 *
 * Handles GoHighLevel API connection management, testing, and validation
 *
 * @package GHL_CRM_Integration
 */

namespace GHL_CRM\API;

use GHL_CRM\Core\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class ConnectionManager
 */
class ConnectionManager {

	/**
	 * Singleton instance
	 *
	 * @var ConnectionManager|null
	 */
	private static $instance = null;

	/**
	 * Settings repository instance
	 *
	 * @var SettingsRepository
	 */
	private $settings_repository;

	/**
	 * Private constructor
	 */
	private function __construct() {
		$this->settings_repository = SettingsRepository::get_instance();
	}

	/**
	 * Get singleton instance
	 *
	 * @return ConnectionManager
	 */
	public static function get_instance(): ConnectionManager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Save manual connection settings
	 *
	 * @param array $new_settings New settings to save.
	 * @return array Result with success status and message.
	 */
	public function save_manual_connection_settings( array $new_settings ): array {
		$current_settings = $this->settings_repository->get_settings_array();

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
		$saved = $this->settings_repository->save_site_settings( $settings );

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
	 * Test API connection
	 *
	 * @return array Result with success status, message, and details.
	 */
	public function test_connection(): array {
		$settings = $this->settings_repository->get_settings_array();

		// Check for OAuth token OR manual API token
		$has_oauth = ! empty( $settings['oauth_access_token'] );
		$has_manual = ! empty( $settings['api_token'] );
		
		if ( ! $has_oauth && ! $has_manual ) {
			return [
				'success' => false,
				'message' => __( 'Please connect your GoHighLevel account first.', 'ghl-crm-integration' ),
				'code'    => 400,
			];
		}

		// Use OAuth token if available, otherwise use manual token
		$auth_token = $has_oauth ? $settings['oauth_access_token'] : $settings['api_token'];
		
		// For OAuth, location_id might be stored differently
		$location_id = $settings['location_id'] ?? '';
		
		if ( empty( $location_id ) ) {
			return [
				'success' => false,
				'message' => __( 'Location ID not found. Please reconnect your account.', 'ghl-crm-integration' ),
				'code'    => 400,
			];
		}

		// Test the connection by fetching contacts (simple scope test)
		$api_url = 'https://services.leadconnectorhq.com/contacts/?locationId=' . $location_id . '&limit=1';

		$response = wp_remote_get(
			$api_url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $auth_token,
					'Version'       => $settings['api_version'] ?? '2021-07-28',
					'Content-Type'  => 'application/json',
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Connection failed: %s', 'ghl-crm-integration' ),
					$response->get_error_message()
				),
				'code'    => 500,
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code === 200 ) {
			// Mark connection as verified
			$this->mark_connection_verified();

			return [
				'success'     => true,
				'message'     => __( 'Connection successful! Your API credentials are working.', 'ghl-crm-integration' ),
				'status_code' => $status_code,
				'code'        => 200,
			];
		} else {
			// Mark connection as not verified
			$this->mark_connection_unverified();

			return [
				'success'     => false,
				'message'     => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Connection failed with status code: %d', 'ghl-crm-integration' ),
					$status_code
				),
				'details'     => $body,
				'status_code' => $status_code,
				'code'        => $status_code,
			];
		}
	}

	/**
	 * Check if connection is verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	public function is_connection_verified( ?int $site_id = null ): bool {
		return $this->settings_repository->is_connection_verified( $site_id );
	}

	/**
	 * Mark connection as verified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	public function mark_connection_verified( ?int $site_id = null ): bool {
		$result = $this->settings_repository->mark_connection_verified( $site_id );
		
		if ( $result ) {
			// Trigger action to notify other components that connection status has changed
			do_action( 'ghl_crm_connection_status_changed', true, 'manual' );
		}
		
		return $result;
	}

	/**
	 * Mark connection as unverified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool
	 */
	public function mark_connection_unverified( ?int $site_id = null ): bool {
		return $this->settings_repository->mark_connection_unverified( $site_id );
	}

	/**
	 * Get connection status
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return array Connection status details.
	 */
	public function get_connection_status( ?int $site_id = null ): array {
		$settings    = $this->settings_repository->get_settings_array( $site_id );
		$is_verified = $this->is_connection_verified( $site_id );

		// Check for OAuth OR manual credentials
		$has_oauth = ! empty( $settings['oauth_access_token'] );
		$has_manual = ! empty( $settings['api_token'] ) && ! empty( $settings['location_id'] );
		$has_credentials = $has_oauth || $has_manual;

		// Show token preview (OAuth or manual)
		$token_preview = '';
		if ( $has_oauth && ! empty( $settings['oauth_access_token'] ) ) {
			$token_preview = substr( $settings['oauth_access_token'], 0, 10 ) . '... (OAuth)';
		} elseif ( ! empty( $settings['api_token'] ) ) {
			$token_preview = substr( $settings['api_token'], 0, 10 ) . '...';
		}

		return [
			'has_credentials' => $has_credentials,
			'is_verified'     => $is_verified,
			'is_oauth'        => $has_oauth,
			'api_token'       => $token_preview,
			'location_id'     => $settings['location_id'] ?? '',
			'api_version'     => $settings['api_version'] ?? '2021-07-28',
			'updated_at'      => $settings['updated_at'] ?? '',
		];
	}

	/**
	 * Disconnect from GoHighLevel
	 *
	 * Clears API credentials and marks connection as unverified
	 *
	 * @param int|null $site_id Optional. Site ID for multisite.
	 * @return bool Success status.
	 */
	public function disconnect( ?int $site_id = null ): bool {
		$settings = $this->settings_repository->get_settings_array( $site_id );

		// Clear BOTH OAuth and manual API credentials
		$settings['api_token']          = '';
		$settings['location_id']        = '';
		$settings['oauth_access_token'] = '';
		$settings['oauth_refresh_token'] = '';
		$settings['oauth_expires_at']   = 0;
		$settings['updated_at']         = current_time( 'mysql' );

		// Save cleared settings
		$saved = $this->settings_repository->save_site_settings( $settings, $site_id );

		if ( $saved ) {
			// Mark as unverified
			$this->mark_connection_unverified( $site_id );

			// Trigger disconnection action
			do_action( 'ghl_crm_connection_status_changed', false, 'disconnected' );

			return true;
		}

		return false;
	}

	/**
	 * Validate API credentials format
	 *
	 * @param string $api_token API token to validate.
	 * @param string $location_id Location ID to validate.
	 * @return array Validation result.
	 */
	public function validate_credentials( string $api_token, string $location_id ): array {
		$errors = [];

		// Validate API token format
		if ( empty( $api_token ) ) {
			$errors[] = __( 'API Token is required.', 'ghl-crm-integration' );
		} elseif ( strlen( $api_token ) < 20 ) {
			$errors[] = __( 'API Token appears to be too short.', 'ghl-crm-integration' );
		}

		// Validate Location ID format
		if ( empty( $location_id ) ) {
			$errors[] = __( 'Location ID is required.', 'ghl-crm-integration' );
		} elseif ( strlen( $location_id ) < 10 ) {
			$errors[] = __( 'Location ID appears to be too short.', 'ghl-crm-integration' );
		}

		if ( ! empty( $errors ) ) {
			return [
				'valid'   => false,
				'errors'  => $errors,
				'message' => implode( ' ', $errors ),
			];
		}

		return [
			'valid'   => true,
			'message' => __( 'Credentials format is valid.', 'ghl-crm-integration' ),
		];
	}
}