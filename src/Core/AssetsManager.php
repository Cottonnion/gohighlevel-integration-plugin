<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

use GHL_CRM\API\ConnectionManager;
use GHL_CRM\Sync\TagManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets Manager class
 *
 * Handles enqueuing of CSS and Javascript
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core
 */
class AssetsManager {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Admin assets organized by page
	 *
	 * @var array<string, array<string, array>>
	 */
	private array $admin_assets = [];

	/**
	 * Public/frontend assets
	 *
	 * @var array<string, array>
	 */
	private array $public_assets = [];

	/**
	 * Block editor assets (Gutenberg editor screens)
	 *
	 * @var array<string, array>
	 */
	private array $block_editor_assets = [];

	/**
	 * Get class instance | singleton pattern
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
	 * Private constructor to prevent direct creation
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
		// Register external libraries (they'll only load when used as dependencies)
		add_action( 'admin_enqueue_scripts', [ $this, 'register_external_libraries' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_external_libraries' ], 5 );

		// Register admin assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Register public assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );

		// Register block editor assets (also register external libs for Select2, etc.)
		add_action( 'enqueue_block_editor_assets', [ $this, 'register_external_libraries' ], 5 );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

		// Remove TinyMCE branding
		add_filter( 'tiny_mce_before_init', [ $this, 'remove_tinymce_branding' ] );

		// Define assets after WordPress has loaded text domain
		add_action( 'init', [ $this, 'define_admin_assets' ] );
		add_action( 'init', [ $this, 'define_frontend_assets' ] );
		add_action( 'init', [ $this, 'define_block_editor_assets' ] );
	}

	/**
	 * Register external libraries (CDN and local)
	 * These are registered but not enqueued - they'll only load when used as dependencies
	 *
	 * @return void
	 */
	public function register_external_libraries(): void {
		// Register SweetAlert2 (local)
		wp_register_style(
			'ghl-sweetalert2',
			GHL_CRM_URL . 'assets/admin/css/sweetalert2.min.css',
			[],
			'11.26.22'
		);

		wp_register_script(
			'ghl-sweetalert2',
			GHL_CRM_URL . 'assets/admin/js/sweetalert2.all.min.js',
			[],
			'11.26.22',
			true
		);

		// Register Chart.js (local)
		wp_register_script(
			'ghl-chartjs',
			GHL_CRM_URL . 'assets/admin/js/chart.umd.min.js',
			[],
			'4.4.0',
			true
		);

		// Register Select2 (local files) with plugin-specific handles to avoid conflicts
		wp_register_style(
			'ghl-crm-select2-css',
			GHL_CRM_URL . 'assets/admin/css/select2.min.css',
			[],
			'4.1.0'
		);

		wp_register_script(
			'ghl-crm-select2',
			GHL_CRM_URL . 'assets/admin/js/select2.min.js',
			[ 'jquery' ],
			'4.1.0',
			true
		);
	}

