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
		$this->settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$this->contact_resource = new \GHL_CRM\API\Resources\ContactResource();
		$this->queue_manager    = \GHL_CRM\Sync\QueueManager::get_instance();
		$this->abandoned_cart_tracker = new AbandonedCartTracker();
		$this->opportunity_manager = new OpportunityManager();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		// Only hook if WooCommerce is active and integration is enabled
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

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
		$settings     = $this->settings_manager->get_settings_array();
		$customer_tags = $settings['wc_customer_tag'] ?? [];
		
		// Ensure tags is an array
		if ( ! is_array( $customer_tags ) ) {
			$customer_tags = ! empty( $customer_tags ) ? [ $customer_tags ] : [];
		}

		// Prepare customer data for queue
		$customer_data = [
			'email'      => $email,
			'firstName'  => $order->get_billing_first_name(),
			'lastName'   => $order->get_billing_last_name(),
			'phone'      => $order->get_billing_phone(),
			'tags'       => $customer_tags,
			'source'     => 'woocommerce_first_purchase',
			'order_id'   => $order_id,
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
		error_log( sprintf(
			'GHL WooCommerce: Queued customer conversion for order #%d, email: %s',
			$order_id,
			$email
		) );
	}

	/**
	 * Process customer conversion from queue
	 * This method is called by the queue processor
	 *
	 * @param array $payload Customer data payload from queue.
	 * @return bool Success status.
	 */
	public function process_customer_conversion( array $payload ): bool {
		try {
			$email = $payload['email'] ?? '';
			$tags  = $payload['tags'] ?? [];

			if ( empty( $email ) ) {
				error_log( 'GHL WooCommerce: Missing email in customer conversion payload' );
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
				error_log( 'GHL WooCommerce: Failed to upsert contact for ' . $email );
				return false;
			}

			$contact_id = $result['contact']['id'];

			// Add customer tags if any
			if ( ! empty( $tags ) && is_array( $tags ) ) {
				$this->contact_resource->add_tags( $contact_id, $tags );
			}

			// Log success
			error_log( sprintf(
				'GHL WooCommerce: Successfully converted lead to customer - Email: %s, Contact ID: %s, Tags: %s',
				$email,
				$contact_id,
				implode( ', ', $tags )
			) );

			return true;

		} catch ( \Exception $e ) {
			error_log( 'GHL WooCommerce: Error processing customer conversion - ' . $e->getMessage() );
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

		$orders = wc_get_orders( [
			'billing_email' => $email,
			'status'        => [ 'wc-completed', 'wc-processing' ],
			'limit'         => -1,
			'return'        => 'ids',
		] );

		return is_array( $orders ) ? $orders : [];
	}

	/**
	 * Process opportunity sync from queue
	 * This method is called by the queue processor
	 *
	 * @param array $payload Opportunity data payload from queue.
	 * @return bool Success status.
	 */
	public function process_opportunity_sync( array $payload ): bool {
		error_log( 'GHL Opportunities: process_opportunity_sync() called with payload: ' . print_r( $payload, true ) );
		
		try {
			error_log( 'GHL Opportunities: Step 1 - Initializing resources' );
			$opportunity_resource = new \GHL_CRM\API\Resources\OpportunityResource();
			$contact_resource = new \GHL_CRM\API\Resources\ContactResource();

			$email = $payload['email'] ?? '';
			if ( empty( $email ) ) {
				error_log( 'GHL Opportunities: Missing email in opportunity payload' );
				return false;
			}

			error_log( 'GHL Opportunities: Step 2 - Getting/creating contact for ' . $email );
			// Get or create contact
			$contact = $this->get_or_create_contact_for_opportunity( $contact_resource, $payload );
			if ( ! $contact ) {
				error_log( 'GHL Opportunities: Failed to get/create contact for ' . $email );
				return false;
			}
			error_log( 'GHL Opportunities: Step 3 - Contact found/created: ' . $contact['id'] );

			error_log( 'GHL Opportunities: Step 4 - Preparing opportunity data' );
			// Get location ID from settings
			$location_id = $this->settings_manager->get_setting( 'location_id' );
			if ( empty( $location_id ) ) {
				error_log( 'GHL Opportunities: Missing location_id in settings' );
				return false;
			}

			// Prepare opportunity data (locationId is REQUIRED by GHL API)
			$opportunity_data = [
				'locationId'       => $location_id,
				'pipelineId'       => $payload['pipeline_id'],
				'pipelineStageId'  => $payload['stage_id'],
				'name'             => $payload['name'],
				'contactId'        => $contact['id'],
				'monetaryValue'    => floatval( $payload['monetary_value'] ?? 0 ),
				'status'           => $payload['status'] ?? 'open',
				'source'           => $payload['source'] ?? 'woocommerce',
			];
			error_log( 'GHL Opportunities: Opportunity data prepared: ' . print_r( $opportunity_data, true ) );

			// Check if this is an update or create
			$opportunity_id = $payload['opportunity_id'] ?? '';

			// If no opportunity ID in payload, check order meta for existing opportunity FOR THIS SPECIFIC ORDER
			if ( empty( $opportunity_id ) && ! empty( $payload['order_id'] ) ) {
				$order = wc_get_order( $payload['order_id'] );
				if ( $order ) {
					$stored_opp_id = $order->get_meta( '_ghl_opportunity_id', true );
					if ( ! empty( $stored_opp_id ) ) {
						$opportunity_id = $stored_opp_id;
						error_log( 'GHL Opportunities: Found existing opportunity ID in order meta: ' . $opportunity_id );
					}
				}
			}

			// If this is a NEW order (no opportunity ID), check if contact has ANY open opportunities in this pipeline
			// If they do, close it as "won" before creating new one (GHL limitation: 1 opportunity per contact per pipeline)
			if ( empty( $opportunity_id ) && ! empty( $payload['opportunity_type'] ) && $payload['opportunity_type'] === 'new_order' ) {
				error_log( 'GHL Opportunities: New order - checking for any existing open opportunities for this contact' );
				// Get contact's previous orders and close any open opportunities
				if ( ! empty( $payload['order_id'] ) ) {
					$customer_email = $payload['email'];
					$previous_orders = wc_get_orders( [
						'billing_email' => $customer_email,
						'limit'         => 10,
						'exclude'       => [ $payload['order_id'] ], // Exclude current order
						'return'        => 'ids',
					] );
					
					// Close any existing opportunities from previous orders
					foreach ( $previous_orders as $prev_order_id ) {
						$prev_order = wc_get_order( $prev_order_id );
						if ( $prev_order ) {
							$prev_opp_id = $prev_order->get_meta( '_ghl_opportunity_id', true );
							if ( ! empty( $prev_opp_id ) ) {
								try {
									// Close previous opportunity as "won"
									$opportunity_resource->update_status( $prev_opp_id, $opportunity_data['pipelineStageId'], 'won' );
									error_log( 'GHL Opportunities: Closed previous opportunity ' . $prev_opp_id . ' as won' );
									break; // Only close one (should only be one open anyway)
								} catch ( \Exception $close_error ) {
									error_log( 'GHL Opportunities: Error closing previous opportunity: ' . $close_error->getMessage() );
									// Continue anyway
								}
							}
						}
					}
				}
			}

			if ( ! empty( $opportunity_id ) ) {
				error_log( 'GHL Opportunities: Step 5 - Updating existing opportunity ' . $opportunity_id );
				// Update existing opportunity (update the monetary value, stage, etc.)
				$update_data = [
					'pipelineStageId' => $opportunity_data['pipelineStageId'],
					'monetaryValue'   => $opportunity_data['monetaryValue'],
					'status'          => $opportunity_data['status'],
				];
				$result = $opportunity_resource->update( $opportunity_id, $update_data );
				error_log( sprintf(
					'GHL Opportunities: Updated opportunity %s (%s) with new order value',
					$opportunity_id,
					$payload['name']
				) );
			} else {
				error_log( 'GHL Opportunities: Step 5 - Creating new opportunity' );
				// Create new opportunity
				try {
					$result = $opportunity_resource->create( $opportunity_data );
					error_log( 'GHL Opportunities: API create() returned successfully' );
					error_log( 'GHL Opportunities: API Response: ' . print_r( $result, true ) );
				} catch ( \Exception $api_error ) {
					error_log( 'GHL Opportunities: API create() threw exception: ' . $api_error->getMessage() );
					error_log( 'GHL Opportunities: Exception trace: ' . $api_error->getTraceAsString() );
					throw $api_error; // Re-throw to be caught by outer catch
				}
				
				// Extract opportunity ID from response
				$created_opportunity_id = $result['opportunity']['id'] ?? $result['id'] ?? '';
				
				if ( ! empty( $created_opportunity_id ) ) {
					error_log( sprintf(
						'GHL Opportunities: Created opportunity %s (%s)',
						$created_opportunity_id,
						$payload['name']
					) );
				}
			}

			// Store opportunity ID in order meta for all cases (create or update)
			$final_opportunity_id = $opportunity_id ?? ( $result['opportunity']['id'] ?? $result['id'] ?? '' );
			if ( ! empty( $final_opportunity_id ) && ! empty( $payload['order_id'] ) ) {
				$order = wc_get_order( $payload['order_id'] );
				if ( $order ) {
					$order->update_meta_data( '_ghl_opportunity_id', $final_opportunity_id );
					$order->save();
					error_log( 'GHL Opportunities: Stored opportunity ID in order meta: ' . $final_opportunity_id );
				}
			}

			// Return result array for proper logging by QueueManager
			// Format: array with 'id' or 'opportunity' containing the GHL ID
			return ! empty( $result ) ? $result : false;

		} catch ( \Exception $e ) {
			error_log( 'GHL Opportunities: Error processing opportunity sync - ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get or create contact for opportunity
	 *
	 * @param \GHL_CRM\API\Resources\ContactResource $contact_resource Contact resource.
	 * @param array $payload Payload data.
	 * @return array|false Contact data or false on failure.
	 */
	private function get_or_create_contact_for_opportunity( $contact_resource, array $payload ) {
		try {
			$email = $payload['email'];
			error_log( 'GHL Opportunities: get_or_create_contact - Searching for email: ' . $email );

			// Try to find existing contact using find_by_email method
			$contact = $contact_resource->find_by_email( $email );
			error_log( 'GHL Opportunities: Search result: ' . print_r( $contact, true ) );
			
			if ( ! empty( $contact ) ) {
				error_log( 'GHL Opportunities: Found existing contact: ' . $contact['id'] );
				return $contact;
			}

			error_log( 'GHL Opportunities: No existing contact found, creating new one' );
			// Create new contact
			$contact_data = [
				'email'     => $email,
				'firstName' => $payload['first_name'] ?? $payload['cart_data']['first_name'] ?? '',
				'lastName'  => $payload['last_name'] ?? $payload['cart_data']['last_name'] ?? '',
				'phone'     => $payload['phone'] ?? $payload['cart_data']['phone'] ?? '',
			];
			error_log( 'GHL Opportunities: Contact data to create: ' . print_r( $contact_data, true ) );

			$result = $contact_resource->create( $contact_data );
			error_log( 'GHL Opportunities: Create contact result: ' . print_r( $result, true ) );
			return $result['contact'] ?? $result;
		} catch ( \Exception $e ) {
			error_log( 'GHL Opportunities: Error getting/creating contact - ' . $e->getMessage() );
			error_log( 'GHL Opportunities: Stack trace - ' . $e->getTraceAsString() );
			return false;
		} catch ( \Error $err ) {
			error_log( 'GHL Opportunities: Fatal error getting/creating contact - ' . $err->getMessage() );
			error_log( 'GHL Opportunities: Stack trace - ' . $err->getTraceAsString() );
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
		$cart_key = $this->get_cart_key_for_order( $order );
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

			error_log( sprintf(
				'GHL Opportunities: %s opportunity %s for order #%d',
				$existing_opportunity_id ? 'Updated' : 'Created',
				$opportunity['id'],
				$order_id
			) );
		}
	}

	/**
	 * Handle opportunity update on order status change
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old order status.
	 * @param string $new_status New order status.
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

		error_log( sprintf(
			'GHL Opportunities: Updated opportunity for order #%d (status: %s → %s)',
			$order_id,
			$old_status,
			$new_status
		) );
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
}
