/**
 * GoHighLevel Membership Admin
 * Handles membership restriction meta box functionality
 */
(function($) {
    'use strict';

    // AssetsManager localizes as syncly_membership_admin_js_data.
    const synclyMembership = window.syncly_membership_admin_js_data || {};

    const SynclyMembershipAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.initSelect2();
            this.initRestrictionType();
        },

        /**
         * Initialize Select2 for tags.
         *
         * Applies to every `.ghl-tags-select` on the screen — the built-in
         * Required Tags field plus any Pro-added fields (e.g. content-access
         * granted/denied tags), not just a single hardcoded element.
         */
        initSelect2: function() {
            const $tagsSelects = $('.ghl-tags-select');

            if ($tagsSelects.length === 0) {
                return;
            }

            var allTags = (typeof synclyMembership !== 'undefined' && synclyMembership.tags) ? synclyMembership.tags : [];

            $tagsSelects.each(function() {
                var $tagsSelect = $(this);

                if ($tagsSelect.hasClass('select2-hidden-accessible')) {
                    return; // Already initialized.
                }

                // Pre-populate options from localized tags (already selected tags are in HTML)
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
        SynclyMembershipAdmin.init();
    });

})(jQuery);