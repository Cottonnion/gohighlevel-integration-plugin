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
		 * Auto-Suggest Mappings Button
		 */
		$('#ghl-auto-suggest-mappings').on('click', function() {
			const $button = $(this);
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span> Analyzing...');

			// Collect unmapped WP fields (only those not explicitly saved)
			const wpFields = [];
			$('select[name^="ghl_field_"]').each(function() {
				const $select = $(this);
				const $row = $select.closest('tr.ghl-field-row');
				const wpField = $select.attr('name').replace('ghl_field_', '');
				const currentValue = $select.val();
				const isExplicitlySaved = $row.attr('data-explicitly-saved') === '1';
				
				// Only suggest for fields that are unmapped AND haven't been explicitly saved
				// This respects user's choice to set a field to "Do not sync"
				if ((!currentValue || currentValue === '') && !isExplicitlySaved) {
					wpFields.push(wpField);
				}
			});

			// Collect all available GHL fields
			const ghlFields = [];
			const $firstSelect = $('select[name^="ghl_field_"]').first();
			$firstSelect.find('option').each(function() {
				const value = $(this).val();
				if (value && value !== '') {
					ghlFields.push(value);
				}
			});

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ghl_crm_get_field_suggestions',
					nonce: ghl_crm_field_mapping_js_data.nonce,
					wp_fields: wpFields,
					ghl_fields: ghlFields
				},
				success: function(response) {
					if (response.success && response.data.suggestions) {
						showSuggestionModal(response.data.suggestions);
					} else {
						showNotice(response.data.message || 'No suggestions found', 'info');
					}
				},
				error: function() {
					showNotice('Failed to get field suggestions', 'error');
				},
				complete: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-lightbulb"></span> Auto-Suggest Mappings');
				}
			});
		});

		/**
		 * Show suggestion modal with SweetAlert2
		 */
		function showSuggestionModal(suggestions) {
			if (Object.keys(suggestions).length === 0) {
				showNotice('All fields are already mapped!', 'info');
				return;
			}

			let tableHtml = '<table style="width: 100%; border-collapse: collapse; text-align: left;">';
			tableHtml += '<thead><tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">';
			tableHtml += '<th style="padding: 12px; width: 40px;"><input type="checkbox" id="ghl-select-all-suggestions" checked style="cursor: pointer;"></th>';
			tableHtml += '<th style="padding: 12px; font-weight: 600;">WordPress Field</th>';
			tableHtml += '<th style="padding: 12px; font-weight: 600;">→</th>';
			tableHtml += '<th style="padding: 12px; font-weight: 600;">Suggested GHL Field</th>';
			tableHtml += '<th style="padding: 12px; font-weight: 600; text-align: center;">Confidence</th>';
			tableHtml += '</tr></thead><tbody>';

			let index = 0;
			$.each(suggestions, function(wpField, details) {
				const confidenceColor = details.confidence >= 90 ? '#10b981' : (details.confidence >= 70 ? '#f59e0b' : '#6b7280');
				tableHtml += '<tr style="border-bottom: 1px solid #f1f5f9;">';
				tableHtml += '<td style="padding: 12px; text-align: center;"><input type="checkbox" class="ghl-suggestion-checkbox" data-wp-field="' + escapeHtml(details.wp_field) + '" data-ghl-field="' + escapeHtml(details.ghl_field) + '" checked style="cursor: pointer;"></td>';
				tableHtml += '<td style="padding: 12px;"><code>' + escapeHtml(details.wp_field) + '</code></td>';
				tableHtml += '<td style="padding: 12px; text-align: center;">→</td>';
				tableHtml += '<td style="padding: 12px;"><strong>' + escapeHtml(details.ghl_field) + '</strong></td>';
				tableHtml += '<td style="padding: 12px; text-align: center;"><span style="display: inline-block; padding: 4px 12px; border-radius: 12px; background: ' + confidenceColor + '20; color: ' + confidenceColor + '; font-weight: 600;">' + details.confidence + '%</span></td>';
				tableHtml += '</tr>';
				index++;
			});

			tableHtml += '</tbody></table>';

			if (typeof Swal !== 'undefined') {
				Swal.fire({
					title: 'Field Mapping Suggestions',
					html: '<div style="max-height: 400px; overflow-y: auto;">' + tableHtml + '</div>' +
						'<p style="margin-top: 16px; color: #64748b; font-size: 14px;">Uncheck any suggestions you don\'t want to apply</p>',
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: 'Apply Selected',
					cancelButtonText: 'Cancel',
					confirmButtonColor: '#3b82f6',
					width: '800px',
					didOpen: () => {
						// Handle "select all" checkbox
						document.getElementById('ghl-select-all-suggestions')?.addEventListener('change', function() {
							const checkboxes = document.querySelectorAll('.ghl-suggestion-checkbox');
							checkboxes.forEach(cb => cb.checked = this.checked);
						});

						// Update "select all" when individual checkboxes change
						document.querySelectorAll('.ghl-suggestion-checkbox').forEach(cb => {
							cb.addEventListener('change', function() {
								const allCheckboxes = document.querySelectorAll('.ghl-suggestion-checkbox');
								const allChecked = Array.from(allCheckboxes).every(c => c.checked);
								const selectAll = document.getElementById('ghl-select-all-suggestions');
								if (selectAll) {
									selectAll.checked = allChecked;
								}
							});
						});
					}
				}).then((result) => {
					if (result.isConfirmed) {
						// Gather only checked suggestions
						const selectedSuggestions = {};
						document.querySelectorAll('.ghl-suggestion-checkbox:checked').forEach(cb => {
							const wpField = cb.getAttribute('data-wp-field');
							const ghlField = cb.getAttribute('data-ghl-field');
							selectedSuggestions[wpField] = {
								wp_field: wpField,
								ghl_field: ghlField
							};
						});
						applySuggestions(selectedSuggestions);
					}
				});
			}
		}

		/**
		 * Apply suggestions to the form
		 * Note: data-explicitly-saved will be updated after user saves the form
		 */
		function applySuggestions(suggestions) {
			let appliedCount = 0;
			
			$.each(suggestions, function(wpField, details) {
				const $select = $('select[name="ghl_field_' + wpField + '"]');
				if ($select.length && $select.val() === '') {
					$select.val(details.ghl_field);
					
					// Add visual feedback - green highlight on the row
					const $row = $select.closest('tr');
					$row.addClass('ghl-field-changed');
					
					// Remove highlight after animation
					setTimeout(function() {
						$row.removeClass('ghl-field-changed');
					}, 2000);
					
					appliedCount++;
				}
			});

			if (appliedCount > 0) {
				showNotice('Applied ' + appliedCount + ' field mapping suggestions. Click "Save Field Mappings" to save changes.', 'success');
				checkDuplicateMappings();
			} else {
				showNotice('No suggestions were applied (fields may already be mapped).', 'info');
			}
		}

		/**
		 * Escape HTML
		 */
		function escapeHtml(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, m => map[m]);
		}

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
		// const $status = $('#ghl-custom-fields-status');
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
			// Get admin bar height for proper positioning
			const adminBarHeight = $('#wpadminbar').outerHeight() || 0;
			
			// Show SweetAlert toast
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: 'info',
				title: 'Loading fields...',
				showConfirmButton: false,
				timer: 3000,
				timerProgressBar: true,
				customClass: {
					container: 'ghl-swal-with-adminbar'
				},
				didOpen: (toast) => {
					toast.style.marginTop = adminBarHeight + 'px';
				}
			});
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
					
					// Show success message via SweetAlert toast
					if (!isInitialLoad) {
						const adminBarHeight = $('#wpadminbar').outerHeight() || 0;
						const customCount = response.data.count || 0;
						let message = 'Loaded ' + fieldCount + ' fields';
						
						Swal.fire({
							toast: true,
							position: 'top-end',
							icon: 'success',
							title: message,
							showConfirmButton: false,
							timer: 3000,
							timerProgressBar: true,
							customClass: {
								container: 'ghl-swal-with-adminbar'
							},
							didOpen: (toast) => {
								toast.style.marginTop = adminBarHeight + 'px';
							}
						});
					}
					
					// Show success message
					const customCount = response.data.count || 0;
					let message = '✅ Loaded ' + fieldCount + ' fields';
					// if (customCount > 0) {
					// 	message += ' (including ' + customCount + ' custom fields)';
					// }
					// $status.html('<span style="color: #46b450;">' + message + '</span>');
					
					// Show notice at top only on manual reload
					// if (!isInitialLoad) {
					// 	$('#ghl-field-mapping-messages').html(
					// 		'<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>'
					// 	);
					// }
					
					// setTimeout(function() {
					// 	$status.fadeOut();
					// }, 5000);
					
					// Update row highlighting for mapped fields
					window.GHL_FieldMapping.updateMappedRows();
					
				} else {
					// $status.html('<span style="color: #dc3232;">⚠ Failed to load fields</span>');
					if (response.data && response.data.error) {
						console.error('GHL Field Load Error:', response.data.error);
					}
				}
			},
			error: function(xhr, status, error) {
				$button.prop('disabled', false);
				$icon.removeClass('dashicons-update-alt').addClass('dashicons-update').css('animation', '');
				// $status.html('<span style="color: #dc3232;">⚠ Error: ' + error + '</span>');
			}
		});
	};
	
	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		// Add data-label attributes for mobile responsiveness
		$('.ghl-crm-field-mapping .form-table tbody tr').each(function() {
			const $row = $(this);
			const $cells = $row.find('td');
			
			// Add labels based on column index
			$cells.eq(0).attr('data-label', 'WordPress Field');
			$cells.eq(1).attr('data-label', 'GoHighLevel Field');
			$cells.eq(2).attr('data-label', 'Sync Direction');
		});
		
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
