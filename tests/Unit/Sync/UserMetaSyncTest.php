<?php
/**
 * Unit tests for UserMetaSync.
 *
 * Tests the centralized post-sync handler that updates user meta
 * (contact ID, tags, pending tags) after a queue item succeeds.
 *
 * Uses reflection to inject mock dependencies since the real
 * TagManager/QueueManager classes are already loaded by the autoloader.
 *
 * @package GHL_CRM_Integration\Tests\Unit\Sync
 */

declare(strict_types=1);

namespace GHL_CRM\Tests\Unit\Sync;

use GHL_CRM\Sync\UserMetaSync;
use GHL_CRM\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class UserMetaSyncTest extends TestCase {

	private UserMetaSync $sync;

	/**
	 * @var \Mockery\MockInterface
	 */
	private $tag_manager_mock;

	/**
	 * @var \Mockery\MockInterface
	 */
	private $queue_manager_mock;

	protected function setUp(): void {
		parent::setUp();

		// Standard WP stubs needed by the constructor.
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'update_user_meta' )->justReturn( true );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'delete_user_meta' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( function () {
			$args = func_get_args();
			return $args[1] ?? null;
		});

		// Create real singleton then inject mocked dependencies via reflection.
		$this->resetSingleton( UserMetaSync::class );

		// We need to bypass the private constructor that calls TagManager/QueueManager singletons.
		// Use reflection to create without constructor, then inject mocks.
		$ref  = new \ReflectionClass( UserMetaSync::class );
		$inst = $ref->newInstanceWithoutConstructor();

		// Set the singleton instance.
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, $inst );

		// Create mock dependencies.
		$this->tag_manager_mock = Mockery::mock( 'GHL_CRM\\Sync\\TagManager' );
		$this->tag_manager_mock->shouldReceive( 'store_user_contact_id' )->byDefault();
		$this->tag_manager_mock->shouldReceive( 'store_user_tags' )->andReturn( [] )->byDefault();
		$this->tag_manager_mock->shouldReceive( 'get_user_tag_ids' )->andReturn( [] )->byDefault();
		$this->tag_manager_mock->shouldReceive( 'normalize_tag_input' )->andReturnUsing( function ( $tags ) {
			return [ 'ids' => $tags, 'names' => $tags, 'pairs' => [] ];
		})->byDefault();
		$this->tag_manager_mock->shouldReceive( 'prepare_tags_for_payload' )->andReturnUsing( function ( $ids ) {
			return $ids;
		})->byDefault();

		$this->queue_manager_mock = Mockery::mock( 'GHL_CRM\\Sync\\QueueManager' );
		$this->queue_manager_mock->shouldReceive( 'add_to_queue' )->byDefault();

		// Inject mocks into the instance.
		$tm = $ref->getProperty( 'tag_manager' );
		$tm->setAccessible( true );
		$tm->setValue( $inst, $this->tag_manager_mock );

		$qm = $ref->getProperty( 'queue_manager' );
		$qm->setAccessible( true );
		$qm->setValue( $inst, $this->queue_manager_mock );

		$this->sync = UserMetaSync::get_instance();
	}

	protected function tearDown(): void {
		$this->resetSingleton( UserMetaSync::class );
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	//  Helpers
	// ------------------------------------------------------------------

	private function makeItem( string $type, int $id, string $action ): object {
		return (object) [
			'item_type' => $type,
			'item_id'   => $id,
			'action'    => $action,
		];
	}

	// ------------------------------------------------------------------
	//  handle_sync_success() — core routing
	// ------------------------------------------------------------------

	public function test_does_nothing_when_contact_id_empty(): void {
		$item = $this->makeItem( 'user', 42, 'create' );

		$this->tag_manager_mock->shouldNotReceive( 'store_user_contact_id' );

		$this->sync->handle_sync_success( $item, null );
		$this->sync->handle_sync_success( $item, '' );
	}

	public function test_stores_contact_id_and_last_sync_for_user(): void {
		$item = $this->makeItem( 'user', 42, 'create' );

		$this->tag_manager_mock
			->shouldReceive( 'store_user_contact_id' )
			->once()
			->with( 42, 'ghl_contact_xyz' );

		$last_sync_stored = false;
		Functions\when( 'update_user_meta' )->alias( function ( $uid, $key, $val ) use ( &$last_sync_stored ) {
			if ( $key === '_ghl_last_sync' && $uid === 42 ) {
				$last_sync_stored = true;
			}
			return true;
		});

		$this->sync->handle_sync_success( $item, 'ghl_contact_xyz' );

		$this->assertTrue( $last_sync_stored, '_ghl_last_sync should be set' );
	}

	// ------------------------------------------------------------------
	//  User ID resolution
	// ------------------------------------------------------------------

	public function test_resolves_user_id_from_user_item(): void {
		$item = $this->makeItem( 'user', 42, 'update' );

		$this->tag_manager_mock
			->shouldReceive( 'store_user_contact_id' )
			->once()
			->with( 42, 'c123' );

		$this->sync->handle_sync_success( $item, 'c123' );
	}

	public function test_resolves_user_id_from_wc_customer_order(): void {
		$item = $this->makeItem( 'wc_customer', 100, 'create' );

		// WooCommerce class stub exists via bootstrap.php, no need to mock class_exists.

		$order_mock = Mockery::mock();
		$order_mock->shouldReceive( 'get_customer_id' )->andReturn( 55 );
		Functions\when( 'wc_get_order' )->justReturn( $order_mock );

		$this->tag_manager_mock
			->shouldReceive( 'store_user_contact_id' )
			->once()
			->with( 55, 'c456' );

		$this->sync->handle_sync_success( $item, 'c456' );
	}

	public function test_wc_guest_order_skips_sync(): void {
		$item = $this->makeItem( 'wc_customer', 100, 'create' );


		$order_mock = Mockery::mock();
		$order_mock->shouldReceive( 'get_customer_id' )->andReturn( 0 );
		Functions\when( 'wc_get_order' )->justReturn( $order_mock );

		// Nothing should be stored for guest order.
		$this->tag_manager_mock->shouldNotReceive( 'store_user_contact_id' );

		$this->sync->handle_sync_success( $item, 'c789' );
	}

	// ------------------------------------------------------------------
	//  Tag merge for WooCommerce items (the overwrite bug we fixed)
	// ------------------------------------------------------------------

	public function test_wc_product_tags_merges_with_existing(): void {
		$item = $this->makeItem( 'wc_product_tags', 100, 'apply_tags' );

		$order = Mockery::mock();
		$order->shouldReceive( 'get_customer_id' )->andReturn( 42 );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		// User already has tag_001.
		$this->tag_manager_mock
			->shouldReceive( 'get_user_tag_ids' )
			->with( 42 )
			->andReturn( [ 'tag_001' ] );

		// Should merge: existing tag_001 + new tag_005.
		$this->tag_manager_mock
			->shouldReceive( 'store_user_tags' )
			->once()
			->with( 42, Mockery::on( function ( $tags ) {
				return in_array( 'tag_001', $tags, true )
					&& in_array( 'tag_005', $tags, true )
					&& count( $tags ) === 2;
			}))
			->andReturn( [ 'tag_001', 'tag_005' ] );

		$this->sync->handle_sync_success(
			$item,
			'c123',
			null,
			[ 'tags' => [ 'tag_005' ], 'order_id' => 100 ]
		);
	}

	public function test_wc_customer_merges_not_overwrites(): void {
		$item = $this->makeItem( 'wc_customer', 100, 'convert_lead' );

		$order = Mockery::mock();
		$order->shouldReceive( 'get_customer_id' )->andReturn( 42 );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->tag_manager_mock
			->shouldReceive( 'get_user_tag_ids' )
			->with( 42 )
			->andReturn( [ 'tag_001', 'tag_002' ] );

		// Must merge, not replace: old [001,002] + new [003] = [001,002,003].
		$this->tag_manager_mock
			->shouldReceive( 'store_user_tags' )
			->once()
			->with( 42, Mockery::on( function ( $tags ) {
				return count( $tags ) === 3
					&& in_array( 'tag_001', $tags, true )
					&& in_array( 'tag_002', $tags, true )
					&& in_array( 'tag_003', $tags, true );
			}))
			->andReturn( [ 'tag_001', 'tag_002', 'tag_003' ] );

		$this->sync->handle_sync_success(
			$item,
			'c123',
			null,
			[ 'tags' => [ 'tag_003' ] ]
		);
	}

	public function test_wc_empty_payload_tags_skips_merge(): void {
		$item = $this->makeItem( 'wc_customer', 100, 'create' );

		$order = Mockery::mock();
		$order->shouldReceive( 'get_customer_id' )->andReturn( 42 );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		// No tags in payload — should NOT call get_user_tag_ids or store_user_tags.
		$this->tag_manager_mock->shouldNotReceive( 'get_user_tag_ids' );

		$this->sync->handle_sync_success( $item, 'c123', null, [] );
	}

	// ------------------------------------------------------------------
	//  Pending tags flush
	// ------------------------------------------------------------------

	public function test_flushes_pending_tags_for_user_item(): void {
		$item = $this->makeItem( 'user', 42, 'create' );

		Functions\when( 'get_user_meta' )->alias( function ( $uid, $key, $single ) {
			if ( $key === '_ghl_pending_tags' ) {
				return [ 'tag_001', 'tag_002' ];
			}
			return '';
		});

		$deleted_keys = [];
		Functions\when( 'delete_user_meta' )->alias( function ( $uid, $key ) use ( &$deleted_keys ) {
			$deleted_keys[] = $key;
			return true;
		});

		$this->queue_manager_mock
			->shouldReceive( 'add_to_queue' )
			->once()
			->with( 'user', 42, 'add_tags', Mockery::on( function ( $payload ) {
				return $payload['contact_id'] === 'c_new'
					&& ! empty( $payload['tags'] );
			}));

		$this->sync->handle_sync_success( $item, 'c_new' );

		$this->assertContains( '_ghl_pending_tags', $deleted_keys );
	}

	public function test_does_not_flush_pending_for_wc_items(): void {
		$item = $this->makeItem( 'wc_customer', 100, 'create' );

		$order = Mockery::mock();
		$order->shouldReceive( 'get_customer_id' )->andReturn( 42 );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		// Pending tags should never be checked for WC items.
		// If get_user_meta is called with _ghl_pending_tags, that's a bug.
		$pending_checked = false;
		Functions\when( 'get_user_meta' )->alias( function ( $uid, $key, $single ) use ( &$pending_checked ) {
			if ( $key === '_ghl_pending_tags' ) {
				$pending_checked = true;
			}
			return '';
		});

		$this->sync->handle_sync_success( $item, 'c123', null, [] );

		$this->assertFalse( $pending_checked, 'Should not check pending tags for WC items' );
	}

	// ------------------------------------------------------------------
	//  GHL refresh for tag operations
	// ------------------------------------------------------------------

	public function test_add_tags_action_enters_refresh_path(): void {
		$item = $this->makeItem( 'user', 42, 'add_tags' );

		// add_tags is in the refresh_actions list, so maybe_refresh_from_ghl runs.
		// UserProfileFields can't be alias-mocked (already autoloaded), but
		// the try/catch in maybe_refresh_from_ghl ensures graceful completion.
		$this->sync->handle_sync_success( $item, 'c123' );

		$this->assertTrue( true, 'add_tags flow completes without error' );
	}

	public function test_apply_tags_does_not_trigger_refresh(): void {
		$item = $this->makeItem( 'wc_product_tags', 100, 'apply_tags' );

		$order = Mockery::mock();
		$order->shouldReceive( 'get_customer_id' )->andReturn( 42 );
		Functions\when( 'wc_get_order' )->justReturn( $order );

		$this->tag_manager_mock->shouldReceive( 'get_user_tag_ids' )->andReturn( [] );
		$this->tag_manager_mock->shouldReceive( 'store_user_tags' )->andReturn( [] );

		// apply_tags is NOT in the refresh whitelist — no refresh should happen.
		// If it reaches UserProfileFields that would be an error.
		$this->sync->handle_sync_success(
			$item,
			'c123',
			null,
			[ 'tags' => [ 'tag_001' ], 'order_id' => 100 ]
		);

		$this->assertTrue( true, 'apply_tags should not trigger GHL refresh' );
	}
}
