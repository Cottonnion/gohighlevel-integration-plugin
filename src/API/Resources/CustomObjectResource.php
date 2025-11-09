<?php
declare(strict_types=1);

namespace GHL_CRM\API\Resources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Object Resource
 *
 * Handles GoHighLevel custom object API operations
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/Resources
 */
class CustomObjectResource extends AbstractResource {
	/**
	 * Resource endpoint
	 *
	 * @var string
	 */
	protected string $endpoint = 'objects/';

	/**
	 * Get all custom object schemas
	 *
	 * @param bool $use_cache Whether to use cached results (default: true)
	 * @return array Array of schema objects
	 */
	public function get_schemas( bool $use_cache = true ): array {
		if ( $use_cache ) {
			$cached = get_transient( 'ghl_custom_objects_schemas' );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		try {
			// Build the full endpoint
			$full_endpoint = $this->build_endpoint();

			// Endpoint: GET /objects/ returns all objects for a location
			$response = $this->all();

			// The response structure is { "objects": [...] } according to GHL docs
			$objects = $response['objects'] ?? [];

			// Cache using configured duration
			$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
			$cache_duration   = absint( $settings_manager->get_setting( 'cache_duration', HOUR_IN_SECONDS ) );
			set_transient( 'ghl_custom_objects_schemas', $objects, $cache_duration );

			return $objects;
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Delete a custom object record
	 *
	 * @param string $record_id   Record ID
	 * @param string $location_id Location ID
	 * @return bool Success status
	 * @throws \Exception If deletion fails
	 */
	public function delete_record( string $record_id, string $schema_id ): bool {
		try {
			if ( empty( $schema_id ) ) {
				throw new \Exception( 'Schema ID is required to delete custom object record' );
			}

			// Use the correct GHL API endpoint format: /objects/:schemaKey/records/:recordId
			$endpoint = "objects/{$schema_id}/records/{$record_id}";

			$this->client->delete( $endpoint, false );

			return true;
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to delete custom object record: ' . $e->getMessage() );
		}
	}

	/**
	 * Get a specific custom object schema by ID
	 *
	 * @param string $schema_id Schema ID
	 * @return array|null Schema data or null if not found
	 */
	public function get_schema( string $schema_id ): ?array {
		try {
			// First try to get from cache
			$schemas = $this->get_schemas( true );
			foreach ( $schemas as $schema ) {
				if ( isset( $schema['id'] ) && $schema['id'] === $schema_id ) {
					return $schema;
				}
			}

			// If not in cache, try API call
			$response = $this->get( $schema_id );

			// GHL API might return the schema directly or wrapped in a property
			if ( isset( $response['id'] ) && $response['id'] === $schema_id ) {
				return $response; // Schema returned directly
			}

			return $response['schema'] ?? $response['object'] ?? null;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Clear cached schemas
	 *
	 * @return bool True if cache was cleared
	 */
	public function clear_cache(): bool {
		return delete_transient( 'ghl_custom_objects_schemas' );
	}

	/**
	 * Create a custom object schema
	 *
	 * @param array $schema_data Schema definition including key, locationId, labels, and primaryDisplayPropertyDetails
	 * @return array Created schema data
	 * @throws \Exception If creation fails
	 */
	public function create_schema( array $schema_data ): array {
		try {
			// Validate required fields per GHL API
			if ( empty( $schema_data['key'] ) ) {
				throw new \Exception( 'Schema key is required' );
			}
			if ( empty( $schema_data['locationId'] ) ) {
				throw new \Exception( 'Location ID is required' );
			}
			if ( empty( $schema_data['primaryDisplayPropertyDetails'] ) ) {
				throw new \Exception( 'Primary display property details are required' );
			}

			// POST to /objects/ to create a new custom object schema
			// locationId should be in the body, NOT as query param
			$response = $this->client->post( 'objects/', $schema_data, false );

			// Clear cache after creating new schema
			$this->clear_cache();

			return $response['schema'] ?? $response['object'] ?? $response;
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to create custom object schema: ' . $e->getMessage() );
		}
	}

	/**
	 * Create a custom object record
	 *
	 * @param string $schema_id Schema ID (not key)
	 * @param array  $data      Record data with properties wrapped in "properties" object
	 * @return array Created record data
	 * @throws \Exception If creation fails
	 */
	public function create_record( string $schema_id, array $data ): array {
		try {
			if ( empty( $schema_id ) ) {
				throw new \Exception( 'Schema ID is required to create custom object record' );
			}

			// Use the correct GHL API endpoint format: /objects/:schemaId/records
			$endpoint = "objects/{$schema_id}/records";

			$response = $this->client->post( $endpoint, $data, false );

			$result = $response['record'] ?? $response;

			return $result;
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to create custom object record: ' . $e->getMessage() );
		}
	}

	/**
	 * Update a custom object record
	 *
	 * @param string $schema_key Schema KEY (e.g., 'custom_objects.my_custom_objects')
	 * @param string $record_id Record ID
	 * @param array  $data      Updated data
	 * @return array Updated record data
	 * @throws \Exception If update fails
	 */
	public function update_record( string $schema_key, string $record_id, array $data ): array {
		try {
			if ( empty( $schema_key ) ) {
				throw new \Exception( 'Schema KEY is required to update custom object record' );
			}

			// GHL API UPDATE endpoint: PUT /objects/:schemaKey/records/:id?locationId=xxx
			// Per GHL docs: schemaKey format is "custom_objects.object_name"
			// locationId MUST be included as query parameter
			$endpoint = "objects/{$schema_key}/records/{$record_id}";

			// Pass true to include locationId in query params (required by GHL API)
			$response = $this->client->put( $endpoint, $data, true );

			return $response['record'] ?? $response;
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to update custom object record: ' . $e->getMessage() );
		}
	}

	/**
	 * Get a custom object record by ID
	 *
	 * @param string $record_id   Record ID
	 * @param string $location_id Location ID
	 * @return array|null Record data or null if not found
	 */
	public function get_record( string $record_id, string $schema_id ): ?array {
		try {
			if ( empty( $schema_id ) ) {
				throw new \Exception( 'Schema ID is required to get custom object record' );
			}

			// Use the correct GHL API endpoint format: /objects/:schemaKey/records/:recordId
			$endpoint = "objects/{$schema_id}/records/{$record_id}";

			$response = $this->client->get( $endpoint, [], false );

			return $response['record'] ?? $response;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get all associations for the location
	 *
	 * @return array Array of associations
	 * @throws \Exception If fetch fails
	 */
	public function get_associations(): array {
		try {
			$endpoint = 'associations/';
			$response = $this->client->get( $endpoint, [], true );

			return $response['associations'] ?? $response;
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to get associations: ' . $e->getMessage() );
		}
	}

	/**
	 * Create an association definition between a custom object and contacts
	 * This defines the relationship type (e.g., "School has many Students")
	 *
	 * @param string $schema_key Custom object schema key (e.g., 'custom_objects.schools')
	 * @param string $singular_label Singular label for the relationship (e.g., 'Student')
	 * @param string $plural_label Plural label for the relationship (e.g., 'Students')
	 * @param string $cardinality Relationship type: 'ONE_TO_MANY' or 'MANY_TO_MANY'
	 * @return array Association definition including ID
	 * @throws \Exception If creation fails
	 */
	public function create_association( string $schema_key, string $singular_label, string $plural_label, string $cardinality = 'ONE_TO_MANY' ): array {
		try {
			$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
			$location_id      = $settings_manager->get_setting( 'location_id', '' );

			// Association definition structure per GHL API
			// Based on error: needs 'key', but NOT 'cardinality' or 'labels'
			// The key should be a unique identifier for this association
			// Format: association between two objects
			$association_key = str_replace( 'custom_objects.', '', $schema_key ) . '_members';

			$data = [
				'key'             => $association_key,   // e.g., "classrooms_members"
				'locationId'      => $location_id,
				'firstObjectKey'  => $schema_key,        // Custom object key
				'secondObjectKey' => 'contact',          // Standard contacts object
			];

			$endpoint = 'associations/';
			$response = $this->client->post( $endpoint, $data, false );

			return $response;
		} catch ( \Exception $e ) {

			throw new \Exception( 'Failed to create association: ' . $e->getMessage() );
		}
	}

	/**
	 * Associate a custom object record with a contact
	 *
	 * @param string      $record_id   Custom object record ID
	 * @param string      $contact_id  Contact ID to associate with
	 * @param string      $schema_key  Schema key (e.g., 'custom_objects.my_custom_objects')
	 * @param string      $association_id Association definition ID
	 * @param string|null $direction Which position custom object is in: 'first' or 'second' (null = assume first)
	 * @return array Response data
	 * @throws \Exception If association fails
	 */
	public function associate_with_contact( string $record_id, string $contact_id, string $schema_key, string $association_id, ?string $direction = null ): array {
		try {
			// CORRECT GHL API endpoint: POST /associations/relations
			// Documentation: https://marketplace.gohighlevel.com/docs/ghl/associations/create-relation
			$endpoint = 'associations/relations';

			// Required fields per GHL API docs:
			// - locationId: The location ID
			// - associationId: The association's ID (not key!)
			// - firstRecordId: First object's record ID (order matters!)
			// - secondRecordId: Second object's record ID (order matters!)
			//
			// IMPORTANT: The order must match the association definition!
			// If association is: contact → custom_object, then firstRecordId = contact, secondRecordId = custom_object
			$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
			$location_id      = $settings_manager->get_setting( 'location_id', '' );

			// Determine correct order based on association direction
			if ( $direction === 'second' ) {
				// Association is: contact (first) → custom_object (second)
				$first_id  = $contact_id;
				$second_id = $record_id;
			} else {
				// Association is: custom_object (first) → contact (second) [default]
				$first_id  = $record_id;
				$second_id = $contact_id;
			}

			$data = [
				'locationId'     => $location_id,
				'associationId'  => $association_id,
				'firstRecordId'  => $first_id,
				'secondRecordId' => $second_id,
			];

			error_log(
				sprintf(
					'GHL Association: Creating relation - Location: %s, Association: %s, First: %s, Second: %s, Direction: %s',
					$location_id,
					$association_id,
					$first_id,
					$second_id,
					$direction ?? 'first (default)'
				)
			);

			$response = $this->client->post( $endpoint, $data, false );

			return $response;
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to associate record with contact: ' . $e->getMessage() );
		}
	}
}
