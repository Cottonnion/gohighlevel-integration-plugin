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
		add_shortcode( 'ghl_family_manager', array( $this, 'render_family_manager_shortcode' ) );
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

	/**
	 * Render family manager shortcode
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string HTML output
	 */
	public function render_family_manager_shortcode( $atts ): string {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to manage family accounts.', 'ghl-crm-integration' ) . '</p>';
		}

		// Check if feature is enabled
		$settings_manager = SettingsManager::get_instance();
		if ( empty( $settings_manager->get_setting( 'enable_family_accounts' ) ) ) {
			return '<p>' . esc_html__( 'Family accounts feature is not enabled.', 'ghl-crm-integration' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$family_repo = \GHL_CRM\Database\FamilyRelationshipsRepository::get_instance();
		
		// Check if user is parent or admin
		$is_admin = current_user_can( 'manage_options' );
		$is_parent = $family_repo->is_parent( $user_id );
		
		$has_parent_tag = false;
		$parent_tag_id  = $settings_manager->get_setting( 'family_parent_tag' );
		if ( ! empty( $parent_tag_id ) ) {
			$tag_manager      = \GHL_CRM\Core\TagManager::get_instance();
			$parent_map       = $tag_manager->map_ids_to_names( [ (string) $parent_tag_id ] );
			$parent_tag_name  = $parent_map[ (string) $parent_tag_id ] ?? '';
			$user_tag_names   = $tag_manager->get_user_tag_names( $user_id );

			if ( '' !== $parent_tag_name && in_array( $parent_tag_name, $user_tag_names, true ) ) {
				$has_parent_tag = true;
			}
		}

		// if ( ! $is_admin && ! $is_parent && ! $has_parent_tag ) {
		// 	return '<p>' . esc_html__( 'You do not have permission to manage family accounts.', 'ghl-crm-integration' ) . '</p>';
		// }

		// Enqueue SweetAlert2 for better UX
		wp_enqueue_style( 'sweetalert2' );
		wp_enqueue_script( 'sweetalert2' );

		// Enqueue styles and scripts
		wp_enqueue_style( 'ghl-family-manager-css' );
		wp_enqueue_script( 'ghl-family-manager' );

		// Localize script data for AJAX, configuration, and i18n
		wp_localize_script(
			'ghl-family-manager',
			'ghlFamilyManagerConfig',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'ghl_crm_nonce' ),
				'currentParentId' => $user_id,
				'isAdmin'         => $is_admin,
				'i18n'            => array(
					// General
					'error'     => __( 'Error', 'ghl-crm-integration' ),
					'success'   => __( 'Success!', 'ghl-crm-integration' ),
					'ok'        => __( 'OK', 'ghl-crm-integration' ),
					'cancel'    => __( 'Cancel', 'ghl-crm-integration' ),
					'ajaxError' => __( 'An error occurred. Please try again.', 'ghl-crm-integration' ),

					// Children list
					'noChildren' => __( 'No children linked yet.', 'ghl-crm-integration' ),
					'unlink'     => __( 'Unlink', 'ghl-crm-integration' ),

					// Search & Invite
					'searching'          => __( 'Searching...', 'ghl-crm-integration' ),
					'searchingText'      => __( 'Looking up user information', 'ghl-crm-integration' ),
					'emptyIdentifier'    => __( 'Please enter an email address.', 'ghl-crm-integration' ),
					'userNotFound'       => __( 'User Not Found', 'ghl-crm-integration' ),
					'userNotFoundText'   => __( 'This user does not exist. Would you like to create an account and send them an invitation?', 'ghl-crm-integration' ),
					'createAndInvite'    => __( 'Create & Send Invite', 'ghl-crm-integration' ),
					'userAvailable'      => __( 'User Found', 'ghl-crm-integration' ),
					'userAvailableText'  => __( 'This user exists. Would you like to link them to your account and send an invitation?', 'ghl-crm-integration' ),
					'sendInvite'         => __( 'Send Invite', 'ghl-crm-integration' ),
					'alreadyLinked'      => __( 'Already Linked', 'ghl-crm-integration' ),
					'alreadyLinkedText'  => __( 'This user is already linked as your child.', 'ghl-crm-integration' ),
					'hasParent'          => __( 'Cannot Link', 'ghl-crm-integration' ),
					'hasParentText'      => __( 'This user is already linked to another parent account.', 'ghl-crm-integration' ),
					'invalidEmail'       => __( 'Invalid Email', 'ghl-crm-integration' ),
					'invalidEmailText'   => __( 'Please enter a valid email address.', 'ghl-crm-integration' ),
					'sendingInvite'      => __( 'Sending Invite...', 'ghl-crm-integration' ),
					'sendingInviteText'  => __( 'Creating account and sending invitation email', 'ghl-crm-integration' ),
					'inviteSent'         => __( 'Invite Sent!', 'ghl-crm-integration' ),
					'inviteSentMessage'  => __( 'The invitation has been sent via email with login credentials.', 'ghl-crm-integration' ),

					// Unlink
					'confirmUnlinkTitle' => __( 'Unlink Child?', 'ghl-crm-integration' ),
					'confirmUnlink'      => __( 'Are you sure you want to unlink this child? They will lose access to inherited permissions.', 'ghl-crm-integration' ),
					'yesUnlink'          => __( 'Yes, Unlink', 'ghl-crm-integration' ),
					'unlinking'          => __( 'Unlinking...', 'ghl-crm-integration' ),
					'childUnlinked'      => __( 'Child unlinked successfully', 'ghl-crm-integration' ),
				),
			)
		);

		// Load template
		ob_start();
		include GHL_CRM_PATH . 'templates/shortcodes/family-manager.php';
		return ob_get_clean();
	}
}
