<?php
declare(strict_types=1);

namespace GHL_CRM\Integrations\Gutenberg;

use GHL_CRM\API\ConnectionManager;
use GHL_CRM\Core\AssetsManager;
use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Sync\TagManager;

defined( 'ABSPATH' ) || exit;

/**
 * Gutenberg Blocks Manager
 *
 * Registers and manages Gutenberg blocks for GoHighLevel integration
 *
 * @package    GHL_CRM_Integration
 * @subpackage Integrations/Gutenberg
 */
class BlocksManager {
	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Connection Manager
	 *
	 * @var ConnectionManager
	 */
	private ConnectionManager $connection_manager;

	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Get instance
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
		$this->connection_manager = ConnectionManager::get_instance();
		$this->settings_manager   = SettingsManager::get_instance();
		$this->init();
	}

	/**
	 * Initialize Gutenberg integration
	 *
	 * @return void
	 */
	public function init(): void {
		// Register blocks
		add_action( 'init', [ $this, 'register_blocks' ] );

		// Add block category
		add_filter( 'block_categories_all', [ $this, 'add_block_category' ], 10, 2 );

		// Block editor assets are now defined centrally in AssetsManager::define_block_editor_assets()

		// Enqueue frontend assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
	}

	/**
	 * Register Gutenberg blocks
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		// Register GHL Form Block
		register_block_type(
			'ghl-crm/form',
			[
				'render_callback' => [ $this, 'render_form_block' ],
				'attributes'      => [
					'formId' => [
						'type'    => 'string',
						'default' => '',
					],
					'width'  => [
						'type'    => 'string',
						'default' => '100%',
					],
					'height' => [
						'type'    => 'string',
						'default' => 'auto',
					],
				],
			]
		);

		// Register Restricted Content Block
		register_block_type(
			'ghl-crm/restricted-content',
			[
				'render_callback' => [ $this, 'render_restricted_content_block' ],
				'attributes'      => [
					'rule'                => [
						'type'    => 'string',
						'default' => 'any',
					],
					'tags'                => [
						'type'    => 'array',
						'default' => [],
					],
					'fallbackContent'     => [
						'type'    => 'string',
						'default' => '',
					],
					'showMessage'         => [
						'type'    => 'boolean',
						'default' => true,
					],
					'fallbackBgColor'     => [
						'type'    => 'string',
						'default' => '#fff3cd',
					],
					'fallbackTextColor'   => [
						'type'    => 'string',
						'default' => '#856404',
					],
					'fallbackBorderColor' => [
						'type'    => 'string',
						'default' => '#ffc107',
					],
					'fallbackPadding'     => [
						'type'    => 'number',
						'default' => 12,
					],
				],
			]
		);
	}

	/**
	 * Add custom block category
	 *
	 * @param array    $categories Array of block categories.
	 * @param \WP_Post $post       Post object.
	 * @return array Modified categories
	 */
	public function add_block_category( array $categories, $post ): array {
		return array_merge(
			[
				[
					'slug'  => 'ghl-crm',
					'title' => __( 'GoHighLevel CRM', 'ghl-crm-integration' ),
					'icon'  => 'admin-links',
				],
			],
			$categories
		);
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		// Only enqueue if blocks are present on the page
		if ( has_block( 'ghl-crm/form' ) || has_block( 'ghl-crm/restricted-content' ) ) {
			AssetsManager::get_instance()->enqueue_public_asset( 'ghl-crm-blocks' );
		}
	}

	/**
	 * Render GHL Form Block
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string Rendered block HTML
	 */
	public function render_form_block( array $attributes, string $content = '' ): string {
		$form_id = $attributes['formId'] ?? '';
		$width   = $attributes['width'] ?? '100%';
		$height  = $attributes['height'] ?? 'auto';

		if ( empty( $form_id ) ) {
			return '';
		}

		// Use ShortcodeManager to render the form
		$shortcode_manager = \GHL_CRM\Frontend\ShortcodeManager::get_instance();
		return $shortcode_manager->render_form_shortcode(
			[
				'id'     => $form_id,
				'width'  => $width,
				'height' => $height,
			]
		);
	}

	/**
	 * Render Restricted Content Block
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block inner content.
	 * @return string Rendered block HTML
	 */
	public function render_restricted_content_block( array $attributes, string $content = '' ): string {
		$rule            = $attributes['rule'] ?? 'any';
		$tags            = $attributes['tags'] ?? [];
		$fallback        = $attributes['fallbackContent'] ?? '';
		$show_message    = $attributes['showMessage'] ?? true;
		$bg_color        = $attributes['fallbackBgColor'] ?? '#fff3cd';
		$text_color      = $attributes['fallbackTextColor'] ?? '#856404';
		$border_color    = $attributes['fallbackBorderColor'] ?? '#ffc107';
		$padding         = isset( $attributes['fallbackPadding'] ) ? (int) $attributes['fallbackPadding'] : 12;
		$condition_logic = $attributes['conditionLogic'] ?? 'and';
		$tag_conditions  = $attributes['tagConditions'] ?? [];

		$bg_color     = ( is_string( $bg_color ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $bg_color ) ) ? $bg_color : '#fff3cd';
		$text_color   = ( is_string( $text_color ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $text_color ) ) ? $text_color : '#856404';
		$border_color = ( is_string( $border_color ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $border_color ) ) ? $border_color : '#ffc107';
		$padding      = max( 0, min( 200, $padding ) );

		// Check if user has required access (compound-aware)
		if ( $this->check_compound_access( $rule, $tags, $condition_logic, $tag_conditions ) ) {
			return '<div class="ghl-restricted-content ghl-access-granted">' . $content . '</div>';
		}

		// User doesn't have access
		if ( $show_message && ! empty( $fallback ) ) {
			$style = sprintf(
				'padding:%dpx; background:%s; border:1px solid %s; color:%s; border-radius:4px;',
				$padding,
				$bg_color,
				$border_color,
				$text_color
			);

			return '<div class="ghl-restricted-content ghl-access-denied" style="' . esc_attr( $style ) . '">' . wp_kses_post( wpautop( $fallback ) ) . '</div>';
		}

		return '';
	}

	/**
	 * Check compound access — evaluates primary rule + additional condition groups
	 *
	 * @param string $rule            Primary rule (any, all, none).
	 * @param array  $tags            Primary tags.
	 * @param string $condition_logic Logic between groups (and, or).
	 * @param array  $tag_conditions  Array of condition groups [{matchType, tags}].
	 * @return bool True if user has access.
	 */
	private function check_compound_access( string $rule, array $tags, string $condition_logic, array $tag_conditions ): bool {
		// Evaluate primary condition
		$primary_result = $this->check_content_access( $rule, $tags );

		// If no additional conditions, return primary result
		if ( empty( $tag_conditions ) || ! is_array( $tag_conditions ) ) {
			return $primary_result;
		}

		// Evaluate each additional condition group
		$results = [ $primary_result ];

		foreach ( $tag_conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				continue;
			}

			$match_type     = $condition['matchType'] ?? 'any';
			$condition_tags = $condition['tags'] ?? [];

			if ( empty( $condition_tags ) || ! is_array( $condition_tags ) ) {
				continue; // Skip empty groups
			}

			$results[] = $this->check_content_access( $match_type, $condition_tags );
		}

		// Combine results
		if ( 'or' === $condition_logic ) {
			return in_array( true, $results, true );
		}

		// AND — all must pass
		return ! in_array( false, $results, true );
	}

	/**
	 * Check if user has access based on tag rules
	 *
	 * @param string $rule Rule type (any, all, none).
	 * @param array  $tags Array of tag IDs.
	 * @return bool True if user has access
	 */
	private function check_content_access( string $rule, array $tags ): bool {
		// If no tags specified, allow access
		if ( empty( $tags ) ) {
			return true;
		}

		// Get user tags using AccessControl
		$access_control = \GHL_CRM\Membership\AccessControl::get_instance();
		$user_id        = get_current_user_id();

		// Guest users
		if ( ! $user_id ) {
			return 'none' === $rule;
		}

		$user_tags = $access_control->get_user_tags( $user_id );

		// Apply rule
		switch ( $rule ) {
			case 'any':
				// User needs at least one of the tags
				return ! empty( array_intersect( $tags, $user_tags ) );

			case 'all':
				// User needs all tags
				return count( array_intersect( $tags, $user_tags ) ) === count( $tags );

			case 'none':
				// User must not have any of these tags
				return empty( array_intersect( $tags, $user_tags ) );

			default:
				return false;
		}
	}
}
