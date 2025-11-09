<?php
/**
 * Abandoned Cart Tracker
 *
 * Tracks abandoned carts and syncs them to GoHighLevel with tags.
 *
 * @package GHL_CRM
 * @subpackage Integrations\WooCommerce
 */

namespace GHL_CRM\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class AbandonedCartTracker
 *
 * Manages abandoned cart detection and GHL sync
 */
class AbandonedCartTracker {

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
	 * Opportunity Manager instance
	 *
	 * @var OpportunityManager
	 */
	private $opportunity_manager;

	/**
	 * Abandonment threshold in minutes
	 *
	 * @var int
	 */
	private $abandonment_threshold;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager    = \GHL_CRM\Core\SettingsManager::get_instance();
		$this->contact_resource    = new \GHL_CRM\API\Resources\ContactResource();
		$this->opportunity_manager = new OpportunityManager();

		$settings                    = $this->settings_manager->get_settings_array();
		$this->abandonment_threshold = absint( $settings['wc_abandoned_cart_time'] ?? 30 ); // Default: 30 minutes
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		// Track cart updates
		add_action( 'woocommerce_add_to_cart', [ $this, 'track_cart_update' ], 10, 0 );
		add_action( 'woocommerce_cart_item_removed', [ $this, 'track_cart_update' ], 10, 0 );
		add_action( 'woocommerce_cart_item_restored', [ $this, 'track_cart_update' ], 10, 0 );
		add_action( 'woocommerce_after_cart_item_quantity_update', [ $this, 'track_cart_update' ], 10, 0 );

