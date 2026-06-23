<?php
/**
 * Role Tags Manager
 *
 * Manages role-based tag assignments and removal for GHL contacts
 *
 * @package    Syncly
 * @subpackage Integrations/Users
 */

declare(strict_types=1);

namespace Syncly\Integrations\Users;

use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;
use Syncly\Sync\QueueManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Role Tags Manager Class
 *
 * Handles automatic tag assignment based on user roles
 */
class RoleTagsManager {

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings Manager instance
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Queue Manager instance
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Location-scoped meta key for GHL contact IDs.
	 *
	 * @var string
	 */
	private string $contact_meta_key;

	/**
	 * Get singleton instance
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
		$this->queue_manager    = QueueManager::get_instance();
		$this->contact_meta_key = TagManager::get_instance()->get_user_contact_id_meta_key();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Hook into role changes
		add_action( 'set_user_role', [ $this, 'handle_role_change' ], 10, 3 );
		add_action( 'add_user_role', [ $this, 'handle_role_added' ], 10, 2 );
		add_action( 'remove_user_role', [ $this, 'handle_role_removed' ], 10, 2 );

		// AJAX handlers for bulk operations
		add_action( 'wp_ajax_syncly_bulk_add_role_tags', [ $this, 'ajax_bulk_add_role_tags' ] );
		add_action( 'wp_ajax_syncly_bulk_remove_role_tags', [ $this, 'ajax_bulk_remove_role_tags' ] );

		// Protect role-based tags from removal by other sources (e.g. group leave).
		add_filter( 'syncly_tags_protected_from_removal', [ $this, 'protect_role_tags' ], 10, 4 );
	}

	/**
	 * Handle user role change (set_user_role action)
	 *
	 * @param int    $user_id   User ID
	 * @param string $new_role  New role
	 * @param array  $old_roles Array of old roles
	 * @return void
	 */
	public function handle_role_change( int $user_id, string $new_role, array $old_roles ): void {
		$role_tags = $this->get_location_role_tags_config();

		// Get contact ID
		$contact_id = get_user_meta( $user_id, $this->contact_meta_key, true );
		if ( empty( $contact_id ) ) {
			return; // User not synced with GHL
		}

		$tags_to_add    = [];
		$tags_to_remove = [];

		// Remove tags from old roles (if configured)
		foreach ( $old_roles as $old_role ) {
			$role_config = $role_tags[ $old_role ] ?? [];
			if ( ! empty( $role_config['remove_on_change'] ) && ! empty( $role_config['tags'] ) ) {
				$tags           = $this->parse_tags( $role_config['tags'] );
				$tags_to_remove = array_merge( $tags_to_remove, $tags );
			}
		}

		// Add tags for new role (if configured)
		$new_role_config = $role_tags[ $new_role ] ?? [];
		if ( ! empty( $new_role_config['auto_apply'] ) && ! empty( $new_role_config['tags'] ) ) {
			$tags        = $this->parse_tags( $new_role_config['tags'] );
			$tags_to_add = array_merge( $tags_to_add, $tags );
		}

		// Remove duplicates
		$tags_to_add    = array_unique( $tags_to_add );
		$tags_to_remove = array_unique( $tags_to_remove );

		// Queue tag updates
		if ( ! empty( $tags_to_remove ) ) {
			$this->queue_tag_removal( $user_id, $contact_id, $tags_to_remove );
		}

		if ( ! empty( $tags_to_add ) ) {
			$this->queue_tag_addition( $user_id, $contact_id, $tags_to_add );
		}
	}

	/**
	 * Handle role added to user (add_user_role action)
	 *
	 * @param int    $user_id User ID
	 * @param string $role    Role added
	 * @return void
	 */
	public function handle_role_added( int $user_id, string $role ): void {
		$role_tags = $this->get_location_role_tags_config();

		// Get contact ID
		$contact_id = get_user_meta( $user_id, $this->contact_meta_key, true );
		if ( empty( $contact_id ) ) {
			return;
		}

		// Check if auto-apply is enabled for this role
		$role_config = $role_tags[ $role ] ?? [];
		if ( empty( $role_config['auto_apply'] ) || empty( $role_config['tags'] ) ) {
			return;
		}

		$tags = $this->parse_tags( $role_config['tags'] );

		$this->queue_tag_addition( $user_id, $contact_id, $tags );
	}

	/**
	 * Handle role removed from user (remove_user_role action)
	 *
	 * @param int    $user_id User ID
	 * @param string $role    Role removed
	 * @return void
	 */
	public function handle_role_removed( int $user_id, string $role ): void {
		$role_tags = $this->get_location_role_tags_config();

		// Get contact ID
		$contact_id = get_user_meta( $user_id, $this->contact_meta_key, true );
		if ( empty( $contact_id ) ) {
			return;
		}

		// Check if remove_on_change is enabled for this role
		$role_config = $role_tags[ $role ] ?? [];
		if ( empty( $role_config['remove_on_change'] ) || empty( $role_config['tags'] ) ) {
			return;
		}

		$tags = $this->parse_tags( $role_config['tags'] );

		$this->queue_tag_removal( $user_id, $contact_id, $tags );
	}

