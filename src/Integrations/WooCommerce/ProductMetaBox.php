<?php
/**
 * WooCommerce Product Meta Box
 *
 * Adds GHL tag selection to product edit pages
 *
 * @package GHL_CRM
 * @subpackage Integrations\WooCommerce
 */

namespace GHL_CRM\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProductMetaBox
 *
 * Handles product-level tag configuration
 */
class ProductMetaBox {

	/**
	 * Settings Manager instance
	 *
	 * @var \GHL_CRM\Core\SettingsManager
	 */
	private $settings_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
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

		// Add product data tab
		add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_product_data_tab' ] );

		// Add product data panel
		add_action( 'woocommerce_product_data_panels', [ $this, 'add_product_data_panel' ] );

		// Save product data
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_data' ] );

		// Enqueue assets for product edit page
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Add custom tab icon styles
		add_action( 'admin_head', [ $this, 'add_tab_icon_styles' ] );
	}

	/**
	 * Add product data tab
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function add_product_data_tab( array $tabs ): array {
		$tabs['ghl_tags'] = [
			'label'    => __( 'GoHighLevel', 'ghl-crm-integration' ),
			'target'   => 'ghl_product_tags_panel',
			'class'    => [ 'show_if_simple', 'show_if_variable', 'show_if_grouped', 'show_if_external' ],
			'priority' => 80,
		];

		return $tabs;
	}

	/**
	 * Add product data panel
	 *
	 * @return void
	 */
	public function add_product_data_panel(): void {
		global $post;

		// Get saved tags
		$saved_tags = get_post_meta( $post->ID, '_ghl_purchase_tags', true );
		if ( ! is_array( $saved_tags ) ) {
			$saved_tags = ! empty( $saved_tags ) ? [ $saved_tags ] : [];
		}

		// Get saved order statuses
		$saved_statuses = get_post_meta( $post->ID, '_ghl_purchase_order_statuses', true );
		if ( ! is_array( $saved_statuses ) ) {
			$saved_statuses = ! empty( $saved_statuses ) ? [ $saved_statuses ] : [ 'completed' ];
		}

		// Get all available WooCommerce order statuses
		$all_statuses = wc_get_order_statuses();

		?>
		<div id="ghl_product_tags_panel" class="panel woocommerce_options_panel hidden">
			<div class="options_group ghl-product-tags-panel">
				<p class="form-field ghl-product-tags-field">
					<label for="ghl_purchase_tags">
						<?php esc_html_e( 'GoHighLevel Tags', 'ghl-crm-integration' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Select which tags to automatically apply to customers when they purchase this product. Tags will be applied when the order reaches the status(es) you select below.', 'ghl-crm-integration' ); ?>">?</span>
					</label>
					<span class="woocommerce-input-wrapper">
						<select
							id="ghl_purchase_tags"
							name="ghl_purchase_tags[]"
							class="ghl-tags-select wc-enhanced-select"
							multiple
							data-saved-tags='<?php echo wp_json_encode( $saved_tags ); ?>'
							data-placeholder="<?php esc_attr_e( 'Select tags to apply...', 'ghl-crm-integration' ); ?>">
							<option value=""><?php esc_html_e( 'Loading tags...', 'ghl-crm-integration' ); ?></option>
						</select>
					</span>
				</p>

				<p class="form-field ghl-product-order-statuses-field">
					<label for="ghl_purchase_order_statuses">
						<?php esc_html_e( 'Trigger on Order Status', 'ghl-crm-integration' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Choose when to apply the tags above. For example, select "Completed" to apply tags only when payment is confirmed and order is complete. You can select multiple statuses if needed.', 'ghl-crm-integration' ); ?>">?</span>
					</label>
					<span class="woocommerce-input-wrapper">
						<select
							id="ghl_purchase_order_statuses"
							name="ghl_purchase_order_statuses[]"
							class="wc-enhanced-select"
							multiple
							data-placeholder="<?php esc_attr_e( 'Select order statuses...', 'ghl-crm-integration' ); ?>">
							<?php foreach ( $all_statuses as $status_slug => $status_name ) : ?>
								<?php
								// Remove 'wc-' prefix from status slug for comparison
								$clean_slug = str_replace( 'wc-', '', $status_slug );
								?>
								<option value="<?php echo esc_attr( $clean_slug ); ?>" <?php selected( in_array( $clean_slug, $saved_statuses, true ) ); ?>>
									<?php echo esc_html( $status_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product data
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_product_data( int $post_id ): void {
		// Save tags
		$tags = isset( $_POST['ghl_purchase_tags'] ) && is_array( $_POST['ghl_purchase_tags'] )
			? array_map( 'sanitize_text_field', $_POST['ghl_purchase_tags'] )
			: [];

		if ( ! empty( $tags ) ) {
			update_post_meta( $post_id, '_ghl_purchase_tags', $tags );
		} else {
			delete_post_meta( $post_id, '_ghl_purchase_tags' );
		}

		// Save order statuses
		$order_statuses = isset( $_POST['ghl_purchase_order_statuses'] ) && is_array( $_POST['ghl_purchase_order_statuses'] )
			? array_map( 'sanitize_text_field', $_POST['ghl_purchase_order_statuses'] )
			: [];

		// Default to 'completed' if no statuses selected but tags are configured
		if ( empty( $order_statuses ) && ! empty( $tags ) ) {
			$order_statuses = [ 'completed' ];
		}

		if ( ! empty( $order_statuses ) ) {
			update_post_meta( $post_id, '_ghl_purchase_order_statuses', $order_statuses );
		} else {
			delete_post_meta( $post_id, '_ghl_purchase_order_statuses' );
		}
	}

	/**
	 * Enqueue assets for product edit page
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only on product edit page
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		// Enqueue tooltip system for the help icons
		wp_enqueue_script(
			'ghl-crm-tooltip-system',
			GHL_CRM_URL . 'assets/admin/js/tooltip-system.js',
			[],
			GHL_CRM_VERSION,
			true
		);

		// Enqueue Select2 (already registered by AssetsManager with plugin-specific handles)
		wp_enqueue_style( 'ghl-crm-select2-css' );
		wp_enqueue_script( 'ghl-crm-select2' );

		// Enqueue our custom script for the meta box
		wp_enqueue_script(
			'ghl-product-meta-box',
			GHL_CRM_URL . 'assets/admin/js/product-meta-box.js',
			[ 'jquery', 'ghl-crm-select2', 'ghl-crm-tooltip-system' ],
			GHL_CRM_VERSION,
			true
		);

		// Localize script with AJAX settings
		wp_localize_script(
			'ghl-product-meta-box',
			'ghlProductMetaBox',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => 'ghl_crm_get_tags',
				'nonce'   => wp_create_nonce( 'ghl_crm_settings_nonce' ),
			]
		);
	}

	/**
	 * Get tags for a product
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of tag names.
	 */
	public static function get_product_tags( int $product_id ): array {
		$tags = get_post_meta( $product_id, '_ghl_purchase_tags', true );

		if ( ! is_array( $tags ) ) {
			$tags = ! empty( $tags ) ? [ $tags ] : [];
		}

		return $tags;
	}

	/**
	 * Get order statuses for a product
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of order status slugs (without 'wc-' prefix).
	 */
	public static function get_product_order_statuses( int $product_id ): array {
		$statuses = get_post_meta( $product_id, '_ghl_purchase_order_statuses', true );

		if ( ! is_array( $statuses ) ) {
			$statuses = ! empty( $statuses ) ? [ $statuses ] : [ 'completed' ];
		}

		// Default to 'completed' if empty
		if ( empty( $statuses ) ) {
			$statuses = [ 'completed' ];
		}

		return $statuses;
	}

	/**
	 * Add custom icon styles for the tab
	 *
	 * @return void
	 */
	public function add_tab_icon_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		?>
	<style>
		/* WooCommerce product tab icon */
		#woocommerce-product-data ul.wc-tabs li.ghl_tags_tab a:before {
			content: "\f335"; /* dashicons-admin-users */
			font-family: Dashicons;
			display: inline-block;
			width: 16px;
			height: 16px;
			vertical-align: middle;
			margin-right: 4px;
			opacity: 0.6;
		}
		#woocommerce-product-data ul.wc-tabs li.ghl_tags_tab.active a:before {
			opacity: 1;
		}

		/* Panel styling */
		#ghl_product_tags_panel .ghl-product-tags-panel {
			margin: 0 24px;
			padding: 20px 0;
		}

		#ghl_product_tags_panel .form-field {
			margin-bottom: 20px;
		}

		#ghl_product_tags_panel .form-field:last-child {
			margin-bottom: 0;
		}

		#ghl_product_tags_panel .form-field label {
			display: flex;
			align-items: center;
			min-width: 160px;
			font-weight: 500;
			color: #23282d;
		}

		#ghl_product_tags_panel .woocommerce-input-wrapper {
			display: block;
			max-width: 400px;
		}

		#ghl_product_tags_panel .select2-container {
			width: 100% !important;
		}
	</style>
		<?php
	}
}
