<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\API\Resources\ContactResource;
use GHL_CRM\API\Client\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GHL to WordPress Sync
 *
 * Handles syncing data from GoHighLevel to WordPress users
 *
 * @package    GHL_CRM_Integration
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
	}

	/**
	 * Sync contact from GHL to WordPress
	 *
	 * @param string $contact_id GHL contact ID
	 * @param array  $contact_data Optional contact data (from webhook)
	 * @return int|\WP_Error User ID or error
	 */
	public function sync_contact_to_wordpress( string $contact_id, array $contact_data = [] ) {
		error_log( '[GHL->WP Sync] ===== SYNC CONTACT TO WORDPRESS =====' );
		error_log( '[GHL->WP Sync] Contact ID: ' . $contact_id );
		error_log( '[GHL->WP Sync] Has contact data: ' . ( empty( $contact_data ) ? 'NO (will fetch)' : 'YES' ) );
		
		// If contact data not provided, fetch from API
		if ( empty( $contact_data ) ) {
			error_log( '[GHL->WP Sync] Fetching contact from API...' );
			
			try {
				$response     = $this->contact_resource->get( $contact_id );
				$contact_data = $response['contact'] ?? [];
				
				error_log( '[GHL->WP Sync] ✓ Contact fetched from API' );
				error_log( '[GHL->WP Sync] Contact data: ' . wp_json_encode( $contact_data ) );
			} catch ( \Exception $e ) {
				error_log( '[GHL->WP Sync] ERROR: Failed to fetch contact - ' . $e->getMessage() );
				
				$this->logger->log(
					'ghl_to_wp_fetch_failed',
					$contact_id,
					'ghl_to_wp',
					[ 'error' => $e->getMessage() ]
				);
				return new \WP_Error( 'fetch_failed', $e->getMessage() );
			}
		}

		// Validate contact data
		if ( empty( $contact_data['email'] ) ) {
			error_log( '[GHL->WP Sync] ERROR: Contact email is missing or empty' );
			return new \WP_Error( 'missing_email', __( 'Contact email is required', 'ghl-crm-integration' ) );
		}

		error_log( '[GHL->WP Sync] Contact Email: ' . $contact_data['email'] );
		error_log( '[GHL->WP Sync] Contact Name: ' . ( $contact_data['name'] ?? 'N/A' ) );

		// Check if user exists by email or GHL ID
		error_log( '[GHL->WP Sync] Searching for existing WordPress user...' );
		$user = $this->find_wordpress_user( $contact_data );

		if ( $user ) {
			error_log( '[GHL->WP Sync] Found existing user ID: ' . $user->ID . ' (' . $user->user_email . ')' );
			// Update existing user
			return $this->update_wordpress_user( $user->ID, $contact_data, $contact_id );
		}

		error_log( '[GHL->WP Sync] No existing user found, creating new user...' );
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
		error_log( '[GHL->WP Sync] Finding WordPress user...' );
		error_log( '[GHL->WP Sync] Searching by GHL contact ID: ' . ( $contact_data['id'] ?? 'N/A' ) );
		
		// First, try to find by stored GHL contact ID
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$users = get_users(
			[
				'meta_key'   => '_ghl_contact_id',
				'meta_value' => $contact_data['id'],
				'number'     => 1,
			]
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( ! empty( $users ) ) {
			error_log( '[GHL->WP Sync] ✓ Found by GHL contact ID: User ID ' . $users[0]->ID );
			return $users[0];
		}

		error_log( '[GHL->WP Sync] Not found by GHL ID, searching by email: ' . $contact_data['email'] );
		
		// Fallback: find by email
		$user = get_user_by( 'email', $contact_data['email'] );

		if ( $user ) {
			error_log( '[GHL->WP Sync] ✓ Found by email: User ID ' . $user->ID );
		} else {
			error_log( '[GHL->WP Sync] User not found by GHL ID or email' );
		}

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
		error_log( '[GHL->WP Sync] Creating new WordPress user...' );
		
		// Get field mappings
		$field_mappings = $this->get_reverse_field_mappings();
		error_log( '[GHL->WP Sync] Field mappings: ' . wp_json_encode( $field_mappings ) );

		// Prepare user data
		$user_data = [
			'user_email' => sanitize_email( $contact_data['email'] ),
			'user_login' => $this->generate_username( $contact_data ),
			'role'       => $this->settings_manager->get_setting( 'default_user_role', 'subscriber' ),
		];

		error_log( '[GHL->WP Sync] User login: ' . $user_data['user_login'] );
		error_log( '[GHL->WP Sync] User role: ' . $user_data['role'] );

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
		
		error_log( '[GHL->WP Sync] Prepared user data: ' . wp_json_encode( array_diff_key( $user_data, [ 'user_pass' => '' ] ) ) );

		// Create user
		error_log( '[GHL->WP Sync] Calling wp_insert_user()...' );
		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			error_log( '[GHL->WP Sync] ERROR: User creation failed - ' . $user_id->get_error_message() );
			
			$this->logger->log(
				'wp_user_create_failed',
				$contact_id,
				'ghl_to_wp',
				[ 'error' => $user_id->get_error_message() ]
			);
			return $user_id;
		}

		error_log( '[GHL->WP Sync] ✓ User created successfully - ID: ' . $user_id );

		// Store GHL contact ID
		update_user_meta( $user_id, '_ghl_contact_id', $contact_id );
		update_user_meta( $user_id, '_ghl_synced_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, '_ghl_last_sync', time() );
		
		error_log( '[GHL->WP Sync] User meta updated: _ghl_contact_id = ' . $contact_id );

		// Update user meta fields
		$this->update_user_meta_fields( $user_id, $contact_data, $field_mappings );

		// Sync tags
		$this->sync_contact_tags_to_user( $user_id, $contact_data );

		$this->logger->log(
			'wp_user_created_from_ghl',
			$contact_id,
			'ghl_to_wp',
			[ 'user_id' => $user_id ]
		);

		error_log( '[GHL->WP Sync] ===== USER CREATION COMPLETE =====' );
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
		error_log( '[GHL->WP Sync] Updating existing WordPress user ID: ' . $user_id );
		
		// Get field mappings
		$field_mappings = $this->get_reverse_field_mappings();

		error_log( '[GHL->WP Sync] ===== FIELD MAPPING DEBUG =====' );
		error_log( '[GHL->WP Sync] Reverse field mappings: ' . wp_json_encode( $field_mappings ) );
		error_log( '[GHL->WP Sync] Contact data keys: ' . wp_json_encode( array_keys( $contact_data ) ) );
		error_log( '[GHL->WP Sync] firstName in contact: ' . ( $contact_data['firstName'] ?? 'NOT SET' ) );
		error_log( '[GHL->WP Sync] lastName in contact: ' . ( $contact_data['lastName'] ?? 'NOT SET' ) );

		// Prepare user data
		$user_data = [ 'ID' => $user_id ];

		// Map GHL fields to WordPress fields
		foreach ( $field_mappings as $ghl_field => $wp_field ) {
			error_log( '[GHL->WP Sync] Processing mapping: ' . $wp_field . ' <- ' . $ghl_field );

			if ( ! $this->should_sync_field( $wp_field, 'ghl_to_wp' ) ) {
				error_log( '[GHL->WP Sync] ✗ Skipped (sync direction): ' . $wp_field );
				continue;
			}

			$value = $this->get_contact_field_value( $contact_data, $ghl_field );
			error_log( '[GHL->WP Sync] Value from contact[' . $ghl_field . ']: ' . ( $value ?? 'NULL' ) );

			if ( null === $value ) {
				error_log( '[GHL->WP Sync] ✗ Skipped (value is null)' );
				continue;
			}

			// Map to WordPress user fields
			switch ( $wp_field ) {
				case 'user_email':
					$user_data['user_email'] = sanitize_email( $value );
					error_log( '[GHL->WP Sync] ✓ Set user_email = ' . $user_data['user_email'] );
					break;
				case 'first_name':
					$user_data['first_name'] = sanitize_text_field( $value );
					error_log( '[GHL->WP Sync] ✓ Set first_name = ' . $user_data['first_name'] );
					break;
				case 'last_name':
					$user_data['last_name'] = sanitize_text_field( $value );
					error_log( '[GHL->WP Sync] ✓ Set last_name = ' . $user_data['last_name'] );
					break;
				case 'display_name':
					$user_data['display_name'] = sanitize_text_field( $value );
					error_log( '[GHL->WP Sync] ✓ Set display_name = ' . $user_data['display_name'] );
					break;
				case 'user_url':
					$user_data['user_url'] = esc_url_raw( $value );
					error_log( '[GHL->WP Sync] ✓ Set user_url = ' . $user_data['user_url'] );
					break;
			}
		}

		error_log( '[GHL->WP Sync] Fields to update: ' . count( $user_data ) );

		// Update user if we have data to update
		if ( count( $user_data ) > 1 ) {
			error_log( '[GHL->WP Sync] Calling wp_update_user()...' );
			$result = wp_update_user( $user_data );

			if ( is_wp_error( $result ) ) {
				error_log( '[GHL->WP Sync] ERROR: User update failed - ' . $result->get_error_message() );
				
				$this->logger->log(
					'wp_user_update_failed',
					$contact_id,
					'ghl_to_wp',
					[ 'error' => $result->get_error_message() ]
				);
				return $result;
			}
			
			error_log( '[GHL->WP Sync] ✓ User updated successfully' );
		}

		// Update GHL contact ID if not set
		if ( ! get_user_meta( $user_id, '_ghl_contact_id', true ) ) {
			update_user_meta( $user_id, '_ghl_contact_id', $contact_id );
			error_log( '[GHL->WP Sync] Added _ghl_contact_id meta: ' . $contact_id );
		}
		update_user_meta( $user_id, '_ghl_synced_at', current_time( 'mysql' ) );
		update_user_meta( $user_id, '_ghl_last_sync', time() );

		// Update user meta fields
		$this->update_user_meta_fields( $user_id, $contact_data, $field_mappings );

		// Sync tags
		$this->sync_contact_tags_to_user( $user_id, $contact_data );

		$this->logger->log(
			'wp_user_updated_from_ghl',
			$user_id,
			'ghl_to_wp',
			'success',
			'WordPress user updated from GHL contact',
			[ 
				'user_id' => $user_id,
				'contact_id' => $contact_id 
			],
			$contact_id
		);

		error_log( '[GHL->WP Sync] ===== USER UPDATE COMPLETE =====' );
		return $user_id;
	}

	/**
	 * Delete WordPress user (from GHL contact delete)
	 *
	 * @param string $contact_id GHL contact ID
	 * @return bool|\WP_Error
	 */
	public function delete_wordpress_user( string $contact_id ) {
		error_log( '[GHL->WP Sync] ===== DELETE WORDPRESS USER =====' );
		error_log( '[GHL->WP Sync] Contact ID: ' . $contact_id );
		
		// Find user by GHL contact ID
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$users = get_users(
			[
				'meta_key'   => '_ghl_contact_id',
				'meta_value' => $contact_id,
				'number'     => 1,
			]
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( empty( $users ) ) {
			error_log( '[GHL->WP Sync] ERROR: WordPress user not found for contact ID: ' . $contact_id );
			return new \WP_Error( 'user_not_found', __( 'WordPress user not found', 'ghl-crm-integration' ) );
		}

		$user_id = $users[0]->ID;
		error_log( '[GHL->WP Sync] Found user ID: ' . $user_id );

		// Check if user deletion is enabled
		$allow_deletion = $this->settings_manager->get_setting( 'allow_user_deletion', false );
		error_log( '[GHL->WP Sync] Allow user deletion setting: ' . ( $allow_deletion ? 'YES' : 'NO' ) );
		
		if ( ! $allow_deletion ) {
			error_log( '[GHL->WP Sync] User deletion disabled, unlinking only...' );
			
			// Just remove the GHL association
			delete_user_meta( $user_id, '_ghl_contact_id' );
			update_user_meta( $user_id, '_ghl_deleted_at', current_time( 'mysql' ) );

			$this->logger->log(
				'wp_user_unlinked',
				$contact_id,
				'ghl_to_wp',
				[ 'user_id' => $user_id ]
			);

			error_log( '[GHL->WP Sync] ✓ User unlinked (not deleted)' );
			return true;
		}

		// Delete user
		error_log( '[GHL->WP Sync] Deleting user ID: ' . $user_id );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$deleted = wp_delete_user( $user_id );

		if ( $deleted ) {
			error_log( '[GHL->WP Sync] ✓ User deleted successfully' );
			
			$this->logger->log(
				'wp_user_deleted',
				$contact_id,
				'ghl_to_wp',
				[ 'user_id' => $user_id ]
			);
			return true;
		}

		error_log( '[GHL->WP Sync] ERROR: Failed to delete user' );
		return new \WP_Error( 'delete_failed', __( 'Failed to delete WordPress user', 'ghl-crm-integration' ) );
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

		error_log( '[GHL->WP Sync] Syncing tags for user ' . $user_id . ': ' . wp_json_encode( $tags ) );

		if ( ! empty( $tags ) && is_array( $tags ) ) {
			update_user_meta( $user_id, '_ghl_tags', $tags );
			error_log( '[GHL->WP Sync] ✓ Tags synced: ' . count( $tags ) . ' tag(s)' );
		} else {
			error_log( '[GHL->WP Sync] No tags to sync' );
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
			$direction = $direction_map[ $direction ] ?? $direction;

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
		$direction_map = [
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

		error_log( '[GHL->WP Sync] Generating username from email: ' . $email );
		error_log( '[GHL->WP Sync] Base username: ' . $base_name );

		// Check if username exists
		if ( ! username_exists( $base_name ) ) {
			error_log( '[GHL->WP Sync] ✓ Username available: ' . $base_name );
			return $base_name;
		}

		error_log( '[GHL->WP Sync] Username exists, adding counter...' );
		
		// Append numbers until we find unique username
		$counter = 1;
		while ( username_exists( $base_name . $counter ) ) {
			++$counter;
		}

		$final_username = $base_name . $counter;
		error_log( '[GHL->WP Sync] ✓ Generated unique username: ' . $final_username );
		
		return $final_username;
	}
}
