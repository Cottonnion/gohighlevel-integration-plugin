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
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		// Add admin menu
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . GHL_CRM_BASENAME, [ $this, 'add_plugin_action_links' ] );
	}

	/**
	 * Add admin menu and submenus
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Main menu page
		add_menu_page(
			__( 'GoHighLevel CRM', 'ghl-crm-integration' ),           // Page title
			__( 'GHL CRM', 'ghl-crm-integration' ),                   // Menu title
			'manage_options',                                          // Capability
			'ghl-crm-integration',                                     // Menu slug
			[ $this, 'render_settings_page' ],                        // Callback
			'dashicons-cloud',                                         // Icon
			58                                                         // Position (after Plugins)
		);

		// Settings submenu (will replace the duplicate main menu item)
		add_submenu_page(
			'ghl-crm-integration',                                     // Parent slug
			__( 'Settings', 'ghl-crm-integration' ),                  // Page title
			__( 'Settings', 'ghl-crm-integration' ),                  // Menu title
			'manage_options',                                          // Capability
			'ghl-crm-integration',                                     // Menu slug (same as parent)
			[ $this, 'render_settings_page' ]                         // Callback
		);

		// Integrations submenu
		add_submenu_page(
			'ghl-crm-integration',                                     // Parent slug
			__( 'Integrations', 'ghl-crm-integration' ),              // Page title
			__( 'Integrations', 'ghl-crm-integration' ),              // Menu title
			'manage_options',                                          // Capability
			'ghl-crm-integrations',                                    // Menu slug
			[ $this, 'render_integrations_page' ]                     // Callback
		);

		// Sync Logs submenu
		add_submenu_page(
			'ghl-crm-integration',                                     // Parent slug
			__( 'Sync Logs', 'ghl-crm-integration' ),                 // Page title
			__( 'Sync Logs', 'ghl-crm-integration' ),                 // Menu title
			'manage_options',                                          // Capability
			'ghl-crm-sync-logs',                                       // Menu slug
			[ $this, 'render_sync_logs_page' ]                        // Callback
		);

		// Field Mapping submenu
		add_submenu_page(
			'ghl-crm-integration',                                     // Parent slug
			__( 'Field Mapping', 'ghl-crm-integration' ),             // Page title
			__( 'Field Mapping', 'ghl-crm-integration' ),             // Menu title
			'manage_options',                                          // Capability
			'ghl-crm-field-mapping',                                   // Menu slug
			[ $this, 'render_field_mapping_page' ]                    // Callback
		);
	}

	/**
	 * Add Settings link to plugin action links
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=ghl-crm-integration' ) ),
			esc_html__( 'Settings', 'ghl-crm-integration' )
		);

		// Add settings link at the beginning
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ghl-crm-integration' ) );
		}

		$this->load_template( 'admin/settings' );
	}

	/**
	 * Render sync logs page
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
	 * Render integrations page
	 *
	 * @return void
	 */
	public function render_integrations_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ghl-crm-integration' ) );
		}

		$this->load_template( 'admin/integrations' );
	}

	/**
	 * Render field mapping page
	 *
	 * @return void
	 */
	public function render_field_mapping_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ghl-crm-integration' ) );
		}

		$this->load_template( 'admin/field-mapping' );
	}

	/**
	 * Load a template file
	 *
	 * @param string $template_name Template name (without .php extension).
	 * @param array  $args          Optional. Arguments to pass to the template.
	 * @return void
	 */
	private function load_template( string $template_name, array $args = [] ): void {
		// Extract args to make them available in the template
		if ( ! empty( $args ) ) {
			extract( $args, EXTR_SKIP );
		}

		// Build template path
		$template_path = GHL_CRM_PATH . 'templates/' . $template_name . '.php';

		// Check if template exists
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			// Show error if template not found
			$this->render_template_error( $template_name );
		}
	}

	/**
	 * Render template error message
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
