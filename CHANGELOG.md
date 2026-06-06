# Changelog

All notable changes to GoHighLevel CRM Integration will be documented in this file.

## [Unreleased]

---

## [1.3.10] - 2026-06-06

### Fixed

- **GitHub release artifact reliability** — Release workflow now installs Composer production dependencies inside the packaged build directory and validates that vendor/autoload.php exists in the final zip.

---

## [1.3.9] - 2026-06-06

### Changed

- **WordPress.org readiness** — Updated plugin metadata, plugin-check compliance, and release packaging safeguards for production zip artifacts.

### Fixed

- **Plugin Check errors** — Resolved i18n translator comments, prepared SQL usage, direct file access checks, review link policy, and release metadata mismatches.

---

## [1.3.8] - 2026-05-10

### Fixed

- **Test Link Debugger** — Now reads actual field mappings from settings instead of hardcoded field names. If phone is mapped to `billing_phone`, the tool will correctly read that field.

---

## [1.3.7] - 2026-05-10

### Changed

- **Test Link Debugger** — Removed GHL API fallback. Test tool now reads exclusively from WordPress user meta. All contact fields must be synced to WP user profile first.

---

## [1.3.6] - 2026-05-10

### Fixed

- **Test Link Debugger** — Empty fields now display as plain text `(empty)` instead of HTML markup.

---

## [1.3.5] - 2026-05-10

### Added

- **Field Privacy** — New "Hide Fields From Guests" setting in Personalization tab. Admin selects which contact fields to hide from campaign visitors (defaults to showing all).
- **Test Link Debugger** — New tool in Personalization settings to test campaign URLs. Admin pastes a `?ghl_cid=` link and sees exactly what contact data and fields would resolve.

### Changed

- **Personalization UX** — Simplified field visibility logic from allowlist to denylist (hide specific fields instead of allow specific fields).

---

## [1.3.4] - 2026-05-10

### Removed

- **Personalization strict mode** — Removed "Strict Mode (Require Signed Token)" option and HMAC secret key from settings. Personalization now works with `?ghl_cid={{contact.id}}` alone, without token requirement.

---

## [1.3.3] - 2026-05-10

### Changed

- **Personalization safe mode only** — Removed `ghl_cid` auto-login behavior from runtime handling.
- **Personalization settings UI** — Removed the "Auto-Login Matched User" option from the free plugin settings.

---

## [1.3.2] - 2026-05-10

### Changed

- **Personalization (Free)** — Removed live guest contact fetch from the free plugin when a `ghl_cid` contact does not map to a WordPress user.
- **Personalization settings UI** — Removed the "Live GHL Fetch (No WP User)" section from the free settings screen.

---

## [1.3.1] - 2026-05-10

### Added

- **Dashboard documentation shortcut** — Added a direct "Documentation" quick action link in the main plugin dashboard to `https://highlevelsync.com/documentation/`.

### Changed

- **Personalization free/pro split** — `?ghl_cid` guest personalization remains available in Free for contacts mapped to WordPress users, while advanced behaviors are now Pro-gated.
- **Personalization settings UI** — Pro-only controls remain visible in Free but are locked with clear "This is a PRO feature" messaging, matching the existing lock pattern used in other settings tabs.

### Pro Integration

- **New Pro unlock flags** — Added `ghl_crm_cid_autologin_enabled` and `ghl_crm_cid_guest_live_fetch_enabled` hooks so Pro can unlock auto-login and live guest contact fetching.

---

## [1.3.0] - 2026-05-10

### Added

- **Email campaign personalization** — New `ContactIdHandler` class handles `?ghl_cid=CONTACT_ID` URL parameters from email campaigns. Guest visitors get their contact ID persisted in a signed HttpOnly cookie for shortcode personalization across page loads.
- **`[ghl_user_meta]` guest support** — Shortcodes now resolve field values for non-logged-in visitors. If the contact maps to a WP user, WP user meta is read directly (same keys as logged-in). For contacts with no WP account, data is fetched from the GHL API and cached as a transient.
- **Personalization settings tab** — New dedicated "Personalization" tab in the plugin settings with: enable toggle, auto-login toggle, HMAC secret key input with generator, strict token mode toggle, and copy link template helper.
- **`TagManager::find_user_by_contact_id()`** — New method to look up a WP user by their linked GHL contact ID, with location-scoped and legacy key fallback.
- **Strict mode** — Optional `require_ghl_cid_token` setting. When enabled, requires a valid `HMAC_SHA256(secret, contact_id)` token in the URL before persisting guest data.
- **Auto-login via signed link** — When enabled and a valid signed token is present, automatically logs in the matched WP user.

---

## [1.2.1] - 2026-04-10

### Added

- **Login Sync settings tab** — Full SPA-routed settings page with custom field mapping (select2), conditional tag assignment (first-login / every-login), inactivity tag removal, and tag-based redirect rules with CPT URL picker.
- **Auto-create GHL contact on login** — When a logged-in user has no GHL contact, the queue processor now falls back to `user_register` to create the contact automatically.
- **`ghl_crm_login_register_payload` filter** — New filter hook allows Pro (or third-party code) to enrich the register payload with login-specific custom fields and tags in a single API call.
- **Login field sync** — User login now updates `last_login` and `login_count` custom fields in GHL on each login.

