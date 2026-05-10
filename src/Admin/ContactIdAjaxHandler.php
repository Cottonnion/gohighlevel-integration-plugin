<?php
declare(strict_types=1);

namespace GHL_CRM\Admin;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Sync\TagManager;
use GHL_CRM\Frontend\ContactIdHandler;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX Handler for Contact ID Debugger
 *
 * Provides endpoints for testing campaign personalization links
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Admin
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
		add_action( 'wp_ajax_ghl_test_cid_link', array( $this, 'test_cid_link' ) );
	}

	/**
	 * Test a contact ID link and return what would resolve
	 *
	 * @return void
	 */
	public function test_cid_link(): void {
		// Verify nonce
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ghl_crm_settings_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed', 'ghl-crm-integration' ),
				)
			);
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions', 'ghl-crm-integration' ),
				)
			);
		}

		// Get contact ID
		$contact_id = isset( $_POST['contact_id'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_id'] ) ) : '';
		if ( empty( $contact_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Contact ID is required', 'ghl-crm-integration' ),
				)
			);
		}

		// Validate contact ID format (alphanumeric, hyphen, underscore)
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $contact_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid contact ID format', 'ghl-crm-integration' ),
				)
			);
		}

		// Get tag manager and try to find WP user
		$tag_manager = TagManager::get_instance();
		$wp_user_id  = $tag_manager->find_user_by_contact_id( $contact_id );

		$fields = array();

		if ( $wp_user_id ) {
			// Get all user meta for this contact
			$user_data = get_userdata( $wp_user_id );
			if ( $user_data ) {
				// Build fields from user object
				$fields['first_name'] = (string) get_user_meta( $wp_user_id, 'first_name', true );
				$fields['last_name']  = (string) get_user_meta( $wp_user_id, 'last_name', true );
				$fields['email']      = $user_data->user_email;
				$fields['phone']      = (string) get_user_meta( $wp_user_id, 'phone', true );
				$fields['company']    = (string) get_user_meta( $wp_user_id, 'company', true );
				$fields['street']     = (string) get_user_meta( $wp_user_id, 'street', true );
				$fields['city']       = (string) get_user_meta( $wp_user_id, 'city', true );
				$fields['state']      = (string) get_user_meta( $wp_user_id, 'state', true );
				$fields['postal_code'] = (string) get_user_meta( $wp_user_id, 'postal_code', true );
				$fields['country']    = (string) get_user_meta( $wp_user_id, 'country', true );
			}
		}

		// Get allowed fields setting
		$settings_manager = SettingsManager::get_instance();
		$hidden_fields_json = $settings_manager->get_setting( 'ghl_cid_hidden_fields', '' );
		$hidden_fields = ! empty( $hidden_fields_json ) ? (array) json_decode( $hidden_fields_json, true ) : array();

		// Filter out hidden fields
		$fields = array_filter(
			$fields,
			function ( $key ) use ( $hidden_fields ) {
				return ! in_array( $key, $hidden_fields, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		wp_send_json_success(
			array(
				'contact_id'   => $contact_id,
				'wp_user_id'   => $wp_user_id ? (int) $wp_user_id : null,
				'wp_user_login' => $wp_user_id ? (string) get_userdata( $wp_user_id )->user_login : null,
				'fields'       => $fields,
			)
		);
	}
}
