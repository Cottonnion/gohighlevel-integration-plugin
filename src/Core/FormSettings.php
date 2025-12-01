<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Form Settings Manager
 *
 * Handles per-form settings for auto-fill and visibility
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core
 */
class FormSettings {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Option key for form settings
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'ghl_crm_form_settings';

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
	 * Constructor
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
		add_action( 'wp_ajax_ghl_save_form_settings', [ $this, 'ajax_save_form_settings' ] );
		add_action( 'wp_ajax_ghl_get_form_settings', [ $this, 'ajax_get_form_settings' ] );
		add_action( 'wp_ajax_ghl_mark_form_submitted', [ $this, 'ajax_mark_form_submitted' ] );
		add_action( 'wp_ajax_nopriv_ghl_mark_form_submitted', [ $this, 'ajax_mark_form_submitted' ] );
	}

	/**
	 * Get all form settings
	 *
	 * @return array
	 */
	public function get_all_settings(): array {
		if ( is_multisite() ) {
			$settings = get_site_option( self::OPTION_KEY, [] );
		} else {
			$settings = get_option( self::OPTION_KEY, [] );
		}

		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Get settings for a specific form
	 *
	 * @param string $form_id Form ID.
	 * @return array Form settings with defaults.
	 */
	public function get_form_settings( string $form_id ): array {
	$all_settings = $this->get_all_settings();
	
	$defaults = [
		'autofill_enabled'    => true,  // Auto-fill enabled by default
		'logged_only'         => false, // Show to everyone by default
		'custom_params'       => [],    // Custom URL parameters
		'submission_limit'    => 'unlimited', // 'unlimited' or 'once'
		'submitted_message'   => '', // Message to show after submission (empty = hide completely)
	];

	if ( isset( $all_settings[ $form_id ] ) && is_array( $all_settings[ $form_id ] ) ) {
		return array_merge( $defaults, $all_settings[ $form_id ] );
	}

	return $defaults;
}	/**
	 * Save settings for a specific form
	 *
	 * @param string $form_id  Form ID.
	 * @param array  $settings Settings to save.
	 * @return bool Success status.
	 */
	public function save_form_settings( string $form_id, array $settings ): bool {
	$all_settings = $this->get_all_settings();
	
	// Sanitize settings
	$sanitized_settings = [
		'autofill_enabled'    => isset( $settings['autofill_enabled'] ) && $settings['autofill_enabled'],
		'logged_only'         => isset( $settings['logged_only'] ) && $settings['logged_only'],
		'custom_params'       => isset( $settings['custom_params'] ) && is_array( $settings['custom_params'] ) 
			? $this->sanitize_custom_params( $settings['custom_params'] ) 
			: [],
		'submission_limit'    => isset( $settings['submission_limit'] ) && in_array( $settings['submission_limit'], [ 'unlimited', 'once' ], true ) 
			? $settings['submission_limit'] 
			: 'unlimited',
		'submitted_message'   => isset( $settings['submitted_message'] ) ? sanitize_textarea_field( $settings['submitted_message'] ) : '',
	];		$all_settings[ $form_id ] = $sanitized_settings;

		if ( is_multisite() ) {
			return update_site_option( self::OPTION_KEY, $all_settings );
		} else {
			return update_option( self::OPTION_KEY, $all_settings );
		}
	}

	/**
	 * AJAX handler to save form settings
	 *
	 * @return void
	 */
	public function ajax_save_form_settings(): void {
		try {
			// Verify nonce
			if ( ! check_ajax_referer( 'ghl_crm_forms_nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'ghl-crm-integration' ) ] );
			}

			// Check permissions
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ghl-crm-integration' ) ] );
			}

		// Get form ID
		$form_id = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';
		if ( empty( $form_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Form ID is required.', 'ghl-crm-integration' ) ] );
		}

		// Get settings - handle both nested array and form-encoded format
		$raw_settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) 
			? $_POST['settings'] 
			: $_POST;

		// Parse boolean values properly (handle 'true'/'false' strings and actual booleans)
		$autofill_enabled = false;
		if ( isset( $raw_settings['autofill_enabled'] ) ) {
			$value = $raw_settings['autofill_enabled'];
			$autofill_enabled = ( $value === true || $value === 'true' || $value === '1' || $value === 1 );
		}

		$logged_only = false;
		if ( isset( $raw_settings['logged_only'] ) ) {
			$value = $raw_settings['logged_only'];
			$logged_only = ( $value === true || $value === 'true' || $value === '1' || $value === 1 );
		}

		// Get and sanitize custom params
		$custom_params = [];
		if ( isset( $raw_settings['custom_params'] ) && is_array( $raw_settings['custom_params'] ) ) {
			$custom_params = $this->sanitize_custom_params( $raw_settings['custom_params'] );
		}

		// Parse submission limit
		$submission_limit = 'unlimited';
		if ( isset( $raw_settings['submission_limit'] ) && in_array( $raw_settings['submission_limit'], [ 'unlimited', 'once' ], true ) ) {
			$submission_limit = $raw_settings['submission_limit'];
		}

		// Parse submitted message
		$submitted_message = '';
		if ( isset( $raw_settings['submitted_message'] ) ) {
			$submitted_message = sanitize_textarea_field( $raw_settings['submitted_message'] );
		}

		$settings = [
			'autofill_enabled'    => $autofill_enabled,
			'logged_only'         => $logged_only,
			'custom_params'       => $custom_params,
			'submission_limit'    => $submission_limit,
			'submitted_message'   => $submitted_message,
		];

		// Save settings (will throw exception if it fails)
		$this->save_form_settings( $form_id, $settings );

		wp_send_json_success( [
			'message'  => __( 'Form settings saved successfully.', 'ghl-crm-integration' ),
			'settings' => $this->get_form_settings( $form_id ),
		] );
	} catch ( \Throwable $e ) {
		wp_send_json_error( [
			'message' => __( 'Error saving form settings.', 'ghl-crm-integration' ),
			'error'   => $e->getMessage(),
			'trace'   => $e->getTraceAsString(),
		] );
	}
}	/**
	 * AJAX handler to get form settings
	 *
	 * @return void
	 */
	public function ajax_get_form_settings(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'ghl_crm_forms_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'ghl-crm-integration' ) ] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'ghl-crm-integration' ) ] );
		}

		// Get form ID
		$form_id = isset( $_GET['form_id'] ) ? sanitize_text_field( wp_unslash( $_GET['form_id'] ) ) : '';
		if ( empty( $form_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Form ID is required.', 'ghl-crm-integration' ) ] );
		}

		$settings = $this->get_form_settings( $form_id );

		wp_send_json_success( [
			'settings' => $settings,
		] );
	}

	/**
	 * AJAX handler to mark form as submitted
	 *
	 * @return void
	 */
	public function ajax_mark_form_submitted(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'ghl_form_submission', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'ghl-crm-integration' ) ] );
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'User not logged in.', 'ghl-crm-integration' ) ] );
		}

		// Get form ID
		$form_id = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';
		if ( empty( $form_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Form ID is required.', 'ghl-crm-integration' ) ] );
		}

		// Mark as submitted
		$this->mark_form_submitted( $form_id );

		wp_send_json_success( [
			'message' => __( 'Form marked as submitted.', 'ghl-crm-integration' ),
		] );
	}

	/**
	 * Check if auto-fill is enabled for a form
	 *
	 * @param string $form_id Form ID.
	 * @return bool
	 */
	public function is_autofill_enabled( string $form_id ): bool {
		$settings = $this->get_form_settings( $form_id );
		return $settings['autofill_enabled'];
	}

	/**
	 * Check if form is logged-in only
	 *
	 * @param string $form_id Form ID.
	 * @return bool
	 */
	public function is_logged_only( string $form_id ): bool {
		$settings = $this->get_form_settings( $form_id );
		return $settings['logged_only'];
	}

	/**
	 * Sanitize custom parameters array
	 *
	 * @param array $params Custom parameters.
	 * @return array Sanitized parameters.
	 */
	private function sanitize_custom_params( array $params ): array {
		$sanitized = [];
		
		foreach ( $params as $param ) {
			if ( isset( $param['key'], $param['value'] ) ) {
				$sanitized[] = [
					'key'   => sanitize_text_field( $param['key'] ),
					'value' => sanitize_textarea_field( $param['value'] ),
				];
			}
		}
		
		return $sanitized;
	}

	/**
	 * Get available variable placeholders
	 *
	 * @return array List of available variables with descriptions.
	 */
	public static function get_available_variables(): array {
		return [
			'{user_email}'       => __( 'User email address', 'ghl-crm-integration' ),
			'{user_login}'       => __( 'Username', 'ghl-crm-integration' ),
			'{user_first_name}'  => __( 'User first name', 'ghl-crm-integration' ),
			'{user_last_name}'   => __( 'User last name', 'ghl-crm-integration' ),
			'{user_display_name}' => __( 'User display name', 'ghl-crm-integration' ),
			'{user_id}'          => __( 'User ID', 'ghl-crm-integration' ),
			'{user_role}'        => __( 'User role', 'ghl-crm-integration' ),
			'{site_url}'         => __( 'Site URL', 'ghl-crm-integration' ),
			'{site_name}'        => __( 'Site name', 'ghl-crm-integration' ),
			'{current_url}'      => __( 'Current page URL', 'ghl-crm-integration' ),
			'{current_title}'    => __( 'Current page title', 'ghl-crm-integration' ),
			'{meta:field_name}'  => __( 'User meta field (replace field_name with actual meta key)', 'ghl-crm-integration' ),
		];
	}

	/**
	 * Replace variables in custom parameters with actual values
	 *
	 * @param array $params Custom parameters with variables.
	 * @return array Parameters with resolved values.
	 */
	public function resolve_custom_params( array $params ): array {
		$resolved = [];
		
		foreach ( $params as $param ) {
			if ( ! isset( $param['key'], $param['value'] ) ) {
				continue;
			}
			
			$key   = $param['key'];
			$value = $this->replace_variables( $param['value'] );
			
			// Only add if value is not empty after replacement
			if ( ! empty( $value ) && $value !== $param['value'] || ! str_contains( $param['value'], '{' ) ) {
				$resolved[ $key ] = $value;
			}
		}
		
		return $resolved;
	}

	/**
	 * Replace variable placeholders with actual values
	 *
	 * @param string $text Text containing variables.
	 * @return string Text with variables replaced.
	 */
	private function replace_variables( string $text ): string {
		// No user logged in, return empty for user-specific variables
		if ( ! is_user_logged_in() ) {
			// Check if text contains user-specific variables
			if ( preg_match( '/{user_|{meta:}/i', $text ) ) {
				return '';
			}
		}
		
		$user = wp_get_current_user();
		
		// User variables
		$replacements = [
			'{user_email}'        => $user->user_email ?? '',
			'{user_login}'        => $user->user_login ?? '',
			'{user_first_name}'   => $user->first_name ?? '',
			'{user_last_name}'    => $user->last_name ?? '',
			'{user_display_name}' => $user->display_name ?? '',
			'{user_id}'           => $user->ID ?? '',
			'{user_role}'         => ! empty( $user->roles ) ? $user->roles[0] : '',
			'{site_url}'          => get_site_url(),
			'{site_name}'         => get_bloginfo( 'name' ),
			'{current_url}'       => '', // Will be replaced by JS
			'{current_title}'     => '', // Will be replaced by JS
		];
		
		// Replace standard variables
		$text = str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
		
		// Handle meta fields {meta:field_name}
		if ( preg_match_all( '/{meta:([^}]+)}/', $text, $matches ) ) {
			foreach ( $matches[1] as $index => $meta_key ) {
				$meta_value = get_user_meta( $user->ID, $meta_key, true );
				$text       = str_replace( $matches[0][ $index ], $meta_value, $text );
			}
		}
		
		return $text;
	}

	/**
	 * Check if user has already submitted a form
	 *
	 * @param string $form_id Form ID.
	 * @param int    $user_id User ID (0 for current user).
	 * @return bool
	 */
	public function has_user_submitted( string $form_id, int $user_id = 0 ): bool {
		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( 0 === $user_id ) {
			return false; // Not logged in
		}

		$submitted_forms = get_user_meta( $user_id, '_ghl_submitted_forms', true );
		if ( ! is_array( $submitted_forms ) ) {
			$submitted_forms = [];
		}

		return in_array( $form_id, $submitted_forms, true );
	}

	/**
	 * Mark form as submitted by user
	 *
	 * @param string $form_id Form ID.
	 * @param int    $user_id User ID (0 for current user).
	 * @return bool
	 */
	public function mark_form_submitted( string $form_id, int $user_id = 0 ): bool {
		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( 0 === $user_id ) {
			return false;
		}

		$submitted_forms = get_user_meta( $user_id, '_ghl_submitted_forms', true );
		if ( ! is_array( $submitted_forms ) ) {
			$submitted_forms = [];
		}

		if ( ! in_array( $form_id, $submitted_forms, true ) ) {
			$submitted_forms[] = $form_id;
			update_user_meta( $user_id, '_ghl_submitted_forms', $submitted_forms );
		}

		return true;
	}

	/**
	 * Check if form should be hidden for user
	 *
	 * @param string $form_id Form ID.
	 * @return bool
	 */
	public function should_hide_form( string $form_id ): bool {
		$settings = $this->get_form_settings( $form_id );

		// If submission limit is unlimited, never hide
		if ( 'unlimited' === $settings['submission_limit'] ) {
			return false;
		}

		// If limit is 'once', check if user has submitted
		if ( 'once' === $settings['submission_limit'] ) {
			return $this->has_user_submitted( $form_id );
		}

		return false;
	}
}
