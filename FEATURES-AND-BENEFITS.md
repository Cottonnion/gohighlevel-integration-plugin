=== GHL Sync Bridge ===
Contributors: cottonnion
Tags: gohighlevel, crm, woocommerce, buddyboss, learndash, membership, webhooks, automation, oauth2, forms
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The most powerful GoHighLevel integration for WordPress. Deep WooCommerce, BuddyBoss, and LearnDash integrations with custom objects, family relationships, conditional menus, forms, webhooks, and complete automation.

== Description ==

# GHL Sync Bridge — The Complete WordPress Automation Solution

Transform your WordPress site into a fully automated CRM powerhouse with the most comprehensive GoHighLevel integration available. While other plugins offer basic contact sync, this plugin delivers enterprise-level automation with deep integrations across your entire WordPress ecosystem.

## What Makes This Different

**Compared to LeadConnector Plugin**: Basic contact sync vs complete custom objects integration, family relationships, BuddyBoss groups sync, tag-based content restriction, and conditional menu displays.

**Compared to Wizard Plugin**: Simple field mapping vs bi-directional sync with webhook automation, role-based tagging, and real-time updates.

**Compared to WP Fusion for GHL**: Tag-based access control vs everything WP Fusion offers PLUS custom objects, OAuth2 security, native BuddyBoss group-to-custom-object sync, LearnDash deep integration, and family relationship management.

This plugin combines the power of WP Fusion, Memberium, and custom development — all built specifically for GoHighLevel users.

---

## Feature Overview: Free vs Pro

> Features marked **(Free)** are included in the free plugin.
> Features marked **(Pro)** require the GoHighLevel CRM Integration Pro add-on.
>
> The free plugin exposes **WordPress filters and actions** at every Pro extension point.
> Pro hooks in via `FreePluginHooks` — no code patching or overrides, just `add_filter` / `add_action`.
> When Pro is not active, the free plugin shows **PRO badges** and **upgrade CTAs** on gated features.

### Quick Comparison

| Feature | Free | Pro |
|---------|:----:|:---:|
| OAuth2 Authentication & Token Refresh | ✅ | ✅ |
| API Scope Detection | ✅ | ✅ |
| Field Mapping (manual) | ✅ | ✅ |
| AI-Assisted Field Suggestions | — | ✅ |
| User Sync (register, profile update, delete, login) | ✅ | ✅ |
| Sync Preview / Dry Run | — | ✅ |
| Role-Based Tags (per-role, registration, bulk) | ✅ | ✅ |
| Global Tags (location-scoped) | — | ✅ |
| Tag-Based Content Restrictions (page/post/CPT) | ✅ | ✅ |
| Restriction Overrides (admin bypass, allowed tags) | — | ✅ |
| Archive & REST API Protection | — | ✅ |
| Elementor Widget Conditions | — | ✅ |
| Gutenberg Restricted Content Block | ✅ | ✅ |
| `[ghl_restrict]` Shortcode | ✅ | ✅ |
| GHL Form Embedding (shortcode, Gutenberg, Elementor) | ✅ | ✅ |
| Per-Form Submission Limits | — | ✅ |
| Contact Form 7 Integration | ✅ | ✅ |
| Webhook Inbound Sync (GHL → WP) | ✅ | ✅ |
| Bulk Import (GHL → WP) | ✅ | ✅ |
| BuddyBoss Group → Custom Object Sync | ✅ | ✅ |
| WooCommerce Settings & Tags | ✅ | ✅ |
| WooCommerce Deep Integration (abandoned cart, per-product tags, opportunities) | — | ✅ |
| LearnDash Integration (courses, quizzes, groups, progress) | — | ✅ |
| Custom Objects (post type → GHL object) | — | ✅ |
| Family Relationships & Tag Inheritance | — | ✅ |
| Conditional Navigation Menus | — | ✅ |
| Extended Field Mapping (XProfile, WC, LearnDash) | — | ✅ |
| Public REST API Endpoints | — | ✅ |
| Analytics Dashboard (charts, CSV export) | — | ✅ |
| Enhanced Sync Logs (detail modal) | — | ✅ |
| Sync Queue & Engine (Action Scheduler) | ✅ | ✅ |
| Email Notifications | ✅ | ✅ |
| Auto-Login Links | ✅ | ✅ |
| White-Label Domain Support | ✅ | ✅ |
| WordPress Multisite | ✅ | ✅ |
| Security (encryption, nonce, rate limiting) | ✅ | ✅ |

