=== Syncly for GoHighLevel ===
Contributors: yahyadeved
Tags: gohighlevel, leadconnector, crm, contact-sync, forms
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.48
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GoHighLevel WordPress integration for two-way contact sync, field mapping, form submissions, and tag-based content access. Connect your CRM.

== Description ==

Connect WordPress to GoHighLevel so user registrations, profile changes, and form submissions reach your CRM without manual exports. Syncly handles GoHighLevel contact sync in both directions, maps your fields, and uses contact tags to control access to WordPress content.

Built specifically for GoHighLevel, also known as HighLevel or LeadConnector, Syncly connects through OAuth. Choose the data you want to sync, configure your mappings, and check the results in Sync Logs. Background processing handles queued work, retries temporary failures, and manages API rate limits.

= Contact sync, field mapping, and content access =

The free plugin includes:

* **Two-way contact sync:** Send WordPress user data to GoHighLevel and receive contact updates through inbound webhooks.
* **Visual field mapping:** Match WordPress user fields and BuddyBoss or BuddyPress extended profile fields to GoHighLevel custom fields.
* **Role-based tags:** Apply or remove GoHighLevel tags when a user's WordPress role changes.
* **Tag-based content access:** Restrict pages, posts, courses, and products according to a user's GoHighLevel tags.
* **Form integrations:** Send submissions from WPForms, Gravity Forms, and Contact Form 7 to GoHighLevel.
* **Embedded GoHighLevel forms:** Add a GHL form to a page or post with a shortcode or Gutenberg block.
* **OAuth connection:** Authorize access without entering a GoHighLevel API key. Syncly handles token exchange and refresh through its OAuth proxy.
* **Background processing and logs:** Process sync jobs with Action Scheduler, retry temporary failures, and review sync activity from WordPress.

= Send WordPress form submissions to GoHighLevel =

**WPForms Lite and Pro**

Map form answers to standard or custom contact fields, add tags, and choose whether to update or skip contacts with a matching email address. Submissions run through the background queue, with results available in Sync Logs.

**Gravity Forms**

Configure each form from its GHL CRM settings tab. Map contact fields, apply tags, choose how to handle existing contacts, and use the form title as the contact source. You can also delay delivery, skip spam entries, and add submission details as a contact timeline note.

Recent submission history shows sync status. Manual resend controls let you send a completed or failed submission again.

**Contact Form 7**

Connect Contact Form 7 submissions to GoHighLevel as part of the free plugin's form integrations.

= What Syncly Pro adds =

Syncly Pro is a separate add-on for ecommerce, course activity, and additional automation:

* **WooCommerce:** Sync customers, orders, and purchase history to GoHighLevel contacts.
* **LearnDash:** Connect course enrollment, completion, and progress with GoHighLevel contact activity.
* **Family relationships and login sync:** Add family relationship handling and sync WordPress login activity.
* **Conditional form rules:** Send submissions only when selected answers match your configured conditions.
* **WPForms automation:** Set custom contact source labels, delay delivery, add contact timeline notes, and enroll contacts in GoHighLevel workflows after a successful sync.
* **Gravity Forms automation:** Add routing, assignment, opportunity mapping, lead scoring, conversation history, and submission replay.

WPForms source labels, delays, timeline notes, and workflow enrollment require Pro. Gravity Forms source naming, delays, timeline notes, submission history, and manual resend controls are included in the free plugin.

= Where Syncly fits =

Use the free plugin to add new WordPress users to your CRM, send form enquiries to GoHighLevel with mapped fields and tags, or manage member access through contact tags.

For stores and course sites, Syncly Pro adds purchase and learning activity to the same contact records. Those events can support follow-up workflows in GoHighLevel, such as responding to an order or a completed course.

Syncly for GoHighLevel is an independent plugin. It is not affiliated with, endorsed by, or sponsored by GoHighLevel or HighLevel.

= External services =

This plugin connects to external services to provide CRM synchronization and OAuth authentication. These services are required for the plugin to connect WordPress data with a GoHighLevel account.

