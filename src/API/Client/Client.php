<?php
declare(strict_types=1);

namespace GHL_CRM\API\Client;

use GHL_CRM\API\Exceptions\ApiException;
use GHL_CRM\API\Exceptions\RateLimitException;
use GHL_CRM\API\Exceptions\AuthenticationException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Client
 *
 * Handles all HTTP communication with GoHighLevel API including OAuth2 authentication
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/Client
 */
class Client implements ClientInterface {
	/**
	 * API Base URL
	 */
	private const BASE_URL = 'https://services.leadconnectorhq.com';

	/**
	 * OAuth2 Authorization URL
	 */
	private const OAUTH_AUTH_URL = 'https://marketplace.leadconnectorhq.com/oauth/chooselocation';

	/**
	 * OAuth2 Token URL
	 */
	private const OAUTH_TOKEN_URL = 'https://services.leadconnectorhq.com/oauth/token';

	/**
	 * OAuth2 Reconnect URL
	 */
	private const OAUTH_RECONNECT_URL = 'https://services.leadconnectorhq.com/oauth/reconnect';

	/**
	 * OAuth2 Client ID
	 * Production OAuth App Client ID
	 *
	 * @var string
	 */
	private const OAUTH_CLIENT_ID = '68ff9baa25051d0ca83341e9-mh9cljcg';

	/**
	 * OAuth2 Client Secret
	 * Production OAuth App Client Secret
	 *
	 * @var string
	 */
	private const OAUTH_CLIENT_SECRET = '17bd923c-13df-4198-8f78-0675a4b2e99a';

	/**
	 * OAuth2 Access Token
	 *
	 * @var string
	 */
	private string $access_token = '';

	/**
	 * OAuth2 Refresh Token
	 *
	 * @var string
	 */
	private string $refresh_token = '';

	/**
	 * API Token (fallback for manual token entry)
	 *
	 * @var string
	 */
	private string $token = '';

	/**
	 * Location ID
	 *
	 * @var string
	 */
	private string $location_id = '';

	/**
	 * API Version
	 *
	 * @var string
	 */
	private string $api_version = '2021-07-28';

	/**
	 * Last response headers
	 *
	 * @var array
	 */
	private array $last_response_headers = [];

	/**
	 * Skip OAuth token refresh (for manual API key testing)
	 *
	 * @var bool
	 */
	private bool $skip_oauth_refresh = false;

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get instance
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
	 * Private constructor
	 */
	private function __construct() {
		$this->load_settings();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Global HTTP response filter for automatic token refresh on ALL API calls
		add_filter( 'http_response', [ $this, 'handle_http_response' ], 50, 3 );
	}

