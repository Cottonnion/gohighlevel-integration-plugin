/**
 * GoHighLevel CRM Integration - SPA Router
 *
 * Handles hash-based routing and dynamic content loading for the admin interface.
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/assets/admin/js
 */

(function($) {
    'use strict';

    /**
     * SPA Router Class
     */
    class GHLCRMRouter {
        constructor() {
            this.appContainer = $('#ghl-crm-app');
            this.currentView = null;
            this.config = window.ghlCrmSpaConfig || {};
            this.viewCache = {};
            
            this.init();
        }

        /**
         * Initialize the router
         */
        init() {
            // Listen for hash changes
            $(window).on('hashchange', () => this.handleRouteChange());
            
            // Load initial route
            this.handleRouteChange();
        }

        /**
         * Handle route changes
         */
        handleRouteChange() {
            const hash = window.location.hash.slice(1) || '/';
            const route = this.parseRoute(hash);
            
            // Check if this is a settings tab hash (not a SPA view)
            const settingsTabs = (this.config.settings && this.config.settings.tabs) 
                ? this.config.settings.tabs 
                : ['general', 'api', 'rest-api', 'webhooks', 'notifications', 'field-sync', 'role-tags', 'advanced', 'stats'];
            if (settingsTabs.includes(hash)) {
                // This is a settings tab, first load settings view if not already loaded
                console.log('Settings tab hash detected:', hash);
                if (this.currentView !== 'settings') {
                    console.log('Loading settings view first...');
                    this.loadView('settings');
                }
                return;
            }
            
            console.log('SPA Route changed:', route);
            this.loadView(route.view, route.params);
            
            // Trigger custom event for menu router to listen to
            $(window).trigger('ghl-spa-route-changed', [route.view]);
        }

        /**
         * Parse route from hash
         * 
         * @param {string} hash - URL hash without #
         * @returns {Object} Parsed route object
         */
        parseRoute(hash) {
            // Remove leading slash
            hash = hash.replace(/^\//, '');
            
            // If empty, default to dashboard
            if (!hash || hash === '') {
                return { view: 'dashboard', params: {} };
            }

            // Parse view and params
            const parts = hash.split('/');
            const view = parts[0] || 'dashboard';
            const params = {};

            // Parse additional path segments as params
            for (let i = 1; i < parts.length; i += 2) {
                if (parts[i] && parts[i + 1]) {
                    params[parts[i]] = parts[i + 1];
                }
            }

            return { view, params };
        }

        /**
         * Load a view via AJAX
         * 
         * @param {string} view - View name
         * @param {Object} params - View parameters
         * @param {Function} callback - Optional callback after view is loaded
         */
        loadView(view, params = {}, callback = null) {
            // Show loading state
            this.showLoading();
            
            // Cache key
            const cacheKey = `${view}_${JSON.stringify(params)}`;

            // Make AJAX request
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ghl_crm_spa_view',
                    nonce: this.config.nonce,
                    view: view,
                    params: params
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.currentView = view;
                        this.renderView(response.data);
                        
                        // Execute callback if provided
                        if (typeof callback === 'function') {
                            callback();
                        }
                    } else {
                        this.showError(response.data?.message || this.config.strings.error);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', error);
                    this.showError(this.config.strings.error);
                }
            });
        }

        /**
         * Render a view
         * 
         * @param {Object} viewData - View data from server
         */
        renderView(viewData) {
            const view = viewData.view;
            const html = viewData.html;

            // Inject the HTML from server
            this.appContainer.html(html);
            
            // Re-initialize any event handlers based on view
            this.initializeViewHandlers(view);
        }
        
        /**
         * Initialize view-specific event handlers after HTML injection
         * 
         * @param {string} view - View name
         */
        initializeViewHandlers(view) {
            switch (view) {
                case 'dashboard':
                    // Re-initialize dashboard handlers
                    if (typeof window.initDashboard === 'function') {
                        window.initDashboard();
                    }
                    break;
                case 'field-mapping':
                    // Re-initialize field mapping handlers
                    if (typeof window.initFieldMapping === 'function') {
                        window.initFieldMapping();
                    }
                    // Load GHL custom fields
                    if (typeof window.GHL_FieldMapping.loadFields === 'function') {
                        window.GHL_FieldMapping.loadFields(false);
                    }
                    break;
                case 'custom-objects':
                    // Re-initialize custom objects handlers
                    if (typeof window.initCustomObjects === 'function') {
                        window.initCustomObjects();
                    }
                    console.log('Custom Objects view loaded');
                    break;
                case 'integrations':
                    // Re-initialize integrations handlers
                    if (typeof window.initIntegrations === 'function') {
                        window.initIntegrations();
                    }
                    break;
                case 'settings':
                    // Re-initialize settings handlers
                    if (typeof window.initSettings === 'function') {
                        window.initSettings();
                    }
                    // Initialize user register tags functionality
                    if (typeof window.initUserRegisterTags === 'function') {
                        window.initUserRegisterTags();
                    }
                    // Initialize user register company functionality
                    if (typeof window.initUserRegisterCompany === 'function') {
                        window.initUserRegisterCompany();
                    }
                    // Initialize restrictions roles select functionality
                    if (typeof window.initRestrictionsTagsSelect === 'function') {
                        window.initRestrictionsTagsSelect();
                    }
                    // Initialize role tags functionality
                    if (typeof window.initRoleTags === 'function') {
                        window.initRoleTags();
                    }
                    // Initialize settings side menu for tab switching (only if not already initialized)
                    if (typeof window.initSettingsMenu === 'function' && !window.ghlSettingsMenuInitialized) {
                        window.initSettingsMenu();
                    }
                    break;
                case 'sync-logs':
                    // Re-initialize sync logs handlers
                    if (typeof window.GHLSyncLogs !== 'undefined' && typeof window.GHLSyncLogs.init === 'function') {
                        window.GHLSyncLogs.init();
                    }
                    break;
            }
        }



        /**
         * Show loading state
         */
        showLoading() {
            this.appContainer.html(`
                <div class="ghl-spa-loading">
                    <div class="ghl-loading-spinner"></div>
                    <p>${this.config.strings.loading}</p>
                </div>
            `);
        }

        /**
         * Show error message
         * 
         * @param {string} message - Error message
         */
        showError(message) {
            this.appContainer.html(`
                <div class="notice notice-error">
                    <p><strong>Error:</strong> ${message}</p>
                </div>
            `);
        }

        /**
         * Show notification (SweetAlert2 toast)
         * 
         * @param {string} message - Notification message
         * @param {string} type - Notification type (success, error, info, warning)
         */
        showNotice(message, type = 'success') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: type,
                    title: message,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                });
            } else {
                console.log(`[${type}] ${message}`);
            }
        }
    }

    // Initialize router when document is ready
    $(document).ready(function() {
        new GHLCRMRouter();
    });

})(jQuery);
