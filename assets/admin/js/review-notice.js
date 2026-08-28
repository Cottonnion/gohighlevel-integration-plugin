/**
 * Syncly - Review Notice
 *
 * Handles dismissal of the review request banner
 *
 * @package    Syncly
 * @subpackage Syncly/assets/admin/js
 */

(function ($) {
  "use strict";

  $(document).ready(function () {
    $(".ghl-dismiss-review-notice").on("click", function (e) {
      e.preventDefault();

      var $notice = $(this).closest(".ghl-review-notice");
      var nonce = $notice.data("nonce");

      $notice.addClass("ghl-dismissing");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "syncly_dismiss_review_notice",
          nonce: nonce,
        },
        success: function () {
          setTimeout(function () {
            $notice.remove();
          }, 300);
        },
        error: function () {
          setTimeout(function () {
            $notice.remove();
          }, 300);
        },
      });
    });
  });
})(jQuery);
