/**
 * Sync Logs Page JavaScript
 *
 * @package GHL_CRM_Integration
 */

(function($) {
    'use strict';

    const SyncLogsPage = {
        modal: null,
        closeBtn: null,
        content: null,
        currentParams: {},

        /**
         * Initialize
         */
        init: function(params = {}) {
            this.currentParams = params || {};
            this.cacheElements();
            this.bindEvents();
            this.applyRouteParams();
        },

        /**
         * Cache frequently accessed DOM elements
         */
        cacheElements: function() {
            this.modal = $('#ghl-details-modal');
            this.closeBtn = $('#ghl-close-modal');
            this.content = $('#ghl-details-content');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // View details button
            $(document)
                .off('click.ghlSyncLogs', '.ghl-view-details')
                .on('click.ghlSyncLogs', '.ghl-view-details', function(e) {
                    e.preventDefault();
                    const details = $(this).attr('data-details');
                    self.showDetailsModal(details);
                });

            // Close modal button
            if (this.closeBtn && this.closeBtn.length) {
                this.closeBtn.off('click.ghlSyncLogs').on('click.ghlSyncLogs', function() {
                    self.hideModal();
                });
            }

            // Click outside to close
            if (this.modal && this.modal.length) {
                this.modal.off('click.ghlSyncLogs').on('click.ghlSyncLogs', function(e) {
                    if (e.target === self.modal[0]) {
                        self.hideModal();
                    }
                });
            }

            // ESC key to close
            $(document)
                .off('keydown.ghlSyncLogs')
                .on('keydown.ghlSyncLogs', function(e) {
                    if (e.key === 'Escape' && self.modal && self.modal.is(':visible')) {
                        self.hideModal();
                    }
                });

            // Delete logs button
            $('#ghl-delete-logs').off('click.ghlSyncLogs').on('click.ghlSyncLogs', function(e) {
                e.preventDefault();
                self.deleteLogs();
            });

            // Clear logs button (different from delete, clears all)
            $('#ghl-clear-all-logs').off('click.ghlSyncLogs').on('click.ghlSyncLogs', function(e) {
                e.preventDefault();
                self.clearAllLogs();
            });

            // Process queue button
            $('#ghl-process-queue').off('click.ghlSyncLogs').on('click.ghlSyncLogs', function(e) {
                e.preventDefault();
                self.processQueue();
            });

            // Pagination
            $(document)
                .off('click.ghlSyncLogs', '.ghl-pagination-link')
                .on('click.ghlSyncLogs', '.ghl-pagination-link', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    self.loadPage(page);
                });

            // Filter by status
            $('#ghl-filter-status').off('change.ghlSyncLogs').on('change.ghlSyncLogs', function() {
                self.loadPage(1);
            });

            // Per-page selector - save to user meta and reload
            $('#ghl-per-page').off('change.ghlPerPage').on('change.ghlPerPage', function() {
                const perPage = $(this).val();
                
                // Save to user meta via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ghl_save_logs_per_page',
                        per_page: perPage,
                        nonce: ghl_crm_sync_logs_js_data.nonce
                    },
                    success: function() {
                        // Reload page to apply new per-page setting
                        window.location.reload();
                    }
                });
            });

            // Search/filter
            $('#ghl-search-logs').off('keyup.ghlSyncLogs').on('keyup.ghlSyncLogs', function() {
                clearTimeout(self.searchTimeout);
                self.searchTimeout = setTimeout(function() {
                    self.loadPage(1);
                }, 500);
            });
        },

        /**
         * Apply hash/route parameters to the current view
         */
        applyRouteParams: function() {
            const params = this.currentParams || {};
            let statusParam = params.status || params.filter;

            if (!statusParam) {
                statusParam = this.extractStatusFromHash();
            }

            if (!statusParam) {
                return;
            }

            const $statusSelect = $('#ghl-filter-status');

            if (!$statusSelect.length) {
                return;
            }

            const hasOption = $statusSelect.find('option[value="' + statusParam + '"]').length > 0;

            if (!hasOption) {
                return;
            }

            if ($statusSelect.val() === statusParam) {
                return;
            }

            $statusSelect.val(statusParam).trigger('change');
        },

        /**
         * Extract status parameter from window hash (fallback for non-SPA loads)
         */
        extractStatusFromHash: function() {
            if (!window.location || !window.location.hash) {
                return '';
            }

            const hash = window.location.hash.replace(/^#/, '');

            if (!hash) {
                return '';
            }

            const queryIndex = hash.indexOf('?');
            let query = '';

            if (queryIndex !== -1) {
                query = hash.slice(queryIndex + 1);
            }

            // Support path-style parameters e.g. sync-logs/status/failed
            const segments = hash.split('/');
            const statusIndex = segments.indexOf('status');
            if (statusIndex !== -1 && typeof segments[statusIndex + 1] !== 'undefined') {
                return decodeURIComponent(segments[statusIndex + 1]);
            }

            if (query) {
                try {
                    const searchParams = new URLSearchParams(query);
                    if (searchParams.has('status')) {
                        return searchParams.get('status');
                    }
                    if (searchParams.has('filter')) {
                        return searchParams.get('filter');
                    }
                } catch (error) {
                    // Ignore invalid query strings
                }
            }

            return '';
        },

        /**
         * Show details modal
         */
        showDetailsModal: function(details) {
            try {
                const parsed = JSON.parse(details);
                this.content.text(JSON.stringify(parsed, null, 2));
            } catch (err) {
                this.content.text(details);
            }
            this.modal.fadeIn(200);
        },

        /**
         * Hide modal
         */
        hideModal: function() {
            this.modal.fadeOut(200);
        },

        /**
         * Delete old logs
         */
        deleteLogs: function() {
            Swal.fire({
                title: 'Delete Old Logs?',
                text: 'This will delete logs older than 30 days. This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete them!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const button = $('#ghl-delete-logs');
                const originalText = button.find('.ghl-button-text').text();
                button.prop('disabled', true).find('.ghl-button-text').text('Deleting...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ghl_delete_old_logs',
                        nonce: ghl_crm_sync_logs_js_data.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: response.data.message || 'Logs deleted successfully',
                                icon: 'success'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: response.data.message || 'Failed to delete logs',
                                icon: 'error'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to delete logs',
                            icon: 'error'
                        });
                    },
                    complete: function() {
                        button.prop('disabled', false).find('.ghl-button-text').text(originalText);
                    }
                });
            });
        },

        /**
         * Clear all logs
         */
        clearAllLogs: function() {
            Swal.fire({
                title: '⚠️ WARNING!',
                text: 'This will DELETE ALL sync logs permanently. This cannot be undone. Are you absolutely sure?',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete everything!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const button = $('#ghl-clear-all-logs');
                const originalText = button.find('.ghl-button-text').text();
                button.prop('disabled', true).find('.ghl-button-text').text('Clearing...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ghl_clear_all_logs',
                        nonce: ghl_crm_sync_logs_js_data.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: response.data.message || 'All logs cleared successfully',
                                icon: 'success'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: response.data.message || 'Failed to clear logs',
                                icon: 'error'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to clear logs',
                            icon: 'error'
                        });
                    },
                    complete: function() {
                        button.prop('disabled', false).find('.ghl-button-text').text(originalText);
                    }
                });
            });
        },

        /**
         * Process queue manually
         */
        processQueue: function() {
            const button = $('#ghl-process-queue');
            const originalText = button.find('.ghl-button-text').text();
            button.prop('disabled', true).find('.ghl-button-text').text('Processing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ghl_crm_manual_queue_trigger',
                    nonce: ghl_crm_sync_logs_js_data.nonce
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.data.message || 'Queue processed successfully',
                            icon: 'success'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.data.message || 'Failed to process queue',
                            icon: 'error'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to process queue',
                        icon: 'error'
                    });
                },
                complete: function() {
                    button.prop('disabled', false).find('.ghl-button-text').text(originalText);
                }
            });
        },

        /**
         * Load page via AJAX
         */
        loadPage: function(page) {
            const status = $('#ghl-filter-status').val();
            const search = $('#ghl-search-logs').val();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ghl_get_logs',
                    nonce: ghl_crm_sync_logs_js_data.nonce,
                    page: page,
                    status: status,
                    search: search
                },
                beforeSend: function() {
                    $('#ghl-logs-table-container').css('opacity', '0.5');
                },
                success: function(response) {
                    if (response.success) {
                        $('#ghl-logs-table-container').html(response.data.html);
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to load logs',
                            icon: 'error'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to load logs',
                        icon: 'error'
                    });
                },
                complete: function() {
                    $('#ghl-logs-table-container').css('opacity', '1');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SyncLogsPage.init();
    });

    // Expose to global scope in a namespaced way for SPA router
    // This avoids conflicts with other plugins for WordPress.org submission
    window.GHLSyncLogs = SyncLogsPage;

})(jQuery);
