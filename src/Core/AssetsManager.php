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

		// Enqueue Font Awesome on all admin pages
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_global_admin_assets' ], 6 );

		// Register admin assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Register public assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );

		// Define admin page assets
		$this->define_admin_assets();
	}

	/**
	 * Register external libraries (CDN and local)
	 * These are registered but not enqueued - they'll only load when used as dependencies
	 *
	 * @return void
	 */
	public function register_external_libraries(): void {
		// Register SweetAlert2
		wp_register_style(
			'sweetalert2',
			'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
			[],
			'11.0.0'
		);

		wp_register_script(
			'sweetalert2',
			'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js',
			[],
			'11.0.0',
			true
		);

		// Register Font Awesome
		wp_register_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css',
			[],
			'6.6.0'
		);

		// Register Select2 (local files)
		wp_register_style(
			'select2',
			GHL_CRM_URL . 'assets/admin/css/select2.min.css',
			[],
			'4.1.0'
		);

		wp_register_script(
			'select2',
			GHL_CRM_URL . 'assets/admin/js/select2.min.js',
			[ 'jquery' ],
			'4.1.0',
			true
		);
	}

	/**
	 * Enqueue global admin assets (Font Awesome, etc.)
	 * These load on all admin pages
	 *
	 * @return void
	 */
	public function enqueue_global_admin_assets(): void {
		// Enqueue Font Awesome on all admin pages
		wp_enqueue_style( 'font-awesome' );
	}

	/**
	 * Define admin page assets
	 *
	 * @return void
	 */
	private function define_admin_assets(): void {

		// Admin Menu Styling (loads on all admin pages to style the menu)
		$this->add_admin_asset(
			'ghl-crm-admin-menu-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'admin-menu.css',
			[],
			[ 'font-awesome' ], // Add Font Awesome as a dependency
			'1.0.0'
		);

		// Menu Router for SPA active state management
		$this->add_admin_asset(
			'ghl-crm-menu-router-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'menu-router.js',
			[ 'jquery' ],
			[],
			'1.0.0',
			true
		);

		// SPA Application assets (new single-page admin)
		$this->add_admin_asset(
			'ghl-crm-spa-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'spa-app.css',
			[],
			[],
			'1.0.0'
		);

		$this->add_admin_asset(
			'ghl-crm-spa-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'spa-router.js',
			[ 'jquery', 'sweetalert2' ],
			[],
			'1.0.34',
			true
		);

		// Dashboard assets (loads on SPA page)
		$this->add_admin_asset(
			'ghl-crm-dashboard-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'dashboard.css',
			[],
			[],
			'1.0.0'
		);

		$this->add_admin_asset(
			'ghl-crm-dashboard-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'dashboard.js',
			[ 'jquery', 'sweetalert2' ],
			[
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'ghl_crm_admin' ),
				'manualConnectNonce' => wp_create_nonce( 'ghl_crm_manual_connect' ),
				'disconnectNonce' => wp_create_nonce( 'ghl_crm_oauth_disconnect' ),
				'i18n'           => [
					'connecting'       => __( 'Connecting...', 'ghl-crm-integration' ),
					'connectionFailed' => __( 'Connection failed', 'ghl-crm-integration' ),
					'connectionError'  => __( 'An error occurred while connecting', 'ghl-crm-integration' ),
					'disconnectConfirm' => __( 'Are you sure you want to disconnect your GoHighLevel account?', 'ghl-crm-integration' ),
					'disconnecting'    => __( 'Disconnecting...', 'ghl-crm-integration' ),
					'disconnectFailed' => __( 'Failed to disconnect', 'ghl-crm-integration' ),
					'disconnectError'  => __( 'An error occurred while disconnecting', 'ghl-crm-integration' ),
				],
			],
			'1.0.9',
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
			[ 'jquery', 'sweetalert2' ],
			[
				'nonce' => wp_create_nonce( 'ghl_crm_field_mapping_nonce' ),
			],
			'1.0.3',
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
			[ 'jquery', 'sweetalert2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
			],
			GHL_CRM_VERSION,
			true
		);

		// Settings assets (need to load on SPA page)
		$this->add_admin_asset(
			'ghl-crm-settings-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'settings.css',
			[ 'sweetalert2', 'select2' ],
			[],
			'1.0.3'
		);

		$this->add_admin_asset(
			'ghl-crm-settings-menu-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'settings-menu.css',
			[],
			[],
			'1.0.1'
		);

		$this->add_admin_asset(
			'ghl-crm-settings-menu-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'settings-menu.js',
			[ 'jquery' ],
			[],
			'1.0.1',
			true
		);

		$this->add_admin_asset(
			'ghl-crm-settings-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'settings.js',
			[ 'jquery', 'sweetalert2', 'select2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
			],
			'1.0.8',
			true
		);

		// Global CSS for all admin pages (now includes tabbed main-settings page)
		$this->add_admin_asset(
			'ghl-crm-globals-css',
			[
				'toplevel_page_ghl-crm-settings',           // Legacy main tabbed page
				'toplevel_page_ghl-crm-admin',              // New SPA page
				'ghl-crm_page_ghl-crm-sync-logs',
			],
			'globals.css',
			[],
			[],
			'1.0.3'
		);

		// Settings page CSS (loads on main-settings page for all tabs)
		$this->add_admin_asset(
			'ghl-crm-settings-css',
			[ 'toplevel_page_ghl-crm-settings' ],
			'settings.css',
			[ 'ghl-crm-globals-css', 'sweetalert2', 'select2' ],
			[],
			'1.0.2'
		);

		// Settings page JS (loads on main-settings page for settings tab)
		$this->add_admin_asset(
			'ghl-crm-settings-js',
			[ 'toplevel_page_ghl-crm-settings' ],
			'settings.js',
			[ 'jquery', 'sweetalert2', 'select2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
			],
			'1.0.2',
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
			[ 'jquery', 'sweetalert2' ],
			[
				'nonce' => wp_create_nonce( 'ghl_crm_field_mapping_nonce' ),
			],
			'1.0.2',
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
			[ 'jquery', 'sweetalert2' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
			],
			GHL_CRM_VERSION,
			true
		);

		// Sync Logs page assets (separate page)
		$this->add_admin_asset(
			'ghl-crm-sync-logs-css',
			[ 'ghl-crm_page_ghl-crm-sync-logs' ],
			'sync-logs.css',
			[ 'ghl-crm-globals-css' ],
			[],
			GHL_CRM_VERSION
		);

		// Custom Objects page assets (SPA page)
		$this->add_admin_asset(
			'ghl-crm-custom-objects-css',
			[ 'toplevel_page_ghl-crm-admin' ],
			'custom-objects.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-custom-objects-js',
			[ 'toplevel_page_ghl-crm-admin' ],
			'custom-objects.js',
			[ 'jquery' ],
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonces'  => [
					'customObjects' => wp_create_nonce( 'ghl_crm_custom_objects' ),
					'mappings'      => wp_create_nonce( 'ghl_crm_mappings' ),
				],
				'i18n'    => [
					// Schema messages
					'failedToLoadSchemas'        => __( 'Failed to load schemas', 'ghl-crm-integration' ),
					'networkError'               => __( 'Network error', 'ghl-crm-integration' ),
					'noCustomObjectsFound'       => __( 'No Custom Objects Found', 'ghl-crm-integration' ),
					'createCustomObjectsMessage' => __( 'Create Custom Objects in your GoHighLevel account to see them here.', 'ghl-crm-integration' ),
					'noDescription'              => __( 'No description available', 'ghl-crm-integration' ),
					'requiredFields'             => __( 'Required Fields', 'ghl-crm-integration' ),
					'searchableFields'           => __( 'Searchable Fields', 'ghl-crm-integration' ),
					'created'                    => __( 'Created', 'ghl-crm-integration' ),
					'viewDetails'                => __( 'View Details', 'ghl-crm-integration' ),
					'loading'                    => __( 'Loading...', 'ghl-crm-integration' ),
					'error'                      => __( 'Error', 'ghl-crm-integration' ),
					'failedToFetchSchemaDetails' => __( 'Failed to fetch schema details', 'ghl-crm-integration' ),
					// Modal messages
					'overview'                   => __( 'Overview', 'ghl-crm-integration' ),
					'type'                       => __( 'Type', 'ghl-crm-integration' ),
					'key'                        => __( 'Key', 'ghl-crm-integration' ),
					'description'                => __( 'Description', 'ghl-crm-integration' ),
					'primaryDisplay'             => __( 'Primary Display', 'ghl-crm-integration' ),
					'updated'                    => __( 'Updated', 'ghl-crm-integration' ),
					'requiredProperties'         => __( 'Required Properties', 'ghl-crm-integration' ),
					'searchableProperties'       => __( 'Searchable Properties', 'ghl-crm-integration' ),
					'uniqueProperties'           => __( 'Unique Properties', 'ghl-crm-integration' ),
					'addRecordConfiguration'     => __( 'Add Record Configuration', 'ghl-crm-integration' ),
					'associations'               => __( 'Associations (Contact Linking)', 'ghl-crm-integration' ),
					'associationsDescription'    => __( 'This object can be associated with the following object types:', 'ghl-crm-integration' ),
					'contactLinkNote'            => __( 'When syncing WordPress posts to this custom object, you must provide a contactId to link the record to a contact.', 'ghl-crm-integration' ),
					'noAssociations'             => __( 'No Associations Configured', 'ghl-crm-integration' ),
					'noAssociationsMessage'      => __( 'This custom object has no associations configured in GoHighLevel. Records will not be linked to contacts.', 'ghl-crm-integration' ),
					'rawJson'                    => __( 'Raw JSON', 'ghl-crm-integration' ),
					// Mapping messages
					'viewMappings'               => __( 'View Mappings', 'ghl-crm-integration' ),
					'backToObjects'              => __( 'Back to Objects', 'ghl-crm-integration' ),
					'noMappingsCreated'          => __( 'No Mappings Created', 'ghl-crm-integration' ),
					'createMappingMessage'       => __( 'Create your first Custom Object mapping to start syncing data.', 'ghl-crm-integration' ),
					'active'                     => __( 'Active', 'ghl-crm-integration' ),
					'inactive'                   => __( 'Inactive', 'ghl-crm-integration' ),
					'cpt'                        => __( 'CPT', 'ghl-crm-integration' ),
					'ghlObject'                  => __( 'GHL Object', 'ghl-crm-integration' ),
					'fields'                     => __( 'Fields', 'ghl-crm-integration' ),
					'edit'                       => __( 'Edit', 'ghl-crm-integration' ),
					'delete'                     => __( 'Delete', 'ghl-crm-integration' ),
					'confirmDelete'              => __( 'Are you sure you want to delete this mapping?', 'ghl-crm-integration' ),
					'errorDeletingMapping'       => __( 'Error deleting mapping', 'ghl-crm-integration' ),
					// Form messages
					'selectPostType'             => __( 'Select Post Type...', 'ghl-crm-integration' ),
					'selectCustomObject'         => __( 'Select Custom Object...', 'ghl-crm-integration' ),
					'selectGHLObjectFirst'       => __( 'Select GHL Object first...', 'ghl-crm-integration' ),
					'selectGHLField'             => __( 'Select GHL Field...', 'ghl-crm-integration' ),
					'enterCustomFieldKey'        => __( '➕ Enter Custom Field Key...', 'ghl-crm-integration' ),
					'selectWPField'              => __( 'Select WP Field...', 'ghl-crm-integration' ),
					'postFields'                 => __( 'Post Fields', 'ghl-crm-integration' ),
					'postTitle'                  => __( 'Post Title', 'ghl-crm-integration' ),
					'postContent'                => __( 'Post Content', 'ghl-crm-integration' ),
					'postExcerpt'                => __( 'Post Excerpt', 'ghl-crm-integration' ),
					'postDate'                   => __( 'Post Date', 'ghl-crm-integration' ),
					'postModified'               => __( 'Post Modified Date', 'ghl-crm-integration' ),
					'postMeta'                   => __( 'Post Meta', 'ghl-crm-integration' ),
					'postMetaCustom'             => __( 'Post Meta (Custom)', 'ghl-crm-integration' ),
					'other'                      => __( 'Other', 'ghl-crm-integration' ),
					'acfField'                   => __( 'ACF Field', 'ghl-crm-integration' ),
					'taxonomyTerm'               => __( 'Taxonomy Term', 'ghl-crm-integration' ),
					'staticValue'                => __( 'Static Value', 'ghl-crm-integration' ),
					'fieldNamePlaceholder'       => __( 'Field name...', 'ghl-crm-integration' ),
					'customFieldPlaceholder'     => __( 'e.g., custom_objects.wordpress_pages.post_content', 'ghl-crm-integration' ),
					'transformNone'              => __( 'None', 'ghl-crm-integration' ),
					'transformSanitize'          => __( 'Sanitize HTML', 'ghl-crm-integration' ),
					'transformNumber'            => __( 'Convert to Number', 'ghl-crm-integration' ),
					'transformDateISO'           => __( 'Format Date (ISO)', 'ghl-crm-integration' ),
					'transformStripHTML'         => __( 'Strip HTML', 'ghl-crm-integration' ),
					'transformJSON'              => __( 'JSON Encode', 'ghl-crm-integration' ),
					'mappingSaved'               => __( 'Mapping saved successfully!', 'ghl-crm-integration' ),
				],
			],
			'1.0.1',
			true
		);
	}   /**
		 * Adds an admin script or style to the array of admin assets.
		 *
		 * @param string $handle           Unique handle for the asset.
		 * @param array  $pages            Array of admin page IDs where this asset should load.
		 * @param string $file             File name (e.g., 'settings.css' or 'settings.js').
		 * @param array  $dependencies     Array of dependency handles.
		 * @param array  $localization     Array of data to localize for scripts.
		 * @param string $version          Version string for cache busting.
		 * @param bool   $enqueue_in_footer Whether to enqueue script in footer (scripts only).
		 * @return void
		 */
	public function add_admin_asset(
		string $handle,
		array $pages,
		string $file,
		array $dependencies = [],
		array $localization = [],
		string $version = GHL_CRM_VERSION,
		bool $enqueue_in_footer = true
	): void {
		foreach ( $pages as $page ) {
			$this->admin_assets[ $page ][ $handle ] = [
				'file'              => $file,
				'dependencies'      => $dependencies,
				'version'           => $version,
				'enqueue_in_footer' => $enqueue_in_footer,
				'localization'      => $localization,
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

		// Check if we have assets for this page
		if ( ! isset( $this->admin_assets[ $screen_id ] ) ) {
			return;
		}

		// Enqueue all assets for this page
		foreach ( $this->admin_assets[ $screen_id ] as $handle => $asset ) {
			$this->enqueue_asset( $handle, $asset, 'admin' );
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

		// Determine if it's a CSS or JS file
		$file_extension = pathinfo( $file, PATHINFO_EXTENSION );
		$is_style       = ( 'css' === $file_extension );

		// Build file URL
		if ( 'admin' === $context ) {
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
	 * @param string $handle           Unique handle for the asset.
	 * @param string $file             File name (e.g., 'style.css' or 'script.js').
	 * @param array  $dependencies     Array of dependency handles.
	 * @param array  $localization     Array of data to localize for scripts.
	 * @param string $version          Version string for cache busting.
	 * @param bool   $enqueue_in_footer Whether to enqueue script in footer (scripts only).
	 * @return void
	 */
	public function add_public_asset(
		string $handle,
		string $file,
		array $dependencies = [],
		array $localization = [],
		string $version = GHL_CRM_VERSION,
		bool $enqueue_in_footer = true
	): void {
		$this->public_assets[ $handle ] = [
			'file'              => $file,
			'dependencies'      => $dependencies,
			'version'           => $version,
			'enqueue_in_footer' => $enqueue_in_footer,
			'localization'      => $localization,
		];
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
