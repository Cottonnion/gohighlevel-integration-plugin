/**
 * GHL Form Auto-fill
 *
 * Detects GoHighLevel form iframes and modifies their src URL to pre-fill fields
 * with logged-in user data
 *
 * @package GHL_CRM_Integration
 */
(function() {
	'use strict';

	/**
	 * Auto-fill handler for GHL forms
	 */
	class GHLFormAutoFill {
		constructor() {
			this.initialized = false;
			this.ghlDomains = [
				'link.leadconnectorhq.com',
                'link.jorgediaz.online',
				'link.msgsndr.com',
				'form.leadconnectorhq.com'
			];
			
			// Load configuration from localized data
			this.config = typeof ghl_form_autofill_data !== 'undefined' ? ghl_form_autofill_data : {};
			this.userData = this.config.userData || {};
			this.formSettings = this.config.formSettings || {};
			this.whiteLabelDomain = this.config.whiteLabelDomain || '';

            console.log('config', this.config);
			
			// Add white label domain to recognized domains if provided
			if (this.whiteLabelDomain) {
				try {
					const domain = new URL(this.whiteLabelDomain).hostname;
					if (domain && !this.ghlDomains.includes(domain)) {
						this.ghlDomains.push(domain);
						console.log('[GHL Form Auto-fill] Added white label domain:', domain);
					}
				} catch (e) {
					console.warn('[GHL Form Auto-fill] Invalid white label domain:', this.whiteLabelDomain);
				}
			}
			
			console.log('[GHL Form Auto-fill] Configuration loaded:', {
				hasUserData: Object.keys(this.userData).length > 0,
				formSettingsCount: Object.keys(this.formSettings).length,
				whiteLabelDomain: this.whiteLabelDomain,
				recognizedDomains: this.ghlDomains
			});
		}

		/**
		 * Initialize the auto-fill functionality
		 */
		init() {
			if (this.initialized) {
				return;
			}

			console.log('[GHL Form Auto-fill] Initializing...');
			
			// Wait for DOM to be ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', () => this.processIframes());
			} else {
				this.processIframes();
			}

			// Watch for dynamically added iframes
			this.observeNewIframes();
			
			this.initialized = true;
		}

		/**
		 * Check if iframe is a GHL form
		 */
		isGHLFormIframe(iframe) {
			const src = iframe.getAttribute('src');
			if (!src) {
				return false;
			}

			// Check if src contains any known GHL domain
			return this.ghlDomains.some(domain => src.includes(domain));
		}

	/**
	 * Get pre-fill data from WordPress user (via localized script)
	 * Falls back to test data if no user data available
	 */
	getPreFillData() {
		// Check if user data was localized by WordPress
		if (Object.keys(this.userData).length > 0) {
			console.log('[GHL Form Auto-fill] Using real WordPress user data:', this.userData);
			return this.userData;
		}

		// Fallback: No user logged in or no data available
		console.log('[GHL Form Auto-fill] No user data available (user not logged in)');
		return {};
	}

	/**
	 * Extract form ID from iframe src URL
	 */
	extractFormId(src) {
		try {
			const url = new URL(src);
			const pathParts = url.pathname.split('/');
			// Form ID is typically the last part of the path
			const formId = pathParts[pathParts.length - 1];
			return formId || null;
		} catch (e) {
			return null;
		}
	}

	/**
	 * Check if auto-fill is enabled for this form
	 */
	isAutofillEnabled(formId) {
		if (!formId) {
			return true; // Default to enabled if we can't determine form ID
		}
		
		const settings = this.formSettings[formId];
		if (!settings) {
			return true; // Default to enabled if no settings found
		}
		
		// Check the autofill_enabled setting
		return settings.autofill_enabled !== false;
	}

	/**
	 * Get custom parameters for a form with JS variables replaced
	 */
	getCustomParams(formId) {
		if (!formId) {
			return {};
		}
		
		const settings = this.formSettings[formId];
		if (!settings || !settings.resolved_params) {
			return {};
		}
		
		const params = {...settings.resolved_params};
		
		// Replace JS-only variables (current_url, current_title)
		Object.keys(params).forEach(key => {
			let value = params[key];
			
			// Replace {current_url}
			if (value.includes('{current_url}')) {
				value = value.replace('{current_url}', window.location.href);
			}
			
			// Replace {current_title}
			if (value.includes('{current_title}')) {
				value = value.replace('{current_title}', document.title);
			}
			
			params[key] = value;
		});
		
		return params;
	}

	/**
	 * Build query string from pre-fill data
	 */
	buildQueryString(data) {
			const params = new URLSearchParams();
			
			for (const [key, value] of Object.entries(data)) {
				if (value) {
					params.append(key, value);
				}
			}

			return params.toString();
		}

	/**
	 * Modify iframe src to include pre-fill parameters
	 */
	modifyIframeSrc(iframe) {
		const currentSrc = iframe.getAttribute('src');
		if (!currentSrc) {
			return;
		}

		// Check if already modified (avoid duplicate processing)
		if (iframe.dataset.ghlAutofilled === 'true') {
			console.log('[GHL Form Auto-fill] Iframe already processed, skipping:', currentSrc);
			return;
		}

		try {
			const url = new URL(currentSrc);
			
			// Extract form ID and check if auto-fill is enabled
			const formId = this.extractFormId(currentSrc);
			if (!this.isAutofillEnabled(formId)) {
				console.log('[GHL Form Auto-fill] Auto-fill disabled for form:', formId);
				iframe.dataset.ghlAutofilled = 'skipped';
				return;
			}
			
			// Start with auto-fill data
			const preFillData = this.getPreFillData();
			
			// Get custom parameters
			const customParams = this.getCustomParams(formId);
			
			// Merge: auto-fill first, then custom params override
			const finalParams = { ...preFillData, ...customParams };
			
			// Only proceed if we have data to add
			if (Object.keys(finalParams).length === 0) {
				console.log('[GHL Form Auto-fill] No data to add to form');
				return;
			}
			
			// Add all parameters to URL
			for (const [key, value] of Object.entries(finalParams)) {
				// Don't overwrite existing parameters in the URL
				if (!url.searchParams.has(key) && value) {
					url.searchParams.set(key, value);
				}
			}

			const newSrc = url.toString();			// Only update if URL actually changed
			if (newSrc !== currentSrc) {
				console.log('[GHL Form Auto-fill] Modifying iframe src:');
				console.log('  Form ID:', formId);
				console.log('  Original:', currentSrc);
				console.log('  Modified:', newSrc);
				console.log('  Auto-fill data:', preFillData);
				console.log('  Custom params:', customParams);
				console.log('  Final merged params:', finalParams);
				
				iframe.setAttribute('src', newSrc);
				iframe.dataset.ghlAutofilled = 'true';
			}
		} catch (error) {
			console.error('[GHL Form Auto-fill] Error modifying iframe src:', error);
		}
	}		/**
		 * Process all existing iframes on the page
		 */
		processIframes() {
			const iframes = document.querySelectorAll('iframe');
			let ghlFormsFound = 0;

			console.log(`[GHL Form Auto-fill] Scanning ${iframes.length} iframes...`);

			iframes.forEach(iframe => {
				if (this.isGHLFormIframe(iframe)) {
					ghlFormsFound++;
					this.modifyIframeSrc(iframe);
				}
			});

			if (ghlFormsFound > 0) {
				console.log(`[GHL Form Auto-fill] Found and processed ${ghlFormsFound} GHL form(s)`);
			} else {
				console.log('[GHL Form Auto-fill] No GHL forms found on this page');
			}
		}

		/**
		 * Watch for dynamically added iframes
		 */
		observeNewIframes() {
			const observer = new MutationObserver(mutations => {
				mutations.forEach(mutation => {
					mutation.addedNodes.forEach(node => {
						// Check if the added node is an iframe
						if (node.nodeName === 'IFRAME' && this.isGHLFormIframe(node)) {
							console.log('[GHL Form Auto-fill] New GHL iframe detected');
							this.modifyIframeSrc(node);
						}
						
						// Check if the added node contains iframes
						if (node.querySelectorAll) {
							const iframes = node.querySelectorAll('iframe');
							iframes.forEach(iframe => {
								if (this.isGHLFormIframe(iframe)) {
									console.log('[GHL Form Auto-fill] New GHL iframe detected (nested)');
									this.modifyIframeSrc(iframe);
								}
							});
						}
					});
				});
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true
			});

			console.log('[GHL Form Auto-fill] MutationObserver active - watching for new iframes');
		}
	}

	// Initialize auto-fill when script loads
	const autoFill = new GHLFormAutoFill();
	autoFill.init();

	// Expose for manual testing
	window.ghlFormAutoFill = autoFill;
	window.testGHLAutoFill = function() {
		console.log('[GHL Form Auto-fill] Manual test triggered');
		autoFill.processIframes();
	};

	// Initialize submission tracking
	function initSubmissionTracking() {
		// Only track forms that need it
		const trackedForms = new Set();
		
		document.querySelectorAll('.ghl-form-wrapper[data-track-submission="1"]').forEach(wrapper => {
			const formId = wrapper.dataset.formId;
			if (formId) {
				trackedForms.add(formId);
			}
		});

		// Listen for GHL postMessage events
		window.addEventListener('message', function(event) {
			// Check if from GHL domain
			const ghlDomains = autoFill.ghlDomains;
			let isGHLDomain = false;
			
			try {
				const origin = new URL(event.origin).hostname;
				isGHLDomain = ghlDomains.some(domain => origin.includes(domain));
			} catch (e) {
				return;
			}

			if (!isGHLDomain) return;

			// GHL sends: ['set-sticky-contacts', 'key', '{"customer_id": ...}', ...]
			if (Array.isArray(event.data) && event.data[0] === 'set-sticky-contacts') {
				const dataString = event.data[2];
				
				if (dataString && typeof dataString === 'string') {
					try {
						const parsedData = JSON.parse(dataString);
						// Successful submission detected
						if (parsedData.customer_id || parsedData.email) {
							// Find which form iframe sent this
							const iframes = document.querySelectorAll('iframe[data-form-id]');
							iframes.forEach(iframe => {
								if (iframe.contentWindow === event.source) {
									const formId = iframe.dataset.formId;
									const wrapper = iframe.closest('.ghl-form-wrapper');
									
									if (formId && trackedForms.has(formId) && wrapper) {
										markFormAsSubmitted(formId, wrapper);
									}
								}
							});
						}
					} catch (e) {
						// Not JSON, ignore
					}
				}
			}
		});
	}

	// Mark form as submitted via AJAX
	function markFormAsSubmitted(formId, wrapper) {
		const data = typeof ghl_form_autofill_data !== 'undefined' ? ghl_form_autofill_data : {};
		
		fetch(data.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				action: 'ghl_mark_form_submitted',
				nonce: data.nonce || '',
				form_id: formId
			})
		})
		.then(response => response.json())
		.then(result => {
			if (result.success) {
				// Check if should hide form or show custom message
				const settings = autoFill.formSettings[formId];
				if (settings && settings.submission_limit === 'once') {
					const message = settings.submitted_message || '';
					
					if (message) {
						// Show custom message
						wrapper.innerHTML = '<div style="padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; text-align: center;">' + 
							message.replace(/\n/g, '<br>') + 
							'</div>';
					} else {
						// Hide completely
						wrapper.innerHTML = '';
					}
				}
			}
		})
		.catch(err => console.error('[GHL Form] Error:', err));
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSubmissionTracking);
	} else {
		initSubmissionTracking();
	}

})();
