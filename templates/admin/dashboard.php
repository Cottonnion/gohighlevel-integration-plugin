<?php
/**
 * Dashboard Template
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug Mode Toggle
 * 
 * Set to TRUE to show debug information including:
 * - Settings dump
 * - Queue manager status
 * - Scheduled action details
 * - Pending queue items
 * - Error logs
 * - API connection test
 * - Manual queue trigger button
 * 
 * Set to FALSE for production (clean dashboard)
 */
define( 'GHL_SHOW_DEBUG', false );

// Get OAuth handler and status
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$settings      = \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();
?>

<?php if ( GHL_SHOW_DEBUG ) : ?>
<?php
// Debug: Print settings
echo '<div style="background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-left: 4px solid #2271b1;">';
echo '<h4 style="margin-top: 0;">Debug: Settings</h4>';
echo '<pre style="background: white; padding: 10px; overflow: auto;">';
print_r($settings);
echo '</pre>';

// Debug: Queue Manager Status
echo '<h4>Debug: Queue Manager Status</h4>';
$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
$queue_status = $queue_manager->get_queue_status();

echo '<h5>Queue Statistics:</h5>';
echo '<pre style="background: white; padding: 10px; overflow: auto;">';
print_r($queue_status);
echo '</pre>';

// Check next scheduled action
if ( function_exists( 'as_next_scheduled_action' ) ) {
	echo '<h5>Next Scheduled Queue Process:</h5>';
	$next_scheduled = as_next_scheduled_action( 'ghl_crm_process_queue' );
	
	if ( $next_scheduled ) {
		$time_until = $next_scheduled - time();
		echo '<p style="background: white; padding: 10px; margin: 10px 0;">';
		echo '<strong>Next Run:</strong> ' . esc_html( gmdate( 'Y-m-d H:i:s', $next_scheduled ) ) . '<br>';
		echo '<strong>Time Until Next Run:</strong> ' . esc_html( $time_until ) . ' seconds<br>';
		echo '<strong>Current Time:</strong> ' . esc_html( gmdate( 'Y-m-d H:i:s', time() ) ) . '<br>';
		echo '<strong>Status:</strong> ' . ( $time_until <= 0 ? '<span style="color: green;">Ready to run</span>' : '<span style="color: orange;">Scheduled</span>' );
		echo '</p>';
	} else {
		echo '<p style="background: white; padding: 10px; color: red; margin: 10px 0;">';
		echo '<strong>⚠ No scheduled action found!</strong> The queue processor may not be running.';
		echo '</p>';
	}
	
	// Get pending queue items
	global $wpdb;
	$table_name = $wpdb->prefix . 'ghl_sync_queue';
	$pending_items = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE status = 'pending' AND site_id = %d ORDER BY created_at ASC LIMIT 5",
			get_current_blog_id()
		)
	);
	
	if ( $pending_items ) {
		echo '<h5>Next 5 Pending Queue Items:</h5>';
		echo '<pre style="background: white; padding: 10px; overflow: auto; max-height: 300px;">';
		print_r($pending_items);
		echo '</pre>';
	} else {
		echo '<p style="background: white; padding: 10px; color: green; margin: 10px 0;">✓ No pending items in queue</p>';
	}
	
	// Manual trigger button with inline script
	$nonce = wp_create_nonce( 'ghl_crm_manual_queue' );

	
	// Check error log for recent queue processing
	echo '<h5>Recent Error Log Entries (Queue Processing):</h5>';
	$log_file = WP_CONTENT_DIR . '/debug.log';
	if ( file_exists( $log_file ) ) {
		$log_contents = file_get_contents( $log_file );
		$log_lines = explode( "\n", $log_contents );
		$ghl_lines = array_filter( $log_lines, function( $line ) {
			return strpos( $line, 'GHL CRM' ) !== false;
		} );
		$recent_logs = array_slice( array_reverse( $ghl_lines ), 0, 20 );
		
		if ( ! empty( $recent_logs ) ) {
			echo '<pre style="background: #2c3338; color: #ddd; padding: 15px; overflow: auto; max-height: 400px; font-size: 12px;">';
			foreach ( $recent_logs as $log_line ) {
				echo esc_html( $log_line ) . "\n";
			}
			echo '</pre>';
		} else {
			echo '<p style="background: white; padding: 10px; color: orange;">No GHL CRM logs found in debug.log</p>';
		}
	} else {
		echo '<p style="background: white; padding: 10px; color: orange;">Debug log not enabled. Add <code>define(\'WP_DEBUG_LOG\', true);</code> to wp-config.php</p>';
	}
} else {
	echo '<p style="background: white; padding: 10px; color: red; margin: 10px 0;">';
	echo '<strong>⚠ Action Scheduler not available!</strong> Using WP-Cron fallback.';
	echo '</p>';
}

