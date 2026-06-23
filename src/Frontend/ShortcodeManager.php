<?php
declare(strict_types=1);

namespace Syncly\Frontend;

use Syncly\Core\SettingsManager;
use Syncly\Integrations\Forms\FormSettings;
use Syncly\Sync\TagManager;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode Manager
 *
 * Handles registration and rendering of plugin shortcodes
 *
 * @package    Syncly
 * @subpackage Syncly/Core
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
			return $this->render_error( __( 'Form ID is required. Use: [ghl_form id="your-form-id"]', 'syncly' ) );
		}

		$form_id = $atts['id'];

		// Check if form is logged-in only
			$form_settings = FormSettings::get_instance();
		if ( $form_settings->is_logged_only( $form_id ) && ! is_user_logged_in() ) {
			// Return empty string - don't show form to non-logged-in users
			return '';
		}

		// Allow extensions to hide forms for their own rules.
		if ( apply_filters( 'syncly_form_should_hide', false, $form_id ) ) {
			$message = (string) apply_filters( 'syncly_form_submitted_message', '', $form_id );

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
			return $this->render_error( __( 'Unable to load form. Please check your GoHighLevel connection.', 'syncly' ) );
		}

		// Sanitize dimensions
		$width  = $this->sanitize_dimension( $atts['width'] );
		$height = 'auto' === $atts['height'] ? 'auto' : $this->sanitize_dimension( $atts['height'] );

		// Generate unique ID for this form instance
		$wrapper_id = 'ghl-form-' . sanitize_key( $form_id ) . '-' . wp_rand( 1000, 9999 );

		$track_submission = (bool) apply_filters( 'syncly_form_track_submission', false, $form_id );

		// Build iframe HTML
		ob_start();
		?>
		<div id="<?php echo esc_attr( $wrapper_id ); ?>" class="ghl-form-wrapper" style="width: <?php echo esc_attr( $width ); ?>; max-width: 100%;" data-loading="true" data-form-id="<?php echo esc_attr( $form_id ); ?>" data-track-submission="<?php echo $track_submission ? '1' : '0'; ?>">
			<div class="ghl-form-loading">
				<div class="ghl-form-spinner"></div>
				<p><?php esc_html_e( 'Loading form...', 'syncly' ); ?></p>
			</div>
			<iframe 
				src="<?php echo esc_url( $embed_url ); ?>" 
				style="width: 100%; <?php echo 'auto' === $height ? 'height: ' . esc_attr( (string) apply_filters( 'syncly_form_default_height', 800 ) ) . 'px;' : 'height: ' . esc_attr( $height ) . ';'; ?> border: none; display: none;"
				scrolling="yes"
				id="<?php echo esc_attr( $wrapper_id ); ?>-iframe"
				data-form-id="<?php echo esc_attr( $form_id ); ?>"
				<?php /* translators: %s: GoHighLevel form identifier. */ ?>
				title="<?php echo esc_attr( sprintf( __( 'GoHighLevel Form %s', 'syncly' ), $atts['id'] ) ); ?>"
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
		$connection_manager = \Syncly\API\ConnectionManager::get_instance();
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

					// Strip all subdomains down to the apex (last 2 parts) then prepend 'link'.
					// e.g. crm.sub.domain.com → link.domain.com
					//      crm.domain.com      → link.domain.com
					//      link.domain.com     → link.domain.com
					if ( count( $host_parts ) >= 2 ) {
						$apex     = array_slice( $host_parts, -2 );
						$base_url = 'https://link.' . implode( '.', $apex );
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
			esc_html__( 'GoHighLevel Form Error', 'syncly' ),
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
		$output = apply_filters( 'syncly_render_family_manager_shortcode', '', $atts );
		if ( is_string( $output ) && '' !== $output ) {
			return $output;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return '';
	}

	/**
	 * Render unavailable notice for family manager shortcode
	 *
	 * @return string HTML output
	 */
	private function render_family_manager_upgrade_notice(): string {
		return '<p>' . esc_html__( 'Family account management is not available in this plugin.', 'syncly' ) . '</p>';
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
			/**
			 * Fires when a non-logged-in user hits a restricted block.
			 * Hook here to redirect or show a login prompt.
			 *
			 * @param array  $atts    Shortcode attributes.
			 * @param string $content Shortcode inner content.
			 */
			do_action( 'syncly_restrict_guest_access', $atts, $content );
			return apply_filters( 'syncly_restrict_guest_output', '', $atts, $content );
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
			return wp_kses_post( do_shortcode( $content ) );
		}

		// Parse tags (comma-separated)
		$required_tags = array_map( 'trim', explode( ',', $atts['tags'] ) );
		$required_tags = array_filter( $required_tags );

		if ( empty( $required_tags ) ) {
			return wp_kses_post( do_shortcode( $content ) );
		}

		// Get user's tags
		$user_id        = get_current_user_id();
		$access_control = \Syncly\Membership\AccessControl::get_instance();
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
			return wp_kses_post( do_shortcode( $content ) );
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
		$is_logged_in     = is_user_logged_in();
		$guest_contact_id = null;

		if ( ! $is_logged_in ) {
			// Allow guest personalization via ?ghl_cid= if the feature is enabled.
			$settings_manager = \Syncly\Core\SettingsManager::get_instance();
			if ( ! empty( $settings_manager->get_setting( 'enable_ghl_cid' ) ) ) {
				$guest_contact_id = ContactIdHandler::get_guest_contact_id();
			}

			if ( null === $guest_contact_id ) {
				return '';
			}
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

		$field      = sanitize_text_field( (string) $atts['field'] );
		$meta_field = sanitize_key( $field );

		if ( empty( $field ) ) {
			return '';
		}

		// --- Guest visitor path ---
		if ( null !== $guest_contact_id ) {
			// Check if field is in hidden list for guests
			$settings_manager   = \Syncly\Core\SettingsManager::get_instance();
			$hidden_fields_json = $settings_manager->get_setting( 'ghl_cid_hidden_fields', '' );
			$hidden_fields      = ! empty( $hidden_fields_json ) ? (array) json_decode( $hidden_fields_json, true ) : array();

			// If field is in hidden list, deny access
			if ( in_array( $meta_field, $hidden_fields, true ) ) {
				return esc_html( $atts['default'] );
			}

			$value = '';
			// Prefer reading WP user meta directly if this contact has a WP account.
			$tag_manager   = \Syncly\Sync\TagManager::get_instance();
			$guest_user_id = $tag_manager->find_user_by_contact_id( $guest_contact_id );

			if ( $guest_user_id ) {
				$value = get_user_meta( $guest_user_id, $meta_field, true );
				if ( empty( $value ) ) {
					$user_obj = get_userdata( $guest_user_id );
					if ( $user_obj && isset( $user_obj->$meta_field ) ) {
						$value = $user_obj->$meta_field;
					}
				}
			}

			if ( empty( $value ) ) {
				return esc_html( $atts['default'] );
			}
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			}
			return esc_html( (string) $value );
		}

		// --- Logged-in user path ---
		$user_id = get_current_user_id();
		$value   = get_user_meta( $user_id, $meta_field, true );

		// Fallback: check core WP user object fields (user_url, user_email, display_name, etc.)
		if ( empty( $value ) ) {
			$user_obj = get_userdata( $user_id );
			if ( $user_obj && isset( $user_obj->$meta_field ) ) {
				$value = $user_obj->$meta_field;
			}
		}

		// Pull from GHL once if empty and sync_if_empty="true"
		if ( empty( $value ) && 'true' === strtolower( $atts['sync_if_empty'] ) ) {
			$value = $this->maybe_pull_field_from_ghl( $user_id, $meta_field );
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
	 * Resolve a field value from guest contact payload.
	 *
	 * @param array  $contact_data Guest contact payload.
	 * @param string $field        Original field name from shortcode.
	 * @param string $meta_field   Sanitized field name.
	 * @return mixed
	 */
	private function resolve_guest_contact_field_value( array $contact_data, string $field, string $meta_field ) {
		if ( isset( $contact_data[ $field ] ) ) {
			return $contact_data[ $field ];
		}

		if ( '' !== $meta_field && isset( $contact_data[ $meta_field ] ) ) {
			return $contact_data[ $meta_field ];
		}

		if ( ! empty( $contact_data['customFields'] ) && is_array( $contact_data['customFields'] ) ) {
			foreach ( $contact_data['customFields'] as $custom_field ) {
				if ( ! is_array( $custom_field ) ) {
					continue;
				}

				$custom_id  = isset( $custom_field['id'] ) ? (string) $custom_field['id'] : '';
				$custom_key = isset( $custom_field['key'] ) ? (string) $custom_field['key'] : '';

				if ( $custom_id === $field || $custom_id === $meta_field || $custom_key === $field || $custom_key === $meta_field ) {
					return $custom_field['value'] ?? '';
				}
			}
		}

		return '';
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
		$transient_key = 'ghl_pull_' . $user_id . '_' . $field;
		if ( get_transient( $transient_key ) ) {
			return '';
		}

		try {
			$tag_manager    = \Syncly\Sync\TagManager::get_instance();
			$contact_id_key = $tag_manager->get_user_contact_id_meta_key();
			$contact_id     = get_user_meta( $user_id, $contact_id_key, true );

			if ( empty( $contact_id ) ) {
				return '';
			}

			// Use existing sync infrastructure — handles API response unwrapping and full field mapping.
			$sync   = \Syncly\Sync\GHLToWordPressSync::get_instance();
			$result = $sync->sync_contact_to_wordpress( $contact_id );

			if ( is_wp_error( $result ) ) {
				return '';
			}

			// Throttle further syncs for this user (default: 1 hour).
			set_transient( $transient_key, 1, (int) apply_filters( 'syncly_sync_throttle_ttl', HOUR_IN_SECONDS ) );

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
