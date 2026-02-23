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
 * Class Client
 *
 * Handles all HTTP communication with GoHighLevel API including OAuth2 authentication.
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
	 * OAuth Proxy Base URL (labgenz.com server)
	 * Handles OAuth token exchanges securely without exposing client secret
	 */
	private const OAUTH_PROXY_URL = 'https://labgenz.com/wp-json/ghl-proxy/v1';

	/**
	 * OAuth2 Client ID (Public - safe to expose)
	 * Production OAuth App Client ID
	 *
	 * @var string
	 */
	private const OAUTH_CLIENT_ID = '68ff9baa25051d0ca83341e9-mh9cljcg';

	/**
	 * OAuth2 Access Token
	 *
	 * @var string
	 */
	private string $access_token = '';

	/**
	 * OAuth2 Access Token Expiry (unix timestamp)
	 *
	 * @var int
	 */
	private int $access_token_expires_at = 0;

	/**
	 * Lightweight logger prefix for OAuth events.
	 *
	 * @var string
	 */
	private string $oauth_log_prefix = '[GHL][OAuth] ';

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
	 * @param array|WP_Error $response HTTP response.
	 * @param array          $args     HTTP request arguments.
	 * @param string         $url      Request URL.
	 * @return array|WP_Error Modified response.
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
				$email = $request_body['email'] ?? null;

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
								$new_contact = $search_response['contacts'][0];
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
											WHERE (meta_key = %s OR meta_key = '_ghl_contact_id') 
											AND meta_value = %s",
											$location_meta_key,
											$old_contact_id
										)
									);

									// Update all found users using TagManager for proper location-scoped storage
									$tag_manager = \GHL_CRM\Core\TagManager::get_instance();
									$updated_count = 0;

									foreach ( array_unique( $user_ids ) as $user_id ) {
										$tag_manager->store_user_contact_id( (int) $user_id, $new_contact_id, $location_id );
										$updated_count++;
									}

									// Log the auto-recovery
									// if ( $updated_count > 0 ) {
									// 	error_log( 
									// 		sprintf(
									// 			'GHL Auto-Recovery (MERGED): Contact %s was merged into %s (email: %s). Updated %d user(s).',
									// 			$old_contact_id,
									// 			$new_contact_id,
									// 			$email,
									// 			$updated_count
									// 		)
									// 	);
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
									$args['body'] = wp_json_encode( $contact_data );
									$new_url = preg_replace( '#/contacts/[a-zA-Z0-9_-]+#', '/contacts', $url );

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
											$location_id = $this->location_id;
											$location_meta_key = '_ghl_contact_id_' . $location_id;
											
											// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
											$user_ids = $wpdb->get_col(
												$wpdb->prepare(
													"SELECT user_id FROM {$wpdb->usermeta} 
													WHERE (meta_key = %s OR meta_key = '_ghl_contact_id') 
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
							'url'     => $url,
							'error'   => $e->getMessage(),
							'status'  => $response_code,
							'body'    => $body_json,
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
	 * Set API version
	 *
	 * @param string $version API version.
	 * @return void
	 */
	public function set_api_version( string $version ): void {
		$this->api_version = $version;
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
	 * Generate OAuth2 authorization URL
	 *
	 * @param string $redirect_uri Redirect URI after authorization.
	 * @param string $state        Random state parameter for security.
	 * @return string Authorization URL.
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
	 * Exchange authorization code for access token
	 * Uses labgenz.com proxy to keep client secret secure
	 *
	 * @param string $code         Authorization code from callback
	 * @param string $redirect_uri Redirect URI used in authorization
	 * @return array Token response
	 * @throws ApiException
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
		$this->log_oauth_event( 'Exchange token proxy body', [ 'status' => $status_code, 'body' => is_array( $decoded ) ? $decoded : $body ] );

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
	 * @return void
	 * @throws ApiException When refresh fails
	 */
	private function ensure_fresh_access_token(): void {
		// If we do not know expiry, skip pre-emptive refresh (will rely on 401 handler)
		if ( $this->access_token_expires_at <= 0 ) {
			return;
		}

		// Refresh one minute before expiry to avoid mid-request failures
		$refresh_threshold = $this->access_token_expires_at - 60;
		if ( time() >= $refresh_threshold ) {
			$this->log_oauth_event( 'Proactive refresh before expiry', [ 'expires_at' => $this->access_token_expires_at ] );
			$this->refresh_access_token();
		}
	}

	/**
	 * Write a concise OAuth log line to the PHP error log.
	 *
	 * @param string $message Log message
	 * @param array  $context Optional context data
	 * @return void
	 */
	private function log_oauth_event( string $message, array $context = [] ): void {
		return; // Disabled Logging for now
		$log = $this->oauth_log_prefix . $message;

		if ( ! empty( $context ) ) {
			$log .= ' | ' . wp_json_encode( $context );
		}

		error_log( $log );
	}

	/**
	 * Refresh OAuth2 access token
	 *
	 * @return array Token response
	 * @throws ApiException
	 */
	public function refresh_access_token(): array {
		if ( empty( $this->refresh_token ) ) {
			$this->log_oauth_event( 'Refresh aborted: no refresh token stored' );
			throw new ApiException( esc_html__( 'No refresh token available', 'ghl-crm-integration' ) );
		}

		// Throttle repeated attempts within 30 seconds to avoid hammering
		$now = time();
		if ( self::$last_refresh_attempt_ts && ( $now - self::$last_refresh_attempt_ts ) < 30 ) {
			$this->log_oauth_event( 'Refresh skipped: throttled', [ 'seconds_since_last' => $now - self::$last_refresh_attempt_ts, 'last_error' => self::$last_refresh_error ] );
			throw new ApiException(
				self::$last_refresh_error
					? sprintf( esc_html__( 'Recent refresh attempt failed: %s', 'ghl-crm-integration' ), self::$last_refresh_error )
					: esc_html__( 'Recent refresh attempt in progress or just failed. Reconnect required.', 'ghl-crm-integration' )
			);
		}

		self::$last_refresh_attempt_ts = $now;
		self::$last_refresh_error      = null;

		// Handle edge case where refresh token might be corrupted (not a string)
		if ( ! is_string( $this->refresh_token ) ) {
			// Try reconnect API as fallback
			try {
				$auth_code = $this->reconnect_api();
				// Exchange auth code for new tokens
				$redirect_uri = admin_url( 'admin.php?page=ghl-crm-settings' );
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

		$args = [
			'method'  => 'POST',
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( $data ),
			'timeout' => 30,
		];

		$proxy_url = self::OAUTH_PROXY_URL . '/refresh-token';
		$response  = wp_remote_request( $proxy_url, $args );
		$this->log_oauth_event( 'Refresh token proxy response', [ 'status' => is_wp_error( $response ) ? 'error' : wp_remote_retrieve_response_code( $response ) ] );

		if ( is_wp_error( $response ) ) {
			self::$last_refresh_error = $response->get_error_message();
			$this->log_oauth_event( 'Refresh token WP_Error', [ 'error' => $response->get_error_message() ] );
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
		$this->log_oauth_event( 'Refresh token endpoint body', [ 'status' => $status_code, 'body' => is_array( $decoded ) ? $decoded : $body ] );

		if ( $status_code !== 200 || empty( $decoded['access_token'] ) ) {
			$decoded_array  = is_array( $decoded ) ? $decoded : [];
			$error_message = $decoded_array['message'] ?? ( is_string( $body ) ? $body : 'unknown' );
			self::$last_refresh_error = sprintf( 'Refresh HTTP %d: %s', $status_code, sanitize_text_field( (string) $error_message ) );

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
					$auth_code     = $this->reconnect_api();
					$redirect_uri  = admin_url( 'admin.php?page=ghl-crm-settings' );
					$token_payload = $this->exchange_code_for_token( $auth_code, $redirect_uri );
					$expires_at    = time() + ( $token_payload['expires_in'] ?? 3600 );
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
		$this->log_oauth_event( 'Request sent', [ 'method' => $method, 'url' => $url, 'status' => is_wp_error( $response ) ? 'error' : wp_remote_retrieve_response_code( $response ) ] );

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
	 * @throws AuthenticationException
	 * @throws RateLimitException
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