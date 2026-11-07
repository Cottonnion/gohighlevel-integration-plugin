<?php
declare(strict_types=1);

namespace {
	if ( ! class_exists( 'WP_User' ) ) {
		class WP_User {
			public int $ID = 42;
			public string $user_email = 'new@example.com';
			public string $first_name = '';
			public string $last_name = '';
			public string $display_name = '';
			public string $user_login = '';
			public string $user_url = '';
			public string $user_registered = '';
			public string $description = '';
		}
	}
}

namespace Syncly\Tests\Unit\Integrations\Users {
	use Brain\Monkey\Functions;
	use Mockery;
	use Syncly\Core\SettingsManager;
	use Syncly\Integrations\Users\RoleTagsManager;
	use Syncly\Integrations\Users\UserHooks;
	use Syncly\Tests\TestCase;

	class UserHooksRegisterTagsTest extends TestCase {
		public function test_external_tags_are_included_in_registration_contact_payload(): void {
			Functions\when( 'is_multisite' )->justReturn( false );
			Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );
			Functions\when( 'get_bloginfo' )->justReturn( 'Example' );
			Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
			Functions\when( 'apply_filters' )->alias( static function ( $hook, $value ) {
				return 'syncly_user_register_tags' === $hook ? array_merge( $value, [ 'external' ] ) : $value;
			} );

			$settings = Mockery::mock( SettingsManager::class );
			$settings->shouldReceive( 'get_settings_array' )->andReturn( [ 'user_field_mapping' => [] ] );
			$settings->shouldReceive( 'get_location_register_tags' )->andReturn( [ 'default' ] );
			$roles = Mockery::mock( RoleTagsManager::class );
			$roles->shouldReceive( 'get_user_role_tags' )->with( 42 )->andReturn( [ 'role' ] );
			( new \ReflectionProperty( RoleTagsManager::class, 'instance' ) )->setValue( null, $roles );

			$hooks = ( new \ReflectionClass( UserHooks::class ) )->newInstanceWithoutConstructor();
			( new \ReflectionProperty( UserHooks::class, 'settings_manager' ) )->setValue( $hooks, $settings );
			$payload = $hooks->build_register_payload( new \WP_User() );

			$this->assertSame( 'new@example.com', $payload['email'] );
			$this->assertSame( [ 'default', 'role', 'external' ], $payload['tags'] );
		}
	}
}
