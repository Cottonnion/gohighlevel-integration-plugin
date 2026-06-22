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
				'link.gohighlevel.com',
				'link.msgsndr.com',
				'form.leadconnectorhq.com'
			];
			
			// Load configuration from localized data
			this.config = typeof ghl_form_autofill_data !== 'undefined' ? ghl_form_autofill_data : {};
			this.userData = this.config.userData || {};
			this.formSettings = this.config.formSettings || {};
			this.whiteLabelDomain = this.config.whiteLabelDomain || '';
			
			// Add white label domain to recognized domains if provided
			if (this.whiteLabelDomain) {
				try {
					const domain = new URL(this.whiteLabelDomain).hostname;
					if (domain && !this.ghlDomains.includes(domain)) {
						this.ghlDomains.push(domain);
				}
			} catch (e) {
				}
			}
		}

		/**
		 * Initialize the auto-fill functionality
		 */
		init() {
			if (this.initialized) {
				return;
			}
			
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
			return this.userData;
		}

		// Fallback: No user logged in or no data available
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
			return;
		}

		try {
			const url = new URL(currentSrc);
			
			// Extract form ID and check if auto-fill is enabled
			const formId = this.extractFormId(currentSrc);
			if (!this.isAutofillEnabled(formId)) {
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
				iframe.setAttribute('src', newSrc);
				iframe.dataset.ghlAutofilled = 'true';
			}
		} catch (error) {
			// Silent fail
		}
	}		/**
		 * Process all existing iframes on the page
		 */
		processIframes() {
			const iframes = document.querySelectorAll('iframe');

			iframes.forEach(iframe => {
				if (this.isGHLFormIframe(iframe)) {
					this.modifyIframeSrc(iframe);
				}
			});
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

							this.modifyIframeSrc(node);
						}
						
						// Check if the added node contains iframes
						if (node.querySelectorAll) {
							const iframes = node.querySelectorAll('iframe');
							iframes.forEach(iframe => {
								if (this.isGHLFormIframe(iframe)) {

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
		}
	}

	// Initialize auto-fill when script loads
	const autoFill = new GHLFormAutoFill();
	autoFill.init();

	// Expose for manual testing
	window.ghlFormAutoFill = autoFill;
	window.testGHLAutoFill = function() {
		autoFill.processIframes();
	};

})();
