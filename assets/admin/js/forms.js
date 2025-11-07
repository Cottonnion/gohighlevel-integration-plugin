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
						<td class="ghl-table-col-name">
							<strong>${this.escapeHtml(form.name || strings.untitledForm || 'Untitled Form')}</strong>
						</td>
						<td class="ghl-table-col-id">
							<code>${this.escapeHtml(form.id)}</code>
						</td>
						<td class="ghl-table-col-submissions">
							${submissionsText}
						</td>
						<td class="ghl-table-col-shortcode">
							<div class="ghl-shortcode-box" title="${strings.clickToCopy || 'Click to copy'}">
								[ghl_form id="${form.id}"]
							</div>
						</td>
						<td class="ghl-table-col-actions">
							<div class="ghl-table-actions">
								<button type="button" class="ghl-button ghl-button-secondary ghl-button-small ghl-copy-shortcode" data-shortcode="[ghl_form id=&quot;${form.id}&quot;]">
									<span class="dashicons dashicons-clipboard"></span>
									${strings.copy || 'Copy'}
								</button>
								${form.widgetUrl ? `
									<a href="${this.escapeHtml(form.widgetUrl)}" target="_blank" rel="noopener noreferrer" class="ghl-button ghl-button-secondary ghl-button-small">
										<span class="dashicons dashicons-visibility"></span>
										${strings.preview || 'Preview'}
									</a>
								` : ''}
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
				console.error('Failed to copy:', err);
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
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		FormsManager.init();
	});

	// Export for potential external use
	window.GHLFormsManager = FormsManager;

})(jQuery);
