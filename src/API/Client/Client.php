<?php
declare(strict_types=1);

namespace GHL_CRM\API\Client;

use GHL_CRM\API\Exceptions\ApiException;
use GHL_CRM\API\Exceptions\RateLimitException;
use GHL_CRM\API\Exceptions\AuthenticationException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Client
 *
 * Handles all HTTP communication with GoHighLevel API
 *
 * @package    GHL_CRM_Integration
 * @subpackage API/Client
 */
class Client implements ClientInterface {
	/**
	 * API Base URL
	 */
	private const BASE_URL = 'https://services.leadconnectorhq.com';

	/**
	 * API Token
	 *
	 * @var string
	 */
	private string $token = '';

	/**
	 * Location ID
	 *
	 * @var string
	 */
	private string $location_id = '';

	/**
	 * API Version
	 *
	 * @var string
	 */
	private string $api_version = '2021-07-28';

	/**
	 * Last response headers
	 *
	 * @var array
	 */
	private array $last_response_headers = [];

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
		$this->load_settings();
	}

	/**
	 * Load settings from options (multisite-aware)
	 *
	 * @return void
	 */
	private function load_settings(): void {
		// Get settings from SettingsManager (multisite-aware)
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		
		if ( ! empty( $settings['api_token'] ) ) {
			$this->token = $settings['api_token'];
		}
		
		if ( ! empty( $settings['location_id'] ) ) {
			$this->location_id = $settings['location_id'];
		}
		
		if ( ! empty( $settings['api_version'] ) ) {
			$this->api_version = $settings['api_version'];
		}
	}

	/**
	 * Reload settings (useful after settings update)
	 *
	 * @return void
	 */
	public function reload_settings(): void {
		$this->load_settings();
	}

	/**
	 * Set API token
	 *
	 * @param string $token API token
	 * @return void
	 */
	public function set_token( string $token ): void {
		$this->token = $token;
	}

	/**
	 * Set location ID
	 *
	 * @param string $location_id Location ID
	 * @return void
	 */
	public function set_location_id( string $location_id ): void {
		$this->location_id = $location_id;
	}

	/**
	 * Set API version
	 *
	 * @param string $version API version
	 * @return void
	 */
	public function set_api_version( string $version ): void {
		$this->api_version = $version;
	}

	/**
	 * Send GET request
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $params   Query parameters
	 * @return array Response data
	 * @throws ApiException
	 */
	public function get( string $endpoint, array $params = [] ): array {
		$url = $this->build_url( $endpoint, $params );
		return $this->request( 'GET', $url );
	}

	/**
	 * Send POST request
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data     Request body
	 * @return array Response data
	 * @throws ApiException
	 */
	public function post( string $endpoint, array $data = [] ): array {
		$url = $this->build_url( $endpoint );
		return $this->request( 'POST', $url, $data );
	}

	/**
	 * Send PUT request
	 *
	 * @param string $endpoint API endpoint
	 * @param array  $data     Request body
	 * @return array Response data
	 * @throws ApiException
	 */
	public function put( string $endpoint, array $data = [] ): array {
		$url = $this->build_url( $endpoint );
		return $this->request( 'PUT', $url, $data );
	}

	/**
	 * Send DELETE request
	 *
	 * @param string $endpoint API endpoint
	 * @return array Response data
	 * @throws ApiException
	 */
	public function delete( string $endpoint ): array {
		$url = $this->build_url( $endpoint );
		return $this->request( 'DELETE', $url );
	}

	/**
	 * Get last response headers
	 *
	 * @return array
	 */
	public function get_last_response_headers(): array {
		return $this->last_response_headers;
	}

	/**
	 * Get rate limit status from last response
	 *
	 * @return array ['remaining' => int, 'limit' => int, 'reset' => int]
	 */
	public function get_rate_limit_status(): array {
		$headers = $this->last_response_headers;
		
		return [
			'remaining' => isset( $headers['x-ratelimit-remaining'] ) ? (int) $headers['x-ratelimit-remaining'] : 0,
			'limit'     => isset( $headers['x-ratelimit-limit'] ) ? (int) $headers['x-ratelimit-limit'] : 0,
			'reset'     => isset( $headers['x-ratelimit-reset'] ) ? (int) $headers['x-ratelimit-reset'] : 0,
		];
	}

	/**
	 * Build full URL with endpoint and params
	 *
	 * @param string $endpoint Endpoint path
	 * @param array  $params   Query parameters
	 * @return string Full URL
	 */
	private function build_url( string $endpoint, array $params = [] ): string {
		$url = self::BASE_URL . '/' . ltrim( $endpoint, '/' );
		
		// Add location ID to params if not already present
		if ( ! empty( $this->location_id ) && ! isset( $params['locationId'] ) ) {
			$params['locationId'] = $this->location_id;
		}
		
		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}
		
		return $url;
	}

	/**
	 * Execute HTTP request
	 *
	 * @param string $method HTTP method
	 * @param string $url    Full URL
	 * @param array  $data   Request body
	 * @return array Response data
	 * @throws ApiException
	 */
	private function request( string $method, string $url, array $data = [] ): array {
		// Validate token
		if ( empty( $this->token ) ) {
			throw new AuthenticationException( __( 'API token is not configured', 'ghl-crm-integration' ) );
		}

		// Build request arguments
		$args = [
			'method'  => $method,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->token,
				'Content-Type'  => 'application/json',
				'Version'       => $this->api_version,
			],
			'timeout' => 30,
		];

		// Add body for POST/PUT requests
		if ( in_array( $method, [ 'POST', 'PUT' ], true ) && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		// Execute request
		$response = wp_remote_request( $url, $args );

		// Check for WP errors
		if ( is_wp_error( $response ) ) {
			throw new ApiException(
				sprintf(
					/* translators: %s: Error message */
					__( 'HTTP Request failed: %s', 'ghl-crm-integration' ),
					$response->get_error_message()
				)
			);
		}

		// Get response data
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		// Store headers for rate limit tracking
		$this->last_response_headers = $headers->getAll();

		// Decode JSON response
		$decoded = json_decode( $body, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new ApiException(
				__( 'Invalid JSON response from API', 'ghl-crm-integration' ),
				$status_code,
				[ 'raw_body' => $body ]
			);
		}

		// Handle error responses
		$this->handle_error_response( $status_code, $decoded );

		return $decoded;
	}

	/**
	 * Handle API error responses
	 *
	 * @param int   $status_code HTTP status code
	 * @param array $response    Decoded response body
	 * @return void
	 * @throws ApiException
	 */
	private function handle_error_response( int $status_code, array $response ): void {
		if ( $status_code >= 200 && $status_code < 300 ) {
			return; // Success
		}

		$error_message = $response['message'] ?? $response['error'] ?? __( 'Unknown API error', 'ghl-crm-integration' );

		// Rate limit exceeded
		if ( 429 === $status_code ) {
			$retry_after = $this->last_response_headers['retry-after'] ?? 60;
			throw new RateLimitException(
				sprintf(
					/* translators: %d: Seconds until retry */
					__( 'Rate limit exceeded. Retry after %d seconds.', 'ghl-crm-integration' ),
					$retry_after
				),
				(int) $retry_after,
				$response
			);
		}

		// Authentication error
		if ( 401 === $status_code || 403 === $status_code ) {
			throw new AuthenticationException( $error_message, $response );
		}

		// Generic API error
		throw new ApiException( $error_message, $status_code, $response );
	}
}
