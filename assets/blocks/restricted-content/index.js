/**
 * Restricted Content Block
 *
 * Gutenberg block for restricting content based on GoHighLevel tags
 */
(function () {
  "use strict";

  if (!window.wp || !window.wp.blocks || !window.wp.element) {
    return;
  }

  var el = window.wp.element.createElement;
  var Fragment = window.wp.element.Fragment;
  var useEffect = window.wp.element.useEffect;
  var useRef = window.wp.element.useRef;
  var RawHTML = window.wp.element.RawHTML;
  var useSelect = window.wp.data ? window.wp.data.useSelect : null;
  var blocks = window.wp.blocks;
  var blockEditor = window.wp.blockEditor || window.wp.editor;
  var components = window.wp.components;
  var __ = window.wp.i18n.__;
  var $ = window.jQuery;

  var blockData = window.ghlRestrictedBlock || { tags: [], connected: false };

  // ─── Helpers ────────────────────────────────────────────────────────────────

  /**
   * Pure function — get human-readable rule label.
   *
   * @param {string} rule
   * @return {string}
   */
  function getRuleLabel(rule) {
    switch (rule) {
      case "any":
        return __("Has ANY of these tags", "ghl-crm-integration");
      case "all":
        return __("Has ALL of these tags", "ghl-crm-integration");
      case "none":
        return __("Does NOT have these tags", "ghl-crm-integration");
      default:
        return __("Restricted", "ghl-crm-integration");
    }
  }

  /**
   * Initialize Select2 on a select element.
   *
   * @param {string}   selectId  DOM id of the <select>.
   * @param {Array}    savedTags Currently saved tag values.
   * @param {Function} onChange  Callback when selection changes.
   */
  function initTagsSelect2(selectId, savedTags, onChange) {
    var $sel = $("#" + selectId);

    if (!$sel.length || typeof $.fn.select2 === "undefined") {
      return;
    }

    if ($sel.data("select2")) {
      return;
    }

    $sel.empty();

    var tags = Array.isArray(savedTags) ? savedTags.map(String) : [];
    var savedSet = new Set(tags);
    var allTags = blockData.tags || [];

    // Add pre-loaded tags
    allTags.forEach(function (tag) {
      var value = String(tag.id);
      var isSelected = savedSet.has(value);
      $sel.append(new Option(tag.text, value, isSelected, isSelected));
    });

    // Add saved tags not in the pre-loaded list
    tags.forEach(function (tagId) {
      if (!$sel.find("option[value='" + tagId + "']").length) {
        $sel.append(new Option(tagId, tagId, true, true));
      }
    });

    $sel.select2({
      tags: true,
      tokenSeparators: [","],
      placeholder: __("Search and select tags...", "ghl-crm-integration"),
      allowClear: true,
      multiple: true,
      width: "100%",
      closeOnSelect: false,
      scrollAfterSelect: false,
      dropdownParent: $sel.parent(),
      minimumResultsForSearch: 0,
    });

    $sel.on("change", function () {
      onChange($sel.val() || []);
    });
  }

  /**
   * Destroy Select2 on a select element if initialized.
   *
   * @param {string} selectId
   */
  function destroyTagsSelect2(selectId) {
    var $sel = $("#" + selectId);
    if ($sel.length && $sel.data("select2")) {
      $sel.off("change");
      $sel.select2("destroy");
    }
  }

  // ─── Block Definition ────────────────────────────────────────────────────────

  blocks.registerBlockType("ghl-crm/restricted-content", {
    apiVersion: 2,
    title: __("Restricted Content", "ghl-crm-integration"),
    description: __(
      "Control content visibility based on GoHighLevel contact tags",
      "ghl-crm-integration",
    ),
    icon: "lock",
    category: "ghl-crm",
    example: {
      attributes: { rule: "any", tags: ["vip", "premium"], showMessage: true },
      innerBlocks: [
        {
          name: "core/paragraph",
          attributes: {
            content: __(
              "This content is restricted to VIP members only",
              "ghl-crm-integration",
            ),
          },
        },
      ],
    },
    attributes: {
      rule: { type: "string", default: "any" },
      tags: { type: "array", default: [] },
      fallbackContent: { type: "string", default: "" },
      showMessage: { type: "boolean", default: true },
      fallbackBgColor: { type: "string", default: "#fff3cd" },
      fallbackTextColor: { type: "string", default: "#856404" },
      fallbackBorderColor: { type: "string", default: "#ffc107" },
      fallbackPadding: { type: "number", default: 12 },
    },

    // ─── Edit ────────────────────────────────────────────────────────────────
    edit: function (props) {
      var attrs = props.attributes;
      var setAttrs = props.setAttributes;
      var clientId = props.clientId;
      var selectId = "ghl-tag-select-" + clientId;

      var isBlockSelected = useSelect
        ? useSelect(
            function (select) {
              return select("core/block-editor").isBlockSelected(clientId);
            },
            [clientId],
          )
        : true;

      var observerRef = useRef(null);

      // Destructure components once per render
      var InspectorControls = blockEditor.InspectorControls;
      var InnerBlocks = blockEditor.InnerBlocks;
      var PanelBody = components.PanelBody;
      var SelectControl = components.SelectControl;
      var TextareaControl = components.TextareaControl;
      var ToggleControl = components.ToggleControl;
      var Placeholder = components.Placeholder;
      var ColorPalette = components.ColorPalette;
      var RangeControl = components.RangeControl;

      // Initialize Select2 via MutationObserver — no polling/retries needed
      useEffect(
        function () {
          if (!isBlockSelected) {
            return;
          }

          // If element already in DOM, init immediately
          if ($("#" + selectId).length) {
            initTagsSelect2(selectId, attrs.tags, function (selected) {
              setAttrs({ tags: selected });
            });
            return;
          }

          // Otherwise observe DOM until element appears
          observerRef.current = new MutationObserver(function () {
            if ($("#" + selectId).length) {
              observerRef.current.disconnect();
              observerRef.current = null;
              initTagsSelect2(selectId, attrs.tags, function (selected) {
                setAttrs({ tags: selected });
              });
            }
          });

          observerRef.current.observe(document.body, {
            childList: true,
            subtree: true,
          });

          return function () {
            if (observerRef.current) {
              observerRef.current.disconnect();
              observerRef.current = null;
            }
            destroyTagsSelect2(selectId);
          };
        },
        [isBlockSelected, selectId],
      ); // tags intentionally excluded — handled by Select2 change event

      // ─── Not Connected ───────────────────────────────────────────────────
      if (!blockData.connected) {
        return el(
          "div",
          { className: "ghl-restricted-content-block" },
          el(
            Placeholder,
            {
              icon: "lock",
              label: __("Restricted Content", "ghl-crm-integration"),
              instructions: __(
                "Please connect to GoHighLevel in plugin settings to use content restrictions.",
                "ghl-crm-integration",
              ),
            },
            el(
              "a",
              {
                href: "/wp-admin/admin.php?page=ghl-crm-settings",
                className: "button button-primary",
              },
              __("Go to Settings", "ghl-crm-integration"),
            ),
          ),
        );
      }

      // ─── Tag Selector ────────────────────────────────────────────────────
      var tagSelector = el(
        "div",
        { className: "ghl-tag-selector-wrapper" },
        el(
          "label",
          {
            style: { display: "block", marginBottom: "8px", fontWeight: "600" },
          },
          __("Select Tags", "ghl-crm-integration"),
        ),
        el("select", {
          id: selectId,
          className: "ghl-tags-select",
          multiple: true,
          style: { width: "100%", minHeight: "36px" },
        }),
        el(
          "p",
          {
            className: "description",
            style: { marginTop: "8px", fontSize: "12px", color: "#666" },
          },
          __(
            "Search for tags or type to create new ones",
            "ghl-crm-integration",
          ),
        ),
      );

      // ─── Inspector ───────────────────────────────────────────────────────
      var inspector = el(
        InspectorControls,
        null,
        el(
          PanelBody,
          {
            title: __("Access Rules", "ghl-crm-integration"),
            initialOpen: true,
          },
          el(SelectControl, {
            label: __("Restriction Rule", "ghl-crm-integration"),
            value: attrs.rule,
            options: [
              {
                label: __("User has ANY of these tags", "ghl-crm-integration"),
                value: "any",
              },
              {
                label: __("User has ALL of these tags", "ghl-crm-integration"),
                value: "all",
              },
              {
                label: __(
                  "User does NOT have these tags",
                  "ghl-crm-integration",
                ),
                value: "none",
              },
            ],
            onChange: function (v) {
              setAttrs({ rule: v });
            },
            help: __(
              "Choose how tags should be checked for access",
              "ghl-crm-integration",
            ),
          }),
          tagSelector,
        ),
        el(
          PanelBody,
          {
            title: __("Fallback Settings", "ghl-crm-integration"),
            initialOpen: false,
          },
          el(ToggleControl, {
            label: __("Show Fallback Message", "ghl-crm-integration"),
            checked: attrs.showMessage,
            onChange: function (v) {
              setAttrs({ showMessage: v });
            },
            help: __(
              "Display a message when user does not have access",
              "ghl-crm-integration",
            ),
          }),
          attrs.showMessage &&
            el(
              Fragment,
              null,
              el(TextareaControl, {
                label: __("Fallback Content", "ghl-crm-integration"),
                value: attrs.fallbackContent,
                onChange: function (v) {
                  setAttrs({ fallbackContent: v });
                },
                help: __(
                  "Message shown to users without access (supports HTML)",
                  "ghl-crm-integration",
                ),
                rows: 4,
              }),
              el(
                "div",
                { className: "ghl-fallback-style-controls" },
                el(
                  "div",
                  { style: { marginBottom: "12px" } },
                  el(
                    "label",
                    {
                      style: {
                        display: "block",
                        fontWeight: "600",
                        marginBottom: "6px",
                      },
                    },
                    __("Background Color", "ghl-crm-integration"),
                  ),
                  el(ColorPalette, {
                    value: attrs.fallbackBgColor,
                    onChange: function (color) {
                      setAttrs({ fallbackBgColor: color || "#fff3cd" });
                    },
                  }),
                ),
                el(
                  "div",
                  { style: { marginBottom: "12px" } },
                  el(
                    "label",
                    {
                      style: {
                        display: "block",
                        fontWeight: "600",
                        marginBottom: "6px",
                      },
                    },
                    __("Text Color", "ghl-crm-integration"),
                  ),
                  el(ColorPalette, {
                    value: attrs.fallbackTextColor,
                    onChange: function (color) {
                      setAttrs({ fallbackTextColor: color || "#856404" });
                    },
                  }),
                ),
                el(
                  "div",
                  { style: { marginBottom: "12px" } },
                  el(
                    "label",
                    {
                      style: {
                        display: "block",
                        fontWeight: "600",
                        marginBottom: "6px",
                      },
                    },
                    __("Border Color", "ghl-crm-integration"),
                  ),
                  el(ColorPalette, {
                    value: attrs.fallbackBorderColor,
                    onChange: function (color) {
                      setAttrs({ fallbackBorderColor: color || "#ffc107" });
                    },
                  }),
                ),
                el(RangeControl, {
                  label: __("Padding (px)", "ghl-crm-integration"),
                  value: attrs.fallbackPadding,
                  onChange: function (value) {
                    setAttrs({ fallbackPadding: value });
                  },
                  min: 0,
                  max: 64,
                  step: 1,
                }),
              ),
            ),
        ),
      );

      // ─── Restriction Indicator ───────────────────────────────────────────
      var tagCount = attrs.tags.length;
      var tagLabel =
        tagCount === 1
          ? __("tag", "ghl-crm-integration")
          : __("tags", "ghl-crm-integration");

      var restrictionIndicator = el(
        "div",
        {
          className: "ghl-restriction-indicator",
          style: {
            padding: "12px 16px",
            background: "#f0f0f1",
            border: "2px solid #2271b1",
            borderRadius: "4px",
            marginBottom: "12px",
            display: "flex",
            alignItems: "center",
            gap: "8px",
          },
        },
        el("span", { style: { fontSize: "20px" } }, "🔒"),
        el(
          "div",
          { style: { flex: 1 } },
          el(
            "strong",
            { style: { display: "block", marginBottom: "4px" } },
            __("Restricted Content", "ghl-crm-integration"),
          ),
          el(
            "span",
            { style: { fontSize: "13px", color: "#646970" } },
            getRuleLabel(attrs.rule) +
              (tagCount > 0 ? " (" + tagCount + " " + tagLabel + ")" : ""),
          ),
        ),
      );

      // ─── Fallback Preview ────────────────────────────────────────────────
      var fallbackPreview =
        attrs.showMessage && attrs.fallbackContent
          ? el(
              "div",
              {
                style: {
                  marginTop: "12px",
                  padding: (attrs.fallbackPadding || 0) + "px",
                  background: attrs.fallbackBgColor || "#fff3cd",
                  border:
                    "1px solid " + (attrs.fallbackBorderColor || "#ffc107"),
                  borderRadius: "4px",
                },
              },
              el(
                "strong",
                { style: { display: "block", marginBottom: "4px" } },
                __("Fallback Content:", "ghl-crm-integration"),
              ),
              el(
                "div",
                {
                  style: {
                    fontSize: "13px",
                    color: attrs.fallbackTextColor || "#856404",
                  },
                },
                el(RawHTML, null, attrs.fallbackContent),
              ),
            )
          : null;

      // ─── Render ──────────────────────────────────────────────────────────
      return el(
        Fragment,
        null,
        inspector,
        el(
          "div",
          { className: "ghl-restricted-content-block" },
          restrictionIndicator,
          el(
            "div",
            {
              style: {
                border: "1px dashed #ccc",
                padding: "16px",
                borderRadius: "4px",
              },
            },
            el(InnerBlocks, { templateLock: false }),
          ),
          fallbackPreview,
        ),
      );
    },

    // ─── Save ────────────────────────────────────────────────────────────────
    save: function () {
      return el(blockEditor.InnerBlocks.Content);
    },
  });
})();
