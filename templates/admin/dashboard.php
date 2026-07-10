<?php
/**
 * Dashboard Template
 *
 * @package Syncly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get OAuth handler and status
$oauth_handler = new \Syncly\API\OAuth\OAuthHandler();
$oauth_status  = $oauth_handler->get_connection_status();
$settings      = \Syncly\Core\SettingsManager::get_instance()->get_settings_array();

$is_connected  = $oauth_status['connected'];
$is_pro_active = (bool) apply_filters( 'syncly_is_pro_active', false );
$has_analytics = $is_pro_active && has_action( 'syncly_render_analytics_tab' );
?>
<div class="syncly-dashboard">
	<?php if ( $is_connected ) : ?>
		<!-- Tab Navigation -->
		<div class="ghl-dashboard-tabs" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px 12px 0 0; padding: 0; margin-bottom: -1px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<div style="display: flex; gap: 8px; padding: 16px 24px; border-bottom: 1px solid #e2e8f0;">
				<button class="ghl-dashboard-tab active" data-tab="reports" style="padding: 10px 20px; border: none; background: #6366f1; color: white; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;">
					<span class="dashicons dashicons-dashboard" style="vertical-align: middle; margin-right: 6px;"></span>
					<?php esc_html_e( 'Dashboard', 'syncly' ); ?>
				</button>
				<button class="ghl-dashboard-tab" data-tab="analytics" style="padding: 10px 20px; border: none; background: transparent; color: #64748b; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;">
					<span class="dashicons dashicons-chart-area" style="vertical-align: middle; margin-right: 6px;"></span>
					<?php esc_html_e( 'Analytics', 'syncly' ); ?>
				</button>
			</div>
		</div>
		
		<!-- Tab Content -->
		<div class="ghl-dashboard-content">
			<!-- Reports Tab -->
			<div id="ghl-tab-reports" class="ghl-tab-content active">
				<?php include plugin_dir_path( __FILE__ ) . 'reports.php'; ?>
			</div>
			
			<!-- Analytics Tab -->
			<div id="ghl-tab-analytics" class="ghl-tab-content" style="display: none;">
				<?php if ( $has_analytics ) : ?>
					<?php do_action( 'syncly_render_analytics_tab' ); ?>
				<?php else : ?>
					<?php include plugin_dir_path( __FILE__ ) . 'analytics.php'; ?>
				<?php endif; ?>
			</div>
		</div>

	<?php else : ?>
		<!-- Not Connected - Show Connection Setup -->
		<div class="ghl-card">
			<h2><?php esc_html_e( 'Connect to GoHighLevel', 'syncly' ); ?></h2>
			<p class="description" style="margin-bottom: 20px;">
				<?php esc_html_e( 'Choose your preferred connection method to get started. Both methods are secure and fully supported.', 'syncly' ); ?>
			</p>
			
			<?php include plugin_dir_path( __FILE__ ) . 'connection-setup.php'; ?>
		</div>
	<?php endif; ?>
</div>