/** Syncly controls for the asynchronously loaded WPForms settings panel. */
(function ($) {
  "use strict";

  const data =
    typeof syncly_wpforms_js_data !== "undefined"
      ? syncly_wpforms_js_data
      : {};
  const panelSelector = ".syncly-wpforms-panel";
  let fields = null;
  let request = null;
  let scheduled = false;

  function schedule() {
    if (scheduled) {
      return;
    }
    scheduled = true;
    window.requestAnimationFrame(function () {
      scheduled = false;
      $(panelSelector).filter(":visible").each(function () {
        init($(this));
      });
      if (!$(panelSelector).is(":visible")) {
        $(panelSelector).find("select").each(function () {
          if ($(this).data("select2")) {
            $(this).select2("close");
          }
        });
      }
    });
  }

  function emailNotice($panel) {
    const mapped = $panel
      .find(".ghl-field-select")
      .toArray()
      .some(el => el.value === "email");
    $panel.find("#syncly_wpforms_email_notice").toggle(!mapped);
  }

  function syncTags($panel) {
    $panel.find("#syncly_wpforms_tags_value").val(
      ($panel.find("#syncly_wpforms_tags").val() || [])
        .filter(Boolean)
        .join(",")
    );
  }

  function enhance($select, $portal, options) {
    if (
      !$select.length ||
      typeof $.fn.select2 !== "function" ||
      $select.data("select2")
    ) {
      return;
    }
    $select.select2(Object.assign({
      width: "100%",
      allowClear: true,
      dropdownParent: $portal
    }, options));
  }

  function populate($panel, $portal) {
    $panel.find(".ghl-field-select").each(function () {
      const $select = $(this);
      if (!$select.data("synclyFieldsLoaded")) {
        const value = $select.data("synclyChanged")
          ? $select.val() || ""
          : $select.val() || $select.attr("data-saved-value") || "";
        $select.empty().append(new Option(fields[""] || "— Do not sync —", ""));
        Object.keys(fields)
          .filter(key => key !== "")
          .forEach(key => $select.append(new Option(fields[key], key)));

        // Retain a saved mapping even if the remote field is no longer returned.
        if (value && !Object.prototype.hasOwnProperty.call(fields, value)) {
          $select.append(new Option(value, value));
        }
        $select.val(value).data("synclyFieldsLoaded", true).trigger("change.select2");
      }
      enhance($select, $portal, {
        placeholder: $select.data("placeholder") || "— Do not sync —"
      });
    });
    emailNotice($panel);
  }

  function init($panel) {
    let $portal = $("#syncly-wpforms-dropdowns");
    if (!$portal.length) {
      $portal = $(
        '<div id="syncly-wpforms-dropdowns" class="syncly-wpforms-dropdowns"></div>'
      ).appendTo(document.body);
    }
    if (!$panel.data("synclyBound")) {
      $panel.data("synclyBound", true).on("change.synclyWPForms", ".ghl-checkbox-original", function () {
        $(this)
          .closest(".ghl-checkbox")
          .toggleClass("is-checked", this.checked)
          .find(".ghl-checkbox-input")
          .toggleClass("is-checked", this.checked);

        if (this.id === "syncly_wpforms_enabled") {
          $panel.find("#syncly_wpforms_settings_container").toggle(this.checked);
          if (!this.checked) {
            $panel.find("select").each(function () {
              if ($(this).data("select2")) {
                $(this).select2("close");
              }
            });
          }
          schedule();
        }
      }).on("change.synclyWPForms", ".ghl-field-select", function () {
        $(this).data("synclyChanged", true);
        emailNotice($panel);
      }).on("change.synclyWPForms", "#syncly_wpforms_tags", function () {
        syncTags($panel);
      });
    }
    const $tags = $panel.find("#syncly_wpforms_tags");
    if ($tags.length && !$tags.data("synclyTagsLoaded")) {
      const saved = $tags.data("saved-tags") || [];
      const initial = $tags.val() && $tags.val().length
        ? $tags.val()
        : Array.isArray(saved)
          ? saved
          : [];
      const names = new Set(
        (data.tags || [])
          .map(tag => typeof tag === "string" ? tag : tag.name)
          .filter(Boolean)
          .concat(initial)
      );

      $tags.empty();
      names.forEach(name => $tags.append(new Option(name, name)));
      $tags.val(initial).data("synclyTagsLoaded", true);
      syncTags($panel);
    }
    enhance($tags, $portal, {
      tags: true,
      tokenSeparators: [ "," ],
      placeholder: $tags.data("placeholder") || "Select tags...",
      closeOnSelect: false
    });
    if (fields) {
      populate($panel, $portal);
      return;
    }
    if (request || !data.nonce) {
      return;
    }
    request = $.ajax({
      url: window.ajaxurl,
      type: "POST",
      data: {
        action: "syncly_get_custom_fields",
        nonce: data.nonce
      }
    });
    request.done(function (response) {
      if (response && response.success && response.data && response.data.fields) {
        fields = response.data.fields;
        schedule();
      } else {
        showError();
      }
    }).fail(showError);
  }

  function showError() {
    $(panelSelector).find(".ghl-field-select").each(function () {
      const $select = $(this);
      // Keep existing mappings; replace only the initial loading placeholder.
      if (!$select.val()) {
        $select
          .empty()
          .append(new Option("— Failed to load fields; reload to retry —", ""))
          .trigger("change.select2");
      }
    });
  }

  $(function () {
    // Native events cover initial loading and navigation between builder sections.
    $(document).on(
      "wpformsBuilderReady.synclyWPForms wpformsBuilderPanelLoaded.synclyWPForms wpformsPanelSwitched.synclyWPForms wpformsPanelSectionSwitched.synclyWPForms",
      schedule
    );

    const builder = document.getElementById("wpforms-builder");
    if (builder && typeof MutationObserver !== "undefined") {
      new MutationObserver(function (records) {
        const relevant = records.some(function (record) {
          if (record.type === "attributes") {
            return record.target.matches(".wpforms-panel-content-section-syncly, #wpforms-panel-settings");
          }
          return Array.from(record.addedNodes).some(
            node => node.nodeType === 1 && (
              node.matches(panelSelector) || node.querySelector(panelSelector)
            )
          );
        });
        if (relevant) {
          schedule();
        }
      }).observe(builder, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: [ "class", "style" ]
      });
    }
    schedule();
  });
})(jQuery);
