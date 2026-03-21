# Developer Changelog

Internal changelog with full technical details. **Not included in release zips.**

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
