<?php
/**
 * REST API Settings Partial
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ghl-settings-rest-api">
	<h2><?php esc_html_e( 'REST API Settings', 'ghl-crm-integration' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure REST API endpoints to allow external services to interact with your plugin.', 'ghl-crm-integration' ); ?>
	</p>

	<form id="ghl-rest-api-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_rest_api_settings', 'ghl_rest_api_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="enable_rest_api">
							<?php esc_html_e( 'Enable REST API', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="enable_rest_api" name="enable_rest_api" value="1" />
							<?php esc_html_e( 'Enable REST API endpoints for external access', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, external services can use REST API to create/update contacts and trigger syncs.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="rest_api_key">
							<?php esc_html_e( 'API Key', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input 
							type="text" 
							id="rest_api_key" 
							name="rest_api_key" 
							value="" 
							class="regular-text code" 
							readonly
						/>
						<button type="button" class="button button-secondary" id="generate-api-key">
							<?php esc_html_e( 'Generate New Key', 'ghl-crm-integration' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Use this key in Authorization header: Bearer YOUR_API_KEY', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="rest_api_ip_whitelist">
							<?php esc_html_e( 'IP Whitelist', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<textarea 
							id="rest_api_ip_whitelist" 
							name="rest_api_ip_whitelist" 
							rows="5" 
							class="large-text code"
							placeholder="192.168.1.1&#10;10.0.0.0/8"
						></textarea>
						<p class="description">
							<?php esc_html_e( 'One IP address or CIDR per line. Leave empty to allow all IPs.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Rate Limiting', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<label>
							<input type="checkbox" id="rest_api_rate_limit" name="rest_api_rate_limit" value="1" checked />
							<?php esc_html_e( 'Enable rate limiting', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Limit API requests to 60 per minute per IP address.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Allowed Endpoints', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="rest_api_endpoints[]" value="contacts" checked />
								<code>/ghl-crm/v1/contacts</code> - Create/Update Contacts
							</label><br>
							<label>
								<input type="checkbox" name="rest_api_endpoints[]" value="sync" checked />
								<code>/ghl-crm/v1/sync</code> - Trigger Manual Sync
							</label><br>
							<label>
								<input type="checkbox" name="rest_api_endpoints[]" value="status" checked />
								<code>/ghl-crm/v1/status</code> - Get Sync Status
							</label><br>
							<label>
								<input type="checkbox" name="rest_api_endpoints[]" value="webhooks" />
								<code>/ghl-crm/v1/webhooks</code> - Receive Webhook Events
							</label>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="ghl-rest-api-docs" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #7e3bd0;">
			<h3><?php esc_html_e( 'API Documentation', 'ghl-crm-integration' ); ?></h3>
			<p><strong><?php esc_html_e( 'Base URL:', 'ghl-crm-integration' ); ?></strong> <code><?php echo esc_url( rest_url( 'ghl-crm/v1' ) ); ?></code></p>
			<p><strong><?php esc_html_e( 'Authentication:', 'ghl-crm-integration' ); ?></strong> <code>Authorization: Bearer YOUR_API_KEY</code></p>
			<p>
				<a href="#" target="_blank" class="button button-small">
					<?php esc_html_e( 'View Full Documentation', 'ghl-crm-integration' ); ?>
				</a>
			</p>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save REST API Settings', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>
</div>
