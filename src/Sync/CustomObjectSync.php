<?php
declare(strict_types=1);

namespace GHL_CRM\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GHL_CRM\API\Resources\CustomObjectResource;
use GHL_CRM\Core\SettingsManager;

/**
 * Custom Object Sync Handler
 *
 * Handles syncing WordPress posts to GHL Custom Objects
 * Integrates with existing queue system and logging
 *
 * @package    GHL_CRM_Integration
 * @subpackage Sync
 */
class CustomObjectSync {
	/**
	 * Queue Manager instance
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Sync Logger instance
	 *
	 * @var SyncLogger
	 */
	private SyncLogger $logger;

	/**
	 * Settings Manager instance
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings;

	/**
	 * Custom Object Resource
	 *
	 * @var CustomObjectResource
	 */
	private CustomObjectResource $custom_object_resource;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->queue_manager          = QueueManager::get_instance();
		$this->logger                 = SyncLogger::get_instance();
		$this->settings               = SettingsManager::get_instance();
		$this->custom_object_resource = new CustomObjectResource();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook into post save
		add_action( 'save_post', array( $this, 'handle_post_save' ), 10, 3 );

		// Hook into post trash
		add_action( 'wp_trash_post', array( $this, 'handle_post_trash' ) );

		// Hook into post delete
		add_action( 'before_delete_post', array( $this, 'handle_post_delete' ) );

