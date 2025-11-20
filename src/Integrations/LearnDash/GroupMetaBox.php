<?php
/**
 * LearnDash group-level settings meta box.
 *
 * @package GHL_CRM_Integration
 */

declare(strict_types=1);

namespace GHL_CRM\Integrations\LearnDash;

defined( 'ABSPATH' ) || exit;

/**
 * Adds per-group configuration for LearnDash group automation hooks.
 */
class GroupMetaBox {
	private const GROUP_TAGS_META_KEY      = '_ghl_ld_group_tags';
	private const AUTO_ENROLL_META_KEY     = '_ghl_ld_group_auto_enroll_tags';
	private const REMOVE_ON_LEAVE_META_KEY = '_ghl_ld_group_remove_on_leave';

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
		add_action( 'save_post', [ $this, 'save_group_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register the group automation meta box.
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'ghl-learndash-group-automation',
			__( 'GoHighLevel Automations', 'ghl-crm-integration' ),
			[ $this, 'render_meta_box' ],
			'groups',
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box UI.
	 *
	 * @param \WP_Post $post Group post object.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'ghl_ld_group_settings', 'ghl_ld_group_nonce' );

		$group_tags      = $this->normalize_tag_meta( get_post_meta( $post->ID, self::GROUP_TAGS_META_KEY, true ) );
		$auto_enroll     = $this->normalize_tag_meta( get_post_meta( $post->ID, self::AUTO_ENROLL_META_KEY, true ) );
		$remove_on_leave = get_post_meta( $post->ID, self::REMOVE_ON_LEAVE_META_KEY, true );
		
		// Default to true (enabled) if not set
		if ( '' === $remove_on_leave ) {
			$remove_on_leave = '1';
		}
		?>
		<div class="ghl-ld-meta-box">
			<div class="ghl-ld-meta-card">
				<div class="ghl-ld-meta-card__header">
					<h4><?php esc_html_e( 'Auto-Enroll When Tag Exists', 'ghl-crm-integration' ); ?></h4>
					<p><?php esc_html_e( 'If a synced contact has any of these tags, automatically add them to this group.', 'ghl-crm-integration' ); ?></p>
				</div>
				<select
					id="ghl_ld_group_auto_enroll"
					name="ghl_ld_group_auto_enroll[]"
					class="ghl-tags-select ghl-ld-tags-select"
					multiple
					data-context="auto-enroll"
					data-saved-tags='<?php echo wp_json_encode( $auto_enroll ); ?>'
					data-placeholder="<?php esc_attr_e( 'Select tags that should auto-enroll a user…', 'ghl-crm-integration' ); ?>"
				>
					<option value=""><?php esc_html_e( 'Loading tags…', 'ghl-crm-integration' ); ?></option>
				</select>
			</div>

			<div class="ghl-ld-meta-card">
				<div class="ghl-ld-meta-card__header">
					<h4><?php esc_html_e( 'Group Membership Tags', 'ghl-crm-integration' ); ?></h4>
					<p><?php esc_html_e( 'These tags are applied when a user joins this group and removed when they leave.', 'ghl-crm-integration' ); ?></p>
				</div>
				<select
					id="ghl_ld_group_tags"
					name="ghl_ld_group_tags[]"
					class="ghl-tags-select ghl-ld-tags-select"
					multiple
					data-context="membership"
					data-saved-tags='<?php echo wp_json_encode( $group_tags ); ?>'
					data-placeholder="<?php esc_attr_e( 'Select tags for group membership…', 'ghl-crm-integration' ); ?>"
				>
					<option value=""><?php esc_html_e( 'Loading tags…', 'ghl-crm-integration' ); ?></option>
				</select>
				<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--ghl-border-secondary);">
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input 
							type="checkbox" 
							id="ghl_ld_group_remove_on_leave"
							name="ghl_ld_group_remove_on_leave" 
							value="1"
							<?php checked( $remove_on_leave, '1' ); ?>
							style="margin: 0;"
						>
						<span style="font-size: 13px; color: var(--ghl-text-primary);">
							<?php esc_html_e( 'Remove tags when user leaves group', 'ghl-crm-integration' ); ?>
						</span>
					</label>
					<p class="description" style="margin: 6px 0 0 28px; font-size: 12px;">
						<?php esc_html_e( 'Only removes tags that were applied BY THIS GROUP, not tags from other sources.', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save group meta when post is updated.
	 *
	 * @param int      $post_id Group post ID.
	 * @param \WP_Post $post    Group post object.
	 */
	public function save_group_meta( int $post_id, \WP_Post $post ): void {
		// Verify this is a group post type
		if ( 'groups' !== $post->post_type ) {
			return;
		}

		// Check nonce
		if ( ! isset( $_POST['ghl_ld_group_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ghl_ld_group_nonce'] ) ), 'ghl_ld_group_settings' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save auto-enroll tags
		$auto_enroll = isset( $_POST['ghl_ld_group_auto_enroll'] ) && is_array( $_POST['ghl_ld_group_auto_enroll'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['ghl_ld_group_auto_enroll'] ) )
			: [];
		$this->update_group_meta( $post_id, self::AUTO_ENROLL_META_KEY, $auto_enroll );

		// Save group membership tags (applied on join, removed on leave)
		$group_tags = isset( $_POST['ghl_ld_group_tags'] ) && is_array( $_POST['ghl_ld_group_tags'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['ghl_ld_group_tags'] ) )
			: [];
		$this->update_group_meta( $post_id, self::GROUP_TAGS_META_KEY, $group_tags );

		// Save remove on leave setting
		$remove_on_leave = isset( $_POST['ghl_ld_group_remove_on_leave'] ) ? '1' : '0';
		update_post_meta( $post_id, self::REMOVE_ON_LEAVE_META_KEY, $remove_on_leave );
	}

	/**
	 * Update or delete group meta.
	 *
	 * @param int               $post_id  Group post ID.
	 * @param string            $meta_key Meta key.
	 * @param array<int,string> $tags     Tag array.
	 */
	private function update_group_meta( int $post_id, string $meta_key, array $tags ): void {
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
	 * Enqueue select2 + styles for the LearnDash group screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();

		if ( ! $screen || 'groups' !== $screen->post_type || ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		// Enqueue Select2
		wp_enqueue_style( 'ghl-crm-select2' );
		wp_enqueue_script( 'ghl-crm-select2' );

		// Enqueue group meta box styles
		wp_enqueue_style(
			'ghl-learndash-group-meta-box',
			GHL_CRM_URL . 'assets/admin/css/learndash-group-meta-box.css',
			[],
			GHL_CRM_VERSION
		);

		// Enqueue group meta box script
		wp_enqueue_script(
			'ghl-learndash-group-meta-box',
			GHL_CRM_URL . 'assets/admin/js/learndash-group-meta-box.js',
			[ 'jquery', 'ghl-crm-select2' ],
			GHL_CRM_VERSION,
			true
		);

		wp_localize_script(
			'ghl-learndash-group-meta-box',
			'ghlLearnDashGroupMetaBox',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => 'ghl_crm_get_tags',
				'nonce'   => wp_create_nonce( 'ghl_crm_settings_nonce' ),
				'i18n'    => [
					// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
					'loading' => __( 'Loading tags…', 'ghl-crm-integration' ),
					'failed' => __( 'Failed to load tags', 'ghl-crm-integration' ),
					'noResults' => __( 'No tags match your search yet.', 'ghl-crm-integration' ),
					// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
				],
			]
		);
	}
}
