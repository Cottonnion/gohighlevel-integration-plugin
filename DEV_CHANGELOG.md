# Developer Changelog

Internal changelog with full technical details. **Not included in release zips.**

---

## [1.4.37] - 2026-08-28

### Review request banner

**File**: `src/Core/AdminNotices.php`

- Added `should_display_review_notice()` which shows the banner only after 3 days of plugin usage (`syncly_activation_date` option) and while the user has not dismissed it.
- Added `render_review_notice()` rendering a dismissible banner (`.ghl-review-notice`) linking to `https://wordpress.org/support/plugin/syncly/reviews/#new-post`.
- Added `ajax_dismiss_review_notice()` (action `syncly_dismiss_review_notice`, nonce-verified, `manage_options`-gated) storing dismissal in user meta `syncly_review_notice_dismissed`.
- Added `is_review_notice_dismissed()` / `reset_review_notice()` helpers mirroring the existing upgrade-notice pattern.

**Files**: `src/Core/Loader.php`, `templates/admin/spa-app.php`

- `Loader::activate()` now records `syncly_activation_date` (first activation only) so the banner timing has a reference point.
- `spa-app.php` renders the review banner beneath the upgrade notice.

**Files**: `src/Core/AssetsManager.php`, `assets/admin/js/review-notice.js`, `assets/admin/css/spa-app.css`

- Registered `syncly-review-notice-js` for the SPA admin page; the JS performs the nonce-authenticated dismissal AJAX and removes the banner.
- The banner CSS uses the GHL `--ghl-amber*` accent for the star/left-border and `--ghl-primary` for the CTA button, matching the design-token system.

---

## [1.4.36] - 2026-08-28

### OAuth scope alignment for Calendar Appointments

**File**: `src/API/Client/Client.php`

- Added `calendars/events.write` scope (book/manage calendar appointments) and aligned the requested scope list with the GoHighLevel marketplace draft: `calendars.readonly`/`calendars.write`, `workflows.readonly`, `conversations.readonly`/`conversations.write`, `conversations/message.readonly`/`conversations/message.write`, `locations.readonly`, plus the existing contacts/tags/customFields/objects/opportunities scopes. Removed `forms.write` and the commented `campaigns.readonly`.
- Added `set_api_version()` / `get_api_version()` so callers can switch the GHL API version header per request.

**File**: `src/API/ScopeChecker.php`

- Removed the legacy `campaigns` scope/endpoint entries.

### Cache-clear action for Pro

**Files**: `src/Core/Settings/MaintenanceHandler.php`, `src/Core/SettingsManager.php`

- Added `do_action( 'syncly_cache_cleared' )` after the dashboard Clear Cache handler wipes caches and after `purge_location_caches()` runs on connection/location changes (gated to avoid firing during early bootstrap).
- Syncly Pro listens on this action to purge its cached GHL calendars/workflows transients.

### Contact API additions (public REST)

**File**: `src/API/Resources/ContactResource.php`

- `get_by_id( $contact_id )`: GET full-detail contact (includes tags + custom field values).
- `update_custom_fields( $contact_id, $custom_fields )`: PUT `{contact}/customFields` with the `locationId` body flag disabled (that endpoint rejects it).

### Manual sync trigger enhancements

**File**: `src/API/RestAPIController.php`

- `trigger_sync()` now lowercases/validates `type` against `all|users|contacts|forms` (400 with allowed list otherwise), calls `QueueManager::process_queue()` inline (same path as the scheduled auto-sync), and returns `processed` queue status in the response.
- Pro registers additional public contact routes via the `syncly_register_public_rest_routes` action.

### UI

**File**: `templates/admin/partials/settings/webhooks.php`

- Rebuilt the Webhook Setup screen on the GHL design-token components (`ghl-settings-wrapper/card/header`, status styling) and added the settings nonce field.

**File**: `templates/admin/partials/settings/upgrade.php`

- Added Pro feature cards for Auto-Book GHL Calendar and GHL Workflow Enrollment.

---

## [1.4.31] - 2026-08-23

### Reliability, Security, and Release Hardening

- `Client`: added atomic OAuth refresh locking, bounded retries for safe transient failures, request correlation IDs, and redacted OAuth diagnostic data.
- `ConnectionManager`: now verifies the connection through the shared API client so it benefits from token lifecycle handling.
- `WebhookHandler`: validates JSON bodies, deduplicates deliveries for seven days, returns retryable errors on failed processing, and avoids logging full payloads.
- `QueueManager` and `RateLimiter`: added atomic job claims, refreshed processing locks, safe dependency waits, terminal stale-job handling, and atomic API-rate reservations.
- `Loader`, `OAuthHandler`, and `FileLogger`: fixed plugin lifecycle hooks, bound OAuth callbacks to the initiating administrator, redacted sensitive structured logs, and made file logging opt-in.
- Release tooling: aligned version/release documentation, added PHP 7.4 + 8.1 CI coverage, and changed production deployment to deploy the tagged release.

### Verification

- `composer validate --strict --no-check-publish`
- `composer lint`
- `composer test` (110 tests, 34,369 assertions)

## [1.4.10] - 2026-08-01

### Queue and Scheduler Hardening

- `src/Sync/QueueManager.php`
  - Changed `PROCESSING_INTERVAL` from 10 seconds to `5 * MINUTE_IN_SECONDS`.
  - Added one-time queue schedule migration (`syncly_queue_schedule_version`) to unschedule legacy high-frequency `syncly_process_queue` jobs before re-seeding with the new cadence.
  - Updated WP-Cron fallback to use a 5-minute schedule slug (`syncly_5min`) instead of `every_minute`.

- `src/Core/Loader.php`
  - Registered `syncly_5min` in `add_cron_schedules()` for fallback scheduling consistency.

- `syncly.php`
  - Replaced unconditional Action Scheduler bootstrap with `syncly_maybe_load_action_scheduler()`.
  - Syncly now loads bundled Action Scheduler only when no provider is already available (`as_schedule_recurring_action` / `ActionScheduler` / `ActionScheduler_Versions` checks), preserving standalone support while reducing duplicate-loader risk.

- `composer.json`
  - Kept `woocommerce/action-scheduler` as a dependency so non-WooCommerce installs remain supported.

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
