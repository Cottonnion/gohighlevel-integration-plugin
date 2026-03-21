<?php
declare(strict_types=1);

namespace GHL_CRM\Admin\Profile;

use GHL_CRM\Core\SettingsManager;
use GHL_CRM\Sync\TagManager;
use GHL_CRM\API\Client\Client;
use GHL_CRM\API\Resources\ContactResource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Profile Fields
 *
 * Adds GoHighLevel data section to user profile/edit pages
 * Includes contact info, tags management with Select2, and sync controls
 *
 * @package    GHL_CRM_Integration
 * @subpackage Admin/Profile
 */
class UserProfileFields {

	/**
	 * Settings Manager
	 *
	 * @var SettingsManager
	 */
	private SettingsManager $settings_manager;

	/**
	 * Contact Resource
	 *
	 * @var ContactResource
	 */
	private ContactResource $contact_resource;

	/**
	 * Tag manager helper
	 */
	private ?TagManager $tag_manager = null;

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

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
		$this->settings_manager = SettingsManager::get_instance();

		// Initialize ContactResource with Client
		$client                 = Client::get_instance();
		$this->contact_resource = new ContactResource( $client );
		$this->tag_manager      = TagManager::get_instance();

		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Only load in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Check if connection is verified
		if ( ! $this->settings_manager->is_connection_verified() ) {
			return;
		}

		// Limit GHL profile tools to site administrators
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add GHL section to user profile pages
		add_action( 'show_user_profile', [ $this, 'render_ghl_section' ], 10 );
		add_action( 'edit_user_profile', [ $this, 'render_ghl_section' ], 10 );