	/**
	 * Define admin page assets
	 * Called on 'init' hook to ensure translations are available
	 *
	 * @return void
	 */
	public function define_admin_assets(): void {
		$settings           = SettingsManager::get_instance()->get_settings_array();
		$white_label_domain = $settings['ghl_white_label_domain'] ?? '';
		$ghl_tags           = TagManager::get_instance()->get_tags_for_localization();

		// Tooltip System (loads on all GHL admin pages)
		$this->add_admin_asset(
			'ghl-crm-tooltip-system',
			[
				'toplevel_page_ghl-crm-admin',              // New SPA page
				'toplevel_page_ghl-crm-settings',           // Legacy main tabbed page
				'ghl-crm_page_ghl-crm-sync-logs',          // Sync logs page
			],
			'tooltip-system.js',
			[],
			[],
			GHL_CRM_VERSION,
			true
		);

		// Admin Menu Styling (loads on all admin pages to style the menu)
		$this->add_admin_asset(
			'ghl-crm-admin-menu-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'admin-menu.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		// Menu Router for SPA active state management
		$this->add_admin_asset(
			'ghl-crm-menu-router-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'menu-router.js',
			[ 'jquery' ],
			[],
			GHL_CRM_VERSION,
			true
		);

		// SPA Application assets (new single-page admin)
		$this->add_admin_asset(
			'ghl-crm-spa-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'spa-app.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-spa-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'spa-router.js',
			[ 'jquery', 'ghl-sweetalert2' ],
			[],
			GHL_CRM_VERSION,
			true
		);

		// Upgrade notice (dismissible banner)
		$this->add_admin_asset(
			'ghl-crm-upgrade-notice-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'upgrade-notice.js',
			[ 'jquery' ],
			[],
			GHL_CRM_VERSION,
			true
		);

		// Dashboard assets (loads on SPA page)
		$this->add_admin_asset(
			'ghl-crm-dashboard-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'dashboard.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-dashboard-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'dashboard.js',
			[ 'jquery', 'ghl-sweetalert2', 'ghl-chartjs' ],
			[
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'ghl_crm_admin' ),
				'manualConnectNonce' => wp_create_nonce( 'ghl_crm_manual_connect' ),
				'disconnectNonce'    => wp_create_nonce( 'ghl_crm_oauth_disconnect' ),
				'manualQueueNonce'   => wp_create_nonce( 'ghl_crm_manual_queue' ),
				'settingsNonce'      => wp_create_nonce( 'ghl_crm_settings_nonce' ),
				'i18n'               => [
					'connecting'                => __( 'Connecting...', 'syncly' ),
					'connectionFailed'          => __( 'Connection failed', 'syncly' ),
					'connectionError'           => __( 'An error occurred while connecting', 'syncly' ),
					'disconnectConfirm'         => __( 'Are you sure you want to disconnect your GoHighLevel account?', 'syncly' ),
					'disconnecting'             => __( 'Disconnecting...', 'syncly' ),
					'disconnectFailed'          => __( 'Failed to disconnect', 'syncly' ),
					'disconnectError'           => __( 'An error occurred while disconnecting', 'syncly' ),
					'manualSyncProcessing'      => __( 'Running manual sync...', 'syncly' ),
					'manualSyncSuccess'         => __( 'Manual sync completed successfully.', 'syncly' ),
					'manualSyncFailed'          => __( 'Manual sync failed.', 'syncly' ),
					'clearCacheProcessing'      => __( 'Clearing cache...', 'syncly' ),
					'clearCacheSuccess'         => __( 'Cache cleared successfully!', 'syncly' ),
					'clearCacheFailed'          => __( 'Failed to clear cache.', 'syncly' ),
					'testConnectionProcessing'  => __( 'Testing connection...', 'syncly' ),
					'testConnectionSuccess'     => __( 'Connection test completed successfully.', 'syncly' ),
					'testConnectionFailed'      => __( 'Connection test failed.', 'syncly' ),
					'refreshMetadataProcessing' => __( 'Refreshing tags and fields...', 'syncly' ),
					'refreshMetadataSuccess'    => __( 'Tags and fields refreshed successfully.', 'syncly' ),
					'refreshMetadataFailed'     => __( 'Failed to refresh tags and fields.', 'syncly' ),
				],
			],
			GHL_CRM_VERSION,
			true
		);

		// Analytics assets (loads on SPA page for analytics view)
		$this->add_admin_asset(
			'ghl-crm-analytics-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'analytics.js',
			[ 'jquery', 'ghl-sweetalert2', 'ghl-chartjs' ],
			[],
			GHL_CRM_VERSION,
			true
		);
		// Field Mapping assets (need to load on SPA page)
		$this->add_admin_asset(
			'ghl-crm-field-mapping-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'field-mapping.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-field-mapping-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'field-mapping.js',
			[ 'jquery', 'ghl-sweetalert2' ],
			[
				'nonce' => wp_create_nonce( 'ghl_crm_field_mapping_nonce' ),
			],
			GHL_CRM_VERSION,
			true
		);

		// Integrations assets (need to load on SPA page)
		$this->add_admin_asset(
			'synclys-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'integrations.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'synclys-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'integrations.js',
			[ 'jquery', 'ghl-sweetalert2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
				'tags'    => $ghl_tags,
			],
			GHL_CRM_VERSION,
			true
		);

		// Settings assets (need to load on SPA page)
		$this->add_admin_asset(
			'ghl-crm-settings-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'settings.css',
			[ 'ghl-sweetalert2', 'ghl-crm-select2-css' ],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-advanced-settings-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'advanced-settings.css',
			[ 'ghl-crm-settings-css' ],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-settings-menu-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'settings-menu.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-settings-menu-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'settings-menu.js',
			[ 'jquery' ],
			[],
			GHL_CRM_VERSION,
			true
		);

		$this->add_admin_asset(
			'ghl-crm-settings-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'settings.js',
			[ 'jquery', 'ghl-sweetalert2', 'ghl-crm-select2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
				'tags'    => $ghl_tags,
			],
			GHL_CRM_VERSION,
			true
		);

		// Sync Preview assets
		$this->add_admin_asset(
			'ghl-crm-sync-preview-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'sync-preview.css',
			[ 'ghl-crm-settings-css' ],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-sync-preview-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'sync-preview.js',
			[ 'jquery', 'ghl-sweetalert2', 'ghl-crm-select2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
				'i18n'    => [
					'searchPlaceholder'  => __( 'Search for a user...', 'syncly' ),
					'missingInfo'        => __( 'Missing Information', 'syncly' ),
					'selectUser'         => __( 'Please select a user from the dropdown', 'syncly' ),
					'validatingUser'     => __( 'Validating User...', 'syncly' ),
					'lookingUpUser'      => __( 'Looking up WordPress user...', 'syncly' ),
					'connectingGHL'      => __( 'Connecting to GoHighLevel...', 'syncly' ),
					'establishingAPI'    => __( 'Establishing API connection...', 'syncly' ),
					'analyzingFields'    => __( 'Analyzing Field Mappings...', 'syncly' ),
					'comparingData'      => __( 'Comparing WordPress and GHL data...', 'syncly' ),
					'previewFailed'      => __( 'Preview Failed', 'syncly' ),
					'unknownError'       => __( 'Unknown error occurred', 'syncly' ),
					'requestFailed'      => __( 'Request Failed', 'syncly' ),
					'connectionError'    => __( 'Could not connect to server. Please check your connection and try again.', 'syncly' ),
					'totalFields'        => __( 'Total Fields', 'syncly' ),
					'willChange'         => __( 'Will Change', 'syncly' ),
					'alreadySynced'      => __( 'Already Synced', 'syncly' ),
					'tagsWillApply'      => __( 'tags will be applied', 'syncly' ),
					'updatingExisting'   => __( 'Updating existing contact:', 'syncly' ),
					'conflictsDetected'  => __( 'Conflicts Detected', 'syncly' ),
					'validationWarnings' => __( 'Validation Warnings', 'syncly' ),
					'fieldMapping'       => __( 'Field Mapping Comparison', 'syncly' ),
					'ghlField'           => __( 'GHL Field', 'syncly' ),
					'currentGHL'         => __( 'Current in GHL', 'syncly' ),
					'wpValue'            => __( 'WordPress Value', 'syncly' ),
					'status'             => __( 'Status', 'syncly' ),
					'willUpdate'         => __( 'WILL UPDATE', 'syncly' ),
					'inSync'             => __( 'IN SYNC', 'syncly' ),
					'tagsToApply'        => __( 'Tags to Apply', 'syncly' ),
					'syncPreview'        => __( 'Sync Preview', 'syncly' ),
					'gotIt'              => __( 'Got it!', 'syncly' ),
				],
			],
			GHL_CRM_VERSION,
			true
		);

		// Global CSS for all admin pages (now includes tabbed main-settings page)
		$this->add_admin_asset(
			'ghl-crm-globals-css',
			[
				'toplevel_page_ghl-crm-settings',           // Legacy main tabbed page
				'toplevel_page_ghl-crm-admin',              // New SPA page
				'ghl-crm_page_ghl-crm-sync-logs',
				'toplevel_page_wpcf7',
			],
			'globals.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		// Settings page CSS (loads on main-settings page for all tabs)
		$this->add_admin_asset(
			'ghl-crm-settings-css',
			[ 'toplevel_page_ghl-crm-settings', 'toplevel_page_wpcf7' ],
			'settings.css',
			[ 'ghl-crm-globals-css', 'ghl-sweetalert2', 'ghl-crm-select2-css' ],
			[],
			GHL_CRM_VERSION
		);

		// Settings page JS (loads on main-settings page for settings tab)
		$this->add_admin_asset(
			'ghl-crm-settings-js',
			[ 'toplevel_page_ghl-crm-settings' ],
			'settings.js',
			[ 'jquery', 'ghl-sweetalert2', 'ghl-crm-select2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
				'tags'    => $ghl_tags,
			],
			GHL_CRM_VERSION,
			true
		);

		// Tools page JS (loads on main-settings page for tools tab)
		$this->add_admin_asset(
			'ghl-crm-tools-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'tools.js',
			[ 'jquery', 'ghl-sweetalert2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
			],
			GHL_CRM_VERSION,
			true
		);

		// Field Mapping CSS (loads on main-settings page for field-mapping tab)
		$this->add_admin_asset(
			'ghl-crm-field-mapping-css',
			[ 'toplevel_page_ghl-crm-settings' ],
			'field-mapping.css',
			[ 'ghl-crm-globals-css' ],
			[],
			GHL_CRM_VERSION
		);

		// Field Mapping JS (loads on main-settings page for field-mapping tab)
		$this->add_admin_asset(
			'ghl-crm-field-mapping-js',
			[ 'toplevel_page_ghl-crm-settings' ],
			'field-mapping.js',
			[ 'jquery', 'ghl-sweetalert2' ],
			[
				'nonce' => wp_create_nonce( 'ghl_crm_field_mapping_nonce' ),
			],
			GHL_CRM_VERSION,
			true
		);
		// Integrations CSS (loads on main-settings page for integrations tab)
		$this->add_admin_asset(
			'synclys-css',
			[ 'toplevel_page_ghl-crm-settings' ],
			'integrations.css',
			[ 'ghl-crm-globals-css' ],
			[],
			GHL_CRM_VERSION
		);

		// Integrations JS (loads on main-settings page for integrations tab)
		$this->add_admin_asset(
			'synclys-js',
			[ 'toplevel_page_ghl-crm-settings' ],
			'integrations.js',
			[ 'jquery', 'ghl-sweetalert2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
				'tags'    => $ghl_tags,
			],
			GHL_CRM_VERSION,
			true
		);

		// Sync Logs page assets (separate page)
		$this->add_admin_asset(
			'ghl-crm-sync-logs-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'sync-logs.css',
			[ 'ghl-crm-globals-css' ],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-sync-logs-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'sync-logs.js',
			[ 'jquery', 'ghl-sweetalert2' ],
			[
				'nonce'      => wp_create_nonce( 'ghl_sync_logs_nonce' ),
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'upgradeUrl' => apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/' ),
			],
			GHL_CRM_VERSION,
			true
		);

		// Forms page assets (SPA)
		$this->add_admin_asset(
			'ghl-crm-forms-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'forms.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		$is_pro_active = (bool) apply_filters( 'ghl_crm_is_pro_active', false );
		$upgrade_url   = apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/' );

		$this->add_admin_asset(
			'ghl-crm-forms-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'forms.js',
			[ 'jquery' ],
			[
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'ghl_crm_forms_nonce' ),
				'whiteLabelDomain' => $white_label_domain,
				'formSettings'     => $this->get_all_form_settings(),
				'isPro'            => $is_pro_active,
				'upgradeUrl'       => $upgrade_url,
				'proFormsMockup'   => $is_pro_active ? '' : $this->get_forms_addon_mockup_html( $upgrade_url ),
				'strings'          => [
					'errorLoad'        => __( 'Failed to load forms', 'syncly' ),
					'ajaxError'        => __( 'AJAX error: ', 'syncly' ),
					'noForms'          => __( 'No Forms Found', 'syncly' ),
					'noFormsDesc'      => __( 'Create forms in your GoHighLevel account to display them here.', 'syncly' ),
					'untitledForm'     => __( 'Untitled Form', 'syncly' ),
					'formName'         => __( 'Form Name', 'syncly' ),
					'formId'           => __( 'Form ID', 'syncly' ),
					'submissions'      => __( 'Submissions', 'syncly' ),
					'shortcode'        => __( 'Shortcode', 'syncly' ),
					'actions'          => __( 'Actions', 'syncly' ),
					'clickToCopy'      => __( 'Click to copy shortcode', 'syncly' ),
					'preview'          => __( 'Preview', 'syncly' ),
					'copy'             => __( 'Copy', 'syncly' ),
					'whiteLabelNotice' => __( 'Using white label domain', 'syncly' ),
					'proBadge'         => __( 'PRO', 'syncly' ),
					'upgradeNow'       => __( 'Learn More', 'syncly' ),
					'proFeature'       => __( 'Available in the companion add-on', 'syncly' ),
					'unlockFeature'    => __( 'Learn more about the companion add-on.', 'syncly' ),
				],
			],
			GHL_CRM_VERSION,
			true
		);

		// Setup Wizard assets (loads only on setup wizard page)
		$this->add_admin_asset(
			'ghl-crm-setup-wizard-css',
			[ 'admin_page_ghl-crm-setup-wizard' ],
			'setup-wizard.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		// Also load dashboard.css on setup wizard for consistent styling (connection tabs, etc.)
		$this->add_admin_asset(
			'ghl-crm-setup-wizard-dashboard-css',
			[ 'admin_page_ghl-crm-setup-wizard' ],
			'dashboard.css',
			[ 'ghl-crm-setup-wizard-css' ],
			[],
			GHL_CRM_VERSION
		);

		// Correctly fetch connection tokens from the settings array
		$settings_manager = SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$location_id      = $settings['location_id'] ?? $settings['oauth_location_id'] ?? '';
		$oauth_token      = $settings['oauth_access_token'] ?? '';
		$api_token        = $settings['api_token'] ?? '';

		// Get current setting values for pre-population
		$enable_user_sync              = $settings['enable_user_sync'] ?? false;
		$user_sync_actions             = $settings['user_sync_actions'] ?? [];
		$user_register_enabled         = in_array( 'user_register', $user_sync_actions, true );
		$user_register_tags            = $settings_manager->get_location_register_tags( $location_id );
		$wc_enabled                    = $settings['wc_enabled'] ?? false;
		$buddyboss_enabled             = $settings['buddyboss_enabled'] ?? false;
		$delete_contact_on_user_delete = $settings['delete_contact_on_user_delete'] ?? false;
		$enable_sync_logging           = $settings['enable_sync_logging'] ?? false;
		$role_tags_config              = $settings_manager->get_location_role_tags( $location_id );
		if ( empty( $role_tags_config ) && ! empty( $settings['role_tags'] ) && is_array( $settings['role_tags'] ) ) {
			$role_tags_config = $settings['role_tags'];
		}
		$enable_role_tags = ! empty( $role_tags_config );

		$this->add_admin_asset(
			'ghl-crm-setup-wizard-js',
			[ 'admin_page_ghl-crm-setup-wizard' ],
			'setup-wizard.js',
			[ 'jquery', 'ghl-sweetalert2' ],
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'ghl_crm_spa_nonce' ),
				'oauthUrl'     => admin_url( 'admin.php?page=ghl-crm-oauth-connect' ),
				'settingsUrl'  => admin_url( 'admin.php?page=ghl-crm-admin#/settings' ),
				'dashboardUrl' => admin_url( 'admin.php?page=ghl-crm-admin' ),
				'isConnected'  => ( ! empty( $oauth_token ) || ! empty( $api_token ) ) ? '1' : '0',
				'tags'         => $ghl_tags,
				'settings'     => [
					'enable_user_sync'              => $enable_user_sync,
					'user_register'                 => $user_register_enabled,
					'user_register_tags'            => $user_register_tags,
					'woocommerce'                   => $wc_enabled,
					'buddyboss'                     => $buddyboss_enabled,
					'delete_contact_on_user_delete' => $delete_contact_on_user_delete,
					'enable_sync_logging'           => $enable_sync_logging,
					'enable_role_tags'              => $enable_role_tags,
				],
			],
			GHL_CRM_VERSION,
			true
		);
	}