	/**
	 * Handle HTTP response globally
	 * Automatically refreshes tokens on 401/403 errors and retries the request
	 *
	 * @param array|WP_Error $response HTTP response
	 * @param array          $args     HTTP request arguments
	 * @param string         $url      Request URL
	 * @return array|WP_Error Modified response
	 */
	public function handle_http_response( $response, $args, $url ) {
		// Only handle GoHighLevel API requests
		if ( strpos( $url, 'services.leadconnectorhq.com' ) === false &&
			strpos( $url, 'rest.gohighlevel.com' ) === false ) {
			return $response;
		}

		// Skip if it's a token request (avoid infinite loop)
		if ( strpos( $url, '/oauth/token' ) !== false ||
			strpos( $url, '/oauth/reconnect' ) !== false ) {
			return $response;
		}

		// Skip if request failed at network level
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		// Success - no action needed
		if ( 200 === $response_code || 201 === $response_code ) {
			return $response;
		}

		$body_json = json_decode( wp_remote_retrieve_body( $response ), true );

		// Handle duplicate contact - auto-update instead of create
		if ( 400 === $response_code &&
			isset( $body_json['message'] ) &&
			'This location does not allow duplicated contacts.' === $body_json['message'] &&
			isset( $body_json['meta']['matchingField'] ) &&
			'email' === $body_json['meta']['matchingField'] ) {

			// Extract contact ID and update instead
			$contact_id = sanitize_text_field( $body_json['meta']['contactId'] ?? '' );

			if ( ! empty( $contact_id ) && 'POST' === $args['method'] ) {
				// Change to PUT request for update
				$args['method'] = 'PUT';

				// Remove locationId from body (causes error on update)
				$contact_data = json_decode( $args['body'], true );
				if ( isset( $contact_data['locationId'] ) ) {
					unset( $contact_data['locationId'] );
				}

				$args['body'] = wp_json_encode( $contact_data );

				// Update the URL to include contact ID
				$base_url = preg_replace( '/contacts\/?$/', '', $url );
				$new_url  = $base_url . 'contacts/' . $contact_id;

				// Retry as update request
				return wp_remote_request( $new_url, $args );
			}
		}

		// Handle 401/403 authentication errors
		if ( ( 401 === $response_code || 403 === $response_code ) &&
			isset( $body_json['message'] ) ) {

			$error_message = $body_json['message'];

			// Skip OAuth refresh if flag is set (e.g., during manual API key testing)
			if ( $this->skip_oauth_refresh ) {
				return $response;
			}

			// Check if it's a token-related error
			$token_errors = [
				'The token does not have access to this location.',
				'access token',
				'refresh token',
				'Invalid JWT',
				'expired',
				'unauthorized',
			];

			$is_token_error = false;
			foreach ( $token_errors as $error_str ) {
				if ( stripos( $error_message, $error_str ) !== false ) {
					$is_token_error = true;
					break;
				}
			}

			if ( $is_token_error ) {
				try {
					// Only attempt to refresh if we have OAuth tokens
					if ( ! empty( $this->refresh_token ) ) {
						// Attempt to refresh the access token
						$this->refresh_access_token();

						// Update authorization header with new token
						$args['headers']['Authorization'] = 'Bearer ' . $this->access_token;

						// Retry the original request
						return wp_remote_request( $url, $args );
					}
					// If no refresh token (manual API key), just return the error response
					return $response;

				} catch ( \Exception $e ) {
					// Token refresh failed - show admin notice
					$notices = \GHL_CRM\Core\AdminNotices::get_instance();
					$notices->error(
						sprintf(
							/* translators: %s: Error message */
							__( 'GoHighLevel token refresh failed: %s. Please reconnect your account.', 'ghl-crm-integration' ),
							$e->getMessage()
						),
						true // Show on all admin pages
					);

					// Return error
					return new \WP_Error(
						'token_refresh_failed',
						sprintf(
							/* translators: %s: Error message */
							__( 'Error refreshing access token: %s', 'ghl-crm-integration' ),
							$e->getMessage()
						)
					);
				}
			}
		}

		return $response;
	}

	/**
	 * Load settings from options (multisite-aware)
	 *
	 * @return void
	 */
	private function load_settings(): void {
		// Get settings from SettingsManager (multisite-aware)
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();

		// OAuth2 tokens from settings
		if ( ! empty( $settings['oauth_access_token'] ) ) {
			$this->access_token = $settings['oauth_access_token'];
		}

		if ( ! empty( $settings['oauth_refresh_token'] ) ) {
			$this->refresh_token = $settings['oauth_refresh_token'];
		}

		// Fallback to manual token if OAuth not configured
		if ( ! empty( $settings['api_token'] ) ) {
			$this->token = $settings['api_token'];
		}

		if ( ! empty( $settings['location_id'] ) ) {
			$this->location_id = $settings['location_id'];
		}

		if ( ! empty( $settings['api_version'] ) ) {
			$this->api_version = $settings['api_version'];
		}
	}

	/**
	 * Reload settings (useful after settings update)
	 *
	 * @return void
	 */
	public function reload_settings(): void {
		$this->load_settings();
	}

	/**
	 * Set API token
	 *
	 * @param string $token API token
	 * @return void
	 */
	public function set_token( string $token ): void {
		$this->token = $token;
	}

	/**
	 * Set location ID
	 *
	 * @param string $location_id Location ID
	 * @return void
	 */
	public function set_location_id( string $location_id ): void {
		$this->location_id = $location_id;
	}

	/**
	 * Set API version
	 *
	 * @param string $version API version
	 * @return void
	 */
	public function set_api_version( string $version ): void {
		$this->api_version = $version;
	}

	/**
	 * Skip OAuth token refresh for manual API key testing
	 *
	 * @param bool $skip Whether to skip OAuth refresh
	 * @return void
	 */
	public function set_skip_oauth_refresh( bool $skip ): void {
		$this->skip_oauth_refresh = $skip;
	}