---

## Core Foundation (Free)

### OAuth2 Secure Authentication
- One-click secure connection to GoHighLevel via OAuth2 proxy
- Automatic token refresh on 401/403 with circuit-breaker protection (3 failures → 5-minute cooldown)
- Manual API token authentication as an alternative
- Multi-location support for agencies
- HTTPS-secured connections with encrypted credential storage (WordPress salts)
- Per-site connections for WordPress Multisite

### API Scope Detection
- Automatic probing of available GHL API scopes on connection
- Detects access to: contacts, tags, custom fields, custom objects, associations, forms, locations, tasks, opportunities
- Admin warning notices when required scopes are missing

### Advanced Field Mapping
- Visual field mapping interface with bi-directional sync control (To GHL, From GHL, Both Ways)
- Supports core WordPress user fields and user meta → GHL standard and custom fields
- Real-time field list refresh from GHL API
- Computed/virtual field resolution via `ghl_crm_resolve_field_value` filter

#### Pro Field Mapping Add-ons (Pro)
- AI-assisted field suggestions using Levenshtein distance and semantic synonym matching (via `ghl_crm_field_suggestions_result` filter). Free plugin shows a PRO badge on the auto-suggest button.

### WordPress User Synchronization
- Automatic contact creation on user registration (hooks `user_register`, `edit_user_created_user`, `wpmu_new_user`, `wpmu_activate_user`, `add_user_to_blog`)
- Profile update syncing with 10-second per-user sync lock to prevent duplicates
- User deletion handling — configurable: delete GHL contact or update tags
- Login tracking with hourly throttle → updates `last_login` custom field in GHL
- Ping-pong prevention between inbound webhooks and outbound sync via transient guards
- Configurable default tags on registration
- Bulk user sync from WordPress Users list (bulk action "Sync to GoHighLevel")
- Duplicate contact auto-recovery: converts POST→PUT on duplicate detection, handles deleted/merged contacts via email re-lookup

#### Pro User Sync Add-ons (Pro)
- Sync preview / dry run — shows action (create/update), field-by-field comparison, tag changes, conflicts, and estimated API calls before syncing (via `ghl_crm_preview_user_sync_result` filter). Free plugin shows a PRO badge and upgrade CTA on the sync preview page.

---

## Tag Management (Free)

### Role-Based Tagging System
- Per-role tag configuration with `auto_apply` and `remove_on_change` options
- Hooks into WordPress role assignment, addition, and removal events
- Registration tags applied on user creation
- Bulk tag operations — add or remove tags for all users in a role via AJAX
- Queue-based reliable processing through Action Scheduler

#### Pro Tag Add-ons (Pro)
- **Global tags** — apply location-scoped tags to every synced contact (gated behind `ghl_crm_global_tags_enabled` filter)

### Tag API
- Cached GHL tag retrieval per-location with site transients
- Tag ID ↔ name conversion, normalization, and search
- 6 public helper functions: `ghl_crm_get_user_tag_ids()`, `ghl_crm_get_user_tag_names()`, `ghl_crm_user_has_tag()`, `ghl_crm_get_user_contact_id()`, `ghl_crm_add_tags_to_user()`, `ghl_crm_remove_tags_from_user()`

---

## Membership & Content Restrictions (Free)

### Tag-Based Access Control
- Three restriction types: Has ANY of tags, Has ALL of tags, Does NOT have tags
- Lock pages, posts, products, courses, and any public custom post type (filterable via `ghl_crm_restriction_post_types`)
- Configurable enforcement: redirect to URL or display custom access-denied message with login link
- Case-insensitive tag matching
- Pro family tag inheritance support via `ghl_user_effective_tags` filter

#### Pro Restriction Add-ons (Pro)
- **Admin bypass capability** — skip restriction checks for admins (gated behind `ghl_crm_restriction_overrides_enabled` filter)
- **Allowed-tag overrides** — extra tag-based access rules (gated behind `ghl_crm_restriction_overrides_enabled` filter)
- **Archive & search result protection** — optionally hides restricted posts from queries and REST API (via `ghl_crm_register_advanced_restriction_hooks` action)

