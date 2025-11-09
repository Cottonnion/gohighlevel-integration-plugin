<?php
/**
 * WooCommerce Sync
 *
 * Handles WooCommerce integration: order syncing, customer tagging, abandoned cart tracking.
 *
 * @package GHL_CRM
 * @subpackage Integrations\WooCommerce
 */

namespace GHL_CRM\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class WooCommerceSync
 *
 * Manages WooCommerce to GoHighLevel synchronization
 */
class WooCommerceSync {

	/**
	 * Settings Manager instance
	 *
	 * @var \GHL_CRM\Core\SettingsManager
	 */
	private $settings_manager;

	/**
	 * Contact Resource instance
	 *
	 * @var \GHL_CRM\API\Resources\ContactResource
	 */
	private $contact_resource;

	/**
	 * Queue Manager instance
	 *
	 * @var \GHL_CRM\Sync\QueueManager
	 */
	private $queue_manager;

	/**
	 * Abandoned Cart Tracker instance
	 *
	 * @var AbandonedCartTracker
	 */
	private $abandoned_cart_tracker;

	/**
	 * Opportunity Manager instance
	 *
	 * @var OpportunityManager
	 */
	private $opportunity_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager       = \GHL_CRM\Core\SettingsManager::get_instance();
		$this->contact_resource       = new \GHL_CRM\API\Resources\ContactResource();
		$this->queue_manager          = \GHL_CRM\Sync\QueueManager::get_instance();
		$this->abandoned_cart_tracker = new AbandonedCartTracker();
		$this->opportunity_manager    = new OpportunityManager();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		// Only hook if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Product purchase tags - always enabled (independent of wc_enabled setting)
		add_action( 'woocommerce_order_status_completed', [ $this, 'handle_product_tags' ], 10, 2 );
		add_action( 'woocommerce_payment_complete', [ $this, 'handle_product_tags' ], 10, 1 );

		// Register queue processor handler for product tags
		add_filter( 'ghl_crm_execute_sync', [ $this, 'handle_queue_execution' ], 10, 5 );

		$settings = $this->settings_manager->get_settings_array();
		if ( empty( $settings['wc_enabled'] ) ) {
			return;
		}

		// Auto-convert lead to customer on first purchase
		if ( ! empty( $settings['wc_convert_lead_enabled'] ) ) {
			$order_statuses = $settings['wc_convert_order_statuses'] ?? [];

			// If no specific statuses are set, hook into all order status changes
			if ( empty( $order_statuses ) || ! is_array( $order_statuses ) ) {
				// Default behavior: convert on any order (typically processing or completed)
				add_action( 'woocommerce_order_status_processing', [ $this, 'handle_customer_conversion' ], 10, 2 );
				add_action( 'woocommerce_order_status_completed', [ $this, 'handle_customer_conversion' ], 10, 2 );
			} else {
				// Hook into each selected status
				foreach ( $order_statuses as $status ) {
					// WooCommerce status format: 'wc-pending' but hook uses 'pending'
					// Strip 'wc-' prefix if present
					$status_slug = str_replace( 'wc-', '', $status );

					// Hook into this specific status transition
					add_action( 'woocommerce_order_status_' . $status_slug, [ $this, 'handle_customer_conversion' ], 10, 2 );
				}
			}
		}

		// Abandoned cart tracking
		if ( ! empty( $settings['wc_abandoned_cart_enabled'] ) ) {
			$this->abandoned_cart_tracker->init();
		}

