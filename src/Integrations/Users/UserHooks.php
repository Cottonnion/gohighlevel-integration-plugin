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

		// Check if user sync is enabled
		if ( empty( $settings['enable_user_sync'] ) ) {
			return;
		}

		// Check API credentials
		if ( empty( $settings['api_token'] ) || empty( $settings['location_id'] ) ) {
			return;
		}

		// Register hooks based on enabled actions
		$sync_actions = $settings['user_sync_actions'] ?? [];

		if ( in_array( 'user_register', $sync_actions, true ) ) {
			add_action( 'user_register', [ $this, 'on_user_register' ], 10, 1 );
		}

		if ( in_array( 'profile_update', $sync_actions, true ) ) {
			add_action( 'profile_update', [ $this, 'on_user_update' ], 10, 2 );
		}

		if ( in_array( 'delete_user', $sync_actions, true ) ) {
			add_action( 'delete_user', [ $this, 'on_user_delete' ], 10, 1 );
		}

		if ( in_array( 'set_user_role', $sync_actions, true ) ) {
			add_action( 'set_user_role', [ $this, 'on_user_role_change' ], 10, 3 );
		}

		if ( in_array( 'user_login', $sync_actions, true ) ) {
			add_action( 'wp_login', [ $this, 'on_user_login' ], 10, 2 );
		}
	}

	/**
	 * Handle user registration
	 *
	 * @param int $user_id User ID
	 * @return void
	 */
	public function on_user_register( int $user_id ): void {
		try {
			$user = get_userdata( $user_id );
			
			if ( ! $user ) {
				return;
			}

			// Prepare contact data
			$contact_data = $this->prepare_contact_data( $user );

			// Create contact in GHL
			$response = $this->contact_resource->upsert( $contact_data );

			// Log success
			$this->log_sync_event( $user_id, 'user_register', 'success', $response );

			// Add tags if configured
			$this->maybe_add_tags( $response['contact']['id'] ?? null, 'user_register' );

		} catch ( \Exception $e ) {
			// Log error
			$this->log_sync_event( $user_id, 'user_register', 'error', [
				'message' => $e->getMessage(),
			] );
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
		try {
			$user = get_userdata( $user_id );
			
			if ( ! $user ) {
				return;
			}

			// Prepare contact data
			$contact_data = $this->prepare_contact_data( $user );

			// Update contact in GHL
			$response = $this->contact_resource->upsert( $contact_data );

			// Log success
			$this->log_sync_event( $user_id, 'profile_update', 'success', $response );

		} catch ( \Exception $e ) {
			// Log error
			$this->log_sync_event( $user_id, 'profile_update', 'error', [
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Handle user deletion
	 *
	 * @param int $user_id User ID
	 * @return void
	 */
	public function on_user_delete( int $user_id ): void {
		try {
			$user = get_userdata( $user_id );
			
			if ( ! $user ) {
				return;
			}

			// Find contact by email
			$contact = $this->contact_resource->find_by_email( $user->user_email );

			if ( $contact ) {
				// Check if we should delete or just tag
				$settings = $this->settings_manager->get_settings_array();
				
				if ( ! empty( $settings['delete_contact_on_user_delete'] ) ) {
					// Delete contact from GHL
					$this->contact_resource->delete( $contact['id'] );
				} else {
					// Just add a "deleted" tag
					$this->contact_resource->add_tags( $contact['id'], [ 'wp-user-deleted' ] );
				}

				// Log success
				$this->log_sync_event( $user_id, 'delete_user', 'success', [ 'contact_id' => $contact['id'] ] );
			}

		} catch ( \Exception $e ) {
			// Log error
			$this->log_sync_event( $user_id, 'delete_user', 'error', [
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Handle user role change
	 *
	 * @param int    $user_id   User ID
	 * @param string $role      New role
	 * @param array  $old_roles Old roles
	 * @return void
	 */
	public function on_user_role_change( int $user_id, string $role, array $old_roles ): void {
		try {
			$user = get_userdata( $user_id );
			
			if ( ! $user ) {
				return;
			}

			// Find contact by email
			$contact = $this->contact_resource->find_by_email( $user->user_email );

			if ( $contact ) {
				// Add role-based tags
				$this->contact_resource->add_tags( $contact['id'], [ "wp-role-{$role}" ] );

				// Update contact data with new role
				$contact_data         = $this->prepare_contact_data( $user );
				$this->contact_resource->update( $contact['id'], $contact_data );

				// Log success
				$this->log_sync_event( $user_id, 'set_user_role', 'success', [
					'new_role'  => $role,
					'old_roles' => $old_roles,
				] );
			}

		} catch ( \Exception $e ) {
			// Log error
			$this->log_sync_event( $user_id, 'set_user_role', 'error', [
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Handle user login
	 *
	 * @param string   $user_login Username
	 * @param \WP_User $user       User object
	 * @return void
	 */
	public function on_user_login( string $user_login, \WP_User $user ): void {
		try {
			// Find contact by email
			$contact = $this->contact_resource->find_by_email( $user->user_email );

			if ( $contact ) {
				// Add note about login
				$this->contact_resource->add_note(
					$contact['id'],
					sprintf(
						/* translators: %s: Date and time */
						__( 'User logged in on %s', 'ghl-crm-integration' ),
						current_time( 'mysql' )
					)
				);

				// Log success
				$this->log_sync_event( $user->ID, 'user_login', 'success', [ 'contact_id' => $contact['id'] ] );
			}

		} catch ( \Exception $e ) {
			// Log error
			$this->log_sync_event( $user->ID, 'user_login', 'error', [
				'message' => $e->getMessage(),
			] );
		}
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
	 * Log sync event
	 *
	 * @param int    $user_id User ID
	 * @param string $action  Action name
	 * @param string $status  Status (success/error)
	 * @param array  $data    Additional data
	 * @return void
	 */
	private function log_sync_event( int $user_id, string $action, string $status, array $data = [] ): void {
		// Store in wp_options for now (Phase 2 will create dedicated table)
		$log_entry = [
			'user_id'   => $user_id,
			'action'    => $action,
			'status'    => $status,
			'data'      => $data,
			'timestamp' => current_time( 'mysql' ),
		];

		// Get existing logs
		$logs = get_option( 'ghl_crm_sync_logs', [] );
		
		// Add new log
		array_unshift( $logs, $log_entry );
		
		// Keep only last 100 logs
		$logs = array_slice( $logs, 0, 100 );
		
		// Update option
		update_option( 'ghl_crm_sync_logs', $logs );

		// Also log to error_log for debugging
		if ( 'error' === $status ) {
			error_log( sprintf(
				'GHL CRM Sync Error: User %d, Action %s, Message: %s',
				$user_id,
				$action,
				$data['message'] ?? 'Unknown error'
			) );
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
