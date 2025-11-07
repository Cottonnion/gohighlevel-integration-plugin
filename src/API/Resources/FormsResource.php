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
 * We manually generate embed URLs using the configured white-label domain (defaults to link.leadconnectorhq.com).
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
	 * Cached base URL for form embeds derived from the white-label domain.
	 *
	 * @var string|null
	 */
	private ?string $form_embed_base_url = null;

	/**
	 * Cache key for forms list
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'ghl_crm_forms_list';

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

		// Fetch submission counts for each form from the submissions endpoint
		foreach ( $processed_forms as $index => $form ) {
			if ( ! empty( $form['id'] ) ) {
				try {
					$submissions_data = $this->get_form_submissions( $form['id'], 1, 1 );
					// The meta.total contains the total count of submissions for this form
					$processed_forms[ $index ]['submissions'] = $submissions_data['meta']['total'] ?? 0;
				} catch ( \Exception $e ) {
					// Keep zero if submissions fetch fails
					$processed_forms[ $index ]['submissions'] = 0;
					// Log error if debug mode
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'Failed to fetch submissions for form %s: %s', $form['id'], $e->getMessage() ) );
					}
				}
			}
		}

		// Cache the results using configured duration
		$cache_duration = absint( $settings_manager->get_setting( 'cache_duration', HOUR_IN_SECONDS ) );
		set_transient( self::CACHE_KEY, $processed_forms, $cache_duration );

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
		$embed_host  = $this->get_form_embed_base_url();
		
		// Ensure both widget and direct form URLs honour the white-label host.
		$widget_url = '';
		$form_url   = '';
		if ( ! empty( $form_id ) ) {
			$widget_url = \trailingslashit( $embed_host ) . 'widget/form/' . rawurlencode( $form_id );
			$form_url   = \trailingslashit( $embed_host ) . 'form/' . rawurlencode( $form_id );
		}

		$iframe_code = ! empty( $widget_url )
			? sprintf(
				'<iframe src="%s" style="width:100%%;height:100%%;border:none;overflow:hidden;" scrolling="no"></iframe>',
				esc_url( $widget_url )
			)
			: '';
		
		return [
			'id'          => $form_id,
			'name'        => $form['name'] ?? __( 'Untitled Form', 'ghl-crm-integration' ),
			'locationId'  => $location_id,
			'submissions' => 0, // Will be fetched separately
			'createdAt'   => $form['createdAt'] ?? '',
			'updatedAt'   => $form['updatedAt'] ?? '',
			'embedUrl'    => $widget_url,
			'widgetUrl'   => $widget_url,
			'formUrl'     => $form_url,
			'embedCode'   => $iframe_code,
			'fields'      => $form['fields'] ?? [],
			'settings'    => $form['settings'] ?? [],
		];
	}

	/**
	 * Build the base URL for form embeds using the white-label domain (app → link).
	 */
	private function get_form_embed_base_url(): string {
		if ( null !== $this->form_embed_base_url ) {
			return $this->form_embed_base_url;
		}

		$settings      = \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();
		$white_label   = $settings['ghl_white_label_domain'] ?? '';
		$scheme        = 'https';
		$host          = '';

		if ( ! empty( $white_label ) ) {
			$parsed = \wp_parse_url( $white_label );
			if ( is_array( $parsed ) ) {
				if ( ! empty( $parsed['scheme'] ) ) {
					$scheme = $parsed['scheme'];
				}
				$host = $parsed['host'] ?? '';
			}

			if ( empty( $host ) ) {
				$normalized = preg_replace( '#^https?://#i', '', $white_label );
				if ( is_string( $normalized ) ) {
					$host_candidate = strtok( $normalized, '/' );
					if ( false !== $host_candidate ) {
						$host = (string) $host_candidate;
					}
				}
			}
		}

		if ( empty( $host ) ) {
			$this->form_embed_base_url = 'https://link.leadconnectorhq.com';
			return $this->form_embed_base_url;
		}

		$host_parts = explode( '.', $host );
		if ( ! empty( $host_parts ) ) {
			if ( isset( $host_parts[0] ) && 'link' === strtolower( $host_parts[0] ) ) {
				// Already using link subdomain.
			} elseif ( count( $host_parts ) >= 3 ) {
				$host_parts[0] = 'link';
			} else {
				array_unshift( $host_parts, 'link' );
			}
		}

		$link_host = implode( '.', $host_parts );
		$this->form_embed_base_url = $scheme . '://' . $link_host;

		return $this->form_embed_base_url;
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

	/**
	 * Get form submissions from the dedicated submissions endpoint
	 *
	 * @param string $form_id The form ID (optional - if empty, gets all submissions).
	 * @param int    $page    Page number for pagination.
	 * @param int    $limit   Number of submissions per page (max 100).
	 * @return array Submissions data with count and submissions array.
	 * @throws APIException When API request fails.
	 */
	public function get_form_submissions( string $form_id = '', int $page = 1, int $limit = 20 ): array {
		$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$location_id      = $settings['location_id'] ?? '';
		
		if ( empty( $location_id ) ) {
			throw new APIException( __( 'Location ID not configured.', 'ghl-crm-integration' ) );
		}

		$endpoint = '/forms/submissions';
		$params   = [
			'locationId' => $location_id,
			'page'       => max( 1, $page ),
			'limit'      => min( 100, max( 1, $limit ) ),
		];

		if ( ! empty( $form_id ) ) {
			$params['formId'] = $form_id;
		}

		$response = $this->client->get( $endpoint, $params, false );

		return [
			'submissions' => $response['submissions'] ?? [],
			'meta'        => $response['meta'] ?? [
				'total'       => 0,
				'currentPage' => $page,
				'nextPage'    => null,
				'prevPage'    => null,
			],
		];
	}
}

