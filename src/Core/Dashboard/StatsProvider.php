<?php
declare(strict_types=1);

namespace GHL_CRM\Core\Dashboard;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Core\TagManager;
use GHL_CRM\Sync\SyncStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard stats provider.
 *
 * Collects data required for the admin dashboard cards.
 * Stats are filtered by current location ID.
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
	 * Location-scoped meta key for GHL contact IDs.
	 *
	 * @var string
	 */
	private string $contact_meta_key;

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
	 * Private constructor.
	 */
	private function __construct() {
		$this->contact_meta_key = TagManager::get_instance()->get_user_contact_id_meta_key();
	}

	/**
	 * Prepare dashboard report data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_report_data(): array {
		return [
			'contacts'               => $this->get_contact_metrics(),
			'sync_activity'          => $this->get_sync_activity_metrics(),
			'integrations'           => $this->get_integration_metrics(),
			'system_health'          => $this->get_system_health_metrics(),
			'recent_activity'        => $this->get_recent_activity(),
			'links'                  => $this->get_dashboard_links(),
			'debug_raw_ghl_response' => get_transient( 'ghl_raw_contacts_response' ),
		];
	}

	/**
	 * Contact metrics: totals and sync success rates.
	 */
	private function get_contact_metrics(): array {
		$stats         = $this->get_sync_stats();
		$total_users   = $this->get_total_users();
		$total_synced  = $this->get_synced_users_count(); // FIXED: Count unique users with contact IDs
		$total_success = (int) ( $stats['success_total'] ?? 0 );
		$total_failed  = (int) ( $stats['failed_total'] ?? 0 );
		$total_events  = max( 0, $total_success + $total_failed );

		$sync_rate = $total_events > 0
			? (int) round( ( $total_success / $total_events ) * 100 )
			: 0;

		return [
			'total_ghl' => $this->get_total_ghl_contacts(),
			'total_wp'  => $total_users,
			'synced'    => $total_synced,
			'pending'   => $this->get_pending_queue_count(),
			'failed'    => $this->get_failed_queue_count(),
			'sync_rate' => $sync_rate,
		];
	}

	/**
	 * Sync volume metrics for the last 24h/7d/30d.
	 */
	private function get_sync_activity_metrics(): array {
		return [
			'last_24h' => $this->count_logs_since( '-24 hours' ),
			'last_7d'  => $this->count_logs_since( '-7 days' ),
			'last_30d' => $this->count_logs_since( '-30 days' ),
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
	 * Collect recent activity from sync logs for current location.
	 */
	private function get_recent_activity(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ghl_sync_log';
		$site  = get_current_blog_id();

		// Get recent activity for users in this location
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fetching latest sync log entries for dashboard.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.sync_type, l.message, l.status, l.created_at 
				FROM {$table} l
				INNER JOIN {$wpdb->usermeta} um ON l.item_id = um.user_id AND um.meta_key = %s
				WHERE l.site_id = %d AND l.sync_type = 'user' AND um.meta_value != ''
				ORDER BY l.created_at DESC LIMIT 5",
				$this->contact_meta_key,
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

	/**
	 * Get count of WordPress users who have been synced to GHL
	 * for the current location (have location-specific _ghl_contact_id_{location_id} meta)
	 *
	 * @return int Number of synced users
	 */
	private function get_synced_users_count(): int {
		if ( empty( $this->contact_meta_key ) ) {
			return 0;
		}

		$users = get_users(
			[
				'meta_key'     => $this->contact_meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'   => '', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare' => '!=',
				'fields'       => 'ID',
				'number'       => -1,
			]
		);

		return count( $users );
	}

	/**
	 * Get total contacts from GoHighLevel API
	 * Falls back to synced user count if API call fails
	 *
	 * @return int Total contacts in GHL
	 */
	private function get_total_ghl_contacts(): int {
		// Try to get from cache first (5 minute cache)
		$cache_key = 'ghl_total_contacts_count';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		try {
			// Attempt to get real count from GHL API
			$client = \GHL_CRM\API\Client\Client::get_instance();

			// Query contacts with limit 1 to get total from pagination
			$response = $client->get(
				'contacts/',
				[
					'limit' => 1,
				]
			);

			// Store raw response for debugging
			set_transient( 'ghl_raw_contacts_response', $response, 5 * MINUTE_IN_SECONDS );

			// GHL API returns total count in response
			if ( isset( $response['meta']['total'] ) ) {
				$total = (int) $response['meta']['total'];
				set_transient( $cache_key, $total, 5 * MINUTE_IN_SECONDS );
				return $total;
			}

			// Fallback: If meta not available but contacts array exists
			if ( isset( $response['contacts'] ) && is_array( $response['contacts'] ) ) {
				// This is not accurate for total, but better than nothing
				// Cache for shorter time since it's not accurate
				$count = $this->get_synced_users_count();
				set_transient( $cache_key, $count, MINUTE_IN_SECONDS );
				return $count;
			}
		} catch ( \Exception $e ) {
			// API call failed - use fallback
			set_transient( 'ghl_raw_contacts_response', [ 'error' => $e->getMessage() ], 5 * MINUTE_IN_SECONDS );
		}

		// Ultimate fallback: count of synced users
		return $this->get_synced_users_count();
	}

	private function get_total_contacts_placeholder(): int {
		// Deprecated - use get_total_ghl_contacts() instead
		return $this->get_total_ghl_contacts();
	}

	private function get_pending_queue_count(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_queue';
		$site  = get_current_blog_id();

		if ( empty( $this->contact_meta_key ) ) {
			return 0;
		}

		// Count pending items for users who belong to this location
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting pending queue rows for dashboard.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT q.id) 
				FROM {$table} q
				INNER JOIN {$wpdb->usermeta} um ON q.item_id = um.user_id AND um.meta_key = %s
				WHERE q.site_id = %d AND q.status = 'pending' AND q.item_type = 'user' AND um.meta_value != ''",
				$this->contact_meta_key,
				$site
			)
		);
	}

	/**
	 * Get count of failed items in queue for current location.
	 *
	 * @return int
	 */
	private function get_failed_queue_count(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_queue';
		$site  = get_current_blog_id();

		if ( empty( $this->contact_meta_key ) ) {
			return 0;
		}

		// Count failed items for users who belong to this location
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting failed queue rows for dashboard.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT q.id) 
				FROM {$table} q
				INNER JOIN {$wpdb->usermeta} um ON q.item_id = um.user_id AND um.meta_key = %s
				WHERE q.site_id = %d AND q.status = 'failed' AND q.item_type = 'user' AND um.meta_value != ''",
				$this->contact_meta_key,
				$site
			)
		);
	}

	private function count_logs_since( string $relative_time ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_log';
		$site  = get_current_blog_id();

		if ( empty( $this->contact_meta_key ) ) {
			return 0;
		}

		$base_timestamp = current_time( 'timestamp' );
		$target_time    = strtotime( $relative_time, $base_timestamp );

		if ( false === $target_time ) {
			return 0;
		}

		$datetime = function_exists( 'wp_date' )
			? wp_date( 'Y-m-d H:i:s', $target_time )
			: date_i18n( 'Y-m-d H:i:s', $target_time );

		// Count logs for users who belong to this location
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregating log counts within rolling windows for dashboard metrics.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT l.id) 
				FROM {$table} l
				INNER JOIN {$wpdb->usermeta} um ON l.item_id = um.user_id AND um.meta_key = %s
				WHERE l.site_id = %d AND l.created_at >= %s AND l.sync_type = 'user' AND um.meta_value != ''",
				$this->contact_meta_key,
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

		return 'https://app.gohighlevel.com';
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
	 * Get analytics data for charts
	 *
	 * @return array Analytics data for dashboard charts.
	 */
	public function get_analytics_data(): array {
		return [
			'daily_activity'        => $this->get_daily_activity( 30 ),
			'sync_type_breakdown'   => $this->get_sync_type_breakdown(),
			'hourly_activity'       => $this->get_hourly_activity(),
			'success_failure_rates' => $this->get_success_failure_rates( 7 ),
		];
	}

	/**
	 * Get daily sync activity for the last N days
	 *
	 * @param int $days Number of days to retrieve.
	 * @return array Array with dates and counts.
	 */
	private function get_daily_activity( int $days = 30 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_log';
		$site  = get_current_blog_id();

		$start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(*) as count, status
				FROM {$table}
				WHERE site_id = %d AND created_at >= %s
				GROUP BY DATE(created_at), status
				ORDER BY date ASC",
				$site,
				$start_date
			),
			ARRAY_A
		);

		// Format data for Chart.js
		$activity_data = [];
		$dates         = [];
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$dates[] = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
		}

		foreach ( $dates as $date ) {
			$activity_data[ $date ] = [
				'success' => 0,
				'failed'  => 0,
			];
		}

		foreach ( $results as $row ) {
			$date   = $row['date'];
			$status = $row['status'];
			$count  = (int) $row['count'];

			if ( isset( $activity_data[ $date ] ) && in_array( $status, [ 'success', 'failed' ], true ) ) {
				$activity_data[ $date ][ $status ] = $count;
			}
		}

		return $activity_data;
	}

	/**
	 * Get sync breakdown by type (user, order, group)
	 *
	 * @return array Type breakdown with counts.
	 */
	private function get_sync_type_breakdown(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_log';
		$site  = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sync_type, COUNT(*) as count
				FROM {$table}
				WHERE site_id = %d
				GROUP BY sync_type",
				$site
			),
			ARRAY_A
		);

		$breakdown = [];
		foreach ( $results as $row ) {
			$type               = $row['sync_type'] ?? 'unknown';
			$breakdown[ $type ] = (int) $row['count'];
		}

		return $breakdown;
	}

	/**
	 * Get hourly sync activity (last 24 hours)
	 *
	 * @return array Hourly activity counts.
	 */
	private function get_hourly_activity(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_log';
		$site  = get_current_blog_id();

		$start_time = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT HOUR(created_at) as hour, COUNT(*) as count
				FROM {$table}
				WHERE site_id = %d AND created_at >= %s
				GROUP BY HOUR(created_at)
				ORDER BY hour ASC",
				$site,
				$start_time
			),
			ARRAY_A
		);

		// Create array for all 24 hours
		$hourly_data = array_fill( 0, 24, 0 );

		foreach ( $results as $row ) {
			$hour                 = (int) $row['hour'];
			$count                = (int) $row['count'];
			$hourly_data[ $hour ] = $count;
		}

		return $hourly_data;
	}

	/**
	 * Get success/failure rates over the last N days
	 *
	 * @param int $days Number of days.
	 * @return array Daily success and failure percentages.
	 */
	private function get_success_failure_rates( int $days = 7 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_log';
		$site  = get_current_blog_id();

		$start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date, status, COUNT(*) as count
				FROM {$table}
				WHERE site_id = %d AND created_at >= %s
				GROUP BY DATE(created_at), status
				ORDER BY date ASC",
				$site,
				$start_date
			),
			ARRAY_A
		);

		$rates = [];
		$dates = [];
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$dates[] = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
		}

		foreach ( $dates as $date ) {
			$rates[ $date ] = [
				'success_rate' => 0,
				'failure_rate' => 0,
			];
		}

		$daily_totals = [];
		foreach ( $results as $row ) {
			$date = $row['date'];
			if ( ! isset( $daily_totals[ $date ] ) ) {
				$daily_totals[ $date ] = [
					'success' => 0,
					'failed'  => 0,
				];
			}
			$daily_totals[ $date ][ $row['status'] ] = (int) $row['count'];
		}

		foreach ( $daily_totals as $date => $totals ) {
			$total = $totals['success'] + $totals['failed'];
			if ( $total > 0 ) {
				$rates[ $date ]['success_rate'] = round( ( $totals['success'] / $total ) * 100, 1 );
				$rates[ $date ]['failure_rate'] = round( ( $totals['failed'] / $total ) * 100, 1 );
			}
		}

		return $rates;
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
				$stats['failed_total']    = $total;
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
