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

<div class="ghl-settings-section">
	<h2><?php esc_html_e( 'Advanced Settings', 'ghl-crm-integration' ); ?></h2>
	
	<form id="ghl-advanced-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_crm_advanced_settings', 'ghl_advanced_nonce' ); ?>
		
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
						<?php esc_html_e( 'Data Management', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<button type="button" class="button button-secondary" id="clear-cache-btn">
							<?php esc_html_e( 'Clear Cache', 'ghl-crm-integration' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Clear all cached API responses.', 'ghl-crm-integration' ); ?>
						</p>
						
						<br><br>
						
						<button type="button" class="button button-secondary" id="reset-settings-btn">
							<?php esc_html_e( 'Reset to Defaults', 'ghl-crm-integration' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Reset all plugin settings to default values (OAuth connection will be preserved).', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Advanced Settings', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>
</div>
