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
            this.prefetchCache = {};
            this.inflightPrefetch = {};
            this.prefetchTtl = 60000; // retain TTL for cache validation
            this.currentParams = {};
            
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
                : ['general', 'api', 'rest-api', 'webhooks', 'notifications', 'sync-options', 'role-tags', 'personalization', 'conversations', 'advanced', 'stats'];
            if (settingsTabs.includes(hash)) {
                // This is a settings tab, first load settings view if not already loaded
                if (this.currentView !== 'settings') {
                    this.loadView('settings');
                }
                return;
            }
            
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

            // Separate query string from path
            let pathPart = hash;
            let queryString = '';

            const queryIndex = hash.indexOf('?');
            if (queryIndex !== -1) {
                pathPart = hash.slice(0, queryIndex);
                queryString = hash.slice(queryIndex + 1);
            }

            // Parse view and params from path segments
            const pathSegments = pathPart.split('/').filter(Boolean);
            const view = pathSegments.shift() || 'dashboard';
            const params = {};

            for (let i = 0; i < pathSegments.length; i += 2) {
                const key = pathSegments[i];
                const value = pathSegments[i + 1];

                if (key && typeof value !== 'undefined') {
                    params[key] = decodeURIComponent(value);
                }
            }

            // Merge query string parameters
            if (queryString) {
                const searchParams = new URLSearchParams(queryString);
                searchParams.forEach((value, key) => {
                    if (typeof params[key] === 'undefined') {
                        params[key] = value;
                    }
                });
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
            const cacheKey = this.getCacheKey(view, params);
            const cached = this.viewCache[cacheKey];

            const validCache = (entry) => entry && (Date.now() - entry.fetchedAt) < this.prefetchTtl;

            const useEntry = (entry) => {
                this.viewCache[cacheKey] = entry; // promote so future reads use main cache
                this.currentView = view;
                this.currentParams = params;
                this.renderView(entry.data, params);

                if (typeof callback === 'function') {
                    callback();
                }

            };

            if (validCache(cached)) {
                useEntry(cached);
                return;
            }

            // Show loading state when no fresh cache
            this.showLoading();

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
                        this.currentParams = params;
                        this.renderView(response.data, params);

                        // Store in view cache for quick subsequent renders
                        this.viewCache[cacheKey] = {
                            data: response.data,
                            fetchedAt: Date.now(),
                        };
                        
                        // Execute callback if provided
                        if (typeof callback === 'function') {
                            callback();
                        }
                    } else {
                        this.showError(response.data?.message || this.config.strings.error);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError(this.config.strings.error);
                }
            });
        }

        /**
         * Render a view
         * 
         * @param {Object} viewData - View data from server
         */
        renderView(viewData, params = {}) {
            const view = viewData.view;
            const html = viewData.html;

            // Inject the HTML from server
            this.appContainer.html(html);
            
            // Re-initialize any event handlers based on view
            this.initializeViewHandlers(view, params);
        }
        
        /**
         * Initialize view-specific event handlers after HTML injection
         * 
         * @param {string} view - View name
         */
        initializeViewHandlers(view, params = {}) {
            switch (view) {
                case 'dashboard':
                    // Re-initialize dashboard handlers
                    if (typeof window.initDashboard === 'function') {
                        window.initDashboard();
                    }

                    if (typeof window.initAnalytics === 'function') {
                        // Get analytics data from JSON script tag
                        const analyticsDataElement = document.getElementById('ghl-analytics-data');
                        if (analyticsDataElement) {
                            try {
                                const analyticsData = JSON.parse(analyticsDataElement.textContent);
                                window.initAnalytics(analyticsData);
                            } catch (e) {
                                console.error('Failed to parse analytics data:', e);
                            }
                        } else {
                            console.warn('Analytics data element not found');
                        }
                    }
                    break;
                case 'field-mapping':
                    // Re-initialize field mapping handlers
                    if (typeof window.initFieldMapping === 'function') {
                        window.initFieldMapping();
                    }
                    // Init Select2 on server-rendered options & highlight mapped rows
                    if (window.GHL_FieldMapping) {
                        if (typeof window.GHL_FieldMapping.initSelect2 === 'function') {
                            window.GHL_FieldMapping.initSelect2();
                        }
                        if (typeof window.GHL_FieldMapping.updateMappedRows === 'function') {
                            window.GHL_FieldMapping.updateMappedRows();
                        }
                    }
                    break;
                case 'custom-objects':
                    // Re-initialize custom objects handlers
                    if (typeof window.initCustomObjects === 'function') {
                        window.initCustomObjects();
                    }
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
                    // Initialize family accounts functionality
                    if (typeof window.initFamilyAccounts === 'function') {
                        window.initFamilyAccounts();
                    }
                    // Reinitialize settings side menu to restore mobile toggle handlers and tab wiring
                    if (typeof window.cleanupSettingsMenu === 'function') {
                        window.cleanupSettingsMenu();
                    }
                    if (typeof window.initSettingsMenu === 'function') {
                        window.initSettingsMenu();
                    }
                    break;
                case 'sync-logs':
                    // Re-initialize sync logs handlers
                    if (typeof window.GHLSyncLogs !== 'undefined' && typeof window.GHLSyncLogs.init === 'function') {
                        window.GHLSyncLogs.init(params);
                    }
                    break;
                case 'forms':
                    // Re-initialize forms handlers
                    if (typeof window.GHLFormsManager !== 'undefined') {
                        if (typeof window.GHLFormsManager.reset === 'function') {
                            window.GHLFormsManager.reset();
                        }
                        if (typeof window.GHLFormsManager.init === 'function') {
                            window.GHLFormsManager.init();
                        }
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
                        <div class="ghl-skeleton-hero"></div>
                        <div class="ghl-skeleton-grid">
                            <div class="ghl-skeleton-card">
                                <div class="ghl-skeleton-bar wide"></div>
                                <div class="ghl-skeleton-bar"></div>
                                <div class="ghl-skeleton-bar short"></div>
                            </div>
                            <div class="ghl-skeleton-card">
                                <div class="ghl-skeleton-bar wide"></div>
                                <div class="ghl-skeleton-bar"></div>
                                <div class="ghl-skeleton-bar short"></div>
                            </div>
                            <div class="ghl-skeleton-card">
                                <div class="ghl-skeleton-bar wide"></div>
                                <div class="ghl-skeleton-bar"></div>
                                <div class="ghl-skeleton-bar short"></div>
                            </div>
                        </div>
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

        getCacheKey(view, params = {}) {
            try {
                return `${view}::${JSON.stringify(params || {})}`;
            } catch (e) {
                return view;
            }
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
                        customClass: {
                            popup: 'ghl-swal-top-toast',
                        },
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