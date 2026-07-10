(function($) {
  'use strict';

  $(document).on('click', '.ghl-dashboard-tab', function() {
    const tab = $(this).data('tab');

    $('.ghl-dashboard-tab').removeClass('active');
    $(this).addClass('active');

    $('.ghl-tab-content').hide();
    $('#ghl-tab-' + tab).show();

    localStorage.setItem('ghl_active_dashboard_tab', tab);
  });

  $(function() {
    const lastTab = localStorage.getItem('ghl_active_dashboard_tab');
    if (lastTab && lastTab === 'analytics') {
      $('.ghl-dashboard-tab[data-tab="analytics"]').trigger('click');
    }
  });
})(jQuery);

/**
 * Dashboard JavaScript
 *
 * Handles dashboard tab switching, connection form submission, and disconnect functionality
 *
 * @package Syncly
 */

(function ($) {
  "use strict";

  /**
   * Initialize dashboard functionality
   */
  function initDashboard() {
    initTabSwitching();
    initDisconnectButton();
    initQuickActions();
  }

  // Expose init function globally for SPA router
  window.initDashboard = initDashboard;

  /**
   * Initialize tab switching functionality
   */
  function initTabSwitching() {
    $(".ghl-tab-button").on("click", function () {
      var tabId = $(this).data("tab");

      // Update button states
      $(".ghl-tab-button").removeClass("active");
      $(this).addClass("active");

      // Update content visibility
      $(".ghl-tab-content").removeClass("active");
      $("#" + tabId + "-tab").addClass("active");
    });
  }

  /**
   * Initialize dashboard quick action buttons (manual sync, cache clear, etc.)
   */
  function initQuickActions() {
    const ajaxEndpoint =
      typeof ajaxurl !== "undefined"
        ? ajaxurl
        : syncly_dashboard_js_data.ajaxUrl;

    const $manualSyncBtn = jQuery("#ghl-trigger-sync");
    const $clearCacheBtn = jQuery("#ghl-clear-cache");
    const $testConnBtn = jQuery("#ghl-test-connection");
    const $refreshMetaBtn = jQuery("#ghl-refresh-tags-fields");
    const $reconnectBtn = jQuery("#ghl-reconnect-account");
    const i18n = syncly_dashboard_js_data.i18n || {};

    if ($manualSyncBtn.length) {
      $manualSyncBtn.on("click", function (event) {
        event.preventDefault();
        handleQuickAction(jQuery(this), {
          action: "syncly_manual_queue_trigger",
          data: {
            action: "syncly_manual_queue_trigger",
            nonce: syncly_dashboard_js_data.manualQueueNonce,
          },
          loadingText: i18n.manualSyncProcessing || "Processing queue...",
          successMessage: function (response) {
            if (response?.data?.message) {
              return response.data.message;
            }
            const processed = response?.data?.processed ?? 0;
            if (processed) {
              return (
                (i18n.manualSyncSuccess ||
                  "Manual sync completed successfully.") +
                " (" +
                processed +
                ")"
              );
            }
            return (
              i18n.manualSyncSuccess || "Manual sync completed successfully."
            );
          },
          errorMessage: function (response) {
            return (
              response?.data?.message ||
              i18n.manualSyncFailed ||
              "Manual sync failed."
            );
          },
          ajaxUrl: ajaxEndpoint,
        });
      });
    }

    if ($clearCacheBtn.length) {
      $clearCacheBtn.on("click", function (event) {
        event.preventDefault();
        handleQuickAction(jQuery(this), {
          action: "syncly_clear_cache",
          data: {
            action: "syncly_clear_cache",
            nonce: syncly_dashboard_js_data.settingsNonce,
          },
          loadingText: i18n.clearCacheProcessing || "Clearing cache...",
          successMessage: function (response) {
            return (
              response?.data?.message ||
              i18n.clearCacheSuccess ||
              "Cache cleared successfully!"
            );
          },
          errorMessage: function (response) {
            return (
              response?.data?.message ||
              i18n.clearCacheFailed ||
              "Failed to clear cache."
            );
          },
          ajaxUrl: ajaxEndpoint,
        });
      });
    }

    if ($testConnBtn.length) {
      $testConnBtn.on("click", function (event) {
        event.preventDefault();
        handleQuickAction(jQuery(this), {
          action: "syncly_test_connection",
          data: {
            action: "syncly_test_connection",
            nonce: syncly_dashboard_js_data.settingsNonce,
          },
          loadingText: i18n.testConnectionProcessing || "Testing connection...",
          successMessage: function (response) {
            if (response?.data?.message) {
              return response.data.message;
            }
            return (
              i18n.testConnectionSuccess ||
              "Connection test completed successfully."
            );
          },
          errorMessage: function (response) {
            return (
              response?.data?.message ||
              i18n.testConnectionFailed ||
              "Connection test failed."
            );
          },
          ajaxUrl: ajaxEndpoint,
        });
      });
    }

    if ($refreshMetaBtn.length) {
      $refreshMetaBtn.on("click", function (event) {
        event.preventDefault();
        handleQuickAction(jQuery(this), {
          action: "syncly_refresh_metadata",
          data: {
            action: "syncly_refresh_metadata",
            nonce: syncly_dashboard_js_data.nonce,
          },
          loadingText:
            i18n.refreshMetadataProcessing || "Refreshing metadata...",
          successMessage: function (response) {
            const tagsCount = response?.data?.tags_count ?? 0;
            const fieldsCount = response?.data?.custom_fields_count ?? 0;
            const baseMessage =
              response?.data?.message ||
              i18n.refreshMetadataSuccess ||
              "Tags and fields refreshed successfully.";
            return (
              baseMessage +
              " (" +
              tagsCount +
              " tags, " +
              fieldsCount +
              " fields)"
            );
          },
          errorMessage: function (response) {
            return (
              response?.data?.message ||
              i18n.refreshMetadataFailed ||
              "Failed to refresh tags and fields."
            );
          },
          ajaxUrl: ajaxEndpoint,
        });
      });
    }

    if ($reconnectBtn.length) {
      $reconnectBtn.on("click", function (event) {
        event.preventDefault();
        handleQuickAction(jQuery(this), {
          action: "syncly_oauth_reconnect",
          data: {
            action: "syncly_oauth_reconnect",
            nonce: syncly_dashboard_js_data.settingsNonce,
          },
          loadingText: "Reconnecting...",
          successMessage: function (response) {
            return (
              response?.data?.message || "Redirecting to reconnect your account..."
            );
          },
          errorMessage: function (response) {
            return (
              response?.data?.message || "Failed to reconnect account."
            );
          },
          onSuccess: function (response) {
            const redirectUrl = response?.data?.redirect_url;
            if (redirectUrl) {
              window.location.href = redirectUrl;
            }
          },
          ajaxUrl: ajaxEndpoint,
        });
      });
    }
  }

  /**
   * Generic handler for dashboard quick action buttons.
   *
   * @param {jQuery} $button Button element.
   * @param {Object} config Configuration object.
   */
  function handleQuickAction($button, config) {
    if (!$button.length || $button.data("ghl-loading")) {
      return;
    }

    const originalHtml = $button.html();
    const loadingText = config.loadingText || "Processing...";
    const spinnerHtml =
      '<span class="dashicons dashicons-update" style="animation: rotation 1s linear infinite; margin-right: 6px;"></span>';
    const ajaxUrl =
      config.ajaxUrl ||
      (typeof ajaxurl !== "undefined"
        ? ajaxurl
        : syncly_dashboard_js_data.ajaxUrl);

    $button
      .data("ghl-loading", true)
      .data("ghl-original-html", originalHtml)
      .prop("disabled", true)
      .html(spinnerHtml + loadingText);

    jQuery.ajax({
      url: ajaxUrl,
      type: "POST",
      data: config.data,
      success: function (response) {
        if (response && response.success) {
          const message =
            typeof config.successMessage === "function"
              ? config.successMessage(response)
              : config.successMessage || response?.data?.message || "Success.";
          showNotice("success", message);
          if (typeof config.onSuccess === "function") {
            config.onSuccess(response);
          }
        } else {
          const message =
            typeof config.errorMessage === "function"
              ? config.errorMessage(response)
              : config.errorMessage ||
                response?.data?.message ||
                "Something went wrong.";
          showNotice("error", message);
        }
      },
      error: function (jqXHR, textStatus) {
        const errorMsg =
          typeof config.errorMessage === "function"
            ? config.errorMessage()
            : jQuery.trim(jqXHR.responseText) ||
              textStatus ||
              "Request failed.";
        showNotice("error", errorMsg);
      },
      complete: function () {
        restoreButtonState($button);
      },
    });
  }

  /**
   * Restore button UI state after quick action completes.
   *
   * @param {jQuery} $button Button element.
   */
  function restoreButtonState($button) {
    const originalHtml = $button.data("ghl-original-html");
    $button
      .prop("disabled", false)
      .removeData("ghl-loading")
      .removeData("ghl-original-html")
      .html(originalHtml);
  }

  /**
   * Display SweetAlert toast notifications (fallbacks to alert).
   *
   * @param {string} type Notification type.
   * @param {string} message Message to display.
   */
  function showNotice(type, message) {
    if (typeof Swal !== "undefined") {
      const toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        customClass: {
          popup: "ghl-swal-top-toast",
        },
      });

      toast.fire({
        icon: type === "error" ? "error" : "success",
        title: message,
      });
      return;
    }

    window.alert(message); // Fallback when SweetAlert is not available.
  }

  /**
   * Initialize disconnect button functionality
   */
  function initDisconnectButton() {
    // OAuth disconnect button
    $("#ghl-disconnect-btn").on("click", function (e) {
      e.preventDefault();

      var $btn = $(this);
      var originalText = $btn.text();

      // Show confirmation dialog
      Swal.fire({
        title:
          syncly_dashboard_js_data.i18n.disconnectConfirm || "Are you sure?",
        text: "You will need to reconnect to use the integration features.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc3232",
        cancelButtonColor: "#2271b1",
        confirmButtonText: "Yes, disconnect",
        cancelButtonText: "Cancel",
      }).then((result) => {
        if (result.isConfirmed) {
          // Show loading state
          $btn
            .prop("disabled", true)
            .text(syncly_dashboard_js_data.i18n.disconnecting);

          $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
              action: "syncly_oauth_disconnect",
              nonce: syncly_dashboard_js_data.disconnectNonce,
            },
            success: function (response) {
              if (response.success) {
                Swal.fire({
                  icon: "success",
                  title: "Disconnected!",
                  text: response.data.message,
                  timer: 1500,
                  showConfirmButton: false,
                }).then(() => {
                  location.reload();
                });
              } else {
                var errorMsg =
                  response.data && response.data.message
                    ? response.data.message
                    : syncly_dashboard_js_data.i18n.disconnectFailed;
                Swal.fire({
                  icon: "error",
                  title: "Disconnect Failed",
                  text: errorMsg,
                });
                $btn.prop("disabled", false).text(originalText);
              }
            },
            error: function () {
              Swal.fire({
                icon: "error",
                title: "Connection Error",
                text: syncly_dashboard_js_data.i18n.disconnectError,
              });
              $btn.prop("disabled", false).text(originalText);
            },
          });
        }
      });
    });

  }

  // Initialize on document ready
  $(document).ready(function () {
    initDashboard();
  });
})(jQuery);
