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

$is_connected  = $oauth_status['connected'] || ! empty( $settings['api_token'] );
$has_analytics = has_action( 'ghl_crm_render_analytics_tab' );
?>
<div class="ghl-crm-dashboard">
	<div style="background: #6366f1; color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px;">
		<span style="font-size: 18px;">🚀</span>
		<?php esc_html_e( 'Auto-deployed via GitHub Actions — CD pipeline is working!', 'ghl-crm-integration' ); ?>
		<span style="margin-left: auto; opacity: 0.8; font-weight: 400; font-size: 12px;"><?php echo esc_html( gmdate( 'Y-m-d H:i' ) . ' UTC' ); ?></span>
	</div>
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
					<?php if ( ! $has_analytics ) : ?>
						<span style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 4px; font-weight: 700;">PRO</span>
					<?php endif; ?>
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
					<?php do_action( 'ghl_crm_render_analytics_tab' ); ?>
				<?php else : ?>
					<div style="background: white; border: 1px solid #e2e8f0; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
						<span class="dashicons dashicons-chart-area" style="font-size: 48px; width: 48px; height: 48px; color: #c7d2fe; margin-bottom: 16px;"></span>
						<h3 style="margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #1e293b;"><?php esc_html_e( 'Sync Analytics Dashboard', 'ghl-crm-integration' ); ?></h3>
						<p style="margin: 0 0 20px; color: #64748b; font-size: 14px; max-width: 480px; margin-left: auto; margin-right: auto;">
							<?php esc_html_e( 'Unlock detailed Chart visualizations including daily activity trends, sync type breakdowns, hourly activity, success/failure rates, and CSV export.', 'ghl-crm-integration' ); ?>
						</p>
						<a href="<?php echo esc_url( apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/upgrade-to-pro' ) ); ?>" target="_blank" class="ghl-button ghl-button-primary" style="text-decoration: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none;">
							<?php esc_html_e( 'Upgrade to Pro', 'ghl-crm-integration' ); ?>
						</a>
					</div>
				<?php endif; ?>
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