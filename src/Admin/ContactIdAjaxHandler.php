<?php
declare(strict_types=1);

namespace Syncly\Admin;

use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;
use Syncly\Frontend\ContactIdHandler;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX Handler for Contact ID Debugger
 *
 * Provides endpoints for testing campaign personalization links
 *
 * @package    Syncly
 * @subpackage Syncly/Admin
 */
class ContactIdAjaxHandler {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get class instance
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
		$this->init();
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_syncly_test_cid_link', array( $this, 'test_cid_link' ) );
	}

	/**
	 * Test a contact ID link and return what would resolve
	 *
	 * @return void
	 */
	public function test_cid_link(): void {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'syncly_settings_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed', 'syncly' ),
				)
			);
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions', 'syncly' ),
				)
			);
		}

		// Get contact ID
		$contact_id = isset( $_POST['contact_id'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_id'] ) ) : '';
		if ( empty( $contact_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Contact ID is required', 'syncly' ),
				)
			);
		}

		// Validate contact ID format (alphanumeric, hyphen, underscore)
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $contact_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid contact ID format', 'syncly' ),
				)
			);
		}

		// Get tag manager and try to find WP user
		$tag_manager      = TagManager::get_instance();
		$settings_manager = SettingsManager::get_instance();
		$wp_user_id       = $tag_manager->find_user_by_contact_id( $contact_id );

		$fields = array();

		if ( $wp_user_id ) {
			// Get all user meta for this contact
			$user_data = get_userdata( $wp_user_id );
			if ( $user_data ) {
				// Get field mappings from settings to know which WP fields to read
				$field_mappings = $settings_manager->get_setting( 'user_field_mapping', array() );

				// Read all mapped WP fields from user meta
				foreach ( $field_mappings as $wp_field => $mapping_data ) {
					if ( ! is_array( $mapping_data ) ) {
						continue;
					}

					$direction     = $mapping_data['direction'] ?? 'both';
					$direction_map = [
						'from_ghl' => 'ghl_to_wp',
						'to_ghl'   => 'wp_to_ghl',
						'both'     => 'both',
					];
					$direction     = $direction_map[ $direction ] ?? $direction;

					// Only include if synced from GHL to WP
					if ( 'ghl_to_wp' !== $direction && 'both' !== $direction ) {
						continue;
					}

					// Read the actual value from user meta
					$value = (string) get_user_meta( $wp_user_id, $wp_field, true );

					// Fallback to user object fields
					if ( empty( $value ) && isset( $user_data->$wp_field ) ) {
						$value = (string) $user_data->$wp_field;
					}

					$fields[ $wp_field ] = $value;
				}
			}
		}

		// Get hidden fields setting
		$hidden_fields_json = $settings_manager->get_setting( 'ghl_cid_hidden_fields', '' );
		$hidden_fields      = ! empty( $hidden_fields_json ) ? (array) json_decode( $hidden_fields_json, true ) : array();

		// Filter out hidden fields
		$fields = array_filter(
			$fields,
			function ( $key ) use ( $hidden_fields ) {
				return ! in_array( $key, $hidden_fields, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		// Get field mapping configuration to show admin what's mapped vs unmapped
		$field_mappings   = $settings_manager->get_setting( 'user_field_mapping', [] );
		$mapped_wp_fields = array_keys( $field_mappings );

		wp_send_json_success(
			array(
				'contact_id'    => $contact_id,
				'wp_user_id'    => $wp_user_id ? (int) $wp_user_id : null,
				'wp_user_login' => $wp_user_id ? (string) get_userdata( $wp_user_id )->user_login : null,
				'fields'        => $fields,
				'mapped_fields' => $mapped_wp_fields,
			)
		);
	}
}
