<?php
declare(strict_types=1);

namespace GHL_CRM\API\Resources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact Resource
 *
 * Handles GoHighLevel Contacts API operations
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/Resources
 */
class ContactResource extends AbstractResource {
	/**
	 * Resource endpoint
	 *
	 * @var string
	 */
	protected string $endpoint = 'contacts';

	/**
	 * Search contacts by email
	 *
	 * @param string $email Email address
	 * @return array|null Contact data or null if not found
	 */
	public function find_by_email( string $email ): ?array {
		$response = $this->client->get(
			$this->endpoint,
			[
				'query' => $email,
			]
		);

		return $response['contacts'][0] ?? null;
	}

	/**
	 * Search contacts by phone
	 *
	 * @param string $phone Phone number
	 * @return array|null Contact data or null if not found
	 */
	public function find_by_phone( string $phone ): ?array {
		$response = $this->client->get(
			$this->endpoint,
			[
				'phone' => $phone,
			]
		);

		return $response['contacts'][0] ?? null;
	}

	/**
	 * Add tags to contact
	 *
	 * @param string $contact_id Contact ID
	 * @param array  $tags       Array of tag names
	 * @return array Response data
	 */
	public function add_tags( string $contact_id, array $tags ): array {
		return $this->client->post(
			$this->build_endpoint( "{$contact_id}/tags" ),
			[ 'tags' => $tags ]
		);
	}

	/**
	 * Remove tags from contact
	 *
	 * @param string $contact_id Contact ID
	 * @param array  $tags       Array of tag names
	 * @return array Response data
	 */
	public function remove_tags( string $contact_id, array $tags ): array {
		return $this->client->delete(
			$this->build_endpoint( "{$contact_id}/tags" ) . '?' . http_build_query( [ 'tags' => $tags ] )
		);
	}

	/**
	 * Add contact to workflow
	 *
	 * @param string $contact_id  Contact ID
	 * @param string $workflow_id Workflow ID
	 * @return array Response data
	 */
	public function add_to_workflow( string $contact_id, string $workflow_id ): array {
		return $this->client->post(
			$this->build_endpoint( "{$contact_id}/workflow/{$workflow_id}" )
		);
	}

	/**
	 * Upsert contact (create or update based on email)
	 *
	 * @param array $data Contact data (must include email)
	 * @return array Response data with 'created' boolean flag
	 * @throws \InvalidArgumentException If email is missing
	 */
	public function upsert( array $data ): array {
		if ( empty( $data['email'] ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Email is required for upsert', 'ghl-crm-integration' ) );
		}

		// Check if contact exists
		$existing = ! empty( $data['email'] ) ? $this->find_by_email( $data['email'] ) : null;

		if ( $existing ) {
			// Update existing contact
			$response            = $this->update( $existing['id'], $data );
			$response['created'] = false;
			return $response;
		}

		// Create new contact
		$response            = $this->create( $data );
		$response['created'] = true;
		return $response;
	}

	/**
	 * Get contact's notes
	 *
	 * @param string $contact_id Contact ID
	 * @return array Response data
	 */
	public function get_notes( string $contact_id ): array {
		return $this->client->get( $this->build_endpoint( "{$contact_id}/notes" ) );
	}

	/**
	 * Add note to contact
	 *
	 * @param string $contact_id Contact ID
	 * @param string $note       Note content
	 * @return array Response data
	 */
	public function add_note( string $contact_id, string $note ): array {
		return $this->client->post(
			$this->build_endpoint( "{$contact_id}/notes" ),
			[ 'body' => $note ]
		);
	}

	/**
	 * Get contact's tasks
	 *
	 * @param string $contact_id Contact ID
	 * @return array Response data
	 */
	public function get_tasks( string $contact_id ): array {
		return $this->client->get( $this->build_endpoint( "{$contact_id}/tasks" ) );
	}

	/**
	 * Get contact's appointments
	 *
	 * @param string $contact_id Contact ID
	 * @return array Response data
	 */
	public function get_appointments( string $contact_id ): array {
		return $this->client->get( $this->build_endpoint( "{$contact_id}/appointments" ) );
	}
}
