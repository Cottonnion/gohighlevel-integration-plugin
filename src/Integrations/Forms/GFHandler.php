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
 * Gravity Forms Integration Handler
 *
 * Adds a "GHL CRM" tab to the Gravity Forms form-settings screen and handles
 * form submissions asynchronously through the sync queue.
 *
 * Free features: enable toggle, standard field mapping, tags, update-existing.
 * Pro features (conditional logic, workflow enrollment) are injected by
 * Syncly Pro through the extension hooks exposed in this class.
 *
 * @package    Syncly
 * @subpackage Syncly/Integrations/Forms
 */
class GFHandler {
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
	 * Key used to store our config inside the GF form array.
	 *
	 * @var string
	 */
	private const FORM_KEY = 'syncly_gf_config';

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
	 * Defer hook registration to 'init' so Gravity Forms is fully loaded.
	 *
	 * @return void
	 */
	public function init(): void {
		// QueueProcessor singleton is resolved before integrations in Loader,
		// and its 'form' handler (execute_form_sync) already supports
		// 'gf_submission'. Defensive registration mirrors CF7Handler.
		$processor = QueueProcessor::get_instance();
		if ( ! $processor->has_handler( 'form' ) ) {
			$processor->register_handler(
				'form',
				[ $processor, 'execute_form_sync' ]
			);
		}

		// Match the working Gravity Forms bridge: register the GF filters/actions
		// without requiring GF admin classes to already be loaded.
		$this->register_hooks();
	}

	/**
	 * Register Gravity Forms hooks (called on 'init' when GF is fully loaded)
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Add "GHL CRM" tab to the form settings menu.
		add_filter( 'gform_form_settings_menu', [ $this, 'add_settings_menu_item' ], 10, 2 );

		// Render the settings tab content (handles its own save postback).
		add_action( 'gform_form_settings_page_syncly_ghl', [ $this, 'render_settings_page' ] );

		// Handle form submission.
		add_action( 'gform_after_submission', [ $this, 'handle_submission' ], 10, 2 );
		add_action( 'syncly_after_sync_success', [ $this, 'record_success' ], 10, 4 );

		// Register admin assets via AssetsManager.
		$this->register_admin_assets();

		// Gravity Forms uses a dynamic admin screen ID. Enqueue directly from
		// the page query, matching the installed bridge plugin's approach.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_gravity_forms_assets' ], 20 );
	}

	/**
	 * Enqueue assets on the Gravity Forms settings screen.
	 *
	 * AssetsManager uses current-screen IDs for most Syncly pages, but GF's
	 * dynamic screen ID varies by GF version. The page query is stable.
	 *
	 * @return void
	 */
	public function enqueue_gravity_forms_assets(): void {
		if ( 'gf_edit_forms' !== ( $_GET['page'] ?? '' ) || 'syncly_ghl' !== ( $_GET['subview'] ?? '' ) ) {
			return;
		}

		$base_url = SYNCLY_URL . 'assets/admin/';

		// Register these locally because GF pages do not load Syncly's global
		// admin asset bundle, which normally registers these dependencies.
		wp_register_style(
			'syncly-globals-css',
			$base_url . 'css/globals.css',
			[],
			SYNCLY_VERSION
		);
		wp_register_style(
			'syncly-select2-css',
			$base_url . 'css/select2.min.css',
			[],
			'4.1.0'
		);
		wp_register_script(
			'syncly-select2',
			$base_url . 'js/select2.min.js',
			[ 'jquery' ],
			'4.1.0',
			true
		);

		wp_enqueue_style( 'syncly-globals-css' );
		wp_enqueue_style( 'syncly-select2-css' );
		wp_enqueue_style(
			'syncly-gf-css',
			$base_url . 'css/gf-integration.css',
			[ 'syncly-globals-css', 'syncly-select2-css' ],
			SYNCLY_VERSION
		);

		wp_enqueue_script(
			'syncly-gf-js',
			$base_url . 'js/gf-integration.js',
			[ 'jquery', 'syncly-select2' ],
			SYNCLY_VERSION,
			true
		);
		wp_localize_script(
			'syncly-gf-js',
			'syncly_gf_js_data',
			[
				'tags'  => TagManager::get_instance()->get_tags_for_localization(),
				'nonce' => wp_create_nonce( 'syncly_field_mapping_nonce' ),
			]
		);
	}

