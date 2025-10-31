<?php
/**
 * Settings - REST API Template
 *
 * REST API settings tab content
 * Controls external API access and authentication
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \GHL_CRM\Core\SettingsManager::get_instance();
$settings = $settings_manager->get_settings_array();

// REST API settings with defaults
$rest_api_enabled = $settings['rest_api_enabled'] ?? false;
$rest_api_key = $settings['rest_api_key'] ?? '';
$rest_api_ip_whitelist = $settings['rest_api_ip_whitelist'] ?? '';
$rest_api_rate_limit = $settings['rest_api_rate_limit'] ?? true;
$rest_api_endpoints = $settings['rest_api_endpoints'] ?? [ 'contacts', 'sync', 'status' ];
$rest_api_requests_per_minute = $settings['rest_api_requests_per_minute'] ?? 60;

// Generate API key if not exists
if ( empty( $rest_api_key ) ) {
	$rest_api_key = wp_generate_password( 32, false );
}
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>
	
	<!-- Master Toggle -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-rest-api"></span>
				<?php esc_html_e( 'REST API Access', 'ghl-crm-integration' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Enable external services to interact with your plugin through REST API endpoints.', 'ghl-crm-integration' ); ?>
			</p>
		</div>
		
		<hr>
		
		<div class="ghl-form-builder">
			<div class="ghl-form">
				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<label class="ghl-checkbox <?php echo $rest_api_enabled ? 'is-checked' : ''; ?>">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="rest_api_enabled" 
								   name="rest_api_enabled" 
								   value="1" 
								   <?php checked( $rest_api_enabled ); ?>>
							<span class="ghl-checkbox-input <?php echo $rest_api_enabled ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Enable REST API endpoints', 'ghl-crm-integration' ); ?>
							</span>
						</label>
					</div>
					<p class="description" style="margin-left: 54px;">
						<?php esc_html_e( 'When enabled, external services can use REST API to create/update contacts and trigger syncs.', 'ghl-crm-integration' ); ?>
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Authentication Settings -->
	<div class="ghl-settings-section ghl-settings-card">
		<h2><?php esc_html_e( 'Authentication', 'ghl-crm-integration' ); ?></h2>
		<p><?php esc_html_e( 'Manage API keys for secure access to REST endpoints.', 'ghl-crm-integration' ); ?></p>
		<hr>
		
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="rest_api_key">
							<?php esc_html_e( 'API Key', 'ghl-crm-integration' ); ?>
						</label>
					</th>
					<td>
						<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
							<input 
								type="text" 
								id="rest_api_key" 
								name="rest_api_key" 
								value="<?php echo esc_attr( $rest_api_key ); ?>" 
								class="regular-text code" 
								readonly
								style="font-family: monospace; background: #f0f0f1;"
							/>
							<button type="button" class="button button-secondary" id="ghl-generate-api-key">
								<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Regenerate', 'ghl-crm-integration' ); ?>
							</button>
							<button type="button" class="button button-secondary" id="ghl-copy-api-key" title="<?php esc_attr_e( 'Copy to clipboard', 'ghl-crm-integration' ); ?>">
								<span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
							</button>
						</div>
						<p class="description">
							<?php esc_html_e( 'Use this key in Authorization header: ', 'ghl-crm-integration' ); ?>
							<code>Authorization: Bearer YOUR_API_KEY</code>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Security Settings -->
	<div class="ghl-settings-section ghl-settings-card">
		<h2><?php esc_html_e( 'Security Settings', 'ghl-crm-integration' ); ?></h2>
		<p><?php esc_html_e( 'Configure IP restrictions and rate limiting for enhanced security.', 'ghl-crm-integration' ); ?></p>
		<hr>
		
		<table class="form-table" role="presentation">
			<tbody>

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
							style="font-family: monospace;"
						><?php echo esc_textarea( $rest_api_ip_whitelist ); ?></textarea>
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
						<label class="ghl-checkbox <?php echo $rest_api_rate_limit ? 'is-checked' : ''; ?>" style="display: inline-flex; align-items: center;">
							<input type="checkbox" 
								   class="ghl-checkbox-original"
								   id="rest_api_rate_limit" 
								   name="rest_api_rate_limit" 
								   value="1" 
								   <?php checked( $rest_api_rate_limit ); ?>>
							<span class="ghl-checkbox-input <?php echo $rest_api_rate_limit ? 'is-checked' : ''; ?>">
								<span class="ghl-checkbox-inner"></span>
							</span>
							<span class="ghl-checkbox-label">
								<?php esc_html_e( 'Enable rate limiting', 'ghl-crm-integration' ); ?>
							</span>
						</label>
						<div style="margin-top: 10px;">
							<label for="rest_api_requests_per_minute" style="display: inline-block; margin-right: 10px;">
								<?php esc_html_e( 'Max requests per minute:', 'ghl-crm-integration' ); ?>
							</label>
							<input type="number" 
								   id="rest_api_requests_per_minute" 
								   name="rest_api_requests_per_minute" 
								   value="<?php echo esc_attr( $rest_api_requests_per_minute ); ?>" 
								   min="10"
								   max="1000"
								   style="width: 80px;">
						</div>
						<p class="description">
							<?php esc_html_e( 'Limit API requests per IP address to prevent abuse.', 'ghl-crm-integration' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Endpoint Configuration -->
	<div class="ghl-settings-section ghl-settings-card">
		<h2><?php esc_html_e( 'Allowed Endpoints', 'ghl-crm-integration' ); ?></h2>
		<p><?php esc_html_e( 'Choose which REST API endpoints are available for external access.', 'ghl-crm-integration' ); ?></p>
		<hr>
		
		<div class="ghl-form-builder">
			<div class="ghl-form">
				<div class="ghl-form-item">
					<div class="ghl-form-item-content">
						<fieldset>
							<label class="ghl-checkbox <?php echo in_array( 'contacts', $rest_api_endpoints, true ) ? 'is-checked' : ''; ?>" style="display: block; margin-bottom: 15px;">
								<input type="checkbox" 
									   class="ghl-checkbox-original"
									   name="rest_api_endpoints[]" 
									   value="contacts" 
									   <?php checked( in_array( 'contacts', $rest_api_endpoints, true ) ); ?>>
								<span class="ghl-checkbox-input <?php echo in_array( 'contacts', $rest_api_endpoints, true ) ? 'is-checked' : ''; ?>">
									<span class="ghl-checkbox-inner"></span>
								</span>
								<span class="ghl-checkbox-label">
									<code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px;">/ghl-crm/v1/contacts</code> - Create/Update Contacts
								</span>
							</label>

							<label class="ghl-checkbox <?php echo in_array( 'sync', $rest_api_endpoints, true ) ? 'is-checked' : ''; ?>" style="display: block; margin-bottom: 15px;">
								<input type="checkbox" 
									   class="ghl-checkbox-original"
									   name="rest_api_endpoints[]" 
									   value="sync" 
									   <?php checked( in_array( 'sync', $rest_api_endpoints, true ) ); ?>>
								<span class="ghl-checkbox-input <?php echo in_array( 'sync', $rest_api_endpoints, true ) ? 'is-checked' : ''; ?>">
									<span class="ghl-checkbox-inner"></span>
								</span>
								<span class="ghl-checkbox-label">
									<code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px;">/ghl-crm/v1/sync</code> - Trigger Manual Sync
								</span>
							</label>

							<label class="ghl-checkbox <?php echo in_array( 'status', $rest_api_endpoints, true ) ? 'is-checked' : ''; ?>" style="display: block; margin-bottom: 15px;">
								<input type="checkbox" 
									   class="ghl-checkbox-original"
									   name="rest_api_endpoints[]" 
									   value="status" 
									   <?php checked( in_array( 'status', $rest_api_endpoints, true ) ); ?>>
								<span class="ghl-checkbox-input <?php echo in_array( 'status', $rest_api_endpoints, true ) ? 'is-checked' : ''; ?>">
									<span class="ghl-checkbox-inner"></span>
								</span>
								<span class="ghl-checkbox-label">
									<code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px;">/ghl-crm/v1/status</code> - Get Sync Status
								</span>
							</label>

							<label class="ghl-checkbox <?php echo in_array( 'webhooks', $rest_api_endpoints, true ) ? 'is-checked' : ''; ?>" style="display: block; margin-bottom: 15px;">
								<input type="checkbox" 
									   class="ghl-checkbox-original"
									   name="rest_api_endpoints[]" 
									   value="webhooks" 
									   <?php checked( in_array( 'webhooks', $rest_api_endpoints, true ) ); ?>>
								<span class="ghl-checkbox-input <?php echo in_array( 'webhooks', $rest_api_endpoints, true ) ? 'is-checked' : ''; ?>">
									<span class="ghl-checkbox-inner"></span>
								</span>
								<span class="ghl-checkbox-label">
									<code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px;">/ghl-crm/v1/webhooks</code> - Receive Webhook Events
								</span>
							</label>
						</fieldset>
					</div>
				</div>
			</div>
		</div>
	</div>


	<!-- Save Button -->
	<button type="button" id="save-rest-api-settings" class="ghl-button ghl-button-success ghl-button-medium">
		<span class="ghl-button-text"><?php esc_html_e( 'Save REST API Settings', 'ghl-crm-integration' ); ?></span>
	</button>

	<!-- Help Section -->
	<div class="ghl-help-box" style="margin-top: 30px;">
		<h3>
			<span class="dashicons dashicons-shield"></span>
			<?php esc_html_e( 'Security Best Practices', 'ghl-crm-integration' ); ?>
		</h3>
		<div class="ghl-help-content">
			<ul style="list-style: disc; margin-left: 20px;">
				<li><?php esc_html_e( 'Always use HTTPS when making API requests', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Store API keys securely and never commit them to version control', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Use IP whitelisting to restrict access to known servers', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Enable rate limiting to prevent abuse', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Regenerate API keys regularly and after any security incident', 'ghl-crm-integration' ); ?></li>
				<li><?php esc_html_e( 'Monitor API usage through the sync logs', 'ghl-crm-integration' ); ?></li>
			</ul>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Generate new API key
	$('#ghl-generate-api-key').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure? This will invalidate the current API key and all services using it will stop working until updated.', 'ghl-crm-integration' ); ?>')) {
			return;
		}

		// Generate a secure random key
		const newKey = Array.from(crypto.getRandomValues(new Uint8Array(32)))
			.map(b => b.toString(16).padStart(2, '0'))
			.join('');
		
		$('#rest_api_key').val(newKey);
		
		// Show success message
		const $notice = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p><?php esc_html_e( 'New API key generated! Remember to save settings.', 'ghl-crm-integration' ); ?></p></div>');
		$(this).closest('td').append($notice);
		
		setTimeout(function() {
			$notice.fadeOut(function() { $(this).remove(); });
		}, 3000);
	});

	// Copy API key to clipboard
	$('#ghl-copy-api-key').on('click', function() {
		const $input = $('#rest_api_key');
		$input.select();
		document.execCommand('copy');
		
		const $btn = $(this);
		const originalHTML = $btn.html();
		$btn.html('<span class="dashicons dashicons-yes" style="vertical-align: middle; color: #46b450;"></span>');
		
		setTimeout(function() {
			$btn.html(originalHTML);
		}, 2000);
	});

	// Save button handler
	$('#save-rest-api-settings').on('click', function(e) {
		e.preventDefault();
		
		const $button = $(this);
		const $buttonText = $button.find('.ghl-button-text');
		const originalText = $buttonText.text();
		
		// Get form data
		const formData = {
			action: 'ghl_crm_save_settings',
			nonce: $('#ghl_crm_nonce').val(),
			rest_api_enabled: $('#rest_api_enabled').is(':checked') ? '1' : '0',
			rest_api_key: $('#rest_api_key').val(),
			rest_api_ip_whitelist: $('#rest_api_ip_whitelist').val(),
			rest_api_rate_limit: $('#rest_api_rate_limit').is(':checked') ? '1' : '0',
			rest_api_requests_per_minute: $('#rest_api_requests_per_minute').val(),
			rest_api_endpoints: []
		};
		
		// Get selected endpoints
		$('input[name="rest_api_endpoints[]"]:checked').each(function() {
			formData.rest_api_endpoints.push($(this).val());
		});
		
		// Handle empty array
		if (formData.rest_api_endpoints.length === 0) {
			formData.rest_api_endpoints = '__EMPTY_ARRAY__';
		}
		
		// Show loading
		$button.prop('disabled', true);
		$buttonText.text('<?php esc_html_e( 'Saving...', 'ghl-crm-integration' ); ?>');
		
		// Save settings
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					$buttonText.text('<?php esc_html_e( 'Saved!', 'ghl-crm-integration' ); ?>');
					
					// Show success notice
					const $notice = $('<div class="notice notice-success is-dismissible"><p>' + (response.data.message || '<?php esc_html_e( 'Settings saved successfully!', 'ghl-crm-integration' ); ?>') + '</p></div>');
					$('.ghl-settings-wrapper').prepend($notice);
					
					setTimeout(function() {
						$notice.fadeOut(function() { $(this).remove(); });
						$buttonText.text(originalText);
						$button.prop('disabled', false);
					}, 2000);
				} else {
					$buttonText.text(originalText);
					$button.prop('disabled', false);
					alert(response.data.message || '<?php esc_html_e( 'Failed to save settings.', 'ghl-crm-integration' ); ?>');
				}
			},
			error: function() {
				$buttonText.text(originalText);
				$button.prop('disabled', false);
				alert('<?php esc_html_e( 'An error occurred while saving settings.', 'ghl-crm-integration' ); ?>');
			}
		});
	});
});
</script>
