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
		$state        = wp_create_nonce( 'ghl_oauth_state' );
		$redirect_uri = $this->get_redirect_uri();

		// Store state in transient for verification
		set_transient( 'ghl_oauth_state_' . get_current_user_id(), $state, HOUR_IN_SECONDS );

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
			return rest_url( 'ghl-crm/v1/oauth/callback' );
		}
		// Primary method: admin page URL for admin_init callback
		return admin_url( 'admin.php?page=ghl-crm-settings&ghl_oauth_callback=1' );
	}

	/**
	 * Handle OAuth callback via admin_init (primary method)
	 * This works even if REST API is disabled
	 *
	 * @return void
	 */
	public function handle_admin_oauth_callback(): void {
		// Check if this is an OAuth callback
		if ( ! isset( $_GET['ghl_oauth_callback'] ) || '1' !== $_GET['ghl_oauth_callback'] ) {
			return;
		}

		// Check if we have required parameters
		if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'oauth'   => 'error',
						'message' => urlencode( __( 'Missing OAuth parameters', 'ghl-crm-integration' ) ),
					],
					admin_url( 'admin.php?page=ghl-crm-settings' )
				)
			);
			exit;
		}

		$code  = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );

		// Process the callback
		$result = $this->process_oauth_callback( $code, $state );

		// Redirect with result
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
	 * @param string $state State parameter for verification
	 * @return array|\WP_Error Processing result or error
	 */
	private function process_oauth_callback( string $code, string $state ) {
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

		try {
			// Exchange code for tokens
			$token_response = $this->client->exchange_code_for_token( $code, $this->get_redirect_uri() );

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
		$current_settings = $this->settings_manager->get_settings_array();

		$oauth_settings = [
			'oauth_access_token'  => $token_response['access_token'],
			'oauth_refresh_token' => $token_response['refresh_token'] ?? '',
			'oauth_expires_at'    => time() + ( $token_response['expires_in'] ?? 3600 ),
			'oauth_connected_at'  => current_time( 'mysql' ),
		];

		// Update location ID if provided
		if ( ! empty( $location_id ) ) {
			$oauth_settings['location_id'] = $location_id;
		}

		// Merge with existing settings
		$updated_settings = array_merge( $current_settings, $oauth_settings );

		// Save settings
		update_option( 'ghl_crm_settings', $updated_settings );

		// Mark connection as verified
		$verification_data = [
			'verified'    => true,
			'verified_at' => current_time( 'mysql' ),
			'method'      => 'oauth2',
		];
		update_option( 'ghl_crm_connection_verified', $verification_data );
	}

	/**
	 * Disconnect OAuth (revoke tokens)
	 *
	 * @return bool Success status
	 */
	public function disconnect(): bool {
		$current_settings = $this->settings_manager->get_settings_array();

		// Remove OAuth-related settings
		$oauth_keys = [
			'oauth_access_token',
			'oauth_refresh_token',
			'oauth_expires_at',
			'oauth_connected_at',
		];

		foreach ( $oauth_keys as $key ) {
			unset( $current_settings[ $key ] );
		}

		// Save updated settings
		$saved = update_option( 'ghl_crm_settings', $current_settings );

		// Remove verification
		delete_option( 'ghl_crm_connection_verified' );

		return $saved;
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
