=== Syncly for GoHighLevel ===
Contributors: thelabgenz, yahyadeved
Tags: gohighlevel, crm, woocommerce, buddyboss, learndash, webhooks
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.17
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Syncly connects WordPress with GoHighLevel CRM for contact sync, field mapping, webhooks, forms, and automation workflows.

== Description ==

Syncly for GoHighLevel connects WordPress sites with GoHighLevel CRM. It helps site administrators synchronize WordPress users, WooCommerce customers and orders, BuddyBoss profile fields, LearnDash activity, tags, custom fields, embedded forms, and webhook events.

The plugin includes OAuth2 connection handling, automatic token refresh, visual field mapping, contact synchronization, role-based tagging, membership content restrictions, webhook processing, queue management with Action Scheduler, and sync logs.

This plugin is not affiliated with, endorsed by, or sponsored by GoHighLevel or HighLevel.

= External services =

This plugin connects to external services to provide CRM synchronization and OAuth authentication. These services are required for the plugin to connect WordPress data with a GoHighLevel account.

GoHighLevel and LeadConnector APIs: The plugin sends CRM-related data such as contact names, email addresses, phone numbers, WordPress user profile data, tag names, custom field values, WooCommerce customer/order data when enabled, BuddyBoss profile data when enabled, LearnDash activity when enabled, webhook payloads, and form identifiers. Data is sent when an administrator connects the plugin, runs sync actions, saves mapping/settings that require metadata lookup, users register or update profiles, connected ecommerce/community/LMS events occur, webhooks are received, or embedded GoHighLevel forms are displayed.

GoHighLevel service links: https://www.gohighlevel.com/terms-of-service and https://www.gohighlevel.com/privacy-policy
LeadConnector service links: https://www.leadconnectorhq.com/terms-of-service and https://www.leadconnectorhq.com/privacy-policy

LabGenz OAuth proxy: The plugin uses a LabGenz proxy endpoint during OAuth token exchange, token refresh, and reconnect flows so OAuth client credentials are not distributed inside the plugin. The proxy receives OAuth authorization codes, refresh tokens, location/account identifiers, and related token request metadata only when an administrator connects or refreshes the GoHighLevel connection.

LabGenz service links: https://labgenz.com/terms/ and https://labgenz.com/privacy-policy/

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

= 1.3.17 =
* Updated plugin branding for the Syncly slug.
* Added WordPress.org external service disclosures.
* Updated payment and upgrade links to the HighLevelSync home page.
* Improved review compliance for request sanitization and escaped filtered output.
