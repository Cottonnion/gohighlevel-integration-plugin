<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main loader class for GoHighLevel CRM Integration
 *
 * Handles initialization, activation, deactivation, and component management.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core
 */
class Loader {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Container for storing plugin components
	 *
	 * @var array<string, object>
	 */
	private array $container = array();

	/**
	 * Plugin components that need initialization
	 *
	 * @var array<string, class-string>
	 */
	private array $components = array();

	/**
	 * Tracks which components have had their init() hook executed.
	 *
	 * @var array<string, bool>
	 */
	private array $initialized = array();

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
		$this->define_components();
		$this->init_hooks();
	}

	/**
	 * Define plugin components
	 *
	 * @return void
	 */
	private function define_components(): void {
		$components = array(
			// Core components
			'core.database'                            => \GHL_CRM\Core\Database::class,
			'core.settings'                            => \GHL_CRM\Core\SettingsManager::class,
			'core.assets'                              => \GHL_CRM\Core\AssetsManager::class,
			'core.menu'                                => \GHL_CRM\Core\MenuManager::class,
			'core.notices'                             => \GHL_CRM\Core\AdminNotices::class,
			'core.autologin'                           => \GHL_CRM\Core\AutoLoginManager::class,
			'core.shortcodes'                          => \GHL_CRM\Core\ShortcodeManager::class,
			'core.notifications'                       => \GHL_CRM\Core\NotificationManager::class,
			'core.form_settings'                       => \GHL_CRM\Core\FormSettings::class,

			// Admin UI components
			'admin.ui'                                 => \GHL_CRM\Admin\AdminUI::class,

			// API components
			'api.oauth'                                => \GHL_CRM\API\OAuth\OAuthHandler::class,
			'api.webhooks'                             => \GHL_CRM\API\Webhooks\WebhookHandler::class,
			'api.rest'                                 => \GHL_CRM\API\RestAPIController::class,

			// Sync components
			'sync.queue'                               => \GHL_CRM\Sync\QueueManager::class,
			'sync.ghl_to_wp'                           => \GHL_CRM\Sync\GHLToWordPressSync::class,

			// Integration components
			'integrations.users'                       => \GHL_CRM\Integrations\Users\UserHooks::class,
			'integrations.role_tags'                   => \GHL_CRM\Integrations\Users\RoleTagsManager::class,

			'integrations.buddyboss'                   => \GHL_CRM\Integrations\BuddyBoss\GroupsSync::class,
			'integrations.buddyboss.group_metabox'     => \GHL_CRM\Integrations\BuddyBoss\GroupMetaBox::class,

			'integrations.elementor'                   => \GHL_CRM\Integrations\Elementor\ElementorIntegration::class,

			// Membership components
			'membership.metaboxes'                     => \GHL_CRM\Membership\Admin\MetaBoxes::class,
			'membership.restrictions'                  => \GHL_CRM\Membership\Restrictions::class,
		);

		if ( function_exists( 'apply_filters' ) ) {
			$this->components = apply_filters( 'ghl_crm_loader_components', $components );
		} else {
			$this->components = $components;
		}
	}

	/**
	 * Initialize hooks for the plugin
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Plugin activation/deactivation
		register_activation_hook( GHL_CRM_PATH . 'gohighlevel-crm-integration.php', array( self::class, 'activate' ) );
		register_deactivation_hook( GHL_CRM_PATH . 'gohighlevel-crm-integration.php', array( self::class, 'deactivate' ) );

		// Initialize components EARLY (priority 1) to catch multisite activation
		// wp-activate.php runs before most plugins, so we need to init ASAP
		add_action( 'plugins_loaded', array( $this, 'init_components' ), 1 );

		// Register custom cron schedules
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Register cleanup action (Action Scheduler hook)
		add_action( 'ghl_crm_cleanup_database', array( \GHL_CRM\Core\Database::class, 'cleanup' ) );
		
		// Setup wizard redirect on activation
		add_action( 'admin_init', array( self::class, 'maybe_redirect_to_wizard' ) );
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_schedules( array $schedules ): array {
		$schedules['ghl_crm_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'ghl-crm-integration' ),
		);
		return $schedules;
	}

	/**
	 * Initialize plugin components
	 *
	 * @return void
	 */
	public function init_components(): void {
		foreach ( $this->components as $key => $class ) {
			$this->resolve_component( $key, $class, true );
		}

		// Fire action after all components are loaded
		do_action( 'ghl_crm_loaded' );
	}

	/**
	 * Plugin activation handler
	 * Multisite-aware: Creates tables for each site
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Check minimum requirements
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( GHL_CRM_BASENAME );
			wp_die(
				esc_html__( 'GoHighLevel CRM Integration requires PHP 7.4 or higher.', 'ghl-crm-integration' ),
				esc_html__( 'Plugin Activation Error', 'ghl-crm-integration' ),
				array( 'back_link' => true )
			);
		}

		// Create/update database tables
		if ( is_multisite() ) {
			// Create tables for all existing sites
			$sites = get_sites(
				array(
					'number' => 999,
				)
			);

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				\GHL_CRM\Core\Database::get_instance()->init();
				restore_current_blog();
			}
		} else {
			\GHL_CRM\Core\Database::get_instance()->init();
		}

		// Schedule cleanup job
		\GHL_CRM\Core\Database::get_instance()->schedule_cleanup();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set transient to trigger setup wizard redirect
		// TESTING MODE: Always set transient on activation for testing
		// PRODUCTION MODE: Only set if wizard not completed
		// TODO: Replace the line below with commented code when done testing:
		// set_transient( 'ghl_crm_activation_redirect', true, 60 );
		
		// Production code :
		if ( ! get_option( 'ghl_crm_setup_wizard_completed', false ) ) {
			set_transient( 'ghl_crm_activation_redirect', true, 60 );
		}

		// Fire activation hook
		do_action( 'ghl_crm_activated' );
	}

	/**
	 * Plugin deactivation handler
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Unschedule all Action Scheduler actions
		\GHL_CRM\Sync\QueueManager::unschedule_actions();

		// Unschedule cleanup (Action Scheduler)
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'ghl_crm_cleanup_database', array(), 'ghl-crm' );
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		// Fire deactivation hook
		do_action( 'ghl_crm_deactivated' );
	}

	/**
	 * Get a component from container
	 *
	 * @param string $key The component key.
	 * @return object|null The component instance or null if not found.
	 */
	public function get_component( string $key ): ?object {
		if ( ! isset( $this->components[ $key ] ) ) {
			return null;
		}

		return $this->resolve_component( $key, $this->components[ $key ], true );
	}

	/**
	 * Instantiate a component and optionally run its init() method.
	 *
	 * @param string       $key        Component key.
	 * @param class-string $class_name Fully qualified class name.
	 * @param bool         $run_init   Whether to call init() immediately.
	 * @return object|null The component instance.
	 */
	private function resolve_component( string $key, string $class_name, bool $run_init = false ): ?object {
		if ( ! isset( $this->container[ $key ] ) ) {
			$this->container[ $key ] = method_exists( $class_name, 'get_instance' )
				? $class_name::get_instance()
				: new $class_name();
		}

		$instance = $this->container[ $key ];

		if ( $run_init && ! ( $this->initialized[ $key ] ?? false ) && method_exists( $instance, 'init' ) ) {
			$instance->init();
			$this->initialized[ $key ] = true;
		}

		return $instance;
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

	/**
	 * Run the plugin (for compatibility)
	 *
	 * @return void
	 */
	public function run(): void {
		// Components are initialized via init_components hook
		// This method is kept for compatibility/future use
	}

	/**
	 * Redirect to setup wizard on plugin activation
	 *
	 * Checks for activation transient and redirects to setup wizard if present.
	 * Includes safety checks to prevent redirects during bulk activation, AJAX, etc.
	 *
	 * TESTING MODE: Currently redirects on every activation
	 * PRODUCTION MODE: Will only redirect if wizard not completed (see activate() method)
	 *
	 * @return void
	 */
	public static function maybe_redirect_to_wizard(): void {
		// Check if we should redirect
		if ( ! get_transient( 'ghl_crm_activation_redirect' ) ) {
			return;
		}

		// Delete the transient so we don't redirect again
		delete_transient( 'ghl_crm_activation_redirect' );

		// Don't redirect if activating multiple plugins
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Don't redirect on AJAX requests
		if ( wp_doing_ajax() ) {
			return;
		}

		// Don't redirect if not in admin
		if ( ! is_admin() ) {
			return;
		}

		// Don't redirect if user can't manage options
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// TESTING MODE: Comment out this check to test wizard on every activation
		// PRODUCTION MODE: Uncomment below to prevent redirect if wizard already completed
		// $wizard_completed = get_option( 'ghl_crm_setup_wizard_completed', false );
		if ( $wizard_completed ) {
			return;
		}

		// Redirect to setup wizard
		wp_safe_redirect( admin_url( 'admin.php?page=ghl-crm-setup-wizard' ) );
		exit;
	}
}
