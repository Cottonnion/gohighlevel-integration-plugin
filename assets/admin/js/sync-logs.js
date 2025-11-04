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

        /**
         * Initialize
         */
        init: function() {
            this.modal = $('#ghl-details-modal');
            this.closeBtn = $('#ghl-close-modal');
            this.content = $('#ghl-details-content');

            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // View details button
            $(document).on('click', '.ghl-view-details', function(e) {
                e.preventDefault();
                const details = $(this).attr('data-details');
                self.showDetailsModal(details);
            });

            // Close modal button
            this.closeBtn.on('click', function() {
                self.hideModal();
            });

            // Click outside to close
            this.modal.on('click', function(e) {
                if (e.target === self.modal[0]) {
                    self.hideModal();
                }
            });

            // ESC key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.modal.is(':visible')) {
                    self.hideModal();
                }
            });

            // Delete logs button
            $('#ghl-delete-logs').on('click', function(e) {
                e.preventDefault();
                self.deleteLogs();
            });

            // Clear logs button (different from delete, clears all)
            $('#ghl-clear-all-logs').on('click', function(e) {
                e.preventDefault();
                self.clearAllLogs();
            });

            // Process queue button
            $('#ghl-process-queue').on('click', function(e) {
                e.preventDefault();
                self.processQueue();
            });

            // Pagination
            $(document).on('click', '.ghl-pagination-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                self.loadPage(page);
            });

            // Filter by status
            $('#ghl-filter-status').on('change', function() {
                self.loadPage(1);
            });

            // Search/filter
            $('#ghl-search-logs').on('keyup', function() {
                clearTimeout(self.searchTimeout);
                self.searchTimeout = setTimeout(function() {
                    self.loadPage(1);
                }, 500);
            });
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
