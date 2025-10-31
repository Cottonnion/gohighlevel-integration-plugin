/**
 * GoHighLevel User Profile Integration
 * Handles tag management with Select2 and sync controls
 */
(function($) {
    'use strict';

    const GHLUserProfile = {
        /**
         * Initialize
         */
        init: function() {
            this.initSelect2();
            // this.initSyncButton();
            this.loadAvailableTags();
        },

        /**
         * Initialize Select2 for tags
         */
        initSelect2: function() {
            const $tagsSelect = $('#ghl-contact-tags');
            
            if ($tagsSelect.length === 0) {
                return;
            }

            $tagsSelect.select2({
                tags: true,
                tokenSeparators: [','],
                placeholder: ghlUserProfile.strings.searchTags,
                allowClear: true,
                width: '100%',
                ajax: {
                    url: ghlUserProfile.ajaxUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'ghl_crm_get_available_tags',
                            nonce: ghlUserProfile.nonce,
                            search: params.term
                        };
                    },
                    processResults: function(response) {
                        if (response.success && response.data) {
                            return {
                                results: response.data.map(function(tag) {
                                    return {
                                        id: tag,
                                        text: tag
                                    };
                                })
                            };
                        }
                        return { results: [] };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            // Allow creating new tags by pressing Enter
            $tagsSelect.on('select2:select', function(e) {
                console.log('Tag selected:', e.params.data);
            });
        },

        /**
         * Load available tags from GHL
         */
        loadAvailableTags: function() {
            const $tagsSelect = $('#ghl-contact-tags');
            
            if ($tagsSelect.length === 0) {
                return;
            }

            // Pre-load common tags
            $.ajax({
                url: ghlUserProfile.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ghl_crm_get_available_tags',
                    nonce: ghlUserProfile.nonce,
                    search: ''
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Tags are loaded via AJAX in Select2
                        console.log('Available tags loaded:', response.data.length);
                    }
                }
            });
        },

        /**
         * Initialize Sync Now button
         */
        initSyncButton: function() {
            const self = this;
            
            $('.ghl-sync-now-btn').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const userId = $button.data('user-id');
                
                if (!userId) {
                    alert('Invalid user ID');
                    return;
                }

                // Confirm action
                if (!confirm(ghlUserProfile.strings.confirmSync)) {
                    return;
                }

                self.syncUserNow(userId, $button);
            });
        },

        /**
         * Sync user now via AJAX
         */
        syncUserNow: function(userId, $button) {
            const self = this; // Store reference to GHLUserProfile object
            const $loading = $('.ghl-loading');
            
            // Disable button and show loading
            $button.prop('disabled', true);
            $loading.addClass('active is-active');

            $.ajax({
                url: ghlUserProfile.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ghl_crm_sync_user_now',
                    nonce: ghlUserProfile.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        self.showNotice('success', ghlUserProfile.strings.syncSuccess);
                        
                        // Reload page after 1 second to show updated data
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        self.showNotice('error', response.data.message || ghlUserProfile.strings.syncError);
                        $button.prop('disabled', false);
                        $loading.removeClass('active is-active');
                    }
                },
                error: function() {
                    self.showNotice('error', ghlUserProfile.strings.syncError);
                    $button.prop('disabled', false);
                    $loading.removeClass('active is-active');
                }
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $('<div>')
                .addClass('notice ' + noticeClass + ' is-dismissible')
                .html('<p>' + message + '</p>');

            // Insert after page title
            $('.wrap h1').first().after($notice);

            // Make dismissible
            $(document).trigger('wp-updates-notice-added');

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        GHLUserProfile.init();
    });

})(jQuery);