	/**
	 * Generate OAuth2 authorization URL
	 *
	 * @param string $redirect_uri Redirect URI after authorization
	 * @param string $state        Random state parameter for security
	 * @return string Authorization URL
	 */
	public function get_oauth_authorization_url( string $redirect_uri, string $state ): string {
		$params = [
			'client_id'     => self::OAUTH_CLIENT_ID,
			'redirect_uri'  => $redirect_uri,
			'scope'         => 'contacts.readonly contacts.write locations/tags.readonly locations/tags.write locations/customFields.readonly locations/customFields.write',
			'response_type' => 'code',
			'state'         => $state,
		];

		return self::OAUTH_AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for access token
	 *
	 * @param string $code         Authorization code from callback
	 * @param string $redirect_uri Redirect URI used in authorization
	 * @return array Token response
	 * @throws ApiException
	 */
	public function exchange_code_for_token( string $code, string $redirect_uri ): array {
		$data = [
			'client_id'     => self::OAUTH_CLIENT_ID,
			'client_secret' => self::OAUTH_CLIENT_SECRET,
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => $redirect_uri,
		];

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body'    => http_build_query( $data ),
			'timeout' => 30,
		];

		$response = wp_remote_request( self::OAUTH_TOKEN_URL, $args );

		if ( is_wp_error( $response ) ) {
			throw new ApiException(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'OAuth token exchange failed: %s', 'ghl-crm-integration' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( $status_code !== 200 || empty( $decoded['access_token'] ) ) {
			$decoded_array = $this->sanitize_response_payload( $decoded );
			throw new ApiException(
				esc_html__( 'Failed to obtain access token from GoHighLevel', 'ghl-crm-integration' ),
				(int) $status_code,
				$decoded_array // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitized via sanitize_response_payload(
			);
		}

		// Store tokens
		$this->access_token  = $decoded['access_token'];
		$this->refresh_token = $decoded['refresh_token'] ?? '';

		return $decoded;
	}

	/**
	 * Refresh OAuth2 access token
	 *
	 * @return array Token response
	 * @throws ApiException
	 */
	public function refresh_access_token(): array {
		if ( empty( $this->refresh_token ) ) {
			throw new ApiException( esc_html__( 'No refresh token available', 'ghl-crm-integration' ) );
		}

		// Handle edge case where refresh token might be corrupted (not a string)
		if ( ! is_string( $this->refresh_token ) ) {
			// Try reconnect API as fallback
			try {
				$auth_code = $this->reconnect_api();
				// Exchange auth code for new tokens
				$redirect_uri = admin_url( 'admin.php?page=ghl-crm-settings' );
				return $this->exchange_code_for_token( $auth_code, $redirect_uri );
			} catch ( ApiException $e ) {
				throw new ApiException(
					esc_html__( 'Refresh token is invalid and reconnect failed', 'ghl-crm-integration' )
				);
			}
		}

		$data = [
			'client_id'     => self::OAUTH_CLIENT_ID,
			'client_secret' => self::OAUTH_CLIENT_SECRET,
			'grant_type'    => 'refresh_token',
			'refresh_token' => $this->refresh_token,
		];

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body'    => http_build_query( $data ),
			'timeout' => 30,
		];

		$response = wp_remote_request( self::OAUTH_TOKEN_URL, $args );

		if ( is_wp_error( $response ) ) {
			throw new ApiException(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'Token refresh failed: %s', 'ghl-crm-integration' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( $status_code !== 200 || empty( $decoded['access_token'] ) ) {
			$decoded_array = is_array( $decoded ) ? $decoded : [];
			throw new ApiException(
				esc_html__( 'Failed to refresh access token', 'ghl-crm-integration' ),
				(int) $status_code,
				$decoded_array // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitized via sanitize_response_payload()
			);
		}

		// Update tokens
		$this->access_token = $decoded['access_token'];
		if ( ! empty( $decoded['refresh_token'] ) ) {
			$this->refresh_token = $decoded['refresh_token'];
		}

		return $decoded;
	}

	/**
	 * Check if OAuth2 is configured and valid
	 *
	 * @return bool
	 */
	public function is_oauth_configured(): bool {
		return ! empty( $this->access_token );
	}

	/**
	 * Reconnect API - Get new authorization code when refresh token fails
	 * Uses GoHighLevel's reconnect endpoint for emergency token recovery
	 *
	 * @return string Authorization code
	 * @throws ApiException
	 */
	public function reconnect_api(): string {
		if ( empty( $this->location_id ) ) {
			throw new ApiException( esc_html__( 'Missing location ID for HighLevel reconnect', 'ghl-crm-integration' ) );
		}

		$data = [
			'clientKey'    => self::OAUTH_CLIENT_ID,
			'clientSecret' => self::OAUTH_CLIENT_SECRET,
			'locationId'   => $this->location_id,
		];

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $data ),
			'timeout' => 15,
		];

		$response = wp_remote_request( self::OAUTH_RECONNECT_URL, $args );

		if ( is_wp_error( $response ) ) {
			throw new ApiException(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'Reconnect API failed: %s', 'ghl-crm-integration' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( $status_code !== 200 || empty( $decoded['authorizationCode'] ) ) {
			$decoded_array = $this->sanitize_response_payload( $decoded );
			throw new ApiException(
				esc_html__( 'Failed to get authorization code from HighLevel reconnect', 'ghl-crm-integration' ),
				(int) $status_code,
				$decoded_array // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitized via sanitize_response_payload(
			);
		}

		return $decoded['authorizationCode'];
	}

	/**
	 * Send GET request
	 *
	 * @param string $endpoint            API endpoint
	 * @param array  $params              Query parameters
	 * @param bool   $include_location_id Whether to include locationId in query params (default: true)
	 * @return array Response data
	 * @throws ApiException
	 */
	public function get( string $endpoint, array $params = [], bool $include_location_id = true ): array {
		$url = $this->build_url( $endpoint, $params, $include_location_id );
		return $this->request( 'GET', $url );
	}

	/**
	 * Send POST request
	 *
	 * @param string $endpoint            API endpoint
	 * @param array  $data                Request body
	 * @param bool   $include_location_id Whether to include locationId in query params (default: true)
	 * @return array Response data
	 * @throws ApiException
	 */
	public function post( string $endpoint, array $data = [], bool $include_location_id = true ): array {
		$url = $this->build_url( $endpoint, [], $include_location_id );
		return $this->request( 'POST', $url, $data );
	}

	/**
	 * Send PUT request
	 *
	 * @param string $endpoint            API endpoint
	 * @param array  $data                Request body
	 * @param bool   $include_location_id Whether to include locationId in query params (default: true)
	 * @return array Response data
	 * @throws ApiException
	 */
	public function put( string $endpoint, array $data = [], bool $include_location_id = true ): array {
		$url = $this->build_url( $endpoint, [], $include_location_id );
		return $this->request( 'PUT', $url, $data );
	}

	/**
	 * Send DELETE request
	 *
	 * @param string $endpoint            API endpoint
	 * @param bool   $include_location_id Whether to include locationId in query params (default: true)
	 * @param array  $data                Optional request body data
	 * @return array Response data
	 * @throws ApiException
	 */
	public function delete( string $endpoint, bool $include_location_id = true, array $data = [] ): array {
		$url = $this->build_url( $endpoint, [], $include_location_id );
		return $this->request( 'DELETE', $url, $data );
	}

	/**
	 * Get last response headers
	 *
	 * @return array
	 */
	public function get_last_response_headers(): array {
		return $this->last_response_headers;
	}

	/**
	 * Get rate limit status from last response
	 *
	 * @return array ['remaining' => int, 'limit' => int, 'reset' => int]
	 */
	public function get_rate_limit_status(): array {
		$headers = $this->last_response_headers;

		return [
			'remaining' => isset( $headers['x-ratelimit-remaining'] ) ? (int) $headers['x-ratelimit-remaining'] : 0,
			'limit'     => isset( $headers['x-ratelimit-limit'] ) ? (int) $headers['x-ratelimit-limit'] : 0,
			'reset'     => isset( $headers['x-ratelimit-reset'] ) ? (int) $headers['x-ratelimit-reset'] : 0,
		];
	}

	/**
	 * Build full URL with endpoint and params
	 *
	 * @param string $endpoint            Endpoint path
	 * @param array  $params              Query parameters
	 * @param bool   $include_location_id Whether to include locationId in query params (default: true)
	 * @return string Full URL
	 */
	private function build_url( string $endpoint, array $params = [], bool $include_location_id = true ): string {
		$url = self::BASE_URL . '/' . ltrim( $endpoint, '/' );

		// Add location ID to params if requested and not already present
		// Skip if endpoint already contains "locations/{locationId}" in the path
		$endpoint_has_location_path = preg_match( '#^locations/[a-zA-Z0-9_-]+/#', $endpoint );

		if ( $include_location_id && ! empty( $this->location_id ) && ! isset( $params['locationId'] ) && ! $endpoint_has_location_path ) {
			$params['locationId'] = $this->location_id;
		}

		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		return $url;
	}

	/**
	 * Execute HTTP request
	 *
	 * @param string $method HTTP method
	 * @param string $url    Full URL
	 * @param array  $data   Request body
	 * @return array Response data
	 * @throws ApiException
	 */
	private function request( string $method, string $url, array $data = [] ): array {
		// Determine which token to use (OAuth2 preferred)
		$auth_token = '';
		if ( $this->is_oauth_configured() ) {
			$auth_token = $this->access_token;
		} elseif ( ! empty( $this->token ) ) {
			$auth_token = $this->token;
		} else {
			throw new AuthenticationException( esc_html__( 'No authentication method configured. Please connect your GoHighLevel account.', 'ghl-crm-integration' ) );
		}

		// Build request arguments
		$args = [
			'method'  => $method,
			'headers' => [
				'Authorization' => 'Bearer ' . $auth_token,
				'Content-Type'  => 'application/json',
				'Version'       => $this->api_version,
			],
			'timeout' => 30,
		];

		// Add body for POST/PUT/DELETE requests
		if ( in_array( $method, [ 'POST', 'PUT', 'DELETE' ], true ) && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		// Execute request
		$response = wp_remote_request( $url, $args );

		// Check for WP errors
		if ( is_wp_error( $response ) ) {
			$error_msg = 'HTTP Request failed: ' . $response->get_error_message();

			throw new ApiException( esc_html( $error_msg ) );
		}

		// Get response data
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		// Log full response

		// Store headers for rate limit tracking
		$this->last_response_headers = $headers->getAll();

		// Handle 401 with OAuth2 - try to refresh token
		if ( 401 === $status_code && $this->is_oauth_configured() && ! empty( $this->refresh_token ) ) {
			try {
				// Attempt to refresh the token
				$this->refresh_access_token();

				// Save refreshed tokens
				$this->save_oauth_tokens();

				// Retry the request with new token
				$args['headers']['Authorization'] = 'Bearer ' . $this->access_token;
				$response                         = wp_remote_request( $url, $args );

				if ( ! is_wp_error( $response ) ) {
					$status_code                 = wp_remote_retrieve_response_code( $response );
					$body                        = wp_remote_retrieve_body( $response );
					$headers                     = wp_remote_retrieve_headers( $response );
					$this->last_response_headers = $headers->getAll();
				}
			} catch ( ApiException $e ) {
				// Refresh failed, clear OAuth tokens
				$this->clear_oauth_tokens();
			}
		}

		// Decode JSON response
		$decoded = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new ApiException(
				esc_html__( 'Invalid JSON response from API', 'ghl-crm-integration' ),
				(int) $status_code,
				[ 'raw_body' => $this->sanitize_response_scalar( (string) $body ) ] // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitized via sanitize_response_scalar()
			); 
		}

		// Handle error responses
		$this->handle_error_response( $status_code, $decoded );

		return $decoded;
	}

	/**
	 * Save OAuth2 tokens to database (multisite-aware)
	 *
	 * @return void
	 */
	private function save_oauth_tokens(): void {
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();

		// Update individual settings using SettingsManager (multisite-aware)
		$settings_manager->update_setting( 'oauth_access_token', $this->access_token );
		$settings_manager->update_setting( 'oauth_refresh_token', $this->refresh_token );
	}

	/**
	 * Clear OAuth2 tokens from database (multisite-aware)
	 *
	 * @return void
	 */
	public function clear_oauth_tokens(): void {
		$this->access_token  = '';
		$this->refresh_token = '';

		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();

		// Delete OAuth settings using SettingsManager (multisite-aware)
		$settings_manager->delete_setting( 'oauth_access_token' );
		$settings_manager->delete_setting( 'oauth_refresh_token' );
		$settings_manager->delete_setting( 'oauth_expires_at' );
	}

	/**
	 * Test manual API connection
	 *
	 * Tests if the provided API token and location ID can successfully authenticate.
	 * Uses Bearer token authentication method.
	 *
	 * @param string $api_token   The API token to test.
	 * @param string $location_id The location ID to test.
	 * @return array Result array with 'success' boolean, 'message' string, and optional 'data'.
	 */
	public function test_manual_connection( string $api_token, string $location_id ): array {
		// Validate inputs
		if ( empty( $api_token ) || empty( $location_id ) ) {
			return [
				'success' => false,
				'message' => __( 'API Token and Location ID are required.', 'ghl-crm-integration' ),
			];
		}

		// Check if token is JWT format (temporary token, not suitable for permanent integration)
		if ( strpos( $api_token, 'eyJ' ) === 0 ) {
			return [
				'success' => false,
				'message' => __( 'Invalid API Key Format: You appear to have entered a JWT token (temporary) instead of a Location API Key (permanent). Please get your Location API Key from: Settings → Private Integrations → API Key in your GoHighLevel location.', 'ghl-crm-integration' ),
			];
		}

		// Test the connection with a simple API call
		$test_url = self::BASE_URL . '/contacts/?locationId=' . $location_id . '&limit=1';

		$response = wp_remote_get(
			$test_url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_token,
					'Content-Type'  => 'application/json',
					'Version'       => $this->api_version,
				],
				'timeout' => 30,
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
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code === 200 ) {
			return [
				'success' => true,
				'message' => __( 'Successfully connected to GoHighLevel!', 'ghl-crm-integration' ),
				'data'    => [
					'status_code' => $status_code,
					'preview'     => substr( $body, 0, 200 ),
				],
			];
		}

		// Handle authentication errors
		if ( $status_code === 401 ) {
			return [
				'success' => false,
				'message' => __( 'Authentication failed. Please verify your API key is correct and has not expired.', 'ghl-crm-integration' ),
			];
		}

		if ( $status_code === 403 ) {
			return [
				'success' => false,
				'message' => __( 'Access denied. Please verify your API key has access to this location.', 'ghl-crm-integration' ),
			];
		}

		// Generic error
		return [
			'success' => false,
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'Connection failed with status code: %d', 'ghl-crm-integration' ),
				$status_code
			),
		];
	}

