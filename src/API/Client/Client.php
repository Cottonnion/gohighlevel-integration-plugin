<?php
declare(strict_types=1);

namespace GHL_CRM\API\Client;

use GHL_CRM\API\Exceptions\ApiException;
use GHL_CRM\API\Exceptions\RateLimitException;
use GHL_CRM\API\Exceptions\AuthenticationException;
use GHL_CRM\Utilities\FileLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Client
 *
 * Central HTTP client for all GoHighLevel API communication.
 * Implements OAuth2 authentication with automatic token lifecycle management.
 *
 * Authentication Strategy:
 * - Primary: OAuth2 Bearer tokens obtained via labgenz.com proxy (keeps client secret server-side)
 * - Fallback: Manual Location API Key for simple setups
 *
 * Token Lifecycle:
 * - Proactive refresh 60s before expiry via ensure_fresh_access_token()
 * - Reactive refresh on 401/403 via handle_http_response() filter and request() retry
 * - Circuit breaker after 3 consecutive failures (5-minute cooldown)
 * - Cross-process mutex lock prevents concurrent refresh races
 * - Reconnect API as emergency recovery when refresh proxy is unreachable
 *
 * Auto-Recovery (handle_http_response):
 * - Duplicate contacts: Auto-converts POST→PUT on duplicate detection
 * - Deleted/merged contacts: Email re-lookup, meta update, and request retry
 * - Deleted contacts on DELETE: Silent success (idempotent)
 *
 * Rate Limiting:
 * - Tracks X-RateLimit-* headers from every response
 * - Throws RateLimitException on 429 with retry-after value
 *
 * Multisite:
 * - All token storage is multisite-aware via SettingsManager
 * - Circuit breaker uses per-site transients
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/Client
 */
class Client implements ClientInterface {
	// =========================================================================
	// Constants — API Endpoints
	// =========================================================================

	/**
	 * GoHighLevel API base URL.
	 *
	 * All REST endpoints are relative to this root.
	 *
	 * @var string
	 */
	private const BASE_URL = 'https://services.leadconnectorhq.com';

	/**
	 * OAuth2 authorization URL.
	 *
	 * Users are redirected here to choose a location and grant scopes.
	 *
	 * @var string
	 */
	private const OAUTH_AUTH_URL = 'https://marketplace.leadconnectorhq.com/oauth/chooselocation';

	/**
	 * OAuth proxy base URL (labgenz.com server).
	 *
	 * All token exchange/refresh requests route through this proxy so the
	 * OAuth client secret is never exposed in plugin source or browser.
	 *
	 * Endpoints:
	 * - /exchange-token — Authorization code → access + refresh tokens
	 * - /refresh-token  — Refresh token → new access + refresh tokens
	 * - /reconnect      — Location ID → new authorization code (emergency)
	 *
	 * @var string
	 */
	private const OAUTH_PROXY_URL = 'https://labgenz.com/wp-json/ghl-proxy/v1';

	/**
	 * OAuth2 Client ID (Public - safe to expose)
	 * Production OAuth App Client ID
	 *
	 * @var string
	 */
	private const OAUTH_CLIENT_ID = '68ff9baa25051d0ca83341e9-mh9cljcg';

	// =========================================================================
	// Properties — OAuth State
	// =========================================================================

	/**
	 * Current OAuth2 access token (Bearer).
	 *
	 * Loaded from DB on construct and refreshed in-memory after each
	 * successful token refresh.
	 *
	 * @var string
	 */
	private string $access_token = '';

	/**
	 * Unix timestamp when the current access token expires.
	 *
	 * Used by ensure_fresh_access_token() to trigger proactive refresh
	 * 60 seconds before actual expiry.
	 *
	 * @var int
	 */
	private int $access_token_expires_at = 0;

	/**
	 * Throttle repeated refresh attempts within a short window to avoid loops.
	 *
	 * @var int
	 */
	private static int $last_refresh_attempt_ts = 0;

	/**
	 * Store last refresh error message for reuse in throttling responses.
	 *
	 * @var string|null
	 */
	private static ?string $last_refresh_error = null;

	// =========================================================================
	// Constants — Circuit Breaker
	// =========================================================================

	/**
	 * Transient key for tracking consecutive refresh failures.
	 *
	 * Stores an array with 'failures' count and 'last_failed' timestamp.
	 *
	 * @var string
	 */
	private const CIRCUIT_BREAKER_KEY = 'ghl_crm_refresh_circuit_breaker';

	/**
	 * Minutes to wait after hitting the failure threshold before retrying.
	 *
	 * @var int
	 */
	private const CIRCUIT_BREAKER_COOLDOWN = 5;

	/**
	 * Max consecutive refresh failures before the circuit opens.
	 *
	 * Once opened, all refresh attempts are blocked until the cooldown expires
	 * (except for the reconnect API recovery path).
	 *
	 * @var int
	 */
	private const CIRCUIT_BREAKER_THRESHOLD = 3;

	// =========================================================================
	// Constants — Throttle & Locking
	// =========================================================================

	/**
	 * Transient key for throttling reconnect attempts.
	 *
	 * Enforces a 2-minute cooldown between reconnect API calls to avoid
	 * hammering the proxy when it is down.
	 *
	 * @var string
	 */
	private const RECONNECT_THROTTLE_KEY = 'ghl_crm_reconnect_throttle';

	/**
	 * Transient key for cross-process refresh mutex.
	 *
	 * When one PHP process is refreshing the token, other processes that
	 * detect this lock will wait 0.5s and reload settings from DB instead
	 * of issuing a parallel refresh request.
	 *
	 * @var string
	 */
	private const REFRESH_LOCK_KEY = 'ghl_crm_token_refresh_lock';

	/**
	 * Duration in seconds the refresh lock is held.
	 *
	 * @var int
	 */
	private const REFRESH_LOCK_TTL = 30;

	/**
	 * OAuth2 refresh token used to obtain fresh access tokens.
	 *
	 * Rotated on every refresh; the new value is persisted to DB immediately.
	 *
	 * @var string
	 */
	private string $refresh_token = '';

	/**
	 * Manual Location API Key (fallback when OAuth is not configured).
	 *
	 * If set and no OAuth access token exists, request() uses this for
	 * Bearer authentication instead.
	 *
	 * @var string
	 */
	private string $token = '';

