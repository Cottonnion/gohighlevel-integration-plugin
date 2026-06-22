<?php
declare(strict_types=1);

namespace GHL_CRM\API\OAuth;

use GHL_CRM\API\Client\Client;
use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Utilities\FileLogger;

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
	 * TTL for OAuth state nonce (in seconds).
	 */
	private const STATE_TTL = 900;

	/**
	 * Cached access token and refresh guard state.
	 *
	 * @var array|null
	 */
	private static ?array $access_token_cache = null;

	/**
	 * Timestamp of last token refresh attempt.
	 *
	 * @var int
	 */
	private static int $last_refresh_time = 0;

	/**
	 * Message from last refresh error (if any).
	 *
	 * @var string|null
	 */
	private static ?string $last_refresh_error = null;

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

		// Generate and persist a nonce-backed state token to prevent CSRF
		$state = $this->generate_state_token();

		return $this->client->get_oauth_authorization_url( $redirect_uri, $state );
	}

	/**
	 * Generate and store OAuth state token to prevent CSRF while preserving proxy return URL flow.
	 * State contains the return URL plus a nonce query parameter (`ghl_state`) that we validate on callback.
	 *
	 * @return string Encoded state value (URL-encoded return URL with nonce)
	 */
	private function generate_state_token(): string {
		$state_nonce = wp_generate_password( 32, false, false );

		$return_url = add_query_arg(
			'ghl_state',
			$state_nonce,
			admin_url( 'admin.php?page=ghl-crm-admin' )
		);

		// Bind state to nonce (not path) to keep compatibility with proxy redirect logic
		set_transient( 'ghl_oauth_state_' . $state_nonce, get_current_user_id(), self::STATE_TTL );

		return rawurlencode( $return_url );
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
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'ghl-crm-admin' !== $page ) {
			return;
		}

		$sanitized_get = array_map(
			static function ( $value ) {
				return is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '';
			},
			$_GET
		);

		$this->log_oauth_event( 'oauth_callback_admin_enter', [ 'query_args' => $sanitized_get ] );

		if ( ! isset( $_GET['code'] ) ) {
			return;
		}

		// We have a code, process the OAuth callback with state verification
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		// Fallback: proxy returns to admin URL with ghl_state instead of state; rebuild encoded state
		if ( empty( $state ) && isset( $_GET['ghl_state'] ) ) {
			$state_nonce = sanitize_text_field( wp_unslash( $_GET['ghl_state'] ) );
			$state       = rawurlencode(
				add_query_arg(
					'ghl_state',
					$state_nonce,
					admin_url( 'admin.php?page=ghl-crm-admin' )
				)
			);
			$this->log_oauth_event(
				'oauth_callback_admin_state_rebuilt',
				[
					'state_nonce' => $state_nonce,
					'state'       => $state,
				]
			);
		}

		// If still empty, fail early with clear error
		if ( empty( $state ) ) {
			$this->log_oauth_event( 'oauth_state_missing_after_rebuild', [] );
			wp_safe_redirect(
				add_query_arg(
					[
						'oauth'   => 'error',
						'message' => urlencode( __( 'Missing state parameter. OAuth cancelled for security.', 'syncly' ) ),
					],
					admin_url( 'admin.php?page=ghl-crm-admin' )
				)
			);
			exit;
		}

		$this->log_oauth_event( 'oauth_callback_admin_received', [ 'state' => $state ] );

		// Process the callback with state parameter
		$result = $this->process_oauth_callback( $code, $state );

		// Redirect with result
		if ( is_wp_error( $result ) ) {
			$this->log_oauth_event(
				'oauth_callback_error',
				[
					'source' => 'admin',
					'error'  => $result->get_error_message(),
				]
			);
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
			$this->log_oauth_event( 'oauth_callback_success', [ 'source' => 'admin' ] );
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
		$this->log_oauth_event( 'oauth_callback_rest_enter', [ 'params' => $request->get_params() ] );
		$code  = sanitize_text_field( $request->get_param( 'code' ) );
		$state = (string) $request->get_param( 'state' );
		$this->log_oauth_event( 'oauth_callback_rest_received', [ 'state' => $state ] );

		$result = $this->process_oauth_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			$this->log_oauth_event(
				'oauth_callback_error',
				[
					'source' => 'rest',
					'error'  => $result->get_error_message(),
				]
			);
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
			$this->log_oauth_event( 'oauth_callback_success', [ 'source' => 'rest' ] );
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
		if ( empty( $state ) ) {
			$this->log_oauth_event( 'oauth_state_missing', [] );
			return new \WP_Error( 'missing_state', __( 'Missing state parameter. Please restart the OAuth flow.', 'syncly' ) );
		}

		$decoded_state = rawurldecode( $state );
		$parsed_state  = wp_parse_url( $decoded_state );
		$query_params  = [];

		if ( isset( $parsed_state['query'] ) ) {
			parse_str( $parsed_state['query'], $query_params );
		}

		$state_nonce = isset( $query_params['ghl_state'] ) ? sanitize_text_field( (string) $query_params['ghl_state'] ) : '';
		$this->log_oauth_event(
			'oauth_state_parsed',
			[
				'state_nonce'   => $state_nonce,
				'decoded_state' => $decoded_state,
			]
		);

		if ( empty( $state_nonce ) ) {
			$this->log_oauth_event( 'oauth_state_nonce_missing', [] );
			return new \WP_Error( 'invalid_state', __( 'OAuth state missing nonce. Please try again.', 'syncly' ) );
		}

		$stored_state = get_transient( 'ghl_oauth_state_' . $state_nonce );

		if ( empty( $stored_state ) ) {
			$this->log_oauth_event( 'oauth_state_expired', [] );
			return new \WP_Error( 'invalid_state', __( 'OAuth state expired. Please try again.', 'syncly' ) );
		}

		// Clean up state transient
		delete_transient( 'ghl_oauth_state_' . $state_nonce );
		$this->log_oauth_event( 'oauth_state_valid', [ 'state_nonce' => $state_nonce ] );

		try {
			// Exchange code for tokens - use REST callback URL to match what was sent to GHL
			$redirect_uri   = rest_url( 'ghl/v1/callback' );
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

		// Trigger action to notify other components that connection status has changed
		do_action( 'ghl_crm_connection_status_changed', true, 'oauth2' );
	}

	/**
	 * Disconnect OAuth (revoke tokens)
	 *
	 * @return bool Success status
	 */
	public function disconnect(): bool {
		try {
			// Remove OAuth-related settings using SettingsManager (multisite-aware)
			$oauth_keys = [
				'oauth_access_token',
				'oauth_refresh_token',
				'oauth_expires_at',
				'oauth_connected_at',
				'location_id',
				'location_name',
			];

			foreach ( $oauth_keys as $key ) {
				$this->settings_manager->delete_setting( $key );
			}

			// Remove verification using SettingsManager (multisite-aware)
			$this->settings_manager->update_option( 'ghl_crm_connection_verified', false );

			// Clear cached token state so refresh guard does not trip after disconnect
			self::$access_token_cache = null;
			self::$last_refresh_time  = 0;
			self::$last_refresh_error = null;

			// Trigger disconnection action
			do_action( 'ghl_crm_connection_status_changed', false, 'oauth_disconnected' );

			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Check if OAuth is connected and valid
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		$settings = $this->settings_manager->get_settings_array();
		$expires  = isset( $settings['oauth_expires_at'] ) ? (int) $settings['oauth_expires_at'] : 0;

		$has_access_token  = ! empty( $settings['oauth_access_token'] );
		$has_refresh_token = ! empty( $settings['oauth_refresh_token'] );
		$has_location_id   = ! empty( $settings['location_id'] );
		$token_is_current  = $expires > time();

		return $has_location_id && $has_access_token && ( $token_is_current || $has_refresh_token );
	}

	/**
	 * Get OAuth connection status
	 *
	 * @return array Connection status information
	 */
	public function get_connection_status(): array {
		$settings = $this->settings_manager->get_settings_array();
		$expires  = isset( $settings['oauth_expires_at'] ) ? (int) $settings['oauth_expires_at'] : 0;

		return [
			'connected'     => $this->is_connected(),
			'connected_at'  => $settings['oauth_connected_at'] ?? '',
			'expires_at'    => $expires,
			'is_expired'    => $expires > 0 && $expires <= time(),
			'can_refresh'   => ! empty( $settings['oauth_refresh_token'] ),
			'location_id'   => $settings['location_id'] ?? '',
			'location_name' => $settings['location_name'] ?? '',
			'health_status' => $settings['oauth_health_status'] ?? 'unknown',
			'health_message' => $settings['oauth_health_message'] ?? '',
			'health_checked_at' => $settings['oauth_health_checked_at'] ?? '',
		];
	}

	/**
	 * Log an OAuth handler event via the dedicated FileLogger.
	 *
	 * @param string $event   Event name.
	 * @param array  $context Context data.
	 * @return void
	 */
	private function log_oauth_event( string $event, array $context = [] ): void {
		FileLogger::get_instance()->log( 'oauth', $event, $context );
	}
}
