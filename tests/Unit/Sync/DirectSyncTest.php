<?php
declare(strict_types=1);

namespace Syncly\Tests\Unit\Sync;

use Brain\Monkey\Functions;
use Mockery;
use Syncly\Core\SettingsManager;
use Syncly\Sync\QueueManager;
use Syncly\Tests\TestCase;

class DirectSyncTest extends TestCase {
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_registration_success_runs_pending_tag_callback_directly_under_same_user_lock(): void {
		define( 'SYNCLY_DIRECT_SYNC', true );
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'current_time' )->justReturn( '2026-11-07 12:00:00' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'lease-test' );
		$lock_attempts = 0;
		Functions\when( 'add_option' )->alias( static function () use ( &$lock_attempts ) { return ++$lock_attempts === 1; } );
		Functions\when( 'wp_cache_delete' )->justReturn( true );

		$wpdb = Mockery::mock( \stdClass::class );
		$wpdb->prefix = 'wp_';
		$wpdb->options = 'wp_options';
		$wpdb->insert_id = 0;
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( static function ( $sql ) { return $sql; } );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'query' )->andReturn( 1 );
		$statuses = [];
		$wpdb->shouldReceive( 'insert' )->andReturnUsing( static function ( $table, $data ) use ( $wpdb, &$statuses ) {
			$statuses[] = $data['status'];
			$wpdb->insert_id = count( $statuses );
			return true;
		} );
		$GLOBALS['wpdb'] = $wpdb;

		$settings = Mockery::mock( SettingsManager::class );
		$settings->shouldReceive( 'get_settings_array' )->andReturn( [] );
		$settings->shouldReceive( 'is_connection_verified' )->andReturn( true );
		$settings->shouldReceive( 'get_setting' )->with( 'location_id' )->andReturn( 'loc-test' );
		( new \ReflectionProperty( SettingsManager::class, 'instance' ) )->setValue( null, $settings );

		$manager = ( new \ReflectionClass( QueueManager::class ) )->newInstanceWithoutConstructor();
		$processor = Mockery::mock( 'Syncly\\Sync\\QueueProcessor' );
		$processor->shouldReceive( 'execute_sync' )->once()->with( 'user', 'user_register', 42, Mockery::any() )->andReturn( [ 'contact' => [ 'id' => 'ghl-42' ] ] );
		$processor->shouldReceive( 'execute_sync' )->once()->with( 'user', 'add_tags', 42, [ 'contact_id' => 'ghl-42', 'tags' => [ 'vip' ] ] )->andReturn( [ 'success' => true, 'contact_id' => 'ghl-42' ] );
		$limiter = Mockery::mock( 'Syncly\\Sync\\RateLimiter' );
		$limiter->shouldReceive( 'reserve_request' )->twice()->andReturn( true );
		$logger = Mockery::mock( 'Syncly\\Sync\\QueueLogger' );
		$logger->shouldReceive( 'log_event' )->twice()->andReturnNull();
		foreach ( [ 'processor' => $processor, 'rate_limiter' => $limiter, 'logger' => $logger ] as $name => $value ) {
			( new \ReflectionProperty( QueueManager::class, $name ) )->setValue( $manager, $value );
		}
		$child_result = null;
		Functions\when( 'do_action' )->alias( static function ( $hook, $item ) use ( $manager, &$child_result ) {
			if ( 'syncly_after_sync_success' === $hook && 'user_register' === $item->action ) {
				$child_result = $manager->add_to_queue( 'user', 42, 'add_tags', [ 'contact_id' => 'ghl-42', 'tags' => [ 'vip' ] ] );
			}
		} );

		$this->assertSame( 1, $manager->add_to_queue( 'user', 42, 'user_register', [ 'email' => 'direct@example.com' ] ) );
		$this->assertSame( 2, $child_result );
		$this->assertSame( [ 'completed', 'completed' ], $statuses );
		$this->assertSame( 1, $lock_attempts );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_failed_direct_operation_falls_back_to_pending_queue(): void {
		define( 'SYNCLY_DIRECT_SYNC', true );
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'current_time' )->justReturn( '2026-11-07 12:00:00' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'lease-test' );
		Functions\when( 'add_option' )->justReturn( true );
		Functions\when( 'wp_cache_delete' )->justReturn( true );
		$wpdb = Mockery::mock( \stdClass::class );
		$wpdb->prefix = 'wp_';
		$wpdb->options = 'wp_options';
		$wpdb->insert_id = 0;
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( static function ( $sql ) { return $sql; } );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'query' )->andReturn( 1 );
		$record = null;
		$wpdb->shouldReceive( 'insert' )->once()->andReturnUsing( static function ( $table, $data ) use ( $wpdb, &$record ) {
			$record = $data;
			$wpdb->insert_id = 72;
			return true;
		} );
		$GLOBALS['wpdb'] = $wpdb;
		$settings = Mockery::mock( SettingsManager::class );
		$settings->shouldReceive( 'get_settings_array' )->andReturn( [] );
		$settings->shouldReceive( 'is_connection_verified' )->andReturn( true );
		$settings->shouldReceive( 'get_setting' )->with( 'location_id' )->andReturn( 'loc-test' );
		( new \ReflectionProperty( SettingsManager::class, 'instance' ) )->setValue( null, $settings );
		$manager = ( new \ReflectionClass( QueueManager::class ) )->newInstanceWithoutConstructor();
		$processor = Mockery::mock( 'Syncly\\Sync\\QueueProcessor' );
		$processor->shouldReceive( 'execute_sync' )->once()->andReturn( [ 'success' => false, 'error' => 'GHL unavailable' ] );
		$limiter = Mockery::mock( 'Syncly\\Sync\\RateLimiter' );
		$limiter->shouldReceive( 'reserve_request' )->once()->andReturn( true );
		foreach ( [ 'processor' => $processor, 'rate_limiter' => $limiter ] as $name => $value ) {
			( new \ReflectionProperty( QueueManager::class, $name ) )->setValue( $manager, $value );
		}

		$this->assertSame( 72, $manager->add_to_queue( 'form', 7, 'add_tags', [ 'tags' => [ 'vip' ] ], 60 ) );
		$this->assertSame( 'pending', $record['status'] );
		$this->assertSame( 60, json_decode( $record['payload'], true )['_depends_on_queue_id'] );
	}
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_post_sync_callback_failure_does_not_requeue_completed_direct_registration(): void {
		define( 'SYNCLY_DIRECT_SYNC', true );
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'current_time' )->justReturn( '2026-11-04 12:00:00' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'lease-test' );
		Functions\when( 'add_option' )->justReturn( true );
		Functions\when( 'wp_cache_delete' )->justReturn( true );
		Functions\when( 'do_action' )->alias( static function () { throw new \RuntimeException( 'Post-sync callback failed' ); } );

		$wpdb = Mockery::mock( \stdClass::class );
		$wpdb->prefix = 'wp_';
		$wpdb->options = 'wp_options';
		$wpdb->insert_id = 0;
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( static function ( $sql ) { return $sql; } );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'query' )->andReturn( 1 );
		$record = null;
		$wpdb->shouldReceive( 'insert' )->once()->andReturnUsing( static function ( $table, $data ) use ( $wpdb, &$record ) {
			$record = $data;
			$wpdb->insert_id = 91;
			return true;
		} );
		$GLOBALS['wpdb'] = $wpdb;

		$settings = Mockery::mock( SettingsManager::class );
		$settings->shouldReceive( 'get_settings_array' )->andReturn( [] );
		$settings->shouldReceive( 'is_connection_verified' )->andReturn( true );
		$settings->shouldReceive( 'get_setting' )->with( 'location_id' )->andReturn( 'loc-test' );
		$sm = new \ReflectionProperty( SettingsManager::class, 'instance' );
		$sm->setValue( null, $settings );

		$manager = ( new \ReflectionClass( QueueManager::class ) )->newInstanceWithoutConstructor();
		$processor = Mockery::mock( 'Syncly\\Sync\\QueueProcessor' );
		$processor->shouldReceive( 'execute_sync' )->once()->with( 'user', 'user_register', 42, [ 'email' => 'direct@example.com' ] )->andReturn( [ 'contact' => [ 'id' => 'ghl-42' ] ] );
		$limiter = Mockery::mock( 'Syncly\\Sync\\RateLimiter' );
		$limiter->shouldReceive( 'reserve_request' )->once()->with( 'loc-test' )->andReturn( true );
		$logger = Mockery::mock( 'Syncly\\Sync\\QueueLogger' );
		$logger->shouldReceive( 'log_event' )->andReturnNull();
		foreach ( [ 'processor' => $processor, 'rate_limiter' => $limiter, 'logger' => $logger ] as $name => $value ) {
			( new \ReflectionProperty( QueueManager::class, $name ) )->setValue( $manager, $value );
		}

		$result = $manager->add_to_queue( 'user', 42, 'user_register', [ 'email' => 'direct@example.com' ] );
		$this->assertSame( 91, $result );
		$this->assertSame( 'completed', $record['status'] );
		$this->assertSame( 'direct@example.com', json_decode( $record['payload'], true )['email'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_direct_mode_defers_when_older_work_for_same_user_is_pending(): void {
		define( 'SYNCLY_DIRECT_SYNC', true );
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'current_time' )->justReturn( '2026-11-04 12:00:00' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$wpdb = Mockery::mock( \stdClass::class );
		$wpdb->prefix = 'wp_';
		$wpdb->insert_id = 0;
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( static function ( $sql ) { return $sql; } );
		$wpdb->shouldReceive( 'get_var' )->once()->withArgs( static function ( $sql ) { return strpos( $sql, "status = 'pending'" ) !== false && strpos( $sql, 'action = %s' ) !== false; } )->andReturn( null );
		$wpdb->shouldReceive( 'get_var' )->once()->withArgs( static function ( $sql ) { return strpos( $sql, "status IN ('pending','processing')" ) !== false; } )->andReturn( 80 );
		$wpdb->shouldReceive( 'get_var' )->once()->withArgs( static function ( $sql ) { return strpos( $sql, 'SELECT COUNT(*)' ) !== false; } )->andReturn( 1 );
		$wpdb->shouldReceive( 'insert' )->once()->andReturnUsing( static function ( $table, $data ) use ( $wpdb ) {
			$wpdb->insert_id = 92;
			return $data['status'] === 'pending';
		} );
		$GLOBALS['wpdb'] = $wpdb;
		$settings = Mockery::mock( SettingsManager::class );
		$settings->shouldReceive( 'is_connection_verified' )->andReturn( false );
		( new \ReflectionProperty( SettingsManager::class, 'instance' ) )->setValue( null, $settings );
		$manager = ( new \ReflectionClass( QueueManager::class ) )->newInstanceWithoutConstructor();
		$this->assertSame( 92, $manager->add_to_queue( 'user', 42, 'add_tags', [ 'tags' => [ 'vip' ] ] ) );
	}
}
