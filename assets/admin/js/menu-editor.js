/**
 * GoHighLevel CRM Integration - Menu Editor
 *
 * Handles conditional menu visibility fields in WordPress menu editor
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/assets/admin/js
 */

(function($) {
    'use strict';

    const GHLMenuEditor = {
        /**
         * Initialize
         */
        init: function() {
            const self = this;
            
            // Initialize Select2 on all tag fields
            self.initializeAllSelect2();
            
            // Handle visibility rule changes
            $(document).on('change', '.ghl-visibility-rule-select', function() {
                const $menuItem = $(this).closest('.menu-item');
                self.updateTagFieldVisibility($menuItem);
            });
            
            // Initialize visibility on page load
            $('.ghl-visibility-rule-select').each(function() {
                const $menuItem = $(this).closest('.menu-item');
                self.updateTagFieldVisibility($menuItem);
            });
            
            // Watch for new menu items being added or expanded
            $(document).on('click', '.item-edit', function() {
                setTimeout(function() {
                    self.initializeAllSelect2();
                }, 100);
            });
        },

        /**
         * Initialize Select2 on all tag fields
         */
        initializeAllSelect2: function() {
            const self = this;
            
            $('.ghl-tags-select').each(function() {
                const $tagSelect = $(this);
                
                // Skip if already initialized
                if ($tagSelect.data('select2')) {
                    return;
                }
                
                const $section = $tagSelect.closest('.ghl-menu-visibility-section');
                let savedTags = $section.data('saved-tags') || [];
                const tagNamesMap = $section.data('tag-names') || {};
                
                // Convert object to array if needed (handle PHP array encoding issues)
                if (typeof savedTags === 'object' && !Array.isArray(savedTags)) {
                    savedTags = Object.values(savedTags).filter(function(tag) {
                        return tag && tag.trim() !== '';
                    });
                }
                
                // Ensure it's an array
                if (!Array.isArray(savedTags)) {
                    savedTags = [];
                }
                
                // Initialize Select2
                $tagSelect.select2({
                    tags: true,
                    tokenSeparators: [','],
                    placeholder: ghlMenuEditor.strings.searchTags,
                    allowClear: true,
                    width: '100%',
                    minimumInputLength: 0,
                    ajax: {
                        url: ghlMenuEditor.ajaxUrl,
                        type: 'POST',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'ghl_crm_get_tags',
                                nonce: ghlMenuEditor.nonce,
                                search: params.term
                            };
                        },
                        processResults: function(response) {
                            if (response.success && response.data && response.data.tags) {
                                return {
                                    results: response.data.tags.map(function(tag) {
                                        return {
                                            id: tag.id,        // Use tag ID as value
                                            text: tag.name     // Display tag name
                                        };
                                    })
                                };
                            }
                            return { results: [] };
                        },
                        cache: true
                    }
                });
                
                // Load saved tags immediately
                if (savedTags.length > 0) {
                    savedTags.forEach(function(tagId) {
                        if (tagId && tagId.trim() !== '') {
                            // Use tag name from map if available, otherwise use ID
                            const tagName = tagNamesMap[tagId] || tagId;
                            const option = new Option(tagName, tagId, true, true);
                            $tagSelect.append(option);
                        }
                    });
                    $tagSelect.trigger('change');
                }
            });
        },

        /**
         * Update tag field visibility based on rule
         */
        updateTagFieldVisibility: function($menuItem) {
            const $ruleSelect = $menuItem.find('.ghl-visibility-rule-select');
            const $tagsField = $menuItem.find('.ghl-tags-field');
            const rule = $ruleSelect.val();

            // Show tags field only for rules that require tags
            const showTags = ['has_any_tag', 'has_all_tags', 'not_has_tags'].includes(rule);
            
            if (showTags) {
                $tagsField.show();
            } else {
                $tagsField.hide();
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        GHLMenuEditor.init();
    });

})(jQuery);
