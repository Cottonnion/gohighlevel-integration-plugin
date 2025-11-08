/**
 * GHL Product Meta Box
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

        // Initialize Select2 with AJAX
        $select.select2({
            placeholder: $select.data('placeholder') || 'Select tags...',
            allowClear: true,
            width: '100%',
            ajax: {
                url: ghlProductMetaBox.ajaxUrl + '?action=' + encodeURIComponent(ghlProductMetaBox.action),
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json; charset=UTF-8',
                processData: false,
                delay: 250,
                data: function(params) {
                    return JSON.stringify({
                        nonce: ghlProductMetaBox.nonce,
                        search: params.term || ''
                    });
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

        // Load saved tags on initialization
        if (savedTags.length > 0) {
            loadSavedTags($select, savedTags);
        } else {
            // Clear loading state
            $select.html('').trigger('change');
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
