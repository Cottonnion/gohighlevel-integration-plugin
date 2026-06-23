<?php
/**
 * Settings - Notifications Template
 *
 * Notification settings tab content
 *
 * @package    Syncly
 * @subpackage Syncly/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \Syncly\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();

// Get notification settings with defaults
$notification_email      = $settings['notification_email'] ?? $settings_manager->get_option( 'admin_email' );
$notify_connection_lost  = $settings['notify_connection_lost'] ?? true;
$notify_sync_errors      = $settings['notify_sync_errors'] ?? true;
$notify_queue_backlog    = $settings['notify_queue_backlog'] ?? true;
$notify_rate_limit       = $settings['notify_rate_limit'] ?? false;
$notify_webhook_failures = $settings['notify_webhook_failures'] ?? true;
$notify_daily_summary    = $settings['notify_daily_summary'] ?? false;
$daily_summary_time      = $settings['daily_summary_time'] ?? '09:00';
$notification_throttle   = $settings['notification_throttle'] ?? '3600';
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'syncly_settings_nonce', 'syncly_nonce' ); ?>
	
	<!-- Email Configuration -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-email"></span>
				<?php esc_html_e( 'Email Configuration', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Configure where sync notifications and alerts should be sent', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<div class="ghl-form">
				<div class="ghl-form-item">
					<div class="ghl-form-item-content ghl-form-item-content--column">
						<label for="notification_email" class="ghl-form-label">
							<?php esc_html_e( 'Notification Email Address', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'All plugin notifications will be sent to this email. Make sure it\'s monitored regularly for critical alerts.', 'syncly' ); ?>">?</span>
						</label>
						<input 
							type="email" 
							id="notification_email" 
							name="notification_email" 
							class="ghl-input ghl-input--wide" 
							value="<?php echo esc_attr( $notification_email ); ?>"
							placeholder="admin@example.com"
							required
						>
						<p class="description ghl-form-description">
							<?php esc_html_e( 'Default: WordPress admin email. Change this if you want notifications sent elsewhere.', 'syncly' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Critical Alerts -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Critical Alerts', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Receive instant email notifications for urgent issues that require immediate attention', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<div class="ghl-form">
				
				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $notify_connection_lost ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
									class="ghl-checkbox-original"
									id="notify_connection_lost" 
									name="notify_connection_lost" 
									value="1" 
									<?php checked( $notify_connection_lost ); ?>>
							<span class="ghl-checkbox-input <?php echo $notify_connection_lost ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Connection Lost', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Get notified immediately when OAuth token expires or API key becomes invalid. This means ALL syncing has stopped.', 'syncly' ); ?>">?</span>
							</span>
						</label>
					</div>
					<p class="description" style="margin-left: 54px;">
						<?php esc_html_e( 'Alert when OAuth/API connection fails (HIGH PRIORITY)', 'syncly' ); ?>
					</p>
				</div>

				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $notify_sync_errors ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
									class="ghl-checkbox-original"
									id="notify_sync_errors" 
									name="notify_sync_errors" 
									value="1" 
									<?php checked( $notify_sync_errors ); ?>>
							<span class="ghl-checkbox-input <?php echo $notify_sync_errors ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Sync Failures', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Get alerted when users, orders, or other data fails to sync to GoHighLevel. Prevents data loss.', 'syncly' ); ?>">?</span>
							</span>
						</label>
					</div>
					<p class="description" style="margin-left: 54px;">
						<?php esc_html_e( 'Notify when sync operations fail with errors', 'syncly' ); ?>
					</p>
				</div>

				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $notify_queue_backlog ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
									class="ghl-checkbox-original"
									id="notify_queue_backlog" 
									name="notify_queue_backlog" 
									value="1" 
									<?php checked( $notify_queue_backlog ); ?>>
							<span class="ghl-checkbox-input <?php echo $notify_queue_backlog ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Queue Backlog Warning', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Alert when more than 1,000 items are waiting in the sync queue. Indicates processing issues or heavy load.', 'syncly' ); ?>">?</span>
							</span>
						</label>
					</div>
					<p class="description" style="margin-left: 54px;">
						<?php esc_html_e( 'Alert when sync queue exceeds 1,000 pending items', 'syncly' ); ?>
					</p>
				</div>

				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $notify_webhook_failures ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
									class="ghl-checkbox-original"
									id="notify_webhook_failures" 
									name="notify_webhook_failures" 
									value="1" 
									<?php checked( $notify_webhook_failures ); ?>>
							<span class="ghl-checkbox-input <?php echo $notify_webhook_failures ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Webhook Failures', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Get notified when your webhook endpoint is down or failing verification. GHL updates won\'t reach your site.', 'syncly' ); ?>">?</span>
							</span>
						</label>
					</div>
					<p class="description" style="margin-left: 54px;">
						<?php esc_html_e( 'Alert when webhooks fail or verification errors occur', 'syncly' ); ?>
					</p>
				</div>

				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $notify_rate_limit ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
									class="ghl-checkbox-original"
									id="notify_rate_limit" 
									name="notify_rate_limit" 
									value="1" 
									<?php checked( $notify_rate_limit ); ?>>
							<span class="ghl-checkbox-input <?php echo $notify_rate_limit ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'API Rate Limit Exceeded', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Get alerted when hitting GoHighLevel API rate limits frequently. May indicate need to adjust sync intervals.', 'syncly' ); ?>">?</span>
							</span>
						</label>
					</div>
					<p class="description" style="margin-left: 54px;">
						<?php esc_html_e( 'Notify when GoHighLevel API rate limits are hit', 'syncly' ); ?>
					</p>
				</div>
				
			</div>
		</div>
	</div>

	<!-- Daily Summary -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-chart-line"></span>
				<?php esc_html_e( 'Daily Activity Summary', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Get a comprehensive email report every 24 hours with sync statistics and activity overview', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<div class="ghl-form">
				
				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $notify_daily_summary ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
									class="ghl-checkbox-original"
									id="notify_daily_summary" 
									name="notify_daily_summary" 
									value="1" 
									<?php checked( $notify_daily_summary ); ?>>
							<span class="ghl-checkbox-input <?php echo $notify_daily_summary ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Enable Daily Summary Email', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Receive a daily email with: total syncs, success rate, failed operations, queue status, webhook activity, and error summary.', 'syncly' ); ?>">?</span>
							</span>
						</label>
					</div>
					<p class="description" style="margin-left: 54px;">
						<?php esc_html_e( 'Includes: users synced, orders processed, webhooks received, errors, queue status', 'syncly' ); ?>
					</p>
				</div>

			</div>
		</div>

		<hr>

		<h3><?php esc_html_e( 'Summary Delivery Settings', 'syncly' ); ?></h3>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="daily_summary_time">
							<?php esc_html_e( 'Delivery Time', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'What time of day should the summary email be sent? Uses your server\'s timezone.', 'syncly' ); ?>">?</span>
						</label>
					</th>
					<td>
						<select id="daily_summary_time" name="daily_summary_time" class="regular-text">
							<?php
							for ( $hour = 0; $hour < 24; $hour++ ) {
								$time_value = sprintf( '%02d:00', $hour );
									$time_label = gmdate( 'g:00 A', strtotime( $time_value ) );
								printf(
									'<option value="%s" %s>%s</option>',
									esc_attr( $time_value ),
									selected( $daily_summary_time, $time_value, false ),
									esc_html( $time_label )
								);
							}
							?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Time of day to send the daily summary (server timezone).', 'syncly' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Throttling Settings -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-shield"></span>
				<?php esc_html_e( 'Notification Throttling', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Prevent email spam by limiting how often you receive alerts for the same issue', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="notification_throttle">
							<?php esc_html_e( 'Minimum Time Between Alerts', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'If the same error happens multiple times, you\'ll only get one email within this timeframe. Prevents inbox spam.', 'syncly' ); ?>">?</span>
						</label>
					</th>
					<td>
						<select id="notification_throttle" name="notification_throttle" class="regular-text">
							<?php
							$throttle_options = array(
								'0'     => __( 'No throttling (send every alert)', 'syncly' ),
								'900'   => __( '15 minutes', 'syncly' ),
								'1800'  => __( '30 minutes', 'syncly' ),
								'3600'  => __( '1 hour (recommended)', 'syncly' ),
								'7200'  => __( '2 hours', 'syncly' ),
								'14400' => __( '4 hours', 'syncly' ),
								'28800' => __( '8 hours', 'syncly' ),
								'86400' => __( '24 hours', 'syncly' ),
							);
							foreach ( $throttle_options as $value => $label ) {
								printf(
									'<option value="%s" %s>%s</option>',
									esc_attr( $value ),
									selected( $notification_throttle, $value, false ),
									esc_html( $label )
								);
							}
							?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Prevent duplicate alerts for the same error type within this timeframe.', 'syncly' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Test Notification -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-tools"></span>
				<?php esc_html_e( 'Test Email Delivery', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Verify your email configuration is working correctly before relying on critical alerts', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<button type="button" id="send-test-notification" class="ghl-button ghl-button-secondary">
			<span class="dashicons dashicons-email-alt"></span>
			<?php esc_html_e( 'Send Test Notification', 'syncly' ); ?>
		</button>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'Sends a test email to verify notifications are working. Check your spam folder if you don\'t receive it.', 'syncly' ); ?>
		</p>
	</div>

	<!-- Save Button -->
	<button type="button" class="ghl-button ghl-button-primary ghl-save-settings-btn">
		<span class="ghl-button-text"><?php esc_html_e( 'Save Notification Settings', 'syncly' ); ?></span>
	</button>

	<!-- Help Section -->
	<div class="ghl-help-box ghl-help-box--spaced">
		<h3>
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'How Notifications Work', 'syncly' ); ?>
		</h3>
		<div class="ghl-help-content">
			<ul>
				<li>
					<strong><?php esc_html_e( 'Critical Alerts:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'Sent immediately when issues occur. These are time-sensitive and should be enabled for production sites.', 'syncly' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Daily Summary:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'One email per day with complete stats: syncs processed, success rate, errors, queue status, and webhook activity.', 'syncly' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Throttling:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'Prevents email spam. If the same error happens 50 times in 10 minutes, you\'ll only get 1 email (per your throttle setting).', 'syncly' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Test First:', 'syncly' ); ?></strong>
					<?php esc_html_e( 'Always send a test notification to verify email delivery before enabling critical alerts.', 'syncly' ); ?>
				</li>
			</ul>
		</div>
	</div>
	
</div>