		// Save GHL data when profile is updated
		add_action( 'personal_options_update', [ $this, 'save_ghl_data' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_ghl_data' ] );

		// Enqueue Select2 and custom scripts via AssetsManager
		$this->register_assets();

		// AJAX handlers
		add_action( 'wp_ajax_ghl_crm_get_contact_data', [ $this, 'ajax_get_contact_data' ] );
		add_action( 'wp_ajax_ghl_crm_sync_user_now', [ $this, 'ajax_sync_user_now' ] );
		add_action( 'wp_ajax_ghl_crm_generate_login_link', [ $this, 'ajax_generate_login_link' ] );
		add_action( 'wp_ajax_ghl_crm_refresh_from_ghl', [ $this, 'ajax_refresh_from_ghl' ] );
	}

	/**
	 * Resolve the tag manager instance, reinstantiating if needed.
	 */
	private function get_tag_manager(): TagManager {
		if ( null === $this->tag_manager ) {
			$this->tag_manager = TagManager::get_instance();
		}

		return $this->tag_manager;
	}

	/**
	 * Build normalized tag pairs for client consumption.
	 */
	private function build_tag_pairs( array $raw_tags, array $tag_ids, array $normalized_pairs ): array {
		$tag_manager  = $this->get_tag_manager();
		$pairs_lookup = [];
		$raw_lookup   = [];
		$unique_pairs = [];
		$seen_ids     = [];

		foreach ( $normalized_pairs as $pair ) {
			$pair_id   = isset( $pair['id'] ) ? (string) $pair['id'] : '';
			$pair_name = isset( $pair['name'] ) ? (string) $pair['name'] : '';

			if ( '' === $pair_id && '' !== $pair_name ) {
				$pair_id = $pair_name;
			}

			if ( '' === $pair_id ) {
				continue;
			}

			if ( ! isset( $pairs_lookup[ $pair_id ] ) || '' === $pairs_lookup[ $pair_id ] ) {
				$pairs_lookup[ $pair_id ] = $pair_name;
			}
		}

		foreach ( $raw_tags as $raw_tag ) {
			if ( is_array( $raw_tag ) ) {
				$rid   = isset( $raw_tag['id'] ) ? (string) $raw_tag['id'] : '';
				$rname = isset( $raw_tag['name'] ) ? (string) $raw_tag['name'] : '';
				if ( '' !== $rid && '' !== $rname ) {
					$raw_lookup[ $rid ] = $rname;
				}
			}
		}

		if ( ! empty( $tag_ids ) ) {
			$tag_map = $tag_manager->map_ids_to_names( $tag_ids );

			foreach ( $tag_ids as $tag_id ) {
				$id = (string) $tag_id;
				if ( '' === $id || isset( $seen_ids[ $id ] ) ) {
					continue;
				}

				$name = $pairs_lookup[ $id ] ?? '';
				if ( '' === $name || $name === $id ) {
					$name = $tag_map[ $id ] ?? $name;
				}
				if ( '' === $name || $name === $id ) {
					$name = $raw_lookup[ $id ] ?? $name;
				}
				if ( '' === $name ) {
					$name = $id;
				}

				$unique_pairs[]  = [
					'id'   => $id,
					'name' => $name,
				];
				$seen_ids[ $id ] = true;
			}
		}

		if ( empty( $unique_pairs ) && ! empty( $pairs_lookup ) ) {
			foreach ( $pairs_lookup as $id => $name ) {
				$id = (string) $id;
				if ( '' === $id || isset( $seen_ids[ $id ] ) ) {
					continue;
				}
				$unique_pairs[]  = [
					'id'   => $id,
					'name' => '' !== $name ? $name : $id,
				];
				$seen_ids[ $id ] = true;
			}
		}

		if ( empty( $unique_pairs ) && ! empty( $raw_tags ) ) {
			foreach ( $raw_tags as $raw_tag ) {
				if ( is_array( $raw_tag ) ) {
					$rid   = isset( $raw_tag['id'] ) ? (string) $raw_tag['id'] : '';
					$rname = isset( $raw_tag['name'] ) ? (string) $raw_tag['name'] : '';

					$id = '' !== $rid ? $rid : $rname;
					if ( '' === $id || isset( $seen_ids[ $id ] ) ) {
						continue;
					}

					$unique_pairs[]  = [
						'id'   => $id,
						'name' => '' !== $rname ? $rname : $id,
					];
					$seen_ids[ $id ] = true;
				} else {
					$value = trim( (string) $raw_tag );
					if ( '' === $value || isset( $seen_ids[ $value ] ) ) {
						continue;
					}

					$unique_pairs[]     = [
						'id'   => $value,
						'name' => $value,
					];
					$seen_ids[ $value ] = true;
				}
			}
		}

		return $unique_pairs;
	}

	/**
	 * Register assets via AssetsManager for user profile screens.
	 */
	private function register_assets(): void {
		$assets_manager = \GHL_CRM\Core\AssetsManager::get_instance();
		$screens        = array( 'profile', 'user-edit' );

		// Globals CSS (Select2 custom styling: checkboxes, borders, highlight colors).
		$assets_manager->add_admin_asset(
			'ghl-crm-globals-css',
			$screens,
			'globals.css',
			array(),
			array(),
			GHL_CRM_VERSION,
			false
		);

		// Settings CSS (button styles and layout).
		$assets_manager->add_admin_asset(
			'ghl-settings-css',
			$screens,
			'settings.css',
			array(),
			array(),
			GHL_CRM_VERSION,
			false
		);

		// User profile CSS.
		$assets_manager->add_admin_asset(
			'ghl-user-profile-css',
			$screens,
			'user-profile.css',
			array( 'ghl-crm-select2-css', 'ghl-crm-globals-css', 'ghl-settings-css' ),
			array(),
			GHL_CRM_VERSION,
			false
		);

		// User profile JS.
		$assets_manager->add_admin_asset(
			'ghl-user-profile-js',
			$screens,
			'user-profile.js',
			array( 'jquery', 'ghl-crm-select2' ),
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ghl_user_profile' ),
				'tags'    => \GHL_CRM\Sync\TagManager::get_instance()->get_tags_for_localization(),
				'strings' => array(
					'loading'        => __( 'Loading...', 'ghl-crm-integration' ),
					'syncSuccess'    => __( 'User synced successfully!', 'ghl-crm-integration' ),
					'syncError'      => __( 'Sync failed. Please try again.', 'ghl-crm-integration' ),
					'confirmSync'    => __( 'Are you sure you want to sync this user now?', 'ghl-crm-integration' ),
					'searchTags'     => __( 'Search or type to add tags...', 'ghl-crm-integration' ),
					'refreshSuccess' => __( 'Successfully synced from GoHighLevel!', 'ghl-crm-integration' ),
					'refreshError'   => __( 'Failed to sync from GoHighLevel. Please try again.', 'ghl-crm-integration' ),
					'syncToSuccess'  => __( 'Successfully queued for sync to GoHighLevel!', 'ghl-crm-integration' ),
					'syncToError'    => __( 'Failed to sync to GoHighLevel. Please try again.', 'ghl-crm-integration' ),
				),
			),
			GHL_CRM_VERSION
		);
	}

