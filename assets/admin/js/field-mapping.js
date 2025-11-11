/**
 * Field Mapping Page JavaScript
 *
 * Handles field mapping form submission via AJAX
 *
 * @package    GHL_CRM_Integration
 * @subpackage Assets/Admin/JS
 */

(function ($, window) {
	'use strict';

	/**
	 * Initialize field mapping functionality
	 */
	function initFieldMapping() {
		const $customToggle = $('#ghl-toggle-custom-fields');
		const $customWrapper = $('#ghl-custom-fields-wrapper');

		if ($customToggle.length && $customWrapper.length) {
			const showLabel = $customToggle.data('label-show');
			const hideLabel = $customToggle.data('label-hide');
			const $labelSpan = $customToggle.find('.ghl-toggle-button__label');

			const setToggleState = (isOpen) => {
				$customToggle.attr('aria-expanded', isOpen ? 'true' : 'false');
				$customWrapper.toggleClass('ghl-is-collapsed', ! isOpen);

				if ($labelSpan.length) {
					$labelSpan.text(isOpen ? hideLabel : showLabel);
				}
			};

			// Ensure initial collapsed state matches aria attribute.
			setToggleState($customToggle.attr('aria-expanded') === 'true');

			$customToggle.on('click', function (event) {
				event.preventDefault();
				const currentlyOpen = $customToggle.attr('aria-expanded') === 'true';
				setToggleState(! currentlyOpen);
			});
		}

		/**
		 * Check for duplicate GHL field mappings and show warnings
		 */
		function checkDuplicateMappings() {
			// Get all selected GHL fields
			const selectedFields = {};
			
			$('select[name^="ghl_field_"]').each(function () {
				const $select = $(this);
				const selectedValue = $select.val();
				
				if (selectedValue !== '' && selectedValue !== undefined) {
					if (!selectedFields[selectedValue]) {
						selectedFields[selectedValue] = [];
					}
					selectedFields[selectedValue].push($select);
				}
			});

			// Clear all previous warnings
			$('.ghl-duplicate-warning').remove();
			$('select[name^="ghl_field_"]').css('border-color', '');

			// Check for duplicates and add warnings
			Object.keys(selectedFields).forEach(function(fieldValue) {
				const selects = selectedFields[fieldValue];
				
				if (selects.length > 1) {
					// Multiple WordPress fields are mapping to the same GHL field
					selects.forEach(function($select) {
						// Add yellow border to highlight the duplicate
						$select.css('border-color', '#f0b849');
						
						// Add warning message if not already present
						const $cell = $select.closest('td');
						if ($cell.find('.ghl-duplicate-warning').length === 0) {
							const fieldName = $select.find('option:selected').text();
							const warningHtml = '<div class="ghl-duplicate-warning" style="margin-top: 5px; padding: 5px 10px; background: #fff3cd; border-left: 3px solid #f0b849; font-size: 12px; color: #856404;">' +
								'<span style="font-weight: 600;">⚠ Duplicate Mapping:</span> This GHL field is mapped ' + selects.length + ' times. ' +
								'Last sync will overwrite earlier values.' +
								'</div>';
							$cell.append(warningHtml);
						}
					});
				}
			});
		}

		/**
		 * Show notification message using SweetAlert2
		 */
		function showNotice(message, type = 'success') {
			const icon = type === 'success' ? 'success' : 'error';
			const title = type === 'success' ? 'Success!' : 'Error';

			Swal.fire({
				icon: icon,
				title: title,
				text: message,
				toast: true,
				position: 'top-end',
				showConfirmButton: false,
				timer: type === 'success' ? 3000 : 5000,
				timerProgressBar: true,
				customClass: {
					popup: 'ghl-swal-top-toast',
				},
				didOpen: (toast) => {
					toast.addEventListener('mouseenter', Swal.stopTimer);
					toast.addEventListener('mouseleave', Swal.resumeTimer);
				}
			});
		}

		/**
		 * Gather field mapping data from form
		 */
		function gatherFieldMappings() {
			const mappings = {};

			// Loop through all GHL field selects
			$('select[name^="ghl_field_"]').each(function () {
				const $select = $(this);
				const fieldName = $select.attr('name').replace('ghl_field_', '');
				const ghlField = $select.val();
				
				// Only save if a GHL field is selected (not empty "Do Not Sync")
				if (ghlField !== '') {
					const directionSelect = $('select[name="sync_direction_' + fieldName + '"]');
					const direction = directionSelect.val() || 'both';

					mappings[fieldName] = {
						ghl_field: ghlField,
						direction: direction
					};
				}
			});

			return mappings;
		}

		/**
		 * Save Field Mapping via AJAX
		 */
		$('#ghl-field-mapping-form').on('submit', function (e) {
			e.preventDefault();

			const $form = $(this);
			const $button = $form.find('input[type="submit"]');
			const buttonOriginalValue = $button.val();

			// Gather field mappings
			const fieldMappings = gatherFieldMappings();

			// Prepare AJAX data
			const formData = {
				action: 'ghl_crm_save_field_mapping',
				nonce: ghl_crm_field_mapping_js_data.nonce,
				field_mappings: fieldMappings
			};

			// Disable button and show loading state
			$button.prop('disabled', true).val('Saving...');

			// Make AJAX request
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				success: function (response) {
					if (response.success) {
						const count = response.data.count || 0;
						const message = response.data.message + ' (' + count + ' fields mapped)';
						showNotice(message, 'success');
					} else {
						showNotice(response.data.message || 'Failed to save field mapping.', 'error');
					}
				},
				error: function (xhr) {
					console.error('AJAX Error:', xhr);
					const errorMsg = xhr.responseJSON?.data?.message || 'An error occurred while saving field mapping.';
					showNotice(errorMsg, 'error');
				},
				complete: function () {
					// Re-enable button
					$button.prop('disabled', false).val(buttonOriginalValue);
				}
			});
		});

		/**
		 * Show confirmation when many fields are being unmapped
		 */
		let initialMappingCount = 0;
		
		// Count initial mappings on page load
		$('select[name^="ghl_field_"]').each(function () {
			if ($(this).val() !== '') {
				initialMappingCount++;
			}
		});

		// Optional: Add visual feedback when changing mappings
		$('select[name^="ghl_field_"], select[name^="sync_direction_"]').on('change', function () {
			const $row = $(this).closest('tr');
			$row.addClass('ghl-field-changed');
			
			// Check for duplicate mappings when a GHL field is selected/changed
			if ($(this).attr('name').startsWith('ghl_field_')) {
				checkDuplicateMappings();
			}
			
			// Remove highlight after a moment
			setTimeout(function () {
				$row.removeClass('ghl-field-changed');
			}, 1000);
		});

		// Initialize duplicate check on page load
		checkDuplicateMappings();
	}

	// Export to global scope for SPA to call
	window.initFieldMapping = initFieldMapping;

	// Initialize on document ready (for non-SPA page loads)
	$(document).ready(initFieldMapping);

})(jQuery, window);

