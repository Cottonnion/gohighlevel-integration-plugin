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
	private const DB_VERSION = '1.10.0';

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
				<li><?php esc_html_e( '✅ Add performance indexes for faster queue processing', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( '✅ Optimize dashboard statistics queries (5-10x faster)', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( '✅ Improve log cleanup efficiency for large databases', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( '⚡ Expected performance boost: up to 10x faster queries', 'ghl-crm-integration' ); ?></li>
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
		$raw_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $raw_nonce, 'ghl_crm_update_db' ) ) {
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

			do_action(
				'ghl_crm_log_event',
				'database_update',
				'GHL CRM database updated successfully.',
				[
					'from_version' => $installed_version,
					'to_version'   => self::DB_VERSION,
					'site_id'      => get_current_blog_id(),
				],
				'info'
			);

			wp_send_json_success( __( 'Database updated successfully', 'ghl-crm-integration' ) );
		} catch ( Exception $e ) {
			do_action(
				'ghl_crm_log_event',
				'database_update_failed',
				'GHL CRM database update failed.',
				[
					'from_version' => $installed_version ?? 'unknown',
					'to_version'   => self::DB_VERSION,
					'error'        => $e->getMessage(),
					'site_id'      => get_current_blog_id(),
				],
				'error'
			);
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
			$log_table   = $wpdb->prefix . 'ghl_sync_log';

			// Remove duplicate pending entries, keep the oldest one
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup requires raw SQL against custom queue table.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"
				DELETE q1 FROM {$queue_table} q1
				INNER JOIN {$queue_table} q2 
				WHERE q1.id > q2.id 
				AND q1.item_type = q2.item_type 
				AND q1.item_id = q2.item_id 
				AND q1.action = q2.action 
				AND q1.site_id = q2.site_id 
				AND q1.status = 'pending'
			"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// Drop old constraint if exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Adjusting table indexes during migration.
			$wpdb->query( "ALTER TABLE {$queue_table} DROP INDEX IF EXISTS unique_pending_item" );

			// Migrate sync_log table from old structure to new structure
			$this->migrate_sync_log_table( $log_table );
		}

		// Add performance indexes for version 1.5.0
		if ( version_compare( $from_version, '1.5.0', '<' ) ) {
			$this->add_performance_indexes();
		}

		// Remove enhanced logging columns if they exist (cleanup from reverted feature)
		if ( version_compare( $from_version, '1.7.0', '>=' ) ) {
			$this->remove_enhanced_logging_columns();
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema inspection required for migration.
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}" );
		if ( empty( $columns ) ) {
			return; // Table doesn't exist, will be created fresh
		}

		$column_names      = wp_list_pluck( $columns, 'Field' );
		$has_old_structure = in_array( 'user_id', $column_names, true ) && in_array( 'contact_id', $column_names, true );
		$has_new_structure = in_array( 'sync_type', $column_names, true ) && in_array( 'item_id', $column_names, true );

		if ( $has_old_structure && ! $has_new_structure ) {
			// Backup existing data by creating a temporary table
			$backup_table = $table_name . '_backup_' . time();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Migration requires temporary table creation.
			$wpdb->query( "CREATE TABLE {$backup_table} AS SELECT * FROM {$table_name}" );

			// Drop the old table completely
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removing legacy table structure.
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		}
	}

	/**
	 * Add performance indexes for v1.5.0
	 * Optimizes the most common query patterns
	 *
	 * @return void
	 */
	private function add_performance_indexes(): void {
		global $wpdb;

		$queue_table = $wpdb->prefix . 'ghl_sync_queue';
		$log_table   = $wpdb->prefix . 'ghl_sync_log';

		// Queue table performance indexes
		// Optimize: "WHERE status = 'pending' AND site_id = X ORDER BY created_at"
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Adding performance indexes.
		$wpdb->query( "ALTER TABLE {$queue_table} ADD KEY IF NOT EXISTS status_site_created (status, site_id, created_at)" );

		// Optimize: "WHERE status = 'completed' AND site_id = X AND processed_at > Y"
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Adding performance indexes.
		$wpdb->query( "ALTER TABLE {$queue_table} ADD KEY IF NOT EXISTS status_site_processed (status, site_id, processed_at)" );

		// Log table performance indexes
		// Optimize: "WHERE site_id = X AND created_at < Y" (cleanup query)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Adding performance indexes.
		$wpdb->query( "ALTER TABLE {$log_table} ADD KEY IF NOT EXISTS site_created_cleanup (site_id, created_at)" );

		// Optimize: "WHERE sync_type = X AND item_id = Y AND site_id = Z"
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Adding performance indexes.
		$wpdb->query( "ALTER TABLE {$log_table} ADD KEY IF NOT EXISTS sync_item_site (sync_type, item_id, site_id)" );
	}

	/**
	 * Remove enhanced logging columns (cleanup from reverted feature)
	 *
	 * @return void
	 */
	private function remove_enhanced_logging_columns(): void {
		global $wpdb;

		$log_table = $wpdb->prefix . 'ghl_sync_log';

		// Check if columns exist before trying to drop them
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema inspection required.
		$columns      = $wpdb->get_results( "SHOW COLUMNS FROM {$log_table}" );
		$column_names = wp_list_pluck( $columns, 'Field' );

		// Drop log_level column if it exists
		if ( in_array( 'log_level', $column_names, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removing reverted feature columns.
			$wpdb->query( "ALTER TABLE {$log_table} DROP COLUMN log_level" );
		}

		// Drop request_data column if it exists
		if ( in_array( 'request_data', $column_names, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removing reverted feature columns.
			$wpdb->query( "ALTER TABLE {$log_table} DROP COLUMN request_data" );
		}

		// Drop duration_ms column if it exists
		if ( in_array( 'duration_ms', $column_names, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removing reverted feature columns.
			$wpdb->query( "ALTER TABLE {$log_table} DROP COLUMN duration_ms" );
		}

		// Drop the log_level index if it exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Removing reverted feature index.
		$wpdb->query( "ALTER TABLE {$log_table} DROP INDEX IF EXISTS log_level_created" );
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
		$sync_queue_table           = $wpdb->prefix . 'ghl_sync_queue';
		$sync_log_table             = $wpdb->prefix . 'ghl_sync_log';
		$family_relationships_table = $wpdb->prefix . 'ghl_family_relationships';
		$reporting_events_table     = $wpdb->prefix . 'ghl_reporting_events';

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
			KEY status_created (status, created_at),
			KEY item_lookup (item_type, item_id),
			KEY status_attempts (status, attempts),
			KEY site_id (site_id),
			KEY site_status (site_id, status),
			KEY status_site_created (status, site_id, created_at),
			KEY status_site_processed (status, site_id, processed_at),
			KEY item_action_status (item_type, item_id, action, site_id, status)
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
			KEY status_created (status, created_at),
			KEY site_created_cleanup (site_id, created_at),
			KEY sync_item_site (sync_type, item_id, site_id)
		) {$charset_collate};";

		// Include WordPress upgrade functions
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// SQL for family relationships table
		$sql_family = "CREATE TABLE {$family_relationships_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			parent_user_id bigint(20) unsigned NOT NULL,
			child_user_id bigint(20) unsigned NOT NULL,
			family_group_id char(36) NOT NULL DEFAULT '',
			status enum('active','inactive','pending') NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			site_id bigint(20) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY unique_child (child_user_id, site_id),
			KEY parent_lookup (parent_user_id, site_id),
			KEY family_group (family_group_id, site_id),
			KEY status (status),
			KEY site_id (site_id),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		$sql_reporting_events = "CREATE TABLE {$reporting_events_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			severity varchar(20) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context longtext DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(2) unsigned NOT NULL DEFAULT 0,
			sent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			site_id bigint(20) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY status_site_created (status, site_id, created_at),
			KEY site_created (site_id, created_at),
			KEY event_severity (event_type, severity)
		) {$charset_collate};";

		// Create tables
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta handles schema creation/updates for plugin tables.
		dbDelta( $sql_queue );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta handles schema creation/updates for plugin tables.
		dbDelta( $sql_log );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta handles schema creation/updates for plugin tables.
		dbDelta( $sql_family );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta handles schema creation/updates for plugin tables.
		dbDelta( $sql_reporting_events );
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
			$wpdb->prefix . 'ghl_family_relationships',
			$wpdb->prefix . 'ghl_reporting_events',
		];

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Removing plugin-managed tables during uninstall.
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

		$sync_queue_table       = $wpdb->prefix . 'ghl_sync_queue';
		$sync_log_table         = $wpdb->prefix . 'ghl_sync_log';
		$reporting_events_table = $wpdb->prefix . 'ghl_reporting_events';
		$current_site_id        = get_current_blog_id();

		// Get retention period from settings via SettingsManager (default: 30 days)
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$retention_days   = absint( $settings_manager->get_setting( 'log_retention_days', 30 ) );
		$retention_hours  = max( 1, $retention_days * 24 ); // Convert to hours, minimum 1 hour

		// Delete completed queue items older than 1 day (keep it lean)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled maintenance against plugin queue table.
		$deleted_completed = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sync_queue_table} WHERE status = 'completed' AND site_id = %d AND processed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) )
			)
		);

		// Delete failed queue items older than 7 days
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled maintenance against plugin queue table.
		$deleted_failed = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sync_queue_table} WHERE status = 'failed' AND site_id = %d AND created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			)
		);

		// Delete logs older than configured retention period
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled maintenance against plugin log table.
		$deleted_logs = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sync_log_table} WHERE site_id = %d AND created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) )
			)
		);

		// Delete reporting events that have been sent or failed beyond retention
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled maintenance against plugin reporting table.
		$deleted_reporting = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$reporting_events_table} WHERE site_id = %d AND status IN ('sent','failed') AND created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) )
			)
		);

		// Emergency cleanup: If queue table is too large (>50k rows), purge oldest completed
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Monitoring queue table size for emergency purge.
		$queue_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$sync_queue_table} WHERE site_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$current_site_id
			)
		);

		if ( $queue_count > 50000 ) {
			// Delete oldest 25k completed/failed items regardless of age
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Emergency purge of oversized queue table.
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