	/**
	 * Adds an admin script or style to the array of admin assets.
	 * Supports page slugs or custom post type keys (e.g., 'cpt:product').
	 * Optionally supports a custom base URL for loading from other plugins.
	 *
	 * @param string      $handle           Unique handle for the asset.
	 * @param array       $pages            Array of admin page IDs or 'cpt:posttype' keys.
	 * @param string      $file             File name (e.g., 'settings.css' or 'settings.js').
	 * @param array       $dependencies     Array of dependency handles.
	 * @param array       $localization     Array of data to localize for scripts.
	 * @param string      $version          Version string for cache busting.
	 * @param bool        $enqueue_in_footer Whether to enqueue script in footer (scripts only).
	 * @param string|null $base_url    Optional custom base URL for the asset (must end with slash).
	 * @return void
	 */
	public function add_admin_asset(
		string $handle,
		array $pages,
		string $file,
		array $dependencies = [],
		array $localization = [],
		string $version = GHL_CRM_VERSION,
		bool $enqueue_in_footer = true,
		?string $base_url = null
	): void {
		foreach ( $pages as $page ) {
			$this->admin_assets[ $page ][ $handle ] = [
				'file'              => $file,
				'dependencies'      => $dependencies,
				'version'           => $version,
				'enqueue_in_footer' => $enqueue_in_footer,
				'localization'      => $localization,
				'base_url'          => $base_url,
			];
		}
	}

