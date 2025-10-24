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
		if ( ! $this->settings_manager->is_connection_verified() ) {
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
			add_action( 'user_register', [ $this, 'on_user_register' ], 10, 1 );
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
		$user = get_userdata( $user_id );
		
		if ( ! $user ) {
			return;
		}

		// Prepare contact data
		$contact_data = $this->prepare_contact_data( $user );

		// Queue for async processing
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_manager->add_to_queue( 'user', $user_id, 'user_register', $contact_data );
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
		$last_sync = get_transient( $last_login_key );
		
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
		$settings    = $this->settings_manager->get_settings_array();
		$field_map   = $settings['user_field_mapping'] ?? [];

		// Base contact data
		$contact_data = [
			'email'     => $user->user_email,
			'firstName' => $user->first_name ?: $user->display_name,
			'lastName'  => $user->last_name,
			'source'    => 'WordPress',
		];

		// Add phone if available
		$phone = get_user_meta( $user->ID, 'billing_phone', true ) ?: get_user_meta( $user->ID, 'phone', true );
		if ( $phone ) {
			$contact_data['phone'] = $phone;
		}

		// Apply custom field mapping
		foreach ( $field_map as $wp_field => $ghl_field ) {
			$value = get_user_meta( $user->ID, $wp_field, true );
			if ( ! empty( $value ) ) {
				$contact_data[ $ghl_field ] = $value;
			}
		}

		// Add custom fields
		$contact_data['customField'] = [
			'wp_user_id'    => (string) $user->ID,
			'wp_user_login' => $user->user_login,
			'wp_user_role'  => implode( ', ', $user->roles ),
		];

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
		$tags     = $settings["user_sync_tags_{$action}"] ?? [];

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
