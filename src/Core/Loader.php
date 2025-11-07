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
		$this->components = array(
			// Core components
			'core.database'      => \GHL_CRM\Core\Database::class,
			'core.settings'      => \GHL_CRM\Core\SettingsManager::class,
			'core.assets'        => \GHL_CRM\Core\AssetsManager::class,
			'core.menu'          => \GHL_CRM\Core\MenuManager::class,
			'core.notices'       => \GHL_CRM\Core\AdminNotices::class,
			'core.autologin'     => \GHL_CRM\Core\AutoLoginManager::class,
			'core.shortcodes'    => \GHL_CRM\Core\ShortcodeManager::class,

			// Admin UI components
			'admin.ui'           => \GHL_CRM\Admin\AdminUI::class,

			// API components
			'api.oauth'          => \GHL_CRM\API\OAuth\OAuthHandler::class,
			'api.webhooks'       => \GHL_CRM\API\Webhooks\WebhookHandler::class,
			'api.rest'           => \GHL_CRM\API\RestAPIController::class,

			// Sync components
			'sync.queue'         => \GHL_CRM\Sync\QueueManager::class,
			'sync.ghl_to_wp'     => \GHL_CRM\Sync\GHLToWordPressSync::class,
			'sync.custom_object' => \GHL_CRM\Sync\CustomObjectSync::class,

			// Integration components
			'integrations.users'      => \GHL_CRM\Integrations\Users\UserHooks::class,
			'integrations.role_tags'  => \GHL_CRM\Integrations\Users\RoleTagsManager::class,
			'integrations.woocommerce' => \GHL_CRM\Integrations\WooCommerce\WooCommerceSync::class,
			'integrations.buddyboss'  => \GHL_CRM\Integrations\BuddyBoss\GroupsSync::class,
			'integrations.buddyboss.metabox' => \GHL_CRM\Integrations\BuddyBoss\GroupMetaBox::class,

			// Membership components
			'membership.metaboxes'    => \GHL_CRM\Membership\Admin\MetaBoxes::class,
			'membership.restrictions' => \GHL_CRM\Membership\Restrictions::class,
		);
	}

	/**
	 * Initialize WordPress hooks
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
			if ( ! isset( $this->container[ $key ] ) ) {
				if ( method_exists( $class, 'get_instance' ) ) {
					$this->container[ $key ] = $class::get_instance();
				} else {
					$this->container[ $key ] = new $class();
				}

				// Call init method if it exists
				if ( method_exists( $this->container[ $key ], 'init' ) ) {
					$this->container[ $key ]->init();
				}
			}
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
		if ( ! isset( $this->container[ $key ] ) && isset( $this->components[ $key ] ) ) {
			$this->container[ $key ] = new $this->components[ $key ]();
		}
		return $this->container[ $key ] ?? null;
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
}
