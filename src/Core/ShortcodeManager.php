<?php
declare(strict_types=1);

namespace GHL_CRM\Core;

use GHL_CRM\API\Resources\FormsResource;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode Manager
 *
 * Handles registration and rendering of plugin shortcodes
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/Core
 */
class ShortcodeManager {
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
		$this->init();
	}

	/**
	 * Initialize shortcodes
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'ghl_form', array( $this, 'render_form_shortcode' ) );
	}

	/**
	 * Render GHL form shortcode
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string HTML output
	 */
	public function render_form_shortcode( $atts ): string {
		// Parse attributes
		$atts = shortcode_atts(
			array(
				'id'     => '',
				'width'  => '100%',
				'height' => 'auto',
			),
			$atts,
			'ghl_form'
		);

		// Validate form ID
		if ( empty( $atts['id'] ) ) {
			return $this->render_error( __( 'Form ID is required. Use: [ghl_form id="your-form-id"]', 'ghl-crm-integration' ) );
		}

		// Get form embed URL
		$embed_url = $this->get_form_embed_url( $atts['id'] );

		if ( ! $embed_url ) {
			return $this->render_error( __( 'Unable to load form. Please check your GoHighLevel connection.', 'ghl-crm-integration' ) );
		}

		// Sanitize dimensions
		$width  = $this->sanitize_dimension( $atts['width'] );
		$height = $atts['height'] === 'auto' ? 'auto' : $this->sanitize_dimension( $atts['height'] );

		// Generate unique ID for this form instance
		$wrapper_id = 'ghl-form-' . sanitize_key( $atts['id'] ) . '-' . wp_rand( 1000, 9999 );

		// Build iframe HTML with loading state
		ob_start();
		?>
		<div id="<?php echo esc_attr( $wrapper_id ); ?>" class="ghl-form-wrapper" style="width: <?php echo esc_attr( $width ); ?>; max-width: 100%;" data-loading="true">
			<div class="ghl-form-loading">
				<div class="ghl-form-spinner"></div>
				<p><?php esc_html_e( 'Loading form...', 'ghl-crm-integration' ); ?></p>
			</div>
			<iframe 
				src="<?php echo esc_url( $embed_url ); ?>" 
				style="width: 100%; <?php echo $height === 'auto' ? 'height: 800px;' : 'height: ' . esc_attr( $height ) . ';'; ?> border: none; display: none;"
				scrolling="yes"
				id="<?php echo esc_attr( $wrapper_id ); ?>-iframe"
				title="<?php
				/* translators: %s: GoHighLevel form ID */
				echo esc_attr( sprintf( __( 'GoHighLevel Form %s', 'ghl-crm-integration' ), $atts['id'] ) );
				?>"
				onload="this.style.display='block'; this.parentElement.setAttribute('data-loading', 'false');"
			></iframe>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get form embed URL
	 *
	 * @param string $form_id Form ID.
	 * @return string|false Embed URL or false on failure
	 */
	private function get_form_embed_url( string $form_id ) {
		// Check connection
		$connection_manager = \GHL_CRM\API\ConnectionManager::get_instance();
		if ( ! $connection_manager->is_connection_verified() ) {
			return false;
		}

		try {
			// Get white-label domain from settings
			$settings_manager   = SettingsManager::get_instance();
			$white_label_domain = $settings_manager->get_setting( 'ghl_white_label_domain', '' );

			// Build base URL (use white-label domain or default)
			if ( ! empty( $white_label_domain ) ) {
				// Parse the white-label domain and convert to link subdomain
				$parsed = wp_parse_url( $white_label_domain );
				if ( ! empty( $parsed['host'] ) ) {
					$host_parts = explode( '.', $parsed['host'] );
					if ( count( $host_parts ) >= 2 ) {
						// Replace first subdomain with "link"
						$host_parts[0] = 'link';
						$base_url      = 'https://' . implode( '.', $host_parts );
					} else {
						$base_url = 'https://link.leadconnectorhq.com';
					}
				} else {
					$base_url = 'https://link.leadconnectorhq.com';
				}
			} else {
				$base_url = 'https://link.leadconnectorhq.com';
			}

			// Build form URL
			$form_url = trailingslashit( $base_url ) . 'widget/form/' . sanitize_text_field( $form_id );

			return $form_url;

		} catch ( \Exception $e ) {

			return false;
		}
	}

	/**
	 * Sanitize dimension value (supports px, %, vh, vw)
	 *
	 * @param string $dimension Dimension value.
	 * @return string Sanitized dimension
	 */
	private function sanitize_dimension( string $dimension ): string {
		// Allow numeric values with px, %, vh, vw, em, rem
		$dimension = trim( $dimension );

		// If just a number, add px
		if ( is_numeric( $dimension ) ) {
			return $dimension . 'px';
		}

		// Validate against allowed units
		if ( preg_match( '/^(\d+(?:\.\d+)?)(px|%|vh|vw|em|rem)$/i', $dimension, $matches ) ) {
			return $matches[1] . strtolower( $matches[2] );
		}

		// Default fallback
		return '100%';
	}

	/**
	 * Render error message
	 *
	 * @param string $message Error message.
	 * @return string HTML error output
	 */
	private function render_error( string $message ): string {
		// Only show errors to logged-in users with appropriate capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return '<!-- GHL Form Error (hidden for non-admins) -->';
		}

		return sprintf(
			'<div class="ghl-form-error" style="padding: 20px; background: #fee; border: 1px solid #c33; border-radius: 4px; color: #c33;">
				<strong>%s:</strong> %s
			</div>',
			esc_html__( 'GoHighLevel Form Error', 'ghl-crm-integration' ),
			esc_html( $message )
		);
	}
}
