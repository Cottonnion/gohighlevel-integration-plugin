/**
 * BuddyBoss Group Meta Box
 * Handles sync actions in group admin screen
 */
(function ($) {
  "use strict";

  // AssetsManager localizes as ghl_buddyboss_group_meta_box_js_data.
  const ghlBuddyBossGroup = window.ghl_buddyboss_group_meta_box_js_data || {};

  const GHLBuddyBossGroupMetaBox = {
    /**
     * Initialize
     */
    init: function () {
      this.bindEvents();
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      $(document).on(
        "click",
        "#ghl-sync-group-btn",
        this.handleGroupSync.bind(this),
      );
      $(document).on(
        "click",
        "#ghl-sync-members-btn",
        this.handleMembersSync.bind(this),
      );
    },

    /**
     * Handle group sync
     */
    handleGroupSync: function (e) {
      e.preventDefault();

      const $btn = $(e.currentTarget);
      const groupId = $btn.data("group-id");
      const nonce = $btn.data("nonce");

      this.performSync("group", groupId, nonce, $btn);
    },

    /**
     * Handle members sync
     */
    handleMembersSync: function (e) {
      e.preventDefault();

      const $btn = $(e.currentTarget);
      const groupId = $btn.data("group-id");
      const nonce = $btn.data("nonce");

      // Use the group sync nonce for members too (reuse)
      const groupSyncNonce = $("#ghl-sync-group-btn").data("nonce");

      this.performSync("members", groupId, groupSyncNonce, $btn);
    },

    /**
     * Perform sync request
     */
    performSync: function (syncType, groupId, nonce, $btn) {
      const $spinner = $(".ghl-sync-spinner");
      const $message = $(".ghl-sync-message");
      const originalText = $btn.html();

      // Disable buttons and show spinner
      $(".ghl-sync-btn").prop("disabled", true);
      $spinner.addClass("is-active").show();
      $message.hide();

      // Update button text
      $btn.html(
        '<span class="dashicons dashicons-update"></span> ' +
          ghlBuddyBossGroup.strings.syncing,
      );

      $.ajax({
        url: ghlBuddyBossGroup.ajaxUrl,
        type: "POST",
        data: {
          action: "ghl_sync_buddyboss_group",
          group_id: groupId,
          sync_type: syncType,
          nonce: nonce,
        },
        success: function (response) {
          if (response.success) {
            $message
              .removeClass("error")
              .addClass("success")
              .text(response.data.message)
              .show();
          } else {
            $message
              .removeClass("success")
              .addClass("error")
              .text(
                response.data.message || ghlBuddyBossGroup.strings.syncError,
              )
              .show();
          }
        },
        error: function () {
          $message
            .removeClass("success")
            .addClass("error")
            .text(ghlBuddyBossGroup.strings.syncError)
            .show();
        },
        complete: function () {
          // Re-enable buttons and hide spinner
          $(".ghl-sync-btn").prop("disabled", false);
          $spinner.removeClass("is-active").hide();
          $btn.html(originalText);

          // Auto-hide message after 5 seconds
          setTimeout(function () {
            $message.fadeOut();
          }, 5000);
        },
      });
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    GHLBuddyBossGroupMetaBox.init();
  });
})(jQuery);
