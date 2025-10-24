<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue Manager
 *
 * Manages async queue for all integrations (Users, WooCommerce, BuddyBoss, LearnDash)
 * Fully multisite-compatible with per-site queues
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 */
class QueueManager {
	/**
	 * Max retry attempts
	 */
	private const MAX_ATTEMPTS = 3;

	/**
	 * Batch size for processing
	 */
	private const BATCH_SIZE = 10;

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
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Register Action Scheduler hook for queue processing
		add_action( 'ghl_crm_process_queue', [ $this, 'process_queue' ] );

		// Schedule recurring action via Action Scheduler (runs every minute)
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			if ( false === as_next_scheduled_action( 'ghl_crm_process_queue' ) ) {
				as_schedule_recurring_action( time(), 60, 'ghl_crm_process_queue', [], 'ghl-crm' );
			}
		} else {
			// Fallback to WP-Cron if Action Scheduler not available
			if ( ! wp_next_scheduled( 'ghl_crm_process_queue' ) ) {
				wp_schedule_event( time(), 'every_minute', 'ghl_crm_process_queue' );
			}
		}
	}

	/**
	 * Add item to queue
	 * Prevents duplicates: Updates payload if pending item exists (ensures latest data synced)
	 * Multisite-aware: Uses current site's table prefix
	 *
	 * @param string $item_type Item type (user, order, group, course, etc.)
	 * @param int    $item_id   Item ID
	 * @param string $action    Action (create, update, delete, etc.)
	 * @param array  $payload   Data payload
	 * @return int|false Queue item ID or false on failure
	 */
	public function add_to_queue( string $item_type, int $item_id, string $action, array $payload ) {
		global $wpdb;

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		// Check for existing pending item
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} 
				WHERE item_type = %s 
				AND item_id = %d 
				AND action = %s 
				AND site_id = %d
				AND status = 'pending' 
				LIMIT 1",
				$item_type,
				$item_id,
				$action,
				$current_site_id
			)
		);

		// If duplicate exists, UPDATE payload with latest data (don't create new row)
		if ( $existing ) {
			$updated = $wpdb->update(
				$table_name,
				[
					'payload'    => wp_json_encode( $payload ),
					'updated_at' => current_time( 'mysql' ),
				],
				[ 'id' => $existing ],
				[ '%s', '%s' ],
				[ '%d' ]
			);

			return $updated ? (int) $existing : false;
		}

		// Safety check: Limit queue size per site (prevent bloat)
		$queue_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE site_id = %d AND status = 'pending'",
				$current_site_id
			)
		);

		if ( $queue_count >= 10000 ) { // Max 10k pending items per site
			error_log(
				sprintf(
					'GHL CRM Queue Limit Reached [Site %d]: Cannot add more items. Current pending: %d',
					$current_site_id,
					$queue_count
				)
			);
			return false;
		}

		// Insert new queue item
		$inserted = $wpdb->insert(
			$table_name,
			[
				'item_type'  => $item_type,
				'item_id'    => $item_id,
				'action'     => $action,
				'payload'    => wp_json_encode( $payload ),
				'status'     => 'pending',
				'attempts'   => 0,
				'created_at' => current_time( 'mysql' ),
				'site_id'    => $current_site_id,
			],
			[
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%d',
			]
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Process queue (run by WP-Cron)
	 * Multisite-aware: Processes queues for all sites
	 * Safety: Prevents concurrent processing with transient lock
	 *
	 * @return void
	 */
	public function process_queue(): void {
		// Prevent concurrent processing (race condition protection)
		$lock_key = 'ghl_crm_queue_processing';
		if ( get_transient( $lock_key ) ) {
			return; // Already processing
		}

		// Set lock for 2 minutes
		set_transient( $lock_key, time(), 2 * MINUTE_IN_SECONDS );

		try {
			if ( is_multisite() ) {
				// Process each site's queue
				$sites = get_sites(
					[
						'number' => 999,
					]
				);

				foreach ( $sites as $site ) {
					switch_to_blog( $site->blog_id );
					$this->process_site_queue();
					restore_current_blog();
				}
			} else {
				$this->process_site_queue();
			}
		} finally {
			// Always release lock
			delete_transient( $lock_key );
		}
	}

	/**
	 * Process queue for current site
	 *
	 * @return void
	 */
	private function process_site_queue(): void {
		global $wpdb;

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		// Clean up stale items first (stuck in processing for >5 minutes)
		$this->cleanup_stale_items();

		// Get pending items for current site
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} 
				WHERE status = 'pending' 
				AND site_id = %d
				AND attempts < %d 
				ORDER BY created_at ASC 
				LIMIT %d",
				$current_site_id,
				self::MAX_ATTEMPTS,
				self::BATCH_SIZE
			)
		);

		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			$this->process_queue_item( $item );
		}
	}

	/**
	 * Clean up stale items that got stuck in processing
	 * Safety mechanism: Reset items that haven't completed in 5 minutes
	 *
	 * @return void
	 */
	private function cleanup_stale_items(): void {
		global $wpdb;

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		// Reset items that have been processing for too long
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} 
				SET status = 'pending', 
					updated_at = %s 
				WHERE status = 'pending' 
				AND site_id = %d
				AND updated_at < %s 
				AND attempts > 0 
				AND attempts < %d",
				current_time( 'mysql' ),
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-5 minutes' ) ),
				self::MAX_ATTEMPTS
			)
		);
	}

	/**
	 * Process single queue item
	 *
	 * @param object $item Queue item
	 * @return void
	 */
	private function process_queue_item( object $item ): void {
		global $wpdb;

		$table_name = $this->get_queue_table_name();
		$start_time = microtime( true );

		// Increment attempts
		$wpdb->update(
			$table_name,
			[
				'attempts'   => $item->attempts + 1,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $item->id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		try {
			$payload = json_decode( $item->payload, true );

			// Execute sync based on item type
			$result = $this->execute_sync( $item->item_type, $item->action, $item->item_id, $payload );

			if ( $result ) {
				// Mark as completed
				$wpdb->update(
					$table_name,
					[
						'status'       => 'completed',
						'processed_at' => current_time( 'mysql' ),
						'updated_at'   => current_time( 'mysql' ),
					],
					[ 'id' => $item->id ],
					[ '%s', '%s', '%s' ],
					[ '%d' ]
				);

				// Log success
				$this->log_sync_event(
					$item->item_id,
					$item->action,
					'success',
					null,
					null,
					microtime( true ) - $start_time
				);
			} else {
				throw new \Exception( 'Sync execution returned false' );
			}
		} catch ( \Exception $e ) {
			// Mark as failed if max attempts reached
			$status = ( $item->attempts + 1 >= self::MAX_ATTEMPTS ) ? 'failed' : 'pending';

			$wpdb->update(
				$table_name,
				[
					'status'        => $status,
					'error_message' => $e->getMessage(),
					'updated_at'    => current_time( 'mysql' ),
				],
				[ 'id' => $item->id ],
				[ '%s', '%s', '%s' ],
				[ '%d' ]
			);

			// Log error
			$this->log_sync_event(
				$item->item_id,
				$item->action,
				'error',
				null,
				$e->getMessage(),
				microtime( true ) - $start_time
			);
		}
	}

	/**
	 * Execute sync action
	 *
	 * @param string $item_type Item type
	 * @param string $action    Action
	 * @param int    $item_id   Item ID
	 * @param array  $payload   Payload data
	 * @return bool Success status
	 * @throws \Exception
	 */
	private function execute_sync( string $item_type, string $action, int $item_id, array $payload ): bool {
		// Route to appropriate integration handler
		switch ( $item_type ) {
			case 'user':
				return $this->execute_user_sync( $action, $item_id, $payload );

			case 'order':
				// Future: WooCommerce order sync
				return apply_filters( 'ghl_crm_execute_order_sync', false, $action, $item_id, $payload );

			case 'group':
				// Future: BuddyBoss group sync
				return apply_filters( 'ghl_crm_execute_group_sync', false, $action, $item_id, $payload );

			case 'course':
				// Future: LearnDash course sync
				return apply_filters( 'ghl_crm_execute_course_sync', false, $action, $item_id, $payload );

			default:
				// Allow third-party extensions
				return apply_filters( 'ghl_crm_execute_sync', false, $item_type, $action, $item_id, $payload );
		}
	}

	/**
	 * Execute user sync
	 *
	 * @param string $action  Action
	 * @param int    $user_id User ID
	 * @param array  $payload Payload data
	 * @return bool Success status
	 * @throws \Exception
	 */
	private function execute_user_sync( string $action, int $user_id, array $payload ): bool {
		$client           = \GHL_CRM\API\Client\Client::get_instance();
		$contact_resource = new \GHL_CRM\API\Resources\ContactResource( $client );

		switch ( $action ) {
			case 'user_register':
			case 'profile_update':
				// Check cache first (15 min TTL)
				$cached_contact = $this->get_cached_contact( $payload['email'] ?? '' );

				if ( $cached_contact ) {
					// Update existing contact
					$result = $contact_resource->update( $cached_contact['id'], $payload );
				} else {
					// Find or create contact
					$existing = $contact_resource->find_by_email( $payload['email'] );

					if ( $existing ) {
						// Cache and update
						$this->cache_contact( $payload['email'], $existing );
						$result = $contact_resource->update( $existing['id'], $payload );
					} else {
						// Create new
						$result = $contact_resource->create( $payload );
						if ( $result && isset( $result['contact']['id'] ) ) {
							$this->cache_contact( $payload['email'], $result['contact'] );
						}
					}
				}

				return ! empty( $result );

			case 'delete_user':
				if ( ! empty( $payload['delete'] ) ) {
					$contact = $contact_resource->find_by_email( $payload['email'] );
					if ( $contact ) {
						$contact_resource->delete( $contact['id'] );
						$this->delete_cached_contact( $payload['email'] );
					}
				}
				return true;

			case 'user_login':
				// Update last_login custom field
				$contact = $this->get_cached_contact( $payload['email'] ?? '' );
				if ( ! $contact ) {
					$contact = $contact_resource->find_by_email( $payload['email'] );
					if ( $contact ) {
						$this->cache_contact( $payload['email'], $contact );
					}
				}

				if ( $contact ) {
					$result = $contact_resource->update(
						$contact['id'],
						[
							'customField' => [
								'last_login' => $payload['last_login'] ?? current_time( 'mysql' ),
							],
						]
					);
					return ! empty( $result );
				}
				return false;

			default:
				throw new \Exception( 'Unknown user action: ' . $action );
		}
	}

	/**
	 * Get cached contact (using transients)
	 *
	 * @param string $email Email address
	 * @return array|null Contact data or null if not cached
	 */
	private function get_cached_contact( string $email ): ?array {
		if ( empty( $email ) ) {
			return null;
		}

		$cache_key = 'ghl_contact_' . md5( strtolower( $email ) );
		$cached    = get_transient( $cache_key );

		return $cached ? $cached : null;
	}

	/**
	 * Cache contact data (using transients)
	 *
	 * @param string $email   Email address
	 * @param array  $contact Contact data
	 * @return bool Success status
	 */
	private function cache_contact( string $email, array $contact ): bool {
		if ( empty( $email ) || empty( $contact ) ) {
			return false;
		}

		$cache_key = 'ghl_contact_' . md5( strtolower( $email ) );
		return set_transient( $cache_key, $contact, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Delete cached contact
	 *
	 * @param string $email Email address
	 * @return bool Success status
	 */
	private function delete_cached_contact( string $email ): bool {
		if ( empty( $email ) ) {
			return false;
		}

		$cache_key = 'ghl_contact_' . md5( strtolower( $email ) );
		return delete_transient( $cache_key );
	}

	/**
	 * Log sync event to database
	 * Multisite-aware: Uses current site's table
	 *
	 * @param int         $user_id        User ID
	 * @param string      $action         Action
	 * @param string      $status         Status (success/error)
	 * @param string|null $contact_id     Contact ID
	 * @param string|null $error_message  Error message
	 * @param float|null  $execution_time Execution time in seconds
	 * @return void
	 */
	private function log_sync_event(
		int $user_id,
		string $action,
		string $status,
		?string $contact_id = null,
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
	 * Get queue status
	 * Multisite-aware: Returns stats for current site
	 *
	 * @return array Queue statistics with health indicators
	 */
	public function get_queue_status(): array {
		global $wpdb;

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' AND site_id = %d",
				$current_site_id
			)
		);

		$failed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed' AND site_id = %d",
				$current_site_id
			)
		);

		$completed_24h = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'completed' AND site_id = %d AND processed_at > %s",
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);

		$total_items = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE site_id = %d",
				$current_site_id
			)
		);

		// Get oldest pending item age (minutes)
		$oldest_pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT TIMESTAMPDIFF(MINUTE, created_at, NOW()) as age 
				FROM {$table_name} 
				WHERE status = 'pending' AND site_id = %d 
				ORDER BY created_at ASC 
				LIMIT 1",
				$current_site_id
			)
		);

		// Health check
		$health   = 'good';
		$warnings = [];

		if ( $pending > 1000 ) {
			$health     = 'warning';
			$warnings[] = sprintf( 'High pending count: %d items', $pending );
		}

		if ( $pending > 5000 ) {
			$health     = 'critical';
			$warnings[] = 'Critical: Queue severely backed up';
		}

		if ( $failed > 100 ) {
			$health     = ( $health === 'critical' ) ? 'critical' : 'warning';
			$warnings[] = sprintf( 'High failure rate: %d failed items', $failed );
		}

		if ( $oldest_pending && $oldest_pending > 60 ) {
			$health     = ( $health === 'critical' ) ? 'critical' : 'warning';
			$warnings[] = sprintf( 'Oldest pending item: %d minutes old', $oldest_pending );
		}

		if ( $total_items > 50000 ) {
			$warnings[] = sprintf( 'Large queue table: %d total rows (cleanup recommended)', $total_items );
		}

		return [
			'pending'                => (int) $pending,
			'failed'                 => (int) $failed,
			'completed_24h'          => (int) $completed_24h,
			'total_items'            => (int) $total_items,
			'oldest_pending_minutes' => (int) $oldest_pending,
			'health'                 => $health,
			'warnings'               => $warnings,
			'site_id'                => $current_site_id,
			'max_queue_limit'        => 10000,
		];
	}

	/**
	 * Get queue table name (multisite-aware)
	 *
	 * @return string Table name with prefix
	 */
	private function get_queue_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ghl_sync_queue';
	}

	/**
	 * Get log table name (multisite-aware)
	 *
	 * @return string Table name with prefix
	 */
	private function get_log_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'ghl_sync_log';
	}

	/**
	 * Check if Action Scheduler is available
	 *
	 * @return bool
	 */
	public static function is_action_scheduler_available(): bool {
		return function_exists( 'as_schedule_recurring_action' );
	}

	/**
	 * Get scheduler type being used
	 *
	 * @return string 'action_scheduler' or 'wp_cron'
	 */
	public static function get_scheduler_type(): string {
		return self::is_action_scheduler_available() ? 'action_scheduler' : 'wp_cron';
	}

	/**
	 * Initialize (called by Loader)
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}

	/**
	 * Unschedule all actions (for deactivation)
	 *
	 * @return void
	 */
	public static function unschedule_actions(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'ghl_crm_process_queue', [], 'ghl-crm' );
		} else {
			// Fallback: Clear WP-Cron
			$timestamp = wp_next_scheduled( 'ghl_crm_process_queue' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'ghl_crm_process_queue' );
			}
		}
	}
}
