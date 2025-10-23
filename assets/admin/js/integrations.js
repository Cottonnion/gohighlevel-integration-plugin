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
		},

		/**
		 * Bind events
		 */
		bindEvents() {
			// Tab navigation
			$('.ghl-tab-button').on('click', this.switchTab.bind(this));

			// Save settings button
			$('#save-integrations-settings').on('click', this.saveSettings.bind(this));

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
		 * Gather form data
		 */
		gatherFormData() {
			const data = {
				action: 'ghl_crm_save_integrations',
				nonce: ghl_crm_integrations_data.nonce,
			};

			// Future: Add BuddyBoss, WooCommerce, LearnDash settings here
			// For now, just return basic structure

			return data;
		},

		/**
		 * Save settings via AJAX
		 */
		saveSettings() {
			const $button = $('#save-integrations-settings');
			const $buttonText = $button.find('.button-text');
			const $spinner = $button.find('.spinner');

			// Disable button and show spinner
			$button.prop('disabled', true).addClass('is-loading');
			$spinner.addClass('is-active');

			// Gather form data
			const formData = this.gatherFormData();

			// Make AJAX request
			$.ajax({
				url: ghl_crm_integrations_data.ajaxUrl,
				type: 'POST',
				data: formData,
				success: (response) => {
					if (response.success) {
						this.showMessage('success', response.data.message || ghl_crm_integrations_data.i18n.settingsSaved);
						
						// Scroll to top to show message
						$('html, body').animate({ scrollTop: 0 }, 300);
					} else {
						this.showMessage('error', response.data.message || ghl_crm_integrations_data.i18n.saveFailed);
						
						// Scroll to top to show error message
						$('html, body').animate({ scrollTop: 0 }, 300);
					}
				},
				error: (xhr, status, error) => {
					console.error('Save error:', error);
					console.error('XHR response:', xhr.responseText);
					
					let errorMessage = ghl_crm_integrations_data.i18n.saveError || 'An error occurred while saving settings.';
					
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
