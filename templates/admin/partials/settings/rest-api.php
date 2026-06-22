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
$settings         = $settings_manager->get_settings_array();

// REST API settings with defaults
$rest_api_enabled             = $settings['rest_api_enabled'] ?? false;
$rest_api_key                 = $settings['rest_api_key'] ?? '';
$rest_api_ip_whitelist        = $settings['rest_api_ip_whitelist'] ?? '';
$rest_api_rate_limit          = $settings['rest_api_rate_limit'] ?? true;
$rest_api_endpoints           = $settings['rest_api_endpoints'] ?? [ 'contacts', 'sync', 'status' ];
$rest_api_requests_per_minute = $settings['rest_api_requests_per_minute'] ?? 60;
$is_pro_active                = apply_filters( 'ghl_crm_public_rest_api_enabled', false );

// Generate API key if not exists
if ( empty( $rest_api_key ) ) {
	$rest_api_key = wp_generate_password( 32, false );
}
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'ghl_crm_settings_nonce', 'ghl_crm_nonce' ); ?>

	<?php if ( ! $is_pro_active ) : ?>
		<div class="ghl-settings-section ghl-settings-card">
			<div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px;">
				<div>
					<span style="display: inline-flex; padding: 3px 9px; border-radius: 999px; background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; font-size: 11px; font-weight: 700; text-transform: uppercase;"><?php esc_html_e( 'Syncly Pro', 'syncly' ); ?></span>
					<h2 style="margin: 8px 0 6px; color: #1e293b;"><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'REST API Access', 'syncly' ); ?></h2>
					<p class="description" style="max-width: 680px;"><?php esc_html_e( 'Expose secure endpoints for approved external systems to create contacts, trigger syncs, check status, and receive webhook events.', 'syncly' ); ?></p>
				</div>
				<a href="<?php echo esc_url( apply_filters( 'ghl_crm_upgrade_url', 'https://highlevelsync.com/' ) ); ?>" class="ghl-button ghl-button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn More', 'syncly' ); ?></a>
			</div>

			<div aria-hidden="true" style="display: grid; gap: 16px; opacity: 0.88;">
				<div style="padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
					<strong><?php esc_html_e( 'Authentication', 'syncly' ); ?></strong>
					<div style="margin-top: 10px; padding: 10px 12px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px; font-family: monospace; color: #475569;">Authorization: Bearer sk_live_••••••••••••••••</div>
				</div>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
					<span style="padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;"><code>/contacts</code><br><?php esc_html_e( 'Create or update contacts', 'syncly' ); ?></span>
					<span style="padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;"><code>/sync</code><br><?php esc_html_e( 'Trigger a sync', 'syncly' ); ?></span>
					<span style="padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;"><code>/status</code><br><?php esc_html_e( 'Read queue status', 'syncly' ); ?></span>
					<span style="padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;"><code>/webhooks</code><br><?php esc_html_e( 'Receive events', 'syncly' ); ?></span>
				</div>
			</div>
		</div>
	</div>
		<?php return; ?>
	<?php endif; ?>
	
	
	<!-- Master Toggle -->
	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-rest-api"></span>
				<?php esc_html_e( 'REST API Access', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Enable external services to interact with your plugin through REST API endpoints.', 'syncly' ); ?>
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
								<?php esc_html_e( 'Enable REST API endpoints', 'syncly' ); ?>
								<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Allows external applications (like Zapier, Make.com, or custom integrations) to interact with your GoHighLevel data through secure API endpoints. Only enable if you need programmatic access.', 'syncly' ); ?>">?</span>
							</span>
						</label>
					</div>
					<p class="description" style="margin-left: 54px;">
						<?php esc_html_e( 'When enabled, external services can use REST API to create/update contacts and trigger syncs.', 'syncly' ); ?>
					</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Authentication Settings -->
	<div class="ghl-settings-section ghl-settings-card">
		<h2><?php esc_html_e( 'Authentication', 'syncly' ); ?></h2>
		<p><?php esc_html_e( 'Manage API keys for secure access to REST endpoints.', 'syncly' ); ?></p>
		<hr>
		
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="rest_api_key">
							<?php esc_html_e( 'API Key', 'syncly' ); ?>
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
							<button type="button" class="ghl-button ghl-button-secondary" id="ghl-generate-api-key">
								<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
								<span data-ghl-tooltip="<?php esc_attr_e( 'Creates a new random API key and invalidates the old one. Update all external integrations with the new key after regenerating.', 'syncly' ); ?>"><?php esc_html_e( 'Regenerate', 'syncly' ); ?></span>
							</button>
							<button type="button" class="button button-secondary" id="ghl-copy-api-key" title="<?php esc_attr_e( 'Copy to clipboard', 'syncly' ); ?>">
								<span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
							</button>
						</div>
						<p class="description">
							<?php esc_html_e( 'Use this key in Authorization header: ', 'syncly' ); ?>
							<code>Authorization: Bearer YOUR_API_KEY</code>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Security Settings -->
	<div class="ghl-settings-section ghl-settings-card">
		<h2><?php esc_html_e( 'Security Settings', 'syncly' ); ?></h2>
		<p><?php esc_html_e( 'Configure IP restrictions and rate limiting for enhanced security.', 'syncly' ); ?></p>
		<hr>
		
		<table class="form-table" role="presentation">
			<tbody>

				<tr>
					<th scope="row">
						<label for="rest_api_ip_whitelist">
							<?php esc_html_e( 'IP Whitelist', 'syncly' ); ?>
							<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Restricts API access to specific IP addresses. Enter one IP or CIDR range per line (e.g., 192.168.1.1 or 10.0.0.0/8). Leave empty to allow requests from any IP address.', 'syncly' ); ?>">?</span>
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
							<?php esc_html_e( 'One IP address or CIDR per line. Leave empty to allow all IPs.', 'syncly' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<?php esc_html_e( 'Rate Limiting', 'syncly' ); ?>
						<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Prevents API abuse by limiting how many requests each IP address can make per minute. Recommended for production sites to prevent server overload.', 'syncly' ); ?>">?</span>
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
								<?php esc_html_e( 'Enable rate limiting', 'syncly' ); ?>
							</span>
						</label>
						<div style="margin-top: 10px;">
							<label for="rest_api_requests_per_minute" style="display: inline-block; margin-right: 10px;">
								<?php esc_html_e( 'Max requests per minute:', 'syncly' ); ?>
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
							<?php esc_html_e( 'Limit API requests per IP address to prevent abuse.', 'syncly' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Endpoint Configuration -->
	<div class="ghl-settings-section ghl-settings-card">
		<h2><?php esc_html_e( 'Allowed Endpoints', 'syncly' ); ?>
		<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Control which specific API endpoints are accessible. Only enable endpoints you need to minimize security exposure. Uncheck unused endpoints to disable them.', 'syncly' ); ?>">?</span>
		</h2>
		<p><?php esc_html_e( 'Choose which REST API endpoints are available for external access.', 'syncly' ); ?></p>
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
	<button type="button" id="save-rest-api-settings" class="ghl-button ghl-button-primary ghl-save-settings-btn">
		<span class="ghl-button-text"><?php esc_html_e( 'Save REST API Settings', 'syncly' ); ?></span>
	</button>

	<!-- Help Section -->
	<div class="ghl-help-box" style="margin-top: 30px;">
		<h3>
			<span class="dashicons dashicons-shield"></span>
			<?php esc_html_e( 'Security Best Practices', 'syncly' ); ?>
		</h3>
		<div class="ghl-help-content">
			<ul style="list-style: disc; margin-left: 20px;">
				<li><?php esc_html_e( 'Always use HTTPS when making API requests', 'syncly' ); ?></li>
				<li><?php esc_html_e( 'Store API keys securely and never commit them to version control', 'syncly' ); ?></li>
				<li><?php esc_html_e( 'Use IP whitelisting to restrict access to known servers', 'syncly' ); ?></li>
				<li><?php esc_html_e( 'Enable rate limiting to prevent abuse', 'syncly' ); ?></li>
				<li><?php esc_html_e( 'Regenerate API keys regularly and after any security incident', 'syncly' ); ?></li>
				<li><?php esc_html_e( 'Monitor API usage through the sync logs', 'syncly' ); ?></li>
			</ul>
		</div>
	</div>
</div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {
	// Generate new API key
	$('#ghl-generate-api-key').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure? This will invalidate the current API key and all services using it will stop working until updated.', 'syncly' ); ?>')) {
			return;
		}

		// Generate a secure random key
		const newKey = Array.from(crypto.getRandomValues(new Uint8Array(32)))
			.map(b => b.toString(16).padStart(2, '0'))
			.join('');
		
		$('#rest_api_key').val(newKey);
		
		// Show success message
		const $notice = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p><?php esc_html_e( 'New API key generated! Remember to save settings.', 'syncly' ); ?></p></div>');
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
		$buttonText.text('<?php esc_html_e( 'Saving...', 'syncly' ); ?>');
		
		// Save settings
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					$buttonText.text('<?php esc_html_e( 'Saved!', 'syncly' ); ?>');
					
					// Show success notice
					const $notice = $('<div class="notice notice-success is-dismissible"><p>' + (response.data.message || '<?php esc_html_e( 'Settings saved successfully!', 'syncly' ); ?>') + '</p></div>');
					$('.ghl-settings-wrapper').prepend($notice);
					
					setTimeout(function() {
						$notice.fadeOut(function() { $(this).remove(); });
						$buttonText.text(originalText);
						$button.prop('disabled', false);
					}, 2000);
				} else {
					$buttonText.text(originalText);
					$button.prop('disabled', false);
					alert(response.data.message || '<?php esc_html_e( 'Failed to save settings.', 'syncly' ); ?>');
				}
			},
			error: function() {
				$buttonText.text(originalText);
				$button.prop('disabled', false);
				alert('<?php esc_html_e( 'An error occurred while saving settings.', 'syncly' ); ?>');
			}
		});
	});
});
<?php wp_add_inline_script( 'ghl-crm-settings-js', ob_get_clean() ); ?>
