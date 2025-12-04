<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GHL_CRM\API\Resources\CustomObjectResource;
use GHL_CRM\Core\SettingsManager;

/**
 * Custom Object Sync Handler
 *
 * Handles syncing WordPress posts to GHL Custom Objects
 * Integrates with existing queue system and logging
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 */
class CustomObjectSync {
	/**
	 * Queue Manager instance
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Sync Logger instance
	 *
	 * @var SyncLogger
	 */
	private SyncLogger $logger;

	/**
	 * Settings Manager instance
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings;

	/**
	 * Custom Object Resource
	 *
	 * @var CustomObjectResource
	 */
	private CustomObjectResource $custom_object_resource;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->queue_manager          = QueueManager::get_instance();
		$this->logger                 = SyncLogger::get_instance();
		$this->settings               = SettingsManager::get_instance();
		$this->custom_object_resource = new CustomObjectResource();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		
		// Hook into post save
		add_action( 'save_post', array( $this, 'handle_post_save' ), 10, 3 );

		// Hook into post trash
		add_action( 'wp_trash_post', array( $this, 'handle_post_trash' ) );

		// Hook into post delete
		add_action( 'before_delete_post', array( $this, 'handle_post_delete' ) );

		// WooCommerce product purchase hooks
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_woocommerce_order_completed' ), 10, 2 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'handle_woocommerce_order_processing' ), 10, 2 );
		add_action( 'woocommerce_thankyou', array( $this, 'handle_woocommerce_thankyou' ), 10, 1 );

