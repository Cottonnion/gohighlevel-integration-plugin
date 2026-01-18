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

		// Re-register hooks when connection status changes (OAuth completion, manual connection, etc.)
		add_action( 'ghl_crm_connection_status_changed', [ $this, 'register_hooks' ] );
	}
	/**
	 * Register WordPress hooks based on settings
	 *
	 * @return void
	 */
	public function register_hooks(): void {
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
			// Priority 999 ensures roles are assigned before we try to read them
			add_action( 'user_register', [ $this, 'on_user_register' ], 999, 1 );
			add_action( 'edit_user_created_user', [ $this, 'on_user_register' ], 999, 1 );

			// Multisite hooks
			if ( is_multisite() ) {
				// When admin directly creates a user in network admin
				add_action( 'wpmu_new_user', [ $this, 'on_user_register' ], 999, 1 );

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

			// Multisite: Also handle when user is removed from a specific site
			if ( is_multisite() ) {
				add_action( 'remove_user_from_blog', [ $this, 'on_user_remove_from_blog' ], 10, 2 );
			}
		}
	}
	/**
	 * Handle user registration
	 *
	 * @param int $user_id User ID.
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
		$register_tags = $this->settings_manager->get_location_register_tags();

		// Get role-based tags
		// During user creation, WordPress hasn't assigned the role to the database yet
		// We need to read it from $_POST['role'] for admin-created users
		$role_tags_manager = RoleTagsManager::get_instance();
		$role_based_tags   = [];

		// Check if this is an admin-created user (role in POST data)
		if ( ! empty( $_POST['role'] ) && is_string( $_POST['role'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core handles this
			// Admin is creating user with specific role - use POST data
			$assigned_role   = sanitize_text_field( wp_unslash( $_POST['role'] ) );
			$role_based_tags = $role_tags_manager->get_tags_for_role( $assigned_role );
		} else {
			// Regular registration or role already assigned - read from user object
			$role_based_tags = $role_tags_manager->get_user_role_tags( $user_id );
		}

		// Combine registration tags with role-based tags
		$all_tags = array_merge( $register_tags, $role_based_tags );
		$all_tags = array_unique( $all_tags );
		// Re-index array to ensure sequential numeric keys (not associative)
		$all_tags = array_values( $all_tags );

		if ( ! empty( $all_tags ) ) {
			$contact_data['tags'] = $all_tags;
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
	 * @param int   $user_id  User ID.
	 * @param mixed $password Password (unused).
	 * @param array $meta     Signup meta (unused).
	 * @return void
	 */
	public function on_multisite_activate_user( int $user_id, $password = null, array $meta = [] ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Call the standard registration handler
		$this->on_user_register( $user_id );
	}

	/**
	 * Handle multisite blog activation (user + site signup)
	 * Fires when a user activates a new site
	 *
	 * @param int    $blog_id Blog ID.
	 * @param int    $user_id User ID.
	 * @param string $password Password (unused).
	 * @param string $signup_title Site title (unused).
	 * @param array  $meta Signup meta (unused).
	 * @return void
	 */
	public function on_multisite_activate_blog( int $blog_id, int $user_id, $password, string $signup_title, array $meta ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Call the standard registration handler
		$this->on_user_register( $user_id );
	}

	/**
	 * Handle when user is added to a blog
	 * This is a fallback that catches users added to existing sites
	 *
	 * @param int    $user_id User ID.
	 * @param string $role    User role (unused).
	 * @param int    $blog_id Blog ID (unused).
	 * @return void
	 */
	public function on_add_user_to_blog( int $user_id, string $role, int $blog_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Only sync if this is a new user (not already synced)
		$already_synced = get_user_meta( $user_id, '_ghl_synced_on_register', true );
		if ( ! $already_synced ) {
			$this->on_user_register( $user_id );
		}
	}
	/**
	 * Handle user profile update
	 *
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data Old user data.
	 * @return void
	 */
	public function on_user_update( int $user_id, \WP_User $old_user_data ): void {
		// Skip if update was triggered by inbound GHL sync to avoid log spam and ping-pong
		$skip = get_user_meta( $user_id, '_ghl_skip_profile_update_sync', true );
		if ( $skip ) {
			delete_user_meta( $user_id, '_ghl_skip_profile_update_sync' );
			return;
		}

		$this->queue_user_profile_sync( $user_id, $old_user_data );
	}

	/**
	 * Queue a user profile sync job for the specified user.
	 *
	 * @param int           $user_id        User ID.
	 * @param \WP_User|null $old_user_data  Previous user data (optional for manual triggers).
	 * @return bool Whether a queue item was created or updated.
	 */
	public function queue_user_profile_sync( int $user_id, ?\WP_User $old_user_data = null ): bool {
		$settings = $this->settings_manager->get_settings_array();

		$lock_key = "ghl_sync_lock_{$user_id}";
		if ( get_transient( $lock_key ) ) {
			return false;
		}

		set_transient( $lock_key, 1, 10 ); // 10 second lock

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		if ( null === $old_user_data ) {
			$old_user_data = clone $user;
		}

		$old_roles    = $old_user_data->roles;
		$new_roles    = $user->roles;
		$role_changed = ( $old_roles !== $new_roles );

		$contact_data = $this->prepare_contact_data( $user );

		$existing_tags = [];
		// Get contact ID for current location
		$location_id = $this->settings_manager->get_setting( 'location_id' ) ?: $this->settings_manager->get_setting( 'oauth_location_id' );
		$contact_id = \GHL_CRM\Core\TagManager::get_instance()->get_user_contact_id( $user_id, $location_id );

		// If this user just came from an inbound webhook, skip outbound profile sync to avoid loops
		if ( $contact_id ) {
			$inbound_guard = get_transient( 'ghl_inbound_webhook_' . $contact_id );
			if ( false !== $inbound_guard ) {
				delete_transient( 'ghl_inbound_webhook_' . $contact_id );
				return false;
			}
		}

		if ( $contact_id ) {
			try {
				$client  = \GHL_CRM\API\Client\Client::get_instance();
				$contact = $client->get( "contacts/{$contact_id}" );

				if ( ! empty( $contact['contact']['tags'] ) && is_array( $contact['contact']['tags'] ) ) {
					$existing_tags = $contact['contact']['tags'];
				}
			} catch ( \Exception $e ) {
				unset( $e );
			}
		}

		$profile_tags = \GHL_CRM\Core\TagManager::get_instance()->get_user_tag_ids( $user_id );
		if ( is_array( $profile_tags ) ) {
			// Profile tags are now stored as IDs - convert to names for payload
			$tag_manager            = \GHL_CRM\Core\TagManager::get_instance();
			$sanitized_profile_tags = array_map( 'sanitize_text_field', $profile_tags );
			$tag_ids                = array_filter(
				$sanitized_profile_tags,
				static function ( $tag ): bool {
					return $tag !== '';
				}
			);
			$existing_tags = $tag_manager->convert_ids_to_names( $tag_ids );
		}

		$role_tags_manager = RoleTagsManager::get_instance();
		$role_tags_config  = $this->get_location_role_tags_config();

		$tags_to_remove = [];
		if ( $role_changed ) {
			foreach ( $old_roles as $old_role ) {
				$old_role_config = $role_tags_config[ $old_role ] ?? [];
				if ( ! empty( $old_role_config['remove_on_change'] ) && ! empty( $old_role_config['tags'] ) ) {
					$old_tags       = is_array( $old_role_config['tags'] ) ? $old_role_config['tags'] : explode( ',', $old_role_config['tags'] );
					$old_tags       = array_map( 'trim', $old_tags );
					$tags_to_remove = array_merge( $tags_to_remove, $old_tags );
				}
			}
		}

		$role_based_tags = $role_tags_manager->get_user_role_tags( $user_id );

		if ( ! empty( $tags_to_remove ) && ! empty( $existing_tags ) ) {
			$existing_tags = array_values( array_diff( $existing_tags, $tags_to_remove ) );
		}

		$wc_customer_tags = [];
		if ( class_exists( 'WooCommerce' ) ) {
			$wc_settings = $settings['wc_convert_lead_enabled'] ?? false;
			if ( $wc_settings ) {
				$recent_orders = wc_get_orders(
					[
						'customer'     => $user->user_email,
						'status'       => [ 'completed', 'processing' ],
						'limit'        => 2,
						'date_created' => '>' . ( time() - 300 ),
					]
				);

				if ( count( $recent_orders ) === 1 ) {
					$customer_tags_setting = $settings['wc_customer_tag'] ?? [];
					if ( ! is_array( $customer_tags_setting ) ) {
						$customer_tags_setting = ! empty( $customer_tags_setting ) ? [ $customer_tags_setting ] : [];
					}
					$wc_customer_tags = $customer_tags_setting;
				}
			}
		}

		$all_tags = array_unique( array_merge( $existing_tags, $role_based_tags, $wc_customer_tags ) );

		if ( ! empty( $all_tags ) ) {
			$contact_data['tags'] = array_values( $all_tags );
		}

		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_id      = $queue_manager->add_to_queue( 'user', $user_id, 'profile_update', $contact_data );

		// If this user is a parent, sync new tags to all children (PRO feature)
		if ( class_exists( 'GHL_CRM_Pro\Database\FamilyRelationshipsRepository' ) ) {
			$family_repo = \GHL_CRM_Pro\Database\FamilyRelationshipsRepository::get_instance();
			$children    = $family_repo->get_children( $user_id );
			
			if ( ! empty( $children ) && class_exists( 'GHL_CRM_Pro\FamilyManager' ) ) {
				$family_manager = \GHL_CRM_Pro\FamilyManager::get_instance();
				foreach ( $children as $child_id ) {
					$family_manager->sync_parent_tags_to_child( $user_id, $child_id );
				}
			}
		}

		return false !== $queue_id;
	}

	/**
	 * Get role tags for the current location with legacy fallback
	 *
	 * @return array
	 */
	private function get_location_role_tags_config(): array {
		$role_tags = $this->settings_manager->get_location_role_tags();

		if ( empty( $role_tags ) ) {
			$role_tags = $this->settings_manager->get_setting( 'role_tags', [] );
		}

		return is_array( $role_tags ) ? $role_tags : [];
	}
	/**
	 * Handle user deletion
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_user_delete( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$settings = $this->settings_manager->get_settings_array();
		// Queue deletion with settings
		$data          = [
			'email'  => $user->user_email,
			'delete' => ! empty( $settings['delete_contact_on_user_delete'] ),
		];
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_manager->add_to_queue( 'user', $user_id, 'delete_user', $data );
	}

	/**
	 * Handle user removal from blog (Multisite)
	 * Fires when user is removed from a specific site, not deleted from network
	 *
	 * @param int $user_id User ID being removed.
	 * @param int $blog_id Blog ID user is being removed from.
	 * @return void
	 */
	public function on_user_remove_from_blog( int $user_id, int $blog_id ): void {
		// Only process if this is the current site
		if ( get_current_blog_id() !== $blog_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$settings = $this->settings_manager->get_settings_array();

		// Queue deletion with settings (same as delete_user)
		$data = [
			'email'   => $user->user_email,
			'delete'  => ! empty( $settings['delete_contact_on_user_delete'] ),
			'blog_id' => $blog_id, // Track which site triggered this
		];

		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_manager->add_to_queue( 'user', $user_id, 'delete_user', $data );
	}
	/**
	 * Handle user login
	 * Updates last_login custom field instead of adding notes (less API spam)
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 * @return void
	 */
	public function on_user_login( $user_login, $user ): void {
		if ( empty( $user_login ) || ! $user instanceof \WP_User ) {
			return; // Ignore malformed login events (e.g., Local auto-login without user object)
		}

		// Throttle: Only update once per hour to avoid API spam
		$last_login_key = "ghl_last_login_{$user->ID}";
		$last_sync      = get_transient( $last_login_key );
		if ( $last_sync ) {
			return; // Already synced within the hour
		}
		set_transient( $last_login_key, time(), HOUR_IN_SECONDS );
		// Queue login tracking (will update custom field, not add note)
		$data          = [
			'email'      => $user->user_email,
			'last_login' => current_time( 'mysql' ),
		];
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_manager->add_to_queue( 'user', $user->ID, 'user_login', $data );
	}
	/**
	 * Prepare contact data from WP User
	 *
	 * @param \WP_User $user WordPress user object.
	 * @return array Contact data for GHL API.
	 */
	private function prepare_contact_data( \WP_User $user ): array {
		$settings  = $this->settings_manager->get_settings_array();
		$field_map = $settings['user_field_mapping'] ?? [];

		// Build source identifier with site info
		$site_url     = get_site_url();
		$site_name    = get_bloginfo( 'name' );
		$source_parts = [ 'WordPress' ];

		if ( is_multisite() ) {
			$site_id        = get_current_blog_id();
			$source_parts[] = "Site #{$site_id}";
		}

		if ( ! empty( $site_name ) ) {
			$source_parts[] = $site_name;
		}

		$parsed_host    = wp_parse_url( $site_url, PHP_URL_HOST );
		$source_parts[] = $parsed_host ? $parsed_host : $site_url;

		// Start with source field (always included)
		$contact_data = [
			'source' => implode( ' - ', $source_parts ),
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
			if ( 'both' !== $direction && 'to_ghl' !== $direction ) {
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
					$custom_field_id     = str_replace( 'custom.', '', $ghl_field );
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

		// Add custom fields to contact data (GHL API uses 'customFields' plural)
		// Format: [{"id": "field_id", "value": "value"}] for custom fields from field mapping

		// Note: GHL API expects 'customFields' (plural) with array of objects containing 'id' and 'value'
		if ( ! empty( $ghl_custom_fields ) ) {
			$contact_data['customFields'] = $ghl_custom_fields;
		}

		return $contact_data;
	}
	/**
	 * Maybe add tags based on action
	 *
	 * @param string|null $contact_id Contact ID.
	 * @param string      $action     Action name.
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
				// Fetch existing tags to merge (don't overwrite)
				$client          = \GHL_CRM\API\Client\Client::get_instance();
				$contact_details = $client->get( "contacts/{$contact_id}" );

				$existing_tags = [];
				if ( ! empty( $contact_details['contact']['tags'] ) && is_array( $contact_details['contact']['tags'] ) ) {
					$existing_tags = $contact_details['contact']['tags'];
				}

				// Merge existing + new tags, remove duplicates
				$merged_tags = array_values( array_unique( array_merge( $existing_tags, $tags ) ) );

				// Update with merged tags
				$this->contact_resource->update( $contact_id, [ 'tags' => $merged_tags ] );
			} catch ( \Exception $e ) {
				// Silent fail for tags.
				unset( $e );
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