		// Capture email at checkout (multiple hooks for different scenarios)
		// Classic checkout hooks
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'capture_checkout_email' ], 10, 1 );
		add_action( 'woocommerce_after_checkout_form', [ $this, 'capture_logged_in_user_email' ], 10, 0 );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'capture_logged_in_user_email' ], 10, 0 );

		// WooCommerce Blocks checkout hooks (Store API)
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ $this, 'capture_block_checkout_email' ], 10, 2 );
		add_action( 'woocommerce_blocks_loaded', [ $this, 'init_blocks_support' ] );

		// REST API endpoint for JavaScript to send cart data
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Schedule cart check
		add_action( 'ghl_crm_check_abandoned_carts', [ $this, 'check_abandoned_carts' ] );

		if ( ! wp_next_scheduled( 'ghl_crm_check_abandoned_carts' ) ) {
			wp_schedule_event( time(), 'ghl_crm_15min', 'ghl_crm_check_abandoned_carts' );
		}

		// Mark cart as recovered when order is completed
		add_action( 'woocommerce_thankyou', [ $this, 'mark_cart_recovered' ], 10, 1 );
	}

	/**
	 * Track cart update
	 *
	 * @return void
	 */
	public function track_cart_update(): void {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$cart_key = $this->get_cart_key();
		if ( empty( $cart_key ) ) {
			return;
		}

		$cart_data = $this->get_cart_snapshot();

		// Store or update cart data in transient
		set_transient( 'ghl_cart_' . $cart_key, $cart_data, DAY_IN_SECONDS * 7 ); // 7 days expiry
	}

	/**
	 * Capture email during checkout
	 *
	 * @param string $post_data Posted checkout data.
	 * @return void
	 */
	public function capture_checkout_email( string $post_data ): void {
		parse_str( $post_data, $data );

		$email = sanitize_email( $data['billing_email'] ?? '' );
		if ( empty( $email ) ) {
			return;
		}

		$cart_key = $this->get_cart_key();
		if ( empty( $cart_key ) ) {
			return;
		}

		// Update cart data with email
		$cart_data = get_transient( 'ghl_cart_' . $cart_key );
		if ( $cart_data === false ) {
			$cart_data = $this->get_cart_snapshot();
		}

		$cart_data['email']            = $email;
		$cart_data['checkout_started'] = true;

		// Extract name if available
		if ( ! empty( $data['billing_first_name'] ) ) {
			$cart_data['first_name'] = sanitize_text_field( $data['billing_first_name'] );
		}
		if ( ! empty( $data['billing_last_name'] ) ) {
			$cart_data['last_name'] = sanitize_text_field( $data['billing_last_name'] );
		}
		if ( ! empty( $data['billing_phone'] ) ) {
			$cart_data['phone'] = sanitize_text_field( $data['billing_phone'] );
		}

		set_transient( 'ghl_cart_' . $cart_key, $cart_data, DAY_IN_SECONDS * 7 );
	}

	/**
	 * Capture logged-in user email when they land on checkout page
	 * This handles cases where users don't trigger checkout_update_order_review
	 *
	 * @return void
	 */
	public function capture_logged_in_user_email(): void {
		// Only for logged-in users
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user = wp_get_current_user();
		if ( empty( $user->user_email ) ) {
			return;
		}

		$cart_key = $this->get_cart_key();
		if ( empty( $cart_key ) ) {
			return;
		}

		// Get existing cart data
		$cart_data = get_transient( 'ghl_cart_' . $cart_key );
		if ( $cart_data === false ) {
			$cart_data = $this->get_cart_snapshot();
		}

		// Only update if email not already captured
		if ( ! empty( $cart_data['email'] ) ) {
			return;
		}

		// Set user data from WordPress user
		$cart_data['email']            = sanitize_email( $user->user_email );
		$cart_data['checkout_started'] = true;
		$cart_data['first_name']       = $user->first_name ?: '';
		$cart_data['last_name']        = $user->last_name ?: '';

		// Try to get phone from billing meta
		$cart_data['phone'] = get_user_meta( $user->ID, 'billing_phone', true ) ?: '';

		set_transient( 'ghl_cart_' . $cart_key, $cart_data, DAY_IN_SECONDS * 7 );
	}

	/**
	 * Capture email from WooCommerce Blocks checkout (Store API)
	 *
	 * @param \WC_Order        $order Order object.
	 * @param \WP_REST_Request $request Request object.
	 * @return void
	 */
	public function capture_block_checkout_email( $order, $request ): void {
		$billing = $request->get_param( 'billing_address' );

		if ( empty( $billing['email'] ) ) {
			return;
		}

		$cart_key = $this->get_cart_key();
		if ( empty( $cart_key ) ) {
			return;
		}

		// Update cart data with email
		$cart_data = get_transient( 'ghl_cart_' . $cart_key );
		if ( $cart_data === false ) {
			$cart_data = $this->get_cart_snapshot();
		}

		$cart_data['email']            = sanitize_email( $billing['email'] );
		$cart_data['checkout_started'] = true;
		$cart_data['first_name']       = sanitize_text_field( $billing['first_name'] ?? '' );
		$cart_data['last_name']        = sanitize_text_field( $billing['last_name'] ?? '' );
		$cart_data['phone']            = sanitize_text_field( $billing['phone'] ?? '' );

		set_transient( 'ghl_cart_' . $cart_key, $cart_data, DAY_IN_SECONDS * 7 );
	}

	/**
	 * Initialize WooCommerce Blocks support
	 *
	 * @return void
	 */
	public function init_blocks_support(): void {
		// Enqueue JavaScript for blocks checkout
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_blocks_script' ] );
	}

	/**
	 * Enqueue JavaScript for WooCommerce Blocks checkout tracking
	 *
	 * @return void
	 */
	public function enqueue_blocks_script(): void {
		// Only load on checkout page
		if ( ! is_checkout() ) {
			return;
		}

		// Check if the page content has the WooCommerce checkout block
		global $post;
		if ( ! $post || ! has_block( 'woocommerce/checkout', $post ) ) {
			return;
		}

		$script_url = GHL_CRM_URL . 'assets/frontend/js/abandoned-cart-blocks.js';

		wp_enqueue_script(
			'ghl-abandoned-cart-blocks',
			$script_url,
			[ 'jquery', 'wp-hooks', 'wc-blocks-checkout' ],
			'1.0.0',
			true
		);

		wp_localize_script(
			'ghl-abandoned-cart-blocks',
			'ghlAbandonedCart',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'ghl-crm/v1/abandoned-cart' ),
				'nonce'   => wp_create_nonce( 'ghl_abandoned_cart' ),
				'userId'  => get_current_user_id(),
				'cartKey' => $this->get_cart_key(),
			]
		);
	}

	/**
	 * Register REST API routes for abandoned cart tracking
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'ghl-crm/v1',
			'/abandoned-cart',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_capture_cart_data' ],
				'permission_callback' => '__return_true', // Public endpoint
			]
		);
	}

	/**
	 * REST API endpoint to capture cart data from JavaScript
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function rest_capture_cart_data( $request ): \WP_REST_Response {
		$email      = sanitize_email( $request->get_param( 'email' ) );
		$first_name = sanitize_text_field( $request->get_param( 'first_name' ) );
		$last_name  = sanitize_text_field( $request->get_param( 'last_name' ) );
		$phone      = sanitize_text_field( $request->get_param( 'phone' ) );
		$cart_key   = sanitize_text_field( $request->get_param( 'cart_key' ) );
		$user_id    = absint( $request->get_param( 'user_id' ) );

		if ( empty( $email ) ) {
			return new \WP_REST_Response( [ 'error' => 'Email required' ], 400 );
		}

		// Use cart key from request if provided, otherwise generate one
		if ( empty( $cart_key ) ) {
			// Initialize WooCommerce cart and session if not already done
			if ( ! WC()->cart ) {
				wc_load_cart();
			}

			$cart_key = $this->get_cart_key();

			// If still no cart key, create one from user ID or email hash
			if ( empty( $cart_key ) ) {
				if ( $user_id > 0 ) {
					$cart_key = 'user_' . $user_id;
				} else {
					$remote_addr      = '';
					$remote_addr_hash = 'unknown_ip';
					if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
						$remote_addr = wp_unslash( $_SERVER['REMOTE_ADDR'] );
						$validated_ip = filter_var( $remote_addr, FILTER_VALIDATE_IP );
						if ( false !== $validated_ip ) {
							$remote_addr_hash = $validated_ip;
						} else {
							$remote_addr_hash = sanitize_text_field( $remote_addr );
						}
					}

					// For guests without a session yet, use email hash combined with sanitized IP fallback
					$cart_key = 'guest_' . md5( $email . $remote_addr_hash );
				}
			}
		}

		// Get or create cart data
		$cart_data = get_transient( 'ghl_cart_' . $cart_key );
		if ( $cart_data === false ) {
			// Create new cart snapshot
			if ( WC()->cart && ! WC()->cart->is_empty() ) {
				$cart_data = $this->get_cart_snapshot();
			} else {
				// Minimal cart data for REST endpoint calls
				$cart_data = [
					'cart_total'       => 0,
					'item_count'       => 0,
					'items'            => [],
					'created_at'       => time(),
					'updated_at'       => time(),
					'abandoned'        => false,
					'recovered'        => false,
					'checkout_started' => false,
				];
			}
		}

		$cart_data['email']            = $email;
		$cart_data['checkout_started'] = true;
		$cart_data['first_name']       = $first_name;
		$cart_data['last_name']        = $last_name;
		$cart_data['phone']            = $phone;
		$cart_data['updated_at']       = time();

		set_transient( 'ghl_cart_' . $cart_key, $cart_data, DAY_IN_SECONDS * 7 );

		return new \WP_REST_Response(
			[
				'success'  => true,
				'cart_key' => $cart_key,
			],
			200
		);
	}

	/**
	 * Check for abandoned carts
	 * Called by WP-Cron every 15 minutes
	 *
	 * @return void
	 */
	public function check_abandoned_carts(): void {
		global $wpdb;

		$settings = $this->settings_manager->get_settings_array();
		if ( empty( $settings['wc_abandoned_cart_enabled'] ) ) {
			return;
		}

		// Get all cart transients
		$transient_prefix = $wpdb->esc_like( '_transient_ghl_cart_' ) . '%';
		$site_id          = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying options table for plugin transients during scheduled cleanup.
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_name NOT LIKE %s",
				$transient_prefix,
				'%_timeout_%'
			)
		);

		foreach ( $transients as $transient_name ) {
			$cart_key = str_replace( '_transient_ghl_cart_', '', $transient_name );
			$this->process_cart( $cart_key );
		}
	}

	/**
	 * Process individual cart for abandonment
	 *
	 * @param string $cart_key Cart key.
	 * @return void
	 */
	private function process_cart( string $cart_key ): void {
		$cart_data = get_transient( 'ghl_cart_' . $cart_key );

		if ( $cart_data === false ) {
			return;
		}

		// Skip if no email captured
		if ( empty( $cart_data['email'] ) ) {
			return;
		}

		// Skip if already marked as abandoned
		if ( ! empty( $cart_data['abandoned'] ) ) {
			return;
		}

		// Skip if recovered
		if ( ! empty( $cart_data['recovered'] ) ) {
			return;
		}

		// Check if cart exceeds abandonment threshold
		$time_elapsed      = time() - $cart_data['updated_at'];
		$threshold_seconds = $this->abandonment_threshold * MINUTE_IN_SECONDS;

		if ( $time_elapsed < $threshold_seconds ) {
			return; // Not abandoned yet
		}

		// Mark as abandoned and sync to GHL
		$this->mark_cart_abandoned( $cart_key, $cart_data );
	}

	/**
	 * Mark cart as abandoned and sync to GHL
	 *
	 * @param string $cart_key  Cart key.
	 * @param array  $cart_data Cart data.
	 * @return void
	 */
	private function mark_cart_abandoned( string $cart_key, array $cart_data ): void {
		try {
			$settings       = $this->settings_manager->get_settings_array();
			$abandoned_tags = $settings['wc_abandoned_cart_tag'] ?? [];

			// Ensure tags is an array
			if ( ! is_array( $abandoned_tags ) ) {
				$abandoned_tags = ! empty( $abandoned_tags ) ? [ $abandoned_tags ] : [];
			}

			if ( empty( $abandoned_tags ) ) {
				return; // No tags configured
			}

			// Prepare contact data
			$contact_data = [
				'email'     => $cart_data['email'],
				'firstName' => $cart_data['first_name'] ?? '',
				'lastName'  => $cart_data['last_name'] ?? '',
				'phone'     => $cart_data['phone'] ?? '',
			];

			// Upsert contact
			$result = $this->contact_resource->upsert( $contact_data );

			if ( empty( $result['contact']['id'] ) ) {

				return;
			}

			$contact_id = $result['contact']['id'];

			// Add abandoned cart tags to GHL
			$this->contact_resource->add_tags( $contact_id, $abandoned_tags );

			// If this is a logged-in user, also add tags to WordPress user meta
			$user_id = null;
			if ( strpos( $cart_key, 'user_' ) === 0 ) {
				$user_id = absint( str_replace( 'user_', '', $cart_key ) );
			} else {
				// Try to find user by email
				$user = get_user_by( 'email', $cart_data['email'] );
				if ( $user ) {
					$user_id = $user->ID;
				}
			}

			if ( $user_id ) {
				// Get existing GHL tags from user meta
				$existing_tags = get_user_meta( $user_id, 'ghl_tags', true );
				if ( ! is_array( $existing_tags ) ) {
					$existing_tags = [];
				}

				// Merge with abandoned cart tags (avoid duplicates)
				$updated_tags = array_unique( array_merge( $existing_tags, $abandoned_tags ) );
				update_user_meta( $user_id, 'ghl_tags', $updated_tags );

				// Also store GHL contact ID if not already set
				$stored_contact_id = get_user_meta( $user_id, 'ghl_contact_id', true );
				if ( empty( $stored_contact_id ) ) {
					update_user_meta( $user_id, 'ghl_contact_id', $contact_id );
				}
			}

			// Create opportunity if enabled (queued for async processing)
			$opportunity_queued = false;
			if ( $this->opportunity_manager->is_enabled() ) {
				// Check if cart matches opportunity filter
				if ( WC()->cart && ! WC()->cart->is_empty() ) {
					if ( $this->opportunity_manager->matches_filter( WC()->cart ) ) {
						// Add cart_key to cart_data for queue processing
						$cart_data['cart_key'] = $cart_key;
						$opportunity_queued    = $this->opportunity_manager->create_abandoned_cart_opportunity(
							$cart_data['email'],
							$cart_data
						);
					}
				}
			}

			// Mark cart as abandoned in transient
			$cart_data['abandoned']              = true;
			$cart_data['abandoned_at']           = time();
			$cart_data['ghl_contact_id']         = $contact_id;
			$cart_data['ghl_tags_applied']       = $abandoned_tags;
			$cart_data['ghl_opportunity_queued'] = $opportunity_queued;
			$cart_data['wp_user_id']             = $user_id;

			set_transient( 'ghl_cart_' . $cart_key, $cart_data, DAY_IN_SECONDS * 7 );

			// Log to sync logger
			if ( class_exists( '\GHL_CRM\Sync\SyncLogger' ) ) {
				\GHL_CRM\Sync\SyncLogger::log(
					'abandoned_cart',
					'success',
					sprintf(
						'Abandoned cart tagged for %s - Cart Value: %s, Items: %d',
						$cart_data['email'],
						wc_price( $cart_data['cart_total'] ),
						$cart_data['item_count']
					),
					[
						'cart_key'     => $cart_key,
						'email'        => $cart_data['email'],
						'contact_id'   => $contact_id,
						'tags'         => $abandoned_tags,
						'cart_total'   => $cart_data['cart_total'],
						'item_count'   => $cart_data['item_count'],
						'user_id'      => $user_id,
						'abandoned_at' => gmdate( 'Y-m-d H:i:s', (int) $cart_data['abandoned_at'] ),
					]
				);
			}

			// Also log to error log for immediate visibility
			error_log(
				sprintf(
					'GHL Abandoned Cart: Tagged cart for %s (User ID: %s) - Cart Value: %s, Items: %d, Tags: %s, GHL Contact: %s',
					$cart_data['email'],
					$user_id ? $user_id : 'guest',
					wc_price( $cart_data['cart_total'] ),
					$cart_data['item_count'],
					implode( ', ', $abandoned_tags ),
					$contact_id
				)
			);        } catch ( \Exception $e ) {

			}
	}

	/**
	 * Mark cart as recovered when order is completed
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function mark_cart_recovered( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$email = $order->get_billing_email();
		if ( empty( $email ) ) {
			return;
		}

		$cart_key = $this->get_cart_key();
		if ( empty( $cart_key ) ) {
			return;
		}

		$cart_data = get_transient( 'ghl_cart_' . $cart_key );
		if ( $cart_data === false ) {
			return;
		}

		// Mark as recovered
		$cart_data['recovered']    = true;
		$cart_data['recovered_at'] = time();
		$cart_data['order_id']     = $order_id;

		set_transient( 'ghl_cart_' . $cart_key, $cart_data, DAY_IN_SECONDS * 7 );

		// Log recovery
		if ( ! empty( $cart_data['abandoned'] ) ) {
			$time_to_recovery = $cart_data['recovered_at'] - $cart_data['abandoned_at'];
			error_log(
				sprintf(
					'GHL Abandoned Cart: Cart recovered - Email: %s, Time: %s, Order: #%d',
					$email,
					human_time_diff( $cart_data['abandoned_at'], $cart_data['recovered_at'] ),
					$order_id
				)
			);
		}
	}

	/**
	 * Get cart snapshot
	 *
	 * @return array Cart data.
	 */
	private function get_cart_snapshot(): array {
		$cart = WC()->cart;

		$items = [];
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			$items[] = [
				'product_id'   => $cart_item['product_id'],
				'variation_id' => $cart_item['variation_id'] ?? 0,
				'quantity'     => $cart_item['quantity'],
				'name'         => $product->get_name(),
				'price'        => $product->get_price(),
			];
		}

		return [
			'cart_total'       => floatval( $cart->get_cart_contents_total() ),
			'item_count'       => $cart->get_cart_contents_count(),
			'items'            => $items,
			'created_at'       => time(),
			'updated_at'       => time(),
			'abandoned'        => false,
			'recovered'        => false,
			'checkout_started' => false,
		];
	}

	/**
	 * Get cart key (session ID or user ID)
	 *
	 * @return string Cart key.
	 */
	private function get_cart_key(): string {
		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}

		// For guests, use WooCommerce session
		if ( WC()->session ) {
			$customer_id = WC()->session->get_customer_id();
			if ( $customer_id ) {
				return 'guest_' . $customer_id;
			}
		}

		return '';
	}
}
