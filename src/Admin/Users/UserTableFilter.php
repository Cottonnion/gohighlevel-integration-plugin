<?php
declare(strict_types=1);

namespace Syncly\Admin\Users;

use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Table Filter
 *
 * Adds a GHL tag dropdown to the WordPress users list table so that admins
 * can filter users by their location-scoped GHL contact tags.
 *
 * @package    Syncly
 * @subpackage Admin/Users
 */
class UserTableFilter {

	/**
	 * Query-string key used to pass the selected tag ID.
	 */
	private const FILTER_KEY = 'ghl_tag';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings manager reference.
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Tag manager reference.
	 *
	 * @var TagManager
	 */
	private TagManager $tag_manager;

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
	 * Register admin hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( is_network_admin() ) {
			return;
		}

		if ( ! $this->settings_manager->is_connection_verified() ) {
			return;
		}

		// Render the tag dropdown above the users table.
		add_action( 'restrict_manage_users', [ $this, 'render_tag_filter' ] );

		// Apply the filter to the user query.
		add_action( 'pre_get_users', [ $this, 'apply_tag_filter' ] );
	}

	/**
	 * Render the GHL tag filter dropdown.
	 *
	 * Outputs a <select> element populated with all tags for the active
	 * location so the admin can filter the users list.
	 *
	 * @return void
	 */
	public function render_tag_filter(): void {
		$tags = $this->tag_manager->get_tags();

		if ( empty( $tags ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter, nonce not required.
		$selected = isset( $_GET[ self::FILTER_KEY ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::FILTER_KEY ] ) ) : '';

		echo '<label class="screen-reader-text" for="ghl-tag-filter">'
			. esc_html__( 'Filter by GHL Tag', 'syncly' )
			. '</label>';

		echo '<select id="ghl-tag-filter" name="' . esc_attr( self::FILTER_KEY ) . '">';
		echo '<option value="">' . esc_html__( 'All GHL Tags', 'syncly' ) . '</option>';

		foreach ( $tags as $tag ) {
			$tag_id   = isset( $tag['id'] ) ? (string) $tag['id'] : '';
			$tag_name = isset( $tag['name'] ) ? (string) $tag['name'] : '';

			if ( '' === $tag_id ) {
				continue;
			}

			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $tag_id ),
				selected( $selected, $tag_id, false ),
				esc_html( $tag_name )
			);
		}

		echo '</select>';
	}

	/**
	 * Apply the tag filter to the WP_User_Query.
	 *
	 * When a tag ID is present in the request, the query is restricted to
	 * users whose location-scoped tag meta contains that tag ID.
	 *
	 * @param \WP_User_Query $query Current user query.
	 * @return void
	 */
	public function apply_tag_filter( \WP_User_Query $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		if ( empty( $_GET[ self::FILTER_KEY ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$tag_id = sanitize_text_field( wp_unslash( $_GET[ self::FILTER_KEY ] ) );

		if ( '' === $tag_id ) {
			return;
		}

		$tags_meta_key = $this->tag_manager->get_user_tags_meta_key();

		$existing_meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $existing_meta_query ) ) {
			$existing_meta_query = [];
		}

		$existing_meta_query[] = [
			'key'     => $tags_meta_key,
			'value'   => $tag_id,
			'compare' => 'LIKE',
		];

		$query->set( 'meta_query', $existing_meta_query );
	}

	/**
	 * Initialize the filter.
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}
