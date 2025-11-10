<?php
declare(strict_types=1);

namespace GHL_CRM\Core\Dashboard;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Sync\SyncStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard stats provider.
 *
 * Collects data required for the admin dashboard cards.
 *
 * @package GHL_CRM_Integration
 */
class StatsProvider {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Sync stats cache for current request.
	 *
	 * @var array<string, mixed>
	 */
	private array $sync_stats_cache = [];

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prepare dashboard report data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_report_data(): array {
		return [
			'contacts'      => $this->get_contact_metrics(),
			'sync_activity' => $this->get_sync_activity_metrics(),
			'integrations'  => $this->get_integration_metrics(),
			'system_health' => $this->get_system_health_metrics(),
			'recent_activity' => $this->get_recent_activity(),
			'links'          => $this->get_dashboard_links(),
		];
	}

	/**
	 * Contact metrics: totals and sync success rates.
	 */
	private function get_contact_metrics(): array {
		$stats         = $this->get_sync_stats();
		$total_users   = $this->get_total_users();
		$total_success = (int) ( $stats['success_total'] ?? 0 );
		$total_failed  = (int) ( $stats['failed_total'] ?? 0 );
		$total_synced  = $total_success;
		$total_events  = max( 0, $total_success + $total_failed );

		$sync_rate = $total_events > 0
			? (int) round( ( $total_success / $total_events ) * 100 )
			: 0;

		return [
			'total_ghl' => $this->get_total_contacts_placeholder(),
			'total_wp'  => $total_users,
			'synced'    => $total_synced,
			'pending'   => $this->get_pending_queue_count(),
			'failed'    => $total_failed,
			'sync_rate' => $sync_rate,
		];
	}

	/**
	 * Sync volume metrics for the last 24h/7d/30d.
	 */
	private function get_sync_activity_metrics(): array {
		return [
			'last_24h'  => $this->count_logs_since( '-24 hours' ),
			'last_7d'   => $this->count_logs_since( '-7 days' ),
			'last_30d'  => $this->count_logs_since( '-30 days' ),
		];
	}

	/**
	 * Basic integration metrics (WooCommerce/BuddyBoss).
	 */
	private function get_integration_metrics(): array {
		$integrations = [
			[
				'key'     => 'woocommerce',
				'label'   => __( 'WooCommerce', 'ghl-crm-integration' ),
				'enabled' => $this->is_integration_enabled( 'woocommerce' ),
			],
			[
				'key'     => 'buddyboss',
				'label'   => __( 'BuddyBoss Groups', 'ghl-crm-integration' ),
				'enabled' => $this->is_integration_enabled( 'buddyboss' ),
			],
		];

		/**
		 * Allow other integrations to register with the dashboard summary.
		 *
		 * @param array $integrations Integrations status list.
		 */
		return apply_filters( 'ghl_crm_dashboard_integrations', $integrations );
	}

	private function get_dashboard_links(): array {
		$location_id = $this->get_location_id();
		$base_url    = $this->get_ghl_base_url();

		$contacts_url = '';
		$available    = false;

		if ( ! empty( $location_id ) ) {
			$contacts_url = sprintf(
				'%s/v2/location/%s/contacts/list',
				rtrim( $base_url, '/' ),
				rawurlencode( $location_id )
			);
			$available    = true;
		}

		return [
			'contacts' => [
				'url'       => $contacts_url,
				'available' => $available,
			],
		];
	}

	/**
	 * System health summary data.
	 */
	private function get_system_health_metrics(): array {
		return [
			'api_connection' => $this->is_connection_healthy() ? 'healthy' : 'disconnected',
			'queue_status'   => $this->is_queue_healthy() ? 'healthy' : 'attention',
			'last_sync'      => $this->get_last_sync_diff(),
			'pending_jobs'   => $this->get_pending_queue_count(),
		];
	}