### Restriction Tools
- **Post/Page Metabox** — Side panel on all public post types with Select2 tag selector, restriction type dropdown, and redirect URL input
- **`[ghl_restrict]` Shortcode** — Inline content restriction with rule types: `any`, `all`, `not`
- **Gutenberg Block** — `ghl-crm/restricted-content` block with tag selection, fallback content, customizable colors and padding

#### Pro Restriction Tools (Pro)
- **Elementor Widget Conditions** — "GoHighLevel Restrictions" section added to Advanced tab of all Elementor widgets with 5 restriction types: `has_any_tag`, `has_all_tags`, `not_has_tags`, `logged_in`, `logged_out` (via `ghl_crm_init_elementor_conditions` action)

---

## Form Management (Free)

### GoHighLevel Forms Embedding
- **`[ghl_form]` Shortcode** — Embeds GHL forms via iframe with configurable width, height, and white-label domain support
- **Elementor Form Widget** — Full Elementor widget with form selector dropdown (populated from GHL API), responsive width/height/margin/padding controls
- **Gutenberg Form Block** — `ghl-crm/form` block with form ID, width, and height attributes
- Per-form settings: autofill enabled, logged-in users only, submission tracking
- Form list caching from GHL API

#### Pro Form Add-ons (Pro)
- Per-form "once" submission limit — prevents the same logged-in user from re-submitting
- Custom URL parameters injected into GHL form submissions
- Custom "already submitted" message

---

## Contact Form 7 Integration (Free)

- GHL CRM tab added to Contact Form 7 editor
- Per-form configuration: enabled toggle, field mapping (CF7 field → GHL field), tags, update-if-exists option
- Automatic submission sync on `wpcf7_before_send_mail` — extracts form fields, maps to GHL, queues contact creation/update

---

## Webhook & Real-Time Sync (Free)

### Inbound Webhook Sync (GHL → WordPress)
- REST endpoint `ghl-crm/v1/webhooks` with shared-secret verification (`x-ghl-token` header)
- 256KB payload limit with auto-generated secrets
- Location scoping — ignores non-matching locations
- 30-second inbound guard transient to prevent ping-pong loops
- Creates WordPress users from GHL contacts with field mapping and auto-generated usernames
- Tag hydration from API when webhook payload is incomplete

### Bulk Import (GHL → WordPress)
- Paginated API import (100 contacts/page, cursor-based pagination)
- AJAX-driven with real-time progress tracking via transients
- Duplicate detection, email validation, location-scoped user meta

---

## BuddyBoss / BuddyPress Integration (Free)

### Group → GHL Custom Object Sync
- Syncs BuddyBoss groups to GHL Custom Objects with group-type → Custom Object schema mapping
- Full lifecycle hooks: group save, delete, join, leave, remove member, ban/unban, group type change
- Member ↔ Contact associations — creates/deletes GHL associations when users join/leave groups
- Dependency system: if a user has no GHL contact, queues contact creation first, then associates
- Group type auto-creation — auto-creates GHL Custom Object schemas when new BuddyBoss group types are published

### Group Admin Metabox
- Side metabox in BuddyBoss group admin showing sync status, GHL Record ID (linked), Object ID, Association ID
- Manual "Sync Now" button with AJAX
- White-label domain support for GHL links

### Bulk Group Sync
- AJAX handler for syncing all BuddyBoss groups at once

---

## WooCommerce Integration

### Free Plugin
- Configurable WooCommerce customer tags — detects recent orders and applies configured tags
- Settings for abandoned cart threshold, tags, and recovery (actual cart tracking in Pro)
- Settings for opportunity pipeline/stage mapping, product/category filters, minimum order value (processing delegated to Pro)

### Pro Add-on (Pro)
- **Auto lead → customer conversion** — converts GHL lead to customer on first WooCommerce purchase, configurable by order status
- **Per-product GHL tags** — Apply/remove different GHL tags per product per order status with deduplication tracking
- **Product meta box** — "GoHighLevel" tab on WooCommerce product data panels with Select2 tag picker per order status
- **Abandoned cart tracking** — hooks into `woocommerce_add_to_cart`, `woocommerce_cart_item_removed`, etc. with transient-based cart storage (7-day expiry)
- **Email capture** — captures email at checkout via classic form, WooCommerce Blocks/Store API, and custom REST endpoint
- **Configurable abandonment threshold** — default 30 minutes with 15-minute scheduled checks via Action Scheduler
- **Abandoned cart tags** — apply GHL tags when cart is abandoned
- **Recovery tags** — apply tags when user returns, with options to remove abandoned tags on purchase/recovery and re-apply on re-abandonment
- **Abandoned cart opportunities** — creates GHL pipeline opportunities for abandoned carts
- **Auto-cleanup** — removes old abandoned cart data after configurable number of days
- **Opportunity/Pipeline management** — configurable pipeline and stage assignments per WC order status (pending, processing, completed, cancelled) with filter criteria (all orders, minimum value, specific products/categories)

