<?php
declare(strict_types=1);

namespace GHL_CRM\Integrations\BuddyBoss;

use GHL_CRM\Sync\QueueManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BuddyBoss Group Meta Box
 *
 * Adds GHL sync info and controls to BuddyBoss group admin screens
 *
 * @package    GHL_CRM_Integration
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
	 * @var \GHL_CRM\Core\SettingsManager
	 */
	private \GHL_CRM\Core\SettingsManager $settings_manager;

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
		$this->settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
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

		// Enqueue scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// AJAX handler for manual sync
		add_action( 'wp_ajax_ghl_sync_buddyboss_group', [ $this, 'handle_manual_sync' ] );
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
			__( 'GoHighLevel Sync', 'ghl-crm-integration' ),
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
			echo '<p>' . esc_html__( 'Save the group first to enable sync.', 'ghl-crm-integration' ) . '</p>';
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
			<style>
				.ghl-group-sync-meta-box { 
					padding: var(--ghl-spacing-md); 
				}
				.ghl-sync-status { 
					margin-bottom: var(--ghl-spacing-lg); 
				}
				.ghl-sync-status-item { 
					display: flex; 
					justify-content: space-between; 
					padding: var(--ghl-spacing-sm) 0; 
					border-bottom: 1px solid var(--ghl-border-primary);
				}
				.ghl-sync-status-item:last-child { 
					border-bottom: none; 
				}
				.ghl-sync-label { 
					font-weight: var(--ghl-font-weight-semibold); 
					color: var(--ghl-text-primary); 
				}
				.ghl-sync-value { 
					color: var(--ghl-text-secondary); 
					font-family: var(--ghl-font-family-mono); 
					font-size: var(--ghl-font-size-xs);
					max-width: 60%;
					text-align: right;
					word-break: break-all;
				}
				.ghl-sync-value a {
					color: var(--ghl-primary);
					text-decoration: none;
				}
				.ghl-sync-value a:hover {
					text-decoration: underline;
				}
				.ghl-sync-value .dashicons {
					font-size: 14px;
					width: 14px;
					height: 14px;
					vertical-align: text-top;
				}
				.ghl-sync-badge {
					display: inline-block;
					padding: 2px var(--ghl-spacing-sm);
					border-radius: var(--ghl-radius-sm);
					font-size: var(--ghl-font-size-xs);
					font-weight: var(--ghl-font-weight-semibold);
				}
				.ghl-sync-badge.synced {
					background: var(--ghl-success-light);
					color: var(--ghl-success-dark);
				}
				.ghl-sync-badge.not-synced {
					background: var(--ghl-error-light);
					color: var(--ghl-error-dark);
				}
				.ghl-sync-actions { 
					margin-top: var(--ghl-spacing-lg); 
				}
				.ghl-sync-btn {
					width: 100%;
					margin-bottom: var(--ghl-spacing-sm);
				}
				.ghl-sync-spinner {
					display: none;
					float: right;
					margin: 5px 5px 0 0;
				}
				.ghl-sync-message {
					margin-top: var(--ghl-spacing-md);
					padding: var(--ghl-spacing-md);
					border-radius: var(--ghl-radius-base);
					font-size: var(--ghl-font-size-sm);
					display: none;
				}
				.ghl-sync-message.success {
					background: var(--ghl-success-light);
					color: var(--ghl-success-dark);
				}
				.ghl-sync-message.error {
					background: var(--ghl-error-light);
					color: var(--ghl-error-dark);
				}
			</style>

			<div class="ghl-sync-status">
				<div class="ghl-sync-status-item">
					<span class="ghl-sync-label"><?php esc_html_e( 'Sync Status:', 'ghl-crm-integration' ); ?></span>
					<span class="ghl-sync-value">
						<?php if ( ! empty( $record_id ) ) : ?>
							<span class="ghl-sync-badge synced"><?php esc_html_e( 'Synced', 'ghl-crm-integration' ); ?></span>
						<?php else : ?>
							<span class="ghl-sync-badge not-synced"><?php esc_html_e( 'Not Synced', 'ghl-crm-integration' ); ?></span>
						<?php endif; ?>
					</span>
				</div>

				<?php if ( ! empty( $record_id ) ) : ?>
					<div class="ghl-sync-status-item">
						<span class="ghl-sync-label"><?php esc_html_e( 'GHL Record ID:', 'ghl-crm-integration' ); ?></span>
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
						<span class="ghl-sync-label"><?php esc_html_e( 'Object ID:', 'ghl-crm-integration' ); ?></span>
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
						<span class="ghl-sync-label"><?php esc_html_e( 'Association ID:', 'ghl-crm-integration' ); ?></span>
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
					<?php esc_html_e( 'Sync Group to GHL', 'ghl-crm-integration' ); ?>
				</button>

				<button type="button" 
					class="ghl-button ghl-button-secondary ghl-sync-btn" 
					id="ghl-sync-members-btn"
					data-group-id="<?php echo esc_attr( $group_id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'ghl_sync_members_' . $group_id ) ); ?>">
					<span class="dashicons dashicons-groups"></span>
					<?php esc_html_e( 'Sync All Members', 'ghl-crm-integration' ); ?>
				</button>

				<span class="ghl-sync-spinner spinner"></span>
			</div>

			<div class="ghl-sync-message"></div>

			<p class="description" style="margin-top: var(--ghl-spacing-lg); padding-top: var(--ghl-spacing-lg); border-top: 1px solid var(--ghl-border-primary); color: var(--ghl-text-secondary); font-size: var(--ghl-font-size-sm);">
				<?php esc_html_e( 'Syncs this group and its members to GoHighLevel custom objects.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current page hook
	 */
	public function enqueue_scripts( string $hook ): void {
		// Only load on BuddyBoss group edit screen
		$screen = get_current_screen();
		// if ( ! $screen || strpos( $screen->id, 'toplevel_page_bp-groups' ) === false ) {
		// return;
		// }

		// Enqueue globals CSS for consistent design
		wp_enqueue_style(
			'ghl-globals',
			GHL_CRM_URL . 'assets/admin/css/globals.css',
			[],
			GHL_CRM_VERSION
		);

		// Enqueue settings CSS for button styles
		wp_enqueue_style(
			'ghl-settings',
			GHL_CRM_URL . 'assets/admin/css/settings.css',
			[ 'ghl-globals' ],
			GHL_CRM_VERSION
		);

		wp_enqueue_script(
			'ghl-buddyboss-group-meta-box',
			GHL_CRM_URL . 'assets/admin/js/buddyboss-group-meta-box.js',
			[ 'jquery' ],
			GHL_CRM_VERSION,
			true
		);

		wp_localize_script(
			'ghl-buddyboss-group-meta-box',
			'ghlBuddyBossGroup',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => [
					'syncing'       => __( 'Syncing...', 'ghl-crm-integration' ),
					'syncSuccess'   => __( 'Sync completed successfully!', 'ghl-crm-integration' ),
					'syncError'     => __( 'Sync failed. Check logs for details.', 'ghl-crm-integration' ),
					'membersQueued' => __( 'Members queued for sync!', 'ghl-crm-integration' ),
				],
			]
		);
	}

	/**
	 * Handle manual sync AJAX request
	 */
	public function handle_manual_sync(): void {
		check_ajax_referer( 'ghl_sync_group_' . absint( $_POST['group_id'] ?? 0 ), 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ] );
		}

		$group_id  = absint( $_POST['group_id'] ?? 0 );
		$sync_type = sanitize_text_field( $_POST['sync_type'] ?? 'group' );

		if ( ! $group_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid group ID', 'ghl-crm-integration' ) ] );
		}

		$group = groups_get_group( $group_id );
		if ( ! $group ) {
			wp_send_json_error( [ 'message' => __( 'Group not found', 'ghl-crm-integration' ) ] );
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
					'message'  => __( 'Group queued for sync', 'ghl-crm-integration' ),
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
						'message' => __( 'Group must be synced first', 'ghl-crm-integration' ),
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
						__( '%d members queued for sync', 'ghl-crm-integration' ),
						$queued
					),
					'queued'  => $queued,
				]
			);
		}

		wp_send_json_error( [ 'message' => __( 'Invalid sync type', 'ghl-crm-integration' ) ] );
	}

	/**
	 * Initialize (called by Loader)
	 */
	public static function init(): void {
		self::get_instance();
	}
}
