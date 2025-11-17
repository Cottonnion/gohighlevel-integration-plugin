<?php
/**
 * LearnDash content-level settings meta box for lessons, topics, and quizzes.
 *
 * @package GHL_CRM_Integration
 */

declare(strict_types=1);

namespace GHL_CRM\Integrations\LearnDash;

defined( 'ABSPATH' ) || exit;

/**
 * Adds per-content configuration for LearnDash lesson, topic, and quiz completion tags.
 */
class ContentMetaBox {
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

		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_content_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register meta boxes for lessons, topics, and quizzes.
	 */
	public function register_meta_boxes(): void {
		$post_types = [
			'sfwd-lessons' => __( 'Lesson Completion Tags', 'ghl-crm-integration' ),
			'sfwd-topic'   => __( 'Topic Completion Tags', 'ghl-crm-integration' ),
			'sfwd-quiz'    => __( 'Quiz Completion Tags', 'ghl-crm-integration' ),
		];

		foreach ( $post_types as $post_type => $title ) {
			add_meta_box(
				'ghl-learndash-content-tags',
				$title,
				[ $this, 'render_meta_box' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box UI.
	 *
	 * @param \WP_Post $post Content post object.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'ghl_ld_content_settings', 'ghl_ld_content_nonce' );

		// Determine content type
		$type = $this->get_content_type( $post->post_type );
		if ( ! $type ) {
			return;
		}

		$meta_key        = sprintf( '_ghl_ld_%s_completed_tags', $type );
		$completion_tags = $this->normalize_tag_meta( get_post_meta( $post->ID, $meta_key, true ) );

		// Get content type label
		$type_label = $this->get_type_label( $type );
		?>
		<div class="ghl-ld-meta-box">
			<div class="ghl-ld-meta-card">
				<div class="ghl-ld-meta-card__header">
					<h4><?php echo esc_html( sprintf( __( 'Apply Tags When %s is Completed', 'ghl-crm-integration' ), $type_label ) ); ?></h4>
					<p><?php echo esc_html( sprintf( __( 'Select the tags that should be pushed to GoHighLevel when a learner completes this %s.', 'ghl-crm-integration' ), strtolower( $type_label ) ) ); ?></p>
				</div>
				<select
					id="ghl_ld_<?php echo esc_attr( $type ); ?>_completed_tags"
					name="ghl_ld_completed_tags[]"
					class="ghl-tags-select ghl-ld-tags-select"
					multiple
					data-context="<?php echo esc_attr( $type ); ?>-completion"
					data-saved-tags='<?php echo wp_json_encode( $completion_tags ); ?>'
					data-placeholder="<?php esc_attr_e( 'Select tags to apply after completion…', 'ghl-crm-integration' ); ?>"
				>
					<option value=""><?php esc_html_e( 'Loading tags…', 'ghl-crm-integration' ); ?></option>
				</select>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							__( 'Tags will be added when the user marks this %s as complete.', 'ghl-crm-integration' ),
							strtolower( $type_label )
						)
					);
					?>
				</p>
			</div>

			<div class="ghl-ld-meta-note">
				<span class="dashicons dashicons-info" aria-hidden="true"></span>
				<p>
					<?php
					echo esc_html(
						sprintf(
							__( 'This works alongside course-level tags. Both %s and course tags will be applied.', 'ghl-crm-integration' ),
							strtolower( $type_label )
						)
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Persist meta box fields when content is saved.
	 *
	 * @param int      $post_id Content post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_content_meta( int $post_id, \WP_Post $post ): void {
		$type = $this->get_content_type( $post->post_type );
		if ( ! $type ) {
			return;
		}

		if ( ! isset( $_POST['ghl_ld_content_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ghl_ld_content_nonce'] ) ), 'ghl_ld_content_settings' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$completion_tags = isset( $_POST['ghl_ld_completed_tags'] )
			? $this->sanitize_tag_input( wp_unslash( $_POST['ghl_ld_completed_tags'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside sanitize_tag_input().
			: [];

		$meta_key = sprintf( '_ghl_ld_%s_completed_tags', $type );
		$this->update_content_meta( $post_id, $meta_key, $completion_tags );
	}

	/**
	 * Get content type from post type.
	 *
	 * @param string $post_type WordPress post type.
	 * @return string|null Content type (lesson|topic|quiz) or null if not supported.
	 */
	private function get_content_type( string $post_type ): ?string {
		$map = [
			'sfwd-lessons' => 'lesson',
			'sfwd-topic'   => 'topic',
			'sfwd-quiz'    => 'quiz',
		];

		return $map[ $post_type ] ?? null;
	}

	/**
	 * Get human-readable label for content type.
	 *
	 * @param string $type Content type (lesson|topic|quiz).
	 * @return string Human-readable label.
	 */
	private function get_type_label( string $type ): string {
		$labels = [
			'lesson' => __( 'Lesson', 'ghl-crm-integration' ),
			'topic'  => __( 'Topic', 'ghl-crm-integration' ),
			'quiz'   => __( 'Quiz', 'ghl-crm-integration' ),
		];

		return $labels[ $type ] ?? ucfirst( $type );
	}

	/**
	 * Normalize raw input string into a sanitized tag array.
	 *
	 * @param mixed $value Raw input value.
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
	 * @param int               $post_id Content ID.
	 * @param string            $meta_key Meta key.
	 * @param array<int,string> $tags Tag array.
	 */
	private function update_content_meta( int $post_id, string $meta_key, array $tags ): void {
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
	 * Enqueue select2 + styles for LearnDash content screens.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, [ 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ], true ) ) {
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
			'ghl-learndash-content-meta-box',
			GHL_CRM_URL . 'assets/admin/js/learndash-content-meta-box.js',
			[ 'jquery', 'ghl-crm-select2' ],
			GHL_CRM_VERSION,
			true
		);

		// Get the content type for this screen
		$type = $this->get_content_type( $screen->post_type );

		wp_localize_script(
			'ghl-learndash-content-meta-box',
			'ghlLearnDashMetaBox',
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'action'      => 'ghl_crm_get_tags',
				'nonce'       => wp_create_nonce( 'ghl_crm_settings_nonce' ),
				'contentType' => $type,
				'i18n'        => [
					'loading'             => __( 'Loading tags…', 'ghl-crm-integration' ),
					'failed'              => __( 'Failed to load tags', 'ghl-crm-integration' ),
					'noResults'           => __( 'No tags match your search yet.', 'ghl-crm-integration' ),
					'completePlaceholder' => __( 'Select tags to apply after completion…', 'ghl-crm-integration' ),
				],
			]
		);
	}
}