		// Register with QueueProcessor to handle custom_object sync execution
		add_filter( 'ghl_crm_execute_sync', array( $this, 'execute_custom_object_sync' ), 10, 5 );
	}

	/**
	 * Handle post save event
	 *
	 * @param int      $post_id Post ID
	 * @param \WP_Post $post    Post object
	 * @param bool     $update  Whether this is an update
	 * @return void
	 */
	public function handle_post_save( int $post_id, \WP_Post $post, bool $update ): void {
		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Get active mapping for this post type
		$mapping = $this->get_active_mapping_for_post_type( $post->post_type );
		if ( ! $mapping ) {
			return;
		}

		// Check if this trigger is enabled
		$trigger = $update ? 'update' : 'publish';
		if ( ! in_array( $trigger, $mapping['triggers'] ?? array(), true ) ) {
			return;
		}

		// Only sync published posts
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Queue the sync operation
		$this->queue_sync_operation( $post_id, $mapping, 'sync_custom_object' );
	}

	/**
	 * Handle post trash event
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function handle_post_trash( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$mapping = $this->get_active_mapping_for_post_type( $post->post_type );
		if ( ! $mapping ) {
			return;
		}

		// Check if trash trigger is enabled
		if ( ! in_array( 'trash', $mapping['triggers'] ?? array(), true ) ) {
			return;
		}

		// Queue the delete operation
		$this->queue_sync_operation( $post_id, $mapping, 'delete' );
	}

	/**
	 * Handle post delete event
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function handle_post_delete( int $post_id ): void {
		$this->handle_post_trash( $post_id );
	}

	/**
	 * Queue a sync operation
	 *
	 * @param int    $post_id  Post ID
	 * @param array  $mapping  Mapping configuration
	 * @param string $action   Action type (sync|delete)
	 * @return void
	 */
	private function queue_sync_operation( int $post_id, array $mapping, string $action ): void {
		// Use correct QueueManager signature: add_to_queue( string $item_type, int $item_id, string $action, array $payload )
		$this->queue_manager->add_to_queue(
			'custom_object', // item_type
			$post_id,        // item_id
			$action,         // action
			array(           // payload
				'mapping_id' => $mapping['id'] ?? '',
				'mapping'    => $mapping,
			)
		);

		// Logging disabled due to schema mismatch - using error_log instead
		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: Queued %s operation for post %d (mapping: %s)',
				$action,
				$post_id,
				$mapping['id'] ?? 'unknown'
			)
		);
	}

	/**
	 * Execute custom object sync operation
	 * Called by QueueProcessor via ghl_crm_execute_sync filter
	 *
	 * @param bool   $result     Previous result (from other filters).
	 * @param string $item_type  Item type.
	 * @param string $action     Action.
	 * @param int    $item_id    Item ID (post ID).
	 * @param array  $payload    Payload data.
	 * @return array|bool API response on success, false on failure or if not custom_object type.
	 */
	public function execute_custom_object_sync( $result, string $item_type, string $action, int $item_id, array $payload ) {
		// Only handle custom_object type
		if ( 'custom_object' !== $item_type ) {
			return $result;
		}

		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: execute_custom_object_sync() called - Post ID: %d, Action: %s',
				$item_id,
				$action
			)
		);

		$post_id = $item_id;
		$mapping = $payload['mapping'] ?? array();

		if ( empty( $mapping ) ) {
			$error_msg = 'Invalid queue item - missing mapping';

			return false;
		}

		try {
			if ( 'delete' === $action ) {
				$result = $this->delete_record( $post_id, $mapping );
			} elseif ( in_array( $action, array( 'sync_custom_object', 'sync' ), true ) ) {
				$result = $this->sync_post( $post_id, $mapping );
			} else {

				return false;
			}

			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: Sync completed - Post ID: %d, Result: %s',
					$post_id,
					$result ? 'SUCCESS' : 'FAILED'
				)
			);

			return $result;
		} catch ( \Exception $e ) {
			$error_msg = 'Sync failed: ' . $e->getMessage();

			return false;
		}
	}

	/**
	 * Sync a post to GHL Custom Object
	 *
	 * @param int   $post_id Post ID
	 * @param array $mapping Mapping configuration
	 * @return bool Success status
	 */
	private function sync_post( int $post_id, array $mapping ): bool {
		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: sync_post() started - Post ID: %d',
				$post_id
			)
		);

		$post = get_post( $post_id );
		if ( ! $post ) {

			return false;
		}

		// Get contact ID
		$contact_id = $this->get_contact_id( $post_id, $mapping );
		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: Contact ID: %s',
				$contact_id ? $contact_id : 'NONE'
			)
		);

		if ( ! $contact_id ) {
			$not_found_action = $mapping['contact_not_found'] ?? 'skip';

			if ( 'skip' === $not_found_action ) {

				return false;
			}

			// TODO: Implement contact creation if needed
			if ( 'create' === $not_found_action ) {

				return false;
			}

			// 'log' action - continue without contact

		}

		// Check if record already exists
		$ghl_record_id = get_post_meta( $post_id, '_ghl_custom_object_record_id', true );
		$is_update     = ! empty( $ghl_record_id );

		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: Existing record ID: %s',
				$ghl_record_id ? $ghl_record_id : 'NONE (will create new)'
			)
		);

		// Build GHL payload (different for create vs update)
		$payload = $this->build_payload( $post, $mapping, $contact_id, $is_update );

		// Get schema ID from mapping
		$schema_id  = $mapping['ghl_object'] ?? '';
		$schema_key = $mapping['ghl_object_key'] ?? '';

		if ( empty( $schema_id ) ) {

			return false;
		}

		// IMPORTANT: GHL API uses schema ID for BOTH create and update
		// The documentation/error messages are misleading - always use the hex ID
		error_log(
			sprintf(
				'GHL CRM CustomObjectSync: Using schema ID: %s for %s operation',
				$schema_id,
				$is_update ? 'UPDATE' : 'CREATE'
			)
		);

		try {
			if ( $is_update ) {
				// Update existing record

				// First, verify the record still exists

				$existing_record = $this->custom_object_resource->get_record( $ghl_record_id, $schema_id );

				if ( ! $existing_record ) {

					// Record doesn't exist, delete local meta and recreate
					delete_post_meta( $post_id, '_ghl_custom_object_record_id' );
					$is_update = false;
					// Fall through to CREATE
				} else {

					// UPDATE requires schema KEY (dot-notation), not schema ID
					if ( empty( $schema_key ) ) {
						throw new \Exception( 'Schema KEY (ghl_object_key) is required for UPDATE operations' );
					}

					error_log(
						sprintf(
							'GHL CRM CustomObjectSync: Using schema KEY for UPDATE: %s',
							$schema_key
						)
					);

					$response = $this->custom_object_resource->update_record( $schema_key, $ghl_record_id, $payload );
					$action   = 'update';
				}
			}

			// CREATE operation (either new post or record was deleted from GHL)
			if ( ! $is_update ) {

				$response = $this->custom_object_resource->create_record( $schema_id, $payload );

				$ghl_record_id = $response['id'] ?? null;
				$action        = 'create';

				// Store record ID
				if ( $ghl_record_id ) {
					update_post_meta( $post_id, '_ghl_custom_object_record_id', $ghl_record_id );

				} else {

				}

				// Associate with contact using the correct API endpoint
				// Endpoint: POST /associations/relations
				// Documentation: https://marketplace.gohighlevel.com/docs/ghl/associations/create-relation
				// NOTE: Requires associationId (the ID of the association definition, not the key/name)
				if ( $ghl_record_id && $contact_id && ! empty( $schema_key ) ) {
					// Get association ID and direction from mapping configuration or fetch from GHL
					$association_id        = $mapping['association_id'] ?? '';
					$association_direction = null; // Track if custom object is 'first' or 'second'

					// If not configured, try to auto-fetch it from GHL
					if ( empty( $association_id ) ) {

						try {
							// Fetch all associations from GHL
							$all_associations = $this->custom_object_resource->get_associations();

							// Find the association that links our custom object to contacts
							foreach ( $all_associations as $assoc ) {
								$first_key  = $assoc['firstObjectKey'] ?? '';
								$second_key = $assoc['secondObjectKey'] ?? '';

								// Check if this association links our custom object to contacts (either direction)
								if ( $first_key === $schema_key && $second_key === 'contact' ) {
									$association_id        = $assoc['id'] ?? '';
									$association_direction = 'first'; // Custom object is first
									error_log(
										sprintf(
											'GHL CRM CustomObjectSync: Auto-discovered association ID: %s (key: %s, direction: %s → %s)',
											$association_id,
											$assoc['key'] ?? 'N/A',
											$first_key,
											$second_key
										)
									);
									break;
								} elseif ( $first_key === 'contact' && $second_key === $schema_key ) {
									$association_id        = $assoc['id'] ?? '';
									$association_direction = 'second'; // Custom object is second
									error_log(
										sprintf(
											'GHL CRM CustomObjectSync: Auto-discovered association ID: %s (key: %s, direction: %s → %s)',
											$association_id,
											$assoc['key'] ?? 'N/A',
											$first_key,
											$second_key
										)
									);
									break;
								}
							}

							if ( empty( $association_id ) ) {
								error_log(
									sprintf(
										'GHL CRM CustomObjectSync: No association found for %s ↔ contact. Create one in GHL first.',
										$schema_key
									)
								);
							}
						} catch ( \Exception $fetch_error ) {

						}
					}

					if ( ! empty( $association_id ) ) {
						try {
							error_log(
								sprintf(
									'GHL CRM CustomObjectSync: Attempting to associate record %s with contact %s (associationId: %s, direction: %s)',
									$ghl_record_id,
									$contact_id,
									$association_id,
									$association_direction ?? 'unknown'
								)
							);

							$this->custom_object_resource->associate_with_contact(
								$ghl_record_id,
								$contact_id,
								$schema_key,
								$association_id,
								$association_direction
							);

						} catch ( \Exception $assoc_error ) {
							// Log but don't fail the whole sync

						}
					}
				} elseif ( $ghl_record_id && ! $schema_key ) {

				}
			}

			// Update sync metadata
			update_post_meta( $post_id, '_ghl_last_sync_time', current_time( 'timestamp' ) );
			update_post_meta( $post_id, '_ghl_sync_status', 'synced' );

			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: SUCCESS - %s completed for post %d (GHL Record ID: %s)',
					$action,
					$post_id,
					$ghl_record_id ?? 'none'
				)
			);

			return true;

		} catch ( \Exception $e ) {

			// Update sync status
			update_post_meta( $post_id, '_ghl_sync_status', 'error' );
			update_post_meta( $post_id, '_ghl_sync_error', $e->getMessage() );

			throw $e;
		}
	}

	/**
	 * Delete a record from GHL
	 *
	 * @param int   $post_id Post ID
	 * @param array $mapping Mapping configuration
	 * @return bool Success status
	 */
	private function delete_record( int $post_id, array $mapping ): bool {
		$ghl_record_id = get_post_meta( $post_id, '_ghl_custom_object_record_id', true );

		if ( ! $ghl_record_id ) {
			// Nothing to delete
			return true;
		}

		try {
			// Get schema ID from mapping
			$schema_id = $mapping['schema_id'] ?? '';
			if ( empty( $schema_id ) ) {
				throw new \Exception( 'Schema ID not found in mapping configuration' );
			}

			$this->custom_object_resource->delete_record( $ghl_record_id, $schema_id );

			// Clean up metadata
			delete_post_meta( $post_id, '_ghl_custom_object_record_id' );
			delete_post_meta( $post_id, '_ghl_last_sync_time' );
			delete_post_meta( $post_id, '_ghl_sync_status' );
			delete_post_meta( $post_id, '_ghl_sync_error' );

			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: Successfully deleted record %s for post %d',
					$ghl_record_id,
					$post_id
				)
			);

			return true;

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: Failed to delete record %s for post %d - %s',
					$ghl_record_id,
					$post_id,
					$e->getMessage()
				)
			);

			throw $e;
		}
	}

	/**
	 * Get contact ID for a post
	 *
	 * @param int   $post_id Post ID
	 * @param array $mapping Mapping configuration
	 * @return string|null Contact ID or null
	 */
	private function get_contact_id( int $post_id, array $mapping ): ?string {
		$source = $mapping['contact_source'] ?? 'post_author';
		$field  = $mapping['contact_field'] ?? '';

		switch ( $source ) {
			case 'post_author':
				$author_id = get_post_field( 'post_author', $post_id );
				return get_user_meta( $author_id, '_ghl_contact_id', true ) ?: null;

			case 'post_meta':
				return get_post_meta( $post_id, $field, true ) ?: null;

			case 'acf':
				if ( function_exists( 'get_field' ) ) {
					return get_field( $field, $post_id ) ?: null;
				}
				return null;

			default:
				return null;
		}
	}

	/**
	 * Build GHL API payload
	 *
	 * @param \WP_Post $post       Post object
	 * @param array    $mapping    Mapping configuration
	 * @param string   $contact_id Contact ID
	 * @param bool     $is_update  Whether this is an update operation (default: false)
	 * @return array Payload data
	 */
	private function build_payload( \WP_Post $post, array $mapping, ?string $contact_id, bool $is_update = false ): array {
		$location_id = $this->settings->get_setting( 'location_id', '' );

		$properties = array();

		// Note: Contact associations are handled separately via POST /associations/relations
		// They are not included in the record properties
		// The association will be created after the record is successfully created
		if ( ! empty( $contact_id ) ) {
			error_log(
				sprintf(
					'GHL CRM CustomObjectSync: Contact ID %s will be associated after record creation',
					$contact_id
				)
			);
		}

		// Map fields
		foreach ( $mapping['field_mappings'] as $field_map ) {
			$wp_field  = $field_map['wp_field'];
			$ghl_field = $field_map['ghl_field'];
			$transform = $field_map['transform'] ?? 'none';

			// Extract WordPress value
			$value = $this->extract_field_value( $post->ID, $wp_field, $field_map );

			// Apply transformation
			$value = $this->apply_transform( $value, $transform );

			// Add to properties
			// Strip the "custom_objects.{object_name}." prefix from field key
			// GHL expects only the field key in properties, not the full path
			$field_key = $ghl_field;
			if ( strpos( $ghl_field, 'custom_objects.' ) === 0 ) {
				// Extract just the field name from "custom_objects.object_name.field_name"
				$parts = explode( '.', $ghl_field );
				if ( count( $parts ) >= 3 ) {
					$field_key = $parts[2]; // Get the field name part
				}
			}

			if ( null !== $value && '' !== $value ) {
				$properties[ $field_key ] = $value;
			}
		}

		// Note: Only include fields that actually exist in the custom object schema
		// Don't add 'name' or other fields unless they're mapped in field_mappings

		// Build payload based on operation type
		// CREATE: requires locationId
		// UPDATE: only needs properties (record is already associated with location)
		if ( $is_update ) {
			$payload = array(
				'properties' => $properties,
			);
		} else {
			$payload = array(
				'locationId' => $location_id,
				'properties' => $properties,
			);
		}

		return $payload;
	}

	/**
	 * Extract field value from WordPress post
	 *
	 * @param int    $post_id   Post ID
	 * @param string $field     Field type
	 * @param array  $field_map Field mapping config
	 * @return mixed Field value
	 */
	private function extract_field_value( int $post_id, string $field, array $field_map ) {
		$field_name = $field_map['wp_field_name'] ?? '';

		switch ( $field ) {
			case 'post_title':
				return get_the_title( $post_id );

			case 'post_content':
				return get_post_field( 'post_content', $post_id );

			case 'post_excerpt':
				return get_the_excerpt( $post_id );

			case 'post_date':
				return get_post_field( 'post_date', $post_id );

			case 'post_modified':
				return get_post_field( 'post_modified', $post_id );

			case 'post_meta':
				return get_post_meta( $post_id, $field_name, true );

			case 'acf_field':
				if ( function_exists( 'get_field' ) ) {
					return get_field( $field_name, $post_id );
				}
				return null;

			case 'taxonomy':
				$terms = get_the_terms( $post_id, $field_name );
				if ( is_array( $terms ) && ! empty( $terms ) ) {
					return wp_list_pluck( $terms, 'name' );
				}
				return null;

			case 'static':
				return $field_name;

			default:
				return null;
		}
	}

	/**
	 * Apply data transformation
	 *
	 * @param mixed  $value     Value to transform
	 * @param string $transform Transform type
	 * @return mixed Transformed value
	 */
	private function apply_transform( $value, string $transform ) {
		if ( null === $value || '' === $value ) {
			return $value;
		}

		switch ( $transform ) {
			case 'sanitize':
				return wp_kses_post( $value );

			case 'strip_html':
				return wp_strip_all_tags( $value );

			case 'number':
				return is_numeric( $value ) ? floatval( $value ) : 0;

			case 'date_iso':
				$timestamp = is_numeric( $value ) ? $value : strtotime( $value );
				return gmdate( 'c', $timestamp );

			case 'json_encode':
				return wp_json_encode( $value );

			default:
				return $value;
		}
	}

	/**
	 * Get active mapping for post type
	 *
	 * @param string $post_type Post type
	 * @return array|null Mapping configuration or null
	 */
	private function get_active_mapping_for_post_type( string $post_type ): ?array {
		$mappings = $this->settings->get_option( 'ghl_crm_custom_object_mappings', array() );

		foreach ( $mappings as $mapping ) {
			if ( isset( $mapping['wp_post_type'], $mapping['active'] ) &&
				$mapping['wp_post_type'] === $post_type &&
				true === $mapping['active'] ) {
				return $mapping;
			}
		}

		return null;
	}

	/**
	 * Manual sync for a specific post
	 *
	 * @param int $post_id Post ID
	 * @return bool Success status
	 */
	public function manual_sync_post( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$mapping = $this->get_active_mapping_for_post_type( $post->post_type );
		if ( ! $mapping ) {
			return false;
		}

		try {
			return $this->sync_post( $post_id, $mapping );
		} catch ( \Exception $e ) {
			return false;
		}
	}
}
