# Changelog

All notable changes to GoHighLevel CRM Integration will be documented in this file.

## [1.1.1] - 2026-03-19

### Added

- **Centralized UserMetaSync Class** — Extracted all post-sync user meta logic (pending tags, tag caching, GHL refresh) from `QueueManager` into a dedicated `UserMetaSync` class under `src/Sync/`, registered in `Loader` via the `ghl_crm_after_sync_success` hook.
- **Asset Auto-Minification Pipeline** — Added `matthiasmullie/minify` dev dependency and `build-minify.php` script (`composer build`) that generates `.min.css` / `.min.js` for all plugin assets.
- **AssetsManager .min Auto-Detection** — New `maybe_use_min_file()` helper in `AssetsManager` automatically serves `.min` assets in production and falls back to source files when `SCRIPT_DEBUG` is on.

### Fixed

- **WooCommerce Product-Tags Queue Routing** — `wc_product_tags` items were routed through a legacy `ghl_crm_execute_sync` filter instead of a proper `register_handler()` call; now registered alongside `wc_customer` in `WooCommerceSync::register_queue_handlers()`.
- **Empty Tag Sync Failure** — Selecting the "Loading tags…" placeholder before tag list finished loading passed an empty string `""` to the API; `process_customer_conversion()` and `process_product_tags()` now strip empty values with `array_filter($tags, 'strlen')`.
- **Tag Overwrite on WC Sync** — `wc_customer` and `wc_product_tags` user meta updates were overwriting existing tags; now fetches current tags via `get_user_tag_ids()` and merges before storing.
- **QueueManager Dead Code Cleanup** — Removed orphaned `handle_after_sync_success()`, `sync_contact_tags_from_ghl()` methods, and legacy `ghl_crm_execute_sync` filter handler from QueueManager.

### Improved

- **Error Catching Robustness** — Changed `catch (\Exception)` to `catch (\Throwable)` in WooCommerceSync queue handlers to also capture `TypeError` and similar fatal errors.

## [1.0.2] - 2026-03-18

### Improved

- **Version Constant Consistency** — Replaced all hardcoded version strings in `add_admin_asset()` / `wp_enqueue_*` calls with `GHL_CRM_VERSION` and `GHL_CRM_PRO_VERSION` constants for single-point version management.

### Fixed

- **User Deletion Sync (WP→GHL)** — Fixed duplicate hook registration in `UserHooks` and unreliable email-based contact lookup; now resolves stored `contact_id` from user meta.
- **Webhook Delete Sync (GHL→WP)** — Added ping-pong prevention on the delete path via `_ghl_skip_delete_sync` meta flag.
- **QueueProcessor Dead Code** — Removed orphaned `wc_customer` and `form` default handlers; added `ghl_crm_queue_processor_ready` hook for handler self-registration.
- **CF7 Submission Timing** — Changed CF7Handler from hook-based to direct `register_handler()` call to fix race condition.
- **Settings Page jQuery Crash** — Applied `CSS.escape()` for dynamic tag name values in jQuery selectors across all JS files.
- **WooCommerce Tag Overwrite** — `WooCommerceSync` convert-lead and product-tag flows now use the additive `add_tags()` (POST) endpoint instead of the destructive full-contact `update()` (PUT).
- **Tag Display Special Characters (Comprehensive)** — Fixed single-quote breakage in `data-saved-tags` HTML attributes via `esc_attr()` around `wp_json_encode()` in 10 PHP files; replaced broken Select2 `data:` config with DOM `<option>` population in 4 JS files; removed destructive `$select.empty()` / `$select.html('')` calls in 4 JS files; added `CSS.escape()` in 8 JS files.

## [1.0.1] - 2026-03-17

### Added

- **LearnDash Course Progress Sync** — Syncs course progress (percentage, status, completed steps, total steps) to GHL custom fields via the Pro add-on. Includes a 15-minute per-user-per-course debounce to prevent API rate limit exhaustion on rapid lesson completions. Filterable via `ghl_crm_progress_debounce_seconds`.
- **Virtual Field Resolution Filter** — Added `ghl_crm_resolve_field_value` filter in `UserHooks::prepare_contact_data()` so integrations can resolve computed/virtual field values (e.g., `ld_progress_percentage_{course_id}`) during regular profile update syncs.
- **Contact Form 7 Integration** — GHL CRM tab in the CF7 editor with per-form field mapping, tag assignment, and automatic submission sync on form send.
- **Sync Preview / Dry Run** — Preview sync operations before executing: shows create vs update action, field-by-field comparison, tag changes, conflicts, and estimated API calls.
- **Email Notification System** — Six notification types (connection lost, sync errors, queue backlog, rate limit, webhook failures, daily summary) with HTML email templates and configurable throttling.
- **Elementor Widget Restrictions** — GoHighLevel Restrictions section in the Advanced tab of all Elementor widgets with five restriction types.
- **Gutenberg Restricted Content Block** — `ghl-crm/restricted-content` block with tag selection, fallback content, and customizable styling.

### Improved

- **Multisite Queue Safety** — Queue processing lock switched from `get_transient`/`set_transient` to `get_site_transient`/`set_site_transient` in `QueueManager` for network-wide locking.
- **TagManager Documentation** — Full PHPDoc blocks added to every method with logical method reordering (no logic changes).
- **FEATURES-AND-BENEFITS.md** — Complete rewrite based on codebase audit. Removed non-existent features, added Free vs Pro distinction, and documented all verified features with technical specifics.

### Fixed

- **Duplicate Contact Recovery** — Auto-converts POST to PUT on duplicate detection; handles deleted/merged contacts via email re-lookup and meta update.
- **Ping-Pong Prevention** — 30-second inbound guard transient prevents infinite sync loops between webhook inbound and profile update outbound sync.

## [1.0.0] - 2026-01-01

- Initial release.
