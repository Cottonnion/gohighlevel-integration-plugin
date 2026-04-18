<?php
/**
 * Unit tests for TagManager.
 *
 * Tests tag normalization, ID/name conversion, user meta storage,
 * and location-scoped meta key generation.
 *
 * @package GHL_CRM_Integration\Tests\Unit\Sync
 */

declare(strict_types=1);

namespace GHL_CRM\Tests\Unit\Sync;

use GHL_CRM\Sync\TagManager;
use GHL_CRM\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class TagManagerTest extends TestCase {

	/**
	 * The TagManager instance under test.
	 *
	 * @var TagManager
	 */
	private TagManager $tag_manager;

	protected function setUp(): void {
		parent::setUp();

		// Mock transient/blog functions used by get_tags() → ensure_tag_cache().
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
		Functions\when( 'get_transient' )->justReturn( $this->getSampleTags() );
		Functions\when( 'set_transient' )->justReturn( true );

		// Build SettingsManager mock and inject via reflection (can't use overload:
		// because the class may already be autoloaded by earlier test suites).
		$settings_mock = Mockery::mock( \GHL_CRM\Core\SettingsManager::class );
		$settings_mock->shouldReceive( 'get_setting' )
			->with( 'location_id' )
			->andReturn( 'loc_abc123' )
			->byDefault();
		$settings_mock->shouldReceive( 'get_setting' )
			->with( 'cache_duration', Mockery::any() )
			->andReturn( 3600 )
			->byDefault();

		$sm_ref  = new \ReflectionClass( \GHL_CRM\Core\SettingsManager::class );
		$sm_prop = $sm_ref->getProperty( 'instance' );
		$sm_prop->setAccessible( true );
		$sm_prop->setValue( null, $settings_mock );

		// Build TagManager without constructor, then inject the SettingsManager mock.
		$this->resetSingleton( TagManager::class );
		$ref  = new \ReflectionClass( TagManager::class );
		$inst = $ref->newInstanceWithoutConstructor();

		// Set TagManager singleton.
		$inst_prop = $ref->getProperty( 'instance' );
		$inst_prop->setAccessible( true );
		$inst_prop->setValue( null, $inst );

		// Inject SettingsManager dependency.
		$sm_dep = $ref->getProperty( 'settings_manager' );
		$sm_dep->setAccessible( true );
		$sm_dep->setValue( $inst, $settings_mock );

		$this->tag_manager = TagManager::get_instance();
	}

	protected function tearDown(): void {
		$this->resetSingleton( TagManager::class );
		$this->resetSingleton( \GHL_CRM\Core\SettingsManager::class );
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	//  Sample data
	// ------------------------------------------------------------------

	private function getSampleTags(): array {
		return [
			[ 'id' => 'tag_001', 'name' => 'VIP Customer' ],
			[ 'id' => 'tag_002', 'name' => 'Newsletter' ],
			[ 'id' => 'tag_003', 'name' => 'LearnDash Complete' ],
			[ 'id' => 'tag_004', 'name' => "Bio's Special" ],
			[ 'id' => 'tag_005', 'name' => 'WooCommerce Buyer' ],
		];
	}

	// ------------------------------------------------------------------
	//  Meta key generation (location-scoped)
	// ------------------------------------------------------------------

	public function test_contact_id_meta_key_uses_location(): void {
		$key = $this->tag_manager->get_user_contact_id_meta_key();
		$this->assertSame( '_ghl_contact_id_loc_abc123', $key );
	}

	public function test_contact_id_meta_key_uses_explicit_location(): void {
		$key = $this->tag_manager->get_user_contact_id_meta_key( 'loc_xyz' );
		$this->assertSame( '_ghl_contact_id_loc_xyz', $key );
	}

	public function test_tags_meta_key_uses_location(): void {
		$key = $this->tag_manager->get_user_tags_meta_key();
		$this->assertSame( '_ghl_contact_tags_loc_abc123', $key );
	}

	public function test_tags_meta_key_uses_explicit_location(): void {
		$key = $this->tag_manager->get_user_tags_meta_key( 'loc_xyz' );
		$this->assertSame( '_ghl_contact_tags_loc_xyz', $key );
	}

	// ------------------------------------------------------------------
	//  normalize_tag_input()
	// ------------------------------------------------------------------

	public function test_normalize_empty_array_returns_empty(): void {
		$result = $this->tag_manager->normalize_tag_input( [] );

		$this->assertSame( [], $result['ids'] );
		$this->assertSame( [], $result['names'] );
		$this->assertSame( [], $result['pairs'] );
	}

	public function test_normalize_with_known_ids(): void {
		$result = $this->tag_manager->normalize_tag_input( [ 'tag_001', 'tag_002' ] );

		$this->assertSame( [ 'tag_001', 'tag_002' ], $result['ids'] );
		$this->assertContains( 'VIP Customer', $result['names'] );
		$this->assertContains( 'Newsletter', $result['names'] );
	}

	public function test_normalize_with_names_resolves_to_ids(): void {
		$result = $this->tag_manager->normalize_tag_input( [ 'VIP Customer', 'Newsletter' ] );

		$this->assertContains( 'tag_001', $result['ids'] );
		$this->assertContains( 'tag_002', $result['ids'] );
	}

	public function test_normalize_is_case_insensitive_for_names(): void {
		$result = $this->tag_manager->normalize_tag_input( [ 'vip customer', 'NEWSLETTER' ] );

		$this->assertContains( 'tag_001', $result['ids'] );
		$this->assertContains( 'tag_002', $result['ids'] );
	}

	public function test_normalize_with_associative_arrays(): void {
		$input = [
			[ 'id' => 'tag_001', 'name' => 'VIP Customer' ],
			[ 'id' => 'tag_003', 'name' => 'LearnDash Complete' ],
		];

		$result = $this->tag_manager->normalize_tag_input( $input );

		$this->assertSame( [ 'tag_001', 'tag_003' ], $result['ids'] );
		$this->assertCount( 2, $result['pairs'] );
	}

	public function test_normalize_skips_empty_strings(): void {
		$result = $this->tag_manager->normalize_tag_input( [ '', '  ', 'tag_001' ] );

		$this->assertSame( [ 'tag_001' ], $result['ids'] );
	}

	public function test_normalize_deduplicates_ids(): void {
		$result = $this->tag_manager->normalize_tag_input( [ 'tag_001', 'tag_001', 'VIP Customer' ] );

		$this->assertSame( [ 'tag_001' ], $result['ids'] );
	}

	public function test_normalize_with_special_characters_in_name(): void {
		$result = $this->tag_manager->normalize_tag_input( [ "Bio's Special" ] );

		$this->assertContains( 'tag_004', $result['ids'] );
		$this->assertContains( "Bio's Special", $result['names'] );
	}

	public function test_normalize_unknown_name_kept_as_is(): void {
		$result = $this->tag_manager->normalize_tag_input( [ 'NonExistentTag' ] );

		// Unknown tags should still appear — they'll be auto-created by GHL.
		$this->assertContains( 'NonExistentTag', $result['ids'] );
		$this->assertContains( 'NonExistentTag', $result['names'] );
	}

	// ------------------------------------------------------------------
	//  convert_ids_to_names()
	// ------------------------------------------------------------------

	public function test_convert_ids_to_names(): void {
		$names = $this->tag_manager->convert_ids_to_names( [ 'tag_001', 'tag_003' ] );

		$this->assertSame( [ 'VIP Customer', 'LearnDash Complete' ], $names );
	}

	public function test_convert_ids_to_names_unknown_id_returns_id(): void {
		$names = $this->tag_manager->convert_ids_to_names( [ 'tag_unknown' ] );

		$this->assertSame( [ 'tag_unknown' ], $names );
	}

	public function test_convert_ids_to_names_empty_returns_empty(): void {
		$this->assertSame( [], $this->tag_manager->convert_ids_to_names( [] ) );
	}

	// ------------------------------------------------------------------
	//  convert_names_to_ids()
	// ------------------------------------------------------------------

	public function test_convert_names_to_ids(): void {
		$ids = $this->tag_manager->convert_names_to_ids( [ 'VIP Customer', 'Newsletter' ] );

		$this->assertSame( [ 'tag_001', 'tag_002' ], $ids );
	}

	public function test_convert_names_to_ids_case_insensitive(): void {
		$ids = $this->tag_manager->convert_names_to_ids( [ 'vip customer' ] );

		$this->assertSame( [ 'tag_001' ], $ids );
	}

	public function test_convert_names_to_ids_unknown_returns_name(): void {
		$ids = $this->tag_manager->convert_names_to_ids( [ 'Unknown Tag' ] );

		$this->assertSame( [ 'Unknown Tag' ], $ids );
	}

	// ------------------------------------------------------------------
	//  store_user_tags() — the bug we fixed
	// ------------------------------------------------------------------

	public function test_store_user_tags_fires_hook_on_change(): void {
		Functions\when( 'get_user_meta' )->alias( function ( $uid, $key, $single ) {
			if ( $key === '_ghl_contact_tags_loc_abc123' ) {
				return [ 'tag_001' ]; // Old: only tag_001.
			}
			return '';
		});

		Functions\when( 'update_user_meta' )->justReturn( true );

		$hook_fired = false;
		$hook_user  = null;
		$hook_tags  = null;
		Functions\when( 'do_action' )->alias( function ( $hook, ...$args ) use ( &$hook_fired, &$hook_user, &$hook_tags ) {
			if ( $hook === 'ghl_crm_user_tags_updated' ) {
				$hook_fired = true;
				$hook_user  = $args[0] ?? null;
				$hook_tags  = $args[1] ?? null;
			}
		});

		$stored = $this->tag_manager->store_user_tags( 42, [ 'tag_001', 'tag_002' ] );

		$this->assertTrue( $hook_fired, 'ghl_crm_user_tags_updated should fire when tags change' );
		$this->assertSame( 42, $hook_user );
		$this->assertContains( 'tag_001', $stored );
		$this->assertContains( 'tag_002', $stored );
	}

	public function test_store_user_tags_no_hook_when_unchanged(): void {
		Functions\when( 'get_user_meta' )->alias( function ( $uid, $key, $single ) {
			if ( $key === '_ghl_contact_tags_loc_abc123' ) {
				return [ 'tag_001' ]; // Same as what we're storing.
			}
			return '';
		});

		Functions\when( 'update_user_meta' )->justReturn( true );

		$hook_fired = false;
		Functions\when( 'do_action' )->alias( function ( $hook ) use ( &$hook_fired ) {
			if ( $hook === 'ghl_crm_user_tags_updated' ) {
				$hook_fired = true;
			}
		});

		$this->tag_manager->store_user_tags( 42, [ 'tag_001' ] );

		$this->assertFalse( $hook_fired, 'Hook should NOT fire when tags are unchanged' );
	}

	// ------------------------------------------------------------------
	//  get_user_tag_ids() — normalization on read
	// ------------------------------------------------------------------

	public function test_get_user_tag_ids_normalizes_legacy_names(): void {
		Functions\when( 'get_user_meta' )->alias( function ( $uid, $key, $single ) {
			if ( $key === '_ghl_contact_tags_loc_abc123' ) {
				return [ 'VIP Customer' ]; // Legacy: stored as name.
			}
			return '';
		});

		$updated_value = null;
		Functions\when( 'update_user_meta' )->alias( function ( $uid, $key, $value ) use ( &$updated_value ) {
			if ( $key === '_ghl_contact_tags_loc_abc123' ) {
				$updated_value = $value;
			}
			return true;
		});

		$ids = $this->tag_manager->get_user_tag_ids( 42 );

		$this->assertSame( [ 'tag_001' ], $ids );
		$this->assertSame( [ 'tag_001' ], $updated_value, 'Should write back normalized IDs' );
	}

	public function test_get_user_tag_ids_returns_empty_for_no_meta(): void {
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$ids = $this->tag_manager->get_user_tag_ids( 42 );

		$this->assertSame( [], $ids );
	}

	// ------------------------------------------------------------------
	//  store_user_contact_id() / get_user_contact_id()
	// ------------------------------------------------------------------

	public function test_store_and_get_contact_id(): void {
		$stored_data = [];

		Functions\when( 'update_user_meta' )->alias( function ( $uid, $key, $val ) use ( &$stored_data ) {
			$stored_data[ $key ] = $val;
			return true;
		});

		Functions\when( 'get_user_meta' )->alias( function ( $uid, $key, $single ) use ( &$stored_data ) {
			return $stored_data[ $key ] ?? '';
		});

		$this->tag_manager->store_user_contact_id( 42, 'ghl_contact_xyz' );
		$contact_id = $this->tag_manager->get_user_contact_id( 42 );

		$this->assertSame( 'ghl_contact_xyz', $contact_id );
	}

	public function test_get_contact_id_returns_null_when_empty(): void {
		Functions\when( 'get_user_meta' )->justReturn( '' );

		$this->assertNull( $this->tag_manager->get_user_contact_id( 42 ) );
	}

	// ------------------------------------------------------------------
	//  search_tags()
	// ------------------------------------------------------------------

	public function test_search_tags_returns_all_when_empty_search(): void {
		$results = $this->tag_manager->search_tags();

		$this->assertCount( 5, $results );
	}

	public function test_search_tags_filters_by_name(): void {
		$results = $this->tag_manager->search_tags( 'learn' );

		$this->assertCount( 1, $results );
		$this->assertSame( 'LearnDash Complete', $results[0]['name'] );
	}
}
