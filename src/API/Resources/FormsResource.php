<?php
declare(strict_types=1);

namespace GHL_CRM\API\Resources;

use GHL_CRM\API\Client\Client;
use GHL_CRM\API\Exceptions\APIException;

defined( 'ABSPATH' ) || exit;

/**
 * Forms Resource class
 *
 * Handles GoHighLevel Forms API operations
 *
 * IMPORTANT: The GHL Forms API is severely limited. The /forms/ endpoint only returns:
 * - id, name, locationId (and sometimes submissions count)
 * - NO field definitions, NO settings, NO embed codes
 * - NO custom domain information from location or company endpoints
 * 
 * We manually generate embed URLs using a custom_domain setting (defaults to link.leadconnectorhq.com).
 * Until GHL provides more comprehensive form data via their API, customization is limited.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/API/Resources
 */
class FormsResource {
	/**
	 * API client instance
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Cache key for forms list
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'ghl_crm_forms_list';

	/**
	 * Cache expiration time (30 minutes)
	 *
	 * @var int
	 */
	private const CACHE_EXPIRATION = 1800;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->client = Client::get_instance();
	}

	/**
	 * Get all forms from GoHighLevel
	 *
	 * @param bool $force_refresh Whether to bypass cache and force fresh data.
	 * @return array List of forms
	 * @throws APIException When API request fails.
	 */
	public function get_forms( bool $force_refresh = false ): array {
		// Check cache first unless force refresh
		if ( ! $force_refresh ) {
			$cached_forms = get_transient( self::CACHE_KEY );
			if ( false !== $cached_forms && is_array( $cached_forms ) ) {
				return $cached_forms;
			}
		}

		// Get location ID from settings (multisite-safe).
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$location_id      = $settings['location_id'] ?? '';
		if ( empty( $location_id ) ) {
			throw new APIException( __( 'Location ID not configured.', 'ghl-crm-integration' ) );
		}

		// Fetch forms from API
		$endpoint = '/forms/';
		$params   = [
			'locationId' => $location_id,
			'limit'      => 50, // Maximum allowed
		];
		$response = $this->client->get( $endpoint, $params, false );

	if ( ! isset( $response['forms'] ) || ! is_array( $response['forms'] ) ) {
		// If no 'forms' key, check if response is array of forms directly
		if ( is_array( $response ) && ! empty( $response ) ) {
			$forms = $response;
		} else {
			$forms = [];
		}
	} else {
		$forms = $response['forms'];
	}

	// Process and normalize forms data
	$processed_forms = $this->process_forms( $forms );

	// Cache the results
	set_transient( self::CACHE_KEY, $processed_forms, self::CACHE_EXPIRATION );

	return $processed_forms;
	}

	/**
	 * Get a single form by ID
	 *
	 * @param string $form_id The form ID.
	 * @return array|null Form data or null if not found.
	 * @throws APIException When API request fails.
	 */
	public function get_form( string $form_id ): ?array {
		if ( empty( $form_id ) ) {
			return null;
		}

		// Get location ID from settings (multisite-safe).
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$location_id      = $settings['location_id'] ?? '';
		if ( empty( $location_id ) ) {
			throw new APIException( __( 'Location ID not configured.', 'ghl-crm-integration' ) );
		}

		// Try to get from cache first
		$cached_forms = get_transient( self::CACHE_KEY );
		if ( false !== $cached_forms && is_array( $cached_forms ) ) {
			foreach ( $cached_forms as $form ) {
				if ( isset( $form['id'] ) && $form['id'] === $form_id ) {
					return $form;
				}
			}
		}

		// Fetch specific form from API
		$endpoint = '/forms/';
		$params   = [
			'locationId' => $location_id,
			'limit'      => 50,
		];
		$response = $this->client->get( $endpoint, $params, false );

		// Search for the specific form in the response
		if ( isset( $response['forms'] ) && is_array( $response['forms'] ) ) {
			foreach ( $response['forms'] as $form ) {
				if ( isset( $form['id'] ) && $form['id'] === $form_id ) {
					return $this->process_form( $form );
				}
			}
		}

		return null;
	}

	/**
	 * Clear forms cache
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_cache(): bool {
		return delete_transient( self::CACHE_KEY );
	}

	/**
	 * Process multiple forms data
	 *
	 * @param array $forms Raw forms data from API.
	 * @return array Processed forms data.
	 */
	private function process_forms( array $forms ): array {
		$processed = [];

		foreach ( $forms as $form ) {
			if ( ! isset( $form['id'] ) ) {
				continue;
			}

			$processed[] = $this->process_form( $form );
		}

		return $processed;
	}

	/**
	 * Process single form data
	 *
	 * Normalizes form data structure and generates embed code
	 *
	 * @param array $form Raw form data from API.
	 * @return array Processed form data.
	 */
	private function process_form( array $form ): array {
		$form_id     = $form['id'] ?? '';
		$location_id = $form['locationId'] ?? '';
		
		// Get the custom domain from settings (multisite-safe).
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$custom_domain    = $settings['custom_domain'] ?? 'link.leadconnectorhq.com'; // GHL default
		
		// Generate embed URL (without notrack parameter)
		$embed_url   = ! empty( $form_id ) 
			? "https://{$custom_domain}/widget/form/{$form_id}" 
			: '';
		
		$iframe_code = ! empty( $embed_url )
			? sprintf(
				'<iframe src="%s" style="width:100%%;height:100%%;border:none;overflow:hidden;" scrolling="no"></iframe>',
				esc_url( $embed_url )
			)
			: '';
		
		return [
			'id'          => $form_id,
			'name'        => $form['name'] ?? __( 'Untitled Form', 'ghl-crm-integration' ),
			'locationId'  => $location_id,
			'submissions' => $form['submissions'] ?? 0,
			'createdAt'   => $form['createdAt'] ?? '',
			'updatedAt'   => $form['updatedAt'] ?? '',
			'embedUrl'    => $embed_url,
			'embedCode'   => $iframe_code,
			'fields'      => $form['fields'] ?? [],
			'settings'    => $form['settings'] ?? [],
		];
	}

	/**
	 * Get form submission count
	 *
	 * @param string $form_id The form ID.
	 * @return int Number of submissions.
	 */
	public function get_submission_count( string $form_id ): int {
		try {
			$form = $this->get_form( $form_id );
			return isset( $form['submissions'] ) ? (int) $form['submissions'] : 0;
		} catch ( APIException $e ) {
			return 0;
		}
	}
}
