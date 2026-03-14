<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

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

		// Remove TinyMCE branding
		add_filter( 'tiny_mce_before_init', [ $this, 'remove_tinymce_branding' ] );

		// Define assets after WordPress has loaded text domain
		add_action( 'init', [ $this, 'define_admin_assets' ] );
		add_action( 'init', [ $this, 'define_frontend_assets' ] );
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
					'connecting'                => __( 'Connecting...', 'ghl-crm-integration' ),
					'connectionFailed'          => __( 'Connection failed', 'ghl-crm-integration' ),
					'connectionError'           => __( 'An error occurred while connecting', 'ghl-crm-integration' ),
					'disconnectConfirm'         => __( 'Are you sure you want to disconnect your GoHighLevel account?', 'ghl-crm-integration' ),
					'disconnecting'             => __( 'Disconnecting...', 'ghl-crm-integration' ),
					'disconnectFailed'          => __( 'Failed to disconnect', 'ghl-crm-integration' ),
					'disconnectError'           => __( 'An error occurred while disconnecting', 'ghl-crm-integration' ),
					'manualSyncProcessing'      => __( 'Running manual sync...', 'ghl-crm-integration' ),
					'manualSyncSuccess'         => __( 'Manual sync completed successfully.', 'ghl-crm-integration' ),
					'manualSyncFailed'          => __( 'Manual sync failed.', 'ghl-crm-integration' ),
					'clearCacheProcessing'      => __( 'Clearing cache...', 'ghl-crm-integration' ),
					'clearCacheSuccess'         => __( 'Cache cleared successfully!', 'ghl-crm-integration' ),
					'clearCacheFailed'          => __( 'Failed to clear cache.', 'ghl-crm-integration' ),
					'testConnectionProcessing'  => __( 'Testing connection...', 'ghl-crm-integration' ),
					'testConnectionSuccess'     => __( 'Connection test completed successfully.', 'ghl-crm-integration' ),
					'testConnectionFailed'      => __( 'Connection test failed.', 'ghl-crm-integration' ),
					'refreshMetadataProcessing' => __( 'Refreshing tags and fields...', 'ghl-crm-integration' ),
					'refreshMetadataSuccess'    => __( 'Tags and fields refreshed successfully.', 'ghl-crm-integration' ),
					'refreshMetadataFailed'     => __( 'Failed to refresh tags and fields.', 'ghl-crm-integration' ),
				],
			],
			'1.1.1',
			true
		);

		// Analytics assets (loads on SPA page for analytics view)
		$this->add_admin_asset(
			'ghl-crm-analytics-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'analytics.js',
			[ 'jquery', 'ghl-sweetalert2', 'ghl-chartjs' ],
			[],
			'1.0.0',
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
			'ghl-crm-integrations-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'integrations.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-integrations-js',
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
					'searchPlaceholder'  => __( 'Search for a user...', 'ghl-crm-integration' ),
					'missingInfo'        => __( 'Missing Information', 'ghl-crm-integration' ),
					'selectUser'         => __( 'Please select a user from the dropdown', 'ghl-crm-integration' ),
					'validatingUser'     => __( 'Validating User...', 'ghl-crm-integration' ),
					'lookingUpUser'      => __( 'Looking up WordPress user...', 'ghl-crm-integration' ),
					'connectingGHL'      => __( 'Connecting to GoHighLevel...', 'ghl-crm-integration' ),
					'establishingAPI'    => __( 'Establishing API connection...', 'ghl-crm-integration' ),
					'analyzingFields'    => __( 'Analyzing Field Mappings...', 'ghl-crm-integration' ),
					'comparingData'      => __( 'Comparing WordPress and GHL data...', 'ghl-crm-integration' ),
					'previewFailed'      => __( 'Preview Failed', 'ghl-crm-integration' ),
					'unknownError'       => __( 'Unknown error occurred', 'ghl-crm-integration' ),
					'requestFailed'      => __( 'Request Failed', 'ghl-crm-integration' ),
					'connectionError'    => __( 'Could not connect to server. Please check your connection and try again.', 'ghl-crm-integration' ),
					'totalFields'        => __( 'Total Fields', 'ghl-crm-integration' ),
					'willChange'         => __( 'Will Change', 'ghl-crm-integration' ),
					'alreadySynced'      => __( 'Already Synced', 'ghl-crm-integration' ),
					'tagsWillApply'      => __( 'tags will be applied', 'ghl-crm-integration' ),
					'updatingExisting'   => __( 'Updating existing contact:', 'ghl-crm-integration' ),
					'conflictsDetected'  => __( 'Conflicts Detected', 'ghl-crm-integration' ),
					'validationWarnings' => __( 'Validation Warnings', 'ghl-crm-integration' ),
					'fieldMapping'       => __( 'Field Mapping Comparison', 'ghl-crm-integration' ),
					'ghlField'           => __( 'GHL Field', 'ghl-crm-integration' ),
					'currentGHL'         => __( 'Current in GHL', 'ghl-crm-integration' ),
					'wpValue'            => __( 'WordPress Value', 'ghl-crm-integration' ),
					'status'             => __( 'Status', 'ghl-crm-integration' ),
					'willUpdate'         => __( 'WILL UPDATE', 'ghl-crm-integration' ),
					'inSync'             => __( 'IN SYNC', 'ghl-crm-integration' ),
					'tagsToApply'        => __( 'Tags to Apply', 'ghl-crm-integration' ),
					'syncPreview'        => __( 'Sync Preview', 'ghl-crm-integration' ),
					'gotIt'              => __( 'Got it!', 'ghl-crm-integration' ),
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
				'toplevel_page_wpcf7'
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
			'ghl-crm-integrations-css',
			[ 'toplevel_page_ghl-crm-settings' ],
			'integrations.css',
			[ 'ghl-crm-globals-css' ],
			[],
			GHL_CRM_VERSION
		);

		// Integrations JS (loads on main-settings page for integrations tab)
		$this->add_admin_asset(
			'ghl-crm-integrations-js',
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
				'nonce'   => wp_create_nonce( 'ghl_sync_logs_nonce' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
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
				'isPro'            => \GHL_CRM\Core\FormSettings::is_pro_active(),
				'upgradeUrl'       => apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/upgrade-to-pro' ),
				'strings'          => [
					'errorLoad'        => __( 'Failed to load forms', 'ghl-crm-integration' ),
					'ajaxError'        => __( 'AJAX error: ', 'ghl-crm-integration' ),
					'noForms'          => __( 'No Forms Found', 'ghl-crm-integration' ),
					'noFormsDesc'      => __( 'Create forms in your GoHighLevel account to display them here.', 'ghl-crm-integration' ),
					'untitledForm'     => __( 'Untitled Form', 'ghl-crm-integration' ),
					'formName'         => __( 'Form Name', 'ghl-crm-integration' ),
					'formId'           => __( 'Form ID', 'ghl-crm-integration' ),
					'submissions'      => __( 'Submissions', 'ghl-crm-integration' ),
					'shortcode'        => __( 'Shortcode', 'ghl-crm-integration' ),
					'actions'          => __( 'Actions', 'ghl-crm-integration' ),
					'clickToCopy'      => __( 'Click to copy shortcode', 'ghl-crm-integration' ),
					'preview'          => __( 'Preview', 'ghl-crm-integration' ),
					'copy'             => __( 'Copy', 'ghl-crm-integration' ),
					'whiteLabelNotice' => __( 'Using white label domain', 'ghl-crm-integration' ),
					'proBadge'         => __( 'PRO', 'ghl-crm-integration' ),
					'upgradeNow'       => __( 'Upgrade Now', 'ghl-crm-integration' ),
					'proFeature'       => __( 'This is a Pro feature', 'ghl-crm-integration' ),
					'unlockFeature'    => __( 'Upgrade to Pro to unlock this feature', 'ghl-crm-integration' ),
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
		if ( empty( $role_tags_config ) && ! empty( $settings['role_tags'] ) && is_array( $settings[] ) ) {
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
		$user_data             = $this->get_current_user_data_for_autofill();
		$form_settings_manager = \GHL_CRM\Core\FormSettings::get_instance();
		$all_form_settings     = $form_settings_manager->get_all_settings();

		// Resolve custom parameters for each form
		foreach ( $all_form_settings as $form_id => $form_config ) {
			if ( isset( $form_config['custom_params'] ) && is_array( $form_config['custom_params'] ) ) {
				$all_form_settings[ $form_id ]['resolved_params'] = $form_settings_manager->resolve_custom_params( $form_config['custom_params'] );
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
		$form_settings = \GHL_CRM\Core\FormSettings::get_instance();
		return $form_settings->get_all_settings();
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
