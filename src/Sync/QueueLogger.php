<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue Logger
 *
 * Handles logging of sync events to database
 * Multisite-aware with per-site tables
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 */
class QueueLogger {
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
		// Intentionally empty
	}

	/**
	 * Log sync event to database
	 *
	 * @param int         $user_id        User ID
	 * @param string      $action         Action
	 * @param string      $status         Status (success/error)
	 * @param string|null $contact_id     Contact ID
	 * @param array|null  $request_data   Request payload
	 * @param array|null  $response_data  Response data
	 * @param string|null $error_message  Error message
	 * @param float|null  $execution_time Execution time in seconds
	 * @return void
	 */
	public function log_event(
		int $user_id,
		string $action,
		string $status,
		?string $contact_id = null,
		?array $request_data = null,
		?array $response_data = null,
		?string $error_message = null,
		?float $execution_time = null
	): void {
		global $wpdb;

		$table_name = $this->get_log_table_name();

		$wpdb->insert(
			$table_name,
			[
				'user_id'        => $user_id,
				'action'         => $action,
				'status'         => $status,
				'contact_id'     => $contact_id,
				'request_data'   => ! empty( $request_data ) ? wp_json_encode( $request_data ) : null,
				'response_data'  => ! empty( $response_data ) ? wp_json_encode( $response_data ) : null,
				'error_message'  => $error_message,
				'execution_time' => $execution_time,
				'created_at'     => current_time( 'mysql' ),
				'site_id'        => get_current_blog_id(),
			],
			[
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%f',
				'%s',
				'%d',
			]
		);

		// Also log errors to error_log
		if ( 'error' === $status ) {
			error_log(
				sprintf(
					'GHL CRM Sync Error [Site %d]: User %d, Action %s, Message: %s',
					get_current_blog_id(),
					$user_id,
					$action,
					$error_message ?? 'Unknown error'
				)
			);
		}
	}

	/**
	 * Get log table name (multisite-aware)
	 *
	 * @return string
	 */
	private function get_log_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ghl_sync_log';
	}
}
