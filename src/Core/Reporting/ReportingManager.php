<?php
declare(strict_types=1);

namespace GHL_CRM\Core\Reporting;

use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reporting Manager
 *
 * Captures telemetry events locally and dispatches them in batches to a remote endpoint
 * when the site owner has explicitly opted in.
 */
class ReportingManager {
	/**
	 * Action Scheduler / WP-Cron hook for dispatching queued reports.
	 */
	private const DISPATCH_HOOK = 'ghl_crm_send_reporting_events';

	/**
	 * Remote endpoint for batched telemetry payloads.
	 */
	private const ENDPOINT = 'https://highlevelsync.com/wp-json/tmt/v1/events';

	/**
	 * Maximum events to send per batch.
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Default dispatch interval in seconds (15 minutes).
	 */
	private const DISPATCH_INTERVAL = 900;

	/**
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
	 * Constructor
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'maybe_schedule_dispatch' ] );
		add_action( 'shutdown', [ $this, 'capture_fatal_error' ] );
		add_action( self::DISPATCH_HOOK, [ $this, 'dispatch_events' ] );

		// Allow other components to log events through a shared action.
		add_action( 'ghl_crm_log_event', [ $this, 'log_event' ], 10, 4 );
	}

	/**
	 * Check if telemetry is enabled (opt-in).
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return ! empty( $this->settings_manager->get_setting( 'enable_telemetry_reporting', false ) );
	}

	/**
	 * Log an event to the local reporting table.
	 *
	 * @param string $event_type Event category key.
	 * @param string $message    Human-readable message (sanitized).
	 * @param array  $context    Additional context (anonymized) to encode as JSON.
	 * @param string $severity   Severity level (info|warning|error|critical).
	 * @return bool True if stored, false otherwise.
	 */
	public function log_event( string $event_type, string $message, array $context = [], string $severity = 'info' ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		// Attach truncated backtrace for error/critical events.
		if ( in_array( $severity, [ 'error', 'critical' ], true ) && empty( $context['backtrace'] ) ) {
			$context['backtrace'] = $this->get_truncated_backtrace();
		}

		global $wpdb;

		$table = $wpdb->prefix . 'ghl_reporting_events';

		$sanitized_message = wp_strip_all_tags( $message );
		$sanitized_context = $this->sanitize_context( $context );
		$now               = current_time( 'mysql' );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			[
				'event_type' => sanitize_text_field( $event_type ),
				'severity'   => sanitize_text_field( $severity ),
				'message'    => $sanitized_message,
				'context'    => wp_json_encode( $sanitized_context ),
				'status'     => 'pending',
				'attempts'   => 0,
				'sent_at'    => null,
				'created_at' => $now,
				'site_id'    => get_current_blog_id(),
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%d',
			]
		);

