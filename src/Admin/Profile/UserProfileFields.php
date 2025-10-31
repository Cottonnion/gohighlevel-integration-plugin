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
		add_action( 'wp_ajax_ghl_crm_get_available_tags', [ $this, 'ajax_get_available_tags' ] );
		add_action( 'wp_ajax_ghl_crm_sync_user_now', [ $this, 'ajax_sync_user_now' ] );
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

		// Enqueue Select2
		wp_enqueue_style( 
			'select2', 
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
			[],
			'4.1.0'
		);
		
		wp_enqueue_script( 
			'select2', 
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
			[ 'jquery' ],
			'4.1.0',
			true
		);

		// Enqueue custom styles
		wp_enqueue_style(
			'ghl-user-profile',
			GHL_CRM_URL . 'assets/admin/css/user-profile.css',
			[ 'select2' ],
			'1.0.0'
		);

		// Enqueue custom script
		wp_enqueue_script(
			'ghl-user-profile-js',
			GHL_CRM_URL . 'assets/admin/js/user-profile.js',
			[ 'jquery', 'select2' ],
			'1.0.0',
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
					'loading'      => __( 'Loading...', 'ghl-crm-integration' ),
					'syncSuccess'  => __( 'User synced successfully!', 'ghl-crm-integration' ),
					'syncError'    => __( 'Sync failed. Please try again.', 'ghl-crm-integration' ),
					'confirmSync'  => __( 'Are you sure you want to sync this user now?', 'ghl-crm-integration' ),
					'searchTags'   => __( 'Search or type to add tags...', 'ghl-crm-integration' ),
				],
			]
		);

		// Add custom styles
		// wp_add_inline_style( 'select2', $this->get_custom_styles() );
	}

	/**
	 * Get custom CSS styles
	 *
	 * @return string CSS styles
	 */
	private function get_custom_styles(): string {
		return "
			.ghl-profile-section {
				background: #fff;
				border: 1px solid #ccd0d4;
				padding: 20px;
				margin-top: 20px;
				border-radius: 4px;
			}
			.ghl-profile-section h2 {
				margin-top: 0;
				padding-bottom: 10px;
				border-bottom: 1px solid #e5e5e5;
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.ghl-profile-section h2 .dashicons {
				color: #2271b1;
			}
			.ghl-data-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
				gap: 15px;
				margin: 20px 0;
			}
			.ghl-data-item {
				display: flex;
				flex-direction: column;
			}
			.ghl-data-item label {
				font-weight: 600;
				margin-bottom: 5px;
				color: #1d2327;
			}
			.ghl-data-item .value {
				color: #50575e;
				font-family: monospace;
				background: #f6f7f7;
				padding: 8px 12px;
				border-radius: 3px;
				border: 1px solid #dcdcde;
			}
			.ghl-data-item .value.empty {
				color: #a0a5aa;
				font-style: italic;
			}
			.ghl-data-item .value a {
				color: #2271b1;
				text-decoration: none;
			}
			.ghl-data-item .value a:hover {
				text-decoration: underline;
			}
			.ghl-sync-status {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 6px 12px;
				border-radius: 3px;
				font-size: 13px;
			}
			.ghl-sync-status.synced {
				background: #d4edda;
				color: #155724;
				border: 1px solid #c3e6cb;
			}
			.ghl-sync-status.not-synced {
				background: #fff3cd;
				color: #856404;
				border: 1px solid #ffeeba;
			}
			.ghl-sync-status .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
			}
			.ghl-tags-section {
				margin: 20px 0;
			}
			.ghl-tags-section .select2-container {
				width: 100% !important;
				max-width: 600px;
			}
			.ghl-actions {
				display: flex;
				gap: 10px;
				margin-top: 15px;
			}
			.ghl-actions .button {
				display: inline-flex;
				align-items: center;
				gap: 6px;
			}
			.ghl-loading {
				display: none;
				margin-left: 10px;
			}
			.ghl-loading.active {
				display: inline-block;
			}
		";
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
				<!-- <button 
					type="button" 
					class="button button-secondary ghl-sync-now-btn" 
					data-user-id="<?php echo esc_attr( $user->ID ); ?>">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Sync Now', 'ghl-crm-integration' ); ?>
				</button> -->
				
				<?php if ( ! empty( $contact_id ) && ! empty( $location_id ) ) : ?>
					<a 
						href="<?php echo esc_url( sprintf( 'https://app.leadconnectorhq.com/v2/location/%s/contacts/detail/%s', $location_id, $contact_id ) ); ?>" 
						class="button button-secondary" 
						target="_blank" 
						rel="noopener noreferrer">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'View in GoHighLevel', 'ghl-crm-integration' ); ?>
					</a>
				<?php endif; ?>
				
				<span class="spinner ghl-loading"></span>
			</div>

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
			error_log( 'GHL CRM: Failed to sync tags - ' . $e->getMessage() );
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
	 * AJAX: Get available tags from GHL
	 *
	 * @return void
	 */
	public function ajax_get_available_tags(): void {
		// Verify nonce
		check_ajax_referer( 'ghl_user_profile', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'ghl-crm-integration' ) ] );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		try {
			$client = Client::get_instance();
			
			// Get tags from GHL (Note: GHL API may not have a direct tags endpoint, so we'll use a cached approach)
			$tags = $this->get_cached_tags();

			// Filter by search term if provided
			if ( ! empty( $search ) ) {
				$tags = array_filter( $tags, function( $tag ) use ( $search ) {
					return stripos( $tag, $search ) !== false;
				});
			}

			wp_send_json_success( array_values( $tags ) );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * Get cached tags (from transient or fetch from GHL)
	 *
	 * @return array Tags array
	 */
	private function get_cached_tags(): array {
		$cache_key = 'ghl_available_tags_' . get_current_blog_id();
		$cached = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Fetch tags from GHL (collect from existing contacts)
		try {
			$client = Client::get_instance();
			$response = $client->get( 'contacts/', [ 'limit' => 100 ] );
			
			$tags = [];
			if ( ! empty( $response['contacts'] ) && is_array( $response['contacts'] ) ) {
				foreach ( $response['contacts'] as $contact ) {
					if ( ! empty( $contact['tags'] ) && is_array( $contact['tags'] ) ) {
						$tags = array_merge( $tags, $contact['tags'] );
					}
				}
			}

			$tags = array_unique( $tags );
			sort( $tags );

			// Cache for 1 hour
			set_transient( $cache_key, $tags, HOUR_IN_SECONDS );

			return $tags;
		} catch ( \Exception $e ) {
			error_log( 'GHL CRM: Failed to fetch tags - ' . $e->getMessage() );
			return [];
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
	 * Initialize (called by Loader)
	 *
	 * @return void
	 */
	public static function init(): void {
		self::get_instance();
	}
}