	/**
	 * Render GHL section on user profile page
	 *
	 * @param \WP_User $user User object
	 * @return void
	 */
	public function render_ghl_section( \WP_User $user ): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		// Get GHL data
		$location_id = $this->settings_manager->get_setting( 'location_id' ) ?: $this->settings_manager->get_setting( 'oauth_location_id' );
		$contact_id  = \GHL_CRM\Sync\TagManager::get_instance()->get_user_contact_id( $user->ID, $location_id );

		// Only show sync timestamps when the user actually has a contact on this location.
		$last_sync       = $contact_id ? get_user_meta( $user->ID, '_ghl_last_sync', true ) : '';
		$synced_on_reg   = $contact_id ? get_user_meta( $user->ID, '_ghl_synced_on_register', true ) : '';
		$tag_manager     = $this->get_tag_manager();
		$current_tag_ids = $contact_id ? $tag_manager->get_user_tag_ids( $user->ID ) : [];
		$current_tag_map = $contact_id ? $tag_manager->map_ids_to_names( $current_tag_ids ) : [];

		// Get settings
		$settings           = $this->settings_manager->get_settings_array();
		$location_id        = $settings['location_id'] ?? '';
		$white_label_domain = $settings['ghl_white_label_domain'] ?? '';

		// Determine base domain (white label or default)
		$base_domain = ! empty( $white_label_domain ) ? rtrim( $white_label_domain, '/' ) : 'https://app.leadconnectorhq.com';

		// Determine sync status
		$is_synced = ! empty( $contact_id );
		$sync_time = $last_sync ?: $synced_on_reg;

		?>
		<div class="ghl-profile-section">
			<h2>
				<span class="dashicons dashicons-cloud"></span>
				<?php esc_html_e( 'GoHighLevel Integration', 'ghl-crm-integration' ); ?>
			</h2>

			<div class="ghl-data-grid">
				<!-- Sync Status -->
				<div class="ghl-data-item">
					<label><?php esc_html_e( 'Sync Status', 'ghl-crm-integration' ); ?></label>
					<div class="value">
						<?php if ( $is_synced ) : ?>
							<span class="ghl-sync-status synced">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Synced', 'ghl-crm-integration' ); ?>
							</span>
						<?php else : ?>
							<span class="ghl-sync-status not-synced">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e( 'Not Synced', 'ghl-crm-integration' ); ?>
							</span>
						<?php endif; ?>
					</div>
				</div>

