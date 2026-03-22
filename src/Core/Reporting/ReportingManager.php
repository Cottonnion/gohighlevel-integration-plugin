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

		$context = [
			'file'    => $last_error['file'] ?? '',
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
