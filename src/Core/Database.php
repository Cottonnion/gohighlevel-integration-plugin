<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Manager
 *
 * Handles creation and management of custom database tables
 *
 * @package    GHL_CRM_Integration
 * @subpackage Core
 */
class Database {
	/**
	 * Database version
	 *
	 * @var string
	 */
	private const DB_VERSION = '1.4.0';

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
		// Constructor is empty, tables are created via init()
	}

	/**
	 * Initialize database tables
	 *
	 * @return void
	 */
	public function init(): void {
		$settings_manager  = SettingsManager::get_instance();
		$installed_version = $settings_manager->get_option( 'ghl_crm_db_version', '0.0.0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			// For new installations, create tables immediately
			if ( '0.0.0' === $installed_version ) {
				$this->migrate_database( $installed_version );
				$settings_manager->update_option( 'ghl_crm_db_version', self::DB_VERSION );
			} else {
				// For existing installations, show admin notice for manual update
				add_action( 'admin_notices', [ $this, 'show_database_update_notice' ] );
				add_action( 'wp_ajax_ghl_crm_update_database', [ $this, 'handle_database_update' ] );
			}
		}
	}

	/**
	 * Show database update notice
	 *
	 * @return void
	 */
	public function show_database_update_notice(): void {
		$settings_manager  = SettingsManager::get_instance();
		$installed_version = $settings_manager->get_option( 'ghl_crm_db_version', '0.0.0' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="notice notice-warning is-dismissible ghl-crm-db-update-notice">
			<h3><?php esc_html_e( 'GHL CRM Integration - Database Update Required', 'ghl-crm-integration' ); ?></h3>
			<p>
				<?php 
				printf(
					/* translators: 1: current version, 2: new version */
					esc_html__( 'The GHL CRM Integration plugin database needs to be updated from version %1$s to %2$s. This update will:', 'ghl-crm-integration' ),
					'<strong>' . esc_html( $installed_version ) . '</strong>',
					'<strong>' . esc_html( self::DB_VERSION ) . '</strong>'
				);
				?>
			</p>
			<ul style="margin-left: 20px;">
				<li><?php esc_html_e( '✅ Fix sync logging to use the correct database columns', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( '✅ Remove duplicate queue entries that are causing conflicts', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( '✅ Update unique constraints to prevent future duplicates', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( '⚠️  Backup your database before proceeding (recommended)', 'ghl-crm-integration' ); ?></li>
			</ul>
			<p>
				<button type="button" class="button button-primary" id="ghl-crm-update-database" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ghl_crm_update_db' ) ); ?>">
					<?php esc_html_e( 'Update Database Now', 'ghl-crm-integration' ); ?>
				</button>
				<button type="button" class="button button-secondary" onclick="jQuery(this).closest('.notice').slideUp();">
					<?php esc_html_e( 'Remind Me Later', 'ghl-crm-integration' ); ?>
				</button>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('#ghl-crm-update-database').on('click', function() {
				var $button = $(this);
				var $notice = $button.closest('.notice');
				var nonce = $button.data('nonce');
				
				$button.prop('disabled', true).text('<?php esc_attr_e( 'Updating...', 'ghl-crm-integration' ); ?>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ghl_crm_update_database',
						nonce: nonce
					},
					success: function(response) {
						if (response.success) {
							$notice.removeClass('notice-warning').addClass('notice-success');
							$notice.html('<p><strong><?php esc_attr_e( 'Database updated successfully!', 'ghl-crm-integration' ); ?></strong> <?php esc_attr_e( 'Sync logging should now work correctly.', 'ghl-crm-integration' ); ?></p>');
							setTimeout(function() {
								$notice.slideUp();
							}, 3000);
						} else {
							$notice.removeClass('notice-warning').addClass('notice-error');
							$notice.html('<p><strong><?php esc_attr_e( 'Update failed:', 'ghl-crm-integration' ); ?></strong> ' + (response.data || '<?php esc_attr_e( 'Unknown error', 'ghl-crm-integration' ); ?>') + '</p>');
							$button.prop('disabled', false).text('<?php esc_attr_e( 'Retry Update', 'ghl-crm-integration' ); ?>');
						}
					},
					error: function() {
						$notice.removeClass('notice-warning').addClass('notice-error');
						$notice.html('<p><strong><?php esc_attr_e( 'Update failed:', 'ghl-crm-integration' ); ?></strong> <?php esc_attr_e( 'Network error. Please try again.', 'ghl-crm-integration' ); ?></p>');
						$button.prop('disabled', false).text('<?php esc_attr_e( 'Retry Update', 'ghl-crm-integration' ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle AJAX database update request
	 *
	 * @return void
	 */
	public function handle_database_update(): void {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ghl_crm_update_db' ) ) {
			wp_send_json_error( __( 'Security check failed', 'ghl-crm-integration' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'ghl-crm-integration' ) );
		}

		try {
			$settings_manager  = SettingsManager::get_instance();
			$installed_version = $settings_manager->get_option( 'ghl_crm_db_version', '0.0.0' );
			
			// Perform the migration
			$this->migrate_database( $installed_version );
			$settings_manager->update_option( 'ghl_crm_db_version', self::DB_VERSION );
			
			wp_send_json_success( __( 'Database updated successfully', 'ghl-crm-integration' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( __( 'Database update failed: ', 'ghl-crm-integration' ) . $e->getMessage() );
		}
	}

	/**
	 * Migrate database based on current version
	 *
	 * @param string $from_version Current database version
	 * @return void
	 */
	private function migrate_database( string $from_version ): void {
		global $wpdb;

		// Clean up duplicate queue entries and fix constraints for version 1.4.0
		if ( version_compare( $from_version, '1.4.0', '<' ) ) {
			// First, clean up duplicate queue entries
			$queue_table = $wpdb->prefix . 'ghl_sync_queue';
			$log_table = $wpdb->prefix . 'ghl_sync_log';
			
			// Remove duplicate pending entries, keep the oldest one
			$wpdb->query("
				DELETE q1 FROM {$queue_table} q1
				INNER JOIN {$queue_table} q2 
				WHERE q1.id > q2.id 
				AND q1.item_type = q2.item_type 
				AND q1.item_id = q2.item_id 
				AND q1.action = q2.action 
				AND q1.site_id = q2.site_id 
				AND q1.status = 'pending'
			");

			// Drop old constraint if exists
			$wpdb->query("ALTER TABLE {$queue_table} DROP INDEX IF EXISTS unique_pending_item");

			// Migrate sync_log table from old structure to new structure
			$this->migrate_sync_log_table( $log_table );
		}

		// Create/update tables with new schema
		$this->create_tables();
	}

	/**
	 * Migrate sync log table from old structure to new structure
	 *
	 * @param string $table_name The sync log table name
	 * @return void
	 */
	private function migrate_sync_log_table( string $table_name ): void {
		global $wpdb;

		// Check if table exists and has old structure
		$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
		if ( empty( $columns ) ) {
			return; // Table doesn't exist, will be created fresh
		}

		$column_names = wp_list_pluck( $columns, 'Field' );
		$has_old_structure = in_array( 'user_id', $column_names, true ) && in_array( 'contact_id', $column_names, true );
		$has_new_structure = in_array( 'sync_type', $column_names, true ) && in_array( 'item_id', $column_names, true );

		if ( $has_old_structure && ! $has_new_structure ) {
			// Backup existing data by creating a temporary table
			$backup_table = $table_name . '_backup_' . time();
			$wpdb->query("CREATE TABLE {$backup_table} AS SELECT * FROM {$table_name}");

			// Drop the old table completely
			$wpdb->query("DROP TABLE IF EXISTS {$table_name}");
		}
	}

	/**
	 * Create all plugin tables
	 * Multisite-aware: Creates tables with site_id for data isolation
	 *
	 * @return void
	 */
	private function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table names (with current site prefix)
		$sync_queue_table = $wpdb->prefix . 'ghl_sync_queue';
		$sync_log_table   = $wpdb->prefix . 'ghl_sync_log';

		// SQL for sync queue table
		// Supports all integrations: users, orders, groups, courses, etc.
		$sql_queue = "CREATE TABLE IF NOT EXISTS {$sync_queue_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_type varchar(50) NOT NULL DEFAULT 'user',
			item_id bigint(20) unsigned NOT NULL,
			action varchar(50) NOT NULL,
			payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(2) unsigned NOT NULL DEFAULT 0,
			error_message text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			processed_at datetime DEFAULT NULL,
			site_id bigint(20) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY unique_item_action (item_type, item_id, action, site_id),
			KEY status_created (status, created_at),
			KEY item_lookup (item_type, item_id),
			KEY status_attempts (status, attempts),
			KEY site_id (site_id),
			KEY site_status (site_id, status)
		) {$charset_collate};";

		// SQL for sync log table  
		$sql_log = "CREATE TABLE {$sync_log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sync_type varchar(50) NOT NULL DEFAULT 'user',
			item_id bigint(20) unsigned NOT NULL,
			action varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			message text DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			ghl_id varchar(100) DEFAULT NULL,
			created_at datetime NOT NULL,
			site_id bigint(20) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY item_lookup (sync_type, item_id),
			KEY action_status (action, status),
			KEY created_at (created_at),
			KEY site_id (site_id),
			KEY site_item (site_id, sync_type, item_id),
			KEY status_created (status, created_at)
		) {$charset_collate};";

		// Include WordPress upgrade functions
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create tables
		dbDelta( $sql_queue );
		dbDelta( $sql_log );
	}

	/**
	 * Drop all plugin tables (for uninstall)
	 * Multisite-aware: Drops tables for current site
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'ghl_sync_queue',
			$wpdb->prefix . 'ghl_sync_log',
		];

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$settings_manager = SettingsManager::get_instance();
		$settings_manager->update_option( 'ghl_crm_db_version', false );
	}

	/**
	 * Drop tables for all sites (network uninstall)
	 *
	 * @return void
	 */
	public static function drop_all_sites_tables(): void {
		if ( ! is_multisite() ) {
			self::drop_tables();
			return;
		}

		$sites = get_sites(
			[
				'number' => 999,
			]
		);

		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			self::drop_tables();
			restore_current_blog();
		}
	}

	/**
	 * Clean up old records
	 * Multisite-aware: Cleans current site's data
	 * Uses configurable retention period from settings
	 *
	 * @return void
	 */
	public function cleanup(): void {
		global $wpdb;

		$sync_queue_table = $wpdb->prefix . 'ghl_sync_queue';
		$sync_log_table   = $wpdb->prefix . 'ghl_sync_log';
		$current_site_id  = get_current_blog_id();

		// Get retention period from settings via SettingsManager (default: 30 days)
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$retention_days   = absint( $settings_manager->get_setting( 'log_retention_days', 30 ) );
		$retention_hours  = max( 1, $retention_days * 24 ); // Convert to hours, minimum 1 hour

		// Delete completed queue items older than 1 day (keep it lean)
		$deleted_completed = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sync_queue_table} WHERE status = 'completed' AND site_id = %d AND processed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) )
			)
		);

		// Delete failed queue items older than 7 days
		$deleted_failed = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sync_queue_table} WHERE status = 'failed' AND site_id = %d AND created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			)
		);

		// Delete logs older than configured retention period
		$deleted_logs = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sync_log_table} WHERE site_id = %d AND created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) )
			)
		);

		// Emergency cleanup: If queue table is too large (>50k rows), purge oldest completed
		$queue_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$sync_queue_table} WHERE site_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id
			)
		);

		if ( $queue_count > 50000 ) {
			// Delete oldest 25k completed/failed items regardless of age
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$sync_queue_table} 
					WHERE site_id = %d 
					AND status IN ('completed', 'failed') 
					ORDER BY created_at ASC 
					LIMIT 25000",
					$current_site_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Schedule cleanup job via Action Scheduler
	 *
	 * @return void
	 */
	public function schedule_cleanup(): void {
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			// Use Action Scheduler (runs daily at midnight)
			if ( false === as_next_scheduled_action( 'ghl_crm_cleanup_database' ) ) {
				as_schedule_recurring_action( strtotime( 'tomorrow midnight' ), DAY_IN_SECONDS, 'ghl_crm_cleanup_database', [], 'ghl-crm' );
			}
		} else {
			// Fallback to WP-Cron if Action Scheduler not available
			if ( ! wp_next_scheduled( 'ghl_crm_cleanup_database' ) ) {
				wp_schedule_event( time(), 'daily', 'ghl_crm_cleanup_database' );
			}
		}
	}
}
