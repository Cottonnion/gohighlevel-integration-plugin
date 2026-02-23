/**
 * GoHighLevel Elementor Conditions
 * Handles dynamic tag loading in Elementor editor
 */
(function($) {
    'use strict';

    // Wait for Elementor to be ready
    $(window).on('elementor:init', function() {
        // Tags are already loaded via PHP localization
        // This file is here for future enhancements if needed
        
        // Debug log (can be removed in production)
        if (typeof ghlElementorData !== 'undefined') {
            console.log('GHL Elementor Conditions loaded with', Object.keys(ghlElementorData.availableTags).length, 'tags');
        }
    });

})(jQuery);
