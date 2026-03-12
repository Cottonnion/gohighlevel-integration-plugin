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

            // Pre-populate options from localized tags (already selected tags are in HTML)
            var allTags = (typeof ghlMembership !== 'undefined' && ghlMembership.tags) ? ghlMembership.tags : [];
            var selectedIds = $tagsSelect.find('option').map(function() {
                return $(this).val();
            }).get();

            allTags.forEach(function(tag) {
                var label = String(tag.name || tag.id || '');
                if (label && selectedIds.indexOf(label) === -1) {
                    $tagsSelect.append(new Option(label, label, false, false));
                }
            });

            $tagsSelect.select2({
                tags: true,
                tokenSeparators: [','],
                placeholder: $tagsSelect.data('placeholder'),
                closeOnSelect: false,
                allowClear: true,
                width: '100%',
                scrollAfterSelect: false
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