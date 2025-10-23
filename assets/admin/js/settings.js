/**
 * Settings Page JavaScript
 *
 * Handles settings page interactions via AJAX
 *
 * @package GHL_CRM_Integration
 */

(function ($, window) {
	'use strict';

	/**
	 * Initialize settings functionality
	 */
	function initSettings() {
		/**
		 * Show notification message
		 */
		function showNotice(message, type = 'success') {
			const $notice = $('#ghl-settings-notice');
			const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
			
			$notice
				.removeClass('notice-success notice-error')
				.addClass('notice ' + noticeClass)
				.html('<p>' + message + '</p>')
				.slideDown();

			// Auto-hide after 5 seconds
			setTimeout(function () {
				$notice.slideUp();
			}, 5000);
		}

		/**
		 * Save Settings via AJAX
		 */
		$('#ghl-crm-settings-form').on('submit', function (e) {
			e.preventDefault();

			const $form = $(this);
			const $button = $('#ghl-save-settings');
			const $buttonText = $button.find('.button-text');
			const $spinner = $button.find('.spinner');

			// Get form data
			const formData = {
				action: 'ghl_crm_save_settings',
				nonce: $('#ghl_crm_nonce').val(),
				api_token: $('#ghl_crm_api_token').val(),
				location_id: $('#ghl_crm_location_id').val(),
				api_version: $('#ghl_crm_api_version').val(),
			};

			// Disable button and show loading state
			$button.prop('disabled', true);
			$buttonText.text('Saving...');
			$spinner.css('display', 'inline-block').addClass('is-active');

			// Make AJAX request
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				success: function (response) {
					if (response.success) {
						showNotice(response.data.message, 'success');
					} else {
						showNotice(response.data.message || 'Failed to save settings.', 'error');
					}
				},
				error: function (xhr) {
					const errorMsg = xhr.responseJSON?.data?.message || 'An error occurred while saving settings.';
					showNotice(errorMsg, 'error');
				},
				complete: function () {
					$button.prop('disabled', false);
					$buttonText.text('Save Settings');
					$spinner.hide().removeClass('is-active');
				},
			});
		});

		/**
		 * Test Connection via AJAX
		 */
		$('#ghl-test-connection').on('click', function () {
			const $button = $(this);
			const $result = $('#ghl-test-result');

			// Disable button and show loading state
			$button.prop('disabled', true).text('Testing...');
			$result.html('<div class="notice notice-info"><p>Testing connection...</p></div>');

			// Make AJAX request
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ghl_crm_test_connection',
					nonce: $('#ghl_crm_nonce').val(),
				},
				success: function (response) {
					if (response.success) {
						let message = '<strong>✓ ' + response.data.message + '</strong>';
						if (response.data.location_name) {
							message += '<br>Location: ' + response.data.location_name;
						}
						$result.html('<div class="notice notice-success"><p>' + message + '</p></div>');
					} else {
						let message = '<strong>✗ ' + response.data.message + '</strong>';
						if (response.data.details) {
							message += '<br><small>Details: ' + JSON.stringify(response.data.details) + '</small>';
						}
						$result.html('<div class="notice notice-error"><p>' + message + '</p></div>');
					}
				},
				error: function (xhr) {
					const errorMsg = xhr.responseJSON?.data?.message || 'Connection test failed.';
					$result.html('<div class="notice notice-error"><p><strong>✗ ' + errorMsg + '</strong></p></div>');
				},
				complete: function () {
					$button.prop('disabled', false).text('Test API Connection');
				},
			});
		});
	}

	// Export to global scope for SPA to call
	window.initSettings = initSettings;

	// Initialize on document ready (for non-SPA page loads)
	$(document).ready(initSettings);

})(jQuery, window);
