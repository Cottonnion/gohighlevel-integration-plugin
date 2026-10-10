<?php
/** OAuth state and concurrency regressions. @package Syncly */
declare(strict_types=1);
namespace Syncly\Tests\Unit\API;

use Brain\Monkey\Functions;
use Mockery;
use Syncly\API\Client\Client;
use Syncly\API\Exceptions\AuthenticationException;
use Syncly\API\OAuth\OAuthFailure;
use Syncly\API\OAuth\OAuthHandler;
use Syncly\API\OAuth\OptionMutex;
use Syncly\API\OAuth\RecurringSchedule;
use Syncly\Core\Settings\SettingsRepository;
use Syncly\Core\SettingsManager;
use Syncly\Sync\QueueManager;
use Syncly\Tests\TestCase;
use Syncly\Utilities\FileLogger;

class OAuthRefreshTest extends TestCase {
	private array $options = array();
	private SettingsRepository $repository;
	private Client $client;

	protected function setUp(): void {
		parent::setUp();
		$this->options = array(
			'syncly_settings' => array(
				'location_id'         => 'loc',
				'oauth_access_token'  => 'old-access',
				'oauth_refresh_token' => 'old-refresh',
				'oauth_expires_at'    => time() - 10,
			),
		);
		Functions\when( 'wp_generate_uuid4' )->alias(
			static function () {
				return uniqid( 'lease', true );
			}
		);
		Functions\when( 'maybe_serialize' )->alias( 'serialize' );
		Functions\when( 'maybe_unserialize' )->alias(
			static function ( $value ) {
				return is_string( $value ) && strpos( $value, 'a:' ) === 0 ? unserialize( $value ) : $value;
			}
		);
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_salt' )->justReturn( 'test-only-salt' );
		Functions\when( 'current_time' )->justReturn( '2026-10-10 12:00:00' );
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $values, $defaults ) {
				return array_merge( $defaults, $values );
			}
		);
		Functions\when( 'get_option' )->alias(
			function ( $key, $fallback = false ) {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'add_option' )->alias(
			function ( $key, $value ) {
				if ( isset( $this->options[ $key ] ) ) {
					return false; }
				$this->options[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) {
				$this->options[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'wp_cache_delete' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 0 );
		Functions\when( 'as_unschedule_all_actions' )->justReturn( null );
		$wpdb          = Mockery::mock( \stdClass::class );
		$wpdb->options = 'wp_options';
		$wpdb->prefix  = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			static function ( $sql, ...$args ) {
				return array( $sql, $args );
			}
		);
		$wpdb->shouldReceive( 'get_var' )->andReturnUsing(
			function ( $query ) {
				$key = $query[1][0];
				if ( ! isset( $this->options[ $key ] ) ) {
					return null; }
				return is_array( $this->options[ $key ] ) ? serialize( $this->options[ $key ] ) : $this->options[ $key ];
			}
		);
		$wpdb->shouldReceive( 'query' )->andReturnUsing(
			function ( $query ) {
				list($sql, $args) = $query;
				if ( strpos( $sql, 'DELETE' ) === 0 ) {
					list($key, $expected) = $args;
					if ( ( $this->options[ $key ] ?? null ) !== $expected ) {
						return 0; }
					unset( $this->options[ $key ] );
					return 1;
				}
				list($value, $key, $expected) = $args;
				$raw                          = isset( $this->options[ $key ] ) && is_array( $this->options[ $key ] ) ? serialize( $this->options[ $key ] ) : ( $this->options[ $key ] ?? null );
				if ( $raw !== $expected ) {
					return 0; }
				$this->options[ $key ] = strpos( $value, 'a:' ) === 0 ? unserialize( $value ) : $value;
				return 1;
			}
		);
		$GLOBALS['wpdb']  = $wpdb;
		$this->repository = SettingsRepository::get_instance();
		$this->repository->get_oauth_state();
		$manager = Mockery::mock( SettingsManager::class );
		$manager->shouldReceive( 'get_settings_array' )->andReturnUsing(
			function () {
				return $this->repository->get_settings_array();
			}
		);
		$manager->shouldReceive( 'is_connection_verified' )->andReturn( true );
		$property = new \ReflectionProperty( SettingsManager::class, 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $manager );
		$this->client = ( new \ReflectionClass( Client::class ) )->newInstanceWithoutConstructor();
		$this->client->reload_settings();
	}

	protected function tearDown(): void {
		$this->resetSingleton( SettingsManager::class );
		\ActionScheduler::$initialized = true;
		\ActionScheduler::$test_store  = null;
		parent::tearDown();
	}

	private function response( int $status, array $body, string $retry = '' ): void {
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( $status );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( json_encode( $body ) );
		Functions\when( 'wp_remote_retrieve_header' )->justReturn( $retry );
		Functions\expect( 'wp_remote_request' )->once()->andReturn( array() );
	}

	public function test_wrapped_invalid_grant_stops_future_requests(): void {
		$this->response(
			400,
			array(
				'code' => 'ghl_error',
				'data' => array(
					'response' => array(
						'error'             => 'invalid_grant',
						'error_description' => 'secret old-refresh',
					),
				),
			)
		);
		for ( $i = 0; $i < 2; ++$i ) {
			try {
				$this->client->refresh_access_token();
				$this->fail( 'Must reject' ); } catch ( AuthenticationException $e ) {
				$this->assertStringNotContainsString( 'old-refresh', $e->getMessage() ); }
		}
		$state = $this->repository->get_oauth_state();
		$this->assertSame( 'reconnect_required', $state['oauth_health_status'] );
		$this->assertSame( '', $state['oauth_refresh_token'] );
		$this->assertFalse( $this->options['syncly_connection_verified'] );
		$this->assertSame( 1, $state['oauth_failure_count'] );
	}

	public function test_inactive_location_is_permanent(): void {
		$this->response( 400, array( 'data' => array( 'response' => array( 'error_description' => 'Location is not active' ) ) ) );
		try {
			$this->client->refresh_access_token(); } catch ( AuthenticationException $e ) {
			}
			$this->assertSame( 'reconnect_required', $this->repository->get_oauth_state()['oauth_health_status'] );
	}

	/** @dataProvider temporaryResponses */
	public function test_temporary_response_preserves_credentials_and_backs_off( int $status, string $category ): void {
		$this->response( $status, array(), '1200' );
		try {
			$this->client->refresh_access_token(); } catch ( AuthenticationException $e ) {
			}
			$state = $this->repository->get_oauth_state();
			$this->assertSame( 'old-refresh', $state['oauth_refresh_token'] );
			$this->assertSame( 'old-access', $state['oauth_access_token'] );
			$this->assertSame( $category, $state['oauth_failure_category'] );
			$this->assertGreaterThanOrEqual( time() + 1199, $state['oauth_next_attempt'] );
			$this->assertFalse( OAuthFailure::allows_attempt( $state ) );
	}

	public static function temporaryResponses(): array {
		return array( array( 429, 'rate_limited' ), array( 500, 'transient' ), array( 503, 'transient' ) ); }

	/** @dataProvider networkFailures */
	public function test_network_failure_preserves_tokens( string $error ): void {
		Functions\when( 'is_wp_error' )->justReturn( true );
		Functions\expect( 'wp_remote_request' )->once()->andReturn( new \WP_Error( 'http_request_failed', $error ) );
		try {
			$this->client->refresh_access_token(); } catch ( AuthenticationException $e ) {
			}
			$state = $this->repository->get_oauth_state();
			$this->assertSame( 'old-refresh', $state['oauth_refresh_token'] );
			$this->assertSame( 'transient', $state['oauth_failure_category'] );
			$this->assertGreaterThanOrEqual( time() + 299, $state['oauth_next_attempt'] );
	}

	public static function networkFailures(): array {
		return array( array( 'cURL error 28 timeout' ), array( 'DNS resolution failed' ) ); }

	public function test_stale_worker_uses_winner_without_request(): void {
		$state                        = $this->repository->get_oauth_state();
		$state['oauth_access_token']  = 'winner-access';
		$state['oauth_refresh_token'] = 'winner-refresh';
		$state['oauth_expires_at']    = time() + 86400;
		$this->assertTrue( $this->repository->save_oauth_state( $state, 0 ) );
		Functions\expect( 'wp_remote_request' )->never();
		$this->assertSame( 'winner-refresh', $this->client->refresh_access_token()['refresh_token'] );
	}

	public function test_stale_writer_cannot_replace_winner_bundle(): void {
		$stale                         = $this->repository->get_oauth_state();
		$winner                        = $stale;
		$winner['oauth_refresh_token'] = 'winner-refresh';
		$winner['oauth_expires_at']    = time() + 86400;
		$this->assertTrue( $this->repository->save_oauth_state( $winner, 0 ) );
		$this->assertFalse( $this->repository->save_oauth_state( $stale, 0 ) );
		$this->assertTrue( $this->repository->save_site_settings( $stale ) );
		$actual = $this->repository->get_oauth_state();
		$this->assertSame( 'winner-refresh', $actual['oauth_refresh_token'] );
		$this->assertSame( $winner['oauth_expires_at'], $actual['oauth_expires_at'] );
		$this->assertStringNotContainsString( 'winner-refresh', serialize( $this->options['syncly_oauth_state'] ) );
		$this->assertArrayNotHasKey( 'oauth_refresh_token', $this->options['syncly_settings'] );
	}

	public function test_success_commits_tokens_expiry_and_clears_failure_state(): void {
		$this->response(
			200,
			array(
				'access_token'  => 'new-access',
				'refresh_token' => 'new-refresh',
				'expires_in'    => 86400,
			)
		);
		$this->client->refresh_access_token();
		$state = $this->repository->get_oauth_state();
		$this->assertSame( 'new-refresh', $state['oauth_refresh_token'] );
		$this->assertSame( 'healthy', $state['oauth_health_status'] );
		$this->assertSame( 0, $state['oauth_failure_count'] );
		$this->assertSame( 0, $state['oauth_next_attempt'] );
		$this->assertSame( 1, $state['oauth_revision'] );
	}

	public function test_contention_and_proxy_duplicate_do_not_count_as_failure(): void {
		$lock = new OptionMutex( 'syncly_token_refresh_lock' );
		$this->assertTrue( $lock->acquire() );
		Functions\expect( 'wp_remote_request' )->never();
		try {
			$this->client->refresh_access_token(); } catch ( AuthenticationException $e ) {
			}
			$this->assertArrayNotHasKey( 'oauth_failure_count', $this->repository->get_oauth_state() );
			$lock->release();
	}

	public function test_proxy_duplicate_does_not_open_circuit(): void {
		$this->response( 409, array( 'code' => 'refresh_in_progress' ) );
		try {
			$this->client->refresh_access_token(); } catch ( AuthenticationException $e ) {
			}
			$this->assertArrayNotHasKey( 'oauth_failure_count', $this->repository->get_oauth_state() );
	}

	public function test_silent_reconnect_uses_client_path_and_does_not_fallback(): void {
		$client = Mockery::mock( Client::class );
		$client->shouldReceive( 'refresh_access_token' )->once()->andThrow( new AuthenticationException( 'Refresh token rejected' ) );
		$client->shouldNotReceive( 'reconnect_api' );
		$handler  = ( new \ReflectionClass( OAuthHandler::class ) )->newInstanceWithoutConstructor();
		$property = new \ReflectionProperty( OAuthHandler::class, 'client' );
		$property->setAccessible( true );
		$property->setValue( $handler, $client );
		$this->assertInstanceOf( \WP_Error::class, $handler->silent_reconnect() );
	}

	/** @dataProvider blockedStates */
	public function test_queue_gate_makes_no_requests_or_queue_changes( array $health ): void {
		$state = array_merge( $this->repository->get_oauth_state(), $health );
		$this->repository->save_oauth_state( $state, 0 );
		$before = $this->options;
		Functions\expect( 'wp_remote_request' )->never();
		$queue  = ( new \ReflectionClass( QueueManager::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( QueueManager::class, 'process_site_queue' );
		$method->setAccessible( true );
		$method->invoke( $queue );
		$this->assertSame( $before, $this->options );
	}

	public static function blockedStates(): array {
		return array(
			array( array( 'oauth_health_status' => 'reconnect_required' ) ),
			array(
				array(
					'oauth_health_status' => 'circuit_open',
					'oauth_next_attempt'  => time() + 3600,
				),
			),
		);
	}

	public function test_expired_lease_owner_cannot_release_replacement(): void {
		$first = new OptionMutex( 'test-lease' );
		$this->assertTrue( $first->acquire( -1 ) );
		$second = new OptionMutex( 'test-lease' );
		$this->assertTrue( $second->acquire() );
		$first->release();
		$this->assertArrayHasKey( 'test-lease', $this->options );
		$second->release();
		$this->assertArrayNotHasKey( 'test-lease', $this->options );
	}

	/** @dataProvider recurringHooks */
	public function test_schedule_migration_preserves_history_and_serializes_creation( string $hook ): void {
		$actions = array();
		foreach ( array( 1, 2 ) as $id ) {
			$action   = Mockery::mock();
			$schedule = Mockery::mock();
			$schedule->shouldReceive( 'is_recurring' )->andReturn( true );
			$action->shouldReceive( 'get_schedule' )->andReturn( $schedule );
			$actions[ $id ] = $action;
		}
		$one_shot          = Mockery::mock();
		$one_shot_schedule = Mockery::mock();
		$one_shot_schedule->shouldReceive( 'is_recurring' )->andReturn( false );
		$one_shot->shouldReceive( 'get_schedule' )->andReturn( $one_shot_schedule );
		$actions[50] = $one_shot;
		$store       = Mockery::mock();
		$store->shouldReceive( 'cancel_action' )->with( 1 )->once();
		$store->shouldReceive( 'cancel_action' )->with( 2 )->once();
		$store->shouldNotReceive( 'cancel_action' )->with( 99 );
		$store->shouldNotReceive( 'cancel_action' )->with( 50 );
		\ActionScheduler::$test_store = $store;
		Functions\expect( 'as_get_scheduled_actions' )->once()->with(
			Mockery::on(
				static function ( $query ) {
					return 'pending' === $query['status'] && 'syncly' === $query['group'];
				}
			)
		)->andReturn( $actions );
		$pending = false;
		Functions\when( 'as_has_scheduled_action' )->alias(
			function ( $hook, $args, $group ) use ( &$pending ) {
				$this->assertSame( array(), $args );
				$this->assertSame( 'syncly', $group );
				return $pending;
			}
		);
		Functions\expect( 'as_schedule_recurring_action' )->once()->andReturnUsing(
			function ( $time, $interval, $hook, $args, $group, $unique ) use ( &$pending ) {
				$this->assertTrue( $unique );
				// Reentrant worker models a request interleaving before insertion completes.
				RecurringSchedule::ensure( $hook, $interval, 'syncly_5min' );
				$pending = true;
				return 3;
			}
		);
		RecurringSchedule::ensure( $hook, 300, 'syncly_5min' );
		RecurringSchedule::ensure( $hook, 300, 'syncly_5min' );
		$this->assertTrue( $pending );
	}

	public static function recurringHooks(): array {
		return array( array( 'syncly_process_queue' ), array( 'syncly_refresh_oauth_token' ) );
	}

	public function test_combined_legacy_auth_error_is_recoverable(): void {
		$error = new \RuntimeException( 'Failed to refresh access token (invalid_grant) and reconnect failed (Refresh token rejected)' );
		$this->assertTrue( OAuthFailure::is_authentication_error( $error ) );
		$this->assertFalse( OAuthFailure::is_authentication_error( new \RuntimeException( 'Contact field invalid' ) ) );
	}

	public function test_backoff_increases_and_caps(): void {
		$state   = array();
		$failure = OAuthFailure::normalize( 503, array() );
		foreach ( array( 300, 900, 3600, 21600, 21600 ) as $delay ) {
			$state = OAuthFailure::failed_state( $state, $failure );
			$this->assertEqualsWithDelta( time() + $delay, $state['oauth_next_attempt'], 1 );
		}
	}

	public function test_manual_exchange_uses_refresh_lease(): void {
		$lock = new OptionMutex( 'syncly_token_refresh_lock' );
		$this->assertTrue( $lock->acquire() );
		Functions\expect( 'wp_remote_request' )->never();
		try {
			$this->client->exchange_code_for_token( 'test-only-code', 'https://example.test/callback' );
			$this->fail( 'Must respect refresh lease' );
		} catch ( AuthenticationException $e ) {
			$this->assertSame( 0, $this->repository->get_oauth_state()['oauth_revision'] );
		}
		$lock->release();
	}

	public function test_manual_authorization_clears_permanent_circuit(): void {
		$state = OAuthFailure::failed_state( $this->repository->get_oauth_state(), OAuthFailure::normalize( 400, array( 'error' => 'invalid_grant' ) ) );
		$this->repository->save_oauth_state( $state, 0 );
		$logger = Mockery::mock( FileLogger::class );
		$logger->shouldReceive( 'log' )->andReturn( true );
		$prop = new \ReflectionProperty( FileLogger::class, 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, $logger );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'as_has_scheduled_action' )->justReturn( true );
		Functions\when( 'as_get_scheduled_actions' )->justReturn( array() );
		$this->response(
			200,
			array(
				'access_token'  => 'manual-access',
				'refresh_token' => 'manual-refresh',
				'expires_in'    => 86400,
			)
		);
		try {
			$this->client->exchange_code_for_token( 'test-only-code', 'https://example.test/callback' );
			$actual = $this->repository->get_oauth_state();
			$this->assertSame( 'healthy', $actual['oauth_health_status'] );
			$this->assertSame( 0, $actual['oauth_failure_count'] );
			$this->assertSame( 2, $actual['oauth_revision'] );
			$this->assertSame( 'manual-refresh', $actual['oauth_refresh_token'] );
		} finally {
			$this->resetSingleton( FileLogger::class );
		}
	}

	public function test_general_save_migrates_before_removing_legacy_credentials(): void {
		unset( $this->options['syncly_oauth_state'] );
		$this->repository->save_site_settings( array( 'location_id' => 'loc' ) );
		$state = $this->repository->get_oauth_state();
		$this->assertSame( 'old-refresh', $state['oauth_refresh_token'] );
		$this->assertFalse( $this->repository->update_setting( 'oauth_refresh_token', 'stale-refresh' ) );
		$this->assertSame( 'old-refresh', $this->repository->get_oauth_state()['oauth_refresh_token'] );
	}

	public function test_oauth_cron_fallback(): void {
		\ActionScheduler::$initialized = false;
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_event' )->once()->with( Mockery::type( 'int' ), 'syncly_oauth_20min', 'syncly_refresh_oauth_token', array() );
		RecurringSchedule::ensure( 'syncly_refresh_oauth_token', 1200, 'syncly_oauth_20min' );
	}

	public function test_released_lease_with_stale_option_cache_can_be_acquired(): void {
		$attempts = 0;
		Functions\when( 'add_option' )->alias(
			function ( $key, $value ) use ( &$attempts ) {
				++$attempts;
				if ( 1 === $attempts ) {
					return false; // WordPress saw a cached lease already released in the database.
				}
				$this->options[ $key ] = $value;
				return true;
			}
		);
		$cache_deletions = array();
		Functions\when( 'wp_cache_delete' )->alias(
			static function ( $key, $group ) use ( &$cache_deletions ) {
				$cache_deletions[] = array( $key, $group );
				return true;
			}
		);
		$lease = new OptionMutex( 'stale-cached-lease' );
		$this->assertTrue( $lease->acquire() );
		$this->assertSame( 2, $attempts );
		$this->assertSame( array( array( 'stale-cached-lease', 'options' ) ), $cache_deletions );
	}

	public function test_stale_client_cannot_save_over_winner(): void {
		$winner                        = $this->repository->get_oauth_state();
		$winner['oauth_access_token']  = 'winner-access';
		$winner['oauth_refresh_token'] = 'winner-refresh';
		$winner['oauth_expires_at']    = time() + 86400;
		$this->repository->save_oauth_state( $winner, 0 );
		$save = new \ReflectionMethod( Client::class, 'save_oauth_tokens' );
		$save->setAccessible( true );
		try {
			$save->invoke( $this->client, time() + 60 );
			$this->fail( 'Stale client must not persist credentials.' );
		} catch ( AuthenticationException $error ) {
			$actual = $this->repository->get_oauth_state();
			$this->assertSame( 'winner-refresh', $actual['oauth_refresh_token'] );
			$this->assertSame( $winner['oauth_expires_at'], $actual['oauth_expires_at'] );
			$this->assertSame( 1, $actual['oauth_revision'] );
		}
	}

	public function test_rate_limit_and_server_errors_override_permanent_body_text(): void {
		$body = array( 'data' => array( 'response' => array( 'error' => 'invalid_grant' ) ) );
		$this->assertSame( 'rate_limited', OAuthFailure::normalize( 429, $body )['category'] );
		$this->assertSame( 'transient', OAuthFailure::normalize( 503, $body )['category'] );
		$after = gmdate( 'D, d M Y H:i:s', time() + 900 ) . ' GMT';
		$this->assertEqualsWithDelta( 900, OAuthFailure::normalize( 429, array(), $after )['retry_after'], 1 );
	}

	public function test_success_after_backoff_clears_stale_failure_metadata(): void {
		$failed                       = OAuthFailure::failed_state( $this->repository->get_oauth_state(), OAuthFailure::normalize( 503, array() ) );
		$failed['oauth_next_attempt'] = time() - 1;
		$this->repository->save_oauth_state( $failed, 0 );
		$this->response(
			200,
			array(
				'access_token'  => 'new-access',
				'refresh_token' => 'new-refresh',
				'expires_in'    => 3600,
			)
		);
		$this->client->refresh_access_token();
		$actual = $this->repository->get_oauth_state();
		$this->assertSame( 'healthy', $actual['oauth_health_status'] );
		$this->assertSame( '', $actual['oauth_error_code'] );
		$this->assertSame( '', $actual['oauth_failure_category'] );
		$this->assertSame( '', $actual['oauth_health_message'] );
		$this->assertSame( 0, $actual['oauth_next_attempt'] );
		$this->assertSame( 0, $actual['oauth_failure_count'] );
	}
}
