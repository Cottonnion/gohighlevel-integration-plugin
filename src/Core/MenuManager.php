<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu Manager class
 *
 * Handles admin menus, submenus, and plugin action links.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core
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
		add_filter( 'plugin_action_links_' . GHL_CRM_BASENAME, [ $this, 'add_plugin_action_links' ] );
		add_action( 'wp_ajax_ghl_crm_spa_view', [ $this, 'handle_spa_view_request' ] );
		add_action( 'wp_ajax_ghl_crm_load_settings_tab', [ $this, 'handle_settings_tab_request' ] );
		add_action( 'wp_ajax_ghl_crm_oauth_disconnect', [ $this, 'handle_oauth_disconnect' ] );
		add_filter( 'admin_footer_text', [ $this, 'custom_admin_footer_text' ] );
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
		$page_hook = add_menu_page(
			__( 'GoHighLevel CRM', 'ghl-crm-integration' ),
			__( 'GHL CRM', 'ghl-crm-integration' ),
			'manage_options',
			'ghl-crm-admin',
			[ $this, 'render_spa_app' ],
			'dashicons-cloud',
			58
		);

		remove_submenu_page( 'ghl-crm-admin', 'ghl-crm-admin' );

		add_submenu_page(
			'ghl-crm-admin',
			__( 'Dashboard', 'ghl-crm-integration' ),
			__( 'Dashboard', 'ghl-crm-integration' ),
			'manage_options',
			'ghl-crm-admin',
			[ $this, 'render_spa_app' ]
		);

		add_submenu_page(
			'ghl-crm-admin',
			__( 'Settings', 'ghl-crm-integration' ),
			__( 'Settings', 'ghl-crm-integration' ),
			'manage_options',
			'ghl-crm-admin#/settings',
			'__return_false'
		);

		add_submenu_page(
			'ghl-crm-admin',
			__( 'Integrations', 'ghl-crm-integration' ),
			__( 'Integrations', 'ghl-crm-integration' ),
			'manage_options',
			'ghl-crm-admin#/integrations',
			'__return_false'
		);

		add_submenu_page(
			'ghl-crm-admin',
			__( 'Field Mapping', 'ghl-crm-integration' ),
			__( 'Field Mapping', 'ghl-crm-integration' ),
			'manage_options',
			'ghl-crm-admin#/field-mapping',
			'__return_false'
		);

		add_submenu_page(
			'ghl-crm-admin',
			__( 'Sync Logs', 'ghl-crm-integration' ),
			__( 'Sync Logs', 'ghl-crm-integration' ),
			'manage_options',
			'ghl-crm-admin#/sync-logs',
			'__return_false'
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
			esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/settings' ) ),
			esc_html__( 'Settings', 'ghl-crm-integration' )
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
		if ( isset( $current_screen->id ) && strpos( $current_screen->id, 'ghl-crm' ) !== false ) {
			$footer_text = sprintf(
				/* translators: 1: Plugin name with link, 2: Star rating HTML */
				esc_html__( 'Thank you for using %1$s! If you find it helpful, please consider leaving a %2$s rating.', 'ghl-crm-integration' ),
				'<strong>' . esc_html__( 'GoHighLevel CRM Integration', 'ghl-crm-integration' ) . '</strong>',
				'<a href="https://wordpress.org/support/plugin/ghl-crm-integration/reviews/?filter=5#new-post" target="_blank" rel="noopener noreferrer">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ghl-crm-integration' ) );
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ghl-crm-integration' ) );
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
		wp_safe_redirect( admin_url( 'admin.php?page=ghl-crm-settings&tab=integrations' ) );
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
		wp_safe_redirect( admin_url( 'admin.php?page=ghl-crm-settings&tab=field-mapping' ) );
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
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ghl-crm-integration' ) );
		}

		$this->load_template( 'admin/spa-app' );
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
		check_ajax_referer( 'ghl_crm_spa_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this view.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		$view   = isset( $_POST['view'] ) ? sanitize_text_field( wp_unslash( $_POST['view'] ) ) : 'dashboard';
		$params = isset( $_POST['params'] ) && is_array( $_POST['params'] ) ? $_POST['params'] : [];

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

			case 'sync-logs':
				$this->get_sync_logs_data( $params );
				break;

			default:
				wp_send_json_error(
					[
						'message' => __( 'Invalid view requested.', 'ghl-crm-integration' ),
					],
					404
				);
		}
	}

	/**
	 * Handle OAuth disconnect request
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_oauth_disconnect(): void {
		check_ajax_referer( 'ghl_crm_oauth_disconnect', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to disconnect OAuth.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
		$result        = $oauth_handler->disconnect();

		if ( $result ) {
			wp_send_json_success(
				[
					'message' => __( 'Successfully disconnected from GoHighLevel.', 'ghl-crm-integration' ),
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Failed to disconnect. Please try again.', 'ghl-crm-integration' ),
				],
				500
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

		// Get OAuth status for dashboard
		$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
		$oauth_status  = $oauth_handler->get_connection_status();
		$settings      = SettingsManager::get_instance()->get_settings_array();

		?>
		<div class="ghl-crm-dashboard">
			<div class="ghl-card">
				<h2><?php esc_html_e( 'Dashboard', 'ghl-crm-integration' ); ?></h2>
				<div class="ghl-connection-status">
					<?php if ( $oauth_status['connected'] ) : ?>
						<div class="ghl-status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<strong><?php esc_html_e( 'Connected', 'ghl-crm-integration' ); ?></strong>
							<?php
							$location_name = $settings['location_name'] ?? 'GoHighLevel';
							if ( ! empty( $location_name ) && $location_name !== 'GoHighLevel' ) {
								printf(
									/* translators: %s: Location name */
									esc_html__( 'to %s', 'ghl-crm-integration' ),
									esc_html( $location_name )
								);
							}
							?>
							<br><small>
								<?php
								printf(
									/* translators: %s: Connection date */
									esc_html__( 'Connected: %s', 'ghl-crm-integration' ),
									esc_html( $oauth_status['connected_at'] ?? __( 'Unknown', 'ghl-crm-integration' ) )
								);
								?>
							</small>
							<br>
							<button type="button" class="button button-secondary" id="ghl-disconnect-btn" style="margin-top: 10px;">
								<?php esc_html_e( 'Disconnect Account', 'ghl-crm-integration' ); ?>
							</button>
						</div>

						<!-- Connection Details -->
						<div style="margin-top: 20px;">
							<h3><?php esc_html_e( 'Connection Details', 'ghl-crm-integration' ); ?></h3>
							<table class="widefat" style="margin-top: 10px;">
								<tbody>
									<tr>
										<td style="width: 30%; font-weight: 600;">
											<?php esc_html_e( 'Location ID:', 'ghl-crm-integration' ); ?>
										</td>
										<td><code><?php echo esc_html( $oauth_status['location_id'] ?: __( 'Not available', 'ghl-crm-integration' ) ); ?></code></td>
									</tr>
									<tr>
										<td style="font-weight: 600;">
											<?php esc_html_e( 'Location Name:', 'ghl-crm-integration' ); ?>
										</td>
										<td><?php echo esc_html( $location_name ); ?></td>
									</tr>
									<tr>
										<td style="font-weight: 600;">
											<?php esc_html_e( 'Token Status:', 'ghl-crm-integration' ); ?>
										</td>
										<td>
											<?php
											$expires_at = $oauth_status['expires_at'];
											if ( $expires_at && $expires_at > time() ) {
												$time_left = human_time_diff( time(), $expires_at );
												printf(
													/* translators: %s: Time remaining */
													esc_html__( 'Valid (expires in %s)', 'ghl-crm-integration' ),
													esc_html( $time_left )
												);
											} else {
												esc_html_e( 'Expired (will auto-refresh)', 'ghl-crm-integration' );
											}
											?>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<!-- Sync Statistics -->
						<?php
						global $wpdb;
						$table_name = $wpdb->prefix . 'ghl_crm_sync_logs';
						
						// Get stats if table exists
						if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) :
							$total_syncs   = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
							$success_syncs = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'success'" );
							$failed_syncs  = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed'" );
							$last_sync     = $wpdb->get_var( "SELECT created_at FROM {$table_name} ORDER BY created_at DESC LIMIT 1" );
							?>
							<div style="margin-top: 20px;">
								<h3><?php esc_html_e( 'Sync Statistics', 'ghl-crm-integration' ); ?></h3>
								<table class="widefat" style="margin-top: 10px;">
									<tbody>
										<tr>
											<td style="width: 30%; font-weight: 600;">
												<?php esc_html_e( 'Total Syncs:', 'ghl-crm-integration' ); ?>
											</td>
											<td><?php echo esc_html( number_format_i18n( (int) $total_syncs ) ); ?></td>
										</tr>
										<tr>
											<td style="font-weight: 600;">
												<?php esc_html_e( 'Successful:', 'ghl-crm-integration' ); ?>
											</td>
											<td style="color: #46b450;"><?php echo esc_html( number_format_i18n( (int) $success_syncs ) ); ?></td>
										</tr>
										<tr>
											<td style="font-weight: 600;">
												<?php esc_html_e( 'Failed:', 'ghl-crm-integration' ); ?>
											</td>
											<td style="color: #dc3232;"><?php echo esc_html( number_format_i18n( (int) $failed_syncs ) ); ?></td>
										</tr>
										<tr>
											<td style="font-weight: 600;">
												<?php esc_html_e( 'Last Sync:', 'ghl-crm-integration' ); ?>
											</td>
											<td>
												<?php
												if ( $last_sync ) {
													echo esc_html( human_time_diff( strtotime( $last_sync ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'ghl-crm-integration' ) );
												} else {
													esc_html_e( 'No syncs yet', 'ghl-crm-integration' );
												}
												?>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						<?php endif; ?>

					<?php else : ?>
						<div class="ghl-status-disconnected">
							<span class="dashicons dashicons-warning"></span>
							<strong><?php esc_html_e( 'Not Connected', 'ghl-crm-integration' ); ?></strong>
							<p><?php esc_html_e( 'Connect your GoHighLevel account to start syncing data.', 'ghl-crm-integration' ); ?></p>
							
							<?php
							$client_id     = $settings['oauth_client_id'] ?? '';
							$client_secret = $settings['oauth_client_secret'] ?? '';
							?>

							<?php if ( empty( $client_id ) || empty( $client_secret ) ) : ?>
								<div class="notice notice-warning inline" style="margin: 15px 0;">
									<p>
										<strong><?php esc_html_e( 'OAuth Credentials Required', 'ghl-crm-integration' ); ?></strong><br>
										<?php
										printf(
											/* translators: %s: Settings page link */
											__( 'Please configure your OAuth Client ID and Client Secret in the <a href="%s">API Settings</a> before connecting.', 'ghl-crm-integration' ),
											esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/settings/api' ) )
										);
										?>
									</p>
								</div>
							<?php else : ?>
								<a href="<?php echo esc_url( $oauth_handler->get_authorization_url() ); ?>" class="button button-primary button-hero" style="margin-top: 15px;">
									<span class="dashicons dashicons-cloud" style="margin-top: 5px;"></span>
									<?php esc_html_e( 'Connect to GoHighLevel', 'ghl-crm-integration' ); ?>
								</a>
								<p class="description" style="margin-top: 10px;">
									<?php esc_html_e( 'You will be redirected to GoHighLevel to authorize this integration.', 'ghl-crm-integration' ); ?>
								</p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#ghl-disconnect-btn').on('click', function(e) {
				e.preventDefault();
				
				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect your GoHighLevel account?', 'ghl-crm-integration' ) ); ?>')) {
					return;
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ghl_crm_oauth_disconnect',
						nonce: '<?php echo esc_js( wp_create_nonce( 'ghl_crm_oauth_disconnect' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data.message || '<?php echo esc_js( __( 'Failed to disconnect', 'ghl-crm-integration' ) ); ?>');
						}
					},
					error: function() {
						alert('<?php echo esc_js( __( 'An error occurred while disconnecting', 'ghl-crm-integration' ) ); ?>');
					}
				});
			});
		});
		</script>
		<?php

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
		$this->load_template( 'admin/field-mapping' );
		$html = ob_get_clean();

		wp_send_json_success(
			[
				'view' => 'field-mapping',
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
	 * Get valid settings tabs
	 *
	 * @return array<string> List of valid settings tab names
	 */
	public static function get_valid_settings_tabs(): array {
		return [
			'general',
			'api',
			'rest-api',
			'webhooks',
			'notifications',
			'field-sync',
			'contact-fields',
			'role-tags',
			'advanced',
		];
	}

	/**
	 * Handle AJAX request to load a specific settings tab
	 *
	 * @return void Outputs JSON response and exits.
	 */
	public function handle_settings_tab_request(): void {
		check_ajax_referer( 'ghl_crm_spa_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this page.', 'ghl-crm-integration' ),
				],
				403
			);
		}

		$tab = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : 'general';

		// Define valid tabs using centralized method
		$valid_tabs = self::get_valid_settings_tabs();

		if ( ! in_array( $tab, $valid_tabs, true ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid settings tab.', 'ghl-crm-integration' ),
				],
				400
			);
		}

		// Load the partial template
		$partial_file = GHL_CRM_PATH . 'templates/admin/partials/settings/' . $tab . '.php';

		if ( ! file_exists( $partial_file ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Settings tab template not found.', 'ghl-crm-integration' ),
				],
				404
			);
		}

		ob_start();
		include $partial_file;
		$html = ob_get_clean();

		wp_send_json_success(
			[
				'tab'  => $tab,
				'html' => $html,
			]
		);
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
			extract( $args, EXTR_SKIP );
		}

		$template_path = GHL_CRM_PATH . 'templates/' . $template_name . '.php';

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
					<strong><?php esc_html_e( 'Template Error:', 'ghl-crm-integration' ); ?></strong>
					<?php
					printf(
						/* translators: %s: Template name */
						esc_html__( 'The template file "%s.php" could not be found.', 'ghl-crm-integration' ),
						esc_html( $template_name )
					);
					?>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: Template path */
						esc_html__( 'Expected location: %s', 'ghl-crm-integration' ),
						'<code>' . esc_html( GHL_CRM_PATH . 'templates/' . $template_name . '.php' ) . '</code>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
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
