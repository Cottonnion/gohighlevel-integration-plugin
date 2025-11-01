/**
 * Settings Side Menu Handler
 * Handles tab switching in the settings page via AJAX
 */

(function($) {
	'use strict';

	/**
	 * Initialize settings menu
	 */
	function initSettingsMenu() {
		// Prevent multiple initializations
		if (window.ghlSettingsMenuInitialized) {
			return;
		}
		window.ghlSettingsMenuInitialized = true;
		
		// Initialize mobile menu toggle
		initMobileMenuToggle();
		
		// Check for hash on page load
		const hash = window.location.hash.slice(1);
		if (hash && hash !== '') {
			// Load tab from hash if present
			const $tab = $('.ghl-settings-nav li[data-tab="' + hash + '"]');
			if ($tab.length) {
				loadSettingsTab(hash);
				$('.ghl-settings-nav li').removeClass('active');
				$tab.addClass('active');
			}
		}
		
		// Remove any existing event handlers first
		$(document).off('click.ghlSettingsMenu', '.ghl-settings-nav li');
		$(window).off('hashchange.ghlSettingsMenu');
		
		// Handle tab clicks with namespaced event
		$(document).on('click.ghlSettingsMenu', '.ghl-settings-nav li', function(e) {
			e.preventDefault();
			
			const $tab = $(this);
			const tab = $tab.data('tab');
			
			// Don't reload if already active
			if ($tab.hasClass('active')) {
				// Close menu on mobile after selection
				closeMobileMenu();
				return;
			}
			
			// Update active state
			$('.ghl-settings-nav li').removeClass('active');
			$tab.addClass('active');
			
			// Update hash
			window.location.hash = tab;
			
			// Load tab content
			loadSettingsTab(tab);
			
			// Close menu on mobile after selection
			closeMobileMenu();
		});
		
		// Handle browser back/forward with hash changes (namespaced event)
		$(window).on('hashchange.ghlSettingsMenu', function() {
			const hash = window.location.hash.slice(1);
			if (hash && hash !== '') {
				// Only handle if it's a settings tab (use centralized config)
				const settingsTabs = (typeof ghlCrmSpaConfig !== 'undefined' && ghlCrmSpaConfig.settings) 
					? ghlCrmSpaConfig.settings.tabs 
					: ['general', 'api', 'rest-api', 'webhooks', 'notifications', 'field-sync', 'role-tags', 'advanced', 'stats'];
				if (settingsTabs.includes(hash)) {
					loadSettingsTab(hash);
					// Update active state
					$('.ghl-settings-nav li').removeClass('active');
					$('.ghl-settings-nav li[data-tab="' + hash + '"]').addClass('active');
				}
			}
		});
	}
	
	/**
	 * Initialize mobile menu toggle
	 */
	function initMobileMenuToggle() {
		const $toggleBtn = $('#ghl-menu-toggle');
		const $nav = $('#ghl-settings-nav');
		
		// Remove existing handlers
		$toggleBtn.off('click.ghlMenuToggle');
		$(document).off('click.ghlMenuOverlay');
		
		// Toggle menu on button click
		$toggleBtn.on('click.ghlMenuToggle', function(e) {
			e.stopPropagation();
			toggleMobileMenu();
		});
		
		// Close menu when clicking outside on mobile
		$(document).on('click.ghlMenuOverlay', function(e) {
			if ($nav.hasClass('expanded')) {
				const isClickInside = $(e.target).closest('.ghl-settings-nav, #ghl-menu-toggle').length > 0;
				if (!isClickInside) {
					closeMobileMenu();
				}
			}
		});
		
		// Close menu on Escape key
		$(document).on('keydown.ghlMenuToggle', function(e) {
			if (e.key === 'Escape' && $nav.hasClass('expanded')) {
				closeMobileMenu();
			}
		});
	}
	
	/**
	 * Toggle mobile menu
	 */
	function toggleMobileMenu() {
		const $nav = $('#ghl-settings-nav');
		$nav.toggleClass('expanded');
		$('body').toggleClass('ghl-menu-open');
		
		// Update aria attributes for accessibility
		const isExpanded = $nav.hasClass('expanded');
		$('#ghl-menu-toggle').attr('aria-expanded', isExpanded);
	}
	
	/**
	 * Close mobile menu
	 */
	function closeMobileMenu() {
		const $nav = $('#ghl-settings-nav');
		if ($nav.hasClass('expanded')) {
			$nav.removeClass('expanded');
			$('body').removeClass('ghl-menu-open');
			$('#ghl-menu-toggle').attr('aria-expanded', 'false');
		}
	}
	
	/**
	 * Load settings tab content via AJAX
	 * 
	 * @param {string} tab - The tab to load (general, api, notifications, advanced, etc.)
	 */
	function loadSettingsTab(tab) {
		const $content = $('.ghl-settings-content');
		
		// Show loading state
		$content.css('opacity', '0.5');
		
		// Check if we have the SPA config
		const ajaxUrl = (typeof ghlCrmSpaConfig !== 'undefined') ? ghlCrmSpaConfig.ajaxUrl : ajaxurl;
		const nonce = (typeof ghlCrmSpaConfig !== 'undefined') ? ghlCrmSpaConfig.nonce : '';
		
		// Make AJAX request to load partial directly
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'ghl_crm_load_settings_tab',
				nonce: nonce,
				tab: tab
			},
			success: function(response) {
				if (response.success && response.data.html) {
					$content.html(response.data.html);
					
					// Re-initialize any scripts for the loaded content
					if (typeof window.initSettings === 'function') {
						window.initSettings();
					}
					
					// Re-initialize user register tags functionality (for general tab)
					if (typeof window.initUserRegisterTags === 'function') {
						window.initUserRegisterTags();
					}
					
					// Re-initialize restrictions roles select functionality (for restrictions tab)
					if (typeof window.initRestrictionsRolesSelect === 'function') {
						window.initRestrictionsRolesSelect();
					}
				} else {
					$content.html('<div class="notice notice-error"><p>' + (response.data.message || 'Failed to load settings tab.') + '</p></div>');
				}
			},
			error: function(xhr, status, error) {
				console.error('Settings tab load error:', error);
				$content.html('<div class="notice notice-error"><p>Error loading settings tab. Please try again.</p></div>');
			},
			complete: function() {
				$content.css('opacity', '1');
			}
		});
	}
	
	// Initialize on document ready
	$(document).ready(function() {
		// Only initialize if we're on a settings page with side menu
		if ($('.ghl-settings-with-sidebar').length) {
			initSettingsMenu();
		}
	});
	
	/**
	 * Cleanup function to reset initialization state
	 */
	function cleanupSettingsMenu() {
		window.ghlSettingsMenuInitialized = false;
		$(document).off('click.ghlSettingsMenu', '.ghl-settings-nav a');
		$(window).off('hashchange.ghlSettingsMenu');
		$('#ghl-menu-toggle').off('click.ghlMenuToggle');
		$(document).off('click.ghlMenuOverlay');
		$(document).off('keydown.ghlMenuToggle');
		$('body').removeClass('ghl-menu-open');
	}
	
	// Export for use in SPA router
	window.initSettingsMenu = initSettingsMenu;
	window.cleanupSettingsMenu = cleanupSettingsMenu;
	
})(jQuery);