		// Register with QueueProcessor to handle custom_object sync execution
		add_filter( 'ghl_crm_execute_sync', array( $this, 'execute_custom_object_sync' ), 10, 5 );
		
	}

	/**
	 * Handle post save event
	 *
	 * @param int      $post_id Post ID
	 * @param \WP_Post $post    Post object
	 * @param bool     $update  Whether this is an update
	 * @return void
	 */
	public function handle_post_save( int $post_id, \WP_Post $post, bool $update ): void {
		error_log( sprintf( '[GHL Custom Objects] Post save triggered - ID: %d, Type: %s, Status: %s, Update: %s', $post_id, $post->post_type, $post->post_status, $update ? 'yes' : 'no' ) );

		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			error_log( '[GHL Custom Objects] Skipped: Autosave' );
			return;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			error_log( '[GHL Custom Objects] Skipped: Revision' );
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			error_log( '[GHL Custom Objects] Skipped: No permission' );
			return;
		}

		// Get active mapping for this post type
		$mapping = $this->get_active_mapping_for_post_type( $post->post_type );
		if ( ! $mapping ) {
			error_log( sprintf( '[GHL Custom Objects] No active mapping found for post type: %s', $post->post_type ) );
			return;
		}

		error_log( sprintf( '[GHL Custom Objects] Found mapping: %s (ID: %s)', $mapping['name'] ?? 'Unnamed', $mapping['id'] ?? 'N/A' ) );

		// Check if this trigger is enabled
		$trigger = $update ? 'update' : 'publish';
		if ( ! in_array( $trigger, $mapping['triggers'] ?? array(), true ) ) {
			error_log( sprintf( '[GHL Custom Objects] Trigger "%s" not enabled. Enabled triggers: %s', $trigger, implode( ', ', $mapping['triggers'] ?? array() ) ) );
			return;
		}

		error_log( sprintf( '[GHL Custom Objects] Trigger "%s" is enabled', $trigger ) );

		// Only sync published posts
		if ( 'publish' !== $post->post_status ) {
			error_log( sprintf( '[GHL Custom Objects] Skipped: Post status is "%s", not "publish"', $post->post_status ) );
			return;
		}

		error_log( sprintf( '[GHL Custom Objects] Queuing sync operation for post ID: %d', $post_id ) );

		// Queue the sync operation
		$this->queue_sync_operation( $post_id, $mapping, 'sync_custom_object' );
	}

	/**
	 * Handle post trash event
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function handle_post_trash( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$mapping = $this->get_active_mapping_for_post_type( $post->post_type );
		if ( ! $mapping ) {
			return;
		}

		// Check if trash trigger is enabled
		if ( ! in_array( 'trash', $mapping['triggers'] ?? array(), true ) ) {
			return;
		}

		// Queue the delete operation
		$this->queue_sync_operation( $post_id, $mapping, 'delete' );
	}

	/**
	 * Handle post delete event
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function handle_post_delete( int $post_id ): void {
		$this->handle_post_trash( $post_id );
	}

	/**
	 * Handle WooCommerce order completion
	 * Triggers custom object sync for purchased products
	 *
	 * @param int $order_id Order ID
	 * @param \WC_Order|null $order Order object
	 * @return void
	 */
	public function handle_woocommerce_order_completed( int $order_id, $order = null ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			error_log( '[GHL Custom Objects] WooCommerce not available for order completed hook' );
			return;
		}

		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			error_log( sprintf( '[GHL Custom Objects] Could not retrieve order #%d', $order_id ) );
			return;
		}

		error_log( sprintf( '[GHL Custom Objects] Processing completed order #%d for custom objects sync', $order_id ) );

		// Get product mapping
		$mapping = $this->get_active_mapping_for_post_type( 'product' );
		if ( ! $mapping ) {
			error_log( '[GHL Custom Objects] No active mapping found for product post type' );
			return;
		}
		
		error_log( sprintf( '[GHL Custom Objects] Full mapping data: %s', wp_json_encode( $mapping ) ) );

		// Check if product_purchased trigger is enabled
		$triggers = $mapping['triggers'] ?? array();
		error_log( sprintf( '[GHL Custom Objects] Mapping triggers: %s', wp_json_encode( $triggers ) ) );
		
		if ( ! in_array( 'product_purchased', $triggers, true ) ) {
			error_log( '[GHL Custom Objects] product_purchased trigger not enabled in mapping' );
			return;
		}
		
		error_log( '[GHL Custom Objects] ✓ product_purchased trigger is enabled, proceeding with sync' );

		// Get order items
		$items = $order->get_items();
		if ( empty( $items ) ) {
			error_log( sprintf( '[GHL Custom Objects] No items found in order #%d', $order_id ) );
			return;
		}

		// Sync each product in the order
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			if ( ! $product_id ) {
				continue;
			}

			error_log( sprintf( 
				'[GHL Custom Objects] Queueing sync for product #%d from order #%d (purchaser: %s)', 
				$product_id, 
				$order_id, 
				$order->get_billing_email() 
			) );

			// Queue sync with order context
			$this->queue_sync_operation( 
				$product_id, 
				$mapping, 
				'sync_custom_object', 
				array(
					'trigger'          => 'product_purchased',
					'order_id'         => $order_id,
					'purchaser_email'  => $order->get_billing_email(),
					'purchaser_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					'quantity'         => $item->get_quantity(),
					'total'            => $item->get_total(),
					'order_total'      => $order->get_total(),
					'payment_method'   => $order->get_payment_method(),
				)
			);
		}

		error_log( sprintf( '[GHL Custom Objects] Queued %d product(s) from order #%d for sync', count( $items ), $order_id ) );
	}

	/**
	 * Handle WooCommerce order processing status
	 * Triggers custom object sync for products in processing orders
	 *
	 * @param int $order_id Order ID
	 * @param \WC_Order|null $order Order object
	 * @return void
	 */
	public function handle_woocommerce_order_processing( int $order_id, $order = null ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		error_log( sprintf( '[GHL Custom Objects] Processing order #%d (processing status)', $order_id ) );

		// Get product mapping
		$mapping = $this->get_active_mapping_for_post_type( 'product' );
		if ( ! $mapping ) {
			return;
		}

		// Check if order_processing OR product_purchased trigger is enabled
		// Many stores use "processing" as final status (virtual products, etc.)
		$triggers = $mapping['triggers'] ?? array();
		error_log( sprintf( '[GHL Custom Objects] Mapping triggers for order_processing: %s', wp_json_encode( $triggers ) ) );
		
		if ( ! in_array( 'order_processing', $triggers, true ) && ! in_array( 'product_purchased', $triggers, true ) ) {
			error_log( '[GHL Custom Objects] Neither order_processing nor product_purchased trigger enabled, skipping' );
			return;
		}
		
		error_log( '[GHL Custom Objects] ✓ Trigger enabled, proceeding with sync' );

		// Get order items
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return;
		}

		// Sync each product in the order
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			if ( ! $product_id ) {
				continue;
			}

			$this->queue_sync_operation( 
				$product_id, 
				$mapping, 
				'sync_custom_object', 
				array(
					'trigger'          => 'order_processing',
					'order_id'         => $order_id,
					'purchaser_email'  => $order->get_billing_email(),
					'purchaser_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					'quantity'         => $item->get_quantity(),
					'total'            => $item->get_total(),
				)
			);
		}
	}

	/**
	 * Handle WooCommerce thank you page
	 * Alternative hook for product purchase sync
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function handle_woocommerce_thankyou( int $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Only process if order is completed or processing
		if ( ! in_array( $order->get_status(), array( 'completed', 'processing' ), true ) ) {
			return;
		}

		error_log( sprintf( '[GHL Custom Objects] Thank you page triggered for order #%d', $order_id ) );

		// Get product mapping
		$mapping = $this->get_active_mapping_for_post_type( 'product' );
		if ( ! $mapping ) {
			return;
		}

		// Check if thankyou_page trigger is enabled (alternative trigger)
		$triggers = $mapping['triggers'] ?? array();
		error_log( sprintf( '[GHL Custom Objects] Mapping triggers for thankyou_page: %s', wp_json_encode( $triggers ) ) );
		
		if ( ! in_array( 'thankyou_page', $triggers, true ) ) {
			error_log( '[GHL Custom Objects] thankyou_page trigger not enabled in mapping, skipping' );
			return;
		}

		// Get order items
		$items = $order->get_items();
		if ( empty( $items ) ) {
			return;
		}

		// Sync each product in the order
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			if ( ! $product_id ) {
				continue;
			}

			$this->queue_sync_operation( 
				$product_id, 
				$mapping, 
				'sync_custom_object', 
				array(
					'trigger'          => 'thankyou_page',
					'order_id'         => $order_id,
					'purchaser_email'  => $order->get_billing_email(),
					'purchaser_name'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					'quantity'         => $item->get_quantity(),
					'total'            => $item->get_total(),
				)
			);
		}
	}

	/**
	 * Queue a sync operation
	 *
	 * @param int    $post_id  Post ID
	 * @param array  $mapping  Mapping configuration
	 * @param string $action   Action type (sync|delete)
	 * @return void
	 */
	/**
	 * Queue a custom object sync operation
	 *
	 * @param int    $post_id Post ID
	 * @param array  $mapping Mapping configuration
	 * @param string $action  Action to perform (sync_custom_object, delete)
	 * @param array  $context Optional context data (order_id, purchaser_email, etc.)
	 * @return void
	 */
	private function queue_sync_operation( int $post_id, array $mapping, string $action, array $context = array() ): void {
		// Build payload
		$payload = array(
			'mapping_id' => $mapping['id'] ?? '',
			'mapping'    => $mapping,
		);

		// Merge context data into payload
		if ( ! empty( $context ) ) {
			$payload['context'] = $context;
		}

		// Use correct QueueManager signature: add_to_queue( string $item_type, int $item_id, string $action, array $payload )
		$this->queue_manager->add_to_queue(
			'custom_object', // item_type
			$post_id,        // item_id
			$action,         // action
			$payload         // payload
		);

		// Logging disabled due to schema mismatch - using error_log instead
		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: Queued %s operation for post %d (mapping: %s)%s',
				$action,
				$post_id,
				$mapping['id'] ?? 'unknown',
				! empty( $context ) ? ' with context: ' . wp_json_encode( $context ) : ''
			)
		);
	}

	/**
	 * Execute custom object sync operation
	 * Called by QueueProcessor via ghl_crm_execute_sync filter
	 *
	 * @param bool   $result     Previous result (from other filters).
	 * @param string $item_type  Item type.
	 * @param string $action     Action.
	 * @param int    $item_id    Item ID (post ID).
	 * @param array  $payload    Payload data.
	 * @return array|bool API response on success, false on failure or if not custom_object type.
	 */
	public function execute_custom_object_sync( $result, string $item_type, string $action, int $item_id, array $payload ) {
		// Only handle custom_object type
		if ( 'custom_object' !== $item_type ) {
			return $result;
		}

		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: execute_custom_object_sync() called - Post ID: %d, Action: %s',
				$item_id,
				$action
			)
		);

		$post_id = $item_id;
		$mapping = $payload['mapping'] ?? array();
		$context = $payload['context'] ?? array(); // Extract context data

		if ( empty( $mapping ) ) {
			$error_msg = 'Invalid queue item - missing mapping';

			return false;
		}

		try {
			if ( 'delete' === $action ) {
				$result = $this->delete_record( $post_id, $mapping );
			} elseif ( in_array( $action, array( 'sync_custom_object', 'sync' ), true ) ) {
				$result = $this->sync_post( $post_id, $mapping, $context );
			} else {

				return false;
			}

			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: Sync completed - Post ID: %d, Result: %s',
					$post_id,
					$result ? 'SUCCESS' : 'FAILED'
				)
			);

			return $result;
		} catch ( \Exception $e ) {
			$error_msg = 'Sync failed: ' . $e->getMessage();
			// Detailed logging for debugging
			error_log( sprintf( '[GHL Custom Objects] execute_custom_object_sync ERROR: %s (post_id=%d, action=%s)', $e->getMessage(), $item_id, $action ) );
			if ( isset( $this->logger ) && method_exists( $this->logger, 'error' ) ) {
				$this->logger->error( $e->getMessage(), [ 'post_id' => $item_id, 'action' => $action, 'payload' => $payload ] );
			}

			return false;
		}
	}

	/**
	 * Sync a post to GHL Custom Object
	 *
	 * @param int   $post_id Post ID
	 * @param array $mapping Mapping configuration
	 * @return bool Success status
	 */
	/**
	 * Sync a post to GHL custom object
	 *
	 * @param int   $post_id Post ID
	 * @param array $mapping Mapping configuration
	 * @param array $context Optional context data (order_id, purchaser_email, etc.)
	 * @return bool Success status
	 */
	private function sync_post( int $post_id, array $mapping, array $context = array() ): bool {
		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: sync_post() started - Post ID: %d%s',
				$post_id,
				! empty( $context ) ? ' with context: ' . wp_json_encode( $context ) : ''
			)
		);

		$post = get_post( $post_id );
		if ( ! $post ) {

			return false;
		}

		// Get contact ID (pass context for dynamic contact resolution)
		$contact_id = $this->get_contact_id( $post_id, $mapping, $context );
		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: Contact ID: %s',
				$contact_id ? $contact_id : 'NONE'
			)
		);

		if ( ! $contact_id ) {
			$not_found_action = $mapping['contact_not_found'] ?? 'skip';

			if ( 'skip' === $not_found_action ) {
				error_log( sprintf( '[GHL Custom Objects] Skipping sync for post %d - no contact found and action is "skip"', $post_id ) );
				return false;
			}

			// Create new contact from context data
			if ( 'create' === $not_found_action ) {
				$email = $context['purchaser_email'] ?? $context['student_email'] ?? $context['user_email'] ?? '';
				$name  = $context['purchaser_name'] ?? $context['student_name'] ?? $context['user_name'] ?? '';
				
				if ( empty( $email ) ) {
					error_log( sprintf( '[GHL Custom Objects] Cannot create contact - no email in context for post %d', $post_id ) );
					return false;
				}
				
				error_log( sprintf( '[GHL Custom Objects] Creating new contact for email: %s, name: %s', $email, $name ) );
				
				// Parse name into first/last
				$name_parts = explode( ' ', trim( $name ), 2 );
				$first_name = $name_parts[0] ?? '';
				$last_name  = $name_parts[1] ?? '';
				
				try {
					$contact_id = $this->create_contact( $email, $first_name, $last_name );
					
					if ( $contact_id ) {
						error_log( sprintf( '[GHL Custom Objects] ✓ Created new contact: %s', $contact_id ) );
						
						// Store contact ID for the user if they exist
						$user = get_user_by( 'email', $email );
						if ( $user ) {
							update_user_meta( (int) $user->ID, '_ghl_contact_id', $contact_id );
						}
					} else {
						error_log( sprintf( '[GHL Custom Objects] ✗ Failed to create contact for %s', $email ) );
						return false;
					}
				} catch ( \Exception $e ) {
					error_log( sprintf( '[GHL Custom Objects] Exception creating contact: %s', $e->getMessage() ) );
					return false;
				}
			}

			// 'log' action - continue without contact

		}

		// Check if record already exists
		// Always use the same record ID for the post, regardless of contact source
		// This allows multiple contacts to be associated with the same product record
		$ghl_record_id = get_post_meta( $post_id, '_ghl_custom_object_record_id', true );
		$is_update = ! empty( $ghl_record_id );

		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: Existing record ID: %s',
				$ghl_record_id ? $ghl_record_id : 'NONE (will create new)'
			)
		);

		// Build GHL payload (different for create vs update)
		$payload = $this->build_payload( $post, $mapping, $contact_id, $is_update );

		// Get schema ID from mapping
		$schema_id  = $mapping['ghl_object'] ?? '';
		$schema_key = $mapping['ghl_object_key'] ?? '';

		if ( empty( $schema_id ) ) {

			return false;
		}

		// IMPORTANT: GHL API uses schema ID for BOTH create and update
		// The documentation/error messages are misleading - always use the hex ID
		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: Using schema ID: %s for %s operation',
				$schema_id,
				$is_update ? 'UPDATE' : 'CREATE'
			)
		);

		try {
			if ( $is_update ) {
				// Update existing record

				// First, verify the record still exists

				$existing_record = $this->custom_object_resource->get_record( $ghl_record_id, $schema_id );

				if ( ! $existing_record ) {

					// Record doesn't exist, delete local meta and recreate
					delete_post_meta( $post_id, '_ghl_custom_object_record_id' );
					$is_update = false;
					// Fall through to CREATE
				} else {

					// UPDATE requires schema KEY (dot-notation), not schema ID
					if ( empty( $schema_key ) ) {
						throw new \Exception( 'Schema KEY (ghl_object_key) is required for UPDATE operations' );
					}

					error_log(
						sprintf(
							'GHL CRM CustomObjectSync: Using schema KEY for UPDATE: %s',
							$schema_key
						)
					);

					$response = $this->custom_object_resource->update_record( $schema_key, $ghl_record_id, $payload );
					$action   = 'update';
					
					// Associate with contact on UPDATE as well (for multiple purchasers)
					// This allows adding new contacts to existing records
					if ( $contact_id && ! empty( $schema_key ) ) {
						$this->associate_contact_with_record( $ghl_record_id, $contact_id, $schema_key, $mapping );
						
						// Associate secondary contacts if configured
						$this->associate_secondary_contacts( $ghl_record_id, $post_id, $schema_key, $mapping, $context );
					}
				}
			}

			// CREATE operation (either new post or record was deleted from GHL)
			if ( ! $is_update ) {

				$response = $this->custom_object_resource->create_record( $schema_id, $payload );

				$ghl_record_id = $response['id'] ?? null;
				$action        = 'create';

				// Store record ID
				if ( $ghl_record_id ) {
					update_post_meta( $post_id, '_ghl_custom_object_record_id', $ghl_record_id );

				} else {

				}

				// Associate with contact
				if ( $ghl_record_id && $contact_id && ! empty( $schema_key ) ) {
					$this->associate_contact_with_record( $ghl_record_id, $contact_id, $schema_key, $mapping );
					
					// Associate secondary contacts if configured
					$this->associate_secondary_contacts( $ghl_record_id, $post_id, $schema_key, $mapping, $context );
				} elseif ( $ghl_record_id && ! $schema_key ) {

				}
			}

			// Update sync metadata
			update_post_meta( $post_id, '_ghl_last_sync_time', current_time( 'timestamp' ) );
			update_post_meta( $post_id, '_ghl_sync_status', 'synced' );

			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: SUCCESS - %s completed for post %d (GHL Record ID: %s)',
					$action,
					$post_id,
					$ghl_record_id ?? 'none'
				)
			);

			return true;

		} catch ( \Exception $e ) {

			// Update sync status
			update_post_meta( $post_id, '_ghl_sync_status', 'error' );
			update_post_meta( $post_id, '_ghl_sync_error', $e->getMessage() );

			throw $e;
		}
	}

	/**
	 * Delete a record from GHL
	 *
	 * @param int   $post_id Post ID
	 * @param array $mapping Mapping configuration
	 * @return bool Success status
	 */
	private function delete_record( int $post_id, array $mapping ): bool {
		$ghl_record_id = get_post_meta( $post_id, '_ghl_custom_object_record_id', true );

		if ( ! $ghl_record_id ) {
			// Nothing to delete
			return true;
		}

		try {
			// Get schema ID from mapping
			$schema_id = $mapping['schema_id'] ?? '';
			if ( empty( $schema_id ) ) {
				throw new \Exception( 'Schema ID not found in mapping configuration' );
			}

			$this->custom_object_resource->delete_record( $ghl_record_id, $schema_id );

			// Clean up metadata
			delete_post_meta( $post_id, '_ghl_custom_object_record_id' );
			delete_post_meta( $post_id, '_ghl_last_sync_time' );
			delete_post_meta( $post_id, '_ghl_sync_status' );
			delete_post_meta( $post_id, '_ghl_sync_error' );

			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: Successfully deleted record %s for post %d',
					$ghl_record_id,
					$post_id
				)
			);

			return true;

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: Failed to delete record %s for post %d - %s',
					$ghl_record_id,
					$post_id,
					$e->getMessage()
				)
			);

			throw $e;
		}
	}

	/**
	 * Get contact ID for a post
	 *
	 * @param int   $post_id Post ID
	 * @param array $mapping Mapping configuration
	 * @return string|null Contact ID or null
	 */
	/**
	 * Get GHL contact ID for a post based on mapping configuration
	 *
	 * @param int   $post_id Post ID
	 * @param array $mapping Mapping configuration
	 * @param array $context Optional context data (purchaser_email, etc.)
	 * @return string|null Contact ID or null if not found
	 */
	private function get_contact_id( int $post_id, array $mapping, array $context = array() ): ?string {
		$source = $mapping['contact_source'] ?? ($mapping['contact_strategy']['mode'] ?? 'post_author');
		$field  = $mapping['contact_field'] ?? ($mapping['contact_strategy']['primary_contact']['meta_key'] ?? '');

		// Normalize common aliases
		if ( 'post_meta' === $source ) {
			$source = 'meta_field';
		}

		switch ( $source ) {
			case 'post_author':
				$author_id = get_post_field( 'post_author', $post_id );
				if ( $author_id ) {
					return get_user_meta( (int) $author_id, '_ghl_contact_id', true ) ?: null;
				}
				return null;

			case 'product_purchasers':
			case 'course_students':
			case 'assignment_students':
				// Dynamic contact resolution from context
				$email = $context['purchaser_email'] ?? $context['student_email'] ?? $context['user_email'] ?? '';
				
				if ( empty( $email ) ) {
					error_log( sprintf( '[GHL Custom Objects] Contact source "%s" requires email in context for post %d', $source, $post_id ) );
					return null;
				}
				
				error_log( sprintf( '[GHL Custom Objects] Resolving contact for email: %s (source: %s)', $email, $source ) );
				
				// Try to find existing WP user
				$user = get_user_by( 'email', $email );
				if ( $user ) {
					$contact_id = get_user_meta( (int) $user->ID, '_ghl_contact_id', true );
					if ( $contact_id ) {
						error_log( sprintf( '[GHL Custom Objects] Found existing contact ID: %s for user %d', $contact_id, $user->ID ) );
						return $contact_id;
					}
				}
				
				// No existing contact found - let the "contact_not_found" action handle it
				error_log( sprintf( '[GHL Custom Objects] No existing contact found for email: %s', $email ) );
				return null;

			case 'meta_field':
				if ( empty( $field ) ) {
					error_log( sprintf( '[GHL Custom Objects] contact meta_field configured but contact_field empty for post %d', $post_id ) );
					return null;
				}
				// The meta field may contain an email or a GHL contact ID depending on configuration
				$meta_value = get_post_meta( $post_id, $field, true );
				if ( empty( $meta_value ) ) {
					return null;
				}
				// If value looks like a GHL ID (hex), return it; otherwise assume email lookup
				if ( is_string( $meta_value ) && preg_match( '/^[0-9a-fA-F\-]{6,}$/', $meta_value ) ) {
					return $meta_value;
				}

				// Try to find user by email and return their stored GHL contact id
				$user = get_user_by( 'email', $meta_value );
				if ( $user ) {
					return get_user_meta( (int) $user->ID, '_ghl_contact_id', true ) ?: null;
				}

				// Not found
				return null;

			case 'acf_field':
				if ( function_exists( 'get_field' ) ) {
					$val = get_field( $field, $post_id );
					if ( empty( $val ) ) {
						return null;
					}
					// If ACF returns an object or ID, try to resolve to GHL contact id
					if ( is_array( $val ) && ! empty( $val['ID'] ) ) {
						return get_user_meta( (int) $val['ID'], '_ghl_contact_id', true ) ?: null;
					}
					if ( is_numeric( $val ) ) {
						return get_user_meta( (int) $val, '_ghl_contact_id', true ) ?: null;
					}
					if ( is_string( $val ) ) {
						$user = get_user_by( 'email', $val );
						if ( $user ) {
							return get_user_meta( (int) $user->ID, '_ghl_contact_id', true ) ?: null;
						}
					}
				}
				return null;

			default:
				// Unhandled/complex sources (product_purchasers, course_students, etc.)
				error_log( sprintf( '[GHL Custom Objects] Unhandled contact source "%s" for post %d', $source, $post_id ) );
				return null;
		}
	}

	/**
	 * Build GHL API payload
	 *
	 * @param \WP_Post $post       Post object
	 * @param array    $mapping    Mapping configuration
	 * @param string   $contact_id Contact ID
	 * @param bool     $is_update  Whether this is an update operation (default: false)
	 * @return array Payload data
	 */
	private function build_payload( \WP_Post $post, array $mapping, ?string $contact_id, bool $is_update = false ): array {
		$location_id = $this->settings->get_setting( 'location_id', '' );

		$properties = array();

		// Note: Contact associations are handled separately via POST /associations/relations
		// They are not included in the record properties
		// The association will be created after the record is successfully created
		if ( ! empty( $contact_id ) ) {
			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: Contact ID %s will be associated after record creation',
					$contact_id
				)
			);
		}

		// Map fields
		foreach ( $mapping['field_mappings'] as $field_map ) {
			$wp_field  = $field_map['wp_field'];
			$ghl_field = $field_map['ghl_field'];
			$transform = $field_map['transform'] ?? 'none';

			// Extract WordPress value
			$value = $this->extract_field_value( $post->ID, $wp_field, $field_map );

			// Apply transformation
			$value = $this->apply_transform( $value, $transform );

			// Add to properties
			// Strip the "custom_objects.{object_name}." prefix from field key
			// GHL expects only the field key in properties, not the full path
			$field_key = $ghl_field;
			if ( strpos( $ghl_field, 'custom_objects.' ) === 0 ) {
				// Extract just the field name from "custom_objects.object_name.field_name"
				$parts = explode( '.', $ghl_field );
				if ( count( $parts ) >= 3 ) {
					$field_key = $parts[2]; // Get the field name part
				}
			}

			if ( null !== $value && '' !== $value ) {
				$properties[ $field_key ] = $value;
			}
		}

		// Note: Only include fields that actually exist in the custom object schema
		// Don't add 'name' or other fields unless they're mapped in field_mappings

		// Build payload based on operation type
		// CREATE: requires locationId
		// UPDATE: only needs properties (record is already associated with location)
		if ( $is_update ) {
			$payload = array(
				'properties' => $properties,
			);
		} else {
			$payload = array(
				'locationId' => $location_id,
				'properties' => $properties,
			);
		}

		return $payload;
	}

	/**
	 * Extract field value from WordPress post
	 *
	 * @param int    $post_id   Post ID
	 * @param string $field     Field type
	 * @param array  $field_map Field mapping config
	 * @return mixed Field value
	 */
	private function extract_field_value( int $post_id, string $field, array $field_map ) {
		$field_name = $field_map['wp_field_name'] ?? '';

		// Support prefixed field keys like "meta:my_key", "acf:field_name", "taxonomy:taxonomy_name"
		if ( false !== strpos( $field, ':' ) ) {
			list( $prefix, $real_key ) = explode( ':', $field, 2 );
			switch ( $prefix ) {
				case 'meta':
					return get_post_meta( $post_id, $real_key, true );
				case 'acf':
					if ( function_exists( 'get_field' ) ) {
						return get_field( $real_key, $post_id );
					}
					return null;
				case 'taxonomy':
					$terms = get_the_terms( $post_id, $real_key );
					if ( is_array( $terms ) && ! empty( $terms ) ) {
						return wp_list_pluck( $terms, 'name' );
					}
					return null;
				default:
					// fallthrough to switch below
					break;
			}
		}

		switch ( $field ) {
			case 'post_title':
				return get_the_title( $post_id );

			case 'post_content':
				return get_post_field( 'post_content', $post_id );

			case 'post_excerpt':
				return get_the_excerpt( $post_id );

			case 'post_date':
				return get_post_field( 'post_date', $post_id );

			case 'post_modified':
				return get_post_field( 'post_modified', $post_id );

			case 'post_meta':
				return get_post_meta( $post_id, $field_name, true );

			case 'acf_field':
				if ( function_exists( 'get_field' ) ) {
					return get_field( $field_name, $post_id );
				}
				return null;

			case 'taxonomy':
				$terms = get_the_terms( $post_id, $field_name );
				if ( is_array( $terms ) && ! empty( $terms ) ) {
					return wp_list_pluck( $terms, 'name' );
				}
				return null;

			case 'static':
				return $field_name;

			default:
				return null;
		}
	}

	/**
	 * Apply data transformation
	 *
	 * @param mixed  $value     Value to transform
	 * @param string $transform Transform type
	 * @return mixed Transformed value
	 */
	private function apply_transform( $value, string $transform ) {
		if ( null === $value || '' === $value ) {
			return $value;
		}

		switch ( $transform ) {
			case 'sanitize':
				return wp_kses_post( $value );

			case 'strip_html':
				return wp_strip_all_tags( $value );

			case 'number':
				return is_numeric( $value ) ? floatval( $value ) : 0;

			case 'date_iso':
				$timestamp = is_numeric( $value ) ? $value : strtotime( $value );
				return gmdate( 'c', $timestamp );

			case 'json_encode':
				return wp_json_encode( $value );

			default:
				return $value;
		}
	}

	/**
	 * Get active mapping for post type
	 *
	 * @param string $post_type Post type
	 * @return array|null Mapping configuration or null
	 */
	private function get_active_mapping_for_post_type( string $post_type ): ?array {
		$mappings = $this->settings->get_option( 'ghl_crm_custom_object_mappings', array() );
		
		error_log( sprintf( '[GHL Custom Objects] Checking %d total mappings for post type: %s', count( $mappings ), $post_type ) );

		foreach ( $mappings as $mapping ) {
			error_log( sprintf( 
				'[GHL Custom Objects] Mapping: %s, Post Type: %s, Active: %s', 
				$mapping['name'] ?? 'Unnamed',
				$mapping['wp_post_type'] ?? 'N/A',
				isset( $mapping['active'] ) ? ( $mapping['active'] ? 'true' : 'false' ) : 'not set'
			) );
			
			if ( isset( $mapping['wp_post_type'], $mapping['active'] ) &&
				$mapping['wp_post_type'] === $post_type &&
				true === $mapping['active'] ) {
				error_log( sprintf( '[GHL Custom Objects] ✓ Found active mapping: %s', $mapping['name'] ?? 'Unnamed' ) );
				return $mapping;
			}
		}

		error_log( sprintf( '[GHL Custom Objects] ✗ No active mapping found for post type: %s', $post_type ) );
		return null;
	}

	/**
	 * Manual sync for a specific post
	 *
	 * @param int $post_id Post ID
	 * @return bool Success status
	 */
	public function manual_sync_post( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$mapping = $this->get_active_mapping_for_post_type( $post->post_type );
		if ( ! $mapping ) {
			return false;
		}

		try {
			return $this->sync_post( $post_id, $mapping );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Create a new contact in GHL
	 *
	 * @param string $email      Contact email
	 * @param string $first_name First name
	 * @param string $last_name  Last name
	 * @return string|null Contact ID on success, null on failure
	 */
	private function create_contact( string $email, string $first_name = '', string $last_name = '' ): ?string {
		$location_id = $this->settings->get_setting( 'location_id', '' );
		if ( empty( $location_id ) ) {
			error_log( '[GHL Custom Objects] Cannot create contact - location_id not configured' );
			return null;
		}

		$contact_data = array(
			'email'      => $email,
			'locationId' => $location_id,
		);

		if ( ! empty( $first_name ) ) {
			$contact_data['firstName'] = $first_name;
		}
		if ( ! empty( $last_name ) ) {
			$contact_data['lastName'] = $last_name;
		}

		try {
			$response = $this->ghl_api->create_contact( $contact_data );
			
			if ( isset( $response['contact']['id'] ) ) {
				return $response['contact']['id'];
			}
			
			error_log( sprintf( '[GHL Custom Objects] Invalid response when creating contact: %s', wp_json_encode( $response ) ) );
			return null;
		} catch ( \Exception $e ) {
			error_log( sprintf( '[GHL Custom Objects] Error creating contact: %s', $e->getMessage() ) );
			return null;
		}
	}

	/**
	 * Associate a contact with a custom object record
	 * This method handles fetching the association ID and creating the relation
	 *
	 * @param string $record_id   GHL record ID
	 * @param string $contact_id  GHL contact ID
	 * @param string $schema_key  Custom object schema key (e.g., "custom_objects.my_object")
	 * @param array  $mapping     Mapping configuration
	 * @return bool Success status
	 */
	private function associate_contact_with_record( string $record_id, string $contact_id, string $schema_key, array $mapping ): bool {
		// Get association ID and direction from mapping configuration or fetch from GHL
		$association_id        = $mapping['association_id'] ?? '';
		$association_direction = null; // Track if custom object is 'first' or 'second'

		// If not configured, try to auto-fetch it from GHL
		if ( empty( $association_id ) ) {
			try {
				// Fetch all associations from GHL
				$all_associations = $this->custom_object_resource->get_associations();

				// Find the association that links our custom object to contacts
				foreach ( $all_associations as $assoc ) {
					$first_key  = $assoc['firstObjectKey'] ?? '';
					$second_key = $assoc['secondObjectKey'] ?? '';

					// Check if this association links our custom object to contacts (either direction)
					if ( $first_key === $schema_key && $second_key === 'contact' ) {
						$association_id        = $assoc['id'] ?? '';
						$association_direction = 'first'; // Custom object is first
						error_log(
							sprintf(
								'[GHL Custom Objects] Auto-discovered association ID: %s (key: %s, direction: %s → %s)',
								$association_id,
								$assoc['key'] ?? 'N/A',
								$first_key,
								$second_key
							)
						);
						break;
					} elseif ( $first_key === 'contact' && $second_key === $schema_key ) {
						$association_id        = $assoc['id'] ?? '';
						$association_direction = 'second'; // Custom object is second
						error_log(
							sprintf(
								'[GHL Custom Objects] Auto-discovered association ID: %s (key: %s, direction: %s → %s)',
								$association_id,
								$assoc['key'] ?? 'N/A',
								$first_key,
								$second_key
							)
						);
						break;
					}
				}

				if ( empty( $association_id ) ) {
					error_log(
						sprintf(
							'[GHL Custom Objects] No association found for %s ↔ contact. Create one in GHL first.',
							$schema_key
						)
					);
					return false;
				}
			} catch ( \Exception $fetch_error ) {
				error_log( sprintf( '[GHL Custom Objects] Error fetching associations: %s', $fetch_error->getMessage() ) );
				return false;
			}
		}

		if ( ! empty( $association_id ) ) {
			try {
				error_log(
					sprintf(
						'[GHL Custom Objects] Attempting to associate record %s with contact %s (associationId: %s, direction: %s)',
						$record_id,
						$contact_id,
						$association_id,
						$association_direction ?? 'unknown'
					)
				);

				$this->custom_object_resource->associate_with_contact(
					$record_id,
					$contact_id,
					$schema_key,
					$association_id,
					$association_direction
				);

				error_log(
					sprintf(
						'[GHL Custom Objects] ✓ Successfully associated contact %s with record %s',
						$contact_id,
						$record_id
					)
				);

				return true;
			} catch ( \Exception $assoc_error ) {
				error_log( sprintf( '[GHL Custom Objects] Error creating association: %s', $assoc_error->getMessage() ) );
				return false;
			}
		}

		return false;
	}

	/**
	 * Associate secondary contacts with the custom object record
	 *
	 * @param string $record_id  GHL record ID
	 * @param int    $post_id    WordPress post ID
	 * @param string $schema_key Custom object schema key
	 * @param array  $mapping    Mapping configuration
	 * @param array  $context    Context data (order info, etc.)
	 * @return void
	 */
	private function associate_secondary_contacts( string $record_id, int $post_id, string $schema_key, array $mapping, array $context = array() ): void {
		// Get secondary associations from the associations array (skip first one which is primary)
		$all_associations = $mapping['associations'] ?? array();
		
		// Skip if no associations or only one (primary)
		if ( empty( $all_associations ) || count( $all_associations ) <= 1 ) {
			return;
		}
		
		// Get secondary associations (all except the first one)
		$secondary_associations = array_slice( $all_associations, 1 );
		
		error_log( sprintf( '[GHL Custom Objects] Processing %d secondary association(s)', count( $secondary_associations ) ) );
		
		foreach ( $secondary_associations as $association ) {
			$source = $association['source'] ?? '';
			
			if ( empty( $source ) ) {
				continue;
			}
			
			$secondary_contact_id = $this->get_secondary_contact_id( $post_id, $source, $context );
			
			if ( $secondary_contact_id ) {
				error_log( sprintf( '[GHL Custom Objects] Associating secondary contact %s (source: %s)', $secondary_contact_id, $source ) );
				$this->associate_contact_with_record( $record_id, $secondary_contact_id, $schema_key, $mapping );
			} else {
				error_log( sprintf( '[GHL Custom Objects] No secondary contact found for source: %s', $source ) );
			}
		}
	}

	/**
	 * Get contact ID for a secondary contact source
	 *
	 * @param int    $post_id Post ID
	 * @param string $source  Contact source (post_author, meta_field, etc.)
	 * @param array  $context Context data
	 * @return string|null Contact ID or null
	 */
	private function get_secondary_contact_id( int $post_id, string $source, array $context = array() ): ?string {
		switch ( $source ) {
			case 'post_author':
				$author_id = get_post_field( 'post_author', $post_id );
				if ( $author_id ) {
					return get_user_meta( (int) $author_id, '_ghl_contact_id', true ) ?: null;
				}
				return null;
				
			case 'product_purchasers':
			case 'course_students':
				// Already handled as primary - skip to avoid duplicate
				return null;
				
			default:
				error_log( sprintf( '[GHL Custom Objects] Unhandled secondary contact source: %s', $source ) );
				return null;
		}
	}
}