/**
 * Field Mapping - GHL Field Loading
 * 
 * Handles loading GoHighLevel custom fields dynamically
 */
(function($) {
	'use strict';

	// Expose globally so SPA router can call it
	window.GHL_FieldMapping = window.GHL_FieldMapping || {};

	/**
	 * Function to update row highlighting based on mapped status
	 */
	window.GHL_FieldMapping.updateMappedRows = function() {
		$('select[name^="ghl_field_"]').each(function() {
			const $select = $(this);
			const $row = $select.closest('tr');
			const selectedValue = $select.val();
			
			// Add/remove highlight class based on whether field is mapped
			if (selectedValue && selectedValue !== '' && selectedValue !== '—') {
				$row.addClass('ghl-mapped-field');
			} else {
				$row.removeClass('ghl-mapped-field');
			}
		});
	};
	
	/**
	 * Function to load GHL fields
	 */
	window.GHL_FieldMapping.loadFields = function(isInitialLoad) {
		const $button = $('#ghl-load-custom-fields');
		const $status = $('#ghl-custom-fields-status');
		const $icon = $button.find('.dashicons');
		
		// Check if elements exist (may not be on this tab)
		if ($button.length === 0) {
			return;
		}
		
		// Get nonce from data attribute or global
		const nonce = $button.data('nonce') || (window.ghl_crm_field_mapping_nonce || '');
		
		// Show loading state
		$button.prop('disabled', true);
		$icon.removeClass('dashicons-update').addClass('dashicons-update-alt').css('animation', 'rotation 2s infinite linear');
		
		if (!isInitialLoad) {
			$status.html('<span style="color: #999;"><span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear; margin-top: 3px;"></span> Loading fields...</span>');
		}
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_custom_fields',
				nonce: nonce
			},
			success: function(response) {
				$button.prop('disabled', false);
				$icon.removeClass('dashicons-update-alt').addClass('dashicons-update').css('animation', '');
				
				if (response.success && response.data.fields) {
					// Update all GHL field dropdowns
					const fields = response.data.fields;
					let fieldCount = Object.keys(fields).length;
					
					$('select[name^="ghl_field_"]').each(function() {
						const $select = $(this);
						// Get saved value from data attribute (set by PHP)
						let savedValue = $select.data('saved-value') || '';
						
						// Force email field to always be mapped to 'email'
						const isEmailField = $select.attr('name') === 'ghl_field_user_email';
						if (isEmailField) {
							savedValue = 'email';
						}
						
						// Clear existing options
						$select.empty();
						
						// Add all fields (including custom fields)
						$.each(fields, function(key, label) {
							const $option = $('<option></option>')
								.attr('value', key)
								.text(label);
							
							// Restore saved selection from data attribute
							if (key === savedValue) {
								$option.attr('selected', 'selected');
							}
							
							$select.append($option);
						});
						
						// Set the value explicitly to ensure it's selected
						if (savedValue) {
							$select.val(savedValue);
						}
						
						// Re-disable email field after populating
						if (isEmailField) {
							$select.prop('disabled', true);
						}
					});
					
					// Show success message
					const customCount = response.data.count || 0;
					let message = '✅ Loaded ' + fieldCount + ' fields';
					if (customCount > 0) {
						message += ' (including ' + customCount + ' custom fields)';
					}
					$status.html('<span style="color: #46b450;">' + message + '</span>');
					
					// Show notice at top only on manual reload
					// if (!isInitialLoad) {
					// 	$('#ghl-field-mapping-messages').html(
					// 		'<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>'
					// 	);
					// }
					
					setTimeout(function() {
						$status.fadeOut();
					}, 5000);
					
					// Update row highlighting for mapped fields
					window.GHL_FieldMapping.updateMappedRows();
					
				} else {
					$status.html('<span style="color: #dc3232;">⚠ Failed to load fields</span>');
					if (response.data && response.data.error) {
						console.error('GHL Field Load Error:', response.data.error);
					}
				}
			},
			error: function(xhr, status, error) {
				$button.prop('disabled', false);
				$icon.removeClass('dashicons-update-alt').addClass('dashicons-update').css('animation', '');
				$status.html('<span style="color: #dc3232;">⚠ Error: ' + error + '</span>');
			}
		});
	};
	
	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		// Auto-load fields on initial page load
		window.GHL_FieldMapping.loadFields(true);
		
		// Handle manual reload button click
		$(document).on('click', '#ghl-load-custom-fields', function() {
			window.GHL_FieldMapping.loadFields(false);
		});
		
		// Handle field mapping changes to update row highlighting
		$(document).on('change', 'select[name^="ghl_field_"]', function() {
			window.GHL_FieldMapping.updateMappedRows();
		});
	});
})(jQuery);
