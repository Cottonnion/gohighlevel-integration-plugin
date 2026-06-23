<?php
declare(strict_types=1);

namespace Syncly\Membership\Admin;

use Syncly\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta Boxes
 *
 * Adds GHL membership restriction meta boxes to pages, posts, and products
 * Allows controlling access based on GoHighLevel tags
 *
 * @package    Syncly
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

		// Register admin assets via AssetsManager
		add_action( 'init', [ $this, 'register_assets' ], 20 );
	}

	/**
	 * Register admin assets via AssetsManager for all supported post types.
	 */
	public function register_assets(): void {
		$assets_manager  = \Syncly\Core\AssetsManager::get_instance();
		$supported_types = $this->get_supported_post_types();
		$screens         = array_map(
			static function ( string $type ): string {
				return 'cpt:' . $type;
			},
			$supported_types
		);

		if ( empty( $screens ) ) {
			return;
		}

		// Globals CSS.
		$assets_manager->add_admin_asset(
			'syncly-globals-css',
			$screens,
			'globals.css',
			array(),
			array(),
			SYNCLY_VERSION,
			false
		);

		// Membership admin CSS.
		$assets_manager->add_admin_asset(
			'ghl-membership-admin-css',
			$screens,
			'membership-admin.css',
			array( 'syncly-select2-css', 'syncly-globals-css' ),
			array(),
			SYNCLY_VERSION,
			false
		);

		// Membership admin JS.
		$assets_manager->add_admin_asset(
			'ghl-membership-admin-js',
			$screens,
			'membership-admin.js',
			array( 'jquery', 'syncly-select2' ),
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_user_profile' ),
				'tags'    => \Syncly\Sync\TagManager::get_instance()->get_tags_for_localization(),
			),
			SYNCLY_VERSION
		);
	}

	/**
	 * Get supported post types for membership restrictions
	 *
	 * @return array
	 */
	private function get_supported_post_types(): array {
		// Get all public post types
		$post_types = get_post_types(
			[
				'public' => true,
			],
			'names'
		);

		// Remove attachment as it's not a content type we want to restrict
		$post_types = array_diff( $post_types, [ 'attachment' ] );

		// Allow filtering of supported post types
		$post_types = apply_filters( 'syncly_restriction_post_types', $post_types );

		return array_values( $post_types );
	}

	/**
	 * Add membership meta box to post types
	 *
	 * @return void
	 */
	public function add_membership_meta_box(): void {
		$post_types = $this->get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'ghl_membership_restrictions',
				__( 'GHL Membership Restrictions', 'syncly' ),
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
		$required_tags    = get_post_meta( $post->ID, \Syncly\Sync\TagManager::scoped_meta_key( '_ghl_required_tags' ), true );
		$redirect_url     = get_post_meta( $post->ID, '_ghl_redirect_url', true );

		if ( ! is_array( $required_tags ) ) {
			$required_tags = [];
		}

		wp_nonce_field( 'ghl_membership_meta_box', 'ghl_membership_nonce' );

		?>
		<div class="ghl-membership-meta-box">
			<p class="description">
				<?php esc_html_e( 'Control who can access this content based on their GoHighLevel tags.', 'syncly' ); ?>
			</p>

			<!-- Restriction Type -->
			<p>
				<label for="ghl_restriction_type">
					<strong><?php esc_html_e( 'Restriction Type', 'syncly' ); ?></strong>
				</label>
			</p>
			<p>
				<select name="ghl_restriction_type" id="ghl_restriction_type" class="widefat">
					<option value="" <?php selected( $restriction_type, '' ); ?>>
						<?php esc_html_e( '— No Restrictions —', 'syncly' ); ?>
					</option>
					<option value="has_any_tag" <?php selected( $restriction_type, 'has_any_tag' ); ?>>
						<?php esc_html_e( 'User has ANY of these tags', 'syncly' ); ?>
					</option>
					<option value="has_all_tags" <?php selected( $restriction_type, 'has_all_tags' ); ?>>
						<?php esc_html_e( 'User has ALL of these tags', 'syncly' ); ?>
					</option>
					<option value="not_has_tags" <?php selected( $restriction_type, 'not_has_tags' ); ?>>
						<?php esc_html_e( 'User does NOT have these tags', 'syncly' ); ?>
					</option>
				</select>
			</p>

			<!-- Required Tags -->
			<div id="ghl-tags-container" style="<?php echo empty( $restriction_type ) ? 'display:none;' : ''; ?>">
				<p>
					<label for="ghl_required_tags">
						<strong><?php esc_html_e( 'Tags', 'syncly' ); ?></strong>
					</label>
				</p>
				<p>
					<select 
						name="ghl_required_tags[]" 
						id="ghl_required_tags" 
						class="widefat ghl-tags-select" 
						multiple="multiple"
						data-placeholder="<?php esc_attr_e( 'Select or type tags...', 'syncly' ); ?>">
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
						<strong><?php esc_html_e( 'Redirect URL', 'syncly' ); ?></strong>
					</label>
				</p>
				<p>
					<input 
						type="url" 
						name="ghl_redirect_url" 
						id="ghl_redirect_url" 
						class="widefat" 
						value="<?php echo esc_url( $redirect_url ); ?>"
						placeholder="<?php esc_attr_e( 'https://example.com/login', 'syncly' ); ?>"
					/>
					<span class="description">
						<?php esc_html_e( 'Where to redirect users who don\'t have access. Leave empty to show a message instead.', 'syncly' ); ?>
					</span>
				</p>
			</div>

			<hr style="margin: 15px 0;">

			<!-- Help Text -->
			<details>
				<summary style="cursor: pointer; font-weight: 600;">
					<?php esc_html_e( 'How it works', 'syncly' ); ?>
				</summary>
				<div style="margin-top: 10px; font-size: 12px; color: #666;">
					<p><strong><?php esc_html_e( 'ANY of these tags:', 'syncly' ); ?></strong><br>
						<?php esc_html_e( 'User needs at least ONE of the selected tags to access.', 'syncly' ); ?>
					</p>
					<p><strong><?php esc_html_e( 'ALL of these tags:', 'syncly' ); ?></strong><br>
						<?php esc_html_e( 'User needs ALL selected tags to access.', 'syncly' ); ?>
					</p>
					<p><strong><?php esc_html_e( 'Does NOT have these tags:', 'syncly' ); ?></strong><br>
						<?php esc_html_e( 'User must NOT have any of the selected tags to access.', 'syncly' ); ?>
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
		update_post_meta( $post_id, \Syncly\Sync\TagManager::scoped_meta_key( '_ghl_required_tags' ), $required_tags );

		// Save redirect URL
		$redirect_url = isset( $_POST['ghl_redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['ghl_redirect_url'] ) ) : '';
		update_post_meta( $post_id, '_ghl_redirect_url', $redirect_url );
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