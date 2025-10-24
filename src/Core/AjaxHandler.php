<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Handler class
 *
 * Handles all AJAX requests for the plugin.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core
 */
class AjaxHandler {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get class instance | singleton pattern
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
	 * Private constructor to prevent direct creation
	 */
	private function __construct() {
		// Constructor is empty, hooks are initialized via init()
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		// Test API connection
		// add_action( 'wp_ajax_ghl_crm_test_connection', [ $this, 'test_connection' ] );
	}

	/**
	 * Test GoHighLevel API connection
	 *
	 * @return void
	 */
	public function test_connection(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_crm_admin', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to perform this action.', 'ghl-crm-integration' ),
				]
			);
		}

		// Get API credentials from POST or from saved options
		$api_token   = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : get_option( 'ghl_crm_api_token', '' );
		$location_id = isset( $_POST['location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['location_id'] ) ) : get_option( 'ghl_crm_location_id', '' );

		// Validate required fields
		if ( empty( $api_token ) || empty( $location_id ) ) {
			wp_send_json_error(
				[
					'message' => __( 'API Token and Location ID are required.', 'ghl-crm-integration' ),
				]
			);
		}

		// Test the connection
		$result = $this->test_ghl_api_connection( $api_token, $location_id );

		if ( $result['success'] ) {
			wp_send_json_success(
				[
					'message'       => __( 'Connection successful!', 'ghl-crm-integration' ),
					'location_name' => $result['location_name'] ?? '',
					'details'       => $result['details'] ?? '',
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => $result['message'] ?? __( 'Connection failed.', 'ghl-crm-integration' ),
				]
			);
		}
	}

	/**
	 * Test GoHighLevel API connection
	 *
	 * @param string $api_token   The API token.
	 * @param string $location_id The location ID.
	 * @return array Result array with success status and details.
	 */
	private function test_ghl_api_connection( string $api_token, string $location_id ): array {
		// API endpoint to get location details
		$api_url = 'https://services.leadconnectorhq.com/locations/' . $location_id;

		// Make API request
		$response = wp_remote_get(
			$api_url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_token,
					'Version'       => '2021-07-28',
					'Content-Type'  => 'application/json',
				],
				'timeout' => 30,
			]
		);

		// Check for WordPress HTTP errors
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'HTTP Error: %s', 'ghl-crm-integration' ),
					$response->get_error_message()
				),
			];
		}

		// Get response code
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		// Handle different response codes
		switch ( $response_code ) {
			case 200:
				$location_name = $data['location']['name'] ?? __( 'Unknown', 'ghl-crm-integration' );
				return [
					'success'       => true,
					'location_name' => $location_name,
					'details'       => sprintf(
						/* translators: %s: Location name */
						__( 'Connected to location: %s', 'ghl-crm-integration' ),
						$location_name
					),
				];

			case 401:
				return [
					'success' => false,
					'message' => __( 'Authentication failed. Please check your API Token.', 'ghl-crm-integration' ),
				];

			case 403:
				return [
					'success' => false,
					'message' => __( 'Access denied. Your API Token does not have permission to access this location.', 'ghl-crm-integration' ),
				];

			case 404:
				return [
					'success' => false,
					'message' => __( 'Location not found. Please check your Location ID.', 'ghl-crm-integration' ),
				];

			case 429:
				return [
					'success' => false,
					'message' => __( 'Rate limit exceeded. Please try again in a few minutes.', 'ghl-crm-integration' ),
				];

			default:
				return [
					'success' => false,
					'message' => sprintf(
						/* translators: %d: HTTP status code */
						__( 'API Error: HTTP %d', 'ghl-crm-integration' ),
						$response_code
					),
				];
		}
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
