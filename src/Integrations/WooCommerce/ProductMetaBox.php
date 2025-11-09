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

		?>
		<div id="ghl_product_tags_panel" class="panel woocommerce_options_panel hidden">
			<div class="options_group ghl-product-tags-panel">
				<p class="form-field ghl-product-tags-field">
					<label for="ghl_purchase_tags">
						<?php esc_html_e( 'Tags to Apply on Purchase', 'ghl-crm-integration' ); ?>
					</label>
					<span class="woocommerce-input-wrapper">
						<select
							id="ghl_purchase_tags"
							name="ghl_purchase_tags[]"
							class="ghl-tags-select wc-enhanced-select"
							multiple
							data-saved-tags='<?php echo wp_json_encode( $saved_tags ); ?>'
							data-placeholder="<?php esc_attr_e( 'Select tags to apply when this product is purchased...', 'ghl-crm-integration' ); ?>">
							<option value=""><?php esc_html_e( 'Loading tags...', 'ghl-crm-integration' ); ?></option>
						</select>
					</span>
					<span class="description">
						<?php esc_html_e( 'Customers will receive the selected GoHighLevel tags automatically once this product is purchased.', 'ghl-crm-integration' ); ?>
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

		// Enqueue Select2 (already registered by AssetsManager with plugin-specific handles)
		wp_enqueue_style( 'ghl-crm-select2-css' );
		wp_enqueue_script( 'ghl-crm-select2' );

		// Enqueue our custom script for the meta box
		wp_enqueue_script(
			'ghl-product-meta-box',
			GHL_CRM_URL . 'assets/admin/js/product-meta-box.js',
			[ 'jquery', 'ghl-crm-select2' ],
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
            padding-top: 16px;
        }
        #ghl_product_tags_panel .ghl-product-tags-field label {
            min-width: 160px;
        }
        #ghl_product_tags_panel .woocommerce-input-wrapper {
            display: block;
            max-width: 360px;
        }
        #ghl_product_tags_panel .select2-container {
            width: 100%;
        }
        #ghl_product_tags_panel .ghl-product-tags-field .description {
            display: block;
            margin-top: 8px;
            color: #555d66;
        }
        #ghl_product_tags_panel .ghl-product-tags-note {
            margin: 8px 24px 24px;
            padding: 16px;
            border: 1px solid #dcdcde;
            border-left: 4px solid #7e3bd0;
            background: #f6f3fb;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            border-radius: 4px;
        }
        #ghl_product_tags_panel .ghl-product-tags-note .dashicons {
            font-size: 20px;
            color: #7e3bd0;
            margin-top: 2px;
        }
        #ghl_product_tags_panel .ghl-product-tags-note .ghl-note-content {
            margin: 0;
        }
        #ghl_product_tags_panel .ghl-product-tags-note ul {
            margin: 6px 0 0;
            padding-left: 18px;
            list-style: disc;
            color: #2c3338;
        }
        #ghl_product_tags_panel .ghl-product-tags-note li {
            margin-bottom: 4px;
        }
    </style>
    <?php
}

}
