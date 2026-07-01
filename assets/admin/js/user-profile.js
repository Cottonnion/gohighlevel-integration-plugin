/**
 * GoHighLevel User Profile Integration
 * Handles tag management with Select2 and sync controls
 */
(function ($) {
  "use strict";

  // AssetsManager localizes as syncly_user_profile_js_data.
  const synclyUserProfile = window.syncly_user_profile_js_data || {};

  const SynclyUserProfile = {
    /**
     * Initialize
     */
    init: function () {
      this.initSelect2();
      this.initActivityTimeline();
      // this.initSyncButton();
    },

    /**
     * Initialize Select2 for tags
     */
    initSelect2: function () {
      const $tagsSelect = $("#ghl-contact-tags");

      if ($tagsSelect.length === 0) {
        return;
      }

      // Pre-populate options from localized tags (already selected tags are in HTML)
      var allTags = synclyUserProfile.tags || [];
      var selectedIds = $tagsSelect
        .find("option")
        .map(function () {
          return $(this).val();
        })
        .get();

      allTags.forEach(function (tag) {
        var id = String(tag.id || tag.name || "");
        var name = String(tag.name || tag.id || "");
        if (id && selectedIds.indexOf(id) === -1) {
          $tagsSelect.append(new Option(name, id, false, false));
        }
      });

      $tagsSelect.select2({
        tags: true,
        tokenSeparators: [","],
        placeholder: synclyUserProfile.strings.searchTags,
        closeOnSelect: false,
        allowClear: true,
        width: "100%",
        scrollAfterSelect: false,
      });
    },

    /**
     * Initialize Sync Now button
     */
    initSyncButton: function () {
      const self = this;

      $(".ghl-sync-now-btn").on("click", function (e) {
        e.preventDefault();

        const $button = $(this);
        const userId = $button.data("user-id");

        if (!userId) {
          alert("Invalid user ID");
          return;
        }

        // Confirm action
        if (!confirm(synclyUserProfile.strings.confirmSync)) {
          return;
        }

        self.syncUserNow(userId, $button);
      });
    },

    /**
     * Sync user now via AJAX
     */
    syncUserNow: function (userId, $button) {
      const self = this; // Store reference to SynclyUserProfile object
      const $loading = $(".ghl-loading");

      // Disable button and show loading
      $button.prop("disabled", true);
      $loading.addClass("active is-active");

      $.ajax({
        url: synclyUserProfile.ajaxUrl,
        type: "POST",
        data: {
          action: "syncly_sync_user_now",
          nonce: synclyUserProfile.nonce,
          user_id: userId,
        },
        success: function (response) {
          if (response.success) {
            // Show success message
            self.showNotice("success", synclyUserProfile.strings.syncSuccess);

            // Reload page after 1 second to show updated data
            setTimeout(function () {
              location.reload();
            }, 1000);
          } else {
            self.showNotice(
              "error",
              response.data.message || synclyUserProfile.strings.syncError,
            );
            $button.prop("disabled", false);
            $loading.removeClass("active is-active");
          }
        },
        error: function () {
          self.showNotice("error", synclyUserProfile.strings.syncError);
          $button.prop("disabled", false);
          $loading.removeClass("active is-active");
        },
      });
    },

    /**
     * Show admin notice
     */
    showNotice: function (type, message) {
      const noticeClass =
        type === "success" ? "notice-success" : "notice-error";
      const $notice = $("<div>")
        .addClass("notice " + noticeClass + " is-dismissible")
        .html("<p>" + message + "</p>");

      // Insert after page title
      $(".wrap h1").first().after($notice);

      // Make dismissible
      $(document).trigger("wp-updates-notice-added");

      // Auto-dismiss after 5 seconds
      setTimeout(function () {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      }, 5000);
    },

    /**
     * Initialize refresh from GHL button
     */
    initRefreshFromGHL: function () {
      const self = this;

      $(document).on("click", ".ghl-refresh-from-ghl-btn", function (e) {
        e.preventDefault();

        const $button = $(this);
        const userId = $button.data("user-id");
        const contactId = $button.data("contact-id");
        const $loading = $(".ghl-loading");

        if (!userId || !contactId) {
          alert("Invalid user ID or contact ID");
          return;
        }

        // Disable button and show loading
        $button.prop("disabled", true);
        const originalHtml = $button.html();
        $button.html(
          '<span class="dashicons dashicons-update"></span> Syncing...',
        );
        $loading.addClass("active is-active");

        $.ajax({
          url: synclyUserProfile.ajaxUrl,
          type: "POST",
          data: {
            action: "syncly_refresh_from_ghl",
            nonce: synclyUserProfile.nonce,
            user_id: userId,
            contact_id: contactId,
          },
          success: function (response) {
            if (response.success) {
              // Show success message
              self.showNotice("success", response.data.message);

              // Update tags in Select2 if returned
              if (
                response.data &&
                response.data.tag_pairs &&
                response.data.tag_pairs.length > 0
              ) {
                const $tagsSelect = $("#ghl-contact-tags");
                $tagsSelect.empty();
                const pairs = response.data.tag_pairs;

                pairs.forEach(function (pair) {
                  if (
                    !pair ||
                    (pair.id === undefined && pair.name === undefined)
                  ) {
                    return;
                  }

                  var id = "";
                  var name = "";

                  if (pair.id !== undefined && pair.id !== null) {
                    id = String(pair.id);
                  }

                  if (pair.name !== undefined && pair.name !== null) {
                    name = String(pair.name);
                  }

                  if (!id && name) {
                    id = name;
                  }

                  if (!name && id) {
                    name = id;
                  }

                  if (!id) {
                    return;
                  }

                  const option = new Option(name, id, true, true);
                  option.dataset.tagName = name;
                  $tagsSelect.append(option);
                });

                $tagsSelect.trigger("change");
              } else if (
                response.data &&
                response.data.tags &&
                response.data.tags.length > 0
              ) {
                const $tagsSelect = $("#ghl-contact-tags");
                $tagsSelect.empty();

                response.data.tags.forEach(function (tag) {
                  var label = String(tag || "");
                  if (!label) {
                    return;
                  }
                  const option = new Option(label, label, true, true);
                  option.dataset.tagName = label;
                  $tagsSelect.append(option);
                });

                $tagsSelect.trigger("change");
              }

              // Reload page after 1.5 seconds to show all updated data
              setTimeout(function () {
                location.reload();
              }, 1500);
            } else {
              self.showNotice(
                "error",
                response.data.message || "Failed to sync from GoHighLevel",
              );
              $button.html(originalHtml);
            }
          },
          error: function () {
            self.showNotice(
              "error",
              "Failed to sync from GoHighLevel. Please try again.",
            );
            $button.html(originalHtml);
          },
          complete: function () {
            $button.prop("disabled", false);
            $loading.removeClass("active is-active");
          },
        });
      });
    },

    /**
     * Initialize sync to GHL button
     */
    initSyncToGHL: function () {
      const self = this;

      $(document).on("click", ".ghl-sync-to-ghl-btn", function (e) {
        e.preventDefault();

        const $button = $(this);
        const userId = $button.data("user-id");
        const $loading = $(".ghl-loading");

        if (!userId) {
          alert("Invalid user ID");
          return;
        }

        // Disable button and show loading
        $button.prop("disabled", true);
        const originalHtml = $button.html();
        $button.html(
          '<span class="dashicons dashicons-update"></span> Syncing...',
        );
        $loading.addClass("active is-active");

        $.ajax({
          url: synclyUserProfile.ajaxUrl,
          type: "POST",
          data: {
            action: "syncly_sync_user_now",
            nonce: synclyUserProfile.nonce,
            user_id: userId,
          },
          success: function (response) {
            if (response.success) {
              // Show success message
              self.showNotice(
                "success",
                synclyUserProfile.strings.syncToSuccess ||
                  "Successfully queued for sync to GoHighLevel!",
              );

              // Reload page after 1.5 seconds
              setTimeout(function () {
                location.reload();
              }, 1500);
            } else {
              self.showNotice(
                "error",
                response.data.message ||
                  synclyUserProfile.strings.syncToError ||
                  "Failed to sync to GoHighLevel",
              );
              $button.html(originalHtml);
            }
          },
          error: function () {
            self.showNotice(
              "error",
              synclyUserProfile.strings.syncToError ||
                "Failed to sync to GoHighLevel. Please try again.",
            );
            $button.html(originalHtml);
          },
          complete: function () {
            $button.prop("disabled", false);
            $loading.removeClass("active is-active");
          },
        });
      });
    },

    /**
     * Initialize auto-login functionality
     */
    initAutoLogin: function () {
      const self = this;

      // Generate login link
      $(document).on("click", ".ghl-generate-login-link", function (e) {
        e.preventDefault();
        const $button = $(this);
        const userId = $button.data("user-id");
        const $display = $(".ghl-login-link-display");
        const $input = $("#ghl-login-link-input");

        $button.prop("disabled", true);
        const originalText = $button.html();
        $button.html(
          '<span class="dashicons dashicons-update"></span> Generating...',
        );

        $.ajax({
          url: synclyUserProfile.ajaxUrl,
          type: "POST",
          data: {
            action: "syncly_generate_login_link",
            user_id: userId,
            nonce: synclyUserProfile.nonce,
          },
          success: function (response) {
            if (response.success && response.data.login_url) {
              $input.val(response.data.login_url);
              $display.slideDown();
              $button.html(
                '<span class="dashicons dashicons-admin-network"></span> Generate New Link',
              );
            } else {
              alert(
                "Error: " +
                  (response.data.message || "Failed to generate login link"),
              );
              $button.html(originalText);
            }
          },
          error: function () {
            alert("Failed to generate login link. Please try again.");
            $button.html(originalText);
          },
          complete: function () {
            $button.prop("disabled", false);
          },
        });
      });

      // Copy to clipboard
      $(document).on("click", ".ghl-copy-login-link", function (e) {
        e.preventDefault();
        const $input = $("#ghl-login-link-input")[0];
        const $button = $(".ghl-copy-login-link");
        const originalHtml = $button.html();

        $input.select();
        $input.setSelectionRange(0, 99999);

        let success = false;

        // Try modern clipboard API first (requires HTTPS)
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard
            .writeText($input.value)
            .then(function () {
              $button.html(
                '<span class="dashicons dashicons-yes"></span> Copied!',
              );
              setTimeout(function () {
                $button.html(originalHtml);
              }, 2000);
            })
            .catch(function () {
              // Fallback to execCommand
              self.copyToClipboardFallback($input, $button, originalHtml);
            });
        } else {
          // Use fallback method
          self.copyToClipboardFallback($input, $button, originalHtml);
        }
      });
    },

    /**
     * Fallback method for copying to clipboard
     */
    copyToClipboardFallback: function ($input, $button, originalHtml) {
      try {
        $input.select();
        $input.setSelectionRange(0, 99999);
        const successful = document.execCommand("copy");

        if (successful) {
          $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
          setTimeout(function () {
            $button.html(originalHtml);
          }, 2000);
        } else {
          alert("Failed to copy. Please copy manually: " + $input.value);
        }
      } catch (err) {
        alert("Failed to copy. Please copy manually: " + $input.value);
      }
    },

    /**
     * Initialize Activity Timeline collapse/expand and pagination
     */
    initActivityTimeline: function () {
      const self = this;
      let currentPage = 1;

      // Toggle expand/collapse
      $('[data-toggle="ghl-activity-timeline"]').on("click", function () {
        const $wrapper = $(".ghl-activity-content-wrapper");
        const $toggle = $(".ghl-activity-toggle");

        if ($wrapper.is(":visible")) {
          $wrapper.slideUp(300);
          $toggle.css("transform", "rotate(0deg)");
        } else {
          $wrapper.slideDown(300);
          $toggle.css("transform", "rotate(180deg)");
        }
      });

      // Pagination - Previous
      $(".ghl-activity-prev").on("click", function () {
        if (currentPage > 1) {
          currentPage--;
          self.switchActivityPage(currentPage);
        }
      });

      // Pagination - Next
      $(".ghl-activity-next").on("click", function () {
        const totalPages = parseInt($(this).data("total-pages")) || 1;
        if (currentPage < totalPages) {
          currentPage++;
          self.switchActivityPage(currentPage);
        }
      });
    },

    /**
     * Switch activity timeline page
     */
    switchActivityPage: function (page) {
      // Hide all pages
      $(".ghl-activity-page").hide();

      // Show target page
      $('.ghl-activity-page[data-page="' + page + '"]').show();

      // Update page counter
      $(".ghl-current-page").text(page);

      // Update button states
      const $prevBtn = $(".ghl-activity-prev");
      const $nextBtn = $(".ghl-activity-next");
      const totalPages = parseInt($nextBtn.data("total-pages")) || 1;

      if (page === 1) {
        $prevBtn.hide();
      } else {
        $prevBtn.show();
      }

      if (page >= totalPages) {
        $nextBtn.hide();
      } else {
        $nextBtn.show();
      }

      // Scroll to timeline
      $("html, body").animate(
        {
          scrollTop: $(".ghl-activity-timeline").offset().top - 100,
        },
        300,
      );
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    SynclyUserProfile.init();
    SynclyUserProfile.initRefreshFromGHL();
    SynclyUserProfile.initSyncToGHL();
    SynclyUserProfile.initAutoLogin();
  });
})(jQuery);
