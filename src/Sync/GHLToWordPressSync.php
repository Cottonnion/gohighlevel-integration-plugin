<?php
declare(strict_types=1);

namespace Syncly\Sync;

use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;
use Syncly\API\Resources\ContactResource;
use Syncly\API\Client\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GHL to WordPress Sync
 *
 * Handles syncing data from GoHighLevel to WordPress users
 *
 * @package    Syncly
 * @subpackage Sync
 */
class GHLToWordPressSync {
	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Contact Resource
	 *
	 * @var ContactResource
	 */
	private ContactResource $contact_resource;

	/**
	 * Sync Logger
	 *
	 * @var SyncLogger
	 */
	private SyncLogger $logger;

	/**
	 * Tag Manager
	 *
	 * @var TagManager
	 */
	private TagManager $tag_manager;

	/**
	 * Location-scoped meta key for GHL contact IDs.
	 *
	 * @var string
	 */
	private string $contact_meta_key;

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
	 * Constructor
	 */
	private function __construct() {
		$this->settings_manager = SettingsManager::get_instance();
		$client                 = Client::get_instance();
		$this->contact_resource = new ContactResource( $client );
		$this->logger           = SyncLogger::get_instance();
		$this->tag_manager      = TagManager::get_instance();
		$this->contact_meta_key = $this->tag_manager->get_user_contact_id_meta_key();
	}

	/**
	 * Sync contact from GHL to WordPress
	 *
	 * @param string $contact_id GHL contact ID
	 * @param array  $contact_data Optional contact data (from webhook)
	 * @return int|\WP_Error User ID or error
	 */
	public function sync_contact_to_wordpress( string $contact_id, array $contact_data = [] ) {
		// If contact data not provided, fetch from API
		if ( empty( $contact_data ) ) {
			try {
				$response     = $this->contact_resource->get( $contact_id );
				$contact_data = $response['contact'] ?? [];
			} catch ( \Exception $e ) {
				$this->logger->log(
					'user',
					0,
					'ghl_to_wp',
					'failed',
					'Failed to fetch contact from GHL',
					[ 'error' => $e->getMessage() ],
					$contact_id
				);
				return new \WP_Error( 'fetch_failed', $e->getMessage() );
			}
		}

		// If tags are missing/empty in webhook payload, hydrate from API to avoid dropping tags
		if ( empty( $contact_data['tags'] ) || ! is_array( $contact_data['tags'] ) ) {
			try {
				$response      = $this->contact_resource->get( $contact_id );
				$fresh_contact = $response['contact'] ?? [];

				if ( ! empty( $fresh_contact ) ) {
					$contact_data = array_merge( $fresh_contact, $contact_data );
				}
			} catch ( \Exception $e ) {
				$this->logger->log(
					'user',
					0,
					'ghl_to_wp',
					'failed',
					'Failed to hydrate tags from GHL',
					[ 'error' => $e->getMessage() ],
					$contact_id
				);
			}
		}

		// Validate contact data
		if ( empty( $contact_data['email'] ) ) {
			return new \WP_Error( 'missing_email', __( 'Contact email is required', 'syncly' ) );
		}

		// Check if user exists by email or GHL ID
		$user = $this->find_wordpress_user( $contact_data );

		if ( $user ) {
			// Update existing user
			return $this->update_wordpress_user( $user->ID, $contact_data, $contact_id );
		}

		// Create new user
		return $this->create_wordpress_user( $contact_data, $contact_id );
	}