	/**
	 * Enqueue admin assets based on current screen
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen ) {
			return;
		}
		$screen_id = $current_screen->id;
		$post_type = isset( $current_screen->post_type ) ? $current_screen->post_type : null;

		// Enqueue WordPress editor assets on admin pages that might need them
		if ( in_array( $screen_id, [ 'toplevel_page_ghl-crm-admin', 'toplevel_page_ghl-crm-settings' ], true ) ) {
			wp_enqueue_editor();
		}

		// Enqueue assets registered for this screen ID
		if ( isset( $this->admin_assets[ $screen_id ] ) ) {
			foreach ( $this->admin_assets[ $screen_id ] as $handle => $asset ) {
				$this->enqueue_asset( $handle, $asset, 'admin' );
			}
		}

		// Enqueue assets registered for this post type (e.g., 'cpt:product')
		if ( $post_type ) {
			$cpt_key = 'cpt:' . $post_type;
			if ( isset( $this->admin_assets[ $cpt_key ] ) ) {
				foreach ( $this->admin_assets[ $cpt_key ] as $handle => $asset ) {
					$this->enqueue_asset( $handle, $asset, 'admin' );
				}
			}
		}
	}

	/**
	 * Enqueue a single asset (style or script)
	 *
	 * @param string $handle Asset handle.
	 * @param array  $asset  Asset configuration.
	 * @param string $context Context: 'admin' or 'public'.
	 * @return void
	 */
	private function enqueue_asset( string $handle, array $asset, string $context = 'admin' ): void {
		$file      = $asset['file'];
		$deps      = $asset['dependencies'] ?? [];
		$version   = $asset['version'] ?? GHL_CRM_VERSION;
		$in_footer = $asset['enqueue_in_footer'] ?? true;
		$localize  = $asset['localization'] ?? [];
		$base_url  = $asset['base_url'] ?? null;

		// Determine if it's a CSS or JS file
		$file_extension = pathinfo( $file, PATHINFO_EXTENSION );
		$is_style       = ( 'css' === $file_extension );

		// Use minified version when not in debug mode.
		$file = $this->maybe_use_min_file( $file, $base_url, $context, $is_style );

		// Build file URL
		if ( $base_url ) {
			$file_url = rtrim( $base_url, '/' ) . '/' . $file;
		} elseif ( 'admin' === $context ) {
			$file_url = GHL_CRM_URL . 'assets/admin/' . ( $is_style ? 'css/' : 'js/' ) . $file;
		} else {
			$file_url = GHL_CRM_URL . 'assets/public/' . ( $is_style ? 'css/' : 'js/' ) . $file;
		}

		// Enqueue the asset
		if ( $is_style ) {
			wp_enqueue_style(
				$handle,
				$file_url,
				$deps,
				$version
			);
		} else {
			wp_enqueue_script(
				$handle,
				$file_url,
				$deps,
				$version,
				$in_footer
			);

			// Add localization if provided
			if ( ! empty( $localize ) ) {
				wp_localize_script(
					$handle,
					str_replace( '-', '_', $handle . '_data' ),
					$localize
				);
			}
		}
	}

