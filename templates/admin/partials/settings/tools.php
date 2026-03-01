<?php
/**
 * Settings - Tools Template
 *
 * Tools tab for data management, import/export, and system utilities
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings = $settings_manager->get_settings_array();
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Data Management Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-database-remove"></span>
				<?php esc_html_e( 'Data Management', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Clear cached data and reset plugin settings to defaults.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Clear Cache', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Deletes all temporarily stored data including contact info, API responses, and rate limit counters. Use when troubleshooting sync issues or after making major changes in GoHighLevel.', 'ghl-crm-integration' ); ?>">?</span>
							</label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="clear-cache-btn">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clear All Cache', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Remove all cached API responses, contact data, and rate limit counters. Use this to force fresh data from GoHighLevel.', 'ghl-crm-integration' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Reset Settings', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Restores ALL settings to factory defaults while keeping your GoHighLevel connection intact. All field mappings, role tags, and notification configs will be lost.', 'ghl-crm-integration' ); ?>">?</span>
							</label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="reset-settings-btn">
								<span class="dashicons dashicons-image-rotate"></span>
								<?php esc_html_e( 'Reset to Defaults', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Reset all plugin settings to default values. Your API connection (OAuth or manual) will be preserved.', 'ghl-crm-integration' ); ?>
								<br>
								<strong style="color: #d63638;">
									<?php esc_html_e( 'Warning: This will clear all custom configurations including field mappings, role tags, and notification settings.', 'ghl-crm-integration' ); ?>
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
				<?php esc_html_e( 'Import / Export', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Export your plugin configuration or import settings from another site.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Export Settings', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Downloads a JSON file with all your current settings (field mappings, role tags, notification configs, etc). API credentials are excluded for security. Perfect for backups or migrating to staging.', 'ghl-crm-integration' ); ?>">?</span>
							</label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="export-settings-btn">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export Configuration', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Download a JSON file containing all plugin settings (excluding API credentials for security).', 'ghl-crm-integration' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="import-settings-file"><?php esc_html_e( 'Import Settings', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Uploads a previously exported JSON file to restore settings. This will overwrite ALL current configurations except API credentials. Great for duplicating setups across multiple sites.', 'ghl-crm-integration' ); ?>">?</span>
							</label>
						</th>
						<td>
							<input type="file" id="import-settings-file" accept=".json" style="display: none;">
							<button type="button" class="ghl-button ghl-button-secondary" id="import-settings-btn">
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e( 'Import Configuration', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Upload a previously exported JSON configuration file. This will overwrite current settings.', 'ghl-crm-integration' ); ?>
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
				<?php esc_html_e( 'Bulk Operations', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Perform bulk sync operations and data management tasks.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Sync All Users', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Queues all WordPress users for synchronization to GoHighLevel. Processing happens in batches of 50 users to prevent timeouts. You can track progress in real-time.', 'ghl-crm-integration' ); ?>">?</span>
							</label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-primary" id="bulk-sync-users-btn">
								<span class="dashicons dashicons-groups"></span>
								<?php esc_html_e( 'Sync All Users to GHL', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Queue all WordPress users for synchronization to GoHighLevel. Processing happens in batches to prevent timeouts.', 'ghl-crm-integration' ); ?>
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
							<label><?php esc_html_e( 'Import from GHL', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Fetches all contacts from your GoHighLevel location and creates WordPress users for each one with a valid email. Already-synced contacts are automatically skipped.', 'ghl-crm-integration' ); ?>">?</span>
							</label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-primary" id="bulk-import-ghl-btn">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Import Contacts from GHL', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Fetch all contacts from GoHighLevel and create WordPress users. Contacts without email and already-synced users are skipped.', 'ghl-crm-integration' ); ?>
								<br>
								<span style="color: #dba617;">
									<?php esc_html_e( 'Note: Uses the deprecated GET /contacts/ endpoint (v2). Will be updated when GHL provides a replacement.', 'ghl-crm-integration' ); ?>
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
			<h2>
				<span class="dashicons dashicons-admin-tools"></span>
				<?php esc_html_e( 'System Diagnostics', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Run diagnostic tests and view system information.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'System Health', 'ghl-crm-integration' ); ?></label>
					</th>
					<td>
						<button type="button" id="health-check-btn" class="ghl-button ghl-button-secondary">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Run Health Check', 'ghl-crm-integration' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Check API connectivity, database tables, and system requirements.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>