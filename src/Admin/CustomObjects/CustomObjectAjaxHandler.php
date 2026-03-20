<?php
declare(strict_types=1);

namespace GHL_CRM\Admin\CustomObjects;

use GHL_CRM\Core\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Object AJAX Handler
 *
 * Handles all Custom Object mapping CRUD and schema retrieval AJAX endpoints.
 * Extracted from SettingsManager to reduce file size and improve cohesion.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core/Settings
 */
class CustomObjectAjaxHandler {

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
		add_action( 'wp_ajax_ghl_crm_get_custom_objects', [ $this, 'get_custom_objects' ] );
		add_action( 'wp_ajax_ghl_crm_get_schema_details', [ $this, 'get_schema_details' ] );
		add_action( 'wp_ajax_ghl_crm_get_post_types', [ $this, 'get_post_types' ] );
		add_action( 'wp_ajax_ghl_crm_get_cpt_fields', [ $this, 'get_cpt_fields' ] );
		add_action( 'wp_ajax_ghl_crm_save_mapping', [ $this, 'save_mapping' ] );
		add_action( 'wp_ajax_ghl_crm_get_mappings', [ $this, 'get_mappings' ] );
		add_action( 'wp_ajax_ghl_crm_delete_mapping', [ $this, 'delete_mapping' ] );
	}

	/**
	 * AJAX handler: Get Custom Object schemas from GHL API.
	 *
	 * @return void
	 */
	public function get_custom_objects(): void {
		check_ajax_referer( 'ghl_crm_custom_objects', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access Custom Objects.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
		$oauth_status  = $oauth_handler->get_connection_status();
		$settings      = SettingsManager::get_instance()->get_settings_array();
		$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );

		if ( ! $is_connected ) {
			wp_send_json_error(
				[
					'message' => __( 'Not connected to GoHighLevel. Please connect first.', 'ghl-crm-integration' ),
				],
				400
			);
		}

		$force_refresh = isset( $_POST['force_refresh'] ) && '1' === $_POST['force_refresh'];

		try {
			$custom_object_resource = new \GHL_CRM\API\Resources\CustomObjectResource(
				\GHL_CRM\API\Client\Client::get_instance()
			);

			$reflection        = new \ReflectionClass( $custom_object_resource );
			$endpoint_property = $reflection->getProperty( 'endpoint' );
			$endpoint_property->setAccessible( true );
			$endpoint_value = $endpoint_property->getValue( $custom_object_resource );

			$schemas = $custom_object_resource->get_schemas( ! $force_refresh );

			wp_send_json_success(
				[
					'schemas' => $schemas,
					'count'   => count( $schemas ),
					'cached'  => ! $force_refresh,
				]
			);
		} catch ( \Exception $e ) {

			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'Failed to fetch Custom Objects: %s', 'ghl-crm-integration' ),
						$e->getMessage()
					),
				],
				500
			);
		}
	}

	/**
	 * AJAX handler: Get detailed schema for a specific custom object.
	 *
	 * @return void
	 */
	public function get_schema_details(): void {
		check_ajax_referer( 'ghl_crm_custom_objects', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access Custom Objects.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		$schema_id = sanitize_text_field( $_POST['schema_id'] ?? '' );

		if ( empty( $schema_id ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Schema ID is required.', 'ghl-crm-integration' ),
				],
				400
			);
		}

		try {
			$client                 = \GHL_CRM\API\Client\Client::get_instance();
			$custom_object_resource = new \GHL_CRM\API\Resources\CustomObjectResource( $client );

			$schema = $custom_object_resource->get_schema( $schema_id );

			if ( ! $schema ) {
				wp_send_json_error(
					[
						'message' => __( 'Schema not found.', 'ghl-crm-integration' ),
					],
					404
				);
			}

			// Try to fetch detailed schema with fields.
			try {
				$detailed_schema = $client->get( 'objects/' . $schema_id, [], false );
				if ( ! empty( $detailed_schema['fields'] ) || ! empty( $detailed_schema['properties'] ) ) {
					$schema = array_merge( $schema, $detailed_schema );
				}
			} catch ( \Exception $fields_error ) {
				// Continue without detailed fields.
			}

			// Fetch custom object fields from the Custom Fields V2 API.
			try {
				$settings    = SettingsManager::get_instance()->get_settings_array();
				$location_id = $settings['location_id'] ?? '';
				$object_key  = $schema['key'] ?? '';

				if ( ! empty( $location_id ) && ! empty( $object_key ) ) {
					$custom_fields_response = $client->get(
						'custom-fields/object-key/' . $object_key,
						[ 'locationId' => $location_id ],
						false
					);

					if ( ! empty( $custom_fields_response['fields'] ) ) {
						$schema['fields'] = $custom_fields_response['fields'];
					}

					if ( ! empty( $custom_fields_response['folders'] ) ) {
						$schema['folders'] = $custom_fields_response['folders'];
					}
				}
			} catch ( \Exception $custom_fields_error ) {
				// Continue without custom fields.
			}

			// Fetch associations.
			$relevant_associations = [];

			try {
				$object_key = $schema['key'] ?? '';

				if ( ! empty( $object_key ) ) {
					$associations_response = $client->get( 'associations/objectKey/' . $object_key );
					$relevant_associations = $associations_response['associations'] ?? [];
				}
			} catch ( \Exception $assoc_error ) {
				// Continue without associations.
			}

			wp_send_json_success(
				[
					'schema'           => $schema,
					'has_associations' => ! empty( $relevant_associations ),
					'associations'     => $relevant_associations,
					'schema_id'        => $schema_id,
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message'   => sprintf(
						/* translators: %s: Error message */
						__( 'Failed to fetch schema details: %s', 'ghl-crm-integration' ),
						$e->getMessage()
					),
					'schema_id' => $schema_id,
				],
				500
			);
		} catch ( \Error $t ) {
			wp_send_json_error(
				[
					'message'   => sprintf(
						/* translators: %s: Error message */
						__( 'An unexpected error occurred while fetching schema details: %s', 'ghl-crm-integration' ),
						$t->getMessage()
					),
					'schema_id' => $schema_id,
				],
				500
			);
		}
	}

	/**
	 * AJAX handler: Get WordPress post types for mapping.
	 *
	 * @return void
	 */
	public function get_post_types(): void {
		check_ajax_referer( 'ghl_crm_mappings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ], 403 );
		}

		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$filtered   = [];

		foreach ( $post_types as $key => $post_type ) {
			if ( ! in_array( $key, [ 'attachment', 'nav_menu_item' ], true ) ) {
				$filtered[ $key ] = $post_type->label;
			}
		}

		wp_send_json_success( [ 'post_types' => $filtered ] );
	}

	/**
	 * AJAX handler: Get available fields for a custom post type.
	 *
	 * @return void
	 */
	public function get_cpt_fields(): void {
		check_ajax_referer( 'ghl_crm_mappings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ], 403 );
		}

		$post_type = sanitize_text_field( $_POST['post_type'] ?? '' );

		if ( empty( $post_type ) ) {
			wp_send_json_error( [ 'message' => __( 'Post type is required', 'ghl-crm-integration' ) ], 400 );
		}

		$fields = \GHL_CRM_Pro\Sync\CustomObjectFieldDiscovery::get_fields_for_post_type( $post_type );

		wp_send_json_success( [ 'fields' => $fields ] );
	}

	/**
	 * AJAX handler: Save custom object mapping.
	 *
	 * @return void
	 */
	public function save_mapping(): void {
		check_ajax_referer( 'ghl_crm_mappings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ], 403 );
		}

		$settings_manager = SettingsManager::get_instance();

		$mapping_id   = sanitize_text_field( $_POST['mapping_id'] ?? '' );
		$mapping_data = [
			'id'                  => $mapping_id ? $mapping_id : 'mapping_' . time(),
			'name'                => sanitize_text_field( $_POST['mapping_name'] ?? '' ),
			'wp_post_type'        => sanitize_text_field( $_POST['wp_post_type'] ?? '' ),
			'wp_post_type_label'  => get_post_type_object( $_POST['wp_post_type'] ?? '' )->label ?? '',
			'ghl_object'          => sanitize_text_field( $_POST['ghl_object'] ?? '' ),
			'ghl_object_key'      => sanitize_text_field( $_POST['ghl_object_key'] ?? '' ),
			'active'              => isset( $_POST['mapping_active'] ) && 'true' === $_POST['mapping_active'],
			'triggers'            => array_map( 'sanitize_text_field', $_POST['triggers'] ?? [] ),

			// Legacy fields for backward compatibility.
			'contact_source'      => sanitize_text_field( $_POST['contact_source'] ?? '' ),
			'contact_field'       => sanitize_text_field( $_POST['contact_field'] ?? '' ),
			'contact_not_found'   => sanitize_text_field( $_POST['contact_not_found'] ?? 'skip' ),

			// Multi-association support.
			'associations'        => [],

			'field_mappings'      => [],
			'enable_batch_sync'   => isset( $_POST['enable_batch_sync'] ) && 'true' === $_POST['enable_batch_sync'],
			'log_sync_operations' => isset( $_POST['log_sync_operations'] ) && 'true' === $_POST['log_sync_operations'],
			'created_at'          => $mapping_id ? null : current_time( 'mysql' ),
			'updated_at'          => current_time( 'mysql' ),
		];

		// Process associations (new format).
		if ( ! empty( $_POST['associations'] ) && is_array( $_POST['associations'] ) ) {
			foreach ( $_POST['associations'] as $assoc ) {
				$mapping_data['associations'][] = [
					'target_type'      => sanitize_text_field( $assoc['target_type'] ?? '' ),
					'source'           => sanitize_text_field( $assoc['source'] ?? '' ),
					'source_field'     => sanitize_text_field( $assoc['source_field'] ?? '' ),
					'not_found_action' => sanitize_text_field( $assoc['not_found_action'] ?? 'skip' ),
					'association_key'  => sanitize_text_field( $assoc['association_key'] ?? '' ),
				];
			}
		} elseif ( ! empty( $mapping_data['contact_source'] ) ) {
			// Backward compatibility: convert old format to new associations format.
			$mapping_data['associations'][] = [
				'target_type'      => 'contact',
				'source'           => $mapping_data['contact_source'],
				'source_field'     => $mapping_data['contact_field'],
				'not_found_action' => $mapping_data['contact_not_found'],
				'association_key'  => '',
			];
		}

		// Process field mappings.
		if ( ! empty( $_POST['field_mappings'] ) && is_array( $_POST['field_mappings'] ) ) {
			foreach ( $_POST['field_mappings'] as $field_map ) {
				$mapping_data['field_mappings'][] = [
					'wp_field'      => sanitize_text_field( $field_map['wp_field'] ?? '' ),
					'wp_field_name' => sanitize_text_field( $field_map['wp_field_name'] ?? '' ),
					'ghl_field'     => sanitize_text_field( $field_map['ghl_field'] ?? '' ),
					'transform'     => sanitize_text_field( $field_map['transform'] ?? 'none' ),
				];
			}
		}

		// Get existing mappings.
		$mappings = $settings_manager->get_option( 'ghl_crm_custom_object_mappings', [] );

		// Update or add mapping.
		$found = false;
		foreach ( $mappings as $key => $existing ) {
			if ( $existing['id'] === $mapping_data['id'] ) {
				$mappings[ $key ] = $mapping_data;
				$found            = true;
				break;
			}
		}

		if ( ! $found ) {
			$mappings[] = $mapping_data;
		}

		$settings_manager->update_option( 'ghl_crm_custom_object_mappings', $mappings );

		wp_send_json_success(
			[
				'message' => __( 'Mapping saved successfully', 'ghl-crm-integration' ),
				'mapping' => $mapping_data,
			]
		);
	}

	/**
	 * AJAX handler: Get all custom object mappings.
	 *
	 * @return void
	 */
	public function get_mappings(): void {
		check_ajax_referer( 'ghl_crm_mappings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ], 403 );
		}

		$mappings = SettingsManager::get_instance()->get_option( 'ghl_crm_custom_object_mappings', [] );

		wp_send_json_success( [ 'mappings' => $mappings ] );
	}

	/**
	 * AJAX handler: Delete custom object mapping.
	 *
	 * @return void
	 */
	public function delete_mapping(): void {
		check_ajax_referer( 'ghl_crm_mappings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ], 403 );
		}

		$settings_manager = SettingsManager::get_instance();
		$mapping_id       = sanitize_text_field( $_POST['mapping_id'] ?? '' );
		$mappings         = $settings_manager->get_option( 'ghl_crm_custom_object_mappings', [] );

		$mappings = array_filter(
			$mappings,
			function ( $mapping ) use ( $mapping_id ) {
				return $mapping['id'] !== $mapping_id;
			}
		);

		$settings_manager->update_option( 'ghl_crm_custom_object_mappings', array_values( $mappings ) );

		wp_send_json_success( [ 'message' => __( 'Mapping deleted successfully', 'ghl-crm-integration' ) ] );
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
