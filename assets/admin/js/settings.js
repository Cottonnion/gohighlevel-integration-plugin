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
	 * Show notification message using SweetAlert2 toast
	 */
	function showNotice(message, type = 'success') {
		if (typeof Swal !== 'undefined') {
			const Toast = Swal.mixin({
				toast: true,
				position: 'top-end',
				showConfirmButton: false,
				timer: 3000,
				timerProgressBar: true,
				didOpen: (toast) => {
					toast.addEventListener('mouseenter', Swal.stopTimer);
					toast.addEventListener('mouseleave', Swal.resumeTimer);
				}
			});

			Toast.fire({
				icon: type === 'success' ? 'success' : 'error',
				title: message
			});
		} else {
			// Fallback to console if SweetAlert2 is not available
			console.log(type + ': ' + message);
		}
	}

	/**
	 * Initialize settings functionality
	 */
	function initSettings() {
		// Prevent multiple initializations
		if (window.ghlSettingsInitialized) {
			return;
		}
		window.ghlSettingsInitialized = true;
		
		/**
		 * Handle custom checkbox state changes
		 * Use delegated event for dynamically loaded content
		 */
		$(document).off('change.ghlCheckbox', '.ghl-checkbox-original')
			.on('change.ghlCheckbox', '.ghl-checkbox-original', function() {
			const $checkbox = $(this);
			const $label = $checkbox.closest('.ghl-checkbox');
			const $input = $checkbox.siblings('.ghl-checkbox-input');
			
			if ($checkbox.is(':checked')) {
				$label.addClass('is-checked');
				$input.addClass('is-checked');
			} else {
				$label.removeClass('is-checked');
				$input.removeClass('is-checked');
			}
		});
		
		/**
		 * Universal Save Settings Handler
		 * Works for all settings tabs - collects all form data from active tab
		 */
		$(document).off('click.ghlSettings', '#save-general-settings, #save-restrictions-settings, .ghl-save-settings-btn')
			.on('click.ghlSettings', '#save-general-settings, #save-restrictions-settings, .ghl-save-settings-btn', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const $buttonText = $button.find('.ghl-button-text, .button-text');
			const originalText = $buttonText.text();
			
			// Find the settings wrapper (could be in different containers)
			const $settingsWrapper = $button.closest('.ghl-settings-wrapper').length 
				? $button.closest('.ghl-settings-wrapper')
				: $('.ghl-settings-wrapper');
			
			// Collect all form data from the active settings tab
			const formData = {
				action: 'ghl_crm_save_settings',
				nonce: $('input[name*="nonce"]').first().val() || $('#ghl_crm_nonce').val(),
			};
			
			// First, identify all checkbox array names to initialize them as empty arrays
			const checkboxArrays = new Set();
			$settingsWrapper.find('input[type="checkbox"]').each(function() {
				const name = $(this).attr('name');
				if (name && name.endsWith('[]')) {
					const baseName = name.replace('[]', '');
					checkboxArrays.add(baseName);
				}
			});
			
			// Initialize all checkbox arrays as empty (will be populated if checked)
			checkboxArrays.forEach(function(baseName) {
				formData[baseName] = [];
			});
			
			// Collect all inputs, checkboxes, selects, textareas
			$settingsWrapper.find('input, select, textarea').each(function() {
				const $input = $(this);
				const name = $input.attr('name');
				const type = $input.attr('type');
				
				// Skip nonce fields (already added)
				if (!name || name.includes('nonce')) {
					return;
				}
				
				// Handle checkboxes
				if (type === 'checkbox') {
					if (name.endsWith('[]')) {
						// Handle checkbox arrays (like user_sync_actions[])
						const baseName = name.replace('[]', '');
						if ($input.is(':checked')) {
							formData[baseName].push($input.val());
						}
						// Array is already initialized as empty above
					} else {
						// Single checkbox - send as 1 or 0
						formData[name] = $input.is(':checked') ? '1' : '0';
					}
				}
				// Handle radio buttons
				else if (type === 'radio') {
					if ($input.is(':checked')) {
						formData[name] = $input.val();
					}
				}
				// Handle multi-select dropdowns
				else if ($input.is('select[multiple]')) {
					const baseName = name.endsWith('[]') ? name.replace('[]', '') : name;
					const selectedValues = $input.val(); // Returns array for multi-select
					if (selectedValues && selectedValues.length > 0) {
						formData[baseName] = selectedValues;
					} else {
						formData[baseName] = [];
					}
				}
				// Handle all other inputs
				else {
					formData[name] = $input.val();
				}
			});
			
			// Convert empty arrays to a special marker so PHP receives them
			// jQuery.ajax won't send empty arrays, so we need to handle this
			Object.keys(formData).forEach(function(key) {
				if (Array.isArray(formData[key]) && formData[key].length === 0) {
					// Send as string "__EMPTY_ARRAY__" which PHP will convert back
					formData[key] = '__EMPTY_ARRAY__';
				}
			});
			
			console.log('Saving settings:', formData);
			
			// Disable button and show loading state
			$button.prop('disabled', true);
			$buttonText.text('Saving...');
			
			// Make AJAX request
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						showNotice(response.data.message || 'Settings saved successfully!', 'success');
						$buttonText.text('✓ Saved');
						
						// Reset button text after 2 seconds
						setTimeout(function() {
							$buttonText.text(originalText);
						}, 2000);
					} else {
						showNotice(response.data.message || 'Failed to save settings.', 'error');
						$buttonText.text(originalText);
					}
				},
				error: function(xhr) {
					const errorMsg = xhr.responseJSON?.data?.message || 'An error occurred while saving settings.';
					showNotice(errorMsg, 'error');
					$buttonText.text(originalText);
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		});
		
		/**
		 * Test Connection via AJAX
		 */
		$('#ghl-test-connection').off('click.ghlSettings').on('click.ghlSettings', function () {
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

	/**
	 * Handle Clear Cache button click
	 */
	$(document).off('click.ghlClearCache', '#clear-cache-btn')
		.on('click.ghlClearCache', '#clear-cache-btn', function(e) {
		e.preventDefault();
		
		const $button = $(this);
		const originalText = $button.text().trim();
		
		// Confirm action
		if (typeof Swal !== 'undefined') {
			Swal.fire({
				title: 'Clear Cache?',
				text: 'This will remove all cached API responses and contact data.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#635bff',
				cancelButtonColor: '#6b7280',
				confirmButtonText: 'Yes, clear it',
				cancelButtonText: 'Cancel'
			}).then((result) => {
				if (result.isConfirmed) {
					clearCache($button, originalText);
				}
			});
		} else {
			if (confirm('Are you sure you want to clear all cached data?')) {
				clearCache($button, originalText);
			}
		}
	});
	
	function clearCache($button, originalText) {
		$button.prop('disabled', true).text('Clearing...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_crm_clear_cache',
				nonce: $('#ghl_crm_nonce').val()
			},
			success: function(response) {
				if (response.success) {
					showNotice(response.data.message || 'Cache cleared successfully!', 'success');
				} else {
					showNotice(response.data.message || 'Failed to clear cache.', 'error');
				}
			},
			error: function() {
				showNotice('An error occurred while clearing cache.', 'error');
			},
			complete: function() {
				$button.prop('disabled', false).text(originalText);
			}
		});
	}
	
	/**
	 * Handle Reset Settings button click
	 */
	$(document).off('click.ghlResetSettings', '#reset-settings-btn')
		.on('click.ghlResetSettings', '#reset-settings-btn', function(e) {
		e.preventDefault();
		
		const $button = $(this);
		const originalText = $button.text().trim();
		
		// First confirmation
		if (typeof Swal !== 'undefined') {
			Swal.fire({
				title: 'Reset Settings?',
				html: 'This will reset all plugin settings to default values.<br><strong>Your OAuth connection will be preserved.</strong>',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#ef4444',
				cancelButtonColor: '#6b7280',
				confirmButtonText: 'Yes, reset',
				cancelButtonText: 'Cancel'
			}).then((result) => {
				if (result.isConfirmed) {
					// Second confirmation
					Swal.fire({
						title: 'Are you absolutely sure?',
						html: 'This action cannot be undone!<br><br>All custom settings will be lost:<br>• Cache duration<br>• Batch size<br>• Log retention<br>• User sync settings<br>• Field mappings<br>• Role tags<br><br><strong>Only Api connection will remain.</strong>',
						icon: 'error',
						showCancelButton: true,
						confirmButtonColor: '#dc2626',
						cancelButtonColor: '#6b7280',
						confirmButtonText: 'Yes, I understand',
						cancelButtonText: 'No, cancel',
						reverseButtons: true
					}).then((finalResult) => {
						if (finalResult.isConfirmed) {
							resetSettings($button, originalText);
						}
					});
				}
			});
		} else {
			// Fallback for browsers without SweetAlert2
			if (confirm('Are you sure you want to reset all settings to defaults? Your OAuth connection will be preserved.')) {
				if (confirm('Final confirmation: This action cannot be undone! All custom settings will be lost. Continue?')) {
					resetSettings($button, originalText);
				}
			}
		}
	});
	
	function resetSettings($button, originalText) {
		$button.prop('disabled', true).text('Resetting...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_crm_reset_settings',
				nonce: $('#ghl_crm_nonce').val()
			},
			success: function(response) {
				if (response.success) {
					showNotice(response.data.message || 'Settings reset successfully!', 'success');
					// Reload the page after 1 second to show updated values
					setTimeout(function() {
						window.location.reload();
					}, 1000);
				} else {
					showNotice(response.data.message || 'Failed to reset settings.', 'error');
				}
			},
			error: function() {
				showNotice('An error occurred while resetting settings.', 'error');
			},
			complete: function() {
				$button.prop('disabled', false).text(originalText);
			}
		});
	}

	/**
	 * Handle Export Settings button click
	 */
	$(document).off('click.ghlExportSettings', '#export-settings-btn')
		.on('click.ghlExportSettings', '#export-settings-btn', function(e) {
		e.preventDefault();
		
		const $button = $(this);
		const originalText = $button.text().trim();
		
		$button.prop('disabled', true).text('Exporting...');
		
		// Get current settings via AJAX
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_settings',
				nonce: $('#ghl_crm_nonce').val()
			},
			success: function(response) {
				if (response.success && response.data.settings) {
					// Remove sensitive credentials from export
					const exportSettings = {...response.data.settings};
					delete exportSettings.api_token;
					delete exportSettings.oauth_access_token;
					delete exportSettings.oauth_refresh_token;
					delete exportSettings.oauth_expires_at;
					delete exportSettings.oauth_token_type;
					delete exportSettings.oauth_connected_at;
					
					// Create JSON file
					const dataStr = JSON.stringify(exportSettings, null, 2);
					const dataBlob = new Blob([dataStr], {type: 'application/json'});
					
					// Create download link
					const url = window.URL.createObjectURL(dataBlob);
					const link = document.createElement('a');
					link.href = url;
					link.download = 'ghl-crm-settings-' + new Date().toISOString().split('T')[0] + '.json';
					document.body.appendChild(link);
					link.click();
					document.body.removeChild(link);
					window.URL.revokeObjectURL(url);
					
					showNotice('Settings exported successfully!', 'success');
				} else {
					showNotice('Failed to export settings.', 'error');
				}
			},
			error: function() {
				showNotice('An error occurred while exporting settings.', 'error');
			},
			complete: function() {
				$button.prop('disabled', false).text(originalText);
			}
		});
	});
	
	/**
	 * Handle Import Settings button click
	 */
	$(document).off('click.ghlImportSettings', '#import-settings-btn')
		.on('click.ghlImportSettings', '#import-settings-btn', function(e) {
		e.preventDefault();
		$('#import-settings-file').trigger('click');
	});
	
	/**
	 * Handle Import Settings file selection
	 */
	$(document).off('change.ghlImportFile', '#import-settings-file')
		.on('change.ghlImportFile', '#import-settings-file', function(e) {
		const file = e.target.files[0];
		if (!file) return;
		
		// Validate file type
		if (file.type !== 'application/json') {
			showNotice('Please select a valid JSON file.', 'error');
			return;
		}
		
		// Confirm import
		if (typeof Swal !== 'undefined') {
			Swal.fire({
				title: 'Import Settings?',
				html: 'This will overwrite your current settings (except API credentials).<br><br>Are you sure you want to continue?',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#635bff',
				cancelButtonColor: '#6b7280',
				confirmButtonText: 'Yes, import',
				cancelButtonText: 'Cancel'
			}).then((result) => {
				if (result.isConfirmed) {
					importSettingsFile(file);
				} else {
					// Reset file input
					$('#import-settings-file').val('');
				}
			});
		} else {
			if (confirm('Import settings? This will overwrite your current configuration (except API credentials).')) {
				importSettingsFile(file);
			} else {
				$('#import-settings-file').val('');
			}
		}
	});
	
	function importSettingsFile(file) {
		const reader = new FileReader();
		
		reader.onload = function(e) {
			try {
				const importedSettings = JSON.parse(e.target.result);
				
				// Save imported settings via AJAX
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ghl_crm_save_settings',
						nonce: $('#ghl_crm_nonce').val(),
						...importedSettings
					},
					success: function(response) {
						if (response.success) {
							showNotice('Settings imported successfully!', 'success');
							// Reload after 1 second
							setTimeout(function() {
								window.location.reload();
							}, 1000);
						} else {
							showNotice(response.data.message || 'Failed to import settings.', 'error');
						}
					},
					error: function() {
						showNotice('An error occurred while importing settings.', 'error');
					},
					complete: function() {
						// Reset file input
						$('#import-settings-file').val('');
					}
				});
			} catch (error) {
				showNotice('Invalid JSON file format.', 'error');
				$('#import-settings-file').val('');
			}
		};
		
		reader.onerror = function() {
			showNotice('Failed to read file.', 'error');
			$('#import-settings-file').val('');
		};
		
		reader.readAsText(file);
	}

	/**
	 * Cleanup function to remove all event handlers
	 */
	function cleanupSettings() {
		$(document).off('.ghlSettings');
		$(document).off('change.ghlCheckbox', '.ghl-checkbox-original');
		$(document).off('click.ghlClearCache', '#clear-cache-btn');
		$(document).off('click.ghlResetSettings', '#reset-settings-btn');
		$(document).off('click.ghlExportSettings', '#export-settings-btn');
		$(document).off('click.ghlImportSettings', '#import-settings-btn');
		$(document).off('change.ghlImportFile', '#import-settings-file');
		$('#ghl-test-connection').off('click.ghlSettings');
		window.ghlSettingsInitialized = false;
	}

	/**
	 * Initialize tags functionality for user registration
	 */
	function initUserRegisterTags() {
		const $enableCheckbox = $('#enable_user_register');
		const $tagsSection = $('#user_register_tags_section');
		const $tagsSelect = $('#user_register_tags');
		
		// Check if elements exist (only on general settings tab)
		if ($enableCheckbox.length === 0 || $tagsSection.length === 0 || $tagsSelect.length === 0) {
			return;
		}
		
		// Remove existing event handlers to prevent duplicates
		$enableCheckbox.off('change.ghlTags');
		
		// Toggle tags section when checkbox changes
		$enableCheckbox.on('change.ghlTags', function() {
			if ($(this).is(':checked')) {
				$tagsSection.slideDown(300);
				// Load tags if not already loaded
				if ($tagsSelect.find('option').length <= 1) {
					loadGoHighLevelTags();
				}
			} else {
				$tagsSection.slideUp(300);
			}
		});
		
		// Load tags on page load if checkbox is already checked
		if ($enableCheckbox.is(':checked') && $tagsSelect.find('option').length <= 1) {
			loadGoHighLevelTags();
		}
	}
	
	/**
	 * Load tags from GoHighLevel
	 */
	function loadGoHighLevelTags() {
		const $tagsSelect = $('#user_register_tags');
		const savedTags = $tagsSelect.data('saved-tags') || [];
		
		// Show loading state
		$tagsSelect.html('<option value="">Loading tags...</option>').prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_tags',
				nonce: $('#ghl_crm_nonce').val()
			},
			success: function(response) {
				if (response.success && response.data.tags) {
					const tags = response.data.tags;
					$tagsSelect.empty();

					if (tags.length === 0) {
						$tagsSelect.append('<option value="">No tags found in your GoHighLevel location</option>');
					} else {
						// Add placeholder option
						$tagsSelect.append('<option value="">Select tags...</option>');

						// Add each tag as an option
						tags.forEach(function(tag) {
							const tagValue = tag.name || tag;
							const tagLabel = tag.name || tag;
							const isSelected = savedTags.includes(tagValue);
							$tagsSelect.append(
								$('<option></option>')
									.attr('value', tagValue)
									.text(tagLabel)
									.prop('selected', isSelected)
							);
						});
					}

					$tagsSelect.prop('disabled', false);

					// Initialize Select2 if available
					if (typeof $.fn.select2 !== 'undefined') {
						$tagsSelect.select2({
							placeholder: 'Select tags to apply on user registration',
							allowClear: true,
							width: '100%'
						});
					}
				} else {
					$tagsSelect.html('<option value="">Failed to load tags</option>');
					console.error('Failed to load tags:', response);
				}
			},
			error: function(xhr, status, error) {
				$tagsSelect.html('<option value="">Error loading tags</option>').prop('disabled', false);
				console.error('Error loading tags:', error);
			}
		});
	}

	/**
	 * Initialize roles select2 for restrictions settings
	 */
	function initRestrictionsRolesSelect() {
		const $rolesSelect = $('.ghl-roles-select');

		if ($rolesSelect.length === 0 || typeof $.fn.select2 === 'undefined') {
			return;
		}

		$rolesSelect.select2({
			placeholder: $rolesSelect.data('placeholder') || 'Select roles that can bypass restrictions...',
			allowClear: true,
			width: '100%',
			closeOnSelect: false
		});
	}

	// Export to global scope for SPA to call
	window.initSettings = initSettings;
	window.cleanupSettings = cleanupSettings;
	window.initUserRegisterTags = initUserRegisterTags;
	window.initRestrictionsRolesSelect = initRestrictionsRolesSelect;

	// Initialize on document ready (for non-SPA page loads)
	$(document).ready(function() {
		initUserRegisterTags();
		initRestrictionsRolesSelect();
		initSettings();
	});

})(jQuery, window);