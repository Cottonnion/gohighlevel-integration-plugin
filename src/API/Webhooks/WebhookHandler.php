<?php
declare(strict_types=1);

namespace Syncly\API\Webhooks;

use Syncly\Core\SettingsManager;
use Syncly\Sync\QueueManager;
use Syncly\Sync\SyncLogger;
use Syncly\Sync\GHLToWordPressSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook Handler
 *
 * Handles incoming webhooks from GoHighLevel.
 *
 * @package    Syncly
 * @subpackage API/Webhooks
 */
class WebhookHandler {

	/**
	 * Header name for shared secret token.
	 */
	private const WEBHOOK_SECRET_HEADER = 'x-ghl-token';

	/**
	 * Maximum allowed webhook payload size (256KB).
	 */
	private const MAX_WEBHOOK_BODY_BYTES = 262144;

	/**
	 * Settings Manager.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Queue Manager.
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Sync Logger.
	 *
	 * @var SyncLogger
	 */
	private SyncLogger $logger;

	/**
	 * GHL to WordPress Sync.
	 *
	 * @var GHLToWordPressSync
	 */
	private GHLToWordPressSync $ghl_sync;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get instance.
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
	 * Constructor.
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
		$this->queue_manager    = QueueManager::get_instance();
		$this->logger           = SyncLogger::get_instance();
		$this->ghl_sync         = GHLToWordPressSync::get_instance();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_webhook_endpoint' ] );
		add_action( 'wp_ajax_syncly_test_webhook', [ $this, 'handle_test_webhook' ] );
		add_action( 'wp_ajax_syncly_regenerate_webhook_secret', [ $this, 'handle_regenerate_webhook_secret' ] );
		add_action( 'syncly_process_webhook_async', [ $this, 'process_webhook_async' ], 10, 2 );
	}

	/**
	 * Register REST API endpoint for webhooks.
	 *
	 * @return void
	 */
	public function register_webhook_endpoint(): void {
		register_rest_route(
			'syncly/v1',
			'/webhooks',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_webhook' ],
				'permission_callback' => [ $this, 'verify_webhook_signature' ],
			]
		);
	}

	/**
	 * Get webhook URL.
	 *
	 * @return string
	 */
	public function get_webhook_url(): string {
		return rest_url( 'syncly/v1/webhooks' );
	}

	/**
	 * Verify webhook signature via shared secret header.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function verify_webhook_signature( \WP_REST_Request $request ) {
		$secret = trim( (string) $this->settings_manager->get_setting( 'webhook_secret', '' ) );

		if ( '' === $secret ) {
			return new \WP_Error(
				'webhook_secret_missing',
				__( 'Webhook secret not configured. Regenerate it in settings and add it to your GoHighLevel webhook headers.', 'syncly' ),
				[ 'status' => 401 ]
			);
		}

		$content_type = (string) $request->get_header( 'content-type' );

		if ( '' === $content_type || false === stripos( $content_type, 'application/json' ) ) {
			return new \WP_Error(
				'invalid_content_type',
				__( 'Content-Type must be application/json', 'syncly' ),
				[ 'status' => 415 ]
			);
		}

		$raw_body   = (string) $request->get_body();
		$body_bytes = strlen( $raw_body );

		if ( $body_bytes > self::MAX_WEBHOOK_BODY_BYTES ) {
			return new \WP_Error(
				'payload_too_large',
				__( 'Webhook payload exceeds the allowed size.', 'syncly' ),
				[ 'status' => 413 ]
			);
		}

		if ( ! is_array( $request->get_json_params() ) ) {
			return new \WP_Error(
				'invalid_webhook_payload',
				__( 'Webhook payload must be a valid JSON object.', 'syncly' ),
				[ 'status' => 400 ]
			);
		}

		$provided_token = trim( (string) $request->get_header( self::WEBHOOK_SECRET_HEADER ) );

		if ( '' === $provided_token || ! hash_equals( $secret, $provided_token ) ) {
			return new \WP_Error(
				'invalid_webhook_signature',
				__( 'Invalid or missing webhook token.', 'syncly' ),
				[ 'status' => 401 ]
			);
		}

		return true;
	}

	/**
	 * Get existing webhook secret or generate one if missing.
	 *
	 * @return string
	 */
	public function get_or_create_webhook_secret(): string {
		$secret = (string) $this->settings_manager->get_setting( 'webhook_secret', '' );

		if ( '' !== $secret ) {
			return $secret;
		}

		return $this->generate_and_store_webhook_secret();
	}

	/**
	 * Generate and persist a new webhook secret.
	 *
	 * @return string
	 */
	private function generate_and_store_webhook_secret(): string {
		$secret = wp_generate_password( 48, false, false );

		$this->settings_manager->update_setting( 'webhook_secret', $secret );

		return $secret;
	}

	/**
	 * Get best-effort remote IP for logging.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return string
	 */
	private function get_remote_ip( \WP_REST_Request $request ): string {
		$ip = $request->get_header( 'x-forwarded-for' );

		if ( is_string( $ip ) && '' !== $ip ) {
			$parts     = explode( ',', $ip );
			$candidate = trim( $parts[0] );

			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		$remote_addr = $request->get_header( 'remote_addr' );

		if ( is_string( $remote_addr ) && '' !== $remote_addr ) {
			$candidate = trim( $remote_addr );

			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		$server_remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';

		if ( is_string( $server_remote_addr ) && '' !== $server_remote_addr ) {
			$candidate = trim( $server_remote_addr );

			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Handle AJAX request to regenerate the webhook secret.
	 *
	 * @return void
	 */
	public function handle_regenerate_webhook_secret(): void {
		check_ajax_referer( 'syncly_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Permission denied.', 'syncly' ),
				],
				403
			);
		}

		$secret = $this->generate_and_store_webhook_secret();

		wp_send_json_success(
			[
				'webhook_secret' => $secret,
				'header'         => self::WEBHOOK_SECRET_HEADER,
				'message'        => __( 'Webhook secret regenerated. Update your GoHighLevel automation headers.', 'syncly' ),
			]
		);
	}

	/**
	 * Handle incoming webhook from GoHighLevel.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_webhook( \WP_REST_Request $request ) {
		$body = $request->get_json_params();

		if ( ! $this->process_webhook_async( $body, $request->get_headers() ) ) {
			return new \WP_Error(
				'webhook_processing_failed',
				__( 'Webhook processing failed and should be retried.', 'syncly' ),
				[ 'status' => 500 ]
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => 'Webhook received and processed',
			],
			200
		);
	}

	/**
	 * Process webhook asynchronously.
	 *
	 * @param array $body    Webhook payload.
	 * @param array $headers Request headers.
	 * @return bool
	 */
	public function process_webhook_async( array $body, array $headers ): bool {
		$normalized = $this->normalize_webhook_payload( $body );
		$event_key  = $this->get_webhook_event_key( $body, $normalized );

		if ( ! $this->claim_webhook_event( $event_key ) ) {
			return true;
		}

		if ( isset( $normalized['data']['id'] ) && ! empty( $normalized['data']['id'] ) ) {
			$contact_id = (string) $normalized['data']['id'];

			set_transient(
				'syncly_inbound_webhook_' . $contact_id,
				time(),
				30
			);
		}

		$event_type = $normalized['type'] ?? '';

		if (
			in_array( $event_type, [ 'ContactCreate', 'ContactUpdate' ], true ) &&
			empty( $normalized['data']['email'] )
		) {
			$this->complete_webhook_event( $event_key );
			return true;
		}

		$this->logger->log(
			'webhook',
			0,
			'ghl_to_wp',
			'success',
			'Webhook received from GoHighLevel',
			[
				'type'        => $normalized['type'] ?? 'unknown',
				'event_key'   => $event_key,
				'location_id' => $normalized['locationId'] ?? '',
			]
		);

		if ( empty( $normalized['type'] ) ) {
			$this->logger->log(
				'webhook',
				0,
				'ghl_to_wp',
				'error',
				'Webhook missing type field',
				[ 'payload' => $body ]
			);

			$this->release_webhook_event( $event_key );

			return false;
		}

		$active_location_id  = $this->settings_manager->get_setting( 'location_id', '' );
		$webhook_location_id = $normalized['locationId'] ?? '';

		if (
			! empty( $active_location_id ) &&
			! empty( $webhook_location_id ) &&
			$webhook_location_id !== $active_location_id
		) {
			$this->logger->log(
				'webhook',
				0,
				'ghl_to_wp',
				'info',
				'Webhook ignored: location mismatch',
				[
					'active_location'  => $active_location_id,
					'webhook_location' => $webhook_location_id,
					'type'             => $normalized['type'] ?? 'unknown',
				]
			);

			$this->complete_webhook_event( $event_key );

			return true;
		}

		$result = $this->route_webhook_event( $normalized );

		if ( is_wp_error( $result ) || false === $result ) {
			$error_message = is_wp_error( $result )
				? $result->get_error_message()
				: __( 'Unknown webhook processing error.', 'syncly' );

			$this->logger->log(
				'webhook',
				0,
				'ghl_to_wp',
				'error',
				'Webhook processing failed: ' . $error_message,
				[
					'type'  => $normalized['type'] ?? 'unknown',
					'error' => $error_message,
				]
			);

			do_action(
				'syncly_log_event',
				'webhook_processing_error',
				'Webhook processing failed',
				[
					'type'       => $normalized['type'] ?? 'unknown',
					'locationId' => $normalized['locationId'] ?? '',
					'error'      => $error_message,
					'site_id'    => get_current_blog_id(),
				],
				'error'
			);

			$this->release_webhook_event( $event_key );

			return false;
		}

		$this->complete_webhook_event( $event_key );

		return true;
	}

	/**
	 * Build a stable durable deduplication key for a webhook delivery.
	 *
	 * @param array $body       Raw webhook body.
	 * @param array $normalized Normalized webhook body.
	 * @return string
	 */
	private function get_webhook_event_key( array $body, array $normalized ): string {
		$event_id = $body['eventId']
			?? $body['event_id']
			?? $body['webhookId']
			?? $body['webhook_id']
			?? '';

		$identity = is_scalar( $event_id ) && '' !== (string) $event_id
			? (string) $event_id
			: wp_json_encode( $normalized );

		return 'syncly_webhook_' . md5( (string) $identity );
	}

	/**
	 * Atomically claim a webhook event for processing.
	 *
	 * @param string $event_key Event key.
	 * @return bool
	 */
	private function claim_webhook_event( string $event_key ): bool {
		$lock_key = $event_key . '_lock';

		if ( ! add_option( $lock_key, time(), '', 'no' ) ) {
			return false;
		}

		try {
			if ( get_transient( $event_key ) ) {
				return false;
			}

			set_transient(
				$event_key,
				'processing',
				10 * MINUTE_IN_SECONDS
			);

			return true;
		} finally {
			delete_option( $lock_key );
		}
	}

	/**
	 * Mark webhook event as successfully processed.
	 *
	 * @param string $event_key Event key.
	 * @return void
	 */
	private function complete_webhook_event( string $event_key ): void {
		set_transient(
			$event_key,
			'completed',
			7 * DAY_IN_SECONDS
		);
	}

	/**
	 * Release a failed event so the provider can retry it.
	 *
	 * @param string $event_key Event key.
	 * @return void
	 */
	private function release_webhook_event( string $event_key ): void {
		delete_transient( $event_key );
	}

	/**
	 * Normalize webhook payload from GHL to Syncly's expected format.
	 *
	 * @param array $payload Raw webhook payload.
	 * @return array
	 */
	private function normalize_webhook_payload( array $payload ): array {
		if ( isset( $payload['type'] ) && isset( $payload['data'] ) ) {
			return $payload;
		}

		$contact_id = $payload['contact_id']
			?? $payload['contactId']
			?? (
				is_array( $payload['contact'] ?? null )
					? ( $payload['contact']['id'] ?? null )
					: null
			)
			?? (
				isset( $payload['id'], $payload['email'] )
					? $payload['id']
					: null
			);

		if ( $contact_id ) {
			$type = 'ContactUpdate';

			$field_count = count( array_filter( $payload ) );

			if ( $field_count <= 3 ) {
				$type = 'ContactDelete';
			}

			$tags_raw = $payload['tags'] ?? null;
			$tags     = [];

			if ( is_array( $tags_raw ) ) {
				$tags = $tags_raw;
			} elseif ( is_string( $tags_raw ) && '' !== $tags_raw ) {
				$tags = array_map( 'trim', explode( ',', $tags_raw ) );
			}

			return [
				'type'       => $type,
				'locationId' => $payload['location']['id'] ?? '',
				'data'       => [
					'id'           => $contact_id,
					'email'        => $payload['email'] ?? '',
					'name'         => $payload['full_name'] ?? (
						( $payload['first_name'] ?? '' ) . ' ' . ( $payload['last_name'] ?? '' )
					),
					'firstName'    => $payload['first_name'] ?? '',
					'lastName'     => $payload['last_name'] ?? '',
					'phone'        => $payload['phone'] ?? '',
					'tags'         => $tags,
					'source'       => $payload['contact_source'] ?? '',
					'country'      => $payload['country'] ?? '',
					'address'      => $payload['full_address'] ?? '',
					'customFields' => $payload['customFields'] ?? $payload['customData'] ?? [],
				],
			];
		}

		return $payload;
	}

	/**
	 * Route webhook event to appropriate handler.
	 *
	 * @param array $payload Webhook payload.
	 * @return bool|\WP_Error
	 */
	private function route_webhook_event( array $payload ) {
		$type = $payload['type'] ?? '';

		switch ( $type ) {
			case 'ContactCreate':
				return $this->handle_contact_create( $payload );

			case 'ContactUpdate':
				return $this->handle_contact_update( $payload );

			case 'ContactDelete':
				return $this->handle_contact_delete( $payload );

			default:
				$this->logger->log(
					'webhook',
					0,
					'ghl_to_wp',
					'warning',
					"Unsupported webhook event: {$type}",
					[ 'type' => $type ]
				);

				return new \WP_Error(
					'unsupported_event',
					"Unsupported webhook event: {$type}"
				);
		}
	}

	/**
	 * Handle contact create webhook.
	 *
	 * @param array $payload Webhook payload.
	 * @return bool
	 */
	private function handle_contact_create( array $payload ): bool {
		$contact_data = $payload['data'] ?? [];

		if ( empty( $contact_data['id'] ) ) {
			return false;
		}

		if ( ! $this->is_sync_direction_enabled( 'ghl_to_wp' ) ) {
			$this->logger->log(
				'webhook',
				0,
				'ghl_to_wp',
				'info',
				'Webhook skipped: Sync direction disabled',
				[ 'reason' => 'Sync direction disabled' ],
				$contact_data['id']
			);

			return true;
		}

		if ( ! empty( $contact_data['email'] ) ) {
			$guard_key = 'ghl_skip_inbound_create_' . md5(
				strtolower( trim( $contact_data['email'] ) )
			);

			if ( get_transient( $guard_key ) ) {
				delete_transient( $guard_key );

				$this->logger->log(
					'webhook',
					0,
					'ghl_to_wp',
					'info',
					'ContactCreate webhook skipped: contact-only mode guard active for this email',
					[
						'email'      => $contact_data['email'],
						'contact_id' => $contact_data['id'],
					]
				);

				return true;
			}
		}

		$result = $this->ghl_sync->sync_contact_to_wordpress(
			$contact_data['id'],
			$contact_data
		);

		return ! is_wp_error( $result );
	}

	/**
	 * Handle contact update webhook.
	 *
	 * @param array $payload Webhook payload.
	 * @return bool
	 */
	private function handle_contact_update( array $payload ): bool {
		$contact_data = $payload['data'] ?? [];

		if ( empty( $contact_data['id'] ) ) {
			return false;
		}

		if ( ! $this->is_sync_direction_enabled( 'ghl_to_wp' ) ) {
			$this->logger->log(
				'webhook',
				0,
				'ghl_to_wp',
				'info',
				'Webhook skipped: Sync direction disabled',
				[ 'reason' => 'Sync direction disabled' ],
				$contact_data['id']
			);

			return true;
		}

		$result = $this->ghl_sync->sync_contact_to_wordpress(
			$contact_data['id'],
			$contact_data
		);

		return ! is_wp_error( $result );
	}

	/**
	 * Handle contact delete webhook.
	 *
	 * @param array $payload Webhook payload.
	 * @return bool
	 */
	private function handle_contact_delete( array $payload ): bool {
		$contact_data = $payload['data'] ?? [];

		if ( empty( $contact_data['id'] ) ) {
			return false;
		}

		if ( ! $this->is_sync_direction_enabled( 'ghl_to_wp' ) ) {
			return true;
		}

		$result = $this->ghl_sync->delete_wordpress_user(
			$contact_data['id']
		);

		return $result;
	}

	/**
	 * Handle test webhook AJAX request.
	 *
	 * @return void
	 */
	public function handle_test_webhook(): void {
		try {
			check_ajax_referer( 'syncly_admin', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error(
					[
						'message' => __( 'Permission denied', 'syncly' ),
					]
				);
			}

			$webhook_secret = isset( $_POST['webhook_secret'] )
				? sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) )
				: '';

			if ( '' === $webhook_secret ) {
				wp_send_json_error(
					[
						'message' => __( 'Webhook secret is required.', 'syncly' ),
					]
				);
			}

			$result = $this->test_webhook_endpoint( $webhook_secret );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					[
						'message' => $result->get_error_message(),
					]
				);
			}

			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'Failed to test webhook: %s', 'syncly' ),
						$e->getMessage()
					),
					'code'    => 'exception',
					'details' => $e->getCode(),
				]
			);
		}
	}

	/**
	 * Check if sync direction is enabled.
	 *
	 * @param string $direction Sync direction.
	 * @return bool
	 */
	private function is_sync_direction_enabled( string $direction ): bool {
		$settings       = $this->settings_manager->get_settings_array();
		$sync_direction = $settings['sync_direction'] ?? 'both';

		if ( 'both' === $sync_direction ) {
			return true;
		}

		return $sync_direction === $direction;
	}

	/**
	 * Get webhook setup instructions for manual configuration.
	 *
	 * @return array
	 */
	public function get_webhook_setup_instructions(): array {
		$webhook_url    = $this->get_webhook_url();
		$webhook_secret = $this->get_or_create_webhook_secret();

		return [
			'webhook_url'    => $webhook_url,
			'webhook_secret' => $webhook_secret,
			'webhook_header' => self::WEBHOOK_SECRET_HEADER,
			'instructions'   => [
				'title'       => 'Manual Webhook Setup in GoHighLevel',
				'description' => 'Copy the webhook URL below and create an automation in your GoHighLevel account.',
				'steps'       => [
					'1. Copy this webhook URL: ' . $webhook_url,
					'2. Log into your GoHighLevel account',
					'3. Go to Automation → Workflows',
					'4. Create a new workflow or edit existing one',
					'5. Add trigger: Contact Created/Updated/Deleted',
					'6. Add action: Outbound Webhook',
					'7. Paste the webhook URL from step 1',
					'8. Set method to POST',
					'9. Add header: ' . strtoupper( self::WEBHOOK_SECRET_HEADER ) . ' = ' . $webhook_secret,
					'10. Set Content-Type header to application/json',
					'11. Configure the JSON body with contact data',
					'12. Save and activate the workflow',
				],
			],
			'payload_examples' => [
				'contact_created' => [
					'type'       => 'ContactCreate',
					'locationId' => '{{location.id}}',
					'data'       => [
						'id'        => '{{contact.id}}',
						'email'     => '{{contact.email}}',
						'name'      => '{{contact.name}}',
						'firstName' => '{{contact.first_name}}',
						'lastName'  => '{{contact.last_name}}',
						'phone'     => '{{contact.phone}}',
						'tags'      => [ '{{contact.tags}}' ],
					],
				],
				'contact_updated' => [
					'type'       => 'ContactUpdate',
					'locationId' => '{{location.id}}',
					'data'       => [
						'id'        => '{{contact.id}}',
						'email'     => '{{contact.email}}',
						'name'      => '{{contact.name}}',
						'firstName' => '{{contact.first_name}}',
						'lastName'  => '{{contact.last_name}}',
						'phone'     => '{{contact.phone}}',
						'tags'      => [ '{{contact.tags}}' ],
					],
				],
				'contact_deleted' => [
					'type'       => 'ContactDelete',
					'locationId' => '{{location.id}}',
					'data'       => [
						'id' => '{{contact.id}}',
					],
				],
			],
			'supported_events' => [
				'ContactCreate' => 'When a contact is created',
				'ContactUpdate' => 'When a contact is updated',
				'ContactDelete' => 'When a contact is deleted',
			],
		];
	}

	/**
	 * Test webhook endpoint to verify it's working.
	 *
	 * @param string $webhook_secret Webhook secret to use for the test.
	 * @return array|\WP_Error
	 */
	public function test_webhook_endpoint( string $webhook_secret ) {
		$webhook_url = $this->get_webhook_url();

		$sample_payload = [
			'type'       => 'ContactCreate',
			'locationId' => 'test_location_123',
			'data'       => [
				'id'        => 'test_contact_' . time(),
				'email'     => 'test@example.com',
				'name'      => 'Test Contact',
				'firstName' => 'Test',
				'lastName'  => 'Contact',
				'phone'     => '+1234567890',
			],
		];

		try {
			$response = wp_remote_post(
				$webhook_url,
				[
					'headers' => [
						'Content-Type' => 'application/json',
						'User-Agent'   => 'GHL-Webhook-Test/1.0',
						'X-GHL-Token'  => $webhook_secret,
					],
					'body'    => wp_json_encode( $sample_payload ),
					'timeout' => 30,
				]
			);

			if ( is_wp_error( $response ) ) {
				return new \WP_Error(
					'webhook_test_failed',
					$response->get_error_message()
				);
			}

			$status_code   = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			$this->logger->log(
				'webhook',
				0,
				'system',
				200 === $status_code ? 'success' : 'warning',
				'Webhook endpoint test completed',
				[
					'status_code' => $status_code,
					'response'    => $response_body,
					'url'         => $webhook_url,
				]
			);

			return [
				'success'     => 200 === $status_code,
				'status_code' => $status_code,
				'response'    => json_decode( $response_body, true ),
				'url'         => $webhook_url,
				'message'     => 200 === $status_code
					? 'Webhook endpoint is working correctly!'
					: 'Webhook endpoint returned an error.',
			];
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'webhook_test_error',
				$e->getMessage()
			);
		}
	}

	/**
	 * Get webhook status.
	 *
	 * @return array
	 */
	public function get_webhook_status(): array {
		$webhook_url = $this->get_webhook_url();

		global $wpdb;

		$table_name = $wpdb->prefix . 'ghl_sync_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$recent_webhooks = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name}
				WHERE sync_type LIKE %s
				AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
				$wpdb->esc_like( 'webhook' ) . '%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_webhook = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name}
				WHERE sync_type LIKE %s
				ORDER BY created_at DESC
				LIMIT 1",
				$wpdb->esc_like( 'webhook' ) . '%'
			)
		);

		return [
			'webhook_url'           => $webhook_url,
			'is_configured'         => $recent_webhooks > 0,
			'recent_webhooks_24h'   => (int) $recent_webhooks,
			'last_webhook_received' => $last_webhook ? $last_webhook->created_at : null,
			'status'                => $recent_webhooks > 0 ? 'active' : 'not_configured',
			'message'               => $recent_webhooks > 0
				? sprintf(
					'Webhook is active. Received %d webhooks in the last 24 hours.',
					$recent_webhooks
				)
				: 'No webhooks received. Please set up the webhook in your GoHighLevel account.',
		];
	}
}