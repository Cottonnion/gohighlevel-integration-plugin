<?php
/**
 * Settings - Tools Template
 *
 * Tools tab for data management, import/export, and system utilities
 *
 * @package    Syncly
 * @subpackage Syncly/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \Syncly\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$tag_manager      = \Syncly\Sync\TagManager::get_instance();
$ghl_tags         = $tag_manager->get_tags_for_localization();
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'syncly_settings_nonce', 'syncly_nonce' ); ?>
	
	<!-- Data Management Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-database-remove"></span>
				<?php esc_html_e( 'Data Management', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Clear cached data and reset plugin settings to defaults.', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Clear Cache', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Deletes all temporarily stored data including contact info, API responses, and rate limit counters. Use when troubleshooting sync issues or after making major changes in GoHighLevel.', 'syncly' ); ?>">?</span>
							</label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="clear-cache-btn">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clear All Cache', 'syncly' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Remove all cached API responses, contact data, and rate limit counters. Use this to force fresh data from GoHighLevel.', 'syncly' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Reset Settings', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Restores ALL settings to factory defaults while keeping your GoHighLevel connection intact. All field mappings, role tags, and notification configs will be lost.', 'syncly' ); ?>">?</span>
							</label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="reset-settings-btn">
								<span class="dashicons dashicons-image-rotate"></span>
								<?php esc_html_e( 'Reset to Defaults', 'syncly' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Reset all plugin settings to default values. Your API connection (OAuth or manual) will be preserved.', 'syncly' ); ?>
								<br>
								<strong style="color: #d63638;">
									<?php esc_html_e( 'Warning: This will clear all custom configurations including field mappings, role tags, and notification settings.', 'syncly' ); ?>
								</strong>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	
	<!-- Import/Export Section -->
	<div class="ghl-settings-section ghl-settings-card" style="margin-top: 20px;">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'Import / Export', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Export your plugin configuration or import settings from another site.', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Export Settings', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Downloads a JSON file with all your current settings (field mappings, role tags, notification configs, etc). API credentials are excluded for security. Perfect for backups or migrating to staging.', 'syncly' ); ?>">?</span>
							</label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="export-settings-btn">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export Configuration', 'syncly' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Download a JSON file containing all plugin settings (excluding API credentials for security).', 'syncly' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="import-settings-file"><?php esc_html_e( 'Import Settings', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Uploads a previously exported JSON file to restore settings. This will overwrite ALL current configurations except API credentials. Great for duplicating setups across multiple sites.', 'syncly' ); ?>">?</span>
							</label>
						</th>
						<td>
							<input type="file" id="import-settings-file" accept=".json" style="display: none;">
							<button type="button" class="ghl-button ghl-button-secondary" id="import-settings-btn">
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e( 'Import Configuration', 'syncly' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Upload a previously exported JSON configuration file. This will overwrite current settings.', 'syncly' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	
	<!-- Bulk Operations Section -->
	<div class="ghl-settings-section ghl-settings-card" style="margin-top: 20px;">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Bulk Operations', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Perform bulk sync operations and data management tasks.', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Sync Users to GHL', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Queues WordPress users for synchronization to GoHighLevel based on selected filters (role, sync status, user meta). Processing happens in batches of 50.', 'syncly' ); ?>">?</span>
							</label>
						</th>
						<td>
							<div class="ghl-filter-group" style="margin-bottom: 15px; padding: 12px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 4px;">
								<p style="margin-top: 0; font-weight: 600;"><?php esc_html_e( 'Filter Users to Sync (Optional):', 'syncly' ); ?></p>
								
								<div style="margin-bottom: 10px;">
									<label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php esc_html_e( 'User Role:', 'syncly' ); ?></label>
									<select id="bulk-sync-role-filter" class="regular-text">
										<option value=""><?php esc_html_e( 'All Roles', 'syncly' ); ?></option>
										<?php wp_dropdown_roles(); ?>
									</select>
								</div>
								
								<div style="margin-bottom: 10px;">
									<label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php esc_html_e( 'Sync Status:', 'syncly' ); ?></label>
									<select id="bulk-sync-status-filter" class="regular-text">
										<option value="all"><?php esc_html_e( 'All Users (Synced & Unsynced)', 'syncly' ); ?></option>
										<option value="unsynced_only"><?php esc_html_e( 'Unsynced Users Only', 'syncly' ); ?></option>
										<option value="synced_only"><?php esc_html_e( 'Already Synced Users Only', 'syncly' ); ?></option>
									</select>
								</div>

								<div>
									<label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php esc_html_e( 'User Meta Filter:', 'syncly' ); ?></label>
									<div style="display: flex; gap: 10px;">
										<input type="text" id="bulk-sync-meta-key" placeholder="<?php esc_attr_e( 'Meta Key (e.g. vip_member)', 'syncly' ); ?>" class="regular-text" style="flex: 1;">
										<input type="text" id="bulk-sync-meta-value" placeholder="<?php esc_attr_e( 'Meta Value (optional)', 'syncly' ); ?>" class="regular-text" style="flex: 1;">
									</div>
								</div>

								<div id="bulk-sync-live-count" style="margin-top: 10px; padding: 6px 10px; background: #e7f5fb; border-left: 4px solid #2271b1; font-weight: 500; color: #1d2327; font-size: 0.9em; display: none;"></div>
							</div>

							<button type="button" class="ghl-button ghl-button-primary" id="bulk-sync-users-btn">
								<span class="dashicons dashicons-groups"></span>
								<?php esc_html_e( 'Sync Filtered Users to GHL', 'syncly' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Queue matching WordPress users for synchronization to GoHighLevel. Leave filters empty to sync all users.', 'syncly' ); ?>
							</p>
							<div id="bulk-sync-progress" style="display: none; margin-top: 15px;">
								<div class="ghl-progress-bar-container">
									<div class="ghl-progress-bar" id="bulk-sync-progress-bar"></div>
								</div>
								<p class="ghl-progress-text" id="bulk-sync-progress-text"></p>
							</div>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Import from GHL', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Fetches contacts from your GoHighLevel location and creates or updates WordPress users. Apply filters to restrict which contacts to import.', 'syncly' ); ?>">?</span>
							</label>
						</th>
						<td>
							<div class="ghl-filter-group" style="margin-bottom: 15px; padding: 12px; background: #f9f9f9; border: 1px solid #e5e5e5; border-radius: 4px;">
								<p style="margin-top: 0; font-weight: 600;"><?php esc_html_e( 'Filter Contacts to Import (Optional):', 'syncly' ); ?></p>
								
								<div style="margin-bottom: 10px;">
									<label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php esc_html_e( 'Search Query:', 'syncly' ); ?></label>
									<input type="text" id="bulk-import-query-filter" placeholder="<?php esc_attr_e( 'Name, Email, or Phone query', 'syncly' ); ?>" class="regular-text">
									<div id="bulk-import-live-preview" style="margin-top: 6px; padding: 6px 10px; background: #e7f5fb; border-left: 4px solid #2271b1; font-weight: 500; color: #1d2327; font-size: 0.9em; display: none;"></div>
								</div>

								<div style="margin-bottom: 10px;">
									<label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php esc_html_e( 'GHL Tag Filter:', 'syncly' ); ?></label>
									<select id="bulk-import-tag-filter" class="regular-text syncly-select2" style="width: 100%;">
										<option value=""><?php esc_html_e( 'All Tags (No Tag Filter)', 'syncly' ); ?></option>
										<?php foreach ( $ghl_tags as $gtag ) : ?>
											<option value="<?php echo esc_attr( $gtag['name'] ); ?>">
												<?php echo esc_html( $gtag['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div>
									<label style="display: block; margin-bottom: 4px; font-weight: 500;"><?php esc_html_e( 'Import Mode:', 'syncly' ); ?></label>
									<select id="bulk-import-mode-filter" class="regular-text">
										<option value="all"><?php esc_html_e( 'All Contacts (Create New & Update Existing)', 'syncly' ); ?></option>
										<option value="new_only"><?php esc_html_e( 'New Contacts Only (Skip Existing Users)', 'syncly' ); ?></option>
										<option value="existing_only"><?php esc_html_e( 'Existing Users Only (Update Only)', 'syncly' ); ?></option>
									</select>
								</div>
							</div>

							<button type="button" class="ghl-button ghl-button-primary" id="bulk-import-ghl-btn">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Import Filtered Contacts from GHL', 'syncly' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Fetch contacts from GoHighLevel and create/update WordPress users. Contacts without email are skipped.', 'syncly' ); ?>
								<br>
								<span style="color: #dba617;">
									<?php esc_html_e( 'Note: Uses the deprecated GET /contacts/ endpoint (v2). Will be updated when GHL provides a replacement.', 'syncly' ); ?>
								</span>
							</p>
							<div id="bulk-import-progress" style="display: none; margin-top: 15px;">
								<div class="ghl-progress-bar-container">
									<div class="ghl-progress-bar" id="bulk-import-progress-bar"></div>
								</div>
								<p class="ghl-progress-text" id="bulk-import-progress-text"></p>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- System Diagnostics Section -->
	<div class="ghl-settings-section ghl-settings-card" style="margin-top: 20px;">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-admin-tools"></span>
				<?php esc_html_e( 'System Diagnostics', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Run diagnostic tests and view system information.', 'syncly' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'System Health', 'syncly' ); ?></label>
					</th>
					<td>
						<button type="button" id="health-check-btn" class="ghl-button ghl-button-secondary">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Run Health Check', 'syncly' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Check API connectivity, database tables, and system requirements.', 'syncly' ); ?>
						</p>
					</td>
				</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>