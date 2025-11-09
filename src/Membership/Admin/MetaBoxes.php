<?php
declare(strict_types=1);

namespace GHL_CRM\Membership\Admin;

use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta Boxes
 *
 * Adds GHL membership restriction meta boxes to pages, posts, and products
 * Allows controlling access based on GoHighLevel tags
 *
 * @package    GHL_CRM_Integration
 * @subpackage Membership/Admin
 */
class MetaBoxes {

	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

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
	 * Private constructor
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Only load in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Check if connection is verified
		if ( ! $this->settings_manager->is_connection_verified() ) {
			return;
		}

		// Check if restrictions are enabled
		if ( ! $this->settings_manager->get_setting( 'restrictions_enabled', true ) ) {
			return;
		}

		// Add meta boxes to post types
		add_action( 'add_meta_boxes', [ $this, 'add_membership_meta_box' ] );

		// Save meta box data
		add_action( 'save_post', [ $this, 'save_membership_meta_box' ], 10, 2 );

		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only load on post edit screens
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
			return;
		}

		// Check if this post type supports our meta box
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, [ 'page', 'post', 'product', 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic' ] ) ) {
			return;
		}

		// Enqueue Select2 (registered globally with plugin-specific handles)
		wp_enqueue_style( 'ghl-crm-select2-css' );
		wp_enqueue_script( 'ghl-crm-select2' );

		// Enqueue our custom assets
		$plugin_dir = plugin_dir_url( dirname( dirname( dirname( __DIR__ ) ) ) . '/gohighlevel-crm-integration.php' );

		// Enqueue globals CSS
		wp_enqueue_style(
			'ghl-globals',
			$plugin_dir . 'assets/admin/css/globals.css',
			[],
			GHL_CRM_VERSION
		);

		wp_enqueue_style(
			'ghl-membership-admin',
			$plugin_dir . 'assets/admin/css/membership-admin.css',
			[ 'ghl-crm-select2-css', 'ghl-globals' ],
			GHL_CRM_VERSION
		);

		wp_enqueue_script(
			'ghl-membership-admin',
			$plugin_dir . 'assets/admin/js/membership-admin.js',
			[ 'jquery', 'ghl-crm-select2' ],
			GHL_CRM_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'ghl-membership-admin',
			'ghlMembership',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_user_profile' ),
			]
		);
	}

	/**
	 * Add membership meta box to post types
	 *
	 * @return void
	 */
	public function add_membership_meta_box(): void {
		$post_types = [ 'page', 'post' ];

		// Add WooCommerce products if WooCommerce is active
		if ( class_exists( 'WooCommerce' ) ) {
			$post_types[] = 'product';
		}

		// Add LearnDash courses if LearnDash is active
		if ( class_exists( 'SFWD_LMS' ) ) {
			$post_types[] = 'sfwd-courses';
			$post_types[] = 'sfwd-lessons';
			$post_types[] = 'sfwd-topic';
		}

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'ghl_membership_restrictions',
				__( 'GHL Membership Restrictions', 'ghl-crm-integration' ),
				[ $this, 'render_membership_meta_box' ],
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render membership meta box
	 *
	 * @param \WP_Post $post Post object
	 * @return void
	 */
	public function render_membership_meta_box( \WP_Post $post ): void {
		// Get saved values
		$restriction_type = get_post_meta( $post->ID, '_ghl_restriction_type', true );
		$required_tags    = get_post_meta( $post->ID, '_ghl_required_tags', true );
		$redirect_url     = get_post_meta( $post->ID, '_ghl_redirect_url', true );

		if ( ! is_array( $required_tags ) ) {
			$required_tags = [];
		}

		wp_nonce_field( 'ghl_membership_meta_box', 'ghl_membership_nonce' );

		?>
		<div class="ghl-membership-meta-box">
			<p class="description">
				<?php esc_html_e( 'Control who can access this content based on their GoHighLevel tags.', 'ghl-crm-integration' ); ?>
			</p>

			<!-- Restriction Type -->
			<p>
				<label for="ghl_restriction_type">
					<strong><?php esc_html_e( 'Restriction Type', 'ghl-crm-integration' ); ?></strong>
				</label>
			</p>
			<p>
				<select name="ghl_restriction_type" id="ghl_restriction_type" class="widefat">
					<option value="" <?php selected( $restriction_type, '' ); ?>>
						<?php esc_html_e( '— No Restrictions —', 'ghl-crm-integration' ); ?>
					</option>
					<option value="has_any_tag" <?php selected( $restriction_type, 'has_any_tag' ); ?>>
						<?php esc_html_e( 'User has ANY of these tags', 'ghl-crm-integration' ); ?>
					</option>
					<option value="has_all_tags" <?php selected( $restriction_type, 'has_all_tags' ); ?>>
						<?php esc_html_e( 'User has ALL of these tags', 'ghl-crm-integration' ); ?>
					</option>
					<option value="not_has_tags" <?php selected( $restriction_type, 'not_has_tags' ); ?>>
						<?php esc_html_e( 'User does NOT have these tags', 'ghl-crm-integration' ); ?>
					</option>
				</select>
			</p>

			<!-- Required Tags -->
			<div id="ghl-tags-container" style="<?php echo empty( $restriction_type ) ? 'display:none;' : ''; ?>">
				<p>
					<label for="ghl_required_tags">
						<strong><?php esc_html_e( 'Tags', 'ghl-crm-integration' ); ?></strong>
					</label>
				</p>
				<p>
					<select 
						name="ghl_required_tags[]" 
						id="ghl_required_tags" 
						class="widefat ghl-tags-select" 
						multiple="multiple"
						data-placeholder="<?php esc_attr_e( 'Select or type tags...', 'ghl-crm-integration' ); ?>">
						<?php foreach ( $required_tags as $tag ) : ?>
							<option value="<?php echo esc_attr( $tag ); ?>" selected="selected">
								<?php echo esc_html( $tag ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<!-- Redirect URL -->
			<div id="ghl-redirect-container" style="<?php echo empty( $restriction_type ) ? 'display:none;' : ''; ?>">
				<p>
					<label for="ghl_redirect_url">
						<strong><?php esc_html_e( 'Redirect URL', 'ghl-crm-integration' ); ?></strong>
					</label>
				</p>
				<p>
					<input 
						type="url" 
						name="ghl_redirect_url" 
						id="ghl_redirect_url" 
						class="widefat" 
						value="<?php echo esc_url( $redirect_url ); ?>"
						placeholder="<?php esc_attr_e( 'https://example.com/login', 'ghl-crm-integration' ); ?>"
					/>
					<span class="description">
						<?php esc_html_e( 'Where to redirect users who don\'t have access. Leave empty to show a message instead.', 'ghl-crm-integration' ); ?>
					</span>
				</p>
			</div>

			<hr style="margin: 15px 0;">

			<!-- Help Text -->
			<details>
				<summary style="cursor: pointer; font-weight: 600;">
					<?php esc_html_e( 'How it works', 'ghl-crm-integration' ); ?>
				</summary>
				<div style="margin-top: 10px; font-size: 12px; color: #666;">
					<p><strong><?php esc_html_e( 'ANY of these tags:', 'ghl-crm-integration' ); ?></strong><br>
						<?php esc_html_e( 'User needs at least ONE of the selected tags to access.', 'ghl-crm-integration' ); ?>
					</p>
					<p><strong><?php esc_html_e( 'ALL of these tags:', 'ghl-crm-integration' ); ?></strong><br>
						<?php esc_html_e( 'User needs ALL selected tags to access.', 'ghl-crm-integration' ); ?>
					</p>
					<p><strong><?php esc_html_e( 'Does NOT have these tags:', 'ghl-crm-integration' ); ?></strong><br>
						<?php esc_html_e( 'User must NOT have any of the selected tags to access.', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			</details>
		</div>
		<?php
	}

	/**
	 * Save membership meta box data
	 *
	 * @param int      $post_id Post ID
	 * @param \WP_Post $post    Post object
	 * @return void
	 */
	public function save_membership_meta_box( int $post_id, \WP_Post $post ): void {
		// Check if our nonce is set
		if ( ! isset( $_POST['ghl_membership_nonce'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ghl_membership_nonce'] ) ), 'ghl_membership_meta_box' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save restriction type
		$restriction_type = isset( $_POST['ghl_restriction_type'] ) ? sanitize_text_field( wp_unslash( $_POST['ghl_restriction_type'] ) ) : '';
		update_post_meta( $post_id, '_ghl_restriction_type', $restriction_type );

		// Save required tags
		$required_tags = isset( $_POST['ghl_required_tags'] ) && is_array( $_POST['ghl_required_tags'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['ghl_required_tags'] ) )
			: [];
		update_post_meta( $post_id, '_ghl_required_tags', $required_tags );

		// Save redirect URL
		$redirect_url = isset( $_POST['ghl_redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['ghl_redirect_url'] ) ) : '';
		update_post_meta( $post_id, '_ghl_redirect_url', $redirect_url );
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		// Only load on post edit pages
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		// Enqueue Select2 (registered globally with plugin-specific handles)
		wp_enqueue_style( 'ghl-crm-select2-css' );
		wp_enqueue_script( 'ghl-crm-select2' );

		// Enqueue custom script
		wp_enqueue_script(
			'ghl-membership-admin',
			GHL_CRM_URL . 'assets/admin/js/membership-admin.js',
			[ 'jquery', 'ghl-crm-select2' ],
			'1.0.0',
			true
		);

		// Localize script
		wp_localize_script(
			'ghl-membership-admin',
			'ghlMembership',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
			]
		);

		// Add inline styles
		wp_add_inline_style(
			'ghl-crm-select2-css',
			'
			.ghl-membership-meta-box .select2-container {
				width: 100% !important;
			}
			.ghl-membership-meta-box details {
				margin-top: 10px;
			}
			.ghl-membership-meta-box details summary {
				padding: 5px 0;
				user-select: none;
			}
			.ghl-membership-meta-box details[open] summary {
				margin-bottom: 10px;
			}
		'
		);
	}

	/**
	 * Initialize (called by Loader)
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}
