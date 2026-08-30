/**
 * GoHighLevel CRM Integration - UI Mode Toggle
 *
 * Handles the Simple/Advanced display mode switch in the admin header.
 *
 * @package    Syncly
 * @subpackage Syncly/assets/admin/js
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    var config = window.synclySpaConfig && window.synclySpaConfig.uiMode;

    if (!config || !config.nonce) {
      return;
    }

    var $toggle = $(".ghl-ui-mode-toggle");
    if (!$toggle.length) {
      return;
    }

    $toggle.on(
      "click",
      ".ghl-ui-mode-btn",
      function (e) {
        e.preventDefault();

        var $btn = $(this);
        var mode = $btn.data("mode");

        if (
          !mode ||
          config.mode === mode ||
          $btn.hasClass("is-disabled")
        ) {
          return;
        }

        var $buttons = $toggle.find(".ghl-ui-mode-btn");
        $buttons.addClass("is-disabled").attr("aria-disabled", "true");

        $.ajax({
          url: config.ajaxUrl,
          type: "POST",
          data: {
            action: "syncly_update_ui_mode",
            nonce: config.nonce,
            mode: mode,
          },
        })
          .done(function (response) {
            if (response && response.success) {
              // Reload so the server re-renders the full UI in the new mode.
              window.location.reload();
              return;
            }

            showError(
              (response &&
                response.data &&
                response.data.message) ||
                config.strings.error
            );
            reset();
          })
          .fail(function () {
            showError(config.strings.error);
            reset();
          });

        function reset() {
          $buttons.removeClass("is-disabled").attr("aria-disabled", "false");
        }

        function showError(message) {
          if (typeof Swal !== "undefined") {
            Swal.fire({
              toast: true,
              position: "top-end",
              icon: "error",
              title: message || config.strings.error,
              showConfirmButton: false,
              timer: 3500,
              timerProgressBar: true,
            });
          } else {
            /* eslint-disable no-alert */
            window.alert(message || config.strings.error);
            /* eslint-enable no-alert */
          }
        }
      }
    );
  });
})(jQuery);
