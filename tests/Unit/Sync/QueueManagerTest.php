<?php
/**
 * Unit tests for QueueManager.
 *
 * Tests the queue backbone: add_to_queue deduplication, queue limits,
 * process_queue_item lifecycle (success, failure, rate limit, auth error),
 * health status calculation, backlog notifications, and scheduler fallback.
 *
 * Uses reflection to inject mock dependencies (RateLimiter, QueueProcessor,
 * QueueLogger, ContactCache) and a mock $wpdb global.
 *
 * @package Syncly\Tests\Unit\Sync
 */

declare(strict_types=1);

namespace Syncly\Tests\Unit\Sync;

use Syncly\Sync\QueueManager;
use Syncly\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class QueueManagerTest extends TestCase {

	private QueueManager $qm;

	/** @var \Mockery\MockInterface */
	private $wpdb;

	/** @var \Mockery\MockInterface */
	private $rate_limiter;

	/** @var \Mockery\MockInterface */
	private $processor;

	/** @var \Mockery\MockInterface */
	private $logger;

	/** @var \Mockery\MockInterface */
	private $settings_manager;

	/** @var \Mockery\MockInterface */
	private $notification_manager;

	/** @var \ReflectionClass */
	private \ReflectionClass $ref;

	protected function setUp(): void {
		parent::setUp();

		// WP function stubs (NOT add_action/do_action/apply_filters — Brain\Monkey handles those).
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'current_time' )->justReturn( '2026-03-20 12:00:00' );
		Functions\when( 'absint' )->alias( function ( $v ) { return abs( (int) $v ); } );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'get_site_transient' )->justReturn( false );
		Functions\when( 'set_site_transient' )->justReturn( true );
		Functions\when( 'delete_site_transient' )->justReturn( true );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'wp_schedule_event' )->justReturn( true );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'update_user_meta' )->justReturn( true );
		Functions\when( 'get_user_meta' )->justReturn( '' );

		// --- Mock $wpdb ---
		$this->wpdb         = Mockery::mock( \stdClass::class );
		$this->wpdb->prefix = 'wp_';
		$this->wpdb->insert_id = 0;

		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () { return func_get_arg( 0 ); } )
			->byDefault();

		$this->wpdb->shouldReceive( 'update' )->andReturn( true )->byDefault();
		$this->wpdb->shouldReceive( 'query' )->andReturn( 0 )->byDefault();

		$GLOBALS['wpdb'] = $this->wpdb;

		// --- Build QueueManager without constructor ---
		$this->resetSingleton( QueueManager::class );

		$this->ref = new \ReflectionClass( QueueManager::class );
		$inst       = $this->ref->newInstanceWithoutConstructor();

		$prop = $this->ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, $inst );

		// --- Mock helper dependencies ---
		$this->rate_limiter = Mockery::mock( 'Syncly\\Sync\\RateLimiter' );
		$this->rate_limiter->shouldReceive( 'check_limits' )->andReturn( true )->byDefault();
		$this->rate_limiter->shouldReceive( 'track_request' )->byDefault();
		$this->rate_limiter->shouldReceive( 'is_rate_limit_error' )->andReturn( false )->byDefault();
		$this->rate_limiter->shouldReceive( 'get_status' )->andReturn( [] )->byDefault();

		$contact_cache = Mockery::mock( 'Syncly\\Sync\\ContactCache' );

		$this->processor = Mockery::mock( 'Syncly\\Sync\\QueueProcessor' );

		$this->logger = Mockery::mock( 'Syncly\\Sync\\QueueLogger' );
		$this->logger->shouldReceive( 'log_event' )->byDefault();

		$this->inject( $inst, 'rate_limiter', $this->rate_limiter );
		$this->inject( $inst, 'contact_cache', $contact_cache );
		$this->inject( $inst, 'processor', $this->processor );
		$this->inject( $inst, 'logger', $this->logger );

		// --- Mock SettingsManager singleton (used by get_ghl_location_id / process_site_queue) ---
		$this->settings_manager = Mockery::mock( \Syncly\Core\SettingsManager::class );
		$this->settings_manager->shouldReceive( 'get_setting' )
			->andReturnUsing( function ( $key = null, $default = null ) {
				if ( 'location_id' === $key ) {
					return 'loc_test';
				}
				if ( 'batch_size' === $key ) {
					return 50;
				}
				return $default;
			} )
			->byDefault();
		$this->settings_manager->shouldReceive( 'is_connection_verified' )->andReturn( true )->byDefault();

		$sm_ref  = new \ReflectionClass( \Syncly\Core\SettingsManager::class );
		$sm_prop = $sm_ref->getProperty( 'instance' );
		$sm_prop->setAccessible( true );
		$sm_prop->setValue( null, $this->settings_manager );

		// --- Mock NotificationManager singleton (used by check_queue_backlog / failure path) ---
		$this->notification_manager = Mockery::mock( \Syncly\Admin\NotificationManager::class );
		$this->notification_manager->shouldReceive( 'send_queue_backlog' )->byDefault();
		$this->notification_manager->shouldReceive( 'send_sync_error' )->byDefault();

		$nm_ref  = new \ReflectionClass( \Syncly\Admin\NotificationManager::class );
		$nm_prop = $nm_ref->getProperty( 'instance' );
		$nm_prop->setAccessible( true );
		$nm_prop->setValue( null, $this->notification_manager );

		$this->qm = QueueManager::get_instance();
	}

	protected function tearDown(): void {
		$this->resetSingleton( QueueManager::class );
		$this->resetSingleton( \Syncly\Core\SettingsManager::class );
		$this->resetSingleton( \Syncly\Admin\NotificationManager::class );
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	//  Helpers
	// ------------------------------------------------------------------

	/**
	 * Inject a value into a private property via reflection.
	 */
	private function inject( object $obj, string $prop, $value ): void {
		$rp = $this->ref->getProperty( $prop );
		$rp->setAccessible( true );
		$rp->setValue( $obj, $value );
	}

	/**
	 * Build a fake queue item object.
	 */
	private function makeQueueItem(
		int $id,
		string $type,
		int $item_id,
		string $action,
		array $payload = [],
		int $attempts = 0
	): object {
		return (object) [
			'id'         => $id,
			'item_type'  => $type,
			'item_id'    => $item_id,
			'action'     => $action,
			'payload'    => json_encode( $payload ),
			'status'     => 'pending',
			'attempts'   => $attempts,
			'created_at' => '2026-03-20 12:00:00',
			'updated_at' => '2026-03-20 12:00:00',
			'site_id'    => 1,
		];
	}

	/**
	 * Call a private method on QueueManager.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	private function callPrivate( string $method, ...$args ) {
		$m = $this->ref->getMethod( $method );
		$m->setAccessible( true );
		return $m->invoke( $this->qm, ...$args );
	}

	// ==========================================
	//  add_to_queue()
	// ==========================================

	public function test_add_to_queue_inserts_new_item(): void {
		// First get_var → null (no duplicate), second get_var → 5 (queue count).
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( null, 5 );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing( function () {
				$this->wpdb->insert_id = 42;
				return true;
			} );

		$result = $this->qm->add_to_queue( 'user', 1, 'create', [ 'email' => 'test@example.com' ] );

		$this->assertSame( 42, $result );
	}

	public function test_add_to_queue_updates_existing_duplicate(): void {
		// Existing pending row with ID 10.
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 10 );

		$this->wpdb->shouldReceive( 'update' )
			->once()
			->andReturn( true );

		$result = $this->qm->add_to_queue( 'user', 1, 'create', [ 'email' => 'test@example.com' ] );

		$this->assertSame( 10, $result );
	}

	public function test_add_to_queue_returns_false_when_queue_full(): void {
		// No duplicate, queue count = 10000 (at limit).
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( null, 10000 );

		$result = $this->qm->add_to_queue( 'user', 1, 'create', [ 'email' => 'test@example.com' ] );

		$this->assertFalse( $result );
	}

	public function test_add_to_queue_stores_dependency_in_payload(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( null, 0 );

		$captured_data = null;
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturnUsing( function ( $table, $data ) use ( &$captured_data ) {
				$captured_data = $data;
				$this->wpdb->insert_id = 99;
				return true;
			} );

		$result = $this->qm->add_to_queue( 'user', 1, 'create', [ 'email' => 'hi@test.com' ], 50 );

		$this->assertSame( 99, $result );
		$decoded = json_decode( $captured_data['payload'], true );
		$this->assertSame( 50, $decoded['_depends_on_queue_id'] );
	}

	public function test_add_to_queue_returns_false_on_insert_failure(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( null, 0 );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$result = $this->qm->add_to_queue( 'user', 1, 'create', [] );

		$this->assertFalse( $result );
	}

	// ==========================================
	//  process_queue_item() — via reflection
	// ==========================================

	public function test_process_item_marks_failed_at_max_attempts(): void {
		$item = $this->makeQueueItem( 1, 'user', 42, 'create', [], 3 ); // MAX_ATTEMPTS = 3

		$status_captured = null;
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->andReturnUsing( function ( $table, $data ) use ( &$status_captured ) {
				$status_captured = $data['status'] ?? null;
				return true;
			} );

		$this->callPrivate( 'process_queue_item', $item );

		$this->assertSame( 'failed', $status_captured );
	}

	public function test_process_item_success_marks_completed(): void {
		$item = $this->makeQueueItem( 1, 'user', 42, 'create', [ 'email' => 'a@a.com' ], 0 );

		$this->settings_manager->shouldReceive( 'get_setting' )
			->with( 'location_id' )
			->andReturn( 'loc_123' );

		$this->processor->shouldReceive( 'execute_sync' )
			->with( 'user', 'create', 42, [ 'email' => 'a@a.com' ] )
			->once()
			->andReturn( [ 'contact' => [ 'id' => 'ghl_c_123' ] ] );

		// wpdb->update is called multiple times (attempts, completed).
		$statuses = [];
		$this->wpdb->shouldReceive( 'update' )
			->andReturnUsing( function ( $table, $data ) use ( &$statuses ) {
				if ( isset( $data['status'] ) ) {
					$statuses[] = $data['status'];
				}
				return true;
			} );

		$this->callPrivate( 'process_queue_item', $item );

		$this->assertContains( 'completed', $statuses );
	}

	public function test_process_item_extracts_contact_id_from_payload_fallback(): void {
		$item = $this->makeQueueItem(
			1,
			'user',
			42,
			'add_tags',
			[ 'contact_id' => 'c_from_payload', 'tags' => [ 'tag1' ] ],
			0
		);

		// Processor returns truthy but without contact.id.
		$this->processor->shouldReceive( 'execute_sync' )
			->once()
			->andReturn( true );

		$this->wpdb->shouldReceive( 'update' )->andReturn( true );

		// Verify logger receives 'c_from_payload' as the ghl_object_id (4th arg).
		$this->logger->shouldReceive( 'log_event' )
			->once()
			->with(
				42,                  // item_id
				'add_tags',          // action
				'success',           // status
				'c_from_payload',    // ghl_object_id ← extracted from payload fallback
				Mockery::any(),      // payload
				Mockery::any(),      // response
				Mockery::any(),      // error
				Mockery::any(),      // duration
				'user'               // item_type
			);

		$this->callPrivate( 'process_queue_item', $item );
	}

	public function test_process_item_daily_limit_sends_notification(): void {
		$item = $this->makeQueueItem( 1, 'user', 42, 'create', [ 'email' => 'a@a.com' ], 0 );

		// Rate limiter blocks (daily limit hit).
		$this->rate_limiter->shouldReceive( 'check_limits' )
			->with( 'loc_test' )
			->andReturn( false );

		$this->rate_limiter->shouldReceive( 'is_daily_limit_reached' )
			->with( 'loc_test' )
			->andReturn( true );

		$this->rate_limiter->shouldReceive( 'get_daily_count' )
			->with( 'loc_test' )
			->andReturn( 200000 );

		// Pending count query.
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( 350 );

		$this->notification_manager
			->shouldReceive( 'send_daily_limit_reached' )
			->once()
			->with( 200000, 350 );

		// Processor should NOT be called — item stays pending.
		$this->processor->shouldNotReceive( 'execute_sync' );

		$this->callPrivate( 'process_queue_item', $item );
	}

	public function test_process_item_burst_limit_does_not_send_daily_notification(): void {
		$item = $this->makeQueueItem( 1, 'user', 42, 'create', [ 'email' => 'a@a.com' ], 0 );

		// Rate limiter blocks (burst limit, not daily).
		$this->rate_limiter->shouldReceive( 'check_limits' )
			->with( 'loc_test' )
			->andReturn( false );

		$this->rate_limiter->shouldReceive( 'is_daily_limit_reached' )
			->with( 'loc_test' )
			->andReturn( false );

		// Daily notification should NOT fire for burst limits.
		$this->notification_manager->shouldNotReceive( 'send_daily_limit_reached' );

		$this->callPrivate( 'process_queue_item', $item );
	}

	public function test_process_item_rate_limit_resets_without_penalty(): void {
		$item = $this->makeQueueItem( 1, 'user', 42, 'create', [ 'email' => 'a@a.com' ], 0 );

		$this->settings_manager->shouldReceive( 'get_setting' )
			->with( 'location_id' )
			->andReturn( 'loc_123' );

		// Processor throws exception.
		$this->processor->shouldReceive( 'execute_sync' )
			->once()
			->andThrow( new \Exception( 'Too Many Requests' ) );

		// Rate limiter recognizes this as a rate-limit error.
		$this->rate_limiter->shouldReceive( 'is_rate_limit_error' )
			->andReturn( true );

		// Expect update with status 'pending' (reset, not 'failed').
		$status_captured = null;
		$this->wpdb->shouldReceive( 'update' )
			->andReturnUsing( function ( $table, $data ) use ( &$status_captured ) {
				// Capture the status from the rate-limit reset update (has error_message key).
				if ( isset( $data['error_message'] ) && isset( $data['status'] ) ) {
					$status_captured = $data['status'];
				}
				return true;
			} );

		$this->callPrivate( 'process_queue_item', $item );

		$this->assertSame( 'pending', $status_captured );
	}

	public function test_process_item_auth_error_rolls_back_attempts(): void {
		$item = $this->makeQueueItem( 1, 'user', 42, 'create', [ 'email' => 'a@a.com' ], 0 );

		$this->settings_manager->shouldReceive( 'get_setting' )
			->with( 'location_id' )
			->andReturn( 'loc_123' );

		// Processor throws auth-related exception.
		$this->processor->shouldReceive( 'execute_sync' )
			->once()
			->andThrow( new \Exception( 'Token refresh failed' ) );

		// Expect update that rolls back attempts.
		$captured_attempts = null;
		$this->wpdb->shouldReceive( 'update' )
			->andReturnUsing( function ( $table, $data ) use ( &$captured_attempts ) {
				if ( isset( $data['attempts'] ) && isset( $data['error_message'] ) ) {
					$captured_attempts = $data['attempts'];
				}
				return true;
			} );

		$this->callPrivate( 'process_queue_item', $item );

		// Attempts were incremented to 1 before the sync, then rolled back to 0.
		$this->assertSame( 0, $captured_attempts );
	}

	public function test_process_item_final_failure_sends_notification(): void {
		// Attempt 2 → incremented to 3 → matches MAX_ATTEMPTS → final failure.
		$item = $this->makeQueueItem( 1, 'user', 42, 'create', [ 'email' => 'a@a.com' ], 2 );

		$this->processor->shouldReceive( 'execute_sync' )
			->once()
			->andReturn( false ); // Falsy = failure.

		$this->wpdb->shouldReceive( 'update' )->andReturn( true );

		$this->notification_manager
			->shouldReceive( 'send_sync_error' )
			->once();

		$this->callPrivate( 'process_queue_item', $item );

		// Status should be 'failed' (not 'pending') since attempts >= MAX.
		$this->assertTrue( true, 'send_sync_error called on final failure' );
	}

	// ==========================================
	//  get_queue_status() — health thresholds
	// ==========================================

	public function test_queue_status_good_health(): void {
		// pending=10, failed=5, completed_24h=100, total=200, oldest=5min.
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 10, 5, 100, 200, 5 );

		$status = $this->qm->get_queue_status();

		$this->assertSame( 'good', $status['health'] );
		$this->assertSame( 10, $status['pending'] );
		$this->assertSame( 5, $status['failed'] );
		$this->assertEmpty( $status['warnings'] );
	}

	public function test_queue_status_warning_on_high_pending(): void {
		// pending=1500 (>1000 threshold).
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 1500, 5, 100, 2000, 10 );

		$status = $this->qm->get_queue_status();

		$this->assertSame( 'warning', $status['health'] );
		$this->assertNotEmpty( $status['warnings'] );
	}

	public function test_queue_status_critical_on_very_high_pending(): void {
		// pending=6000 (>5000 threshold).
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 6000, 5, 100, 8000, 10 );

		$status = $this->qm->get_queue_status();

		$this->assertSame( 'critical', $status['health'] );
	}

	public function test_queue_status_warning_on_high_failures(): void {
		// pending=50 (ok), failed=150 (>100 threshold).
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 50, 150, 100, 500, 5 );

		$status = $this->qm->get_queue_status();

		$this->assertSame( 'warning', $status['health'] );
		$this->assertNotEmpty( array_filter(
			$status['warnings'],
			fn( $w ) => str_contains( $w, 'failure' )
		) );
	}

	public function test_queue_status_warning_on_old_pending(): void {
		// pending=50, failed=5, completed=100, total=200, oldest=90min (>60 threshold).
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 50, 5, 100, 200, 90 );

		$status = $this->qm->get_queue_status();

		$this->assertSame( 'warning', $status['health'] );
		$this->assertSame( 90, $status['oldest_pending_minutes'] );
	}

	// ==========================================
	//  check_queue_backlog()
	// ==========================================

	public function test_backlog_no_notification_under_threshold(): void {
		// pending = 500 (under default 1000 threshold).
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( 500 );

		$this->notification_manager->shouldNotReceive( 'send_queue_backlog' );

		$this->qm->check_queue_backlog();
	}

	public function test_backlog_sends_notification_over_threshold(): void {
		// pending = 2000 (over 1000 threshold), no cooldown.
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( 2000 );

		$this->notification_manager
			->shouldReceive( 'send_queue_backlog' )
			->once()
			->with( 2000 );

		$this->qm->check_queue_backlog();
	}

	public function test_backlog_cooldown_prevents_spam(): void {
		// pending = 2000 (over threshold) but cooldown transient is set.
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( 2000 );

		Functions\when( 'get_transient' )->alias( function ( $key ) {
			return $key === 'syncly_backlog_notified' ? true : false;
		} );

		$this->notification_manager->shouldNotReceive( 'send_queue_backlog' );

		$this->qm->check_queue_backlog();
	}

	// ==========================================
	//  schedule_queue_processor()
	//  NOTE: WP-Cron test MUST come first — once Brain\Monkey
	//  defines as_next_scheduled_action, it persists for the
	//  process and function_exists() returns true permanently.
	// ==========================================

	public function test_schedule_falls_back_to_wp_cron(): void {
		// as_next_scheduled_action is NOT defined → falls through to WP-Cron.
		$cron_scheduled = false;
		Functions\when( 'wp_schedule_event' )->alias(
			function () use ( &$cron_scheduled ) {
				$cron_scheduled = true;
				return true;
			}
		);

		$this->qm->schedule_queue_processor();

		$this->assertTrue( $cron_scheduled, 'Should fall back to WP-Cron' );
	}

	public function test_schedule_uses_action_scheduler_when_available(): void {
		// Define AS functions to make function_exists() return true.
		Functions\when( 'as_next_scheduled_action' )->justReturn( false );

		$scheduled = false;
		Functions\when( 'as_schedule_recurring_action' )->alias(
			function () use ( &$scheduled ) {
				$scheduled = true;
				return 1;
			}
		);

		$this->qm->schedule_queue_processor();

		$this->assertTrue( $scheduled, 'Should schedule via Action Scheduler' );
	}

	// ==========================================
	//  Static helpers
	// ==========================================

	public function test_is_action_scheduler_available_reflects_function_existence(): void {
		// After test_schedule_uses_action_scheduler_when_available defines the AS
		// function, it persists for the process. Verify the method reflects reality.
		$available = QueueManager::is_action_scheduler_available();
		$type      = QueueManager::get_scheduler_type();

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			$this->assertTrue( $available );
			$this->assertSame( 'action_scheduler', $type );
		} else {
			$this->assertFalse( $available );
			$this->assertSame( 'wp_cron', $type );
		}
	}

	public function test_get_friendly_label_for_known_types(): void {
		$label = $this->callPrivate( 'get_friendly_sync_type_label', 'user', 'create' );
		$this->assertSame( 'WordPress User', $label );

		$label = $this->callPrivate( 'get_friendly_sync_type_label', 'wc_customer', 'add_tags' );
		$this->assertSame( 'WooCommerce Order (Add Tags)', $label );
	}

	public function test_get_friendly_label_for_unknown_type(): void {
		$label = $this->callPrivate( 'get_friendly_sync_type_label', 'custom_widget', 'update' );
		$this->assertSame( 'Custom Widget', $label );
	}
}
