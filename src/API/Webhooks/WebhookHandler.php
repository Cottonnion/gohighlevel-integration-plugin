<?php
declare(strict_types=1);

namespace GHL_CRM\API\Webhooks;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Sync\QueueManager;
use GHL_CRM\Sync\SyncLogger;
use GHL_CRM\Sync\GHLToWordPressSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook Handler
 *
 * Handles incoming webhooks from GoHighLevel.
 * Users must manually set up webhooks in their GHL account using the provided URL.
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/Webhooks
 */
class WebhookHandler {
	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Queue Manager
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Sync Logger
	 *
	 * @var SyncLogger
	 */
	private SyncLogger $logger;

	/**
	 * GHL to WordPress Sync
	 *
	 * @var GHLToWordPressSync
	 */
	private GHLToWordPressSync $ghl_sync;

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
	 * Constructor
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
		$this->queue_manager    = QueueManager::get_instance();
		$this->logger           = SyncLogger::get_instance();
		$this->ghl_sync         = GHLToWordPressSync::get_instance();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_webhook_endpoint' ] );
		add_action( 'wp_ajax_ghl_crm_test_webhook', [ $this, 'handle_test_webhook' ] );
		add_action( 'ghl_process_webhook_async', [ $this, 'process_webhook_async' ], 10, 2 );
	}

	/**
	 * Register REST API endpoint for webhooks
	 *
	 * @return void
	 */
	public function register_webhook_endpoint(): void {
		register_rest_route(
			'ghl-crm/v1',
			'/webhooks',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_webhook' ],
				'permission_callback' => [ $this, 'verify_webhook_signature' ],
			]
		);
	}

	/**
	 * Get webhook URL
	 *
	 * @return string
	 */
	public function get_webhook_url(): string {
		return rest_url( 'ghl-crm/v1/webhooks' );
	}

	/**
	 * Verify webhook signature (simplified for manual setup)
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return bool
	 */
	public function verify_webhook_signature( \WP_REST_Request $request ): bool {
		// For manual setup, we allow all requests
		// Users can implement additional security if needed
		return true;
	}

	/**
	 * Handle incoming webhook from GoHighLevel
	 *
	 * Processes webhook according to GHL documentation:
	 * - Accept POST with JSON payload
	 * - Process data (type, data payload)
	 * - Return 200 OK quickly for best performance
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( \WP_REST_Request $request ) {
		$body = $request->get_json_params();

		error_log( '[GHL Webhook] ===== WEBHOOK RECEIVED =====' );
		error_log( '[GHL Webhook] Raw payload: ' . wp_json_encode( $body ) );
		error_log( '[GHL Webhook] Headers: ' . wp_json_encode( $request->get_headers() ) );
		error_log( '[GHL Webhook] Remote IP: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );

		// Process the webhook synchronously (WP-Cron might be disabled on live sites)
		// The actual sync happens via queue processor, so this is just validation + queueing
		$this->process_webhook_async( $body, $request->get_headers() );

		error_log( '[GHL Webhook] Webhook processed successfully' );

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => 'Webhook received and processed',
			],
			200
		);
	}

	/**
	 * Process webhook asynchronously
	 *
	 * @param array $body    Webhook payload
	 * @param array $headers Request headers
	 * @return void
	 */
	public function process_webhook_async( array $body, array $headers ): void {
		error_log( '[GHL Webhook] ===== ASYNC PROCESSING STARTED =====' );
		error_log( '[GHL Webhook] Processing at: ' . current_time( 'mysql' ) );
		
		// Detect webhook format and normalize
		$normalized = $this->normalize_webhook_payload( $body );

		error_log( '[GHL Webhook] Normalized payload: ' . wp_json_encode( $normalized ) );
		error_log( '[GHL Webhook] Detected type: ' . ( $normalized['type'] ?? 'UNKNOWN' ) );

		// Log webhook receipt
		$this->logger->log(
			'webhook_received',
			0,
			'ghl_to_wp',
			'success',
			'Webhook received from GoHighLevel',
			[
				'type'               => $normalized['type'] ?? 'unknown',
				'raw_payload'        => $body,
				'normalized_payload' => $normalized,
			]
		);

		// Validate payload
		if ( empty( $normalized['type'] ) ) {
			error_log( '[GHL Webhook] ERROR: Missing type field in normalized payload' );
			
			$this->logger->log(
				'webhook_invalid',
				0,
				'ghl_to_wp',
				'error',
				'Webhook missing type field',
				[ 'payload' => $body ]
			);
			return;
		}

		// Route to appropriate handler
		error_log( '[GHL Webhook] Routing to handler for type: ' . $normalized['type'] );
		$result = $this->route_webhook_event( $normalized );

		if ( is_wp_error( $result ) ) {
			error_log( '[GHL Webhook] ERROR: ' . $result->get_error_message() );
			
			$this->logger->log(
				'webhook_processing_error',
				0,
				'ghl_to_wp',
				'error',
				'Webhook processing failed: ' . $result->get_error_message(),
				[
					'type'  => $normalized['type'] ?? 'unknown',
					'error' => $result->get_error_message(),
				]
			);
		} else {
			error_log( '[GHL Webhook] ✓ Webhook processed successfully' );
		}
		
		error_log( '[GHL Webhook] ===== ASYNC PROCESSING COMPLETE =====' );
	}

	/**
	 * Normalize webhook payload from GHL's actual format to our expected format
	 *
	 * GHL sends data like: {contact_id, first_name, last_name, email, tags, ...}
	 * We need it like: {type, data: {id, firstName, lastName, email, tags, ...}}
	 *
	 * @param array $payload Raw webhook payload from GHL
	 * @return array Normalized payload
	 */
	private function normalize_webhook_payload( array $payload ): array {
		error_log( '[GHL Webhook] Normalizing payload...' );
		
		// If already in our format, return as-is
		if ( isset( $payload['type'] ) && isset( $payload['data'] ) ) {
			error_log( '[GHL Webhook] Payload already normalized' );
			return $payload;
		}

		// Detect event type from GHL payload
		// If contact_id exists, it's a contact event
		if ( isset( $payload['contact_id'] ) ) {
			error_log( '[GHL Webhook] Detected contact_id: ' . $payload['contact_id'] );
			
			// Determine if it's create, update, or delete based on available fields
			$type = 'ContactUpdate'; // Default to update for existing contacts

			// If minimal fields, might be a delete
			$field_count = count( array_filter( $payload ) );
			error_log( '[GHL Webhook] Field count: ' . $field_count );
			
			if ( $field_count <= 3 ) {
				$type = 'ContactDelete';
				error_log( '[GHL Webhook] Detected as DELETE (minimal fields)' );
			} else {
				error_log( '[GHL Webhook] Detected as UPDATE (has data fields)' );
			}

			// Normalize to our format
			$normalized = [
				'type'       => $type,
				'locationId' => $payload['location']['id'] ?? '',
				'data'       => [
					'id'           => $payload['contact_id'],
					'email'        => $payload['email'] ?? '',
					'name'         => $payload['full_name'] ?? ( ( $payload['first_name'] ?? '' ) . ' ' . ( $payload['last_name'] ?? '' ) ),
					'firstName'    => $payload['first_name'] ?? '',
					'lastName'     => $payload['last_name'] ?? '',
					'phone'        => $payload['phone'] ?? '',
					'tags'         => isset( $payload['tags'] ) ? ( is_array( $payload['tags'] ) ? $payload['tags'] : explode( ',', $payload['tags'] ) ) : [],
					'source'       => $payload['contact_source'] ?? '',
					'country'      => $payload['country'] ?? '',
					'address'      => $payload['full_address'] ?? '',
					'customFields' => $payload['customData'] ?? [],
				],
			];
			
			error_log( '[GHL Webhook] Normalized contact data - Email: ' . ( $normalized['data']['email'] ?? 'NONE' ) );
			error_log( '[GHL Webhook] Normalized contact data - Name: ' . ( $normalized['data']['name'] ?? 'NONE' ) );
			
			return $normalized;
		}

		// Unknown format, return as-is and let it fail validation
		error_log( '[GHL Webhook] WARNING: Unknown payload format, no contact_id found' );
		return $payload;
	}

	/**
	 * Route webhook event to appropriate handler
	 *
	 * @param array $payload Webhook payload
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
				// Log unsupported event type
				$this->logger->log(
					'webhook_unsupported',
					0,
					'ghl_to_wp',
					'warning',
					"Unsupported webhook event: {$type}",
					[ 'type' => $type ]
				);
				return new \WP_Error( 'unsupported_event', "Unsupported webhook event: {$type}" );
		}
	}

	/**
	 * Handle contact create webhook
	 *
	 * @param array $payload Webhook payload
	 * @return bool
	 */
	private function handle_contact_create( array $payload ): bool {
		error_log( '[GHL Webhook] Handle Contact CREATE' );
		
		$contact_data = $payload['data'] ?? [];

		if ( empty( $contact_data['id'] ) ) {
			error_log( '[GHL Webhook] ERROR: Missing contact ID in create payload' );
			return false;
		}

		error_log( '[GHL Webhook] Contact ID: ' . $contact_data['id'] );
		error_log( '[GHL Webhook] Contact Email: ' . ( $contact_data['email'] ?? 'NONE' ) );

		// Check if sync from GHL to WP is enabled
		if ( ! $this->is_sync_direction_enabled( 'ghl_to_wp' ) ) {
			error_log( '[GHL Webhook] SKIPPED: Sync direction GHL->WP is disabled' );
			
			$this->logger->log(
				'webhook_skipped',
				0,
				'ghl_to_wp',
				'info',
				'Webhook skipped: Sync direction disabled',
				[ 'reason' => 'Sync direction disabled' ],
				$contact_data['id']
			);
			return true;
		}

		error_log( '[GHL Webhook] Adding to queue: contact_create' );

		// Process synchronously instead of queueing for immediate feedback
		error_log( '[GHL Webhook] Processing contact create synchronously...' );
		
		$result = $this->ghl_sync->sync_contact_to_wordpress( $contact_data['id'] );

		if ( is_wp_error( $result ) ) {
			error_log( '[GHL Webhook] ✗ Failed to create contact: ' . $result->get_error_message() );
			return false;
		}

		error_log( '[GHL Webhook] ✓ Contact created successfully (User ID: ' . $result . ')' );
		return true;
	}

	/**
	 * Handle contact update webhook
	 *
	 * @param array $payload Webhook payload
	 * @return bool
	 */
	private function handle_contact_update( array $payload ): bool {
		error_log( '[GHL Webhook] Handle Contact UPDATE' );
		
		$contact_data = $payload['data'] ?? [];

		if ( empty( $contact_data['id'] ) ) {
			error_log( '[GHL Webhook] ERROR: Missing contact ID in update payload' );
			return false;
		}

		error_log( '[GHL Webhook] Contact ID: ' . $contact_data['id'] );
		error_log( '[GHL Webhook] Contact Email: ' . ( $contact_data['email'] ?? 'NONE' ) );

		// Check if sync from GHL to WP is enabled
		if ( ! $this->is_sync_direction_enabled( 'ghl_to_wp' ) ) {
			error_log( '[GHL Webhook] SKIPPED: Sync direction GHL->WP is disabled' );
			
			$this->logger->log(
				'webhook_skipped',
				0,
				'ghl_to_wp',
				'info',
				'Webhook skipped: Sync direction disabled',
				[ 'reason' => 'Sync direction disabled' ],
				$contact_data['id']
			);
			return true;
		}

		error_log( '[GHL Webhook] Adding to queue: contact_update' );

		// Process synchronously instead of queueing for immediate feedback
		error_log( '[GHL Webhook] Processing contact update synchronously...' );
		
		$result = $this->ghl_sync->sync_contact_to_wordpress( $contact_data['id'] );

		if ( is_wp_error( $result ) ) {
			error_log( '[GHL Webhook] ✗ Failed to update contact: ' . $result->get_error_message() );
			return false;
		}

		error_log( '[GHL Webhook] ✓ Contact updated successfully (User ID: ' . $result . ')' );
		return true;
	}

	/**
	 * Handle contact delete webhook
	 *
	 * @param array $payload Webhook payload
	 * @return bool
	 */
	private function handle_contact_delete( array $payload ): bool {
		error_log( '[GHL Webhook] Handle Contact DELETE' );
		
		$contact_data = $payload['data'] ?? [];

		if ( empty( $contact_data['id'] ) ) {
			error_log( '[GHL Webhook] ERROR: Missing contact ID in delete payload' );
			return false;
		}

		error_log( '[GHL Webhook] Contact ID to delete: ' . $contact_data['id'] );

		// Check if sync from GHL to WP is enabled
		if ( ! $this->is_sync_direction_enabled( 'ghl_to_wp' ) ) {
			error_log( '[GHL Webhook] SKIPPED: Sync direction GHL->WP is disabled' );
			return true;
		}

		error_log( '[GHL Webhook] Adding to queue: contact_delete' );

		// Process synchronously instead of queueing for immediate feedback
		error_log( '[GHL Webhook] Processing contact delete synchronously...' );
		
		$result = $this->ghl_sync->delete_wordpress_user( $contact_data['id'] );

		if ( $result ) {
			error_log( '[GHL Webhook] ✓ Contact deleted/unlinked successfully' );
		} else {
			error_log( '[GHL Webhook] ✗ Failed to delete/unlink contact' );
		}

		return $result;

		error_log( '[GHL Webhook] ✓ Contact delete queued successfully' );
		return true;
	}


	/**
	 * Handle test webhook AJAX request
	 *
	 * @return void
	 */
	public function handle_test_webhook(): void {
		try {
			check_ajax_referer( 'ghl_crm_admin', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ] );
			}

			$webhook_handler = self::get_instance();
			$result          = $webhook_handler->test_webhook_endpoint();

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ] );
			} else {
				wp_send_json_success( $result );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'Failed to test webhook: %s', 'ghl-crm-integration' ),
						$e->getMessage()
					),
					'code'    => 'exception',
					'details' => $e->getCode(),
				]
			);
		}
	}

	/**
	 * Check if sync direction is enabled
	 *
	 * @param string $direction Sync direction (wp_to_ghl, ghl_to_wp, bidirectional)
	 * @return bool
	 */
	private function is_sync_direction_enabled( string $direction ): bool {
		$settings       = $this->settings_manager->get_settings_array();
		$sync_direction = $settings['sync_direction'] ?? 'both'; // Changed default to 'both'
		
		if ( 'both' === $sync_direction ) {
			return true;
		}
		
		return $sync_direction === $direction;
	}

	/**
	 * Get webhook setup instructions for manual configuration
	 *
	 * @return array
	 */
	public function get_webhook_setup_instructions(): array {
		$webhook_url = $this->get_webhook_url();

		return [
			'webhook_url'      => $webhook_url,
			'instructions'     => [
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
					'9. Set Content-Type header to application/json',
					'10. Configure the JSON body with contact data',
					'11. Save and activate the workflow',
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
	 * Test webhook endpoint to verify it's working
	 *
	 * @return array|\WP_Error
	 */
	public function test_webhook_endpoint() {
		$webhook_url = $this->get_webhook_url();

		// Sample contact create payload
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
			// Test the endpoint
			$response = wp_remote_post(
				$webhook_url,
				[
					'headers' => [
						'Content-Type' => 'application/json',
						'User-Agent'   => 'GHL-Webhook-Test/1.0',
					],
					'body'    => json_encode( $sample_payload ),
					'timeout' => 30,
				]
			);

			if ( is_wp_error( $response ) ) {
				return new \WP_Error( 'webhook_test_failed', $response->get_error_message() );
			}

			$status_code   = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );

			$this->logger->log(
				'webhook_test_completed',
				0,
				'system',
				$status_code === 200 ? 'success' : 'warning',
				'Webhook endpoint test completed',
				[
					'status_code' => $status_code,
					'response'    => $response_body,
					'url'         => $webhook_url,
				]
			);

			return [
				'success'     => $status_code === 200,
				'status_code' => $status_code,
				'response'    => json_decode( $response_body, true ),
				'url'         => $webhook_url,
				'message'     => $status_code === 200 ? 'Webhook endpoint is working correctly!' : 'Webhook endpoint returned an error.',
			];

		} catch ( \Exception $e ) {
			return new \WP_Error( 'webhook_test_error', $e->getMessage() );
		}
	}

	/**
	 * Test webhook endpoint to verify it's working /**
	 * Check if webhook has been set up (basic validation)
	 *
	 * @return array
	 */
	public function get_webhook_status(): array {
		$webhook_url = $this->get_webhook_url();

		// Check if we've received any webhooks recently
		global $wpdb;
		$table_name = $wpdb->prefix . 'ghl_sync_log';

		// Count recent webhook events (sync_type starting with 'webhook')
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Inspecting webhook activity in plugin log table.
		$recent_webhooks = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} 
				WHERE sync_type LIKE %s 
				AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
				$wpdb->esc_like( 'webhook' ) . '%'
			)
		);

		// Get last webhook received
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Retrieving latest webhook log entry for status overview.
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
				? sprintf( 'Webhook is active. Received %d webhooks in the last 24 hours.', $recent_webhooks )
				: 'No webhooks received. Please set up the webhook in your GoHighLevel account.',
		];
	}
}
