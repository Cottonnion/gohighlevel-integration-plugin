/**
 * Field Mapping Page JavaScript
 *
 * Handles field mapping form submission via AJAX
 *
 * @package    GHL_CRM_Integration
 * @subpackage Assets/Admin/JS
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		/**
		 * Update GHL field dropdowns to show which fields are already mapped
		 */
		function updateGHLFieldAvailability() {
			// Get all selected GHL fields
			const selectedFields = {};
			
			$('select[name^="ghl_field_"]').each(function () {
				const $select = $(this);
				const selectedValue = $select.val();
				
				if (selectedValue !== '') {
					if (!selectedFields[selectedValue]) {
						selectedFields[selectedValue] = [];
					}
					selectedFields[selectedValue].push($select);
				}
			});

			// Update all dropdowns
			$('select[name^="ghl_field_"]').each(function () {
				const $currentSelect = $(this);
				const currentValue = $currentSelect.val();

				$currentSelect.find('option').each(function () {
					const $option = $(this);
					const optionValue = $option.val();

					// Skip empty option (Do Not Sync)
					if (optionValue === '') {
						return;
					}

					// Check if this field is already selected elsewhere
					if (selectedFields[optionValue] && selectedFields[optionValue].length > 0) {
						const isCurrentSelect = selectedFields[optionValue].some(function ($select) {
							return $select.is($currentSelect);
						});

						if (!isCurrentSelect) {
							// Field is selected elsewhere - disable it and add checkmark
							$option.prop('disabled', true);
							const originalText = $option.text().replace(' ✓ (mapped)', '');
							$option.text(originalText + ' ✓ (mapped)');
							$option.css('color', '#999');
						} else {
							// This is the current select - enable it
							$option.prop('disabled', false);
							const originalText = $option.text().replace(' ✓ (mapped)', '');
							$option.text(originalText);
							$option.css('color', '');
						}
					} else {
						// Field is not selected - enable it
						$option.prop('disabled', false);
						const originalText = $option.text().replace(' ✓ (mapped)', '');
						$option.text(originalText);
						$option.css('color', '');
					}
				});
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
			
			// Update GHL field availability when a field is selected/changed
			if ($(this).attr('name').startsWith('ghl_field_')) {
				updateGHLFieldAvailability();
			}
			
			// Remove highlight after a moment
			setTimeout(function () {
				$row.removeClass('ghl-field-changed');
			}, 1000);
		});

		// Initialize field availability on page load
		updateGHLFieldAvailability();
	});

})(jQuery);
