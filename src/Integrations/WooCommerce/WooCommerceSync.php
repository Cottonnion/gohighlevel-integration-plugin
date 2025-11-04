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
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$this->contact_resource = new \GHL_CRM\API\Resources\ContactResource();
		$this->queue_manager    = \GHL_CRM\Sync\QueueManager::get_instance();
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
			add_action( 'woocommerce_order_status_completed', [ $this, 'handle_customer_conversion' ], 10, 2 );
			add_action( 'woocommerce_order_status_processing', [ $this, 'handle_customer_conversion' ], 10, 2 );
		}

		// Abandoned cart tracking (future implementation)
		if ( ! empty( $settings['wc_abandoned_cart_enabled'] ) ) {
			// Note: Abandoned cart detection requires additional setup
			// This is a placeholder for future development
			// Typically requires tracking cart updates and scheduled checks
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
	 * Get all completed orders for a customer by email
	 *
	 * @param string $email Customer email.
	 * @return array Array of WC_Order objects.
	 */
	private function get_customer_completed_orders( string $email ): array {
		if ( empty( $email ) ) {
			return [];
		}

		$orders = wc_get_orders(
			[
				'billing_email' => $email,
				'limit'         => -1,
				'status'        => [ 'completed', 'processing' ],
				'return'        => 'objects',
			]
		);

		return is_array( $orders ) ? $orders : [];
	}
}