// Test API Connection - Try to fetch contacts
if ( ! empty( $settings['api_token'] ) && ! empty( $settings['location_id'] ) ) {
	echo '<h4>Debug: Testing API Connection</h4>';
	echo '<p>Attempting to fetch contacts from GoHighLevel...</p>';
	
	try {
		$client = \GHL_CRM\API\Client\Client::get_instance();
		
		// Try to get first 5 contacts
		$response = $client->get( 'contacts/', [ 
			'locationId' => $settings['location_id'],
			'limit' => 5 
		] );
		
		echo '<p style="color: green; font-weight: bold;">✓ API Connection Successful!</p>';
		echo '<h5>Contacts Retrieved:</h5>';
		echo '<pre style="background: white; padding: 10px; overflow: auto; max-height: 300px;">';
		print_r($response);
		echo '</pre>';
		
		// Show contact count and names
		if ( isset( $response['contacts'] ) && is_array( $response['contacts'] ) ) {
			echo '<h5>Summary:</h5>';
			echo '<ul>';
			echo '<li>Total contacts in response: ' . count( $response['contacts'] ) . '</li>';
			foreach ( $response['contacts'] as $contact ) {
				$name = $contact['firstName'] ?? '';
				$last = $contact['lastName'] ?? '';
				$email = $contact['email'] ?? 'No email';
				echo '<li><strong>' . esc_html( $name . ' ' . $last ) . '</strong> - ' . esc_html( $email ) . '</li>';
			}
			echo '</ul>';
		}
		
	} catch ( \Exception $e ) {
		echo '<p style="color: red; font-weight: bold;">✗ API Connection Failed</p>';
		echo '<p>Error: ' . esc_html( $e->getMessage() ) . '</p>';
		echo '<pre style="background: white; padding: 10px; overflow: auto;">';
		echo 'Exception Class: ' . esc_html( get_class( $e ) ) . "\n";
		echo 'Exception Code: ' . esc_html( (string) $e->getCode() ) . "\n";
		echo 'Trace: ' . "\n" . esc_html( $e->getTraceAsString() );
		echo '</pre>';
	}
} else {
	echo '<p style="color: orange;">⚠ No API credentials found - cannot test connection</p>';
}

echo '</div>';
?>
<?php endif; // End GHL_SHOW_DEBUG ?>

