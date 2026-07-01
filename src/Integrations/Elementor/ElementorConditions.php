<?php
declare(strict_types=1);

namespace Syncly\Integrations\Elementor;

use Elementor\Controls_Manager;
use Syncly\Core\AssetsManager;
use Syncly\Membership\AccessControl;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor Conditions
 *
 * Adds conditional visibility controls to all Elementor widgets based on GHL tags
 *
 * @package    Syncly
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
			'syncly-elementor-conditions',
			'elementor-conditions.js',
			[ 'jquery', 'elementor-editor' ],
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'syncly_user_profile' ),
				'availableTags' => $this->get_available_tags(),
			],
			SYNCLY_VERSION,
			true,
			SYNCLY_URL . 'assets/admin/js/'
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
				'label' => __( 'GHL Conditional Display', 'syncly' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			]
		);

		// Enable restriction
		$element->add_control(
			'ghl_enable_restriction',
			[
				'label'        => __( 'Enable Restriction', 'syncly' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'syncly' ),
				'label_off'    => __( 'No', 'syncly' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Show/hide this widget based on GoHighLevel tags', 'syncly' ),
			]
		);

		// Restriction type
		$element->add_control(
			'ghl_restriction_type',
			[
				'label'     => __( 'Restriction Type', 'syncly' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'has_any_tag',
				'options'   => [
					'has_any_tag'  => __( 'User has ANY of these tags', 'syncly' ),
					'has_all_tags' => __( 'User has ALL of these tags', 'syncly' ),
					'not_has_tags' => __( 'User does NOT have these tags', 'syncly' ),
					'logged_in'    => __( 'User is logged in (any user)', 'syncly' ),
					'logged_out'   => __( 'User is NOT logged in', 'syncly' ),
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
				'label'       => __( 'Required Tags', 'syncly' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->get_available_tags(),
				'label_block' => true,
				'description' => __( 'Select tags from your GoHighLevel account', 'syncly' ),
				'condition'   => [
					'ghl_enable_restriction' => 'yes',
					'ghl_restriction_type!'  => [ 'logged_in', 'logged_out' ],
				],
			]
		);

		// ── Additional Tag Condition Groups (Repeater) ──────────

		$element->add_control(
			'ghl_conditions_heading',
			[
				'label'     => __( 'Additional Conditions', 'syncly' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'ghl_enable_restriction' => 'yes',
					'ghl_restriction_type!'  => [ 'logged_in', 'logged_out' ],
				],
			]
		);

		$element->add_control(
			'ghl_condition_logic',
			[
				'label'       => __( 'Condition Logic', 'syncly' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'and',
				'options'     => [
					'and' => __( 'AND — all conditions must pass', 'syncly' ),
					'or'  => __( 'OR — any condition can pass', 'syncly' ),
				],
				'description' => __( 'How the primary condition above combines with additional conditions below.', 'syncly' ),
				'condition'   => [
					'ghl_enable_restriction' => 'yes',
					'ghl_restriction_type!'  => [ 'logged_in', 'logged_out' ],
				],
			]
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'match_type',
			[
				'label'   => __( 'Match Type', 'syncly' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'has_any',
				'options' => [
					'has_any'  => __( 'User has ANY of these tags', 'syncly' ),
					'has_all'  => __( 'User has ALL of these tags', 'syncly' ),
					'has_none' => __( 'User has NONE of these tags', 'syncly' ),
				],
			]
		);

		$repeater->add_control(
			'tags',
			[
				'label'       => __( 'Tags', 'syncly' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $this->get_available_tags(),
				'label_block' => true,
			]
		);

		$element->add_control(
			'ghl_tag_conditions',
			[
				'label'         => __( 'Condition Groups', 'syncly' ),
				'type'          => Controls_Manager::REPEATER,
				'fields'        => $repeater->get_controls(),
				'default'       => [],
				'prevent_empty' => false,
				'title_field'   => '{{{ match_type === "has_any" ? "Has ANY of" : match_type === "has_all" ? "Has ALL of" : "Has NONE of" }}} ({{{ tags ? tags.length : 0 }}} tags)',
				'description'   => __( 'Add condition groups to build compound rules. Leave empty to use only the primary condition above.', 'syncly' ),
				'condition'     => [
					'ghl_enable_restriction' => 'yes',
					'ghl_restriction_type!'  => [ 'logged_in', 'logged_out' ],
				],
			]
		);

		// Hide for non-admins option
		$element->add_control(
			'ghl_hide_completely',
			[
				'label'        => __( 'Hide Completely', 'syncly' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'syncly' ),
				'label_off'    => __( 'No', 'syncly' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'If disabled, widget space will be preserved (invisible but takes space)', 'syncly' ),
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

		// For tag-based conditions, logged-out users cannot have tags
		// (except not_has_tags with no additional conditions — handled below)
		$is_logged_in = is_user_logged_in();
		$user_tags    = [];

		if ( $is_logged_in ) {
			$user_id        = get_current_user_id();
			$access_control = AccessControl::get_instance();
			$user_tags      = array_map( 'strtolower', $access_control->get_user_tags( $user_id ) );
		}

		// Evaluate primary condition
		$required_tags  = $this->normalize_tags_setting( $settings['ghl_required_tags'] ?? '' );
		$primary_result = $this->evaluate_condition( $restriction_type, $required_tags, $user_tags, $is_logged_in );

		// Check for additional condition groups (repeater)
		$tag_conditions  = $settings['ghl_tag_conditions'] ?? [];
		$condition_logic = $settings['ghl_condition_logic'] ?? 'and';

		if ( ! empty( $tag_conditions ) && is_array( $tag_conditions ) ) {
			// Evaluate each additional condition group
			$group_results = [ $primary_result ];

			foreach ( $tag_conditions as $condition ) {
				$match_type     = $condition['match_type'] ?? 'has_any';
				$condition_tags = $this->normalize_tags_setting( $condition['tags'] ?? [] );

				if ( empty( $condition_tags ) ) {
					continue; // Skip empty groups
				}

				// Map repeater match_type to restriction_type format
				$mapped_type     = $this->map_match_type( $match_type );
				$group_results[] = $this->evaluate_condition( $mapped_type, $condition_tags, $user_tags, $is_logged_in );
			}

			// Combine all results using the chosen logic
			if ( 'or' === $condition_logic ) {
				$has_access = in_array( true, $group_results, true );
			} else {
				// AND — all conditions must pass
				$has_access = ! in_array( false, $group_results, true );
			}
		} else {
			// No additional conditions — just use primary result
			$has_access = $primary_result;
		}

		if ( $has_access ) {
			return $content;
		}

		return $this->get_hidden_content( $hide_completely );
	}

	/**
	 * Evaluate a single tag condition
	 *
	 * @param string $restriction_type Condition type (has_any_tag, has_all_tags, not_has_tags).
	 * @param array  $required_tags    Required tag names (lowercase).
	 * @param array  $user_tags        User's current tags (lowercase).
	 * @param bool   $is_logged_in     Whether the user is logged in.
	 * @return bool Whether the condition passes.
	 */
	private function evaluate_condition( string $restriction_type, array $required_tags, array $user_tags, bool $is_logged_in ): bool {
		// For positive conditions (has_any, has_all), logged-out users fail
		if ( 'not_has_tags' !== $restriction_type && ! $is_logged_in ) {
			return false;
		}

		// No tags specified
		if ( empty( $required_tags ) ) {
			if ( 'not_has_tags' === $restriction_type ) {
				return true; // No tags to avoid = pass
			}
			// has_any/has_all with no tags: pass if logged in
			return $is_logged_in;
		}

		// Not logged in + not_has_tags: logged-out user has no tags, so they pass
		if ( ! $is_logged_in && 'not_has_tags' === $restriction_type ) {
			return true;
		}

		$required_lower = array_map( 'strtolower', $required_tags );

		switch ( $restriction_type ) {
			case 'has_all_tags':
				return empty( array_diff( $required_lower, $user_tags ) );

			case 'not_has_tags':
				return empty( array_intersect( $required_lower, $user_tags ) );

			case 'has_any_tag':
			default:
				return ! empty( array_intersect( $required_lower, $user_tags ) );
		}
	}

	/**
	 * Map repeater match_type to restriction_type format
	 *
	 * @param string $match_type Repeater match type (has_any, has_all, has_none).
	 * @return string Restriction type format.
	 */
	private function map_match_type( string $match_type ): string {
		$map = [
			'has_any'  => 'has_any_tag',
			'has_all'  => 'has_all_tags',
			'has_none' => 'not_has_tags',
		];
		return $map[ $match_type ] ?? 'has_any_tag';
	}

	/**
	 * Normalize a tags setting value to a clean array
	 *
	 * @param mixed $tags Raw tags value (string, array, or empty).
	 * @return array Clean array of tag names.
	 */
	private function normalize_tags_setting( $tags ): array {
		if ( is_string( $tags ) ) {
			$tags = array_map( 'trim', explode( ',', $tags ) );
		}

		if ( ! is_array( $tags ) ) {
			$tags = [];
		}

		return array_values( array_filter( $tags ) );
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
		$tag_manager = \Syncly\Sync\TagManager::get_instance();

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
		AssetsManager::get_instance()->enqueue_public_asset( 'syncly-elementor-conditions' );
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