				<!-- Contact ID -->
				<div class="ghl-data-item">
					<label><?php esc_html_e( 'GHL Contact ID', 'ghl-crm-integration' ); ?></label>
					<div class="value <?php echo empty( $contact_id ) ? 'empty' : ''; ?>">
						<?php if ( ! empty( $contact_id ) && ! empty( $location_id ) ) : ?>
							<a href="<?php echo esc_url( sprintf( 'https://app.leadconnectorhq.com/v2/location/%s/contacts/detail/%s', $location_id, $contact_id ) ); ?>" 
								target="_blank" 
								rel="noopener noreferrer">
								<?php echo esc_html( $contact_id ); ?>
								<span class="dashicons dashicons-external" style="font-size: 14px;"></span>
							</a>
						<?php elseif ( ! empty( $contact_id ) ) : ?>
							<?php echo esc_html( $contact_id ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Not available', 'ghl-crm-integration' ); ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- Last Sync Time -->
				<div class="ghl-data-item">
					<label><?php esc_html_e( 'Last Synced', 'ghl-crm-integration' ); ?></label>
					<div class="value <?php echo empty( $sync_time ) ? 'empty' : ''; ?>">
						<?php
						if ( ! empty( $sync_time ) ) {
							$time_ago = human_time_diff( (int) $sync_time, current_time( 'timestamp' ) );
							printf(
								/* translators: %s: Time difference */
								esc_html__( '%s ago', 'ghl-crm-integration' ),
								esc_html( $time_ago )
							);
						} else {
							esc_html_e( 'Never', 'ghl-crm-integration' );
						}
						?>
					</div>
				</div>

				<!-- Location ID -->
				<div class="ghl-data-item">
					<label><?php esc_html_e( 'GHL Location', 'ghl-crm-integration' ); ?></label>
					<div class="value <?php echo empty( $location_id ) ? 'empty' : ''; ?>">
						<?php echo ! empty( $location_id ) ? esc_html( $location_id ) : esc_html__( 'Not configured', 'ghl-crm-integration' ); ?>
					</div>
				</div>
			</div>

			<!-- Tags Section -->
			<div class="ghl-tags-section">
				<h3><?php esc_html_e( 'Contact Tags', 'ghl-crm-integration' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Manage tags for this contact in GoHighLevel. Changes will be synced when you save the profile.', 'ghl-crm-integration' ); ?>
					<br>
					<strong><?php esc_html_e( 'Note:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Updates are processed in the background and may take up to 60 seconds to appear in your GoHighLevel.', 'ghl-crm-integration' ); ?>
					<br>
					<?php esc_html_e( 'Click "Update User" button at the bottom of this page to resync this user to GoHighLevel.', 'ghl-crm-integration' ); ?>
				</p>
				
				<select 
					name="ghl_contact_tags[]" 
					id="ghl-contact-tags" 
					class="ghl-tags-select" 
					multiple="multiple" 
					data-user-id="<?php echo esc_attr( $user->ID ); ?>"
					data-contact-id="<?php echo esc_attr( $contact_id ); ?>">
					<?php foreach ( $current_tag_map as $tag_id => $tag_name ) : ?>
						<option value="<?php echo esc_attr( $tag_id ); ?>" selected="selected">
							<?php echo esc_html( $tag_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Actions -->
			<div class="ghl-actions">
				<?php if ( ! empty( $contact_id ) ) : ?>
					<button 
						type="button" 
						class="ghl-button ghl-button-secondary ghl-refresh-from-ghl-btn" 
						data-user-id="<?php echo esc_attr( $user->ID ); ?>"
						data-contact-id="<?php echo esc_attr( $contact_id ); ?>">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Sync from GoHighLevel', 'ghl-crm-integration' ); ?>
					</button>
					
					<button 
						type="button" 
						class="ghl-button ghl-button-secondary ghl-sync-to-ghl-btn" 
						data-user-id="<?php echo esc_attr( $user->ID ); ?>">
						<span class="dashicons dashicons-upload"></span>
						<?php esc_html_e( 'Sync to GoHighLevel', 'ghl-crm-integration' ); ?>
					</button>
				<?php endif; ?>
				
				<?php if ( ! empty( $contact_id ) && ! empty( $location_id ) ) : ?>
					<button 
						type="button"
						class="ghl-button ghl-button-secondary ghl-view-in-ghl-btn"
						onclick="window.open('<?php echo esc_url( sprintf( '%s/v2/location/%s/contacts/detail/%s', $base_domain, $location_id, $contact_id ) ); ?>', '_blank', 'noopener,noreferrer')">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'View in GoHighLevel', 'ghl-crm-integration' ); ?>
					</button>
				<?php endif; ?>
				
				<span class="spinner ghl-loading"></span>
			</div>

			<!-- Auto Login Section (Admin Only) -->
			<?php if ( current_user_can( 'administrator' ) && $user->ID !== get_current_user_id() ) : ?>
				<div class="ghl-autologin-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
					<h3 style="margin-top:0px;"><?php esc_html_e( 'Admin Tools', 'ghl-crm-integration' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Generate a secure one-time login link to access this user account. Link expires in 15 minutes.', 'ghl-crm-integration' ); ?>
					</p>
					
					<div class="ghl-autologin-controls">
						<button 
							type="button" 
							class="ghl-button ghl-button-secondary ghl-generate-login-link" 
							data-user-id="<?php echo esc_attr( $user->ID ); ?>">
							<span class="dashicons dashicons-admin-network"></span>
							<?php esc_html_e( 'Generate Login Link', 'ghl-crm-integration' ); ?>
						</button>
						
						<div class="ghl-login-link-display" style="display: none; margin-top: 10px;">
							<input 
								type="text" 
								id="ghl-login-link-input" 
								class="regular-text" 
								readonly 
								style="width: 100%; max-width: 600px;">
							<button 
								type="button" 
								class="ghl-button ghl-button-secondary ghl-copy-login-link" 
								style="margin-left: 5px;">
								<span class="dashicons dashicons-clipboard"></span>
								<?php esc_html_e( 'Copy', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description" style="color: #d63638; margin-top: 10px;">
								<strong><?php esc_html_e( 'Warning:', 'ghl-crm-integration' ); ?></strong>
								<?php esc_html_e( 'This link grants full access to this user account. Expires in 15 minutes or after one use.', 'ghl-crm-integration' ); ?>
							</p>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- User Activity Log Section -->
			<div class="ghl-activity-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
				<?php
				$activity_data = $this->get_user_activity_logs( $user->ID, 50 );
				$activity_logs = $activity_data['logs'];
				$total_logs    = $activity_data['total'];
				?>
				
				<div class="ghl-activity-header-wrapper" style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;" data-toggle="ghl-activity-timeline">
					<h3 style="margin:0; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-clock" style="color: #2271b1;"></span>
						<?php esc_html_e( 'Activity Timeline', 'ghl-crm-integration' ); ?>
						<?php if ( $total_logs > 0 ) : ?>
							<span class="ghl-activity-count" style="background: #2271b1; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600;">
								<?php echo esc_html( number_format( $total_logs ) ); ?>
							</span>
						<?php endif; ?>
					</h3>
					<span class="dashicons dashicons-arrow-down-alt2 ghl-activity-toggle" style="color: #2271b1; transition: transform 0.3s ease;"></span>
				</div>
				
				<div class="ghl-activity-content-wrapper" style="display: none; margin-top: 15px;">
					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'Sync activities and events for this user.', 'ghl-crm-integration' ); ?>
					</p>
					
					<?php if ( ! empty( $activity_logs ) ) : ?>
						<div class="ghl-activity-timeline">
							<?php
							$per_page    = 10;
							$total_pages = ceil( count( $activity_logs ) / $per_page );

							for ( $page = 1; $page <= $total_pages; $page++ ) :
								$offset    = ( $page - 1 ) * $per_page;
								$page_logs = array_slice( $activity_logs, $offset, $per_page );
								?>
								<div class="ghl-activity-page" data-page="<?php echo esc_attr( $page ); ?>" style="<?php echo 1 === $page ? '' : 'display: none;'; ?>">
									<?php foreach ( $page_logs as $log ) : ?>
										<?php
										$status_class = strtolower( $log['status'] ?? 'unknown' );
										$icon         = 'yes-alt';
										if ( 'failed' === $status_class ) {
											$icon = 'dismiss';
										} elseif ( 'pending' === $status_class ) {
											$icon = 'clock';
										}
										?>
										<div class="ghl-activity-item ghl-activity-<?php echo esc_attr( $status_class ); ?>">
											<div class="ghl-activity-icon">
												<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
											</div>
											<div class="ghl-activity-content">
												<div class="ghl-activity-header">
													<strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $log['action'] ?? 'Unknown' ) ) ); ?></strong>
													<span class="ghl-activity-status ghl-status-<?php echo esc_attr( $status_class ); ?>">
														<?php echo esc_html( ucfirst( $log['status'] ?? 'Unknown' ) ); ?>
													</span>
												</div>
												<div class="ghl-activity-message">
													<?php echo esc_html( $log['message'] ?? '' ); ?>
												</div>
												<div class="ghl-activity-time">
													<?php
													if ( ! empty( $log['created_at'] ) ) {
														$timestamp = strtotime( $log['created_at'] );
														if ( $timestamp ) {
															$time_ago = human_time_diff( $timestamp, current_time( 'timestamp' ) );
															printf(
																/* translators: %s: Time difference */
																esc_html__( '%s ago', 'ghl-crm-integration' ),
																esc_html( $time_ago )
															);
														}
													}
													?>
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endfor; ?>
						</div>
						