		// Opportunities tracking
		if ( $this->opportunity_manager->is_enabled() ) {
			// Hook into order creation
			add_action( 'woocommerce_checkout_order_processed', [ $this, 'handle_new_order_opportunity' ], 10, 1 );

			// Hook into order status changes
			add_action( 'woocommerce_order_status_changed', [ $this, 'handle_order_status_change_opportunity' ], 10, 4 );
		}
	}

	/**
	 * Handle customer conversion (lead to customer)
	 *
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order    Order object.
	 * @return void
	 */
	public function handle_customer_conversion( int $order_id, $order = null ): void {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$email = $order->get_billing_email();
		if ( empty( $email ) ) {
			return;
		}

		// Check if this is the customer's first purchase
		$customer_orders = $this->get_customer_completed_orders( $email );

		// Count only completed/processing orders
		$order_count = count( $customer_orders );

		// Only convert on first purchase
		if ( $order_count !== 1 ) {
			return;
		}

		// Get settings
		$settings      = $this->settings_manager->get_settings_array();
		$customer_tags = $settings['wc_customer_tag'] ?? [];

		// Ensure tags is an array
		if ( ! is_array( $customer_tags ) ) {
			$customer_tags = ! empty( $customer_tags ) ? [ $customer_tags ] : [];
		}

		// Prepare customer data for queue
		$customer_data = [
			'email'       => $email,
			'firstName'   => $order->get_billing_first_name(),
			'lastName'    => $order->get_billing_last_name(),
			'phone'       => $order->get_billing_phone(),
			'tags'        => $customer_tags,
			'source'      => 'woocommerce_first_purchase',
			'order_id'    => $order_id,
			'order_total' => $order->get_total(),
		];

		// Add to queue for processing
		$this->queue_manager->add_to_queue(
			'wc_customer',
			$order_id,
			'convert_lead',
			$customer_data
		);

		// Log for debugging
		error_log(
			sprintf(
				'GHL WooCommerce: Queued customer conversion for order #%d, email: %s',
				$order_id,
				$email
			)
		);
	}

	/**
	 * Process customer conversion from queue
	 * This method is called by the queue processor
	 *
	 * @param array $payload Customer data payload from queue.
	 * @return array|bool Array with contact data on success, false on failure.
	 */
	public function process_customer_conversion( array $payload ) {
		try {
			$email = $payload['email'] ?? '';
			$tags  = $payload['tags'] ?? [];

			if ( empty( $email ) ) {

				return false;
			}

			// Prepare contact data
			$contact_data = [
				'email'     => $email,
				'firstName' => $payload['firstName'] ?? '',
				'lastName'  => $payload['lastName'] ?? '',
				'phone'     => $payload['phone'] ?? '',
				'type'      => 'customer', // Convert lead to customer
			];

			// Upsert contact (create or update)
			$result = $this->contact_resource->upsert( $contact_data );

			if ( empty( $result['contact']['id'] ) ) {

				return false;
			}

			$contact_id = $result['contact']['id'];

			// Add customer tags if any - MERGE with existing tags
			if ( ! empty( $tags ) && is_array( $tags ) ) {
				// Fetch existing tags from the contact
				$existing_tags = $result['contact']['tags'] ?? [];

				error_log(
					sprintf(
						'asiya log: convert_lead existing tags | contact %s | tags [%s]',
						$contact_id,
						implode( ', ', $existing_tags )
					)
				);

				// Merge: combine existing + new tags, remove duplicates
				$merged_tags = array_values( array_unique( array_merge( $existing_tags, $tags ) ) );

				error_log(
					sprintf(
						'asiya log: convert_lead merging tags | contact %s | existing [%s] + new [%s] = merged [%s]',
						$contact_id,
						implode( ', ', $existing_tags ),
						implode( ', ', $tags ),
						implode( ', ', $merged_tags )
					)
				);

				// Update with merged tags
				$tag_result = $this->contact_resource->update( $contact_id, [ 'tags' => $merged_tags ] );

				error_log(
					sprintf(
						'asiya log: convert_lead update tags response | contact %s | merged tags [%s] | response %s',
						$contact_id,
						implode( ', ', $merged_tags ),
						json_encode( $tag_result )
					)
				);
			}

			// Log success
			error_log(
				sprintf(
					'GHL WooCommerce: Successfully converted lead to customer - Email: %s, Contact ID: %s, Tags: %s',
					$email,
					$contact_id,
					implode( ', ', $tags )
				)
			);

			// Return contact data for auto-sync
			return [
				'success' => true,
				'contact' => [
					'id' => $contact_id,
				],
			];

		} catch ( \Exception $e ) {

			return false;
		}
	}

	/**
	 * Get customer's completed orders by email
	 *
	 * @param string $email Customer email
	 * @return array Array of completed order IDs
	 */
	private function get_customer_completed_orders( string $email ): array {
		if ( empty( $email ) ) {
			return [];
		}

		$orders = wc_get_orders(
			[
				'billing_email' => $email,
				'status'        => [ 'wc-completed', 'wc-processing' ],
				'limit'         => -1,
				'return'        => 'ids',
			]
		);

		return is_array( $orders ) ? $orders : [];
	}

	/**
	 * Handle product purchase tags
	 *
	 * @param int       $order_id Order ID.
	 * @param \WC_Order $order    Order object (optional).
	 * @return void
	 */
	public function handle_product_tags( int $order_id, $order = null ): void {
		try {

			if ( ! $order ) {
				$order = wc_get_order( $order_id );
			}

			if ( ! $order ) {

				return;
			}

			$email = $order->get_billing_email();
			if ( empty( $email ) ) {

				return;
			}

			// Collect all tags from purchased products
			$product_tags    = [];
			$product_details = [];

			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$tags       = ProductMetaBox::get_product_tags( $product_id );

				if ( ! empty( $tags ) && is_array( $tags ) ) {
					$product_tags      = array_merge( $product_tags, $tags );
					$product_details[] = sprintf( '#%d (%s)', $product_id, implode( ', ', $tags ) );
				}
			}

			// Remove duplicates
			$product_tags = array_unique( $product_tags );

			if ( empty( $product_tags ) ) {

				return;
			}

			$tag_data = [
				'email'     => $email,
				'firstName' => $order->get_billing_first_name(),
				'lastName'  => $order->get_billing_last_name(),
				'phone'     => $order->get_billing_phone(),
				'tags'      => $product_tags,
				'source'    => 'woocommerce_product_purchase',
				'order_id'  => $order_id,
			];

			// Check if there's a pending profile_update for this user (to create dependency)
			$user_id             = $order->get_user_id();
			$depends_on_queue_id = null;

			if ( $user_id ) {
				// Get the most recent pending profile_update queue item for this user
				global $wpdb;
				$table_name          = $wpdb->prefix . 'ghl_sync_queue';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking plugin queue table for dependency chaining.
				$depends_on_queue_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table_name} 
						WHERE item_type = 'user' 
						AND item_id = %d 
						AND action = 'profile_update' 
						AND status = 'pending' 
						ORDER BY created_at DESC 
						LIMIT 1",
						$user_id
					)
				);

				if ( $depends_on_queue_id ) {
					error_log(
						sprintf(
							'asiya log: handle_product_tags dependency | order #%d | depends on queue_id %d (user #%d profile_update)',
							$order_id,
							$depends_on_queue_id,
							$user_id
						)
					);
				}
			}

			$this->queue_manager->add_to_queue(
				'wc_product_tags',
				$order_id,
				'apply_tags',
				$tag_data,
				$depends_on_queue_id // Add dependency parameter
			);

		} catch ( \Throwable $error ) {

		} catch ( \Error $error ) {

		}
	}

	/**
	 * Process product tags sync from queue
	 *
	 * @param array $payload Product tags data payload.
	 * @return array|bool Array with contact data on success, false on failure.
	 */
	public function process_product_tags( array $payload ) {
		try {

			$email = $payload['email'] ?? '';
			$tags  = $payload['tags'] ?? [];

			if ( empty( $email ) || empty( $tags ) ) {

				return false;
			}

			$contact_data = [
				'email'     => $email,
				'firstName' => $payload['firstName'] ?? '',
				'lastName'  => $payload['lastName'] ?? '',
				'phone'     => $payload['phone'] ?? '',
			];

			$result = $this->contact_resource->upsert( $contact_data );

			$contact_id = $result['contact']['id'] ?? $result['id'] ?? '';

			if ( empty( $contact_id ) ) {

				return false;
			}

			// Fetch existing contact tags to merge with new ones
			$existing_tags = [];
			try {
				$contact_details = $this->contact_resource->get( $contact_id );
				if ( ! empty( $contact_details['contact']['tags'] ) && is_array( $contact_details['contact']['tags'] ) ) {
					$existing_tags = $contact_details['contact']['tags'];
					error_log(
						sprintf(
							'asiya log: process_product_tags existing tags | contact %s | tags [%s]',
							$contact_id,
							implode( ', ', $existing_tags )
						)
					);
				}
			} catch ( \Throwable $fetch_error ) {
				error_log(
					sprintf(
						'asiya log: process_product_tags could not fetch existing tags | contact %s | error %s',
						$contact_id,
						$fetch_error->getMessage()
					)
				);
				// Continue anyway, we'll just add the new tags
			}

			// Merge tags: combine existing + new, remove duplicates
			$merged_tags = array_unique( array_merge( $existing_tags, $tags ) );

			error_log(
				sprintf(
					'asiya log: process_product_tags merging tags | contact %s | existing [%s] + new [%s] = merged [%s]',
					$contact_id,
					implode( ', ', $existing_tags ),
					implode( ', ', $tags ),
					implode( ', ', $merged_tags )
				)
			);

			try {
				// Update contact with merged tags (not just add, to ensure all tags are present)
				$update_result = $this->contact_resource->update( $contact_id, [ 'tags' => $merged_tags ] );
				error_log(
					sprintf(
						'asiya log: process_product_tags update tags response | contact %s | merged tags [%s] | response %s',
						$contact_id,
						implode( ', ', $merged_tags ),
						wp_json_encode( $update_result )
					)
				);

				// Return contact data for QueueManager to extract contact_id
				return [
					'success' => true,
					'contact' => [
						'id'   => $contact_id,
						'tags' => $merged_tags,
					],
				];
			} catch ( \Throwable $tag_error ) {
				error_log(
					sprintf(
						'asiya log: process_product_tags add_tags error | contact %s | message %s',
						$contact_id,
						$tag_error->getMessage()
					)
				);
				return false;
			}
		} catch ( \Throwable $error ) {

			return false;
		}
	}

	/**
	 * Process opportunity sync from queue
	 * This method is called by the queue processor
	 *
	 * @param array $payload Opportunity data payload from queue.
	 * @return bool Success status.
	 */
	public function process_opportunity_sync( array $payload ): bool {

		try {

			$opportunity_resource = new \GHL_CRM\API\Resources\OpportunityResource();
			$contact_resource     = new \GHL_CRM\API\Resources\ContactResource();

			$email = $payload['email'] ?? '';
			if ( empty( $email ) ) {

				return false;
			}

			// Get or create contact
			$contact = $this->get_or_create_contact_for_opportunity( $contact_resource, $payload );
			if ( ! $contact ) {

				return false;
			}

			// Get location ID from settings
			$location_id = $this->settings_manager->get_setting( 'location_id' );
			if ( empty( $location_id ) ) {

				return false;
			}

			// Prepare opportunity data (locationId is REQUIRED by GHL API)
			$opportunity_data = [
				'locationId'      => $location_id,
				'pipelineId'      => $payload['pipeline_id'],
				'pipelineStageId' => $payload['stage_id'],
				'name'            => $payload['name'],
				'contactId'       => $contact['id'],
				'monetaryValue'   => floatval( $payload['monetary_value'] ?? 0 ),
				'status'          => $payload['status'] ?? 'open',
				'source'          => $payload['source'] ?? 'woocommerce',
			];

			// Check if this is an update or create
			$opportunity_id = $payload['opportunity_id'] ?? '';

			// If no opportunity ID in payload, check order meta for existing opportunity FOR THIS SPECIFIC ORDER
			if ( empty( $opportunity_id ) && ! empty( $payload['order_id'] ) ) {
				$order = wc_get_order( $payload['order_id'] );
				if ( $order ) {
					$stored_opp_id = $order->get_meta( '_ghl_opportunity_id', true );
					if ( ! empty( $stored_opp_id ) ) {
						$opportunity_id = $stored_opp_id;

					}
				}
			}

			// If this is a NEW order (no opportunity ID), check if contact has ANY open opportunities in this pipeline
			// If they do, close it as "won" before creating new one (GHL limitation: 1 opportunity per contact per pipeline)
			if ( empty( $opportunity_id ) && ! empty( $payload['opportunity_type'] ) && $payload['opportunity_type'] === 'new_order' ) {

				// Get contact's previous orders and close any open opportunities
				if ( ! empty( $payload['order_id'] ) ) {
					$customer_email  = $payload['email'];
					$previous_orders = wc_get_orders( // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- exclude used with small limit to skip current order
						[
							'billing_email' => $customer_email,
							'limit'         => 10,
							'exclude'       => [ $payload['order_id'] ], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
							'return'        => 'ids',
						]
					);

					// Close any existing opportunities from previous orders
					foreach ( $previous_orders as $prev_order_id ) {
						$prev_order = wc_get_order( $prev_order_id );
						if ( $prev_order ) {
							$prev_opp_id = $prev_order->get_meta( '_ghl_opportunity_id', true );
							if ( ! empty( $prev_opp_id ) ) {
								try {
									// Close previous opportunity as "won"
									$opportunity_resource->update_status( $prev_opp_id, $opportunity_data['pipelineStageId'], 'won' );

									break; // Only close one (should only be one open anyway)
								} catch ( \Exception $close_error ) {

									// Continue anyway
								}
							}
						}
					}
				}
			}

			if ( ! empty( $opportunity_id ) ) {

				// Update existing opportunity (update the monetary value, stage, etc.)
				$update_data = [
					'pipelineStageId' => $opportunity_data['pipelineStageId'],
					'monetaryValue'   => $opportunity_data['monetaryValue'],
					'status'          => $opportunity_data['status'],
				];
				$result      = $opportunity_resource->update( $opportunity_id, $update_data );
				error_log(
					sprintf(
						'GHL Opportunities: Updated opportunity %s (%s) with new order value',
						$opportunity_id,
						$payload['name']
					)
				);
			} else {

				// Create new opportunity
				try {
					$result = $opportunity_resource->create( $opportunity_data );

				} catch ( \Exception $api_error ) {

					throw $api_error; // Re-throw to be caught by outer catch
				}

				// Extract opportunity ID from response
				$created_opportunity_id = $result['opportunity']['id'] ?? $result['id'] ?? '';

				if ( ! empty( $created_opportunity_id ) ) {
					error_log(
						sprintf(
							'GHL Opportunities: Created opportunity %s (%s)',
							$created_opportunity_id,
							$payload['name']
						)
					);
				}
			}

			// Store opportunity ID in order meta for all cases (create or update)
			$final_opportunity_id = $opportunity_id ?? ( $result['opportunity']['id'] ?? $result['id'] ?? '' );
			if ( ! empty( $final_opportunity_id ) && ! empty( $payload['order_id'] ) ) {
				$order = wc_get_order( $payload['order_id'] );
				if ( $order ) {
					$order->update_meta_data( '_ghl_opportunity_id', $final_opportunity_id );
					$order->save();

				}
			}

			// Return result array for proper logging by QueueManager
			// Format: array with 'id' or 'opportunity' containing the GHL ID
			return ! empty( $result ) ? $result : false;

		} catch ( \Exception $e ) {

			return false;
		}
	}

	/**
	 * Get or create contact for opportunity
	 *
	 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource.
	 * @param array                                  $payload Payload data.
	 * @return array|false Contact data or false on failure.
	 */
	private function get_or_create_contact_for_opportunity( $contact_resource, array $payload ) {
		try {
			$email = $payload['email'];

			// Try to find existing contact using find_by_email method
			$contact = $contact_resource->find_by_email( $email );

			if ( ! empty( $contact ) ) {

				return $contact;
			}

			// Create new contact
			$contact_data = [
				'email'     => $email,
				'firstName' => $payload['first_name'] ?? $payload['cart_data']['first_name'] ?? '',
				'lastName'  => $payload['last_name'] ?? $payload['cart_data']['last_name'] ?? '',
				'phone'     => $payload['phone'] ?? $payload['cart_data']['phone'] ?? '',
			];

			$result = $contact_resource->create( $contact_data );

			return $result['contact'] ?? $result;
		} catch ( \Exception $e ) {

			return false;
		} catch ( \Error $err ) {

			return false;
		}
	}

	/**
	 * Handle opportunity creation for new order
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function handle_new_order_opportunity( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if there's an existing opportunity from abandoned cart
		$cart_key                = $this->get_cart_key_for_order( $order );
		$existing_opportunity_id = '';

		if ( $cart_key ) {
			$cart_data = get_transient( 'ghl_cart_' . $cart_key );
			if ( $cart_data && ! empty( $cart_data['ghl_opportunity_id'] ) ) {
				$existing_opportunity_id = $cart_data['ghl_opportunity_id'];
			}
		}

		// Create or update opportunity
		$opportunity = $this->opportunity_manager->handle_order_opportunity( $order, $existing_opportunity_id );

		if ( $opportunity && ! empty( $opportunity['id'] ) ) {
			// Store opportunity ID in order meta
			$order->update_meta_data( '_ghl_opportunity_id', $opportunity['id'] );
			$order->save();

			error_log(
				sprintf(
					'GHL Opportunities: %s opportunity %s for order #%d',
					$existing_opportunity_id ? 'Updated' : 'Created',
					$opportunity['id'],
					$order_id
				)
			);
		}
	}

	/**
	 * Handle opportunity update on order status change
	 *
	 * @param int       $order_id   Order ID.
	 * @param string    $old_status Old order status.
	 * @param string    $new_status New order status.
	 * @param \WC_Order $order   Order object.
	 * @return void
	 */
	public function handle_order_status_change_opportunity( int $order_id, string $old_status, string $new_status, $order ): void {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		// Get existing opportunity ID from order meta
		$opportunity_id = $order->get_meta( '_ghl_opportunity_id' );

		// Create or update opportunity
		$this->opportunity_manager->handle_order_opportunity( $order, $opportunity_id );

		error_log(
			sprintf(
				'GHL Opportunities: Updated opportunity for order #%d (status: %s → %s)',
				$order_id,
				$old_status,
				$new_status
			)
		);
	}

	/**
	 * Get cart key for order (from session or user)
	 *
	 * @param \WC_Order $order Order object.
	 * @return string|false Cart key or false.
	 */
	private function get_cart_key_for_order( \WC_Order $order ) {
		$user_id = $order->get_user_id();

		if ( $user_id ) {
			return 'user_' . $user_id;
		}

		// Try to get from session (less reliable)
		$email = $order->get_billing_email();
		if ( $email ) {
			return 'guest_' . md5( $email );
		}

		return false;
	}

	/**
	 * Handle queue execution for WooCommerce-specific item types
	 *
	 * @param mixed  $result    Current result (false by default).
	 * @param string $item_type Item type.
	 * @param string $action    Action.
	 * @param int    $item_id   Item ID.
	 * @param array  $payload   Payload data.
	 * @return mixed Result from handler or false.
	 */
	public function handle_queue_execution( $result, string $item_type, string $action, int $item_id, array $payload ) {
		// Only handle WooCommerce product tags
		if ( 'wc_product_tags' !== $item_type ) {
			return $result;
		}

		// Route to appropriate handler based on action
		switch ( $action ) {
			case 'apply_tags':
				return $this->process_product_tags( $payload );

			default:
				return $result;
		}
	}
}
