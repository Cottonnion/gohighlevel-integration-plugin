/**
 * Custom Objects Admin JavaScript
 *
 * @package GHL_CRM_Integration
 * @since 1.0.0
 */

(function($) {
	'use strict';

	// Global variables
	let currentMappings = [];
	let wpFieldOptions = [];
	let ghlFieldOptions = [];

	/**
	 * Initialize the custom objects page
	 */
	function init() {
		// Load schemas on page load
		loadSchemas();

		// Event handlers
		$('#ghl-refresh-schemas').on('click', function() {
			loadSchemas(true);
		});

		$('#ghl-view-mappings').on('click', toggleMappingsView);
		$('#ghl-create-mapping').on('click', function() {
			openMappingModal();
		});

		$(document).on('click', '.ghl-view-schema', viewSchemaDetails);
		$(document).on('click', '.ghl-modal-close, .ghl-modal-overlay', closeModal);
		$(document).on('click', '.edit-mapping', editMapping);
		$(document).on('click', '.delete-mapping', deleteMapping);

		$('#wp-post-type').on('change', function() {
			const postType = $(this).val();
			if (postType) {
				loadCPTFields(postType);
			}
		});

		$('#ghl-object').on('change', function() {
			const schemaId = $(this).val();
			if (schemaId) {
				loadGHLObjectFields(schemaId);
			}
		});

		$('#contact-source').on('change', function() {
			const $fieldRow = $('#contact-field-row');
			const value = $(this).val();
			if (value === 'post_meta' || value === 'acf' || value === 'meta_field') {
				$fieldRow.show();
			} else {
				$fieldRow.hide();
			}
		});

		$('#add-field-mapping').on('click', function() {
			addFieldMappingRow();
		});

		$('#ghl-mapping-form').on('submit', saveMapping);
	}

	/**
	 * Load schemas from API
	 */
	function loadSchemas(forceRefresh = false, callback = null) {
		const $container = $('#ghl-schemas-container');
		const $spinner = $('.ghl-custom-objects-controls .spinner');

		$spinner.addClass('is-active');
		
		// If schemas already loaded and not forcing refresh, just call callback
		if (!forceRefresh && window.ghlSchemas && window.ghlSchemas.length > 0 && $container.find('.ghl-schemas-grid').length > 0) {
			$spinner.removeClass('is-active');
			if (callback) callback();
			return;
		}

		$.ajax({
			url: ghl_crm_custom_objects_js_data.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_custom_objects',
				nonce: ghl_crm_custom_objects_js_data.nonces.customObjects,
				force_refresh: forceRefresh ? 1 : 0
			},
			success: function(response) {
				if (response.success) {
					renderSchemas(response.data.schemas);
					if (callback) callback();
				} else {
					showError(response.data.message || ghl_crm_custom_objects_js_data.i18n.failedToLoadSchemas);
				}
			},
			error: function(xhr, status, error) {
				showError(ghl_crm_custom_objects_js_data.i18n.networkError + ': ' + error);
			},
			complete: function() {
				$spinner.removeClass('is-active');
			}
		});
	}

	/**
	 * Render schemas
	 */
	function renderSchemas(schemas) {
		const $container = $('#ghl-schemas-container');
		
		if (!schemas || schemas.length === 0) {
			$container.html(`
				<div class="ghl-empty-state">
					<span class="dashicons dashicons-database"></span>
					<h3>${ghl_crm_custom_objects_js_data.i18n.noCustomObjectsFound}</h3>
					<p>${ghl_crm_custom_objects_js_data.i18n.createCustomObjectsMessage}</p>
				</div>
			`);
			return;
		}

		let html = '<div class="ghl-schemas-grid">';
		
		schemas.forEach(function(schema) {
			const singularLabel = schema.labels?.singular || schema.name || 'Object';
			const pluralLabel = schema.labels?.plural || singularLabel + 's';
			const description = schema.description || ghl_crm_custom_objects_js_data.i18n.noDescription;
			const requiredCount = schema.requiredProperties ? schema.requiredProperties.length : 0;
			const searchableCount = schema.searchableProperties ? schema.searchableProperties.length : 0;
			const typeBadge = schema.type === 'SYSTEM_DEFINED' 
				? '<span class="ghl-type-badge system">System</span>' 
				: '<span class="ghl-type-badge custom">Custom</span>';
			const iconHtml = schema.icon?.svg 
				? `<span class="ghl-schema-icon-svg">${schema.icon.svg}</span>`
				: '<span class="dashicons dashicons-database ghl-schema-icon"></span>';
			const createdDate = schema.createdAt ? new Date(schema.createdAt).toLocaleDateString() : 'Unknown';
			
			html += `
				<div class="ghl-schema-card ${schema.type === 'SYSTEM_DEFINED' ? 'system-object' : 'custom-object'}" data-schema-id="${schema.id}">
					<div class="ghl-schema-card-header">
						${iconHtml}
						<div class="ghl-schema-card-title">
							<h3>${escapeHtml(singularLabel)} ${typeBadge}</h3>
							<p class="ghl-schema-plural">${escapeHtml(pluralLabel)}</p>
						</div>
					</div>
					<div class="ghl-schema-card-body">
						<p class="ghl-schema-description">${escapeHtml(description)}</p>
						<div class="ghl-schema-meta">
							<div class="ghl-schema-meta-item">
								<span class="dashicons dashicons-yes-alt"></span>
								<span>${requiredCount} ${ghl_crm_custom_objects_js_data.i18n.requiredFields}</span>
							</div>
							<div class="ghl-schema-meta-item">
								<span class="dashicons dashicons-search"></span>
								<span>${searchableCount} ${ghl_crm_custom_objects_js_data.i18n.searchableFields}</span>
							</div>
							<div class="ghl-schema-meta-item">
								<span class="dashicons dashicons-calendar-alt"></span>
								<span>${ghl_crm_custom_objects_js_data.i18n.created}: ${createdDate}</span>
							</div>
						</div>
					</div>
					<div class="ghl-schema-card-footer">
						<button type="button" class="ghl-button ghl-button-secondary ghl-view-schema" data-schema-id="${schema.id}">
							<span class="dashicons dashicons-visibility"></span>
							${ghl_crm_custom_objects_js_data.i18n.viewDetails}
						</button>
					</div>
				</div>
			`;
		});
		
		html += '</div>';
		$container.html(html);

		// Store schemas for later use
		window.ghlSchemas = schemas;
	}

	/**
	 * View schema details - Fetch full schema with associations
	 */
	function viewSchemaDetails() {
		const $btn = $(this);
		const schemaId = $btn.data('schema-id');
		const originalHtml = $btn.html();
		
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update ghl-spin"></span> ' + ghl_crm_custom_objects_js_data.i18n.loading);
		
		$.ajax({
			url: ghl_crm_custom_objects_js_data.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_schema_details',
				nonce: ghl_crm_custom_objects_js_data.nonces.customObjects,
				schema_id: schemaId
			},
			success: function(response) {
				$btn.prop('disabled', false).html(originalHtml);
				
				if (response.success) {
					showSchemaModal(response.data.schema, response.data.associations);
				} else {
					Swal.fire({
						icon: 'error',
						title: ghl_crm_custom_objects_js_data.i18n.error,
						text: response.data?.message || ghl_crm_custom_objects_js_data.i18n.failedToFetchSchemaDetails,
						confirmButtonColor: '#d63638'
					});
				}
			},
			error: function(xhr, status, error) {
				$btn.prop('disabled', false).html(originalHtml);
				Swal.fire({
					icon: 'error',
					title: ghl_crm_custom_objects_js_data.i18n.failedToFetchSchemaDetails,
					text: error,
					confirmButtonColor: '#d63638'
				});
			}
		});
	}

	/**
	 * Show schema details modal
	 */
	function showSchemaModal(schema, associations = []) {
		const $modal = $('#ghl-schema-details-modal');
		const title = schema.labels?.singular || schema.name || 'Object';
		$('#ghl-modal-title').text(title);
		
		let bodyHtml = '';
		
		// Schema Overview
		bodyHtml += '<div class="ghl-modal-section">';
		bodyHtml += '<h3>' + ghl_crm_custom_objects_js_data.i18n.overview + '</h3>';
		bodyHtml += '<table class="ghl-detail-table">';
		bodyHtml += `<tr><th>${ghl_crm_custom_objects_js_data.i18n.type}:</th><td>${schema.type === 'SYSTEM_DEFINED' ? 'System Defined' : 'User Defined'}</td></tr>`;
		bodyHtml += `<tr><th>${ghl_crm_custom_objects_js_data.i18n.key}:</th><td><code>${escapeHtml(schema.key)}</code></td></tr>`;
		bodyHtml += `<tr><th>ID:</th><td><code>${escapeHtml(schema.id)}</code></td></tr>`;
		if (schema.description) {
			bodyHtml += `<tr><th>${ghl_crm_custom_objects_js_data.i18n.description}:</th><td>${escapeHtml(schema.description)}</td></tr>`;
		}
		if (schema.primaryDisplayProperty) {
			bodyHtml += `<tr><th>${ghl_crm_custom_objects_js_data.i18n.primaryDisplay}:</th><td><code>${escapeHtml(schema.primaryDisplayProperty)}</code></td></tr>`;
		}
		bodyHtml += `<tr><th>${ghl_crm_custom_objects_js_data.i18n.created}:</th><td>${new Date(schema.createdAt).toLocaleString()}</td></tr>`;
		bodyHtml += `<tr><th>${ghl_crm_custom_objects_js_data.i18n.updated}:</th><td>${new Date(schema.updatedAt).toLocaleString()}</td></tr>`;
		bodyHtml += '</table>';
		bodyHtml += '</div>';
		
		// Required Properties
		if (schema.requiredProperties && schema.requiredProperties.length > 0) {
			bodyHtml += '<div class="ghl-modal-section">';
			bodyHtml += '<h3>' + ghl_crm_custom_objects_js_data.i18n.requiredProperties + '</h3>';
			bodyHtml += '<ul class="ghl-property-list">';
			schema.requiredProperties.forEach(function(prop) {
				bodyHtml += `<li><span class="dashicons dashicons-yes-alt"></span> <code>${escapeHtml(prop)}</code></li>`;
			});
			bodyHtml += '</ul>';
			bodyHtml += '</div>';
		}
		
		// Searchable Properties
		if (schema.searchableProperties && schema.searchableProperties.length > 0) {
			bodyHtml += '<div class="ghl-modal-section">';
			bodyHtml += '<h3>' + ghl_crm_custom_objects_js_data.i18n.searchableProperties + '</h3>';
			bodyHtml += '<ul class="ghl-property-list">';
			schema.searchableProperties.forEach(function(prop) {
				bodyHtml += `<li><span class="dashicons dashicons-search"></span> <code>${escapeHtml(prop)}</code></li>`;
			});
			bodyHtml += '</ul>';
			bodyHtml += '</div>';
		}
		
		// Unique Properties
		if (schema.uniqueProperties && schema.uniqueProperties.length > 0) {
			bodyHtml += '<div class="ghl-modal-section">';
			bodyHtml += '<h3>' + ghl_crm_custom_objects_js_data.i18n.uniqueProperties + '</h3>';
			bodyHtml += '<ul class="ghl-property-list">';
			schema.uniqueProperties.forEach(function(prop) {
				bodyHtml += `<li><span class="dashicons dashicons-admin-network"></span> <code>${escapeHtml(prop)}</code></li>`;
			});
			bodyHtml += '</ul>';
			bodyHtml += '</div>';
		}
		
		// Add Record Configuration
		if (schema.addRecordConfiguration && schema.addRecordConfiguration.length > 0) {
			bodyHtml += '<div class="ghl-modal-section">';
			bodyHtml += '<h3>' + ghl_crm_custom_objects_js_data.i18n.addRecordConfiguration + '</h3>';
			bodyHtml += '<ul class="ghl-field-list">';
			schema.addRecordConfiguration.forEach(function(config) {
				const isGroup = config.isGroupField;
				const displayKey = config.key || config.groupKey;
				bodyHtml += `
					<li class="ghl-field-item">
						<div class="ghl-field-name">
							${isGroup ? '<span class="dashicons dashicons-category"></span>' : '<span class="dashicons dashicons-admin-settings"></span>'}
							<code>${escapeHtml(displayKey)}</code>
							${config.required ? '<span class="ghl-field-badge required">Required</span>' : ''}
							${config.isEditable ? '<span class="ghl-field-badge editable">Editable</span>' : ''}
							${isGroup ? '<span class="ghl-field-badge group">Group</span>' : ''}
						</div>
						<div class="ghl-field-meta">Order: ${config.order}</div>
					</li>
				`;
			});
			bodyHtml += '</ul>';
			bodyHtml += '</div>';
		}

		// Associations Section
		if (associations && associations.length > 0) {
			bodyHtml += '<div class="ghl-modal-section" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px;">';
			bodyHtml += '<h3><span class="dashicons dashicons-admin-links"></span> ' + ghl_crm_custom_objects_js_data.i18n.associations + '</h3>';
			bodyHtml += '<p class="description">' + ghl_crm_custom_objects_js_data.i18n.associationsDescription + '</p>';
			bodyHtml += '<ul class="ghl-property-list">';
			associations.forEach(function(assoc) {
				const assocType = assoc.type || assoc.key || 'Unknown';
				const assocLabel = assoc.label || assocType;
				bodyHtml += `<li><span class="dashicons dashicons-admin-links" style="color: #2271b1;"></span> <strong>${escapeHtml(assocLabel)}</strong> <code>(${escapeHtml(assocType)})</code></li>`;
			});
			bodyHtml += '</ul>';
			bodyHtml += '<p style="margin-top: 10px;"><strong>Note:</strong> ' + ghl_crm_custom_objects_js_data.i18n.contactLinkNote + '</p>';
			bodyHtml += '</div>';
		} else {
			bodyHtml += '<div class="ghl-modal-section" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px;">';
			bodyHtml += '<h3><span class="dashicons dashicons-info"></span> ' + ghl_crm_custom_objects_js_data.i18n.noAssociations + '</h3>';
			bodyHtml += '<p>' + ghl_crm_custom_objects_js_data.i18n.noAssociationsMessage + '</p>';
			bodyHtml += '</div>';
		}

		bodyHtml += '<div class="ghl-modal-section">';
		bodyHtml += '<h3>' + ghl_crm_custom_objects_js_data.i18n.rawJson + '</h3>';
		bodyHtml += '<pre class="ghl-json-viewer">' + JSON.stringify(schema, null, 2) + '</pre>';
		bodyHtml += '</div>';

		$('#ghl-modal-body').html(bodyHtml);
		$modal.fadeIn(200);
	}

	/**
	 * Close modal
	 */
	function closeModal(e) {
		if ($(e.target).hasClass('ghl-modal-close') || $(e.target).hasClass('dashicons-no-alt') || $(e.target).hasClass('ghl-modal-overlay')) {
			$('.ghl-modal').fadeOut(200);
		}
	}

	/**
	 * Toggle mappings view
	 */
	function toggleMappingsView() {
		const $schemasContainer = $('#ghl-schemas-container');
		const $mappingsSection = $('#ghl-mappings-section');
		
		if ($mappingsSection.is(':visible')) {
			$mappingsSection.hide();
			$schemasContainer.show();
			$(this).find('.dashicons').removeClass('dashicons-arrow-left').addClass('dashicons-admin-settings');
			$(this).contents().last()[0].textContent = ' ' + ghl_crm_custom_objects_js_data.i18n.viewMappings;
		} else {
			$schemasContainer.hide();
			$mappingsSection.show();
			$(this).find('.dashicons').removeClass('dashicons-admin-settings').addClass('dashicons-arrow-left');
			$(this).contents().last()[0].textContent = ' ' + ghl_crm_custom_objects_js_data.i18n.backToObjects;
			loadMappings();
		}
	}

	/**
	 * Open mapping modal
	 */
	function openMappingModal(mappingId = null) {
		// Reset form
		$('#ghl-mapping-form')[0].reset();
		$('#mapping-id').val('');
		$('#field-mappings-body').empty();
		
		// Ensure schemas are loaded first
		if (!window.ghlSchemas || window.ghlSchemas.length === 0) {
			loadSchemas(false, function() {
				openMappingModalAfterLoad(mappingId);
			});
			return;
		}
		
		openMappingModalAfterLoad(mappingId);
	}
	
	/**
	 * Open modal after schemas are loaded
	 */
	function openMappingModalAfterLoad(mappingId = null) {
		const $modal = $('#ghl-mapping-modal');
		
		$modal.fadeIn(200);
		
		if (mappingId) {
			const mapping = currentMappings.find(m => m.id === mappingId);
			if (mapping) {
				// Load post types with the mapping's selected value
				loadPostTypes(mapping.wp_post_type, function() {
					// After post types are loaded, populate the rest of the form
					populateMappingForm(mapping);
				});
				loadGHLObjects();
			}
		} else {
			loadPostTypes();
			loadGHLObjects();
			addFieldMappingRow();
			
			// Auto-select if only one custom object
			const $ghlObjectSelect = $('#ghl-object');
			if ($ghlObjectSelect.find('option').length === 2) {
				const firstObjectId = $ghlObjectSelect.find('option:eq(1)').val();
				if (firstObjectId) {
					$ghlObjectSelect.val(firstObjectId);
					loadGHLObjectFields(firstObjectId);
				}
			}
		}
	}

	/**
	 * Load WordPress post types
	 */
	function loadPostTypes(selectedValue = null, callback = null) {
		$.ajax({
			url: ghl_crm_custom_objects_js_data.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_post_types',
				nonce: ghl_crm_custom_objects_js_data.nonces.mappings
			},
			success: function(response) {
				if (response.success) {
					const $select = $('#wp-post-type');
					$select.empty().append('<option value="">' + ghl_crm_custom_objects_js_data.i18n.selectPostType + '</option>');
					
					$.each(response.data.post_types, function(key, label) {
						$select.append(`<option value="${key}">${escapeHtml(label)}</option>`);
					});
					
					// Pre-select value if provided
					if (selectedValue) {
						$select.val(selectedValue);
					}
					
					// Call callback if provided
					if (typeof callback === 'function') {
						callback();
					}
				}
			}
		});
	}

	/**
	 * Load available fields for selected CPT
	 */
	function loadCPTFields(postType, callback, selectedContactSource) {
		console.log('Loading fields for post type:', postType);
		
		// Store globally for field mapping
		window.cptFields = {
			core_fields: [],
			meta_fields: [],
			taxonomies: [],
			acf_fields: [],
			contact_options: {primary: [], secondary: []}
		};

		$.ajax({
			url: ghl_crm_custom_objects_js_data.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_cpt_fields',
				nonce: ghl_crm_custom_objects_js_data.nonces.mappings,
				post_type: postType
			},
			success: function(response) {
				if (response.success && response.data.fields) {
					window.cptFields = response.data.fields;
					console.log('Loaded CPT fields:', window.cptFields);
					
					// Update contact source options with selected value if provided
					updateContactSourceOptions(response.data.fields.contact_options, selectedContactSource);
					
					// Update sync triggers
					updateSyncTriggers(response.data.fields.sync_triggers);
					
					// Refresh field mapping rows with new options
					refreshFieldMappingOptions();
					
					// Call callback if provided
					if (typeof callback === 'function') {
						callback();
					}
				} else {
					console.error('Failed to load CPT fields');
				}
			},
			error: function(xhr, status, error) {
				console.error('Error loading CPT fields:', error);
			}
		});
	}

	/**
	 * Update contact source dropdown with context-aware options
	 */
	function updateContactSourceOptions(contactOptions, selectedValue) {
		// Update primary contact dropdown
		const $primarySelect = $('#contact-source');
		if ($primarySelect.length && contactOptions && contactOptions.primary) {
			const currentValue = selectedValue || $primarySelect.val();
			$primarySelect.empty();
			
			contactOptions.primary.forEach(function(option) {
				$primarySelect.append(`<option value="${option.key}">${escapeHtml(option.label)}</option>`);
			});

			// Restore previous value or set selected value if available
			if (currentValue && $primarySelect.find(`option[value="${currentValue}"]`).length) {
				$primarySelect.val(currentValue);
			}
			
			// Show/hide meta field input based on selection
			$primarySelect.trigger('change');
		}

		// Update secondary contacts checkboxes
		const $secondaryGroup = $('#secondary-contacts-group');
		if ($secondaryGroup.length && contactOptions && contactOptions.secondary) {
			// Clear existing checkboxes except the first one (post_author)
			$secondaryGroup.find('label:not(:first)').remove();
			
			// Add context-aware secondary contact options
			contactOptions.secondary.forEach(function(option) {
				// Skip post_author as it's already in the HTML
				if (option.key === 'post_author') return;
				
				const checkbox = `
					<label>
						<input type="checkbox" name="secondary_contacts[]" value="${escapeHtml(option.key)}">
						${escapeHtml(option.label)}
					</label>
				`;
				$secondaryGroup.append(checkbox);
			});
		}
	}

	/**
	 * Update sync triggers based on post type
	 */
	function updateSyncTriggers(triggers) {
		const $triggersGroup = $('#sync-triggers-group');
		if (!$triggersGroup.length || !triggers || !triggers.length) return;

		// Store currently selected triggers
		const selectedTriggers = [];
		$triggersGroup.find('input[name="triggers[]"]:checked').each(function() {
			selectedTriggers.push($(this).val());
		});

		// Clear all triggers
		$triggersGroup.empty();

		// Add new triggers with descriptions
		triggers.forEach(function(trigger) {
			const isChecked = selectedTriggers.includes(trigger.key) || trigger.key === 'publish' || trigger.key === 'update';
			const checkbox = `
				<label title="${escapeHtml(trigger.description || '')}">
					<input type="checkbox" name="triggers[]" value="${escapeHtml(trigger.key)}" ${isChecked ? 'checked' : ''}>
					${escapeHtml(trigger.label)}
					${trigger.description ? '<span class="description" style="display: block; margin-left: 24px; margin-top: 2px; font-size: 12px; color: #646970;">' + escapeHtml(trigger.description) + '</span>' : ''}
				</label>
			`;
			$triggersGroup.append(checkbox);
		});

		console.log('Updated sync triggers:', triggers.length);
	}

	/**
	 * Refresh field mapping dropdowns with newly loaded CPT fields
	 */
	function refreshFieldMappingOptions() {
		// Clear current WP field options
		wpFieldOptions = [];
		
		if (!window.cptFields) return;

		// Build WP field options from all sources
		const fields = window.cptFields;
		
		// Add grouped options
		if (fields.core_fields && fields.core_fields.length > 0) {
			wpFieldOptions.push({
				label: 'Core Fields',
				options: fields.core_fields.map(f => ({
					value: f.key,
					label: f.label,
					type: f.type
				}))
			});
		}

		if (fields.meta_fields && fields.meta_fields.length > 0) {
			wpFieldOptions.push({
				label: 'Custom Fields',
				options: fields.meta_fields.map(f => ({
					value: f.key,
					label: f.label,
					type: f.type
				}))
			});
		}

		if (fields.taxonomies && fields.taxonomies.length > 0) {
			wpFieldOptions.push({
				label: 'Taxonomies',
				options: fields.taxonomies.map(f => ({
					value: f.key,
					label: f.label,
					type: f.type
				}))
			});
		}

		if (fields.acf_fields && fields.acf_fields.length > 0) {
			wpFieldOptions.push({
				label: 'ACF Fields',
				options: fields.acf_fields.map(f => ({
					value: f.key,
					label: f.label,
					type: f.acf_type || f.type
				}))
			});
		}

		console.log('Built WP field options:', wpFieldOptions);
		
		// Update existing field mapping rows
		$('.wp-field-select').each(function() {
			const $select = $(this);
			const currentValue = $select.val();
			
			$select.empty().append('<option value="">Select WordPress Field...</option>');
			
			wpFieldOptions.forEach(function(group) {
				const $optgroup = $('<optgroup>').attr('label', group.label);
				group.options.forEach(function(option) {
					$optgroup.append(`<option value="${option.value}">${escapeHtml(option.label)}</option>`);
				});
				$select.append($optgroup);
			});
			
			// Restore previous value if available
			if (currentValue) {
				$select.val(currentValue);
			}
		});
	}

	/**
	 * Load GHL Custom Objects (exclude system objects)
	 */
	function loadGHLObjects() {
		const $select = $('#ghl-object');
		$select.empty().append('<option value="">' + ghl_crm_custom_objects_js_data.i18n.selectCustomObject + '</option>');
		
		if (!window.ghlSchemas || window.ghlSchemas.length === 0) {
			console.warn('GHL Schemas not loaded yet');
			return;
		}
		
		let userDefinedCount = 0;
		window.ghlSchemas.forEach(function(schema) {
			if (schema.type === 'USER_DEFINED') {
				const label = schema.labels?.singular || schema.key;
				$select.append(`<option value="${schema.id}" data-key="${schema.key}">${escapeHtml(label)}</option>`);
				userDefinedCount++;
			}
		});
		
		console.log(`Loaded ${userDefinedCount} USER_DEFINED custom objects out of ${window.ghlSchemas.length} total schemas`);
	}

	/**
	 * Load GHL object fields
	 */
	function loadGHLObjectFields(schemaId) {
		$.ajax({
			url: ghl_crm_custom_objects_js_data.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_schema_details',
				nonce: ghl_crm_custom_objects_js_data.nonces.customObjects,
				schema_id: schemaId
			},
			success: function(response) {
				if (response.success && response.data.schema) {
					processSchemaFields(response.data.schema);
				} else {
					console.error('Failed to load schema details:', response);
					const cachedSchema = window.ghlSchemas.find(s => s.id === schemaId);
					if (cachedSchema) {
						processSchemaFields(cachedSchema);
					}
				}
			},
			error: function(xhr, status, error) {
				console.error('Error loading schema details:', error);
				const cachedSchema = window.ghlSchemas.find(s => s.id === schemaId);
				if (cachedSchema) {
					processSchemaFields(cachedSchema);
				}
			}
		});
	}
	
	/**
	 * Process schema fields and populate dropdowns
	 */
	function processSchemaFields(schema) {
		if (!schema) return;
		
		ghlFieldOptions = [];
		
		const objectKey = schema.key || '';
		const schemaId = schema.id || '';
		
		console.log('Loading fields for schema:', schemaId);
		console.log('Object key:', objectKey);
		console.log('Schema fields:', schema.fields);
		
		// Only add fields from schema.fields array - show ALL fields
		if (schema.fields && Array.isArray(schema.fields) && schema.fields.length > 0) {
			schema.fields.forEach(function(field) {
				const fieldKey = field.fieldKey || field.key || field.name || field.id;
				if (!fieldKey) return;
				
				const displayName = field.name || field.label || fieldKey.split('.').pop();
				const fieldType = field.dataType || field.type || 'TEXT';
				const isRequired = field.required || false;
				
				ghlFieldOptions.push({
					id: fieldKey,
					text: displayName + (isRequired ? ' (Required)' : '') + ' [' + fieldType + ']',
					required: isRequired
				});
			});
			
			console.log('Loaded ALL fields from schema.fields array');
		} else {
			console.log('No fields array found or fields array is empty');
		}
		
		console.log('Total GHL field options:', ghlFieldOptions.length, ghlFieldOptions);
		
		updateGHLFieldDropdowns();
	}

	/**
	 * Update GHL field dropdowns
	 */
	function updateGHLFieldDropdowns() {
		$('.ghl-field-select').each(function() {
			const currentValue = $(this).val();
			$(this).empty();
			
			if (ghlFieldOptions.length === 0) {
				$(this).append('<option value="">' + ghl_crm_custom_objects_js_data.i18n.selectGHLObjectFirst + '</option>');
			} else {
				$(this).append('<option value="">' + ghl_crm_custom_objects_js_data.i18n.selectGHLField + '</option>');
				
				ghlFieldOptions.forEach(function(field) {
					const $option = $(`<option value="${field.id}">${escapeHtml(field.text)}</option>`);
					if (field.required) {
						$option.attr('data-required', 'true');
					}
					$(this).append($option);
				}.bind(this));
				
				$(this).append('<option value="__custom__">' + ghl_crm_custom_objects_js_data.i18n.enterCustomFieldKey + '</option>');
				
				if (currentValue) {
					$(this).val(currentValue);
				}
			}
		});
	}

	/**
	 * Add field mapping row
	 */
	function addFieldMappingRow(wpField = '', ghlField = '', transform = 'none') {
		const rowId = 'mapping-row-' + Date.now();
		
		// Build WP field options HTML
		let wpFieldOptionsHtml = '<option value="">Select WordPress Field...</option>';
		
		if (wpFieldOptions && wpFieldOptions.length > 0) {
			wpFieldOptions.forEach(function(group) {
				wpFieldOptionsHtml += `<optgroup label="${escapeHtml(group.label)}">`;
				group.options.forEach(function(option) {
					const selected = wpField === option.value ? 'selected' : '';
					wpFieldOptionsHtml += `<option value="${escapeHtml(option.value)}" ${selected}>${escapeHtml(option.label)}</option>`;
				});
				wpFieldOptionsHtml += '</optgroup>';
			});
		} else {
			// Fallback to basic options if no fields loaded yet
			wpFieldOptionsHtml += `
				<optgroup label="Core Fields">
					<option value="post_title">Title</option>
					<option value="post_content">Content</option>
					<option value="post_excerpt">Excerpt</option>
				</optgroup>
			`;
		}
		
		const row = `
			<tr class="ghl-mapping-row" data-row-id="${rowId}">
				<td>
					<select name="field_mappings[${rowId}][wp_field]" class="wp-field-select" required>
						${wpFieldOptionsHtml}
					</select>
					<input type="text" name="field_mappings[${rowId}][wp_field_name]" class="wp-field-name" placeholder="${ghl_crm_custom_objects_js_data.i18n.fieldNamePlaceholder}" style="display:none; margin-top: 5px; width: 100%;">
				</td>
				<td class="arrow-cell">→</td>
				<td>
					<select name="field_mappings[${rowId}][ghl_field]" class="ghl-field-select" required>
						<option value="">${ghl_crm_custom_objects_js_data.i18n.selectGHLField}</option>
						<option value="__custom__">${ghl_crm_custom_objects_js_data.i18n.enterCustomFieldKey}</option>
					</select>
					<input type="text" name="field_mappings[${rowId}][ghl_field_custom]" class="ghl-field-custom" placeholder="${ghl_crm_custom_objects_js_data.i18n.customFieldPlaceholder}" style="display:none; margin-top: 5px; width: 100%;">
				</td>
				<td>
					<select name="field_mappings[${rowId}][transform]">
						<option value="none">${ghl_crm_custom_objects_js_data.i18n.transformNone}</option>
						<option value="sanitize">${ghl_crm_custom_objects_js_data.i18n.transformSanitize}</option>
						<option value="number">${ghl_crm_custom_objects_js_data.i18n.transformNumber}</option>
						<option value="date_iso">${ghl_crm_custom_objects_js_data.i18n.transformDateISO}</option>
						<option value="strip_html">${ghl_crm_custom_objects_js_data.i18n.transformStripHTML}</option>
						<option value="json_encode">${ghl_crm_custom_objects_js_data.i18n.transformJSON}</option>
					</select>
				</td>
				<td>
					<button type="button" class="button remove-mapping" data-row-id="${rowId}">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</td>
			</tr>
		`;
		
		$('#field-mappings-body').append(row);
		
		// Set GHL field if provided
		if (ghlField) {
			$(`tr[data-row-id="${rowId}"] .ghl-field-select`).val(ghlField);
		}
		
		// Set transform if provided
		if (transform) {
			$(`tr[data-row-id="${rowId}"] select[name*="[transform]"]`).val(transform);
		}
		
		// Show field name input for custom WP fields
		$(`tr[data-row-id="${rowId}"] .wp-field-select`).on('change', function() {
			const $row = $(this).closest('tr');
			const $nameInput = $row.find('.wp-field-name');
			const value = $(this).val();
			
			// Check if it's a meta, acf, or taxonomy field that needs extra input
			if (value && (value.indexOf(':') === -1 && ['post_meta', 'acf_field', 'taxonomy', 'static'].includes(value))) {
				$nameInput.show().prop('required', true);
			} else {
				$nameInput.hide().prop('required', false);
			}
		});
		
		// Show custom field input for manual GHL field entry
		$(`tr[data-row-id="${rowId}"] .ghl-field-select`).on('change', function() {
			const $row = $(this).closest('tr');
			const $customInput = $row.find('.ghl-field-custom');
			const $select = $(this);
			
			if ($(this).val() === '__custom__') {
				$customInput.show().prop('required', true);
				$select.prop('required', false);
			} else {
				$customInput.hide().prop('required', false);
				$select.prop('required', true);
			}
		});
		
		updateGHLFieldDropdowns();
		
		// Set values if provided
		if (wpField) {
			$(`tr[data-row-id="${rowId}"] .wp-field-select`).val(wpField).trigger('change');
		}
		if (ghlField) {
			$(`tr[data-row-id="${rowId}"] .ghl-field-select`).val(ghlField);
		}
		if (transform) {
			$(`tr[data-row-id="${rowId}"] select[name*="[transform]"]`).val(transform);
		}
	}

	/**
	 * Remove field mapping row
	 */
	$(document).on('click', '.remove-mapping', function() {
		const rowId = $(this).data('row-id');
		$(`tr[data-row-id="${rowId}"]`).remove();
	});

	/**
	 * Save mapping
	 */
	function saveMapping(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $spinner = $form.find('.spinner');
		
		const mappingData = {
			action: 'ghl_crm_save_mapping',
			nonce: ghl_crm_custom_objects_js_data.nonces.mappings,
			mapping_id: $('#mapping-id').val(),
			mapping_name: $('#mapping-name').val(),
			wp_post_type: $('#wp-post-type').val(),
			ghl_object: $('#ghl-object').val(),
			ghl_object_key: $('#ghl-object option:selected').data('key'),
			mapping_active: $('#mapping-active').is(':checked'),
			triggers: $('input[name="triggers[]"]:checked').map(function() { return $(this).val(); }).get(),
			contact_source: $('#contact-source').val(),
			contact_field: $('#contact-field').val(),
			contact_not_found: $('#contact-not-found').val(),
			associations: [],
			field_mappings: [],
			enable_batch_sync: $('input[name="enable_batch_sync"]').is(':checked'),
			log_sync_operations: $('input[name="log_sync_operations"]').is(':checked')
		};
		
		// Build associations array (new format)
		// Primary contact association
		const primarySource = $('#contact-source').val();
		const primaryField = $('#contact-field').val();
		const primaryNotFound = $('#contact-not-found').val();
		
		if (primarySource) {
			mappingData.associations.push({
				target_type: 'contact',
				source: primarySource,
				source_field: primaryField,
				not_found_action: primaryNotFound,
				association_key: '' // Will be determined from schema
			});
		}
		
		// Secondary contact associations
		$('input[name="secondary_contacts[]"]:checked').each(function() {
			const secondarySource = $(this).val();
			mappingData.associations.push({
				target_type: 'contact',
				source: secondarySource,
				source_field: '',
				not_found_action: primaryNotFound, // Use same action as primary
				association_key: '' // Will be determined from schema
			});
		});
		
		// Collect field mappings
		$('#field-mappings-body tr').each(function() {
			const $row = $(this);
			const wpField = $row.find('.wp-field-select').val();
			const wpFieldName = $row.find('.wp-field-name').val();
			let ghlField = $row.find('.ghl-field-select').val();
			const ghlFieldCustom = $row.find('.ghl-field-custom').val();
			const transform = $row.find('select[name*="[transform]"]').val();
			
			if (ghlField === '__custom__' && ghlFieldCustom) {
				ghlField = ghlFieldCustom;
			}
			
			if (wpField && ghlField && ghlField !== '__custom__') {
				mappingData.field_mappings.push({
					wp_field: wpField,
					wp_field_name: wpFieldName,
					ghl_field: ghlField,
					transform: transform
				});
			}
		});
		
		$spinner.addClass('is-active');
		
		$.ajax({
			url: ghl_crm_custom_objects_js_data.ajaxUrl,
			type: 'POST',
			data: mappingData,
			success: function(response) {
				if (response.success) {
					$('#ghl-mapping-modal').fadeOut(200);
					loadMappings();
					Swal.fire({
						icon: 'success',
						title: ghl_crm_custom_objects_js_data.i18n.mappingSaved,
						showConfirmButton: false,
						timer: 1500
					});
				} else {
					Swal.fire({
						icon: 'error',
						title: ghl_crm_custom_objects_js_data.i18n.error,
						text: response.data.message,
						confirmButtonColor: '#d63638'
					});
				}
			},
			error: function() {
				Swal.fire({
					icon: 'error',
					title: ghl_crm_custom_objects_js_data.i18n.networkError,
					confirmButtonColor: '#d63638'
				});
			},
			complete: function() {
				$spinner.removeClass('is-active');
			}
		});
	}

	/**
	 * Load mappings list
	 */
	function loadMappings() {
		$.ajax({
			url: ghl_crm_custom_objects_js_data.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ghl_crm_get_mappings',
				nonce: ghl_crm_custom_objects_js_data.nonces.mappings
			},
			success: function(response) {
				if (response.success) {
					currentMappings = response.data.mappings || [];
					renderMappings(currentMappings);
				}
			}
		});
	}

	/**
	 * Render mappings list
	 */
	function renderMappings(mappings) {
		const $container = $('#ghl-mappings-list');
		
		if (!mappings || mappings.length === 0) {
			$container.html(`
				<div class="ghl-empty-state">
					<span class="dashicons dashicons-admin-settings"></span>
					<h3>${ghl_crm_custom_objects_js_data.i18n.noMappingsCreated}</h3>
					<p>${ghl_crm_custom_objects_js_data.i18n.createMappingMessage}</p>
				</div>
			`);
			return;
		}
		
		let html = '';
		mappings.forEach(function(mapping) {
			const statusClass = mapping.active ? 'active' : 'inactive';
			const statusLabel = mapping.active ? ghl_crm_custom_objects_js_data.i18n.active : ghl_crm_custom_objects_js_data.i18n.inactive;
			
			html += `
				<div class="ghl-mapping-card ${statusClass}">
					<div class="ghl-mapping-card-header">
						<h3 class="ghl-mapping-title">${escapeHtml(mapping.name)}</h3>
						<span class="ghl-mapping-status ${statusClass}">${statusLabel}</span>
					</div>
					<div class="ghl-mapping-meta">
						<span><strong>${ghl_crm_custom_objects_js_data.i18n.cpt}:</strong> ${escapeHtml(mapping.wp_post_type_label || mapping.wp_post_type)}</span>
						<span>→</span>
						<span><strong>${ghl_crm_custom_objects_js_data.i18n.ghlObject}:</strong> ${escapeHtml(mapping.ghl_object_label || mapping.ghl_object_key)}</span>
						<span>|</span>
						<span><strong>${ghl_crm_custom_objects_js_data.i18n.fields}:</strong> ${mapping.field_mappings ? mapping.field_mappings.length : 0}</span>
					</div>
					<div class="ghl-mapping-actions">
						<button type="button" class="ghl-button ghl-button-secondary edit-mapping" data-mapping-id="${mapping.id}">
							<span class="dashicons dashicons-edit"></span>
							${ghl_crm_custom_objects_js_data.i18n.edit}
						</button>
						<button type="button" class="ghl-button ghl-button-secondary delete-mapping" data-mapping-id="${mapping.id}">
							<span class="dashicons dashicons-trash"></span>
							${ghl_crm_custom_objects_js_data.i18n.delete}
						</button>
					</div>
				</div>
			`;
		});
		
		$container.html(html);
	}

	/**
	 * Edit mapping
	 */
	function editMapping() {
		const mappingId = $(this).data('mapping-id');
		openMappingModal(mappingId);
	}

	/**
	 * Delete mapping
	 */
	function deleteMapping() {
		const mappingId = $(this).data('mapping-id');
		
		Swal.fire({
			title: ghl_crm_custom_objects_js_data.i18n.confirmDelete,
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d63638',
			cancelButtonColor: '#2271b1',
			confirmButtonText: ghl_crm_custom_objects_js_data.i18n.delete || 'Delete',
			cancelButtonText: ghl_crm_custom_objects_js_data.i18n.cancel || 'Cancel'
		}).then((result) => {
			if (result.isConfirmed) {
				$.ajax({
					url: ghl_crm_custom_objects_js_data.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ghl_crm_delete_mapping',
						nonce: ghl_crm_custom_objects_js_data.nonces.mappings,
						mapping_id: mappingId
					},
					success: function(response) {
						if (response.success) {
							Swal.fire({
								icon: 'success',
								title: ghl_crm_custom_objects_js_data.i18n.deleted || 'Deleted!',
								text: ghl_crm_custom_objects_js_data.i18n.mappingDeleted || 'Mapping has been deleted.',
								showConfirmButton: false,
								timer: 1500
							});
							loadMappings();
						} else {
							Swal.fire({
								icon: 'error',
								title: ghl_crm_custom_objects_js_data.i18n.errorDeletingMapping,
								confirmButtonColor: '#d63638'
							});
						}
					},
					error: function() {
						Swal.fire({
							icon: 'error',
							title: ghl_crm_custom_objects_js_data.i18n.networkError,
							confirmButtonColor: '#d63638'
						});
					}
				});
			}
		});
	}

	/**
	 * Populate form with existing mapping data
	 */
	function populateMappingForm(mapping) {
		console.log('Populating form with mapping:', mapping);
		
		$('#mapping-id').val(mapping.id);
		$('#mapping-name').val(mapping.name);
		$('#mapping-active').prop('checked', mapping.active);
		
		$('input[name="triggers[]"]').prop('checked', false);
		if (mapping.triggers) {
			mapping.triggers.forEach(function(trigger) {
				$(`input[name="triggers[]"][value="${trigger}"]`).prop('checked', true);
			});
		}
		
		// Extract primary contact source and other association data
		let primarySource = '';
		let primaryField = '';
		let primaryNotFound = 'skip';
		let secondaryAssociations = [];
		
		// Handle associations (new format) or legacy contact_source
		if (mapping.associations && mapping.associations.length > 0) {
			// New format: use associations array
			const primaryAssoc = mapping.associations[0];
			primarySource = primaryAssoc.source || '';
			primaryField = primaryAssoc.source_field || '';
			primaryNotFound = primaryAssoc.not_found_action || 'skip';
			secondaryAssociations = mapping.associations.slice(1);
		} else {
			// Legacy format: use contact_source
			primarySource = mapping.contact_source || '';
			primaryField = mapping.contact_field || '';
			primaryNotFound = mapping.contact_not_found || 'skip';
		}
		
		$('input[name="enable_batch_sync"]').prop('checked', mapping.enable_batch_sync);
		$('input[name="log_sync_operations"]').prop('checked', mapping.log_sync_operations);
		
		$('#ghl-object').val(mapping.ghl_object);
		loadGHLObjectFields(mapping.ghl_object);
		
		// Load CPT fields for the selected post type (already set by loadPostTypes callback)
		// Pass the primary contact source so it gets selected when dropdown is rebuilt
		if (mapping.wp_post_type) {
			loadCPTFields(mapping.wp_post_type, function() {
				// After CPT fields are loaded and contact dropdown is rebuilt with correct value,
				// set the other contact fields
				$('#contact-field').val(primaryField);
				$('#contact-not-found').val(primaryNotFound);
				
				// Check secondary contact checkboxes
				$('input[name="secondary_contacts[]"]').prop('checked', false);
				secondaryAssociations.forEach(function(assoc) {
					$(`input[name="secondary_contacts[]"][value="${assoc.source}"]`).prop('checked', true);
				});
				
				// Re-apply triggers after CPT fields are loaded
				if (mapping.triggers) {
					mapping.triggers.forEach(function(trigger) {
						$(`input[name="triggers[]"][value="${trigger}"]`).prop('checked', true);
					});
				}
				
				// Wait for GHL fields to load, then populate field mappings
				const checkGHLFieldsLoaded = setInterval(function() {
					if (ghlFieldOptions.length > 0) {
						clearInterval(checkGHLFieldsLoaded);
						populateFieldMappings(mapping);
					}
				}, 100);
			}, primarySource); // Pass the primary source to loadCPTFields
		}
	}
	
	/**
	 * Populate field mappings after data is ready
	 */
	function populateFieldMappings(mapping) {
		console.log('Both post types and GHL fields loaded - populating field mappings');
		
		$('#field-mappings-body').empty();
		
		if (mapping.field_mappings && mapping.field_mappings.length > 0) {
			mapping.field_mappings.forEach(function(fieldMap) {
				addFieldMappingRow(fieldMap.wp_field, fieldMap.ghl_field, fieldMap.transform);
				
				if (fieldMap.wp_field_name) {
					const $lastRow = $('#field-mappings-body tr:last');
					$lastRow.find('.wp-field-name').val(fieldMap.wp_field_name);
				}
			});
		} else {
			addFieldMappingRow();
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
	 * Show error message
	 */
	function showError(message) {
		const $container = $('#ghl-schemas-container');x
		$container.html(`
			<div class="notice notice-error">
				<p><strong>${ghl_crm_custom_objects_js_data.i18n.error}:</strong> ${escapeHtml(message)}</p>
			</div>
		`);
	}

	// Expose init function globally for SPA router
	window.initCustomObjects = init;

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);