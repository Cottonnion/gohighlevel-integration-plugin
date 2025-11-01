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
	private const DB_VERSION = '1.0.0';

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
		$installed_version = get_option( 'ghl_crm_db_version', '0.0.0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			$this->create_tables();
			update_option( 'ghl_crm_db_version', self::DB_VERSION );
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
			UNIQUE KEY unique_pending_item (item_type, item_id, action, site_id, status),
			KEY status_created (status, created_at),
			KEY item_lookup (item_type, item_id),
			KEY status_attempts (status, attempts),
			KEY site_id (site_id),
			KEY site_status (site_id, status)
		) {$charset_collate};";

		// SQL for sync log table
		$sql_log = "CREATE TABLE IF NOT EXISTS {$sync_log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			action varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			contact_id varchar(100) DEFAULT NULL,
			request_data longtext DEFAULT NULL,
			response_data longtext DEFAULT NULL,
			error_message text DEFAULT NULL,
			execution_time decimal(10,3) DEFAULT NULL,
			created_at datetime NOT NULL,
			site_id bigint(20) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY action_status (action, status),
			KEY created_at (created_at),
			KEY site_id (site_id),
			KEY site_user (site_id, user_id)
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
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		delete_option( 'ghl_crm_db_version' );
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
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$sync_queue_table} 
					WHERE site_id = %d 
					AND status IN ('completed', 'failed') 
					ORDER BY created_at ASC 
					LIMIT 25000", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$current_site_id
				)
			);

			error_log(
				sprintf(
					'GHL CRM Emergency Cleanup [Site %d]: Queue exceeded 50k rows. Purged old items.',
					$current_site_id
				)
			);
		}

		// Log cleanup stats
		if ( $deleted_completed || $deleted_failed || $deleted_logs ) {
			error_log(
				sprintf(
					'GHL CRM Cleanup [Site %d]: Deleted %d completed, %d failed queue items, %d logs',
					$current_site_id,
					$deleted_completed,
					$deleted_failed,
					$deleted_logs
				)
			);
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
