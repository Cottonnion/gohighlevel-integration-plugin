<?php
/**
 * LearnDash course-level settings meta box.
 *
 * @package GHL_CRM_Integration
 */

declare(strict_types=1);

namespace GHL_CRM\Integrations\LearnDash;

defined( 'ABSPATH' ) || exit;

/**
 * Adds per-course configuration for LearnDash automation hooks.
 */
class CourseMetaBox {
	private const AUTO_ENROLL_META_KEY = '_ghl_ld_auto_enroll_tag';
	private const COMPLETION_META_KEY  = '_ghl_ld_completed_tags';

	/**
	 * Initialize WordPress hooks.
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'boot' ] );
	}

	/**
	 * Register meta box hooks once LearnDash is available.
	 */
	public function boot(): void {
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {
			return;
		}

		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_course_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register the course automation meta box.
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'ghl-learndash-automation',
			__( 'GoHighLevel Automations', 'ghl-crm-integration' ),
			[ $this, 'render_meta_box' ],
			'sfwd-courses',
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box UI.
	 *
	 * @param \WP_Post $post Course post object.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'ghl_ld_course_settings', 'ghl_ld_course_nonce' );

		$auto_enroll_tags = $this->normalize_tag_meta( get_post_meta( $post->ID, self::AUTO_ENROLL_META_KEY, true ) );
		$completion_tags  = $this->normalize_tag_meta( get_post_meta( $post->ID, self::COMPLETION_META_KEY, true ) );
		?>
		<div class="ghl-ld-meta-box">
			<div class="ghl-ld-meta-card">
				<div class="ghl-ld-meta-card__header">
					<h4><?php esc_html_e( 'Auto-Enroll When Tag Exists', 'ghl-crm-integration' ); ?></h4>
					<p><?php esc_html_e( 'If a synced contact already has any of these tags, automatically enroll them into this course.', 'ghl-crm-integration' ); ?></p>
				</div>
				<select
					id="ghl_ld_auto_enroll_tag"
					name="ghl_ld_auto_enroll_tag[]"
					class="ghl-tags-select ghl-ld-tags-select"
					multiple
					data-context="auto-enroll"
					data-saved-tags='<?php echo wp_json_encode( $auto_enroll_tags ); ?>'
					data-placeholder="<?php esc_attr_e( 'Select tags that should auto-enroll a user…', 'ghl-crm-integration' ); ?>"
				>
					<option value=""><?php esc_html_e( 'Loading tags…', 'ghl-crm-integration' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Use this when you want WordPress to mirror GHL automation. For example, enrolling anyone tagged "Onboarding Cohort A".', 'ghl-crm-integration' ); ?>
				</p>
			</div>

			<div class="ghl-ld-meta-card">
				<div class="ghl-ld-meta-card__header">
					<h4><?php esc_html_e( 'Apply Tags When Course is Completed', 'ghl-crm-integration' ); ?></h4>
					<p><?php esc_html_e( 'Select the tags that should be pushed back to GoHighLevel once a learner finishes this course.', 'ghl-crm-integration' ); ?></p>
				</div>
				<select
					id="ghl_ld_completed_tags"
					name="ghl_ld_completed_tags[]"
					class="ghl-tags-select ghl-ld-tags-select"
					multiple
					data-context="completion"
					data-saved-tags='<?php echo wp_json_encode( $completion_tags ); ?>'
					data-placeholder="<?php esc_attr_e( 'Select tags to apply after completion…', 'ghl-crm-integration' ); ?>"
				>
					<option value=""><?php esc_html_e( 'Loading tags…', 'ghl-crm-integration' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Great for triggering post-course automations like certificates, upsells, or nurture sequences.', 'ghl-crm-integration' ); ?>
				</p>
			</div>

			<div class="ghl-ld-meta-note">
				<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
				<p>
					<?php esc_html_e( 'Need inspiration? Open Settings → General → User Signup Settings to see the same tagging UI in action.', 'ghl-crm-integration' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Persist meta box fields when the course is saved.
	 *
	 * @param int      $post_id Course post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_course_meta( int $post_id, \WP_Post $post ): void {
		if ( 'sfwd-courses' !== $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST['ghl_ld_course_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ghl_ld_course_nonce'] ) ), 'ghl_ld_course_settings' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$auto_enroll = isset( $_POST['ghl_ld_auto_enroll_tag'] )
			? $this->sanitize_tag_input( wp_unslash( $_POST['ghl_ld_auto_enroll_tag'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside sanitize_tag_input().
			: [];
		$completion  = isset( $_POST['ghl_ld_completed_tags'] )
			? $this->sanitize_tag_input( wp_unslash( $_POST['ghl_ld_completed_tags'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside sanitize_tag_input().
			: [];

		$this->update_course_meta( $post_id, self::AUTO_ENROLL_META_KEY, $auto_enroll );
		$this->update_course_meta( $post_id, self::COMPLETION_META_KEY, $completion );
	}

	/**
	 * Normalize raw input string into a sanitized tag array.
	 *
	 * @param mixed $value Raw input value.
	 *
	 * @return array<int,string>
	 */
	private function sanitize_tag_input( $value ): array {
		if ( is_array( $value ) ) {
			$sanitized = array_map( 'sanitize_text_field', $value );
		} else {
			$parts     = is_string( $value ) ? explode( ',', $value ) : [];
			$sanitized = array_map( 'sanitize_text_field', array_map( 'trim', $parts ) );
		}

		$sanitized = array_filter(
			$sanitized,
			static function ( $part ) {
				return '' !== $part;
			}
		);

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Save or delete post meta based on value contents.
	 *
	 * @param int               $post_id Course ID.
	 * @param string            $meta_key Meta key.
	 * @param array<int,string> $tags Tag array.
	 */
	private function update_course_meta( int $post_id, string $meta_key, array $tags ): void {
		if ( empty( $tags ) ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $tags );
	}

	/**
	 * Ensure stored meta is always returned as an array of strings.
	 *
	 * @param mixed $value Meta value.
	 * @return array<int,string>
	 */
	private function normalize_tag_meta( $value ): array {
		if ( empty( $value ) ) {
			return [];
		}

		if ( ! is_array( $value ) ) {
			$value = [ $value ];
		}

		return array_values( array_map( 'sanitize_text_field', $value ) );
	}

	/**
	 * Enqueue select2 + styles for the LearnDash course screen.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'sfwd-courses' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'ghl-globals',
			GHL_CRM_URL . 'assets/admin/css/globals.css',
			[],
			GHL_CRM_VERSION
		);

		wp_enqueue_style( 'ghl-crm-select2-css' );
		wp_enqueue_script( 'ghl-crm-select2' );

		wp_enqueue_style(
			'ghl-learndash-meta-box',
			GHL_CRM_URL . 'assets/admin/css/learndash-meta-box.css',
			[ 'ghl-globals', 'ghl-crm-select2-css' ],
			GHL_CRM_VERSION
		);

		wp_enqueue_script(
			'ghl-learndash-course-meta-box',
			GHL_CRM_URL . 'assets/admin/js/learndash-course-meta-box.js',
			[ 'jquery', 'ghl-crm-select2' ],
			GHL_CRM_VERSION,
			true
		);

		wp_localize_script(
			'ghl-learndash-course-meta-box',
			'ghlLearnDashMetaBox',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => 'ghl_crm_get_tags',
				'nonce'   => wp_create_nonce( 'ghl_crm_settings_nonce' ),
				'i18n'    => [
					// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
					'loading' => __( 'Loading tags…', 'ghl-crm-integration' ),
					'failed' => __( 'Failed to load tags', 'ghl-crm-integration' ),
					'noResults' => __( 'No tags match your search yet.', 'ghl-crm-integration' ),
					'autoPlaceholder' => __( 'Select tags that should auto-enroll a user…', 'ghl-crm-integration' ),
					'completePlaceholder' => __( 'Select tags to apply after completion…', 'ghl-crm-integration' ),
					// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
				],
			]
		);
	}
}