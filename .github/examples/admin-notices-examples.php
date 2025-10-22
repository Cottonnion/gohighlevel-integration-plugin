<?php
/**
 * Examples: Using the Admin Notices System
 *
 * This file demonstrates various ways to use the AdminNotices system
 * throughout the plugin. These are examples only - implement as needed.
 *
 * @package GHL_CRM_Integration
 */

// Example 1: Simple success message after settings save
function example_settings_saved() {
	$notices = \GHL_CRM\Core\AdminNotices::get_instance();
	$notices->success( __( 'Settings saved successfully!', 'ghl-crm-integration' ) );
}

// Example 2: Error message with global display
function example_api_connection_failed() {
	$notices = \GHL_CRM\Core\AdminNotices::get_instance();
	$notices->error( 
		__( 'Failed to connect to GoHighLevel API. Please check your credentials.', 'ghl-crm-integration' ),
		true // Show on all admin pages
	);
}

// Example 3: Warning about expiring token
function example_token_expiring_soon() {
	$notices = \GHL_CRM\Core\AdminNotices::get_instance();
	$notices->warning( 
		__( 'Your API token will expire in 7 days. Please renew your connection.', 'ghl-crm-integration' )
	);
}

// Example 4: Info message about background process
function example_background_sync() {
	$notices = \GHL_CRM\Core\AdminNotices::get_instance();
	$notices->info( 
		__( 'Syncing 250 contacts in the background. This may take a few minutes.', 'ghl-crm-integration' )
	);
}

// Example 5: Display exception message
function example_handle_sync_exception() {
	try {
		// Some operation that might fail
		throw new Exception( 'Contact sync failed: Invalid API response' );
	} catch ( Exception $e ) {
		$notices = \GHL_CRM\Core\AdminNotices::get_instance();
		$notices->from_exception( $e );
	}
}

// Example 6: Custom notice via action hook
add_action( 'ghl_crm_settings_notices', function() {
	$pending_count = get_option( 'ghl_pending_contacts', 0 );
	
	if ( $pending_count > 50 ) {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Sync Queue Alert:', 'ghl-crm-integration' ); ?></strong>
				<?php 
				printf(
					/* translators: %d: Number of pending contacts */
					esc_html__( 'You have %d contacts waiting to be synced.', 'ghl-crm-integration' ),
					$pending_count
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-sync' ) ); ?>">
					<?php esc_html_e( 'View Queue', 'ghl-crm-integration' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
});

// Example 7: Use in AJAX handler
function example_ajax_response() {
	// After processing AJAX request
	if ( $success ) {
		$notices = \GHL_CRM\Core\AdminNotices::get_instance();
		$notices->success( 
			__( 'Contact synced successfully!', 'ghl-crm-integration' )
		);
		wp_send_json_success();
	} else {
		$notices = \GHL_CRM\Core\AdminNotices::get_instance();
		$notices->error( 
			__( 'Failed to sync contact. Please try again.', 'ghl-crm-integration' )
		);
		wp_send_json_error();
	}
}

// Example 8: Notice after redirect
function example_oauth_callback() {
	if ( isset( $_GET['code'] ) ) {
		// Process OAuth callback
		try {
			// OAuth logic...
			
			$notices = \GHL_CRM\Core\AdminNotices::get_instance();
			$notices->success( 
				__( 'Successfully connected to GoHighLevel!', 'ghl-crm-integration' ),
				true
			);
			
			// Redirect (notice will persist via transient)
			wp_safe_redirect( admin_url( 'admin.php?page=ghl-crm-settings' ) );
			exit;
			
		} catch ( Exception $e ) {
			$notices = \GHL_CRM\Core\AdminNotices::get_instance();
			$notices->error( 
				__( 'OAuth connection failed: ', 'ghl-crm-integration' ) . $e->getMessage(),
				true
			);
			
			wp_safe_redirect( admin_url( 'admin.php?page=ghl-crm-settings' ) );
			exit;
		}
	}
}

// Example 9: Conditional notice based on settings
function example_check_configuration() {
	$settings = get_option( 'ghl_crm_settings', [] );
	
	if ( empty( $settings['oauth_access_token'] ) && empty( $settings['api_token'] ) ) {
		$notices = \GHL_CRM\Core\AdminNotices::get_instance();
		$notices->warning( 
			sprintf(
				/* translators: %s: Settings page URL */
				__( 'GoHighLevel is not connected. <a href="%s">Connect now</a>', 'ghl-crm-integration' ),
				admin_url( 'admin.php?page=ghl-crm-settings' )
			),
			true // Show on all admin pages until configured
		);
	}
}

// Example 10: Notice with multiple variables
function example_sync_summary( $synced, $failed, $skipped ) {
	$notices = \GHL_CRM\Core\AdminNotices::get_instance();
	
	if ( $failed > 0 ) {
		$notices->warning(
			sprintf(
				/* translators: 1: synced count, 2: failed count, 3: skipped count */
				__( 'Sync completed: %1$d synced, %2$d failed, %3$d skipped.', 'ghl-crm-integration' ),
				$synced,
				$failed,
				$skipped
			)
		);
	} else {
		$notices->success(
			sprintf(
				/* translators: 1: synced count, 2: skipped count */
				__( 'Sync completed successfully: %1$d synced, %2$d skipped.', 'ghl-crm-integration' ),
				$synced,
				$skipped
			)
		);
	}
}
