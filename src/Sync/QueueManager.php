<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

use GHL_CRM\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue Manager
 *
 * Manages async queue for all integrations (Users, WooCommerce, BuddyBoss, LearnDash)
 * Fully multisite-compatible with per-site queues
 *
 * REFACTORED: Now uses helper classes for separation of concerns
 * - RateLimiter: Handle API rate limiting
 * - ContactCache: Handle contact caching
 * - QueueProcessor: Handle sync execution
 * - QueueLogger: Handle logging
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
	private const BATCH_SIZE = 50;

	/**
	 * Queue processing interval in seconds
	 */
	private const PROCESSING_INTERVAL = 10;

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Rate limiter instance
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $rate_limiter;

	/**
	 * Contact cache instance
	 *
	 * @var ContactCache
	 */
	private ContactCache $contact_cache;

	/**
	 * Queue processor instance
	 *
	 * @var QueueProcessor
	 */
	private QueueProcessor $processor;

	/**
	 * Logger instance
	 *
	 * @var QueueLogger
	 */
	private QueueLogger $logger;

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
	 * Initialize helper class dependencies
	 */
	private function __construct() {
		$this->rate_limiter  = RateLimiter::get_instance();
		$this->contact_cache = ContactCache::get_instance();
		$this->processor     = QueueProcessor::get_instance();
		$this->logger        = QueueLogger::get_instance();

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

		// Schedule recurring action AFTER Action Scheduler is ready
		add_action( 'init', [ $this, 'schedule_queue_processor' ], 999 );
	}

	/**
	 * Schedule the queue processor (called on 'init' hook after Action Scheduler is ready)
	 *
	 * Ensures the recurring queue processor is scheduled to run automatically.
	 * This is called once on WordPress 'init' at priority 999 to ensure Action Scheduler
	 * is fully loaded.
	 *
	 * Scheduling Strategy:
	 * - Primary: Action Scheduler with 10-second interval (PROCESSING_INTERVAL constant)
	 * - Fallback: WP-Cron with 1-minute interval (if Action Scheduler unavailable)
	 *
	 * Action Scheduler Benefits:
	 * - More reliable than WP-Cron (doesn't require site traffic)
	 * - Better handling of missed runs
	 * - Built-in logging and retry logic
	 * - Won't schedule duplicate actions
	 *
	 * Safety:
	 * - Checks if action already scheduled before creating new one
	 * - Idempotent: Safe to call multiple times
	 *
	 * @return void
	 */
	public function schedule_queue_processor(): void {
		// Schedule recurring action via Action Scheduler (runs every 10 seconds)
		if ( function_exists( 'as_next_scheduled_action' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			$next_scheduled = as_next_scheduled_action( 'ghl_crm_process_queue' );

			if ( false === $next_scheduled ) {
				as_schedule_recurring_action( time(), self::PROCESSING_INTERVAL, 'ghl_crm_process_queue', [], 'ghl-crm' );
			}
		} else {
			// Fallback to WP-Cron if Action Scheduler not available or not yet initialized
			if ( ! wp_next_scheduled( 'ghl_crm_process_queue' ) ) {
				wp_schedule_event( time(), 'every_minute', 'ghl_crm_process_queue' );
			}
		}
	}

	/**
	 * Add item to queue
	 *
	 * Intelligently adds a sync task to the queue with automatic duplicate prevention.
	 * If a pending item with the same type/id/action already exists, it updates the
	 * payload with latest data instead of creating a duplicate row.
	 *
	 * Multisite-aware: Uses current site's table prefix and tracks site_id.
	 *
	 * Safety Features:
	 * - Prevents duplicate pending items (updates existing instead)
	 * - Enforces 10,000 item queue limit per site to prevent bloat
	 * - Supports task dependencies via depends_on_queue_id
	 *
	 * Performance Notes:
	 * - Uses indexed lookup for duplicate detection
	 * - Single UPDATE query for duplicates (no INSERT)
	 * - Counts are cached at PHP level during request
	 *
	 * @param string   $item_type         Item type (user, wc_customer, learndash_course, etc.)
	 * @param int      $item_id           WordPress object ID (user_id, order_id, course_id, etc.)
	 * @param string   $action            Action to perform (create, update, delete, add_tags, remove_tags)
	 * @param array    $payload           Data payload containing sync details (contacts, tags, metadata)
	 * @param int|null $depends_on_queue_id Optional queue ID this task depends on (waits for completion)
	 * @return int|false Queue item ID on success, false if queue full or database error
	 */
	public function add_to_queue( string $item_type, int $item_id, string $action, array $payload, ?int $depends_on_queue_id = null ) {
		global $wpdb;

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		// Check for existing pending item
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Inspecting queue table for duplicate pending entry.
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Updating existing queue payload to avoid duplicate rows.
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting pending queue items for throttling.
		$queue_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE site_id = %d AND status = 'pending'",
				$current_site_id
			)
		);

		if ( $queue_count >= 10000 ) { // Max 10k pending items per site
			return false;
		}

		// Store dependency in payload if provided
		if ( null !== $depends_on_queue_id ) {
			$payload['_depends_on_queue_id'] = $depends_on_queue_id;
		}

		// Insert new queue item
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Inserting new job into plugin queue table.
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
				'updated_at' => current_time( 'mysql' ),
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
				'%s',
				'%d',
			]
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Process queue (run by Action Scheduler every 10 seconds)
	 *
	 * Main queue processor that runs on a recurring schedule. This is the entry point
	 * for all background sync operations.
	 *
	 * Multisite Support:
	 * - Automatically detects multisite environment
	 * - Iterates through all sites (up to 999 sites)
	 * - Switches blog context and reloads API client settings for each site
	 * - Restores original blog context after processing
	 *
	 * Safety Features:
	 * - Uses transient lock to prevent concurrent processing (2-minute timeout)
	 * - Lock is ALWAYS released in finally block (even on fatal errors)
	 * - Checks queue backlog and sends admin notifications if threshold exceeded
	 *
	 * Processing Flow:
	 * 1. Acquire processing lock (exit if already running)
	 * 2. Check queue backlog health
	 * 3. If multisite: loop through sites, switch context, process each
	 * 4. If single site: process current site queue
	 * 5. Release lock
	 *
	 * Performance:
	 * - Processes up to batch_size items per site (default: 50)
	 * - Respects GHL API rate limits (100 req/10s, 200k/day)
	 * - Early exits if rate limited (prevents wasted processing)
	 *
	 * @return void
	 */
	public function process_queue(): void {

		// Prevent concurrent processing (race condition protection)
		// Use site transient for network-wide lock in multisite (wp_sitemeta table).
		// In single-site this falls back to wp_options (same as get_transient).
		$lock_key = 'ghl_crm_queue_processing';
		if ( get_site_transient( $lock_key ) ) {

			return; // Already processing
		}

		// Set lock for 2 minutes
		set_site_transient( $lock_key, time(), 2 * MINUTE_IN_SECONDS );

		try {
			// Check for queue backlog and send notification if needed
			$this->check_queue_backlog();

			if ( is_multisite() ) {

				// Process each site's queue
				$sites = get_sites(
					[
						'number' => 999,
					]
				);

				foreach ( $sites as $site ) {

					switch_to_blog( $site->blog_id );

					// CRITICAL: Reload Client settings after blog switch (multisite fix)
					// The Client singleton caches settings on first init, before blog switch
					\GHL_CRM\API\Client\Client::get_instance()->reload_settings();

					$this->process_site_queue();
					restore_current_blog();
				}
			} else {
				$this->process_site_queue();
			}
		} finally {
			// Always release lock
			delete_site_transient( $lock_key );

		}
	}

	/**
	 * Process queue for current site
	 *
	 * Processes a batch of pending queue items for the current WordPress site.
	 * This method is called by process_queue() after blog context is set.
	 *
	 * Processing Steps:
	 * 1. Get batch size from settings (default 50, clamped 1-500)
	 * 2. Clean up stale items stuck in processing >5 minutes
	 * 3. Mark items with MAX_ATTEMPTS as failed
	 * 4. Fetch oldest pending items (FIFO order)
	 * 5. Process each item via process_queue_item()
	 *
	 * Safety Features:
	 * - Cleanup prevents items stuck in limbo
	 * - Respects MAX_ATTEMPTS limit (5 retries)
	 * - Batch size prevents memory exhaustion
	 * - Early exit if no pending items
	 *
	 * Performance:
	 * - Uses indexed queries (status, site_id, created_at)
	 * - Limits result set to configured batch_size
	 * - Processes items sequentially (not parallel)
	 *
	 * @return void
	 */
	private function process_site_queue(): void {
		global $wpdb;

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		// CRITICAL: Skip processing if OAuth is not connected
		// Prevents burning retry attempts when auth is down
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		if ( ! $settings_manager->is_connection_verified() ) {
			return;
		}

		// Get batch size from settings via SettingsManager
		$batch_size = absint( $settings_manager->get_setting( 'batch_size', self::BATCH_SIZE ) );
		$batch_size = max( 1, min( 500, $batch_size ) ); // Clamp between 1-500

		// Clean up stale items first (stuck in processing for >5 minutes)
		$this->cleanup_stale_items();

		// Mark items with MAX_ATTEMPTS as failed (fix for stuck items)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Resetting stuck queue items in plugin table.
		$fixed_count = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} 
				SET status = 'failed' 
				WHERE status = 'pending' 
				AND site_id = %d
				AND attempts >= %d",
				$current_site_id,
				self::MAX_ATTEMPTS
			)
		);

		// Get pending items for current site (using configured batch size)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fetching pending jobs for processing from plugin queue table.
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
				$batch_size
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
	 *
	 * Safety mechanism that detects items stuck in limbo and resets them to pending
	 * status so they can be retried.
	 *
	 * An item becomes "stale" when:
	 * - Status is 'pending' (actively being processed)
	 * - Has attempts > 0 (not first run)
	 * - updated_at timestamp is >5 minutes old
	 * - attempts < MAX_ATTEMPTS (still has retries left)
	 *
	 * Common Causes of Stale Items:
	 * - PHP fatal errors during processing
	 * - Server crashes or restarts
	 * - Memory exhaustion
	 * - Timeout issues
	 * - Database connection failures
	 *
	 * The 5-minute window is conservative to avoid interfering with legitimately
	 * slow API operations while catching truly stuck items.
	 *
	 * Called automatically at the start of each process_site_queue() run.
	 *
	 * @return void
	 */
	private function cleanup_stale_items(): void {
		global $wpdb;

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		// Reset items that have been processing for too long
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Restoring stuck jobs in plugin queue table.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} 
				SET status = 'pending', 
					updated_at = %s 
				WHERE status = 'processing' 
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
	 * The core processing logic for a single sync task. This method handles the complete
	 * lifecycle of syncing a WordPress object to GoHighLevel CRM.
	 *
	 * Processing Flow:
	 * 1. Validate item hasn't exceeded MAX_ATTEMPTS (mark failed if so)
	 * 2. Check GHL API rate limits (exit early if throttled)
	 * 3. Increment attempt counter
	 * 4. Register fatal error handler (catch PHP crashes)
	 * 5. Execute sync via QueueProcessor helper
	 * 6. Track API usage via RateLimiter
	 * 7. Handle result (success/failure/dependency-wait)
	 * 8. Update user meta with contact IDs and sync timestamps
	 * 9. Mark item as completed or failed
	 * 10. Log event via QueueLogger
	 * 11. Fire action hooks for extensibility
	 *
	 * Error Handling:
	 * - Rate limit errors: Reset to pending without incrementing attempts
	 * - Transient errors: Keep pending if attempts < MAX_ATTEMPTS
	 * - Fatal errors: Mark failed and send admin notification
	 * - Dependency waits: Leave pending without penalty
	 *
	 * Side Effects:
	 * - Updates queue item status in database
	 * - Updates user meta (_ghl_contact_id, _ghl_last_sync, _ghl_tags)
	 * - Processes pending tags after contact creation
	 * - Logs to ghl_sync_log table
	 * - Fires 'ghl_crm_after_sync_success' and 'ghl_crm_log_event' hooks
	 * - Sends admin notifications on final failure
	 *
	 * Dependencies:
	 * - RateLimiter: For API throttling
	 * - QueueProcessor: For sync execution
	 * - QueueLogger: For event logging
	 * - TagManager: For tag storage
	 * - NotificationManager: For error alerts
	 *
	 * Performance:
	 * - Tracks execution time via microtime()
	 * - Single database transaction per item
	 * - Atomic operations prevent partial updates
	 *
	 * @param object $item Queue item from database with properties: id, item_type, item_id,
	 *                     action, payload (JSON), status, attempts, created_at, site_id
	 * @return void
	 */
	private function process_queue_item( object $item ): void {
		global $wpdb;

		// Declare variables at method scope to avoid undefined variable errors in catch blocks
		$table_name  = $this->get_queue_table_name();
		$start_time  = microtime( true );
		$location_id = null;
		$result      = null;
		$payload     = json_decode( $item->payload, true );

		// Safety check: If item already has MAX_ATTEMPTS, mark as failed immediately
		if ( $item->attempts >= self::MAX_ATTEMPTS ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Marking exhausted job as failed in queue table.
			$wpdb->update(
				$table_name,
				[
					'status'        => 'failed',
					'error_message' => 'Maximum retry attempts reached',
					'updated_at'    => current_time( 'mysql' ),
				],
				[ 'id' => $item->id ],
				[ '%s', '%s', '%s' ],
				[ '%d' ]
			);
			return;
		}

		try {
			// Check rate limits before processing (using RateLimiter helper)
			$location_id = $this->get_ghl_location_id();
			$rate_ok     = $location_id ? $this->rate_limiter->check_limits( $location_id ) : true;

			if ( ! $rate_ok ) {
				// If the daily limit specifically was hit, notify the admin (once per day).
				if ( $location_id && $this->rate_limiter->is_daily_limit_reached( $location_id ) ) {
					$notification_manager = \GHL_CRM\Admin\NotificationManager::get_instance();
					$notification_manager->send_daily_limit_reached(
						$this->rate_limiter->get_daily_count( $location_id ),
						$this->get_pending_count()
					);
				}
				return;
			}
		} catch ( \Exception $e ) {
			return;
		} catch ( \Throwable $e ) {
			return;
		}

		// Increment attempts
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Incrementing job attempt metadata.
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

		// Update item object to reflect database change
		$item->attempts = $item->attempts + 1;

		try {
			// Register fatal error handler
			register_shutdown_function(
				function () use ( $item ) {
					$error = error_get_last();
					if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
					}
				}
			);

			// Execute sync using QueueProcessor helper
			$result         = $this->processor->execute_sync( $item->item_type, $item->action, (int) $item->item_id, $payload );
			$last_php_error = error_get_last(); // Capture immediately — may reveal silent handler failures

			// Track API request using RateLimiter helper
			if ( $location_id ) {
				$this->rate_limiter->track_request( $location_id );
			}

			// Check if result indicates success or should be skipped (dependency waiting)
			// Some integrations return arrays with 'success' => false
			$is_success  = false;
			$should_skip = false;

			if ( is_array( $result ) && isset( $result['success'] ) ) {
				$is_success = $result['success'];
				// Check if this is a dependency wait (don't increment retry counter)
				$should_skip = ! empty( $result['skip'] );
			} elseif ( $result ) {
				$is_success = true;
			}

			// If waiting for dependency, skip processing but don't fail
			if ( $should_skip ) {
				// Check if payload needs to be updated with dependency info
				if ( ! empty( $result['update_payload'] ) && is_array( $result['update_payload'] ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Persisting updated dependency payload to queue table.
					$wpdb->update(
						$table_name,
						[
							'payload'    => wp_json_encode( $result['update_payload'] ),
							'updated_at' => current_time( 'mysql' ),
						],
						[ 'id' => $item->id ],
						[ '%s', '%s' ],
						[ '%d' ]
					);
				}
				return; // Leave item pending, will retry later when dependency completes
			}

			if ( $is_success ) {

				// Extract contact ID or opportunity ID from result if available
				$contact_id    = null;
				$ghl_object_id = null;

				// Try to get contact_id from result first
				if ( is_array( $result ) ) {
					// For contacts
					$contact_id = $result['contact']['id'] ?? $result['id'] ?? null;

					// For opportunities (WooCommerce)
					if ( 'wc_customer' === $item->item_type && ! empty( $result['opportunity']['id'] ) ) {
						$ghl_object_id = $result['opportunity']['id'];
					} elseif ( ! empty( $contact_id ) ) {
						$ghl_object_id = $contact_id;
					}
				}

				// If contact_id not in result, try to get it from payload (for add_tags/remove_tags actions)
				if ( empty( $contact_id ) ) {
					$contact_id = $payload['contact_id'] ?? null;
					if ( ! empty( $contact_id ) ) {
						$ghl_object_id = $contact_id;
					}
				}

				// Mark as completed
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Updating queue row status in custom table after successful sync.
				$update_result = $wpdb->update(
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

				// Automatically fire enterprise-grade hook so integrations can handle sync-back logic
				do_action( 'ghl_crm_after_sync_success', $item, $contact_id, $result, $payload );

				// Log success with full request/response data (using QueueLogger helper)
				$this->logger->log_event(
					(int) $item->item_id,
					$item->action,
					'success',
					$ghl_object_id, // Contact ID for users, Opportunity ID for WooCommerce
					$payload,       // Request data
					is_array( $result ) ? $result : null, // Response data
					null,
					microtime( true ) - $start_time,
					$item->item_type // Sync type
				);

			} else {

				// Build detailed error context for logging
				$error_context = [
					'queue_id'    => $item->id,
					'item_type'   => $item->item_type,
					'item_id'     => $item->item_id,
					'action'      => $item->action,
					'attempts'    => $item->attempts,
					'payload'     => $payload,
					'result_type' => gettype( $result ),
					'result'      => $result,
				];

				// Extract error message if available
				$error_message = 'Sync execution failed';
				if ( is_array( $result ) && ! empty( $result['error'] ) ) {
					$error_message .= ': ' . $result['error'];
				} elseif ( empty( $result ) ) {
					$error_message .= ': Empty result returned from sync processor';
					if ( ! empty( $last_php_error ) ) {
						$error_message .= sprintf(
							' | Last PHP error: [type %d] %s in %s:%d',
							$last_php_error['type'],
							$last_php_error['message'],
							basename( $last_php_error['file'] ),
							$last_php_error['line']
						);
					}
				} else {
					$error_message .= ': ' . print_r( $result, true );
				}

				throw new \Exception( $error_message );
			}
		} catch ( \Exception $e ) {

			// Check if it's a rate limit error (using RateLimiter helper)
			if ( $this->rate_limiter->is_rate_limit_error( $e ) ) {
				// Don't increment attempts for rate limit errors
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Resetting queue item to pending after rate limit to ensure retry.
				$wpdb->update(
					$table_name,
					[
						'status'        => 'pending',
						'error_message' => 'Rate limit exceeded - will retry',
						'updated_at'    => current_time( 'mysql' ),
					],
					[ 'id' => $item->id ],
					[ '%s', '%s', '%s' ],
					[ '%d' ]
				);

				return; // Stop processing this batch
			}

			// Check if it's an authentication/token error (circuit breaker, token refresh failure, etc.)
			// Don't burn retry attempts on auth errors - the item itself isn't broken, auth is.
			$is_auth_error = ( $e instanceof \GHL_CRM\API\Exceptions\AuthenticationException )
				|| false !== stripos( $e->getMessage(), 'Token refresh' )
				|| false !== stripos( $e->getMessage(), 'refresh temporarily disabled' )
				|| false !== stripos( $e->getMessage(), 'No refresh token' )
				|| false !== stripos( $e->getMessage(), 'No authentication method' )
				|| false !== stripos( $e->getMessage(), 'reconnect your account' )
				|| false !== stripos( $e->getMessage(), 'Recent refresh attempt' );

			if ( $is_auth_error ) {
				// Roll back the attempt counter - don't penalize the item for auth issues.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Resetting queue item to pending after auth error.
				$wpdb->update(
					$table_name,
					[
						'status'        => 'pending',
						'attempts'      => max( 0, $item->attempts - 1 ),
						'error_message' => 'Authentication error - will retry when reconnected: ' . $e->getMessage(),
						'updated_at'    => current_time( 'mysql' ),
					],
					[ 'id' => $item->id ],
					[ '%s', '%d', '%s', '%s' ],
					[ '%d' ]
				);

				// Log but don't count as failure.
				$this->logger->log_event(
					(int) $item->item_id,
					$item->action,
					'failed',
					null,
					$payload,
					null,
					$e->getMessage(),
					microtime( true ) - $start_time,
					$item->item_type
				);

				// Stop processing this entire batch - all items will hit the same auth error.
				return;
			}

			// Mark as failed if max attempts reached (attempts already incremented above)
			$status = ( $item->attempts >= self::MAX_ATTEMPTS ) ? 'failed' : 'pending';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Persisting failure state for queue record in custom table.
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

			// Send notification if max attempts reached (final failure)
			if ( 'failed' === $status ) {
				$notification_manager = \GHL_CRM\Admin\NotificationManager::get_instance();
				$sync_type_label      = $this->get_friendly_sync_type_label( $item->item_type, $item->action );

				$notification_manager->send_sync_error(
					$sync_type_label,
					$e->getMessage(),
					[
						'queue_id'  => $item->id,
						'item_id'   => $item->item_id,
						'item_type' => $item->item_type,
						'action'    => $item->action,
						'attempts'  => $item->attempts,
					]
				);
			}

			// Log error with request/response details
			$this->logger->log_event(
				(int) $item->item_id,
				$item->action,
				'error',
				null,
				$payload,
				is_array( $result ?? null ) ? $result : null,
				$e->getMessage(),
				microtime( true ) - $start_time,
				$item->item_type
			);

			do_action(
				'ghl_crm_log_event',
				'queue_item_error',
				'Queue item processing failed',
				array_merge(
					$error_context ?? [],
					[
						'error'   => $e->getMessage(),
						'status'  => $status,
						'site_id' => get_current_blog_id(),
					]
				),
				'error'
			);
		}
	}
	/**
	 * Get queue status
	 *
	 * Returns comprehensive queue statistics and health indicators for the current site.
	 * Used by admin dashboards, status pages, and monitoring systems.
	 *
	 * Multisite-aware: Returns stats only for current site (not network-wide).
	 *
	 * Statistics Returned:
	 * - pending: Number of items waiting to be processed
	 * - failed: Number of permanently failed items (exceeded MAX_ATTEMPTS)
	 * - completed_24h: Items successfully processed in last 24 hours
	 * - total_items: All items in queue table for this site
	 * - oldest_pending_minutes: Age of oldest pending item (for backlog detection)
	 * - health: Overall status ('good', 'warning', 'critical')
	 * - warnings: Array of human-readable warning messages
	 * - rate_limits: Current API rate limit status from RateLimiter
	 *
	 * Health Thresholds:
	 * - Good: <1000 pending, <100 failed, oldest <60 min
	 * - Warning: 1000-5000 pending, 100+ failed, oldest 60+ min
	 * - Critical: >5000 pending (severe backlog)
	 *
	 * Performance:
	 * - Executes 4-5 COUNT queries (all indexed)
	 * - Results not cached (always fresh data)
	 * - Typical execution: 5-10ms
	 *
	 * @return array Queue statistics with health indicators
	 */
	public function get_queue_status(): array {
		global $wpdb;

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Gathering queue metrics for status dashboard.
		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' AND site_id = %d",
				$current_site_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Gathering queue metrics for status dashboard.
		$failed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed' AND site_id = %d",
				$current_site_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Gathering queue metrics for status dashboard.
		$completed_24h = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'completed' AND site_id = %d AND processed_at > %s",
				$current_site_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Gathering queue metrics for status dashboard.
		$total_items = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE site_id = %d",
				$current_site_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Calculating age of oldest pending job for alerting.
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

		// Get rate limit info
		$rate_limits = $this->get_rate_limit_status();

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
			'rate_limits'            => $rate_limits,
		];
	}

	/**
	 * Get pending count for current site
	 *
	 * @return int Number of pending items
	 */
	public function get_pending_count(): int {
		global $wpdb;

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Getting pending count for queue monitoring.
		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' AND site_id = %d",
				$current_site_id
			)
		);

		return (int) $pending;
	}

	/**
	 * Get rate limit status (using RateLimiter helper)
	 *
	 * @return array Rate limit statistics
	 */
	private function get_rate_limit_status(): array {
		$location_id = $this->get_ghl_location_id();
		if ( empty( $location_id ) ) {
			return [
				'burst'               => [
					'limit'     => 100,
					'used'      => 0,
					'remaining' => 100,
					'percent'   => 0,
					'window'    => '10 seconds',
				],
				'daily'               => [
					'limit'     => 200000,
					'used'      => 0,
					'remaining' => 200000,
					'percent'   => 0,
					'resets_at' => gmdate( 'Y-m-d H:i:s', strtotime( 'tomorrow midnight' ) ),
				],
				'throttled'           => false,
				'location_id'         => null,
				'shared_across_sites' => false,
			];
		}

		// Use RateLimiter helper to get status
		return $this->rate_limiter->get_status( $location_id );
	}

	/**
	 * Get GHL location ID for current site
	 * Used for rate limiting tracking across sites sharing same GHL account
	 *
	 * @return string|null Location ID or null if not configured
	 */
	private function get_ghl_location_id(): ?string {
		// Get from settings manager
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$location_id      = $settings_manager->get_setting( 'location_id' );

		return ! empty( $location_id ) ? (string) $location_id : null;
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
		if ( function_exists( 'as_unschedule_all_actions' ) && class_exists( 'ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			as_unschedule_all_actions( 'ghl_crm_process_queue', [], 'ghl-crm' );
		} else {
			// Fallback: Clear WP-Cron (AS not available or not yet initialized)
			$timestamp = wp_next_scheduled( 'ghl_crm_process_queue' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'ghl_crm_process_queue' );
			}
		}
	}

	/**
	 * Get friendly sync type label for notifications
	 *
	 * @param string $item_type Item type from queue
	 * @param string $action    Action being performed
	 * @return string Friendly label
	 */
	private function get_friendly_sync_type_label( string $item_type, string $action ): string {
		$labels = [
			'user'                         => 'WordPress User',
			'wc_customer'                  => 'WooCommerce Order',
			'woocommerce_order'            => 'WooCommerce Order',
			'learndash_course'             => 'LearnDash Course',
			'learndash_course_enrollment'  => 'LearnDash Course Enrollment',
			'learndash_content_event'      => 'LearnDash Content Completion',
			'buddyboss_group'              => 'BuddyBoss Group',
			'buddyboss_member_association' => 'BuddyBoss Member Association',
			'custom_object'                => 'Custom Object',
		];

		$base_label = $labels[ $item_type ] ?? ucwords( str_replace( '_', ' ', $item_type ) );

		// Add action context if relevant
		if ( 'add_tags' === $action ) {
			return $base_label . ' (Add Tags)';
		} elseif ( 'remove_tags' === $action ) {
			return $base_label . ' (Remove Tags)';
		}

		return $base_label;
	}

	/**
	 * Check queue backlog and send notification if threshold exceeded
	 *
	 * Monitors queue health by checking the pending item count and sends an admin
	 * notification if the backlog exceeds the configured threshold.
	 *
	 * This prevents silent queue failures by alerting administrators when:
	 * - Processing speed is slower than item creation rate
	 * - API rate limits are causing delays
	 * - System resources are constrained
	 * - Integration failures are accumulating
	 *
	 * Called automatically by process_queue() on every run (every 10 seconds).
	 *
	 * Threshold:
	 * - Default: 1000 pending items
	 * - Configurable via 'ghl_crm_queue_backlog_threshold' filter
	 * - Notification sent once per backlog event (via NotificationManager)
	 *
	 * Performance:
	 * - Single COUNT query (indexed on status + site_id)
	 * - Minimal overhead (~1ms)
	 *
	 * @return void
	 */
	public function check_queue_backlog(): void {
		$pending_count = $this->get_pending_count();

		// Threshold for backlog warning (configurable via filter)
		$threshold = apply_filters( 'ghl_crm_queue_backlog_threshold', 1000 );

		// Add cooldown to prevent notification spam (once per hour)
		if ( $pending_count > $threshold && ! get_transient( 'ghl_crm_backlog_notified' ) ) {
			$notification_manager = \GHL_CRM\Admin\NotificationManager::get_instance();
			$notification_manager->send_queue_backlog( $pending_count );
			// Set cooldown to prevent spam (1 hour)
			set_transient( 'ghl_crm_backlog_notified', true, HOUR_IN_SECONDS );
		}
	}
}
