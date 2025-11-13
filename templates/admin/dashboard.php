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

$is_connected = $oauth_status['connected'] || ! empty( $settings['api_token'] );
?>
<div class="ghl-crm-dashboard">
	<?php if ( $is_connected ) : ?>
		<!-- Tab Navigation -->
		<div class="ghl-dashboard-tabs" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px 12px 0 0; padding: 0; margin-bottom: -1px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<div style="display: flex; gap: 8px; padding: 16px 24px; border-bottom: 1px solid #e2e8f0;">
				<button class="ghl-dashboard-tab active" data-tab="reports" style="padding: 10px 20px; border: none; background: #6366f1; color: white; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;">
					<span class="dashicons dashicons-dashboard" style="vertical-align: middle; margin-right: 6px;"></span>
					<?php esc_html_e( 'Dashboard', 'ghl-crm-integration' ); ?>
				</button>
				<button class="ghl-dashboard-tab" data-tab="analytics" style="padding: 10px 20px; border: none; background: transparent; color: #64748b; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;">
					<span class="dashicons dashicons-chart-area" style="vertical-align: middle; margin-right: 6px;"></span>
					<?php esc_html_e( 'Analytics', 'ghl-crm-integration' ); ?>
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
				<?php include plugin_dir_path( __FILE__ ) . 'analytics.php'; ?>
			</div>
		</div>

		<style>
			.ghl-dashboard-tab {
				transition: all 0.2s ease;
			}
			.ghl-dashboard-tab:hover {
				background: #f1f5f9 !important;
				color: #1e293b !important;
			}
			.ghl-dashboard-tab.active {
				background: #6366f1 !important;
				color: white !important;
			}
			.ghl-dashboard-tab.active:hover {
				background: #4f46e5 !important;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('.ghl-dashboard-tab').on('click', function() {
				const tab = $(this).data('tab');
				
				// Update active states
				$('.ghl-dashboard-tab').removeClass('active');
				$(this).addClass('active');
				
				// Show/hide content
				$('.ghl-tab-content').hide();
				$('#ghl-tab-' + tab).show();
				
				// Store preference
				localStorage.setItem('ghl_active_dashboard_tab', tab);
			});
			
			// Restore last active tab
			const lastTab = localStorage.getItem('ghl_active_dashboard_tab');
			if (lastTab && lastTab === 'analytics') {
				$('.ghl-dashboard-tab[data-tab="analytics"]').trigger('click');
			}
		});
		</script>
		
	<?php else : ?>
		<!-- Not Connected - Show Connection Setup -->
		<div class="ghl-card">
			<h2><?php esc_html_e( 'Connect to GoHighLevel', 'ghl-crm-integration' ); ?></h2>
			<p class="description" style="margin-bottom: 20px;">
				<?php esc_html_e( 'Choose your preferred connection method to get started. Both methods are secure and fully supported.', 'ghl-crm-integration' ); ?>
			</p>
			
			<?php include plugin_dir_path( __FILE__ ) . 'connection-setup.php'; ?>
		</div>
	<?php endif; ?>
</div>