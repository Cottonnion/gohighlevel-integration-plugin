=== Syncly for GoHighLevel ===
Contributors: yahyadeved
Tags: gohighlevel, wpfusion, contact-sync, woocommerce, leadconnector
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.25
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The most complete WordPress to GoHighLevel integration. Two-way contact sync, field mapping, WooCommerce, LearnDash, BuddyBoss, webhooks, and automation — all in one plugin.

== Description ==

**Syncly is the most complete WordPress ↔ GoHighLevel CRM integration available.** Whether you're migrating from WP Fusion, looking for a GoHighLevel-native alternative, or starting fresh, Syncly gives you deeper sync, smarter automation, and tighter control — at a fraction of the cost.

= Why Syncly Outperforms Other WordPress CRM Plugins =

Unlike generic CRM connectors, Syncly is built exclusively for GoHighLevel (HighLevel / LeadConnector). That means every feature — field mapping, tag automation, webhook sync, membership gating, WooCommerce sync, LearnDash course tracking — is optimized specifically for the GHL API and data model.

* **True bi-directional sync** — push WordPress user data to GoHighLevel contacts AND pull GHL contact updates back into WordPress in real time
* **Visual field mapper** — map any WordPress or BuddyBoss XProfile field to any GoHighLevel custom field with one click
* **Role-based tag automation** — automatically assign or remove GoHighLevel tags when WordPress roles change
* **WooCommerce CRM sync** — sync customers, orders, and purchase history to GoHighLevel contacts
* **LearnDash course sync** — enroll, complete, and track courses as GoHighLevel contact activity
* **BuddyBoss / BuddyPress profile sync** — map extended profile fields directly to GHL custom fields
* **Membership content restrictions** — gate pages, posts, courses, and products by GoHighLevel tags
* **Webhook automation** — receive real-time updates from GoHighLevel and act on them instantly in WordPress
* **Embedded GoHighLevel forms** — drop GHL forms into any page or post with a shortcode or Gutenberg block
* **Action Scheduler queue** — reliable background processing with automatic retry and rate-limit handling (100 req/10 s, 200k/day)
* **OAuth2 only** — no API keys stored in the database; secure token exchange via the Syncly proxy

= Common Use Cases =

* Sync WordPress user registrations to GoHighLevel contacts automatically
* Tag GoHighLevel contacts when a WooCommerce order is placed or a LearnDash course is completed
* Restrict membership site content based on GoHighLevel tags assigned in your CRM pipelines
* Replace WP Fusion with a GoHighLevel-first integration that needs no third-party middleware
* Run marketing automation workflows in GoHighLevel triggered by WordPress events
* Keep WooCommerce customer records and GoHighLevel contacts in sync without manual exports

= Searched for any of these? Syncly is the answer. =

gohighlevel wordpress plugin, wordpress gohighlevel integration, wp to gohighlevel, gohighlevel contact sync, highlevel wordpress, leadconnector wordpress, gohighlevel woocommerce, wordpress crm sync, gohighlevel field mapping, gohighlevel membership, wp fusion alternative gohighlevel, gohighlevel webhooks wordpress, gohighlevel learndash, buddyboss gohighlevel

Syncly for GoHighLevel connects WordPress sites with GoHighLevel CRM. It helps site administrators synchronize WordPress users, WooCommerce customers and orders, BuddyBoss profile fields, LearnDash activity, tags, custom fields, embedded forms, and webhook events.

The plugin includes OAuth2 connection handling, automatic token refresh, visual field mapping, contact synchronization, role-based tagging, membership content restrictions, webhook processing, queue management with Action Scheduler, and sync logs.

This plugin is not affiliated with, endorsed by, or sponsored by GoHighLevel or HighLevel.

= External services =

This plugin connects to external services to provide CRM synchronization and OAuth authentication. These services are required for the plugin to connect WordPress data with a GoHighLevel account.

GoHighLevel and LeadConnector APIs: The plugin sends CRM-related data such as contact names, email addresses, phone numbers, WordPress user profile data, tag names, custom field values, WooCommerce customer/order data when enabled, BuddyBoss profile data when enabled, LearnDash activity when enabled, webhook payloads, and form identifiers. Data is sent when an administrator connects the plugin, runs sync actions, saves mapping/settings that require metadata lookup, users register or update profiles, connected ecommerce/community/LMS events occur, webhooks are received, or embedded GoHighLevel forms are displayed.

GoHighLevel service links: https://www.gohighlevel.com/terms-of-service and https://www.gohighlevel.com/privacy-policy

Syncly OAuth proxy: The plugin uses a Syncly proxy endpoint during OAuth token exchange, token refresh, and reconnect flows so OAuth client credentials are not distributed inside the plugin. The proxy receives OAuth authorization codes, refresh tokens, location/account identifiers, and related token request metadata only when an administrator connects or refreshes the GoHighLevel connection.

Syncly service links: http://synclyforgohighlevel.com/terms-of-service/ and http://synclyforgohighlevel.com/privacy-policy/

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/syncly` or install the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate Syncly for GoHighLevel from the Plugins screen.
3. Open the Syncly admin menu.
4. Connect your GoHighLevel account using OAuth.
5. Configure field mappings, sync settings, forms, webhooks, and optional integrations as needed.

== Frequently Asked Questions ==

= Do I need a GoHighLevel account? =

Yes. Syncly connects WordPress to a GoHighLevel account and requires authorization through GoHighLevel OAuth.

= Is this an official GoHighLevel plugin? =

No. Syncly for GoHighLevel is an independent plugin and is not affiliated with, endorsed by, or sponsored by GoHighLevel or HighLevel.

= Does the plugin include Action Scheduler? =

Yes. Action Scheduler is included through Composer dependencies for background queue processing.

== Changelog ==

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
* Updated payment and upgrade links to the HighLevelSync home page.
* Improved review compliance for request sanitization and escaped filtered output.
