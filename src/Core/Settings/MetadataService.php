<?php
declare(strict_types=1);

namespace GHL_CRM\Core\Settings;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metadata Service
 *
 * Handles GHL tags, custom fields, and metadata refresh AJAX endpoints.
 * Extracted from SettingsManager to reduce file size and improve cohesion.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core/Settings
 */
class MetadataService {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor. */
	private function __construct() {}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_ghl_crm_get_tags', [ $this, 'get_tags' ] );
		add_action( 'wp_ajax_ghl_crm_get_custom_fields', [ $this, 'get_custom_fields' ] );
		add_action( 'wp_ajax_ghl_crm_refresh_metadata', [ $this, 'refresh_metadata' ] );
	}

	/**
	 * AJAX handler: Fetch GHL tags.
	 *
	 * Supports multiple nonce contexts (settings page, user profile, SPA).
	 *
	 * @return void
	 */
	public function get_tags(): void {
		$request_data = $_POST;

		if ( empty( $request_data ) ) {
			// phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative -- Reading raw JSON body from php://input is required for non-form AJAX payloads.
			$raw_body = file_get_contents( 'php://input' );
			if ( ! empty( $raw_body ) ) {
				$decoded = json_decode( $raw_body, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					$request_data = $decoded;
				}
			}
		}

		$nonce = $request_data['nonce'] ?? '';

		if (
			! wp_verify_nonce( $nonce, 'ghl_crm_settings_nonce' ) &&
			! wp_verify_nonce( $nonce, 'ghl_crm_admin' ) &&
			! wp_verify_nonce( $nonce, 'ghl_user_profile' ) &&
			! wp_verify_nonce( $nonce, 'ghl_crm_spa_nonce' )
		) {
			wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
		}

		// Allow edit_users capability for user profile pages.
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access tags.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		try {
			$tag_manager = TagManager::get_instance();
			$force       = ! empty( $request_data['force_refresh'] );
			$tags        = $tag_manager->get_tags( $force );

			$search_term = isset( $request_data['search'] ) ? sanitize_text_field( wp_unslash( $request_data['search'] ) ) : '';
			if ( '' !== $search_term ) {
				$tags = $tag_manager->search_tags( $search_term );
			}

			$normalized_tags = array_map(
				static function ( array $tag ): array {
					return [
						'id'   => isset( $tag['id'] ) ? (string) $tag['id'] : '',
						'name' => isset( $tag['name'] ) ? (string) $tag['name'] : '',
					];
				},
				$tags
			);

			wp_send_json_success(
				[
					'tags'    => $normalized_tags,
					'message' => __( 'Tags loaded successfully.', 'ghl-crm-integration' ),
					'cached'  => $tag_manager->last_fetch_was_cached(),
				]
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to fetch tags: %s', 'ghl-crm-integration' ),
						$e->getMessage()
					),
				],
				500
			);
		}
	}

	/**
	 * Get GHL fields with transient caching.
	 *
	 * Returns cached fields if available, otherwise fetches from API and caches.
	 * Used by the PHP template to render <option> elements server-side.
	 *
	 * @param bool $force_refresh Whether to bypass the cache and fetch fresh data.
	 * @return array{fields: array<string, string>, fieldTypes: array<string, string>, count: int}
	 */
	public function get_ghl_fields_cached( bool $force_refresh = false ): array {
		$settings_manager = SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$location_id      = $settings['location_id'] ?? '';
		$site_id          = get_current_blog_id();

		$transient_key = 'ghl_fields_' . $location_id . '_site_' . $site_id;

		if ( ! $force_refresh ) {
			$cached = get_transient( $transient_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = [
			'fields'     => $this->get_standard_ghl_fields(),
			'fieldTypes' => [],
			'count'      => 0,
		];

		if ( empty( $location_id ) ) {
			return $result;
		}

		try {
			$client   = \GHL_CRM\API\Client\Client::get_instance();
			$response = $client->get( 'locations/' . $location_id . '/customFields', [] );

			if ( ! empty( $response['customFields'] ) && is_array( $response['customFields'] ) ) {
				$field_types = [];

				foreach ( $response['customFields'] as $field ) {
					$field_id   = $field['id'] ?? '';
					$field_name = $field['name'] ?? '';
					$data_type  = $field['dataType'] ?? '';

					if ( $field_id && $field_name ) {
						$result['fields'][ 'custom.' . $field_id ] = $field_name . ' (Custom)';
						if ( $data_type ) {
							$field_types[ 'custom.' . $field_id ] = strtolower( $data_type );
						}
					}
				}

				$result['fieldTypes'] = $field_types;
				$result['count']      = count( $response['customFields'] );
			}

			$cache_duration = absint( $settings_manager->get_setting( 'cache_duration', HOUR_IN_SECONDS ) );
			set_transient( $transient_key, $result, $cache_duration );

		} catch ( \Exception $e ) {
			// Silently fall back to standard fields on API failure.
		}

		return $result;
	}

	/**
	 * AJAX handler: Get GHL custom fields for field mapping dropdowns.
	 *
	 * Forces a fresh API fetch via get_ghl_fields_cached( true ).
	 *
	 * @return void
	 */
	public function get_custom_fields(): void {
		check_ajax_referer( 'ghl_crm_field_mapping_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this data.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		$result = $this->get_ghl_fields_cached( true );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Refresh tags and custom fields metadata.
	 *
	 * @return void
	 */
	public function refresh_metadata(): void {
		check_ajax_referer( 'ghl_crm_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to perform this action.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		try {
			$settings_manager = SettingsManager::get_instance();
			$settings         = $settings_manager->get_settings_array();
			$location_id      = $settings['location_id'] ?? '';

			if ( empty( $location_id ) ) {
				wp_send_json_error(
					[
						'message' => __( 'Location ID not configured. Please connect to GoHighLevel first.', 'ghl-crm-integration' ),
					],
					400
				);
			}

			$client = \GHL_CRM\API\Client\Client::get_instance();

			// Fetch tags directly from API and refresh transient cache.
			$tags_response = $client->get( 'locations/' . $location_id . '/tags' );
			$tags          = [];

			if ( isset( $tags_response['tags'] ) && is_array( $tags_response['tags'] ) ) {
				$tags = $tags_response['tags'];
			}

			$site_id       = get_current_blog_id();
			$cache_seconds = absint( $settings_manager->get_setting( 'cache_duration', HOUR_IN_SECONDS ) );
			$tags_key      = 'ghl_tags_' . $location_id . '_site_' . $site_id;

			set_transient( $tags_key, $tags, $cache_seconds );

			// Fetch custom fields from API.
			$fields_response   = $client->get( 'locations/' . $location_id . '/customFields', [] );
			$custom_fields_raw = isset( $fields_response['customFields'] ) && is_array( $fields_response['customFields'] )
				? $fields_response['customFields']
				: [];

			$all_fields  = $this->get_standard_ghl_fields();
			$field_types = [];

			foreach ( $custom_fields_raw as $field ) {
				$field_id   = $field['id'] ?? '';
				$field_name = $field['name'] ?? '';
				$data_type  = $field['dataType'] ?? '';

				if ( $field_id && $field_name ) {
					$all_fields[ 'custom.' . $field_id ] = $field_name . ' (Custom)';
					if ( $data_type ) {
						$field_types[ 'custom.' . $field_id ] = strtolower( $data_type );
					}
				}
			}

			// Save the fields transient so subsequent page loads (e.g. Login Sync tab) pick up the refreshed data.
			$fields_transient_key = 'ghl_fields_' . $location_id . '_site_' . $site_id;
			set_transient(
				$fields_transient_key,
				[
					'fields'     => $all_fields,
					'fieldTypes' => $field_types,
					'count'      => count( $custom_fields_raw ),
				],
				$cache_seconds
			);

			wp_send_json_success(
				[
					'message'             => __( 'Tags and fields refreshed successfully.', 'ghl-crm-integration' ),
					'tags_count'          => count( $tags ),
					'custom_fields_count' => count( $custom_fields_raw ),
					'has_custom_fields'   => ! empty( $custom_fields_raw ),
				]
			);

		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to refresh metadata: %s', 'ghl-crm-integration' ),
						$e->getMessage()
					),
				],
				500
			);
		} catch ( \Error $err ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'A fatal error occurred while refreshing metadata: %s', 'ghl-crm-integration' ),
						$err->getMessage()
					),
				],
				500
			);
		} catch ( \Throwable $throwable ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'An unexpected error occurred while refreshing metadata: %s', 'ghl-crm-integration' ),
						$throwable->getMessage()
					),
				],
				500
			);
		}
	}

	/**
	 * Get standard GoHighLevel contact fields.
	 *
	 * @return array<string, string> Standard field mappings.
	 */
	public function get_standard_ghl_fields(): array {
		return [
			''            => __( '— Do Not Sync —', 'ghl-crm-integration' ),
			'firstName'   => __( 'First Name', 'ghl-crm-integration' ),
			'lastName'    => __( 'Last Name', 'ghl-crm-integration' ),
			'name'        => __( 'Full Name', 'ghl-crm-integration' ),
			'email'       => __( 'Email', 'ghl-crm-integration' ),
			'phone'       => __( 'Phone', 'ghl-crm-integration' ),
			'address1'    => __( 'Address Line 1', 'ghl-crm-integration' ),
			'city'        => __( 'City', 'ghl-crm-integration' ),
			'state'       => __( 'State', 'ghl-crm-integration' ),
			'country'     => __( 'Country', 'ghl-crm-integration' ),
			'postalCode'  => __( 'Postal Code', 'ghl-crm-integration' ),
			'website'     => __( 'Website', 'ghl-crm-integration' ),
			'timezone'    => __( 'Timezone', 'ghl-crm-integration' ),
			'companyName' => __( 'Company Name', 'ghl-crm-integration' ),
			'source'      => __( 'Source', 'ghl-crm-integration' ),
			'dateOfBirth' => __( 'Date of Birth', 'ghl-crm-integration' ),
		];
	}

	/** Prevent cloning. */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
