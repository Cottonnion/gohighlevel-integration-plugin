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
							<label><?php esc_html_e( 'Clear Cache', 'ghl-crm-integration' ); ?></label>
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
							<label><?php esc_html_e( 'Reset Settings', 'ghl-crm-integration' ); ?></label>
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
							<label><?php esc_html_e( 'Export Settings', 'ghl-crm-integration' ); ?></label>
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
							<label for="import-settings-file"><?php esc_html_e( 'Import Settings', 'ghl-crm-integration' ); ?></label>
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
							<label><?php esc_html_e( 'Sync All Users', 'ghl-crm-integration' ); ?></label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="bulk-sync-users-btn" disabled>
								<span class="dashicons dashicons-groups"></span>
								<?php esc_html_e( 'Sync All Users to GHL', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Queue all WordPress users for synchronization to GoHighLevel. Coming soon.', 'ghl-crm-integration' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Clear Sync Queue', 'ghl-crm-integration' ); ?></label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="clear-queue-btn" disabled>
								<span class="dashicons dashicons-list-view"></span>
								<?php esc_html_e( 'Clear Pending Queue', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Remove all pending items from the sync queue. Coming soon.', 'ghl-crm-integration' ); ?>
							</p>
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
							<label><?php esc_html_e( 'System Health Check', 'ghl-crm-integration' ); ?></label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="health-check-btn" disabled>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Run Health Check', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Test API connectivity, database tables, and system requirements. Coming soon.', 'ghl-crm-integration' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Debug Log', 'ghl-crm-integration' ); ?></label>
						</th>
						<td>
							<button type="button" class="ghl-button ghl-button-secondary" id="view-debug-log-btn" disabled>
								<span class="dashicons dashicons-media-text"></span>
								<?php esc_html_e( 'View Debug Log', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'View recent error logs and debug information. Coming soon.', 'ghl-crm-integration' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