						<?php if ( $total_pages > 1 ) : ?>
							<div class="ghl-activity-pagination" style="margin-top: 20px; display: flex; align-items: center; justify-content: space-between; padding: 10px; background: #f6f7f7; border-radius: 4px;">
								<button type="button" class="ghl-button ghl-button-small ghl-button-secondary ghl-activity-prev" style="display: none;">
									<span class="dashicons dashicons-arrow-left-alt2"></span>
									<?php esc_html_e( 'Previous', 'ghl-crm-integration' ); ?>
								</button>
								<span class="ghl-activity-page-info" style="color: #50575e; font-size: 13px;">
									<?php
									printf(
										/* translators: 1: Current page, 2: Total pages */
										esc_html__( 'Page %1$d of %2$d', 'ghl-crm-integration' ),
										'<span class="ghl-current-page">1</span>',
										esc_html( $total_pages )
									);
									?>
								</span>
								<button type="button" class="ghl-button ghl-button-small ghl-button-secondary ghl-activity-next" data-total-pages="<?php echo esc_attr( $total_pages ); ?>">
									<?php esc_html_e( 'Next', 'ghl-crm-integration' ); ?>
									<span class="dashicons dashicons-arrow-right-alt2"></span>
								</button>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'No activity recorded yet.', 'ghl-crm-integration' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<?php wp_nonce_field( 'ghl_save_user_data', 'ghl_user_nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Get user activity logs from sync log table
	 *
	 * @param int $user_id User ID
	 * @param int $limit   Number of logs to retrieve
	 * @return array Array with 'logs' and 'total' count
	 */
	private function get_user_activity_logs( int $user_id, int $limit = 50 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ghl_sync_log';

		// Get total count for this user
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Counting user activity logs.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				 FROM {$table_name} 
				 WHERE sync_type = 'user' 
				   AND item_id = %d 
				   AND site_id = %d",
				$user_id,
				get_current_blog_id()
			)
		);

