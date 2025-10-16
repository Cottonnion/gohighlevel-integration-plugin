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
	private array $container = [];

	/**
	 * Plugin components that need initialization
	 *
	 * @var array<string, class-string>
	 */
	private array $components = [];

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
		$this->components = [
			// Core components
			'core.assets'     => \GHL_CRM\Core\AssetsManager::class,
			'core.ajax'       => \GHL_CRM\Core\AjaxHandler::class,
			'core.menu'       => \GHL_CRM\Core\MenuManager::class,
		];
	}	
    
    /**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Plugin activation/deactivation
		register_activation_hook( GHL_CRM_PATH . 'gohighlevel-crm-integration.php', [ self::class, 'activate' ] );
		register_deactivation_hook( GHL_CRM_PATH . 'gohighlevel-crm-integration.php', [ self::class, 'deactivate' ] );

		// Initialize components after plugins loaded
		add_action( 'plugins_loaded', [ $this, 'init_components' ], 20 );

		// Load textdomain
		add_action( 'init', [ $this, 'load_textdomain' ] );
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
				[ 'back_link' => true ]
			);
		}

		// Activation logic here
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
		// Deactivation logic here
		flush_rewrite_rules();

		// Fire deactivation hook
		do_action( 'ghl_crm_deactivated' );
	}

	/**
	 * Load plugin textdomain
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			GHL_CRM_TEXTDOMAIN,
			false,
			dirname( GHL_CRM_BASENAME ) . '/languages'
		);
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
