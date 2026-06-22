<?php
declare(strict_types=1);

namespace GHL_CRM\Admin\Columns;

use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Columns
 *
 * Adds custom columns to the WordPress users list table in admin
 *
 * @package    GHL_CRM_Integration
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
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$location_id      = $settings_manager->get_setting( 'location_id' ) ?: $settings_manager->get_setting( 'oauth_location_id' );
		$contact_id       = \GHL_CRM\Sync\TagManager::get_instance()->get_user_contact_id( $user_id, $location_id );

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
		$settings_manager   = \GHL_CRM\Core\SettingsManager::get_instance();
		$location_id        = $settings_manager->get_setting( 'location_id' ) ?: $settings_manager->get_setting( 'oauth_location_id' );
		$contact_id         = \GHL_CRM\Sync\TagManager::get_instance()->get_user_contact_id( $user_id, $location_id );

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

		$orderby = $query->get( 'orderby' );

		switch ( $orderby ) {
			case 'ghl_contact_id':
				$query->set( 'meta_key', \GHL_CRM\Sync\TagManager::get_instance()->get_user_contact_id_meta_key() );
				$query->set( 'orderby', 'meta_value' );
				break;

			case 'ghl_sync_status':
				$query->set( 'meta_key', '_ghl_last_sync' );
				$query->set( 'orderby', 'meta_value_num' );
				break;
		}
	}

	/**
	 * Add custom styles for columns
	 *
	 * @return void
	 */
	public function add_column_styles(): void {
		wp_register_style( 'syncly-user-columns-inline', false, [], GHL_CRM_VERSION );
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