<div class="ghl-crm-dashboard">
	<div class="ghl-card">
		<h2><?php esc_html_e( 'Connection Setup', 'ghl-crm-integration' ); ?></h2>
		
		<?php if ( $oauth_status['connected'] || ! empty( $settings['api_token'] ) ) : ?>
			<!-- Connected State -->
			<div class="ghl-connection-status">
			<?php if ( $oauth_status['connected'] ) : ?>
				<div class="ghl-status-connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<strong><?php esc_html_e( 'Connected', 'ghl-crm-integration' ); ?></strong>
					<?php
					$location_name = $settings['location_name'] ?? 'GoHighLevel';
					if ( ! empty( $location_name ) && $location_name !== 'GoHighLevel' ) {
						printf(
							/* translators: %s: Location name */
							esc_html__( 'to %s', 'ghl-crm-integration' ),
							esc_html( $location_name )
						);
					}
					?>
					<br><small>
						<?php
						printf(
							/* translators: %s: Connection date */
							esc_html__( 'Connected: %s', 'ghl-crm-integration' ),
							esc_html( $oauth_status['connected_at'] ?? __( 'Unknown', 'ghl-crm-integration' ) )
						);
						?>
					</small>
					<br>
					<button type="button" class="button button-secondary" id="ghl-disconnect-btn" style="margin-top: 10px;">
						<?php esc_html_e( 'Disconnect Account', 'ghl-crm-integration' ); ?>
					</button>
				</div>

				<!-- Connection Details -->
				<div style="margin-top: 20px;">
					<h3><?php esc_html_e( 'Connection Details', 'ghl-crm-integration' ); ?></h3>
					<table class="widefat" style="margin-top: 10px;">
						<tbody>
							<tr>
								<td style="width: 30%; font-weight: 600;">
									<?php esc_html_e( 'Location ID:', 'ghl-crm-integration' ); ?>
								</td>
								<td><code><?php echo esc_html( $oauth_status['location_id'] ?: __( 'Not available', 'ghl-crm-integration' ) ); ?></code></td>
							</tr>
							<tr>
								<td style="font-weight: 600;">
									<?php esc_html_e( 'Location Name:', 'ghl-crm-integration' ); ?>
								</td>
								<td><?php echo esc_html( $location_name ); ?></td>
							</tr>
							<tr>
								<td style="font-weight: 600;">
									<?php esc_html_e( 'Token Status:', 'ghl-crm-integration' ); ?>
								</td>
								<td>
									<?php
									$expires_at = $oauth_status['expires_at'];
									if ( $expires_at && $expires_at > time() ) {
										$time_left = human_time_diff( time(), $expires_at );
										printf(
											/* translators: %s: Time remaining */
											esc_html__( 'Valid (expires in %s)', 'ghl-crm-integration' ),
											esc_html( $time_left )
										);
									} else {
										esc_html_e( 'Expired (will auto-refresh)', 'ghl-crm-integration' );
									}
									?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Sync Statistics -->
				<?php
				global $wpdb;
				$table_name = $wpdb->prefix . 'ghl_sync_log';
				
				// Get stats if table exists
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) :
					$total_syncs   = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
					$success_syncs = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'success'" );
					$failed_syncs  = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed'" );
					$last_sync     = $wpdb->get_var( "SELECT created_at FROM {$table_name} ORDER BY created_at DESC LIMIT 1" );
					?>
					<div style="margin-top: 20px;">
						<h3><?php esc_html_e( 'Sync Statistics', 'ghl-crm-integration' ); ?></h3>
						<table class="widefat" style="margin-top: 10px;">
							<tbody>
								<tr>
									<td style="width: 30%; font-weight: 600;">
										<?php esc_html_e( 'Total Syncs:', 'ghl-crm-integration' ); ?>
									</td>
									<td><?php echo esc_html( number_format_i18n( (int) $total_syncs ) ); ?></td>
								</tr>
								<tr>
									<td style="font-weight: 600;">
										<?php esc_html_e( 'Successful:', 'ghl-crm-integration' ); ?>
									</td>
									<td style="color: #46b450;"><?php echo esc_html( number_format_i18n( (int) $success_syncs ) ); ?></td>
								</tr>
								<tr>
									<td style="font-weight: 600;">
										<?php esc_html_e( 'Failed:', 'ghl-crm-integration' ); ?>
									</td>
									<td style="color: #dc3232;"><?php echo esc_html( number_format_i18n( (int) $failed_syncs ) ); ?></td>
								</tr>
								<tr>
									<td style="font-weight: 600;">
										<?php esc_html_e( 'Last Sync:', 'ghl-crm-integration' ); ?>
									</td>
									<td>
										<?php
										if ( $last_sync ) {
											echo esc_html( human_time_diff( strtotime( $last_sync ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'ghl-crm-integration' ) );
										} else {
											esc_html_e( 'No syncs yet', 'ghl-crm-integration' );
										}
										?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

			<?php elseif ( ! empty( $settings['api_token'] ) ) : ?>
				<!-- Manual API Key Connected -->
				<div class="ghl-status-connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<strong><?php esc_html_e( 'Connected (API Key)', 'ghl-crm-integration' ); ?></strong>
					<p><?php esc_html_e( 'Using manual API key configuration.', 'ghl-crm-integration' ); ?></p>
					
					<div style="margin-top: 20px;">
						<h3><?php esc_html_e( 'Connection Details', 'ghl-crm-integration' ); ?></h3>
						<table class="widefat" style="margin-top: 10px;">
							<tbody>
								<tr>
									<td style="width: 30%; font-weight: 600;">
										<?php esc_html_e( 'Location ID:', 'ghl-crm-integration' ); ?>
									</td>
									<td><code><?php echo esc_html( $settings['location_id'] ?? __( 'Not set', 'ghl-crm-integration' ) ); ?></code></td>
								</tr>
								<tr>
									<td style="font-weight: 600;">
										<?php esc_html_e( 'API Token:', 'ghl-crm-integration' ); ?>
									</td>
									<td><code><?php echo esc_html( substr( $settings['api_token'], 0, 20 ) . '...' ); ?></code></td>
								</tr>
							</tbody>
						</table>
					</div>
					
					<div style="margin-top: 15px;">
						<button type="button" class="button button-secondary" id="ghl-disconnect-api-btn">
							<span class="dashicons dashicons-dismiss"></span>
							<?php esc_html_e( 'Disconnect API Key', 'ghl-crm-integration' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/settings' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Update Settings', 'ghl-crm-integration' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
			</div>
			
		<?php else : ?>
			<!-- Not Connected - Show Connection Options -->
			<div class="ghl-connection-tabs">
				<!-- Tab Navigation -->
				<div class="ghl-tab-nav">
					<button type="button" class="ghl-tab-button active" data-tab="manual">
						<span class="dashicons dashicons-admin-network"></span>
						<?php esc_html_e( 'API Key (Recommended)', 'ghl-crm-integration' ); ?>
					</button>
					<button type="button" class="ghl-tab-button" data-tab="oauth">
						<span class="dashicons dashicons-cloud"></span>
						<?php esc_html_e( 'OAuth Connection', 'ghl-crm-integration' ); ?>
					</button>
				</div>

				<!-- Manual API Key Tab -->
				<div class="ghl-tab-content active" id="manual-tab">
					<div class="ghl-tab-inner">
						<h3><?php esc_html_e( 'Connect Using API Key', 'ghl-crm-integration' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'This is the recommended method. Use a GoHighLevel API key to connect your location. This method is more reliable and doesn\'t require OAuth app configuration.', 'ghl-crm-integration' ); ?>
						</p>

						<div class="ghl-info-box" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
							<h4 style="margin-top: 0;">
								<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
								<?php esc_html_e( 'How to Get Your API Key:', 'ghl-crm-integration' ); ?>
							</h4>
							<ol style="margin: 10px 0 0 20px;">
								<li><?php esc_html_e( 'Log into your GoHighLevel location (sub-account)', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Go to Settings → Integrations → API Key', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Click "Generate API Key" or copy your existing key', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Copy the Location ID from the same page', 'ghl-crm-integration' ); ?></li>
							</ol>
						</div>

						<form id="ghl-manual-connection-form" method="post" style="max-width: 600px;">
							<?php wp_nonce_field( 'ghl_crm_manual_connect', 'ghl_manual_connect_nonce' ); ?>
							
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="api_token">
											<?php esc_html_e( 'API Key', 'ghl-crm-integration' ); ?>
											<span class="required" style="color: #dc3232;">*</span>
										</label>
									</th>
									<td>
										<input 
											type="text" 
											id="api_token" 
											name="api_token" 
											class="regular-text code" 
											placeholder="<?php esc_attr_e( 'Enter your GoHighLevel API key', 'ghl-crm-integration' ); ?>"
											required
										/>
										<p class="description">
											<?php esc_html_e( 'Your location API key from GoHighLevel Settings', 'ghl-crm-integration' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="location_id">
											<?php esc_html_e( 'Location ID', 'ghl-crm-integration' ); ?>
											<span class="required" style="color: #dc3232;">*</span>
										</label>
									</th>
									<td>
										<input 
											type="text" 
											id="location_id" 
											name="location_id" 
											class="regular-text code" 
											placeholder="<?php esc_attr_e( 'Enter your Location ID', 'ghl-crm-integration' ); ?>"
											required
										/>
										<p class="description">
											<?php esc_html_e( 'Found in the same page as your API key', 'ghl-crm-integration' ); ?>
										</p>
									</td>
								</tr>
							</table>

							<p class="submit">
								<button type="submit" class="button button-primary button-large">
									<span class="dashicons dashicons-yes-alt" style="margin-top: 3px;"></span>
									<?php esc_html_e( 'Connect Now', 'ghl-crm-integration' ); ?>
								</button>
							</p>
						</form>
					</div>
				</div>

				<!-- OAuth Tab -->
				<div class="ghl-tab-content" id="oauth-tab">
					<div class="ghl-tab-inner">
						<h3><?php esc_html_e( 'Connect Using OAuth', 'ghl-crm-integration' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'Use our OAuth app to connect multiple locations easily. This method is ideal for agencies managing multiple sub-accounts.', 'ghl-crm-integration' ); ?>
						</p>

						<div class="ghl-info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
							<h4 style="margin-top: 0;">
								<span class="dashicons dashicons-lightbulb" style="color: #ffc107;"></span>
								<?php esc_html_e( 'About OAuth Connection:', 'ghl-crm-integration' ); ?>
							</h4>
							<ul style="margin: 10px 0 0 20px;">
								<li><?php esc_html_e( 'One-click connection to GoHighLevel', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Automatic token refresh (stays connected)', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'Works across multiple locations', 'ghl-crm-integration' ); ?></li>
								<li><?php esc_html_e( 'More secure than manual API keys', 'ghl-crm-integration' ); ?></li>
							</ul>
						</div>

						<div class="ghl-oauth-benefits" style="background: #f9f9f9; padding: 20px; border-radius: 4px; margin: 20px 0;">
							<h4><?php esc_html_e( 'Required Permissions:', 'ghl-crm-integration' ); ?></h4>
							<ul style="list-style: none; padding: 0;">
								<li style="padding: 5px 0;">
									<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
									<?php esc_html_e( 'Read and write contacts', 'ghl-crm-integration' ); ?>
								</li>
								<li style="padding: 5px 0;">
									<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
									<?php esc_html_e( 'Manage contact tags', 'ghl-crm-integration' ); ?>
								</li>
								<li style="padding: 5px 0;">
									<span class="dashicons dashicons-yes" style="color: #46b450;"></span>
									<?php esc_html_e( 'Manage custom fields', 'ghl-crm-integration' ); ?>
								</li>
							</ul>
						</div>

						<div style="text-align: center; padding: 20px;">
							<a target='_blank' href="<?php echo esc_url( $oauth_handler->get_authorization_url() ); ?>" class="button button-primary button-hero">
								<span class="dashicons dashicons-cloud" style="margin-top: 5px;"></span>
								<?php esc_html_e( 'Connect with GoHighLevel', 'ghl-crm-integration' ); ?>
							</a>
							<p class="description" style="margin-top: 15px;">
								<?php esc_html_e( 'You will be redirected to GoHighLevel to authorize this integration. After authorization, you\'ll be redirected back here.', 'ghl-crm-integration' ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
	
	<!-- Manual Queue Trigger Button (Bottom of Page) -->
	<?php if ( GHL_SHOW_DEBUG && function_exists( 'as_next_scheduled_action' ) ) : ?>
		<?php 
		$manual_nonce = wp_create_nonce( 'ghl_crm_manual_queue' );
		$batch_size = 50; // Default batch size
		?>
		<div class="ghl-card" style="margin-top: 20px;">
			<h2>🔧 Manual Queue Control</h2>
			<p class="description">Use this button to manually trigger the queue processor and process pending sync items immediately.</p>
			<button type="button" id="ghl-manual-queue-trigger" class="button button-primary button-large" style="margin-top: 15px;">
				<span class="dashicons dashicons-controls-play" style="margin-top: 3px;"></span> 
				Manually Run Queue Now
			</button>
			<p class="description" style="margin-top: 10px;">
				This will process up to <?php echo esc_html( $batch_size ); ?> items from the queue.
			</p>
		</div>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#ghl-manual-queue-trigger').on('click', function() {
				var $btn = $(this);
				var originalText = $btn.html();
				
				$btn.prop('disabled', true).html('<span class="dashicons dashicons-update ghl-spin" style="margin-top: 3px;"></span> Processing...');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ghl_crm_manual_queue_trigger',
						nonce: '<?php echo esc_js( $manual_nonce ); ?>'
					},
					success: function(response) {
						if (response.success) {
							alert('✓ Queue processed!\n\n' + 
								'Before: ' + response.data.before + ' items\n' +
								'Processed: ' + response.data.processed + ' items\n' +
								'Remaining: ' + response.data.remaining + ' items\n\n' +
								response.data.message);
							location.reload();
						} else {
							alert('✗ Error: ' + response.data.message);
							$btn.prop('disabled', false).html(originalText);
						}
					},
					error: function(xhr, status, error) {
						alert('✗ AJAX request failed: ' + error);
						$btn.prop('disabled', false).html(originalText);
					}
				});
			});
		});
		</script>
		
		<style>
		.dashicons.ghl-spin {
			animation: ghl-spin-animation 1s linear infinite;
		}
		@keyframes ghl-spin-animation {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		</style>
	<?php endif; ?>
</div>