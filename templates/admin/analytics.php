<?php
/**
 * Analytics Upgrade Preview Template.
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$upgrade_url = apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/' );
?>

<div class="ghl-analytics-upgrade-preview" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
	<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 22px;">
		<div>
			<span style="display: inline-flex; align-items: center; padding: 3px 9px; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 999px; color: #3730a3; font-size: 11px; font-weight: 700; text-transform: uppercase;">
				<?php esc_html_e( 'Syncly Pro', 'syncly' ); ?>
			</span>
			<h2 style="margin: 8px 0 6px; font-size: 24px; font-weight: 700; color: #1e293b;">
				<?php esc_html_e( 'Sync Analytics Dashboard', 'syncly' ); ?>
			</h2>
			<p style="margin: 0; color: #64748b; font-size: 14px;">
				<?php esc_html_e( 'Track sync volume, success rates, activity trends, and export performance reports.', 'syncly' ); ?>
			</p>
		</div>
		<a href="<?php echo esc_url( $upgrade_url ); ?>" class="ghl-button ghl-button-primary" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
			<span class="dashicons dashicons-unlock"></span>
			<?php esc_html_e( 'Learn More', 'syncly' ); ?>
		</a>
	</div>

	<div aria-hidden="true" style="display: grid; gap: 20px; opacity: 0.82;">
		<div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; background: #f8fafc;">
			<div style="display: flex; justify-content: space-between; gap: 12px; margin-bottom: 14px;">
				<strong style="color: #1e293b;"><?php esc_html_e( 'Sync Activity - Last 30 Days', 'syncly' ); ?></strong>
				<span style="color: #64748b; font-size: 12px;"><?php esc_html_e( 'Successful vs Failed', 'syncly' ); ?></span>
			</div>
			<div style="height: 180px; display: flex; align-items: end; gap: 10px; padding: 14px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
				<?php foreach ( [ 48, 72, 56, 86, 64, 92, 76, 110, 88, 124, 96, 138 ] as $height ) : ?>
					<span style="flex: 1; height: <?php echo esc_attr( (string) $height ); ?>px; min-width: 10px; background: linear-gradient(180deg, #6366f1, #10b981); border-radius: 5px 5px 2px 2px;"></span>
				<?php endforeach; ?>
			</div>
		</div>

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
			<div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; background: #f8fafc;">
				<strong style="display: block; margin-bottom: 14px; color: #1e293b;"><?php esc_html_e( 'Syncs by Type', 'syncly' ); ?></strong>
				<div style="width: 132px; height: 132px; margin: 0 auto; border-radius: 50%; background: conic-gradient(#6366f1 0 42%, #10b981 42% 74%, #f59e0b 74% 100%);"></div>
			</div>

			<div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; background: #f8fafc;">
				<strong style="display: block; margin-bottom: 14px; color: #1e293b;"><?php esc_html_e( 'Success Rate Trend', 'syncly' ); ?></strong>
				<div style="display: grid; gap: 10px;">
					<div style="height: 12px; width: 92%; background: #10b981; border-radius: 999px;"></div>
					<div style="height: 12px; width: 84%; background: #22c55e; border-radius: 999px;"></div>
					<div style="height: 12px; width: 96%; background: #14b8a6; border-radius: 999px;"></div>
					<div style="height: 12px; width: 88%; background: #6366f1; border-radius: 999px;"></div>
				</div>
			</div>
		</div>
	</div>
</div>
