<?php
declare(strict_types=1);

namespace Syncly\Admin\Columns;

use Syncly\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Columns
 *
 * Adds custom columns to the WordPress users list table in admin
 *
 * @package    Syncly
 * @subpackage Admin/Columns
 */
class UserColumns {

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

		// Add custom columns to users table
		add_filter( 'manage_users_columns', [ $this, 'add_custom_columns' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'render_custom_column' ], 10, 3 );
		add_filter( 'manage_users_sortable_columns', [ $this, 'make_columns_sortable' ] );

		// Handle sorting
		add_action( 'pre_get_users', [ $this, 'handle_column_sorting' ] );

		// Add and apply active-location tag filter on users table.
		add_action( 'restrict_manage_users', [ $this, 'render_tag_filter' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_tag_filter_assets' ] );
		add_action( 'admin_footer-users.php', [ $this, 'render_tag_filter_init_script' ] );

		// Add custom styles for columns
		add_action( 'admin_head-users.php', [ $this, 'add_column_styles' ] );
	}

	/**
	 * Add custom columns to users table
	 *
	 * @param array $columns Existing columns
	 * @return array Modified columns
	 */
	public function add_custom_columns( array $columns ): array {
		// Insert GHL columns before the "Posts" column (or at the end if it doesn't exist)
		$new_columns = [];

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			// Add GHL columns after email column
			if ( 'email' === $key ) {
				$new_columns['ghl_contact_id']  = __( 'GHL Contact ID', 'syncly' );
				$new_columns['ghl_sync_status'] = __( 'GHL Sync Status', 'syncly' );
			}
		}

		// If email column doesn't exist, add at the end
		if ( ! isset( $columns['email'] ) ) {
			$new_columns['ghl_contact_id']  = __( 'GHL Contact ID', 'syncly' );
			$new_columns['ghl_sync_status'] = __( 'GHL Sync Status', 'syncly' );
		}

		return $new_columns;
	}

	/**
	 * Render custom column content
	 *
	 * @param string $output      Custom column output (empty by default)
	 * @param string $column_name Column name
	 * @param int    $user_id     User ID
	 * @return string Column content
	 */
	public function render_custom_column( string $output, string $column_name, int $user_id ): string {
		switch ( $column_name ) {
			case 'ghl_contact_id':
				return $this->render_contact_id_column( $user_id );

			case 'ghl_sync_status':
				return $this->render_sync_status_column( $user_id );

			default:
				return $output;
		}
	}

	/**
	 * Render GHL Contact ID column
	 *
	 * @param int $user_id User ID
	 * @return string Column HTML
	 */
	private function render_contact_id_column( int $user_id ): string {
		$settings_manager = \Syncly\Core\SettingsManager::get_instance();
		$location_id      = $settings_manager->get_setting( 'location_id' ) ?: $settings_manager->get_setting( 'oauth_location_id' );
		$contact_id       = \Syncly\Sync\TagManager::get_instance()->get_user_contact_id( $user_id, $location_id );

		if ( empty( $contact_id ) ) {
			return '<span class="ghl-no-contact">—</span>';
		}

		// Get location ID and white label domain from settings
		$settings           = $this->settings_manager->get_settings_array();
		$location_id        = $settings['location_id'] ?? '';
		$white_label_domain = $settings['ghl_white_label_domain'] ?? '';

		// Determine base domain (white label or default)
		$base_domain = ! empty( $white_label_domain ) ? rtrim( $white_label_domain, '/' ) : 'https://app.leadconnectorhq.com';

		if ( ! empty( $location_id ) ) {
			// Build link to GHL contact
			$ghl_url = sprintf(
				'%s/v2/location/%s/contacts/detail/%s',
				$base_domain,
				esc_attr( $location_id ),
				esc_attr( $contact_id )
			);

			return sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="ghl-contact-link" title="%s">
					<code>%s</code>
					<span class="dashicons dashicons-external" style="font-size: 12px; vertical-align: middle;"></span>
				</a>',
				esc_url( $ghl_url ),
				esc_attr__( 'View in GoHighLevel', 'syncly' ),
				esc_html( substr( $contact_id, 0, 8 ) . '...' )
			);
		}

