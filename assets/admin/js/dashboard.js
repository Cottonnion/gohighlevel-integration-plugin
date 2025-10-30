/**
 * Dashboard JavaScript
 *
 * Handles dashboard tab switching, connection form submission, and disconnect functionality
 *
 * @package GHL_CRM_Integration
 */

(function($) {
	'use strict';

	/**
	 * Initialize dashboard functionality
	 */
	function initDashboard() {
		initTabSwitching();
		initManualConnectionForm();
		initDisconnectButton();
	}
	
	// Expose init function globally for SPA router
	window.initDashboard = initDashboard;

	/**
	 * Initialize tab switching functionality
	 */
	function initTabSwitching() {
		$('.ghl-tab-button').on('click', function() {
			var tabId = $(this).data('tab');
			
			// Update button states
			$('.ghl-tab-button').removeClass('active');
			$(this).addClass('active');
			
			// Update content visibility
			$('.ghl-tab-content').removeClass('active');
			$('#' + tabId + '-tab').addClass('active');
		});
	}

	/**
	 * Initialize manual connection form submission
	 */
	function initManualConnectionForm() {
		$('#ghl-manual-connection-form').on('submit', function(e) {
			e.preventDefault();
			
			var $form = $(this);
			var $submitBtn = $form.find('button[type="submit"]');
			var originalText = $submitBtn.html();
			
		// Disable button and show loading
		$submitBtn.prop('disabled', true).html(
			'<span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear; margin-top: 3px;"></span> ' + 
			ghl_crm_dashboard_js_data.i18n.connecting
		);			// Remove any existing notices
			$form.prev('.notice').remove();
			
			// Get values and ensure they're strings
			var apiToken = String($('#api_token').val() || '').trim();
			var locationId = String($('#location_id').val() || '').trim();
			var nonce = ghl_crm_dashboard_js_data.manualConnectNonce;
			
			// Debug: Log what we're sending
			console.log('Sending connection request:', {
				action: 'ghl_crm_manual_connect',
				nonce: nonce ? nonce.substring(0, 10) + '...' : 'MISSING',
				api_token: apiToken.substring(0, 15) + '...',
				location_id: locationId,
				ajaxurl: ajaxurl
			});
			
			// Build FormData to ensure proper encoding
			var formData = new FormData();
			formData.append('action', 'ghl_crm_manual_connect');
			formData.append('ghl_manual_connect_nonce', nonce);
			formData.append('api_token', apiToken);
			formData.append('location_id', locationId);
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						// Show success message
						$form.before(
							'<div class="notice notice-success is-dismissible"><p>' + 
							response.data.message + 
							'</p></div>'
						);
						
						// Reload page after 1 second
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						// Show error message
						var errorMsg = response.data && response.data.message 
							? response.data.message 
							: ghl_crm_dashboard_js_data.i18n.connectionFailed;
							
						$form.before(
							'<div class="notice notice-error is-dismissible"><p>' + 
							errorMsg + 
							'</p></div>'
						);
						
						$submitBtn.prop('disabled', false).html(originalText);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					console.error('AJAX Error:', {
						status: jqXHR.status,
						statusText: textStatus,
						responseText: jqXHR.responseText,
						error: errorThrown
					});
					
					var errorMsg = ghl_crm_dashboard_js_data.i18n.connectionError;
					
					// If response is -1, it's likely a nonce or action registration issue
					if (jqXHR.responseText === '-1' || jqXHR.responseText === '0') {
						errorMsg += ' (WordPress AJAX error: ' + jqXHR.responseText + ' - Check nonce or action registration)';
					}
					
					$form.before(
						'<div class="notice notice-error is-dismissible"><p>' + 
						errorMsg + 
						'</p></div>'
					);
					$submitBtn.prop('disabled', false).html(originalText);
				}
			});
		});
	}

	/**
	 * Initialize disconnect button functionality
	 */
	function initDisconnectButton() {
		// OAuth disconnect button
		$('#ghl-disconnect-btn').on('click', function(e) {
			e.preventDefault();
			
			var $btn = $(this);
			var originalText = $btn.text();
			
			// Show confirmation dialog
			Swal.fire({
				title: ghl_crm_dashboard_js_data.i18n.disconnectConfirm || 'Are you sure?',
				text: 'You will need to reconnect to use the integration features.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#dc3232',
				cancelButtonColor: '#2271b1',
				confirmButtonText: 'Yes, disconnect',
				cancelButtonText: 'Cancel'
			}).then((result) => {
				if (result.isConfirmed) {
					// Show loading state
					$btn.prop('disabled', true).text(ghl_crm_dashboard_js_data.i18n.disconnecting);

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'ghl_crm_oauth_disconnect',
							nonce: ghl_crm_dashboard_js_data.disconnectNonce
						},
						success: function(response) {
							if (response.success) {
								Swal.fire({
									icon: 'success',
									title: 'Disconnected!',
									text: response.data.message,
									timer: 1500,
									showConfirmButton: false
								}).then(() => {
									location.reload();
								});
							} else {
								var errorMsg = response.data && response.data.message 
									? response.data.message 
									: ghl_crm_dashboard_js_data.i18n.disconnectFailed;
								Swal.fire({
									icon: 'error',
									title: 'Disconnect Failed',
									text: errorMsg
								});
								$btn.prop('disabled', false).text(originalText);
							}
						},
						error: function() {
							Swal.fire({
								icon: 'error',
								title: 'Connection Error',
								text: ghl_crm_dashboard_js_data.i18n.disconnectError
							});
							$btn.prop('disabled', false).text(originalText);
						}
					});
				}
			});
		});

		// Manual API Key disconnect button
		$('#ghl-disconnect-api-btn').on('click', function(e) {
			e.preventDefault();
			
			var $btn = $(this);
			var originalText = $btn.html();
			
			// Show confirmation dialog
			Swal.fire({
				title: ghl_crm_dashboard_js_data.i18n.disconnectConfirm || 'Are you sure?',
				text: 'Your API credentials will be removed. You will need to reconnect to use the integration features.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#dc3232',
				cancelButtonColor: '#2271b1',
				confirmButtonText: 'Yes, disconnect',
				cancelButtonText: 'Cancel'
			}).then((result) => {
				if (result.isConfirmed) {
					// Show loading state
					$btn.prop('disabled', true).html(
						'<span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear;"></span> ' +
						ghl_crm_dashboard_js_data.i18n.disconnecting
					);

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'ghl_crm_disconnect_api',
							nonce: ghl_crm_dashboard_js_data.disconnectNonce
						},
						success: function(response) {
							if (response.success) {
								Swal.fire({
									icon: 'success',
									title: 'Disconnected!',
									text: response.data.message,
									timer: 1500,
									showConfirmButton: false
								}).then(() => {
									location.reload();
								});
							} else {
								var errorMsg = response.data && response.data.message 
									? response.data.message 
									: ghl_crm_dashboard_js_data.i18n.disconnectFailed;
								Swal.fire({
									icon: 'error',
									title: 'Disconnect Failed',
									text: errorMsg
								});
								$btn.prop('disabled', false).html(originalText);
							}
						},
						error: function() {
							Swal.fire({
								icon: 'error',
								title: 'Connection Error',
								text: ghl_crm_dashboard_js_data.i18n.disconnectError
							});
							$btn.prop('disabled', false).html(originalText);
						}
					});
				}
			});
		});
	}

	// Initialize on document ready
	$(document).ready(function() {
		initDashboard();
	});

})(jQuery);
