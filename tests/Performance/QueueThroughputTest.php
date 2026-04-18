<?php
/**
 * Queue throughput benchmark.
 *
 * Measures how fast the queue code can insert and process items,
 * then compares against the GHL API rate limit (100 req / 10 sec).
 *
 * Run only benchmarks:
 *   vendor/bin/phpunit --testsuite Performance
 *
 * This reveals whether **your PHP/DB code** or **the GHL API** is the bottleneck.
 * In production the answer is almost always the API, but if your code is slow
 * the queue will fall behind even before hitting rate limits.
 *
 * Results table printed to stdout at the end of each test.
 *
 * @package GHL_CRM_Integration\Tests\Performance
 */

declare(strict_types=1);

namespace GHL_CRM\Tests\Performance;

use GHL_CRM\Sync\QueueManager;
use GHL_CRM\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class QueueThroughputTest extends TestCase {

	private QueueManager $qm;

	/** @var \Mockery\MockInterface */
	private $wpdb;

	/** @var \Mockery\MockInterface */
	private $processor;

	/** @var \ReflectionClass */
	private \ReflectionClass $ref;

	/**
	 * Stored results from each benchmark, printed in a summary table at the end.
	 *
	 * @var array<string, array>
	 */
	private static array $results = [];

	// ------------------------------------------------------------------
	//  GHL rate limits (official docs)
	// ------------------------------------------------------------------

	/** Burst limit: 100 requests per 10 seconds per location */
	private const GHL_BURST_LIMIT = 100;
	private const GHL_BURST_WINDOW = 10; // seconds

	/** Daily limit: 200,000 requests per day per location */
	private const GHL_DAILY_LIMIT = 200_000;

	// ------------------------------------------------------------------
	//  Action Scheduler constraints
	// ------------------------------------------------------------------

	/** Action Scheduler fires process_queue every N seconds */
	private const AS_INTERVAL = 10;

	/** Default batch size per AS run */
	private const DEFAULT_BATCH = 50;

	/** Batch sizes to benchmark (configurable via plugin settings 1-500) */
	private const BATCH_SIZES = [ 10, 25, 50, 100, 200, 500 ];

	/** Simulated API latencies in ms (GHL typical range) */
	private const API_LATENCIES_MS = [ 30, 50, 100, 200 ];

	// ------------------------------------------------------------------
	//  Test sizes — number of queue items per benchmark
	// ------------------------------------------------------------------

	/** @var int[] Sizes for lightweight ops (INSERT/DEDUP) */
	private const LOAD_SIZES = [ 100, 500, 1_000, 5_000, 10_000 ];

	/** @var int[] Sizes for heavy ops (PROCESS) — smaller to avoid OOM
	 * from register_shutdown_function accumulation in process_queue_item */
	private const PROCESS_SIZES = [ 100, 500, 1_000, 2_000 ];

	// ------------------------------------------------------------------
	//  setUp / tearDown
	// ------------------------------------------------------------------

	protected function setUp(): void {
		parent::setUp();

		// WP Stubs.
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

		// Mock $wpdb — fast in-memory simulation.
		$this->wpdb           = Mockery::mock( \stdClass::class );
		$this->wpdb->prefix   = 'wp_';
		$this->wpdb->insert_id = 0;
		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing( function () { return func_get_arg( 0 ); } )
			->byDefault();
		$this->wpdb->shouldReceive( 'update' )->andReturn( true )->byDefault();
		$this->wpdb->shouldReceive( 'query' )->andReturn( 0 )->byDefault();

		$GLOBALS['wpdb'] = $this->wpdb;

		// Build QueueManager without constructor.
		$this->resetSingleton( QueueManager::class );
		$this->ref = new \ReflectionClass( QueueManager::class );
		$inst       = $this->ref->newInstanceWithoutConstructor();

		$prop = $this->ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, $inst );

		// Mock helpers.
		$rate_limiter = Mockery::mock( 'GHL_CRM\\Sync\\RateLimiter' );
		$rate_limiter->shouldReceive( 'check_limits' )->andReturn( true )->byDefault();
		$rate_limiter->shouldReceive( 'track_request' )->byDefault();
		$rate_limiter->shouldReceive( 'is_rate_limit_error' )->andReturn( false )->byDefault();
		$rate_limiter->shouldReceive( 'get_status' )->andReturn( [] )->byDefault();

		$contact_cache = Mockery::mock( 'GHL_CRM\\Sync\\ContactCache' );

		$this->processor = Mockery::mock( 'GHL_CRM\\Sync\\QueueProcessor' );

		$logger = Mockery::mock( 'GHL_CRM\\Sync\\QueueLogger' );
		$logger->shouldReceive( 'log_event' )->byDefault();

		$this->inject( $inst, 'rate_limiter', $rate_limiter );
		$this->inject( $inst, 'contact_cache', $contact_cache );
		$this->inject( $inst, 'processor', $this->processor );
		$this->inject( $inst, 'logger', $logger );

		// SettingsManager mock.
		$sm = Mockery::mock( \GHL_CRM\Core\SettingsManager::class );
		$sm->shouldReceive( 'get_setting' )->andReturnUsing( function ( $key = null, $default = null ) {
			return match ( $key ) {
				'location_id' => 'loc_bench',
				'batch_size'  => 50,
				default       => $default,
			};
		} )->byDefault();
		$sm->shouldReceive( 'is_connection_verified' )->andReturn( true )->byDefault();

		$sm_ref  = new \ReflectionClass( \GHL_CRM\Core\SettingsManager::class );
		$sm_prop = $sm_ref->getProperty( 'instance' );
		$sm_prop->setAccessible( true );
		$sm_prop->setValue( null, $sm );

		// NotificationManager mock.
		$nm = Mockery::mock( \GHL_CRM\Admin\NotificationManager::class );
		$nm->shouldReceive( 'send_queue_backlog' )->byDefault();
		$nm->shouldReceive( 'send_sync_error' )->byDefault();

		$nm_ref  = new \ReflectionClass( \GHL_CRM\Admin\NotificationManager::class );
		$nm_prop = $nm_ref->getProperty( 'instance' );
		$nm_prop->setAccessible( true );
		$nm_prop->setValue( null, $nm );

		$this->qm = QueueManager::get_instance();
	}

	protected function tearDown(): void {
		$this->resetSingleton( QueueManager::class );
		$this->resetSingleton( \GHL_CRM\Core\SettingsManager::class );
		$this->resetSingleton( \GHL_CRM\Admin\NotificationManager::class );
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	//  Helpers
	// ------------------------------------------------------------------

	private function inject( object $obj, string $prop, $value ): void {
		$rp = $this->ref->getProperty( $prop );
		$rp->setAccessible( true );
		$rp->setValue( $obj, $value );
	}

	private function callPrivate( string $method, ...$args ) {
		$m = $this->ref->getMethod( $method );
		$m->setAccessible( true );
		return $m->invoke( $this->qm, ...$args );
	}

	private function makeQueueItem( int $id, string $type, int $item_id, string $action, array $payload = [] ): object {
		return (object) [
			'id'         => $id,
			'item_type'  => $type,
			'item_id'    => $item_id,
			'action'     => $action,
			'payload'    => json_encode( $payload ),
			'status'     => 'pending',
			'attempts'   => 0,
			'created_at' => '2026-03-20 12:00:00',
			'updated_at' => '2026-03-20 12:00:00',
			'site_id'    => 1,
		];
	}

	/**
	 * Record a benchmark result for the final summary.
	 */
	private function record( string $label, int $count, float $seconds ): void {
		$per_sec = $seconds > 0 ? $count / $seconds : PHP_INT_MAX;

		self::$results[ $label ] = [
			'items'   => $count,
			'time_ms' => round( $seconds * 1000, 2 ),
			'per_sec' => round( $per_sec, 1 ),
		];

		// Print inline so the user sees it even without --testdox.
		fwrite( STDERR, sprintf(
			"\n    ⏱  %-45s %6d items  %8.1f ms  %8.1f items/sec",
			$label,
			$count,
			$seconds * 1000,
			$per_sec
		) );
	}

	// ==================================================================
	//  BENCHMARK 1 — add_to_queue() INSERT throughput
	//  (Measures: PHP overhead + serializing payloads. No real DB.)
	// ==================================================================

	/**
	 * @dataProvider loadSizeProvider
	 */
	public function test_insert_throughput( int $count ): void {
		$insert_counter = 0;

		// Simulate: no duplicates, queue never full.
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( null, 0 );

		$this->wpdb->shouldReceive( 'insert' )
			->andReturnUsing( function () use ( &$insert_counter ) {
				++$insert_counter;
				$this->wpdb->insert_id = $insert_counter;
				return true;
			} );

		$start = hrtime( true );

		for ( $i = 1; $i <= $count; $i++ ) {
			$result = $this->qm->add_to_queue( 'user', $i, 'create', [
				'email'      => "user{$i}@example.com",
				'first_name' => "User {$i}",
				'last_name'  => 'Load Test',
				'phone'      => '+1555000' . str_pad( (string) $i, 4, '0', STR_PAD_LEFT ),
				'tags'       => [ 'load_test', "batch_{$count}" ],
			]);

			$this->assertIsInt( $result );
		}

		$elapsed = ( hrtime( true ) - $start ) / 1e9;

		$this->assertSame( $count, $insert_counter );
		$this->record( "INSERT ({$count} items)", $count, $elapsed );
	}

	// ==================================================================
	//  BENCHMARK 2 — add_to_queue() DEDUP throughput
	//  (Measures: duplicate detection + payload UPDATE. No real DB.)
	// ==================================================================

	/**
	 * @dataProvider loadSizeProvider
	 */
	public function test_dedup_throughput( int $count ): void {
		// Simulate: every item is a duplicate (existing id = 99).
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 99 );

		$this->wpdb->shouldReceive( 'update' )
			->andReturn( true );

		$start = hrtime( true );

		for ( $i = 1; $i <= $count; $i++ ) {
			$result = $this->qm->add_to_queue( 'user', 1, 'create', [
				'email' => "user{$i}@example.com",
			]);

			$this->assertSame( 99, $result );
		}

		$elapsed = ( hrtime( true ) - $start ) / 1e9;
		$this->record( "DEDUP UPDATE ({$count} items)", $count, $elapsed );
	}

	// ==================================================================
	//  BENCHMARK 3 — process_queue_item() throughput
	//  (Measures: full processing pipeline with mocked API.
	//   This is the realistic ceiling — how fast PHP can drive the queue.)
	// ==================================================================

	/**
	 * @dataProvider processSizeProvider
	 */
	public function test_process_throughput( int $count ): void {
		// Processor always succeeds instantly (0ms API latency → pure PHP overhead).
		$this->processor->shouldReceive( 'execute_sync' )
			->andReturn( [ 'contact' => [ 'id' => 'c_bench_123' ] ] );

		$this->wpdb->shouldReceive( 'update' )->andReturn( true );

		$start = hrtime( true );

		for ( $i = 1; $i <= $count; $i++ ) {
			$item = $this->makeQueueItem( $i, 'user', $i, 'create', [
				'email' => "user{$i}@example.com",
			]);
			$this->callPrivate( 'process_queue_item', $item );
		}

		$elapsed = ( hrtime( true ) - $start ) / 1e9;
		$this->record( "PROCESS ({$count} items, 0ms API)", $count, $elapsed );
	}

	// ==================================================================
	//  BENCHMARK 4 — process_queue_item() with simulated API latency
	//  (50ms per call — typical GHL response time)
	// ==================================================================

	public function test_process_with_simulated_api_latency(): void {
		$api_latency_ms = 50;
		$count          = 100; // Keep small — 100 × 50ms = 5 seconds.

		$this->processor->shouldReceive( 'execute_sync' )
			->andReturnUsing( function () use ( $api_latency_ms ) {
				usleep( $api_latency_ms * 1000 );
				return [ 'contact' => [ 'id' => 'c_bench_123' ] ];
			} );

		$this->wpdb->shouldReceive( 'update' )->andReturn( true );

		$start = hrtime( true );

		for ( $i = 1; $i <= $count; $i++ ) {
			$item = $this->makeQueueItem( $i, 'user', $i, 'create', [
				'email' => "user{$i}@example.com",
			]);
			$this->callPrivate( 'process_queue_item', $item );
		}

		$elapsed = ( hrtime( true ) - $start ) / 1e9;
		$this->record( "PROCESS ({$count} items, {$api_latency_ms}ms API)", $count, $elapsed );
	}

	// ==================================================================
	//  BENCHMARK 5 — Mixed workload (realistic production mix)
	//  60% user creates, 20% WC orders, 10% tag ops, 10% LearnDash
	// ==================================================================

	public function test_mixed_workload_throughput(): void {
		$count = 1_000;

		$this->processor->shouldReceive( 'execute_sync' )
			->andReturn( [ 'contact' => [ 'id' => 'c_bench_123' ] ] );

		$this->wpdb->shouldReceive( 'update' )->andReturn( true );

		$types = [
			[ 'user', 'create' ],
			[ 'user', 'create' ],
			[ 'user', 'create' ],
			[ 'user', 'update' ],
			[ 'user', 'update' ],
			[ 'user', 'update' ],
			[ 'wc_customer', 'create' ],
			[ 'wc_customer', 'create' ],
			[ 'user', 'add_tags' ],
			[ 'learndash_course', 'create' ],
		];

		$start = hrtime( true );

		for ( $i = 1; $i <= $count; $i++ ) {
			$pick = $types[ $i % count( $types ) ];
			$item = $this->makeQueueItem( $i, $pick[0], $i, $pick[1], [
				'email' => "user{$i}@example.com",
				'tags'  => [ 'tag_001' ],
			]);
			$this->callPrivate( 'process_queue_item', $item );
		}

		$elapsed = ( hrtime( true ) - $start ) / 1e9;
		$this->record( "MIXED WORKLOAD ({$count} items)", $count, $elapsed );
	}

	// ==================================================================
	//  BENCHMARK 6 — Failure + retry storm
	//  (All items fail — measures error-handling overhead)
	// ==================================================================

	public function test_failure_handling_throughput(): void {
		$count = 1_000;

		// Every sync fails.
		$this->processor->shouldReceive( 'execute_sync' )
			->andReturn( false );

		$this->wpdb->shouldReceive( 'update' )->andReturn( true );

		$start = hrtime( true );

		for ( $i = 1; $i <= $count; $i++ ) {
			$item = $this->makeQueueItem( $i, 'user', $i, 'create', [
				'email' => "user{$i}@example.com",
			]);
			$this->callPrivate( 'process_queue_item', $item );
		}

		$elapsed = ( hrtime( true ) - $start ) / 1e9;
		$this->record( "FAILURE STORM ({$count} items)", $count, $elapsed );
	}

	// ==================================================================
	//  BENCHMARK 7 — get_queue_status() under load
	//  (Simulates dashboard polling while queue is processing)
	// ==================================================================

	public function test_queue_status_overhead(): void {
		$calls = 1_000;

		// Simulate moderate queue.
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 500, 10, 200, 1000, 15 );

		$start = hrtime( true );

		for ( $i = 0; $i < $calls; $i++ ) {
			$status = $this->qm->get_queue_status();
			$this->assertSame( 'good', $status['health'] );
		}

		$elapsed = ( hrtime( true ) - $start ) / 1e9;
		$this->record( "STATUS CHECK ({$calls} calls)", $calls, $elapsed );
	}

	// ==================================================================
	//  BENCHMARK 8 — Action Scheduler batch cycle simulation
	//  Simulates ONE AS run: fetch batch, process N items sequentially,
	//  each with realistic API latency. Shows whether the batch can
	//  finish within the 10-second AS interval.
	// ==================================================================

	/**
	 * @dataProvider batchCycleProvider
	 */
	public function test_action_scheduler_batch_cycle( int $batch_size, int $api_latency_ms ): void {
		$this->processor->shouldReceive( 'execute_sync' )
			->andReturnUsing( function () use ( $api_latency_ms ) {
				usleep( $api_latency_ms * 1000 );
				return [ 'contact' => [ 'id' => 'c_bench_123' ] ];
			} );

		$this->wpdb->shouldReceive( 'update' )->andReturn( true );

		$start = hrtime( true );

		for ( $i = 1; $i <= $batch_size; $i++ ) {
			$item = $this->makeQueueItem( $i, 'user', $i, 'create', [
				'email' => "user{$i}@example.com",
			]);
			$this->callPrivate( 'process_queue_item', $item );
		}

		$elapsed = ( hrtime( true ) - $start ) / 1e9;

		$fits_in_window = $elapsed < self::AS_INTERVAL;
		$items_per_hour = ( $batch_size / max( $elapsed, self::AS_INTERVAL ) ) * 3600;

		$label = sprintf(
			'AS BATCH %d items @ %dms API',
			$batch_size,
			$api_latency_ms
		);

		self::$results[ $label ] = [
			'items'       => $batch_size,
			'time_ms'     => round( $elapsed * 1000, 2 ),
			'per_sec'     => $elapsed > 0 ? round( $batch_size / $elapsed, 1 ) : 0,
			'per_hour'    => round( $items_per_hour ),
			'fits_window' => $fits_in_window,
			'api_ms'      => $api_latency_ms,
			'batch_size'  => $batch_size,
			'is_as_cycle' => true,
		];

		fwrite( STDERR, sprintf(
			"\n    ⏱  %-45s %4d items  %8.1f ms  %s  ~%s/hr",
			$label,
			$batch_size,
			$elapsed * 1000,
			$fits_in_window ? '✅ fits 10s' : '⚠️  OVERFLOW',
			number_format( (int) $items_per_hour )
		) );

		$this->assertTrue( true ); // Always passes — this is a measurement.
	}

	// ==================================================================
	//  Data provider
	// ==================================================================

	/**
	 * @return array<string, array{int}>
	 */
	public static function loadSizeProvider(): array {
		$data = [];
		foreach ( self::LOAD_SIZES as $size ) {
			$data[ number_format( $size ) . ' items' ] = [ $size ];
		}
		return $data;
	}

	/**
	 * @return array<string, array{int}>
	 */
	public static function processSizeProvider(): array {
		$data = [];
		foreach ( self::PROCESS_SIZES as $size ) {
			$data[ number_format( $size ) . ' items' ] = [ $size ];
		}
		return $data;
	}

	/**
	 * Batch cycle combinations: (batch_size, api_latency_ms).
	 * Only tests realistic combos to keep runtime reasonable.
	 *
	 * @return array<string, array{int, int}>
	 */
	public static function batchCycleProvider(): array {
		// Key combos: small/medium/large batch × fast/typical/slow API.
		return [
			'10 items @ 50ms'   => [ 10, 50 ],
			'25 items @ 50ms'   => [ 25, 50 ],
			'50 items @ 30ms'   => [ 50, 30 ],
			'50 items @ 50ms'   => [ 50, 50 ],   // DEFAULT config
			'50 items @ 100ms'  => [ 50, 100 ],
			'100 items @ 30ms'  => [ 100, 30 ],
			'100 items @ 50ms'  => [ 100, 50 ],  // GHL burst max
			'100 items @ 100ms' => [ 100, 100 ],
			'200 items @ 50ms'  => [ 200, 50 ],  // Beyond burst limit
		];
	}

	// ==================================================================
	//  Summary — printed after all benchmarks complete
	// ==================================================================

	public static function tearDownAfterClass(): void {
		if ( empty( self::$results ) ) {
			return;
		}

		$ghl_burst_per_sec = self::GHL_BURST_LIMIT / self::GHL_BURST_WINDOW;
		$ghl_daily_per_sec = self::GHL_DAILY_LIMIT / 86400;

		$sep = str_repeat( '─', 90 );

		// ── Section 1: Raw throughput ──
		fwrite( STDERR, "\n\n" );
		fwrite( STDERR, "╔══════════════════════════════════════════════════════════════════════════════════════════╗\n" );
		fwrite( STDERR, "║                          QUEUE THROUGHPUT BENCHMARK SUMMARY                            ║\n" );
		fwrite( STDERR, "╠══════════════════════════════════════════════════════════════════════════════════════════╣\n" );
		fwrite( STDERR, sprintf(
			"║  %-47s %7s  %10s  %12s ║\n",
			'Benchmark',
			'Items',
			'Time (ms)',
			'Items/sec'
		) );
		fwrite( STDERR, "║  {$sep} ║\n" );

		$max_process_rate = 0;

		foreach ( self::$results as $label => $data ) {
			if ( ! empty( $data['is_as_cycle'] ) ) {
				continue; // Print AS cycles in separate section.
			}

			fwrite( STDERR, sprintf(
				"║  %-47s %7d  %10.1f  %12.1f ║\n",
				$label,
				$data['items'],
				$data['time_ms'],
				$data['per_sec']
			) );

			if ( str_starts_with( $label, 'PROCESS' ) && $data['per_sec'] > $max_process_rate ) {
				$max_process_rate = $data['per_sec'];
			}
		}

		// ── Section 2: Action Scheduler batch cycles ──
		$as_cycles = array_filter( self::$results, fn( $d ) => ! empty( $d['is_as_cycle'] ) );

		if ( ! empty( $as_cycles ) ) {
			fwrite( STDERR, "╠══════════════════════════════════════════════════════════════════════════════════════════╣\n" );
			fwrite( STDERR, "║  ACTION SCHEDULER BATCH SIMULATION  (AS fires every 10s)                               ║\n" );
			fwrite( STDERR, "╠══════════════════════════════════════════════════════════════════════════════════════════╣\n" );
			fwrite( STDERR, sprintf(
				"║  %-30s %6s  %8s  %10s  %10s  %9s ║\n",
				'Config',
				'Batch',
				'API ms',
				'Time (ms)',
				'Items/hr',
				'Fits 10s?'
			) );
			fwrite( STDERR, "║  {$sep} ║\n" );

			foreach ( $as_cycles as $label => $data ) {
				$per_hour = $data['per_hour'];

				// Cap items/hr by GHL burst limit: max 100 items per 10s window.
				$ghl_capped_per_hour = min( $per_hour, self::GHL_BURST_LIMIT * 360 );

				$fits = $data['fits_window'] ? '  ✅' : '  ⚠️';

				// Warn if batch > burst limit.
				$burst_warn = $data['batch_size'] > self::GHL_BURST_LIMIT ? ' ⛔BURST' : '';

				fwrite( STDERR, sprintf(
					"║  %-30s %6d  %8d  %10.1f  %10s  %5s%s ║\n",
					$label,
					$data['batch_size'],
					$data['api_ms'],
					$data['time_ms'],
					number_format( (int) $ghl_capped_per_hour ),
					$fits,
					$burst_warn
				) );
			}
		}

		// ── Section 3: Limits + bottleneck ──
		fwrite( STDERR, "╠══════════════════════════════════════════════════════════════════════════════════════════╣\n" );
		fwrite( STDERR, sprintf(
			"║  GHL API burst limit:  %5.1f req/sec  (100 per 10 seconds)                              ║\n",
			$ghl_burst_per_sec
		) );
		fwrite( STDERR, sprintf(
			"║  GHL API daily limit:  %5.1f req/sec  (200,000 per day)                                 ║\n",
			$ghl_daily_per_sec
		) );
		fwrite( STDERR, sprintf(
			"║  Action Scheduler:     every %ds  (batch_size configurable 1-500)                      ║\n",
			self::AS_INTERVAL
		) );

		if ( $max_process_rate > 0 ) {
			$bottleneck = $max_process_rate > $ghl_burst_per_sec ? 'GHL API rate limit' : 'PHP processing speed';
			$headroom   = $max_process_rate / $ghl_burst_per_sec;

			fwrite( STDERR, "╠══════════════════════════════════════════════════════════════════════════════════════════╣\n" );
			fwrite( STDERR, sprintf(
				"║  PHP code ceiling:     %8.1f items/sec  (0ms API latency)                           ║\n",
				$max_process_rate
			) );
			fwrite( STDERR, sprintf(
				"║  Headroom vs burst:    %8.1fx  faster than GHL allows                               ║\n",
				$headroom
			) );
			fwrite( STDERR, sprintf(
				"║  ⚡ BOTTLENECK:         %-60s   ║\n",
				$bottleneck
			) );
		}

		// ── Section 4: Realistic production capacity ──
		// Best case: batch_size=100 (GHL burst max) at typical ~50ms API.
		$realistic_batch = min( 100, self::GHL_BURST_LIMIT );
		$typical_api_ms  = 50;
		$time_per_batch  = $realistic_batch * ( $typical_api_ms / 1000 ); // Just API time.
		$batches_per_hr  = 3600 / max( $time_per_batch, (float) self::AS_INTERVAL );
		$items_per_hr_as = $realistic_batch * $batches_per_hr;
		$items_per_day_as = $items_per_hr_as * 24;

		$effective_per_day = min( $items_per_day_as, (float) self::GHL_DAILY_LIMIT );

		fwrite( STDERR, "╠══════════════════════════════════════════════════════════════════════════════════════════╣\n" );
		fwrite( STDERR, "║  REALISTIC PRODUCTION CAPACITY (24/7 site, Action Scheduler)                           ║\n" );
		fwrite( STDERR, sprintf(
			"║  Scenario: batch_size=%d, ~%dms avg API latency, AS every %ds                          ║\n",
			$realistic_batch,
			$typical_api_ms,
			self::AS_INTERVAL
		) );
		fwrite( STDERR, sprintf(
			"║  Time per batch:       %6.1fs  (%d items × %dms)                                      ║\n",
			$time_per_batch,
			$realistic_batch,
			$typical_api_ms
		) );
		fwrite( STDERR, sprintf(
			"║  Batches per hour:     %6.0f   (AS interval = %ds, batch time = %.1fs)                 ║\n",
			$batches_per_hr,
			self::AS_INTERVAL,
			$time_per_batch
		) );
		fwrite( STDERR, sprintf(
			"║  Max by AS + API:      %10s items/day                                               ║\n",
			number_format( (int) $items_per_day_as )
		) );
		fwrite( STDERR, sprintf(
			"║  Max by GHL daily cap: %10s items/day                                               ║\n",
			number_format( self::GHL_DAILY_LIMIT )
		) );
		fwrite( STDERR, sprintf(
			"║  ✅ EFFECTIVE CAPACITY: %10s items/day                                               ║\n",
			number_format( (int) $effective_per_day )
		) );

		// ── Section 5: Quick reference — what batch_size to use ──
		fwrite( STDERR, "╠══════════════════════════════════════════════════════════════════════════════════════════╣\n" );
		fwrite( STDERR, "║  BATCH SIZE GUIDE (what to set in plugin settings)                                     ║\n" );
		fwrite( STDERR, "║  ────────────────────────────────────────────────────────────────────────               ║\n" );

		$guide = [
			[ 10,  'Light site, few users (safest)'             ],
			[ 25,  'Small business, < 100 orders/day'           ],
			[ 50,  'DEFAULT — good balance for most sites'      ],
			[ 100, 'High volume — maxes GHL burst limit'        ],
			[ 200, 'RISKY — exceeds burst, may get rate-limited'  ],
			[ 500, 'DANGEROUS — will definitely get throttled'  ],
		];

		foreach ( $guide as $g ) {
			$per_day_est = min(
				$g[0] * ( 3600 / (float) self::AS_INTERVAL ) * 24,
				(float) self::GHL_DAILY_LIMIT
			);
			$flag = $g[0] > self::GHL_BURST_LIMIT ? '⚠️ ' : '   ';
			fwrite( STDERR, sprintf(
				"║  %s batch_size=%-3d → ~%8s/day  %-40s ║\n",
				$flag,
				$g[0],
				number_format( (int) $per_day_est ),
				$g[1]
			) );
		}

		fwrite( STDERR, "╚══════════════════════════════════════════════════════════════════════════════════════════╝\n\n" );
	}
}
