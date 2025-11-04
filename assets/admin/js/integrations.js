/**
 * Integrations Page JavaScript
 *
 * @package    GHL_CRM_Integration
 * @subpackage Assets/Admin/JS
 */

(function ($) {
	'use strict';

	/**
	 * Integrations Manager
	 */
	const IntegrationsManager = {
		/**
		 * Initialize
		 */
		init() {
			this.bindEvents();
			this.loadSettings();
			this.initTagsSelect();
			this.initOrderStatusSelect();
		},

		/**
		 * Bind events
		 */
		bindEvents() {
			// Tab navigation
			$('.ghl-tab-button').on('click', this.switchTab.bind(this));

			// Save settings button
			$('#save-integrations-settings').on('click', this.saveSettings.bind(this));

			// WooCommerce toggles
			$('#wc_enabled').on('change', this.handleWooCommerceToggle.bind(this));
			$('#wc_convert_lead_enabled').on('change', this.handleConvertLeadToggle.bind(this));
			$('#wc_abandoned_cart_enabled').on('change', this.handleAbandonedCartToggle.bind(this));

			// Prevent form submission on enter
			$(document).on('keypress', function (e) {
				if (e.which === 13 && $(e.target).closest('.ghl-tab-panel').length) {
					e.preventDefault();
					return false;
				}
			});
		},

		/**
		 * Switch between tabs
		 */
		switchTab(e) {
			const $button = $(e.currentTarget);
			
			// Don't switch if disabled
			if ($button.prop('disabled')) {
				return;
			}

			const tabName = $button.data('tab');

			// Update active states
			$('.ghl-tab-button').removeClass('active');
			$button.addClass('active');

			$('.ghl-tab-panel').removeClass('active');
			$(`.ghl-tab-panel[data-tab="${tabName}"]`).addClass('active');
		},

		/**
		 * Load current settings
		 */
		loadSettings() {
			// Settings are already loaded in PHP template
			// This method is kept for potential AJAX reload in future
			console.log('Integrations settings loaded');
		},

		/**
		 * Initialize tags Select2 dropdowns
		 */
		initTagsSelect() {
			const $tagsSelects = $('.ghl-tags-select');

			if ($tagsSelects.length === 0 || typeof $.fn.select2 === 'undefined') {
				return;
			}

			// Initialize each Select2 dropdown
			$tagsSelects.each(function() {
				const $select = $(this);
				
				// Initialize Select2 with AJAX
				$select.select2({
					placeholder: $select.data('placeholder') || 'Select tags...',
					allowClear: true,
					width: '100%',
					closeOnSelect: false,
					ajax: {
						url: ghl_crm_integrations_js_data.ajaxUrl,
						type: 'POST',
						dataType: 'json',
						delay: 250,
						data: function(params) {
							return {
								action: 'ghl_crm_get_tags',
								nonce: ghl_crm_integrations_js_data.nonce,
								search: params.term || ''
							};
						},
						processResults: function(response) {
							if (response.success && response.data && response.data.tags) {
								return {
									results: response.data.tags.map(function(tag) {
										// Handle both object format {id, name} and string format
										if (typeof tag === 'object' && tag !== null) {
											return {
												id: String(tag.name || tag.id || ''),
												text: String(tag.name || tag.id || '')
											};
										}
										// Fallback for string format
										return {
											id: String(tag || ''),
											text: String(tag || '')
										};
									})
								};
							}
							return { results: [] };
						},
						cache: true
					},
					minimumInputLength: 0
				});

				// Pre-populate with saved tags from data attribute
				const savedTags = $select.data('saved-tags') || [];
				if (Array.isArray(savedTags) && savedTags.length > 0) {
					savedTags.forEach(function(tag) {
						// Create option if it doesn't exist
						if ($select.find("option[value='" + tag + "']").length === 0) {
							const newOption = new Option(tag, tag, true, true);
							$select.append(newOption);
						}
					});
					$select.val(savedTags).trigger('change');
				}
			});
		},

		/**
		 * Initialize Order Status Select2
		 */
		initOrderStatusSelect() {
			const $orderStatusSelect = $('.ghl-order-status-select');

			if ($orderStatusSelect.length === 0 || typeof $.fn.select2 === 'undefined') {
				return;
			}

			// Initialize Select2 for order status (no AJAX needed, options already in HTML)
			$orderStatusSelect.each(function() {
				const $select = $(this);
				
				$select.select2({
					placeholder: $select.data('placeholder') || 'Leave empty to convert on any order...',
					allowClear: true,
					width: '100%',
					closeOnSelect: false
				});
			});
		},

		/**
		 * Gather form data
		 */
		gatherFormData() {
			const data = {
				action: 'ghl_crm_save_integrations',
				nonce: ghl_crm_integrations_js_data.nonce,
			};

			// WooCommerce Settings
			if ($('#wc_enabled').length) {
				data.wc_enabled = $('#wc_enabled').is(':checked') ? '1' : '0';
				data.wc_convert_lead_enabled = $('#wc_convert_lead_enabled').is(':checked') ? '1' : '0';
				
				// Get customer tags (Select2 returns array)
				const customerTags = $('#wc_customer_tag').val();
				data.wc_customer_tag = Array.isArray(customerTags) ? customerTags : (customerTags ? [customerTags] : []);
				
				// Get order statuses for conversion (Select2 returns array)
				const orderStatuses = $('#wc_convert_order_statuses').val();
				data.wc_convert_order_statuses = Array.isArray(orderStatuses) ? orderStatuses : (orderStatuses ? [orderStatuses] : []);
				
				data.wc_abandoned_cart_enabled = $('#wc_abandoned_cart_enabled').is(':checked') ? '1' : '0';
				data.wc_abandoned_cart_time = $('#wc_abandoned_cart_time').val();
				
				// Get abandoned cart tags (Select2 returns array)
				const abandonedTags = $('#wc_abandoned_cart_tag').val();
				data.wc_abandoned_cart_tag = Array.isArray(abandonedTags) ? abandonedTags : (abandonedTags ? [abandonedTags] : []);
			}

			// Future: Add BuddyBoss, LearnDash settings here

			return data;
		},

		/**
		 * Save settings via AJAX
		 */
		saveSettings() {
			const $button = $('#save-integrations-settings');
			const $buttonText = $button.find('.button-text');
			const $spinner = $button.find('.spinner');

			// Store original button text if not already stored
			if (!$buttonText.data('original-text')) {
				$buttonText.data('original-text', $buttonText.text());
			}

			// Disable button and show spinner
			$button.prop('disabled', true).addClass('is-loading');
			$spinner.addClass('is-active');

			// Gather form data
			const formData = this.gatherFormData();

			// Make AJAX request
			$.ajax({
				url: ghl_crm_integrations_js_data.ajaxUrl,
				type: 'POST',
				data: formData,
				success: (response) => {
					if (response.success) {
						this.showMessage('success', response.data.message || ghl_crm_integrations_js_data.i18n.settingsSaved);
						
						// Add visual feedback to save button
						$buttonText.html('<span class="dashicons dashicons-yes-alt" style="font-size: 14px; margin-right: 4px;"></span>Saved Successfully!');
						
						// Reset button text after 3 seconds
						setTimeout(() => {
							$buttonText.text($buttonText.data('original-text') || 'Save Integration Settings');
						}, 3000);
						
						// Scroll to top to show message
						$('html, body').animate({ scrollTop: 0 }, 300);
					} else {
						this.showMessage('error', response.data.message || ghl_crm_integrations_js_data.i18n.saveFailed);
						
						// Scroll to top to show error message
						$('html, body').animate({ scrollTop: 0 }, 300);
					}
				},
				error: (xhr, status, error) => {
					console.error('Save error:', error);
					console.error('XHR response:', xhr.responseText);
					
					let errorMessage = ghl_crm_integrations_js_data.i18n.saveError || 'An error occurred while saving settings.';
					
					// Try to parse JSON response for error message
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMessage = xhr.responseJSON.data.message;
					} else if (xhr.responseText) {
						try {
							const parsed = JSON.parse(xhr.responseText);
							if (parsed.data && parsed.data.message) {
								errorMessage = parsed.data.message;
							}
						} catch (e) {
							// If parsing fails, use default error message
						}
					}
					
					this.showMessage('error', errorMessage);
					
					// Scroll to top to show error message
					$('html, body').animate({ scrollTop: 0 }, 300);
				},
				complete: () => {
					// Re-enable button and hide spinner
					$button.prop('disabled', false).removeClass('is-loading');
					$spinner.removeClass('is-active');
				},
			});
		},

		/**
		 * Handle WooCommerce main toggle
		 */
		handleWooCommerceToggle(e) {
			const $checkbox = $(e.target);
			const $label = $checkbox.closest('.ghl-checkbox');
			const $checkboxInput = $label.find('.ghl-checkbox-input');
			const $checkboxLabel = $label.find('.ghl-checkbox-label');
			const $settingsBody = $('#wc-settings-body');
			
			if ($checkbox.is(':checked')) {
				$label.addClass('is-checked');
				$checkboxInput.addClass('is-checked');
				$checkboxLabel.text('Enabled');
				$settingsBody.slideDown(300);
				
				// Show success feedback
				this.showInlineFeedback($checkbox, 'WooCommerce integration enabled', 'success');
			} else {
				$label.removeClass('is-checked');
				$checkboxInput.removeClass('is-checked');
				$checkboxLabel.text('Disabled');
				$settingsBody.slideUp(300);
				
				// Show info feedback
				this.showInlineFeedback($checkbox, 'WooCommerce integration disabled', 'info');
			}
		},

		/**
		 * Handle convert lead toggle
		 */
		handleConvertLeadToggle(e) {
			const $checkbox = $(e.target);
			const $label = $checkbox.closest('.ghl-checkbox');
			const $checkboxInput = $label.find('.ghl-checkbox-input');
			const $tagField = $('#wc-customer-tag-field');
			const $statusField = $('#wc-convert-order-status-field');
			
			if ($checkbox.is(':checked')) {
				$label.addClass('is-checked');
				$checkboxInput.addClass('is-checked');
				$tagField.slideDown(300);
				$statusField.slideDown(300);
				
				// Show success feedback
				this.showInlineFeedback($checkbox, 'Lead-to-customer conversion enabled', 'success');
			} else {
				$label.removeClass('is-checked');
				$checkboxInput.removeClass('is-checked');
				$tagField.slideUp(300);
				$statusField.slideUp(300);
				
				// Show info feedback
				this.showInlineFeedback($checkbox, 'Lead-to-customer conversion disabled', 'info');
			}
		},

		/**
		 * Handle abandoned cart toggle
		 */
		handleAbandonedCartToggle(e) {
			const $checkbox = $(e.target);
			const $label = $checkbox.closest('.ghl-checkbox');
			const $checkboxInput = $label.find('.ghl-checkbox-input');
			const $settings = $('#wc-abandoned-cart-settings');
			
			if ($checkbox.is(':checked')) {
				$label.addClass('is-checked');
				$checkboxInput.addClass('is-checked');
				$settings.slideDown(300);
				
				// Show success feedback
				this.showInlineFeedback($checkbox, 'Abandoned cart tracking enabled', 'success');
			} else {
				$label.removeClass('is-checked');
				$checkboxInput.removeClass('is-checked');
				$settings.slideUp(300);
				
				// Show info feedback
				this.showInlineFeedback($checkbox, 'Abandoned cart tracking disabled', 'info');
			}
		},

		/**
		 * Show inline feedback near an element
		 */
		showInlineFeedback($element, message, type = 'success') {
			// Remove any existing feedback
			$element.closest('.ghl-form-item').find('.ghl-inline-feedback').remove();
			
			// Determine icon and color
			let icon = 'yes-alt';
			let color = '#22c55e';
			
			if (type === 'info') {
				icon = 'info';
				color = '#3b82f6';
			} else if (type === 'error') {
				icon = 'dismiss';
				color = '#ef4444';
			}
			
			// Create feedback element
			const $feedback = $(`
				<span class="ghl-inline-feedback" style="
					display: inline-flex;
					align-items: center;
					gap: 6px;
					margin-left: 12px;
					color: ${color};
					font-size: 13px;
					font-weight: 500;
					animation: fadeInSlide 0.3s ease-out;
				">
					<span class="dashicons dashicons-${icon}" style="font-size: 16px;"></span>
					${message}
				</span>
			`);
			
			// Add animation styles if not already present
			if (!$('#ghl-inline-feedback-animation').length) {
				$('<style id="ghl-inline-feedback-animation">@keyframes fadeInSlide { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }</style>').appendTo('head');
			}
			
			// Insert feedback
			$element.closest('.ghl-checkbox-label').after($feedback);
			
			// Auto-remove after 3 seconds
			setTimeout(() => {
				$feedback.fadeOut(300, function() {
					$(this).remove();
				});
			}, 3000);
		},

		/**
		 * Show message
		 */
		showMessage(type, message) {
			const $container = $('#ghl-integrations-messages');
			const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';

			const html = `
				<div class="notice ${noticeClass} is-dismissible">
					<p>${message}</p>
				</div>
			`;

			// Remove existing messages
			$container.empty();

			// Add new message
			$container.html(html);

			// Auto-dismiss after 5 seconds
			setTimeout(() => {
				$container.find('.notice').fadeOut(300, function () {
					$(this).remove();
				});
			}, 5000);

			// Handle dismiss button click
			$container.find('.notice-dismiss').on('click', function () {
				$(this).closest('.notice').fadeOut(300, function () {
					$(this).remove();
				});
			});
		},
	};

	/**
	 * Initialize integrations functionality
	 */
	function initIntegrations() {
		IntegrationsManager.init();
	}

	// Export to global scope for SPA to call
	window.initIntegrations = initIntegrations;

	// Initialize on document ready (for non-SPA page loads)
	$(document).ready(initIntegrations);

})(jQuery);
