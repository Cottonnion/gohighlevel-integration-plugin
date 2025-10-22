<?php
/**
 * Template: Settings Page
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get settings manager instance
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();

// Get OAuth handler
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$is_connected  = $oauth_status['connected'];

// Check for OAuth callback messages
$oauth_message = '';
$oauth_error   = '';
if ( isset( $_GET['oauth'] ) ) {
	if ( 'success' === $_GET['oauth'] ) {
		$oauth_message = __( 'Successfully connected to GoHighLevel!', 'ghl-crm-integration' );
	} elseif ( 'error' === $_GET['oauth'] && isset( $_GET['message'] ) ) {
		$oauth_error = sanitize_text_field( wp_unslash( $_GET['message'] ) );
	}
}

// Handle OAuth disconnect
if ( isset( $_POST['ghl_disconnect_oauth'] ) && check_admin_referer( 'ghl_disconnect_oauth', 'ghl_disconnect_nonce' ) ) {
	$oauth_handler->disconnect();
	wp_safe_redirect( add_query_arg( 'oauth', 'disconnected', admin_url( 'admin.php?page=ghl-crm-settings' ) ) );
	exit;
}

// Check for disconnect success message
if ( isset( $_GET['oauth'] ) && 'disconnected' === $_GET['oauth'] ) {
	$oauth_message = __( 'Successfully disconnected from GoHighLevel.', 'ghl-crm-integration' );
	// Refresh connection status
	$oauth_status  = $oauth_handler->get_connection_status();
	$is_connected  = $oauth_status['connected'];
}

?>
<div class="wrap ghl-crm-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<!-- Dynamic notification area -->
	<div id="ghl-settings-notice" style="display: none;"></div>
	
	<?php if ( $oauth_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $oauth_message ); ?></p>
		</div>
	<?php endif; ?>
	
	<?php if ( $oauth_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $oauth_error ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Action hook: Display admin notices on settings page
	 *
	 * Use this hook to display custom notices from anywhere in the plugin.
	 * 
	 * Example usage:
	 * add_action( 'ghl_crm_settings_notices', function() {
	 *     echo '<div class="notice notice-error is-dismissible"><p>Error message here</p></div>';
	 * });
	 *
	 * @since 1.0.0
	 */
	do_action( 'ghl_crm_settings_notices' );
	?>
	
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Getting Started:', 'ghl-crm-integration' ); ?></strong>
			<?php esc_html_e( 'Connect your GoHighLevel account using OAuth for secure, automatic authentication.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<div class="ghl-container ghl-crm-container">
		<div class="ghl-main-content ghl-crm-main-content">
	<?php if ( $is_connected ) : ?>
		<!-- OAuth Connected State -->
		<div class="ghl-oauth-connected">
			<h2><?php esc_html_e( 'Connection Status', 'ghl-crm-integration' ); ?></h2>
			
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Status', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<span class="ghl-status-badge ghl-status-connected">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Connected via OAuth', 'ghl-crm-integration' ); ?>
							</span>
							<?php if ( ! empty( $oauth_status['connected_at'] ) ) : ?>
								<p class="description">
									<?php 
									/* translators: %s: formatted date/time */
									printf( 
										esc_html__( 'Connected since: %s', 'ghl-crm-integration' ), 
										esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $oauth_status['connected_at'] ) ) ) 
									); 
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					
					<?php if ( ! empty( $oauth_status['location_id'] ) ) : ?>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Location ID', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<code><?php echo esc_html( $oauth_status['location_id'] ); ?></code>
						</td>
					</tr>
					<?php endif; ?>
					
					<tr>
						<th scope="row">
							<?php esc_html_e( 'API Version', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<code><?php echo esc_html( $settings['api_version'] ?? '2021-07-28' ); ?></code>
						</td>
					</tr>
					
					<?php if ( ! empty( $oauth_status['expires_at'] ) ) : ?>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Token Expires', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<?php 
							$expires_timestamp = intval( $oauth_status['expires_at'] );
							if ( $expires_timestamp > time() ) {
								/* translators: %s: formatted date/time */
								printf( 
									esc_html__( '%s (auto-refreshes)', 'ghl-crm-integration' ), 
									esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_timestamp ) ) 
								);
							} else {
								esc_html_e( 'Expired (will auto-refresh on next API call)', 'ghl-crm-integration' );
							}
							?>
						</td>
					</tr>
					<?php endif; ?>
					
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Actions', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<form method="post" action="" style="display: inline;">
								<?php wp_nonce_field( 'ghl_disconnect_oauth', 'ghl_disconnect_nonce' ); ?>
								<input type="hidden" name="ghl_disconnect_oauth" value="1" />
								<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect? You will need to re-authorize to reconnect.', 'ghl-crm-integration' ) ); ?>');">
									<?php esc_html_e( 'Disconnect OAuth', 'ghl-crm-integration' ); ?>
								</button>
							</form>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<!-- OAuth Not Connected State -->
		<form id="ghl-settings-form" method="post" action="">
			<?php wp_nonce_field( 'ghl_save_settings', 'ghl_settings_nonce' ); ?>
			
			<h2><?php esc_html_e( 'Connect to GoHighLevel', 'ghl-crm-integration' ); ?></h2>
			
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'OAuth Connection', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<a href="<?php echo esc_url( $oauth_handler->get_authorization_url() ); ?>" class="button button-primary button-hero">
								<span class="dashicons dashicons-admin-network" style="margin-top: 4px;"></span>
								<?php esc_html_e( 'Connect with GoHighLevel', 'ghl-crm-integration' ); ?>
							</a>
							<p class="description">
								<?php esc_html_e( 'Click to securely connect your GoHighLevel account using OAuth. You will be redirected to GoHighLevel to authorize access.', 'ghl-crm-integration' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="api_version">
								<?php esc_html_e( 'API Version', 'ghl-crm-integration' ); ?>
							</label>
						</th>
						<td>
							<input 
								type="text" 
								id="api_version" 
								name="api_version" 
								value="<?php echo esc_attr( $settings['api_version'] ?? '2021-07-28' ); ?>" 
								class="regular-text" 
								placeholder="2021-07-28"
							/>
							<p class="description">
								<?php esc_html_e( 'Default: 2021-07-28', 'ghl-crm-integration' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
			
			<p class="submit">
				<button type="submit" name="submit" id="ghl-save-settings" class="button button-primary ghl-button ghl-button-primary">
					<span class="button-text"><?php esc_html_e( 'Save Settings', 'ghl-crm-integration' ); ?></span>
					<span class="spinner" style="display: none; float: none; margin: 0 0 0 8px;"></span>
				</button>
			</p>
		</form>
		
		<div class="ghl-divider"></div>
		
		<div class="ghl-crm-test-connection">
			<h2 class="ghl-heading-tertiary"><?php esc_html_e( 'Test Connection', 'ghl-crm-integration' ); ?></h2>
			<p class="ghl-text-secondary"><?php esc_html_e( 'Test your API connection to ensure everything is configured correctly.', 'ghl-crm-integration' ); ?></p>
			<button type="button" id="ghl-test-connection" class="button button-secondary ghl-button ghl-button-secondary">
				<?php esc_html_e( 'Test API Connection', 'ghl-crm-integration' ); ?>
			</button>
			<div id="ghl-test-result" class="ghl-mt-base"></div>
		</div>
	<?php endif; // End connected/not connected conditional ?>
		</div>

		<div class="ghl-sidebar ghl-crm-sidebar">
			<div class="ghl-card ghl-crm-card">
				<h2 class="ghl-heading-secondary"><?php esc_html_e( 'Quick Start Guide', 'ghl-crm-integration' ); ?></h2>
				<?php if ( $is_connected ) : ?>
					<ol class="ghl-list">
						<li><?php esc_html_e( 'Your account is connected via OAuth', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Configure field mapping to sync user data', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Enable sync modules (WooCommerce, BuddyBoss, etc.)', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Monitor sync logs for activity', 'ghl-crm-integration' ); ?></li>
					</ol>
				<?php else : ?>
					<ol class="ghl-list">
						<li><?php esc_html_e( 'Click "Connect with GoHighLevel" above', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Authorize the app in your GoHighLevel account', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'You will be redirected back automatically', 'ghl-crm-integration' ); ?></li>
						<li><?php esc_html_e( 'Configure field mapping and enable sync modules', 'ghl-crm-integration' ); ?></li>
					</ol>
				<?php endif; ?>
					<li><?php esc_html_e( 'Configure field mapping and enable sync', 'ghl-crm-integration' ); ?></li>
				</ol>
			</div>

			<div class="ghl-card ghl-crm-card">
				<h2 class="ghl-heading-secondary"><?php esc_html_e( 'Need Help?', 'ghl-crm-integration' ); ?></h2>
				<ul class="ghl-list">
					<li>
						<a href="https://marketplace.gohighlevel.com/docs/" target="_blank">
							<?php esc_html_e( 'GoHighLevel API Documentation', 'ghl-crm-integration' ); ?>
						</a>
					</li>
					<li>
						<a href="https://marketplace.gohighlevel.com/docs/Authorization/PrivateIntegrationsToken" target="_blank">
							<?php esc_html_e( 'How to Create Private Integration Token', 'ghl-crm-integration' ); ?>
						</a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>
