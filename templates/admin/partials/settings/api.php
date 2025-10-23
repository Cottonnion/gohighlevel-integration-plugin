<?php
/**
 * Settings - API Configuration Template
 *
 * API configuration settings tab content
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
	<h2><?php esc_html_e( 'API Configuration', 'ghl-crm-integration' ); ?></h2>
	
	<form id="ghl-api-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_crm_api_settings', 'ghl_api_nonce' ); ?>
		
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="api_timeout">
							<?php esc_html_e( 'API Timeout', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input type="number" 
							   id="api_timeout" 
							   name="api_timeout" 
							   value="<?php echo esc_attr( $settings['api_timeout'] ?? 30 ); ?>" 
							   min="5"
							   max="120"
							   class="small-text">
						<span><?php esc_html_e( 'seconds', 'ghl-crm-integration' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Maximum time to wait for API responses.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="api_rate_limit">
							<?php esc_html_e( 'Rate Limit', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input type="number" 
							   id="api_rate_limit" 
							   name="api_rate_limit" 
							   value="<?php echo esc_attr( $settings['api_rate_limit'] ?? 100 ); ?>" 
							   min="10"
							   max="1000"
							   class="small-text">
						<span><?php esc_html_e( 'requests per minute', 'ghl-crm-integration' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Maximum number of API requests per minute.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="enable_retry">
							<?php esc_html_e( 'Auto Retry', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label for="enable_retry">
							<input type="checkbox" 
								   id="enable_retry" 
								   name="enable_retry" 
								   value="1" 
								   <?php checked( $settings['enable_retry'] ?? true ); ?>>
							<?php esc_html_e( 'Automatically retry failed API requests', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Will retry up to 3 times with exponential backoff.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		
		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save API Settings', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>
</div>