	/**
	 * Swap a filename for its .min counterpart when it exists on disk and SCRIPT_DEBUG is off.
	 *
	 * @param string      $file     Original filename (e.g. 'style.css').
	 * @param string|null $base_url Optional custom base URL supplied by the caller.
	 * @param string      $context  'admin' or 'public'.
	 * @param bool        $is_style Whether the file is a stylesheet.
	 * @return string The (possibly swapped) filename.
	 */
	private function maybe_use_min_file( string $file, ?string $base_url, string $context, bool $is_style ): string {
		// Never swap when WordPress is in script-debug mode.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return $file;
		}

		// Already minified — nothing to do.
		if ( preg_match( '/\.min\.(css|js)$/i', $file ) ) {
			return $file;
		}

		// Build the minified filename.
		$ext      = $is_style ? '.css' : '.js';
		$min_file = substr( $file, 0, -strlen( $ext ) ) . '.min' . $ext;

		/*
		 * Resolve the absolute filesystem path so we can check file_exists().
		 *
		 * When a $base_url is provided the asset may live outside this plugin
		 * (e.g. the pro add-on).  We convert the URL back to a path by
		 * replacing the plugins URL prefix with the plugins directory prefix.
		 */
		if ( $base_url ) {
			// Normalise: plugins_url() may or may not have a trailing slash.
			$plugins_url  = trailingslashit( plugins_url() );
			$plugins_dir  = trailingslashit( WP_PLUGIN_DIR );
			$relative_url = str_replace( $plugins_url, '', trailingslashit( $base_url ) );
			$abs_path     = $plugins_dir . $relative_url . $min_file;
		} else {
			$sub_dir  = ( 'admin' === $context )
				? 'assets/admin/' . ( $is_style ? 'css/' : 'js/' )
				: 'assets/public/' . ( $is_style ? 'css/' : 'js/' );
			$abs_path = GHL_CRM_PATH . $sub_dir . $min_file;
		}

