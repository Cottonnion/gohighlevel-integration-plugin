<?php
declare(strict_types=1);

namespace Syncly\API\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Client Interface
 *
 * Contract for all API client implementations
 *
 * @package    Syncly
 * @subpackage API/Client
 */
interface ClientInterface {
	/**
	 * Send GET request
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $params   Query parameters
	 * @return array Response data
	 * @throws \Syncly\API\Exceptions\ApiException
	 */
	public function get( string $endpoint, array $params = [] ): array;

	/**
	 * Send POST request
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data     Request body
	 * @return array Response data
	 * @throws \Syncly\API\Exceptions\ApiException
	 */
	public function post( string $endpoint, array $data = [] ): array;

	/**
	 * Send PUT request
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data     Request body
	 * @return array Response data
	 * @throws \Syncly\API\Exceptions\ApiException
	 */
	public function put( string $endpoint, array $data = [] ): array;

	/**
	 * Send DELETE request
	 *
	 * @param string $endpoint API endpoint
	 * @return array Response data
	 * @throws \Syncly\API\Exceptions\ApiException
	 */
	public function delete( string $endpoint ): array;

	/**
	 * Set API token
	 *
	 * @param string $token API token
	 * @return void
	 */
	public function set_token( string $token ): void;

	/**
	 * Set location ID
	 *
	 * @param string $location_id Location ID
	 * @return void
	 */
	public function set_location_id( string $location_id ): void;

	/**
	 * Get last response headers
	 *
	 * @return array
	 */
	public function get_last_response_headers(): array;

	/**
	 * Get rate limit status
	 *
	 * @return array ['remaining' => int, 'limit' => int, 'reset' => int]
	 */
	public function get_rate_limit_status(): array;
}