---

## LearnDash Integration (Pro)

### Course Sync
- **Enrollment tags** — apply configurable GHL tags when a user is enrolled in a course
- **Completion tags** — apply configurable GHL tags when a user completes a course
- **Revocation tags** — add/remove tags when course access is revoked
- **Tag-based auto-enrollment** — if a GHL contact gets a matching tag, auto-enroll them in the corresponding course. Batch processing (500 users/batch via Action Scheduler)
- **Auto-unenrollment** — remove from course when auto-enroll tag is removed from GHL contact
- **Batch re-enrollment** — when auto-enroll tags change on a course, batch-processes existing users
- **Course meta box** — "GoHighLevel Automations" side meta box on `sfwd-courses` with auto-enroll tag + completion tag Select2 pickers

### Content Sync (Lessons, Topics, Quizzes)
- **Per-content completion tags** — per lesson, topic, and quiz completion tags via post meta
- **Quiz score-based threshold tagging** — configurable score ranges → different tags. Multiple threshold rows with min/max score and associated tags
- **Pending tags cache** — uses `_ghl_pending_tags` user meta to prevent race conditions across concurrent completions
- **Content meta box** — admin UI for lesson/topic/quiz with completion tag selectors and dynamic quiz score threshold row builder

### Group Sync
- **Group join tags** — apply GHL tags when user joins a LearnDash group (deduplicated, only new tags applied)
- **Group leave tag removal** — remove group-specific tags on leave (tracks which tags each group applied per user)
- **Tag-based group auto-enrollment** — GHL tag triggers auto-add to LearnDash group
- **Group meta box** — "GoHighLevel Automations" meta box on `groups` post type: auto-enroll tag, membership tag, "remove on leave" checkbox

### Course Progress Sync
- **Progress → GHL custom fields** — syncs percentage, status (`not_started`/`in_progress`/`completed`), completed steps, and total steps to GHL custom fields
- **Per-course field mapping** — per-course override via post meta, or global from settings. Virtual field keys: `ld_progress_percentage_{course_id}`, `ld_progress_status_{course_id}`, `ld_progress_completed_{course_id}`, `ld_progress_total_{course_id}`
- **Virtual field resolver** — resolves LearnDash progress fields at sync time via `ghl_crm_resolve_field_value` filter (works with regular profile update syncs)
- **15-minute debounce** — per-user-per-course debounce window to prevent API spam on rapid lesson completions (filterable via `ghl_crm_progress_debounce_seconds`)
- **Triggers** — fires on `learndash_course_completed`, `learndash_lesson_completed`, `learndash_topic_completed`

---

## Custom Objects Integration (Pro)

### WordPress Posts → GHL Custom Objects
- Map any WordPress custom post type to a GHL Custom Object schema with configurable field mappings
- **Field discovery** — dynamically discovers core post fields (ID, title, content, excerpt, date, status, slug, author), post meta, taxonomies, and ACF fields per post type
- **Context-aware sync triggers**:
  - Universal: publish, update, delete
  - WooCommerce: product_purchased, order_processing, thankyou_page, stock_changed
  - LearnDash courses: student_enrolled, student_completed, student_unenrolled
  - LearnDash lessons: lesson_completed, lesson_accessed
  - LearnDash quizzes: quiz_completed, quiz_passed, quiz_failed
  - LearnDash assignments: assignment_submitted, assignment_graded, assignment_approved
  - LearnDash certificates: certificate_earned
  - BuddyBoss groups: group_created, member_joined, member_left
  - LearnDash transactions: order_completed, order_refunded
