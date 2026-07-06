<?php
declare(strict_types=1);

namespace Syncly\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu Manager class
 *
 * Handles admin menus, submenus, and plugin action links.
 *
 * @package    Syncly
 * @subpackage Syncly/Core
 */
class MenuManager {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

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
	 * Initialize WordPress hooks
	 *
	 * Registers all WordPress hooks for menu management, plugin action links,
	 * and AJAX handlers for SPA view loading.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_filter( 'plugin_action_links_' . SYNCLY_BASENAME, [ $this, 'add_plugin_action_links' ] );
		add_action( 'wp_ajax_syncly_spa_view', [ $this, 'handle_spa_view_request' ] );
		add_action( 'wp_ajax_syncly_load_settings_tab', [ $this, 'handle_settings_tab_request' ] );
		add_action( 'wp_ajax_syncly_oauth_disconnect', [ $this, 'handle_oauth_disconnect' ] );
		add_filter( 'admin_footer_text', [ $this, 'custom_admin_footer_text' ] );
		add_action( 'admin_head', [ $this, 'adjust_admin_viewport' ] );
		add_action( 'admin_head', [ $this, 'remove_notices_on_plugins_admin_pages' ] );
	}

	/**
	 * Adjust viewport meta tag on plugin admin screens to prevent zooming.
	 *
	 * @return void
	 */
	public function adjust_admin_viewport(): void {
		$current_screen = get_current_screen();

		if ( ! $current_screen || strpos( (string) $current_screen->id, 'syncly' ) === false ) {
			return;
		}

		wp_add_inline_script(
			'jquery-core',
			"(function(){var meta=document.querySelector('meta[name=\"viewport\"]');if(meta){meta.setAttribute('content','width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no');}})();"
		);
	}

	/**
	 * Remove all admin notices on GHL CRM admin pages
	 *
	 * @return void
	 */
	public function remove_notices_on_plugins_admin_pages(): void {
		$current_screen = get_current_screen();

		if ( ! $current_screen || strpos( $current_screen->id, 'syncly' ) === false ) {
			return;
		}

		// Remove all admin notices on all GHL CRM pages
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	/**
	 * Add admin menu and submenus
	 *
	 * Creates the main admin menu with SPA routing support and submenu items.
	 * The main menu uses hash-based routing for seamless navigation without page reloads.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Get SVG icon
		$icon_svg = $this->get_menu_icon();

		$page_hook = add_menu_page(
			__( 'Syncly', 'syncly' ),
			__( 'Syncly', 'syncly' ),
			'manage_options',
			'syncly-admin',
			[ $this, 'render_spa_app' ],
			'dashicons-randomize',
			51
		);

		remove_submenu_page( 'syncly-admin', 'syncly-admin' );

		add_submenu_page(
			'syncly-admin',
			__( 'Dashboard', 'syncly' ),
			__( 'Dashboard', 'syncly' ),
			'manage_options',
			'syncly-admin',
			[ $this, 'render_spa_app' ]
		);

		add_submenu_page(
			'syncly-admin',
			__( 'Settings', 'syncly' ),
			__( 'Settings', 'syncly' ),
			'manage_options',
			'syncly-admin#/settings',
			'__return_false'
		);

		add_submenu_page(
			'syncly-admin',
			__( 'Integrations', 'syncly' ),
			__( 'Integrations', 'syncly' ),
			'manage_options',
			'syncly-admin#/integrations',
			'__return_false'
		);

		add_submenu_page(
			'syncly-admin',
			__( 'Field Mapping', 'syncly' ),
			__( 'Field Mapping', 'syncly' ),
			'manage_options',
			'syncly-admin#/field-mapping',
			'__return_false'
		);

		add_submenu_page(
			'syncly-admin',
			__( 'Sync Logs', 'syncly' ),
			__( 'Sync Logs', 'syncly' ),
			'manage_options',
			'syncly-admin#/sync-logs',
			'__return_false'
		);

		add_submenu_page(
			'syncly-admin',
			__( 'Forms', 'syncly' ),
			__( 'Forms', 'syncly' ),
			'manage_options',
			'syncly-admin#/forms',
			'__return_false'
		);

		add_submenu_page(
			null, // Hidden from menu
			__( 'Setup Wizard', 'syncly' ),
			__( 'Setup Wizard', 'syncly' ),
			'manage_options',
			'syncly-setup-wizard',
			[ $this, 'render_setup_wizard' ]
		);
	}

	/**
	 * Add Settings link to plugin action links
	 *
	 * Adds a "Settings" link to the plugin's action links on the Plugins page.
	 * This link directs users to the Settings view using hash routing.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links with Settings link prepended.
	 */
	public function add_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=syncly-admin#/settings' ) ),
			esc_html__( 'Settings', 'syncly' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Custom admin footer text for plugin pages
	 *
	 * Displays custom footer text on plugin admin pages encouraging users to rate the plugin.
	 *
	 * @param string $footer_text The existing footer text.
	 * @return string Modified footer text on plugin pages, original text otherwise.
	 */
	public function custom_admin_footer_text( string $footer_text ): string {
		// Get current screen
		$current_screen = get_current_screen();

		// Check if we're on one of our plugin pages
		if ( isset( $current_screen->id ) && strpos( $current_screen->id, 'syncly' ) !== false ) {
			$footer_text = sprintf(
				/* translators: 1: Plugin name highlighted in strong text, 2: Review link HTML. */
				esc_html__( 'Thank you for using %1$s! If you find it helpful, please %2$s on WordPress.org.', 'syncly' ),
				'<strong>' . esc_html( SYNCLY_PLUGIN_NAME ) . '</strong>',
				'<a href="https://wordpress.org/support/plugin/syncly/reviews/#new-post" target="_blank" rel="noopener noreferrer">' . esc_html__( 'leave a review', 'syncly' ) . '</a>'
			);
		}

		return $footer_text;
	}

	/**
	 * Render main settings page with tabs (legacy)
	 *
	 * Legacy method for rendering the tabbed settings page.
	 * Checks user permissions before loading the template.
	 *
	 * @return void
	 */
	public function render_main_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'syncly' ) );
		}

		$this->load_template( 'admin/main-settings' );
	}

	/**
	 * Render settings page (legacy compatibility method)
	 *
	 * Backwards compatibility method that delegates to render_main_settings_page().
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$this->render_main_settings_page();
	}

	/**
	 * Render sync logs page (legacy)
	 *
	 * Legacy method for rendering the sync logs page.
	 * Checks user permissions before loading the template.
	 *
	 * @return void
	 */
	public function render_sync_logs_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'syncly' ) );
		}

		$this->load_template( 'admin/sync-logs' );
	}

	/**
	 * Redirect to integrations tab (legacy compatibility)
	 *
	 * Legacy redirect method for backwards compatibility with old URL structure.
	 * Redirects to the main settings page with integrations tab.
	 *
	 * @return void
	 */
	public function render_integrations_page(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=syncly-settings&tab=integrations' ) );
		exit;
	}

	/**
	 * Redirect to field mapping tab (legacy compatibility)
	 *
	 * Legacy redirect method for backwards compatibility with old URL structure.
	 * Redirects to the main settings page with field-mapping tab.
	 *
	 * @return void
	 */
	public function render_field_mapping_page(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=syncly-settings&tab=field-mapping' ) );
		exit;
	}

	/**
	 * Render SPA application container
	 *
	 * Renders the main Single Page Application container template.
	 * This is the entry point for the SPA and loads the base HTML structure.
	 * All content is then dynamically loaded via AJAX and hash routing.
	 *
	 * @return void
	 */
	public function render_spa_app(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'syncly' ) );
		}

		$this->load_template( 'admin/spa-app' );
	}

	/*
	* Render Setup Wizard
	*
	* @return void
	*/
	public function render_setup_wizard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'syncly' ) );
		}
		$this->load_template( 'admin/setup-wizard' );
	}

	/**
	 * Handle AJAX requests for SPA views
	 *
	 * Processes AJAX requests for loading different views in the SPA.
	 * Verifies nonce and permissions, then routes to the appropriate view handler
	 * based on the requested view parameter.
	 *
	 * Supported views: dashboard, settings, integrations, field-mapping, sync-logs
	 *
	 * @return void
	 */
	public function handle_spa_view_request(): void {
		if ( ! $this->verify_spa_nonce() ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'syncly' ),
				],
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this view.', 'syncly' ),
				],
				403
			);
		}
		try {

			$view       = isset( $_POST['view'] ) ? sanitize_text_field( wp_unslash( $_POST['view'] ) ) : 'dashboard';
			$raw_params = isset( $_POST['params'] ) ? wp_unslash( $_POST['params'] ) : [];
			$params     = is_array( $raw_params ) ? array_map( 'sanitize_text_field', $raw_params ) : [];

			switch ( $view ) {
				case 'dashboard':
					$this->get_dashboard_data();
					break;

				case 'settings':
					$this->get_settings_data( $params );
					break;

				case 'integrations':
					$this->get_integrations_data();
					break;

				case 'field-mapping':
					$this->get_field_mapping_data();
					break;

				case 'custom-objects':
					$this->get_custom_objects_data( $params );
					break;

				case 'forms':
					$this->get_forms_data();
					break;

				case 'sync-logs':
					$this->get_sync_logs_data( $params );
					break;          default:
					wp_send_json_error(
						[
							'message' => __( 'Invalid view requested.', 'syncly' ),
						],
						404
					);
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: 1: error message, 2: file path, 3: line number */
						__( 'An error occurred while processing the request: %1$s in %2$s:%3$d', 'syncly' ),
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					),
				],
				500
			);
		}
	}

	/**
	 * Handle OAuth disconnect request
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_oauth_disconnect(): void {
		check_ajax_referer( 'syncly_oauth_disconnect', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to disconnect OAuth.', 'syncly' ),
				],
				403
			);
		}

		try {
			$oauth_handler = new \Syncly\API\OAuth\OAuthHandler();
			$result        = $oauth_handler->disconnect();

			if ( $result ) {
				wp_send_json_success(
					[
						'message' => __( 'Successfully disconnected from GoHighLevel.', 'syncly' ),
					]
				);
			} else {
				wp_send_json_error(
					[
						'message' => __( 'Failed to disconnect. Please try again.', 'syncly' ),
					]
				);
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: 1: error message, 2: file path, 3: line number */
						__( 'Disconnect error: %1$s in %2$s:%3$d', 'syncly' ),
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					),
				]
			);
		}
	}

	/**
	 * Get dashboard data for SPA view
	 *
	 * Retrieves OAuth connection status and generates the dashboard HTML.
	 * Returns JSON response with the rendered HTML for the dashboard view.
	 *
	 * @return void Outputs JSON response and exits.
	 */
	private function get_dashboard_data(): void {
		ob_start();
		$this->load_template( 'admin/dashboard' );
		$html = ob_get_clean();

		wp_send_json_success(
			[
				'view' => 'dashboard',
				'html' => $html,
			]
		);
	}

	/**
	 * Get settings data for SPA view
	 *
	 * Loads the settings template and returns the rendered HTML.
	 * Returns JSON response with the settings view HTML.
	 *
	 * @param array $params Optional parameters including settings_tab.
	 * @return void Outputs JSON response and exits.
	 */
	private function get_settings_data( array $params = [] ): void {
		// Set the settings tab in $_GET so the template can access it
		if ( isset( $params['settings_tab'] ) ) {
			$_GET['settings_tab'] = sanitize_text_field( $params['settings_tab'] );
		}

		ob_start();
		$this->load_template( 'admin/settings' );
		$html = ob_get_clean();

		wp_send_json_success(
			[
				'view' => 'settings',
				'html' => $html,
			]
		);
	}

	/**
	 * Get integrations data for SPA view
	 *
	 * Loads the integrations template and returns the rendered HTML.
	 * Returns JSON response with the integrations view HTML.
	 *
	 * @return void Outputs JSON response and exits.
	 */
	private function get_integrations_data(): void {
		ob_start();
		$this->load_template( 'admin/integrations' );
		$html = ob_get_clean();

		wp_send_json_success(
			[
				'view' => 'integrations',
				'html' => $html,
			]
		);
	}

	/**
	 * Get field mapping data for SPA view
	 *
	 * Loads the field mapping template and returns the rendered HTML.
	 * Returns JSON response with the field mapping view HTML.
	 *
	 * @return void Outputs JSON response and exits.
	 */
	private function get_field_mapping_data(): void {
		ob_start();

		try {
			$this->load_template( 'admin/field-mapping' );
			$html = ob_get_clean();
		} catch ( \Throwable $e ) {
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}

			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: 1: error message, 2: file path, 3: line number */
						__( 'Field mapping view failed: %1$s in %2$s:%3$d', 'syncly' ),
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					),
				],
				500
			);
		}

		wp_send_json_success(
			[
				'view' => 'field-mapping',
				'html' => $html,
			]
		);
	}

	/**
	 * Get custom objects data for SPA view
	 *
	 * Loads the custom objects template and returns the rendered HTML.
	 * Returns JSON response with the custom objects view HTML.
	 *
	 * @param array $params Query parameters for filtering and pagination.
	 * @return void Outputs JSON response and exits.
	 */
	private function get_custom_objects_data( array $params ): void {
		/**
		 * Filter the custom objects template path.
		 * Allows Pro plugin to provide its own template.
		 *
		 * @param string|null $template_path Custom template path or null to use default
		 */
		$custom_template_path = apply_filters( 'syncly_custom_objects_template_path', null );

		ob_start();
		if ( $custom_template_path && file_exists( $custom_template_path ) ) {
			include $custom_template_path;
		} else {
			$this->load_template( 'admin/custom-objects' );
		}
		$html = ob_get_clean();

		wp_send_json_success(
			[
				'view' => 'custom-objects',
				'html' => $html,
			]
		);
	}

	/**
	 * Get sync logs data for SPA view
	 *
	 * Loads the sync logs template and returns the rendered HTML.
	 * Returns JSON response with the sync logs view HTML.
	 *
	 * @param array $params Query parameters for filtering and pagination.
	 * @return void Outputs JSON response and exits.
	 */
	private function get_sync_logs_data( array $params ): void {
		ob_start();
		$this->load_template( 'admin/sync-logs' );
		$html = ob_get_clean();

		wp_send_json_success(
			[
				'view' => 'sync-logs',
				'html' => $html,
			]
		);
	}

	/**
	 * Get forms data for SPA view
	 *
	 * Loads the forms template and returns the rendered HTML.
	 * Returns JSON response with the forms view HTML.
	 *
	 * @return void Outputs JSON response and exits.
	 */
	private function get_forms_data(): void {
		ob_start();
		$this->load_template( 'admin/forms' );
		$html = ob_get_clean();

		wp_send_json_success(
			[
				'view' => 'forms',
				'html' => $html,
			]
		);
	}

	/**
	 * Get valid settings tabs
	 *
	 * @return array<string> List of valid settings tab names
	 */
	public static function get_valid_settings_tabs(): array {
		// Get base tabs
		$base_tabs = [
			'general',
			'restrictions-manager',
			'webhooks',
			'notifications',
			'sync-options',
			'role-tags',
			'personalization',
			// 'conversations',
			'advanced',
			'tools',
			'stats',
			'upgrade',
		];

		// Get settings manager to check connection status
		$settings_manager = \Syncly\Core\SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$oauth_handler    = new \Syncly\API\OAuth\OAuthHandler();
		$oauth_status     = $oauth_handler->get_connection_status();
		$is_connected     = $oauth_status['connected'] || ! empty( $settings['api_token'] );

		// Build settings tabs array (same structure as in settings.php)
		$settings_tabs = [
			'general'              => [ 'label' => __( 'General', 'syncly' ) ],
			'restrictions-manager' => [ 'label' => __( 'Restrictions Manager', 'syncly' ) ],
			'webhooks'             => [ 'label' => __( 'Webhooks', 'syncly' ) ],
			'notifications'        => [ 'label' => __( 'Email Notifications', 'syncly' ) ],
			'role-tags'            => [ 'label' => __( 'Role-Based Tags', 'syncly' ) ],
			'personalization'      => [ 'label' => __( 'Personalization', 'syncly' ) ],
			// 'conversations'        => [ 'label' => __( 'Conversations', 'syncly' ) ],
			'advanced'             => [ 'label' => __( 'Advanced', 'syncly' ) ],
			'tools'                => [ 'label' => __( 'Tools', 'syncly' ) ],
			'stats'                => [ 'label' => __( 'System Status', 'syncly' ) ],
		];

		// Hide the upsell tab once Pro is active and licensed — nothing left to upgrade to.
		if ( ! apply_filters( 'syncly_is_pro_active', false ) ) {
			$settings_tabs['upgrade'] = [ 'label' => __( 'Upgrade to Pro', 'syncly' ) ];
		}

		/**
		 * Allow developers to add custom settings tabs
		 * This filter is used in both settings.php template and AJAX handler
		 *
		 * @param array $settings_tabs Array of settings tabs
		 * @param bool  $is_connected  Whether the plugin is connected to GoHighLevel
		 * @param array $settings      Current plugin settings
		 */
		$settings_tabs = apply_filters( 'syncly_settings_tabs', $settings_tabs, $is_connected, $settings );

		// Return just the tab keys
		return array_keys( $settings_tabs );
	}

	/**
	 * Handle AJAX request to load a specific settings tab
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_settings_tab_request(): void {
		if ( ! $this->verify_spa_nonce() ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'syncly' ),
				],
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this page.', 'syncly' ),
				],
				403
			);
		}

		$tab = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : 'general';

		// Define valid tabs using centralized method (includes filtered tabs)
		$valid_tabs = self::get_valid_settings_tabs();

		if ( ! in_array( $tab, $valid_tabs, true ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid settings tab.', 'syncly' ),
				],
				400
			);
		}

		// Get settings to check connection and build tabs array
		$settings_manager = \Syncly\Core\SettingsManager::get_instance();
		$settings         = $settings_manager->get_settings_array();
		$oauth_handler    = new \Syncly\API\OAuth\OAuthHandler();
		$oauth_status     = $oauth_handler->get_connection_status();
		$is_connected     = $oauth_status['connected'] || ! empty( $settings['api_token'] );

		// Build full settings tabs array to get file path
		$settings_tabs = [
			'general'              => [ 'label' => __( 'General', 'syncly' ) ],
			'restrictions-manager' => [ 'label' => __( 'Restrictions Manager', 'syncly' ) ],
			'webhooks'             => [ 'label' => __( 'Webhooks', 'syncly' ) ],
			'notifications'        => [ 'label' => __( 'Email Notifications', 'syncly' ) ],
			'role-tags'            => [ 'label' => __( 'Role-Based Tags', 'syncly' ) ],
			'personalization'      => [ 'label' => __( 'Personalization', 'syncly' ) ],
			// 'conversations'        => [ 'label' => __( 'Conversations', 'syncly' ) ],
			'advanced'             => [ 'label' => __( 'Advanced', 'syncly' ) ],
			'tools'                => [ 'label' => __( 'Tools', 'syncly' ) ],
			'stats'                => [ 'label' => __( 'System Status', 'syncly' ) ],
		];

		// Hide the upsell tab once Pro is active and licensed — nothing left to upgrade to.
		if ( ! apply_filters( 'syncly_is_pro_active', false ) ) {
			$settings_tabs['upgrade'] = [ 'label' => __( 'Upgrade to Pro', 'syncly' ) ];
		}

		// Apply the same filter as settings.php
		$settings_tabs = apply_filters( 'syncly_settings_tabs', $settings_tabs, $is_connected, $settings );

		// Check if tab has custom file path or callback
		$tab_config = $settings_tabs[ $tab ] ?? [];

		// Determine template file path
		if ( isset( $tab_config['file'] ) && file_exists( $tab_config['file'] ) ) {
			// Custom file path from filter (e.g., PRO plugin)
			$partial_file = $tab_config['file'];
		} else {
			// Default path in FREE plugin
			$partial_file = SYNCLY_PATH . 'templates/admin/partials/settings/' . $tab . '.php';
		}

		// Check if file exists
		if ( ! file_exists( $partial_file ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Settings tab template not found.', 'syncly' ),
					'debug'   => WP_DEBUG ? $partial_file : null,
				],
				404
			);
		}

		// Check if tab has a custom callback
		if ( isset( $tab_config['callback'] ) && is_callable( $tab_config['callback'] ) ) {
			ob_start();
			call_user_func( $tab_config['callback'], $tab, $tab_config, $settings );
			$html = ob_get_clean();
		} else {
			// Load the partial template
			ob_start();
			include $partial_file;
			$html = ob_get_clean();
		}

		wp_send_json_success(
			[
				'tab'  => $tab,
				'html' => $html,
			]
		);
	}

	/**
	 * Verify nonce for admin SPA requests.
	 *
	 * @return bool Whether the request nonce is valid.
	 */
	private function verify_spa_nonce(): bool {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, 'syncly_spa_nonce' ) || (bool) wp_verify_nonce( $nonce, 'syncly_admin' );
	}

	/**
	 * Load and include a template file
	 *
	 * Loads a PHP template file from the templates directory.
	 * Extracts provided arguments into the template's variable scope.
	 * Displays an error message if the template file is not found.
	 *
	 * @param string $template_name Template name without .php extension (e.g., 'admin/settings').
	 * @param array  $args          Optional. Associative array of variables to pass to the template.
	 * @return void
	 */
	private function load_template( string $template_name, array $args = [] ): void {
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template partials rely on scoped variables from caller-provided args.
			extract( $args, EXTR_SKIP );
		}

		$template_path = SYNCLY_PATH . 'templates/' . $template_name . '.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			$this->render_template_error( $template_name );
		}
	}

	/**
	 * Render template error message
	 *
	 * Displays a user-friendly error message when a template file cannot be found.
	 * Shows the expected template location for debugging purposes.
	 *
	 * @param string $template_name The template name that was not found.
	 * @return void
	 */
	private function render_template_error( string $template_name ): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Template Error:', 'syncly' ); ?></strong>
					<?php
					printf(
						/* translators: %s: Template name */
						esc_html__( 'The template file "%s.php" could not be found.', 'syncly' ),
						esc_html( $template_name )
					);
					?>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: Template path */
						esc_html__( 'Expected location: %s', 'syncly' ),
						'<code>' . esc_html( SYNCLY_PATH . 'templates/' . $template_name . '.php' ) . '</code>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get menu icon SVG
	 *
	 * Returns the custom SVG icon for the admin menu.
	 * Uses base64 encoded SVG for WordPress menu compatibility.
	 *
	 * @return string Base64 encoded SVG data URI.
	 */
	private function get_menu_icon(): string {
		$icon_path = SYNCLY_PATH . 'assets/images/ghl-icon.svg';
		$icon_url  = SYNCLY_URL . 'assets/images/ghl-icon.svg';

		if ( file_exists( $icon_path ) ) {
			return $icon_url;
		}

		// Fallback to dashicon if file not found
		return 'dashicons-cloud';
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
