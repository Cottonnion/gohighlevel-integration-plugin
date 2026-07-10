<?php
/**
 * Connection Manager
 *
 * Handles GoHighLevel API connection management, testing, and validation
 *
 * @package Syncly
 */

namespace Syncly\API;

use Syncly\Core\Settings\SettingsRepository;

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
	 * Manual API key connections are intentionally unsupported.
	 *
	 * @param array $new_settings Ignored.
	 * @return array
	 */
	public function save_manual_connection_settings( array $new_settings ): array {
		unset( $new_settings );

		return [
			'success' => false,
			'message' => __( 'Manual API key connections are not supported. Please connect using OAuth.', 'syncly' ),
		];
	}

	/**
	 * Test API connection
	 *
	 * @return array Result with success status, message, and details.
	 */
	public function test_connection(): array {
		$settings = $this->settings_repository->get_settings_array();

		$has_oauth = ! empty( $settings['oauth_access_token'] );

		if ( ! $has_oauth ) {
			return [
				'success' => false,
				'message' => __( 'Please connect your GoHighLevel account first.', 'syncly' ),
				'code'    => 400,
			];
		}

		$auth_token = $settings['oauth_access_token'];

		// For OAuth, location_id might be stored differently
		$location_id = $settings['location_id'] ?? '';

		if ( empty( $location_id ) ) {
			return [
				'success' => false,
				'message' => __( 'Location ID not found. Please reconnect your account.', 'syncly' ),
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
					__( 'Connection failed: %s', 'syncly' ),
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
				'message'     => __( 'Connection successful! Your API credentials are working.', 'syncly' ),
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
					__( 'Connection failed with status code: %d', 'syncly' ),
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
			do_action( 'syncly_connection_status_changed', true, 'oauth' );
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

		$has_oauth       = ! empty( $settings['oauth_access_token'] );
		$has_credentials = $has_oauth;

		// Show token preview (OAuth)
		$token_preview = '';
		if ( $has_oauth && ! empty( $settings['oauth_access_token'] ) ) {
			$token_preview = substr( $settings['oauth_access_token'], 0, 10 ) . '... (OAuth)';
		}

		return [
			'has_credentials' => $has_credentials,
			'is_verified'     => $is_verified,
			'is_oauth'        => $has_oauth,
			'token_preview'   => $token_preview,
			'location_id'     => $settings['location_id'] ?? '',
			'api_version'     => $settings['api_version'] ?? '2021-07-28',
			'updated_at'      => $settings['updated_at'] ?? '',
			'oauth_health_status' => $settings['oauth_health_status'] ?? 'unknown',
			'oauth_health_message' => $settings['oauth_health_message'] ?? '',
			'oauth_health_checked_at' => $settings['oauth_health_checked_at'] ?? '',
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

		// Clear OAuth credentials
		$settings['api_token']           = '';
		$settings['location_id']         = '';
		$settings['oauth_access_token']  = '';
		$settings['oauth_refresh_token'] = '';
		$settings['oauth_expires_at']    = 0;
		$settings['updated_at']          = current_time( 'mysql' );

		// Save cleared settings
		$saved = $this->settings_repository->save_site_settings( $settings, $site_id );

		if ( $saved ) {
			// Mark as unverified
			$this->mark_connection_unverified( $site_id );

			// Trigger disconnection action
			do_action( 'syncly_connection_status_changed', false, 'disconnected' );

			return true;
		}

		return false;
	}

	/**
	 * Manual API credential validation is intentionally unsupported.
	 *
	 * @param string $api_token Ignored.
	 * @param string $location_id Ignored.
	 * @return array
	 */
	public function validate_credentials( string $api_token, string $location_id ): array {
		unset( $api_token, $location_id );

		return [
			'valid'   => false,
			'errors'  => [ __( 'Manual API key connections are not supported. Please use OAuth.', 'syncly' ) ],
			'message' => __( 'Manual API key connections are not supported. Please use OAuth.', 'syncly' ),
		];
	}
}