	/**
	 * Handle API error responses
	 *
	 * @param int   $status_code HTTP status code
	 * @param array $response    Decoded response body
	 * @return void
	 * @throws ApiException
	 */
	private function handle_error_response( int $status_code, array $response ): void {
		if ( $status_code >= 200 && $status_code < 300 ) {
			return; // Success
		}

		$sanitized_response = $this->sanitize_response_payload( $response );

		// Extract error message - handle both string and array formats
		$error_raw = $response['message'] ?? $response['error'] ?? esc_html__( 'Unknown API error', 'ghl-crm-integration' );

		if ( is_array( $error_raw ) ) {
			// Convert array to readable string
			$error_message = implode(
				', ',
				array_map(
					function ( $item ) {
						return is_string( $item ) ? $item : wp_json_encode( $item );
					},
					$error_raw
				)
			);
		} else {
			$error_message = (string) $error_raw;
		}

		// Rate limit exceeded
		if ( 429 === $status_code ) {
			$headers      = isset( $sanitized_response['headers'] ) && is_array( $sanitized_response['headers'] ) ? $sanitized_response['headers'] : [];
			$retry_after  = $headers['retry-after'] ?? $headers['Retry-After'] ?? 60;
			$response_body = $sanitized_response;
			throw new RateLimitException(
				sprintf(
				/* translators: %d: Seconds until retry */
					esc_html__( 'Rate limit exceeded. Retry after %d seconds.', 'ghl-crm-integration' ),
					(int) $retry_after
				),
				(int) $retry_after,
				$response_body // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitized via sanitize_response_payload()
			); 
		}

		// Authentication error
		if ( 401 === $status_code || 403 === $status_code ) {
			$response_body = $sanitized_response;
			throw new AuthenticationException( esc_html( $error_message ), $response_body ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitized via sanitize_response_payload()
		}

		// Generic API error
		$response_body = $sanitized_response;
		throw new ApiException( esc_html( $error_message ), (int) $status_code, $response_body ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitized via sanitize_response_payload()
	}

	/**
	 * Sanitize response payload before attaching to exceptions.
	 *
	 * @param mixed $payload Raw payload data from the API.
	 * @return array Sanitized payload safe for logging or exception context.
	 */
	private function sanitize_response_payload( $payload ): array {
		if ( ! is_array( $payload ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $payload as $key => $value ) {
			$sanitized[ $key ] = $this->sanitize_response_value( $value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize individual payload values recursively.
	 *
	 * @param mixed $value Payload value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_response_value( $value ) {
		if ( is_array( $value ) ) {
			return $this->sanitize_response_payload( $value );
		}

		if ( is_object( $value ) ) {
			return $this->sanitize_response_payload( (array) $value );
		}

		if ( is_string( $value ) ) {
			return $this->sanitize_response_scalar( $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return $this->sanitize_response_scalar( (string) wp_json_encode( $value ) );
	}

	/**
	 * Sanitize scalar payload value.
	 *
	 * @param string $value Raw string value.
	 * @return string Sanitized string.
	 */
	private function sanitize_response_scalar( string $value ): string {
		return sanitize_text_field( wp_strip_all_tags( $value ) );
	}
}
