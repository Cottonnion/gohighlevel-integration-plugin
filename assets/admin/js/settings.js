/* Settings page JavaScript */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Test API Connection
		$('#ghl-test-connection').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const apiToken = $('#ghl_crm_api_token').val().trim();
			const locationId = $('#ghl_crm_location_id').val().trim();
			
			// Validate fields
			if (!apiToken || !locationId) {
				Swal.fire({
					icon: 'warning',
					title: 'Required Fields',
					text: 'Please enter both API Token and Location ID before testing.',
					confirmButtonColor: '#2271b1'
				});
				return;
			}
			
			// Show loading state
			Swal.fire({
				title: 'Testing Connection',
				html: 'Connecting to GoHighLevel API...',
				allowOutsideClick: false,
				allowEscapeKey: false,
				showConfirmButton: false,
				willOpen: () => {
					Swal.showLoading();
				}
			});
			
			// Make AJAX request
			$.ajax({
				url: ghl_crm_settings_js_data.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ghl_crm_test_connection',
					nonce: ghl_crm_settings_js_data.nonce,
					api_token: apiToken,
					location_id: locationId
				},
				success: function(response) {
					if (response.success) {
						Swal.fire({
							icon: 'success',
							title: 'Connection Successful!',
							html: response.data.details || 'Your GoHighLevel API credentials are valid.',
							confirmButtonColor: '#2271b1'
						});
					} else {
						Swal.fire({
							icon: 'error',
							title: 'Connection Failed',
							text: response.data.message || 'Unable to connect to GoHighLevel API.',
							confirmButtonColor: '#2271b1'
						});
					}
				},
				error: function(xhr, status, error) {
					Swal.fire({
						icon: 'error',
						title: 'Request Failed',
						text: 'An error occurred while testing the connection. Please try again.',
						footer: 'Error: ' + error,
						confirmButtonColor: '#2271b1'
					});
				}
			});
		});

		// Form validation before submit
		$('.ghl-crm-form').on('submit', function(e) {
			const apiToken = $('#ghl_crm_api_token').val().trim();
			const locationId = $('#ghl_crm_location_id').val().trim();

			if (!apiToken || !locationId) {
				e.preventDefault();
				Swal.fire({
					icon: 'warning',
					title: 'Required Fields',
					text: 'Please fill in both API Token and Location ID.',
					confirmButtonColor: '#2271b1'
				});
				return false;
			}
		});
	});

})(jQuery);
