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
$settings = $settings_manager->get_settings_array();
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
						<label><?php esc_html_e( 'Data Management', 'ghl-crm-integration' ); ?></label>
					</th>
					<td>
						<div style="margin-bottom: 15px;">
							<button type="button" class="ghl-button ghl-button-secondary" id="clear-cache-btn">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clear Cache', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Clear all cached API responses and contact data.', 'ghl-crm-integration' ); ?>
							</p>
						</div>
						
						<div>
							<button type="button" class="ghl-button ghl-button-secondary" id="reset-settings-btn">
								<span class="dashicons dashicons-image-rotate"></span>
								<?php esc_html_e( 'Reset to Defaults', 'ghl-crm-integration' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Reset all plugin settings to default values (OAuth connection will be preserved).', 'ghl-crm-integration' ); ?>
							</p>
						</div>
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
