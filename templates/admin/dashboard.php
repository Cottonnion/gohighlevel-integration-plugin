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
		<div class="ghl-dashboard-tabs">
			<div class="ghl-dashboard-tabs-bar">
				<button class="ghl-dashboard-tab active" data-tab="reports">
					<span class="dashicons dashicons-dashboard"></span>
					<?php esc_html_e( 'Dashboard', 'syncly' ); ?>
				</button>
				<button class="ghl-dashboard-tab" data-tab="analytics">
					<span class="dashicons dashicons-chart-area"></span>
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
			<div id="ghl-tab-analytics" class="ghl-tab-content">
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
			<p class="description">
				<?php esc_html_e( 'Choose your preferred connection method to get started. Both methods are secure and fully supported.', 'syncly' ); ?>
			</p>
			
			<?php include plugin_dir_path( __FILE__ ) . 'connection-setup.php'; ?>
		</div>
	<?php endif; ?>
</div>