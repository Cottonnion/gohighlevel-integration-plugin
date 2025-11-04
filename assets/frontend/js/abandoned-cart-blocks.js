/**
 * Abandoned Cart Tracking for WooCommerce Blocks
 *
 * Tracks cart data when using WooCommerce Checkout Block
 *
 * @package GHL_CRM
 */

(function ($) {
	'use strict';

	// Wait for WooCommerce Blocks checkout to load
	const { subscribe, select } = wp.data;
	const CHECKOUT_STORE_KEY = 'wc/store/checkout';

	let lastEmail = '';
	let debounceTimer = null;

	/**
	 * Send cart data to server
	 */
	function sendCartData(billingData) {
		const email = billingData.email || '';
		
		console.log('GHL: sendCartData called with email:', email);
		
		// Only send if email is valid and changed
		if (!email || email === lastEmail || !email.includes('@')) {
			console.log('GHL: Skipping - invalid or duplicate email');
			return;
		}

		lastEmail = email;
		console.log('GHL: Preparing to send cart data for', email);

		// Debounce to avoid excessive requests
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(() => {
			const payload = {
				email: email,
				first_name: billingData.first_name || '',
				last_name: billingData.last_name || '',
				phone: billingData.phone || '',
				cart_key: ghlAbandonedCart.cartKey || '',
				user_id: ghlAbandonedCart.userId || 0
			};
			
			console.log('GHL: Sending payload:', payload);
			
			fetch(ghlAbandonedCart.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify(payload)
			}).then(response => {
				if (response.ok) {
					return response.json().then(data => {
						console.log('GHL: Cart data tracked for', email, '- Cart Key:', data.cart_key);
					});
				} else {
					response.json().then(data => {
						console.error('GHL: Error response:', data);
					});
				}
			}).catch(error => {
				console.error('GHL: Error tracking cart', error);
			});
		}, 1000); // Wait 1 second after user stops typing
	}

	/**
	 * Monitor checkout store for billing data changes
	 */
	function monitorCheckoutData() {
		if (!select(CHECKOUT_STORE_KEY)) {
			console.log('GHL: Checkout store not available yet, retrying...');
			setTimeout(monitorCheckoutData, 500);
			return;
		}

		let previousBillingAddress = {};
		let initialCaptureDone = false;

		console.log('GHL: Checkout store available, setting up monitoring...');

		// Try to capture initial data from DOM if store doesn't have it yet
		function captureFromDOM() {
			console.log('GHL: Attempting to capture from DOM...');
			const emailInput = document.getElementById('email');
			const firstNameInput = document.getElementById('billing-first_name') || document.querySelector('input[name="billing-first_name"]');
			const lastNameInput = document.getElementById('billing-last_name') || document.querySelector('input[name="billing-last_name"]');
			const phoneInput = document.getElementById('billing-phone') || document.querySelector('input[name="billing-phone"]');

			console.log('GHL: Email input found:', !!emailInput, 'Value:', emailInput?.value);

			if (emailInput && emailInput.value && emailInput.value.includes('@')) {
				const billingData = {
					email: emailInput.value,
					first_name: firstNameInput ? firstNameInput.value : '',
					last_name: lastNameInput ? lastNameInput.value : '',
					phone: phoneInput ? phoneInput.value : ''
				};

				console.log('GHL: Found pre-filled email in DOM:', billingData.email);
				previousBillingAddress = { ...billingData };
				sendCartData(billingData);
				initialCaptureDone = true;
				return true;
			}

			console.log('GHL: No email found in DOM');
			return false;
		}

		// Try immediate capture from DOM
		setTimeout(() => {
			if (!initialCaptureDone) {
				captureFromDOM();
			}
		}, 1000);

		// Add direct input listeners as fallback for guest users
		function setupInputListeners() {
			const emailInput = document.getElementById('email');
			const firstNameInput = document.getElementById('billing-first_name') || document.querySelector('input[name="billing-first_name"]');
			const lastNameInput = document.getElementById('billing-last_name') || document.querySelector('input[name="billing-last_name"]');
			const phoneInput = document.getElementById('billing-phone') || document.querySelector('input[name="billing-phone"]');

			if (emailInput) {
				console.log('GHL: Setting up direct input listener on email field');
				
				// Listen for input changes on email field
				emailInput.addEventListener('input', function() {
					console.log('GHL: Email input detected:', emailInput.value);
					
					if (emailInput.value && emailInput.value.includes('@')) {
						const billingData = {
							email: emailInput.value,
							first_name: firstNameInput ? firstNameInput.value : '',
							last_name: lastNameInput ? lastNameInput.value : '',
							phone: phoneInput ? phoneInput.value : ''
						};
						
						sendCartData(billingData);
					}
				});

				// Also listen on blur (when user leaves the field)
				emailInput.addEventListener('blur', function() {
					console.log('GHL: Email blur detected:', emailInput.value);
					
					if (emailInput.value && emailInput.value.includes('@')) {
						const billingData = {
							email: emailInput.value,
							first_name: firstNameInput ? firstNameInput.value : '',
							last_name: lastNameInput ? lastNameInput.value : '',
							phone: phoneInput ? phoneInput.value : ''
						};
						
						sendCartData(billingData);
					}
				});
			} else {
				console.log('GHL: Email input not found, will retry...');
				setTimeout(setupInputListeners, 500);
			}
		}

		// Set up input listeners
		setTimeout(setupInputListeners, 500);

		// Subscribe to store changes for ongoing monitoring
		subscribe(() => {
			try {
				// Get billing address from store
				const billingAddress = select(CHECKOUT_STORE_KEY).getBillingAddress?.() || {};
				
				// If store has email and we haven't captured yet, use it
				if (!initialCaptureDone && billingAddress.email) {
					console.log('GHL: Found email in store:', billingAddress.email);
					previousBillingAddress = { ...billingAddress };
					sendCartData(billingAddress);
					initialCaptureDone = true;
					return;
				}

				// Check if billing address changed
				if (billingAddress.email) {
					const addressChanged = 
						billingAddress.email !== previousBillingAddress.email ||
						billingAddress.first_name !== previousBillingAddress.first_name ||
						billingAddress.last_name !== previousBillingAddress.last_name ||
						billingAddress.phone !== previousBillingAddress.phone;

					if (addressChanged) {
						console.log('GHL: Billing address changed in store, updating...');
						previousBillingAddress = { ...billingAddress };
						sendCartData(billingAddress);
					}
				}
			} catch (error) {
				// Silent fail if store methods not available yet
			}
		});

		console.log('GHL: Abandoned cart tracking initialized for WooCommerce Blocks');
	}

	// Start monitoring when DOM is ready
	$(document).ready(function () {
		// Check if we're on a checkout page with blocks
		if ($('.wp-block-woocommerce-checkout').length > 0) {
			monitorCheckoutData();
		}
	});

})(jQuery);
