<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conditional Menus Manager
 *
 * Controls WordPress menu item visibility based on user's GHL contact tags.
 * Uses tag IDs for storage and converts to names for display/comparison.
 *
 * @package    GHL_CRM_Integration
 * @subpackage Core
 * @since      1.0.0
 */
class ConditionalMenus {
	/**
	 * Meta key for visibility rule
	 */
	private const META_VISIBILITY_RULE = '_ghl_visibility_rule';

	/**
	 * Meta key for required tag IDs
	 */
	private const META_REQUIRED_TAGS = '_ghl_required_tags';

	/**
	 * Meta key for user's GHL tags
	 */
	private const META_USER_TAGS = '_ghl_contact_tags';

	/**
	 * Transient key pattern for tag cache
	 */
	private const TRANSIENT_TAG_PATTERN = 'ghl_tags_%s_site_%d';

	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get class instance | singleton pattern
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
	 * Private constructor to prevent direct creation
	 */
	private function __construct() {
		// Constructor is empty, hooks are initialized via init()
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function initialize_hooks(): void {
		// Add menu item meta box
		add_action( 'admin_init', [ $this, 'add_menu_meta_boxes' ] );

		// Save menu item settings
		add_action( 'wp_update_nav_menu_item', [ $this, 'save_menu_item_settings' ], 10, 2 );

		// Filter menu items on frontend
		add_filter( 'wp_get_nav_menu_items', [ $this, 'filter_menu_items' ], 10, 3 );

		// Add custom walker edit fields
		add_filter( 'wp_setup_nav_menu_item', [ $this, 'add_custom_nav_fields' ] );
		
		// Render custom fields in menu editor
		add_action( 'wp_nav_menu_item_custom_fields', [ $this, 'render_menu_item_fields' ], 10, 4 );

		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Add menu item meta boxes
	 *
	 * @return void
	 */
	public function add_menu_meta_boxes(): void {
		add_meta_box(
			'ghl_menu_visibility',
			__( 'GHL Tag Visibility', 'ghl-crm-integration' ),
			[ $this, 'render_menu_meta_box' ],
			'nav-menus',
			'side',
			'default'
		);
	}

	/**
	 * Render menu meta box
	 *
	 * @param object $item Menu item object
	 * @return void
	 */
	public function render_menu_meta_box( $item ): void {
		?>
		<div class="ghl-menu-visibility-info">
			<p>
				<?php esc_html_e( 'Configure menu item visibility based on user tags when editing menu items.', 'ghl-crm-integration' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'How it works:', 'ghl-crm-integration' ); ?></strong>
			</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><?php esc_html_e( 'Expand any menu item below', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Look for the "GHL Tag Visibility" section', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Choose visibility rules based on user tags', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Menu items will show/hide automatically', 'ghl-crm-integration' ); ?></li>
			</ul>
			<p style="margin-top: 15px;">
				<em><?php esc_html_e( 'Logged-out users will see menu items set to "Show to Everyone" or "Hide from Logged-In Users".', 'ghl-crm-integration' ); ?></em>
			</p>
		</div>
		<?php
	}

	/**
	 * Add custom fields to nav menu items
	 *
	 * @param object $menu_item Menu item object.
	 * @return object Modified menu item.
	 */
	public function add_custom_nav_fields( $menu_item ) {
		$menu_item->ghl_visibility_rule = get_post_meta( $menu_item->ID, self::META_VISIBILITY_RULE, true );
		$menu_item->ghl_required_tags   = get_post_meta( $menu_item->ID, self::META_REQUIRED_TAGS, true );

		if ( ! is_array( $menu_item->ghl_required_tags ) ) {
			$menu_item->ghl_required_tags = [];
		}

		return $menu_item;
	}

	/**
	 * Render custom fields in menu item editor
	 *
	 * @param int    $item_id Menu item ID.
	 * @param object $item    Menu item object.
	 * @param int    $depth   Depth of menu item.
	 * @param array  $args    Menu item args.
	 * @return void
	 */
	public function render_menu_item_fields( $item_id, $item, $depth, $args ): void {
		$visibility_rule  = get_post_meta( $item_id, self::META_VISIBILITY_RULE, true );
		$required_tag_ids = get_post_meta( $item_id, self::META_REQUIRED_TAGS, true );

		// Ensure tag IDs is a clean indexed array
		$required_tag_ids = is_array( $required_tag_ids ) 
			? array_values( array_filter( $required_tag_ids ) ) 
			: [];
		
		// Get tag names for JavaScript initialization
		$tag_names_map   = $this->get_tag_names_map( $required_tag_ids );
		$saved_tags_json = esc_attr( wp_json_encode( $required_tag_ids ) );
		$tag_names_json  = esc_attr( wp_json_encode( $tag_names_map ) );
		?>
		<div class="ghl-menu-visibility-section" data-saved-tags='<?php echo $saved_tags_json; ?>' data-tag-names='<?php echo $tag_names_json; ?>'>
			<p class="description description-wide">
				<label for="ghl-visibility-rule-<?php echo esc_attr( $item_id ); ?>">
					<strong><?php esc_html_e( 'GHL Tag Visibility', 'ghl-crm-integration' ); ?></strong>
					<br>
					<select id="ghl-visibility-rule-<?php echo esc_attr( $item_id ); ?>" 
							name="menu-item-ghl-visibility-rule[<?php echo esc_attr( $item_id ); ?>]" 
							class="widefat ghl-visibility-rule-select">
						<option value=""><?php esc_html_e( 'Show to Everyone (Default)', 'ghl-crm-integration' ); ?></option>
						<option value="logged_in" <?php selected( $visibility_rule, 'logged_in' ); ?>><?php esc_html_e( 'Show to Logged-In Users Only', 'ghl-crm-integration' ); ?></option>
						<option value="logged_out" <?php selected( $visibility_rule, 'logged_out' ); ?>><?php esc_html_e( 'Show to Logged-Out Users Only', 'ghl-crm-integration' ); ?></option>
						<option value="has_any_tag" <?php selected( $visibility_rule, 'has_any_tag' ); ?>><?php esc_html_e( 'Has ANY of these tags', 'ghl-crm-integration' ); ?></option>
						<option value="has_all_tags" <?php selected( $visibility_rule, 'has_all_tags' ); ?>><?php esc_html_e( 'Has ALL of these tags', 'ghl-crm-integration' ); ?></option>
						<option value="not_has_tags" <?php selected( $visibility_rule, 'not_has_tags' ); ?>><?php esc_html_e( 'Does NOT have these tags', 'ghl-crm-integration' ); ?></option>
					</select>
				</label>
			</p>
			<p class="description description-wide ghl-tags-field" id="ghl-menu-tags-field-<?php echo esc_attr( $item_id ); ?>" style="display: none;">
				<label for="ghl-tags-<?php echo esc_attr( $item_id ); ?>">
					<?php esc_html_e( 'Required Tags', 'ghl-crm-integration' ); ?>
					<br>
					<select id="ghl-tags-<?php echo esc_attr( $item_id ); ?>" 
							name="menu-item-ghl-tags[<?php echo esc_attr( $item_id ); ?>][]" 
							class="widefat ghl-tags-select" 
							multiple="multiple">
					</select>
					<span class="description"><?php esc_html_e( 'Type to search or add new tags', 'ghl-crm-integration' ); ?></span>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts for menu editor
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'nav-menus.php' !== $hook ) {
			return;
		}

		// Enqueue Select2 (local files registered by AssetsManager)
		wp_enqueue_style( 'ghl-crm-select2-css' );
		wp_enqueue_script( 'ghl-crm-select2' );

		// Enqueue global CSS
		wp_enqueue_style( 'ghl-crm-globals-css' );

		// Enqueue custom menu editor script
		wp_enqueue_script(
			'ghl-crm-menu-editor',
			GHL_CRM_URL . 'assets/admin/js/menu-editor.js',
			[ 'jquery', 'ghl-crm-select2' ],
			GHL_CRM_VERSION,
			true
		);

		// Localize script with AJAX settings
		wp_localize_script(
			'ghl-crm-menu-editor',
			'ghlMenuEditor',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
				'strings' => [
					'searchTags' => __( 'Search or add tags...', 'ghl-crm-integration' ),
				],
			]
		);

		// Add inline CSS for menu editor
		wp_add_inline_style( 'ghl-crm-select2-css', $this->get_menu_editor_styles() );
	}

	/**
	 * Get menu editor inline CSS
	 *
	 * @return string CSS styles.
	 */
	private function get_menu_editor_styles(): string {
		return '
			.ghl-menu-visibility-section {
				padding: 10px 15px;
				background: #f9f9f9;
				border: 1px solid #ddd;
				border-radius: 3px;
				margin: 10px 0;
			}
			.ghl-menu-visibility-section label {
				display: block;
				margin-bottom: 8px;
				font-weight: 600;
			}
			.ghl-menu-visibility-section select {
				width: 100%;
				margin-bottom: 10px;
			}
			.ghl-menu-visibility-section .description {
				font-size: 12px;
				color: #666;
				font-style: italic;
				margin-top: 5px;
			}
			.ghl-menu-visibility-info {
				padding: 15px;
			}
			.ghl-menu-visibility-info ul {
				margin: 10px 0;
			}
			.ghl-menu-visibility-info li {
				margin-bottom: 5px;
				line-height: 1.5;
			}
		';
	}

	/**
	 * Save menu item settings
	 *
	 * @param int $menu_id         Menu ID.
	 * @param int $menu_item_db_id Menu item database ID.
	 * @return void
	 */
	public function save_menu_item_settings( int $menu_id, int $menu_item_db_id ): void {
		// Security checks
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['update-nav-menu-nonce'] ) || 
			 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['update-nav-menu-nonce'] ) ), 'update-nav_menu' ) ) {
			return;
		}

		// Save visibility rule
		if ( isset( $_POST['menu-item-ghl-visibility-rule'][ $menu_item_db_id ] ) ) {
			$rule = sanitize_text_field( wp_unslash( $_POST['menu-item-ghl-visibility-rule'][ $menu_item_db_id ] ) );
			update_post_meta( $menu_item_db_id, self::META_VISIBILITY_RULE, $rule );
		} else {
			delete_post_meta( $menu_item_db_id, self::META_VISIBILITY_RULE );
		}

		// Save required tag IDs (sanitize, de-duplicate, re-index)
		if ( isset( $_POST['menu-item-ghl-tags'][ $menu_item_db_id ] ) ) {
			$raw_tags = wp_unslash( $_POST['menu-item-ghl-tags'][ $menu_item_db_id ] );
			$tag_ids  = is_array( $raw_tags ) ? array_map( 'sanitize_text_field', $raw_tags ) : [];
			$tag_ids  = array_values( array_unique( array_filter( $tag_ids ) ) );
			
			update_post_meta( $menu_item_db_id, self::META_REQUIRED_TAGS, $tag_ids );
		} else {
			delete_post_meta( $menu_item_db_id, self::META_REQUIRED_TAGS );
		}
	}

	/**
	 * Filter menu items based on visibility rules
	 *
	 * @param array  $items Menu items
	 * @param object $menu  Menu object
	 * @param array  $args  Menu arguments
	 * @return array Filtered menu items
	 */
	public function filter_menu_items( $items, $menu, $args ) {
		if ( is_admin() ) {
			return $items; // Don't filter in admin
		}

		$current_user_id = get_current_user_id();
		$user_tags       = [];

		// Get current user's tags if logged in
		if ( $current_user_id > 0 ) {
			$user_tags = $this->get_user_tags( $current_user_id );
		}

		$filtered_items = [];

		foreach ( $items as $item ) {
			if ( $this->should_display_menu_item( $item, $current_user_id, $user_tags ) ) {
				$filtered_items[] = $item;
			}
		}

		return $filtered_items;
	}

	/**
	 * Check if menu item should be displayed
	 *
	 * @param object $item      Menu item.
	 * @param int    $user_id   Current user ID (0 if not logged in).
	 * @param array  $user_tags User's GHL tags (lowercase).
	 * @return bool True if should display.
	 */
	private function should_display_menu_item( $item, int $user_id, array $user_tags ): bool {
		$rule = get_post_meta( $item->ID, self::META_VISIBILITY_RULE, true );

		// No rule set - show to everyone (default behavior)
		if ( empty( $rule ) ) {
			return true;
		}

		$is_logged_in = $user_id > 0;

		// Handle login-based rules (no tags needed)
		if ( 'logged_in' === $rule ) {
			return $is_logged_in;
		}

		if ( 'logged_out' === $rule ) {
			return ! $is_logged_in;
		}

		// Tag-based rules require tag processing
		$required_tag_ids = get_post_meta( $item->ID, self::META_REQUIRED_TAGS, true );
		$required_tag_ids = is_array( $required_tag_ids ) ? $required_tag_ids : [];

		// Convert tag IDs to names and normalize to lowercase
		$required_tags = array_map( 'strtolower', $this->convert_tag_ids_to_names( $required_tag_ids ) );

		// Handle tag-based visibility rules
		switch ( $rule ) {
			case 'has_any_tag':
				// User must be logged in AND have at least one tag
				return $is_logged_in && ( empty( $required_tags ) || $this->user_has_any_tag( $user_tags, $required_tags ) );

			case 'has_all_tags':
				// User must be logged in AND have all required tags
				return $is_logged_in && ( empty( $required_tags ) || $this->user_has_all_tags( $user_tags, $required_tags ) );

			case 'not_has_tags':
				// Show to logged-out users OR users without these tags
				return ! $is_logged_in || empty( $required_tags ) || $this->user_not_has_tags( $user_tags, $required_tags );

			default:
				// Unknown rule, show by default (fail-open for safety)
				return true;
		}
	}

	/**
	 * Get user's GHL contact tags
	 *
	 * @param int $user_id User ID.
	 * @return array Array of tag names (lowercase).
	 */
	private function get_user_tags( int $user_id ): array {
		$tags = get_user_meta( $user_id, self::META_USER_TAGS, true );

		if ( ! is_array( $tags ) ) {
			return [];
		}

		return array_map( 'strtolower', $tags );
	}

	/**
	 * Get cached tags from transient
	 *
	 * @return array Array of tag objects from GHL API.
	 */
	private function get_cached_tags(): array {
		$settings    = get_option( 'ghl_crm_settings', [] );
		$location_id = $settings['location_id'] ?? '';

		if ( empty( $location_id ) ) {
			return [];
		}

		$site_id       = get_current_blog_id();
		$transient_key = sprintf( self::TRANSIENT_TAG_PATTERN, $location_id, $site_id );
		$cached_tags   = get_transient( $transient_key );

		return is_array( $cached_tags ) ? $cached_tags : [];
	}

	/**
	 * Convert tag IDs to tag names
	 *
	 * @param array $tag_ids Array of GHL tag IDs.
	 * @return array Array of tag names (fallback to ID if not found).
	 */
	private function convert_tag_ids_to_names( array $tag_ids ): array {
		if ( empty( $tag_ids ) ) {
			return [];
		}

		$all_tags = $this->get_cached_tags();
		if ( empty( $all_tags ) ) {
			// No cache available, return IDs as fallback
			return $tag_ids;
		}

		// Build ID => name map
		$tag_map = [];
		foreach ( $all_tags as $tag ) {
			if ( isset( $tag['id'], $tag['name'] ) ) {
				$tag_map[ $tag['id'] ] = $tag['name'];
			}
		}

		// Convert IDs to names (use ID as fallback if name not found)
		$tag_names = [];
		foreach ( $tag_ids as $tag_id ) {
			$tag_names[] = $tag_map[ $tag_id ] ?? $tag_id;
		}

		return $tag_names;
	}

	/**
	 * Get tag names map for given tag IDs (for JavaScript initialization)
	 *
	 * @param array $tag_ids Array of GHL tag IDs.
	 * @return array Associative array of tag ID => tag name.
	 */
	private function get_tag_names_map( array $tag_ids ): array {
		if ( empty( $tag_ids ) ) {
			return [];
		}

		$all_tags = $this->get_cached_tags();
		if ( empty( $all_tags ) ) {
			return [];
		}

		// Build filtered map for requested IDs only
		$tag_names_map = [];
		foreach ( $all_tags as $tag ) {
			if ( isset( $tag['id'], $tag['name'] ) && in_array( $tag['id'], $tag_ids, true ) ) {
				$tag_names_map[ $tag['id'] ] = $tag['name'];
			}
		}

		return $tag_names_map;
	}

	/**
	 * Check if user has ANY of the required tags
	 *
	 * @param array $user_tags     User's tags (lowercase).
	 * @param array $required_tags Required tags (lowercase).
	 * @return bool True if user has at least one matching tag.
	 */
	private function user_has_any_tag( array $user_tags, array $required_tags ): bool {
		return ! empty( array_intersect( $user_tags, $required_tags ) );
	}

	/**
	 * Check if user has ALL of the required tags
	 *
	 * @param array $user_tags     User's tags (lowercase).
	 * @param array $required_tags Required tags (lowercase).
	 * @return bool True if user has all required tags.
	 */
	private function user_has_all_tags( array $user_tags, array $required_tags ): bool {
		return count( array_intersect( $required_tags, $user_tags ) ) === count( $required_tags );
	}

	/**
	 * Check if user does NOT have any of the specified tags
	 *
	 * @param array $user_tags     User's tags (lowercase).
	 * @param array $required_tags Tags user should NOT have (lowercase).
	 * @return bool True if user has none of these tags.
	 */
	private function user_not_has_tags( array $user_tags, array $required_tags ): bool {
		return empty( array_intersect( $user_tags, $required_tags ) );
	}

	/**
	 * Initialize (called by Loader)
	 *
	 * @return void
	 */
	public static function init(): void {
		$instance = self::get_instance();
		$instance->initialize_hooks();
	}
}
