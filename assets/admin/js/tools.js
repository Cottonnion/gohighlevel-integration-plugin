/**
 * Tools Page JavaScript
 *
 * Handles bulk sync all users functionality
 * Other tool operations (cache, reset, export, import, health check) are handled by settings.js
 *
 * @package Syncly
 */

(function ($) {
  "use strict";

  /**
   * Bulk Sync Users Handler
   */
  const BulkSyncHandler = {
    isRunning: false,
    totalQueued: 0,
    totalFailed: 0,

    /**
     * Initialize bulk sync
     */
    init() {
      $("#bulk-sync-users-btn").on("click", () => this.start());
    },

    /**
     * Start bulk sync process
     */
    start() {
      if (this.isRunning) {
        return;
      }

      // Show confirmation directly
      Swal.fire({
        title: "Sync All Users?",
        html: '<p>This will queue all WordPress users for synchronization to GoHighLevel.</p><p style="color: #666; font-size: 0.9em;">Processing happens in batches of 50 users. Time required depends on total user count. You can monitor progress in real-time.</p>',
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, Sync All Users",
        cancelButtonText: "Cancel",
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
      }).then((result) => {
        if (result.isConfirmed) {
          this.totalQueued = 0;
          this.totalFailed = 0;
          this.processBatch(0);
        }
      });
    },

    /**
     * Process a batch of users
     *
     * @param {number} batch - Batch number to process
     */
    processBatch(batch) {
      this.isRunning = true;

      // Show progress UI
      $("#bulk-sync-progress").show();
      $("#bulk-sync-users-btn").prop("disabled", true);

      $.ajax({
        url: syncly_tools_js_data.ajaxUrl,
        type: "POST",
        data: {
          action: "syncly_bulk_sync_users",
          nonce: syncly_tools_js_data.nonce,
          batch: batch,
        },
        success: (response) => {
          if (response.success) {
            const data = response.data;

            // Update totals
            this.totalQueued += data.queued || 0;
            this.totalFailed += data.failed || 0;

            // Update progress bar
            const percentage = (data.processed / data.total) * 100;
            $("#bulk-sync-progress-bar").css("width", percentage + "%");

            // Update progress text
            $("#bulk-sync-progress-text").html(
              `<strong>${data.processed}</strong> of <strong>${data.total}</strong> users processed<br>` +
                `<span style="color: #46b450;">✓ ${this.totalQueued} queued</span> | ` +
                `<span style="color: ${this.totalFailed > 0 ? "#dc3232" : "#666"};">${this.totalFailed > 0 ? "✗" : ""} ${this.totalFailed} failed</span>`,
            );

            // Continue with next batch if needed
            if (data.has_more) {
              this.processBatch(data.next_batch);
            } else {
              this.complete();
            }
          } else {
            this.error(response.data?.message || "An error occurred");
          }
        },
        error: (xhr, status, error) => {
          this.error("Network error occurred. Please try again.");
        },
      });
    },

    /**
     * Handle completion
     */
    complete() {
      this.isRunning = false;
      $("#bulk-sync-users-btn").prop("disabled", false);

      // Hide progress after a delay
      setTimeout(() => {
        $("#bulk-sync-progress").fadeOut();
      }, 3000);

      // Show success message
      let message = `Successfully queued <strong>${this.totalQueued}</strong> users for synchronization!`;

      if (this.totalFailed > 0) {
        message += `<br><br><span style="color: #dc3232;">${this.totalFailed} users could not be queued.</span>`;
      }

      message += `<br><br><small>Synchronization will happen in the background. You can monitor progress in the Sync Logs tab.</small>`;

      Swal.fire({
        title: "Bulk Sync Complete!",
        html: message,
        icon: this.totalFailed > 0 ? "warning" : "success",
        confirmButtonText: "OK",
      });

      // Reset counters
      this.totalQueued = 0;
      this.totalFailed = 0;
    },

    /**
     * Handle error
     *
     * @param {string} message - Error message
     */
    error(message) {
      this.isRunning = false;
      $("#bulk-sync-users-btn").prop("disabled", false);
      $("#bulk-sync-progress").hide();

      Swal.fire({
        title: "Error",
        text: message,
        icon: "error",
        confirmButtonText: "OK",
      });

      // Reset counters
      this.totalQueued = 0;
      this.totalFailed = 0;
    },
  };

  /**
   * Initialize bulk sync handler
   * Called from settings-menu.js when tools tab is loaded
   */
  /**
   * Bulk Import Handler – imports GHL contacts as WordPress users
   */
  const BulkImportHandler = {
    isRunning: false,
    totals: {
      created: 0,
      updated: 0,
      skipped_no_email: 0,
      skipped_duplicate: 0,
      failed: 0,
    },
    totalContacts: 0,
    pages: 0,

    /**
     * Initialize the import handler
     */
    init() {
      $("#bulk-import-ghl-btn").on("click", () => {
        if (this.isRunning) return;

        Swal.fire({
          title: "Import Contacts from GHL",
          html: "This will fetch all contacts from GoHighLevel and create or update WordPress users for each one that has an email address.<br><br><strong>Existing users will be updated with the latest data.</strong>",
          icon: "question",
          showCancelButton: true,
          confirmButtonText: "Start Import",
          cancelButtonText: "Cancel",
        }).then((result) => {
          if (result.isConfirmed) {
            this.start();
          }
        });
      });
    },

    /**
     * Start the import process
     */
    start() {
      this.reset();
      this.isRunning = true;
      $("#bulk-import-progress").show();
      $("#bulk-import-progress-bar")
        .addClass("ghl-progress-bar-indeterminate")
        .css("width", "30%");
      $("#bulk-import-progress-text").html(
        "Starting import from GoHighLevel&hellip;",
      );
      this.processPage(null, 1);
    },

    /**
     * Reset counters
     */
    reset() {
      this.totals = {
        created: 0,
        updated: 0,
        skipped_no_email: 0,
        skipped_duplicate: 0,
        failed: 0,
      };
      this.totalContacts = 0;
      this.pages = 0;
    },

    /**
     * Process one page of GHL contacts
     *
     * @param {string|null} cursor - Cursor for the next page
     * @param {number}      page   - Current page number
     */
    processPage(cursor, page) {
      this.isRunning = true;

      $("#bulk-import-progress").show();
      $("#bulk-import-ghl-btn").prop("disabled", true);

      const postData = {
        action: "syncly_bulk_import_from_ghl",
        nonce: syncly_tools_js_data.nonce,
        page: page,
      };

      if (cursor) {
        postData.cursor = cursor;
      }

      $.ajax({
        url: syncly_tools_js_data.ajaxUrl,
        type: "POST",
        data: postData,
        success: (response) => {
          if (response.success) {
            const d = response.data;

            this.totals.created = d.total_created || 0;
            this.totals.updated = d.total_updated || 0;
            this.totals.skipped_no_email = d.total_skipped_no_email || 0;
            this.totals.skipped_duplicate = d.total_skipped_duplicate || 0;
            this.totals.failed = d.total_failed || 0;
            this.totalContacts = d.total_contacts || 0;
            this.pages = d.pages_complete || page;

            const processed = d.total_processed || 0;

            // Update progress bar — use real percentage if total is known
            const $bar = $("#bulk-import-progress-bar");
            if (this.totalContacts > 0) {
              const pct = Math.min(
                100,
                Math.round((processed / this.totalContacts) * 100),
              );
              $bar
                .removeClass("ghl-progress-bar-indeterminate")
                .css("width", pct + "%");
            }

            // Update progress text
            const ofTotal =
              this.totalContacts > 0
                ? ` of <strong>${this.totalContacts}</strong>`
                : "";
            // Build progress text — only show categories that have counts
            const parts = [
              `<strong>${processed}</strong>${ofTotal} contacts processed &mdash; `,
              `<span style="color:#46b450;">\u2713 ${this.totals.created} created</span>`,
              `<span style="color:#0073aa;">\u21bb ${this.totals.updated} updated</span>`,
            ];
            if (this.totals.skipped_no_email > 0) {
              parts.push(
                `<span style="color:#999;">\u2298 ${this.totals.skipped_no_email} no email</span>`,
              );
            }
            if (this.totals.skipped_duplicate > 0) {
              parts.push(
                `<span style="color:#aaa;">\u2298 ${this.totals.skipped_duplicate} API duplicates</span>`,
              );
            }
            parts.push(
              `<span style="color:${this.totals.failed > 0 ? "#dc3232" : "#666"};">${this.totals.failed > 0 ? "\u2717 " : ""}${this.totals.failed} failed</span>`,
            );
            $("#bulk-import-progress-text").html(
              parts[0] + parts.slice(1).join(" | "),
            );

            if (d.has_more && d.next_cursor) {
              this.processPage(d.next_cursor, d.next_page);
            } else {
              this.complete();
            }
          } else {
            this.error(
              response.data?.message || "An error occurred during import.",
            );
          }
        },
        error: () => {
          this.error("Network error occurred. Please try again.");
        },
      });
    },

    /**
     * Handle completion
     */
    complete() {
      this.isRunning = false;
      $("#bulk-import-ghl-btn").prop("disabled", false);

      setTimeout(() => {
        $("#bulk-import-progress").fadeOut();
      }, 5000);

      const total =
        this.totals.created +
        this.totals.updated +
        this.totals.skipped_no_email +
        this.totals.skipped_duplicate +
        this.totals.failed;
      const unique = total - this.totals.skipped_duplicate;

      let message =
        `<strong>${unique}</strong> unique contacts processed across <strong>${this.pages}</strong> page(s):<br><br>` +
        `<span style="color:#46b450;">\u2713 ${this.totals.created} created</span><br>` +
        `<span style="color:#0073aa;">\u21bb ${this.totals.updated} updated</span><br>` +
        `<span style="color:#999;">\u2298 ${this.totals.skipped_no_email} skipped (no email)</span>`;

      if (this.totals.skipped_duplicate > 0) {
        message += `<br><span style="color:#aaa;">\u2298 ${this.totals.skipped_duplicate} API duplicates filtered</span>`;
      }

      if (this.totals.failed > 0) {
        message += `<br><span style="color:#dc3232;">\u2717 ${this.totals.failed} failed</span>`;
      }

      Swal.fire({
        title: "Import Complete!",
        html: message,
        icon: this.totals.failed > 0 ? "warning" : "success",
        confirmButtonText: "OK",
      });

      this.reset();
    },

    /**
     * Handle error
     *
     * @param {string} message - Error message
     */
    error(message) {
      this.isRunning = false;
      $("#bulk-import-ghl-btn").prop("disabled", false);
      $("#bulk-import-progress").hide();

      Swal.fire({
        title: "Import Error",
        text: message,
        icon: "error",
        confirmButtonText: "OK",
      });

      this.reset();
    },
  };

  function initToolsHandlers() {
    BulkSyncHandler.init();
    BulkImportHandler.init();
  }

  // Export for use in settings-menu.js
  window.initToolsHandlers = initToolsHandlers;
})(jQuery);
