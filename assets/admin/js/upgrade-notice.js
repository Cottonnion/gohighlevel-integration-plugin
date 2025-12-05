/**
 * GoHighLevel CRM Integration - Upgrade Notice
 *
 * Handles dismissal of the upgrade notice banner
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/assets/admin/js
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle dismiss button click
        $('.ghl-dismiss-upgrade-notice').on('click', function(e) {
            e.preventDefault();
            
            const $notice = $(this).closest('.ghl-upgrade-notice');
            const nonce = $notice.data('nonce');
            
            // Add dismissing animation class
            $notice.addClass('ghl-dismissing');
            
            // Send AJAX request to dismiss
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ghl_crm_dismiss_upgrade_notice',
                    nonce: nonce
                },
                success: function(response) {
                    // Remove the notice after animation completes
                    setTimeout(function() {
                        $notice.remove();
                    }, 300);
                },
                error: function(xhr, status, error) {
                    console.error('Failed to dismiss upgrade notice:', error);
                    // Still remove it from UI even if AJAX fails
                    setTimeout(function() {
                        $notice.remove();
                    }, 300);
                }
            });
        });
    });

})(jQuery);
