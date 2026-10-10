<?php
declare(strict_types=1);

namespace Syncly\API\Client;

use Syncly\API\Exceptions\ApiException;
use Syncly\API\Exceptions\RateLimitException;
use Syncly\API\Exceptions\AuthenticationException;
use Syncly\Utilities\FileLogger;

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
	 * - Primary: OAuth2 Bearer tokens obtained via synclyforgohighlevel.com proxy (keeps client secret server-side)
 *
 * GHL Token Lifetimes (per official docs):
 * - Access token:  24 hours
 * - Refresh token: 1 year (single-use — rotated on every refresh)
 *
 * Token Lifecycle:
 * - Proactive refresh 30 min before expiry via ensure_fresh_access_token()
 * - Scheduled background refresh via Action Scheduler (every 20 min, 12h window)
 * - Reactive refresh on 401/403 via request() retry
 * - Automatic retry with longer timeout (25s) on proxy cURL timeouts
 * - Short cooldown after temporary refresh/rate-limit failures
 * - Cross-process mutex lock prevents concurrent refresh races
 * - Reconnect API as emergency recovery when refresh proxy is unreachable
 *
 * Low-Traffic Site Considerations:
 * - WP-Cron only fires on page visits; sites with few visitors may miss refresh windows
 * - The 12h scheduled refresh buffer covers this: any visit in the last 12h of a 24h token refreshes it
 * - Cron/CLI contexts get the same 15s timeout as admin (not the 8s frontend timeout)
 * - For truly zero-traffic sites, recommend server-side cron: wp-cron.php every 15 min
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
 * - Refresh cooldown uses per-site transients
 *
 * @package    Syncly
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
	 * OAuth proxy base URL (synclyforgohighlevel.com server).
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
	private const OAUTH_PROXY_URL = 'https://synclyforgohighlevel.com/wp-json/ghl-proxy/v1';

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
	 * 30 minutes before actual expiry.
	 *
	 * @var int
	 */
	private int $access_token_expires_at = 0;

	// =========================================================================
	// Constants — OAuth Cooldowns
	// =========================================================================

	/**
	 * Transient key for pausing refresh attempts after a temporary failure.
	 *
	 * @var string
	 */
	private const REFRESH_COOLDOWN_KEY = 'syncly_refresh_cooldown';

	/**
	 * Transient key for throttling global OAuth admin notices.
	 *
	 * @var string
	 */
	private const OAUTH_NOTICE_THROTTLE_KEY = 'syncly_oauth_notice_throttle';

	/**
	 * Transient key for throttling OAuth authorization-code exchange attempts.
	 *
	 * @var string
	 */
	private const TOKEN_EXCHANGE_COOLDOWN_KEY = 'syncly_token_exchange_cooldown';

	/**
	 * Minutes to wait after a temporary OAuth refresh or exchange failure.
	 *
	 * @var int
	 */
	private const OAUTH_COOLDOWN_MINUTES = 5;

	/**
	 * Maximum number of automatic retries for transient API failures.
	 */
	private const MAX_TRANSIENT_RETRIES = 3;

	/**
	 * Base delay for transient retries, in seconds.
	 */
	private const TRANSIENT_RETRY_BASE_DELAY = 1;

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
	private const RECONNECT_THROTTLE_KEY = 'syncly_reconnect_throttle';

	/**
	 * Transient key for cross-process refresh mutex.
	 *
	 * When one PHP process is refreshing the token, other processes that
	 * detect this lock will wait 0.5s and reload settings from DB instead
	 * of issuing a parallel refresh request.
	 *
	 * @var string
	 */
	/** @var \Syncly\API\OAuth\OptionMutex|null Owned refresh lease. */
	private ?\Syncly\API\OAuth\OptionMutex $refresh_mutex = null;
	/** @var int Credential revision originally loaded. */
	private int $oauth_revision                           = 0;

	private const REFRESH_LOCK_KEY = 'syncly_token_refresh_lock';

	/**
	 * Duration in seconds the refresh lock is held.
	 *
	 * @var int
	 */
	private const REFRESH_LOCK_TTL = 120;

	/**
	 * Seconds before token expiry to trigger proactive refresh.
	 *
	 * Set to 30 minutes to account for gaps between API requests
	 * (e.g. 1-hour tag cache intervals).
	 *
	 * @var int
	 */
	private const PROACTIVE_REFRESH_BUFFER = 30 * MINUTE_IN_SECONDS;

	/**
	 * Action Scheduler hook for background token refresh.
	 *
	 * @var string
	 */
	public const SCHEDULED_REFRESH_HOOK = 'syncly_refresh_oauth_token';

	/**
	 * How often (in seconds) the background refresh check runs.
	 *
	 * @var int
	 */
	private const SCHEDULED_REFRESH_INTERVAL = 20 * MINUTE_IN_SECONDS;

	/**
	 * OAuth2 refresh token used to obtain fresh access tokens.
	 *
	 * Rotated on every refresh; the new value is persisted to DB immediately.
	 *
	 * @var string
	 */
	private string $refresh_token = '';

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
	 * Set during probe checks so a failing probe does not trigger
	 * an unrelated refresh attempt on the OAuth tokens.
	 *
	 * @var bool
	 */
	private bool $skip_oauth_refresh = false;

	/**
	 * Flag to prevent duplicate 401 handling.
	 *
	 * When request() is retrying after a 401, this flag prevents
	 * handle_http_response() from also attempting a refresh.
	 *
	 * @var bool
	 */
	private bool $is_retrying_after_refresh = false;

	/**
	 * Flag indicating a refresh already failed during this request lifecycle.
	 *
	 * When true, subsequent calls to ensure_fresh_access_token() and the 401
	 * handler in request() will skip refresh entirely to avoid cascading
	 * failures and log spam within the same PHP process.
	 *
	 * @var bool
	 */
	private bool $refresh_failed_this_request = false;

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
		add_filter(
			'cron_schedules',
			static function ( array $schedules ): array {
				$schedules['syncly_oauth_20min'] = [
					'interval' => self::SCHEDULED_REFRESH_INTERVAL,
					'display'  => __( 'Syncly OAuth refresh', 'syncly' ),
				];
				return $schedules;
			}
		);
		add_action( 'init', [ $this, 'maybe_schedule_background_refresh' ], 999 );
		add_action(
			'admin_init',
			function (): void {
				$state = \Syncly\Core\Settings\SettingsRepository::get_instance()->get_oauth_state();
				if ( 'reconnect_required' === ( $state['oauth_health_status'] ?? '' ) ) {
					$this->queue_oauth_health_notice( 'Please reconnect your GoHighLevel account.' );
				}
			}
		);
		add_filter(
			'site_status_tests',
			static function ( array $tests ): array {
				$tests['direct']['syncly_server_cron'] = [
					'label' => __( 'Syncly background scheduling', 'syncly' ),
					'test'  => [ self::class, 'get_oauth_cron_health' ],
				];
				return $tests;
			}
		);

		// Defer scheduling to when Action Scheduler is fully initialized.
		// AS fires 'action_scheduler_init' on `init` priority 1, but the Client
		// singleton is typically created at `init` priority 0 — so AS is NOT
		// ready yet in the constructor. Hooking here guarantees it.
		if ( class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			$this->maybe_schedule_background_refresh();
		} else {
			add_action( 'action_scheduler_init', [ $this, 'maybe_schedule_background_refresh' ] );
		}
	}

	/**
	 * Recommend a server cron when the refresh job is overdue.
	 *
	 * @return array WordPress Site Health result.
	 */
	public static function get_oauth_cron_health(): array {
		$next = wp_next_scheduled( self::SCHEDULED_REFRESH_HOOK, [] );
		if ( function_exists( 'as_next_scheduled_action' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			$next = as_next_scheduled_action( self::SCHEDULED_REFRESH_HOOK, [], 'syncly' );
		}
		$overdue = is_int( $next ) && $next < time() - 2 * self::SCHEDULED_REFRESH_INTERVAL;
		return [
			'label'       => $overdue ? __( 'Syncly refresh is overdue; check server cron', 'syncly' ) : __( 'Syncly has no overdue refresh job', 'syncly' ),
			'status'      => $overdue ? 'recommended' : 'good',
			'badge'       => [
				'label' => 'Syncly',
				'color' => 'blue',
			],
			'description' => '<p>' . esc_html__( 'WordPress cron and Action Scheduler need requests to run. Configure a real server cron to invoke WordPress cron regularly on low-traffic sites; PHP cannot guarantee execution with no requests.', 'syncly' ) . '</p>',
			'actions'     => '',
			'test'        => 'syncly_server_cron',
		];
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

		// Register background token refresh handler
		add_action( self::SCHEDULED_REFRESH_HOOK, [ $this, 'handle_scheduled_refresh' ] );
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
	 * - Respects skip_oauth_refresh flag for probe requests
	 * - Refresh cooldown blocks retry storms when proxy is failing
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

		// Skip if request() is already retrying after a refresh (prevent double handling)
		if ( $this->is_retrying_after_refresh ) {
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
				// DELETE on a non-existent contact is idempotent, succeed silently.
				// This covers tag removal, workflow removal, etc. where the body
				// may not contain an email for a merged-contact lookup.
				if ( 'DELETE' === $args['method'] ) {
					return [
						'success' => true,
						'message' => 'Contact already deleted',
					];
				}

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
									$tag_manager   = \Syncly\Sync\TagManager::get_instance();
									$updated_count = 0;

									foreach ( array_unique( $user_ids ) as $user_id ) {
										$tag_manager->store_user_contact_id( (int) $user_id, $new_contact_id, $location_id );
										++$updated_count;
									}

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

											$tag_manager = \Syncly\Sync\TagManager::get_instance();
											foreach ( array_unique( $user_ids ) as $user_id ) {
												$tag_manager->store_user_contact_id( (int) $user_id, $new_contact_id, $location_id );
											}
										}
									}

									return $create_response;
								}
							}
						} catch ( \Exception $e ) {
							// Email lookup failed, continue with original error
							error_log(
								sprintf(
									'GHL Auto-Recovery failed for contact %s (email: %s): %s',
									$old_contact_id,
									'[redacted-email]',
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

			// Skip OAuth refresh if flag is set (e.g., during scope probes)
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

				// If refresh already failed this request, don't attempt again from this filter
				if ( $this->refresh_failed_this_request ) {
					return $response;
				}

				$this->log_oauth_event(
					'401/403 token error — about to attempt refresh',
					[
						'url'                     => $url,
						'response_code'           => $response_code,
						'error_category'          => 'api_auth_rejected',
						'has_refresh_token'       => ! empty( $this->refresh_token ),
						'location_id'             => $this->location_id ?? '(none)',
						'access_token_expires_at' => $this->access_token_expires_at ?? 0,
						'now'                     => time(),
					],
					'warning'
				);
				try {
					// Only attempt to refresh if we have OAuth tokens
					if ( ! empty( $this->refresh_token ) ) {
						if ( $this->is_refresh_cooldown_active() ) {
							$this->log_oauth_event( '401/403 refresh skipped: refresh cooldown active' );
							return $response;
						}

						// Attempt to refresh the access token
						$this->refresh_access_token();

						// Update authorization header with new token
						$args['headers']['Authorization'] = 'Bearer ' . $this->access_token;

						// Prevent handle_http_response from firing again on the retry
						$this->is_retrying_after_refresh = true;
						$retry_response                  = wp_remote_request( $url, $args );
						$this->is_retrying_after_refresh = false;

						return $retry_response;
					}
					// If no refresh token is available, just return the error response
					return $response;

				} catch ( \Exception $e ) {
					$this->refresh_failed_this_request = true;
					$this->queue_oauth_health_notice( $e->getMessage() );

					// Log failure context for debugging
					$this->log_oauth_event(
						'Auto-refresh failed after 401/403',
						[
							'url'    => $url,
							'error'  => $e->getMessage(),
							'status' => $response_code,
						]
					);

					// Return error
					return new \WP_Error(
						'token_refresh_failed',
						sprintf(
							/* translators: %s: Error message */
							__( 'Error refreshing access token: %s', 'syncly' ),
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
	 * Reads all OAuth tokens, location ID, and API version
	 * from SettingsManager. Called once during construction and again by
	 * reload_settings() when another process may have refreshed tokens.
	 *
	 * @return void
	 */
	private function load_settings(): void {
		// Get settings from SettingsManager (multisite-aware)
		$settings_manager = \Syncly\Core\SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();

		$this->access_token            = (string) ( $settings['oauth_access_token'] ?? '' );
		$this->refresh_token           = (string) ( $settings['oauth_refresh_token'] ?? '' );
		$this->access_token_expires_at = (int) ( $settings['oauth_expires_at'] ?? 0 );
		$this->oauth_revision          = (int) ( $settings['oauth_revision'] ?? 0 );

		$this->location_id = (string) ( $settings['location_id'] ?? '' );

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
	 * Set location ID
	 *
	 * @param string $location_id Location ID.
	 * @return void
	 */
	public function set_location_id( string $location_id ): void {
		$this->location_id = $location_id;
	}

	/**
	 * Set the API version header used for subsequent requests.
	 *
	 * Allows callers to temporarily switch to a specific GHL API version
	 * (e.g. v3 routes) for a single request and restore the configured one.
	 *
	 * @param string $api_version API version header value.
	 * @return void
	 */
	public function set_api_version( string $api_version ): void {
		if ( '' !== trim( $api_version ) ) {
			$this->api_version = $api_version;
		}
	}

	/**
	 * Get the current API version header value.
	 *
	 * @return string
	 */
	public function get_api_version(): string {
		return $this->api_version;
	}

	/**
	 * Skip OAuth token refresh for probe checks.
	 *
	 * @param bool $skip Whether to skip OAuth refresh.
	 * @return void
	 */
	public function set_skip_oauth_refresh( bool $skip ): void {
		$this->skip_oauth_refresh = $skip;
	}

	/**
	 * OAuth2 scopes requested from GoHighLevel.
	 *
	 * Single source of truth for the scopes bundled into the authorization URL
	 * as well as the scopes listed to users in the UI (dashboard + setup wizard).
	 * Keys are the raw GHL scope strings, values are human-readable labels.
	 *
	 * @var array<string, string>
	 */
	private const OAUTH_SCOPES = [
		'contacts.readonly'               => 'View Contacts',
		'contacts.write'                  => 'Edit Contacts',
		'locations/tags.readonly'         => 'View Tags',
		'locations/tags.write'            => 'Edit Tags',
		'locations/customFields.readonly' => 'View Custom Fields',
		'locations/customFields.write'    => 'Edit Custom Fields',
		'forms.readonly'                  => 'View Forms',
		'objects/schema.readonly'         => 'View Objects Schema',
		'objects/schema.write'            => 'Edit Objects Schema',
		'objects/record.readonly'         => 'View Object Records',
		'objects/record.write'            => 'Edit Object Records',
		'associations.write'              => 'Write Associations',
		'associations.readonly'           => 'View Associations',
		'associations/relation.write'     => 'Write Association Relations',
		'conversations.readonly'          => 'View Conversations',
		'conversations.write'             => 'Write Conversations',
		'conversations/message.readonly'  => 'View Conversation Messages',
		'conversations/message.write'     => 'Write Conversation Messages',
		'locations.readonly'              => 'View Location',
		'workflows.readonly'              => 'View Workflows',
		'calendars.readonly'              => 'View Calendars',
		'calendars.write'                 => 'Edit Calendars',
		'calendars/events.write'          => 'Manage Calendar Appointments',
		'opportunities.readonly'          => 'View Opportunities',
		'opportunities.write'             => 'Edit Opportunities',
	];

	/**
	 * Get the OAuth scopes requested from GoHighLevel.
	 *
	 * @return array<string, string> Map of raw scope string to human-readable label.
	 */
	public static function get_oauth_scopes(): array {
		return [
			'contacts.readonly'               => __( 'View Contacts', 'syncly' ),
			'contacts.write'                  => __( 'Edit Contacts', 'syncly' ),
			'locations/tags.readonly'         => __( 'View Tags', 'syncly' ),
			'locations/tags.write'            => __( 'Edit Tags', 'syncly' ),
			'locations/customFields.readonly' => __( 'View Custom Fields', 'syncly' ),
			'locations/customFields.write'    => __( 'Edit Custom Fields', 'syncly' ),
			'forms.readonly'                  => __( 'View Forms', 'syncly' ),
			'objects/schema.readonly'         => __( 'View Objects Schema', 'syncly' ),
			'objects/schema.write'            => __( 'Edit Objects Schema', 'syncly' ),
			'objects/record.readonly'         => __( 'View Object Records', 'syncly' ),
			'objects/record.write'            => __( 'Edit Object Records', 'syncly' ),
			'associations.write'              => __( 'Write Associations', 'syncly' ),
			'associations.readonly'           => __( 'View Associations', 'syncly' ),
			'associations/relation.write'     => __( 'Write Association Relations', 'syncly' ),
			'conversations.readonly'          => __( 'View Conversations', 'syncly' ),
			'conversations.write'             => __( 'Write Conversations', 'syncly' ),
			'conversations/message.readonly'  => __( 'View Conversation Messages', 'syncly' ),
			'conversations/message.write'     => __( 'Write Conversation Messages', 'syncly' ),
			'locations.readonly'              => __( 'View Location', 'syncly' ),
			'workflows.readonly'              => __( 'View Workflows', 'syncly' ),
			'calendars.readonly'              => __( 'View Calendars', 'syncly' ),
			'calendars.write'                 => __( 'Edit Calendars', 'syncly' ),
			'calendars/events.write'          => __( 'Manage Calendar Appointments', 'syncly' ),
			'opportunities.readonly'          => __( 'View Opportunities', 'syncly' ),
			'opportunities.write'             => __( 'Edit Opportunities', 'syncly' ),
		];
	}

	/**
	 * Generate the OAuth2 authorization URL.
	 *
	 * Builds the marketplace chooselocation URL with all required scopes.
	 * The redirect_uri always points to synclyforgohighlevel.com/wp-json/ghl/v1/callback
	 * (the proxy), which then forwards back to the WordPress site.
	 *
	 * Scopes requested (aligned with the OAUTH_SCOPES constant):
	 * - contacts.readonly / contacts.write
	 * - locations/tags.readonly / locations/tags.write
	 * - locations/customFields.readonly / locations/customFields.write
	 * - forms.readonly
	 * - objects/schema + record + associations (Custom Objects)
	 * - conversations / conversations/message (Conversations)
	 * - locations.readonly
	 * - workflows.readonly
	 * - calendars.readonly / calendars.write
	 * - calendars/events.write (Calendar Appointments)
	 * - opportunities.readonly / opportunities.write
	 *
	 * @param string $redirect_uri Unused directly (proxy handles redirect).
	 * @param string $return_url   The WP admin URL to return to after auth; passed as `state`.
	 * @return string Full authorization URL with query params.
	 */
	public function get_oauth_authorization_url( string $redirect_uri, string $return_url ): string {
		$params = [
			'client_id'     => self::OAUTH_CLIENT_ID,
			'redirect_uri'  => 'https://synclyforgohighlevel.com/wp-json/ghl/v1/callback',
			'scope'         => implode( ' ', array_keys( self::OAUTH_SCOPES ) ),
			'response_type' => 'code',
			'state'         => $return_url,
		];

		return self::OAUTH_AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Exchange an authorization code for OAuth2 tokens.
	 *
	 * Sends the code to the synclyforgohighlevel.com proxy which pairs it with the
	 * client secret and calls GHL’s `/oauth/token` endpoint.
	 *
	 * On success, both access_token and refresh_token are updated in-memory
	 * Credentials and successful health are persisted together under the refresh lease.
	 *
	 * @param string $code         Authorization code from the OAuth callback.
	 * @param string $redirect_uri Redirect URI that was used during authorization.
	 * @return array Decoded token payload (access_token, refresh_token, expires_in, etc.).
	 * @throws ApiException On network error or invalid response from proxy.
	 */
	public function exchange_code_for_token( string $code, string $redirect_uri ): array {
		if ( ! $this->acquire_refresh_lock() ) {
			throw new AuthenticationException( 'Another token refresh is in progress.' );
		}
		try {
			$this->reload_settings();
			$result = $this->perform_code_exchange( $code, $redirect_uri );
			$this->save_oauth_tokens( time() + (int) ( $result['expires_in'] ?? 3600 ) );
			$this->maybe_schedule_background_refresh();
			return $result;
		} finally {
			$this->release_refresh_lock();
		}
	}

	/**
	 * Exchange an authorization code while the caller holds the refresh lease.
	 *
	 * @param string $code Authorization code.
	 * @param string $redirect_uri OAuth callback URL.
	 * @return array Token payload.
	 */
	private function perform_code_exchange( string $code, string $redirect_uri ): array {
		if ( get_transient( self::TOKEN_EXCHANGE_COOLDOWN_KEY ) ) {
			$this->log_oauth_event( 'Exchange token blocked: cooldown active' );

			throw new ApiException(
				esc_html__( 'Token exchange is temporarily rate limited. Please wait a few minutes before reconnecting.', 'syncly' ),
				429
			);
		}

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
			$this->log_oauth_event( 'Exchange token WP_Error', [ 'error' => $response->get_error_message() ] );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is not rendered directly in browser output.
			throw new ApiException(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'Token exchange failed: %s', 'syncly' ),
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
				'status'        => $status_code,
				'response_keys' => is_array( $decoded ) ? array_keys( $decoded ) : [],
			]
		);

		if ( $status_code !== 200 || empty( $decoded['access_token'] ) ) {
			$decoded_array = $this->sanitize_response_payload( $decoded );

			if ( 429 === $status_code ) {
				set_transient( self::TOKEN_EXCHANGE_COOLDOWN_KEY, true, self::OAUTH_COOLDOWN_MINUTES * MINUTE_IN_SECONDS );
				$this->log_oauth_event( 'Exchange token rate limited; cooldown started' );
			}

			throw new ApiException(
				esc_html__( 'Failed to obtain access token from GoHighLevel', 'syncly' ),
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
	 * Get the redirect URI used for token exchange via the OAuth proxy.
	 *
	 * The browser OAuth flow returns through the proxy and then back to the
	 * plugin REST callback. Emergency reconnect recovery must use the same
	 * callback target or token exchange can fail intermittently.
	 *
	 * @return string
	 */
	private function get_oauth_callback_redirect_uri(): string {
		return rest_url( 'ghl/v1/callback' );
	}

	/**
	 * Schedule a recurring background task to refresh the OAuth token.
	 *
	 * Uses Action Scheduler to run every 20 minutes. Execution depends on WordPress cron
	 * being invoked, through traffic or a real server cron on idle sites.
	 *
	 * Only schedules if:
	 * - Action Scheduler is available
	 * - OAuth is configured with a refresh token
	 * - No existing schedule is already active
	 *
	 * @return void
	 */
	public function maybe_schedule_background_refresh(): void {
		$state   = \Syncly\Core\Settings\SettingsRepository::get_instance()->get_oauth_state();
		$enabled = ! empty( $state['oauth_refresh_token'] ) && ! in_array( $state['oauth_health_status'] ?? '', [ 'reconnect_required', 'permanently_invalid' ], true );
		\Syncly\API\OAuth\RecurringSchedule::ensure( self::SCHEDULED_REFRESH_HOOK, self::SCHEDULED_REFRESH_INTERVAL, 'syncly_oauth_20min', $enabled );
	}

	/**
	 * Handle the scheduled background token refresh.
	 *
	 * Called by Action Scheduler every 20 minutes. Checks if the token
	 * needs refreshing (within 12 hours of expiry) and refreshes if needed.
	 *
	 * The wide 12-hour window is critical for low-traffic sites where
	 * WP-Cron (and therefore Action Scheduler) may only fire a few times
	 * per day. GHL access tokens last 24h, so refreshing anytime in the
	 * last 12 hours gives ample opportunity to succeed before expiry.
	 *
	 * @return void
	 */
	public function handle_scheduled_refresh(): void {
		// Reload settings to get latest token state
		$this->reload_settings();

		// Skip if OAuth is not configured
		if ( ! $this->is_oauth_configured() || empty( $this->refresh_token ) ) {
			$this->log_oauth_event( 'Scheduled refresh skipped: no OAuth tokens' );
			return;
		}

		// Refresh if token is within 12 hours of expiry. GHL tokens last 24h,
		// so this means we refresh on roughly every other scheduled run that
		// actually fires — well before the token dies.
		$buffer = 12 * HOUR_IN_SECONDS;
		if ( $this->access_token_expires_at > 0 && ( $this->access_token_expires_at - time() ) > $buffer ) {
			return; // Token is still fresh, nothing to do
		}

		$this->log_oauth_event(
			'Scheduled background refresh triggered',
			[
				'expires_at' => $this->access_token_expires_at,
				'expires_in' => $this->access_token_expires_at - time(),
			]
		);

		try {
			$this->refresh_access_token();
			$this->log_oauth_event( 'Scheduled background refresh succeeded', [ 'new_expires_at' => $this->access_token_expires_at ] );
		} catch ( \Exception $e ) {
			$this->log_oauth_event( 'Scheduled background refresh failed', [ 'error' => $e->getMessage() ], 'error' );
		}
	}

	/**
	 * Unschedule the background token refresh.
	 *
	 * Called on plugin deactivation or when OAuth is disconnected.
	 *
	 * @return void
	 */
	public static function unschedule_background_refresh(): void {
		wp_clear_scheduled_hook( self::SCHEDULED_REFRESH_HOOK );
		if ( function_exists( 'as_unschedule_all_actions' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			as_unschedule_all_actions( self::SCHEDULED_REFRESH_HOOK, [], 'syncly' );
		}
	}

	/**
	 * Ensure access token is still valid; refresh if close to expiry.
	 *
	 * This is best-effort only while the current token is still usable. If the
	 * access token is already expired and refresh cannot happen, the request is
	 * blocked before sending a guaranteed-401 API call.
	 *
	 * @return void
	 * @throws ApiException When the access token is expired and refresh failed.
	 */
	private function ensure_fresh_access_token(): void {
		$state = \Syncly\Core\Settings\SettingsRepository::get_instance()->get_oauth_state();
		if ( ! \Syncly\API\OAuth\OAuthFailure::allows_attempt( $state ) ) {
			throw new AuthenticationException( 'OAuth circuit is open. Please reconnect your account or wait for backoff.' );
		}
		// If we do not know expiry, skip pre-emptive refresh (will rely on 401 handler)
		if ( $this->access_token_expires_at <= 0 ) {
			return;
		}

		// If a refresh already failed this request, don't try again (prevents cascade)
		if ( $this->refresh_failed_this_request ) {
			if ( time() >= $this->access_token_expires_at ) {
				throw new ApiException( esc_html__( 'Access token is expired and refresh is temporarily unavailable. Please wait for the cooldown period or reconnect your GoHighLevel account.', 'syncly' ) );
			}

			return;
		}

		// Refresh 30 minutes before expiry to account for gaps between requests
		// (e.g. tag cache is 1 hour, so a 60s buffer would miss the window entirely)
		$refresh_threshold = $this->access_token_expires_at - ( 30 * MINUTE_IN_SECONDS );
		if ( time() >= $refresh_threshold ) {
			try {
				$this->log_oauth_event( 'Proactive refresh before expiry', [ 'expires_at' => $this->access_token_expires_at ] );
				$this->refresh_access_token();
				$this->refresh_failed_this_request = false; // Reset on success
			} catch ( \Exception $e ) {
				$this->refresh_failed_this_request = true;
				$this->queue_oauth_health_notice( $e->getMessage() );
				$this->log_oauth_event( 'Proactive refresh failed (non-fatal)', [ 'error' => $e->getMessage() ] );

				if ( time() >= $this->access_token_expires_at ) {
					throw new ApiException(
						sprintf(
							/* translators: %s: refresh failure message */
							esc_html__( 'Access token is expired and refresh failed: %s', 'syncly' ),
							esc_html( $e->getMessage() )
						),
						(int) $e->getCode()
					);
				}
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
	 * Queue one stable admin notice for OAuth health issues.
	 *
	 * @param string $error_message Detailed error message for logs.
	 * @return void
	 */
	private function queue_oauth_health_notice( string $error_message ): void {
		if ( ! is_admin() ) {
			return;
		}

		$transient_key = self::OAUTH_NOTICE_THROTTLE_KEY . '_' . get_current_user_id();
		if ( get_transient( $transient_key ) ) {
			return;
		}

		set_transient( $transient_key, true, DAY_IN_SECONDS );

		\Syncly\Core\AdminNotices::get_instance()->error(
			\Syncly\API\OAuth\OAuthFailure::allows_attempt( \Syncly\Core\Settings\SettingsRepository::get_instance()->get_oauth_state() )
				? __( 'GoHighLevel refresh is temporarily unavailable. The plugin will retry after backoff.', 'syncly' )
				: __( 'GoHighLevel connection needs attention. Automatic refresh is paused until backoff ends or you reconnect with an active location.', 'syncly' ),
			true
		);

		$this->log_oauth_event( 'Queued OAuth health admin notice', [ 'error' => $error_message ], 'warning' );
	}

	/**
	 * Check whether OAuth refresh is temporarily cooling down.
	 *
	 * @return bool True if refresh attempts should be skipped for now.
	 */
	private function is_refresh_cooldown_active(): bool {
		return (bool) get_transient( self::REFRESH_COOLDOWN_KEY );
	}

	/**
	 * Clear OAuth refresh cooldown after a successful refresh or disconnect.
	 *
	 * @return void
	 */
	private function clear_refresh_cooldown(): void {
		delete_transient( self::REFRESH_COOLDOWN_KEY );
	}

	/**
	 * Refresh rotating credentials using a fresh read under the site lease.
	 * Permanent failures require manual authorization; transient outcomes back off.
	 * Ambiguous network outcomes are never retried immediately.
	 *
	 * @return array Token payload.
	 * @throws AuthenticationException When refresh cannot proceed.
	 */
	public function refresh_access_token(): array {
		$previous_token = $this->access_token;
		if ( ! $this->acquire_refresh_lock() ) {
			$this->reload_settings();
			if ( $this->access_token !== $previous_token && $this->access_token_expires_at > time() + 60 ) {
				return [
					'access_token'  => $this->access_token,
					'refresh_token' => $this->refresh_token,
				];
			}
			throw new AuthenticationException( 'Another token refresh is in progress.' );
		}
		try {
			$this->reload_settings();
			$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
			$state      = $repository->get_oauth_state();
			if ( ! \Syncly\API\OAuth\OAuthFailure::allows_attempt( $state ) ) {
				throw new AuthenticationException( 'OAuth circuit is open. Please reconnect your account or wait for backoff.' );
			}
			// A winner refreshed since this object loaded; do not rotate its new token again.
			if ( $this->access_token !== $previous_token && $this->access_token_expires_at > time() + 60 ) {
				return [
					'access_token'  => $this->access_token,
					'refresh_token' => $this->refresh_token,
				];
			}
			if ( empty( $this->refresh_token ) ) {
				throw new AuthenticationException( 'No refresh token available. Please reconnect your account.' );
			}
			$response = wp_remote_request(
				self::OAUTH_PROXY_URL . '/refresh-token',
				[
					'method'  => 'POST',
					'headers' => [
						'X-Syncly-Request-ID'    => wp_generate_uuid4(),
						'Content-Type'           => 'application/x-www-form-urlencoded',
						'X-Syncly-Version'       => defined( 'SYNCLY_VERSION' ) ? SYNCLY_VERSION : 'unknown',
						'X-Syncly-Circuit-State' => $state['oauth_health_status'] ?? 'unknown',
					],
					'body'    => [ 'refresh_token' => $this->refresh_token ],
					'timeout' => 25,
				]
			);
			$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$decoded  = is_wp_error( $response ) ? [] : json_decode( wp_remote_retrieve_body( $response ), true );
			$decoded  = is_array( $decoded ) ? $decoded : [];
			if ( 200 !== $status || empty( $decoded['access_token'] ) || empty( $decoded['refresh_token'] ) ) {
				$retry_after = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_header( $response, 'retry-after' );
				$failure     = \Syncly\API\OAuth\OAuthFailure::normalize( $status, $decoded, $retry_after );
				if ( 'duplicate' !== $failure['category'] ) {
					$failed                            = \Syncly\API\OAuth\OAuthFailure::failed_state( $state, $failure );
					$failed['oauth_health_checked_at'] = current_time( 'mysql' );
					if ( $repository->save_oauth_state( $failed, $this->oauth_revision ) && 'permanent_auth' === $failure['category'] ) {
						update_option( 'syncly_connection_verified', false, false );
						self::unschedule_background_refresh();
						$this->queue_oauth_health_notice( $failure['message'] );
					}
				}
				$this->reload_settings();
				throw new AuthenticationException(
					$failure['message'],
					[
						'category' => $failure['category'],
						'code'     => $failure['code'],
					]
				);
			}
			$this->access_token  = $decoded['access_token'];
			$this->refresh_token = $decoded['refresh_token'];
			$this->save_oauth_tokens( time() + (int) ( $decoded['expires_in'] ?? 3600 ) );
			return $decoded;
		} finally {
			$this->release_refresh_lock();
		}
	}

	/**
	 * Atomically acquire the refresh mutex using add_option().
	 *
	 * @return bool True when this process owns the lock.
	 */
	private function acquire_refresh_lock(): bool {
		$this->refresh_mutex = new \Syncly\API\OAuth\OptionMutex( self::REFRESH_LOCK_KEY );
		return $this->refresh_mutex->acquire( self::REFRESH_LOCK_TTL );
	}

	/**
	 * Release only the lease owned by this client.
	 *
	 * @return void
	 */
	private function release_refresh_lock(): void {
		if ( null !== $this->refresh_mutex ) {
			$this->refresh_mutex->release();
		}
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
	 * - Timeout: 25s (generous — reconnect is a last-resort recovery path)
	 *
	 * @return string Fresh authorization code.
	 * @throws ApiException On missing location ID, throttle, network error, or invalid response.
	 */
	public function reconnect_api(): string {
		if ( ! \Syncly\API\OAuth\OAuthFailure::allows_attempt( \Syncly\Core\Settings\SettingsRepository::get_instance()->get_oauth_state() ) ) {
			throw new AuthenticationException( 'OAuth circuit is open. Please reconnect your account or wait for backoff.' );
		}
		if ( empty( $this->location_id ) ) {
			throw new ApiException( esc_html__( 'Missing location ID for HighLevel reconnect', 'syncly' ) );
		}

		// Throttle reconnect attempts (2-minute cooldown)
		if ( get_transient( self::RECONNECT_THROTTLE_KEY ) ) {
			throw new ApiException( esc_html__( 'Reconnect attempt throttled. Please wait before retrying.', 'syncly' ) );
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
			'timeout' => 25,
		];

		$proxy_url = self::OAUTH_PROXY_URL . '/reconnect';
		$response  = wp_remote_request( $proxy_url, $args );

		if ( is_wp_error( $response ) ) {
			throw new ApiException(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'Reconnect API failed: %s', 'syncly' ),
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
				esc_html__( 'Failed to get authorization code from HighLevel reconnect', 'syncly' ),
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

		// GHL API requires locationId in the POST body for resource creation.
		if ( $include_location_id && ! empty( $this->location_id ) && ! isset( $data['locationId'] ) ) {
			$data['locationId'] = $this->location_id;
		}

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
	 * - Uses OAuth2 access token
	 * - Throws AuthenticationException if OAuth is not configured
	 *
	 * Token Lifecycle:
	 * - Calls ensure_fresh_access_token() before sending (proactive refresh)
	 * - On 401, attempts refresh_access_token() and retries once
	 * - On refresh failure, reloads settings to check for concurrent refresh
	 * - Quarantines credentials only on confirmed permanent OAuth failure
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

		if ( ! $this->is_oauth_configured() ) {
			throw new AuthenticationException( esc_html__( 'No authentication method configured. Please connect your GoHighLevel account.', 'syncly' ) );
		}

		$auth_token = $this->access_token;

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

		$request_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'syncly-', true );
		$args['headers']['X-Syncly-Request-ID'] = $request_id;
		$retry_count = 0;

		while ( true ) {
			$response = wp_remote_request( $url, $args );
			$this->log_oauth_event(
				'Request sent',
				[
					'correlation_id' => $request_id,
					'attempt'        => $retry_count + 1,
					'method'         => $method,
					'url'            => $url,
					'status'         => is_wp_error( $response ) ? 'error' : wp_remote_retrieve_response_code( $response ),
				]
			);

			if ( is_wp_error( $response ) ) {
				if ( $retry_count < self::MAX_TRANSIENT_RETRIES && $this->is_safe_retry_method( $method ) ) {
					$this->sleep_before_retry( $retry_count, $request_id, 'network_error' );
					++$retry_count;
					continue;
				}

				$error_msg = 'HTTP Request failed: ' . $response->get_error_message();
				throw new ApiException( esc_html( $error_msg ) );
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$response_headers = wp_remote_retrieve_headers( $response );
			$this->last_response_headers = $response_headers->getAll();

			if ( $retry_count < self::MAX_TRANSIENT_RETRIES && $this->is_retryable_http_failure( $method, $status_code ) ) {
				$retry_after = 429 === $status_code ? $this->get_retry_after_seconds( $this->last_response_headers ) : null;
				$this->sleep_before_retry( $retry_count, $request_id, 'http_' . $status_code, $retry_after );
				++$retry_count;
				continue;
			}

			break;
		}

		// Get response data
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		// Log full response

		// Store headers for rate limit tracking
		$this->last_response_headers = $headers->getAll();

		// Handle 401 with OAuth2 - try to refresh token
		if ( 401 === $status_code && $this->is_oauth_configured() && ! empty( $this->refresh_token ) && ! $this->refresh_failed_this_request ) {
			try {
				// Attempt to refresh the token
				$this->refresh_access_token();

				$this->log_oauth_event( 'Retrying request after 401 with refreshed token', [ 'url' => $url ] );

				// Set flag to prevent handle_http_response from also attempting refresh
				$this->is_retrying_after_refresh = true;

				// Retry the request with new token
				$args['headers']['Authorization'] = 'Bearer ' . $this->access_token;
				$response                         = wp_remote_request( $url, $args );

				$this->is_retrying_after_refresh = false;

				if ( ! is_wp_error( $response ) ) {
					$status_code                 = wp_remote_retrieve_response_code( $response );
					$body                        = wp_remote_retrieve_body( $response );
					$headers                     = wp_remote_retrieve_headers( $response );
					$this->last_response_headers = $headers->getAll();
				}
			} catch ( ApiException $e ) {
				$this->is_retrying_after_refresh   = false;
				$this->refresh_failed_this_request = true;

				// Refresh failed — reload settings to check if another process saved valid tokens
				$this->reload_settings();
				$this->log_oauth_event(
					'Refresh failed in request() 401 handler, reloaded settings',
					[
						'error'      => $e->getMessage(),
						'has_token'  => ! empty( $this->access_token ),
						'expires_at' => $this->access_token_expires_at,
					]
				);
				// NEVER clear tokens on temporary failures (timeout, 5xx, throttle)
				// Permanent refresh failures quarantine credentials through the bundle store.
				// Keeping the refresh token allows recovery on the next attempt
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
				esc_html__( 'Invalid JSON response from API', 'syncly' ),
				(int) $status_code,
				[ 'raw_body' => $this->sanitize_response_scalar( (string) $body ) ] // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sanitized via sanitize_response_scalar()
			);
		}

		// Handle error responses
		$this->handle_error_response( $status_code, $decoded );

		return $decoded;
	}

	/**
	 * Determine whether retrying the operation is safe without an idempotency key.
	 *
	 * @param string $method HTTP method.
	 * @return bool
	 */
	private function is_safe_retry_method( string $method ): bool {
		return in_array( strtoupper( $method ), [ 'GET', 'PUT', 'DELETE' ], true );
	}

	/**
	 * Determine whether an HTTP response is transient and safe to retry.
	 *
	 * @param string $method HTTP method.
	 * @param int    $status HTTP status code.
	 * @return bool
	 */
	private function is_retryable_http_failure( string $method, int $status ): bool {
		if ( 429 === $status ) {
			return true;
		}

		return $this->is_safe_retry_method( $method ) && in_array( $status, [ 500, 502, 503, 504 ], true );
	}

	/**
	 * Parse a Retry-After response header.
	 *
	 * @param array $headers Response headers.
	 * @return int|null Delay in seconds, or null when absent/invalid.
	 */
	private function get_retry_after_seconds( array $headers ): ?int {
		$value = $headers['retry-after'] ?? $headers['Retry-After'] ?? null;
		if ( null === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}

		$timestamp = strtotime( (string) $value );
		return false === $timestamp ? null : max( 0, $timestamp - time() );
	}

	/**
	 * Apply bounded exponential backoff with jitter before a retry.
	 *
	 * @param int         $retry_count Current zero-based retry number.
	 * @param string      $request_id  Correlation ID.
	 * @param string      $reason      Retry reason.
	 * @param int|null    $retry_after Provider-requested delay.
	 * @return void
	 */
	private function sleep_before_retry( int $retry_count, string $request_id, string $reason, ?int $retry_after = null ): void {
		$base_delay = self::TRANSIENT_RETRY_BASE_DELAY * ( 2 ** $retry_count );
		$jitter     = random_int( 0, max( 1, $base_delay * 1000 ) ) / 1000;
		$delay      = null !== $retry_after ? max( (float) $retry_after, $jitter ) : $base_delay + $jitter;

		$this->log_oauth_event(
			'Retrying API request',
			[
				'correlation_id' => $request_id,
				'retry_number'   => $retry_count + 1,
				'reason'         => $reason,
				'delay_seconds'  => round( $delay, 3 ),
			]
		);

		usleep( (int) round( $delay * 1000000 ) );
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
		$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
		$state      = $repository->get_oauth_state();
		$state      = array_merge(
			$state,
			[
				'oauth_access_token'      => $this->access_token,
				'oauth_refresh_token'     => $this->refresh_token,
				'oauth_expires_at'        => $expires_at ?? $this->access_token_expires_at,
				'oauth_connected_at'      => current_time( 'mysql' ),
				'oauth_health_status'     => 'healthy',
				'oauth_health_message'    => '',
				'oauth_health_checked_at' => current_time( 'mysql' ),
				'oauth_failure_count'     => 0,
				'oauth_failure_category'  => '',
				'oauth_last_failure'      => 0,
				'oauth_next_attempt'      => 0,
				'oauth_error_code'        => '',
			]
		);
		if ( ! $repository->save_oauth_state( $state, $this->oauth_revision ) ) {
			$this->reload_settings();
			throw new AuthenticationException( 'OAuth credentials changed concurrently. Please retry.' );
		}
		$this->reload_settings();
		$this->clear_refresh_cooldown();
		update_option(
			'syncly_connection_verified',
			[
				'verified'    => true,
				'method'      => 'oauth2',
				'verified_at' => current_time( 'mysql' ),
			],
			false
		);
	}

	/**
	 * Clear all OAuth2 tokens from in-memory state and the database.
	 *
	 * Called when:
	 * - Admin disconnects the account
	 * Permanent provider failures quarantine credentials using the same bundle store.
	 *
	 * Also clears refresh cooldown to avoid confusing
	 * "temporarily disabled" errors after a manual disconnect.
	 *
	 * @return void
	 */
	public function clear_oauth_tokens(): void {
		if ( ! $this->acquire_refresh_lock() ) {
			throw new AuthenticationException( 'Another token refresh is in progress.' );
		}
		try {
			$this->reload_settings();
			$repository = \Syncly\Core\Settings\SettingsRepository::get_instance();
			$state      = $repository->get_oauth_state();
			$failure    = \Syncly\API\OAuth\OAuthFailure::normalize( 400, [ 'error' => 'invalid_grant' ] );
			if ( ! $repository->save_oauth_state( \Syncly\API\OAuth\OAuthFailure::failed_state( $state, $failure ), $this->oauth_revision ) ) {
				throw new AuthenticationException( 'OAuth credentials changed concurrently. Please retry.' );
			}
			$this->reload_settings();
			update_option( 'syncly_connection_verified', false, false );
			self::unschedule_background_refresh();
			do_action( 'syncly_connection_status_changed', false, 'oauth_tokens_cleared' );
		} finally {
			$this->release_refresh_lock();
		}
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
		$error_raw = isset( $response['message'] ) ? $response['message'] : ( isset( $response['error'] ) ? $response['error'] : esc_html__( 'Unknown API error', 'syncly' ) );

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
					esc_html__( 'Rate limit exceeded. Retry after %d seconds.', 'syncly' ),
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
