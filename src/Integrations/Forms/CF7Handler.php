<?php
declare(strict_types=1);

namespace GHL_CRM\Integrations\Forms;

defined( 'ABSPATH' ) || exit;

use GHL_CRM\Core\AssetsManager;
use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Core\TagManager;
use GHL_CRM\Sync\QueueManager;

/**
 * Contact Form 7 Integration Handler
 *
 * Adds GHL CRM tab to CF7 form editor and handles form submissions
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Integrations/Forms
 */
class CF7Handler {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings Manager instance
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Meta key for storing form-specific GHL settings
	 *
	 * @var string
	 */
	private const META_KEY = '_ghl_crm_cf7_config';

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
		$this->settings_manager = SettingsManager::get_instance();
	}

	/**
	 * Initialize hooks
	 *
	 * CF7 may not be loaded yet when our plugin initializes (plugins_loaded priority 1),
	 * so we defer hook registration to 'init' when all plugins are fully loaded.
	 *
	 * @return void
	 */
	public function init(): void {
		// Defer to 'init' hook so CF7 is fully loaded and WPCF7_VERSION is defined
		add_action( 'init', [ $this, 'register_hooks' ] );
	}

	/**
	 * Register CF7-related hooks (called on 'init' when CF7 is fully loaded)
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Check if CF7 is active
		if ( ! $this->is_cf7_active() ) {
			return;
		}

		// Add GHL CRM tab to CF7 editor
		add_filter( 'wpcf7_editor_panels', [ $this, 'add_ghl_panel' ] );

		// Save GHL settings when CF7 form is saved
		add_action( 'wpcf7_save_contact_form', [ $this, 'save_form_settings' ], 10, 1 );

		// Handle form submission (CF7 5.5+ passes 3 params: $contact_form, &$abort, $submission)
		add_action( 'wpcf7_before_send_mail', [ $this, 'handle_submission' ], 10, 3 );

		// Register CF7 admin assets via AssetsManager
		$this->register_admin_assets();
	}

	/**
	 * Check if Contact Form 7 is active
	 *
	 * @return bool
	 */
	private function is_cf7_active(): bool {
		return defined( 'WPCF7_VERSION' );
	}

	/**
	 * Add GHL CRM panel to CF7 editor
	 *
	 * @param array $panels Existing panels.
	 * @return array Modified panels.
	 */
	public function add_ghl_panel( array $panels ): array {
		$panels['ghl-crm-panel'] = [
			'title'    => __( 'GHL CRM', 'ghl-crm-integration' ),
			'callback' => [ $this, 'render_panel' ],
		];

		return $panels;
	}

	/**
	 * Render GHL CRM panel content
	 *
	 * @param \WPCF7_ContactForm $contact_form CF7 form object.
	 * @return void
	 */
	public function render_panel( $contact_form ): void {
		$form_id = $contact_form->id();
		$config  = $this->get_form_config( $form_id );

		// Get CF7 form fields for mapping
		$cf7_fields = $this->get_cf7_form_fields( $contact_form );

		// Load template (GHL fields loaded dynamically via AJAX)
		include GHL_CRM_PATH . 'templates/admin/cf7-ghl-panel.php';
	}

	/**
	 * Get CF7 form fields
	 *
	 * @param \WPCF7_ContactForm $contact_form CF7 form object.
	 * @return array Array of field names.
	 */
	private function get_cf7_form_fields( $contact_form ): array {
		$form_tags = $contact_form->scan_form_tags();
		$fields    = [];

		foreach ( $form_tags as $tag ) {
			// Skip submit buttons and hidden fields
			if ( in_array( $tag->basetype, [ 'submit', 'hidden' ], true ) ) {
				continue;
			}

			$fields[] = [
				'name'  => $tag->name,
				'type'  => $tag->basetype,
				'label' => $tag->name, // CF7 doesn't have labels in tags, use name
			];
		}

		return $fields;
	}

	/**
	 * Get form configuration
	 *
	 * @param int $form_id CF7 form post ID.
	 * @return array Form config with defaults.
	 */
	private function get_form_config( int $form_id ): array {
		$config = get_post_meta( $form_id, self::META_KEY, true );

		$defaults = [
			'enabled'       => false,
			'field_mapping' => [],
			'tags'          => [],
			'update_exists' => true, // Update if contact exists
		];

		return is_array( $config ) ? array_merge( $defaults, $config ) : $defaults;
	}

	/**
	 * Save form settings
	 *
	 * @param \WPCF7_ContactForm $contact_form CF7 form object.
	 * @return void
	 */
	public function save_form_settings( $contact_form ): void {
		// Verify nonce
		if ( ! isset( $_POST['ghl_crm_cf7_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ghl_crm_cf7_nonce'] ) ), 'ghl_crm_cf7_save' ) ) {
			return;
		}

		$form_id = $contact_form->id();

		// Get submitted data
		$enabled       = isset( $_POST['ghl_crm_enabled'] ) && '1' === $_POST['ghl_crm_enabled'];
		$update_exists = isset( $_POST['ghl_crm_update_exists'] ) && '1' === $_POST['ghl_crm_update_exists'];

		// Sanitize field mapping
		$field_mapping = [];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above
		if ( isset( $_POST['ghl_crm_field_mapping'] ) && is_array( $_POST['ghl_crm_field_mapping'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in loop below
			$raw_mapping = wp_unslash( $_POST['ghl_crm_field_mapping'] );
			foreach ( $raw_mapping as $cf7_field => $ghl_field ) {
				$cf7_field_clean = sanitize_text_field( $cf7_field );
				$ghl_field_clean = sanitize_text_field( $ghl_field );

				if ( ! empty( $ghl_field_clean ) ) {
					$field_mapping[ $cf7_field_clean ] = $ghl_field_clean;
				}
			}
		}

		// Sanitize tags
		$tags = [];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above
		if ( isset( $_POST['ghl_crm_tags'] ) && is_array( $_POST['ghl_crm_tags'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in loop below
			$raw_tags = wp_unslash( $_POST['ghl_crm_tags'] );
			foreach ( $raw_tags as $tag ) {
				$tag_clean = sanitize_text_field( $tag );
				if ( ! empty( $tag_clean ) ) {
					$tags[] = $tag_clean;
				}
			}
		}

		// Save config
		$config = [
			'enabled'       => $enabled,
			'field_mapping' => $field_mapping,
			'tags'          => $tags,
			'update_exists' => $update_exists,
		];

		update_post_meta( $form_id, self::META_KEY, $config );
	}

	/**
	 * Handle form submission
	 *
	 * @param \WPCF7_ContactForm $contact_form CF7 form object.
	 * @param bool               $abort        Whether to abort sending mail (passed by reference).
	 * @param \WPCF7_Submission  $submission   CF7 submission instance.
	 * @return void
	 */
	public function handle_submission( $contact_form, &$abort, $submission ): void {
		$form_id = $contact_form->id();
		$config  = $this->get_form_config( $form_id );

		// Check if GHL integration is enabled for this form
		if ( empty( $config['enabled'] ) ) {
			return;
		}

		// Check if GHL connection is active
		$settings = $this->settings_manager->get_settings_array();
		if ( empty( $settings['location_id'] ) ) {
			return;
		}

		// Get submission data (injected by CF7 5.5+ via hook params)
		$posted_data = $submission->get_posted_data();

		// Map CF7 fields to GHL fields
		$contact_data = $this->map_submission_data( $posted_data, $config['field_mapping'] );


		// Validate email (required)
		if ( empty( $contact_data['email'] ) ) {
			// Form submission missing email - log for debugging but don't throw error
			do_action(
				'ghl_crm_log_event',
				'cf7_missing_email',
				'CF7 submission missing email field',
				[ 'form_id' => $form_id ],
				'warning'
			);
			return;
		}

		// Add source
		$contact_data['source'] = sprintf(
			'CF7 Form: %s (ID: %d)',
			$contact_form->title(),
			$form_id
		);

		// Queue contact create/update via existing user handler (no tags in payload).
		$contact_data['_update_exists'] = $config['update_exists'];

		$queue_manager = QueueManager::get_instance();
		$queue_id      = $queue_manager->add_to_queue(
			'form',
			$form_id,
			'cf7_submission',
			$contact_data
		);

		// Queue tags separately — depends_on ensures contact is created first.
		// Email is included so handle_add_tags can resolve contact_id from cache.
		if ( ! empty( $config['tags'] ) && $queue_id ) {
			$queue_manager->add_to_queue(
				'form',
				$form_id,
				'add_tags',
				[
					'email' => $contact_data['email'],
					'tags'  => $config['tags'],
				],
				(int) $queue_id
			);
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for CF7 integration
		error_log( 'GHL CRM CF7: Successfully queued submission for form ID ' . $form_id . ' (email: ' . ( $contact_data['email'] ?? 'N/A' ) . ')' );
	}

	/**
	 * Map CF7 submission data to GHL fields
	 *
	 * @param array $posted_data   CF7 posted data.
	 * @param array $field_mapping Field mapping config.
	 * @return array Mapped data for GHL API.
	 */
	private function map_submission_data( array $posted_data, array $field_mapping ): array {
		$contact_data  = [];
		$custom_fields = [];

		foreach ( $field_mapping as $cf7_field => $ghl_field ) {
			if ( ! isset( $posted_data[ $cf7_field ] ) ) {
				continue;
			}

			$value = $posted_data[ $cf7_field ];

			// Handle arrays (checkboxes, multi-select)
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			$value = sanitize_text_field( $value );

			if ( empty( $value ) ) {
				continue;
			}

			// Check if custom field (custom fields from GHL have IDs)
			if ( strlen( $ghl_field ) > 20 && ! in_array( $ghl_field, [ 'email', 'firstName', 'lastName', 'phone', 'address1', 'city', 'state', 'postalCode', 'country', 'companyName', 'website' ], true ) ) {
				$custom_fields[] = [
					'id'    => $ghl_field,
					'value' => $value,
				];
			} else {
				// Standard field
				$contact_data[ $ghl_field ] = $value;
			}
		}

		// Add custom fields if any
		if ( ! empty( $custom_fields ) ) {
			$contact_data['customFields'] = $custom_fields;
		}

		return $contact_data;
	}

	/**
	 * Register admin assets via AssetsManager
	 *
	 * @return void
	 */
	private function register_admin_assets(): void {
		$assets_manager = AssetsManager::get_instance();
		$ghl_tags       = TagManager::get_instance()->get_tags_for_localization();

		$assets_manager->add_admin_asset(
			'ghl-crm-cf7-css',
			[ 'toplevel_page_wpcf7' ],
			'cf7-integration.css',
			[ 'ghl-crm-globals-css', 'ghl-crm-select2-css' ],
			[],
			'1.0.2'
		);

		$assets_manager->add_admin_asset(
			'ghl-crm-cf7-js',
			[ 'toplevel_page_wpcf7' ],
			'cf7-integration.js',
			[ 'jquery', 'ghl-crm-select2' ],
			[
				'tags'  => $ghl_tags,
				'nonce' => wp_create_nonce( 'ghl_crm_field_mapping_nonce' ),
			],
			'1.0.3',
			true
		);
	}
}
