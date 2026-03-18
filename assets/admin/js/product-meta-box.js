/**
 * GHL Product Meta Box
 * 
 * THIS FILE IS NOT USED NOW, IT HAS BEEN COPIED TO THE PRO VERSION OF THE PLUGIN.
 * WILL BE REMOVED FROM THE FREE VERSION IN FUTURE UPDATES.
 *
 * Handles tag selection for WooCommerce products in Product Data tabs
 */
(function($) {
    'use strict';

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Wait for WooCommerce to initialize its tabs
        if (typeof woocommerce_admin_meta_boxes !== 'undefined') {
            initializeTagsSelect();
        } else {
            // Fallback: wait a bit for WooCommerce to load
            setTimeout(initializeTagsSelect, 500);
        }
    });

    /**
     * Initialize tags select with Select2
     */
    function initializeTagsSelect() {
        const $select = $('#ghl_purchase_tags');
        
        if (!$select.length) {
            return;
        }

        // Get saved tags from data attribute
        const savedTags = $select.data('saved-tags') || [];

        // Pre-populate options from localized tags
        var allTags = (typeof ghl_crm_pro_woocommerce_data !== 'undefined' && ghl_crm_pro_woocommerce_data.tags) ? ghl_crm_pro_woocommerce_data.tags : [];
        allTags.forEach(function(tag) {
            var label = String(tag.name || tag.id || '');
            if (label && !$select[0].querySelector('option[value="' + CSS.escape(label) + '"]')) {
                var isSelected = savedTags.indexOf(label) !== -1;
                $select.append(new Option(label, label, isSelected, isSelected));
            }
        });

        // Initialize Select2 without AJAX
        $select.select2({
            placeholder: $select.data('placeholder') || 'Select tags...',
            allowClear: true,
            closeOnSelect: false,
            tags: true,
            tokenSeparators: [','],
            scrollAfterSelect: false,
            width: '100%'
        });

        // Prevent dropdown from auto-scrolling on selection/unselection
        $select.on('select2:select select2:unselect', function(e) {
            var $dropdown = $(this).data('select2').$dropdown;
            if ($dropdown) {
                var $results = $dropdown.find('.select2-results__options');
                if ($results.length) {
                    var scrollPos = $results.scrollTop();
                    setTimeout(function() {
                        $results.scrollTop(scrollPos);
                    }, 1);
                }
            }
        });

        // Load saved tags on initialization
        if (savedTags.length > 0) {
            loadSavedTags($select, savedTags);
        }
    }

    /**
     * Load saved tags into select
     *
     * @param {jQuery} $select Select element
     * @param {Array} tags Array of tag names
     */
    function loadSavedTags($select, tags) {
        // Add options for saved tags
        tags.forEach(function(tag) {
            const option = new Option(tag, tag, true, true);
            $select.append(option);
        });

        // Trigger change to update Select2
        $select.trigger('change');
    }

})(jQuery);