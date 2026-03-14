/**
 * CF7 GHL CRM Integration - Admin JavaScript
 *
 * Handles checkbox toggles, field mapping (loaded via AJAX), and Select2 tag input
 */

(function($) {
	'use strict';

	// Localized data (handle: ghl-crm-cf7-js → var: ghl_crm_cf7_js_data)
	const localized = (typeof ghl_crm_cf7_js_data !== 'undefined') ? ghl_crm_cf7_js_data : {};

	const GHL_CF7_Admin = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.loadGHLFields();
			this.initTagsSelect2();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Toggle settings visibility when integration is enabled/disabled
			$('#ghl_crm_enabled').on('change', this.toggleSettings);

			// Checkbox visual toggle (ghl-checkbox pattern)
			$(document).on('change', '.ghl-checkbox-original', function() {
				const $label = $(this).closest('.ghl-checkbox');
				const $span = $label.find('.ghl-checkbox-input');
				if ($(this).is(':checked')) {
					$label.addClass('is-checked');
					$span.addClass('is-checked');
				} else {
					$label.removeClass('is-checked');
					$span.removeClass('is-checked');
				}
			});
		},

		/**
		 * Toggle settings container visibility
		 */
		toggleSettings: function() {
			const isEnabled = $(this).is(':checked');
			$('#ghl_crm_settings_container').toggle(isEnabled);
		},

		/**
		 * Load GHL fields via AJAX (same endpoint as field-mapping page)
		 */
		loadGHLFields: function() {
			const nonce = localized.nonce || '';
			if (!nonce) {
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ghl_crm_get_custom_fields',
					nonce: nonce
				},
				success: function(response) {
					if (response.success && response.data.fields) {
						GHL_CF7_Admin.populateFieldDropdowns(response.data.fields);
					} else {
						GHL_CF7_Admin.showFieldsError();
					}
				},
				error: function() {
					GHL_CF7_Admin.showFieldsError();
				}
			});
		},

		/**
		 * Populate all GHL field dropdowns with fetched fields
		 *
		 * @param {Object} fields Key-value pairs of field_id => field_label
		 */
		populateFieldDropdowns: function(fields) {
			$('.ghl-field-select').each(function() {
				const $select = $(this);
				const savedValue = $select.data('saved-value') || '';

				// Clear loading placeholder
				$select.empty();

				// Add each field as an option
				$.each(fields, function(key, label) {
					const $option = $('<option></option>')
						.attr('value', key)
						.text(label);

					if (key === savedValue) {
						$option.attr('selected', 'selected');
					}

					$select.append($option);
				});

			// Restore saved value
				if (savedValue) {
					$select.val(savedValue);
				}
			});

			// Check email mapping after fields are loaded
			this.checkEmailMapping();

			// Re-check on any dropdown change
			$('.ghl-field-select').on('change', this.checkEmailMapping);
		},

		/**
		 * Show error state in field dropdowns
		 */
		showFieldsError: function() {
			$('.ghl-field-select').each(function() {
				const $select = $(this);
				$select.empty();
				$select.append('<option value="">— Failed to load fields —</option>');
			});
		},

		/**
		 * Check if at least one field is mapped to 'email' and show/hide notice
		 */
		checkEmailMapping: function() {
			let hasEmail = false;
			$('.ghl-field-select').each(function() {
				if ($(this).val() === 'email') {
					hasEmail = true;
					return false; // break
				}
			});

			$('#ghl_crm_email_notice').toggle(!hasEmail);
		},

		/**
		 * Initialize Select2 for tags
		 */
		initTagsSelect2: function() {
			const $select = $('#ghl_crm_cf7_tags');
			if (!$select.length) {
				return;
			}

			const tags = localized.tags || [];
			const savedTags = $select.data('saved-tags') || [];

			// Clear loading placeholder
			$select.empty();

			// Add tag options
			if (tags.length) {
				tags.forEach(function(tag) {
					const tagName = tag.name || tag;
					const option = new Option(tagName, tagName, false, false);
					$select.append(option);
				});
			}

			// Also add saved tags that might not be in the current list
			if (savedTags.length) {
				savedTags.forEach(function(tagName) {
					if (!$select.find('option[value="' + tagName + '"]').length) {
						const option = new Option(tagName, tagName, false, false);
						$select.append(option);
					}
				});
			}

			// Initialize Select2
			$select.select2({
				tags: true,
				tokenSeparators: [','],
				placeholder: $select.data('placeholder') || 'Select tags...',
				allowClear: true,
				width: '100%'
			});

			// Pre-select saved tags
			if (savedTags.length) {
				$select.val(savedTags).trigger('change');
			}
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.ghl-crm-cf7-panel').length) {
			GHL_CF7_Admin.init();
		}
	});

})(jQuery);
