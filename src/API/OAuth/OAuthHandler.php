<?php
declare(strict_types=1);

namespace Syncly\API\OAuth;

use Syncly\API\Client\Client;
use Syncly\Core\SettingsManager;
use Syncly\Utilities\FileLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OAuth2 Handler
 *
 * Handles GoHighLevel OAuth2 authentication flow
 *
 * @package    Syncly
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
			'syncly/v1',
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
	 * @param string $return_url Optional. Admin URL to return to after auth.
	 *                           When empty, the main Syncly dashboard is used.
	 *                           The setup wizard passes its own URL so users
	 *                           are sent back to the wizard after connecting.
	 * @return string Authorization URL
	 */
	public function get_authorization_url( string $return_url = '' ): string {
		// Use REST API callback as primary method (it proxies to admin page)
		$redirect_uri = rest_url( 'ghl/v1/callback' );

		// Generate and persist a nonce-backed state token to prevent CSRF
		$state = $this->generate_state_token( $return_url );

		return $this->client->get_oauth_authorization_url( $redirect_uri, $state );
	}

	/**
	 * Generate and store OAuth state token to prevent CSRF while preserving proxy return URL flow.
	 * State contains the return URL plus a nonce query parameter (`ghl_state`) that we validate on callback.
	 *
	 * @param string $return_url Optional. URL to return to after auth. Defaults to the Syncly dashboard.
	 * @return string Encoded state value (URL-encoded return URL with nonce)
	 */
	private function generate_state_token( string $return_url = '' ): string {
		$state_nonce = wp_generate_password( 32, false, false );

		if ( '' === $return_url ) {
			$return_url = admin_url( 'admin.php?page=syncly-admin' );
		}

		$return_url = add_query_arg(
			'ghl_state',
			$state_nonce,
			$return_url
		);

		// Bind state to nonce (not path) to keep compatibility with proxy redirect logic
		set_transient( 'syncly_oauth_state_' . $state_nonce, get_current_user_id(), self::STATE_TTL );

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
		// Use syncly-admin (SPA page) instead of syncly-settings
		return admin_url( 'admin.php?page=syncly-admin' );
	}

	/**
	 * Handle OAuth callback via admin_init (primary method)
	 * This works after the REST proxy forwards the code to the admin page
	 *
	 * @return void
	 */
	public function handle_admin_oauth_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if this is an OAuth callback - look for 'code' parameter on a Syncly page.
		// Accept the setup wizard as well so connections started from the wizard's
		// Connect step are processed there and return the user to the wizard.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! in_array( $page, [ 'syncly-admin', 'syncly-setup-wizard' ], true ) ) {
			return;
		}

		$sanitized_get = [];
		foreach ( $_GET as $key => $value ) {
			$clean_key = sanitize_key( $key );
			if ( '' !== $clean_key ) {
				$sanitized_get[ $clean_key ] = is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '';
			}
		}

		// Do not write authorization codes or state values to logs.
		$this->log_oauth_event( 'oauth_callback_admin_enter', [ 'query_arg_keys' => array_keys( $sanitized_get ) ] );

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
					$this->get_current_admin_return_url()
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

		$fallback_url = $this->get_current_admin_return_url();

		// If still empty, fail early with clear error
		if ( empty( $state ) ) {
			$this->log_oauth_event( 'oauth_state_missing_after_rebuild', [] );
			wp_safe_redirect(
				add_query_arg(
					[
						'oauth'   => 'error',
						'message' => urlencode( __( 'Missing state parameter. OAuth cancelled for security.', 'syncly' ) ),
					],
					$fallback_url
				)
			);
			exit;
		}

		$this->log_oauth_event( 'oauth_callback_admin_received' );

		// Process the callback with state parameter
		$result = $this->process_oauth_callback( $code, $state );

		// Resolve where to send the user back to — the return URL was baked into
		// the state when the flow started (dashboard by default, setup wizard when
		// the connection step initiated it).
		$redirect_url = $this->get_state_return_url( $state );

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
					$redirect_url
				)
			);
		} else {
			$this->log_oauth_event( 'oauth_callback_success', [ 'source' => 'admin' ] );
			wp_safe_redirect( add_query_arg( 'oauth', 'success', $redirect_url ) );
		}
		exit;
	}

	/**
	 * Extract the post-auth return URL from an encoded OAuth state value.
	 *
	 * The `ghl_state` nonce is removed from the query so the user lands on a
	 * clean page (`page`, `step` params are preserved). Falls back to the main
	 * Syncly dashboard when no valid return URL can be derived.
	 *
	 * @param string $state Encoded state value (URL-encoded return URL + nonce).
	 * @return string URL to redirect the user to after the OAuth round-trip.
	 */
	private function get_state_return_url( string $state ): string {
		$default = admin_url( 'admin.php?page=syncly-admin' );

		if ( '' === $state ) {
			return $default;
		}

		$decoded = rawurldecode( $state );
		$url     = remove_query_arg( 'ghl_state', $decoded );

		if ( empty( $url ) || ! is_string( $url ) ) {
			return $default;
		}

		return $url;
	}

	private function get_current_admin_return_url(): string {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'syncly-setup-wizard' === $page ) {
			$args = [ 'page' => 'syncly-setup-wizard' ];
			if ( isset( $_GET['step'] ) ) {
				$step = absint( wp_unslash( $_GET['step'] ) );
				if ( $step > 0 ) {
					$args['step'] = $step;
				}
			}

			return admin_url( add_query_arg( $args, 'admin.php' ) );
		}

		return admin_url( 'admin.php?page=syncly-admin' );
	}

	/**
	 * Handle OAuth callback via REST API (fallback method)
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_oauth_rest_callback( $request ) {
		$this->log_oauth_event( 'oauth_callback_rest_enter', [ 'param_keys' => array_keys( $request->get_params() ) ] );
		$code  = sanitize_text_field( $request->get_param( 'code' ) );
		$state = (string) $request->get_param( 'state' );
		$this->log_oauth_event( 'oauth_callback_rest_received' );

		$result = $this->process_oauth_callback( $code, $state );

		$redirect_url = $this->get_state_return_url( $state );

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
					$redirect_url
				)
			);
		} else {
			$this->log_oauth_event( 'oauth_callback_success', [ 'source' => 'rest' ] );
			wp_safe_redirect( add_query_arg( 'oauth', 'success', $redirect_url ) );
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
			[ 'state_present' => '' !== $state_nonce ]
		);

		if ( empty( $state_nonce ) ) {
			$this->log_oauth_event( 'oauth_state_nonce_missing', [] );
			return new \WP_Error( 'invalid_state', __( 'OAuth state missing nonce. Please try again.', 'syncly' ) );
		}

		$stored_state = get_transient( 'syncly_oauth_state_' . $state_nonce );

		if ( empty( $stored_state ) ) {
			$this->log_oauth_event( 'oauth_state_expired', [] );
			return new \WP_Error( 'invalid_state', __( 'OAuth state expired. Please try again.', 'syncly' ) );
		}

		if ( ! $this->state_belongs_to_current_user( $stored_state ) ) {
			$this->log_oauth_event( 'oauth_state_user_mismatch', [ 'state_user_id' => (int) $stored_state ] );
			return new \WP_Error( 'invalid_state', __( 'OAuth state does not belong to the current user.', 'syncly' ) );
		}

		// Clean up state transient
		delete_transient( 'syncly_oauth_state_' . $state_nonce );
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
		$this->settings_manager->update_option( 'syncly_connection_verified', $verification_data );

		/*
		* Trigger a custom action to notify other parts of the plugin that the connection status has changed.
		* @param bool $is_connected Current connection status (true since tokens are saved).
		* @param string $method Authentication method used ('oauth2').
		*/
		do_action( 'syncly_connection_status_changed', true, 'oauth2' );
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
			$this->settings_manager->update_option( 'syncly_connection_verified', false );

			// Clear cached token state so refresh guard does not trip after disconnect
			self::$access_token_cache = null;
			self::$last_refresh_time  = 0;
			self::$last_refresh_error = null;

			/*
			* Trigger a custom action to notify other parts of the plugin that the connection status has changed.
			* @param bool $is_connected Current connection status (false, most likely a manual disconnect).
			* @param string $method Authentication method used ('oauth_disconnected').
			*/
			do_action( 'syncly_connection_status_changed', false, 'oauth_disconnected' );

			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Attempt silent reconnection — no user interaction required.
	 *
	 * 1. If the access token is still valid, confirms the connection is healthy.
	 * 2. If the token is expired, tries a proxy refresh (form-encoded, uses client_secret).
	 * 3. If refresh fails, tries the proxy reconnect endpoint (gets fresh auth code).
	 * 4. If everything fails, returns WP_Error so the caller can redirect to GHL.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function silent_reconnect() {
		$settings    = $this->settings_manager->get_settings_array();
		$location_id = $settings['location_id'] ?? '';
		$expires     = isset( $settings['oauth_expires_at'] ) ? (int) $settings['oauth_expires_at'] : 0;
		$has_token   = ! empty( $settings['oauth_refresh_token'] ) || ! empty( $settings['oauth_access_token'] );

		if ( empty( $location_id ) || ! $has_token ) {
			return new \WP_Error(
				'insufficient_data',
				__( 'No existing token or location ID available for silent reconnect.', 'syncly' )
			);
		}

		// Always try proxy refresh to get a fresh token (even if current one is still valid).
		$refresh_token = $settings['oauth_refresh_token'] ?? '';
		if ( ! empty( $refresh_token ) ) {
			$refresh_result = $this->try_proxy_refresh( $refresh_token );
			if ( true === $refresh_result ) {
				return true;
			}
		}

		// Refresh failed — try proxy reconnect endpoint (gets fresh auth code).
		try {
			$auth_code = $this->client->reconnect_api();
			$redirect_uri   = rest_url( 'ghl/v1/callback' );
			$token_response = $this->client->exchange_code_for_token( $auth_code, $redirect_uri );
			$this->save_oauth_credentials( $token_response, $location_id );
			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'silent_reconnect_failed',
				$e->getMessage()
			);
		}
	}

	/**
	 * Attempt a token refresh directly through the proxy (form-encoded request).
	 *
	 * @param string $refresh_token The current refresh token.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function try_proxy_refresh( string $refresh_token ) {
		$proxy_url = 'https://synclyforgohighlevel.com/wp-json/ghl-proxy/v1/refresh-token';

		$response = wp_remote_post(
			$proxy_url,
			[
				'body'    => [
					'refresh_token' => $refresh_token,
				],
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'refresh_network_error', $response->get_error_message() );
		}

		$status     = wp_remote_retrieve_response_code( $response );
		$body       = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || empty( $body['access_token'] ) ) {
			$message = $body['message'] ?? $body['error'] ?? 'Refresh failed';
			return new \WP_Error( 'refresh_failed', $message );
		}

		// Save the refreshed tokens.
		$settings    = $this->settings_manager->get_settings_array();
		$location_id = $settings['location_id'] ?? '';
		$this->save_oauth_credentials( $body, $location_id );

		return true;
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
		$is_verified       = $this->settings_manager->is_connection_verified();

		return $is_verified && $has_location_id && $has_access_token && ( $token_is_current || $has_refresh_token );
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
	 * Verify that an OAuth state was initiated by the current user.
	 *
	 * @param mixed $stored_state User ID stored with the state nonce.
	 * @return bool
	 */
	private function state_belongs_to_current_user( $stored_state ): bool {
		$current_user_id = get_current_user_id();

		return $current_user_id > 0 && (int) $stored_state === $current_user_id;
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