	/**
	 * Get tags for a user based on their current roles
	 *
	 * @param int $user_id User ID
	 * @return array Array of tag names
	 */
	public function get_user_role_tags( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {

			return [];
		}

		$role_tags   = $this->get_location_role_tags_config();
		$global_tags = $this->get_location_global_tags_config();

		$all_tags = [];

		// Add role-based tags
		foreach ( $user->roles as $role ) {
			$role_config = $role_tags[ $role ] ?? [];

			if ( ! empty( $role_config['tags'] ) ) {
				$tags = $this->parse_tags( $role_config['tags'] );

				$all_tags = array_merge( $all_tags, $tags );
			} else {

			}
		}

		// Add global tags
		if ( ! empty( $global_tags ) ) {
			$all_tags = array_merge( $all_tags, $global_tags );
		}

		// Ensure array has sequential keys for proper JSON encoding
		$final_tags = array_values( array_unique( $all_tags ) );

		return $final_tags;
	}

	/**
	 * Get tags for a specific role (without requiring a user object)
	 * Useful during user creation when role isn't saved to DB yet
	 *
	 * @param string $role Role slug (e.g., 'administrator', 'editor')
	 * @return array Array of tags for this role
	 */
	public function get_tags_for_role( string $role ): array {
		// Get location-specific role tags and global tags
		$role_tags   = $this->get_location_role_tags_config();
		$global_tags = $this->get_location_global_tags_config();

		$all_tags = [];

		// Get tags for this specific role
		$role_config = $role_tags[ $role ] ?? [];

		if ( ! empty( $role_config['tags'] ) ) {
			$tags = $this->parse_tags( $role_config['tags'] );

			$all_tags = array_merge( $all_tags, $tags );
		} else {

		}

		// Add global tags
		if ( ! empty( $global_tags ) ) {
			$all_tags = array_merge( $all_tags, $global_tags );
		}

		// Ensure array has sequential keys for proper JSON encoding
		return array_values( array_unique( $all_tags ) );
	}

	/**
	 * Get role tags for current location with legacy fallback
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
	 * Get global tags for current location with legacy fallback
	 *
	 * @return array
	 */
	private function get_location_global_tags_config(): array {
		$global_tags = $this->settings_manager->get_location_global_tags();

		if ( empty( $global_tags ) ) {
			$global_tags = $this->settings_manager->get_setting( 'global_tags', [] );
		}

		return $this->parse_tags( $global_tags );
	}

	/**
	 * Parse tags from array or comma-separated string
	 *
	 * @param mixed $tags Tags array or comma-separated string
	 * @return array Array of trimmed tag names
	 */
	private function parse_tags( $tags ): array {
		// If already an array, just clean it
		if ( is_array( $tags ) ) {
			$tags = array_map( 'trim', $tags );
			$tags = array_filter( $tags ); // Remove empty values
			return $tags;
		}

		// If string, split by comma
		if ( is_string( $tags ) && ! empty( $tags ) ) {
			$tags = explode( ',', $tags );
			$tags = array_map( 'trim', $tags );
			$tags = array_filter( $tags ); // Remove empty values
			return $tags;
		}

		return [];
	}

	/**
	 * Queue tag addition for user
	 *
	 * @param int    $user_id    User ID
	 * @param string $contact_id GHL Contact ID
	 * @param array  $tags       Tags to add
	 * @return void
	 */
	private function queue_tag_addition( int $user_id, string $contact_id, array $tags ): void {
		try {
			$this->queue_manager->add_to_queue(
				'user',
				$user_id,
				'add_tags',
				[
					'contact_id' => $contact_id,
					'tags'       => $tags,
					'source'     => 'role_change',
				]
			);
		} catch ( \Exception $e ) {
			do_action( 'syncly_log_event', 'queue_tag_addition_failed', $e->getMessage(), [ 'user_id' => $user_id ], 'error' );
		}
	}

	/**
	 * Queue tag removal for user
	 *
	 * @param int    $user_id    User ID
	 * @param string $contact_id GHL Contact ID
	 * @param array  $tags       Tags to remove
	 * @return void
	 */
	private function queue_tag_removal( int $user_id, string $contact_id, array $tags ): void {
		try {
			$this->queue_manager->add_to_queue(
				'user',
				$user_id,
				'remove_tags',
				[
					'contact_id' => $contact_id,
					'tags'       => $tags,
					'source'     => 'role_change',
				]
			);
		} catch ( \Exception $e ) {
			do_action( 'syncly_log_event', 'queue_tag_removal_failed', $e->getMessage(), [ 'user_id' => $user_id ], 'error' );
		}
	}

