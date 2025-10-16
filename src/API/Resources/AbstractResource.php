<?php
declare(strict_types=1);

namespace GHL_CRM\API\Resources;

use GHL_CRM\API\Client\ClientInterface;
use GHL_CRM\API\Client\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Resource
 *
 * Base class for all API resources
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/Resources
 */
abstract class AbstractResource {
	/**
	 * API Client
	 *
	 * @var ClientInterface
	 */
	protected ClientInterface $client;

	/**
	 * Resource base endpoint
	 *
	 * @var string
	 */
	protected string $endpoint = '';

	/**
	 * Constructor
	 *
	 * @param ClientInterface|null $client Optional custom client
	 */
	public function __construct( ?ClientInterface $client = null ) {
		$this->client = $client ?? Client::get_instance();
	}

	/**
	 * Build full endpoint path
	 *
	 * @param string $path Additional path
	 * @return string Full endpoint
	 */
	protected function build_endpoint( string $path = '' ): string {
		$endpoint = $this->endpoint;
		
		if ( ! empty( $path ) ) {
			$endpoint .= '/' . ltrim( $path, '/' );
		}
		
		return $endpoint;
	}

	/**
	 * Get all items (paginated)
	 *
	 * @param array $params Query parameters
	 * @return array Response data
	 */
	public function all( array $params = [] ): array {
		return $this->client->get( $this->endpoint, $params );
	}

	/**
	 * Get single item by ID
	 *
	 * @param string $id Resource ID
	 * @return array Response data
	 */
	public function get( string $id ): array {
		return $this->client->get( $this->build_endpoint( $id ) );
	}

	/**
	 * Create new resource
	 *
	 * @param array $data Resource data
	 * @return array Response data
	 */
	public function create( array $data ): array {
		return $this->client->post( $this->endpoint, $data );
	}

	/**
	 * Update existing resource
	 *
	 * @param string $id   Resource ID
	 * @param array  $data Update data
	 * @return array Response data
	 */
	public function update( string $id, array $data ): array {
		return $this->client->put( $this->build_endpoint( $id ), $data );
	}

	/**
	 * Delete resource
	 *
	 * @param string $id Resource ID
	 * @return array Response data
	 */
	public function delete( string $id ): array {
		return $this->client->delete( $this->build_endpoint( $id ) );
	}
}