- **Contact association** — links custom object records to GHL contacts. Sources: post_author, product_purchaser, course_students, lesson_student, meta_field, ACF field. Auto-creates contacts if configured.
- **Secondary contact associations** — multiple contacts per record with auto-detected association direction from GHL API
- **Data transforms** — none, sanitize HTML, strip HTML, convert to number, ISO date format, JSON encode
- **Nested meta access** — dot-notation for nested post meta values (e.g., `meta:ld_course_steps.steps_count`)
- **Lifecycle management** — creates records with location_id, updates with only changed properties, deletes with metadata cleanup. Verifies record existence before update; recreates if record was deleted from GHL.
- **Manual sync** — on-demand sync of individual posts

---

## Family Relationship System (Pro)

The free plugin includes a **Family Relationships settings tab** with a greyed-out feature preview and upgrade CTA. Full functionality requires Pro.

### Parent-Child Account Management
- Custom database table (`ghl_family_relationships`) with parent_user_id, child_user_id, family_group_id, status (active/pending), location_id, site_id
- **Invitation system** — search by email/username, create new WP user + send HTML invitation email, invite existing users. Token-based acceptance (64-character hex token, 7-day expiry).
- **Tag inheritance** — parent's GHL tags automatically inherited by children (minus configurable "parent-only" tags). Tracks inherited tags in `_ghl_inherited_tag_ids` user meta. Queue-based async tag sync.
- **Pending family tags** — children without a GHL contact yet get tags stored in `_ghl_pending_family_tags` for later sync
- **Tag removal on unlink** — inherited tags removed via queue when child is unlinked from parent
- **Circular relationship prevention** — repository prevents parent ↔ child circular references
- **Location-aware** — all queries scoped by GHL location_id + WordPress site_id
- **User deletion cleanup** — cascading cleanup of relationships + meta
- **Multisite support** — handles `remove_user_from_blog` for per-site relationship cleanup

### Frontend Shortcode
- `[ghl_family_manager]` shortcode with SweetAlert2-powered UI for managing family members
- Template-based rendering (`family-manager.php`)
- AJAX endpoints: search user, get children, link child, unlink child, get all parents

### BuddyBoss Group Integration
- Auto-creates private BuddyBoss group per parent family
- Adds/removes children from group on link/unlink
- Handles group member removal → breaks family relationship
- Configurable group name/description with placeholders (`{parent_name}`, `{child_count}`, etc.)
- Batch sync all families
- Controlled by `family_buddyboss_groups` setting

---

## Conditional Navigation Menus (Pro)

- 6 visibility rules: Show to Everyone, Logged-In Only, Logged-Out Only, Has ANY of tags, Has ALL of tags, Does NOT have any of tags
- Custom fields injected into WordPress menu editor with Select2 tag picker
- Frontend filtering via `wp_get_nav_menu_items` hook — show/hide menu items based on current user's GHL tags
- Rule and tag data stored in nav menu item post meta

---

## Extended Field Mapping (Pro)

- **Custom user meta fields** — discovers all custom user meta keys (excluding WP internal, BuddyPress, GHL, WooCommerce-specific keys) and makes them mappable to GHL fields
- **WooCommerce fields** — exposes `billing_*`, `shipping_*`, `wc_*` user meta as a separate "WooCommerce Fields" group in field mapping
- **BuddyBoss XProfile fields** — discovers all BuddyBoss/BuddyPress XProfile field groups and fields (textbox, textarea, number, date, dropdown, multi-select, radio, checkbox, URL, phone), keyed as `xprofile_{id}`
- **LearnDash progress fields** — exposes per-course progress fields as mappable: `ld_progress_percentage_{id}`, `ld_progress_status_{id}`, `ld_progress_completed_{id}`, `ld_progress_total_{id}` for every published course

---

## Enhanced Sync Logs (Pro)

- "View Details" button on sync log entries that opens a modal with formatted JSON payload details
- Dedicated CSS/JS assets for sync log modal UI

---

## Sync Queue & Engine (Free)

### Action Scheduler Queue
- 10-second processing interval with WP-Cron fallback
- 10,000 item queue limit per site, batch size of 50
- Duplicate prevention — updates existing pending item instead of creating a new one
- 2-minute network-wide processing lock (site transient) for multisite safety
- Routes by type: `user`, `contact`, `wc_customer`, `form` (built-in). `order`, `group`, `course`, `custom_object` delegated via `ghl_crm_execute_sync` filter to Pro.
- Dependency system for prerequisite tasks (e.g., create contact before associating)

