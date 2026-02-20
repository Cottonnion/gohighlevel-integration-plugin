<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

use GHL_CRM\Core\TagManager;

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
	private const MAX_ATTEMPTS = 5;

	/**
	 * Batch size for processing
	 */
	private const BATCH_SIZE = 50;

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

		// Listen for successful sync completions (provides enterprise-grade extensibility)
		add_action( 'ghl_crm_after_sync_success', [ $this, 'handle_after_sync_success' ], 10, 4 );
	}

	/**
	 * Schedule the queue processor (called on 'init' hook after Action Scheduler is ready)
	 *
	 * @return void
	 */
	public function schedule_queue_processor(): void {
		// Schedule recurring action via Action Scheduler (runs every 10 seconds)
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$next_scheduled = as_next_scheduled_action( 'ghl_crm_process_queue' );

			if ( false === $next_scheduled ) {
				as_schedule_recurring_action( time(), 10, 'ghl_crm_process_queue', [], 'ghl-crm' );
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
	 * @param string   $item_type         Item type (user, order, group, course, etc.)
	 * @param int      $item_id           Item ID
	 * @param string   $action            Action (create, update, delete, etc.)
	 * @param array    $payload           Data payload
	 * @param int|null $depends_on_queue_id Optional queue ID this task depends on
	 * @return int|false Queue item ID or false on failure
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

		// Get batch size from settings via SettingsManager
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$batch_size       = absint( $settings_manager->get_setting( 'batch_size', self::BATCH_SIZE ) );
		$batch_size       = max( 1, min( 500, $batch_size ) ); // Clamp between 1-500

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
	 * Safety mechanism: Reset items that haven't completed in 5 minutes
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

		// Safety check: If item already has MAX_ATTEMPTS, mark as failed immediately
		if ( $item->attempts >= self::MAX_ATTEMPTS ) {

			$table_name = $this->get_queue_table_name();
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
			$table_name = $this->get_queue_table_name();

			$start_time = microtime( true );

			// Check rate limits before processing (using RateLimiter helper)

			$location_id = $this->get_ghl_location_id();
			$rate_ok     = $location_id ? $this->rate_limiter->check_limits( $location_id ) : true;

			if ( ! $rate_ok ) {

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
			$payload = json_decode( $item->payload, true );

			// Execute sync based on item type

			// Register fatal error handler
			register_shutdown_function(
				function () use ( $item ) {
					$error = error_get_last();
					if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {

					}
				}
			);

			// Execute sync using QueueProcessor helper
			$result = $this->processor->execute_sync( $item->item_type, $item->action, (int) $item->item_id, $payload );

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
					$payload_data = json_decode( $item->payload, true );
					$contact_id   = $payload_data['contact_id'] ?? null;
					if ( ! empty( $contact_id ) ) {
						$ghl_object_id = $contact_id;
					}
				}

				// Store contact ID, tags, and sync time in user meta (for admin columns and profile page)
				if ( 'user' === $item->item_type && ! empty( $contact_id ) ) {
					$tag_manager = TagManager::get_instance();
					$tag_manager->store_user_contact_id( (int) $item->item_id, (string) $contact_id );
					update_user_meta( (int) $item->item_id, '_ghl_last_sync', time() );

					$pending_tags = get_user_meta( (int) $item->item_id, '_ghl_pending_tags', true );
					if ( is_array( $pending_tags ) && ! empty( $pending_tags ) ) {
						$normalized_pending = $tag_manager->normalize_tag_input( $pending_tags );
						$payload_tags      = $tag_manager->prepare_tags_for_payload( $normalized_pending['ids'], $normalized_pending['pairs'] );

						if ( ! empty( $payload_tags ) ) {
							$this->add_to_queue(
								'user',
								(int) $item->item_id,
								'add_tags',
								[
									'contact_id' => $contact_id,
									'tags'       => array_values( array_unique( $payload_tags ) ),
									'reason'     => 'Pending tags applied after contact creation',
								]
							);
						}

						delete_user_meta( (int) $item->item_id, '_ghl_pending_tags' );
					}

					// Check for pending family tags and queue them
					$pending_family_tags = get_user_meta( (int) $item->item_id, '_ghl_pending_family_tags', true );
					if ( is_array( $pending_family_tags ) && ! empty( $pending_family_tags ) ) {
						$payload_tags = $tag_manager->prepare_tags_for_payload( $pending_family_tags );

						$this->add_to_queue(
							'user',
							(int) $item->item_id,
							'add_tags',
							[
								'contact_id' => $contact_id,
								'tags'       => array_values( array_unique( $payload_tags ) ),
								'reason'     => 'Family inheritance - pending tags applied after registration',
							]
						);

						// Clear pending tags
						delete_user_meta( (int) $item->item_id, '_ghl_pending_family_tags' );
					}

					// Store tags from payload (what we sent) OR from response
					$tags_to_cache = null;

					// First try to get tags from the API response
					if ( is_array( $result ) ) {
						// For remove_tags/add_tags actions, tags are directly in result
						if ( ! empty( $result['tags'] ) && is_array( $result['tags'] ) ) {
							$tags_to_cache = $result['tags'];
						} else {
							// For other actions, tags might be nested in contact data
							$contact_data = $result['contact'] ?? $result;
							if ( ! empty( $contact_data['tags'] ) && is_array( $contact_data['tags'] ) ) {
								$tags_to_cache = $contact_data['tags'];
							}
						}
					}

					// If tags not in response, use what we sent in the payload
					if ( null === $tags_to_cache ) {
						$payload_data = json_decode( $item->payload, true );
						if ( ! empty( $payload_data['tags'] ) && is_array( $payload_data['tags'] ) ) {
							$tags_to_cache = $payload_data['tags'];
						}
					}

					// Update user meta with tags (store as IDs)
					if ( ! empty( $tags_to_cache ) && is_array( $tags_to_cache ) ) {
						TagManager::get_instance()->store_user_tags( (int) $item->item_id, $tags_to_cache );
					}
				}

				// Handle WooCommerce customer conversion - update user meta for the customer
				if ( 'wc_customer' === $item->item_type && ! empty( $contact_id ) && class_exists( 'WooCommerce' ) ) {
					$order = wc_get_order( $item->item_id );
					if ( $order && $order->get_customer_id() ) {
						$user_id = $order->get_customer_id();
						$tag_manager = TagManager::get_instance();
						$tag_manager->store_user_contact_id( $user_id, (string) $contact_id );
						update_user_meta( $user_id, '_ghl_last_sync', time() );

						// Store tags from payload
						$payload_data = json_decode( $item->payload, true );
						if ( ! empty( $payload_data['tags'] ) && is_array( $payload_data['tags'] ) ) {
							$tag_manager->store_user_tags( $user_id, $payload_data['tags'] );
						}
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
					$payload, // Request data
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

				// Log detailed context with asiya log prefix

				// Extract error message if available
				$error_message = 'Sync execution failed';
				if ( is_array( $result ) && ! empty( $result['error'] ) ) {
					$error_message .= ': ' . $result['error'];
				} elseif ( empty( $result ) ) {
					$error_message .= ': Empty result returned from sync processor';
				} else {
					$error_message .= ': ' . print_r( $result, true );
				}

				throw new \Exception( $error_message );
			}
		} catch ( \Exception $e ) {
			// Check if it's a rate limit error (using RateLimiter helper)
			if ( $this->rate_limiter->is_rate_limit_error( $e ) ) {
				// Don't increment attempts for rate limit errors
				// Reset attempt counter so it will retry
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
				$notification_manager = \GHL_CRM\Core\NotificationManager::get_instance();
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
		}
	}

	/**
	 * ============================================================
	 * REFACTORING COMPLETE
	 * ============================================================
	 * The following methods have been extracted to helper classes
	 * for better separation of concerns and maintainability:
	 *
	 * QueueProcessor (src/Sync/QueueProcessor.php):
	 * - execute_sync()
	 * - execute_user_sync()
	 * - execute_contact_sync()
	 *
	 * RateLimiter (src/Sync/RateLimiter.php):
	 * - check_rate_limits() → check_limits()
	 * - track_api_request() → track_request()
	 * - is_rate_limit_error()
	 *
	 * ContactCache (src/Sync/ContactCache.php):
	 * - get_cached_contact() → get()
	 * - cache_contact() → set()
	 * - delete_cached_contact() → delete()
	 *
	 * QueueLogger (src/Sync/QueueLogger.php):
	 * - log_sync_event() → log_event()
	 * ============================================================
	 */

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

		// Fallback: Try to get from OAuth tokens if available
		if ( empty( $location_id ) ) {
			$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
			if ( method_exists( $oauth_handler, 'get_location_id' ) ) {
				$location_id = $oauth_handler->get_location_id();
			}
		}

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

	/**
	 * Sync contact tags from GHL after successful queue completion
	 * Delegates to UserProfileFields refresh logic for consistency
	 *
	 * @param object      $item Queue item
	 * @param string|null $contact_id GHL Contact ID
	 * @return void
	 */
	public function handle_after_sync_success( object $item, ?string $contact_id, $result = null, array $payload = [] ): void {
		$this->sync_contact_tags_from_ghl( $item, $contact_id );
	}

	/**
	 * Sync contact tags from GHL after successful queue completion
	 * Delegates to UserProfileFields refresh logic for consistency
	 *
	 * @param object      $item Queue item
	 * @param string|null $contact_id GHL Contact ID
	 * @return void
	 */
	private function sync_contact_tags_from_ghl( object $item, ?string $contact_id ): void {
		// Only sync for user-related items with a contact ID
		if ( empty( $contact_id ) ) {
			return;
		}

		$user_id = null;

		// Determine user ID based on item type
		if ( 'user' === $item->item_type ) {
			$user_id = (int) $item->item_id;
		} elseif ( 'wc_customer' === $item->item_type && class_exists( 'WooCommerce' ) ) {
			$order = wc_get_order( $item->item_id );
			if ( $order ) {
				$user_id = (int) $order->get_customer_id();
			}
		} elseif ( 'wc_product_tags' === $item->item_type ) {
			// Get user from order
			$payload  = json_decode( $item->payload, true );
			$order_id = $payload['order_id'] ?? 0;
			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$user_id = (int) $order->get_customer_id();
				}
			}
		}

		if ( empty( $user_id ) ) {
			return;
		}

		try {
			// Use the same refresh logic from UserProfileFields
			$profile_fields = \GHL_CRM\Admin\Profile\UserProfileFields::get_instance();

			// Call the internal refresh method (we'll create a public wrapper)
			if ( method_exists( $profile_fields, 'refresh_user_from_ghl' ) ) {
				$profile_fields->refresh_user_from_ghl( $user_id, $contact_id );
			} else {
				// Fallback: Use Client directly (same pattern as AJAX action)
				$client   = \GHL_CRM\API\Client\Client::get_instance();
				$response = $client->get( "contacts/{$contact_id}" );

				if ( ! empty( $response['contact'] ) ) {
					$contact = $response['contact'];

					update_user_meta( $user_id, '_ghl_contact_id', $contact_id );
					update_user_meta( $user_id, '_ghl_last_sync', time() );

					if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) ) {
						TagManager::get_instance()->store_user_tags( $user_id, $contact['tags'] );
					}

					if ( ! empty( $contact['type'] ) ) {
						update_user_meta( $user_id, '_ghl_contact_type', $contact['type'] );
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Keep queue item intact if tag sync fails
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
	 * Called periodically by process_queue to monitor queue health
	 *
	 * @return void
	 */
	public function check_queue_backlog(): void {
		$pending_count = $this->get_pending_count();

		// Threshold for backlog warning (configurable via filter)
		$threshold = apply_filters( 'ghl_crm_queue_backlog_threshold', 1000 );

		if ( $pending_count > $threshold ) {
			$notification_manager = \GHL_CRM\Core\NotificationManager::get_instance();
			$notification_manager->send_queue_backlog( $pending_count );
		}
	}
}