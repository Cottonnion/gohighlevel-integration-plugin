/**
 * Field Mapping Page JavaScript
 *
 * Handles field mapping form submission via AJAX
 *
 * @package    Syncly
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
				action: 'syncly_save_field_mapping',
				nonce: syncly_field_mapping_js_data.nonce,
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

		if (window.Syncly_FieldMapping && typeof window.Syncly_FieldMapping.bindLazyDropdowns === 'function') {
			window.Syncly_FieldMapping.bindLazyDropdowns();
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
				window.Syncly_FIELDS = data.fields;
			}

			if (data.savedMappings && typeof data.savedMappings === 'object') {
				window.Syncly_SAVED_MAPPINGS = data.savedMappings;
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
 * The full searchable dropdown is built from window.Syncly_FIELDS only when
 * the user clicks a trigger — one dropdown at a time.
 */
(function($) {
	'use strict';

	var activeDropdown = null;

	/* -------------------------------------------------- helpers */

	function getDisplayText(value) {
		if (!value) return '— Do Not Sync —';
		var fields = window.Syncly_FIELDS || {};
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
		var fields = window.Syncly_FIELDS || {};

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

	window.Syncly_FieldMapping = window.Syncly_FieldMapping || {};

	window.Syncly_FieldMapping.bindLazyDropdowns = function() {
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
	window.Syncly_FieldMapping.initSelect2 = window.Syncly_FieldMapping.bindLazyDropdowns;

	/**
	 * Update row highlighting based on mapped status
	 */
	window.Syncly_FieldMapping.updateMappedRows = function() {
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
		window.Syncly_FieldMapping.bindLazyDropdowns();

		// Add data-label attributes for mobile responsive card layout
		$('.syncly-field-mapping .ghl-table tbody tr').each(function() {
			var $cells = $(this).find('td');
			$cells.eq(0).attr('data-label', 'WordPress Field');
			$cells.eq(1).attr('data-label', 'GoHighLevel Field');
			$cells.eq(2).attr('data-label', 'Sync Direction');
		});

		// Initial row highlighting
		window.Syncly_FieldMapping.updateMappedRows();

		// Re-highlight on change
		$(document).on('change', 'input[name^="ghl_field_"]', function() {
			window.Syncly_FieldMapping.updateMappedRows();
		});
	});

})(jQuery);