	// =========================================================================
	// Properties — Request State
	// =========================================================================

	/**
	 * GoHighLevel location (sub-account) ID.
	 *
	 * Appended as `locationId` query parameter to most API requests
	 * via build_url() unless the endpoint path already contains a location.
	 *
	 * @var string
	 */
	private string $location_id = '';

	/**
	 * API version header sent with every request.
	 *
	 * GHL uses date-based versioning ("Version" header).
	 * Default: 2021-07-28 (v1 endpoints).
	 *
	 * @var string
	 */
	private string $api_version = '2021-07-28';

	/**
	 * Headers from the most recent API response.
	 *
	 * Stored after every request() call and exposed via
	 * get_last_response_headers() for rate-limit inspection.
	 *
	 * @var array
	 */
	private array $last_response_headers = [];

	/**
	 * When true, suppresses automatic OAuth token refresh on 401/403.
	 *
	 * Set during manual API key testing so a failing key doesn't trigger
	 * an unrelated refresh attempt on the OAuth tokens.
	 *
	 * @var bool
	 */
	private bool $skip_oauth_refresh = false;

	// =========================================================================
	// Singleton
	// =========================================================================

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
	 * Initialize WordPress hooks.
	 *
	 * Registers the global HTTP response filter that intercepts every
	 * outgoing wp_remote_* call to GHL endpoints for:
	 * - Duplicate contact auto-recovery (POST→PUT)
	 * - Deleted/merged contact re-lookup and retry
	 * - 401/403 automatic token refresh and retry
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Global HTTP response filter for automatic token refresh on ALL API calls
		add_filter( 'http_response', [ $this, 'handle_http_response' ], 50, 3 );
	}

	/**
	 * Handle HTTP response globally.
	 *
	 * Hooked into WordPress `http_response` filter at priority 50.
	 * Only processes responses from `services.leadconnectorhq.com` or
	 * `rest.gohighlevel.com`; all other URLs pass through untouched.
	 *
	 * Recovery Strategies (in order of evaluation):
	 *
	 * 1. **Duplicate Contact (400)** — If GHL returns "does not allow duplicated
	 *    contacts" with a matching contactId, the original POST is converted to
	 *    a PUT against `/contacts/{id}` and retried immediately.
	 *
	 * 2. **Contact Not Found (404/400)** — For PUT/DELETE on a vanished contact:
	 *    - Email re-lookup via `GET /contacts?query={email}`
	 *    - If found under a new ID (merge scenario): update all WP user meta,
	 *      then retry the original request against the new ID.
	 *    - If not found (hard delete): convert PUT→POST to re-create, or
	 *      return silent success for DELETE (idempotent).
	 *
	 * 3. **Auth Error (401/403)** — If the error message matches known token
	 *    strings, refresh the access token and retry. On failure, shows an
	 *    admin notice and returns WP_Error.
	 *
	 * Safety:
	 * - Skips token/reconnect URLs to avoid infinite loops
	 * - Respects skip_oauth_refresh flag for manual key testing
	 * - Circuit breaker blocks frontend refreshes when proxy is failing
	 *
	 * @param array|\WP_Error $response HTTP response or WP_Error.
	 * @param array           $args     HTTP request arguments.
	 * @param string          $url      Request URL.
	 * @return array|\WP_Error Modified or original response.
	 */
	public function handle_http_response( $response, $args, $url ) {
		// Only handle GoHighLevel API requests
		if ( false === strpos( $url, 'services.leadconnectorhq.com' ) &&
				false === strpos( $url, 'rest.gohighlevel.com' ) ) {
			return $response;
		}

		// Skip if it's a token request (avoid infinite loop)
		if ( false !== strpos( $url, '/oauth/token' ) ||
				false !== strpos( $url, '/oauth/reconnect' ) ) {
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
				$body_json['message'] === 'This location does not allow duplicated contacts.' &&
				isset( $body_json['meta']['matchingField'] ) &&
				$body_json['meta']['matchingField'] === 'email' ) {

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

				// Remove query string first, then strip contacts endpoint
				$base_url = preg_replace( '/\?.*$/', '', $url ); // Remove ?locationId=...
				$base_url = preg_replace( '/contacts\/?$/', '', $base_url );
				$new_url  = $base_url . 'contacts/' . $contact_id;

				// Retry as update request
				return wp_remote_request( $new_url, $args );
			}
		}

		// Handle contact not found (deleted/merged in GHL) - Auto-recovery
		if ( 404 === $response_code ||
				( 400 === $response_code &&
					isset( $body_json['error'] ) &&
					( stripos( $body_json['error'], 'Contact with id' ) !== false ||
						stripos( $body_json['message'] ?? '', 'not found' ) !== false ) ) ) {

			// Only attempt recovery for PUT/DELETE requests (updates/deletes on existing contacts)
			if ( in_array( $args['method'], [ 'PUT', 'DELETE' ], true ) ) {
				// Extract email from request body for lookup
				$request_body = json_decode( $args['body'] ?? '{}', true );
				$email        = $request_body['email'] ?? null;

				// Extract old contact ID from URL
				if ( preg_match( '/contacts\/([a-zA-Z0-9_-]+)/', $url, $matches ) ) {
					$old_contact_id = $matches[1];

					if ( $email ) {
						try {
							// Try to find contact by email (they may have been merged)
							$search_response = $this->get(
								'contacts',
								[ 'query' => $email ]
							);

							if ( ! empty( $search_response['contacts'][0]['id'] ) ) {
								// Contact found by email - they merged it
								$new_contact    = $search_response['contacts'][0];
								$new_contact_id = $new_contact['id'];

								// Only continue if we found a different contact ID
								if ( $new_contact_id !== $old_contact_id ) {
									// Find WordPress user(s) with this old contact ID
									global $wpdb;
									$location_id = $this->location_id;

									// Check location-scoped key
									$location_meta_key = '_ghl_contact_id_' . $location_id;

									// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
									$user_ids = $wpdb->get_col(
										$wpdb->prepare(
											"SELECT user_id FROM {$wpdb->usermeta} 
											WHERE meta_key = %s 
											AND meta_value = %s",
											$location_meta_key,
											$old_contact_id
										)
									);

									// Update all found users using TagManager for proper location-scoped storage
									$tag_manager   = \GHL_CRM\Core\TagManager::get_instance();
									$updated_count = 0;

									foreach ( array_unique( $user_ids ) as $user_id ) {
										$tag_manager->store_user_contact_id( (int) $user_id, $new_contact_id, $location_id );
										++$updated_count;
									}

									// Log the auto-recovery
									// if ( $updated_count > 0 ) {
									// error_log(
									// sprintf(
									// 'GHL Auto-Recovery (MERGED): Contact %s was merged into %s (email: %s). Updated %d user(s).',
									// $old_contact_id,
									// $new_contact_id,
									// $email,
									// $updated_count
									// )
									// );
									// }

									// Retry request with new contact ID
									$new_url = str_replace(
										"contacts/{$old_contact_id}",
										"contacts/{$new_contact_id}",
										$url
									);

									return wp_remote_request( $new_url, $args );
								}
							} else {
								// Contact not found by email - they deleted it completely
								// For PUT requests, convert to POST to create a new contact
								if ( 'PUT' === $args['method'] ) {
									// Add locationId back to body for creation
									$contact_data = $request_body;
									if ( ! isset( $contact_data['locationId'] ) && ! empty( $this->location_id ) ) {
										$contact_data['locationId'] = $this->location_id;
									}

									// Change to POST and remove contact ID from URL
									$args['method'] = 'POST';
									$args['body']   = wp_json_encode( $contact_data );
									$new_url        = preg_replace( '#/contacts/[a-zA-Z0-9_-]+#', '/contacts', $url );

									// Retry as POST (create)
									$create_response = wp_remote_request( $new_url, $args );

									// Update WordPress database with new contact ID if successful
									if ( ! is_wp_error( $create_response ) &&
											in_array( wp_remote_retrieve_response_code( $create_response ), [ 200, 201 ], true ) ) {
										$create_body = json_decode( wp_remote_retrieve_body( $create_response ), true );

										if ( ! empty( $create_body['contact']['id'] ) ) {
											$new_contact_id = $create_body['contact']['id'];

											// Update database
											global $wpdb;
											$location_id       = $this->location_id;
											$location_meta_key = '_ghl_contact_id_' . $location_id;

											// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
											$user_ids = $wpdb->get_col(
												$wpdb->prepare(
													"SELECT user_id FROM {$wpdb->usermeta} 
													WHERE meta_key = %s 
													AND meta_value = %s",
													$location_meta_key,
													$old_contact_id
												)
											);

											$tag_manager = \GHL_CRM\Core\TagManager::get_instance();
											foreach ( array_unique( $user_ids ) as $user_id ) {
												$tag_manager->store_user_contact_id( (int) $user_id, $new_contact_id, $location_id );
											}
										}
									}

									return $create_response;
								}

								// For DELETE requests on non-existent contacts, succeed silently
								if ( 'DELETE' === $args['method'] ) {

									// Return fake success response
									return [
										'success' => true,
										'message' => 'Contact already deleted',
									];
								}
							}
						} catch ( \Exception $e ) {
							// Email lookup failed, continue with original error
							error_log(
								sprintf(
									'GHL Auto-Recovery failed for contact %s (email: %s): %s',
									$old_contact_id,
									$email,
									$e->getMessage()
								)
							);
						}
					}
				}
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
				if ( false !== stripos( $error_message, $error_str ) ) {
					$is_token_error = true;
					break;
				}
			}

			if ( $is_token_error ) {
				try {
					// Only attempt to refresh if we have OAuth tokens
					if ( ! empty( $this->refresh_token ) ) {
						// Skip refresh on frontend if circuit breaker is open (avoid blocking page loads)
						if ( ! is_admin() && $this->is_circuit_breaker_open() ) {
							$this->log_oauth_event( 'Frontend refresh skipped: circuit breaker open' );
							return $response;
						}

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

					// Log failure context for debugging
					$this->log_oauth_event(
						'Auto-refresh failed after 401/403',
						[
							'url'    => $url,
							'error'  => $e->getMessage(),
							'status' => $response_code,
							'body'   => $body_json,
						]
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
	 * Load settings from options (multisite-aware).
	 *
	 * Reads all OAuth tokens, manual API key, location ID, and API version
	 * from SettingsManager. Called once during construction and again by
	 * reload_settings() when another process may have refreshed tokens.
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

		if ( ! empty( $settings['oauth_expires_at'] ) ) {
			$this->access_token_expires_at = (int) $settings['oauth_expires_at'];
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
	 * Reload settings from the database.
	 *
	 * Used after a failed refresh to check whether a concurrent process
	 * already saved valid tokens, avoiding unnecessary reconnect attempts.
	 *
	 * @return void
	 */
	public function reload_settings(): void {
		$this->load_settings();
	}

	/**
	 * Set API token
	 *
	 * @param string $token API token.
	 * @return void
	 */
	public function set_token( string $token ): void {
		$this->token = $token;
	}

	/**
	 * Set location ID
	 *
	 * @param string $location_id Location ID.
	 * @return void
	 */
	public function set_location_id( string $location_id ): void {
		$this->location_id = $location_id;
	}

	/**
	 * Skip OAuth token refresh for manual API key testing
	 *
	 * @param bool $skip Whether to skip OAuth refresh.
	 * @return void
	 */
	public function set_skip_oauth_refresh( bool $skip ): void {
		$this->skip_oauth_refresh = $skip;
	}

	/**
	 * Generate the OAuth2 authorization URL.
	 *
	 * Builds the marketplace chooselocation URL with all required scopes.
	 * The redirect_uri always points to labgenz.com/wp-json/ghl/v1/callback
	 * (the proxy), which then forwards back to the WordPress site.
	 *
	 * Scopes requested:
	 * - contacts.readonly / contacts.write
	 * - locations/tags.readonly / locations/tags.write
	 * - locations/customFields.readonly / locations/customFields.write
	 * - opportunities.readonly / opportunities.write
	 * - workflows.readonly / forms.readonly / forms.write
	 * - objects/schema + record + associations (Custom Objects)
	 *
	 * @param string $redirect_uri Unused directly (proxy handles redirect).
	 * @param string $return_url   The WP admin URL to return to after auth; passed as `state`.
	 * @return string Full authorization URL with query params.
	 */
	public function get_oauth_authorization_url( string $redirect_uri, string $return_url ): string {
		$params = [
			'client_id'     => self::OAUTH_CLIENT_ID,
			'redirect_uri'  => 'https://labgenz.com/wp-json/ghl/v1/callback',
			'scope'         => implode(
				' ',
				[
					'contacts.readonly',                // View Contacts
					'contacts.write',                   // Edit Contacts
					'locations/tags.readonly',          // View Tags
					'locations/tags.write',             // Edit Tags
					'locations/customFields.readonly',  // View Custom Fields
					'locations/customFields.write',     // Edit Custom Fields
					'opportunities.readonly',           // View Opportunities
					'opportunities.write',              // Edit Opportunities
					'workflows.readonly',               // View Workflows
					'forms.readonly',                   // View Forms
					'forms.write',                      // Edit Forms
					'objects/schema.readonly',          // View Objects Schema
					'objects/schema.write',             // Edit Objects Schema
					'objects/record.readonly',          // View Objects Records
					'objects/record.write',             // Edit Objects Records
					'associations.readonly',            // View Associations
					'associations.write',               // Write Associations
					'associations/relation.write',      // Write Associations Relations
				]
			),
			'response_type' => 'code',
			'state'         => $return_url,
		];

		return self::OAUTH_AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Exchange an authorization code for OAuth2 tokens.
	 *
	 * Sends the code to the labgenz.com proxy which pairs it with the
	 * client secret and calls GHL’s `/oauth/token` endpoint.
	 *
	 * On success, both access_token and refresh_token are updated in-memory
	 * (caller is responsible for persisting via save_oauth_tokens).
	 *
	 * @param string $code         Authorization code from the OAuth callback.
	 * @param string $redirect_uri Redirect URI that was used during authorization.
	 * @return array Decoded token payload (access_token, refresh_token, expires_in, etc.).
	 * @throws ApiException On network error or invalid response from proxy.
	 */
	public function exchange_code_for_token( string $code, string $redirect_uri ): array {
		$data = [
			'code'         => $code,
			'redirect_uri' => $redirect_uri,
		];

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $data ),
			'timeout' => 30,
		];

		$proxy_url = self::OAUTH_PROXY_URL . '/exchange-token';
		$response  = wp_remote_request( $proxy_url, $args );
		$this->log_oauth_event( 'Exchange token proxy response', [ 'status' => is_wp_error( $response ) ? 'error' : wp_remote_retrieve_response_code( $response ) ] );

		if ( is_wp_error( $response ) ) {
			self::$last_refresh_error = $response->get_error_message();
			$this->log_oauth_event( 'Exchange token WP_Error', [ 'error' => $response->get_error_message() ] );
			throw new ApiException(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'Token exchange failed: %s', 'ghl-crm-integration' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );
		$this->log_oauth_event(
			'Exchange token proxy body',
			[
				'status' => $status_code,
				'body'   => is_array( $decoded ) ? $decoded : $body,
			]
		);

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
	 * Ensure access token is still valid; refresh if close to expiry.
	 *
	 * This is a best-effort proactive refresh. If it fails (e.g. circuit breaker
	 * is open, proxy is down), we let the request proceed with the current token.
	 * The 401 handler will catch actual auth failures downstream.
	 *
	 * @return void
	 */
	private function ensure_fresh_access_token(): void {
		// If we do not know expiry, skip pre-emptive refresh (will rely on 401 handler)
		if ( $this->access_token_expires_at <= 0 ) {
			return;
		}

		// Refresh one minute before expiry to avoid mid-request failures
		$refresh_threshold = $this->access_token_expires_at - 60;
		if ( time() >= $refresh_threshold ) {
			try {
				$this->log_oauth_event( 'Proactive refresh before expiry', [ 'expires_at' => $this->access_token_expires_at ] );
				$this->refresh_access_token();
			} catch ( \Exception $e ) {
				// Best-effort: let the request proceed; 401 handler will catch it.
				$this->log_oauth_event( 'Proactive refresh failed (non-fatal)', [ 'error' => $e->getMessage() ] );
			}
		}
	}

	/**
	 * Log an OAuth event via the dedicated FileLogger.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context data.
	 * @param string $level   Log level (debug, info, warning, error).
	 * @return void
	 */
	private function log_oauth_event( string $message, array $context = [], string $level = 'info' ): void {
		FileLogger::get_instance()->log( 'oauth', $message, $context, $level );
	}

	/**
	 * Check if circuit breaker is open (too many recent failures).
	 *
	 * @return bool True if circuit is open and refresh should not be attempted.
	 */
	private function is_circuit_breaker_open(): bool {
		$circuit_data = get_transient( self::CIRCUIT_BREAKER_KEY );
		if ( ! $circuit_data ) {
			return false;
		}

		$failures    = $circuit_data['failures'] ?? 0;
		$last_failed = $circuit_data['last_failed'] ?? 0;

		// Circuit is open if we hit threshold
		if ( $failures >= self::CIRCUIT_BREAKER_THRESHOLD ) {
			$cooldown_expires = $last_failed + ( self::CIRCUIT_BREAKER_COOLDOWN * MINUTE_IN_SECONDS );
			if ( time() < $cooldown_expires ) {
				return true;
			}
			// Cooldown expired, reset circuit
			delete_transient( self::CIRCUIT_BREAKER_KEY );
		}

		return false;
	}

	/**
	 * Record a refresh failure in the circuit breaker.
	 *
	 * @return void
	 */
	private function record_refresh_failure(): void {
		$circuit_data = get_transient( self::CIRCUIT_BREAKER_KEY );
		$failures     = isset( $circuit_data['failures'] ) ? (int) $circuit_data['failures'] + 1 : 1;

		set_transient(
			self::CIRCUIT_BREAKER_KEY,
			[
				'failures'    => $failures,
				'last_failed' => time(),
			],
			self::CIRCUIT_BREAKER_COOLDOWN * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Clear the circuit breaker after successful refresh.
	 *
	 * @return void
	 */
	private function reset_circuit_breaker(): void {
		delete_transient( self::CIRCUIT_BREAKER_KEY );
	}

	/**
	 * Refresh the OAuth2 access token.
	 *
	 * Full refresh flow with multiple layers of protection:
	 *
	 * 1. **Guard: no refresh token** — Aborts immediately.
	 * 2. **Guard: circuit breaker open** — If ≥ 3 consecutive failures within
	 *    the last 5 minutes, blocks the attempt. Falls through to reconnect
	 *    API as an emergency recovery path.
	 * 3. **Guard: in-process throttle** — Skips if another attempt was made
	 *    less than 30 seconds ago (avoids hammering within one PHP process).
	 * 4. **Guard: cross-process mutex** — If another PHP process holds the
	 *    refresh lock, waits 0.5s then reloads settings from DB. If tokens
	 *    are now fresh, returns immediately.
	 * 5. **Primary path** — POSTs refresh_token to the proxy’s `/refresh-token`.
	 * 6. **Fallback: reconnect API** — If the proxy returns an error or is
	 *    unreachable, attempts the GHL reconnect endpoint to obtain a new
	 *    authorization code, then exchanges it for fresh tokens.
	 * 7. **Invalid grant** — If GHL returns `invalid_grant`, tokens are cleared
	 *    and the user must reconnect manually.
	 *
	 * On success:
	 * - Updates in-memory access_token / refresh_token / expires_at
	 * - Persists to DB via save_oauth_tokens()
	 * - Resets circuit breaker
	 * - Releases refresh lock
	 *
	 * Timeout:
	 * - Admin context: 15s
	 * - Frontend context: 8s (avoids blocking page loads)
	 *
	 * @return array Decoded token payload from proxy.
	 * @throws ApiException On all recovery paths exhausted.
	 */
	public function refresh_access_token(): array {
		if ( empty( $this->refresh_token ) ) {
			// If circuit breaker is open but no tokens exist, clear it to avoid confusion
			if ( $this->is_circuit_breaker_open() ) {
				$this->reset_circuit_breaker();
			}
			$this->log_oauth_event( 'Refresh aborted: no refresh token stored' );
			throw new ApiException( esc_html__( 'No refresh token available. Please connect your GoHighLevel account.', 'ghl-crm-integration' ) );
		}

		// Check circuit breaker (prevents hammering failing proxy)
		if ( $this->is_circuit_breaker_open() ) {
			$this->log_oauth_event( 'Refresh blocked: circuit breaker open (too many recent failures)' );

			// Circuit breaker blocks refresh, but still attempt reconnect API as recovery path
			// Reconnect uses a different GHL endpoint and may work even when refresh proxy is failing
			if ( ! empty( $this->location_id ) ) {
				try {
					$this->log_oauth_event( 'Circuit breaker open, attempting reconnect recovery', [ 'location_id' => $this->location_id ] );
					$auth_code                     = $this->reconnect_api();
					$redirect_uri                  = admin_url( 'admin.php?page=ghl-crm-settings' );
					$token_payload                 = $this->exchange_code_for_token( $auth_code, $redirect_uri );
					$expires_at                    = time() + ( $token_payload['expires_in'] ?? 3600 );
					$this->access_token_expires_at = $expires_at;
					$this->save_oauth_tokens( $expires_at );
					$this->reset_circuit_breaker();
					self::$last_refresh_attempt_ts = 0;
					$this->log_oauth_event( 'Reconnect recovered from circuit breaker state', [ 'expires_at' => $expires_at ] );

					return $token_payload;
				} catch ( \Exception $reconnect_error ) {
					$this->log_oauth_event( 'Reconnect also failed during circuit breaker', [ 'error' => $reconnect_error->getMessage() ] );
					// Fall through to circuit breaker error
				}
			}

			throw new ApiException(
				sprintf(
					/* translators: %d: cooldown minutes */
					esc_html__( 'Token refresh temporarily disabled due to repeated failures. Please try again in %d minutes or reconnect your account.', 'ghl-crm-integration' ),
					self::CIRCUIT_BREAKER_COOLDOWN
				)
			);
		}

		// Throttle repeated attempts within 30 seconds to avoid hammering
		$now = time();
		if ( self::$last_refresh_attempt_ts && ( $now - self::$last_refresh_attempt_ts ) < 30 ) {
			$this->log_oauth_event(
				'Refresh skipped: throttled',
				[
					'seconds_since_last' => $now - self::$last_refresh_attempt_ts,
					'last_error'         => self::$last_refresh_error,
				]
			);
			throw new ApiException(
				self::$last_refresh_error
					? sprintf( esc_html__( 'Recent refresh attempt failed: %s', 'ghl-crm-integration' ), self::$last_refresh_error )
					: esc_html__( 'Recent refresh attempt in progress or just failed. Reconnect required.', 'ghl-crm-integration' )
			);
		}

		self::$last_refresh_attempt_ts = $now;
		self::$last_refresh_error      = null;

		// Cross-process mutex: if another process is already refreshing, wait and reload
		$lock_owner = get_transient( self::REFRESH_LOCK_KEY );
		if ( $lock_owner && getmypid() !== $lock_owner ) {
			$this->log_oauth_event( 'Refresh deferred: another process holds the lock', [ 'lock_owner' => $lock_owner ] );
			// Wait briefly for the other process to finish, then reload tokens from DB
			usleep( 500000 ); // 0.5 seconds
			$this->reload_settings();

			// If the other process succeeded, we now have fresh tokens
			if ( $this->access_token_expires_at > time() + 60 ) {
				$this->log_oauth_event( 'Refresh resolved by another process', [ 'expires_at' => $this->access_token_expires_at ] );
				return [
					'access_token'  => $this->access_token,
					'refresh_token' => $this->refresh_token,
				];
			}
			// Other process may have failed — fall through and try ourselves
		}

		// Acquire the refresh lock for this process
		set_transient( self::REFRESH_LOCK_KEY, getmypid(), self::REFRESH_LOCK_TTL );

		// Handle edge case where refresh token might be corrupted (not a string)
		if ( ! is_string( $this->refresh_token ) ) {
			// Try reconnect API as fallback
			try {
				$auth_code = $this->reconnect_api();
				// Exchange auth code for new tokens
				$redirect_uri = admin_url( 'admin.php?page=ghl-crm-admin' );
				return $this->exchange_code_for_token( $auth_code, $redirect_uri );
			} catch ( ApiException $e ) {
				$this->log_oauth_event( 'Refresh token corrupted and reconnect failed', [ 'error' => $e->getMessage() ] );
				throw new ApiException(
					esc_html__( 'Refresh token is invalid and reconnect failed', 'ghl-crm-integration' )
				);
			}
		}

		$this->log_oauth_event( 'Attempting token refresh', [ 'expires_in' => $this->access_token_expires_at - time() ] );

		$data = [
			'refresh_token' => $this->refresh_token,
		];

		// Use shorter timeout on frontend to avoid blocking page loads
		$timeout = is_admin() ? 15 : 8;

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $data ),
			'timeout' => $timeout,
		];

		$proxy_url = self::OAUTH_PROXY_URL . '/refresh-token';
		$response  = wp_remote_request( $proxy_url, $args );
		$this->log_oauth_event( 'Refresh token proxy response', [ 'status' => is_wp_error( $response ) ? 'error' : wp_remote_retrieve_response_code( $response ) ] );

		if ( is_wp_error( $response ) ) {
			self::$last_refresh_error = $response->get_error_message();
			$this->record_refresh_failure();
			$this->log_oauth_event( 'Refresh token WP_Error', [ 'error' => $response->get_error_message() ] );

			// Proxy timed out or is unreachable - try reconnect API as fallback
			// Reconnect uses a different GHL endpoint and may succeed even when refresh proxy times out
			if ( ! empty( $this->location_id ) ) {
				try {
					$this->log_oauth_event( 'Proxy unreachable, attempting reconnect fallback', [ 'location_id' => $this->location_id ] );
					$auth_code                     = $this->reconnect_api();
					$redirect_uri                  = admin_url( 'admin.php?page=ghl-crm-settings' );
					$token_payload                 = $this->exchange_code_for_token( $auth_code, $redirect_uri );
					$expires_at                    = time() + ( $token_payload['expires_in'] ?? 3600 );
					$this->access_token_expires_at = $expires_at;
					$this->save_oauth_tokens( $expires_at );
					$this->reset_circuit_breaker();
					$this->log_oauth_event( 'Reconnect succeeded after proxy timeout', [ 'expires_at' => $expires_at ] );

					return $token_payload;
				} catch ( \Exception $reconnect_error ) {
					$this->log_oauth_event( 'Reconnect also failed after proxy timeout', [ 'error' => $reconnect_error->getMessage() ] );
					// Fall through to throw original error
				}
			}

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
		$this->log_oauth_event(
			'Refresh token endpoint body',
			[
				'status' => $status_code,
				'body'   => is_array( $decoded ) ? $decoded : $body,
			]
		);

		if ( $status_code !== 200 || empty( $decoded['access_token'] ) ) {
			$decoded_array            = is_array( $decoded ) ? $decoded : [];
			$error_message            = $decoded_array['message'] ?? ( is_string( $body ) ? $body : 'unknown' );
			self::$last_refresh_error = sprintf( 'Refresh HTTP %d: %s', $status_code, sanitize_text_field( (string) $error_message ) );
			$this->record_refresh_failure();

			// If refresh token is invalid, clear tokens and force reconnect to avoid loops
			if ( isset( $decoded_array['error'] ) && 'invalid_grant' === $decoded_array['error'] ) {
				$this->log_oauth_event( 'Refresh token marked invalid by provider; clearing stored tokens', $decoded_array );
				$this->clear_oauth_tokens();
				throw new ApiException(
					esc_html__( 'Refresh token is invalid. Please reconnect your GoHighLevel account.', 'ghl-crm-integration' ),
					(int) $status_code,
					$decoded_array
				);
			}

			// Fallback: attempt reconnect API once to recover tokens
			if ( ! empty( $this->location_id ) ) {
				try {
					$this->log_oauth_event( 'Primary refresh failed, attempting reconnect', [ 'location_id' => $this->location_id ] );
					$auth_code                     = $this->reconnect_api();
					$redirect_uri                  = admin_url( 'admin.php?page=ghl-crm-settings' );
					$token_payload                 = $this->exchange_code_for_token( $auth_code, $redirect_uri );
					$expires_at                    = time() + ( $token_payload['expires_in'] ?? 3600 );
					$this->access_token_expires_at = $expires_at;
					$this->save_oauth_tokens( $expires_at );
					$this->log_oauth_event( 'Reconnect succeeded after refresh failure', [ 'expires_at' => $expires_at ] );

					return $token_payload;
				} catch ( ApiException $reconnect_error ) {
					self::$last_refresh_error = $reconnect_error->getMessage();
					$this->log_oauth_event(
						'Reconnect attempt failed',
						[
							'refresh_error'   => $decoded_array['message'] ?? 'unknown',
							'reconnect_error' => $reconnect_error->getMessage(),
						]
					);
					// If reconnect also fails, bubble original refresh error with context
					throw new ApiException(
						sprintf(
							/* translators: 1: refresh error, 2: reconnect error */
							esc_html__( 'Failed to refresh access token (%1$s) and reconnect failed (%2$s)', 'ghl-crm-integration' ),
							esc_html( $decoded_array['message'] ?? 'unknown' ),
							esc_html( $reconnect_error->getMessage() )
						),
						(int) $status_code,
						$decoded_array
					);
				}
			}

			self::$last_refresh_error = $decoded_array['message'] ?? 'Failed to refresh access token';
			throw new ApiException(
				sanitize_text_field( self::$last_refresh_error ),
				(int) $status_code,
				$decoded_array // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitized via sanitize_response_payload()
			);
		}

		// Update tokens
		$this->access_token = $decoded['access_token'];
		if ( ! empty( $decoded['refresh_token'] ) ) {
			$this->refresh_token = $decoded['refresh_token'];
		}

		// Persist refreshed tokens and expiry so new requests use the latest values
		$expires_at                    = time() + ( $decoded['expires_in'] ?? 3600 );
		$this->access_token_expires_at = $expires_at;
		self::$last_refresh_error      = null;
		$this->save_oauth_tokens( $expires_at );

		// Reset circuit breaker on successful refresh
		$this->reset_circuit_breaker();

		// Release the refresh lock
		delete_transient( self::REFRESH_LOCK_KEY );

		$this->log_oauth_event( 'Token refresh succeeded', [ 'expires_at' => $expires_at ] );

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
	 * Reconnect API — emergency token recovery.
	 *
	 * When the refresh token is exhausted or the proxy is unreachable,
	 * this method calls GHL’s install-level reconnect endpoint (via proxy)
	 * to obtain a fresh authorization code without requiring user interaction.
	 *
	 * The returned authorization code must be exchanged via
	 * exchange_code_for_token() to obtain usable access + refresh tokens.
	 *
	 * Safety:
	 * - Requires a valid location_id (throws if missing)
	 * - 2-minute transient throttle prevents hammering
	 * - Timeout: 15s
	 *
	 * @return string Fresh authorization code.
	 * @throws ApiException On missing location ID, throttle, network error, or invalid response.
	 */
	public function reconnect_api(): string {
		if ( empty( $this->location_id ) ) {
			throw new ApiException( esc_html__( 'Missing location ID for HighLevel reconnect', 'ghl-crm-integration' ) );
		}

		// Throttle reconnect attempts (2-minute cooldown)
		if ( get_transient( self::RECONNECT_THROTTLE_KEY ) ) {
			throw new ApiException( esc_html__( 'Reconnect attempt throttled. Please wait before retrying.', 'ghl-crm-integration' ) );
		}
		set_transient( self::RECONNECT_THROTTLE_KEY, true, 2 * MINUTE_IN_SECONDS );

		$data = [
			'location_id' => $this->location_id,
		];

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $data ),
			'timeout' => 15,
		];

		$proxy_url = self::OAUTH_PROXY_URL . '/reconnect';
		$response  = wp_remote_request( $proxy_url, $args );

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

	// =========================================================================
	// HTTP Verb Helpers
	// =========================================================================

	/**
	 * Send a GET request to the GHL API.
	 *
	 * @param string $endpoint            API endpoint (e.g. 'contacts', 'contacts/{id}').
	 * @param array  $params              Query parameters appended to the URL.
	 * @param bool   $include_location_id Whether to auto-append locationId (default: true).
	 * @return array Decoded JSON response.
	 * @throws ApiException|AuthenticationException|RateLimitException
	 */
	public function get( string $endpoint, array $params = [], bool $include_location_id = true ): array {
		$url = $this->build_url( $endpoint, $params, $include_location_id );
		return $this->request( 'GET', $url );
	}

	/**
	 * Send a POST request to the GHL API.
	 *
	 * @param string $endpoint            API endpoint.
	 * @param array  $data                Request body (JSON-encoded automatically).
	 * @param bool   $include_location_id Whether to auto-append locationId (default: true).
	 * @return array Decoded JSON response.
	 * @throws ApiException|AuthenticationException|RateLimitException
	 */
	public function post( string $endpoint, array $data = [], bool $include_location_id = true ): array {
		$url = $this->build_url( $endpoint, [], $include_location_id );
		return $this->request( 'POST', $url, $data );
	}

	/**
	 * Send a PUT request to the GHL API.
	 *
	 * @param string $endpoint            API endpoint.
	 * @param array  $data                Request body (JSON-encoded automatically).
	 * @param bool   $include_location_id Whether to auto-append locationId (default: true).
	 * @return array Decoded JSON response.
	 * @throws ApiException|AuthenticationException|RateLimitException
	 */
	public function put( string $endpoint, array $data = [], bool $include_location_id = true ): array {
		$url = $this->build_url( $endpoint, [], $include_location_id );
		return $this->request( 'PUT', $url, $data );
	}

	/**
	 * Send a DELETE request to the GHL API.
	 *
	 * @param string $endpoint            API endpoint.
	 * @param bool   $include_location_id Whether to auto-append locationId (default: true).
	 * @param array  $data                Optional request body data.
	 * @return array Decoded JSON response.
	 * @throws ApiException|AuthenticationException|RateLimitException
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
	 * Get rate limit status from the last API response.
	 *
	 * Reads `X-RateLimit-*` headers stored by request().
	 * Useful for RateLimiter to decide whether to delay the next call.
	 *
	 * @return array{remaining: int, limit: int, reset: int}
	 */
	public function get_rate_limit_status(): array {
		$headers = $this->last_response_headers;

		return [
			'remaining' => isset( $headers['x-ratelimit-remaining'] ) ? (int) $headers['x-ratelimit-remaining'] : 0,
			'limit'     => isset( $headers['x-ratelimit-limit'] ) ? (int) $headers['x-ratelimit-limit'] : 0,
			'reset'     => isset( $headers['x-ratelimit-reset'] ) ? (int) $headers['x-ratelimit-reset'] : 0,
		];
	}

	// =========================================================================
	// Internal Request Engine
	// =========================================================================

	/**
	 * Build the full API URL from an endpoint path and optional query params.
	 *
	 * Automatically appends `locationId` unless:
	 * - $include_location_id is false
	 * - The endpoint path already contains `locations/{id}/`
	 * - locationId is already present in $params
	 *
	 * @param string $endpoint            Endpoint path (e.g. 'contacts/{id}').
	 * @param array  $params              Additional query parameters.
	 * @param bool   $include_location_id Whether to auto-append locationId.
	 * @return string Fully qualified URL.
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
	 * Execute an HTTP request against the GHL API.
	 *
	 * Core request engine used by all HTTP verb helpers (get/post/put/delete).
	 *
	 * Authentication:
	 * - Prefers OAuth2 access token when available
	 * - Falls back to manual Location API Key
	 * - Throws AuthenticationException if neither is configured
	 *
	 * Token Lifecycle:
	 * - Calls ensure_fresh_access_token() before sending (proactive refresh)
	 * - On 401, attempts refresh_access_token() and retries once
	 * - On refresh failure, reloads settings to check for concurrent refresh
	 * - Clears tokens only if genuinely expired (not just refreshed by another process)
	 *
	 * Response Handling:
	 * - Stores response headers for rate-limit inspection
	 * - Decodes JSON body; returns empty array for 2xx with empty body
	 * - Delegates error classification to handle_error_response()
	 *
	 * @param string $method HTTP method (GET, POST, PUT, DELETE).
	 * @param string $url    Fully qualified URL (from build_url).
	 * @param array  $data   Request body for POST/PUT/DELETE.
	 * @return array Decoded JSON response.
	 * @throws ApiException|AuthenticationException|RateLimitException
	 */
	private function request( string $method, string $url, array $data = [] ): array {
		// Proactively refresh if token is near expiry
		if ( $this->is_oauth_configured() && ! empty( $this->refresh_token ) ) {
			$this->ensure_fresh_access_token();
		}

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
		$this->log_oauth_event(
			'Request sent',
			[
				'method' => $method,
				'url'    => $url,
				'status' => is_wp_error( $response ) ? 'error' : wp_remote_retrieve_response_code( $response ),
			]
		);

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
				$this->log_oauth_event( 'Retrying request after 401 with refreshed token', [ 'url' => $url ] );

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
				// Refresh failed — reload settings to check if another process saved valid tokens
				$this->reload_settings();
				$this->log_oauth_event(
					'Refresh failed in request() 401 handler, reloaded settings',
					[
						'error'        => $e->getMessage(),
						'has_token'    => ! empty( $this->access_token ),
						'expires_at'   => $this->access_token_expires_at,
					]
				);
				// Only clear tokens if they are genuinely expired (not just refreshed by another process)
				if ( empty( $this->access_token ) || $this->access_token_expires_at <= time() ) {
					$this->clear_oauth_tokens();
				}
			}
		}

		// Decode JSON response
		$decoded = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// If the response is successful (2xx) with an empty body, return an empty array
			// Some GHL endpoints return 200/201 with no body
			if ( $status_code >= 200 && $status_code < 300 && '' === trim( $body ) ) {
				return [];
			}

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

	// =========================================================================
	// Token Persistence (multisite-aware)
	// =========================================================================

	/**
	 * Persist OAuth2 tokens to the database.
	 *
	 * Uses SettingsManager for multisite-safe storage.
	 * Called after every successful refresh or token exchange.
	 *
	 * @param int|null $expires_at Optional unix timestamp for token expiry.
	 * @return void
	 */
	private function save_oauth_tokens( ?int $expires_at = null ): void {
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();

		// Update individual settings using SettingsManager (multisite-aware)
		$settings_manager->update_setting( 'oauth_access_token', $this->access_token );
		$settings_manager->update_setting( 'oauth_refresh_token', $this->refresh_token );

		if ( null !== $expires_at ) {
			$this->access_token_expires_at = $expires_at;
			$settings_manager->update_setting( 'oauth_expires_at', $expires_at );
		}
	}

	/**
	 * Clear all OAuth2 tokens from in-memory state and the database.
	 *
	 * Called when:
	 * - GHL returns `invalid_grant` (token permanently revoked)
	 * - Admin disconnects the account
	 * - Refresh fails and tokens are genuinely expired
	 *
	 * Also resets the circuit breaker to avoid confusing
	 * "temporarily disabled" errors after a manual disconnect.
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

		// Clear circuit breaker when disconnecting to avoid confusing errors
		$this->reset_circuit_breaker();
	}

	// =========================================================================
	// Connection Testing
	// =========================================================================

	/**
	 * Test a manual API key connection.
	 *
	 * Performs a lightweight `GET /contacts/?limit=1` call using the provided
	 * token and location ID to verify credentials without side effects.
	 *
	 * Validation:
	 * - Rejects empty inputs
	 * - Detects JWT (temporary) tokens and advises the user to use a
	 *   permanent Location API Key instead
	 *
	 * @param string $api_token   The Location API Key to test.
	 * @param string $location_id The GHL location (sub-account) ID.
	 * @return array{success: bool, message: string, data?: array} Result with status and message.
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

	// =========================================================================
	// Error Handling & Response Sanitization
	// =========================================================================

	/**
	 * Classify and throw typed exceptions for non-2xx API responses.
	 *
	 * Exception mapping:
	 * - 429 → RateLimitException (includes retry-after seconds)
	 * - 401/403 → AuthenticationException
	 * - All others → ApiException
	 *
	 * All exception payloads are sanitized via sanitize_response_payload()
	 * before being attached as context.
	 *
	 * @param int   $status_code HTTP status code.
	 * @param array $response    Decoded JSON response body.
	 * @return void
	 * @throws ApiException|AuthenticationException|RateLimitException
	 */
	private function handle_error_response( int $status_code, array $response ): void {
		if ( $status_code >= 200 && $status_code < 300 ) {
			return; // Success
		}

		$sanitized_response = $this->sanitize_response_payload( $response );

		// Extract error message - handle both string and array formats
		$error_raw = isset( $response['message'] ) ? $response['message'] : ( isset( $response['error'] ) ? $response['error'] : esc_html__( 'Unknown API error', 'ghl-crm-integration' ) );

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
			$headers       = isset( $sanitized_response['headers'] ) && is_array( $sanitized_response['headers'] ) ? $sanitized_response['headers'] : [];
			$retry_after   = $headers['retry-after'] ?? $headers['Retry-After'] ?? 60;
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
	 * Sanitize an API response payload for safe exception context.
	 *
	 * Recursively walks the decoded response array and applies
	 * sanitize_text_field + wp_strip_all_tags to all string values.
	 * Non-array input returns an empty array.
	 *
	 * @param mixed $payload Raw decoded API response.
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
	 * Sanitize a single payload value recursively.
	 *
	 * Delegates arrays/objects to sanitize_response_payload(),
	 * strings to sanitize_response_scalar(), and passes through
	 * booleans, integers, floats, and null unchanged.
	 *
	 * @param mixed $value Raw value from the API response.
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
	 * Sanitize a scalar string value.
	 *
	 * Strips HTML tags and applies WordPress sanitize_text_field().
	 *
	 * @param string $value Raw string from the API response.
	 * @return string Sanitized string safe for logging.
	 */
	private function sanitize_response_scalar( string $value ): string {
		return sanitize_text_field( wp_strip_all_tags( $value ) );
	}
}
