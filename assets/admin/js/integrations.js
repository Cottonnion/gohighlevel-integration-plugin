/**
 * Integrations Page JavaScript
 *
 * @package    GHL_CRM_Integration
 * @subpackage Assets/Admin/JS
 */

(function ($) {
	'use strict';

	/**
	 * Integrations Manager
	 */
	const IntegrationsManager = {
		/**
		 * Initialize
		 */
		init() {
			this.bindEvents();
			this.loadSettings();
			this.initTagsSelect();
			this.initOrderStatusSelect();
			this.initOpportunitiesSelects();
			this.initGroupTypeSelect();
		},

		/**
		 * Bind events
		 */
		bindEvents() {
			// Tab navigation
			$('.ghl-tab-button').on('click', this.switchTab.bind(this));

			// Save settings button
			$('#save-integrations-settings').on('click', this.saveSettings.bind(this));

			// WooCommerce toggles
			$('#wc_enabled').on('change', this.handleWooCommerceToggle.bind(this));
			$('#wc_convert_lead_enabled').on('change', this.handleConvertLeadToggle.bind(this));
			$('#wc_abandoned_cart_enabled').on('change', this.handleAbandonedCartToggle.bind(this));
			
			// Opportunities toggles
			$('#wc_opportunities_enabled').on('change', this.handleOpportunitiesToggle.bind(this));
			$('#wc_opportunities_pipeline').on('change', this.handlePipelineChange.bind(this));
			$('#wc_opportunities_filter_type').on('change', this.handleFilterTypeChange.bind(this));

			// Prevent form submission on enter
			$(document).on('keypress', function (e) {
				if (e.which === 13 && $(e.target).closest('.ghl-tab-panel').length) {
					e.preventDefault();
					return false;
				}
			});
		},

		/**
		 * Switch between tabs
		 */
		switchTab(e) {
			const $button = $(e.currentTarget);
			
			// Don't switch if disabled
			if ($button.prop('disabled')) {
				return;
			}

			const tabName = $button.data('tab');

			// Update active states
			$('.ghl-tab-button').removeClass('active');
			$button.addClass('active');

			$('.ghl-tab-panel').removeClass('active');
			$(`.ghl-tab-panel[data-tab="${tabName}"]`).addClass('active');
		},

		/**
		 * Load current settings
		 */
		loadSettings() {
			// Settings are already loaded in PHP template
			// This method is kept for potential AJAX reload in future
			console.log('Integrations settings loaded');
		},

		/**
		 * Initialize tags Select2 dropdowns
		 */
		initTagsSelect() {
			const $tagsSelects = $('.ghl-tags-select');

			if ($tagsSelects.length === 0 || typeof $.fn.select2 === 'undefined') {
				return;
			}

			// Initialize each Select2 dropdown
			$tagsSelects.each(function() {
				const $select = $(this);
				
				// Initialize Select2 with AJAX
				$select.select2({
					placeholder: $select.data('placeholder') || 'Select tags...',
					allowClear: true,
					width: '100%',
					closeOnSelect: false,
					ajax: {
						url: ghl_crm_integrations_js_data.ajaxUrl,
						type: 'POST',
						dataType: 'json',
						delay: 250,
						data: function(params) {
							return {
								action: 'ghl_crm_get_tags',
								nonce: ghl_crm_integrations_js_data.nonce,
								search: params.term || ''
							};
						},
						processResults: function(response, params) {
							if (!response.success || !response.data || !response.data.tags) {
								return { results: [] };
							}

							var items = response.data.tags.map(function(tag) {
								if (typeof tag === 'object' && tag !== null) {
									var label = String(tag.name || tag.id || '');
									return {
										id: label,
										text: label
									};
								}
								var value = String(tag || '');
								return {
									id: value,
									text: value
								};
							});

							if (params && params.term) {
								var term = params.term.toLowerCase();
								items = items.filter(function(item) {
									return item.text && item.text.toLowerCase().indexOf(term) !== -1;
								});
							}

							return { results: items };
						},
						cache: true
					},
					minimumInputLength: 0
				});

				// Pre-populate with saved tags from data attribute
				const savedTags = $select.data('saved-tags') || [];
				if (Array.isArray(savedTags) && savedTags.length > 0) {
					savedTags.forEach(function(tag) {
						// Create option if it doesn't exist
						if ($select.find("option[value='" + tag + "']").length === 0) {
							const newOption = new Option(tag, tag, true, true);
							$select.append(newOption);
						}
					});
					$select.val(savedTags).trigger('change');
				}
			});
		},

		/**
		 * Initialize Order Status Select2
		 */
		initOrderStatusSelect() {
			const $orderStatusSelect = $('.ghl-order-status-select');

			if ($orderStatusSelect.length === 0 || typeof $.fn.select2 === 'undefined') {
				return;
			}

			// Initialize Select2 for order status (no AJAX needed, options already in HTML)
			$orderStatusSelect.each(function() {
				const $select = $(this);
				
				$select.select2({
					placeholder: $select.data('placeholder') || 'Leave empty to convert on any order...',
					allowClear: true,
					width: '100%',
					closeOnSelect: false
				});
			});
		},

		/**
		 * Initialize Group Type Select2 for BuddyBoss
		 */
		initGroupTypeSelect() {
			const $groupTypeSelect = $('.ghl-group-type-select');

			if ($groupTypeSelect.length === 0 || typeof $.fn.select2 === 'undefined') {
				return;
			}

			// Initialize Select2 for group type (no AJAX needed, options already in HTML)
			$groupTypeSelect.each(function() {
				const $select = $(this);
				
				$select.select2({
					placeholder: $select.find('option:first').text() || 'Skip groups without a type',
					allowClear: true,
					width: '100%',
					minimumResultsForSearch: 5 // Show search if more than 5 options
				});
			});
		},

		/**
		 * Gather form data
		 */
		gatherFormData() {
			const data = {
				action: 'ghl_crm_save_integrations',
				nonce: ghl_crm_integrations_js_data.nonce,
			};

			// WooCommerce Settings
			if ($('#wc_enabled').length) {
				data.wc_enabled = $('#wc_enabled').is(':checked') ? '1' : '0';
				data.wc_convert_lead_enabled = $('#wc_convert_lead_enabled').is(':checked') ? '1' : '0';
				
				// Get customer tags (Select2 returns array)
				const customerTags = $('#wc_customer_tag').val();
				data.wc_customer_tag = Array.isArray(customerTags) ? customerTags : (customerTags ? [customerTags] : []);
				
				// Get order statuses for conversion (Select2 returns array)
				const orderStatuses = $('#wc_convert_order_statuses').val();
				data.wc_convert_order_statuses = Array.isArray(orderStatuses) ? orderStatuses : (orderStatuses ? [orderStatuses] : []);
				
				data.wc_abandoned_cart_enabled = $('#wc_abandoned_cart_enabled').is(':checked') ? '1' : '0';
				data.wc_abandoned_cart_time = $('#wc_abandoned_cart_time').val();
				
				// Get abandoned cart tags (Select2 returns array)
				const abandonedTags = $('#wc_abandoned_cart_tag').val();
				data.wc_abandoned_cart_tag = Array.isArray(abandonedTags) ? abandonedTags : (abandonedTags ? [abandonedTags] : []);

				// Opportunities Settings
				data.wc_opportunities_enabled = $('#wc_opportunities_enabled').is(':checked') ? '1' : '0';
				data.wc_opportunities_pipeline = $('#wc_opportunities_pipeline').val() || '';
				data.wc_opportunities_stage_abandoned = $('#wc_opportunities_stage_abandoned').val() || '';
				data.wc_opportunities_stage_pending = $('#wc_opportunities_stage_pending').val() || '';
				data.wc_opportunities_stage_processing = $('#wc_opportunities_stage_processing').val() || '';
				data.wc_opportunities_stage_completed = $('#wc_opportunities_stage_completed').val() || '';
				data.wc_opportunities_stage_cancelled = $('#wc_opportunities_stage_cancelled').val() || '';
				data.wc_opportunities_filter_type = $('#wc_opportunities_filter_type').val() || 'all';
				data.wc_opportunities_min_value = $('#wc_opportunities_min_value').val() || 0;

				// Get opportunities products (Select2 returns array)
				const opportunityProducts = $('#wc_opportunities_products').val();
				data.wc_opportunities_products = Array.isArray(opportunityProducts) ? opportunityProducts : (opportunityProducts ? [opportunityProducts] : []);

				// Get opportunities categories (Select2 returns array)
				const opportunityCategories = $('#wc_opportunities_categories').val();
				data.wc_opportunities_categories = Array.isArray(opportunityCategories) ? opportunityCategories : (opportunityCategories ? [opportunityCategories] : []);
			}

			// BuddyBoss Settings
			if ($('#buddyboss_groups_enabled').length) {
				data.buddyboss_groups_enabled = $('#buddyboss_groups_enabled').is(':checked') ? '1' : '0';
				data.buddyboss_auto_delete_custom_objects = $('#buddyboss_auto_delete_custom_objects').is(':checked') ? '1' : '0';
				data.buddyboss_field_length_limit = $('#buddyboss_field_length_limit').val() || 250;
				data.buddyboss_sync_private_groups = $('#buddyboss_sync_private_groups').is(':checked') ? '1' : '0';
				data.buddyboss_sync_hidden_groups = $('#buddyboss_sync_hidden_groups').is(':checked') ? '1' : '0';
				data.buddyboss_real_time_sync = $('#buddyboss_real_time_sync').is(':checked') ? '1' : '0';
				data.buddyboss_log_sync_operations = $('#buddyboss_log_sync_operations').is(':checked') ? '1' : '0';
				
				// Association behavior settings
				data.buddyboss_missing_contact_strategy = $('input[name="buddyboss_missing_contact_strategy"]:checked').val() || 'skip';
				data.buddyboss_default_group_type = $('#buddyboss_default_group_type').val() || '';
			}

			// Future: Add LearnDash settings here

			return data;
		},

		/**
		 * Save settings via AJAX
		 */
		saveSettings() {
			const $button = $('#save-integrations-settings');
			const $buttonText = $button.find('.button-text');
			const $spinner = $button.find('.spinner');

			// Store original button text if not already stored
			if (!$buttonText.data('original-text')) {
				$buttonText.data('original-text', $buttonText.text());
			}

			// Disable button and show spinner
			$button.prop('disabled', true).addClass('is-loading');
			$spinner.addClass('is-active');

			// Gather form data
			const formData = this.gatherFormData();

			// Make AJAX request
			$.ajax({
				url: ghl_crm_integrations_js_data.ajaxUrl,
				type: 'POST',
				data: formData,
				success: (response) => {
					if (response.success) {
						this.showMessage('success', response.data.message || ghl_crm_integrations_js_data.i18n.settingsSaved);
						
						// Add visual feedback to save button
						$buttonText.html('<span class="dashicons dashicons-yes-alt" style="font-size: 14px; margin-right: 4px;"></span>Saved Successfully!');
						
						// Reset button text after 3 seconds
						setTimeout(() => {
							$buttonText.text($buttonText.data('original-text') || 'Save Integration Settings');
						}, 3000);
						
						// Scroll to top to show message
						$('html, body').animate({ scrollTop: 0 }, 300);
					} else {
						this.showMessage('error', response.data.message || ghl_crm_integrations_js_data.i18n.saveFailed);
						
						// Scroll to top to show error message
						$('html, body').animate({ scrollTop: 0 }, 300);
					}
				},
				error: (xhr, status, error) => {
					console.error('Save error:', error);
					console.error('XHR response:', xhr.responseText);
					
					let errorMessage = ghl_crm_integrations_js_data.i18n.saveError || 'An error occurred while saving settings.';
					
					// Try to parse JSON response for error message
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMessage = xhr.responseJSON.data.message;
					} else if (xhr.responseText) {
						try {
							const parsed = JSON.parse(xhr.responseText);
							if (parsed.data && parsed.data.message) {
								errorMessage = parsed.data.message;
							}
						} catch (e) {
							// If parsing fails, use default error message
						}
					}
					
					this.showMessage('error', errorMessage);
					
					// Scroll to top to show error message
					$('html, body').animate({ scrollTop: 0 }, 300);
				},
				complete: () => {
					// Re-enable button and hide spinner
					$button.prop('disabled', false).removeClass('is-loading');
					$spinner.removeClass('is-active');
				},
			});
		},

		/**
		 * Handle WooCommerce main toggle
		 */
		handleWooCommerceToggle(e) {
			const $checkbox = $(e.target);
			const $label = $checkbox.closest('.ghl-checkbox');
			const $checkboxInput = $label.find('.ghl-checkbox-input');
			const $checkboxLabel = $label.find('.ghl-checkbox-label');
			const $settingsBody = $('#wc-settings-body');
			
			if ($checkbox.is(':checked')) {
				$label.addClass('is-checked');
				$checkboxInput.addClass('is-checked');
				$checkboxLabel.text('Enabled');
				$settingsBody.slideDown(300);
				
				// Show success feedback
				this.showInlineFeedback($checkbox, 'WooCommerce integration enabled', 'success');
			} else {
				$label.removeClass('is-checked');
				$checkboxInput.removeClass('is-checked');
				$checkboxLabel.text('Disabled');
				$settingsBody.slideUp(300);
				
				// Show info feedback
				this.showInlineFeedback($checkbox, 'WooCommerce integration disabled', 'info');
			}
		},

		/**
		 * Handle convert lead toggle
		 */
		handleConvertLeadToggle(e) {
			const $checkbox = $(e.target);
			const $label = $checkbox.closest('.ghl-checkbox');
			const $checkboxInput = $label.find('.ghl-checkbox-input');
			const $tagField = $('#wc-customer-tag-field');
			const $statusField = $('#wc-convert-order-status-field');
			
			if ($checkbox.is(':checked')) {
				$label.addClass('is-checked');
				$checkboxInput.addClass('is-checked');
				$tagField.slideDown(300);
				$statusField.slideDown(300);
				
				// Show success feedback
				this.showInlineFeedback($checkbox, 'Lead-to-customer conversion enabled', 'success');
			} else {
				$label.removeClass('is-checked');
				$checkboxInput.removeClass('is-checked');
				$tagField.slideUp(300);
				$statusField.slideUp(300);
				
				// Show info feedback
				this.showInlineFeedback($checkbox, 'Lead-to-customer conversion disabled', 'info');
			}
		},

		/**
		 * Handle abandoned cart toggle
		 */
		handleAbandonedCartToggle(e) {
			const $checkbox = $(e.target);
			const $label = $checkbox.closest('.ghl-checkbox');
			const $checkboxInput = $label.find('.ghl-checkbox-input');
			const $settings = $('#wc-abandoned-cart-settings');
			
			if ($checkbox.is(':checked')) {
				$label.addClass('is-checked');
				$checkboxInput.addClass('is-checked');
				$settings.slideDown(300);
				
				// Show success feedback
				this.showInlineFeedback($checkbox, 'Abandoned cart tracking enabled', 'success');
			} else {
				$label.removeClass('is-checked');
				$checkboxInput.removeClass('is-checked');
				$settings.slideUp(300);
				
				// Show info feedback
				this.showInlineFeedback($checkbox, 'Abandoned cart tracking disabled', 'info');
			}
		},

		/**
		 * Show inline feedback near an element
		 */
		showInlineFeedback($element, message, type = 'success') {
			// Remove any existing feedback
			$element.closest('.ghl-form-item').find('.ghl-inline-feedback').remove();
			
			// Determine icon and color
			let icon = 'yes-alt';
			let color = '#22c55e';
			
			if (type === 'info') {
				icon = 'info';
				color = '#3b82f6';
			} else if (type === 'error') {
				icon = 'dismiss';
				color = '#ef4444';
			}
			
			// Create feedback element
			const $feedback = $(`
				<span class="ghl-inline-feedback" style="
					display: inline-flex;
					align-items: center;
					gap: 6px;
					margin-left: 12px;
					color: ${color};
					font-size: 13px;
					font-weight: 500;
					animation: fadeInSlide 0.3s ease-out;
				">
					<span class="dashicons dashicons-${icon}" style="font-size: 16px;"></span>
					${message}
				</span>
			`);
			
			// Add animation styles if not already present
			if (!$('#ghl-inline-feedback-animation').length) {
				$('<style id="ghl-inline-feedback-animation">@keyframes fadeInSlide { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }</style>').appendTo('head');
			}
			
			// Insert feedback
			$element.closest('.ghl-checkbox-label').after($feedback);
			
			// Auto-remove after 3 seconds
			setTimeout(() => {
				$feedback.fadeOut(300, function() {
					$(this).remove();
				});
			}, 3000);
		},

		/**
		 * Initialize Opportunities selects
		 */
		initOpportunitiesSelects() {
			this.initPipelineSelect();
			this.initProductsSelect();
			this.initCategoriesSelect();
		},

		/**
		 * Initialize pipeline Select2
		 */
		initPipelineSelect() {
			const $pipelineSelect = $('#wc_opportunities_pipeline');
			
			if ($pipelineSelect.length === 0 || typeof $.fn.select2 === 'undefined') {
				return;
			}

			// Get saved value
			const savedValue = $pipelineSelect.data('saved-value');

			// Store pipelines data for stage lookup
			this.pipelinesData = {};

			// Initialize Select2
			$pipelineSelect.select2({
				placeholder: $pipelineSelect.data('placeholder') || 'Select a pipeline...',
				allowClear: true,
				width: '100%',
				ajax: {
					url: ghl_crm_integrations_js_data.ajaxUrl,
					dataType: 'json',
					delay: 250,
					data: (params) => ({
						action: 'ghl_get_pipelines',
						nonce: ghl_crm_integrations_js_data.nonce,
						search: params.term,
						page: params.page || 1
					}),
					processResults: (data) => {
						if (data.success && data.data.pipelines) {
							// Store pipelines with stages for later use
							data.data.pipelines.forEach(pipeline => {
								this.pipelinesData[pipeline.id] = pipeline;
							});

							return {
								results: data.data.pipelines.map(pipeline => ({
									id: pipeline.id,
									text: pipeline.name
								}))
							};
						}
						return { results: [] };
					},
					cache: true
				},
				minimumInputLength: 0
			});

			// Set saved value if exists
			if (savedValue) {
				// Trigger change to load stages
				setTimeout(() => {
					$pipelineSelect.val(savedValue).trigger('change');
				}, 500);
			}
		},

		/**
		 * Initialize stage selects for a pipeline
		 */
		initStageSelects(pipelineId) {
			const $stageSelects = $('.ghl-stage-select');
			
			if ($stageSelects.length === 0 || !pipelineId) {
				return;
			}

			// Get pipeline data from stored pipelines
			const pipeline = this.pipelinesData[pipelineId];
			
			if (!pipeline || !pipeline.stages) {
				// If pipeline not in cache, load all pipelines first
				this.loadPipelineStages(pipelineId, $stageSelects);
				return;
			}

			// Populate stage selects with stages from pipeline
			$stageSelects.each(function() {
				const $select = $(this);
				const savedValue = $select.data('saved-value');

				// Clear existing options
				$select.empty().append('<option value="">Select stage...</option>');
				
				// Add stage options (sorted by position)
				const sortedStages = pipeline.stages.sort((a, b) => a.position - b.position);
				sortedStages.forEach(stage => {
					const $option = $('<option></option>')
						.val(stage.id)
						.text(stage.name)
						.prop('selected', stage.id === savedValue);
					$select.append($option);
				});

				// Initialize Select2 on stage select
				$select.select2({
					placeholder: 'Select stage...',
					allowClear: true,
					width: '100%'
				});
			});
		},

		/**
		 * Load pipeline stages if not in cache
		 */
		loadPipelineStages(pipelineId, $stageSelects) {
			$.ajax({
				url: ghl_crm_integrations_js_data.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ghl_get_pipelines',
					nonce: ghl_crm_integrations_js_data.nonce,
					page: 1
				},
				success: (response) => {
					if (response.success && response.data.pipelines) {
						// Store all pipelines
						response.data.pipelines.forEach(pipeline => {
							this.pipelinesData[pipeline.id] = pipeline;
						});
						// Try again now that we have the data
						this.initStageSelects(pipelineId);
					}
				},
				error: () => {
					$stageSelects.empty().append('<option value="">Failed to load stages</option>');
				}
			});
		},

		/**
		 * Initialize products Select2 with AJAX search
		 */
		initProductsSelect() {
			const $productsSelect = $('#wc_opportunities_products');
			
			if ($productsSelect.length === 0 || typeof $.fn.select2 === 'undefined') {
				return;
			}

			$productsSelect.select2({
				placeholder: $productsSelect.data('placeholder') || 'Search and select products...',
				allowClear: true,
				width: '100%',
				closeOnSelect: false,
				ajax: {
					url: ghl_crm_integrations_js_data.ajaxUrl,
					dataType: 'json',
					delay: 250,
					data: (params) => ({
						action: 'ghl_search_products',
						nonce: ghl_crm_integrations_js_data.nonce,
						search: params.term,
						page: params.page || 1
					}),
					processResults: (data) => {
						if (data.success && data.data.products) {
							return {
								results: data.data.products.map(product => ({
									id: product.id,
									text: product.name
								}))
							};
						}
						return { results: [] };
					},
					cache: true
				},
				minimumInputLength: 2
			});
		},

		/**
		 * Initialize categories Select2
		 */
		initCategoriesSelect() {
			const $categoriesSelect = $('#wc_opportunities_categories');
			
			if ($categoriesSelect.length === 0 || typeof $.fn.select2 === 'undefined') {
				return;
			}

			$categoriesSelect.select2({
				placeholder: $categoriesSelect.data('placeholder') || 'Select categories...',
				allowClear: true,
				width: '100%',
				closeOnSelect: false
			});
		},

		/**
		 * Handle opportunities toggle
		 */
		handleOpportunitiesToggle(e) {
			const $checkbox = $(e.currentTarget);
			const isChecked = $checkbox.is(':checked');
			const $settings = $('#wc-opportunities-settings');

			if (isChecked) {
				$settings.slideDown(300);
			} else {
				$settings.slideUp(300);
			}

			// Update checkbox UI
			$checkbox.closest('.ghl-checkbox').toggleClass('is-checked', isChecked);
			$checkbox.siblings('.ghl-checkbox-input').toggleClass('is-checked', isChecked);
		},

		/**
		 * Handle pipeline change - load stages
		 */
		handlePipelineChange(e) {
			const $select = $(e.currentTarget);
			const pipelineId = $select.val();
			const $stageMapping = $('#wc-opportunities-stage-mapping');

			if (pipelineId) {
				$stageMapping.slideDown(300);
				this.initStageSelects(pipelineId);
			} else {
				$stageMapping.slideUp(300);
			}
		},

		/**
		 * Handle filter type change
		 */
		handleFilterTypeChange(e) {
			const $select = $(e.currentTarget);
			const filterType = $select.val();

			// Hide all filter sections
			$('#wc-opportunities-products-filter').hide();
			$('#wc-opportunities-categories-filter').hide();
			$('#wc-opportunities-minvalue-filter').hide();

			// Show selected filter section
			switch(filterType) {
				case 'products':
					$('#wc-opportunities-products-filter').slideDown(300);
					break;
				case 'categories':
					$('#wc-opportunities-categories-filter').slideDown(300);
					break;
				case 'min_value':
					$('#wc-opportunities-minvalue-filter').slideDown(300);
					break;
			}
		},

		/**
		 * Show message
		 */
		showMessage(type, message) {
			const $container = $('#ghl-integrations-messages');
			const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';

			const html = `
				<div class="notice ${noticeClass} is-dismissible">
					<p>${message}</p>
				</div>
			`;

			// Remove existing messages
			$container.empty();

			// Add new message
			$container.html(html);

			// Auto-dismiss after 5 seconds
			setTimeout(() => {
				$container.find('.notice').fadeOut(300, function () {
					$(this).remove();
				});
			}, 5000);

			// Handle dismiss button click
			$container.find('.notice-dismiss').on('click', function () {
				$(this).closest('.notice').fadeOut(300, function () {
					$(this).remove();
				});
			});
		},
	};

	/**
	 * Initialize integrations functionality
	 */
	function initIntegrations() {
		IntegrationsManager.init();
	}

	// Export to global scope for SPA to call
	window.initIntegrations = initIntegrations;

	// Initialize on document ready (for non-SPA page loads)
	$(document).ready(initIntegrations);

})(jQuery);
