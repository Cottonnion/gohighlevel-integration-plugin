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
		hydrateFieldMappingData();

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
			// Get all selected GHL fields from hidden inputs
			const selectedFields = {};
			
			$('input[type="hidden"][name^="ghl_field_"]').each(function () {
				const $input = $(this);
				const selectedValue = $input.val();
				
				if (selectedValue !== '' && selectedValue !== undefined && selectedValue !== null) {
					if (!selectedFields[selectedValue]) {
						selectedFields[selectedValue] = [];
					}
					selectedFields[selectedValue].push($input);
				}
			});

			// Clear all previous warnings and border highlights
			$('.ghl-duplicate-warning').remove();
			$('.ghl-lazy-select').css('border-color', '');

			// Check for duplicates and add warnings
			Object.keys(selectedFields).forEach(function(fieldValue) {
				const inputs = selectedFields[fieldValue];
				
				if (inputs.length > 1) {
					inputs.forEach(function($input) {
						// Add yellow border to the lazy-select trigger
						$input.siblings('.ghl-lazy-select').css('border-color', '#f0b849');
						
						// Add warning message if not already present
						const $cell = $input.closest('td');
						if ($cell.find('.ghl-duplicate-warning').length === 0) {
							const fieldLabel = $input.siblings('.ghl-lazy-select').find('.ghl-lazy-select__text').text();
							const warningHtml = '<div class="ghl-duplicate-warning" style="margin-top: 5px; padding: 5px 10px; background: #fff3cd; border-left: 3px solid #f0b849; font-size: 12px; color: #856404;">' +
								'<span style="font-weight: 600;">⚠ Duplicate Mapping:</span> This GHL field is mapped ' + inputs.length + ' times. ' +
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

			// Loop through all GHL field hidden inputs
			$('input[type="hidden"][name^="ghl_field_"]').each(function () {
				const $input = $(this);
				const fieldName = $input.attr('name').replace('ghl_field_', '');
				const ghlField = $input.val();
				
				// Only save if a GHL field is selected (not empty "Do Not Sync")
				if (ghlField && ghlField !== '') {
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
			$('input[type="hidden"][name^="ghl_field_"]').each(function() {
				const $input = $(this);
				const $row = $input.closest('tr.ghl-field-row');
				const wpField = $input.attr('name').replace('ghl_field_', '');
				const currentValue = $input.val();
				const isExplicitlySaved = $row.attr('data-explicitly-saved') === '1';
				
				// Only suggest for fields that are unmapped AND haven't been explicitly saved
				if ((!currentValue || currentValue === '') && !isExplicitlySaved) {
					wpFields.push(wpField);
				}
			});

			// Collect all available GHL fields from global JSON
			const ghlFields = Object.keys(window.GHL_FIELDS || {}).filter(function(k) { return k !== ''; });

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
				const $input = $('input[type="hidden"][name="ghl_field_' + wpField + '"]');
				if ($input.length && (!$input.val() || $input.val() === '')) {
					// Update hidden input value
					$input.val(details.ghl_field).trigger('change');
					
					// Update the lazy-select display text
					const $lazySelect = $input.siblings('.ghl-lazy-select');
					if ($lazySelect.length) {
						$lazySelect.attr('data-value', details.ghl_field);
						const label = (window.GHL_FIELDS || {})[details.ghl_field] || details.ghl_field;
						$lazySelect.find('.ghl-lazy-select__text').text(label);
					}
					
					// Add visual feedback - green highlight on the row
					const $row = $input.closest('tr');
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
		$('input[type="hidden"][name^="ghl_field_"]').each(function () {
			if ($(this).val() !== '') {
				initialMappingCount++;
			}
		});

		// Visual feedback when changing mappings (listen on hidden inputs + sync direction selects)
		$(document).on('change', 'input[name^="ghl_field_"], select[name^="sync_direction_"]', function () {
			const $row = $(this).closest('tr');
			$row.addClass('ghl-field-changed');
			
			// Check for duplicate mappings when a GHL field is changed
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

		if (window.GHL_FieldMapping && typeof window.GHL_FieldMapping.bindLazyDropdowns === 'function') {
			window.GHL_FieldMapping.bindLazyDropdowns();
		}
	}

	function hydrateFieldMappingData() {
		const dataElement = document.getElementById('ghl-field-mapping-data');

		if (!dataElement) {
			return;
		}

		try {
			const data = JSON.parse(dataElement.textContent || '{}');

			if (data.fields && typeof data.fields === 'object') {
				window.GHL_FIELDS = data.fields;
			}

			if (data.savedMappings && typeof data.savedMappings === 'object') {
				window.GHL_SAVED_MAPPINGS = data.savedMappings;
			}
		} catch (error) {
			console.error('Failed to parse field mapping data:', error);
		}
	}

	// Export to global scope for SPA to call
	window.initFieldMapping = initFieldMapping;

	// Initialize on document ready (for non-SPA page loads)
	$(document).ready(initFieldMapping);

})(jQuery, window);

/**
 * Field Mapping - Lazy Dropdown System
 *
 * Instead of rendering hundreds of <select> elements (one per row) each
 * containing all GHL fields, we render a lightweight <div> trigger per row.
 * The full searchable dropdown is built from window.GHL_FIELDS only when
 * the user clicks a trigger — one dropdown at a time.
 */
(function($) {
	'use strict';

	var activeDropdown = null;

	/* -------------------------------------------------- helpers */

	function getDisplayText(value) {
		if (!value) return '— Do Not Sync —';
		var fields = window.GHL_FIELDS || {};
		return fields[value] || value;
	}

	function escapeHtml(text) {
		var map = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'};
		return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	/* -------------------------------------------------- open / close */

	function openDropdown($trigger) {
		closeDropdown();

		var currentValue = $trigger.attr('data-value') || '';
		var fields = window.GHL_FIELDS || {};

		// Build dropdown DOM
		var $dropdown = $('<div class="ghl-lazy-dropdown"></div>');
		var $search   = $('<input type="text" class="ghl-lazy-dropdown__search" placeholder="Search fields…" autocomplete="off">');
		var $list     = $('<div class="ghl-lazy-dropdown__list"></div>');

		// "Do Not Sync" option
		$list.append(
			'<div class="ghl-lazy-dropdown__option' + (!currentValue ? ' ghl-lazy-dropdown__option--selected' : '') +
			'" data-value=""><em>— Do Not Sync —</em></div>'
		);

		// All GHL fields
		var keys = Object.keys(fields);
		for (var i = 0; i < keys.length; i++) {
			var key = keys[i];
			if (key === '') continue;
			var cls = (key === currentValue) ? ' ghl-lazy-dropdown__option--selected' : '';
			$list.append(
				'<div class="ghl-lazy-dropdown__option' + cls + '" data-value="' + escapeHtml(key) + '">' +
				escapeHtml(fields[key]) + '</div>'
			);
		}

		$dropdown.append($search).append($list);

		// Append dropdown to body so it is never clipped by overflow:hidden ancestors
		$(document.body).append($dropdown);
		$trigger.addClass('ghl-lazy-select--open');

		activeDropdown = { $trigger: $trigger, $dropdown: $dropdown };

		// Position the fixed dropdown relative to the trigger
		positionDropdown();

		// Focus search input
		$search[0].focus();

		// Scroll to currently selected option
		var $selected = $list.find('.ghl-lazy-dropdown__option--selected');
		if ($selected.length) {
			$list[0].scrollTop = $selected[0].offsetTop - $list[0].offsetHeight / 2;
		}

		// Live search filtering
		$search.on('input', function() {
			var query = this.value.toLowerCase();
			$list.find('.ghl-lazy-dropdown__option').each(function() {
				var text = this.textContent.toLowerCase();
				var val  = (this.getAttribute('data-value') || '').toLowerCase();
				this.style.display = (text.indexOf(query) !== -1 || val.indexOf(query) !== -1) ? '' : 'none';
			});
		});

		// Option click
		$list.on('click', '.ghl-lazy-dropdown__option', function(e) {
			e.stopPropagation();
			selectValue($trigger, $(this).attr('data-value') || '');
			closeDropdown();
		});

		// Keyboard
		$search.on('keydown', function(e) {
			if (e.key === 'Escape') {
				closeDropdown();
				$trigger.focus();
			} else if (e.key === 'Enter') {
				var $visible = $list.find('.ghl-lazy-dropdown__option:visible');
				if ($visible.length === 1) {
					$visible.trigger('click');
				}
			} else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				var $opts = $list.find('.ghl-lazy-dropdown__option:visible');
				var $focused = $list.find('.ghl-lazy-dropdown__option--focused');
				var idx = $opts.index($focused);
				$opts.removeClass('ghl-lazy-dropdown__option--focused');
				if (e.key === 'ArrowDown') {
					idx = (idx + 1) % $opts.length;
				} else {
					idx = (idx - 1 + $opts.length) % $opts.length;
				}
				$opts.eq(idx).addClass('ghl-lazy-dropdown__option--focused');
				// Scroll into view
				var el = $opts[idx];
				if (el) el.scrollIntoView({ block: 'nearest' });
			}
		});

		// Enter on focused option
		$search.on('keyup', function(e) {
			if (e.key === 'Enter') {
				var $focused = $list.find('.ghl-lazy-dropdown__option--focused:visible');
				if ($focused.length) {
					$focused.trigger('click');
				}
			}
		});
	}

	/**
	 * Position the dropdown panel aligned to the trigger using fixed coords.
	 * Flips upward automatically when there isn't enough room below.
	 */
	function positionDropdown() {
		if (!activeDropdown) return;
		var rect = activeDropdown.$trigger[0].getBoundingClientRect();
		var dd   = activeDropdown.$dropdown[0];
		var gap  = 2; // px between trigger and dropdown

		// Temporarily show to measure height
		dd.style.visibility = 'hidden';
		dd.style.display = '';
		var ddH = dd.offsetHeight;
		dd.style.visibility = '';

		var spaceBelow = window.innerHeight - rect.bottom - gap;
		var flipUp = ddH > spaceBelow && rect.top > spaceBelow;

		dd.style.left  = rect.left + 'px';
		dd.style.width = rect.width + 'px';

		if (flipUp) {
			dd.style.top    = '';
			dd.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
		} else {
			dd.style.top    = (rect.bottom + gap) + 'px';
			dd.style.bottom = '';
		}
	}

	function closeDropdown() {
		if (!activeDropdown) return;
		activeDropdown.$dropdown.remove();
		activeDropdown.$trigger.removeClass('ghl-lazy-select--open');
		activeDropdown = null;
	}

	function selectValue($trigger, value) {
		$trigger.attr('data-value', value);
		$trigger.find('.ghl-lazy-select__text').text(getDisplayText(value));

		// Update the sibling hidden input and fire change for other listeners
		var name = $trigger.attr('data-name');
		$trigger.siblings('input[type="hidden"][name="' + name + '"]').val(value).trigger('change');
	}

	/* -------------------------------------------------- event bindings */

	// Click to open / toggle
	$(document).on('click', '.ghl-lazy-select:not(.ghl-lazy-select--disabled)', function(e) {
		// If click was inside the dropdown, ignore — it handles itself
		if ($(e.target).closest('.ghl-lazy-dropdown').length) return;

		e.stopPropagation();
		var $this = $(this);
		if ($this.hasClass('ghl-lazy-select--open')) {
			closeDropdown();
		} else {
			openDropdown($this);
		}
	});

	// Reposition on scroll / resize so the panel follows the trigger
	$(window).on('scroll resize', function() {
		positionDropdown();
	});

	// Outside click closes dropdown
	$(document).on('mousedown', function(e) {
		if (activeDropdown && !$(e.target).closest('.ghl-lazy-select--open').length && !$(e.target).closest('.ghl-lazy-dropdown').length) {
			closeDropdown();
		}
	});

	/* -------------------------------------------------- global API (SPA compat) */

	window.GHL_FieldMapping = window.GHL_FieldMapping || {};

	window.GHL_FieldMapping.bindLazyDropdowns = function() {
		$('.ghl-lazy-select:not(.ghl-lazy-select--disabled)')
			.off('click.ghlLazySelect')
			.on('click.ghlLazySelect', function(e) {
				if ($(e.target).closest('.ghl-lazy-dropdown').length) return;

				e.stopPropagation();
				var $this = $(this);
				if ($this.hasClass('ghl-lazy-select--open')) {
					closeDropdown();
				} else {
					openDropdown($this);
				}
			});
	};

	/**
	 * No-op — kept for SPA-router backward compatibility.
	 * Select2 is no longer used; dropdowns are lazy.
	 */
	window.GHL_FieldMapping.initSelect2 = window.GHL_FieldMapping.bindLazyDropdowns;

	/**
	 * Update row highlighting based on mapped status
	 */
	window.GHL_FieldMapping.updateMappedRows = function() {
		$('input[type="hidden"][name^="ghl_field_"]').each(function() {
			var $input = $(this);
			var $row   = $input.closest('tr');
			if ($input.val()) {
				$row.addClass('ghl-mapped-field');
			} else {
				$row.removeClass('ghl-mapped-field');
			}
		});
	};

	/* -------------------------------------------------- doc-ready */

	$(document).ready(function() {
		window.GHL_FieldMapping.bindLazyDropdowns();

		// Add data-label attributes for mobile responsive card layout
		$('.ghl-crm-field-mapping .ghl-table tbody tr').each(function() {
			var $cells = $(this).find('td');
			$cells.eq(0).attr('data-label', 'WordPress Field');
			$cells.eq(1).attr('data-label', 'GoHighLevel Field');
			$cells.eq(2).attr('data-label', 'Sync Direction');
		});

		// Initial row highlighting
		window.GHL_FieldMapping.updateMappedRows();

		// Re-highlight on change
		$(document).on('change', 'input[name^="ghl_field_"]', function() {
			window.GHL_FieldMapping.updateMappedRows();
		});
	});

})(jQuery);
