# Developer Changelog

Internal changelog with full technical details. **Not included in release zips.**

---

## [1.2.0] - 2026-03-26

### Free / Pro Separation — Hook-Based Architecture

Replaced direct class references with WordPress action/filter hooks so the free plugin
has zero hard dependencies on Pro code. Pro registers handlers for each hook.

**Files modified:**

- `src/API/RestAPIController.php` — Removed 60+ lines of public endpoint registration.
  Replaced with `do_action('ghl_crm_register_public_rest_routes', $this)`. Pro hooks in
  to register contacts, sync, status, webhooks endpoints with its own settings checks.

- `src/Core/Settings/AjaxHandler.php` — `get_field_suggestions()` no longer instantiates
  `FieldMatcher`. Uses `apply_filters('ghl_crm_field_suggestions_result', null, $wp_fields, $ghl_fields)`.
  Returns 403 with Pro upsell message if filter returns null.

- `src/Core/SettingsManager.php` — `preview_user_sync()` no longer instantiates
  `SyncPreview`. Uses `apply_filters('ghl_crm_preview_user_sync_result', null, $user_id)`.
  Returns 403 if null.

- `src/Integrations/Elementor/ElementorIntegration.php` — Removed direct
  `ElementorConditions::init()` call. Uses `do_action('ghl_crm_init_elementor_conditions')`.

- `src/Membership/Restrictions.php` — Archive/REST hiding hooks extracted to
  `do_action('ghl_crm_register_advanced_restriction_hooks', $this, $settings_manager)`.
  Admin bypass / allowed-tag override gated behind `ghl_crm_restriction_overrides_enabled`.

- `src/Integrations/Users/RoleTagsManager.php` — `get_location_global_tags_config()`
  returns `[]` unless `ghl_crm_global_tags_enabled` filter returns true (Pro).

- `src/Core/MenuManager.php` — Added `family-relationships` to valid settings tabs list
  in both `get_valid_settings_tabs()` occurrences.

### Telemetry Fix

- `src/Core/Reporting/ReportingManager.php` — `capture_fatal_error()` now checks
  `strpos($file, WP_CONTENT_DIR . '/plugins/ghl-crm-integration')` and the pro dir.
  Only logs fatal errors from either plugin directory.

### Template / UI Changes

- `templates/admin/dashboard.php` — Analytics tab checks `has_action('ghl_crm_render_analytics_tab')`;
  shows upgrade CTA with greyed preview if no handler.
- `templates/admin/field-mapping.php` — Auto-Suggest button disabled + PRO badge via
  `ghl_crm_field_suggestions_enabled` filter.
- `templates/admin/settings.php` — REST API, Family Relationships, Sync Preview tabs get
  `pro` and `pro_filter` metadata. Sidebar renders PRO badge when filter returns false.
- `templates/admin/partials/settings/rest-api.php` — Full upgrade CTA + greyed-out preview
  when `ghl_crm_public_rest_api_enabled` is false.
- `templates/admin/partials/settings/sync-preview.php` — Upgrade CTA + mock table preview.
- `templates/admin/partials/settings/restrictions-manager.php` — Archive/REST checkboxes
  and override section disabled with PRO badges.
- `templates/admin/partials/settings/role-tags.php` — Global tags field disabled + PRO badge.
- `templates/admin/partials/settings/family-relationships.php` — New file. Full teaser
  template with hardcoded demo data showing family accounts feature preview.

---

## [1.1.2] - 2026-03-21

### What Changed

This is a compatibility bump — no production code was modified in the free plugin.
All fixes in this release cycle are in the Pro add-on (LearnDash modules).

### Session Work (Not Shipped in Free)

- Audited all `add_to_queue()` calls across both plugins for dedup collision bugs.
- Confirmed free plugin's `QueueManager::add_to_queue()` dedup key logic
  `(item_type, item_id, action)` is correct — the issue was in how Pro's LearnDash
  sync modules populated `item_id`.
- Verified BuddyBoss integration already uses `$user_id` as `item_id` — no bug.
- Removed `"Inter"` from CSS `--ghl-font-family` variable in `globals.css` (font was
  referenced but never loaded via @font-face or CDN).

---

## [1.1.1] - 2026-03-19

### Technical Details

- **UserMetaSync extraction**: Moved `handle_after_sync_success()`,
  `sync_contact_tags_from_ghl()`, and all post-sync user meta operations from
  `QueueManager` into `src/Sync/UserMetaSync.php`. Registered via
  `ghl_crm_after_sync_success` action in `Loader`.
- **Build pipeline**: Added `matthiasmullie/minify` ^1.3 to require-dev.
  `build-minify.php` walks asset directories, generates `.min.css`/`.min.js` siblings.
  `AssetsManager::maybe_use_min_file()` auto-serves `.min` unless `SCRIPT_DEBUG`.
- **WC product-tags routing**: `wc_product_tags` handler moved from legacy
  `ghl_crm_execute_sync` filter to `QueueProcessor::register_handler()` in
  `WooCommerceSync::register_queue_handlers()`.
- **Empty tag fix**: `array_filter($tags, 'strlen')` added to
  `process_customer_conversion()` and `process_product_tags()`.
- **Tag merge fix**: `get_user_tag_ids()` + array merge before
  `update_user_meta('_ghl_tag_ids')` in WC sync paths.
- **Error catching**: `catch (\Exception)` → `catch (\Throwable)` in WC queue handlers.

---

## [1.0.2] - 2026-03-18

- Version constant audit: replaced hardcoded `'1.0.0'` in 23 `wp_enqueue_*` calls.
- User deletion: fixed double `add_action('delete_user')` in UserHooks; switched from
  email-based lookup to stored `_ghl_contact_id` meta.
- Webhook delete: added `_ghl_skip_delete_sync` transient flag (30s) for ping-pong.
- QueueProcessor: removed dead `wc_customer`/`form` defaults; added
  `ghl_crm_queue_processor_ready` action.
- CF7: `CF7Handler` → direct `register_handler()` instead of hook-based init.
- jQuery: `CSS.escape()` for tag name selectors in 8 JS files.
- WC tag overwrite: switched to `add_tags()` (POST) from `update()` (PUT).
- Special chars: `esc_attr(wp_json_encode())` in 10 PHP templates; Select2 `<option>`
  DOM approach in 4 JS files.

---

## [1.0.1] - 2026-03-17

- LearnDash progress: `CourseProgressSync` in Pro extracts from
  `_sfwd-course_progress` user meta. 15-min debounce via `ghl_ld_progress_{uid}_{cid}`
  transient. Filterable: `ghl_crm_progress_debounce_seconds`.
- Virtual fields: `ghl_crm_resolve_field_value` filter in
  `UserHooks::prepare_contact_data()` for `ld_progress_*` keys.
- CF7: `CF7Handler` class with `wpcf7_mail_sent` hook, per-form meta
  `_ghl_cf7_field_mapping` + `_ghl_cf7_tags`.
- Sync preview: `SyncPreviewAjax` class, compares local vs remote field values,
  returns diff + estimated API calls.
- Notifications: `NotificationManager` with 6 types, HTML templates in
  `templates/emails/`, throttle via transients.
- Elementor: `ElementorRestrictions` condition in Advanced tab, 5 restriction modes.
- Gutenberg: `ghl-crm/restricted-content` block, `render_callback` checks user tags.
- Multisite: `get_site_transient` / `set_site_transient` for queue lock.
- Duplicate recovery: POST → PUT fallback on 409; email re-lookup on 404.
- Ping-pong: `_ghl_inbound_guard_{contact_id}` transient (30s).
