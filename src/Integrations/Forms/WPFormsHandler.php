<?php
declare(strict_types=1);

namespace Syncly\Integrations\Forms;

defined( 'ABSPATH' ) || exit;

use Syncly\Core\AssetsManager;
use Syncly\Core\SettingsManager;
use Syncly\Sync\TagManager;
use Syncly\Sync\QueueManager;
use Syncly\Sync\QueueProcessor;

/**
 * WPForms Integration Handler
 *
 * Adds GHL CRM settings to WPForms builder and handles form submissions
 *
 * @package    Syncly
 * @subpackage Syncly/Integrations/Forms
 */
class WPFormsHandler {
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
	 * Key used for both the builder settings section and the stored settings
	 *
	 * Must stay in sync with the `settings[...]` input names in the panel
	 * template so WPForms persists the values with the form.
	 *
	 * @var string
	 */
	private const FORM_KEY = 'syncly';

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
	 * @return void
	 */
	public function init(): void {
		// Register the 'form' queue handler
		$processor = QueueProcessor::get_instance();
		if ( ! $processor->has_handler( 'form' ) ) {
			$processor->register_handler(
				'form',
				[ $processor, 'execute_form_sync' ]
			);
		}

		add_action( 'init', [ $this, 'register_hooks' ] );
	}

	/**
	 * Register WPForms hooks (called on 'init' when WPForms is fully loaded)
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! $this->is_wpforms_active() ) {
			return;
		}

		// Add Syncly section to the WPForms builder settings menu.
		add_filter( 'wpforms_builder_settings_sections', [ $this, 'add_settings_section' ], 20, 2 );

		/*
		 * Render the settings section content.
		 *
		 * WPForms fires this action from
		 * WPForms_Builder_Panel_Settings::panel_content() and passes a single
		 * argument (the panel instance). Every section is rendered on each
		 * builder page load and toggled client-side, so this must echo its own
		 * .wpforms-panel-content-section wrapper.
		 */
		add_action( 'wpforms_form_settings_panel_content', [ $this, 'render_settings_content' ], 20, 1 );

		// Normalize and sanitize the settings on save.
		add_filter( 'wpforms_save_form_args', [ $this, 'save_form_args' ] );

		// Handle form submission.
		add_action( 'wpforms_process_complete', [ $this, 'handle_submission' ], 10, 4 );