		// Get logs for this user
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reading user activity from plugin sync log table.
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sync_type, item_id, action, status, message, created_at, ghl_id 
				 FROM {$table_name} 
				 WHERE sync_type = 'user' 
				   AND item_id = %d 
				   AND site_id = %d 
				 ORDER BY created_at DESC 
				 LIMIT %d",
				$user_id,
				get_current_blog_id(),
				$limit
			),
			ARRAY_A
		);

		return [
			'logs'  => $logs ?: [],
			'total' => $total,
		];
	}

	/**
	 * Save GHL data when user profile is updated
	 *
	 * @param int $user_id User ID
	 * @return void
	 */
	public function save_ghl_data( int $user_id ): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['ghl_user_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ghl_user_nonce'] ) ), 'ghl_save_user_data' ) ) {
			return;
		}

		// Get submitted tags
		$submitted_tags = isset( $_POST['ghl_contact_tags'] ) && is_array( $_POST['ghl_contact_tags'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['ghl_contact_tags'] ) )
			: [];

		$tag_manager = $this->get_tag_manager();
		$current_ids = $tag_manager->get_user_tag_ids( $user_id );
		$normalized  = $tag_manager->normalize_tag_input( $submitted_tags );
		$new_ids     = $normalized['ids'];

		if ( $new_ids !== $current_ids ) {
			$stored_ids  = $tag_manager->store_user_tags( $user_id, $submitted_tags );
			$location_id = $this->settings_manager->get_setting( 'location_id' ) ?: $this->settings_manager->get_setting( 'oauth_location_id' );
			$contact_id  = \GHL_CRM\Sync\TagManager::get_instance()->get_user_contact_id( $user_id, $location_id );

			if ( ! empty( $contact_id ) ) {
				$payload_tags = $tag_manager->prepare_tags_for_payload( $stored_ids, $normalized['pairs'] ?? [] );
				$this->sync_tags_to_ghl( $contact_id, $payload_tags );
			}

			// Note: ghl_crm_user_tags_updated hook is now fired automatically
			// inside TagManager::store_user_tags() when tags change.
		}
	}

	/**
	 * Sync tags to GoHighLevel
	 *
	 * @param string $contact_id Contact ID
	 * @param array  $tags       Tags array
	 * @return bool Success status
	 */
	private function sync_tags_to_ghl( string $contact_id, array $tags ): bool {
		try {
			// Update contact with new tags
			$result = $this->contact_resource->update( $contact_id, [ 'tags' => $tags ] );
			return ! empty( $result );
		} catch ( \Exception $e ) {

			return false;
		}
	}

	/**
	 * AJAX: Get contact data from GHL
	 *
	 * @return void
	 */
	public function ajax_get_contact_data(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_user_profile', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ] );
		}

		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;

		if ( empty( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user ID', 'ghl-crm-integration' ) ] );
		}

		$location_id = $this->settings_manager->get_setting( 'location_id' ) ?: $this->settings_manager->get_setting( 'oauth_location_id' );
		$contact_id  = \GHL_CRM\Sync\TagManager::get_instance()->get_user_contact_id( $user_id, $location_id );

		if ( empty( $contact_id ) ) {
			wp_send_json_error( [ 'message' => __( 'User not synced to GHL', 'ghl-crm-integration' ) ] );
		}

		try {
			$tag_manager = $this->get_tag_manager();
			$client      = Client::get_instance();
			$contact     = $client->get( "contacts/{$contact_id}" );

			if ( ! empty( $contact ) ) {
				$raw_tags = [];
				if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) ) {
					$raw_tags = $contact['tags'];
				}

				$normalized = $tag_manager->normalize_tag_input( $raw_tags );
				$tag_ids    = $tag_manager->store_user_tags( $user_id, $raw_tags );
				$tag_pairs  = $this->build_tag_pairs( $raw_tags, $tag_ids, $normalized['pairs'] );

				$contact['tags']      = array_map(
					static function ( array $pair ): string {
						return isset( $pair['name'] ) ? (string) $pair['name'] : '';
					},
					$tag_pairs
				);
				$contact['tag_ids']   = array_map(
					static function ( array $pair ): string {
						return isset( $pair['id'] ) ? (string) $pair['id'] : '';
					},
					$tag_pairs
				);
				$contact['tag_pairs'] = $tag_pairs;

				wp_send_json_success( $contact );
			} else {
				wp_send_json_error( [ 'message' => __( 'Contact not found', 'ghl-crm-integration' ) ] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * AJAX: Sync user now
	 *
	 * @return void
	 */
	public function ajax_sync_user_now(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_user_profile', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ] );
		}

		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;

		if ( empty( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user ID', 'ghl-crm-integration' ) ] );
		}

		// Get user
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( [ 'message' => __( 'User not found', 'ghl-crm-integration' ) ] );
		}

		// Trigger sync via UserHooks
		$user_hooks = \GHL_CRM\Integrations\Users\UserHooks::get_instance();

		// Prepare contact data
		$contact_data = apply_filters( 'ghl_crm_prepare_user_contact_data', [], $user );

		// Add to queue
		$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
		$queue_id      = $queue_manager->add_to_queue( 'user', $user_id, 'profile_update', $contact_data );

		if ( $queue_id ) {
			wp_send_json_success(
				[
					'message'  => __( 'User queued for sync successfully', 'ghl-crm-integration' ),
					'queue_id' => $queue_id,
				]
			);
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to queue user for sync', 'ghl-crm-integration' ) ] );
		}
	}

	/**
	 * AJAX: Generate auto-login link.
	 *
	 * @return void
	 */
	public function ajax_generate_login_link(): void {
		// Verify nonce.
		check_ajax_referer( 'ghl_user_profile', 'nonce' );

		// Check permissions - only administrators.
		if ( ! current_user_can( 'administrator' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ] );
		}

		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;

		if ( empty( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user ID', 'ghl-crm-integration' ) ] );
		}

		// Don't allow generating link for current user.
		if ( $user_id === get_current_user_id() ) {
			wp_send_json_error( [ 'message' => __( 'Cannot generate login link for yourself', 'ghl-crm-integration' ) ] );
		}

		// Verify user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( [ 'message' => __( 'User not found', 'ghl-crm-integration' ) ] );
		}

		try {
			$auto_login_manager = \GHL_CRM\Auth\AutoLoginManager::get_instance();
			$token_data         = $auto_login_manager->generate_token( $user_id );

			// Get WordPress date/time format using SettingsManager (multisite-aware)
			$date_format = $this->settings_manager->get_option( 'date_format' );
			$time_format = $this->settings_manager->get_option( 'time_format' );

			wp_send_json_success(
				[
					'login_url' => $token_data['login_url'],
					'expires'   => date_i18n( $date_format . ' ' . $time_format, $token_data['expires'] ),
					'message'   => __( 'Login link generated successfully', 'ghl-crm-integration' ),
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Refresh user data from GHL (public wrapper for queue manager)
	 *
	 * @param int    $user_id User ID
	 * @param string $contact_id GHL Contact ID
	 * @return bool Success status
	 */
	public function refresh_user_from_ghl( int $user_id, string $contact_id ): bool {
		if ( empty( $user_id ) || empty( $contact_id ) ) {
			return false;
		}

		try {
			$tag_manager = $this->get_tag_manager();
			$client      = Client::get_instance();

			// Fetch fresh contact data from GHL
			$response = $client->get( "contacts/{$contact_id}" );

			if ( empty( $response['contact'] ) ) {
				return false;
			}

			$contact = $response['contact'];

			// Update user meta with fresh data
			$location_id = $this->settings_manager->get_setting( 'location_id' ) ?: $this->settings_manager->get_setting( 'oauth_location_id' );
			$tag_manager->store_user_contact_id( $user_id, $contact_id, $location_id );
			update_user_meta( $user_id, '_ghl_last_sync', time() );
			$raw_tags = [];
			if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) ) {
				$raw_tags = $contact['tags'];
			}
			$tag_manager->store_user_tags( $user_id, $raw_tags );

			// Update contact type if available
			if ( ! empty( $contact['type'] ) ) {
				update_user_meta( $user_id, TagManager::scoped_meta_key( '_ghl_contact_type' ), $contact['type'] );
			}

			return true;

		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * AJAX: Refresh user data from GoHighLevel
	 * Fetches fresh contact data from GHL and updates local WordPress cache
	 *
	 * @return void
	 */
	public function ajax_refresh_from_ghl(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_user_profile', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ] );
		}

		$user_id    = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		$contact_id = isset( $_POST['contact_id'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_id'] ) ) : '';

		if ( empty( $user_id ) || empty( $contact_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user ID or contact ID', 'ghl-crm-integration' ) ] );
		}

		try {
			$tag_manager = $this->get_tag_manager();
			$client      = Client::get_instance();

			// Fetch fresh contact data from GHL
			$response = $client->get( "contacts/{$contact_id}" );

			if ( empty( $response['contact'] ) ) {
				wp_send_json_error( [ 'message' => __( 'Contact not found in GoHighLevel', 'ghl-crm-integration' ) ] );
			}

			$contact = $response['contact'];

			// Update user meta with fresh data
			$location_id = $this->settings_manager->get_setting( 'location_id' ) ?: $this->settings_manager->get_setting( 'oauth_location_id' );
			$tag_manager->store_user_contact_id( $user_id, $contact_id, $location_id );
			update_user_meta( $user_id, '_ghl_last_sync', time() );
			$raw_tags = [];
			if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) ) {
				$raw_tags = $contact['tags'];
			}
			$normalized           = $tag_manager->normalize_tag_input( $raw_tags );
			$tag_ids              = $tag_manager->store_user_tags( $user_id, $raw_tags );
			$tag_pairs            = $this->build_tag_pairs( $raw_tags, $tag_ids, $normalized['pairs'] );
			$tag_names            = array_map(
				static function ( array $pair ): string {
					return isset( $pair['name'] ) ? (string) $pair['name'] : '';
				},
				$tag_pairs
			);
			$tag_id_list          = array_map(
				static function ( array $pair ): string {
					return isset( $pair['id'] ) ? (string) $pair['id'] : '';
				},
				$tag_pairs
			);
			$contact['tags']      = $tag_names;
			$contact['tag_ids']   = $tag_id_list;
			$contact['tag_pairs'] = $tag_pairs;

			// Update contact type if available
			if ( ! empty( $contact['type'] ) ) {
				update_user_meta( $user_id, TagManager::scoped_meta_key( '_ghl_contact_type' ), $contact['type'] );
			}

			wp_send_json_success(
				[
					'message'   => __( 'Successfully synced from GoHighLevel!', 'ghl-crm-integration' ),
					'contact'   => $contact,
					'tags'      => $tag_names,
					'tag_ids'   => $tag_id_list,
					'tag_pairs' => $tag_pairs,
					'type'      => $contact['type'] ?? 'lead',
				]
			);

		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %s: Error message */
						__( 'Failed to sync from GoHighLevel: %s', 'ghl-crm-integration' ),
						$e->getMessage()
					),
				]
			);
		}
	}

	/**
	 * Initialize (called by Loader)
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}