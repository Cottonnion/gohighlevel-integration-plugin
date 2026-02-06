/**
 * Forms Management JavaScript
 *
 * @package GHL_CRM_Integration
 * @subpackage Assets/Admin/JS
 */

(function($) {
	'use strict';

	const formsData = window.ghl_crm_forms_js_data || {};
	const strings = formsData.strings || {};
	const whiteLabelDomain = formsData.whiteLabelDomain || '';
	const ajaxUrl = formsData.ajaxUrl || '';
	const nonce = formsData.nonce || '';
	const formSettings = formsData.formSettings || {};

	// Debug: Log form settings
	console.log('Form Settings loaded:', formSettings);

	const FormsManager = {
		initialized: false,
		loading: false,

		init: function() {
			if (this.initialized) {
				// Already initialized, just rebind events
				this.bindEvents();
				return;
			}
			this.initialized = true;
			this.bindEvents();
			this.loadForms();
		},

		reset: function() {
			this.initialized = false;
			this.loading = false;
		},

		bindEvents: function() {
			const self = this;
			$('#ghl-refresh-forms').off('click').on('click', function(e) {
				e.preventDefault();
				self.loadForms();
			});
		},

		loadForms: function() {
			if (this.loading) {
				return;
			}
			this.loading = true;

			$('#ghl-forms-loading').show();
			$('#ghl-forms-error').hide();
			$('#ghl-forms-table-wrapper').empty();

			$.ajax({
				url: formsData.ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'ghl_crm_get_forms',
					nonce: formsData.nonce
				},
				success: (response) => {
					this.loading = false;
					$('#ghl-forms-loading').hide();
					
					if (response.success) {
						this.renderForms(response.data.forms || []);
					} else {
						this.showError(response.data?.message || strings.errorLoad || 'Failed to load forms');
					}
				},
				error: (xhr, status, error) => {
					this.loading = false;
					$('#ghl-forms-loading').hide();
					this.showError((strings.ajaxError || 'AJAX error: ') + error);
				}
			});
		},

		renderForms: function(forms) {
			const $wrapper = $('#ghl-forms-table-wrapper');
			$wrapper.empty();
			
			if (!forms || forms.length === 0) {
				$wrapper.html(`
					<div class="ghl-forms-empty">
						<span class="dashicons dashicons-media-document"></span>
						<h3>${strings.noForms || 'No Forms Found'}</h3>
						<p>${strings.noFormsDesc || 'Create forms in your GoHighLevel account to display them here.'}</p>
					</div>
				`);
				return;
			}

			let tableHtml = `
				<table class="ghl-forms-table">
					<thead>
						<tr>
							<th class="ghl-table-col-name">${strings.formName || 'Form Name'}</th>
							<th class="ghl-table-col-id">${strings.formId || 'Form ID'}</th>
							<th class="ghl-table-col-submissions">${strings.submissions || 'Submissions'}</th>
							<th class="ghl-table-col-shortcode">${strings.shortcode || 'Shortcode'}</th>
							<th class="ghl-table-col-actions">${strings.actions || 'Actions'}</th>
						</tr>
					</thead>
					<tbody>
			`;

			forms.forEach(form => {
				const submissionsText = form.submissions ? form.submissions : '0';
				tableHtml += `
					<tr data-form-id="${form.id}">
						<td class="ghl-table-col-name" data-label="${strings.formName || 'Form Name'}">
							<strong>${this.escapeHtml(form.name || strings.untitledForm || 'Untitled Form')}</strong>
						</td>
						<td class="ghl-table-col-id" data-label="${strings.formId || 'Form ID'}">
							<code>${this.escapeHtml(form.id)}</code>
						</td>
						<td class="ghl-table-col-submissions" data-label="${strings.submissions || 'Submissions'}">
							${submissionsText}
						</td>
						<td class="ghl-table-col-shortcode" data-label="${strings.shortcode || 'Shortcode'}">
							<div class="ghl-shortcode-box" title="${strings.clickToCopy || 'Click to copy'}">
								[ghl_form id="${form.id}"]
							</div>
						</td>
						<td class="ghl-table-col-actions" data-label="${strings.actions || 'Actions'}">
							<div class="ghl-table-actions">
								<button type="button" class="ghl-form-icon-btn ghl-copy-shortcode" data-shortcode="[ghl_form id=&quot;${form.id}&quot;]" title="${strings.copy || 'Copy shortcode'}">
									<span class="dashicons dashicons-clipboard"></span>
								</button>
								${form.widgetUrl ? `
									<a href="${this.escapeHtml(form.widgetUrl)}" target="_blank" rel="noopener noreferrer" class="ghl-form-icon-btn" title="${strings.preview || 'Preview form'}">
										<span class="dashicons dashicons-visibility"></span>
									</a>
								` : ''}
								<button type="button" class="ghl-form-icon-btn ghl-form-settings-btn" data-form-id="${form.id}" title="${strings.settings || 'Form settings'}">
									<span class="dashicons dashicons-admin-generic"></span>
								</button>
							</div>
						</td>
					</tr>
				`;
			});

			tableHtml += `
					</tbody>
				</table>
			`;

			$wrapper.html(tableHtml);

			// Bind copy button events
			$('.ghl-copy-shortcode').on('click', function() {
				const shortcode = $(this).data('shortcode');
				FormsManager.copyToClipboard(shortcode, $(this));
			});

			// Bind shortcode box click events
			$('.ghl-shortcode-box').on('click', function() {
				const text = $(this).text().trim();
				FormsManager.copyToClipboard(text, $(this));
			});

			// Bind form settings button events
			$('.ghl-form-settings-btn').on('click', function() {
				const formId = $(this).data('form-id');
				FormsManager.showFormSettings(formId);
			});
		},

		showError: function(message) {
			$('#ghl-forms-error-message').text(message);
			$('#ghl-forms-error').show();
		},

		copyToClipboard: function(text, $element) {
			if (!navigator.clipboard) {
				// Fallback for older browsers
				const $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(text).select();
				document.execCommand('copy');
				$temp.remove();
				this.showCopyFeedback($element);
				return;
			}

			navigator.clipboard.writeText(text).then(() => {
				this.showCopyFeedback($element);
			}).catch(err => {
				swal.fire({
					title: strings.copyFailed || 'Copy failed',
					text: strings.copyFailedDesc || 'Unable to copy to clipboard. Please try copying manually.',
					icon: 'error',
					confirmButtonText: strings.ok || 'OK'
				});
			});
		},

		showCopyFeedback: function($element) {
			const originalBg = $element.css('background-color');
			const originalText = $element.html();
			
			$element.css('background-color', '#46b450');
			if ($element.hasClass('button')) {
				$element.html('<span class="dashicons dashicons-yes"></span> Copied!');
			}
			
			setTimeout(() => {
				$element.css('background-color', originalBg);
				if ($element.hasClass('button')) {
					$element.html(originalText);
				}
			}, 1500);
		},

		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, m => map[m]);
	},

	showFormSettings: function(formId) {
		// Get existing settings from localized data
		const existingSettings = formSettings[formId] || {};
		
		const autofillEnabled = existingSettings.autofill_enabled !== false; // Default to true
		const loggedOnly = existingSettings.logged_only === true; // Default to false
		const customParams = existingSettings.custom_params || [];
		const submissionLimit = existingSettings.submission_limit || 'unlimited';
		const submittedMessage = existingSettings.submitted_message || '';
		
		// Build custom params HTML
		let customParamsHtml = '';
		if (customParams.length > 0) {
			customParams.forEach((param, index) => {
				customParamsHtml += this.buildCustomParamRow(index, param.key, param.value);
			});
		} else {
			customParamsHtml = this.buildCustomParamRow(0, '', '');
		}
		
		// Create settings panel HTML
		const panelHtml = `
			<div class="ghl-form-settings-panel" data-form-id="${formId}">
				<div class="ghl-form-settings-overlay"></div>
				<div class="ghl-form-settings-sidebar">
					<div class="ghl-settings-header">
						<h2>
							<span class="dashicons dashicons-admin-generic"></span>
								Form Settings
							</h2>
							<button type="button" class="ghl-settings-close">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
						
						<div class="ghl-settings-body">
							<div class="ghl-settings-section ghl-settings-card">
								<div class="ghl-form-builder">
									<!-- Auto-fill Settings -->
									<div class="ghl-settings-group">
										<h3>
											<span class="dashicons dashicons-admin-users"></span>
											Auto-fill Settings
										</h3>
										<p class="description">Control how this form behaves for logged-in users</p>
										
										<div class="ghl-form-item">
											<div class="ghl-form-item-content">
												<label class="ghl-checkbox ${autofillEnabled ? 'is-checked' : ''}">
													<input type="checkbox" 
														   class="ghl-checkbox-original"
														   id="ghl-form-autofill-${formId}" 
														   name="autofill_enabled" 
														   value="1" 
														   ${autofillEnabled ? 'checked' : ''}>
													<span class="ghl-checkbox-input ${autofillEnabled ? 'is-checked' : ''}">
														<span class="ghl-checkbox-inner"></span>
													</span>
													<span class="ghl-checkbox-label">
														Enable Auto-fill for Logged-in Users
														<span class="ghl-tooltip-icon" data-ghl-tooltip="Automatically pre-fill form fields with user data (name, email, phone, address, etc.) when a logged-in user views this form.">?</span>
													</span>
												</label>
											</div>
										</div>
										
										<div class="ghl-form-item">
											<div class="ghl-form-item-content">
												<label class="ghl-checkbox ${loggedOnly ? 'is-checked' : ''}">
													<input type="checkbox" 
														   class="ghl-checkbox-original"
														   id="ghl-form-logged-only-${formId}" 
														   name="logged_only" 
														   value="1"
														   ${loggedOnly ? 'checked' : ''}>
													<span class="ghl-checkbox-input ${loggedOnly ? 'is-checked' : ''}">
														<span class="ghl-checkbox-inner"></span>
													</span>
													<span class="ghl-checkbox-label">
														Show Form to Logged-in Users Only
														<span class="ghl-tooltip-icon" data-ghl-tooltip="Hide this form from visitors who are not logged in.">?</span>
													</span>
												</label>
											</div>
										</div>
									</div>
									
									<!-- Submission Limit Settings -->
									<div class="ghl-settings-group">
										<h3>
											<span class="dashicons dashicons-forms"></span>
											Submission Controls
											${!ghl_crm_forms_js_data.isPro ? `<span class="dashicons dashicons-lock ghl-pro-lock-icon" data-ghl-tooltip="Upgrade to Pro to unlock submission controls"></span>` : ''}
										</h3>
										<p class="description">Control how many times users can submit this form</p>
										
										<div class="ghl-form-item">
											<label for="ghl-form-submission-limit-${formId}">
												Submission Limit
												<span class="ghl-tooltip-icon" data-ghl-tooltip="Choose whether users can submit this form multiple times or only once.">?</span>
											</label>
											<select id="ghl-form-submission-limit-${formId}" name="submission_limit" class="ghl-select" ${!ghl_crm_forms_js_data.isPro ? 'disabled' : ''}>
												<option value="unlimited" ${submissionLimit === 'unlimited' ? 'selected' : ''}>Unlimited - Allow multiple submissions</option>
												<option value="once" ${submissionLimit === 'once' ? 'selected' : ''}>Once - One submission per user</option>
											</select>
										</div>
										
										<div class="ghl-form-item" id="ghl-submitted-message-wrapper-${formId}" style="${submissionLimit === 'once' ? '' : 'display:none;'}">
											<label for="ghl-form-submitted-message-${formId}">
												Submitted Message
												<span class="ghl-tooltip-icon" data-ghl-tooltip="Message to show after user submits. Leave empty to hide the form completely without showing any message.">?</span>
											</label>
											<textarea 
												id="ghl-form-submitted-message-${formId}" 
												name="submitted_message" 
												class="ghl-textarea" 
												rows="3" 
												placeholder="Thank you! You have already submitted this form."
												${!ghl_crm_forms_js_data.isPro ? 'disabled' : ''}
											>${this.escapeHtml(submittedMessage)}</textarea>
											<p class="description" style="margin-top: 5px; color: #666;">Leave empty to hide form completely without showing any message.</p>
										</div>
									</div>
									
									<!-- Custom URL Parameters -->
									<div class="ghl-settings-group">
										<h3>
											<span class="dashicons dashicons-admin-settings"></span>
											Custom URL Parameters
											${!ghl_crm_forms_js_data.isPro ? `<span class="dashicons dashicons-lock ghl-pro-lock-icon" data-ghl-tooltip="Upgrade to Pro to unlock custom URL parameters"></span>` : ''}
										</h3>
										<p class="description">Add custom parameters to the form URL using dynamic variables</p>
										
										<div class="ghl-custom-params-container" id="ghl-custom-params-${formId}">
											${customParamsHtml}
										</div>
										<div class="ghl-form-item">
											<button type="button" class="ghl-button ghl-button-secondary ghl-add-custom-param" ${!ghl_crm_forms_js_data.isPro ? 'disabled' : ''}>
												<span class="dashicons dashicons-plus-alt"></span>
												Add Parameter
											</button>
										</div>
										<div class="ghl-form-item">
											<div class="ghl-available-variables">
												<h4>Available Variables:</h4>
												<ul>
													<li><code class="ghl-copy-variable" data-variable="{user_email}">{user_email}</code> - User's email address</li>
													<li><code class="ghl-copy-variable" data-variable="{user_first_name}">{user_first_name}</code> - User's first name</li>
													<li><code class="ghl-copy-variable" data-variable="{user_last_name}">{user_last_name}</code> - User's last name</li>
													<li><code class="ghl-copy-variable" data-variable="{user_display_name}">{user_display_name}</code> - User's display name</li>
													<li><code class="ghl-copy-variable" data-variable="{user_login}">{user_login}</code> - User's login username</li>
													<li><code class="ghl-copy-variable" data-variable="{user_id}">{user_id}</code> - User's ID</li>
													<li><code class="ghl-copy-variable" data-variable="{user_role}">{user_role}</code> - User's role</li>
													<li><code class="ghl-copy-variable" data-variable="{site_url}">{site_url}</code> - Site URL</li>
													<li><code class="ghl-copy-variable" data-variable="{site_name}">{site_name}</code> - Site name</li>
													<li><code class="ghl-copy-variable" data-variable="{current_url}">{current_url}</code> - Current page URL</li>
													<li><code class="ghl-copy-variable" data-variable="{current_title}">{current_title}</code> - Current page title</li>
													<li><code class="ghl-copy-variable" data-variable="{meta:field_name}">{meta:field_name}</code> - User meta field value</li>
												</ul>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						
						<div class="ghl-settings-footer">
							<button type="button" class="ghl-button ghl-button-primary ghl-save-form-settings">
								<span class="dashicons dashicons-yes"></span>
								Save Settings
							</button>
							<button type="button" class="ghl-button ghl-button-secondary ghl-settings-close">
								Cancel
							</button>
						</div>
					</div>
				</div>
			`;
			
			// Remove any existing panel
			$('.ghl-form-settings-panel').remove();
			
			// Add panel to body
			$('body').append(panelHtml);
			
			// Initialize tooltips
			if (window.initializeTooltips) {
				window.initializeTooltips();
			}
			
			// Show panel with animation
			setTimeout(() => {
				$('.ghl-form-settings-panel').addClass('is-open');
			}, 10);
			
			// Bind close events
			$('.ghl-settings-close, .ghl-form-settings-overlay').on('click', function() {
				FormsManager.closeFormSettings();
			});
			
			// Bind checkbox toggle
			$('.ghl-checkbox').on('click', function() {
				const $checkbox = $(this).find('.ghl-checkbox-original');
				const $input = $(this).find('.ghl-checkbox-input');
				
				$checkbox.prop('checked', !$checkbox.prop('checked'));
				
				if ($checkbox.prop('checked')) {
					$(this).addClass('is-checked');
					$input.addClass('is-checked');
				} else {
					$(this).removeClass('is-checked');
					$input.removeClass('is-checked');
				}
		});
		
		// Bind submission limit dropdown
		$(`#ghl-form-submission-limit-${formId}`).on('change', function() {
			const value = $(this).val();
			const $msgWrapper = $(`#ghl-submitted-message-wrapper-${formId}`);
			if (value === 'once') {
				$msgWrapper.slideDown(200);
			} else {
				$msgWrapper.slideUp(200);
			}
		});
		
		// Bind add parameter button
		$('.ghl-add-custom-param').on('click', function() {
			const $container = $(`#ghl-custom-params-${formId}`);
			const index = $container.find('.ghl-custom-param-row').length;
			const rowHtml = FormsManager.buildCustomParamRow(index, '', '');
			$container.append(rowHtml);
			FormsManager.bindCustomParamEvents();
		});
		
		// Bind copy variable events
		$('.ghl-copy-variable').on('click', function(e) {
			e.preventDefault();
			const variable = $(this).data('variable');
			FormsManager.copyToClipboard(variable, $(this));
		});
		
		// Bind remove parameter events
		this.bindCustomParamEvents();
		
		// Bind save button
		$('.ghl-save-form-settings').on('click', function() {
			const $btn = $(this);
			
			// Collect custom parameters
			const custom_params = [];
			$(`#ghl-custom-params-${formId} .ghl-custom-param-row`).each(function() {
				const key = $(this).find('.ghl-param-key').val().trim();
				const value = $(this).find('.ghl-param-value').val().trim();
				if (key && value) {
					custom_params.push({ key, value });
				}
			});
			
			const settings = {
				autofill_enabled: $(`#ghl-form-autofill-${formId}`).prop('checked'),
				logged_only: $(`#ghl-form-logged-only-${formId}`).prop('checked'),
				custom_params: custom_params,
				submission_limit: $(`#ghl-form-submission-limit-${formId}`).val(),
				submitted_message: $(`#ghl-form-submitted-message-${formId}`).val()
			};
			
		
		// Disable button during save
		$btn.prop('disabled', true).text('Saving...');
		
		// Save via AJAX
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'ghl_save_form_settings',
				nonce: nonce,
				form_id: formId,
				settings: settings
			},
			success: function(response) {
				if (response.success) {
					// Update local reference
					formSettings[formId] = settings;
					
					// Show success message
					FormsManager.showSuccessToast('Form settings saved successfully');						// Close panel
						FormsManager.closeFormSettings();
					} else {
						alert('Error: ' + (response.data || 'Unknown error'));
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Save Settings');
					}
				},
				error: function(xhr, status, error) {
					alert('AJAX Error: ' + error);
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Save Settings');
				}
			});
		});
	},
	
	buildCustomParamRow: function(index, key, value) {
		key = key || '';
		value = value || '';
		
		return `
			<div class="ghl-custom-param-row ghl-form-item">
				<div class="ghl-param-inputs">
					<input type="text" 
						   class="ghl-param-key" 
						   placeholder="Parameter name (e.g., source)" 
						   value="${this.escapeHtml(key)}">
					<input type="text" 
						   class="ghl-param-value" 
						   placeholder="Value (e.g., {user_email})" 
						   value="${this.escapeHtml(value)}">
					<button type="button" class="ghl-button ghl-button-danger ghl-remove-custom-param" title="Remove parameter">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			</div>
		`;
	},
	
	bindCustomParamEvents: function() {
		$('.ghl-remove-custom-param').off('click').on('click', function() {
			$(this).closest('.ghl-custom-param-row').remove();
		});
	},
	
	closeFormSettings: function() {
			$('.ghl-form-settings-panel').removeClass('is-open');
			setTimeout(() => {
				$('.ghl-form-settings-panel').remove();
			}, 300);
		},

		showSuccessToast: function(message) {
			const toast = $('<div class="ghl-toast ghl-toast-success">' +
				'<span class="dashicons dashicons-yes-alt"></span> ' +
				message +
				'</div>');
			
			$('body').append(toast);
			
			setTimeout(() => {
				toast.addClass('is-visible');
			}, 10);
			
			setTimeout(() => {
				toast.removeClass('is-visible');
				setTimeout(() => {
					toast.remove();
				}, 300);
			}, 2000);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		FormsManager.init();
	});

	// Export for potential external use
	window.GHLFormsManager = FormsManager;

})(jQuery);
