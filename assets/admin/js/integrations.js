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

			// Update toggle label when master toggle changes
			$('#enable_user_sync').on('change', this.updateToggleLabel.bind(this));

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
		 * Update toggle label text
		 */
		updateToggleLabel(e) {
			const $toggle = $(e.currentTarget);
			const $label = $toggle.closest('.ghl-card-header-right').find('.ghl-toggle-label');
			
			if ($toggle.is(':checked')) {
				$label.text(ghlCrmAdmin.i18n.enabled || 'Enabled');
			} else {
				$label.text(ghlCrmAdmin.i18n.disabled || 'Disabled');
			}
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
				action: 'ghl_crm_save_settings',
				nonce: ghl_crm_integrations_data.nonce,
			};

			// Get API settings from main settings (preserve them)
			// We'll fetch these from hidden fields or existing settings

			// User sync settings
			data.enable_user_sync = $('#enable_user_sync').is(':checked');
			
			// Get selected sync actions
			const syncActions = [];
			$('input[name="user_sync_actions[]"]:checked').each(function () {
				syncActions.push($(this).val());
			});
			data.user_sync_actions = syncActions;

			// Delete behavior
			data.delete_contact_on_user_delete = $('#delete_contact_on_user_delete').is(':checked');

			return data;
		},

		/**
		 * Save settings via AJAX
		 */
		saveSettings() {
			const $button = $('#save-integrations-settings');
			const $buttonText = $button.find('.button-text');
			const $spinner = $button.find('.spinner');

			// Validate at least one action is selected if sync is enabled
			if ($('#enable_user_sync').is(':checked')) {
				const checkedActions = $('input[name="user_sync_actions[]"]:checked').length;
				if (checkedActions === 0) {
					this.showMessage('error', ghl_crm_integrations_data.i18n.selectAtLeastOneAction || 'Please select at least one sync action.');
					return;
				}
			}

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

	// Initialize on document ready
	$(document).ready(function () {
		IntegrationsManager.init();
	});

})(jQuery);