### Rate Limiter
- Burst: 100 requests per 10 seconds per location
- Daily: 200,000 requests per day
- Site transient tracking for multisite compatibility

### Contact Cache
- Transient-based GHL contact caching with location-scoped keys
- Configurable TTL (0–86400 seconds)
- Bulk cache clear via direct database query

### Sync Logging
- Database logging to custom `ghl_sync_log` table
- Sensitive data redaction — automatically redacts tokens, secrets, and authorization headers in logged payloads
- Status tracking per sync event (success, failed, pending)

### Sync Statistics
- Aggregate success/failed counters per site
- Last sync timestamps
- Multisite-aware using `get_blog_option`/`update_blog_option`

---

## GHL API Resources (Free)

- **Contacts** — full CRUD, find by email/phone, add/remove tags, add to workflow, upsert, notes CRUD, tasks, appointments, cursor-based list pagination
- **Opportunities** — pipeline listing, opportunity CRUD, status updates, upsert with WordPress meta tracking
- **Custom Objects** — schema CRUD, record CRUD, associations, schema caching with configurable duration
- **Forms** — form listing with caching, submission counts, white-label embed URL support

---

## REST API (Free)

### Editor-Only Endpoints (always registered)
- `/ghl-crm/v1/connection/status` — connection verification
- `/ghl-crm/v1/forms` — form listing
- `/ghl-crm/v1/tags` — tag listing
- Requires `edit_posts` capability

### Public Endpoints (Pro)
Registered by Pro via `ghl_crm_register_public_rest_routes` action. Free plugin shows a PRO badge and upgrade CTA on the REST API settings page.
- `/ghl-crm/v1/contacts` (POST) — create/update contacts
- `/ghl-crm/v1/sync` (POST) — trigger sync operations
- `/ghl-crm/v1/status` (GET) — sync status
- `/ghl-crm/v1/webhooks` (GET/POST) — webhook management
- Bearer token authentication, IP whitelist, rate limiting
- Requires `rest_api_enabled` setting

---

## Admin Interface (Free)

### SPA Admin Panel
- Single-page application with hash-based routing (`#/settings`, `#/integrations`, `#/field-mapping`, `#/sync-logs`, `#/forms`)
- AJAX-powered view loading with no full-page reloads
- SweetAlert2 toast notifications and modal confirmations
- Tabbed settings organization
- Mobile-responsive design

### Dashboard & Analytics
- Contact metrics: total GHL contacts, total WP users, synced, pending, failed, sync rate
- Integration status overview
- Recent activity feed

#### Pro Analytics (Pro)
Rendered by Pro via `ghl_crm_render_analytics_tab` action. Free plugin shows an upgrade CTA with a greyed-out feature preview.
- Sync activity trends (24h / 7d / 30d)
- System health indicators
- Chart.js visualizations (daily activity, sync type breakdown, hourly activity, success/failure rates)
- CSV export

### Setup Wizard
- First-activation redirect to guided setup
- Step-by-step connection setup on a dedicated admin page

### User Management Enhancements
- **User list columns** — "GHL Contact ID" (linked to GHL, sortable) and "GHL Sync Status" (synced/not synced with relative timestamp)
- **User profile section** — admin-only GHL section on user edit pages with contact data display, Select2 tag management, manual "Sync Now", "Refresh from GHL", and auto-login link generation

### Admin Notifications
- Centralized notice system using site transients (multisite-aware)
- Success, error, warning, and info notice types
- Upgrade notice dismissal

### Plugin Action Links
- "Settings" link on plugins page
- Conditional "Upgrade to Pro" link (hidden when Pro is active)

---

## Email Notification System (Free)

- 6 notification types: `connection_lost`, `sync_errors`, `queue_backlog`, `rate_limit`, `webhook_failures`, `daily_summary`
- HTML email templates with configurable throttling
- Daily summary scheduling via Action Scheduler or WP-Cron
- Test notification AJAX handlers

---

## Auto-Login Links (Free)

- Secure one-time login links with 15-minute expiry
- SHA-256 token hashing with one-use enforcement
- Expired token cleanup
- Checks `ghl_autologin` query parameter on WordPress `init`

---

## White Label Domain Support (Free)

- Configure custom GoHighLevel white-label domains
- All links to GHL records (contact IDs, custom object records) use your branded domain
- Agency-ready branding

