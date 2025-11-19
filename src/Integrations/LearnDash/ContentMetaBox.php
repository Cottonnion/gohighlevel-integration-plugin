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

		// For quizzes, get score thresholds
		$score_thresholds = [];
		if ( 'quiz' === $type ) {
			$score_thresholds = $this->normalize_score_thresholds(
				get_post_meta( $post->ID, '_ghl_ld_quiz_score_thresholds', true )
			);
		}
		?>
		<div class="ghl-ld-meta-box">
			<?php if ( 'quiz' === $type ) : ?>
				<!-- Quiz Score-Based Tagging -->
				<div class="ghl-ld-meta-card">
					<div class="ghl-ld-meta-card__header">
						<h4><?php esc_html_e( 'Score-Based Tagging', 'ghl-crm-integration' ); ?></h4>
						<p><?php esc_html_e( 'Apply different tags based on quiz performance. Define score ranges and the tags to apply for each level.', 'ghl-crm-integration' ); ?></p>
					</div>

					<div id="ghl-quiz-score-thresholds" class="ghl-quiz-score-thresholds">
						<?php if ( ! empty( $score_thresholds ) ) : ?>
							<?php foreach ( $score_thresholds as $index => $threshold ) : ?>
								<?php $this->render_threshold_row( $index, $threshold ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<button type="button" class="ghl-button ghl-button-secondary ghl-add-threshold" id="ghl-add-score-threshold">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Score Range', 'ghl-crm-integration' ); ?>
					</button>

					<p class="description" style="margin-top: 12px;">
						<?php esc_html_e( 'Example: 90-100 = "High Achiever", 70-89 = "Passed", 0-69 = "Needs Improvement"', 'ghl-crm-integration' ); ?>
					</p>
				</div>

				<div class="ghl-ld-meta-note" style="margin-bottom: 20px;">
					<span class="dashicons dashicons-info" aria-hidden="true"></span>
					<p><?php esc_html_e( 'Score-based tags are applied in addition to completion tags. If ranges overlap, all matching tags will be applied.', 'ghl-crm-integration' ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Standard Completion Tags -->
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
	 * Render a single score threshold row.
	 *
	 * @param int   $index     Row index.
	 * @param array $threshold Threshold data.
	 */
	private function render_threshold_row( int $index, array $threshold ): void {
		$min_score = $threshold['min_score'] ?? '';
		$max_score = $threshold['max_score'] ?? '';
		$tags      = $threshold['tags'] ?? [];
		?>
		<div class="ghl-threshold-row" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="ghl-threshold-inputs">
				<div class="ghl-threshold-score-range">
					<label>
						<span><?php esc_html_e( 'Min %', 'ghl-crm-integration' ); ?></span>
						<input
							type="number"
							name="ghl_quiz_thresholds[<?php echo esc_attr( $index ); ?>][min_score]"
							class="ghl-threshold-min"
							min="0"
							max="100"
							step="1"
							value="<?php echo esc_attr( $min_score ); ?>"
							placeholder="0"
						/>
					</label>
					<span class="ghl-threshold-separator">–</span>
					<label>
						<span><?php esc_html_e( 'Max %', 'ghl-crm-integration' ); ?></span>
						<input
							type="number"
							name="ghl_quiz_thresholds[<?php echo esc_attr( $index ); ?>][max_score]"
							class="ghl-threshold-max"
							min="0"
							max="100"
							step="1"
							value="<?php echo esc_attr( $max_score ); ?>"
							placeholder="100"
						/>
					</label>
				</div>
				<div class="ghl-threshold-tags">
					<select
						name="ghl_quiz_thresholds[<?php echo esc_attr( $index ); ?>][tags][]"
						class="ghl-tags-select ghl-threshold-tags-select"
						multiple
						data-context="quiz-threshold-<?php echo esc_attr( $index ); ?>"
						data-saved-tags='<?php echo wp_json_encode( $tags ); ?>'
						data-placeholder="<?php esc_attr_e( 'Select tags for this score range…', 'ghl-crm-integration' ); ?>"
					>
						<option value=""><?php esc_html_e( 'Loading tags…', 'ghl-crm-integration' ); ?></option>
					</select>
				</div>
			</div>
			<button type="button" class="button button-link-delete ghl-remove-threshold" title="<?php esc_attr_e( 'Remove this range', 'ghl-crm-integration' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
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

		// Save standard completion tags
		$completion_tags = isset( $_POST['ghl_ld_completed_tags'] )
			? $this->sanitize_tag_input( wp_unslash( $_POST['ghl_ld_completed_tags'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside sanitize_tag_input().
			: [];

		$meta_key = sprintf( '_ghl_ld_%s_completed_tags', $type );
		$this->update_content_meta( $post_id, $meta_key, $completion_tags );

		// Save quiz score thresholds
		if ( 'quiz' === $type && isset( $_POST['ghl_quiz_thresholds'] ) ) {
			$raw_thresholds = wp_unslash( $_POST['ghl_quiz_thresholds'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
			$thresholds     = $this->sanitize_score_thresholds( $raw_thresholds );
			
			if ( ! empty( $thresholds ) ) {
				update_post_meta( $post_id, '_ghl_ld_quiz_score_thresholds', $thresholds );
			} else {
				delete_post_meta( $post_id, '_ghl_ld_quiz_score_thresholds' );
			}
		}
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
	 * Sanitize and validate score thresholds input.
	 *
	 * @param mixed $raw_thresholds Raw threshold data from POST.
	 * @return array<int,array> Sanitized thresholds.
	 */
	private function sanitize_score_thresholds( $raw_thresholds ): array {
		if ( ! is_array( $raw_thresholds ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $raw_thresholds as $threshold ) {
			if ( ! is_array( $threshold ) ) {
				continue;
			}

			$min_score = isset( $threshold['min_score'] ) ? absint( $threshold['min_score'] ) : null;
			$max_score = isset( $threshold['max_score'] ) ? absint( $threshold['max_score'] ) : null;
			$tags      = isset( $threshold['tags'] ) ? $this->sanitize_tag_input( $threshold['tags'] ) : [];

			// Skip invalid or empty thresholds
			if ( null === $min_score || null === $max_score || empty( $tags ) ) {
				continue;
			}

			// Validate range
			if ( $min_score < 0 || $min_score > 100 || $max_score < 0 || $max_score > 100 ) {
				continue;
			}

			if ( $min_score > $max_score ) {
				continue;
			}

			$sanitized[] = [
				'min_score' => $min_score,
				'max_score' => $max_score,
				'tags'      => $tags,
			];
		}

		return $sanitized;
	}

	/**
	 * Normalize score thresholds from database.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array<int,array> Normalized thresholds.
	 */
	private function normalize_score_thresholds( $value ): array {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $value as $threshold ) {
			if ( ! is_array( $threshold ) ) {
				continue;
			}

			$min_score = isset( $threshold['min_score'] ) ? absint( $threshold['min_score'] ) : 0;
			$max_score = isset( $threshold['max_score'] ) ? absint( $threshold['max_score'] ) : 100;
			$tags      = isset( $threshold['tags'] ) ? $this->normalize_tag_meta( $threshold['tags'] ) : [];

			if ( empty( $tags ) ) {
				continue;
			}

			$normalized[] = [
				'min_score' => $min_score,
				'max_score' => $max_score,
				'tags'      => $tags,
			];
		}

		return $normalized;
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
					'loading'              => __( 'Loading tags…', 'ghl-crm-integration' ),
					'failed'               => __( 'Failed to load tags', 'ghl-crm-integration' ),
					'noResults'            => __( 'No tags match your search yet.', 'ghl-crm-integration' ),
					'completePlaceholder'  => __( 'Select tags to apply after completion…', 'ghl-crm-integration' ),
					'thresholdPlaceholder' => __( 'Select tags for this score range…', 'ghl-crm-integration' ),
					'minScore'             => __( 'Min %', 'ghl-crm-integration' ),
					'maxScore'             => __( 'Max %', 'ghl-crm-integration' ),
					'removeRange'          => __( 'Remove this range', 'ghl-crm-integration' ),
				],
			]
		);
	}
}
