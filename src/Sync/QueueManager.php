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
	private const MAX_ATTEMPTS = 5;

	/**
	 * Batch size for processing
	 */
	private const BATCH_SIZE = 50;

	/**
	 * GHL API Rate Limits (per location)
	 */
	private const RATE_LIMIT_BURST        = 100; // Max requests per 10 seconds
	private const RATE_LIMIT_BURST_WINDOW = 10; // Seconds
	private const RATE_LIMIT_DAILY        = 200000; // Max requests per day

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

		// Schedule recurring action AFTER Action Scheduler is ready
		add_action( 'init', [ $this, 'schedule_queue_processor' ], 999 );
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
	 * @param string $item_type Item type (user, order, group, course, etc.)
	 * @param int    $item_id   Item ID
	 * @param string $action    Action (create, update, delete, etc.)
	 * @param array  $payload   Data payload
	 * @return int|false Queue item ID or false on failure
	 */
	public function add_to_queue( string $item_type, int $item_id, string $action, array $payload ) {
		global $wpdb;

		error_log( sprintf(
			'GHL CRM QueueManager: add_to_queue() called - Type: %s, ID: %d, Action: %s',
			$item_type,
			$item_id,
			$action
		) );

		$table_name      = $this->get_queue_table_name();
		$current_site_id = get_current_blog_id();

		error_log( 'GHL CRM QueueManager: Table name: ' . $table_name );
		error_log( 'GHL CRM QueueManager: Site ID: ' . $current_site_id );

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

		error_log( 'GHL CRM QueueManager: Existing queue item: ' . ( $existing ? $existing : 'NONE' ) );

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

		if ( $inserted ) {
			error_log( 'GHL CRM QueueManager: Successfully inserted queue item with ID: ' . $wpdb->insert_id );
		} else {
			error_log( 'GHL CRM QueueManager: FAILED to insert queue item. Error: ' . $wpdb->last_error );
		}

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
		error_log( '🚀 GHL CRM: process_queue() CALLED at ' . current_time( 'mysql' ) );
		
		// Prevent concurrent processing (race condition protection)
		$lock_key = 'ghl_crm_queue_processing';
		if ( get_transient( $lock_key ) ) {
			error_log( '⏸️ GHL CRM: Queue already processing, skipping' );
			return; // Already processing
		}

		// Set lock for 2 minutes
		set_transient( $lock_key, time(), 2 * MINUTE_IN_SECONDS );
		error_log( '🔒 GHL CRM: Lock acquired, starting queue processing' );

		try {
			if ( is_multisite() ) {
				error_log( '🌐 GHL CRM: Multisite detected, processing all sites' );
				// Process each site's queue
				$sites = get_sites(
					[
						'number' => 999,
					]
				);

				foreach ( $sites as $site ) {
					error_log( '📍 GHL CRM: Switching to blog ' . $site->blog_id );
					switch_to_blog( $site->blog_id );
					
					// CRITICAL: Reload Client settings after blog switch (multisite fix)
					// The Client singleton caches settings on first init, before blog switch
					\GHL_CRM\API\Client\Client::get_instance()->reload_settings();
					error_log( '🔄 GHL CRM: Reloaded Client settings for blog ' . $site->blog_id );
					
					$this->process_site_queue();
					restore_current_blog();
				}
			} else {
				$this->process_site_queue();
			}
		} finally {
			// Always release lock
			delete_transient( $lock_key );
			error_log( '🔓 GHL CRM: Lock released, queue processing complete' );
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

		error_log( '📊 GHL CRM: Processing queue for site ' . $current_site_id );

		// Clean up stale items first (stuck in processing for >5 minutes)
		$this->cleanup_stale_items();

		// Mark items with MAX_ATTEMPTS as failed (fix for stuck items)
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

		if ( $fixed_count > 0 ) {
			error_log( sprintf(
				'🔧 GHL CRM: Marked %d items as failed (reached max attempts)',
				$fixed_count
			) );
		}

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

		error_log( '📦 GHL CRM: Found ' . count( $items ) . ' pending items in queue' );

		if ( empty( $items ) ) {
			error_log( '⚠️ GHL CRM: No pending items, exiting' );
			return;
		}

		foreach ( $items as $item ) {
			error_log( '🔄 GHL CRM: Processing item ID ' . $item->id . ' - Type: ' . $item->item_type . ', Action: ' . $item->action );
			$this->process_queue_item( $item );
		}
		
		error_log( '✅ GHL CRM: Site queue processing complete' );
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

		error_log( '⚙️ GHL CRM: process_queue_item() START - Item ID: ' . $item->id );

		try {
			$table_name = $this->get_queue_table_name();
			error_log( '📋 GHL CRM: Got table name: ' . $table_name );
			
			$start_time = microtime( true );
			error_log( '⏱️ GHL CRM: Start time: ' . $start_time );

			// Check rate limits before processing
			error_log( '🔍 GHL CRM: Checking rate limits...' );
			$rate_ok = $this->check_rate_limits();
			error_log( '🔍 GHL CRM: Rate limit check returned: ' . ( $rate_ok ? 'TRUE' : 'FALSE' ) );
			
			if ( ! $rate_ok ) {
				error_log( '🚫 GHL CRM: Rate limit exceeded, skipping item ' . $item->id );
				return;
			}
		} catch ( \Exception $e ) {
			error_log( '❌ GHL CRM: EXCEPTION in process_queue_item: ' . $e->getMessage() );
			error_log( '❌ GHL CRM: Stack trace: ' . $e->getTraceAsString() );
			return;
		} catch ( \Throwable $e ) {
			error_log( '💥 GHL CRM: FATAL ERROR in process_queue_item: ' . $e->getMessage() );
			error_log( '💥 GHL CRM: Stack trace: ' . $e->getTraceAsString() );
			return;
		}

		error_log( '✅ GHL CRM: Rate limit OK, processing item ' . $item->id );

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

		error_log( '📝 GHL CRM: Incremented attempts to ' . ( $item->attempts + 1 ) );

		// Update item object to reflect database change
		$item->attempts = $item->attempts + 1;

		try {
			$payload = json_decode( $item->payload, true );
			error_log( '📦 GHL CRM: Decoded payload: ' . print_r( $payload, true ) );

			// Execute sync based on item type
			error_log( '🎯 GHL CRM: Calling execute_sync() for type: ' . $item->item_type . ', action: ' . $item->action );
			error_log( '🔍 GHL CRM: About to call $this->execute_sync() method' );
			error_log( '🔍 GHL CRM: Method exists: ' . ( method_exists( $this, 'execute_sync' ) ? 'YES' : 'NO' ) );
			
			// Register fatal error handler
			register_shutdown_function( function() use ( $item ) {
				$error = error_get_last();
				if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
					error_log( '💀 FATAL ERROR during execute_sync for item ' . $item->id );
					error_log( '💀 Error: ' . print_r( $error, true ) );
				}
			} );
			
			$result = $this->execute_sync( $item->item_type, $item->action, (int) $item->item_id, $payload );
			error_log( '📊 GHL CRM: execute_sync() returned: ' . ( $result ? 'TRUE' : 'FALSE' ) );

			// Track API request
			$this->track_api_request();

			if ( $result ) {
				error_log( '✅ GHL CRM: Sync successful, marking as completed' );
				
				// Extract contact ID from result if available
				$contact_id = null;
				if ( is_array( $result ) ) {
					$contact_id = $result['contact']['id'] ?? $result['id'] ?? null;
				}
				
				// Store contact ID and sync time in user meta (for admin columns)
				if ( 'user' === $item->item_type && ! empty( $contact_id ) ) {
					update_user_meta( (int) $item->item_id, '_ghl_contact_id', $contact_id );
					update_user_meta( (int) $item->item_id, '_ghl_last_sync', time() );
				}
				
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

				// Log success with full request/response data
				$this->log_sync_event(
					(int) $item->item_id,
					$item->action,
					'success',
					$contact_id,
					$payload, // Request data
					is_array( $result ) ? $result : null, // Response data
					null,
					microtime( true ) - $start_time
				);
			} else {
				throw new \Exception( 'Sync execution returned false' );
			}
		} catch ( \Exception $e ) {
			error_log( '❌ GHL CRM: CAUGHT EXCEPTION in process_queue_item()' );
			error_log( '❌ Exception Message: ' . $e->getMessage() );
			error_log( '❌ Exception File: ' . $e->getFile() . ':' . $e->getLine() );
			error_log( '❌ Stack Trace: ' . $e->getTraceAsString() );
			
			// Check if it's a rate limit error
			if ( $this->is_rate_limit_error( $e ) ) {
				// Don't increment attempts for rate limit errors
				// Reset attempt counter so it will retry
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

				// Log rate limit hit
				error_log(
					sprintf(
						'GHL CRM Rate Limit Hit [Site %d]: Item %d paused for retry',
						get_current_blog_id(),
						$item->id
					)
				);

				return; // Stop processing this batch
			}

		// Mark as failed if max attempts reached (attempts already incremented above)
		$status = ( $item->attempts >= self::MAX_ATTEMPTS ) ? 'failed' : 'pending';

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

		// Log when item is marked as failed
		if ( $status === 'failed' ) {
			error_log( sprintf(
				'❌ GHL CRM: Item %d marked as FAILED after %d attempts. Error: %s',
				$item->id,
				$item->attempts,
				$e->getMessage()
			) );
		}			// Log error with request data
			$this->log_sync_event(
				(int) $item->item_id,
				$item->action,
				'error',
				null,
				$payload, // Request data that failed
				null, // No response data on error
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
	/**
	 * Execute sync operation
	 * 
	 * @param string $item_type Item type
	 * @param string $action Action
	 * @param int $item_id Item ID
	 * @param array $payload Payload data
	 * @return array|bool API response array on success, false on failure
	 * @throws \Exception
	 */
	private function execute_sync( string $item_type, string $action, int $item_id, array $payload ) {
		error_log( '🔧 GHL CRM: execute_sync() ENTERED - Type: ' . $item_type . ', Action: ' . $action . ', ID: ' . $item_id );
		
		try {
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
		} catch ( \Exception $e ) {
			error_log( '❌ GHL CRM: execute_sync() EXCEPTION: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Execute user sync
	 *
	 * @param string $action  Action
	 * @param int    $user_id User ID
	 * @param array  $payload Payload data
	 * @return array|bool API response array on success, false on failure
	 * @throws \Exception
	 */
private function execute_user_sync( string $action, int $user_id, array $payload ) {
	error_log( '👤 GHL CRM: execute_user_sync() START - Action: ' . $action . ', User ID: ' . $user_id );
	try {
		error_log( '📡 GHL CRM: Getting Client instance...' );
		$client = \GHL_CRM\API\Client\Client::get_instance();
		error_log( '📦 GHL CRM: Creating ContactResource...' );
		$contact_resource = new \GHL_CRM\API\Resources\ContactResource( $client );
		error_log( '✅ GHL CRM: Client and ContactResource ready' );

		switch ( $action ) {
	case 'user_register':
		case 'profile_update':
			error_log( '🔄 GHL CRM: Register/Update for: ' . ( $payload['email'] ?? 'no-email' ) );
			
			// Get location ID
			$location_id = $this->get_ghl_location_id();
			if ( empty( $location_id ) ) {
				error_log( '❌ No location ID configured' );
				return false;
			}
			
			$cached_contact = $this->get_cached_contact( $payload['email'] ?? '' );

			if ( $cached_contact ) {
				error_log( '💾 Using cached contact ID: ' . $cached_contact['id'] );
				$result = $contact_resource->update( $cached_contact['id'], $payload );
			} else {
				error_log( '🔍 Searching for existing contact by email...' );
				$email = $payload['email'] ?? '';
				$existing = $client->get( 'contacts/', [ 'query' => $email ] );
				if ( ! empty( $existing['contacts'][0] ) ) {
					$contact = $existing['contacts'][0];
					error_log( '✅ Found existing contact ID: ' . $contact['id'] );
					$this->cache_contact( $payload['email'], $contact );
					$result = $contact_resource->update( $contact['id'], $payload );
				} else {
					error_log( '🆕 Creating new contact via direct POST...' );
					// POST to /contacts/ with locationId in the body
					$result = $client->post( 'contacts/', array_merge( $payload, [ 'locationId' => $location_id ] ) );
					if ( $result && isset( $result['contact']['id'] ) ) {
						error_log( '✅ Contact created with ID: ' . $result['contact']['id'] );
						$this->cache_contact( $payload['email'], $result['contact'] );
					}
				}
			}
			error_log( '✅ Register/Update completed: ' . ( ! empty( $result ) ? 'SUCCESS' : 'FAILED' ) );
			return ! empty( $result ) ? $result : false;			case 'delete_user':
				error_log( '🗑️ Deleting user...' );
				if ( ! empty( $payload['delete'] ) ) {
					$email = $payload['email'] ?? '';
					$existing = $client->get( 'contacts/', [ 'query' => $email ] );
					if ( ! empty( $existing['contacts'][0]['id'] ) ) {
						$result = $contact_resource->delete( $existing['contacts'][0]['id'] );
						$this->delete_cached_contact( $payload['email'] );
						error_log( '✅ Contact deleted' );
						return [ 'deleted' => true, 'contact_id' => $existing['contacts'][0]['id'] ];
					}
				}
				return [ 'deleted' => false, 'message' => 'Contact not found or delete flag not set' ];

			case 'user_login':
				error_log( '🔐 User login - updating last_login for: ' . ( $payload['email'] ?? 'no-email' ) );
				$contact = $this->get_cached_contact( $payload['email'] ?? '' );
				if ( ! $contact ) {
					$email = urlencode( $payload['email'] ?? '' );
					$existing = $client->get( 'contacts?query=' . $email );
					if ( ! empty( $existing['contacts'][0] ) ) {
						$contact = $existing['contacts'][0];
						error_log( '✅ Found contact ID: ' . $contact['id'] );
						$this->cache_contact( $payload['email'], $contact );
					}
				}
				if ( $contact ) {
					error_log( '📝 Updating last_login for contact ID: ' . $contact['id'] );
					$result = $contact_resource->update( $contact['id'], [
						'customFields' => [ 'last_login' => $payload['last_login'] ?? current_time( 'mysql' ) ],
					] );
					error_log( '✅ Last login updated: ' . ( ! empty( $result ) ? 'SUCCESS' : 'FAILED' ) );
					return ! empty( $result ) ? $result : false;
				}
				error_log( '⚠️ No contact found, returning false' );
				return false;

			default:
				error_log( '❌ Unknown action: ' . $action );
				throw new \Exception( 'Unknown user action: ' . $action );
		}
	} catch ( \Exception $e ) {
		error_log( '❌ execute_user_sync() EXCEPTION: ' . $e->getMessage() );
		error_log( '📚 Stack trace: ' . $e->getTraceAsString() );
		throw $e;
	}
}


	/**
	 * Check if rate limits allow processing
	 * Checks both burst limit (100 per 10 sec) and daily limit (200k per day)
	 * Tracks by GHL location ID so multiple sites sharing same account share limits
	 *
	 * @return bool True if under limits, false if exceeded
	 */
	private function check_rate_limits(): bool {
		$location_id = $this->get_ghl_location_id();
		if ( empty( $location_id ) ) {
			// No location ID configured, allow processing but log warning
			error_log( 'GHL CRM: No location ID configured for site ' . get_current_blog_id() );
			return true;
		}

		// Check burst limit (100 requests per 10 seconds) - shared across sites with same location
		$burst_key   = 'ghl_rate_burst_' . md5( $location_id );
		$burst_data  = get_site_transient( $burst_key );
		$burst_count = $burst_data ? (int) $burst_data : 0;

		if ( $burst_count >= self::RATE_LIMIT_BURST ) {
			error_log(
				sprintf(
					'GHL CRM Burst Rate Limit Hit [Location %s, Site %d]: %d/%d requests in 10 seconds',
					$location_id,
					get_current_blog_id(),
					$burst_count,
					self::RATE_LIMIT_BURST
				)
			);
			return false;
		}

		// Check daily limit (200,000 requests per day) - shared across sites with same location
		$daily_key   = 'ghl_rate_daily_' . md5( $location_id ) . '_' . gmdate( 'Y-m-d' );
		$daily_data  = get_site_transient( $daily_key );
		$daily_count = $daily_data ? (int) $daily_data : 0;

		if ( $daily_count >= self::RATE_LIMIT_DAILY ) {
			error_log(
				sprintf(
					'GHL CRM Daily Rate Limit Hit [Location %s, Site %d]: %d/%d requests today',
					$location_id,
					get_current_blog_id(),
					$daily_count,
					self::RATE_LIMIT_DAILY
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Track API request for rate limiting
	 * Increments both burst and daily counters
	 * Tracks by GHL location ID so multiple sites sharing same account share limits
	 *
	 * @return void
	 */
	private function track_api_request(): void {
		$location_id = $this->get_ghl_location_id();
		if ( empty( $location_id ) ) {
			return; // Skip tracking if no location ID
		}

		// Track burst (10 second window) - shared across all sites with same location
		$burst_key   = 'ghl_rate_burst_' . md5( $location_id );
		$burst_data  = get_site_transient( $burst_key );
		$burst_count = $burst_data ? (int) $burst_data : 0;
		set_site_transient( $burst_key, $burst_count + 1, self::RATE_LIMIT_BURST_WINDOW );

		// Track daily (24 hour window) - shared across all sites with same location
		$daily_key   = 'ghl_rate_daily_' . md5( $location_id ) . '_' . gmdate( 'Y-m-d' );
		$daily_data  = get_site_transient( $daily_key );
		$daily_count = $daily_data ? (int) $daily_data : 0;

		// Set expiry to end of day
		$end_of_day = strtotime( 'tomorrow midnight' ) - time();
		set_site_transient( $daily_key, $daily_count + 1, $end_of_day );
	}

	/**
	 * Check if exception is a rate limit error
	 *
	 * @param \Exception $e Exception to check
	 * @return bool True if rate limit error
	 */
	private function is_rate_limit_error( \Exception $e ): bool {
		$message = strtolower( $e->getMessage() );

		// Check for common rate limit indicators
		return strpos( $message, 'rate limit' ) !== false
			|| strpos( $message, 'too many requests' ) !== false
			|| strpos( $message, '429' ) !== false
			|| ( $e instanceof \GHL_CRM\API\Exceptions\RateLimitException );
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
	 * @param array|null  $request_data   Request payload sent to API
	 * @param array|null  $response_data  Response received from API
	 * @param string|null $error_message  Error message
	 * @param float|null  $execution_time Execution time in seconds
	 * @return void
	 */
	private function log_sync_event(
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
	 * Get rate limit status
	 *
	 * @return array Rate limit statistics
	 */
	private function get_rate_limit_status(): array {
		$location_id = $this->get_ghl_location_id();
		if ( empty( $location_id ) ) {
			return [
				'burst'     => [
					'limit'     => self::RATE_LIMIT_BURST,
					'used'      => 0,
					'remaining' => self::RATE_LIMIT_BURST,
					'percent'   => 0,
					'window'    => self::RATE_LIMIT_BURST_WINDOW . ' seconds',
				],
				'daily'     => [
					'limit'     => self::RATE_LIMIT_DAILY,
					'used'      => 0,
					'remaining' => self::RATE_LIMIT_DAILY,
					'percent'   => 0,
					'resets_at' => gmdate( 'Y-m-d H:i:s', strtotime( 'tomorrow midnight' ) ),
				],
				'throttled' => false,
				'location_id' => null,
				'shared_across_sites' => false,
			];
		}

		// Burst limit status (shared across sites with same location)
		$burst_key       = 'ghl_rate_burst_' . md5( $location_id );
		$burst_data      = get_site_transient( $burst_key );
		$burst_count     = $burst_data ? (int) $burst_data : 0;
		$burst_remaining = max( 0, self::RATE_LIMIT_BURST - $burst_count );

		// Daily limit status (shared across sites with same location)
		$daily_key       = 'ghl_rate_daily_' . md5( $location_id ) . '_' . gmdate( 'Y-m-d' );
		$daily_data      = get_site_transient( $daily_key );
		$daily_count     = $daily_data ? (int) $daily_data : 0;
		$daily_remaining = max( 0, self::RATE_LIMIT_DAILY - $daily_count );

		// Calculate percentages
		$burst_percent = ( $burst_count / self::RATE_LIMIT_BURST ) * 100;
		$daily_percent = ( $daily_count / self::RATE_LIMIT_DAILY ) * 100;

		return [
			'burst'     => [
				'limit'     => self::RATE_LIMIT_BURST,
				'used'      => $burst_count,
				'remaining' => $burst_remaining,
				'percent'   => round( $burst_percent, 2 ),
				'window'    => self::RATE_LIMIT_BURST_WINDOW . ' seconds',
			],
			'daily'     => [
				'limit'     => self::RATE_LIMIT_DAILY,
				'used'      => $daily_count,
				'remaining' => $daily_remaining,
				'percent'   => round( $daily_percent, 2 ),
				'resets_at' => gmdate( 'Y-m-d H:i:s', strtotime( 'tomorrow midnight' ) ),
			],
			'throttled' => $burst_count >= self::RATE_LIMIT_BURST || $daily_count >= self::RATE_LIMIT_DAILY,
			'location_id' => $location_id,
			'shared_across_sites' => is_multisite(),
		];
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
}