---

## WordPress Multisite Support (Free)

- Per-site OAuth connections and settings
- Site-specific field mappings and sync queues
- Site-scoped transients for queue locks and caching
- `switch_to_blog` / `restore_current_blog` handling in queue processing
- Multisite-aware activation/deactivation hooks
- Per-site sync statistics via `get_blog_option` / `update_blog_option`

---

## Security (Free)

- OAuth2 industry-standard authentication with encrypted credential storage
- Automatic token encryption using WordPress salts
- Nonce verification on all AJAX requests
- Capability checks for all admin operations
- Input sanitization and output escaping
- Secure webhook verification via shared-secret header
- Rate limiting protection (burst + daily limits)
- Sensitive data redaction in all sync logs

---

## Database & Infrastructure (Free)

- 4 custom tables: `ghl_sync_queue`, `ghl_sync_log`, `ghl_family_relationships`, `ghl_reporting_events`
- Versioned migration system (1.4.0 duplicate cleanup → 1.5.0 performance indexes → 1.11.0 location_id on family table)
- Bundled Action Scheduler library for reliable async processing
- Composer PSR-4 autoloading

---

## Developer Extensibility (Free)

### Filters
- `ghl_crm_execute_sync` — handle custom sync types (Pro uses for orders, groups, courses, custom objects)
- `ghl_crm_resolve_field_value` — resolve computed/virtual field values at sync time
- `ghl_crm_save_integration_settings` — extend integration settings
- `ghl_crm_form_settings_before_save` — modify form settings before save
- `ghl_crm_should_deny_access` — override access control decisions
- `ghl_crm_denial_page_content` — customize denial messages
- `ghl_user_effective_tags` — extend effective tags for access control (Pro adds family inheritance)
- `ghl_crm_restriction_post_types` — control which post types show restriction metabox
- `ghl_crm_progress_debounce_seconds` — customize LearnDash progress debounce window

#### Pro Extension Filters (added in 1.2.0)
- `ghl_crm_field_suggestions_result` — return AI-assisted field mapping suggestions
- `ghl_crm_preview_user_sync_result` — return sync preview / dry-run data
- `ghl_crm_sync_preview_enabled` — enable sync preview UI (Pro returns `true`)
- `ghl_crm_field_suggestions_enabled` — enable AI suggest button (Pro returns `true`)
- `ghl_crm_archive_protection_enabled` — enable archive/REST protection settings (Pro returns `true`)
- `ghl_crm_public_rest_api_enabled` — enable public REST API settings (Pro returns `true`)
- `ghl_crm_family_relationships_enabled` — enable family settings tab (Pro returns `true`)
- `ghl_crm_restriction_overrides_enabled` — enable admin bypass & allowed-tag overrides (Pro returns `true`)
- `ghl_crm_global_tags_enabled` — enable global tag configuration (Pro returns `true`)

### Actions
- `ghl_crm_connection_status_changed` — fired when connection status changes
- `ghl_crm_loader_components` — register additional components (Pro registers 22 components)

#### Pro Extension Actions (added in 1.2.0)
- `ghl_crm_init_elementor_conditions` — initialize Elementor widget restriction conditions
- `ghl_crm_register_advanced_restriction_hooks` — register archive & REST API protection hooks
- `ghl_crm_register_public_rest_routes` — register public REST API endpoints (contacts, sync, status, webhooks)
- `ghl_crm_render_analytics_tab` — render analytics dashboard charts & CSV export

### Architecture
- Clean MVC architecture with PSR-4 autoloading
- WordPress Coding Standards compliant
- Extensive PHPDoc documentation throughout
- Component-based registration system via Loader class

---

## Pro License System (Pro)

- AJAX-based license activation/deactivation with multi-endpoint obfuscation
- SHA-256 domain fingerprinting for license verification
- 24-hour license caching with daily background re-validation via Action Scheduler
- License Guard — degrades features on invalid license: disables background sync, limits batch size to 5, adds shutdown delay
- File integrity checker — MD5 baseline verification of critical files to detect tampering
- Feature gating — without valid license, only 5 core components (ProFlag, AdminNotices, LicenseManager, LicenseGuard, LicenseSettings) are loaded

---

## Telemetry & Reporting (Free, Opt-in)

