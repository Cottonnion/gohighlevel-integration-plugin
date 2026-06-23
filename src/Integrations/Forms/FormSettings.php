<?php
declare(strict_types=1);

namespace Syncly\Integrations\Forms;

use Syncly\Core\SettingsManager;

defined( 'ABSPATH' ) || exit;

/**
 * Form Settings Manager
 *
 * Handles per-form settings for auto-fill and visibility
 *
 * @package    Syncly
 * @subpackage Syncly/Core
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
	private const OPTION_KEY = 'syncly_form_settings';

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
		add_action( 'wp_ajax_syncly_get_forms', [ $this, 'handle_get_forms' ] );
		add_action( 'wp_ajax_ghl_save_form_settings', [ $this, 'ajax_save_form_settings' ] );
		add_action( 'wp_ajax_ghl_get_form_settings', [ $this, 'ajax_get_form_settings' ] );
	}

	/**
	 * Check if licensed Pro form features are available.
	 *
	 * @return bool
	 */
	public static function is_pro_active(): bool {
		return (bool) apply_filters( 'syncly_is_pro_active', false );
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
			'autofill_enabled' => true,
			'logged_only'      => false,
		];

		if ( isset( $all_settings[ $form_id ] ) && is_array( $all_settings[ $form_id ] ) ) {
			return array_merge( $defaults, $all_settings[ $form_id ] );
		}

		return $defaults;
	}   /**
		 * Save settings for a specific form
		 *
		 * @param string $form_id      Form ID.
		 * @param array  $settings     Sanitized free settings to save.
		 * @param array  $raw_settings Raw POST data for Pro plugin filter.
		 * @return bool Success status.
		 */
	public function save_form_settings( string $form_id, array $settings, array $raw_settings = [] ): bool {
		$all_settings = $this->get_all_settings();

		$sanitized_settings = [
			'autofill_enabled' => isset( $settings['autofill_enabled'] ) && $settings['autofill_enabled'],
			'logged_only'      => isset( $settings['logged_only'] ) && $settings['logged_only'],
		];

		/**
		 * Filter settings before save
		 * Allows Pro plugin to add custom_params and submission_limit
		 *
		 * @param array  $sanitized_settings Sanitized FREE settings
		 * @param string $form_id            Form ID
		 * @param array  $raw_settings       Raw POST data from AJAX request
		 */
		$sanitized_settings = apply_filters( 'syncly_form_settings_before_save', $sanitized_settings, $form_id, $raw_settings );

		$all_settings[ $form_id ] = $sanitized_settings;

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
			if ( ! check_ajax_referer( 'syncly_forms_nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'syncly' ) ] );
			}

			// Check permissions
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'syncly' ) ] );
			}

			// Get form ID
			$form_id = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';
			if ( empty( $form_id ) ) {
				wp_send_json_error( [ 'message' => __( 'Form ID is required.', 'syncly' ) ] );
			}

			// Get settings - handle both nested array and form-encoded format
			$sanitized_post = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
			$raw_settings   = isset( $sanitized_post['settings'] ) && is_array( $sanitized_post['settings'] )
				? $sanitized_post['settings']
				: $sanitized_post;

			// Parse boolean values properly (handle 'true'/'false' strings and actual booleans)
			$autofill_enabled = false;
			if ( isset( $raw_settings['autofill_enabled'] ) ) {
				$value            = $raw_settings['autofill_enabled'];
				$autofill_enabled = ( $value === true || $value === 'true' || $value === '1' || $value === 1 );
			}

			$logged_only = false;
			if ( isset( $raw_settings['logged_only'] ) ) {
				$value       = $raw_settings['logged_only'];
				$logged_only = ( $value === true || $value === 'true' || $value === '1' || $value === 1 );
			}

			$settings = [
				'autofill_enabled' => $autofill_enabled,
				'logged_only'      => $logged_only,
			];

			// Save settings (pass raw POST data to filter for Pro plugin to access)
			$this->save_form_settings( $form_id, $settings, $raw_settings );

			wp_send_json_success(
				[
					'message'  => __( 'Form settings saved successfully.', 'syncly' ),
					'settings' => $this->get_form_settings( $form_id ),
				]
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				[
					'message' => __( 'Error saving form settings.', 'syncly' ),
					'error'   => $e->getMessage(),
					'trace'   => $e->getTraceAsString(),
				]
			);
		}
	}   /**
		 * AJAX handler to get form settings
		 *
		 * @return void
		 */
	public function ajax_get_form_settings(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'syncly_forms_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'syncly' ) ] );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'syncly' ) ] );
		}

		// Get form ID
		$form_id = isset( $_GET['form_id'] ) ? sanitize_text_field( wp_unslash( $_GET['form_id'] ) ) : '';
		if ( empty( $form_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Form ID is required.', 'syncly' ) ] );
		}

		$settings = $this->get_form_settings( $form_id );

		wp_send_json_success(
			[
				'settings' => $settings,
			]
		);
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
	 * Get available variable placeholders
	 *
	 * @return array List of available variables with descriptions.
	 */
	public static function get_available_variables(): array {
		return [
			'{user_email}'        => __( 'User email address', 'syncly' ),
			'{user_login}'        => __( 'Username', 'syncly' ),
			'{user_first_name}'   => __( 'User first name', 'syncly' ),
			'{user_last_name}'    => __( 'User last name', 'syncly' ),
			'{user_display_name}' => __( 'User display name', 'syncly' ),
			'{user_id}'           => __( 'User ID', 'syncly' ),
			'{user_role}'         => __( 'User role', 'syncly' ),
			'{site_url}'          => __( 'Site URL', 'syncly' ),
			'{site_name}'         => __( 'Site name', 'syncly' ),
			'{current_url}'       => __( 'Current page URL', 'syncly' ),
			'{current_title}'     => __( 'Current page title', 'syncly' ),
			'{meta:field_name}'   => __( 'User meta field (replace field_name with actual meta key)', 'syncly' ),
		];
	}

	/**
	 * Handle AJAX request to get forms from GoHighLevel.
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_get_forms(): void {
		check_ajax_referer( 'syncly_forms_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access forms.', 'syncly' ),
				],
				403
			);
		}

		$settings_manager = SettingsManager::get_instance();
		if ( ! $settings_manager->is_connection_verified() ) {
			wp_send_json_error(
				[
					'message' => __( 'Please connect to GoHighLevel first.', 'syncly' ),
				],
				401
			);
		}

		try {
			$forms_resource = new \Syncly\API\Resources\FormsResource();
			$forms          = $forms_resource->get_forms( true );

			$settings           = $settings_manager->get_settings_array();
			$white_label_domain = $settings['ghl_white_label_domain'] ?? '';

			wp_send_json_success(
				[
					'forms'              => $forms,
					'white_label_domain' => $white_label_domain,
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'Failed to fetch forms: %s', 'syncly' ),
						$e->getMessage()
					),
				],
				500
			);
		} catch ( \Error $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'A fatal error occurred while fetching forms: %s', 'syncly' ),
						$e->getMessage()
					),
				],
				500
			);
		}
	}

}
