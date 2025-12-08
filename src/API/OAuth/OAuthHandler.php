<?php
declare(strict_types=1);

namespace GHL_CRM\API\OAuth;

use GHL_CRM\API\Client\Client;
use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OAuth2 Handler
 *
 * Handles GoHighLevel OAuth2 authentication flow
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/OAuth
 */
class OAuthHandler {
	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * API Client
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
		$this->client           = Client::get_instance();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Register OAuth callback endpoint (REST API method)
		add_action( 'rest_api_init', [ $this, 'register_oauth_endpoint' ] );

		// Handle OAuth callback via admin_init (fallback if REST API disabled)
		add_action( 'admin_init', [ $this, 'handle_admin_oauth_callback' ] );
	}

	/**
	 * Register REST API endpoint for OAuth callback
	 *
	 * @return void
	 */
	public function register_oauth_endpoint(): void {
		register_rest_route(
			'ghl-crm/v1',
			'/oauth/callback',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_oauth_rest_callback' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Generate OAuth authorization URL
	 *
	 * @return string Authorization URL
	 */
	public function get_authorization_url(): string {
		// Use REST API callback as primary method (it proxies to admin page)
		$redirect_uri = rest_url( 'ghl/v1/callback' );

		// State contains the admin page URL to return to after OAuth
		$return_url = admin_url( 'admin.php?page=ghl-crm-admin' );
		$state = rawurlencode( $return_url );

		return $this->client->get_oauth_authorization_url( $redirect_uri, $state );
	}

	/**
	 * Get OAuth redirect URI
	 * Returns admin settings page URL (for admin_init callback) or REST URL as fallback
	 *
	 * @param bool $use_rest Whether to use REST API URL
	 * @return string Redirect URI
	 */
	private function get_redirect_uri( bool $use_rest = false ): string {
		if ( $use_rest ) {
			return rest_url( 'ghl/v1/callback' );
		}
		// Primary method: admin page URL for admin_init callback
		// Use ghl-crm-admin (SPA page) instead of ghl-crm-settings
		return admin_url( 'admin.php?page=ghl-crm-admin' );
	}

	/**
	 * Handle OAuth callback via admin_init (primary method)
	 * This works after the REST proxy forwards the code to the admin page
	 *
	 * @return void
	 */
	public function handle_admin_oauth_callback(): void {
		// Check if this is an OAuth callback - look for 'code' parameter on ghl-crm-admin page
		if ( ! isset( $_GET['page'] ) || 'ghl-crm-admin' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['code'] ) ) {
			return;
		}

		// We have a code, process the OAuth callback (no state verification since it was used for return URL)
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );

		// Process the callback without state parameter
		$result = $this->process_oauth_callback( $code, '' );

		// Redirect with result
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'oauth'   => 'error',
						'message' => urlencode( $result->get_error_message() ),
					],
					admin_url( 'admin.php?page=ghl-crm-admin' )
				)
			);
		} else {
			wp_safe_redirect( add_query_arg( 'oauth', 'success', admin_url( 'admin.php?page=ghl-crm-admin' ) ) );
		}
		exit;
	}

	/**
	 * Handle OAuth callback via REST API (fallback method)
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_oauth_rest_callback( $request ) {
		$code  = sanitize_text_field( $request->get_param( 'code' ) );
		$state = sanitize_text_field( $request->get_param( 'state' ) );

		$result = $this->process_oauth_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'oauth'   => 'error',
						'message' => urlencode( $result->get_error_message() ),
					],
					admin_url( 'admin.php?page=ghl-crm-settings' )
				)
			);
		} else {
			wp_safe_redirect( add_query_arg( 'oauth', 'success', admin_url( 'admin.php?page=ghl-crm-settings' ) ) );
		}
		exit;
	}

	/**
	 * Process OAuth callback and exchange code for tokens
	 *
	 * @param string $code  Authorization code
	 * @param string $state State parameter for verification (empty when using REST proxy)
	 * @return array|\WP_Error Processing result or error
	 */
	private function process_oauth_callback( string $code, string $state ) {
		// Only verify state if it was provided (not used when REST proxy handles return URL)
		if ( ! empty( $state ) ) {
			// Verify state parameter
			$stored_state = get_transient( 'ghl_oauth_state_' . get_current_user_id() );

			if ( empty( $stored_state ) ) {
				return new \WP_Error( 'invalid_state', __( 'OAuth state expired. Please try again.', 'ghl-crm-integration' ) );
			}

			if ( ! hash_equals( $stored_state, $state ) ) {
				return new \WP_Error( 'invalid_state', __( 'Invalid OAuth state parameter', 'ghl-crm-integration' ) );
			}

			// Clean up state transient
			delete_transient( 'ghl_oauth_state_' . get_current_user_id() );
		}

		try {
			// Exchange code for tokens - use REST callback URL to match what was sent to GHL
			$redirect_uri = rest_url( 'ghl/v1/callback' );
			$token_response = $this->client->exchange_code_for_token( $code, $redirect_uri );

			// Extract location ID from response (single location only)
			$location_id = '';
			if ( ! empty( $token_response['locationId'] ) ) {
				$location_id = $token_response['locationId'];
			} elseif ( isset( $_GET['locationId'] ) ) {
				$location_id = sanitize_text_field( wp_unslash( $_GET['locationId'] ) );
			}

			// Save OAuth tokens and location
			$this->save_oauth_credentials( $token_response, $location_id );

			return [
				'success'     => true,
				'location_id' => $location_id,
				'expires_in'  => $token_response['expires_in'] ?? 3600,
			];
		} catch ( \Exception $e ) {
			return new \WP_Error( 'token_exchange_failed', $e->getMessage() );
		}
	}

	/**
	 * Save OAuth credentials to settings
	 *
	 * @param array  $token_response Token response from GoHighLevel
	 * @param string $location_id    Location ID if available
	 * @return void
	 */
	private function save_oauth_credentials( array $token_response, string $location_id = '' ): void {
		// Save OAuth settings using SettingsManager (multisite-aware)
		$this->settings_manager->update_setting( 'oauth_access_token', $token_response['access_token'] );
		$this->settings_manager->update_setting( 'oauth_refresh_token', $token_response['refresh_token'] ?? '' );
		$this->settings_manager->update_setting( 'oauth_expires_at', time() + ( $token_response['expires_in'] ?? 3600 ) );
		$this->settings_manager->update_setting( 'oauth_connected_at', current_time( 'mysql' ) );

		// Update location ID if provided
		if ( ! empty( $location_id ) ) {
			$this->settings_manager->update_setting( 'location_id', $location_id );
		}

		// Mark connection as verified using SettingsManager (multisite-aware)
		$verification_data = [
			'verified'    => true,
			'verified_at' => current_time( 'mysql' ),
			'method'      => 'oauth2',
		];
		$this->settings_manager->update_option( 'ghl_crm_connection_verified', $verification_data );
	}

	/**
	 * Disconnect OAuth (revoke tokens)
	 *
	 * @return bool Success status
	 */
	public function disconnect(): bool {
		// Remove OAuth-related settings using SettingsManager (multisite-aware)
		$oauth_keys = [
			'oauth_access_token',
			'oauth_refresh_token',
			'oauth_expires_at',
			'oauth_connected_at',
		];

		$success = true;
		foreach ( $oauth_keys as $key ) {
			$result = $this->settings_manager->delete_setting( $key );
			if ( ! $result ) {
				$success = false;
			}
		}

		// Remove verification using SettingsManager (multisite-aware)
		$this->settings_manager->update_option( 'ghl_crm_connection_verified', false );

		return $success;
	}

	/**
	 * Check if OAuth is connected and valid
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		$settings = $this->settings_manager->get_settings_array();

		return ! empty( $settings['oauth_access_token'] ) &&
				! empty( $settings['oauth_refresh_token'] );
	}

	/**
	 * Get OAuth connection status
	 *
	 * @return array Connection status information
	 */
	public function get_connection_status(): array {
		$settings = $this->settings_manager->get_settings_array();

		return [
			'connected'     => $this->is_connected(),
			'connected_at'  => $settings['oauth_connected_at'] ?? '',
			'expires_at'    => $settings['oauth_expires_at'] ?? '',
			'location_id'   => $settings['location_id'] ?? '',
			'location_name' => $settings['location_name'] ?? '',
		];
	}
}