- Opt-in telemetry: batches events locally, dispatches to `highlevelsync.com`
- Batch size 50, 15-minute dispatch interval
- Captures fatal errors on shutdown — **filtered to only log errors from `ghl-crm-integration` or `ghl-crm-integration-pro` directories** (prevents unrelated theme/plugin errors)
- Action Scheduler or WP-Cron fallback

---

## Use Cases

### For Marketing Agencies
- Manage multiple client sites with individual GHL locations
- Per-site OAuth connections and settings
- White-label domain support
- Network-level controls with WordPress Multisite

### For eCommerce Stores (Pro)
- Tag customers by products purchased with per-product tag configuration
- Abandoned cart tracking with tag-based recovery automation
- Pipeline/opportunity management mapped to WooCommerce order statuses
- Auto lead → customer conversion on first purchase

### For Course Creators (Pro)
- Tag students by course, lesson, topic, and quiz completion
- Quiz score-based threshold tagging (e.g., 90%+ gets "high-performer" tag)
- Sync course progress percentage, status, and step counts to GHL custom fields
- Auto-enroll students in courses when they receive specific GHL tags
- Per-content completion tags for granular automation

### For Membership Sites
- Restrict content by GHL tags with ANY/ALL/NONE logic (Free)
- Gutenberg block-level restrictions (Free)
- Shortcode content restrictions (Free)
- Elementor widget-level restrictions (Pro)
- Archive & search result protection (Pro)
- Conditional navigation menus based on GHL tags (Pro)
- Family relationship management with tag inheritance (Pro)

### For Community Platforms
- BuddyBoss Groups → GHL Custom Objects sync (Free)
- Group membership → contact associations (Free)
- BuddyBoss XProfile fields → GHL custom fields mapping (Pro)
- Family BuddyBoss group integration (Pro)

---

## Requirements

- WordPress 5.8+ (6.0+ recommended)
- PHP 7.4+ (8.0+ recommended)
- GoHighLevel account with API access
- HTTPS (required for OAuth2)
- WordPress Cron or server cron enabled

### Optional Platform Requirements
- WooCommerce 5.0+ for eCommerce features
- BuddyBoss / BuddyPress for community features
- LearnDash 3.0+ for LMS features
- Elementor for widget restrictions and form widget
- Contact Form 7 for CF7 integration

### Recommended Server Settings
- PHP Memory Limit: 256MB+
- Max Execution Time: 300 seconds

---

## Installation

1. Upload plugin to `/wp-content/plugins/ghl-crm-integration`
2. Activate through WordPress admin
3. Navigate to **GHL CRM** menu
4. Click **"Connect with GoHighLevel"** or enter your API token
5. Authorize the OAuth connection
6. Configure field mappings
7. Enable desired integrations
8. Start automating!

---

## Quick Setup Steps

1. **OAuth Connection** — One-click secure connection
2. **Field Mapping** — Map WordPress fields to GHL fields
3. **Enable User Sync** — Turn on automatic synchronization
4. **Role Tags** (optional) — Configure role-based tagging
5. **Content Restrictions** (optional) — Set up tag-based access control
6. **Webhooks** (optional) — Enable real-time inbound sync
7. **Integrations** (optional) — Enable WooCommerce, LearnDash, BuddyBoss
8. **Forms** (optional) — Embed and configure GHL forms

---

## Why Choose This Plugin

- **Most comprehensive** GHL integration available for WordPress
- **Only plugin** with BuddyBoss Group → GHL Custom Object sync
- **Only plugin** with family relationship management and tag inheritance
- **Deep integrations** — not just basic contact sync
- **OAuth2 security** — encrypted credentials, automatic token refresh, circuit-breaker protection
- **Real-time sync** — webhook automation with ping-pong prevention
- **Enterprise ready** — WordPress Multisite with per-site isolation
- **Modern UI** — clean SPA interface with instant feedback
- **Reliable** — Action Scheduler queue with rate limiting and duplicate prevention
- **Extensible** — comprehensive filter and action hook system for developers
- **Well documented** — extensive documentation included

---

## License

GPL v2 or later

---

## Developer

**Yahya Eddaqqaq**
- Website: [yahyadev.com](https://yahyadev.online/)
- GitHub: [@Cottonnion](https://github.com/Cottonnion)
- Company: [LabGenz](https://labgenz.com/)

---

**Transform your WordPress site into a CRM automation powerhouse. Get started today.**