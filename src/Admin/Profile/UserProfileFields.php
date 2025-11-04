<?php
declare(strict_types=1);

namespace GHL_CRM\Admin\Profile;

use GHL_CRM\Core\SettingsManager;
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

		// Add GHL section to user profile pages
		add_action( 'show_user_profile', [ $this, 'render_ghl_section' ], 10 );
		add_action( 'edit_user_profile', [ $this, 'render_ghl_section' ], 10 );

		// Save GHL data when profile is updated
		add_action( 'personal_options_update', [ $this, 'save_ghl_data' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_ghl_data' ] );

		// Enqueue Select2 and custom scripts
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// AJAX handlers
		add_action( 'wp_ajax_ghl_crm_get_contact_data', [ $this, 'ajax_get_contact_data' ] );
		add_action( 'wp_ajax_ghl_crm_sync_user_now', [ $this, 'ajax_sync_user_now' ] );
		add_action( 'wp_ajax_ghl_crm_generate_login_link', [ $this, 'ajax_generate_login_link' ] );
		add_action( 'wp_ajax_ghl_crm_refresh_from_ghl', [ $this, 'ajax_refresh_from_ghl' ] );
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		// Only load on user edit pages
		if ( ! in_array( $hook, [ 'profile.php', 'user-edit.php' ], true ) ) {
			return;
		}

		// Enqueue Select2 (local files)
		wp_enqueue_style( 
			'select2', 
			GHL_CRM_URL . 'assets/admin/css/select2.min.css',
			[],
			'1.0.0'
		);
		
		wp_enqueue_script( 
			'select2', 
			GHL_CRM_URL . 'assets/admin/js/select2.min.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);

		// Enqueue settings CSS (for button styles and layout)
		wp_enqueue_style(
			'ghl-settings',
			GHL_CRM_URL . 'assets/admin/css/settings.css',
			[],
			'1.0.0'
		);

		// Enqueue custom styles
		wp_enqueue_style(
			'ghl-user-profile',
			GHL_CRM_URL . 'assets/admin/css/user-profile.css',
			[ 'select2', 'ghl-settings' ],
			'1.0.0'
		);

		// Enqueue custom script
		wp_enqueue_script(
			'ghl-user-profile-js',
			GHL_CRM_URL . 'assets/admin/js/user-profile.js',
			[ 'jquery', 'select2' ],
			'1.0.1',
			true
		);

		// Localize script
		wp_localize_script(
			'ghl-user-profile-js',
			'ghlUserProfile',
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'ghl_user_profile' ),
				'strings'       => [
					'loading'         => __( 'Loading...', 'ghl-crm-integration' ),
					'syncSuccess'     => __( 'User synced successfully!', 'ghl-crm-integration' ),
					'syncError'       => __( 'Sync failed. Please try again.', 'ghl-crm-integration' ),
					'confirmSync'     => __( 'Are you sure you want to sync this user now?', 'ghl-crm-integration' ),
					'searchTags'      => __( 'Search or type to add tags...', 'ghl-crm-integration' ),
					'refreshSuccess'  => __( 'Successfully synced from GoHighLevel!', 'ghl-crm-integration' ),
					'refreshError'    => __( 'Failed to sync from GoHighLevel. Please try again.', 'ghl-crm-integration' ),
					'syncToSuccess'   => __( 'Successfully queued for sync to GoHighLevel!', 'ghl-crm-integration' ),
					'syncToError'     => __( 'Failed to sync to GoHighLevel. Please try again.', 'ghl-crm-integration' ),
				],
			]
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
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		// Get GHL data
		$contact_id     = get_user_meta( $user->ID, '_ghl_contact_id', true );
		$last_sync      = get_user_meta( $user->ID, '_ghl_last_sync', true );
		$synced_on_reg  = get_user_meta( $user->ID, '_ghl_synced_on_register', true );
		$current_tags   = get_user_meta( $user->ID, '_ghl_contact_tags', true );
		
		if ( ! is_array( $current_tags ) ) {
			$current_tags = [];
		}

		// Get settings
		$settings    = $this->settings_manager->get_settings_array();
		$location_id = $settings['location_id'] ?? '';

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
					<?php foreach ( $current_tags as $tag ) : ?>
						<option value="<?php echo esc_attr( $tag ); ?>" selected="selected">
							<?php echo esc_html( $tag ); ?>
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
						onclick="window.open('<?php echo esc_url( sprintf( 'https://app.leadconnectorhq.com/v2/location/%s/contacts/detail/%s', $location_id, $contact_id ) ); ?>', '_blank', 'noopener,noreferrer')">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'View in GoHighLevel', 'ghl-crm-integration' ); ?>
					</button>
				<?php endif; ?>
				
				<span class="spinner ghl-loading"></span>
			</div>

			<!-- Auto Login Section (Admin Only) -->
			<?php if ( current_user_can( 'administrator' ) && $user->ID !== get_current_user_id() ) : ?>
				<div class="ghl-autologin-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
					<h3><?php esc_html_e( 'Admin Tools', 'ghl-crm-integration' ); ?></h3>
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

			<?php wp_nonce_field( 'ghl_save_user_data', 'ghl_user_nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Save GHL data when user profile is updated
	 *
	 * @param int $user_id User ID
	 * @return void
	 */
	public function save_ghl_data( int $user_id ): void {
		// Check permissions
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
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

		// Get current tags
		$current_tags = get_user_meta( $user_id, '_ghl_contact_tags', true );
		if ( ! is_array( $current_tags ) ) {
			$current_tags = [];
		}

		// Only sync if tags changed
		if ( $submitted_tags !== $current_tags ) {
			// Update tags in meta
			update_user_meta( $user_id, '_ghl_contact_tags', $submitted_tags );

			// Get contact ID
			$contact_id = get_user_meta( $user_id, '_ghl_contact_id', true );

			if ( ! empty( $contact_id ) ) {
				// Sync tags to GHL
				$this->sync_tags_to_ghl( $contact_id, $submitted_tags );
			}
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
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ] );
		}

		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;

		if ( empty( $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user ID', 'ghl-crm-integration' ) ] );
		}

		$contact_id = get_user_meta( $user_id, '_ghl_contact_id', true );

		if ( empty( $contact_id ) ) {
			wp_send_json_error( [ 'message' => __( 'User not synced to GHL', 'ghl-crm-integration' ) ] );
		}

		try {
			$client = Client::get_instance();
			$contact = $client->get( "contacts/{$contact_id}" );

			if ( ! empty( $contact ) ) {
				// Update cached tags
				if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) ) {
					update_user_meta( $user_id, '_ghl_contact_tags', $contact['tags'] );
				}

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
		if ( ! current_user_can( 'edit_users' ) ) {
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
		$queue_id = $queue_manager->add_to_queue( 'user', $user_id, 'profile_update', $contact_data );

		if ( $queue_id ) {
			wp_send_json_success( [
				'message' => __( 'User queued for sync successfully', 'ghl-crm-integration' ),
				'queue_id' => $queue_id,
			] );
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
			$auto_login_manager = \GHL_CRM\Core\AutoLoginManager::get_instance();
			$token_data = $auto_login_manager->generate_token( $user_id );

			// Get WordPress date/time format using SettingsManager (multisite-aware)
			$date_format = $this->settings_manager->get_option( 'date_format' );
			$time_format = $this->settings_manager->get_option( 'time_format' );

			wp_send_json_success( [
				'login_url' => $token_data['login_url'],
				'expires'   => date_i18n( $date_format . ' ' . $time_format, $token_data['expires'] ),
				'message'   => __( 'Login link generated successfully', 'ghl-crm-integration' ),
			] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
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
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ] );
		}

		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		$contact_id = isset( $_POST['contact_id'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_id'] ) ) : '';

		if ( empty( $user_id ) || empty( $contact_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user ID or contact ID', 'ghl-crm-integration' ) ] );
		}

		try {
			$client = Client::get_instance();
			
			// Fetch fresh contact data from GHL
			$response = $client->get( "contacts/{$contact_id}" );

			if ( empty( $response['contact'] ) ) {
				wp_send_json_error( [ 'message' => __( 'Contact not found in GoHighLevel', 'ghl-crm-integration' ) ] );
			}

			$contact = $response['contact'];

			// Update user meta with fresh data
			update_user_meta( $user_id, '_ghl_contact_id', $contact_id );
			update_user_meta( $user_id, '_ghl_last_sync', time() );

			// Update tags if available
			if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) ) {
				update_user_meta( $user_id, '_ghl_contact_tags', $contact['tags'] );
			}

			// Update contact type if available
			if ( ! empty( $contact['type'] ) ) {
				update_user_meta( $user_id, '_ghl_contact_type', $contact['type'] );
			}

			wp_send_json_success( [
				'message' => __( 'Successfully synced from GoHighLevel!', 'ghl-crm-integration' ),
				'contact' => $contact,
				'tags' => $contact['tags'] ?? [],
				'type' => $contact['type'] ?? 'lead',
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [ 
				'message' => sprintf( 
					/* translators: %s: Error message */
					__( 'Failed to sync from GoHighLevel: %s', 'ghl-crm-integration' ),
					$e->getMessage()
				)
			] );
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
