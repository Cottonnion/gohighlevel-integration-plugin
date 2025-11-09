<?php
declare(strict_types=1);

namespace GHL_CRM\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Controller
 *
 * Handles external REST API endpoints with authentication and rate limiting
 *
 * @package    GHL_CRM_Integration
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
	 * @var \GHL_CRM\Core\SettingsManager
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
		$this->settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
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
		$settings         = $this->settings_manager->get_settings_array();
		$rest_api_enabled = $settings['rest_api_enabled'] ?? false;

		// Only register routes if REST API is enabled
		if ( ! $rest_api_enabled ) {
			return;
		}

		$allowed_endpoints = $settings['rest_api_endpoints'] ?? [];

		// Contacts endpoint
		if ( in_array( 'contacts', $allowed_endpoints, true ) ) {
			register_rest_route(
				'ghl-crm/v1',
				'/contacts',
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_or_update_contact' ],
					'permission_callback' => [ $this, 'check_api_permission' ],
				]
			);
		}

		// Sync endpoint
		if ( in_array( 'sync', $allowed_endpoints, true ) ) {
			register_rest_route(
				'ghl-crm/v1',
				'/sync',
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'trigger_sync' ],
					'permission_callback' => [ $this, 'check_api_permission' ],
				]
			);
		}

		// Status endpoint
		if ( in_array( 'status', $allowed_endpoints, true ) ) {
			register_rest_route(
				'ghl-crm/v1',
				'/status',
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_status' ],
					'permission_callback' => [ $this, 'check_api_permission' ],
				]
			);
		}

		// Webhooks endpoint (GET for verification, POST for events)
		if ( in_array( 'webhooks', $allowed_endpoints, true ) ) {
			register_rest_route(
				'ghl-crm/v1',
				'/webhooks',
				[
					[
						'methods'             => 'GET',
						'callback'            => [ $this, 'verify_webhook' ],
						'permission_callback' => '__return_true', // Public for verification
					],
					[
						'methods'             => 'POST',
						'callback'            => [ $this, 'handle_webhook' ],
						'permission_callback' => [ $this, 'check_api_permission' ],
					],
				]
			);
		}
	}

	/**
	 * Check API permission (authentication)
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return bool|\ WP_Error
	 */
	public function check_api_permission( \WP_REST_Request $request ) {
		$settings   = $this->settings_manager->get_settings_array();
		$stored_key = $settings['rest_api_key'] ?? '';

		// Get API key from Authorization header
		$auth_header = $request->get_header( 'Authorization' );

		if ( empty( $auth_header ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Authorization header missing. Include: Authorization: Bearer YOUR_API_KEY', 'ghl-crm-integration' ),
				[ 'status' => 401 ]
			);
		}

		// Extract Bearer token
		if ( preg_match( '/Bearer\s+(.+)/i', $auth_header, $matches ) ) {
			$provided_key = trim( $matches[1] );
		} else {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Invalid Authorization header format. Use: Bearer YOUR_API_KEY', 'ghl-crm-integration' ),
				[ 'status' => 401 ]
			);
		}

		// Validate API key
		if ( empty( $stored_key ) || $provided_key !== $stored_key ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Invalid API key', 'ghl-crm-integration' ),
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
					__( 'IP address not whitelisted', 'ghl-crm-integration' ),
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
		$params = $request->get_json_params();

		if ( empty( $params['email'] ) ) {
			return new \WP_Error(
				'missing_email',
				__( 'Email is required', 'ghl-crm-integration' ),
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
				$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
				$queue_manager->add_to_queue( 'user', $user->ID, 'profile_update', $params );

				return new \WP_REST_Response(
					[
						'success' => true,
						'message' => __( 'User updated successfully', 'ghl-crm-integration' ),
						'user_id' => $user->ID,
					],
					200
				);
			} else {
				// Create new user
				$username = sanitize_user( $params['username'] ?? $params['email'] );
				$password = $params['password'] ?? wp_generate_password();

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
				$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
				$queue_manager->add_to_queue( 'user', $user_id, 'user_register', $params );

				return new \WP_REST_Response(
					[
						'success' => true,
						'message' => __( 'User created successfully', 'ghl-crm-integration' ),
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
			do_action( 'ghl_crm_trigger_manual_sync', $sync_type );

			return new \WP_REST_Response(
				[
					'success' => true,
					/* translators: %s: Type of sync (e.g., users, contacts) */
					'message' => sprintf( __( '%s sync triggered successfully', 'ghl-crm-integration' ), ucfirst( $sync_type ) ),
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
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
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
				'message' => __( 'Webhook endpoint is active', 'ghl-crm-integration' ),
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
		$webhook_handler = \GHL_CRM\API\Webhooks\WebhookHandler::get_instance();
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

		$transient_key = 'ghl_rest_api_rate_' . md5( $client_ip );
		$request_count = get_transient( $transient_key );

		if ( false === $request_count ) {
			// First request in this minute
			set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
			return true;
		}

		if ( $request_count >= $requests_per_minute ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: Maximum number of requests allowed per minute */
					__( 'Rate limit exceeded. Maximum %d requests per minute allowed.', 'ghl-crm-integration' ),
					$requests_per_minute
				),
				[
					'status'      => 429,
					'retry_after' => 60,
					'limit'       => $requests_per_minute,
					'remaining'   => 0,
				]
			);
		}

		// Increment counter
		set_transient( $transient_key, $request_count + 1, MINUTE_IN_SECONDS );

		return true;
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
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (take first one)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
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
			// Check for CIDR notation
			if ( strpos( $allowed, '/' ) !== false ) {
				if ( $this->ip_in_cidr( $ip, $allowed ) ) {
					return true;
				}
			} else {
				// Direct IP match
				if ( $ip === $allowed ) {
					return true;
				}
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
		list( $subnet, $mask ) = explode( '/', $cidr );

		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );
		$mask_long   = -1 << ( 32 - (int) $mask );

		return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
	}
}
