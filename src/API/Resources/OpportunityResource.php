<?php
declare(strict_types=1);

namespace GHL_CRM\API\Resources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opportunity Resource
 *
 * Handles GoHighLevel Opportunities API operations
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/Resources
 */
class OpportunityResource extends AbstractResource {
	/**
	 * Resource endpoint
	 *
	 * @var string
	 */
	protected string $endpoint = 'opportunities/';

	/**
	 * Search opportunities
	 *
	 * @param array $params Search parameters (location_id, pipelineId, status, etc.)
	 * @return array Response data with opportunities array
	 */
	public function search( array $params = [] ): array {
		return $this->client->get( $this->build_endpoint( 'search' ), $params );
	}

	/**
	 * Get all pipelines for a location
	 *
	 * @param string $location_id Location ID
	 * @return array Response data with pipelines array
	 */
	public function get_pipelines( string $location_id ): array {
		return $this->client->get( $this->build_endpoint( 'pipelines' ), [ 'locationId' => $location_id ] );
	}

	/**
	 * Create a new opportunity
	 *
	 * @param array $data Opportunity data
	 *                    Required: location_id, pipeline_id, contact_id, name
	 *                    Optional: stage_id, status, monetary_value, assigned_to, etc.
	 * @return array Response data with created opportunity
	 */
	public function create( array $data ): array {
		// Don't include locationId in URL query params - it's already in the body
		return $this->client->post( $this->endpoint, $data, false );
	}

	/**
	 * Update an existing opportunity
	 *
	 * @param string $opportunity_id Opportunity ID
	 * @param array  $data           Update data (stage_id, status, monetary_value, etc.)
	 * @return array Response data with updated opportunity
	 */
	public function update( string $opportunity_id, array $data ): array {
		return $this->client->put( $this->build_endpoint( $opportunity_id ), $data );
	}

	/**
	 * Update opportunity status/stage
	 *
	 * @param string $opportunity_id Opportunity ID
	 * @param string $stage_id       New stage ID
	 * @param string $status         Status (open, won, lost, abandoned)
	 * @return array Response data
	 */
	public function update_status( string $opportunity_id, string $stage_id, string $status = 'open' ): array {
		return $this->update(
			$opportunity_id,
			[
				'stageId' => $stage_id,
				'status'  => $status,
			]
		);
	}

	/**
	 * Delete an opportunity
	 *
	 * @param string $opportunity_id Opportunity ID
	 * @return array Response data
	 */
	public function delete( string $opportunity_id ): array {
		return $this->client->delete( $this->build_endpoint( $opportunity_id ) );
	}

	/**
	 * Upsert opportunity (create or update based on existing check)
	 *
	 * @param array  $opportunity_data Opportunity data
	 * @param string $meta_key         WordPress meta key to check for existing opportunity ID
	 * @param int    $object_id        WordPress object ID (user_id, order_id, etc.)
	 * @return array Response data with opportunity
	 */
	public function upsert( array $opportunity_data, string $meta_key, int $object_id ): array {
		// Check if opportunity already exists
		$existing_opportunity_id = get_metadata( 'user', $object_id, $meta_key, true );

		if ( ! empty( $existing_opportunity_id ) ) {
			// Update existing opportunity
			try {
				return $this->update( $existing_opportunity_id, $opportunity_data );
			} catch ( \Exception $e ) {
				// If update fails (opportunity deleted in GHL), create new one
				delete_metadata( 'user', $object_id, $meta_key );
			}
		}

		// Create new opportunity
		$response = $this->create( $opportunity_data );

		// Store opportunity ID in WordPress meta
		if ( ! empty( $response['opportunity']['id'] ) ) {
			update_metadata( 'user', $object_id, $meta_key, $response['opportunity']['id'] );
		}

		return $response;
	}
}
