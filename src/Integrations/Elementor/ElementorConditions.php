<?php
declare(strict_types=1);

namespace GHL_CRM\Integrations\Elementor;

use Elementor\Controls_Manager;
use GHL_CRM\Core\AssetsManager;
use GHL_CRM\Membership\AccessControl;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor Conditions
 *
 * Adds conditional visibility controls to all Elementor widgets based on GHL tags
 *
 * @package    GHL_CRM_Integration
 * @subpackage Integrations/Elementor
 */
class ElementorConditions {

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

		// Add controls to widget Advanced tab
		add_action( 'elementor/element/common/_section_style/after_section_end', [ $this, 'add_restriction_controls' ], 10, 2 );

		// Filter widget render content to prevent output for restricted widgets
		// Using high priority to run early
		add_filter( 'elementor/widget/render_content', [ $this, 'filter_widget_content' ], 10, 2 );

		// Register and enqueue editor scripts when Elementor editor loads
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );
	}

	/**
	 * Register Elementor editor assets with AssetsManager
	 *
	 * @return void
	 */
	private function register_editor_assets(): void {
		AssetsManager::get_instance()->add_public_asset(
			'ghl-elementor-conditions',
			'elementor-conditions.js',
			[ 'jquery', 'elementor-editor' ],
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'ghl_user_profile' ),
				'availableTags' => $this->get_available_tags(),
			],
			GHL_CRM_VERSION,
			true,
			GHL_CRM_URL . 'assets/admin/js/'
		);
	}

	/**
	 * Add restriction controls to Advanced tab
	 *
	 * @param \Elementor\Widget_Base $element Widget instance.
	 * @param array                  $args    Arguments (unused, provided by Elementor).
	 * @return void
	 */
	public function add_restriction_controls( $element, $args ): void {
		// Suppressing unused parameter warning - required by Elementor hook signature
		unset( $args );

		// Start controls section
		$element->start_controls_section(
			'ghl_restriction_section',
			[
				'label' => __( 'GoHighLevel Restrictions', 'ghl-crm-integration' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			]
		);

		// Enable restriction
		$element->add_control(
			'ghl_enable_restriction',
			[
				'label'        => __( 'Enable Restriction', 'ghl-crm-integration' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'ghl-crm-integration' ),
				'label_off'    => __( 'No', 'ghl-crm-integration' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Show/hide this widget based on GoHighLevel tags', 'ghl-crm-integration' ),
			]
		);

		// Restriction type
		$element->add_control(
			'ghl_restriction_type',
			[
				'label'     => __( 'Restriction Type', 'ghl-crm-integration' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'has_any_tag',
				'options'   => [
					'has_any_tag'  => __( 'User has ANY of these tags', 'ghl-crm-integration' ),
					'has_all_tags' => __( 'User has ALL of these tags', 'ghl-crm-integration' ),
					'not_has_tags' => __( 'User does NOT have these tags', 'ghl-crm-integration' ),
					'logged_in'    => __( 'User is logged in (any user)', 'ghl-crm-integration' ),
					'logged_out'   => __( 'User is NOT logged in', 'ghl-crm-integration' ),
				],
				'condition' => [
					'ghl_enable_restriction' => 'yes',
				],
			]
		);

		// Tags input
		$element->add_control(
			'ghl_required_tags',
			[
				'label'       => __( 'Required Tags', 'ghl-crm-integration' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->get_available_tags(),
				'label_block' => true,
				'description' => __( 'Select tags from your GoHighLevel account', 'ghl-crm-integration' ),
				'condition'   => [
					'ghl_enable_restriction' => 'yes',
					'ghl_restriction_type!'  => [ 'logged_in', 'logged_out' ],
				],
			]
		);

		// Hide for non-admins option
		$element->add_control(
			'ghl_hide_completely',
			[
				'label'        => __( 'Hide Completely', 'ghl-crm-integration' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'ghl-crm-integration' ),
				'label_off'    => __( 'No', 'ghl-crm-integration' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'If disabled, widget space will be preserved (invisible but takes space)', 'ghl-crm-integration' ),
				'condition'    => [
					'ghl_enable_restriction' => 'yes',
				],
			]
		);

		$element->end_controls_section();
	}

	/**
	 * Filter widget render content to hide restricted widgets
	 *
	 * @param string                 $content Widget HTML content.
	 * @param \Elementor\Widget_Base $widget  Widget instance.
	 * @return string Filtered content (empty if restricted)
	 */
	public function filter_widget_content( string $content, $widget ): string {
		// Skip in editor mode
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $content;
		}

		// Get widget settings
		$settings = $widget->get_settings();

		// Check if restriction is enabled
		if ( empty( $settings['ghl_enable_restriction'] ) || 'yes' !== $settings['ghl_enable_restriction'] ) {
			return $content;
		}

		// Get restriction type
		$restriction_type = $settings['ghl_restriction_type'] ?? 'has_any_tag';
		$hide_completely  = $settings['ghl_hide_completely'] ?? 'yes';

		// Check logged-in/logged-out conditions first
		if ( 'logged_in' === $restriction_type ) {
			if ( ! is_user_logged_in() ) {
				return $this->get_hidden_content( $hide_completely );
			}
			return $content;
		}

		if ( 'logged_out' === $restriction_type ) {
			if ( is_user_logged_in() ) {
				return $this->get_hidden_content( $hide_completely );
			}
			return $content;
		}

		// Get required tags first
		$required_tags = isset( $settings['ghl_required_tags'] ) ? $settings['ghl_required_tags'] : '';

		// Handle both array (SELECT2) and string (legacy) formats
		if ( is_string( $required_tags ) ) {
			$required_tags = array_map( 'trim', explode( ',', $required_tags ) );
		}

		if ( ! is_array( $required_tags ) ) {
			$required_tags = [];
		}

		$required_tags = array_filter( $required_tags );

		// For has_any_tag and has_all_tags: logged-out users should be hidden regardless of tags
		if ( 'not_has_tags' !== $restriction_type && ! is_user_logged_in() ) {
			return $this->get_hidden_content( $hide_completely );
		}

		// If no tags specified and it's not_has_tags, this means "user has none of (empty)" = allow
		// For has_any_tag/has_all_tags with no tags, require login to see
		if ( empty( $required_tags ) ) {
			if ( 'not_has_tags' === $restriction_type ) {
				// No tags to avoid = show content
				return $content;
			}
			// For has_any_tag/has_all_tags with no tags selected: hide from non-logged-in users
			if ( ! is_user_logged_in() ) {
				return $this->get_hidden_content( $hide_completely );
			}
			// Logged in user with no tags required = show content
			return $content;
		}

		// Get user's tags
		$user_id        = get_current_user_id();
		$access_control = AccessControl::get_instance();
		$user_tags      = $access_control->get_user_tags( $user_id );

		// Normalize tags for comparison
		$required_tags_lower = array_map( 'strtolower', $required_tags );
		$user_tags_lower     = array_map( 'strtolower', $user_tags );

		// Check access based on restriction type
		$has_access = false;

		switch ( $restriction_type ) {
			case 'has_all_tags':
				// User must have ALL required tags
				$has_access = empty( array_diff( $required_tags_lower, $user_tags_lower ) );
				break;

			case 'not_has_tags':
				// User must NOT have any of the tags
				$has_access = empty( array_intersect( $required_tags_lower, $user_tags_lower ) );
				break;

			case 'has_any_tag':
			default:
				// User must have AT LEAST ONE tag
				$has_access = ! empty( array_intersect( $required_tags_lower, $user_tags_lower ) );
				break;
		}

		// Return content or hide based on access
		if ( $has_access ) {
			return $content;
		}

		return $this->get_hidden_content( $hide_completely );
	}

	/**
	 * Get hidden content (empty or placeholder)
	 *
	 * @param string $hide_completely Whether to hide completely or preserve space.
	 * @return string Empty string or invisible placeholder.
	 */
	private function get_hidden_content( string $hide_completely ): string {
		if ( 'yes' === $hide_completely ) {
			// Return empty - widget won't render at all
			return '';
		}

		// Return invisible placeholder that preserves space
		return '<div style="visibility: hidden; min-height: 50px;"></div>';
	}

	/**
	 * Get available tags from GoHighLevel
	 *
	 * @return array Array of tag options [tag_name => tag_name]
	 */
	private function get_available_tags(): array {
		$tag_manager = \GHL_CRM\Sync\TagManager::get_instance();

		try {
			// Get all tags for the current location
			$tags = $tag_manager->get_tags();

			if ( empty( $tags ) || ! is_array( $tags ) ) {
				return [];
			}

			// Build options array [name => name] for Elementor SELECT2
			$options = [];
			foreach ( $tags as $tag ) {
				if ( is_array( $tag ) && isset( $tag['name'] ) ) {
					$tag_name             = (string) $tag['name'];
					$options[ $tag_name ] = $tag_name;
				} elseif ( is_string( $tag ) ) {
					$options[ $tag ] = $tag;
				}
			}

			return $options;
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Enqueue editor scripts for dynamic tag loading
	 *
	 * Registers the asset here (instead of in the constructor) so that
	 * the `elementor-editor` dependency is already registered by
	 * Elementor before we call wp_register_script.
	 *
	 * @return void
	 */
	public function enqueue_editor_scripts(): void {
		$this->register_editor_assets();
		AssetsManager::get_instance()->enqueue_public_asset( 'ghl-elementor-conditions' );
	}

	/**
	 * Initialize (called by ElementorIntegration)
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}
