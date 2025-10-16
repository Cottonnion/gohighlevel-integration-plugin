<?php
/**
 * Template: Settings Page
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get settings manager instance
$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
?>
<div class="wrap ghl-crm-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<!-- Dynamic notification area -->
	<div id="ghl-settings-notice" style="display: none;"></div>
	
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Getting Started:', 'ghl-crm-integration' ); ?></strong>
			<?php esc_html_e( 'Configure your GoHighLevel API connection below to start syncing data.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<div class="ghl-container ghl-crm-container">
		<div class="ghl-main-content ghl-crm-main-content">
			<form id="ghl-crm-settings-form" class="ghl-crm-form">
				<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
				
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="ghl_crm_api_token" class="ghl-label ghl-label--required">
									<?php esc_html_e( 'API Token', 'ghl-crm-integration' ); ?>
								</label>
							</th>
							<td>
								<input 
									type="password" 
									id="ghl_crm_api_token" 
									name="api_token" 
									value="<?php echo esc_attr( $settings['api_token'] ); ?>" 
									class="regular-text ghl-input"
									placeholder="<?php esc_attr_e( 'Enter your Private Integration Token', 'ghl-crm-integration' ); ?>"
									required
								/>
								<p class="description ghl-form-description">
									<?php 
									printf(
										/* translators: %s: Link to GoHighLevel settings */
										esc_html__( 'Get your Private Integration Token from %s', 'ghl-crm-integration' ),
										'<a href="https://app.gohighlevel.com/settings/integrations" target="_blank">' . esc_html__( 'GoHighLevel Settings → Private Integrations', 'ghl-crm-integration' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ghl_crm_location_id" class="ghl-label ghl-label--required">
									<?php esc_html_e( 'Location ID', 'ghl-crm-integration' ); ?>
								</label>
							</th>
							<td>
								<input 
									type="text" 
									id="ghl_crm_location_id" 
									name="location_id" 
									value="<?php echo esc_attr( $settings['location_id'] ); ?>" 
									class="regular-text ghl-input"
									placeholder="<?php esc_attr_e( 'Enter your Location/Sub-Account ID', 'ghl-crm-integration' ); ?>"
									required
								/>
								<p class="description ghl-form-description">
									<?php esc_html_e( 'Your GoHighLevel Location ID (also called Sub-Account ID)', 'ghl-crm-integration' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ghl_crm_api_version" class="ghl-label">
									<?php esc_html_e( 'API Version', 'ghl-crm-integration' ); ?>
								</label>
							</th>
							<td>
								<input 
									type="text" 
									id="ghl_crm_api_version" 
									name="api_version" 
									value="<?php echo esc_attr( $settings['api_version'] ); ?>" 
									class="regular-text ghl-input"
									readonly
								/>
								<p class="description ghl-form-description">
									<?php esc_html_e( 'Default API version (recommended to keep as is)', 'ghl-crm-integration' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" name="submit" id="ghl-save-settings" class="button button-primary ghl-button ghl-button-primary">
						<span class="button-text"><?php esc_html_e( 'Save Settings', 'ghl-crm-integration' ); ?></span>
						<span class="spinner" style="display: none; float: none; margin: 0 0 0 8px;"></span>
					</button>
				</p>
			</form>

			<div class="ghl-divider"></div>

			<div class="ghl-crm-test-connection">
				<h2 class="ghl-heading-tertiary"><?php esc_html_e( 'Test Connection', 'ghl-crm-integration' ); ?></h2>
				<p class="ghl-text-secondary"><?php esc_html_e( 'Test your API connection to ensure everything is configured correctly.', 'ghl-crm-integration' ); ?></p>
				<button type="button" id="ghl-test-connection" class="button button-secondary ghl-button ghl-button-secondary">
					<?php esc_html_e( 'Test API Connection', 'ghl-crm-integration' ); ?>
				</button>
				<div id="ghl-test-result" class="ghl-mt-base"></div>
			</div>
		</div>

		<div class="ghl-sidebar ghl-crm-sidebar">
			<div class="ghl-card ghl-crm-card">
				<h2 class="ghl-heading-secondary"><?php esc_html_e( 'Quick Start Guide', 'ghl-crm-integration' ); ?></h2>
				<ol class="ghl-list">
					<li><?php esc_html_e( 'Generate a Private Integration Token in GoHighLevel', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Copy your Location ID from your GoHighLevel account', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Enter both values above and save', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Test your connection', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Configure field mapping and enable sync', 'ghl-crm-integration' ); ?></li>
				</ol>
			</div>

			<div class="ghl-card ghl-crm-card">
				<h2 class="ghl-heading-secondary"><?php esc_html_e( 'Need Help?', 'ghl-crm-integration' ); ?></h2>
				<ul class="ghl-list">
					<li>
						<a href="https://marketplace.gohighlevel.com/docs/" target="_blank">
							<?php esc_html_e( 'GoHighLevel API Documentation', 'ghl-crm-integration' ); ?>
						</a>
					</li>
					<li>
						<a href="https://marketplace.gohighlevel.com/docs/Authorization/PrivateIntegrationsToken" target="_blank">
							<?php esc_html_e( 'How to Create Private Integration Token', 'ghl-crm-integration' ); ?>
						</a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>
