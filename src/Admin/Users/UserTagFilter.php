<?php
declare(strict_types=1);

namespace Syncly\Admin\Users;

use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Tag Filter
 *
 * Adds a GHL tag dropdown to the WordPress users list table so administrators
 * can filter users by the tags stored in the active location.
 *
 * @package    Syncly
 * @subpackage Admin/Users
 */
class UserTagFilter {

	/**
	 * Settings Manager.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Tag Manager.
	 *
	 * @var TagManager
	 */
	private TagManager $tag_manager;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
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
	 * Private constructor.
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
		$this->tag_manager      = TagManager::get_instance();
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! $this->settings_manager->is_connection_verified() ) {
			return;
		}

		add_action( 'restrict_manage_users', [ $this, 'render_tag_filter_dropdown' ] );
		add_action( 'pre_get_users', [ $this, 'apply_tag_filter' ] );
		add_action( 'admin_head-users.php', [ $this, 'add_filter_styles' ] );
	}

	/**
	 * Render the GHL tag filter dropdown above the users table.
	 *
	 * @return void
	 */
	public function render_tag_filter_dropdown(): void {
		$tags = $this->tag_manager->get_tags();

		if ( empty( $tags ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$selected_tag = isset( $_GET['ghl_tag_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['ghl_tag_filter'] ) ) : '';

		echo '<label class="screen-reader-text" for="ghl-tag-filter">' . esc_html__( 'Filter by GHL Tag', 'syncly' ) . '</label>';
		echo '<select id="ghl-tag-filter" name="ghl_tag_filter" class="syncly-tag-filter-select">';
		echo '<option value="">' . esc_html__( '— GHL Tag —', 'syncly' ) . '</option>';

		foreach ( $tags as $tag ) {
			if ( empty( $tag['id'] ) || empty( $tag['name'] ) ) {
				continue;
			}
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $tag['id'] ),
				selected( $selected_tag, $tag['id'], false ),
				esc_html( $tag['name'] )
			);
		}

		echo '</select>';
	}

	/**
	 * Filter the user query when a GHL tag is selected.
	 *
	 * Only modifies the query on the admin users screen.
	 *
	 * @param \WP_User_Query $query The user query object.
	 * @return void
	 */
	public function apply_tag_filter( \WP_User_Query $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		// Only act on the main users list screen.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'users' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$tag_id = isset( $_GET['ghl_tag_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['ghl_tag_filter'] ) ) : '';

		if ( empty( $tag_id ) ) {
			return;
		}

		$location_id = $this->settings_manager->get_setting( 'location_id' );
		$meta_key    = $this->tag_manager->get_user_tags_meta_key( $location_id ?: null );

		$existing_meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $existing_meta_query ) ) {
			$existing_meta_query = [];
		}

		$existing_meta_query[] = [
			'key'     => $meta_key,
			'value'   => '"' . $tag_id . '"',
			'compare' => 'LIKE',
		];

		$query->set( 'meta_query', $existing_meta_query );
	}

	/**
	 * Add inline styles for the tag filter dropdown.
	 *
	 * @return void
	 */
	public function add_filter_styles(): void {
		wp_register_style( 'syncly-user-tag-filter-inline', false, [], SYNCLY_VERSION );
		wp_enqueue_style( 'syncly-user-tag-filter-inline' );
		wp_add_inline_style(
			'syncly-user-tag-filter-inline',
			'
			.syncly-tag-filter-select {
				min-width: 150px;
			}
			'
		);
	}

	/**
	 * Initialize (called by Loader).
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}
