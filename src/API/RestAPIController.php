<?php
declare(strict_types=1);

namespace Syncly\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Controller
 *
 * Handles external REST API endpoints with authentication and rate limiting
 *
 * @package    Syncly
 * @subpackage API
 */
class RestAPIController {
	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings manager instance
	 *
	 * @var \Syncly\Core\SettingsManager
	 */
	private $settings_manager;

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
		$this->settings_manager = \Syncly\Core\SettingsManager::get_instance();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Editor-only routes (admin-only) — registered regardless of public REST toggle
		register_rest_route(
			'syncly/v1',
			'/connection/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_editor_connection_status' ],
				'permission_callback' => [ $this, 'check_editor_permission' ],
			]
		);

		register_rest_route(
			'syncly/v1',
			'/forms',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_editor_forms' ],
				'permission_callback' => [ $this, 'check_editor_permission' ],
			]
		);

		register_rest_route(
			'syncly/v1',
			'/tags',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_editor_tags' ],
				'permission_callback' => [ $this, 'check_editor_permission' ],
			]
		);

		/**
		 * Allow Pro to register public REST API routes.
		 *
		 * @since 1.0.0
		 *
		 * @param \Syncly\API\RestAPIController $controller The REST API controller instance.
		 */
		do_action( 'syncly_register_public_rest_routes', $this );
	}

	/**
	 * Check editor permission - administrators only
	 *
	 * @return bool|\WP_Error
	 */
	public function check_editor_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'syncly' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	/**
	 * Get connection status for block editor
	 *
	 * @return \WP_REST_Response
	 */
	public function get_editor_connection_status(): \WP_REST_Response {
		$connection_manager = ConnectionManager::get_instance();
		return new \WP_REST_Response(
			[
				'connected' => $connection_manager->is_connection_verified(),
			]
		);
	}

	/**
	 * Get forms for block editor
	 *
	 * @return \WP_REST_Response
	 */
	public function get_editor_forms(): \WP_REST_Response {
		$connection_manager = ConnectionManager::get_instance();

		if ( ! $connection_manager->is_connection_verified() ) {
			return new \WP_REST_Response(
				[
					'forms' => [],
					'error' => __( 'Not connected to GoHighLevel', 'syncly' ),
				]
			);
		}

		try {
			$forms_resource = new \Syncly\API\Resources\FormsResource();
			$forms          = $forms_resource->get_forms();

			if ( is_wp_error( $forms ) ) {
				return new \WP_REST_Response(
					[
						'forms' => [],
						'error' => $forms->get_error_message(),
					]
				);
			}

			return new \WP_REST_Response(
				[
					'forms' => $forms,
				]
			);
		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				[
					'forms' => [],
					'error' => $e->getMessage(),
				]
			);
		}
	}

	/**
	 * Get tags for block editor
	 *
	 * @return \WP_REST_Response
	 */
	public function get_editor_tags(): \WP_REST_Response {
		$connection_manager = ConnectionManager::get_instance();

		if ( ! $connection_manager->is_connection_verified() ) {
			return new \WP_REST_Response(
				[
					'tags'  => [],
					'error' => __( 'Not connected to GoHighLevel', 'syncly' ),
				]
			);
		}

		try {
			$tag_manager = \Syncly\Sync\TagManager::get_instance();
			$tags        = $tag_manager->get_tags( false );

			if ( empty( $tags ) ) {
				return new \WP_REST_Response(
					[
						'tags'  => [],
						'error' => __( 'No tags found. Please sync your GoHighLevel tags in settings.', 'syncly' ),
					]
				);
			}

			$formatted_tags = [];
			foreach ( $tags as $tag ) {
				$formatted_tags[] = [
					'id'   => $tag['id'] ?? '',
					'name' => $tag['name'] ?? $tag['id'] ?? '',
				];
			}

			return new \WP_REST_Response(
				[
					'tags' => $formatted_tags,
				]
			);
		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				[
					'tags'  => [],
					'error' => $e->getMessage(),
				],
				500
			);
		}
	}

	/**
	 * Check API permission (authentication)
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return true|\WP_Error
	 */
	public function check_api_permission( \WP_REST_Request $request ) {
		$settings   = $this->settings_manager->get_settings_array();
		$stored_key = $settings['rest_api_key'] ?? '';

		// Get API key from Authorization header
		$auth_header = $request->get_header( 'Authorization' );

		if ( empty( $auth_header ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Authorization header missing. Include: Authorization: Bearer YOUR_API_KEY', 'syncly' ),
				[ 'status' => 401 ]
			);
		}

		// Extract Bearer token
		if ( preg_match( '/Bearer\s+(.+)/i', $auth_header, $matches ) ) {
			$provided_key = trim( $matches[1] );
		} else {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Invalid Authorization header format. Use: Bearer YOUR_API_KEY', 'syncly' ),
				[ 'status' => 401 ]
			);
		}

		// Validate API key
		if ( empty( $stored_key ) || hash_equals( $stored_key, $provided_key ) === false ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Invalid API key', 'syncly' ),
				[ 'status' => 403 ]
			);
		}

		// Check IP whitelist if configured
		$ip_whitelist = $settings['rest_api_ip_whitelist'] ?? '';
		if ( ! empty( $ip_whitelist ) ) {
			$client_ip = $this->get_client_ip();
			if ( ! $this->is_ip_allowed( $client_ip, $ip_whitelist ) ) {
				return new \WP_Error(
					'rest_forbidden',
					__( 'IP address not whitelisted', 'syncly' ),
					[ 'status' => 403 ]
				);
			}
		}

		// Check rate limits
		$rate_limit_enabled = $settings['rest_api_rate_limit'] ?? true;
		if ( $rate_limit_enabled ) {
			$rate_limit_check = $this->check_rate_limit();
			if ( is_wp_error( $rate_limit_check ) ) {
				return $rate_limit_check;
			}
		}

		return true;
	}

	/**
	 * Create or update contact
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_or_update_contact( \WP_REST_Request $request ) {
		$raw_body = $request->get_body();
		if ( strlen( (string) $raw_body ) > 100000 ) {
			return new \WP_Error(
				'payload_too_large',
				__( 'Payload too large.', 'syncly' ),
				[ 'status' => 413 ]
			);
		}

		$params = $request->get_json_params();

		if ( empty( $params['email'] ) ) {
			return new \WP_Error(
				'missing_email',
				__( 'Email is required', 'syncly' ),
				[ 'status' => 400 ]
			);
		}

		try {
			// Check if user exists by email
			$user = get_user_by( 'email', sanitize_email( $params['email'] ) );

			if ( $user ) {
				// Update existing user
				$user_data = [ 'ID' => $user->ID ];

				if ( ! empty( $params['first_name'] ) ) {
					$user_data['first_name'] = sanitize_text_field( $params['first_name'] );
				}
				if ( ! empty( $params['last_name'] ) ) {
					$user_data['last_name'] = sanitize_text_field( $params['last_name'] );
				}

				$result = wp_update_user( $user_data );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				// Queue sync to GHL
				$queue_manager = \Syncly\Sync\QueueManager::get_instance();
				$queue_manager->add_to_queue( 'user', $user->ID, 'profile_update', $params );

				return new \WP_REST_Response(
					[
						'success' => true,
						'message' => __( 'User updated successfully', 'syncly' ),
						'user_id' => $user->ID,
					],
					200
				);
			} else {
				// Create new user (always generate password server-side)
				$username = sanitize_user( $params['username'] ?? $params['email'] );
				$password = wp_generate_password();

				$user_id = wp_create_user( $username, $password, sanitize_email( $params['email'] ) );

				if ( is_wp_error( $user_id ) ) {
					return $user_id;
				}

				// Update user meta
				if ( ! empty( $params['first_name'] ) ) {
					update_user_meta( $user_id, 'first_name', sanitize_text_field( $params['first_name'] ) );
				}
				if ( ! empty( $params['last_name'] ) ) {
					update_user_meta( $user_id, 'last_name', sanitize_text_field( $params['last_name'] ) );
				}

				// Queue sync to GHL
				$queue_manager = \Syncly\Sync\QueueManager::get_instance();
				$queue_manager->add_to_queue( 'user', $user_id, 'user_register', $params );

				return new \WP_REST_Response(
					[
						'success' => true,
						'message' => __( 'User created successfully', 'syncly' ),
						'user_id' => $user_id,
					],
					201
				);
			}
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'sync_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Trigger manual sync
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function trigger_sync( \WP_REST_Request $request ) {
		$params    = $request->get_json_params();
		$sync_type = $params['type'] ?? 'users';

		try {
			do_action( 'syncly_trigger_manual_sync', $sync_type );

			return new \WP_REST_Response(
				[
					'success' => true,
					/* translators: %s: Type of sync (e.g., users, contacts) */
					'message' => sprintf( __( '%s sync triggered successfully', 'syncly' ), ucfirst( $sync_type ) ),
				],
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'sync_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Get sync status
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response
	 */
	public function get_status( \WP_REST_Request $request ) {
		$queue_manager = \Syncly\Sync\QueueManager::get_instance();
		$status        = $queue_manager->get_queue_status();

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $status,
			],
			200
		);
	}

	/**
	 * Verify webhook (for webhook setup)
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response
	 */
	public function verify_webhook( \WP_REST_Request $request ) {
		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Webhook endpoint is active', 'syncly' ),
			],
			200
		);
	}

	/**
	 * Handle incoming webhook
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( \WP_REST_Request $request ) {
		// Delegate to WebhookHandler
		$webhook_handler = \Syncly\API\Webhooks\WebhookHandler::get_instance();
		return $webhook_handler->handle_request( $request );
	}

	/**
	 * Check rate limits
	 *
	 * @return true|\WP_Error
	 */
	private function check_rate_limit() {
		$settings            = $this->settings_manager->get_settings_array();
		$requests_per_minute = $settings['rest_api_requests_per_minute'] ?? 60;
		$client_ip           = $this->get_client_ip();
		$sanitized_ip        = sanitize_key( $client_ip );
		$sanitized_ip        = $sanitized_ip ?: 'unknown';

		$transient_key = 'ghl_rest_api_rate_' . $sanitized_ip;
		$lock_key      = 'ghl_rest_api_rate_lock_' . $sanitized_ip;

		if ( ! $this->acquire_rate_limit_lock( $lock_key ) ) {
			return new \WP_Error(
				'rate_limit_busy',
				__( 'Rate limit is busy. Please retry shortly.', 'syncly' ),
				[ 'status' => 429 ]
			);
		}

		$request_count = get_transient( $transient_key );
		$request_count = ( false === $request_count ) ? 1 : ( (int) $request_count + 1 );

		set_transient( $transient_key, $request_count, MINUTE_IN_SECONDS );

		if ( $request_count > $requests_per_minute ) {
			$this->release_rate_limit_lock( $lock_key );
			return new \WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: Maximum number of requests allowed per minute */
					__( 'Rate limit exceeded. Maximum %d requests per minute allowed.', 'syncly' ),
					$requests_per_minute
				),
				[
					'status'      => 429,
					'retry_after' => 60,
					'limit'       => $requests_per_minute,
					'remaining'   => max( 0, $requests_per_minute - $request_count ),
				]
			);
		}

		$this->release_rate_limit_lock( $lock_key );
		return true;
	}

	/**
	 * Acquire a short-lived lock for rate limiting.
	 *
	 * @param string $lock_key Lock transient/cache key.
	 * @return bool
	 */
	private function acquire_rate_limit_lock( string $lock_key ): bool {
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $lock_key, 1, '', 5 );
		}

		if ( false === get_transient( $lock_key ) ) {
			set_transient( $lock_key, 1, 5 );
			return true;
		}

		return false;
	}

	/**
	 * Release a rate limit lock.
	 *
	 * @param string $lock_key Lock transient/cache key.
	 * @return void
	 */
	private function release_rate_limit_lock( string $lock_key ): void {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $lock_key, '' );
		} else {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		];

		foreach ( $ip_keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$raw_ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$parts  = array_map( 'trim', explode( ',', $raw_ip ) );

			foreach ( $parts as $candidate ) {
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					return $candidate;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Check if IP is allowed
	 *
	 * @param string $ip IP address to check
	 * @param string $whitelist Whitelist string (one IP/CIDR per line)
	 * @return bool
	 */
	private function is_ip_allowed( string $ip, string $whitelist ): bool {
		$allowed_ips = array_filter( array_map( 'trim', explode( "\n", $whitelist ) ) );

		foreach ( $allowed_ips as $allowed ) {
			// Check for CIDR notation.
			if ( strpos( $allowed, '/' ) !== false && $this->ip_in_cidr( $ip, $allowed ) ) {
				return true;
			} elseif ( $ip === $allowed ) {
				// Direct IP match.
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if IP is in CIDR range
	 *
	 * @param string $ip IP address
	 * @param string $cidr CIDR notation (e.g., 192.168.1.0/24)
	 * @return bool
	 */
	private function ip_in_cidr( string $ip, string $cidr ): bool {
		if ( strpos( $cidr, '/' ) === false ) {
			return false;
		}

		list( $subnet, $mask ) = explode( '/', $cidr );

		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );
		$mask        = (int) $mask;
		if ( false === $ip_long || false === $subnet_long || $mask < 0 || $mask > 32 ) {
			return false;
		}

		$mask_long = -1 << ( 32 - $mask );

		return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
	}
}
