/**
 * GoHighLevel CRM Integration - Menu Router
 *
 * Manages active menu state based on SPA hash routing
 *
 * @package    GHL_CRM_Integration
 * @subpackage GHL_CRM_Integration/assets/admin/js
 */

(function ($) {
  "use strict";

  /**
   * Menu Router Class
   * Handles synchronization between hash routes and admin menu active states
   */
  class GHLMenuRouter {
    constructor() {
      this.menuItems = {};
      this.currentHash = "";

      this.init();
    }

    /**
     * Initialize the menu router
     */
    init() {
      // Build menu items map
      this.buildMenuMap();

      // Listen for hash changes
      $(window).on("hashchange", () => this.updateActiveMenuItem());

      // Set initial active state
      this.updateActiveMenuItem();

      // Also update when clicking menu items directly
      this.attachMenuClickHandlers();
    }

    /**
     * Build a map of routes to menu items
     */
    buildMenuMap() {
      // Find all submenu items under our plugin menu (sidebar)
      const $submenuItems = $(
        "#adminmenu .toplevel_page_ghl-crm-admin .wp-submenu a",
      );

      $submenuItems.each((index, element) => {
        const $item = $(element);
        const href = $item.attr("href");

        if (href) {
          // Extract the hash from href (e.g., "admin.php?page=ghl-crm-admin#/settings" -> "settings")
          const hashMatch = href.match(/#\/([\w-]*)/);

          if (hashMatch) {
            const route = hashMatch[1] || "dashboard"; // Empty hash means dashboard

            if (!this.menuItems[route]) {
              this.menuItems[route] = [];
            }

            this.menuItems[route].push($item);
          }
        }
      });

      // Also find header navigation items
      const $headerNavItems = $(".ghl-nav-tab[data-route]");

      $headerNavItems.each((index, element) => {
        const $item = $(element);
        const route = $item.data("route");

        if (route) {
          if (!this.menuItems[route]) {
            this.menuItems[route] = [];
          }

          this.menuItems[route].push($item);
        }
      });
    }

    /**
     * Update the active menu item based on current hash
     */
    updateActiveMenuItem() {
      // Get current hash (remove # and leading /)
      let hash = window.location.hash.replace(/^#\/?/, "") || "dashboard";

      // Check if this is a settings tab - if so, treat as settings view
      const settingsTabs =
        typeof ghlCrmSpaConfig !== "undefined" && ghlCrmSpaConfig.settings
          ? ghlCrmSpaConfig.settings.tabs
          : [
              "general",
              "api",
              "rest-api",
              "webhooks",
              "notifications",
              "sync-options",
              "role-tags",
              "conversations",
              "advanced",
              "stats",
            ];

      if (settingsTabs.includes(hash)) {
        hash = "settings"; // Treat all settings tabs as the settings view for menu highlighting
      }

      // Only update if hash has changed
      if (hash === this.currentHash) {
        return;
      }

      this.currentHash = hash;

      // Remove 'current' and 'active' classes from all menu items
      Object.values(this.menuItems).forEach(($items) => {
        if (Array.isArray($items)) {
          $items.forEach(($item) => {
            $item.removeClass("current active");
          });
        } else {
          $items.removeClass("current active");
        }
      });

      // Add appropriate class to the matching menu items
      if (this.menuItems[hash]) {
        const items = this.menuItems[hash];

        if (Array.isArray(items)) {
          items.forEach(($item) => {
            // Use 'current' for sidebar menu, 'active' for header nav
            if ($item.hasClass("ghl-nav-tab")) {
              $item.addClass("active");
            } else {
              $item.addClass("current");
            }
          });
        } else {
          items.addClass("current");
        }
      } else {
        console.warn("No menu item found for hash:", hash);
      }

      // Ensure parent menu item is expanded
      this.ensureParentExpanded();
    }

    /**
     * Ensure the parent menu item is expanded (wp-has-current-submenu class)
     */
    ensureParentExpanded() {
      const $parentMenuItem = $("#adminmenu .toplevel_page_ghl-crm-admin");

      // Add classes to ensure submenu stays open
      $parentMenuItem.addClass("wp-has-current-submenu wp-menu-open");
      $parentMenuItem.removeClass("wp-not-current-submenu");

      // Ensure the parent link has the proper classes
      $parentMenuItem.find("> a").addClass("wp-has-current-submenu");
    }

    /**
     * Attach click handlers to menu items to update hash
     */
    attachMenuClickHandlers() {
      Object.entries(this.menuItems).forEach(([route, $items]) => {
        const items = Array.isArray($items) ? $items : [$items];

        items.forEach(($item) => {
          $item.on("click", (e) => {
            // Let the default behavior happen (hash change)
            // But also ensure immediate visual feedback
            setTimeout(() => {
              this.updateActiveMenuItem();
            }, 10);
          });
        });
      });
    }
  }

  // Initialize menu router when document is ready
  $(document).ready(function () {
    // Only initialize if we're on the plugin admin page
    if ($("#adminmenu .toplevel_page_ghl-crm-admin").length > 0) {
      new GHLMenuRouter();
    }
  });
})(jQuery);
