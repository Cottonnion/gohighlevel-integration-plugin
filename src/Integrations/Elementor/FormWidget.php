<?php
declare(strict_types=1);

namespace Syncly\Integrations\Elementor;

use Syncly\API\Resources\FormsResource;
use Syncly\API\Client\Client;
use Syncly\Frontend\ShortcodeManager;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor Form Widget
 *
 * Provides an Elementor widget for embedding GoHighLevel forms
 *
 * @package    Syncly
 * @subpackage Integrations/Elementor
 */
class FormWidget extends \Elementor\Widget_Base {

	/**
	 * Get widget name
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'syncly_form';
	}

	/**
	 * Get widget title
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'GoHighLevel Form', 'syncly' );
	}

	/**
	 * Get widget icon
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-form-horizontal';
	}

	/**
	 * Get widget categories
	 *
	 * @return array
	 */
	public function get_categories(): array {
		return [ 'syncly', 'general' ];
	}

	/**
	 * Get widget keywords
	 *
	 * @return array
	 */
	public function get_keywords(): array {
		return [ 'form', 'gohighlevel', 'ghl', 'contact', 'crm' ];
	}

	/**
	 * Whether the reload preview is required or not
	 *
	 * @return bool
	 */
	public function is_reload_preview_required(): bool {
		return true;
	}

	/**
	 * Register widget controls
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		// Content Section
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Form Settings', 'syncly' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		// Info box about backend settings
		$this->add_control(
			'settings_notice',
			[
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					'<div style="padding: 15px; background: #e8f4fd; border-left: 4px solid #2196F3; margin-bottom: 15px;">
						<p style="margin: 0; font-size: 13px; line-height: 1.6; color: #000; font-style: normal;">
							<strong style="display: block; margin-bottom: 5px; color: #000; font-style: normal;">%s</strong>
							<span style="color: #000; font-style: normal;">%s</span>
						</p>
					</div>',
					__( 'Form Configuration', 'syncly' ),
					sprintf(
						/* translators: %s: Link to forms settings page */
						__( 'Advanced form settings (submission limits, logged-in restrictions, custom messages) are managed from the <a href="%s" target="_blank" style="color: #2196F3; font-style: normal;">Forms Settings</a> page in the plugin dashboard.', 'syncly' ),
						admin_url( 'admin.php?page=syncly-admin#forms' )
					)
				),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			]
		);

		// Form selection dropdown
		$this->add_control(
			'form_id',
			[
				'label'       => __( 'Select Form', 'syncly' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $this->get_available_forms(),
				'default'     => '',
				'label_block' => true,
				'description' => __( 'Choose a GoHighLevel form to display. Forms are synced from your GHL account.', 'syncly' ),
			]
		);

		$this->end_controls_section();

		// Style Section
		$this->start_controls_section(
			'style_section',
			[
				'label' => __( 'Form Style', 'syncly' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'form_width',
			[
				'label'      => __( 'Width', 'syncly' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range'      => [
					'px' => [
						'min' => 200,
						'max' => 1200,
					],
					'%'  => [
						'min' => 10,
						'max' => 100,
					],
				],
				'default'    => [
					'unit' => '%',
					'size' => 100,
				],
				'selectors'  => [
					'{{WRAPPER}} .ghl-form-wrapper' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'form_height',
			[
				'label'       => __( 'Minimum Height', 'syncly' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [
					'px' => [
						'min' => 300,
						'max' => 1200,
					],
				],
				'default'     => [
					'unit' => 'px',
					'size' => 500,
				],
				'selectors'   => [
					'{{WRAPPER}} .ghl-form-wrapper' => 'min-height: {{SIZE}}{{UNIT}};',
				],
				'description' => __( 'Set a minimum height for the form. The form will expand if content is larger.', 'syncly' ),
			]
		);

		$this->add_responsive_control(
			'form_margin',
			[
				'label'      => __( 'Margin', 'syncly' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .ghl-form-wrapper' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'form_padding',
			[
				'label'      => __( 'Padding', 'syncly' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .ghl-form-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Get available forms from GoHighLevel
	 *
	 * @return array
	 */
	private function get_available_forms(): array {
		$options = [
			'' => __( '— Select a Form —', 'syncly' ),
		];

		try {
			// Check if connected
			$settings_manager = \Syncly\Core\SettingsManager::get_instance();
			if ( ! $settings_manager->is_connection_verified() ) {
				return [
					'' => __( '— Not Connected to GoHighLevel —', 'syncly' ),
				];
			}

			// Get forms
			$client         = Client::get_instance();
			$forms_resource = new FormsResource( $client );
			$forms          = $forms_resource->get_forms();

			if ( ! empty( $forms ) && is_array( $forms ) ) {
				foreach ( $forms as $form ) {
					if ( ! empty( $form['id'] ) && ! empty( $form['name'] ) ) {
						$options[ $form['id'] ] = $form['name'];
					}
				}
			}

			if ( count( $options ) === 1 ) {
				return [
					'' => __( '— No Forms Found —', 'syncly' ),
				];
			}
		} catch ( \Exception $e ) {
			return [
				'' => __( '— Error Loading Forms —', 'syncly' ),
			];
		}

		return $options;
	}

	/**
	 * Render widget output on the frontend
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$form_id  = $settings['form_id'] ?? '';

		if ( empty( $form_id ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 40px; text-align: center; background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 4px;">';
				echo '<p style="margin: 0; color: #6c757d; font-size: 14px;">';
				echo '<strong>' . esc_html__( 'GoHighLevel Form Widget', 'syncly' ) . '</strong><br>';
				echo esc_html__( 'Please select a form from the widget settings.', 'syncly' );
				echo '</p>';
				echo '</div>';
			}
			return;
		}

		// Use the existing shortcode renderer
		$shortcode_manager = ShortcodeManager::get_instance();
		$output            = $shortcode_manager->render_form_shortcode(
			[
				'id'     => $form_id,
				'width'  => '100%',
				'height' => 'auto',
			]
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_kses( $output, $this->get_allowed_form_html() );
	}

	/**
	 * Get allowed HTML for the rendered form embed.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private function get_allowed_form_html(): array {
		return [
			'div'    => [
				'id'                    => true,
				'class'                 => true,
				'style'                 => true,
				'data-loading'          => true,
				'data-form-id'          => true,
				'data-track-submission' => true,
			],
			'p'      => [
				'class' => true,
				'style' => true,
			],
			'strong' => [],
			'iframe' => [
				'src'          => true,
				'style'        => true,
				'scrolling'    => true,
				'id'           => true,
				'data-form-id' => true,
				'title'        => true,
				'onload'       => true,
			],
		];
	}

	/**
	 * Get script dependencies
	 *
	 * @return array
	 */
	public function get_script_depends(): array {
		return [];
	}

	/**
	 * Get style dependencies
	 *
	 * @return array
	 */
	public function get_style_depends(): array {
		return [];
	}
}
