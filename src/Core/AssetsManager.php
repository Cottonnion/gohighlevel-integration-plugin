<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assets Manager class
 *
 * Handles enqueuing of CSS and JavaScript files for admin and frontend.
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

		// Define admin page assets
		$this->define_admin_assets();
	}

	/**
	 * Register external libraries (CDN)
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
	}

	/**
	 * Define admin page assets
	 *
	 * @return void
	 */
	private function define_admin_assets(): void {
		// Settings page assets
		$this->add_admin_asset(
			'ghl-crm-settings-css',
			[ 'toplevel_page_ghl-crm-integration' ],
			'settings.css',
			[ 'sweetalert2' ], // SweetAlert2 CSS will load automatically
			[],
			GHL_CRM_VERSION
		);

		$this->add_admin_asset(
			'ghl-crm-settings-js',
			[ 'toplevel_page_ghl-crm-integration' ],
			'settings.js',
			[ 'jquery', 'sweetalert2' ], // SweetAlert2 JS will load automatically
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_crm_admin' ),
			],
			GHL_CRM_VERSION,
			true
		);

		// Sync Logs page assets
		$this->add_admin_asset(
			'ghl-crm-sync-logs-css',
			[ 'ghl-crm_page_ghl-crm-sync-logs' ],
			'sync-logs.css',
			[],
			[],
			GHL_CRM_VERSION
		);

		// Field Mapping page assets
		$this->add_admin_asset(
			'ghl-crm-field-mapping-css',
			[ 'ghl-crm_page_ghl-crm-field-mapping' ],
			'field-mapping.css',
			[],
			[],
			GHL_CRM_VERSION
		);
	}

	/**
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
