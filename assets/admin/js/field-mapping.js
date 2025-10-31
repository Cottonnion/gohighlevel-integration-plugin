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
