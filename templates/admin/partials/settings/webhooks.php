<?php
/**
 * Webhooks Settings Partial
 *
 * @package GHL_CRM_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ghl-settings-webhooks">
	<h2><?php esc_html_e( 'Webhook Settings', 'ghl-crm-integration' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure webhooks to receive real-time updates from GoHighLevel.', 'ghl-crm-integration' ); ?>
	</p>

	<form id="ghl-webhooks-settings-form" method="post">
		<?php wp_nonce_field( 'ghl_webhooks_settings', 'ghl_webhooks_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="enable_webhooks">
							<?php esc_html_e( 'Enable Webhooks', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="enable_webhooks" name="enable_webhooks" value="1" />
							<?php esc_html_e( 'Enable webhook endpoint to receive events from GoHighLevel', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, GoHighLevel can send real-time updates to your site.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="webhook_url">
							<?php esc_html_e( 'Webhook URL', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input 
							type="text" 
							id="webhook_url" 
							value="<?php echo esc_url( rest_url( 'ghl-crm/v1/webhooks' ) ); ?>" 
							class="large-text code" 
							readonly
						/>
						<button type="button" class="button button-secondary" id="copy-webhook-url">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy', 'ghl-crm-integration' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Use this URL when setting up webhooks in your GoHighLevel account.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="webhook_secret">
							<?php esc_html_e( 'Webhook Secret', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<input 
							type="text" 
							id="webhook_secret" 
							name="webhook_secret" 
							value="" 
							class="regular-text code" 
							readonly
						/>
						<button type="button" class="button button-secondary" id="generate-webhook-secret">
							<?php esc_html_e( 'Generate New Secret', 'ghl-crm-integration' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Used to verify webhook authenticity. Add this as the signing secret in GoHighLevel.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Webhook Events', 'ghl-crm-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Select which events to process', 'ghl-crm-integration' ); ?></legend>
							<label>
								<input type="checkbox" name="webhook_events[]" value="contact.created" checked />
								<?php esc_html_e( 'Contact Created', 'ghl-crm-integration' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="webhook_events[]" value="contact.updated" checked />
								<?php esc_html_e( 'Contact Updated', 'ghl-crm-integration' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="webhook_events[]" value="contact.deleted" />
								<?php esc_html_e( 'Contact Deleted', 'ghl-crm-integration' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="webhook_events[]" value="opportunity.created" />
								<?php esc_html_e( 'Opportunity Created', 'ghl-crm-integration' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="webhook_events[]" value="opportunity.updated" />
								<?php esc_html_e( 'Opportunity Updated', 'ghl-crm-integration' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="webhook_events[]" value="task.created" />
								<?php esc_html_e( 'Task Created', 'ghl-crm-integration' ); ?>
							</label>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Select which webhook events should be processed by the plugin.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="webhook_log_events">
							<?php esc_html_e( 'Log Webhook Events', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="webhook_log_events" name="webhook_log_events" value="1" checked />
							<?php esc_html_e( 'Log all webhook events for debugging', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Logs will be stored for 30 days. Disable to save database space.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="webhook_retry_failed">
							<?php esc_html_e( 'Retry Failed Webhooks', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="webhook_retry_failed" name="webhook_retry_failed" value="1" />
							<?php esc_html_e( 'Automatically retry failed webhook processing', 'ghl-crm-integration' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Will retry up to 3 times with exponential backoff.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="ghl-webhook-stats" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #7e3bd0;">
			<h3><?php esc_html_e( 'Webhook Statistics (Last 30 Days)', 'ghl-crm-integration' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Event Type', 'ghl-crm-integration' ); ?></th>
						<th><?php esc_html_e( 'Received', 'ghl-crm-integration' ); ?></th>
						<th><?php esc_html_e( 'Successful', 'ghl-crm-integration' ); ?></th>
						<th><?php esc_html_e( 'Failed', 'ghl-crm-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>contact.created</td>
						<td>0</td>
						<td>0</td>
						<td>0</td>
					</tr>
					<tr>
						<td>contact.updated</td>
						<td>0</td>
						<td>0</td>
						<td>0</td>
					</tr>
				</tbody>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Webhook Settings', 'ghl-crm-integration' ); ?>
			</button>
		</p>
	</form>
</div>
