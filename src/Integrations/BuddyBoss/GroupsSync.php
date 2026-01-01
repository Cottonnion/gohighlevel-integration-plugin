<?php
declare(strict_types=1);

namespace GHL_CRM\Integrations\BuddyBoss;

use GHL_CRM\API\Resources\CustomObjectResource;
use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Sync\QueueManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BuddyBoss Groups Integration
 *
 * Automatically syncs BuddyBoss Groups to GoHighLevel Custom Objects
 * Creates Custom Objects per group type and links members via GoHighLevel associations
 *
 * @package    GHL_CRM_Integration
 * @subpackage Integrations/BuddyBoss
 */
class GroupsSync {
	/**
	 * Custom Object Resource
	 *
	 * @var CustomObjectResource
	 */
	private CustomObjectResource $custom_object_resource;

	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Queue Manager
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Group type to Custom Object mappings
	 *
	 * @var array
	 */
	private array $group_type_mappings = [
		'community' => 'Communities',
		'school'    => 'Schools',
		'classroom' => 'Classrooms',
		'cohort'    => 'Cohorts',
	];

	/**
	 * Meta key used to avoid queuing duplicate contact creations within a short window.
	 */
	private const CONTACT_SYNC_PENDING_META_KEY = '_ghl_contact_sync_pending';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->custom_object_resource = new CustomObjectResource();
		$this->settings_manager       = SettingsManager::get_instance();
		$this->queue_manager          = QueueManager::get_instance();

