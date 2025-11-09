/**
 * GoHighLevel Membership Admin
 * Handles membership restriction meta box functionality
 */
(function($) {
    'use strict';

    const GHLMembershipAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.initSelect2();
            this.initRestrictionType();
        },

        /**
         * Initialize Select2 for tags
         */
        initSelect2: function() {
            const $tagsSelect = $('#ghl_required_tags');
            
            if ($tagsSelect.length === 0) {
                return;
            }

            $tagsSelect.select2({
                tags: true,
                tokenSeparators: [','],
                placeholder: $tagsSelect.data('placeholder'),
                closeOnSelect: false,
                allowClear: true,
                width: '100%',
                scrollAfterSelect: false,
                ajax: {
                    url: ghlMembership.ajaxUrl,
                    dataType: 'json',
                    type: 'POST',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'ghl_crm_get_tags',
                            nonce: ghlMembership.nonce,
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
        },

        /**
         * Handle restriction type changes
         */
        initRestrictionType: function() {
            const $restrictionType = $('#ghl_restriction_type');
            const $tagsContainer = $('#ghl-tags-container');
            const $redirectContainer = $('#ghl-redirect-container');
            
            if ($restrictionType.length === 0) {
                return;
            }

            $restrictionType.on('change', function() {
                const value = $(this).val();
                
                if (value) {
                    $tagsContainer.slideDown(200);
                    $redirectContainer.slideDown(200);
                } else {
                    $tagsContainer.slideUp(200);
                    $redirectContainer.slideUp(200);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        GHLMembershipAdmin.init();
    });

})(jQuery);
