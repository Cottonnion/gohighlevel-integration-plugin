<?php
/**
 * Settings - Advanced Template
 *
 * Advanced settings tab content
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Performance & Caching Section -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-performance"></span>
				<?php esc_html_e( 'Performance & Caching', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Configure caching, batch processing, and data retention to optimize plugin performance.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				<table class="form-table" role="presentation">
					<tbody>
				<tr>
					<th scope="row">
						<label for="cache_duration">
							<?php esc_html_e( 'Cache Duration', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'How long to store API responses in memory before fetching fresh data. Longer caching reduces API calls but may show stale data. Set to 0 to disable.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="number" 
								id="cache_duration" 
								name="cache_duration" 
								value="<?php echo esc_attr( $settings['cache_duration'] ?? 3600 ); ?>" 
								min="0"
								max="86400"
								class="small-text">
						<span><?php esc_html_e( 'seconds', 'ghl-crm-integration' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'How long to cache API responses. Set to 0 to disable caching.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="batch_size">
							<?php esc_html_e( 'Batch Size', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'How many items to process at once during bulk sync operations. Higher values = faster sync but more server load. Lower values = slower but safer for shared hosting.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="number" 
								id="batch_size" 
								name="batch_size" 
								value="<?php echo esc_attr( $settings['batch_size'] ?? 50 ); ?>" 
								min="1"
								max="500"
								class="small-text">
						<span><?php esc_html_e( 'items', 'ghl-crm-integration' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Number of items to process in each batch during sync.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="log_retention_days">
							<?php esc_html_e( 'Log Retention Period', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'How long to keep historical sync logs and completed queue items before automatic deletion. Older logs are permanently removed to save database space.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<input type="number" 
								id="log_retention_days" 
								name="log_retention_days" 
								value="<?php echo esc_attr( $settings['log_retention_days'] ?? 30 ); ?>" 
								min="1"
								max="365"
								class="small-text">
						<span><?php esc_html_e( 'days', 'ghl-crm-integration' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Number of days to keep sync logs and completed queue items before automatic cleanup.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="enable_sync_logging">
							<?php esc_html_e( 'Sync & Queue Logging', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Records all sync operations and queue activities to the database for troubleshooting. Disable only if you need to minimize database writes or have confirmed everything works correctly.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<label class="ghl-checkbox ghl-advanced-checkbox-label <?php echo ! empty( $settings['enable_sync_logging'] ) ? 'is-checked' : ''; ?>">
							<input 
								type="checkbox" 
								class="ghl-checkbox-original"
								id="enable_sync_logging" 
								name="enable_sync_logging" 
								value="1"
								<?php checked( ! empty( $settings['enable_sync_logging'] ), true ); ?>
							>
							<span class="ghl-checkbox-input <?php echo ! empty( $settings['enable_sync_logging'] ) ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Enable logging to database tables', 'ghl-crm-integration' ); ?>
							</span>
						</label>
						<p class="description ghl-description-spacing">
							<?php esc_html_e( 'When enabled, sync events and queue operations will be logged to wp_ghl_sync_log and wp_ghl_sync_queue tables. This provides detailed tracking but may impact performance on high-traffic sites. Disable to reduce database writes.', 'ghl-crm-integration' ); ?>
						</p>
						<?php if ( ! empty( $settings['enable_sync_logging'] ) ) : ?>
							<div class="ghl-logging-status-active">
								<p>
									<strong>✓ <?php esc_html_e( 'Active:', 'ghl-crm-integration' ); ?></strong>
									<?php esc_html_e( 'Logging is enabled. You can view logs in the Sync Logs section.', 'ghl-crm-integration' ); ?>
								</p>
							</div>
						<?php else : ?>
							<div class="ghl-logging-status-disabled">
								<p>
									<strong>⚠️ <?php esc_html_e( 'Disabled:', 'ghl-crm-integration' ); ?></strong>
									<?php esc_html_e( 'Logging is currently disabled. No sync events or queue operations will be recorded to the database.', 'ghl-crm-integration' ); ?>
								</p>
							</div>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="enable_telemetry_reporting">
							<?php esc_html_e( 'Telemetry & Reporting (Opt-in)', 'ghl-crm-integration' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'When enabled, the plugin stores anonymized error and usage events locally and sends batched reports to the developer endpoint to improve stability. No personal data or content is sent.', 'ghl-crm-integration' ); ?>">?</span>
						</label>
					</th>
					<td>
						<label class="ghl-checkbox ghl-advanced-checkbox-label <?php echo ! empty( $settings['enable_telemetry_reporting'] ) ? 'is-checked' : ''; ?>">
							<input 
								type="checkbox" 
								class="ghl-checkbox-original"
								id="enable_telemetry_reporting" 
								name="enable_telemetry_reporting" 
								value="1"
								<?php checked( ! empty( $settings['enable_telemetry_reporting'] ), true ); ?>
							>
							<span class="ghl-checkbox-input <?php echo ! empty( $settings['enable_telemetry_reporting'] ) ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Share anonymized diagnostics to help improve stability', 'ghl-crm-integration' ); ?>
							</span>
						</label>
						<p class="description ghl-description-spacing">
							<?php esc_html_e( 'If enabled, the plugin will capture error and performance events, store them locally, and periodically send summaries. Disable to keep all diagnostics on this site only.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
					</tbody>
				</table>
			</form>
		</div>

		<hr>

		<!-- Save Button -->
		<button type="button" id="save-advanced-settings" class="ghl-button ghl-button-primary ghl-save-settings-btn">
			<span class="ghl-button-text"><?php esc_html_e( 'Save Advanced Settings', 'ghl-crm-integration' ); ?></span>
		</button>
	</div>
</div>