		return (bool) $inserted;
	}

	/**
	 * Capture fatal errors on shutdown and log them for reporting.
	 *
	 * @return void
	 */
	public function capture_fatal_error(): void {
		$last_error = error_get_last();

		if ( ! $this->is_enabled() || empty( $last_error ) ) {
			return;
		}

		$fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ];

		if ( ! in_array( $last_error['type'], $fatal_types, true ) ) {
			return;
		}

		$file = $last_error['file'] ?? '';

		// Only log if the error file is inside either free or pro plugin directory
		$plugin_dirs = [
			WP_CONTENT_DIR . '/plugins/ghl-crm-integration',
			WP_CONTENT_DIR . '/plugins/ghl-crm-integration-pro',
		];
		$in_plugin   = false;
		foreach ( $plugin_dirs as $dir ) {
			if ( strpos( $file, $dir ) !== false ) {
				$in_plugin = true;
				break;
			}
		}
		if ( ! $in_plugin ) {
			return;
		}

		$context = [
			'file'    => $file,
			'line'    => isset( $last_error['line'] ) ? (int) $last_error['line'] : 0,
			'plugin'  => 'ghl-crm-integration',
			'php'     => PHP_VERSION,
			'wp'      => get_bloginfo( 'version' ),
			'site_id' => get_current_blog_id(),
		];

		$this->log_event( 'fatal_error', $last_error['message'] ?? 'Fatal error', $context, 'critical' );
	}

	/**
	 * Dispatch pending events to the remote endpoint.
	 *
	 * @return void
	 */
	public function dispatch_events(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'ghl_reporting_events';

		$pending_events = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND site_id = %d ORDER BY id ASC LIMIT %d",
				'pending',
				get_current_blog_id(),
				self::BATCH_SIZE
			),
			ARRAY_A
		);

		if ( empty( $pending_events ) ) {
			return;
		}

		$payload = [
			'site_url'       => esc_url_raw( home_url() ),
			'plugin_version' => defined( 'GHL_CRM_VERSION' ) ? GHL_CRM_VERSION : 'unknown',
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'multisite'      => is_multisite(),
			'environment'    => $this->get_environment_snapshot(),
			'active_plugins' => $this->get_active_plugin_slugs(),
			'features'       => $this->get_feature_flags(),
			'events'         => array_map( [ $this, 'transform_event_for_payload' ], $pending_events ),
		];

		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'timeout' => 10,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->mark_batch_failed( $pending_events );
			return;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			$this->mark_batch_failed( $pending_events );
			return;
		}

		$this->mark_batch_sent( $pending_events );
	}

	/**
	 * Schedule the dispatch job if not already scheduled.
	 *
	 * @return void
	 */
	public function maybe_schedule_dispatch(): void {
		if ( ! $this->is_enabled() ) {
			$this->unschedule_dispatch();
			return;
		}

		$as_ready       = function_exists( 'as_next_scheduled_action' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized();
		$next_scheduled = $as_ready ? as_next_scheduled_action( self::DISPATCH_HOOK ) : wp_next_scheduled( self::DISPATCH_HOOK );

		if ( $next_scheduled ) {
			return;
		}

		$first_run = time() + self::DISPATCH_INTERVAL;

		if ( $as_ready ) {
			as_schedule_recurring_action( $first_run, self::DISPATCH_INTERVAL, self::DISPATCH_HOOK, [], 'ghl-crm' );
			return;
		}

		wp_schedule_event( $first_run, 'ghl_crm_15min', self::DISPATCH_HOOK );
	}

	/**
	 * Unschedule dispatch job.
	 *
	 * @return void
	 */
	public function unschedule_dispatch(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			as_unschedule_all_actions( self::DISPATCH_HOOK, [], 'ghl-crm' );
		}

		$timestamp = wp_next_scheduled( self::DISPATCH_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::DISPATCH_HOOK );
		}
	}

	/**
	 * Transform stored row for payload.
	 *
	 * @param array $event Event row.
	 * @return array
	 */
	private function transform_event_for_payload( array $event ): array {
		return [
			'id'         => (int) $event['id'],
			'event_type' => sanitize_text_field( $event['event_type'] ),
			'severity'   => sanitize_text_field( $event['severity'] ),
			'message'    => wp_strip_all_tags( $event['message'] ),
			'context'    => $this->decode_context( $event['context'] ),
			'created_at' => $event['created_at'],
		];
	}

	/**
	 * Decode context JSON safely.
	 *
	 * @param string|null $context_json Context JSON string.
	 * @return array
	 */
	private function decode_context( ?string $context_json ): array {
		if ( empty( $context_json ) ) {
			return [];
		}

		$decoded = json_decode( $context_json, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Sanitize context array recursively.
	 *
	 * @param array $context Context data.
	 * @return array
	 */
	private function sanitize_context( array $context ): array {
		$sanitized = [];

		foreach ( $context as $key => $value ) {
			$clean_key = sanitize_text_field( (string) $key );

			if ( is_scalar( $value ) || null === $value ) {
				$sanitized[ $clean_key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = $this->sanitize_context( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Get a snapshot of the server environment.
	 *
	 * @return array
	 */
	private function get_environment_snapshot(): array {
		return [
			'memory_limit'       => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : ini_get( 'memory_limit' ),
			'max_execution_time' => (int) ini_get( 'max_execution_time' ),
			'server_software'    => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'unknown',
			'is_ssl'             => is_ssl(),
			'locale'             => get_locale(),
			'timezone'           => wp_timezone_string(),
		];
	}

	/**
	 * Get active plugin slugs (directory names only, no paths).
	 *
	 * @return array<int, string>
	 */
	private function get_active_plugin_slugs(): array {
		$active = get_option( 'active_plugins', [] );

		return array_values(
			array_unique(
				array_map(
					function ( string $plugin ): string {
						return dirname( $plugin );
					},
					$active
				)
			)
		);
	}

	/**
	 * Get anonymized feature usage flags.
	 *
	 * @return array
	 */
	private function get_feature_flags(): array {
		$settings = $this->settings_manager->get_settings_array();

		$field_map_count = 0;
		if ( ! empty( $settings['user_field_mapping'] ) && is_array( $settings['user_field_mapping'] ) ) {
			$field_map_count = count( $settings['user_field_mapping'] );
		}

		return [
			'user_sync'          => ! empty( $settings['enable_user_sync'] ),
			'sync_logging'       => ! empty( $settings['enable_sync_logging'] ),
			'woocommerce'        => ! empty( $settings['wc_enabled'] ),
			'wc_abandoned_cart'  => ! empty( $settings['wc_abandoned_cart_enabled'] ),
			'wc_opportunities'   => ! empty( $settings['wc_opportunities_enabled'] ),
			'learndash'          => ! empty( $settings['learndash_enabled'] ),
			'buddyboss_groups'   => ! empty( $settings['buddyboss_groups_enabled'] ),
			'family_accounts'    => ! empty( $settings['enable_family_accounts'] ),
			'field_mapping_count' => $field_map_count,
			'queue_processor'    => class_exists( 'ActionScheduler' ) ? 'action_scheduler' : 'wp_cron',
			'pro_active'         => defined( 'GHL_CRM_PRO_VERSION' ),
		];
	}

	/**
	 * Get a truncated backtrace for error context.
	 *
	 * Returns the last 5 frames from this plugin only, with paths relative to the plugin root.
	 *
	 * @return array<int, array{file: string, line: int, function: string}>
	 */
	private function get_truncated_backtrace(): array {
		$raw_trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

		$plugin_dirs = [
			WP_CONTENT_DIR . '/plugins/ghl-crm-integration/',
			WP_CONTENT_DIR . '/plugins/ghl-crm-integration-pro/',
		];

		$frames = [];

		foreach ( $raw_trace as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}

			$in_plugin = false;
			foreach ( $plugin_dirs as $dir ) {
				if ( str_starts_with( $frame['file'], $dir ) ) {
					$frame['file'] = str_replace( $dir, '', $frame['file'] );
					$in_plugin     = true;
					break;
				}
			}

			if ( ! $in_plugin ) {
				continue;
			}

			$frames[] = [
				'file'     => $frame['file'],
				'line'     => (int) ( $frame['line'] ?? 0 ),
				'function' => $frame['function'] ?? '',
			];

			if ( count( $frames ) >= 5 ) {
				break;
			}
		}

		return $frames;
	}

	/**
	 * Mark batch as sent.
	 *
	 * @param array<int, array> $events Events that were dispatched.
	 * @return void
	 */
	private function mark_batch_sent( array $events ): void {
		global $wpdb;

		$table    = $wpdb->prefix . 'ghl_reporting_events';
		$ids      = wp_list_pluck( $events, 'id' );
		$id_place = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, sent_at = %s WHERE id IN ({$id_place})",
				array_merge( [ 'sent', current_time( 'mysql' ) ], $ids )
			)
		);
	}

	/**
	 * Mark batch as failed (increments attempts to avoid infinite retries).
	 *
	 * @param array<int, array> $events Events attempted.
	 * @return void
	 */
	private function mark_batch_failed( array $events ): void {
		global $wpdb;

		$table    = $wpdb->prefix . 'ghl_reporting_events';
		$ids      = wp_list_pluck( $events, 'id' );
		$id_place = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"UPDATE {$table} SET attempts = attempts + 1, status = CASE WHEN attempts >= 4 THEN 'failed' ELSE 'pending' END WHERE id IN ({$id_place})",
				$ids
			)
		);
	}
}