		// Enqueue assets on the WPForms builder page.
		add_action( 'wpforms_builder_enqueues', [ $this, 'enqueue_builder_assets' ] );
	}

	/**
	 * Check if WPForms is active
	 *
	 * @return bool
	 */
	private function is_wpforms_active(): bool {
		return function_exists( 'wpforms' ) && class_exists( 'WPForms' );
	}

	/**
	 * Add Syncly section to the WPForms builder settings menu.
	 *
	 * @param array $sections   Existing sections.
	 * @param array $form_data  Form data.
	 * @return array Modified sections.
	 */
	public function add_settings_section( array $sections, $form_data ): array {
		$sections[ self::FORM_KEY ] = __( 'Syncly', 'syncly' );
		return $sections;
	}

	/**
	 * Render the Syncly settings section content.
	 *
	 * @param object $instance WPForms_Builder_Panel_Settings instance.
	 * @return void
	 */
	public function render_settings_content( $instance ): void {
		$form_data = $this->get_form_data( $instance );
		$form_id   = absint( $form_data['id'] ?? 0 );

		if ( $form_id <= 0 ) {
			return;
		}

		$config = $this->get_form_config( $form_data );

		// WPForms fields available for mapping.
		$wpforms_fields = $this->get_wpforms_fields( $form_data );

		// GHL connection status, used by the template.
		$settings = $this->settings_manager->get_settings_array();

		printf(
			'<div class="wpforms-panel-content-section wpforms-panel-content-section-%1$s" data-panel="%1$s">',
			esc_attr( self::FORM_KEY )
		);

		include SYNCLY_PATH . 'templates/admin/wpforms-ghl-panel.php';

		echo '</div>';
	}

	/**
	 * Resolve form data from the settings panel instance.
	 *
	 * @param object $instance WPForms_Builder_Panel_Settings instance.
	 * @return array Form data.
	 */
	private function get_form_data( $instance ): array {
		if ( is_object( $instance ) && ! empty( $instance->form_data ) && is_array( $instance->form_data ) ) {
			return $instance->form_data;
		}

		$form_id = 0;

		if ( is_object( $instance ) && isset( $instance->form_id ) ) {
			$form_id = absint( $instance->form_id );
		} elseif ( is_object( $instance ) && isset( $instance->id ) ) {
			$form_id = absint( $instance->id );
		}

		if ( $form_id <= 0 && class_exists( 'WPForms_Form_Handler' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup of the builder form.
			$form_id = absint( $_GET['form_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( $form_id > 0 ) {
			$form = WPForms_Form_Handler::get( $form_id );

			if ( is_array( $form ) && ! empty( $form['fields'] ) ) {
				return $form;
			}
		}

		return [];
	}

	/**
	 * Get WPForms fields from form data
	 *
	 * @param array $form_data WPForms form data.
	 * @return array Array of fields.
	 */
	private function get_wpforms_fields( array $form_data ): array {
		$fields = [];

		if ( empty( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) {
			return $fields;
		}

		foreach ( $form_data['fields'] as $field_id => $field ) {
			// Skip layout/display-only field types
			if ( in_array( $field['type'] ?? '', [ 'html', 'content', 'divider', 'pagebreak', 'captcha' ], true ) ) {
				continue;
			}

			$label = $field['label'] ?? __( 'Untitled Field', 'syncly' );
			$type  = $field['type'] ?? 'text';

			$fields[] = [
				'id'    => (string) $field_id,
				'label' => $label,
				'type'  => $type,
			];
		}

		return $fields;
	}

	/**
	 * Get form configuration
	 *
	 * @param array $form_data WPForms form data.
	 * @return array Form config with defaults.
	 */
	private function get_form_config( array $form_data ): array {
		$config = $form_data['settings'][ self::FORM_KEY ] ?? [];

		$defaults = [
			'enabled'       => false,
			'field_mapping' => [],
			'tags'          => [],
			'update_exists' => true,
		];

		if ( ! is_array( $config ) ) {
			return $defaults;
		}

		$defaults = apply_filters( 'syncly_wpforms_config_defaults', $defaults, $form_data );

		return array_merge( $defaults, $config );
	}

	/**
	 * Sanitize and normalize the Syncly settings before WPForms saves the form.
	 *
	 * WPForms stores form settings as JSON inside `post_content`, and passes the
	 * save arguments through this filter with that JSON-encoded payload. The
	 * decoded settings are re-cast here because unchecked checkboxes are absent
	 * from the payload and values are otherwise stored verbatim.
	 *
	 * @param array $args Form save arguments.
	 * @return array Modified save arguments.
	 */
	public function save_form_args( $args ): array {
		$args = (array) $args;

		if ( empty( $args['post_content'] ) ) {
			return $args;
		}

		$form_data = json_decode( stripslashes( $args['post_content'] ), true );

		if ( empty( $form_data['settings'] ) || ! is_array( $form_data['settings'] ) ) {
			return $args;
		}

		$settings = $form_data['settings'];

		// Panel was not rendered (or nothing submitted) — leave stored values intact.
		if ( ! isset( $settings[ self::FORM_KEY ] ) || ! is_array( $settings[ self::FORM_KEY ] ) ) {
			return $args;
		}

		$input  = $settings[ self::FORM_KEY ];
		$clean  = [];

		$clean['enabled']       = ! empty( $input['enabled'] );
		$clean['update_exists'] = ! empty( $input['update_exists'] );

		$clean['field_mapping'] = [];
		if ( ! empty( $input['field_mapping'] ) && is_array( $input['field_mapping'] ) ) {
			foreach ( $input['field_mapping'] as $field_id => $ghl_field ) {
				$ghl_field = is_scalar( $ghl_field ) ? sanitize_text_field( (string) $ghl_field ) : '';

				if ( '' === $ghl_field ) {
					continue;
				}

				$clean['field_mapping'][ sanitize_text_field( (string) $field_id ) ] = $ghl_field;
			}
		}

		$clean['tags'] = [];

		/*
		 * The panel posts tags as a comma-separated string in a hidden field:
		 * WPForms' wpforms_prepare_form_data() folds repeated `name[]` inputs
		 * into one shared empty array key, so a multiple select could never
		 * persist more than a single tag. Arrays are still accepted.
		 */
		$tags_input = $input['tags'] ?? [];

		if ( is_string( $tags_input ) ) {
			$tags_input = '' === trim( $tags_input ) ? [] : explode( ',', $tags_input );
		}

		if ( is_array( $tags_input ) ) {
			foreach ( $tags_input as $tag ) {
				if ( ! is_scalar( $tag ) ) {
					continue;
				}

				$tag = sanitize_text_field( (string) $tag );

				if ( '' !== $tag ) {
					$clean['tags'][] = $tag;
				}
			}

			$clean['tags'] = array_values( array_unique( $clean['tags'] ) );
		}

		if ( FormSettings::is_pro_active() ) {
			$clean = apply_filters( 'syncly_wpforms_config_before_save', $clean, $input, $form_data );
		}

		$form_data['settings']                = $settings;
		$form_data['settings'][ self::FORM_KEY ] = $clean;
		$args['post_content']                 = function_exists( 'wpforms_encode' )
			? wpforms_encode( $form_data )
			: wp_slash( wp_json_encode( $form_data ) );

		return $args;
	}

	/**
	 * Handle form submission.
	 *
	 * WPForms passes processed fields, raw entry, form configuration, and entry ID.
	 * Lite may provide an entry ID of zero; the form ID comes from form data.
	 *
	 * @param array $fields    Processed fields keyed by field ID.
	 * @param array $entry     Raw submitted entry.
	 * @param array $form_data Complete form data.
	 * @param int   $entry_id  Entry ID.
	 * @return void
	 */
	public function handle_submission( $fields, $entry, $form_data, $entry_id = 0 ): void {
		if ( ! is_array( $fields ) || ! is_array( $entry ) || ! is_array( $form_data ) ) {
			return;
		}

		$form_id = absint( $form_data['id'] ?? 0 );

		if ( $form_id <= 0 ) {
			return;
		}

		$config = $this->get_form_config( $form_data );

		// Check if GHL integration is enabled
		if ( empty( $config['enabled'] ) ) {
			return;
		}

		if ( FormSettings::is_pro_active() && ! apply_filters( 'syncly_wpforms_should_sync_submission', true, $fields, $form_data, $config ) ) {
			return;
		}

		// Check if GHL connection is active
		$settings = $this->settings_manager->get_settings_array();
		if ( empty( $settings['location_id'] ) ) {
			return;
		}

		// Map submission data to GHL contact fields.
		$contact_data = $this->map_submission_data( $fields, $config['field_mapping'] ?? [] );

		// Validate email (required)
		if ( empty( $contact_data['email'] ) ) {
			do_action(
				'syncly_log_event',
				'wpforms_missing_email',
				'WPForms submission missing email field',
				[ 'form_id' => $form_id ],
				'warning'
			);
			return;
		}

		// Add source
		$form_name              = $form_data['settings']['form_title'] ?? __( 'WPForms', 'syncly' );
		$contact_data['source'] = sanitize_text_field( 'WPForms: ' . $form_name );
		$contact_data['_syncly_wpforms_entry_id'] = $entry_id;
		$contact_data['_syncly_wpforms_form_id']  = $form_id;
		$contact_data['_update_exists']           = $config['update_exists'];

		if ( FormSettings::is_pro_active() ) {
			$contact_data = apply_filters( 'syncly_wpforms_contact_payload', $contact_data, $fields, $form_data, $config );
		}

		// Queue the contact sync
		$queue_manager = QueueManager::get_instance();
		$queue_id      = $queue_manager->add_to_queue(
			'form',
			$form_id,
			'wpforms_submission',
			$contact_data,
			null,
			false
		);

		// Queue tags separately if configured
		if ( ! empty( $config['tags'] ) && $queue_id ) {
			$queue_manager->add_to_queue(
				'form',
				$form_id,
				'add_tags',
				[
					'email' => $contact_data['email'],
					'tags'  => $config['tags'],
				],
				(int) $queue_id,
				false
			);
		}

		/**
		 * Fires after a WPForms submission has been queued for sync.
		 *
		 * @param int|false $queue_id     Queue item ID.
		 * @param array     $contact_data Mapped contact payload.
		 * @param array     $entry        WPForms entry.
		 * @param array     $form_data    WPForms form data.
		 * @param array     $config       Integration config.
		 * @param array     $fields       Processed WPForms fields.
		 */
		do_action( 'syncly_wpforms_submission_queued', $queue_id, $contact_data, $entry, $form_data, $config, $fields );
	}

	/**
	 * Map processed WPForms fields to GHL contact fields.
	 *
	 * @param array $entry        Processed WPForms fields keyed by field ID.
	 * @param array $field_mapping Field mapping config.
	 * @return array Mapped data for the GHL API.
	 */
	private function map_submission_data( array $entry, array $field_mapping ): array {
		$contact_data  = [];
		$custom_fields = [];

		foreach ( $field_mapping as $wpforms_field_id => $ghl_field ) {
			if ( empty( $ghl_field ) || ! isset( $entry[ $wpforms_field_id ] ) ) {
				continue;
			}

			$value = $entry[ $wpforms_field_id ];

			// Processed fields contain metadata alongside the sanitized value.
			// Never include the field label, ID, or type in the contact payload.
			if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
				if ( 'firstName' === $ghl_field && isset( $value['first'] ) ) {
					$value = $value['first'];
				} elseif ( 'lastName' === $ghl_field && isset( $value['last'] ) ) {
					$value = $value['last'];
				} else {
					$value = $value['value'];
				}
			}


			if ( '' === $value || null === $value || [] === $value ) {
				continue;
			}

			// Split Name/Full Name style compound values into their parts.
			if ( is_array( $value ) && isset( $value['first'], $value['last'] ) ) {
				if ( 'firstName' === $ghl_field ) {
					$value = $value['first'];
				} elseif ( 'lastName' === $ghl_field ) {
					$value = $value['last'];
				} else {
					$value = trim( $value['first'] . ' ' . $value['last'] );
				}
			}

			// Flatten array values (checkboxes, multi-select, address blocks).
			if ( is_array( $value ) ) {
				$flat = [];
				array_walk_recursive(
					$value,
					static function ( $item ) use ( &$flat ) {
						if ( is_scalar( $item ) ) {
							$flat[] = (string) $item;
						}
					}
				);
				$value = implode( ', ', $flat );
			}

			$value = trim( sanitize_text_field( (string) $value ) );

			if ( '' === $value ) {
				continue;
			}

			// GHL custom fields are prefixed with "custom.".
			if ( 0 === strpos( $ghl_field, 'custom.' ) ) {
				$custom_fields[] = [
					'id'    => substr( $ghl_field, 7 ),
					'value' => $value,
				];
			} else {
				$contact_data[ $ghl_field ] = $value;
			}
		}

		if ( ! empty( $custom_fields ) ) {
			$contact_data['customFields'] = $custom_fields;
		}

		return $contact_data;
	}

	/**
	 * Enqueue assets on the WPForms builder page.
	 *
	 * @param string $view Current builder view.
	 * @return void
	 */
	public function enqueue_builder_assets( string $view ): void {
		// All sections are rendered on every builder view and switched client-side.
		// Use the builder hook because its asset timing is controlled by WPForms.
		$assets_manager = AssetsManager::get_instance();
		$asset_group    = 'syncly-wpforms-builder';
		$asset_version  = SYNCLY_VERSION . '.' . max(
			filemtime( SYNCLY_PATH . 'assets/admin/js/wpforms-integration.js' ),
			filemtime( SYNCLY_PATH . 'assets/admin/css/wpforms-integration.css' )
		);

		// The builder hook can run before the manager's admin enqueue callback.
		$assets_manager->register_external_libraries();

		$assets_manager->add_admin_asset(
			'syncly-wpforms-css',
			[ $asset_group ],
			'wpforms-integration.css',
			[ 'syncly-globals-css', 'syncly-select2-css' ],
			[],
			$asset_version
		);

		$assets_manager->add_admin_asset(
			'syncly-wpforms-js',
			[ $asset_group ],
			'wpforms-integration.js',
			[ 'jquery', 'syncly-select2' ],
			[
				'tags'  => TagManager::get_instance()->get_tags_for_localization(),
				'nonce' => wp_create_nonce( 'syncly_field_mapping_nonce' ),
			],
			$asset_version,
			true
		);

		$assets_manager->enqueue_admin_asset( 'syncly-wpforms-css', $asset_group );
		$assets_manager->enqueue_admin_asset( 'syncly-wpforms-js', $asset_group );
	}
}
