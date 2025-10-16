<?php
/**
 * Template: Settings Page
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ghl-crm-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Getting Started:', 'ghl-crm-integration' ); ?></strong>
			<?php esc_html_e( 'Configure your GoHighLevel API connection below to start syncing data.', 'ghl-crm-integration' ); ?>
		</p>
	</div>

	<div class="ghl-crm-container">
		<div class="ghl-crm-main-content">
			<form method="post" action="options.php" class="ghl-crm-form">
				<?php
				settings_fields( 'ghl_crm_settings' );
				do_settings_sections( 'ghl-crm-integration' );
				?>
				
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="ghl_crm_api_token">
									<?php esc_html_e( 'API Token', 'ghl-crm-integration' ); ?>
									<span class="required">*</span>
								</label>
							</th>
							<td>
								<input 
									type="password" 
									id="ghl_crm_api_token" 
									name="ghl_crm_api_token" 
									value="<?php echo esc_attr( get_option( 'ghl_crm_api_token', '' ) ); ?>" 
									class="regular-text"
									placeholder="<?php esc_attr_e( 'Enter your Private Integration Token', 'ghl-crm-integration' ); ?>"
								/>
								<p class="description">
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
								<label for="ghl_crm_location_id">
									<?php esc_html_e( 'Location ID', 'ghl-crm-integration' ); ?>
									<span class="required">*</span>
								</label>
							</th>
							<td>
								<input 
									type="text" 
									id="ghl_crm_location_id" 
									name="ghl_crm_location_id" 
									value="<?php echo esc_attr( get_option( 'ghl_crm_location_id', '' ) ); ?>" 
									class="regular-text"
									placeholder="<?php esc_attr_e( 'Enter your Location/Sub-Account ID', 'ghl-crm-integration' ); ?>"
								/>
								<p class="description">
									<?php esc_html_e( 'Your GoHighLevel Location ID (also called Sub-Account ID)', 'ghl-crm-integration' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ghl_crm_api_version">
									<?php esc_html_e( 'API Version', 'ghl-crm-integration' ); ?>
								</label>
							</th>
							<td>
								<input 
									type="text" 
									id="ghl_crm_api_version" 
									name="ghl_crm_api_version" 
									value="<?php echo esc_attr( get_option( 'ghl_crm_api_version', '2021-07-28' ) ); ?>" 
									class="regular-text"
									readonly
								/>
								<p class="description">
									<?php esc_html_e( 'Default API version (recommended to keep as is)', 'ghl-crm-integration' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'ghl-crm-integration' ) ); ?>
			</form>

			<hr />

			<div class="ghl-crm-test-connection">
				<h2><?php esc_html_e( 'Test Connection', 'ghl-crm-integration' ); ?></h2>
				<p><?php esc_html_e( 'Test your API connection to ensure everything is configured correctly.', 'ghl-crm-integration' ); ?></p>
				<button type="button" id="ghl-test-connection" class="button button-secondary">
					<?php esc_html_e( 'Test API Connection', 'ghl-crm-integration' ); ?>
				</button>
				<div id="ghl-test-result" style="margin-top: 15px;"></div>
			</div>
		</div>

		<div class="ghl-crm-sidebar">
			<div class="ghl-crm-card">
				<h2><?php esc_html_e( 'Quick Start Guide', 'ghl-crm-integration' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Generate a Private Integration Token in GoHighLevel', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Copy your Location ID from your GoHighLevel account', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Enter both values above and save', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Test your connection', 'ghl-crm-integration' ); ?></li>
					<li><?php esc_html_e( 'Configure field mapping and enable sync', 'ghl-crm-integration' ); ?></li>
				</ol>
			</div>

			<div class="ghl-crm-card">
				<h2><?php esc_html_e( 'Need Help?', 'ghl-crm-integration' ); ?></h2>
				<ul>
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
