<?php
/**
 * System Status Template
 *
 * Display plugin status, connection health, and system information
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();

// Get plugin stats
global $wpdb;
$user_count  = count_users();
$total_users = $user_count['total_users'];

// Get OAuth connection status.
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();

$is_oauth_connected = ! empty( $oauth_status['connected'] );
$connected_at_raw   = $oauth_status['connected_at'] ?? '';
$expires_at_raw     = $oauth_status['expires_at'] ?? '';

// Normalize expires_at to timestamp only when present to avoid epoch defaults that show as decades ago.
$expires_timestamp = null;
if ( $is_oauth_connected && ! empty( $expires_at_raw ) ) {
	$expires_timestamp = is_numeric( $expires_at_raw ) ? (int) $expires_at_raw : strtotime( $expires_at_raw );
	if ( $expires_timestamp <= 0 ) {
		$expires_timestamp = null;
	}
}

// Check if API is connected (via OAuth OR manual API token).
$api_connected = $is_oauth_connected || ! empty( $settings['api_token'] );
$location_id   = $oauth_status['location_id'] ?? '';

// Fallback: Check if location_id exists (means was connected before, even if tokens expired)
$has_location    = ! empty( $location_id );
$needs_reconnect = $has_location && ! $api_connected;

// Get rate limiter stats
$rate_limiter      = \GHL_CRM\Sync\RateLimiter::get_instance();
$rate_limit_status = $rate_limiter->get_status( $location_id );

// Burst limit (100 requests per 10 seconds)
$burst_limit     = $rate_limit_status['burst']['limit'] ?? 'NaN';
$burst_used      = $rate_limit_status['burst']['used'] ?? 0;
$burst_remaining = $rate_limit_status['burst']['remaining'] ?? 'NaN';
$burst_percent   = $rate_limit_status['burst']['percent'] ?? 0;

// Daily limit (200,000 requests per day)
$daily_limit     = $rate_limit_status['daily']['limit'] ?? 'NaN';
$daily_used      = $rate_limit_status['daily']['used'] ?? 0;
$daily_remaining = $rate_limit_status['daily']['remaining'] ?? 'NaN';
$daily_percent   = $rate_limit_status['daily']['percent'] ?? 0;
$daily_resets_at = $rate_limit_status['daily']['resets_at'] ?? null;
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Plugin Status -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-plugins"></span>
				<?php esc_html_e( 'Plugin Status', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Current operational status of the GoHighLevel CRM Integration plugin.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div style="margin-top: 20px;">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Plugin Status', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<span style="display: inline-flex; align-items: center; gap: 8px; color: #00a32a; font-weight: 600;">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Active', 'ghl-crm-integration' ); ?>
							</span>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<?php esc_html_e( 'WordPress Users', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<strong style="font-size: 18px;"><?php echo esc_html( number_format( $total_users ) ); ?></strong>
							<span style="color: #646970; margin-left: 8px;">
								<?php esc_html_e( 'registered users on this site', 'ghl-crm-integration' ); ?>
							</span>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<?php esc_html_e( 'User Sync', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<?php
							$user_sync_enabled = $settings['enable_user_sync'] ?? false;
							if ( $user_sync_enabled ) {
								echo '<span style="display: inline-flex; align-items: center; gap: 8px; color: #00a32a;">';
								echo '<span class="dashicons dashicons-yes-alt"></span>';
								echo esc_html__( 'Enabled', 'ghl-crm-integration' );
								echo '</span>';
							} else {
								echo '<span style="display: inline-flex; align-items: center; gap: 8px; color: #646970;">';
								echo '<span class="dashicons dashicons-minus"></span>';
								echo esc_html__( 'Disabled', 'ghl-crm-integration' );
								echo '</span>';
							}
							?>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Content Restrictions', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<?php
							$restrictions_enabled = $settings['restrictions_enabled'] ?? false;
							if ( $restrictions_enabled ) {
								echo '<span style="display: inline-flex; align-items: center; gap: 8px; color: #00a32a;">';
								echo '<span class="dashicons dashicons-yes-alt"></span>';
								echo esc_html__( 'Enabled', 'ghl-crm-integration' );
								echo '</span>';
							} else {
								echo '<span style="display: inline-flex; align-items: center; gap: 8px; color: #646970;">';
								echo '<span class="dashicons dashicons-minus"></span>';
								echo esc_html__( 'Disabled', 'ghl-crm-integration' );
								echo '</span>';
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	
	<!-- API Rate Limit Status -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-performance"></span>
				<?php esc_html_e( 'API Rate Limits', 'ghl-crm-integration' ); ?>
				<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'GoHighLevel enforces two rate limits: 100 requests per 10 seconds (burst) and 200,000 requests per day. Exceeding these will temporarily block API calls. Monitor usage here to avoid hitting limits.', 'ghl-crm-integration' ); ?>">?</span>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Current API request usage for burst and daily limits.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<!-- Burst Limit (10 seconds window) -->
		<div style="margin-top: 20px;">
			<h3 style="margin-bottom: 15px;">
				<span class="dashicons dashicons-clock" style="color: #2271b1;"></span>
				<?php esc_html_e( 'Burst Limit (10 seconds)', 'ghl-crm-integration' ); ?>
			</h3>
			
			<div class="ghl-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
				<!-- Burst Remaining -->
				<div class="ghl-stat-card" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #2271b1;">
					<div class="stat-value" style="font-size: 28px; font-weight: bold; color: #1d2327;">
						<?php echo esc_html( number_format( $burst_remaining ) ); ?> / <?php echo esc_html( number_format( $burst_limit ) ); ?>
					</div>
					<div class="stat-label" style="color: #646970; margin-top: 5px;">
						<?php esc_html_e( 'Requests Remaining', 'ghl-crm-integration' ); ?>
					</div>
				</div>
				
				<!-- Burst Progress -->
				<div class="ghl-stat-card" style="background: #f9f9f9; padding: 20px; border-radius: 8px;">
					<?php
					$burst_bar_color = $burst_percent > 90 ? '#d63638' : ( $burst_percent > 70 ? '#dba617' : '#00a32a' );
					?>
					<div style="background: #e0e0e0; height: 30px; border-radius: 4px; overflow: hidden; position: relative;">
						<div style="background: <?php echo esc_attr( $burst_bar_color ); ?>; height: 100%; width: <?php echo esc_attr( $burst_percent ); ?>%; transition: width 0.3s ease;"></div>
						<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: 600; color: #1d2327; text-shadow: 0 0 3px rgba(255,255,255,0.8);">
							<?php echo esc_html( number_format( $burst_percent, 1 ) ); ?>% <?php esc_html_e( 'Used', 'ghl-crm-integration' ); ?>
						</div>
					</div>
					<div class="stat-label" style="color: #646970; margin-top: 10px; text-align: center;">
						<?php echo esc_html( number_format( $burst_used ) ); ?> <?php esc_html_e( 'requests used', 'ghl-crm-integration' ); ?>
					</div>
				</div>
			</div>
		</div>
		
		<hr style="margin: 30px 0;">
		
		<!-- Daily Limit -->
		<div>
			<h3 style="margin-bottom: 15px;">
				<span class="dashicons dashicons-calendar" style="color: #00a32a;"></span>
				<?php esc_html_e( 'Daily Limit (24 hours)', 'ghl-crm-integration' ); ?>
			</h3>
			
			<div class="ghl-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
				<!-- Daily Remaining -->
				<div class="ghl-stat-card" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #00a32a;">
					<div class="stat-value" style="font-size: 28px; font-weight: bold; color: #1d2327;">
						<?php echo esc_html( number_format( $daily_remaining ) ); ?> / <?php echo esc_html( number_format( $daily_limit ) ); ?>
					</div>
					<div class="stat-label" style="color: #646970; margin-top: 5px;">
						<?php esc_html_e( 'Requests Remaining Today', 'ghl-crm-integration' ); ?>
					</div>
				</div>
				
				<!-- Daily Progress -->
				<div class="ghl-stat-card" style="background: #f9f9f9; padding: 20px; border-radius: 8px;">
					<?php
					$daily_bar_color = $daily_percent > 90 ? '#d63638' : ( $daily_percent > 70 ? '#dba617' : '#00a32a' );
					?>
					<div style="background: #e0e0e0; height: 30px; border-radius: 4px; overflow: hidden; position: relative;">
						<div style="background: <?php echo esc_attr( $daily_bar_color ); ?>; height: 100%; width: <?php echo esc_attr( $daily_percent ); ?>%; transition: width 0.3s ease;"></div>
						<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: 600; color: #1d2327; text-shadow: 0 0 3px rgba(255,255,255,0.8);">
							<?php echo esc_html( number_format( $daily_percent, 1 ) ); ?>% <?php esc_html_e( 'Used', 'ghl-crm-integration' ); ?>
						</div>
					</div>
					<div class="stat-label" style="color: #646970; margin-top: 10px; text-align: center;">
						<?php echo esc_html( number_format( $daily_used ) ); ?> <?php esc_html_e( 'requests used today', 'ghl-crm-integration' ); ?>
					</div>
				</div>
			</div>
			
			<?php if ( $daily_resets_at ) : ?>
				<div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
					<strong><?php esc_html_e( 'Daily Limit Resets:', 'ghl-crm-integration' ); ?></strong>
					<?php
					$reset_timestamp  = strtotime( $daily_resets_at );
					$time_until_reset = $reset_timestamp - current_time( 'timestamp' );
					if ( $time_until_reset > 0 ) {
						echo esc_html( human_time_diff( current_time( 'timestamp' ), $reset_timestamp ) );
						$hours   = floor( $time_until_reset / 3600 );
						$minutes = floor( ( $time_until_reset % 3600 ) / 60 );
						echo ' (' . esc_html( sprintf( '%02d:%02d', $hours, $minutes ) ) . ')';
					} else {
						echo esc_html__( 'Now', 'ghl-crm-integration' );
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	
	<!-- API Connection Status -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-cloud"></span>
				<?php esc_html_e( 'API Connection Status', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Current status of your GoHighLevel API connection.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div style="margin-top: 20px;">
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Connection Status', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<?php if ( $api_connected ) : ?>
							<span style="display: inline-flex; align-items: center; gap: 8px; color: #00a32a; font-weight: 600;">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Connected', 'ghl-crm-integration' ); ?>
							</span>
						<?php elseif ( $needs_reconnect ) : ?>
							<span style="display: inline-flex; align-items: center; gap: 8px; color: #dba617; font-weight: 600;">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e( 'Token Expired - Reconnect Required', 'ghl-crm-integration' ); ?>
							</span>
						<?php else : ?>
							<span style="display: inline-flex; align-items: center; gap: 8px; color: #d63638; font-weight: 600;">
								<span class="dashicons dashicons-dismiss"></span>
								<?php esc_html_e( 'Not Connected', 'ghl-crm-integration' ); ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>				<?php if ( $api_connected ) : ?>
					<?php if ( $is_oauth_connected ) : ?>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Connected Since', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<?php
						if ( ! empty( $connected_at_raw ) ) {
							echo esc_html( human_time_diff( strtotime( $connected_at_raw ), current_time( 'timestamp' ) ) );
							echo ' ' . esc_html__( 'ago', 'ghl-crm-integration' );
						} else {
							echo '—';
						}
						?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Token Expires', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<?php
						if ( $expires_timestamp ) {
							$time_until_expiry = $expires_timestamp - current_time( 'timestamp' );

							if ( $time_until_expiry > 0 ) {
								echo '<span style="color: #00a32a;">';
								echo esc_html__( 'In ', 'ghl-crm-integration' );
								echo esc_html( human_time_diff( current_time( 'timestamp' ), $expires_timestamp ) );
								echo '</span>';
							} else {
								echo '<span style="color: #d63638;">';
								echo esc_html__( 'Expired ', 'ghl-crm-integration' );
								echo esc_html( human_time_diff( $expires_timestamp, current_time( 'timestamp' ) ) );
								echo ' ' . esc_html__( 'ago', 'ghl-crm-integration' );
								echo '</span>';
							}
						} else {
							echo esc_html__( 'Not available (manual token or missing expiry)', 'ghl-crm-integration' );
						}
						?>
					</td>
				</tr>
				<?php endif; ?>
				
					<?php if ( $location_id ) : ?>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Location ID', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<code style="background: #f0f0f1; padding: 4px 8px; border-radius: 3px;">
							<?php echo esc_html( $location_id ); ?>
						</code>
					</td>
				</tr>
				<?php endif; ?>
				
					<?php if ( $api_connected && ! empty( $oauth_status['location_name'] ) ) : ?>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Location Name', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<strong><?php echo esc_html( $oauth_status['location_name'] ); ?></strong>
					</td>
				</tr>
				<?php endif; ?>
			<?php endif; ?>

				<tr>
						<th scope="row">
							<?php esc_html_e( 'Rate Limiting', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<?php
							$rate_limit_enabled = $settings['enable_rate_limiting'] ?? true;
							if ( $rate_limit_enabled ) {
								$requests_per_minute = $settings['requests_per_minute'] ?? 100;
								echo '<span style="color: #00a32a;">';
								printf(
									/* translators: %d: number of requests per minute */
									esc_html__( 'Enabled (%d requests/minute)', 'ghl-crm-integration' ),
									esc_html( $requests_per_minute )
								);
								echo '</span>';
							} else {
								echo '<span style="color: #d63638;">' . esc_html__( 'Disabled', 'ghl-crm-integration' ) . '</span>';
							}
							?>
						</td>
					</tr>
					
					<tr>
						<!-- <th scope="row">
							<?php esc_html_e( 'Webhook Status', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<?php
							$webhooks_enabled = $settings['enable_webhooks'] ?? false;
							if ( $webhooks_enabled ) {
								echo '<span style="display: inline-flex; align-items: center; gap: 8px; color: #00a32a;">';
								echo '<span class="dashicons dashicons-yes-alt"></span>';
								echo esc_html__( 'Enabled', 'ghl-crm-integration' );
								echo '</span>';
							} else {
								echo '<span style="display: inline-flex; align-items: center; gap: 8px; color: #646970;">';
								echo '<span class="dashicons dashicons-minus"></span>';
								echo esc_html__( 'Disabled', 'ghl-crm-integration' );
								echo '</span>';
							}
							?>
						</td> -->
					</tr>
				</tbody>
			</table>
		</div>
		
		<?php if ( $api_connected ) : ?>
			<div style="margin-top: 20px; padding: 15px; background: #e7f5fe; border-left: 4px solid #2271b1; border-radius: 4px;">
				<p style="margin: 0;">
					<strong><?php esc_html_e( 'API Health:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Your connection to GoHighLevel is active and operational.', 'ghl-crm-integration' ); ?>
				</p>
			</div>
		<?php else : ?>
			<div style="margin-top: 20px; padding: 15px; background: #fcf0f1; border-left: 4px solid #d63638; border-radius: 4px;">
				<p style="margin: 0;">
					<strong><?php esc_html_e( 'Action Required:', 'ghl-crm-integration' ); ?></strong>
					<?php esc_html_e( 'Please connect to GoHighLevel in the Dashboard to start syncing data.', 'ghl-crm-integration' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>
	
	<!-- System Information -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-tools"></span>
				<?php esc_html_e( 'System Information', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Technical details about your WordPress environment.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div style="margin-top: 20px;">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Plugin Version', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<code><?php echo esc_html( GHL_CRM_VERSION ?? '1.0.0' ); ?></code>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<?php esc_html_e( 'WordPress Version', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<?php esc_html_e( 'PHP Version', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<code><?php echo esc_html( phpversion() ); ?></code>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Database Prefix', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<code><?php echo esc_html( $wpdb->prefix ); ?></code>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Multisite', 'ghl-crm-integration' ); ?>
						</th>
						<td>
							<?php echo is_multisite() ? '<span style="color: #00a32a;">' . esc_html__( 'Yes', 'ghl-crm-integration' ) . '</span>' : esc_html__( 'No', 'ghl-crm-integration' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	
</div>