<?php
declare(strict_types=1);

namespace Syncly\Integrations\BuddyBoss;

use Syncly\Sync\QueueManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BuddyBoss Group Meta Box
 *
 * Adds GHL sync info and controls to BuddyBoss group admin screens
 *
 * @package    Syncly
 * @subpackage Integrations/BuddyBoss
 */
class GroupMetaBox {
	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Queue Manager
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Settings Manager
	 *
	 * @var \Syncly\Core\SettingsManager
	 */
	private \Syncly\Core\SettingsManager $settings_manager;

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
		$this->queue_manager    = QueueManager::get_instance();
		$this->settings_manager = \Syncly\Core\SettingsManager::get_instance();
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 */
	private function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Check if BuddyBoss integration is enabled
		if ( ! $this->is_integration_enabled() ) {
			return;
		}

		// Add custom admin field to group edit screen
		add_action( 'bp_groups_admin_meta_boxes', [ $this, 'add_meta_box' ] );

		// Register admin assets via AssetsManager
		$this->register_assets();

		// AJAX handler for manual sync
		add_action( 'wp_ajax_ghl_sync_buddyboss_group', [ $this, 'handle_manual_sync' ] );
	}

	/**
	 * Register admin assets via AssetsManager.
	 */
	private function register_assets(): void {
		$assets_manager = \Syncly\Core\AssetsManager::get_instance();

		// BuddyBoss group edit screen ID (child of buddyboss-platform menu).
		$screens = array( 'buddyboss_page_bp-groups' );

		// Globals CSS for design tokens (custom properties).
		$assets_manager->add_admin_asset(
			'syncly-globals-css',
			$screens,
			'globals.css',
			array(),
			array(),
			SYNCLY_VERSION,
			false
		);

		// BuddyBoss group meta box CSS.
		$assets_manager->add_admin_asset(
			'ghl-buddyboss-group-meta-box-css',
			$screens,
			'buddyboss-group-meta-box.css',
			array( 'syncly-globals-css' ),
			array(),
			SYNCLY_VERSION,
			false
		);

		// BuddyBoss group meta box JS.
		$assets_manager->add_admin_asset(
			'ghl-buddyboss-group-meta-box-js',
			$screens,
			'buddyboss-group-meta-box.js',
			array( 'jquery' ),
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => array(
					'syncing'       => __( 'Syncing...', 'syncly' ),
					'syncSuccess'   => __( 'Sync completed successfully!', 'syncly' ),
					'syncError'     => __( 'Sync failed. Check logs for details.', 'syncly' ),
					'membersQueued' => __( 'Members queued for sync!', 'syncly' ),
				),
			),
			SYNCLY_VERSION
		);
	}

	/**
	 * Check if BuddyBoss integration is enabled
	 *
	 * @return bool
	 */
	private function is_integration_enabled(): bool {
		$settings = $this->settings_manager->get_settings_array();
		return ! empty( $settings['buddyboss_groups_enabled'] );
	}

	/**
	 * Add meta box to BuddyBoss group edit screen
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'ghl_buddyboss_sync',
			__( 'GoHighLevel Sync', 'syncly' ),
			[ $this, 'render_meta_box' ],
			get_current_screen()->id,
			'side',
			'core'
		);
	}

	/**
	 * Render meta box content
	 *
	 * @param object $item Group object
	 */
	public function render_meta_box( $item ): void {
		if ( empty( $item->id ) ) {
			echo '<p>' . esc_html__( 'Save the group first to enable sync.', 'syncly' ) . '</p>';
			return;
		}

		$group_id = absint( $item->id );

		// Get GHL sync data
		$record_id      = groups_get_groupmeta( $group_id, 'ghl_custom_object_record_id', true );
		$object_id      = groups_get_groupmeta( $group_id, 'ghl_custom_object_id', true );
		$object_slug    = groups_get_groupmeta( $group_id, 'ghl_custom_object_slug', true );
		$association_id = groups_get_groupmeta( $group_id, 'ghl_association_id', true );
		$group_types    = bp_groups_get_group_type( $group_id, false );
		$group_type     = ! empty( $group_types ) ? $group_types[0] : '';

		// Get settings for white label domain and location ID
		$settings           = $this->settings_manager->get_settings_array();
		$location_id        = $settings['location_id'] ?? '';
		$white_label_domain = $settings['ghl_white_label_domain'] ?? '';

		// Determine base domain (white label or default)
		$base_domain = ! empty( $white_label_domain ) ? rtrim( $white_label_domain, '/' ) : 'https://app.gohighlevel.com';

		// Build GHL URLs (v2 format)
		$record_url = '';
		$object_url = '';
		if ( ! empty( $location_id ) && ! empty( $object_slug ) ) {
			// Object list URL
			$object_url = sprintf(
				'%s/v2/location/%s/objects/%s/list',
				$base_domain,
				rawurlencode( $location_id ),
				rawurlencode( $object_slug )
			);

			// Specific record URL with sort and recordId parameters
			if ( ! empty( $record_id ) ) {
				$sort_param = rawurlencode( '[{"field":"createdAt","dir":"desc"}]' );
				$record_url = sprintf(
					'%s/v2/location/%s/objects/%s/list?sort=%s&recordId=%s&t=a',
					$base_domain,
					rawurlencode( $location_id ),
					rawurlencode( $object_slug ),
					$sort_param,
					rawurlencode( $record_id )
				);
			}
		}

		wp_nonce_field( 'ghl_sync_group_' . $group_id, 'ghl_sync_nonce' );
		?>
		<div class="ghl-group-sync-meta-box">

			<div class="ghl-sync-status">
				<div class="ghl-sync-status-item">
					<span class="ghl-sync-label"><?php esc_html_e( 'Sync Status:', 'syncly' ); ?></span>
					<span class="ghl-sync-value">
						<?php if ( ! empty( $record_id ) ) : ?>
							<span class="ghl-sync-badge synced"><?php esc_html_e( 'Synced', 'syncly' ); ?></span>
						<?php else : ?>
							<span class="ghl-sync-badge not-synced"><?php esc_html_e( 'Not Synced', 'syncly' ); ?></span>
						<?php endif; ?>
					</span>
				</div>

				<?php if ( ! empty( $record_id ) ) : ?>
					<div class="ghl-sync-status-item">
						<span class="ghl-sync-label"><?php esc_html_e( 'GHL Record ID:', 'syncly' ); ?></span>
						<span class="ghl-sync-value">
							<?php if ( ! empty( $record_url ) ) : ?>
								<a href="<?php echo esc_url( $record_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $record_id ); ?>
									<span class="dashicons dashicons-external"></span>
								</a>
							<?php else : ?>
								<?php echo esc_html( $record_id ); ?>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $object_id ) ) : ?>
					<div class="ghl-sync-status-item">
						<span class="ghl-sync-label"><?php esc_html_e( 'Object ID:', 'syncly' ); ?></span>
						<span class="ghl-sync-value">
							<?php if ( ! empty( $object_url ) ) : ?>
								<a href="<?php echo esc_url( $object_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $object_id ); ?>
									<span class="dashicons dashicons-external"></span>
								</a>
							<?php else : ?>
								<?php echo esc_html( $object_id ); ?>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $association_id ) ) : ?>
					<div class="ghl-sync-status-item">
						<span class="ghl-sync-label"><?php esc_html_e( 'Association ID:', 'syncly' ); ?></span>
						<span class="ghl-sync-value"><?php echo esc_html( $association_id ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<div class="ghl-sync-actions">
				<button type="button" 
					class="ghl-button ghl-button-secondary ghl-sync-btn" 
					id="ghl-sync-group-btn"
					data-group-id="<?php echo esc_attr( $group_id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'ghl_sync_group_' . $group_id ) ); ?>">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Sync Group to GHL', 'syncly' ); ?>
				</button>

				<button type="button" 
					class="ghl-button ghl-button-secondary ghl-sync-btn" 
					id="ghl-sync-members-btn"
					data-group-id="<?php echo esc_attr( $group_id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'ghl_sync_members_' . $group_id ) ); ?>">
					<span class="dashicons dashicons-groups"></span>
					<?php esc_html_e( 'Sync All Members', 'syncly' ); ?>
				</button>

				<span class="ghl-sync-spinner spinner"></span>
			</div>

			<div class="ghl-sync-message"></div>

			<p class="description" style="margin-top: var(--ghl-spacing-lg); padding-top: var(--ghl-spacing-lg); border-top: 1px solid var(--ghl-border-primary); color: var(--ghl-text-secondary); font-size: var(--ghl-font-size-sm);">
				<?php esc_html_e( 'Syncs this group and its members to GoHighLevel custom objects.', 'syncly' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle manual sync AJAX request
	 */
	public function handle_manual_sync(): void {
		check_ajax_referer( 'ghl_sync_group_' . absint( $_POST['group_id'] ?? 0 ), 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'syncly' ) ] );
		}

		$group_id  = absint( $_POST['group_id'] ?? 0 );
		$sync_type = sanitize_text_field( $_POST['sync_type'] ?? 'group' );

		if ( ! $group_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid group ID', 'syncly' ) ] );
		}

		$group = groups_get_group( $group_id );
		if ( ! $group ) {
			wp_send_json_error( [ 'message' => __( 'Group not found', 'syncly' ) ] );
		}

		if ( 'group' === $sync_type ) {
			// Queue group sync
			$queue_id = $this->queue_manager->add_to_queue(
				'buddyboss_group',
				$group_id,
				'sync_group',
				[
					'group_name' => $group->name,
					'group_id'   => $group_id,
				]
			);

			wp_send_json_success(
				[
					'message'  => __( 'Group queued for sync', 'syncly' ),
					'queue_id' => $queue_id,
				]
			);
		} elseif ( 'members' === $sync_type ) {
			// Queue all members for association
			$members = groups_get_group_members( [ 'group_id' => $group_id ] );
			$queued  = 0;

			$record_id      = groups_get_groupmeta( $group_id, 'ghl_custom_object_record_id', true );
			$association_id = groups_get_groupmeta( $group_id, 'ghl_association_id', true );

			if ( empty( $record_id ) || empty( $association_id ) ) {
				wp_send_json_error(
					[
						'message' => __( 'Group must be synced first', 'syncly' ),
					]
				);
			}

			if ( ! empty( $members['members'] ) ) {
				foreach ( $members['members'] as $member ) {
					$this->queue_manager->add_to_queue(
						'buddyboss_member_association',
						$member->ID,
						'create_association',
						[
							'user_id'        => $member->ID,
							'group_id'       => $group_id,
							'record_id'      => $record_id,
							'association_id' => $association_id,
						]
					);
					++$queued;
				}
			}

			wp_send_json_success(
				[
					'message' => sprintf(
						/* translators: %d: number of members */
						__( '%d members queued for sync', 'syncly' ),
						$queued
					),
					'queued'  => $queued,
				]
			);
		}

		wp_send_json_error( [ 'message' => __( 'Invalid sync type', 'syncly' ) ] );
	}

	/**
	 * Initialize (called by Loader)
	 */
	public static function init(): void {
		self::get_instance();
	}
}