		return file_exists( $abs_path ) ? $min_file : $file;
	}

	/**
	 * Add a public/frontend asset
	 *
	 * @param string      $handle            Unique handle for the asset.
	 * @param string      $file              File name (e.g., 'style.css' or 'script.js').
	 * @param array       $dependencies      Array of dependency handles.
	 * @param array       $localization      Array of data to localize for scripts.
	 * @param string      $version           Version string for cache busting.
	 * @param bool        $enqueue_in_footer Whether to enqueue script in footer (scripts only).
	 * @param string|null $base_url          Optional custom base URL for the asset (must end with slash if provided).
	 * @return void
	 */
	public function add_public_asset(
		string $handle,
		string $file,
		array $dependencies = [],
		array $localization = [],
		string $version = GHL_CRM_VERSION,
		bool $enqueue_in_footer = true,
		?string $base_url = null
	): void {
		$this->public_assets[ $handle ] = [
			'file'              => $file,
			'dependencies'      => $dependencies,
			'version'           => $version,
			'enqueue_in_footer' => $enqueue_in_footer,
			'localization'      => $localization,
			'base_url'          => $base_url,
		];
	}

	/**
	 * Define frontend/public assets
	 * Called on 'init' hook to ensure translations are available
	 *
	 * @return void
	 */
	public function define_frontend_assets(): void {
		// GHL Forms frontend CSS
		$this->add_public_asset(
			'ghl-crm-forms-frontend-css',
			'forms.css',
			[],
			[],
			GHL_CRM_VERSION,
			false
		);

		// Restrictions frontend CSS (used by membership restrictions)
		$this->add_public_asset(
			'ghl-restrictions',
			'restrictions.css',
			[],
			[],
			GHL_CRM_VERSION,
			false,
			GHL_CRM_URL . 'assets/frontend/css/'
		);

		// Blocks frontend CSS (for block render output)
		$this->add_public_asset(
			'ghl-crm-blocks',
			'blocks-frontend.css',
			[],
			[],
			GHL_CRM_VERSION,
			false,
			GHL_CRM_URL . 'assets/blocks/'
		);

		// GHL Forms frontend JS
		// $this->add_public_asset(
		// 'ghl-crm-forms-frontend-js',
		// 'form-handler.js',
		// [ 'jquery' ],
		// [
		// 'ajax_url' => admin_url( 'admin-ajax.php' ),
		// 'nonce'    => wp_create_nonce( 'ghl_form_submission' ),
		// ],
		// GHL_CRM_VERSION,
		// true
		// );

		// GHL Form Auto-fill (experimental - tests URL parameter pre-filling)
		$user_data         = $this->get_current_user_data_for_autofill();
		$all_form_settings = \GHL_CRM\Integrations\Forms\FormSettings::get_instance()->get_all_settings();

		$is_pro_active = \GHL_CRM\Integrations\Forms\FormSettings::is_pro_active();

		// Resolve custom parameters for each form when the companion add-on enables them.
		foreach ( $all_form_settings as $form_id => $form_config ) {
			if ( ! $is_pro_active ) {
				$all_form_settings[ $form_id ]['submission_limit']  = 'unlimited';
				$all_form_settings[ $form_id ]['submitted_message'] = '';
				unset( $all_form_settings[ $form_id ]['custom_params'], $all_form_settings[ $form_id ]['resolved_params'] );
				continue;
			}

			$resolved_params = apply_filters( 'ghl_crm_form_custom_params', [], (string) $form_id );
			if ( ! empty( $resolved_params ) && is_array( $resolved_params ) ) {
				$all_form_settings[ $form_id ]['resolved_params'] = $resolved_params;
			} else {
				unset( $all_form_settings[ $form_id ]['resolved_params'] );
			}
		}

		// Get white label domain
		$settings           = SettingsManager::get_instance()->get_settings_array();
		$white_label_domain = $settings['ghl_white_label_domain'] ?? '';

		$this->add_public_asset(
			'ghl-form-autofill',
			'form-autofill.js',
			[],
			[
				'userData'         => $user_data,
				'formSettings'     => $all_form_settings,
				'whiteLabelDomain' => $white_label_domain,
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'ghl_form_submission' ),
			],
			GHL_CRM_VERSION,
			true
		);
	}

	/**
	 * Enqueue public/frontend assets
	 *
	 * @return void
	 */
	public function enqueue_public_assets(): void {
		foreach ( $this->public_assets as $handle => $asset ) {
			$this->enqueue_asset( $handle, $asset, 'public' );
		}
	}

	/**
	 * Enqueue a single public asset by handle.
	 *
	 * @param string $handle Asset handle registered via add_public_asset().
	 * @return void
	 */
	public function enqueue_public_asset( string $handle ): void {
		if ( isset( $this->public_assets[ $handle ] ) ) {
			$this->enqueue_asset( $handle, $this->public_assets[ $handle ], 'public' );
		}
	}

	/**
	 * Add a block editor asset (for Gutenberg editor screens).
	 *
	 * Unlike admin assets (matched by page slug), block editor assets load on
	 * all block editor screens via the `enqueue_block_editor_assets` hook.
	 *
	 * @param string $handle        Unique handle for the asset.
	 * @param string $file_url      Full URL to the asset file.
	 * @param array  $dependencies  Array of dependency handles.
	 * @param string $version       Version string for cache busting.
	 * @param bool   $in_footer     Whether to enqueue script in footer (scripts only).
	 * @param array  $localizations Array of ['name' => string, 'data' => array] pairs.
	 * @return void
	 */
	public function add_block_editor_asset(
		string $handle,
		string $file_url,
		array $dependencies = [],
		string $version = GHL_CRM_VERSION,
		bool $in_footer = true,
		array $localizations = []
	): void {
		$this->block_editor_assets[ $handle ] = [
			'file_url'      => $file_url,
			'dependencies'  => $dependencies,
			'version'       => $version,
			'in_footer'     => $in_footer,
			'localizations' => $localizations,
		];
	}

	/**
	 * Enqueue all registered block editor assets.
	 *
	 * Hooked to `enqueue_block_editor_assets`.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets(): void {
		foreach ( $this->block_editor_assets as $handle => $asset ) {
			$file_url = $asset['file_url'];
			$deps     = $asset['dependencies'] ?? [];
			$version  = $asset['version'] ?? GHL_CRM_VERSION;
			$ext      = pathinfo( wp_parse_url( $file_url, PHP_URL_PATH ) ?: $file_url, PATHINFO_EXTENSION );

			if ( 'css' === $ext ) {
				wp_enqueue_style( $handle, $file_url, $deps, $version );
			} else {
				wp_enqueue_script( $handle, $file_url, $deps, $version, $asset['in_footer'] ?? true );

				foreach ( $asset['localizations'] ?? [] as $localization ) {
					if ( ! empty( $localization['name'] ) && ! empty( $localization['data'] ) ) {
						wp_localize_script( $handle, $localization['name'], $localization['data'] );
					}
				}
			}
		}
	}

	/**
	 * Define block editor assets.
	 *
	 * Called on 'init' hook to ensure translations and connection data are available.
	 *
	 * @return void
	 */
	public function define_block_editor_assets(): void {
		$connection_status = ConnectionManager::get_instance()->get_connection_status();
		$is_connected      = ( $connection_status['has_credentials'] && $connection_status['is_verified'] );

		// GHL Form Block JS
		$this->add_block_editor_asset(
			'ghl-crm-form-block',
			GHL_CRM_URL . 'assets/blocks/ghl-form/index.js',
			[ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
			GHL_CRM_VERSION,
			true,
			[
				[
					'name' => 'ghlCrmSettings',
					'data' => [
						'locationId'  => $connection_status['location_id'] ?? '',
						'connected'   => $is_connected,
						'settingsUrl' => admin_url( 'admin.php?page=ghl-crm-settings' ),
					],
				],
			]
		);

		// GHL Form Block Editor CSS
		$this->add_block_editor_asset(
			'ghl-crm-form-block-editor',
			GHL_CRM_URL . 'assets/blocks/ghl-form/editor.css',
			[ 'wp-edit-blocks' ],
			GHL_CRM_VERSION
		);

		// Restricted Content Block JS
		$formatted_tags = [];
		if ( $is_connected ) {
			$tag_manager = TagManager::get_instance();
			$tags        = $tag_manager->get_tags( false );
			foreach ( $tags as $tag ) {
				$formatted_tags[] = [
					'id'   => $tag['id'] ?? '',
					'text' => $tag['name'] ?? $tag['id'] ?? '',
				];
			}
		}

		$this->add_block_editor_asset(
			'ghl-crm-restricted-content-block',
			GHL_CRM_URL . 'assets/blocks/restricted-content/index.js',
			[ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-block-editor', 'wp-components', 'wp-i18n', 'jquery', 'ghl-crm-select2' ],
			GHL_CRM_VERSION,
			true,
			[
				[
					'name' => 'ghlRestrictedBlock',
					'data' => [
						'tags'      => $formatted_tags,
						'connected' => $is_connected,
					],
				],
			]
		);

		// Restricted Content Block Editor CSS
		$this->add_block_editor_asset(
			'ghl-crm-restricted-content-block-editor',
			GHL_CRM_URL . 'assets/blocks/restricted-content/editor.css',
			[ 'wp-edit-blocks', 'ghl-crm-select2-css' ],
			GHL_CRM_VERSION
		);
	}

	/**
	 * Remove TinyMCE branding
	 *
	 * @param array $options TinyMCE options.
	 * @return array
	 */
	public function remove_tinymce_branding( array $options ): array {
		$options['branding'] = false;
		return $options;
	}

	/**
	 * Get current logged-in user data for form auto-fill
	 *
	 * @return array User data array with common fields
	 */
	private function get_current_user_data_for_autofill(): array {
		if ( ! is_user_logged_in() ) {
			return [];
		}

		$user = wp_get_current_user();

		// Build user data array with common form fields
		$user_data = [
			'email'        => $user->user_email,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'name'         => trim( $user->first_name . ' ' . $user->last_name ),
			'user_login'   => $user->user_login,
			'display_name' => $user->display_name,
		];

		// Get phone from user meta (common meta keys)
		$phone = get_user_meta( $user->ID, 'billing_phone', true );
		if ( empty( $phone ) ) {
			$phone = get_user_meta( $user->ID, 'phone', true );
		}
		if ( ! empty( $phone ) ) {
			$user_data['phone'] = $phone;
			// Also add common variations
			$user_data['Phone']        = $phone;
			$user_data['phone_number'] = $phone;
		}

		// Get additional common fields from user meta
		$meta_mappings = [
			'billing_first_name' => 'billing_first_name',
			'billing_last_name'  => 'billing_last_name',
			'billing_company'    => 'company',
			'billing_address_1'  => 'address',
			'billing_city'       => 'city',
			'billing_state'      => 'state',
			'billing_postcode'   => 'zip',
			'billing_country'    => 'country',
		];

		foreach ( $meta_mappings as $meta_key => $field_name ) {
			$value = get_user_meta( $user->ID, $meta_key, true );
			if ( ! empty( $value ) ) {
				$user_data[ $field_name ] = $value;
			}
		}

		// Filter empty values
		$user_data = array_filter(
			$user_data,
			function ( $value ) {
				return ! empty( $value );
			}
		);

		return $user_data;
	}

	/**
	 * Get all form settings from FormSettings class
	 *
	 * @return array All form settings
	 */
	private function get_all_form_settings(): array {
		$form_settings = \GHL_CRM\Integrations\Forms\FormSettings::get_instance();
		return $form_settings->get_all_settings();
	}

	/**
	 * Get the free-plugin forms add-on preview markup.
	 *
	 * @param string $upgrade_url Companion add-on URL.
	 * @return string Preview markup.
	 */
	private function get_forms_addon_mockup_html( string $upgrade_url ): string {
		ob_start();
		?>
		<div class="ghl-settings-group ghl-pro-preview-card">
			<div class="ghl-pro-preview-header">
				<h3>
					<span class="dashicons dashicons-lock"></span>
					<?php esc_html_e( 'Advanced Form Controls', 'syncly' ); ?>
					<span class="ghl-pro-badge-small"><?php esc_html_e( 'Add-on', 'syncly' ); ?></span>
				</h3>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="ghl-button ghl-button-secondary">
					<span class="dashicons dashicons-unlock"></span>
					<?php esc_html_e( 'Learn More', 'syncly' ); ?>
				</a>
			</div>
			<div class="ghl-pro-preview-surface" aria-hidden="true">
				<div class="ghl-pro-preview-section">
					<div class="ghl-pro-preview-title-row">
						<span class="ghl-pro-preview-title"><?php esc_html_e( 'Auto-fill Form Data', 'syncly' ); ?></span>
						<span class="ghl-pro-preview-toggle is-on"></span>
					</div>
					<p class="ghl-pro-preview-copy"><?php esc_html_e( 'Pass logged-in user data into embedded forms.', 'syncly' ); ?></p>
				</div>
				<div class="ghl-pro-preview-section">
					<div class="ghl-pro-preview-title-row">
						<span class="ghl-pro-preview-title"><?php esc_html_e( 'Submission Controls', 'syncly' ); ?></span>
						<span class="ghl-pro-preview-pill"><?php esc_html_e( 'Once per user', 'syncly' ); ?></span>
					</div>
					<div class="ghl-pro-preview-message"><?php esc_html_e( 'Thank you. You have already submitted this form.', 'syncly' ); ?></div>
				</div>
				<div class="ghl-pro-preview-section">
					<div class="ghl-pro-preview-title-row">
						<span class="ghl-pro-preview-title"><?php esc_html_e( 'Custom URL Parameters', 'syncly' ); ?></span>
						<span class="ghl-pro-preview-pill"><?php esc_html_e( 'Dynamic', 'syncly' ); ?></span>
					</div>
					<div class="ghl-pro-preview-param"><code>{user_email}</code><span>=</span><code>email</code></div>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