	/**
	 * Find WordPress user by GHL contact data
	 *
	 * @param array $contact_data Contact data
	 * @return \WP_User|null
	 */
	private function find_wordpress_user( array $contact_data ): ?\WP_User {
		// First, try to find by stored GHL contact ID
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$users = get_users(
			[
				'meta_key'   => $this->contact_meta_key,
				'meta_value' => $contact_data['id'],
				'number'     => 1,
			]
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( ! empty( $users ) ) {
			return $users[0];
		}

		// Fallback: find by email
		$user = get_user_by( 'email', $contact_data['email'] );

		return $user ?: null;
	}

	/**
	 * Create WordPress user from GHL contact
	 *
	 * @param array  $contact_data Contact data
	 * @param string $contact_id   GHL contact ID
	 * @return int|\WP_Error User ID or error
	 */
	private function create_wordpress_user( array $contact_data, string $contact_id ) {
		// Get field mappings
		$field_mappings = $this->get_reverse_field_mappings();

		// Prepare user data
		$user_data = [
			'user_email' => sanitize_email( $contact_data['email'] ),
			'user_login' => $this->generate_username( $contact_data ),
			'role'       => 'subscriber',
		];

		// Map GHL fields to WordPress fields
		foreach ( $field_mappings as $ghl_field => $wp_field ) {
			if ( ! $this->should_sync_field( $wp_field, 'ghl_to_wp' ) ) {
				continue;
			}

			$value = $this->get_contact_field_value( $contact_data, $ghl_field );

			if ( null === $value ) {
				continue;
			}

			// Map to WordPress user fields
			switch ( $wp_field ) {
				case 'first_name':
					$user_data['first_name'] = sanitize_text_field( $value );
					break;
				case 'last_name':
					$user_data['last_name'] = sanitize_text_field( $value );
					break;
				case 'display_name':
					$user_data['display_name'] = sanitize_text_field( $value );
					break;
				case 'user_url':
					$user_data['user_url'] = esc_url_raw( $value );
					break;
			}
		}

		// Generate random password
		$user_data['user_pass'] = wp_generate_password( 20, true, true );

		// Create user
		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			$this->logger->log(
				'user',
				0,
				'ghl_to_wp',
				'failed',
				'Failed to create WordPress user from GHL contact',
				[ 'error' => $user_id->get_error_message() ],
				$contact_id
			);
			return $user_id;
		}

		// Store GHL contact ID (writes both scoped and legacy keys)
		$this->tag_manager->store_user_contact_id( $user_id, $contact_id );
		update_user_meta( $user_id, '_ghl_synced_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, '_ghl_last_sync', time() );

		// Update user meta fields
		$this->update_user_meta_fields( $user_id, $contact_data, $field_mappings );

		// Sync tags
		$this->sync_contact_tags_to_user( $user_id, $contact_data );

		$this->logger->log(
			'user',
			$user_id,
			'ghl_to_wp',
			'success',
			'WordPress user created from GHL contact',
			[ 'user_id' => $user_id ],
			$contact_id
		);
		return $user_id;
	}

	/**
	 * Update WordPress user from GHL contact
	 *
	 * @param int    $user_id      WordPress user ID
	 * @param array  $contact_data Contact data
	 * @param string $contact_id   GHL contact ID
	 * @return int|\WP_Error User ID or error
	 */
	private function update_wordpress_user( int $user_id, array $contact_data, string $contact_id ) {
		// Get field mappings
		$field_mappings = $this->get_reverse_field_mappings();

		// Prepare user data
		$user_data = [ 'ID' => $user_id ];

		// Map GHL fields to WordPress fields
		foreach ( $field_mappings as $ghl_field => $wp_field ) {
			if ( ! $this->should_sync_field( $wp_field, 'ghl_to_wp' ) ) {
				continue;
			}

			$value = $this->get_contact_field_value( $contact_data, $ghl_field );

			if ( null === $value ) {
				continue;
			}

			// Map to WordPress user fields
			switch ( $wp_field ) {
				case 'user_email':
					$user_data['user_email'] = sanitize_email( $value );
					break;
				case 'first_name':
					$user_data['first_name'] = sanitize_text_field( $value );
					break;
				case 'last_name':
					$user_data['last_name'] = sanitize_text_field( $value );
					break;
				case 'display_name':
					$user_data['display_name'] = sanitize_text_field( $value );
					break;
				case 'user_url':
					$user_data['user_url'] = esc_url_raw( $value );
					break;
			}
		}

		// Update user if we have data to update
		if ( count( $user_data ) > 1 ) {
			// Prevent ping-pong: skip outbound profile_update hook triggered by this inbound sync
			update_user_meta( $user_id, '_ghl_skip_profile_update_sync', 1 );
			$result = wp_update_user( $user_data );
			delete_user_meta( $user_id, '_ghl_skip_profile_update_sync' );

			if ( is_wp_error( $result ) ) {
				$this->logger->log(
					'user',
					$user_id,
					'ghl_to_wp',
					'failed',
					'Failed to update WordPress user from GHL contact',
					[ 'error' => $result->get_error_message() ],
					$contact_id
				);
				return $result;
			}
		}

		// Update GHL contact ID if not set
		if ( ! $this->tag_manager->get_user_contact_id( $user_id ) ) {
			$this->tag_manager->store_user_contact_id( $user_id, $contact_id );
		}
		update_user_meta( $user_id, '_ghl_synced_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, '_ghl_last_sync', time() );

		// Update user meta fields
		$this->update_user_meta_fields( $user_id, $contact_data, $field_mappings );

		// Sync tags
		$this->sync_contact_tags_to_user( $user_id, $contact_data );

		$this->logger->log(
			'user',
			$user_id,
			'ghl_to_wp',
			'success',
			'WordPress user updated from GHL contact',
			[
				'user_id'    => $user_id,
				'contact_id' => $contact_id,
			],
			$contact_id
		);
		return $user_id;
	}

	/**
	 * Delete WordPress user (from GHL contact delete)
	 *
	 * @param string $contact_id GHL contact ID
	 * @return bool|\WP_Error
	 */
	public function delete_wordpress_user( string $contact_id ) {
		// Find user by GHL contact ID
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$users = get_users(
			[
				'meta_key'   => $this->contact_meta_key,
				'meta_value' => $contact_id,
				'number'     => 1,
			]
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( empty( $users ) ) {
			return new \WP_Error( 'user_not_found', __( 'WordPress user not found', 'syncly' ) );
		}

		$user_id = $users[0]->ID;

		// Check if user deletion is enabled
		$allow_deletion = $this->settings_manager->get_setting( 'allow_user_deletion', false );
		if ( ! $allow_deletion ) {
			// Just remove the GHL association
			delete_user_meta( $user_id, $this->contact_meta_key );
			delete_user_meta( $user_id, TagManager::LEGACY_CONTACT_META_KEY );
			update_user_meta( $user_id, '_ghl_deleted_at', current_time( 'mysql' ) );

			$this->logger->log(
				'user',
				$user_id,
				'ghl_to_wp',
				'success',
				'WordPress user unlinked from GHL contact (deletion disabled)',
				[ 'user_id' => $user_id ],
				$contact_id
			);
			return true;
		}

		// Delete user
		require_once ABSPATH . 'wp-admin/includes/user.php';

		// Prevent ping-pong: skip outbound delete_user hook triggered by this inbound webhook deletion
		update_user_meta( $user_id, '_ghl_skip_delete_sync', 1 );
		$deleted = wp_delete_user( $user_id );

		if ( $deleted ) {
			$this->logger->log(
				'user',
				$user_id,
				'ghl_to_wp',
				'success',
				'WordPress user deleted from GHL contact deletion',
				[ 'user_id' => $user_id ],
				$contact_id
			);
			return true;
		}

		return new \WP_Error( 'delete_failed', __( 'Failed to delete WordPress user', 'syncly' ) );
	}

	/**
	 * Update user meta fields from contact data
	 *
	 * @param int   $user_id        User ID
	 * @param array $contact_data   Contact data
	 * @param array $field_mappings Field mappings
	 * @return void
	 */
	private function update_user_meta_fields( int $user_id, array $contact_data, array $field_mappings ): void {
		foreach ( $field_mappings as $ghl_field => $wp_field ) {
			// Skip base user fields
			if ( in_array( $wp_field, [ 'user_email', 'user_login', 'display_name', 'user_url' ], true ) ) {
				continue;
			}

			if ( ! $this->should_sync_field( $wp_field, 'ghl_to_wp' ) ) {
				continue;
			}

			$value = $this->get_contact_field_value( $contact_data, $ghl_field );

			if ( null !== $value ) {
				update_user_meta( $user_id, $wp_field, $value );
			}
		}
	}

	/**
	 * Sync GHL contact tags to WordPress user meta
	 *
	 * @param int   $user_id      User ID
	 * @param array $contact_data Contact data
	 * @return void
	 */
	private function sync_contact_tags_to_user( int $user_id, array $contact_data ): void {
		$tags = $contact_data['tags'] ?? [];

		if ( ! empty( $tags ) && is_array( $tags ) ) {
			// Use TagManager to store tags with the correct location-scoped meta key
			// and automatically fire the syncly_user_tags_updated hook on change.
			\Syncly\Sync\TagManager::get_instance()->store_user_tags( $user_id, $tags );
		}
	}

	/**
	 * Get reverse field mappings (GHL => WordPress)
	 * Only includes fields that are configured to sync from GHL to WP
	 *
	 * @return array
	 */
	private function get_reverse_field_mappings(): array {
		$settings = $this->settings_manager->get_settings_array();
		$mappings = $settings['user_field_mapping'] ?? [];

		// Reverse the mappings (WP => GHL becomes GHL => WP)
		// Only include fields that sync from GHL to WP (direction: 'ghl_to_wp' or 'both')
		$reversed = [];
		foreach ( $mappings as $wp_field => $mapping_data ) {
			if ( ! is_array( $mapping_data ) ) {
				continue;
			}

			$ghl_field = $mapping_data['ghl_field'] ?? '';
			$direction = $mapping_data['direction'] ?? 'both';

			// Normalize frontend values to backend values
			$direction_map = [
				'from_ghl' => 'ghl_to_wp',
				'to_ghl'   => 'wp_to_ghl',
				'both'     => 'both',
			];
			$direction     = $direction_map[ $direction ] ?? $direction;

			// Only include if direction allows GHL to WP sync
			if ( ! empty( $ghl_field ) && ( 'ghl_to_wp' === $direction || 'both' === $direction ) ) {
				$reversed[ $ghl_field ] = $wp_field;
			}
		}

		return $reversed;
	}

	/**
	 * Get contact field value from nested data
	 *
	 * @param array  $contact_data Contact data
	 * @param string $field_path   Field path (e.g., 'firstName', 'customFields.phone')
	 * @return mixed|null
	 */
	private function get_contact_field_value( array $contact_data, string $field_path ) {
		// Handle custom fields
		if ( strpos( $field_path, 'custom.' ) === 0 ) {
			$custom_field_id = str_replace( 'custom.', '', $field_path );
			$custom_fields   = $contact_data['customFields'] ?? [];

			foreach ( $custom_fields as $field ) {
				if ( isset( $field['id'] ) && $field['id'] === $custom_field_id ) {
					return $field['value'] ?? null;
				}
			}

			return null;
		}

		// Handle standard fields
		return $contact_data[ $field_path ] ?? null;
	}

	/**
	 * Check if field should be synced based on direction
	 *
	 * @param string $field     WordPress field name
	 * @param string $direction Sync direction ('ghl_to_wp' or 'wp_to_ghl')
	 * @return bool
	 */
	private function should_sync_field( string $field, string $direction ): bool {
		$settings       = $this->settings_manager->get_settings_array();
		$field_mappings = $settings['user_field_mapping'] ?? [];

		// If field not mapped, don't sync
		if ( ! isset( $field_mappings[ $field ] ) ) {
			return false;
		}

		$field_direction = $field_mappings[ $field ]['direction'] ?? 'both';

		// Normalize frontend values to backend values
		$direction_map   = [
			'from_ghl' => 'ghl_to_wp',
			'to_ghl'   => 'wp_to_ghl',
			'both'     => 'both',
		];
		$field_direction = $direction_map[ $field_direction ] ?? $field_direction;

		// 'both' means bidirectional sync is enabled
		if ( 'both' === $field_direction ) {
			return true;
		}

		return $field_direction === $direction;
	}

	/**
	 * Generate unique username from contact data
	 *
	 * @param array $contact_data Contact data
	 * @return string
	 */
	private function generate_username( array $contact_data ): string {
		$email     = $contact_data['email'];
		$base_name = sanitize_user( explode( '@', $email )[0] );

		// Check if username exists
		if ( ! username_exists( $base_name ) ) {
			return $base_name;
		}

		// Append numbers until we find unique username
		$counter = 1;
		while ( username_exists( $base_name . $counter ) ) {
			++$counter;
		}

		$final_username = $base_name . $counter;
		return $final_username;
	}
}
