<?php
/**
 * Settings - Personalization Template
 *
 * Personalization settings tab content
 *
 * @package    Syncly
 * @subpackage Syncly/templates/admin/partials/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_manager = \Syncly\Core\SettingsManager::get_instance();
$settings         = $settings_manager->get_settings_array();
$campaign_token   = trim( (string) ( $settings['ghl_cid_secret_key'] ?? '' ) );
$link_token       = '' !== $campaign_token ? rawurlencode( $campaign_token ) : 'YOUR_CAMPAIGN_ACCESS_TOKEN';
$link_template    = home_url( '/page' ) . '?syncly_cid={{contact.id}}&syncly_token=' . $link_token;
?>

<div class="ghl-settings-wrapper">
	<?php wp_nonce_field( 'syncly_settings_nonce', 'syncly_nonce' ); ?>

	<div class="ghl-settings-section ghl-settings-card">
		<div class="ghl-settings-header">
			<h2>
				<span class="dashicons dashicons-email-alt"></span>
				<?php esc_html_e( 'Email Campaign Personalization', 'syncly' ); ?>
			</h2>
			<p class="description">
				<?php esc_html_e( 'When GoHighLevel sends an email campaign, append the contact ID and your campaign access token to links so visitors arriving from those emails can see personalized content even without logging in. Use [syncly_user_meta] shortcodes normally; the plugin resolves them from the contact\'s GHL data only after the access token is valid.', 'syncly' ); ?>
			</p>
			<p class="description" style="margin-top: 6px;">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: Site home URL for personalization link example. */
						__( '<strong>Campaign personalization:</strong> <code>https://%s/page?syncly_cid={{contact.id}}&amp;syncly_token=YOUR_CAMPAIGN_ACCESS_TOKEN</code>', 'syncly' ),
						esc_html( home_url() )
					),
					[
						'strong' => [],
						'code'   => [],
					]
				);
				?>
			</p>
		</div>

		<hr>

		<div class="ghl-form-builder">
			<form class="ghl-form" method="post">
				<table class="form-table" role="presentation">
					<tbody>

						<tr>
							<th scope="row">
								<label for="enable_ghl_cid">
										<?php esc_html_e( 'Enable Campaign Contact Links', 'syncly' ); ?>
										<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'When enabled, the plugin reads the syncly_cid query parameter from the URL and uses it to personalize [syncly_user_meta] shortcodes only when the syncly_token access token is valid.', 'syncly' ); ?>">?</span>
								</label>
							</th>
							<td>
								<label class="ghl-checkbox ghl-advanced-checkbox-label <?php echo ! empty( $settings['enable_ghl_cid'] ) ? 'is-checked' : ''; ?>">
									<input
										type="checkbox"
										class="ghl-checkbox-original"
										id="enable_ghl_cid"
										name="enable_ghl_cid"
										value="1"
										<?php checked( ! empty( $settings['enable_ghl_cid'] ), true ); ?>
									>
									<span class="ghl-checkbox-input <?php echo ! empty( $settings['enable_ghl_cid'] ) ? 'is-checked' : ''; ?>">
										<span class="ghl-checkbox-inner"></span>
									</span>
									<span class="ghl-checkbox-label">
										<?php esc_html_e( 'Personalize pages for visitors from GHL email links', 'syncly' ); ?>
									</span>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ghl_cid_secret_key">
									<?php esc_html_e( 'Campaign Access Token', 'syncly' ); ?>
									<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Create a long random token, save it here, and add the same value to your GoHighLevel email links as syncly_token. Requests without this token are ignored.', 'syncly' ); ?>">?</span>
								</label>
							</th>
							<td>
								<input
									type="password"
									id="ghl_cid_secret_key"
									name="ghl_cid_secret_key"
									class="regular-text"
									value="<?php echo esc_attr( $settings['ghl_cid_secret_key'] ?? '' ); ?>"
									autocomplete="off"
								>
								<p class="description ghl-description-spacing">
									<?php esc_html_e( 'Required. Use a long random value that only appears in trusted GoHighLevel campaign links.', 'syncly' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ghl-cid-link-template">
									<?php esc_html_e( 'Copy Link Template', 'syncly' ); ?>
								</label>
							</th>
							<td>
								<input
									type="text"
									id="ghl-cid-link-template"
									readonly
									class="regular-text"
									value="<?php echo esc_attr( $link_template ); ?>"
								>
								<button type="button" class="ghl-button ghl-button-secondary" id="ghl-copy-cid-template" style="margin-left: 8px; vertical-align: middle;">
									<?php esc_html_e( 'Copy', 'syncly' ); ?>
								</button>
								<p class="description ghl-description-spacing">
										<?php esc_html_e( 'Copy this template and use it in your GHL email campaigns.', 'syncly' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="ghl_cid_hidden_fields">
									<?php esc_html_e( 'Hide Fields From Guests', 'syncly' ); ?>
									<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Select fields to hide from campaign visitors. Leave empty to show all fields.', 'syncly' ); ?>">?</span>
								</label>
							</th>
							<td>
								<select
									id="ghl_cid_hidden_fields"
									name="ghl_cid_hidden_fields[]"
									class="ghl-field-select2"
									style="width: 100%;"
									multiple="multiple"
								>
									<?php
									$available_fields = array(
										'first_name'  => __( 'First Name', 'syncly' ),
										'last_name'   => __( 'Last Name', 'syncly' ),
										'email'       => __( 'Email Address', 'syncly' ),
										'phone'       => __( 'Phone Number', 'syncly' ),
										'company'     => __( 'Company Name', 'syncly' ),
										'street'      => __( 'Street Address', 'syncly' ),
										'city'        => __( 'City', 'syncly' ),
										'state'       => __( 'State/Province', 'syncly' ),
										'postal_code' => __( 'Postal Code', 'syncly' ),
										'country'     => __( 'Country', 'syncly' ),
									);

									$hidden_fields = isset( $settings['ghl_cid_hidden_fields'] ) ? (array) json_decode( $settings['ghl_cid_hidden_fields'], true ) : array();

									foreach ( $available_fields as $field_key => $field_label ) {
										?>
										<option value="<?php echo esc_attr( $field_key ); ?>" <?php selected( in_array( $field_key, $hidden_fields, true ), true ); ?>>
											<?php echo esc_html( $field_label ); ?>
										</option>
										<?php
									}
									?>
								</select>
								<p class="description ghl-description-spacing" style="margin-top: 12px;">
									<?php esc_html_e( 'By default, all fields are visible to campaign visitors. Select fields above to hide them.', 'syncly' ); ?>
								</p>
								<input type="hidden" id="ghl_cid_hidden_fields_json" name="ghl_cid_hidden_fields" value="<?php echo esc_attr( $settings['ghl_cid_hidden_fields'] ?? '[]' ); ?>">
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'Test Link Debugger', 'syncly' ); ?>
									<span class="ghl-tooltip-icon" data-ghl-tooltip="<?php esc_attr_e( 'Paste a campaign URL and see exactly what contact data resolves and which fields are available.', 'syncly' ); ?>">?</span>
								</label>
							</th>
							<td>
								<button type="button" class="ghl-button ghl-button-secondary" id="ghl-test-link-btn">
									<?php esc_html_e( 'Open Test Link Tool', 'syncly' ); ?>
								</button>
								<p class="description ghl-description-spacing">
									<?php esc_html_e( 'Debug what a campaign visitor will see from a signed contact URL.', 'syncly' ); ?>
								</p>
							</td>
						</tr>

					</tbody>
				</table>
			</form>
		</div>

		<hr>

		<button type="button" class="ghl-button ghl-button-primary ghl-save-settings-btn">
			<span class="ghl-button-text"><?php esc_html_e( 'Save Personalization Settings', 'syncly' ); ?></span>
		</button>
	</div>
</div>

<?php ob_start(); ?>
(function() {
	// Initialize Select2 for field selection
	var fieldSelect = document.querySelector('.ghl-field-select2');
	if (fieldSelect && typeof jQuery !== 'undefined' && jQuery.fn.select2) {
		jQuery(fieldSelect).select2({
			placeholder: '<?php echo esc_js( __( 'Select fields to hide...', 'syncly' ) ); ?>',
			allowClear: true,
			width: '100%'
		});

		// Sync Select2 changes to hidden JSON field
		jQuery(fieldSelect).on('change', function() {
			var selected = jQuery(this).val() || [];
			document.getElementById('ghl_cid_hidden_fields_json').value = JSON.stringify(selected);
		});
	}

	// Copy link template button
	var copyButton = document.getElementById('ghl-copy-cid-template');
	var templateInput = document.getElementById('ghl-cid-link-template');

	if (copyButton && templateInput) {
		copyButton.addEventListener('click', function() {
			templateInput.select();
			templateInput.setSelectionRange(0, 99999);

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(templateInput.value);
			} else {
				document.execCommand('copy');
			}

			copyButton.textContent = 'Copied';
			setTimeout(function() {
				copyButton.textContent = 'Copy';
			}, 1500);
		});
	}

	// Test Link button
	var testLinkBtn = document.getElementById('ghl-test-link-btn');
	if (testLinkBtn) {
		testLinkBtn.addEventListener('click', function(e) {
			e.preventDefault();
			openTestLinkModal();
		});
	}

	function openTestLinkModal() {
		var html = `
			<div id="ghl-test-link-modal" style="
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.5);
				display: flex;
				align-items: center;
				justify-content: center;
				z-index: 10000;
			">
				<div style="
					background: white;
					border-radius: 8px;
					padding: 24px;
					max-width: 600px;
					width: 90%;
					max-height: 80vh;
					overflow-y: auto;
					box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
				">
					<h2 style="margin-top: 0;">Test Link Debugger</h2>
					<p style="color: #666; margin-bottom: 16px;">Paste a campaign URL with a valid access token to see what resolves</p>

					<div style="margin-bottom: 16px;">
						<label style="display: block; margin-bottom: 8px; font-weight: 500;">Campaign URL</label>
						<input
							type="text"
							id="ghl-test-url-input"
							placeholder="https://yoursite.com/page?syncly_cid=abc123&syncly_token=..."
							style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;"
						>
					</div>

					<div style="margin-bottom: 16px;">
						<button type="button" class="ghl-button ghl-button-primary" id="ghl-test-link-submit" style="width: 100%;">
							Test Link
						</button>
					</div>

					<div id="ghl-test-results" style="
						background: #f5f5f5;
						border-radius: 4px;
						padding: 16px;
						margin-bottom: 16px;
						display: none;
						font-size: 14px;
						font-family: monospace;
					"></div>

					<button type="button" id="ghl-test-link-close" class="ghl-button ghl-button-secondary" style="width: 100%;">
						Close
					</button>
				</div>
			</div>
		`;

		var modal = document.createElement('div');
		modal.innerHTML = html;
		document.body.appendChild(modal);

		var submitBtn = document.getElementById('ghl-test-link-submit');
		var closeBtn = document.getElementById('ghl-test-link-close');
		var urlInput = document.getElementById('ghl-test-url-input');
		var resultsDiv = document.getElementById('ghl-test-results');

		submitBtn.addEventListener('click', function() {
			var url = urlInput.value.trim();
			if (!url) {
				alert('Please enter a URL');
				return;
			}

			submitBtn.disabled = true;
			submitBtn.textContent = 'Testing...';
			resultsDiv.innerHTML = 'Loading...';
			resultsDiv.style.display = 'block';

			var urlObj = new URL(url, window.location.origin);
			var contactId = urlObj.searchParams.get('syncly_cid') || urlObj.searchParams.get('ghl_cid');

			if (!contactId) {
				resultsDiv.innerHTML = '<strong style="color: red;">Error:</strong> No contact ID parameter found in URL';
				submitBtn.disabled = false;
				submitBtn.textContent = 'Test Link';
				return;
			}

			var formData = new FormData();
			formData.append('action', 'syncly_test_cid_link');
			formData.append('contact_id', contactId);
			formData.append('nonce', document.querySelector('input[name="syncly_nonce"]').value);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(function(response) { return response.json(); })
			.then(function(data) {
				resultsDiv.style.display = 'block';
				if (data.success) {
					var html = '<strong style="color: green;">✓ Success</strong><br><br>';
					html += '<strong>Contact ID:</strong> ' + escapeHtml(data.data.contact_id) + '<br>';
					if (data.data.wp_user_id) {
						html += '<strong>Mapped to WP User:</strong> #' + escapeHtml(data.data.wp_user_id) + ' (' + escapeHtml(data.data.wp_user_login) + ')<br>';
					} else {
						html += '<strong>WP User:</strong> <span style="color: #999;">not mapped</span><br>';
					}
					html += '<strong>Available Fields:</strong><br>';
					if (data.data.fields && Object.keys(data.data.fields).length > 0) {
						html += '<div style="margin-left: 16px; margin-top: 8px;">';
						for (var key in data.data.fields) {
							var value = data.data.fields[key];
							var displayValue = value && value !== '' ? escapeHtml(String(value)) : '(empty)';
							html += '<div>' + escapeHtml(key) + ': ' + displayValue + '</div>';
						}
						html += '</div>';
					} else {
						html += '<div style="color: #999; margin-left: 16px;">No fields available</div>';
					}
					resultsDiv.innerHTML = html;
				} else {
					resultsDiv.innerHTML = '<strong style="color: red;">Error:</strong> ' + escapeHtml(data.data.message || 'Unknown error');
				}
			})
			.catch(function(error) {
				resultsDiv.innerHTML = '<strong style="color: red;">Error:</strong> ' + escapeHtml(error.message);
			})
			.finally(function() {
				submitBtn.disabled = false;
				submitBtn.textContent = 'Test Link';
			});
		});

		closeBtn.addEventListener('click', function() {
			modal.remove();
		});

		document.getElementById('ghl-test-link-modal').addEventListener('click', function(e) {
			if (e.target === this) {
				modal.remove();
			}
		});

		urlInput.focus();
	}

	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}
})();
<?php wp_add_inline_script( 'syncly-settings-js', ob_get_clean() ); ?>
