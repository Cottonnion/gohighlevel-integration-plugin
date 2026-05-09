<?php
declare(strict_types=1);

namespace GHL_CRM\Frontend;

use GHL_CRM\API\Resources\FormsResource;
use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Integrations\Forms\FormSettings;
use GHL_CRM\Sync\TagManager;

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
		add_shortcode( 'ghl_restrict', array( $this, 'render_restrict_shortcode' ) );
		add_shortcode( 'ghl_user_meta', array( $this, 'render_user_meta_shortcode' ) );
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

		$form_id = $atts['id'];

		// Check if form is logged-in only
			$form_settings = FormSettings::get_instance();
		if ( $form_settings->is_logged_only( $form_id ) && ! is_user_logged_in() ) {
			// Return empty string - don't show form to non-logged-in users
			return '';
		}

		// Check if form should be hidden due to submission limit
		if ( $form_settings->should_hide_form( $form_id ) ) {
			$settings = $form_settings->get_form_settings( $form_id );
			$message  = ! empty( $settings['submitted_message'] ) ? $settings['submitted_message'] : '';

			if ( ! empty( $message ) ) {
				return '<div class="ghl-form-submitted-message" style="padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; text-align: center;">' .
				wp_kses_post( wpautop( $message ) ) .
				'</div>';
			}

			// Empty message = hide completely
			return '';
		}

		// Get form embed URL
		$embed_url = $this->get_form_embed_url( $form_id );     if ( ! $embed_url ) {
			return $this->render_error( __( 'Unable to load form. Please check your GoHighLevel connection.', 'ghl-crm-integration' ) );
		}

		// Sanitize dimensions
		$width  = $this->sanitize_dimension( $atts['width'] );
		$height = 'auto' === $atts['height'] ? 'auto' : $this->sanitize_dimension( $atts['height'] );

		// Generate unique ID for this form instance
		$wrapper_id = 'ghl-form-' . sanitize_key( $form_id ) . '-' . wp_rand( 1000, 9999 );

		// Check if tracking is needed
		$settings         = $form_settings->get_form_settings( $form_id );
		$track_submission = ( 'once' === $settings['submission_limit'] && is_user_logged_in() );

		// Build iframe HTML
		ob_start();
		?>
		<div id="<?php echo esc_attr( $wrapper_id ); ?>" class="ghl-form-wrapper" style="width: <?php echo esc_attr( $width ); ?>; max-width: 100%;" data-loading="true" data-form-id="<?php echo esc_attr( $form_id ); ?>" data-track-submission="<?php echo $track_submission ? '1' : '0'; ?>">
			<div class="ghl-form-loading">
				<div class="ghl-form-spinner"></div>
				<p><?php esc_html_e( 'Loading form...', 'ghl-crm-integration' ); ?></p>
			</div>
			<iframe 
				src="<?php echo esc_url( $embed_url ); ?>" 
				style="width: 100%; <?php echo 'auto' === $height ? 'height: 800px;' : 'height: ' . esc_attr( $height ) . ';'; ?> border: none; display: none;"
				scrolling="yes"
				id="<?php echo esc_attr( $wrapper_id ); ?>-iframe"
				data-form-id="<?php echo esc_attr( $form_id ); ?>"
				title="<?php echo esc_attr( sprintf( __( 'GoHighLevel Form %s', 'ghl-crm-integration' ), $atts['id'] ) ); ?>"
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

					// Check if already using link subdomain
					if ( isset( $host_parts[0] ) && 'link' === strtolower( $host_parts[0] ) ) {
						// Already correct
						$base_url = 'https://' . implode( '.', $host_parts );
					} elseif ( count( $host_parts ) >= 3 ) {
						// Has subdomain (e.g., crm.jorgediaz.online) - replace it
						$host_parts[0] = 'link';
						$base_url      = 'https://' . implode( '.', $host_parts );
					} elseif ( count( $host_parts ) === 2 ) {
						// No subdomain (e.g., jorgediaz.online) - prepend link
						array_unshift( $host_parts, 'link' );
						$base_url = 'https://' . implode( '.', $host_parts );
					} else {
						$base_url = 'https://link.gohighlevel.com';
					}
				} else {
					$base_url = 'https://link.gohighlevel.com';
				}
			} else {
				$base_url = 'https://link.gohighlevel.com';
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
		// Check if PRO plugin is active and has FamilyManager
		if ( class_exists( '\\GHL_CRM_Pro\\FamilyManager' ) ) {
			// Delegate to PRO plugin's implementation
			return $this->render_family_manager_pro( $atts );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		// PRO plugin not active - show upgrade notice
		return $this->render_family_manager_upgrade_notice();
	}

	/**
	 * Render family manager from PRO plugin
	 *
	 * @param array|string $atts Shortcode attributes (unused, reserved for future).
	 * @return string HTML output
	 */
	private function render_family_manager_pro( $atts ): string {
		// Suppressing unused parameter warning - reserved for future use
		unset( $atts );

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to manage family accounts.', 'ghl-crm-integration' ) . '</p>';
		}

		// Check if feature is enabled
		$settings_manager = SettingsManager::get_instance();
		if ( empty( $settings_manager->get_setting( 'enable_family_accounts' ) ) && current_user_can( 'manage_options' ) ) {
			return '<p>' . esc_html__( 'Family accounts feature is not enabled - This is only shown to you as an administrator.', 'ghl-crm-integration' ) . '</p>';
		}

		$user_id = get_current_user_id();

		// Use PRO plugin's repository
		$family_repo = \GHL_CRM_Pro\Database\FamilyRelationshipsRepository::get_instance();

		// Check if user is parent or admin
		$is_admin  = current_user_can( 'manage_options' );
		$is_parent = $family_repo->is_parent( $user_id );

		$has_parent_tag = false;
		$parent_tag_id  = $settings_manager->get_setting( 'family_parent_tag' );
		if ( ! empty( $parent_tag_id ) ) {
					$tag_manager = TagManager::get_instance();
			$parent_map          = $tag_manager->map_ids_to_names( [ (string) $parent_tag_id ] );
			$parent_tag_name     = $parent_map[ (string) $parent_tag_id ] ?? '';
			$user_tag_names      = $tag_manager->get_user_tag_names( $user_id );

			if ( '' !== $parent_tag_name && in_array( $parent_tag_name, $user_tag_names, true ) ) {
				$has_parent_tag = true;
			}
		}

		// Load template from PRO plugin
		$pro_template = defined( 'GHL_CRM_PRO_PATH' ) ? GHL_CRM_PRO_PATH . 'pro/templates/shortcodes/family-manager.php' : '';

		if ( file_exists( $pro_template ) ) {
			ob_start();
			include $pro_template;
			return ob_get_clean();
		}

		return '<p>' . esc_html__( 'Family manager template not found.', 'ghl-crm-integration' ) . '</p>';
	}

	/**
	 * Render upgrade notice for family manager shortcode
	 *
	 * @return string HTML output
	 */
	private function render_family_manager_upgrade_notice(): string {
		ob_start();

		// Set up upgrade notice variables
		$title       = __( 'Family Accounts', 'ghl-crm-integration' );
		$description = __( 'Create parent-child relationships where children inherit membership access and tags from their parents. Manage invitations, family groups, and automatic BuddyBoss group creation.', 'ghl-crm-integration' );
		$features    = array(
			__( 'Parent-child account relationships with tag inheritance', 'ghl-crm-integration' ),
			__( 'Email invitation system with custom templates', 'ghl-crm-integration' ),
			__( 'Automatic BuddyBoss group creation for families', 'ghl-crm-integration' ),
			__( 'Frontend family manager dashboard via shortcode', 'ghl-crm-integration' ),
			__( 'Admin controls and family statistics', 'ghl-crm-integration' ),
		);
		$cta_text    = __( 'Upgrade to PRO', 'ghl-crm-integration' );
		$style       = 'box';

		include GHL_CRM_PATH . 'templates/admin/partials/pro-upgrade-notice.php';

		return ob_get_clean();
	}

	/**
	 * Render conditional content shortcode
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Shortcode content.
	 * @return string HTML output or empty string if access denied
	 */
	public function render_restrict_shortcode( $atts, $content = null ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		// Parse attributes
		$atts = shortcode_atts(
			array(
				'tags' => '',
				'type' => 'any', // any, all, none
			),
			$atts,
			'ghl_restrict'
		);

		// Validate tags
		if ( empty( $atts['tags'] ) ) {
			// No tags specified - show to all logged-in users
			return do_shortcode( $content );
		}

		// Parse tags (comma-separated)
		$required_tags = array_map( 'trim', explode( ',', $atts['tags'] ) );
		$required_tags = array_filter( $required_tags );

		if ( empty( $required_tags ) ) {
			return do_shortcode( $content );
		}

		// Get user's tags
		$user_id        = get_current_user_id();
		$access_control = \GHL_CRM\Membership\AccessControl::get_instance();
		$user_tags      = $access_control->get_user_tags( $user_id );

		// Normalize tags for comparison
		$required_tags_lower = array_map( 'strtolower', $required_tags );
		$user_tags_lower     = array_map( 'strtolower', $user_tags );

		// Check access based on type
		$has_access = false;

		switch ( strtolower( $atts['type'] ) ) {
			case 'all':
				// User must have ALL required tags
				$has_access = empty( array_diff( $required_tags_lower, $user_tags_lower ) );
				break;

			case 'none':
				// User must NOT have any of the tags
				$has_access = empty( array_intersect( $required_tags_lower, $user_tags_lower ) );
				break;

			case 'any':
			default:
				// User must have AT LEAST ONE tag
				$has_access = ! empty( array_intersect( $required_tags_lower, $user_tags_lower ) );
				break;
		}

		// Return content if access granted
		if ( $has_access ) {
			return do_shortcode( $content );
		}

		// Access denied - return empty string
		return '';
	}

	/**
	 * Render user meta shortcode — displays CRM field data for the current user.
	 *
	 * Usage:
	 *   [ghl_user_meta field="first_name"]
	 *   [ghl_user_meta field="exam_date" default="Not set"]
	 *   [ghl_user_meta field="advisor_name" sync_if_empty="true"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Field value, default, or empty string.
	 */
	public function render_user_meta_shortcode( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$atts = shortcode_atts(
			[
				'field'         => '',
				'default'       => '',
				'sync_if_empty' => 'false',
			],
			$atts,
			'ghl_user_meta'
		);

		$field = sanitize_key( $atts['field'] );

		if ( empty( $field ) ) {
			return '';
		}

		$user_id = get_current_user_id();
		$value   = get_user_meta( $user_id, $field, true );

		// Fallback: check core WP user object fields (user_url, user_email, display_name, etc.)
		if ( empty( $value ) ) {
			$user_obj = get_userdata( $user_id );
			if ( $user_obj && isset( $user_obj->$field ) ) {
				$value = $user_obj->$field;
			}
		}

		// Pull from GHL once if empty and sync_if_empty="true"
		if ( empty( $value ) && 'true' === strtolower( $atts['sync_if_empty'] ) ) {
			$value = $this->maybe_pull_field_from_ghl( $user_id, $field );
		}

		if ( empty( $value ) ) {
			return esc_html( $atts['default'] );
		}

		// Handle array values (e.g. multi-select fields)
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
		}

		return esc_html( (string) $value );
	}

	/**
	 * Pull a single field from GHL for a user and store it in user meta.
	 * Throttled to once per hour per user/field via transient.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $field   User meta key / GHL field key.
	 * @return string The fetched value or empty string on failure.
	 */
	private function maybe_pull_field_from_ghl( int $user_id, string $field ): string {
		$transient_key = 'ghl_pull_' . $user_id;
		if ( get_transient( $transient_key ) ) {
			return '';
		}

		try {
			$tag_manager    = \GHL_CRM\Sync\TagManager::get_instance();
			$contact_id_key = $tag_manager->get_user_contact_id_meta_key();
			$contact_id     = get_user_meta( $user_id, $contact_id_key, true );

			if ( empty( $contact_id ) ) {
				return '';
			}

			// Use existing sync infrastructure — handles API response unwrapping and full field mapping.
			$sync   = \GHL_CRM\Sync\GHLToWordPressSync::get_instance();
			$result = $sync->sync_contact_to_wordpress( $contact_id );

			if ( is_wp_error( $result ) ) {
				return '';
			}

			// Throttle further syncs for this user for 1 hour.
			set_transient( $transient_key, 1, HOUR_IN_SECONDS );

			// Re-read the requested field now that sync has populated user meta.
			$value = get_user_meta( $user_id, $field, true );
			if ( is_array( $value ) ) {
				return implode( ', ', array_map( 'sanitize_text_field', $value ) );
			}
			return sanitize_text_field( (string) $value );

		} catch ( \Exception $e ) {
			// Fail silently — don't break the page.
		}

		return '';
	}
}