	/**
	 * Collect recent activity from sync logs.
	 */
	private function get_recent_activity(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ghl_sync_log';
		$site  = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fetching latest sync log entries for dashboard.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sync_type, message, status, created_at FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT 5",
				$site
			),
			ARRAY_A
		);

		$results = [];

		foreach ( $rows as $row ) {
			$type = 'info';
			if ( 'success' === $row['status'] ) {
				$type = 'success';
			} elseif ( 'failed' === $row['status'] ) {
				$type = 'warning';
			}

			$results[] = [
				'type'    => $type,
				'message' => $row['message'],
				'time'    => $this->human_time_diff_from( $row['created_at'] ),
			];
		}

		return $results;
	}

	private function get_sync_stats(): array {
		if ( ! empty( $this->sync_stats_cache ) ) {
			return $this->sync_stats_cache;
		}

		$stats = SyncStats::get_instance()->get_site_stats();

		if ( ! $this->stats_have_history( $stats ) ) {
			$stats = $this->hydrate_stats_from_logs( $stats );
		}

		$this->sync_stats_cache = $stats;

		return $this->sync_stats_cache;
	}

	private function get_total_users(): int {
		return (int) count_users()['total_users'];
	}

	private function get_total_contacts_placeholder(): int {
		// TODO: Replace with real GoHighLevel contact totals once API client supports it.
		return (int) ( $this->get_sync_stats()['success_total'] ?? 0 );
	}

	private function get_pending_queue_count(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_queue';
		$site  = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting pending queue rows for dashboard.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE site_id = %d AND status = 'pending'",
				$site
			)
		);
	}

	private function count_logs_since( string $relative_time ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_log';
		$site  = get_current_blog_id();

		$base_timestamp = current_time( 'timestamp' );
		$target_time    = strtotime( $relative_time, $base_timestamp );

		if ( false === $target_time ) {
			return 0;
		}

		$datetime = function_exists( 'wp_date' )
			? wp_date( 'Y-m-d H:i:s', $target_time )
			: date_i18n( 'Y-m-d H:i:s', $target_time );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregating log counts within rolling windows for dashboard metrics.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE site_id = %d AND created_at >= %s",
				$site,
				$datetime
			)
		);
	}

	private function is_integration_enabled( string $integration ): bool {
		$settings = SettingsManager::get_instance()->get_settings_array();

		if ( 'woocommerce' === $integration ) {
			if ( isset( $settings['wc_enabled'] ) ) {
				return ! empty( $settings['wc_enabled'] );
			}

			return ! empty( $settings['enable_woocommerce'] );
		}

		if ( 'buddyboss' === $integration ) {
			if ( isset( $settings['buddyboss_groups_enabled'] ) ) {
				return ! empty( $settings['buddyboss_groups_enabled'] );
			}

			return ! empty( $settings['enable_buddyboss'] );
		}

		return false;
	}

	private function is_connection_healthy(): bool {
		$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
		$status        = $oauth_handler->get_connection_status();
		$settings      = SettingsManager::get_instance()->get_settings_array();

		return ! empty( $status['connected'] ) || ! empty( $settings['api_token'] );
	}

	private function get_location_id(): string {
		$settings = SettingsManager::get_instance()->get_settings_array();
		return (string) ( $settings['location_id'] ?? '' );
	}

	private function get_ghl_base_url(): string {
		$settings    = SettingsManager::get_instance()->get_settings_array();
		$white_label = isset( $settings['ghl_white_label_domain'] ) ? trim( (string) $settings['ghl_white_label_domain'] ) : '';

		if ( ! empty( $white_label ) ) {
			return untrailingslashit( $white_label );
		}

		return 'https://app.leadconnectorhq.com';
	}

	private function is_queue_healthy(): bool {
		return $this->get_pending_queue_count() < 100;
	}

	private function get_last_sync_diff(): string {
		$stats = $this->get_sync_stats();
		$last  = $stats['last_event_at'] ?? '';

		if ( empty( $last ) ) {
			return __( 'No sync activity yet', 'ghl-crm-integration' );
		}

		return $this->human_time_diff_from( $last );
	}

	private function human_time_diff_from( string $datetime ): string {
		$timestamp = strtotime( $datetime );

		if ( ! $timestamp ) {
			return $datetime;
		}

		return sprintf(
			/* translators: %s: human-readable time difference */
			__( '%s ago', 'ghl-crm-integration' ),
			human_time_diff( $timestamp, current_time( 'timestamp' ) )
		);
	}

	/**
	 * Determine if stored stats already contain historical values.
	 */
	private function stats_have_history( array $stats ): bool {
		return ( (int) $stats['success_total'] > 0 )
			|| ( (int) $stats['failed_total'] > 0 )
			|| ! empty( $stats['last_event_at'] );
	}

	/**
	 * Hydrate stats from existing sync logs when option data is missing.
	 */
	private function hydrate_stats_from_logs( array $stats ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_log';
		$site  = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregating existing log totals for dashboard bootstrapping.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS total, MAX(created_at) AS last_at FROM {$table} WHERE site_id = %d GROUP BY status",
				$site
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return $stats;
		}

		foreach ( $rows as $row ) {
			$status = $row['status'] ?? '';
			$total  = isset( $row['total'] ) ? (int) $row['total'] : 0;
			$last   = $row['last_at'] ?? '';

			if ( 'success' === $status ) {
				$stats['success_total']   = $total;
				$stats['last_success_at'] = $last;
			} elseif ( 'failed' === $status ) {
				$stats['failed_total']   = $total;
				$stats['last_failure_at'] = $last;
			}
		}

		// Overall last event timestamp.
		$last_event = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(created_at) FROM {$table} WHERE site_id = %d",
				$site
			)
		);

		if ( $last_event ) {
			$stats['last_event_at'] = $last_event;
		}

		$last_type = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sync_type FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT 1",
				$site
			)
		);

		if ( $last_type ) {
			$stats['last_sync_type'] = $last_type;
		}

		SyncStats::get_instance()->replace_site_stats( $stats );

		return $stats;
	}
}
