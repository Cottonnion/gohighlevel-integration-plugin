<?php
/**
 * Dashboard Template
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get OAuth handler and status
$oauth_handler = new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$settings      = \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();
?>

<div class="ghl-crm-dashboard">
	<div class="ghl-card">
		<h2><?php esc_html_e( 'Dashboard', 'ghl-crm-integration' ); ?></h2>
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
				$table_name = $wpdb->prefix . 'ghl_crm_sync_logs';
				
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

			<?php else : ?>
				<div class="ghl-status-disconnected">
					<span class="dashicons dashicons-warning"></span>
					<strong><?php esc_html_e( 'Not Connected', 'ghl-crm-integration' ); ?></strong>
					<p><?php esc_html_e( 'Connect your GoHighLevel account to start syncing data.', 'ghl-crm-integration' ); ?></p>
					
					<a href="<?php echo esc_url( $oauth_handler->get_authorization_url() ); ?>" class="button button-primary button-hero" style="margin-top: 15px;">
						<span class="dashicons dashicons-cloud" style="margin-top: 5px;"></span>
						<?php esc_html_e( 'Connect to GoHighLevel', 'ghl-crm-integration' ); ?>
					</a>
					<p class="description" style="margin-top: 10px;">
						<?php esc_html_e( 'You will be redirected to GoHighLevel to authorize this integration.', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#ghl-disconnect-btn').on('click', function(e) {
		e.preventDefault();
		
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect your GoHighLevel account?', 'ghl-crm-integration' ) ); ?>')) {
			return;
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_crm_oauth_disconnect',
				nonce: '<?php echo esc_js( wp_create_nonce( 'ghl_crm_oauth_disconnect' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || '<?php echo esc_js( __( 'Failed to disconnect', 'ghl-crm-integration' ) ); ?>');
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'An error occurred while disconnecting', 'ghl-crm-integration' ) ); ?>');
			}
		});
	});
});
</script>