### Fixed

- **WooCommerce product tags — `locationId` rejection** — `add_tags()` was injecting `locationId` into the POST body sent to `contacts/{id}/tags`, which GHL rejects with `"property locationId should not exist"`. Fixed by suppressing body injection for that endpoint.
- **MetadataService `refresh_metadata`** — Custom fields transient was never saved during metadata refresh; only tags were persisted. Now saves both.

### Improved

- **Error event logging** — Errors now capture a PHP backtrace and environment details for easier remote debugging.
- **Log sanitization** — Context passed to the file logger now has sensitive keys redacted and inline secrets scrubbed before storage.
- **Queue processor** — Streamlined user action routing; dead-end sync log entries for social-media-only contacts with no email are suppressed.

---

## [1.2.0] - 2026-03-26

### Changed — Free / Pro Feature Separation

- **AI Field Suggestions** — Moved to Pro; free plugin delegates via `ghl_crm_field_suggestions_result` filter.
- **Sync Preview / Dry Run** — Moved to Pro; free plugin delegates via `ghl_crm_preview_user_sync_result` filter.
- **Public REST API Endpoints** — Moved to Pro; free plugin fires `ghl_crm_register_public_rest_routes` action for Pro to register endpoints (contacts, sync, status, webhooks).
- **Elementor Widget Conditions** — Moved to Pro; free plugin fires `ghl_crm_init_elementor_conditions` action.
- **Archive & REST API Protection** — Moved to Pro; free plugin fires `ghl_crm_register_advanced_restriction_hooks` action.
- **Restriction Override Settings** — Admin bypass and allowed-tag overrides gated behind `ghl_crm_restriction_overrides_enabled` filter (Pro).
- **Global Tags** — Gated behind `ghl_crm_global_tags_enabled` filter (Pro).
- **Analytics Dashboard** — Chart visualizations moved to Pro via `ghl_crm_render_analytics_tab` action; free shows upgrade CTA.

### Added

- **Family Relationships Settings Tab** — New teaser page with greyed-out preview and upgrade CTA for the Pro family accounts feature.
- **PRO Badges** — Settings sidebar tabs, field mapping auto-suggest button, restriction settings, role tag global settings, sync preview, and REST API page now show PRO badges when Pro is not active.
- **Upgrade CTAs** — Sync Preview, REST API, Analytics, and Family Relationships pages show full upgrade banners with greyed-out feature previews.

### Fixed

- **Telemetry Fatal Error Filter** — `capture_fatal_error()` now only logs errors originating from `ghl-crm-integration` or `ghl-crm-integration-pro` directories, preventing unrelated theme/plugin errors from being sent.

### Improved

- **FEATURES-AND-BENEFITS.md** — Updated to accurately reflect the Free vs Pro feature split.

## [1.1.3] - 2026-03-22

### Fixed

- **Action Scheduler Timing Race** — Scheduled OAuth token refresh was never registering because Action Scheduler initializes at `init` priority 1 while plugin components loaded at priority 0. Deferred scheduling to `action_scheduler_init` hook.
- **Cron/CLI OAuth Timeout** — Cron and WP-CLI requests now receive 15s timeout (previously got 8s frontend timeout), preventing token refresh failures on slow connections.
- **Cascading Refresh Failures** — Added per-request `$refresh_failed_this_request` flag to prevent retry loops within the same PHP process.
- **Log Flood Prevention** — Added 30-second deduplication window in FileLogger to suppress identical repeated log entries.

### Improved

- **Scheduled Refresh Window** — Widened from 2-hour to 12-hour buffer before token expiry, ensuring low-traffic sites refresh in time.
- **cURL Timeout Retry** — Auto-retry on cURL error 28 with 25s timeout before falling back to reconnect flow.
- **Reconnect Endpoint Timeout** — Bumped from 15s to 25s for more reliable proxy communication.
- **Action Scheduler Guards** — Added `ActionScheduler::is_initialized()` checks to NotificationManager, ReportingManager, and QueueManager scheduling methods.
- **Token Expiry Display** — Connection status now shows exact HH:MM:SS countdown instead of approximate `human_time_diff`.

### Added

- **Reconnect Account Button** — Quick action on reports dashboard to manually trigger OAuth token refresh.
- **GHL Quick Links** — Direct link to GoHighLevel dashboard from reports page.
- **Compound Tag Conditions** — Gutenberg blocks and Elementor widgets now support AND/OR/NOT logic for tag-based content restriction.
- **Elementor Widget Rename** — "GHL Restricted Content" renamed to "GHL Content" for clarity.

## [1.1.2] - 2026-03-21

### Improved

- **Compatibility** — Version bump for Pro add-on 1.1.2 compatibility (LearnDash sync reliability fixes).

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
