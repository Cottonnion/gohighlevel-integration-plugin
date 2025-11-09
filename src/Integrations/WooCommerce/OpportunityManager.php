<?php
/**
 * Opportunity Manager
 *
 * Handles WooCommerce opportunity creation and updates in GoHighLevel pipelines.
 *
 * @package GHL_CRM
 * @subpackage Integrations\WooCommerce
 */

namespace GHL_CRM\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class OpportunityManager
 *
 * Manages opportunity lifecycle for WooCommerce carts and orders
 */
class OpportunityManager {

	/**
	 * Settings Manager instance
	 *
	 * @var \GHL_CRM\Core\SettingsManager
	 */
	private $settings_manager;

	/**
	 * Opportunity Resource instance
	 *
	 * @var \GHL_CRM\API\Resources\OpportunityResource
	 */
	private $opportunity_resource;

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
		$this->settings_manager     = \GHL_CRM\Core\SettingsManager::get_instance();
		$this->opportunity_resource = new \GHL_CRM\API\Resources\OpportunityResource();
		$this->contact_resource     = new \GHL_CRM\API\Resources\ContactResource();
		$this->queue_manager        = \GHL_CRM\Sync\QueueManager::get_instance();
	}

	/**
	 * Check if opportunities feature is enabled and configured
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$settings = $this->settings_manager->get_settings_array();

		return ! empty( $settings['wc_opportunities_enabled'] )
			&& ! empty( $settings['wc_opportunities_pipeline'] );
	}

	/**
	 * Check if cart/order matches filter criteria
	 *
	 * @param \WC_Cart|\WC_Order $cart_or_order Cart or Order object.
	 * @return bool
	 */
	public function matches_filter( $cart_or_order ): bool {
		$settings    = $this->settings_manager->get_settings_array();
		$filter_type = $settings['wc_opportunities_filter_type'] ?? 'all';

		// If filter type is 'all', always create opportunity
		if ( $filter_type === 'all' ) {
			return true;
		}

		// Get items from cart or order
		$items = $cart_or_order instanceof \WC_Order
			? $cart_or_order->get_items()
			: $cart_or_order->get_cart();

		// Minimum value filter
		if ( $filter_type === 'min_value' ) {
			$min_value = floatval( $settings['wc_opportunities_min_value'] ?? 0 );
			$total     = $cart_or_order instanceof \WC_Order
				? floatval( $cart_or_order->get_total() )
				: floatval( $cart_or_order->get_cart_contents_total() );

			return $total >= $min_value;
		}

		// Specific products filter
		if ( $filter_type === 'products' ) {
			$allowed_products = $settings['wc_opportunities_products'] ?? [];
			if ( empty( $allowed_products ) ) {
				return false;
			}

			foreach ( $items as $item ) {
				$product_id = $cart_or_order instanceof \WC_Order
					? $item->get_product_id()
					: $item['product_id'];

				if ( in_array( $product_id, $allowed_products ) ) {
					return true;
				}
			}
			return false;
		}

		// Specific categories filter
		if ( $filter_type === 'categories' ) {
			$allowed_categories = $settings['wc_opportunities_categories'] ?? [];
			if ( empty( $allowed_categories ) ) {
				return false;
			}

			foreach ( $items as $item ) {
				$product_id = $cart_or_order instanceof \WC_Order
					? $item->get_product_id()
					: $item['product_id'];

				$product_categories = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );

				if ( ! empty( array_intersect( $product_categories, $allowed_categories ) ) ) {
					return true;
				}
			}
			return false;
		}

		return false;
	}

	/**
	 * Create opportunity for abandoned cart
	 *
	 * @param string $email Customer email.
	 * @param array  $cart_data Cart data.
	 * @return bool Success status.
	 */
	public function create_abandoned_cart_opportunity( string $email, array $cart_data ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$settings = $this->settings_manager->get_settings_array();
		$stage_id = $settings['wc_opportunities_stage_abandoned'] ?? '';

		if ( empty( $stage_id ) ) {

			return false;
		}

		// Prepare opportunity data for queue
		$opportunity_payload = [
			'email'            => $email,
			'cart_data'        => $cart_data,
			'pipeline_id'      => $settings['wc_opportunities_pipeline'],
			'stage_id'         => $stage_id,
			'name'             => sprintf( 'Abandoned Cart - %s', $email ),
			'monetary_value'   => floatval( $cart_data['cart_total'] ?? 0 ),
			'status'           => 'open',
			'source'           => 'woocommerce_abandoned_cart',
			'opportunity_type' => 'abandoned_cart',
		];

		// Add to queue for async processing
		$cart_key = $cart_data['cart_key'] ?? 'unknown';
		$queue_id = $this->queue_manager->add_to_queue(
			'wc_customer',
			crc32( $cart_key ), // Use cart key as unique ID
			'create_opportunity',
			$opportunity_payload
		);

		if ( $queue_id ) {
			error_log(
				sprintf(
					'GHL Opportunities: Queued abandoned cart opportunity for %s (Queue ID: %d)',
					$email,
					$queue_id
				)
			);
			return true;
		}

		return false;
	}

	/**
	 * Create or update opportunity for order
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @param string    $opportunity_id Existing opportunity ID (if updating).
	 * @return bool Success status.
	 */
	public function handle_order_opportunity( \WC_Order $order, string $opportunity_id = '' ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		// Check if order matches filter
		if ( ! $this->matches_filter( $order ) ) {
			return false;
		}

		$settings = $this->settings_manager->get_settings_array();
		$email    = $order->get_billing_email();

		// Determine stage based on order status
		$stage_id = $this->get_stage_for_order_status( $order->get_status() );
		if ( empty( $stage_id ) ) {

			return false;
		}

		// Determine status (won/lost/open)
		$status = $this->get_opportunity_status( $order->get_status() );

		// Prepare opportunity data for queue
		$opportunity_payload = [
			'order_id'         => $order->get_id(),
			'email'            => $email,
			'first_name'       => $order->get_billing_first_name(),
			'last_name'        => $order->get_billing_last_name(),
			'phone'            => $order->get_billing_phone(),
			'pipeline_id'      => $settings['wc_opportunities_pipeline'],
			'stage_id'         => $stage_id,
			'name'             => sprintf( 'Order #%d - %s', $order->get_id(), $email ),
			'monetary_value'   => floatval( $order->get_total() ),
			'status'           => $status,
			'source'           => 'woocommerce_order',
			'opportunity_id'   => $opportunity_id,
			'opportunity_type' => empty( $opportunity_id ) ? 'new_order' : 'order_update',
		];

		// Determine action based on whether opportunity exists
		$action = empty( $opportunity_id ) ? 'create_opportunity' : 'update_opportunity';

		// Add to queue for async processing
		$queue_id = $this->queue_manager->add_to_queue(
			'wc_customer',
			$order->get_id(),
			$action,
			$opportunity_payload
		);

		if ( $queue_id ) {
			error_log(
				sprintf(
					'GHL Opportunities: Queued %s for order #%d (Queue ID: %d)',
					$action === 'create_opportunity' ? 'opportunity creation' : 'opportunity update',
					$order->get_id(),
					$queue_id
				)
			);
			return true;
		}

		return false;
	}

	/**
	 * Get stage ID for order status
	 *
	 * @param string $order_status WooCommerce order status.
	 * @return string Stage ID.
	 */
	private function get_stage_for_order_status( string $order_status ): string {
		$settings = $this->settings_manager->get_settings_array();

		// Map WC status to configured stage
		$status_map = [
			'pending'    => 'wc_opportunities_stage_pending',
			'processing' => 'wc_opportunities_stage_processing',
			'completed'  => 'wc_opportunities_stage_completed',
			'cancelled'  => 'wc_opportunities_stage_cancelled',
			'refunded'   => 'wc_opportunities_stage_cancelled',
			'failed'     => 'wc_opportunities_stage_cancelled',
		];

		$setting_key = $status_map[ $order_status ] ?? '';
		return $setting_key ? ( $settings[ $setting_key ] ?? '' ) : '';
	}

	/**
	 * Get opportunity status based on order status
	 *
	 * @param string $order_status WooCommerce order status.
	 * @return string 'open', 'won', or 'lost'.
	 */
	private function get_opportunity_status( string $order_status ): string {
		// Won statuses
		if ( in_array( $order_status, [ 'completed' ] ) ) {
			return 'won';
		}

		// Lost statuses
		if ( in_array( $order_status, [ 'cancelled', 'refunded', 'failed' ] ) ) {
			return 'lost';
		}

		// Everything else is still open
		return 'open';
	}

	/**
	 * Get or create contact in GHL
	 *
	 * @param string $email Contact email.
	 * @param array  $additional_data Additional contact data.
	 * @return array|false Contact data or false on failure.
	 */
	private function get_or_create_contact( string $email, array $additional_data = [] ) {
		try {
			// Try to find existing contact
			$contacts = $this->contact_resource->search( [ 'email' => $email ] );

			if ( ! empty( $contacts['contacts'][0] ) ) {
				return $contacts['contacts'][0];
			}

			// Create new contact
			$contact_data = [
				'email'     => $email,
				'firstName' => $additional_data['first_name'] ?? '',
				'lastName'  => $additional_data['last_name'] ?? '',
				'phone'     => $additional_data['phone'] ?? '',
			];

			$result = $this->contact_resource->create( $contact_data );
			return $result['contact'] ?? $result;
		} catch ( \Exception $e ) {

			return false;
		}
	}

	/**
	 * Delete opportunity
	 *
	 * @param string $opportunity_id Opportunity ID.
	 * @return bool Success status.
	 */
	public function delete_opportunity( string $opportunity_id ): bool {
		try {
			$this->opportunity_resource->delete( $opportunity_id );
			return true;
		} catch ( \Exception $e ) {

			return false;
		}
	}
}