		return sprintf( '<code>%s</code>', esc_html( substr( $contact_id, 0, 8 ) . '...' ) );
	}   /**
		 * Render sync status column
		 *
		 * @param int $user_id User ID
		 * @return string Column HTML
		 */
	private function render_sync_status_column( int $user_id ): string {
		$synced_on_register = get_user_meta( $user_id, '_ghl_synced_on_register', true );
		$last_sync_time     = get_user_meta( $user_id, '_ghl_last_sync', true );
		$settings_manager   = \Syncly\Core\SettingsManager::get_instance();
		$location_id        = $settings_manager->get_setting( 'location_id' ) ?: $settings_manager->get_setting( 'oauth_location_id' );
		$contact_id         = \Syncly\Sync\TagManager::get_instance()->get_user_contact_id( $user_id, $location_id );

		if ( empty( $contact_id ) ) {
			// Not synced yet
			return '<span class="ghl-sync-status ghl-sync-never" title="' . esc_attr__( 'Never synced to GoHighLevel', 'syncly' ) . '">
				<span class="dashicons dashicons-warning" style="color: #dba617;"></span> ' .
				esc_html__( 'Not Synced', 'syncly' ) .
				'</span>';
		}

		// Synced - show last sync time
		$sync_time = $last_sync_time ?: $synced_on_register;

		if ( $sync_time ) {
			$time_diff = human_time_diff( (int) $sync_time, current_time( 'timestamp' ) );

			return sprintf(
				'<span class="ghl-sync-status ghl-sync-success" title="%s">
					<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> %s
				</span>',
				esc_attr(
					sprintf(
					/* translators: %s: Time difference */
						__( 'Last synced: %s ago', 'syncly' ),
						$time_diff
					)
				),
				esc_html(
					sprintf(
					/* translators: %s: Time difference */
						__( '%s ago', 'syncly' ),
						$time_diff
					)
				)
			);
		}

		return '<span class="ghl-sync-status ghl-sync-success">
			<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' .
			esc_html__( 'Synced', 'syncly' ) .
			'</span>';
	}

	/**
	 * Make custom columns sortable
	 *
	 * @param array $columns Sortable columns
	 * @return array Modified sortable columns
	 */
	public function make_columns_sortable( array $columns ): array {
		$columns['ghl_contact_id']  = 'ghl_contact_id';
		$columns['ghl_sync_status'] = 'ghl_sync_status';

		return $columns;
	}

	/**
	 * Handle column sorting
	 *
	 * @param \WP_User_Query $query User query object
	 * @return void
	 */
	public function handle_column_sorting( \WP_User_Query $query ): void {
		// Only in admin area
		if ( ! is_admin() ) {
			return;
		}

		$this->apply_tag_filter( $query );

		$orderby = $query->get( 'orderby' );

		switch ( $orderby ) {
			case 'ghl_contact_id':
				$query->set( 'meta_key', \Syncly\Sync\TagManager::get_instance()->get_user_contact_id_meta_key() );
				$query->set( 'orderby', 'meta_value' );
				break;

			case 'ghl_sync_status':
				$query->set( 'meta_key', '_ghl_last_sync' );
				$query->set( 'orderby', 'meta_value_num' );
				break;
		}
	}

	/**
	 * Render location-scoped GHL tags filter in users table.
	 *
	 * @param string $which Position of filter controls. 'top' or 'bottom'.
	 * @return void
	 */
	public function render_tag_filter( string $which ): void {
		if ( 'top' !== $which || ! $this->is_users_screen() ) {
			return;
		}

		$location_id = $this->get_active_location_id();
		if ( '' === $location_id ) {
			return;
		}

		$selected_tag_ids = $this->get_selected_filter_tag_ids();
		$tags             = \Syncly\Sync\TagManager::get_instance()->get_tags();

		if ( empty( $tags ) ) {
			return;
		}

		usort(
			$tags,
			static function ( array $left, array $right ): int {
				$left_name  = isset( $left['name'] ) ? (string) $left['name'] : '';
				$right_name = isset( $right['name'] ) ? (string) $right['name'] : '';

				return strcasecmp( $left_name, $right_name );
			}
		);

		echo '<label class="screen-reader-text" for="syncly-ghl-tags-filter">' . esc_html__( 'Filter by GoHighLevel tags', 'syncly' ) . '</label>';
		echo '<select name="syncly_ghl_tags[]" id="syncly-ghl-tags-filter" multiple="multiple" data-placeholder="' . esc_attr__( 'Tags', 'syncly' ) . '" style="max-width: 280px; min-width: 220px; margin-right: 6px;">';

		foreach ( $tags as $tag ) {
			$tag_id   = isset( $tag['id'] ) ? (string) $tag['id'] : '';
			$tag_name = isset( $tag['name'] ) ? (string) $tag['name'] : '';

			if ( '' === $tag_id || '' === $tag_name ) {
				continue;
			}

			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $tag_id ),
				selected( in_array( $tag_id, $selected_tag_ids, true ), true, false ),
				esc_html( $tag_name )
			);
		}

		echo '</select>';
	}

	/**
	 * Apply location-scoped GHL tags filter to the users query.
	 *
	 * @param \WP_User_Query $query User query object.
	 * @return void
	 */
	private function apply_tag_filter( \WP_User_Query $query ): void {
		if ( ! $this->is_users_screen() ) {
			return;
		}

		$selected_tag_ids = $this->get_selected_filter_tag_ids();
		if ( empty( $selected_tag_ids ) ) {
			return;
		}

		$location_id = $this->get_active_location_id();
		if ( '' === $location_id ) {
			return;
		}

		$meta_key       = \Syncly\Sync\TagManager::get_instance()->get_user_tags_meta_key( $location_id );
		$meta_query     = (array) $query->get( 'meta_query' );
		$tag_conditions = [];

		foreach ( $selected_tag_ids as $tag_id ) {
			$tag_conditions[] = [
				'key'     => $meta_key,
				'value'   => '"' . $tag_id . '"',
				'compare' => 'LIKE',
			];
		}

		if ( 1 === count( $tag_conditions ) ) {
			$meta_query[] = $tag_conditions[0];
		} else {
			$meta_query[] = array_merge(
				[ 'relation' => 'AND' ],
				$tag_conditions
			);
		}

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Get currently selected tag IDs from users table filter request.
	 *
	 * @return array<int, string>
	 */
	private function get_selected_filter_tag_ids(): array {
		$raw_selected = $_GET['syncly_ghl_tags'] ?? []; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list table filtering.

		if ( is_string( $raw_selected ) ) {
			$raw_selected = explode( ',', $raw_selected );
		}

		if ( ! is_array( $raw_selected ) ) {
			return [];
		}

		$selected = array_map(
			static function ( $tag_id ): string {
				return sanitize_text_field( wp_unslash( (string) $tag_id ) );
			},
			$raw_selected
		);

		$selected = array_values(
			array_unique(
				array_filter(
					$selected,
					static function ( string $tag_id ): bool {
						return '' !== $tag_id;
					}
				)
			)
		);

		return $selected;
	}

	/**
	 * Get active location ID using the plugin's existing fallback logic.
	 *
	 * @return string
	 */
	private function get_active_location_id(): string {
		return (string) ( $this->settings_manager->get_setting( 'location_id' ) ?: $this->settings_manager->get_setting( 'oauth_location_id' ) );
	}

	/**
	 * Determine if the current admin screen is the users list table.
	 *
	 * @return bool
	 */
	private function is_users_screen(): bool {
		global $pagenow;

		return 'users.php' === $pagenow;
	}

	/**
	 * Enqueue Select2 assets for the users tags filter.
	 *
	 * @return void
	 */
	public function enqueue_tag_filter_assets(): void {
		if ( ! $this->is_users_screen() ) {
			return;
		}

		wp_enqueue_style( 'syncly-select2-css' );
		wp_enqueue_script( 'syncly-select2' );
	}

	/**
	 * Initialize Select2 for the users tags filter.
	 *
	 * @return void
	 */
	public function render_tag_filter_init_script(): void {
		if ( ! $this->is_users_screen() ) {
			return;
		}

		?>
		<script>
			jQuery(function($) {
				var $filter = $('#syncly-ghl-tags-filter');

				if (!$filter.length || typeof $filter.select2 !== 'function') {
					return;
				}

				$filter.select2({
					placeholder: $filter.data('placeholder') || 'Tags',
					allowClear: true,
					closeOnSelect: false,
					width: '220px'
				});
			});
		</script>
		<?php
	}

	/**
	 * Add custom styles for columns
	 *
	 * @return void
	 */
	public function add_column_styles(): void {
		wp_register_style( 'syncly-user-columns-inline', false, [], SYNCLY_VERSION );
		wp_enqueue_style( 'syncly-user-columns-inline' );
		wp_add_inline_style(
			'syncly-user-columns-inline',
			'
			.column-ghl_contact_id {
				width: 120px;
			}
			.column-ghl_sync_status {
				width: 140px;
			}
			.ghl-contact-link {
				text-decoration: none;
			}
			.ghl-contact-link:hover code {
				color: #0073aa;
			}
			.ghl-sync-status {
				display: inline-flex;
				align-items: center;
				gap: 4px;
				font-size: 12px;
			}
			.ghl-sync-status .dashicons {
				width: 16px;
				height: 16px;
				font-size: 16px;
			}
			.ghl-column-empty {
				color: #a0a5aa;
			}
			#syncly-ghl-tags-filter {
				vertical-align: top;
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