<?php
/**
 * Connection Status Widget (Sidebar)
 * Displays a compact connection status for the reports dashboard
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get connection data (these are expected to be passed from parent template)
$oauth_handler = $oauth_handler ?? new \GHL_CRM\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_status ?? $oauth_handler->get_connection_status();
$settings      = $settings ?? \GHL_CRM\Core\SettingsManager::get_instance()->get_settings_array();

$is_connected = $oauth_status['connected'] || ! empty( $settings['api_token'] );
?>

<!-- Connection Status Widget -->
<div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; margin-bottom: 20px;">
	<h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
		<span class="dashicons dashicons-admin-site" style="color: #6366f1;"></span>
		Connection Status
	</h3>
	
	<?php if ( $is_connected ) : ?>
		<?php if ( $oauth_status['connected'] ) : ?>
			<!-- OAuth Connected -->
			<div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
				<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
					<span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 20px;"></span>
					<strong style="color: #166534; font-size: 14px;">Connected (OAuth)</strong>
				</div>
				<?php
				$location_name = $settings['location_name'] ?? 'GoHighLevel';
				if ( ! empty( $location_name ) && $location_name !== 'GoHighLevel' ) :
				?>
					<div style="font-size: 13px; color: #15803d; margin-bottom: 4px;">
						<strong><?php echo esc_html( $location_name ); ?></strong>
					</div>
				<?php endif; ?>
				<div style="font-size: 12px; color: #166534;">
					<?php
					$expires_at = $oauth_status['expires_at'];
					if ( $expires_at && $expires_at > time() ) {
						$time_left = human_time_diff( time(), $expires_at );
						printf(
							/* translators: %s: Time remaining */
							esc_html__( 'Token expires in %s', 'ghl-crm-integration' ),
							esc_html( $time_left )
						);
					} else {
						esc_html_e( 'Token expired (auto-refresh)', 'ghl-crm-integration' );
					}
					?>
				</div>
			</div>

			<!-- Connection Details -->
			<div style="background: #f8fafc; border-radius: 6px; padding: 12px; margin-bottom: 12px;">
				<div style="display: flex; flex-direction: column; gap: 8px; font-size: 12px;">
					<div style="display: flex; justify-content: space-between;">
						<span style="color: #64748b;">Location ID:</span>
						<code style="font-size: 11px; background: white; padding: 2px 6px; border-radius: 3px; color: #1e293b;"><?php echo esc_html( substr( $oauth_status['location_id'] ?: 'N/A', 0, 12 ) ); ?>...</code>
					</div>
					<?php if ( ! empty( $oauth_status['connected_at'] ) ) : ?>
					<div style="display: flex; justify-content: space-between;">
						<span style="color: #64748b;">Connected:</span>
						<span style="color: #1e293b;"><?php echo esc_html( $oauth_status['connected_at'] ); ?></span>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<button type="button" class="ghl-button ghl-button-secondary" id="ghl-disconnect-btn" style="width: 100%; justify-content: center; text-align: center; font-size: 13px;">
				<span class="dashicons dashicons-dismiss" style="font-size: 14px; margin-top: 3px;"></span>
				<?php esc_html_e( 'Disconnect', 'ghl-crm-integration' ); ?>
			</button>

		<?php else : ?>
			<!-- API Key Connected -->
			<div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
				<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
					<span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 20px;"></span>
					<strong style="color: #166534; font-size: 14px;">Connected (API Key)</strong>
				</div>
				<div style="font-size: 12px; color: #166534;">
					<?php esc_html_e( 'Using manual API key', 'ghl-crm-integration' ); ?>
				</div>
			</div>

			<!-- Connection Details -->
			<div style="background: #f8fafc; border-radius: 6px; padding: 12px; margin-bottom: 12px;">
				<div style="display: flex; flex-direction: column; gap: 8px; font-size: 12px;">
					<div style="display: flex; justify-content: space-between;">
						<span style="color: #64748b;">Location ID:</span>
						<code style="font-size: 11px; background: white; padding: 2px 6px; border-radius: 3px; color: #1e293b;"><?php echo esc_html( substr( $settings['location_id'] ?? 'N/A', 0, 12 ) ); ?>...</code>
					</div>
					<div style="display: flex; justify-content: space-between;">
						<span style="color: #64748b;">API Token:</span>
						<code style="font-size: 11px; background: white; padding: 2px 6px; border-radius: 3px; color: #1e293b;"><?php echo esc_html( substr( $settings['api_token'], 0, 8 ) ); ?>...</code>
					</div>
				</div>
			</div>

			<div style="display: flex; flex-direction: column; gap: 8px;">
				<button type="button" class="ghl-button ghl-button-secondary" id="ghl-disconnect-api-btn" style="width: 100%; justify-content: center; text-align: center; font-size: 13px;">
					<span class="dashicons dashicons-dismiss" style="font-size: 14px; margin-top: 3px;"></span>
					<?php esc_html_e( 'Disconnect', 'ghl-crm-integration' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/settings' ) ); ?>" class="ghl-button" style=" text-align: center; font-size: 12px; text-decoration: none;">
					<?php esc_html_e( 'Update Settings', 'ghl-crm-integration' ); ?>
				</a>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<!-- Not Connected -->
		<div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
			<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
				<span class="dashicons dashicons-warning" style="color: #f59e0b; font-size: 20px;"></span>
				<strong style="color: #92400e; font-size: 14px;">Not Connected</strong>
			</div>
			<div style="font-size: 12px; color: #92400e; line-height: 1.5;">
				<?php esc_html_e( 'Connect to GoHighLevel to start syncing data.', 'ghl-crm-integration' ); ?>
			</div>
		</div>
		
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-dashboard' ) ); ?>" class="ghl-button ghl-button-primary" style="width: 100%; justify-content: center; text-align: center; font-size: 13px;">
			<span class="dashicons dashicons-admin-site" style="font-size: 14px; margin-top: 3px;"></span>
			<?php esc_html_e( 'Setup Connection', 'ghl-crm-integration' ); ?>
		</a>
	<?php endif; ?>
</div>