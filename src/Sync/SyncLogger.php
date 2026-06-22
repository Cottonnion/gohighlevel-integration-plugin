<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GHL_CRM\Sync\SyncStats;

/**
 * Sync Logger
 *
 * Handles logging of synchronization activities to the database
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 */
class SyncLogger {
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
		// Constructor is empty
	}

	/**
	 * Log a sync activity
	 *
	 * @param string $sync_type    Type of sync (user, order, contact, etc.)
	 * @param int    $item_id      WordPress item ID
	 * @param string $action       Action performed (create, update, delete)
	 * @param string $status       Status (success, failed, pending)
	 * @param string $message      Log message
	 * @param array  $metadata     Additional metadata (optional)
	 * @param string $ghl_id       GoHighLevel contact/object ID (optional)
	 * @return int|false Log ID on success, false on failure
	 */
	public function log( string $sync_type, int $item_id, string $action, string $status, string $message, array $metadata = [], string $ghl_id = '' ) {
		// Check if sync logging is enabled
		if ( ! \GHL_CRM\Core\SettingsManager::is_sync_logging_enabled() ) {
			return false;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'ghl_sync_log';

		$metadata = $this->sanitize_metadata( $metadata );

		$data = [
			'sync_type'  => sanitize_text_field( $sync_type ),
			'item_id'    => $item_id,
			'action'     => sanitize_text_field( $action ),
			'status'     => sanitize_text_field( $status ),
			'message'    => sanitize_text_field( $message ),
			'metadata'   => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
			'ghl_id'     => sanitize_text_field( $ghl_id ),
			'created_at' => current_time( 'mysql' ),
			'site_id'    => get_current_blog_id(),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Logging sync events directly to plugin-managed table.
		$result = $wpdb->insert( $table_name, $data );

		if ( $result === false ) {

			return false;
		}

		// Track aggregate sync stats for dashboard insights.
		try {
			$stats = SyncStats::get_instance();
			$stats->record_event( $data['status'], $data['sync_type'], $data['created_at'] );
		} catch ( \Throwable $th ) {
			// Swallow errors to avoid blocking sync logging if stats persistence fails.
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Redact sensitive values from metadata before persistence.
	 *
	 * @param array $metadata Metadata payload.
	 * @return array
	 */
	private function sanitize_metadata( array $metadata ): array {
		$sensitive_keys = [
			'authorization',
			'access_token',
			'oauth_access_token',
			'oauth_refresh_token',
			'refresh_token',
			'api_token',
			'token',
			'secret',
			'client_secret',
		];

		foreach ( $metadata as $key => $value ) {
			$normalized_key = strtolower( (string) $key );

			if ( is_array( $value ) ) {
				$metadata[ $key ] = $this->sanitize_metadata( $value );
				continue;
			}

			if ( in_array( $normalized_key, $sensitive_keys, true ) ) {
				$metadata[ $key ] = '[REDACTED]';
				continue;
			}

			if ( 'authorization' === $normalized_key && is_string( $value ) ) {
				$metadata[ $key ] = '[REDACTED]';
			}
		}

		return $metadata;
	}

	/**
	 * Log a successful sync
	 *
	 * @param string $sync_type Type of sync
	 * @param int    $item_id   WordPress item ID
	 * @param string $action    Action performed
	 * @param string $ghl_id    GoHighLevel ID
	 * @param array  $metadata  Additional metadata
	 * @return int|false
	 */
	public function log_success( string $sync_type, int $item_id, string $action, string $ghl_id = '', array $metadata = [] ) {
		$message = sprintf(
			/* translators: 1: Sync type, 2: Action, 3: Item ID */
			__( '%1$s %2$s completed successfully (ID: %3$d)', 'syncly' ),
			ucfirst( $sync_type ),
			$action,
			$item_id
		);

		return $this->log( $sync_type, $item_id, $action, 'success', $message, $metadata, $ghl_id );
	}

	/**
	 * Log a failed sync
	 *
	 * @param string $sync_type     Type of sync
	 * @param int    $item_id       WordPress item ID
	 * @param string $action        Action attempted
	 * @param string $error_message Error message
	 * @param array  $metadata      Additional metadata
	 * @return int|false
	 */
	public function log_failure( string $sync_type, int $item_id, string $action, string $error_message, array $metadata = [] ) {
		$message = sprintf(
			/* translators: 1: Sync type, 2: Action, 3: Error message */
			__( '%1$s %2$s failed: %3$s', 'syncly' ),
			ucfirst( $sync_type ),
			$action,
			$error_message
		);

		return $this->log( $sync_type, $item_id, $action, 'failed', $message, $metadata );
	}

	/**
	 * Get sync logs with filters
	 *
	 * @param array $args Query arguments
	 * @return array
	 */
	public function get_logs( array $args = [] ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ghl_sync_log';

		// Default arguments
		$defaults = [
			'sync_type' => '',
			'status'    => '',
			'item_id'   => 0,
			'limit'     => 50,
			'offset'    => 0,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause
		$where   = [ '1=1' ];
		$where[] = $wpdb->prepare( 'site_id = %d', get_current_blog_id() );

		if ( ! empty( $args['sync_type'] ) ) {
			$where[] = $wpdb->prepare( 'sync_type = %s', $args['sync_type'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		if ( ! empty( $args['item_id'] ) ) {
			$where[] = $wpdb->prepare( 'item_id = %d', $args['item_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]     = $wpdb->prepare( '(action LIKE %s OR message LIKE %s OR sync_type LIKE %s)', $search_term, $search_term, $search_term );
		}

		$where_clause = implode( ' AND ', $where );

		// Build ORDER BY clause
		$allowed_orderby = [ 'id', 'created_at', 'sync_type', 'status' ];
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Build query - phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$args['limit'],
			$args['offset']
		);

		// Execute query
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Retrieving log entries from plugin-managed table.
		$results = $wpdb->get_results(
			$query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return $results ?: [];
	}

	/**
	 * Get total log count with filters
	 *
	 * @param array $args Query arguments
	 * @return int
	 */
	public function get_log_count( array $args = [] ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ghl_sync_log';

		// Build WHERE clause
		$where   = [ '1=1' ];
		$where[] = $wpdb->prepare( 'site_id = %d', get_current_blog_id() );

		if ( ! empty( $args['sync_type'] ) ) {
			$where[] = $wpdb->prepare( 'sync_type = %s', $args['sync_type'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		if ( ! empty( $args['item_id'] ) ) {
			$where[] = $wpdb->prepare( 'item_id = %d', $args['item_id'] );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting log entries in plugin-managed table.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}" );

		return (int) $count;
	}

	/**
	 * Clear old logs (older than X days)
	 *
	 * @param int $days Number of days to keep
	 * @return int|false Number of rows deleted or false on failure
	 */
	public function clear_old_logs( int $days = 30 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ghl_sync_log';
		$date_limit = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Purging old entries from plugin log table.
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s AND site_id = %d",
				$date_limit,
				get_current_blog_id()
			)
		);
	}

	/**
	 * Clear all logs
	 *
	 * @return int|false Number of rows deleted or false on failure
	 */
	public function clear_all_logs() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ghl_sync_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Clearing plugin log table for current site.
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE site_id = %d",
				get_current_blog_id()
			)
		);
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
