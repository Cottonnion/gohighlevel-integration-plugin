<?php
declare(strict_types=1);

namespace GHL_CRM\Core\Settings;

use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System Health Check
 *
 * Runs comprehensive diagnostics on the plugin and server environment.
 * Extracted from SettingsManager to reduce file size and improve cohesion.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core/Settings
 */
class SystemHealthCheck {

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

	/** Private constructor. */
	private function __construct() {}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_ghl_crm_system_health_check', [ $this, 'system_health_check' ] );
	}

	/**
	 * AJAX handler: Run system health check diagnostics.
	 *
	 * @return void
	 */
	public function system_health_check(): void {
		check_ajax_referer( 'ghl_crm_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to run system diagnostics.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		$settings_manager = SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$checks           = [];

		$this->check_wordpress_environment( $checks );
		$this->check_database_tables( $checks );
		$this->check_api_connection( $checks, $settings, $settings_manager );
		$this->check_php_extensions( $checks );
		$this->check_queue_status( $checks );
		$this->check_file_permissions( $checks );
		$this->check_performance( $checks, $settings );

		// Calculate overall status.
		$overall_status = 'success';
		foreach ( $checks as $check ) {
			if ( $check['status'] === 'error' ) {
				$overall_status = 'error';
				break;
			} elseif ( $check['status'] === 'warning' && $overall_status !== 'error' ) {
				$overall_status = 'warning';
			}
		}

		wp_send_json_success(
			[
				'overall_status' => $overall_status,
				'checks'         => $checks,
				'timestamp'      => current_time( 'mysql' ),
				'message'        => $overall_status === 'success'
					? __( 'All system checks passed!', 'ghl-crm-integration' )
					: ( $overall_status === 'warning'
						? __( 'System checks passed with warnings.', 'ghl-crm-integration' )
						: __( 'Some system checks failed. Please review the details.', 'ghl-crm-integration' ) ),
			]
		);
	}

	/**
	 * Check WordPress environment.
	 *
	 * @param array $checks Reference to the checks array.
	 * @return void
	 */
	private function check_wordpress_environment( array &$checks ): void {
		$checks['wordpress'] = [
			'label'  => __( 'WordPress Environment', 'ghl-crm-integration' ),
			'status' => 'success',
			'items'  => [
				[
					'label'  => __( 'WordPress Version', 'ghl-crm-integration' ),
					'value'  => get_bloginfo( 'version' ),
					'status' => version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ? 'success' : 'warning',
				],
				[
					'label'  => __( 'PHP Version', 'ghl-crm-integration' ),
					'value'  => PHP_VERSION,
					'status' => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'success' : 'error',
				],
				[
					'label'  => __( 'Multisite', 'ghl-crm-integration' ),
					'value'  => is_multisite() ? __( 'Yes', 'ghl-crm-integration' ) : __( 'No', 'ghl-crm-integration' ),
					'status' => 'success',
				],
			],
		];
	}

	/**
	 * Check database tables.
	 *
	 * @param array $checks Reference to the checks array.
	 * @return void
	 */
	private function check_database_tables( array &$checks ): void {
		global $wpdb;
		$table_prefix     = $wpdb->prefix;
		$required_tables  = [ 'ghl_sync_queue', 'ghl_sync_log' ];
		$tables_status    = [];
		$tables_all_exist = true;

		foreach ( $required_tables as $table ) {
			$table_name = $table_prefix . $table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Inspecting table existence during diagnostics.
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

			$tables_status[] = [
				'label'  => $table_name,
				'value'  => $table_exists ? __( 'Exists', 'ghl-crm-integration' ) : __( 'Missing', 'ghl-crm-integration' ),
				'status' => $table_exists ? 'success' : 'error',
			];

			if ( ! $table_exists ) {
				$tables_all_exist = false;
			}
		}

		$checks['database'] = [
			'label'  => __( 'Database Tables', 'ghl-crm-integration' ),
			'status' => $tables_all_exist ? 'success' : 'error',
			'items'  => $tables_status,
		];
	}

	/**
	 * Check API connection status.
	 *
	 * @param array           $checks           Reference to the checks array.
	 * @param array           $settings         Current plugin settings.
	 * @param SettingsManager $settings_manager  Settings manager instance.
	 * @return void
	 */
	private function check_api_connection( array &$checks, array $settings, SettingsManager $settings_manager ): void {
		$has_oauth          = ! empty( $settings['oauth_access_token'] );
		$has_manual_api     = ! empty( $settings['api_token'] ) && ! empty( $settings['location_id'] );
		$has_any_connection = $has_oauth || $has_manual_api;
		$is_verified        = $settings_manager->is_connection_verified();

		$checks['api_connection'] = [
			'label'  => __( 'API Connection', 'ghl-crm-integration' ),
			'status' => $has_any_connection ? ( $is_verified ? 'success' : 'warning' ) : 'error',
			'items'  => [
				[
					'label'  => __( 'Connection Type', 'ghl-crm-integration' ),
					'value'  => $has_oauth ? __( 'OAuth', 'ghl-crm-integration' ) : ( $has_manual_api ? __( 'Manual API', 'ghl-crm-integration' ) : __( 'Not Connected', 'ghl-crm-integration' ) ),
					'status' => $has_any_connection ? 'success' : 'error',
				],
				[
					'label'  => __( 'Connection Verified', 'ghl-crm-integration' ),
					'value'  => $is_verified ? __( 'Yes', 'ghl-crm-integration' ) : __( 'No', 'ghl-crm-integration' ),
					'status' => $is_verified ? 'success' : 'warning',
				],
				[
					'label'  => __( 'Location ID', 'ghl-crm-integration' ),
					'value'  => ! empty( $settings['location_id'] ) ? substr( $settings['location_id'], 0, 10 ) . '...' : __( 'Not Set', 'ghl-crm-integration' ),
					'status' => ! empty( $settings['location_id'] ) ? 'success' : 'error',
				],
			],
		];
	}

	/**
	 * Check required PHP extensions.
	 *
	 * @param array $checks Reference to the checks array.
	 * @return void
	 */
	private function check_php_extensions( array &$checks ): void {
		$required_extensions = [
			'curl'     => __( 'cURL', 'ghl-crm-integration' ),
			'json'     => __( 'JSON', 'ghl-crm-integration' ),
			'mbstring' => __( 'Multibyte String', 'ghl-crm-integration' ),
		];
		$extensions_status   = [];
		$all_extensions_ok   = true;

		foreach ( $required_extensions as $ext => $label ) {
			$loaded              = extension_loaded( $ext );
			$extensions_status[] = [
				'label'  => $label,
				'value'  => $loaded ? __( 'Loaded', 'ghl-crm-integration' ) : __( 'Missing', 'ghl-crm-integration' ),
				'status' => $loaded ? 'success' : 'error',
			];

			if ( ! $loaded ) {
				$all_extensions_ok = false;
			}
		}

		$checks['php_extensions'] = [
			'label'  => __( 'PHP Extensions', 'ghl-crm-integration' ),
			'status' => $all_extensions_ok ? 'success' : 'error',
			'items'  => $extensions_status,
		];
	}

	/**
	 * Check sync queue status.
	 *
	 * @param array $checks Reference to the checks array.
	 * @return void
	 */
	private function check_queue_status( array &$checks ): void {
		global $wpdb;
		$queue_table     = $wpdb->prefix . 'ghl_sync_queue';
		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Collecting queue metrics for system health report.
		$pending_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE status = 'pending' AND site_id = %d",
				$current_site_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Collecting queue metrics for system health report.
		$failed_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE status = 'failed' AND site_id = %d",
				$current_site_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Collecting queue metrics for system health report.
		$processing_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE status = 'processing' AND site_id = %d",
				$current_site_id
			)
		);

		$checks['sync_queue'] = [
			'label'  => __( 'Sync Queue', 'ghl-crm-integration' ),
			'status' => $failed_count > 10 ? 'warning' : 'success',
			'items'  => [
				[
					'label'  => __( 'Pending Items', 'ghl-crm-integration' ),
					'value'  => $pending_count,
					'status' => 'success',
				],
				[
					'label'  => __( 'Processing Items', 'ghl-crm-integration' ),
					'value'  => $processing_count,
					'status' => $processing_count > 0 ? 'success' : 'info',
				],
				[
					'label'  => __( 'Failed Items', 'ghl-crm-integration' ),
					'value'  => $failed_count,
					'status' => $failed_count > 10 ? 'warning' : ( $failed_count > 0 ? 'info' : 'success' ),
				],
			],
		];
	}

	/**
	 * Check file permissions.
	 *
	 * @param array $checks Reference to the checks array.
	 * @return void
	 */
	private function check_file_permissions( array &$checks ): void {
		$upload_dir      = wp_upload_dir();
		$upload_writable = wp_is_writable( $upload_dir['basedir'] );
		$plugin_dir      = GHL_CRM_PATH;
		$plugin_readable = is_readable( $plugin_dir );

		$checks['file_permissions'] = [
			'label'  => __( 'File Permissions', 'ghl-crm-integration' ),
			'status' => ( $upload_writable && $plugin_readable ) ? 'success' : 'warning',
			'items'  => [
				[
					'label'  => __( 'Upload Directory', 'ghl-crm-integration' ),
					'value'  => $upload_writable ? __( 'Writable', 'ghl-crm-integration' ) : __( 'Not Writable', 'ghl-crm-integration' ),
					'status' => $upload_writable ? 'success' : 'error',
				],
				[
					'label'  => __( 'Plugin Directory', 'ghl-crm-integration' ),
					'value'  => $plugin_readable ? __( 'Readable', 'ghl-crm-integration' ) : __( 'Not Readable', 'ghl-crm-integration' ),
					'status' => $plugin_readable ? 'success' : 'error',
				],
			],
		];
	}

	/**
	 * Check memory and performance settings.
	 *
	 * @param array $checks  Reference to the checks array.
	 * @param array $settings Current plugin settings.
	 * @return void
	 */
	private function check_performance( array &$checks, array $settings ): void {
		$memory_limit       = ini_get( 'memory_limit' );
		$max_execution_time = ini_get( 'max_execution_time' );

		$checks['performance'] = [
			'label'  => __( 'Performance Settings', 'ghl-crm-integration' ),
			'status' => 'success',
			'items'  => [
				[
					'label'  => __( 'PHP Memory Limit', 'ghl-crm-integration' ),
					'value'  => $memory_limit,
					'status' => 'info',
				],
				[
					'label'  => __( 'Max Execution Time', 'ghl-crm-integration' ),
					'value'  => $max_execution_time . 's',
					'status' => 'info',
				],
				[
					'label'  => __( 'Cache Duration', 'ghl-crm-integration' ),
					'value'  => $settings['cache_duration'] . 's',
					'status' => 'info',
				],
				[
					'label'  => __( 'Batch Size', 'ghl-crm-integration' ),
					'value'  => $settings['batch_size'],
					'status' => 'info',
				],
			],
		];
	}

	/** Prevent cloning. */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
