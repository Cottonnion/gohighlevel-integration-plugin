<?php
declare(strict_types=1);

namespace Syncly\Core\Settings;

use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metadata Service
 *
 * Handles GHL tags, custom fields, and metadata refresh AJAX endpoints.
 * Extracted from SettingsManager to reduce file size and improve cohesion.
 *
 * @package    Syncly
 * @subpackage Syncly/Core/Settings
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
		add_action( 'wp_ajax_syncly_get_tags', [ $this, 'get_tags' ] );
		add_action( 'wp_ajax_syncly_get_custom_fields', [ $this, 'get_custom_fields' ] );
		add_action( 'wp_ajax_syncly_refresh_metadata', [ $this, 'refresh_metadata' ] );
		add_action( 'wp_ajax_syncly_create_custom_field', [ $this, 'create_custom_field' ] );
	}

	/**
	 * AJAX handler: Fetch GHL tags.
	 *
	 * Supports multiple nonce contexts (settings page, user profile, SPA).
	 *
	 * @return void
	 */
	public function get_tags(): void {
		$request_data = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );

		if ( empty( $request_data ) ) {
			// phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative -- Reading raw JSON body from php://input is required for non-form AJAX payloads.
			$raw_body = file_get_contents( 'php://input' );
			// Guard against oversized payloads before decoding.
			if ( ! empty( $raw_body ) && strlen( $raw_body ) <= 1048576 ) {
				$decoded = json_decode( $raw_body, true );
				// json_decode does not sanitize; sanitize all values after decoding.
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					$request_data = map_deep( $decoded, 'sanitize_text_field' );
				}
			}
		}

		$nonce = isset( $request_data['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $request_data['nonce'] ) ) : '';

		if (
			! wp_verify_nonce( $nonce, 'syncly_settings_nonce' ) &&
			! wp_verify_nonce( $nonce, 'syncly_admin' ) &&
			! wp_verify_nonce( $nonce, 'syncly_user_profile' ) &&
			! wp_verify_nonce( $nonce, 'syncly_spa_nonce' )
		) {
			wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
		}

		// Allow edit_users capability for user profile pages.
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access tags.', 'syncly' ),
				],
				403
			);
		}

		try {
			$tag_manager = TagManager::get_instance();
			$force       = ! empty( $request_data['force_refresh'] );
			$tags        = $tag_manager->get_tags( $force );

			$search_term = isset( $request_data['search'] ) ? sanitize_text_field( (string) $request_data['search'] ) : '';
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
					'message' => __( 'Tags loaded successfully.', 'syncly' ),
					'cached'  => $tag_manager->last_fetch_was_cached(),
				]
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to fetch tags: %s', 'syncly' ),
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
	public function get_syncly_fields_cached( bool $force_refresh = false ): array {
		$settings_manager = SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$location_id      = $settings['location_id'] ?? '';
		$site_id          = get_current_blog_id();

		$transient_key = 'syncly_fields_' . $location_id . '_site_' . $site_id;

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
			$client   = \Syncly\API\Client\Client::get_instance();
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
	 * Forces a fresh API fetch via get_syncly_fields_cached( true ).
	 *
	 * @return void
	 */
	public function get_custom_fields(): void {
		check_ajax_referer( 'syncly_field_mapping_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this data.', 'syncly' ),
				],
				403
			);
		}

		$result = $this->get_syncly_fields_cached( true );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Refresh tags and custom fields metadata.
	 *
	 * @return void
	 */
	public function refresh_metadata(): void {
		check_ajax_referer( 'syncly_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to perform this action.', 'syncly' ),
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
						'message' => __( 'Location ID not configured. Please connect to GoHighLevel first.', 'syncly' ),
					],
					400
				);
			}

			$client = \Syncly\API\Client\Client::get_instance();

			// Fetch tags directly from API and refresh transient cache.
			$tags_response = $client->get( 'locations/' . $location_id . '/tags' );
			$tags          = [];

			if ( isset( $tags_response['tags'] ) && is_array( $tags_response['tags'] ) ) {
				$tags = $tags_response['tags'];
			}

			$site_id       = get_current_blog_id();
			$cache_seconds = absint( $settings_manager->get_setting( 'cache_duration', HOUR_IN_SECONDS ) );
			$tags_key      = 'syncly_tags_' . $location_id . '_site_' . $site_id;

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
			$fields_transient_key = 'syncly_fields_' . $location_id . '_site_' . $site_id;
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
					'message'             => __( 'Tags and fields refreshed successfully.', 'syncly' ),
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
						__( 'Failed to refresh metadata: %s', 'syncly' ),
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
						__( 'A fatal error occurred while refreshing metadata: %s', 'syncly' ),
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
						__( 'An unexpected error occurred while refreshing metadata: %s', 'syncly' ),
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
			''            => __( '— Do Not Sync —', 'syncly' ),
			'firstName'   => __( 'First Name', 'syncly' ),
			'lastName'    => __( 'Last Name', 'syncly' ),
			'name'        => __( 'Full Name', 'syncly' ),
			'email'       => __( 'Email', 'syncly' ),
			'phone'       => __( 'Phone', 'syncly' ),
			'address1'    => __( 'Address Line 1', 'syncly' ),
			'city'        => __( 'City', 'syncly' ),
			'state'       => __( 'State', 'syncly' ),
			'country'     => __( 'Country', 'syncly' ),
			'postalCode'  => __( 'Postal Code', 'syncly' ),
			'website'     => __( 'Website', 'syncly' ),
			'timezone'    => __( 'Timezone', 'syncly' ),
			'companyName' => __( 'Company Name', 'syncly' ),
			'source'      => __( 'Source', 'syncly' ),
			'dateOfBirth' => __( 'Date of Birth', 'syncly' ),
		];
	}

	/**
	 * Normalize a field name for duplicate matching.
	 *
	 * @param string $value Raw field name or label.
	 * @return string Normalized string.
	 */
	private function normalize_custom_field_name( string $value ): string {
		$value = strtolower( trim( preg_replace( '/\s+/', ' ', $value ) ) );
		$value = preg_replace( '/[^a-z0-9]+/', '', $value ) ?? '';
		return $value;
	}

	/**
	 * Find an existing custom field with the same name in the currently loaded GHL field set.
	 *
	 * @param string $field_name Requested custom field name.
	 * @param array  $fields_data Cached fields payload from MetadataService.
	 * @return array{key:string,label:string}|null Matching field details, if any.
	 */
	private function find_matching_custom_field( string $field_name, array $fields_data ): ?array {
		$normalized_name = $this->normalize_custom_field_name( $field_name );
		if ( '' === $normalized_name ) {
			return null;
		}

		foreach ( $fields_data['fields'] ?? [] as $key => $label ) {
			if ( strpos( $key, 'custom.' ) !== 0 ) {
				continue;
			}

			$existing_label = str_replace( ' (Custom)', '', (string) $label );
			if ( $this->normalize_custom_field_name( $existing_label ) === $normalized_name ) {
				return [
					'key'   => $key,
					'label' => $existing_label,
				];
			}
		}

		return null;
	}

	/**
	 * AJAX handler: Create a new custom field in GHL and return its key/label.
	 *
	 * Calls POST /locations/{locationId}/customFields with an allowed dataType.
	 * On success, busts the fields transient so the new field appears on
	 * the next server-side render, then returns { key, label } to the JS.
	 *
	 * @return void
	 */
	public function create_custom_field(): void {
		check_ajax_referer( 'syncly_field_mapping_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to create custom fields.', 'syncly' ) ], 403 );
		}

		$field_name = isset( $_POST['field_name'] ) ? sanitize_text_field( wp_unslash( $_POST['field_name'] ) ) : '';
		$field_type = isset( $_POST['field_type'] ) ? sanitize_text_field( wp_unslash( $_POST['field_type'] ) ) : '';
		$allowed_field_types = [ 'TEXT', 'LARGE_TEXT', 'NUMERICAL', 'PHONE', 'MONETORY', 'CHECKBOX', 'SINGLE_OPTIONS', 'MULTIPLE_OPTIONS', 'FLOAT', 'TIME', 'DATE', 'TEXTBOX_LIST', 'FILE_UPLOAD', 'SIGNATURE', 'RADIO' ];
		$field_type = strtoupper( $field_type );
		if ( ! in_array( $field_type, $allowed_field_types, true ) ) {
			$field_type = 'TEXT';
		}

		if ( '' === $field_name || mb_strlen( $field_name ) > 255 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid field name.', 'syncly' ) ], 400 );
		}

		$settings_manager = SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$location_id      = $settings['location_id'] ?? '';

		if ( empty( $location_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Location ID not configured. Please connect to GoHighLevel first.', 'syncly' ) ], 400 );
		}

		try {
			$existing_field = $this->find_matching_custom_field( $field_name, $this->get_syncly_fields_cached( true ) );
			if ( null !== $existing_field ) {
				wp_send_json_success(
					[
						'key'      => $existing_field['key'],
						'label'    => $existing_field['label'] . ' (Custom)',
						'existing' => true,
						'created'  => false,
						'message'  => __( 'That custom field already exists in GoHighLevel.', 'syncly' ),
					]
				);
			}

			$client   = \Syncly\API\Client\Client::get_instance();
			// Pass false so locationId is not added to body or query — it is already in the path.
			$response = $client->post(
				'locations/' . $location_id . '/customFields',
				[
					'name'     => $field_name,
					'dataType' => $field_type,
				],
				false
			);

			$field = $response['customField'] ?? [];
			$id    = $field['id'] ?? '';
			$name  = isset( $field['name'] ) ? sanitize_text_field( $field['name'] ) : $field_name;

			if ( empty( $id ) ) {
				wp_send_json_error( [ 'message' => __( 'GoHighLevel did not return a field ID.', 'syncly' ) ], 500 );
			}

			// Bust the fields transient so the new field is included on next server-side render.
			$site_id       = get_current_blog_id();
			$transient_key = 'syncly_fields_' . $location_id . '_site_' . $site_id;
			delete_transient( $transient_key );

			wp_send_json_success(
				[
					'key'       => 'custom.' . sanitize_key( $id ),
					'label'     => $name . ' (Custom)',
					'created'   => true,
					'existing'  => false,
					'field_type' => $field_type,
					'message'   => __( 'Custom field created successfully.', 'syncly' ),
				]
			);

		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Failed to create custom field: %s', 'syncly' ),
						$e->getMessage()
					),
				],
				500
			);
		}
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
