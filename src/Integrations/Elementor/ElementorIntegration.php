<?php
declare(strict_types=1);

namespace GHL_CRM\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor Integration
 *
 * Handles Elementor plugin integration and widget registration
 *
 * @package    GHL_CRM_Integration
 * @subpackage Integrations/Elementor
 */
class ElementorIntegration {

	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get class instance
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
	 * Private constructor
	 */
	private function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks and filters
	 *
	 * @return void
	 */
	private function setup_hooks(): void {
		// Check if Elementor is active
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		// Register widgets
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );

		// Register widget category
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );

		// Enqueue editor styles
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'enqueue_editor_styles' ] );
	}

	/**
	 * Register Elementor widgets
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ): void {
		require_once GHL_CRM_PATH . 'src/Integrations/Elementor/FormWidget.php';

		$widgets_manager->register( new FormWidget() );
	}

	/**
	 * Register custom widget category
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 * @return void
	 */
	public function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			'ghl-crm',
			[
				'title' => __( 'GoHighLevel CRM', 'ghl-crm-integration' ),
				'icon'  => 'fa fa-plug',
			]
		);
	}

	/**
	 * Enqueue editor styles
	 *
	 * @return void
	 */
	public function enqueue_editor_styles(): void {
		// Add custom CSS for Elementor editor if needed
		wp_add_inline_style(
			'elementor-editor',
			'
			.elementor-element .icon .eicon-form-horizontal {
				color: #2196F3;
			}
			'
		);
	}

	/**
	 * Check if Elementor is active
	 *
	 * @return bool
	 */
	public static function is_elementor_active(): bool {
		return did_action( 'elementor/loaded' );
	}

	/**
	 * Initialize (called by Loader)
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}