	/**
	 * Check if Gravity Forms is active
	 *
	 * @return bool
	 */
	private function is_gf_active(): bool {
		return class_exists( 'GFForms' ) && class_exists( 'GFFormSettings' );
	}

	/**
	 * Add the GHL CRM item to the GF form settings menu
	 *
	 * @param array $menu_items Existing menu items.
	 * @param int   $form_id    Current form ID.
	 * @return array Modified menu items.
	 */
	public function add_settings_menu_item( array $menu_items, $form_id ): array {
		$menu_items[] = [
			'name'  => 'syncly_ghl',
			'label' => __( 'Syncly', 'syncly' ),
			'icon'  => 'dashicons dashicons-cloud',
		];

		return $menu_items;
	}

	/**
	 * Render the GHL CRM settings page (inside the GF form settings shell)
	 *
	 * Custom form-settings subviews must handle their own save postback —
	 * GF's built-in gform_pre_form_settings_save only fires for the core
	 * settings subview. This follows the same pattern the GHL GF bridge uses.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$form_id = absint( rgget( 'id' ) );
		if ( ! $form_id ) {
			return;
		}

		$form = \GFFormsModel::get_form_meta( $form_id );
		if ( empty( $form ) ) {
			return;
		}

		// Handle save postback before rendering so the UI shows fresh values.
		$saved = $this->maybe_save_settings( $form );
		if ( $saved ) {
			// Re-fetch the form so $config reflects what was just saved.
			$form = \GFFormsModel::get_form_meta( $form_id );
		}

		$config    = $this->get_form_config( $form );
		$gf_fields = $this->get_gf_form_fields( $form );
		$resend_id = $this->maybe_resend_submission( $form_id );
		$history   = \Syncly\Integrations\Forms\FormSettings::is_pro_active() ? $this->get_submission_history( $form_id ) : [];

		\GFFormSettings::page_header();

		if ( $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'GHL CRM settings saved.', 'syncly' ) . '</strong></p></div>';
		}
		// Allow Pro to inject its badge/banner above the free settings.
		do_action( 'syncly_gf_panel_before', $form_id, $config );

		?>
		<form method="post" action="">
			<?php include SYNCLY_PATH . 'templates/admin/gf-ghl-panel.php'; ?>

			<?php
			// Allow Pro to append its licensed automation settings.
			do_action( 'syncly_gf_panel_after', $form_id, $config, $history );
			?>

			<p class="submit">
				<button type="submit" name="syncly_gf_save" value="1" class="button button-primary">
					<?php esc_html_e( 'Save GHL CRM Settings', 'syncly' ); ?>
				</button>
			</p>
		</form>
		<?php

		\GFFormSettings::page_footer();
	}

	/**
	 * Save settings when our form posts back.
	 *
	 * @param array $form GF form array.
	 * @return bool True when settings were saved.
	 */
	private function maybe_save_settings( array $form ): bool {
		if ( ! isset( $_POST['syncly_gf_save'] ) ) {
			return false;
		}

		if ( ! isset( $_POST['syncly_gf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['syncly_gf_nonce'] ) ), 'syncly_gf_save' ) ) {
			return false;
		}

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'gravityforms_edit_forms' ) ) {
			return false;
		}

		$form_id = absint( $form['id'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		$enabled       = isset( $_POST['syncly_gf_enabled'] ) && '1' === $_POST['syncly_gf_enabled'];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$update_exists = isset( $_POST['syncly_gf_update_exists'] ) && '1' === $_POST['syncly_gf_update_exists'];

		// Sanitize field mapping.
		$field_mapping = [];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
		if ( isset( $_POST['syncly_gf_field_mapping'] ) && is_array( $_POST['syncly_gf_field_mapping'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Sanitized in loop below, nonce verified above
			$raw_mapping = wp_unslash( $_POST['syncly_gf_field_mapping'] );
			foreach ( $raw_mapping as $gf_field => $ghl_field ) {
				$gf_field_clean  = sanitize_text_field( $gf_field );
				$ghl_field_clean = sanitize_text_field( $ghl_field );

				if ( '' !== $ghl_field_clean ) {
					$field_mapping[ $gf_field_clean ] = $ghl_field_clean;
				}
			}
		}

		$config = [
			'enabled'       => $enabled,
			'field_mapping' => $field_mapping,
			'update_exists' => $update_exists,
		];

		/**
		 * Filter the config before it is saved into the form array.
		 * Pro uses this to sanitize and merge its own settings.
		 *
		 * @param array $config Free config values.
		 * @param array $form   GF form array.
		 */
		$config = apply_filters( 'syncly_gf_config_before_save', $config, $form );

		$form[ self::FORM_KEY ] = $config;

		\GFFormsModel::update_form_meta( $form_id, $form );

		return true;
	}

	/**
	 * Get mappable fields from a GF form (including sub-inputs for
	 * complex fields like name and address)
	 *
	 * @param array $form GF form array.
	 * @return array<int, array<string, string>> List of mappable fields.
	 */
	private function get_gf_form_fields( array $form ): array {
		$fields = [];

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $fields;
		}

		foreach ( $form['fields'] as $field ) {
			// Skip layout/display-only field types.
			if ( in_array( $field->type, [ 'html', 'section', 'page', 'captcha', 'password' ], true ) ) {
				continue;
			}

			$label = $field->get_field_label( false, '' );
			if ( '' === $label ) {
				$label = $field->label;
			}

			// Fields with sub-inputs (name, address, checkbox, etc.).
			$inputs = $field->get_entry_inputs();
			if ( is_array( $inputs ) && count( $inputs ) > 1 ) {
				foreach ( $inputs as $input ) {
					$input_label = isset( $input['label'] ) ? $input['label'] : $label;
					$fields[]    = [
						'id'    => (string) $input['id'],
						'label' => trim( $label . ' — ' . $input_label ),
						'type'  => $field->type,
					];
				}
			} else {
				$fields[] = [
					'id'    => (string) $field->id,
					'label' => $label,
					'type'  => $field->type,
				];
			}
		}

		return $fields;
	}

	/**
	 * Get form configuration from the GF form array
	 *
	 * @param array $form GF form array.
	 * @return array Form config with defaults.
	 */
	private function get_form_config( array $form ): array {
		$config = isset( $form[ self::FORM_KEY ] ) && is_array( $form[ self::FORM_KEY ] )
			? $form[ self::FORM_KEY ]
			: [];

		$defaults = [
			'enabled'       => false,
			'field_mapping' => [],
			'update_exists' => true,
		];

		/**
		 * Filter the default config for the GF integration.
		 * Pro uses this to register its own config keys.
		 *
		 * @param array $defaults Default config values.
		 * @param array $form     GF form array.
		 */
		$defaults = apply_filters( 'syncly_gf_config_defaults', $defaults, $form );

		return array_merge( $defaults, $config );
	}

	/**
	 * Handle form submission
	 *
	 * @param array $entry GF entry array.
	 * @param array $form  GF form array.
	 * @return void
	 */
	public function handle_submission( array $entry, array $form ): void {
		$form_id = absint( $form['id'] );
		$config  = $this->get_form_config( $form );
		$entry_id = absint( $entry['id'] ?? 0 );

		// Check if the integration is enabled for this form.
		if ( empty( $config['enabled'] ) ) {
			return;
		}

		if ( \Syncly\Integrations\Forms\FormSettings::is_pro_active() && ! empty( $config['skip_spam'] ) && 'spam' === rgar( $entry, 'status' ) ) {
			$this->save_entry_sync_meta( $entry_id, [ 'status' => 'skipped_spam' ] );
			return;
		}

		// Check if GHL connection is active.
		$settings = $this->settings_manager->get_settings_array();
		if ( empty( $settings['location_id'] ) ) {
			return;
		}

		/**
		 * Filter whether this submission should be synced.
		 * Pro's conditional-logic feature hooks here and can return false.
		 *
		 * @param bool  $should_sync Whether to sync. Default true.
		 * @param array $entry       GF entry array.
		 * @param array $form        GF form array.
		 * @param array $config      Integration config.
		 */
		if ( ! apply_filters( 'syncly_gf_should_sync_submission', true, $entry, $form, $config ) ) {
			return;
		}

		// Map GF fields to GHL fields.
		$contact_data = $this->map_submission_data( $entry, $form, $config['field_mapping'] );

		/**
		 * Filter the mapped contact data before it is queued.
		 *
		 * @param array $contact_data Mapped GHL contact payload.
		 * @param array $entry        GF entry array.
		 * @param array $form         GF form array.
		 * @param array $config       Integration config.
		 */
		$contact_data = apply_filters( 'syncly_gf_contact_data', $contact_data, $entry, $form, $config );

		// Validate email (required).
		if ( empty( $contact_data['email'] ) ) {
			do_action(
				'syncly_log_event',
				'gf_missing_email',
				'Gravity Forms submission missing email field',
				[ 'form_id' => $form_id ],
				'warning'
			);
			return;
		}

		// Add source.
		$source_name          = \Syncly\Integrations\Forms\FormSettings::is_pro_active() ? (string) ( $config['source_name'] ?? 'Gravity Forms: {form_title}' ) : 'Gravity Forms: {form_title}';
		$contact_data['source'] = sanitize_text_field( str_replace( '{form_title}', (string) ( $form['title'] ?? '' ), $source_name ) );
		$contact_data['_syncly_gf_entry_id'] = $entry_id;
		$contact_data['_syncly_gf_form_id']  = $form_id;

		$note = '';
		if ( \Syncly\Integrations\Forms\FormSettings::is_pro_active() && ! empty( $config['note_enabled'] ) && ! empty( $config['note_field'] ) ) {
			$note = sanitize_textarea_field( (string) rgar( $entry, $config['note_field'] ) );
		}
		if ( '' !== $note ) {
			$contact_data['_syncly_gf_note'] = $note;
		}
		if ( \Syncly\Integrations\Forms\FormSettings::is_pro_active() && absint( $config['sync_delay'] ?? 0 ) > 0 ) {
			$contact_data['_syncly_gf_not_before'] = time() + absint( $config['sync_delay'] ) * MINUTE_IN_SECONDS;
		}

		$contact_data['_update_exists'] = $config['update_exists'];

		$queue_manager = QueueManager::get_instance();
		$queue_id      = $queue_manager->add_to_queue(
			'form',
			$form_id,
			'gf_submission',
			$contact_data
		);

		// Queue tags separately — depends_on ensures the contact exists first.
		if ( \Syncly\Integrations\Forms\FormSettings::is_pro_active() && ! empty( $config['tags'] ) && $queue_id ) {
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

		if ( \Syncly\Integrations\Forms\FormSettings::is_pro_active() && '' !== $note && $queue_id ) {
			$queue_manager->add_to_queue(
				'form',
				$form_id,
				'gf_add_note',
				[ 'email' => $contact_data['email'], 'note' => $note ],
				(int) $queue_id
			);
		}

		$this->save_entry_sync_meta(
			$entry_id,
			[
				'status'   => $queue_id ? 'queued' : 'queue_failed',
				'queue_id' => $queue_id ? (int) $queue_id : 0,
				'form_id'  => $form_id,
				'email'    => $contact_data['email'],
			]
		);

		/**
		 * Fires after a GF submission has been queued for sync.
		 * Pro uses this to queue workflow enrollment after contact creation.
		 *
		 * @param int|false $queue_id     Queue item ID of the contact sync.
		 * @param array     $contact_data Mapped contact payload.
		 * @param array     $entry        GF entry array.
		 * @param array     $form         GF form array.
		 * @param array     $config       Integration config.
		 */
		do_action( 'syncly_gf_submission_queued', $queue_id, $contact_data, $entry, $form, $config );
	}

	/**
	 * Record a completed contact sync against its Gravity Forms entry.
	 *
	 * @param object $item       Queue item.
	 * @param mixed  $contact_id GHL contact ID.
	 * @param mixed  $result     API result.
	 * @param array  $payload    Queue payload.
	 * @return void
	 */
	public function record_success( object $item, $contact_id, $result, array $payload ): void {
		if ( 'form' !== $item->item_type || empty( $payload['_syncly_gf_entry_id'] ) ) {
			return;
		}

		$this->save_entry_sync_meta(
			absint( $payload['_syncly_gf_entry_id'] ),
			[
				'status'     => 'completed',
				'queue_id'   => (int) $item->id,
				'contact_id' => sanitize_text_field( (string) $contact_id ),
			]
		);
	}

	/**
	 * Get recent GF submission queue records for the settings panel.
	 *
	 * @param int $form_id GF form ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_submission_history( int $form_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_queue';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, action, payload, status, error_message, created_at FROM {$table} WHERE item_type = %s AND item_id = %d AND site_id = %d ORDER BY id DESC LIMIT 20",
				'form',
				$form_id,
				get_current_blog_id()
			),
			ARRAY_A
		);

		$history = [];
		foreach ( (array) $rows as $row ) {
			$payload = json_decode( (string) $row['payload'], true );
			if ( ! is_array( $payload ) || empty( $payload['_syncly_gf_entry_id'] ) ) {
				continue;
			}

			$history[] = [
				'queue_id' => (int) $row['id'],
				'entry_id' => (int) $payload['_syncly_gf_entry_id'],
				'email'    => sanitize_text_field( (string) ( $payload['email'] ?? '' ) ),
				'action'   => sanitize_text_field( (string) $row['action'] ),
				'status'   => sanitize_key( (string) $row['status'] ),
				'error'    => sanitize_text_field( (string) $row['error_message'] ),
				'created'  => sanitize_text_field( (string) $row['created_at'] ),
			];
		}

		return $history;
	}

	/**
	 * Requeue one of this form's failed/completed contact jobs.
	 *
	 * @param int $form_id GF form ID.
	 * @return int|false Requeued queue ID, or false.
	 */
	private function maybe_resend_submission( int $form_id ) {
		if ( ! \Syncly\Integrations\Forms\FormSettings::is_pro_active() || empty( $_POST['syncly_gf_resend'] ) || empty( $_POST['syncly_gf_resend_nonce'] ) ) {
			return false;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['syncly_gf_resend_nonce'] ) ), 'syncly_gf_resend' ) ) {
			return false;
		}

		$queue_id = absint( $_POST['syncly_gf_resend'] );
		if ( ! $queue_id ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ghl_sync_queue';
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d AND item_type = %s AND item_id = %d AND site_id = %d", $queue_id, 'form', $form_id, get_current_blog_id() )
		);
		if ( ! $row ) {
			return false;
		}

		$updated = $wpdb->update(
			$table,
			[ 'status' => 'pending', 'attempts' => 0, 'error_message' => null, 'processed_at' => null, 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $queue_id ],
			[ '%s', '%d', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $updated ? $queue_id : false;
	}

	/**
	 * Store lightweight sync metadata on a GF entry.
	 *
	 * @param int   $entry_id Entry ID.
	 * @param array $meta     Metadata values.
	 * @return void
	 */
	private function save_entry_sync_meta( int $entry_id, array $meta ): void {
		if ( $entry_id > 0 && function_exists( 'gform_update_meta' ) ) {
			gform_update_meta( $entry_id, '_syncly_gf_sync', $meta );
		}
	}

	/**
	 * Map GF entry data to GHL contact fields
	 *
	 * @param array $entry         GF entry array.
	 * @param array $form          GF form array.
	 * @param array $field_mapping Field mapping config (GF field ID => GHL field).
	 * @return array Mapped data for the GHL API.
	 */
	private function map_submission_data( array $entry, array $form, array $field_mapping ): array {
		$contact_data  = [];
		$custom_fields = [];

		foreach ( $field_mapping as $gf_field_id => $ghl_field ) {
			$value = rgar( $entry, $gf_field_id );

			if ( '' === $value || null === $value ) {
				continue;
			}

			// Handle serialized/array values (checkboxes, multi-select, lists).
			$decoded = maybe_unserialize( $value );
			if ( is_array( $decoded ) ) {
				$decoded = array_filter( array_map( 'strval', $decoded ) );
				$value   = implode( ', ', $decoded );
			}

			$value = sanitize_textarea_field( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			// GHL custom fields are prefixed with "custom." in the fields
			// endpoint — strip the prefix and use the raw field ID (matches
			// the UserHooks convention).
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
	 * Register admin assets via AssetsManager
	 *
	 * @return void
	 */
	private function register_admin_assets(): void {
		$assets_manager = AssetsManager::get_instance();
		$ghl_tags       = TagManager::get_instance()->get_tags_for_localization();

		$assets_manager->add_admin_asset(
			'syncly-gf-css',
			[ 'toplevel_page_gf_edit_forms', 'forms_page_gf_edit_forms' ],
			'gf-integration.css',
			[ 'syncly-globals-css', 'syncly-select2-css' ],
			[],
			SYNCLY_VERSION
		);

		$assets_manager->add_admin_asset(
			'syncly-gf-js',
			[ 'toplevel_page_gf_edit_forms', 'forms_page_gf_edit_forms' ],
			'gf-integration.js',
			[ 'jquery', 'syncly-select2' ],
			[
				'tags'  => $ghl_tags,
				'nonce' => wp_create_nonce( 'syncly_field_mapping_nonce' ),
			],
			SYNCLY_VERSION,
			true
		);
	}
}
