<?php
declare(strict_types=1);
namespace GHL_CRM\Integrations\Users;
use GHL_CRM\API\Client\Client;
use GHL_CRM\API\Resources\ContactResource;
use GHL_CRM\Core\SettingsManager;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * User Hooks
 *
 * Listens to WordPress user events and triggers sync to GoHighLevel
 *
 * @package    GHL_CRM_Integration
 * @subpackage Integrations/Users
 */
class UserHooks {
	/**
	 * Contact Resource
	 *
	 * @var ContactResource
	 */
	private ContactResource $contact_resource;
	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;
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
		$this->settings_manager = SettingsManager::get_instance();
		// Initialize ContactResource with Client
		$client                 = Client::get_instance();
		$this->contact_resource = new ContactResource( $client );
		// Register hooks if sync is enabled
		$this->register_hooks();
	}
	/**
	 * Register WordPress hooks based on settings
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$settings = $this->settings_manager->get_settings_array();
		
		// Check if connection is verified first
		$is_verified = $this->settings_manager->is_connection_verified();
		
		if ( ! $is_verified ) {
			return;
		}
		// Check API credentials (OAuth or manual token)
		$has_oauth = ! empty( $settings['oauth_access_token'] );
		$has_token = ! empty( $settings['api_token'] );
		if ( ! $has_oauth && ! $has_token ) {
			return;
		}
		if ( empty( $settings['location_id'] ) ) {
			return;
		}
		// Get sync actions array
		$sync_actions = $settings['user_sync_actions'] ?? [];
		// 1. User registration hook - Create new contacts in GoHighLevel when users register
		if ( in_array( 'user_register', $sync_actions, true ) ) {
			
			// Standard WordPress registration (single site or admin-created users)
			add_action( 'user_register', [ $this, 'on_user_register' ], 10, 1 );
			add_action( 'edit_user_created_user', [ $this, 'on_user_register' ], 10, 1 );
			
			// Multisite hooks
			if ( is_multisite() ) {
				// When admin directly creates a user in network admin
				add_action( 'wpmu_new_user', [ $this, 'on_user_register' ], 10, 1 );
				
				// CRITICAL: These are the hooks that fire during multisite frontend registration/activation
				// Priority 999 to ensure WordPress has finished all its setup
				add_action( 'wpmu_activate_user', [ $this, 'on_multisite_activate_user' ], 999, 3 );
				add_action( 'wpmu_activate_blog', [ $this, 'on_multisite_activate_blog' ], 999, 5 );
				
				// Alternative: Hook into when user is added to a blog
				add_action( 'add_user_to_blog', [ $this, 'on_add_user_to_blog' ], 10, 3 );
			}
		}
		// 2. User sync enabled - Sync profile updates and logins
		if ( ! empty( $settings['enable_user_sync'] ) ) {
			// Sync user profile updates to GoHighLevel
			add_action( 'profile_update', [ $this, 'on_user_update' ], 10, 2 );
			// Track user logins in GoHighLevel
			add_action( 'wp_login', [ $this, 'on_user_login' ], 10, 2 );
		}
		// 3. User deletion hook - Handle contact deletion/tagging when user is deleted
		if ( ! empty( $settings['delete_contact_on_user_delete'] ) ) {
			add_action( 'delete_user', [ $this, 'on_user_delete' ], 10, 1 );
		}
	}
	/**
	 * Handle user registration
	 *
	 * @param int $user_id User ID
	 * @return void
	 */
	public function on_user_register( int $user_id ): void {
		// Check if already synced to prevent duplicates
		$already_synced = get_user_meta( $user_id, '_ghl_synced_on_register', true );
		if ( $already_synced ) {
			return;
		}
		
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		
		// Prepare contact data
		$contact_data = $this->prepare_contact_data( $user );
		
		// Add registration tags if configured
		$settings = $this->settings_manager->get_settings_array();
		$register_tags = $settings['user_register_tags'] ?? [];
		
		if ( ! empty( $register_tags ) && is_array( $register_tags ) ) {
			$contact_data['tags'] = $register_tags;
		}
		
		// Queue for async processing
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		
		$queue_id = $queue_manager->add_to_queue( 'user', $user_id, 'user_register', $contact_data );
		
		// Mark as synced
		if ( $queue_id ) {
			update_user_meta( $user_id, '_ghl_synced_on_register', time() );
		}
	}
	/**
	 * Handle multisite user activation (user-only signup)
	 * Fires when a user activates their account via email
	 *
	 * @param int   $user_id  User ID
	 * @param mixed $password Password
	 * @param array $meta     Signup meta
	 * @return void
	 */
	public function on_multisite_activate_user( int $user_id, $password = null, array $meta = [] ): void {
		// Call the standard registration handler
		$this->on_user_register( $user_id );
	}
	
	/**
	 * Handle multisite blog activation (user + site signup)
	 * Fires when a user activates a new site
	 *
	 * @param int    $blog_id Blog ID
	 * @param int    $user_id User ID
	 * @param string $password Password
	 * @param string $signup_title Site title
	 * @param array  $meta Signup meta
	 * @return void
	 */
	public function on_multisite_activate_blog( int $blog_id, int $user_id, $password, string $signup_title, array $meta ): void {
		// Call the standard registration handler
		$this->on_user_register( $user_id );
	}
	
	/**
	 * Handle when user is added to a blog
	 * This is a fallback that catches users added to existing sites
	 *
	 * @param int    $user_id User ID
	 * @param string $role    User role
	 * @param int    $blog_id Blog ID
	 * @return void
	 */
	public function on_add_user_to_blog( int $user_id, string $role, int $blog_id ): void {
		// Only sync if this is a new user (not already synced)
		$already_synced = get_user_meta( $user_id, '_ghl_synced_on_register', true );
		if ( ! $already_synced ) {
			$this->on_user_register( $user_id );
		}
	}
	/**
	 * Handle user profile update
	 *
	 * @param int      $user_id       User ID
	 * @param \WP_User $old_user_data Old user data
	 * @return void
	 */
	public function on_user_update( int $user_id, \WP_User $old_user_data ): void {
		// Debounce: Prevent multiple API calls on rapid updates
		$lock_key = "ghl_sync_lock_{$user_id}";
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 10 ); // 10 second lock
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		// Prepare contact data
		$contact_data = $this->prepare_contact_data( $user );
		// Queue for async processing
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_manager->add_to_queue( 'user', $user_id, 'profile_update', $contact_data );
	}
	/**
	 * Handle user deletion
	 *
	 * @param int $user_id User ID
	 * @return void
	 */
	public function on_user_delete( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$settings = $this->settings_manager->get_settings_array();
		// Queue deletion with settings
		$data = [
			'email'  => $user->user_email,
			'delete' => ! empty( $settings['delete_contact_on_user_delete'] ),
		];
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_manager->add_to_queue( 'user', $user_id, 'delete_user', $data );
	}
	/**
	 * Handle user login
	 * Updates last_login custom field instead of adding notes (less API spam)
	 *
	 * @param string   $user_login Username
	 * @param \WP_User $user       User object
	 * @return void
	 */
	public function on_user_login( string $user_login, \WP_User $user ): void {
		// Throttle: Only update once per hour to avoid API spam
		$last_login_key = "ghl_last_login_{$user->ID}";
		$last_sync      = get_transient( $last_login_key );
		if ( $last_sync ) {
			return; // Already synced within the hour
		}
		set_transient( $last_login_key, time(), HOUR_IN_SECONDS );
		// Queue login tracking (will update custom field, not add note)
		$data = [
			'email'      => $user->user_email,
			'last_login' => current_time( 'mysql' ),
		];
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_manager->add_to_queue( 'user', $user->ID, 'user_login', $data );
	}
	/**
	 * Prepare contact data from WP User
	 *
	 * @param \WP_User $user WordPress user object
	 * @return array Contact data for GHL API
	 */
	private function prepare_contact_data( \WP_User $user ): array {
		$settings  = $this->settings_manager->get_settings_array();
		$field_map = $settings['user_field_mapping'] ?? [];

		// Start with source field (always included)
		$contact_data = [
			'source' => 'WordPress',
		];

		// Map of WP user properties to their values
		$wp_user_data = [
			'user_email'   => $user->user_email,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'display_name' => $user->display_name,
			'user_login'   => $user->user_login,
			'user_url'     => $user->user_url,
			'description'  => $user->description,
		];

		// Separate standard fields and custom fields
		$ghl_custom_fields = [];

		// Apply field mappings - only sync fields that are explicitly mapped
		foreach ( $field_map as $wp_field => $mapping ) {
			// Skip if no GHL field is set or set to "Do Not Sync" (empty)
			if ( empty( $mapping['ghl_field'] ) ) {
				continue;
			}

			// Only sync if direction is 'both' or 'to_ghl'
			$direction = $mapping['direction'] ?? 'both';
			if ( $direction !== 'both' && $direction !== 'to_ghl' ) {
				continue;
			}

			$ghl_field = $mapping['ghl_field'];
			$value     = null;

			// Check if it's a standard user property
			if ( isset( $wp_user_data[ $wp_field ] ) ) {
				$value = $wp_user_data[ $wp_field ];
			} else {
				// Try to get from user meta
				$value = get_user_meta( $user->ID, $wp_field, true );
			}

			// Only add non-empty values
			if ( ! empty( $value ) ) {
				// Check if this is a GHL custom field (prefixed with "custom.")
				if ( strpos( $ghl_field, 'custom.' ) === 0 ) {
					// Extract custom field ID
					$custom_field_id = str_replace( 'custom.', '', $ghl_field );
					$ghl_custom_fields[] = [
						'id'    => $custom_field_id,
						'value' => $value,
					];
				} else {
					// Standard GHL field
					$contact_data[ $ghl_field ] = $value;
				}
			}
		}

		// Fallback: If email is not mapped, always include it (required by GHL)
		if ( ! isset( $contact_data['email'] ) && ! empty( $user->user_email ) ) {
			$contact_data['email'] = $user->user_email;
		}

		// Add GHL custom fields if any were mapped
		if ( ! empty( $ghl_custom_fields ) ) {
			$contact_data['customField'] = $ghl_custom_fields;
		}

		// Debug log: Show which fields are being synced
		error_log( sprintf(
			'GHL CRM: Preparing contact data for user %d - Syncing %d standard fields + %d custom fields',
			$user->ID,
			count( $contact_data ) - 1, // -1 for 'source' field
			count( $ghl_custom_fields )
		) );
		error_log( 'GHL CRM: Mapped fields: ' . implode( ', ', array_keys( $contact_data ) ) );
		
		// Add WordPress tracking fields (always included for reference)
		$custom_fields = [];
		if ( ! empty( $user->ID ) ) {
			$custom_fields[] = [
				'key'   => 'wp_user_id',
				'value' => (string) $user->ID,
			];
		}
		if ( ! empty( $user->user_login ) ) {
			$custom_fields[] = [
				'key'   => 'wp_user_login',
				'value' => $user->user_login,
			];
		}
		if ( ! empty( $user->roles ) ) {
			$custom_fields[] = [
				'key'   => 'wp_user_role',
				'value' => implode( ', ', $user->roles ),
			];
		}
		
		// Only add customFields if we have data
		if ( ! empty( $custom_fields ) ) {
			$contact_data['customFields'] = $custom_fields;
		}
		
		return $contact_data;
	}
	/**
	 * Maybe add tags based on action
	 *
	 * @param string|null $contact_id Contact ID
	 * @param string      $action     Action name
	 * @return void
	 */
	private function maybe_add_tags( ?string $contact_id, string $action ): void {
		if ( ! $contact_id ) {
			return;
		}
		$settings = $this->settings_manager->get_settings_array();
		$tags     = $settings[ "user_sync_tags_{$action}" ] ?? [];
		if ( ! empty( $tags ) && is_array( $tags ) ) {
			try {
				$this->contact_resource->add_tags( $contact_id, $tags );
			} catch ( \Exception $e ) {
				// Silent fail for tags
				error_log( 'GHL CRM: Failed to add tags - ' . $e->getMessage() );
			}
		}
	}
	/**
	 * Initialize hooks (called by Loader)
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}