GoHighLevel and LeadConnector APIs: The plugin sends CRM-related data such as contact names, email addresses, phone numbers, WordPress user profile data, tag names, custom field values, WooCommerce customer/order data when enabled, BuddyBoss profile data when enabled, LearnDash activity when enabled, webhook payloads, mapped form answers, and form identifiers. Data is sent when an administrator connects the plugin, runs sync actions, saves mapping/settings that require metadata lookup, users register or update profiles, connected ecommerce/community/LMS events occur, enabled WPForms or Gravity Forms submissions are processed, webhooks are received, or embedded GoHighLevel forms are displayed.

GoHighLevel service links: https://www.gohighlevel.com/terms-of-service and https://www.gohighlevel.com/privacy-policy

Syncly OAuth proxy: The plugin uses a Syncly proxy endpoint during OAuth token exchange, token refresh, and reconnect flows so OAuth client credentials are not distributed inside the plugin. The proxy receives OAuth authorization codes, refresh tokens, location/account identifiers, and related token request metadata during connection, token refresh, and reconnect requests. Token refresh may run automatically in the background.

Syncly service links: https://synclyforgohighlevel.com/terms-of-service/ and https://synclyforgohighlevel.com/privacy-policy/

= Debug logging =

File logging is disabled by default. To enable temporary support logging, add `define( 'SYNCLY_BRIDGE_LOG', true );` to `wp-config.php`. Logs may contain operational metadata and must not be exposed publicly. Apache and IIS installations are protected automatically; nginx administrators must deny web access to `wp-content/uploads/syncly-logs/` before enabling logging.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/syncly` or install the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate Syncly for GoHighLevel from the Plugins screen.
3. Open the Syncly admin menu.
4. Connect your GoHighLevel account using OAuth.
5. Configure field mappings, sync settings, forms, webhooks, and optional integrations as needed.

== Frequently Asked Questions ==

= Do I need a GoHighLevel account? =

Yes. You need a GoHighLevel account and must authorize the connection through GoHighLevel OAuth.

= Can GoHighLevel update my WordPress users? =

Yes. WordPress CRM sync works in both directions. Configure inbound webhooks to receive GoHighLevel contact changes in WordPress. The Inbound Contact Actions setting lets you allow user creation and updates, creation only, or updates only.

= How do I connect a WPForms form? =

Open the form in the WPForms builder and go to Settings > Syncly. Enable the integration and map at least an email field. Add any contact tags and choose whether matching contacts should be updated or skipped.

This works with WPForms Lite and WPForms Pro. Check Syncly > Sync Logs to see the result after the submission is processed.

= Do I need Syncly Pro for form submissions or content restrictions? =

No. Contact sync, field mapping, role-based tags, tag-based content restrictions, inbound webhooks, and the WPForms, Gravity Forms, and Contact Form 7 integrations are included in the free plugin.

Pro adds WooCommerce and LearnDash sync, family relationships, login sync, conditional form rules, and the additional automation described above.

= What happens when a sync fails or reaches an API limit? =

Syncly queues work with Action Scheduler and retries temporary failures. It throttles requests at 100 requests per 10 seconds and 200,000 per day, with backoff when rate limits are reached.

Check queue status and Sync Logs to investigate failed jobs. If GoHighLevel rejects a refresh token, Syncly pauses automatic connection attempts and asks an administrator to reconnect. Sites with little or no traffic need a real server cron trigger to keep scheduled work running.

Action Scheduler is included with the plugin.

= What data leaves my site? =

Syncly sends data needed for the features you enable, including contact details, mapped fields, tags, and applicable form or integration data. The External services section lists the data, destinations, and events that trigger transmission.

The Syncly OAuth proxy handles token requests so OAuth client credentials are not distributed in the plugin. File logging is off by default. Site owners remain responsible for obtaining any required consent and reviewing how GoHighLevel stores and processes CRM data.

= Is Syncly an official GoHighLevel plugin? =

No. Syncly is independently developed and is not affiliated with, endorsed by, or sponsored by GoHighLevel or HighLevel.

== Screenshots ==

1. Syncly dashboard with connection health, queue status, and quick actions.
2. Sync analytics showing activity and success rates.
3. General settings for connection branding and user data synchronization.
4. WooCommerce integration settings with Syncly Pro.
5. Visual mapping of WordPress fields to GoHighLevel contact fields.
6. Gravity Forms contact field mapping and existing-contact behavior.
7. Gravity Forms submission settings and notes, with conditional rules provided by Syncly Pro.
8. Embedded GoHighLevel forms and available embed options.
9. LearnDash course tag and campaign automation with Syncly Pro.
10. LearnDash course progress field mapping with Syncly Pro.
11. WPForms integration with contact field mapping, tags, and existing-contact update settings.

== Upgrade Notice ==

= 1.4.48 =
Fixes a fatal error that could interrupt manual or background queue processing when an API response returns plain-array headers. No settings changes required.

= 1.4.47 =
Improves OAuth refresh coordination, retry backoff, and background scheduling. Connections with rejected refresh tokens require manual reconnection. Idle sites need a real server cron trigger.

= 1.4.46 =
Documentation and listing refresh: clearer feature descriptions, new FAQs, corrected free vs Pro boundaries, and updated links. No functional changes to syncing.

== Changelog ==

= 1.4.48 =
* Fixed a fatal error that could stop manual or background queue processing when WordPress returned API response headers as an array instead of a header dictionary.
* Refreshed the plugin description, FAQ, screenshots, and upgrade notice for clarity; no changes to sync behavior.

= 1.4.47 =
* Fixed overlapping OAuth refresh attempts by sharing a per-site lock and reloading current credentials before refresh.
* Saved encrypted token pairs, expiry, and health together with revision checks to prevent stale settings overwrites.
* Recognized nested invalid-refresh-token and inactive-location errors, paused automatic attempts, and added throttled administrator notices.
* Added persistent retry backoff for temporary failures and Retry-After handling for rate limits; sync work remains recoverable while authentication is unavailable.
* Removed immediate timeout retries and automatic refresh/reconnect fallback loops.
* Deduplicated pending recurring queue and OAuth refresh schedules, added OAuth WP-Cron fallback, and added a Site Health recommendation for overdue refresh jobs.

= 1.4.46 =
* Refreshed the WordPress.org listing with a clearer short description, natural keyword coverage, and a new "Built for GoHighLevel" section.
* Added FAQ entries covering free vs Syncly Pro, privacy and data protection, API rate limits, and WP Fusion migration.
* Clarified free vs Syncly Pro feature boundaries across the readme and documentation, and corrected Pro-gated feature claims.
* Updated plugin and documentation links to HTTPS and corrected stale internal feature metadata.

= 1.4.45 =
* Added WPForms Lite and Pro integration with per-form contact field mapping, tags, and update-or-skip behavior.
* Improved WPForms builder asset loading and searchable dropdowns, including delayed panel rendering.
* Preserved separate queued form submissions and added support for Syncly Pro WPForms automation.
* Fixed the dashboard manual-sync button when controls are rendered after page load.


= 1.4.44 =
- Removed query limit on user meta keys and values dropdowns in Bulk Sync tools to display all available options.
- Added extraction of scalar values from serialized user meta arrays for comprehensive filter options.

= 1.4.43 =
- Fixed User Meta Filter dropdowns in Bulk Sync tools not loading in production environments.
- Updated all minified CSS and JS build assets, including Select2 AJAX meta key/value handlers.
- Enhanced deployment workflows to build fresh minified assets prior to SVN releases.

= 1.4.42 =
- Fixed a 404 after submitting an embedded GoHighLevel form whose redirect URL points back to WordPress. GHL appends `name` as a query arg, which WordPress treats as a post slug.

= 1.4.41 =
- Added "Inbound Contact Actions" setting in Webhooks tab with three modes: Create & Update (default), Create Only, and Update Only.
- Fixed duplicate email error when inbound webhook tries to create a WordPress user with an existing email address. Now logs as "skipped" instead of "failed".
- Fixed ContactCreate and ContactUpdate webhooks attempting to create users when email already exists in WordPress with different GoHighLevel contact IDs.
- Bulk Sync to GHL: user meta key filter is now a searchable dropdown listing existing meta keys, with all stored values for the chosen key offered as a multi-select that also accepts custom values.

= 1.4.40 =
* Fixed all queue action handlers (add_tags, remove_tags, user_register, profile_update, delete_user, gf_add_note) to handle contacts that were deleted or merged in GoHighLevel. Stale contact IDs are now cleaned up and operations return graceful success instead of burning through retry attempts.
* Fixed inbound webhook sync overwriting a WordPress user's name when a duplicate contact with the same email but different GHL contact ID triggers an update.
* Fixed UserMetaSync leaving stale contact IDs in user meta when the GHL contact no longer exists.
* Custom Objects: added ability to create new custom object schemas directly from the WordPress admin.
* Custom Objects: added per-object association lookup for more reliable association loading.
* Custom Objects: expanded post type list to include show_ui types, with a `syncly_supported_post_types` filter for extensions.
* Custom Objects: background queue processing and sync logging are now always enabled for all mappings.
* Custom Objects: added pattern field to field mappings for transform support.

= 1.4.39 =
* Setup Wizard: reordered steps so you pick your preferred view (Simple or Advanced) before connecting, and connection no longer blocks setup — you can finish configuration and connect GoHighLevel at any point.
* Added a Simple/Advanced display mode: choose a beginner-friendly dashboard or the full advanced interface with every settings tab and Sync Logs.
* OAuth: the setup wizard now returns you to the exact step you connected from, and reconnecting after a failed session no longer kicks you out of the wizard to a generic page.
* Connection screen now lists the exact GoHighLevel permissions (OAuth scopes) requested during authorization.
* Consistency pass: user profile colors and links now use the shared GHL design tokens instead of hardcoded colors.

= 1.4.38 =
* Fixed user profile tags sometimes showing as raw tag IDs and being synced to GoHighLevel as IDs when the OAuth connection was unhealthy. The plugin now keeps a local tag ID → name map so tags are always synced by their real names, even during brief API outages.

= 1.4.37 =
* Added a dismissible review-request banner on the Syncly dashboard that invites you to leave a review on WordPress.org after you've been using the plugin for 3 days.

= 1.4.36 =
* Calendar Appointments: added the calendars/events.write OAuth scope so Syncly Pro can book and manage GoHighLevel calendar appointments.
* Broadened OAuth scopes to align with the GoHighLevel marketplace draft (calendars, workflows, conversations, messages, locations).
* Clear Cache now also purges the cached GoHighLevel calendars and workflows used by Syncly Pro's product auto-booking settings.
* REST API: the manual sync trigger now validates sync types, processes the sync queue immediately, and returns queue status.
* Added full-detail contact lookup and custom-field update support to the contact API for Syncly Pro's public REST endpoints.
* Rebuilt the Webhook Setup screen with the GHL design-token styling.
* Removed the obsolete Campaigns scope; added Upgrade page cards for Syncly Pro's new calendar booking and workflow enrollment features.

= 1.4.35 =
* Consolidated admin styling into shared GHL design tokens (globals.css) so colors, spacing, and fonts stay consistent across every screen.
* Added reusable GHL components to the global stylesheet: checkbox toggles (ghl-button), form inputs (ghl-input/ghl-select), and Select2 dropdowns.
* Dashboard: moved the tab bar styling out of inline attributes into dashboard.css and matched the active tab to the brand accent color.
* Settings: restyled the Roles/Permissions tag toggles ("Auto-apply tags", "Remove on change") as GHL checkboxes.
* Gravity Forms: the GHL field selector is now a searchable Select2 dropdown; settings inputs and buttons use the GHL component styles.
* Refactored admin column/profile assets into the shared asset manager so the global design tokens load on every screen.
* "View in GHL" links in user columns and profiles now respect the configured white-label brand.

= 1.4.34 =
* Added advanced bulk sync to GHL with filters: user role, sync status, and user meta key/value.
* Added advanced bulk import from GHL with filters: search query (name, email, phone), GHL tag filter, and import mode (all, new only, existing only).
* Added silent reconnect: "Reconnect Account" button now attempts a proxy token refresh before redirecting to GoHighLevel.
* Fixed OAuth proxy domain references from labgenz.com to synclyforgohighlevel.com.
* Fixed token refresh to send form-encoded data matching the proxy endpoint expectations.
* Updated admin menu references from "GHL CRM" to "Syncly".

= 1.4.32 =
* Fixed Gravity Forms note sometimes failing with "Contact not found" by using the in-memory contact cache instead of hitting GHL's eventually-consistent search API.

= 1.4.31 =
* Improved sync reliability with atomic OAuth token refresh locking, safe retries for transient API failures, request correlation, and shared token-refresh handling for connection tests.
* Hardened webhook handling with JSON validation, durable delivery deduplication, safer retry responses, and privacy-conscious webhook logging.
* Improved queue safety with atomic job claims, atomic rate-limit reservations, stale final-attempt recovery, dependency-wait retry protection, and temporary tag-fetch failure backoff.
* Fixed plugin activation and deactivation setup, including database initialization and scheduled cleanup registration.
* Strengthened OAuth callback binding, redacted sensitive logging data, and made file logging opt-in.
* Improved the webhook test action so it uses the current settings nonce and configured secret.
* Expanded automated regression coverage and CI validation for PHP 7.4 and PHP 8.1.

= 1.4.27 =
- Added Gravity Forms integration with a dedicated Per-form Syncly settings tab.
- Added Gravity Forms field mapping for standard and custom GoHighLevel contact fields, per-form contact tags, duplicate update behavior, automatic source naming, configurable queue delay, and spam-entry protection.
- Added optional Gravity Forms submission text to the GoHighLevel contact timeline as a queued note.
- Added recent Gravity Forms sync history with queue status and manual resend controls.
- Added Syncly Pro conditional submission rules for Gravity Forms, including field, operator, and value matching.
- Added Gravity Forms Automation to the centralized Upgrade to Pro feature list.
- Kept Gravity Forms synchronization asynchronous through the existing Action Scheduler queue.

= 1.4.26 =
- Redesigned the setup wizard with a cleaner layout, better-looking buttons, and a bigger celebration screen when setup finishes.
- Fixed a bug where the setup wizard could show a broken, overlapping "Back/Continue" bar on some steps.
- Fixed a bug where the setup wizard sometimes showed a "failed to save" message even though your settings were actually saved.
- Fixed a bug where a default tag entered during setup for new user registrations was not being saved.
- The setup wizard now clearly shows which integrations (WooCommerce, LearnDash, Custom Objects, Abandoned Cart Recovery, Webhooks, Login Sync) require the Pro upgrade, instead of hiding them.
- Removed a "Role-Based Tags" option from the setup wizard that didn't do anything on its own — that feature is configured from its own settings page instead.
- Added an optional "Help Improve Syncly" toggle in setup so you can choose to share anonymous usage data.
- Updated internal branding references so the Elementor and Gutenberg integration menus always show the current plugin name.
- Updated the secure connection service used for GoHighLevel login to our new domain.

= 1.4.25 =
- Updated plugin directory listing with new icon and banner assets.
- Improved readme.txt with clearer feature descriptions and SEO-optimised keyword coverage for WordPress.org search.
- Hardened release workflow to deploy directory assets separately from plugin files.

= 1.4.24 =
- Updated the plugin’s runtime URLs and OAuth proxy/upgrade links.
- Fixed remaining legacy branding references in the admin UI so the active plugin experience consistently points to the current Syncly site.
- Kept the production release flow aligned with the current WordPress.org release pipeline and external service disclosures.

= 1.4.21 =
- Hotfix: removed outdated OAuth scopes for Opportunities and Workflows from the GoHighLevel connection, keeping the app permissions aligned with the current integration requirements.

= 1.4.20 =
- Added a Tag Rules Impact panel to the user profile screen that shows which site automations apply to that user's current GoHighLevel tags.
- Added Contact Form 7, Elementor, and Gutenberg tag automations to the Tag Rules page so every free-tier tag rule is listed in one place.
- Added Rule Safety warnings that highlight tag configurations which can unintentionally bypass content restrictions.

= 1.4.19 =
- Added Tag Rules, a single page that shows all tag automations on the site in one place so admins can quickly review what happens when a contact has a given tag.

= 1.4.18 =
- Refreshed the Upgrade to Pro page to highlight the latest Syncly Pro automation features for roles, memberships, subscriptions, and content access.

= 1.4.17 =
- Added support for creating a GoHighLevel tag directly from the tag selector when the entered tag does not already exist in GoHighLevel.
- Added page-view outcome tagging controls so contacts can be tagged when access to a protected page is approved or denied.
- Added bidirectional tag-to-role sync so GoHighLevel tags can automatically assign matching WordPress roles.
- Added clearer Pro upsell messaging in Role Tags settings for approve/deny tagging automation.

= 1.4.15 =
- Fixed a sync loop issue where updates coming from GoHighLevel could sometimes trigger the same update again in WordPress.
- This now protects user creation, profile updates, user deletion, and tag updates so they do not bounce back and forth between systems.
- Fixed the Webhooks settings buttons so Copy URL, Copy Token, Regenerate Token, and Test Webhook all work reliably again.

= 1.4.14 =
* Added a GoHighLevel Tags filter to the WordPress Users table.
* Scoped the Users tag filter to the active GoHighLevel location only.
* Added searchable Select2 support for the Users tag filter without adding extra API requests.

= 1.4.13 =
* Fixed Field Mapping persistence so selected GHL field types are saved and restored correctly on reload.
* Fixed registration date mapping to DATE fields by including user_registered and normalizing values to Y-m-d before sync.
* Updated available custom field data types to match current GoHighLevel-supported values used by field creation and mapping.

= 1.4.12 =
* Added typed custom-field creation support (`DATE`, `NUMBER`, and `TEXT`) for shared field-mapping create flows used by Syncly Pro settings.
* Added deferred create-option resolution in settings save so Login Sync field selections can create missing GHL custom fields reliably on save.
* Improved user profile contact ID display resolution to prefer location-scoped contact meta and safely fall back to legacy contact keys.

= 1.4.10 =
* Queue processing cadence reduced from 10 seconds to 5 minutes to lower continuous background load on production sites.
* Added one-time queue schedule migration to remove legacy high-frequency sync schedule entries.
* Action Scheduler bootstrap hardened: Syncly now loads bundled Action Scheduler only when no provider is already available.
* Preserved standalone compatibility for sites without WooCommerce while reducing duplicate-loader risk.

= 1.4.7 =
* Security hardening: removed registration-time trust of request role input and now uses persisted user role data for role-tag sync.
* Security hardening: strengthened admin AJAX guards by enforcing capability checks alongside nonce validation.
* Security hardening: required admin capability for OAuth admin callback handling before processing query parameters.

= 1.4.6 =
* Removed legacy manual API-key connection pathways from runtime client flow and kept OAuth-only auth handling.
* Added generated minified asset ignore rules for local development while preserving CI-built production minification.
* Minor reviewer-compliance hardening and cleanup updates.

= 1.4.5 =
* WordPress.org review hardening: moved Global Tags and AI-assisted field suggestions ownership fully to Syncly Pro, with free-plugin notice/delegation only.
* Aligned reviewer-facing copy and changelog notes with current free/pro feature boundaries.
* Kept Sync Preview as Pro-only in free response paths and validated packaging guard checks.

= 1.4.3 =
* Addressed WordPress.org review feedback for public contact-link validation, unique transient prefixes, and restriction feature availability.
* Added GitHub Actions packaging checks and release ZIP generation for production submissions.

= 1.4.2 =
* Prepared production package for WordPress.org review.
* Updated packaged assets and asset versioning.
* Removed development-only files from the production build.

= 1.4.1 =
* Removed remaining locked/mockup screens (Forms add-on preview, Sync Logs detail preview) from the free plugin; replaced with plain upgrade notices.
* Moved Family Relationships and Login Sync settings tabs to the Pro plugin; the free plugin no longer ships any disabled preview of these features.
* Added a single "Upgrade to Pro" settings tab listing Pro-only features; it's automatically hidden once Pro is active and licensed.
* Removed remaining legacy branding identifiers from the free and Pro plugins.
* Replaced hardcoded plugin name strings with a single constant for consistent display text.
* Removed a non-system font reference from the admin UI in favor of WordPress's own font stack.

= 1.4.0 =
* Renamed internal namespace, hooks, and identifiers to Syncly throughout the codebase.
* Removed locked/disabled preview screens from the free plugin; Pro-only features are now referenced via a plain upgrade link instead of an in-app mockup.
* Moved inline scripts/styles to wp_enqueue_script/wp_enqueue_style.
* Bundled SweetAlert2 locally instead of loading it from a CDN.
* Updated Chart.js to the latest stable release.

= 1.3.17 =
* Updated plugin branding for the Syncly slug.
* Added WordPress.org external service disclosures.
* Updated payment and upgrade links to the Syncly home page.
* Improved review compliance for request sanitization and escaped filtered output.