		// Defer initialization until BuddyBoss is loaded
		// BuddyBoss fires bp_init after it's fully loaded
		add_action( 'bp_init', [ $this, 'init_hooks' ], 10 );
	}

	/**
	 * Initialize WordPress hooks
	 */
	public function init_hooks(): void {
		// Only initialize if BuddyBoss is active and integration is enabled
		if ( ! $this->is_buddyboss_active() || ! $this->is_integration_enabled() ) {
			return;
		}

		// Group hooks
		add_action( 'groups_group_after_save', [ $this, 'handle_group_save' ], 10, 1 );
		add_action( 'groups_before_delete_group', [ $this, 'handle_group_delete' ], 10, 1 );

		// Group membership hooks
		add_action( 'groups_join_group', [ $this, 'handle_user_join_group' ], 10, 2 );
		add_action( 'groups_leave_group', [ $this, 'handle_user_leave_group' ], 10, 2 );
		add_action( 'groups_remove_member', [ $this, 'handle_user_leave_group' ], 10, 2 );
		add_action( 'groups_ban_member', [ $this, 'handle_user_leave_group' ], 10, 2 );
		add_action( 'groups_unban_member', [ $this, 'handle_user_join_group' ], 10, 2 );

		// Group type assignment hooks
		add_action( 'bp_groups_set_group_type', [ $this, 'handle_group_type_assignment' ], 10, 3 );

		// Group type hooks (using WordPress post hooks since group types are custom post types)
		add_action( 'save_post', [ $this, 'handle_group_type_save' ], 10, 2 );
		add_action( 'before_delete_post', [ $this, 'handle_group_type_delete' ], 10, 2 );

		// Bulk sync action
		add_action( 'wp_ajax_ghl_buddyboss_bulk_sync', [ $this, 'handle_bulk_sync' ] );

		// Queue processor filters
		add_filter( 'ghl_crm_execute_sync', [ $this, 'execute_buddyboss_sync' ], 10, 5 );
	}

	/**
	 * Check if BuddyBoss is active
	 */
	private function is_buddyboss_active(): bool {
		return function_exists( 'bp_is_active' ) && bp_is_active( 'groups' );
	}

	/**
	 * Check if BuddyBoss integration is enabled
	 */
	private function is_integration_enabled(): bool {
		$settings = $this->settings_manager->get_settings_array();
		return ! empty( $settings['buddyboss_groups_enabled'] );
	}

	/**
	 * Check if a post is a group type post
	 *
	 * @param \WP_Post $post The post object
	 * @return bool True if this is a group type post
	 */
	private function is_group_type_post( \WP_Post $post ): bool {
		// Get the group type post type name
		if ( ! function_exists( 'bp_get_group_type_post_type' ) ) {
			return false;
		}

		$group_type_post_type = bp_get_group_type_post_type();
		return $post->post_type === $group_type_post_type;
	}

	/**
	 * Handle group save (create/update)
	 *
	 * @param \BP_Groups_Group $group The group object
	 */
	public function handle_group_save( $group ): void {
		// Check if group has a group type assigned
		$group_types = bp_groups_get_group_type( $group->id, false );
		$settings    = $this->settings_manager->get_settings_array();
		$fallback    = ! empty( $settings['buddyboss_default_group_type'] ) ? sanitize_key( $settings['buddyboss_default_group_type'] ) : '';

		// Skip groups without a group type
		if ( empty( $group_types ) ) {
			if ( empty( $fallback ) ) {
				return;
			}

			$group_types = [ $fallback ];
		}

		// Queue group sync
		$this->queue_manager->add_to_queue(
			'buddyboss_group',
			$group->id,
			'sync_group',
			[
				'group_name' => $group->name,
				'group_id'   => $group->id,
			]
		);
	}

	/**
	 * Handle group deletion
	 *
	 * @param int $group_id The group ID
	 */
	public function handle_group_delete( int $group_id ): void {
		// Queue group deletion sync
		$this->queue_manager->add_to_queue(
			'buddyboss_group',
			$group_id,
			'delete_group',
			[
				'group_id' => $group_id,
			]
		);
	}

	/**
	 * Handle user joining a group
	 *
	 * @param int $group_id The group ID
	 * @param int $user_id  The user ID
	 */
	public function handle_user_join_group( int $group_id, int $user_id ): void {
		$this->queue_member_association_for_user( $user_id, $group_id, 'create_association' );
	}

	/**
	 * Handle user leaving a group
	 *
	 * @param int $group_id The group ID
	 * @param int $user_id  The user ID
	 */
	public function handle_user_leave_group( int $group_id, int $user_id ): void {
		$this->queue_member_association_for_user( $user_id, $group_id, 'delete_association' );
	}

	/**
	 * Handle group type assignment events so new types sync immediately.
	 *
	 * @param int          $group_id   The group ID.
	 * @param string|array $group_type Group type slug or array of slugs.
	 * @param bool         $append     Whether additional types were appended.
	 */
	public function handle_group_type_assignment( int $group_id, $group_type, bool $append = false ): void {
		if ( $group_id <= 0 ) {
			return;
		}

		$raw_types       = is_array( $group_type ) ? $group_type : [ $group_type ];
		$sanitized_types = array_filter( array_map( 'sanitize_key', $raw_types ) );

		if ( empty( $sanitized_types ) ) {
			return;
		}

		$primary_group_type = reset( $sanitized_types );
		$group              = groups_get_group( $group_id );

		if ( ! $group ) {
			return;
		}

		$this->queue_manager->add_to_queue(
			'buddyboss_group',
			$group_id,
			'sync_group',
			[
				'group_name' => $group->name,
				'group_id'   => $group_id,
				'group_type' => $primary_group_type,
			]
		);
	}

	/**
	 * Handle group type save - auto-create Custom Object
	 *
	 * @param int      $post_id The post ID
	 * @param \WP_Post $post    The post object
	 */
	public function handle_group_type_save( int $post_id, \WP_Post $post ): void {
		// Check if this is a group type post
		if ( ! $this->is_group_type_post( $post ) ) {

			return;
		}

		// Skip if this is an autosave, revision, or trashed post
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || $post->post_status === 'trash' ) {

			return;
		}

		// Only proceed if post is published
		if ( $post->post_status !== 'publish' ) {

			return;
		}

		// Use post slug as group type identifier
		$group_type = $post->post_name;

		// Queue Custom Object creation
		$this->queue_manager->add_to_queue(
			'buddyboss_group_type',
			$post_id,
			'create_custom_object',
			[
				'group_type' => $group_type,
				'post_id'    => $post_id,
			]
		);
	}

	/**
	 * Handle group type deletion
	 *
	 * @param int      $post_id The post ID
	 * @param \WP_Post $post    The post object
	 */
	public function handle_group_type_delete( int $post_id, \WP_Post $post ): void {
		// Check if this is a group type post
		if ( ! $this->is_group_type_post( $post ) ) {
			return;
		}

		$settings = $this->settings_manager->get_settings_array();

		// Check if auto-deletion is enabled
		if ( ! empty( $settings['buddyboss_auto_delete_custom_objects'] ) ) {
			// Use post slug as group type identifier
			$group_type = $post->post_name;

			$this->queue_manager->add_to_queue(
				'buddyboss_group_type',
				$post_id,
				'delete_custom_object',
				[
					'group_type' => $group_type,
					'post_id'    => $post_id,
				]
			);
		}
	}

	/**
	 * Queue member association for a single user
	 *
	 * @param int    $user_id  The user ID
	 * @param int    $group_id The group ID
	 * @param string $action   The action (create_association, delete_association)
	 */
	private function queue_member_association_for_user( int $user_id, int $group_id, string $action ): void {
		// Get group's custom object record ID and association ID
		$record_id      = groups_get_groupmeta( $group_id, 'ghl_custom_object_record_id', true );
		$association_id = groups_get_groupmeta( $group_id, 'ghl_association_id', true );

		if ( empty( $record_id ) || empty( $association_id ) ) {
			return;
		}

		$this->queue_manager->add_to_queue(
			'buddyboss_member_association',
			$user_id,
			$action,
			[
				'user_id'        => $user_id,
				'group_id'       => $group_id,
				'record_id'      => $record_id,
				'association_id' => $association_id,
			]
		);
	}

	/**
	 * Sync group to Custom Object
	 *
	 * @param int $group_id The group ID
	 * @return array Result of sync operation
	 */
	public function sync_group_to_custom_object( int $group_id ): array {
		$group = groups_get_group( $group_id );
		if ( ! $group ) {
			return [
				'success' => false,
				'error'   => 'Group not found',
			];
		}

		// Get group type
		$group_types = bp_groups_get_group_type( $group_id, false );
		$group_type  = ! empty( $group_types ) ? $group_types[0] : 'community';

		// Get or create Custom Object for this group type
		$custom_object_name = $this->group_type_mappings[ $group_type ] ?? 'Communities';
		$custom_object_id   = $this->get_or_create_custom_object( $custom_object_name, $group_type );

		if ( ! $custom_object_id ) {
			return [
				'success' => false,
				'error'   => 'Failed to create/get Custom Object',
			];
		}

		// Prepare group data
		$location_id = $this->settings_manager->get_setting( 'location_id' );

		// Build the record data - wrap properties in a "properties" object
		$group_data = [
			'locationId' => $location_id,
			'properties' => [
				'name' => $group->name,
			],
		];

		// Sync to GHL Custom Object - use schema ID in the endpoint
		try {
			// Check if record already exists
			$existing_record_id = groups_get_groupmeta( $group_id, 'ghl_custom_object_record_id', true );

			if ( $existing_record_id ) {
				$record_id = $existing_record_id;
			} else {
				// Create new record
				$result = $this->custom_object_resource->create_record( $custom_object_id, $group_data );

				// Store GHL record ID for future updates
				$record_id = $result['id'] ?? null;
				if ( ! $record_id ) {
					throw new \Exception( 'No record ID returned from create_record' );
				}

				groups_update_groupmeta( $group_id, 'ghl_custom_object_record_id', $record_id );
			}

			groups_update_groupmeta( $group_id, 'ghl_custom_object_id', $custom_object_id );

			// Store object slug for URL building (convert name to lowercase slug)
			$object_slug = strtolower( str_replace( ' ', '_', $custom_object_name ) );
			groups_update_groupmeta( $group_id, 'ghl_custom_object_slug', $object_slug );

			// Get or create association definition
			$schema_key     = 'custom_objects.' . $object_slug;
			$association_id = $this->get_or_create_association( $schema_key, $custom_object_name, $group_type );

			if ( $association_id ) {
				// Store association ID for later use
				groups_update_groupmeta( $group_id, 'ghl_association_id', $association_id );

				// Queue association creation for all group members
				$this->queue_member_associations( $group_id, $record_id, $association_id );
			}

			return [
				'success'          => true,
				'message'          => 'Group synced successfully',
				'custom_object_id' => $custom_object_id,
				'record_id'        => $record_id,
				'association_id'   => $association_id,
			];
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Get or create Custom Object for group type
	 *
	 * @param string $object_name The Custom Object name
	 * @param string $group_type  The group type
	 * @return string|null Custom Object ID or null on failure
	 */
	private function get_or_create_custom_object( string $object_name, string $group_type ): ?string {
		// Check if Custom Object already exists
		try {
			$existing_objects = $this->custom_object_resource->get_schemas();

			foreach ( $existing_objects as $object ) {
				// GHL Custom Objects use labels.plural for the display name
				$obj_name = $object['labels']['plural'] ?? $object['name'] ?? '';
				$obj_id   = $object['id'] ?? '';

				if ( $obj_name === $object_name ) {

					return $obj_id;
				}
			}
		} catch ( \Exception $e ) {
			return null;
		}

		// Create new Custom Object
		$object_data = [
			'name'        => $object_name,
			'description' => sprintf( 'BuddyBoss %s groups', ucfirst( $group_type ) ),
			'properties'  => [
				'name'         => [
					'type'        => 'text',
					'label'       => 'Group Name',
					'required'    => true,
					'description' => 'The name of the group',
				],
				'description'  => [
					'type'        => 'textarea',
					'label'       => 'Description',
					'required'    => false,
					'description' => 'Group description',
				],
				'status'       => [
					'type'        => 'text',
					'label'       => 'Status',
					'required'    => false,
					'description' => 'Group status (public, private, hidden)',
				],
				'slug'         => [
					'type'        => 'text',
					'label'       => 'Slug',
					'required'    => false,
					'description' => 'Group URL slug',
				],
				'created'      => [
					'type'        => 'date',
					'label'       => 'Created Date',
					'required'    => false,
					'description' => 'When the group was created',
				],
				'member_count' => [
					'type'        => 'number',
					'label'       => 'Member Count',
					'required'    => false,
					'description' => 'Total number of members',
				],
				'group_type'   => [
					'type'        => 'text',
					'label'       => 'Group Type',
					'required'    => false,
					'description' => 'BuddyBoss group type',
				],
				'wordpress_id' => [
					'type'        => 'number',
					'label'       => 'WordPress ID',
					'required'    => true,
					'description' => 'WordPress group ID',
				],
			],
		];

		// Try to create the Custom Object schema via API
		try {
			// Get location ID from settings
			$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
			$location_id      = $settings_manager->get_setting( 'location_id', '' );

			if ( empty( $location_id ) ) {
				throw new \Exception( 'Location ID not configured in plugin settings' );
			}

			// Generate key from object name (lowercase with underscores)
			$base_key = strtolower( str_replace( ' ', '_', $object_name ) );

			// GHL requires custom objects to have 'custom_objects.' prefix in the key
			$object_key = "custom_objects.{$base_key}";

			// The primary display property key also needs the full custom_objects prefix
			$primary_property_key = "{$object_key}.name";

			// Prepare schema data according to GHL API format
			// Based on example: custom_objects.pet with primaryDisplayPropertyDetails.key = custom_objects.pet.name
			$schema_data = [
				'key'                           => $object_key,
				'locationId'                    => $location_id,
				'labels'                        => [
					'singular' => rtrim( $object_name, 's' ), // e.g., "School"
					'plural'   => $object_name,                // e.g., "Schools"
				],
				'description'                   => sprintf( 'BuddyBoss %s groups', ucfirst( $group_type ) ),
				'primaryDisplayPropertyDetails' => [
					'key'      => $primary_property_key,  // e.g., "custom_objects.schools.name"
					'name'     => 'Group Name',            // Display label for the property
					'dataType' => 'TEXT',                  // Must be uppercase: TEXT, NUMBER, DATE, etc.
				],
			];

			$created_schema = $this->custom_object_resource->create_schema( $schema_data );

			if ( ! empty( $created_schema['id'] ) ) {
				return $created_schema['id'];
			}

			return null;

		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get or create association definition between Custom Object and Contacts
	 *
	 * @param string $schema_key Custom object schema key (e.g., 'custom_objects.schools')
	 * @param string $object_name The Custom Object name (e.g., 'Schools')
	 * @param string $group_type The group type (e.g., 'school')
	 * @return string|null Association ID or null on failure
	 */
	private function get_or_create_association( string $schema_key, string $object_name, string $group_type ): ?string {
		try {
			// Get existing associations
			$associations = $this->custom_object_resource->get_associations();

			// Look for existing association between this custom object and contacts
			foreach ( $associations as $association ) {
				$first_key  = $association['firstObjectKey'] ?? '';
				$second_key = $association['secondObjectKey'] ?? '';

				// Check if this association matches our custom object <-> contact relationship
				if ( ( $first_key === $schema_key && $second_key === 'contact' ) ||
					( $first_key === 'contact' && $second_key === $schema_key )
				) {
					$assoc_id = $association['id'] ?? '';
					return $assoc_id;
				}
			}

			// Association doesn't exist, create it
			// Generate labels based on group type
			// e.g., "School" -> "Member", "Members"
			$singular_label = 'Member';
			$plural_label   = 'Members';

			// Create the association
			// ONE_TO_MANY: One custom object record (e.g., School) can have many contacts (Members)
			$created_association = $this->custom_object_resource->create_association(
				$schema_key,
				$singular_label,
				$plural_label,
				'ONE_TO_MANY'
			);

			if ( ! empty( $created_association['id'] ) ) {
				return $created_association['id'];
			}

			return null;

		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Handle bulk sync AJAX request
	 */
	public function handle_bulk_sync(): void {
		// Verify nonce and capabilities
		check_ajax_referer( 'ghl_buddyboss_bulk_sync', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'ghl-crm-integration' ) );
		}

		$sync_type = sanitize_text_field( $_POST['sync_type'] ?? '' );

		switch ( $sync_type ) {
			case 'all_groups':
				$result = $this->bulk_sync_all_groups();
				break;
			case 'group_types':
				$result = $this->bulk_sync_group_types();
				break;
			default:
				$result = [
					'success' => false,
					'message' => 'Invalid sync type',
				];
		}

		wp_send_json( $result );
	}

	/**
	 * Execute BuddyBoss sync tasks from queue
	 *
	 * @param mixed  $result     Previous filter result
	 * @param string $item_type  Item type
	 * @param string $action     Action
	 * @param int    $item_id    Item ID
	 * @param array  $payload    Payload data
	 * @return mixed Result of sync operation or previous filter result
	 */
	public function execute_buddyboss_sync( $result, string $item_type, string $action, int $item_id, array $payload ) {
		// Only handle our task types
		if ( ! in_array( $item_type, [ 'buddyboss_group', 'buddyboss_member_association', 'buddyboss_group_type' ], true ) ) {
			return $result;
		}

		switch ( $item_type ) {
			case 'buddyboss_group':
				return $this->execute_group_sync_task( $action, $item_id, $payload );
			case 'buddyboss_member_association':
				return $this->execute_member_association_task( $action, $item_id, $payload );
			case 'buddyboss_group_type':
				return $this->execute_group_type_task( $action, $item_id, $payload );
			default:
				return $result;
		}
	}

	/**
	 * Execute group sync task
	 *
	 * @param string $action  The action to execute
	 * @param int    $item_id The item ID (group ID)
	 * @param array  $payload Task payload
	 * @return array Result of sync operation
	 */
	private function execute_group_sync_task( string $action, int $item_id, array $payload ): array {
		$group_id = $item_id;

		// Quick skip: if group already has a GHL record, mark task as successful and skip work
		$existing_record_id = groups_get_groupmeta( $group_id, 'ghl_custom_object_record_id', true );
		if ( $action === 'sync_group' && ! empty( $existing_record_id ) ) {
			return [
				'success'   => true,
				'message'   => 'Skipped sync - record already exists',
				'record_id' => $existing_record_id,
			];
		}

		switch ( $action ) {
			case 'sync_group':
				return $this->sync_group_to_custom_object( $group_id );
			case 'delete_group':
				return $this->delete_group_from_custom_object( $group_id );
			default:
				return [
					'success' => false,
					'error'   => 'Unknown group sync action',
				];
		}
	}

	/**
	 * Execute member association task
	 *
	 * @param string $action  The action to execute
	 * @param int    $item_id The item ID (user ID)
	 * @param array  $payload Task payload
	 * @return array Result of sync operation
	 */
	private function execute_member_association_task( string $action, int $item_id, array $payload ): array {
		$user_id        = $item_id;
		$group_id       = absint( $payload['group_id'] ?? 0 );
		$record_id      = $payload['record_id'] ?? '';
		$association_id = $payload['association_id'] ?? '';

		if ( empty( $record_id ) || empty( $association_id ) ) {
			return [
				'success' => false,
				'error'   => 'Missing record_id or association_id',
			];
		}

		switch ( $action ) {
			case 'create_association':
				$result = $this->create_member_association( $user_id, $group_id, $record_id, $association_id );

				// If invalid record id returned from GHL, log and return a clear error so operator can recreate record
				if ( empty( $result['success'] ) && ! empty( $result['error'] ) && strpos( $result['error'], 'Invalid record id' ) !== false ) {
					// Return failure but include an 'abort' flag for visibility
					return [
						'success' => false,
						'error'   => $result['error'],
						'abort'   => true,
					];
				}

				return $result;
			case 'delete_association':
				return $this->delete_member_association( $user_id, $group_id, $record_id, $association_id );
			default:
				return [
					'success' => false,
					'error'   => 'Unknown association action',
				];
		}
	}

	/**
	 * Create association between a member and a custom object record
	 *
	 * @param int    $user_id        The user ID
	 * @param int    $group_id       The group ID
	 * @param string $record_id      The custom object record ID
	 * @param string $association_id The association definition ID
	 * @return array Result of association creation
	 */
	private function create_member_association( int $user_id, int $group_id, string $record_id, string $association_id ): array {
		// Get user's GHL contact ID
		$contact_id = get_user_meta( $user_id, '_ghl_contact_id', true );
		$settings   = $this->settings_manager->get_settings_array();
		$strategy   = $settings['buddyboss_missing_contact_strategy'] ?? 'skip';

		if ( ! $contact_id ) {
			if ( 'create' === $strategy ) {
				// Attempt to create contact automatically
				$pending_flag = get_user_meta( $user_id, self::CONTACT_SYNC_PENDING_META_KEY, true );

				if ( ! empty( $pending_flag ) && ( time() - (int) $pending_flag < 300 ) ) {
					return [
						'success' => false,
						'error'   => 'Contact creation already pending - will retry',
					];
				}

				// Queue contact creation
				$user = get_userdata( $user_id );
				if ( ! $user ) {

					return [
						'success' => false,
						'error'   => 'User not found',
					];
				}

				// Mark as pending to avoid duplicate queue
				update_user_meta( $user_id, self::CONTACT_SYNC_PENDING_META_KEY, time() );

				// Prepare contact data
				$contact_data = $this->prepare_contact_data_for_user( $user );

				$queue_manager    = \GHL_CRM\Sync\QueueManager::get_instance();
				$contact_queue_id = $queue_manager->add_to_queue( 'user', $user_id, 'profile_update', $contact_data );

				// Build the updated payload with dependency, preserving all original data
				$updated_payload = [
					'user_id'              => $user_id,
					'group_id'             => $group_id,
					'record_id'            => $record_id,
					'association_id'       => $association_id,
					'_depends_on_queue_id' => $contact_queue_id,
				];

				return [
					'success'        => false,
					'error'          => 'Contact creation queued - will retry association after contact is created',
					'skip'           => true, // Don't increment retry counter
					'update_payload' => $updated_payload, // Signal to update the queue item payload
				];
			}

			// Skip strategy
			return [
				'success' => false,
				'error'   => 'User not synced to GoHighLevel - please sync user first',
			];
		}

		// Clear pending flag if contact now exists
		delete_user_meta( $user_id, self::CONTACT_SYNC_PENDING_META_KEY );

			// --- New: log and validate group/schema/record/association details ---
			$stored_schema_id = groups_get_groupmeta( $group_id, 'ghl_custom_object_id', true );

			// Try to fetch schema info (if we have an ID) to log the schema key/name
			$schema_info = null;
		if ( ! empty( $stored_schema_id ) ) {
			$schema_info = $this->custom_object_resource->get_schema( $stored_schema_id );

		}

			// Verify that the record exists under the stored schema (if available)
		if ( ! empty( $stored_schema_id ) ) {
			$record_check = $this->custom_object_resource->get_record( $record_id, $stored_schema_id );

			if ( empty( $record_check ) ) {

				return [
					'success' => false,
					'error'   => sprintf( 'Record %s not found under schema %s', $record_id, $stored_schema_id ),
					'abort'   => true,
				];
			}
		}

			// Fetch association definition and log it
			$assoc_def = null;
		try {
			$associations = $this->custom_object_resource->get_associations();
			foreach ( $associations as $a ) {
				if ( isset( $a['id'] ) && $a['id'] === $association_id ) {
					$assoc_def = $a;
					break;
				}
			}
		} catch ( \Exception $e ) {

		}

			// Determine correct direction based on association definition
			$direction = 'first'; // default: custom_object first, contact second
		if ( ! empty( $assoc_def ) ) {
			$first_key = $assoc_def['firstObjectKey'] ?? '';
			// If association puts contact as the first object, switch direction
			if ( $first_key === 'contact' ) {
				$direction = 'second';
			}

		}
			// --- End new validation/logging ---

			// Get group type to determine schema key (used for logging/fallback)
			$group_types = bp_groups_get_group_type( $group_id, false );
			$group_type  = ! empty( $group_types ) ? $group_types[0] : 'community';

			$custom_object_name = $this->group_type_mappings[ $group_type ] ?? 'Communities';
			$schema_key         = 'custom_objects.' . strtolower( str_replace( ' ', '_', $custom_object_name ) );

		try {
			// Create the association relation
			// Direction: determined above
			$result = $this->custom_object_resource->associate_with_contact(
				$record_id,
				$contact_id,
				$schema_key,
				$association_id,
				$direction
			);

			return [
				'success' => true,
				'result'  => $result,
			];
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * @param int    $group_id       The group ID
	 * @param string $record_id      The custom object record ID
	 * @param string $association_id The association definition ID
	 * @return array Result of association deletion
	 */
	private function delete_member_association( int $user_id, int $group_id, string $record_id, string $association_id ): array {
		// Validate inputs
		if ( empty( $record_id ) ) {
			$error_msg = sprintf( 'Empty record_id provided for user %d, group %d', $user_id, $group_id );
			return [
				'success' => false,
				'error'   => $error_msg,
			];
		}

		if ( empty( $association_id ) ) {
			$error_msg = sprintf( 'Empty association_id provided for user %d, group %d', $user_id, $group_id );
			return [
				'success' => false,
				'error'   => $error_msg,
			];
		}

		// Get user's GHL contact ID
		$contact_id = get_user_meta( $user_id, '_ghl_contact_id', true );

		if ( ! $contact_id ) {
			$error_msg = sprintf( 'User %d has no GHL contact ID (_ghl_contact_id meta is empty)', $user_id );
			return [
				'success' => false,
				'error'   => 'User not synced to GoHighLevel - ' . $error_msg,
			];
		}

		// Get group type to determine schema key
		$group_types = bp_groups_get_group_type( $group_id, false );
		$group_type  = ! empty( $group_types ) ? $group_types[0] : 'community';

		$custom_object_name = $this->group_type_mappings[ $group_type ] ?? 'Communities';
		$schema_key         = 'custom_objects.' . strtolower( str_replace( ' ', '_', $custom_object_name ) );

		// Fetch association definition to determine direction
		$direction = 'first';
		try {
			$associations = $this->custom_object_resource->get_associations();

			foreach ( $associations as $a ) {
				if ( isset( $a['id'] ) && $a['id'] === $association_id ) {
					$first_key = $a['firstObjectKey'] ?? '';
					if ( $first_key === 'contact' ) {
						$direction = 'second';
					}
					break;
				}
			}
		} catch ( \Throwable $e ) {
			// Continue with default direction
		}

		try {
			// Delete the association
			$result = $this->custom_object_resource->disassociate_from_contact(
				$record_id,
				$contact_id,
				$schema_key,
				$association_id,
				$direction
			);

			return [
				'success' => true,
				'result'  => $result,
			];
		} catch ( \Throwable $e ) {
			$error_msg = sprintf(
				'Failed to delete association for user %d (contact %s) from record %s - Error: %s, Code: %s, File: %s:%d',
				$user_id,
				$contact_id,
				$record_id,
				$e->getMessage(),
				$e->getCode(),
				$e->getFile(),
				$e->getLine()
			);

			return [
				'success' => false,
				'error'   => $e->getMessage(),
				'details' => $error_msg,
			];
		}
	}

	/**
	 * Execute group type task
	 *
	 * @param string $action  The action to execute
	 * @param int    $item_id The item ID (post ID)
	 * @param array  $payload Task payload
	 * @return array Result of sync operation
	 */
	private function execute_group_type_task( string $action, int $item_id, array $payload ): array {
		$group_type = sanitize_text_field( $payload['group_type'] ?? '' );

		switch ( $action ) {
			case 'create_custom_object':
				$custom_object_name = $this->group_type_mappings[ $group_type ] ?? ucfirst( $group_type ) . 's';
				$object_id          = $this->get_or_create_custom_object( $custom_object_name, $group_type );

				return [
					'success'          => ! empty( $object_id ),
					'custom_object_id' => $object_id,
					'group_type'       => $group_type,
				];
			case 'delete_custom_object':
				return $this->delete_custom_object_for_group_type( $group_type );
			default:
				return [
					'success' => false,
					'error'   => 'Unknown group type sync action',
				];
		}
	}

	/**
	 * Delete group from Custom Object
	 *
	 * @param int $group_id The group ID
	 * @return array Result of deletion
	 */
	private function delete_group_from_custom_object( int $group_id ): array {
		// Get stored GHL record info
		$custom_object_id = groups_get_groupmeta( $group_id, 'ghl_custom_object_id', true );
		$record_id        = groups_get_groupmeta( $group_id, 'ghl_custom_object_record_id', true );

		if ( ! $custom_object_id || ! $record_id ) {
			return [
				'success' => false,
				'error'   => 'No GHL record found for group',
			];
		}

		try {
			$this->custom_object_resource->delete_record( $custom_object_id, $record_id );

			// Clean up meta
			groups_delete_groupmeta( $group_id, 'ghl_custom_object_id' );
			groups_delete_groupmeta( $group_id, 'ghl_custom_object_record_id' );

			return [
				'success'          => true,
				'custom_object_id' => $custom_object_id,
				'record_id'        => $record_id,
			];
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Delete Custom Object for group type
	 *
	 * @param string $group_type The group type
	 * @return array Result of deletion
	 */
	private function delete_custom_object_for_group_type( string $group_type ): array {
		$custom_object_name = $this->group_type_mappings[ $group_type ] ?? ucfirst( $group_type ) . 's';

		// Find the Custom Object
		$existing_objects = $this->custom_object_resource->get_schemas();
		$object_id        = null;

		foreach ( $existing_objects as $object ) {
			if ( $object['name'] === $custom_object_name ) {
				$object_id = $object['id'];
				break;
			}
		}

		if ( ! $object_id ) {
			return [
				'success' => false,
				'error'   => 'Custom Object not found',
			];
		}

		try {
			$this->custom_object_resource->delete_schema( $object_id );

			return [
				'success'            => true,
				'custom_object_id'   => $object_id,
				'custom_object_name' => $custom_object_name,
			];
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Bulk sync all groups
	 *
	 * @return array Result of bulk sync
	 */
	private function bulk_sync_all_groups(): array {
		$groups = groups_get_groups(
			[
				'type'     => 'alphabetical',
				'per_page' => -1,
			]
		);

		$queued = 0;
		foreach ( $groups['groups'] as $group ) {
			$this->queue_manager->add_to_queue(
				'buddyboss_group',
				$group->id,
				'sync_group',
				[
					'group_id'   => $group->id,
					'group_name' => $group->name,
				]
			);
			++$queued;
		}

		return [
			'success' => true,
			'message' => sprintf( 'Queued %d groups for sync', $queued ),
		];
	}

	/**
	 * Bulk sync group types (create Custom Objects)
	 *
	 * @return array Result of bulk sync
	 */
	private function bulk_sync_group_types(): array {
		$group_types = bp_groups_get_group_types();
		$created     = 0;

		foreach ( $group_types as $type_key => $type_name ) {
			$custom_object_name = $this->group_type_mappings[ $type_key ] ?? ucfirst( $type_key ) . 's';
			$object_id          = $this->get_or_create_custom_object( $custom_object_name, $type_key );

			if ( $object_id ) {
				++$created;
			}
		}

		return [
			'success' => true,
			'message' => sprintf( 'Created/verified %d Custom Objects for group types', $created ),
		];
	}

	/**
	 * Prepare contact data for a WordPress user
	 * Simplified version of UserHooks::prepare_contact_data for BuddyBoss use
	 *
	 * @param \WP_User $user WordPress user object
	 * @return array Contact data array
	 */
	private function prepare_contact_data_for_user( \WP_User $user ): array {
		$settings  = $this->settings_manager->get_settings_array();
		$field_map = $settings['user_field_mapping'] ?? [];

		// Build source identifier
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

		// Start with required fields
		$contact_data = [
			'email'  => $user->user_email,
			'source' => implode( ' - ', $source_parts ),
		];

		// Add basic name fields if available
		if ( ! empty( $user->first_name ) ) {
			$contact_data['firstName'] = $user->first_name;
		}
		if ( ! empty( $user->last_name ) ) {
			$contact_data['lastName'] = $user->last_name;
		}

		return $contact_data;
	}
}
