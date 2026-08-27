/**
 * GoHighLevel Users List Integration
 * Initializes Select2 on the GHL tags filter in the users list table.
 */
(function ($) {
  "use strict";

  const SynclyUserColumns = {
    /**
     * Initialize
     */
    init: function () {
      this.initTagFilterSelect2();
    },

    /**
     * Initialize Select2 on the restrict-manage-users tag filter.
     */
    initTagFilterSelect2: function () {
      const $filter = $("#syncly-ghl-tags-filter");

      if ($filter.length === 0 || typeof $filter.select2 !== "function") {
        return;
      }

      $filter.select2({
        placeholder: $filter.data("placeholder") || "Tags",
        allowClear: true,
        closeOnSelect: false,
        width: "220px"
      });
    }
  };

  $(function () {
    SynclyUserColumns.init();
  });
})(jQuery);