	/**
	 * AJAX: Bulk add tags to all users with a role
	 *
	 * @return void
	 */
	public function ajax_bulk_add_role_tags(): void {
		try {
			// Verify nonce
			check_ajax_referer( 'syncly_settings_nonce', 'nonce' );

			// Check permissions
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'syncly' ) ] );
				return;
			}

			$role        = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
			$tags_string = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';

			if ( empty( $role ) || empty( $tags_string ) ) {
				wp_send_json_error( [ 'message' => __( 'Role and tags are required', 'syncly' ) ] );
				return;
			}

			$tags = $this->parse_tags( $tags_string );

			if ( empty( $tags ) ) {
				wp_send_json_error( [ 'message' => __( 'No valid tags provided', 'syncly' ) ] );
				return;
			}

			// Get all users with this role
			$users = get_users( [ 'role' => $role ] );

			if ( empty( $users ) ) {
				wp_send_json_error( [ 'message' => __( 'No users found with this role', 'syncly' ) ] );
				return;
			}

			$queued = 0;
			$errors = [];

			foreach ( $users as $user ) {
				try {
					$contact_id = get_user_meta( $user->ID, $this->contact_meta_key, true );
					if ( ! empty( $contact_id ) ) {
						$this->queue_tag_addition( $user->ID, $contact_id, $tags );
						++$queued;
					}
				} catch ( \Exception $e ) {
					$errors[] = sprintf( 'User %d: %s', $user->ID, $e->getMessage() );

				}
			}

			wp_send_json_success(
				[
					'message' => sprintf(
						/* translators: %d: Number of users queued */
						__( '%d users queued for tag addition.', 'syncly' ),
						$queued
					),
					'queued'  => $queued,
					'total'   => count( $users ),
					'errors'  => $errors,
				]
			);
		} catch ( \Exception $e ) {

			wp_send_json_error(
				[
					'message'       => sprintf(
						/* translators: %s: Error message */
						__( 'Error: %s', 'syncly' ),
						$e->getMessage()
					),
					'error_details' => $e->getMessage(),
					'error_trace'   => $e->getTraceAsString(),
				]
			);
		} catch ( \Error $e ) {

			wp_send_json_error(
				[
					'message'       => sprintf(
						/* translators: %s: Error message */
						__( 'Error: %s', 'syncly' ),
						$e->getMessage()
					),
					'error_details' => $e->getMessage(),
					'error_trace'   => $e->getTraceAsString(),
				]
			);
		}
	}

	/**
	 * AJAX: Bulk remove tags from all users with a role
	 *
	 * @return void
	 */
	public function ajax_bulk_remove_role_tags(): void {
		try {
			// Verify nonce
			check_ajax_referer( 'syncly_settings_nonce', 'nonce' );

			// Check permissions
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Permission denied', 'syncly' ) ] );
				return;
			}

			$role        = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
			$tags_string = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';

			if ( empty( $role ) || empty( $tags_string ) ) {
				wp_send_json_error( [ 'message' => __( 'Role and tags are required', 'syncly' ) ] );
				return;
			}

			$tags = $this->parse_tags( $tags_string );

			if ( empty( $tags ) ) {
				wp_send_json_error( [ 'message' => __( 'No valid tags provided', 'syncly' ) ] );
				return;
			}

			// Get all users with this role
			$users = get_users( [ 'role' => $role ] );

			if ( empty( $users ) ) {
				wp_send_json_error( [ 'message' => __( 'No users found with this role', 'syncly' ) ] );
				return;
			}

			$queued = 0;
			$errors = [];

			foreach ( $users as $user ) {
				try {
					$contact_id = get_user_meta( $user->ID, $this->contact_meta_key, true );
					if ( ! empty( $contact_id ) ) {
						$this->queue_tag_removal( $user->ID, $contact_id, $tags );
						++$queued;
					}
				} catch ( \Exception $e ) {
					$errors[] = sprintf( 'User %d: %s', $user->ID, $e->getMessage() );

				}
			}

			wp_send_json_success(
				[
					'message' => sprintf(
						/* translators: %d: Number of users queued */
						__( '%d users queued for tag removal.', 'syncly' ),
						$queued
					),
					'queued'  => $queued,
					'total'   => count( $users ),
					'errors'  => $errors,
				]
			);
		} catch ( \Exception $e ) {

			wp_send_json_error(
				[
					'message'       => sprintf(
						/* translators: %s: Error message */
						__( 'Error: %s', 'syncly' ),
						$e->getMessage()
					),
					'error_details' => $e->getMessage(),
					'error_trace'   => $e->getTraceAsString(),
				]
			);
		}
	}

	/**
	 * Initialize hooks (called by Loader)
	 *
	 * @return void
	 */
	/**
	 * Claim tags that the user's current roles provide.
	 *
	 * Hooked to `syncly_tags_protected_from_removal` — prevents other sources
	 * from removing tags that belong to the user's active WordPress role(s).
	 *
	 * @since 1.1.3
	 * @param array  $protected Tags already protected by other sources.
	 * @param int    $user_id   WordPress user ID.
	 * @param string $source    Source requesting removal.
	 * @param int    $source_id Source-specific ID.
	 * @return array Merged protected tags.
	 */
	public function protect_role_tags( array $protected, int $user_id, string $source, int $source_id ): array {
		$role_tags = $this->get_user_role_tags( $user_id );

		if ( ! empty( $role_tags ) ) {
			$protected = array_merge( $protected, $role_tags );
		}

		return $protected;
	}

	public static function init(): void {
		self::get_instance();
	}
}
