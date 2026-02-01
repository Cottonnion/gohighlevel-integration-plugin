<?php
/**
 * Analytics Dashboard Template
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$analytics_data = \GHL_CRM\Core\Dashboard\StatsProvider::get_instance()->get_analytics_data();
$is_logging_enabled = \GHL_CRM\Core\SettingsManager::is_sync_logging_enabled();
?>

<div class="ghl-analytics-dashboard">
	<!-- Header -->
	<div style="background: white; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06); margin-bottom: 24px;">
		<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
			<div>
				<h2 style="margin: 0 0 8px; font-size: 24px; font-weight: 700; color: #1e293b;">
					<?php esc_html_e( 'Sync Analytics Dashboard', 'ghl-crm-integration' ); ?>
				</h2>
				<p style="margin: 0; color: #64748b; font-size: 14px;">
					<?php esc_html_e( 'Real-time insights into your GoHighLevel integration performance', 'ghl-crm-integration' ); ?>
				</p>
			</div>
			<div style="display: flex; gap: 12px;">
				<button type="button" class="ghl-button ghl-button-secondary" id="ghl-refresh-analytics">
					<span class="dashicons dashicons-update-alt" style="margin-right: 6px;"></span>
					<?php esc_html_e( 'Refresh Data', 'ghl-crm-integration' ); ?>
				</button>
				<button type="button" class="ghl-button ghl-button-primary" id="ghl-export-analytics">
					<span class="dashicons dashicons-download" style="margin-right: 6px;"></span>
					<?php esc_html_e( 'Export CSV', 'ghl-crm-integration' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Logging Status Notice -->
	<?php if ( ! $is_logging_enabled ) : ?>
		<div style="background: #fef3c7; border: 1px solid #fbbf24; border-left-width: 4px; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<div style="display: flex; align-items: start; gap: 12px;">
				<span class="dashicons dashicons-warning" style="color: #f59e0b; font-size: 24px; flex-shrink: 0;"></span>
				<div style="flex: 1;">
					<h3 style="margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #92400e;">
						<?php esc_html_e( 'Sync Logging is Disabled', 'ghl-crm-integration' ); ?>
					</h3>
					<p style="margin: 0 0 12px; color: #78350f; font-size: 14px; line-height: 1.6;">
						<?php esc_html_e( 'Analytics data requires sync logging to be enabled. Without logging, the charts below will not display any data. Enable sync logging to start tracking your integration performance.', 'ghl-crm-integration' ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#advanced' ) ); ?>" 
					   class="ghl-button ghl-button-secondary" 
					   style="display: inline-flex; align-items: center; gap: 6px; background: white; border: 1px solid #f59e0b; color: #92400e; padding: 8px 16px; text-decoration: none;">
						<span class="dashicons dashicons-admin-settings" style="font-size: 16px;"></span>
						<?php esc_html_e( 'Enable in Advanced Settings', 'ghl-crm-integration' ); ?>
					</a>
				</div>
			</div>
		</div>
	<?php else : ?>
		<div style="background: #ecfdf5; border: 1px solid #10b981; border-left-width: 4px; padding: 12px 20px; border-radius: 8px; margin-bottom: 24px;">
			<div style="display: flex; align-items: center; gap: 12px;">
				<span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 20px;"></span>
				<div>
					<strong style="color: #065f46; font-size: 14px; font-weight: 600;">
						<?php esc_html_e( 'Sync Logging is Active', 'ghl-crm-integration' ); ?>
					</strong>
					<span style="color: #047857; font-size: 13px; margin-left: 8px;">
						<?php esc_html_e( 'Analytics data is being collected from your sync logs.', 'ghl-crm-integration' ); ?>
					</span>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Charts Grid -->
	<div style="display: grid; gap: 24px;">
		
		<!-- Sync Activity Over Time (Line Chart) -->
		<div style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
				<h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;">
					<span class="dashicons dashicons-chart-line" style="color: #6366f1; margin-right: 8px;"></span>
					<?php esc_html_e( 'Sync Activity - Last 30 Days', 'ghl-crm-integration' ); ?>
				</h3>
				<div class="chart-legend" style="display: flex; gap: 16px; font-size: 13px;">
					<div style="display: flex; align-items: center; gap: 6px;">
						<span style="width: 12px; height: 12px; background: #10b981; border-radius: 2px;"></span>
						<span style="color: #64748b;"><?php esc_html_e( 'Successful', 'ghl-crm-integration' ); ?></span>
					</div>
					<div style="display: flex; align-items: center; gap: 6px;">
						<span style="width: 12px; height: 12px; background: #ef4444; border-radius: 2px;"></span>
						<span style="color: #64748b;"><?php esc_html_e( 'Failed', 'ghl-crm-integration' ); ?></span>
					</div>
				</div>
			</div>
			<div style="position: relative; height: 300px;">
				<canvas id="ghl-daily-activity-chart"></canvas>
			</div>
		</div>

		<!-- Two Column Layout for Medium Charts -->
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
			
			<!-- Sync Type Breakdown (Pie Chart) -->
			<div style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
				<h3 style="margin: 0 0 20px; font-size: 18px; font-weight: 600; color: #1e293b;">
					<span class="dashicons dashicons-chart-pie" style="color: #6366f1; margin-right: 8px;"></span>
					<?php esc_html_e( 'Syncs by Type', 'ghl-crm-integration' ); ?>
				</h3>
				<div style="position: relative; height: 300px;">
					<canvas id="ghl-sync-type-chart"></canvas>
				</div>
			</div>

			<!-- Success vs Failure Rates (Pie Chart) -->
			<div style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
				<h3 style="margin: 0 0 20px; font-size: 18px; font-weight: 600; color: #1e293b;">
					<span class="dashicons dashicons-chart-pie" style="color: #6366f1; margin-right: 8px;"></span>
					<?php esc_html_e( 'Success vs Failure Rate', 'ghl-crm-integration' ); ?>
				</h3>
				<div style="position: relative; height: 300px;">
					<canvas id="ghl-success-failure-chart"></canvas>
				</div>
			</div>

		</div>

		<!-- Hourly Activity (Bar Chart) -->
		<div style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<h3 style="margin: 0 0 20px; font-size: 18px; font-weight: 600; color: #1e293b;">
				<span class="dashicons dashicons-chart-bar" style="color: #6366f1; margin-right: 8px;"></span>
				<?php esc_html_e( 'Hourly Activity - Last 24 Hours', 'ghl-crm-integration' ); ?>
			</h3>
			<div style="position: relative; height: 300px;">
				<canvas id="ghl-hourly-activity-chart"></canvas>
			</div>
		</div>

		<!-- Success Rate Trend (Line Chart) -->
		<div style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<h3 style="margin: 0 0 20px; font-size: 18px; font-weight: 600; color: #1e293b;">
				<span class="dashicons dashicons-chart-line" style="color: #6366f1; margin-right: 8px;"></span>
				<?php esc_html_e( 'Success Rate Trend - Last 7 Days', 'ghl-crm-integration' ); ?>
			</h3>
			<div style="position: relative; height: 300px;">
				<canvas id="ghl-success-rate-trend-chart"></canvas>
			</div>
		</div>

	</div>
</div>

<!-- Analytics data for JavaScript -->
<script type="application/json" id="ghl-analytics-data">
	<?php echo wp_json_encode( $analytics_data, JSON_PRETTY_PRINT ); ?>
</script>