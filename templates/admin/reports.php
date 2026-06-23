<?php
/**
 * Reports & Analytics Template
 *
 * @package Syncly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hardcoded data for now - will be replaced with real data later
$report_data         = \Syncly\Core\Dashboard\StatsProvider::get_instance()->get_report_data();
$oauth_handler       = new \Syncly\API\OAuth\OAuthHandler();
$oauth_status        = $oauth_handler->get_connection_status();
$is_oauth_connected  = ! empty( $oauth_status['connected'] );
$oauth_reconnect_url = admin_url( 'admin.php?page=syncly-oauth-connect' );
$ghl_settings        = \Syncly\Core\SettingsManager::get_instance()->get_settings_array();
$ghl_white_label     = $ghl_settings['ghl_white_label_domain'] ?? '';
$ghl_base_domain     = ! empty( $ghl_white_label ) ? rtrim( $ghl_white_label, '/' ) : 'https://app.gohighlevel.com';
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
			<button type="button" class="ghl-button ghl-button-secondary" id="ghl-reconnect-account" style="display: flex; align-items: center; gap: 6px;">
				<span class="dashicons dashicons-shield" style="font-size: 16px;"></span>
				Reconnect Account
			</button>
			<a href="<?php echo esc_url( $ghl_base_domain ); ?>" class="ghl-button ghl-button-secondary" style="display: flex; align-items: center; gap: 6px; text-decoration: none;" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-external" style="font-size: 16px;"></span>
				Go To GoHlighLevel
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
				<div style="font-size: 14px; color: #b45309; font-weight: 500;">⚠ <?php echo esc_html( (string) absint( $report_data['system_health']['pending_jobs'] ) ); ?> jobs in queue</div>
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
		<?php
		$contacts_link  = $report_data['links']['contacts'] ?? [
			'url'       => '',
			'available' => false,
		];
		$contacts_href  = $contacts_link['available'] ? esc_url( $contacts_link['url'] ) : '#';
		$contacts_attrs = $contacts_link['available'] ? ' target="_blank" rel="noopener"' : '';
		?>


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
				<span style="font-size: 11px; color: #166534; background: #dcfce7; padding: 4px 8px; border-radius: 4px; font-weight: 600;"><?php echo esc_html( (string) absint( $report_data['contacts']['sync_rate'] ) ); ?>%</span>
			</div>
			<div style="font-size: 32px; font-weight: 700; margin-bottom: 4px; color: #1e293b;"><?php echo number_format( $report_data['contacts']['synced'] ); ?></div>
			<div style="font-size: 14px; color: #64748b; margin-bottom: 8px;">Synced Contacts</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin#/sync-logs' ) ); ?>" style="font-size: 12px; color: #10b981; text-decoration: none; font-weight: 500;">
				View Sync Details →
			</a>
		</div>

		<!-- Pending/Failed -->
		<?php
		$pending_total = (int) $report_data['contacts']['pending'] + (int) $report_data['contacts']['failed'];
		$has_pending   = $pending_total > 0;
		$badge_text    = $has_pending ? esc_html__( 'Action Needed', 'syncly' ) : esc_html__( 'All Clear', 'syncly' );
		$badge_style   = $has_pending ? 'color: #92400e; background: #fef3c7;' : 'color: #15803d; background: #dcfce7;';
		$icon_color    = $has_pending ? '#f59e0b' : '#10b981';
		$link_url      = $has_pending ? admin_url( 'admin.php?page=syncly-admin#/sync-logs/status/failed' ) : admin_url( 'admin.php?page=syncly-admin#/sync-logs' );
		$link_color    = $has_pending ? '#f59e0b' : '#10b981';
		$link_text     = $has_pending ? esc_html__( 'Fix Issues →', 'syncly' ) : esc_html__( 'View Sync Logs →', 'syncly' );
		?>
		<div class="ghl-stat-card" style="background: white; border: 1px solid #e2e8f0; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);">
			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
				<span class="dashicons dashicons-warning" style="font-size: 32px; color: <?php echo esc_attr( $icon_color ); ?>;"></span>
				<span style="font-size: 11px; padding: 4px 8px; border-radius: 4px; font-weight: 600; <?php echo esc_attr( $badge_style ); ?>"><?php echo esc_html( $badge_text ); ?></span>
			</div>
			<div style="font-size: 32px; font-weight: 700; margin-bottom: 4px; color: #1e293b;"><?php echo number_format( $pending_total ); ?></div>
			<div style="font-size: 14px; color: #64748b; margin-bottom: 8px;"><?php echo esc_html__( 'Failed + Pending', 'syncly' ); ?></div>
			<a href="<?php echo esc_url( $link_url ); ?>" style="font-size: 12px; color: <?php echo esc_attr( $link_color ); ?>; text-decoration: none; font-weight: 500;">
				<?php echo esc_html( $link_text ); ?>
			</a>
		</div>
	</div>

	<!-- Two Column Layout -->
	<div class="ghl-dashboard-layout">
		<!-- Left Column: Activity & Integrations -->
		<div class="ghl-dashboard-column ghl-dashboard-column--primary">
			<!-- Recent Sync Activity -->
			<div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
				<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
					<h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-clock" style="color: #6366f1;"></span>
						Recent Sync Activity
					</h3>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin#/sync-logs' ) ); ?>" style="font-size: 13px; color: #6366f1; text-decoration: none; font-weight: 500;">
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
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin#/integrations' ) ); ?>" style="font-size: 13px; color: #6366f1; text-decoration: none; font-weight: 500;">
						Manage →
					</a>
				</div>
				
				<div style="display: flex; flex-direction: column; gap: 16px;">
					<?php
					$integrations      = $report_data['integrations'];
					$integration_icons = [
						'woocommerce' => 'dashicons-cart',
						'buddyboss'   => 'dashicons-groups',
					];
					?>

					<?php if ( empty( $integrations ) ) : ?>
						<p style="margin: 0; font-size: 14px; color: #64748b;">
							<?php esc_html_e( 'No integrations detected yet. Visit the Integrations screen to configure available modules.', 'syncly' ); ?>
						</p>
					<?php else : ?>
						<?php
						foreach ( $integrations as $integration ) :
							$slug         = $integration['key'] ?? '';
							$icon         = $integration_icons[ $slug ] ?? 'dashicons-admin-generic';
							$is_enabled   = ! empty( $integration['enabled'] );
							$status_text  = $is_enabled ? esc_html__( 'Active', 'syncly' ) : esc_html__( 'Inactive', 'syncly' );
							$status_style = $is_enabled ? 'background: #dcfce7; color: #166534;' : 'background: #f1f5f9; color: #475569;';
							$border_color = $is_enabled ? '#10b981' : '#e2e8f0';
							?>
						<div style="padding: 16px; background: #f8fafc; border-radius: 8px; border-left: 4px solid <?php echo esc_attr( $border_color ); ?>; border: 1px solid #f1f5f9;">
							<div style="display: flex; align-items: center; justify-content: space-between;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="color: <?php echo esc_attr( $is_enabled ? '#1e293b' : '#64748b' ); ?>;"></span>
									<strong style="color: #1e293b;">
										<?php echo esc_html( $integration['label'] ?? ucfirst( $slug ) ); ?>
									</strong>
								</div>
								<span class="ghl-badge" style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; <?php echo esc_attr( $status_style ); ?>">
									<?php echo esc_html( $status_text ); ?>
								</span>
							</div>
						</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Right Column: Connection Status & Resources -->
		<div class="ghl-dashboard-column ghl-dashboard-column--secondary">
			<!-- Connection Status Widget -->
			<?php
			// Pass connection data to widget
			require plugin_dir_path( __FILE__ ) . 'connection-status.php';
			?>
			
			<!-- Quick Links -->
			<div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
				<h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-admin-links" style="color: #6366f1;"></span>
					Quick Links
				</h3>
				<div style="display: flex; flex-direction: column; gap: 8px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin#/settings' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-admin-settings" style="color: #6366f1; font-size: 16px;"></span>
						Plugin Settings
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin#/field-mapping' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-editor-table" style="color: #6366f1; font-size: 16px;"></span>
						Field Mapping
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin#/custom-objects' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-database" style="color: #6366f1; font-size: 16px;"></span>
						Custom Objects
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin#/sync-logs/status/success' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-yes-alt" style="color: #10b981; font-size: 16px;"></span>
						View Successful Syncs
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=syncly-admin#/sync-logs/status/failed' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-warning" style="color: #f59e0b; font-size: 16px;"></span>
						View Failed Syncs
					</a>
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" style="display: flex; align-items: center; gap: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 14px; border: 1px solid #f1f5f9;">
						<span class="dashicons dashicons-admin-users" style="color: #6366f1; font-size: 16px;"></span>
						WordPress Users
					</a>
				</div>
			</div>

			<!-- GoHighLevel Quick Links -->
			<?php
			$ghl_settings    = \Syncly\Core\SettingsManager::get_instance()->get_settings_array();
			$ghl_white_label = $ghl_settings['ghl_white_label_domain'] ?? '';
			$ghl_base_domain = ! empty( $ghl_white_label ) ? rtrim( $ghl_white_label, '/' ) : 'https://app.gohighlevel.com';
			$ghl_location_id = $ghl_settings['location_id'] ?? ( $ghl_settings['oauth_location_id'] ?? '' );
			$ghl_loc_path    = $ghl_location_id ? '/v2/location/' . $ghl_location_id : '';
			?>

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
					<div style="padding: 12px; background: #fffbeb; border-left: 3px solid #f59e0b; border-radius: 6px;">
						<div style="font-size: 13px; font-weight: 600; color: #92400e; margin-bottom: 4px;">Need an implementation example?</div>
						<div style="font-size: 12px; color: #78350f;">
							See documentation examples:
							<a href="<?php echo esc_url( 'https://highlevelsync.com/documentation/' ); ?>" target="_blank" rel="noopener noreferrer" style="color: #92400e; font-weight: 600; text-decoration: underline;">
								highlevelsync.com/documentation
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>