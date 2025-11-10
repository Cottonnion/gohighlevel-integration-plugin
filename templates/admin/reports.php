<?php
/**
 * Reports & Analytics Template
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hardcoded data for now - will be replaced with real data later
$report_data = [
	'contacts' => [
		'total_ghl'       => 1247,
		'total_wp'        => 892,
		'synced'          => 856,
		'pending'         => 36,
		'failed'          => 12,
		'sync_rate'       => 96, // percentage
	],
	'sync_activity' => [
		'last_24h'        => 127,
		'last_7d'         => 543,
		'last_30d'        => 1823,
	],
	'integrations' => [
		'woocommerce'     => [
			'enabled'     => true,
			'orders'      => 234,
			'synced'      => 228,
		],
		'buddyboss'       => [
			'enabled'     => true,
			'groups'      => 12,
			'synced'      => 12,
		],
	],
	'system_health' => [
		'api_connection'  => 'healthy',
		'queue_status'    => 'healthy',
		'last_sync'       => '2 minutes ago',
		'pending_jobs'    => 3,
	],
	'recent_activity' => [
		['type' => 'success', 'message' => 'Contact synced: John Doe', 'time' => '2 minutes ago'],
		['type' => 'success', 'message' => 'Order #1234 synced to GHL', 'time' => '15 minutes ago'],
		['type' => 'warning', 'message' => 'Retry: Contact sync failed (will retry)', 'time' => '1 hour ago'],
		['type' => 'success', 'message' => 'BuddyBoss group synced: Marketing Team', 'time' => '2 hours ago'],
		['type' => 'success', 'message' => 'Contact synced: Jane Smith', 'time' => '3 hours ago'],
	],
];
?>

<div class="ghl-reports-dashboard">
	<!-- Quick Actions Bar -->
	<div style="background: white; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06); margin-bottom: 24px;">
		<h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
			<span class="dashicons dashicons-admin-tools" style="color: #6366f1;"></span>
			Quick Actions
		</h3>
		<div style="display: flex; gap: 12px; flex-wrap: wrap;">
			<button type="button" class="ghl-button ghl-button-primary" id="ghl-trigger-sync" style="display: flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-update" style="font-size: 16px;"></span>
				Run Manual Sync
			</button>
			<button type="button" class="ghl-button ghl-button-secondary" id="ghl-test-connection" style="display: flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-admin-site" style="font-size: 16px;"></span>
				Test Connection
			</button>
			<button type="button" class="ghl-button ghl-button-secondary" id="ghl-clear-cache" style="display: flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-trash" style="font-size: 16px;"></span>
				Clear Cache
			</button>
			<button type="button" class="ghl-button ghl-button-secondary" id="ghl-refresh-tags-fields" style="display: flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-update-alt" style="font-size: 16px;"></span>
				Refresh Tags &amp; Fields
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/sync-logs' ) ); ?>" class="ghl-button ghl-button-secondary" style="display: flex; align-items: center; gap: 6px; text-decoration: none;">
				<span class="dashicons dashicons-list-view" style="font-size: 16px;"></span>
				View Sync Logs
			</a>
		</div>
	</div>

	<!-- System Health Status -->
	<div style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06); margin-bottom: 24px;">
		<h3 style="margin: 0 0 20px; font-size: 18px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
			<span class="dashicons dashicons-heart" style="color: #10b981;"></span>
			System Health
		</h3>
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
			<div style="padding: 16px; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 8px;">
				<div style="font-size: 12px; color: #166534; font-weight: 600; margin-bottom: 4px;">API CONNECTION</div>
				<div style="font-size: 14px; color: #15803d; font-weight: 500;">✓ Connected & Healthy</div>
			</div>
			<div style="padding: 16px; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 8px;">
				<div style="font-size: 12px; color: #166534; font-weight: 600; margin-bottom: 4px;">SYNC QUEUE</div>
				<div style="font-size: 14px; color: #15803d; font-weight: 500;">✓ Running Smoothly</div>
			</div>
			<div style="padding: 16px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px;">
				<div style="font-size: 12px; color: #92400e; font-weight: 600; margin-bottom: 4px;">PENDING JOBS</div>
				<div style="font-size: 14px; color: #b45309; font-weight: 500;">⚠ <?php echo $report_data['system_health']['pending_jobs']; ?> jobs in queue</div>
			</div>
			<div style="padding: 16px; background: #f0fdf4; border-left: 4px solid #10b981; border-radius: 8px;">
				<div style="font-size: 12px; color: #166534; font-weight: 600; margin-bottom: 4px;">LAST SYNC</div>
				<div style="font-size: 14px; color: #15803d; font-weight: 500;">✓ <?php echo esc_html( $report_data['system_health']['last_sync'] ); ?></div>
			</div>
		</div>
	</div>

	<!-- Stats Overview Cards -->
	<div class="ghl-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
		<!-- Total GHL Contacts -->
		<div class="ghl-stat-card" style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
				<span class="dashicons dashicons-admin-users" style="font-size: 32px; color: #6366f1;"></span>
				<span style="font-size: 11px; color: #64748b; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-weight: 500;">GoHighLevel</span>
			</div>
			<div style="font-size: 32px; font-weight: 700; margin-bottom: 4px; color: #1e293b;"><?php echo number_format( $report_data['contacts']['total_ghl'] ); ?></div>
			<div style="font-size: 14px; color: #64748b; margin-bottom: 8px;">Total Contacts</div>
			<a href="#" class="ghl-view-in-ghl" style="font-size: 12px; color: #6366f1; text-decoration: none; font-weight: 500;">
				View in GoHighLevel →
			</a>
		</div>

		<!-- Total WP Users -->
		<div class="ghl-stat-card" style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
				<span class="dashicons dashicons-wordpress" style="font-size: 32px; color: #0891b2;"></span>
				<span style="font-size: 11px; color: #64748b; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-weight: 500;">WordPress</span>
			</div>
			<div style="font-size: 32px; font-weight: 700; margin-bottom: 4px; color: #1e293b;"><?php echo number_format( $report_data['contacts']['total_wp'] ); ?></div>
			<div style="font-size: 14px; color: #64748b; margin-bottom: 8px;">Total Users</div>
			<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" style="font-size: 12px; color: #0891b2; text-decoration: none; font-weight: 500;">
				Manage Users →
			</a>
		</div>

		<!-- Synced Contacts -->
		<div class="ghl-stat-card" style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
				<span class="dashicons dashicons-yes-alt" style="font-size: 32px; color: #10b981;"></span>
				<span style="font-size: 11px; color: #166534; background: #dcfce7; padding: 4px 8px; border-radius: 4px; font-weight: 600;"><?php echo $report_data['contacts']['sync_rate']; ?>%</span>
			</div>
			<div style="font-size: 32px; font-weight: 700; margin-bottom: 4px; color: #1e293b;"><?php echo number_format( $report_data['contacts']['synced'] ); ?></div>
			<div style="font-size: 14px; color: #64748b; margin-bottom: 8px;">Synced Contacts</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/sync-logs' ) ); ?>" style="font-size: 12px; color: #10b981; text-decoration: none; font-weight: 500;">
				View Sync Details →
			</a>
		</div>

		<!-- Pending/Failed -->
		<div class="ghl-stat-card" style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
				<span class="dashicons dashicons-warning" style="font-size: 32px; color: #f59e0b;"></span>
				<span style="font-size: 11px; color: #92400e; background: #fef3c7; padding: 4px 8px; border-radius: 4px; font-weight: 600;">Action Needed</span>
			</div>
			<div style="font-size: 32px; font-weight: 700; margin-bottom: 4px; color: #1e293b;"><?php echo number_format( $report_data['contacts']['pending'] + $report_data['contacts']['failed'] ); ?></div>
			<div style="font-size: 14px; color: #64748b; margin-bottom: 8px;">Pending + Failed</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/sync-logs/status/failed' ) ); ?>" style="font-size: 12px; color: #f59e0b; text-decoration: none; font-weight: 500;">
				Fix Issues →
			</a>
		</div>
	</div>

	<!-- Two Column Layout -->
	<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
		<!-- Left Column: Activity & Integrations -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<!-- Recent Sync Activity -->
			<div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
				<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
					<h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-clock" style="color: #6366f1;"></span>
						Recent Sync Activity
					</h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/sync-logs' ) ); ?>" style="font-size: 13px; color: #6366f1; text-decoration: none; font-weight: 500;">
						View All →
					</a>
				</div>
				
				<div style="display: flex; flex-direction: column; gap: 12px;">
					<?php foreach ( $report_data['recent_activity'] as $activity ) : ?>
					<div style="display: flex; align-items: start; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #f1f5f9;">
						<?php if ( $activity['type'] === 'success' ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color: #10b981; flex-shrink: 0; margin-top: 2px;"></span>
						<?php elseif ( $activity['type'] === 'warning' ) : ?>
							<span class="dashicons dashicons-warning" style="color: #f59e0b; flex-shrink: 0; margin-top: 2px;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-info" style="color: #3b82f6; flex-shrink: 0; margin-top: 2px;"></span>
						<?php endif; ?>
						<div style="flex: 1;">
							<div style="font-size: 14px; color: #1e293b; margin-bottom: 4px;"><?php echo esc_html( $activity['message'] ); ?></div>
							<div style="font-size: 12px; color: #94a3b8;"><?php echo esc_html( $activity['time'] ); ?></div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Active Integrations -->
			<div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
				<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
					<h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-admin-plugins" style="color: #6366f1;"></span>
						Active Integrations
					</h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/integrations' ) ); ?>" style="font-size: 13px; color: #6366f1; text-decoration: none; font-weight: 500;">
						Manage →
					</a>
				</div>
				
				<div style="display: flex; flex-direction: column; gap: 16px;">
					<!-- WooCommerce -->
					<?php if ( $report_data['integrations']['woocommerce']['enabled'] ) : ?>
					<div style="padding: 16px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #7c3aed; border: 1px solid #f1f5f9;">
						<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
							<div style="display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-cart" style="color: #7c3aed;"></span>
								<strong style="color: #1e293b;">WooCommerce</strong>
							</div>
							<span class="ghl-badge" style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Active</span>
						</div>
						<div style="font-size: 14px; color: #64748b; margin-bottom: 8px;">
							<?php echo number_format( $report_data['integrations']['woocommerce']['synced'] ); ?> / <?php echo number_format( $report_data['integrations']['woocommerce']['orders'] ); ?> orders synced
						</div>
						<div style="background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden;">
							<div style="background: #10b981; height: 100%; width: <?php echo round( ( $report_data['integrations']['woocommerce']['synced'] / $report_data['integrations']['woocommerce']['orders'] ) * 100 ); ?>%; transition: width 0.3s;"></div>
						</div>
					</div>
					<?php endif; ?>

					<!-- BuddyBoss -->
					<?php if ( $report_data['integrations']['buddyboss']['enabled'] ) : ?>
					<div style="padding: 16px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #f97316; border: 1px solid #f1f5f9;">
						<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
							<div style="display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-groups" style="color: #f97316;"></span>
								<strong style="color: #1e293b;">BuddyBoss</strong>
							</div>
							<span class="ghl-badge" style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Active</span>
						</div>
						<div style="font-size: 14px; color: #64748b; margin-bottom: 8px;">
							<?php echo number_format( $report_data['integrations']['buddyboss']['synced'] ); ?> / <?php echo number_format( $report_data['integrations']['buddyboss']['groups'] ); ?> groups synced
						</div>
						<div style="background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden;">
							<div style="background: #10b981; height: 100%; width: 100%; transition: width 0.3s;"></div>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Right Column: Connection Status & Resources -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<!-- Connection Status Widget -->
			<?php
			// Pass connection data to widget
			include plugin_dir_path( __FILE__ ) . 'connection-status.php';
			?>
			
			<!-- Quick Links -->
			<div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
				<h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-admin-links" style="color: #6366f1;"></span>
					Quick Links
				</h3>
				<div style="display: flex; flex-direction: column; gap: 8px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/settings' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-admin-settings" style="color: #6366f1; font-size: 16px;"></span>
						Plugin Settings
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/field-mapping' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-editor-table" style="color: #6366f1; font-size: 16px;"></span>
						Field Mapping
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/custom-objects' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-database" style="color: #6366f1; font-size: 16px;"></span>
						Custom Objects
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/sync-logs/status/success' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 16px;"></span>
						View Successful Syncs
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ghl-crm-admin#/sync-logs/status/failed' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-warning" style="color: #f59e0b; font-size: 16px;"></span>
						View Failed Syncs
					</a>
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-admin-users" style="color: #6366f1; font-size: 16px;"></span>
						WordPress Users
					</a>
				</div>
			</div>

			<!-- Common Issues & Solutions -->
			<div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
				<h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-sos" style="color: #f59e0b;"></span>
					Need Help?
				</h3>
				<div style="display: flex; flex-direction: column; gap: 12px;">
					<div style="padding: 12px; background: #fffbeb; border-left: 3px solid #f59e0b; border-radius: 6px;">
						<div style="font-size: 13px; font-weight: 600; color: #92400e; margin-bottom: 4px;">Contacts not syncing?</div>
						<div style="font-size: 12px; color: #78350f;">Check API connection and make sure field mapping is configured.</div>
					</div>
					<div style="padding: 12px; background: #fffbeb; border-left: 3px solid #f59e0b; border-radius: 6px;">
						<div style="font-size: 13px; font-weight: 600; color: #92400e; margin-bottom: 4px;">Queue stuck?</div>
						<div style="font-size: 12px; color: #78350f;">Try clearing cache or running manual sync from Quick Actions above.</div>
					</div>
					<div style="padding: 12px; background: #fffbeb; border-left: 3px solid #f59e0b; border-radius: 6px;">
						<div style="font-size: 13px; font-weight: 600; color: #92400e; margin-bottom: 4px;">Missing data in GHL?</div>
						<div style="font-size: 12px; color: #78350f;">Review sync logs to see what errors occurred and retry failed items.</div>
					</div>
				</div>
			</div>

			<!-- Support Resources -->
			<div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(99,102,241,0.2);">
				<h3 style="margin: 0 0 12px; font-size: 16px; font-weight: 600; color: white;">Documentation</h3>
				<p style="margin: 0 0 16px; font-size: 13px; color: rgba(255,255,255,0.9); line-height: 1.5;">
					Step-by-step guides, video tutorials, and troubleshooting help.
				</p>
				<a href="#" class="ghl-button" style="background: white; color: #6366f1; border: none; font-weight: 600; width: 100%; text-align: center; padding: 10px; text-decoration: none; display: block;">
					View Documentation
				</a>
			</div>
		</div>
	</div>
</div>
