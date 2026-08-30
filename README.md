=== Syncly for GoHighLevel ===
Contributors: cottonnion
Tags: gohighlevel, crm, woocommerce, buddyboss, learndash, membership, webhooks
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.39
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WordPress users, WooCommerce orders, BuddyBoss profiles, LearnDash activity, and form submissions to GoHighLevel CRM via OAuth2 — with tag-based membership restrictions, webhooks, and background queue processing.

== Description ==

# Syncly for GoHighLevel

A WordPress plugin that connects your site to GoHighLevel CRM. Sync users, orders, courses, community profiles, and form submissions automatically — and restrict content based on GoHighLevel contact tags.

![Version](https://img.shields.io/badge/version-1.4.39-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-5.8%2B-brightgreen.svg)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-red.svg)
![Multisite](https://img.shields.io/badge/multisite-ready-green.svg)

## What it does

- **Sync WordPress users** to GoHighLevel contacts on registration, profile update, or login
- **Sync WooCommerce orders** and customer data automatically
- **Sync BuddyBoss profiles** and groups to GoHighLevel custom objects
- **Sync LearnDash course enrollment**, progress, and quiz results
- **Sync form submissions** from Gravity Forms and Contact Form 7
- **Tag-based content restrictions** — gate pages, posts, courses, or custom post types behind GoHighLevel tags
- **Role-based tagging** — auto-assign or remove tags when users gain/lose WordPress roles
- **Bidirectional webhooks** — receive GoHighLevel contact changes in real time
- **Background queue** — reliable bulk operations via Action Scheduler with rate limiting
- **Elementor integration** — tag-driven page conditions and GHL form widget

## OAuth2 connection

Syncly authenticates with GoHighLevel through an OAuth2 proxy hosted at **synclyforgohighlevel.com**. The client secret never touches your WordPress site or the distributed plugin code.

```
Your WordPress site  →  synclyforgohighlevel.com proxy  →  GoHighLevel API
```

The plugin holds only a **client ID** and a **refresh token**. Token exchange, refresh, and reconnect operations are routed through the proxy, which holds the client secret server-side. This keeps the plugin compatible with WordPress.org distribution guidelines.

**How it works:**
1. You click "Connect with GoHighLevel" in wp-admin
2. You authorize the app in GoHighLevel (one-time)
3. The proxy exchanges the authorization code for access + refresh tokens
4. The plugin stores tokens locally in `wp_options` (per-site in multisite)
5. Tokens refresh automatically; if refresh fails, the plugin attempts reconnection through the proxy

No API keys or secrets are stored in plugin files or `wp-config.php`.

## Requirements

- WordPress 5.8+ (6.0+ recommended)
- PHP 7.4+ (8.0+ recommended)
- HTTPS (required for OAuth2)
- GoHighLevel account

**Optional:**
- WooCommerce 5.0+ — for order/customer sync
- BuddyBoss/BuddyPress — for groups and XProfile field sync
- LearnDash 3.0+ — for course enrollment and progress sync
- Gravity Forms — for form submission sync
- Contact Form 7 — for form submission sync
- Elementor — for tag-driven page conditions and form widget
- Action Scheduler (bundled) — for background queue processing

## Installation

### From WordPress admin

1. Download the plugin ZIP from the [releases page](https://github.com/Cottonnion/gohighlevel-integration-plugin/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and click **Install Now**
4. Activate

### Manual

Copy the plugin folder to `/wp-content/plugins/syncly` and activate from the Plugins menu.

## Configuration

### 1. Connect to GoHighLevel

1. Go to **Syncly → Settings**
2. Click **"Connect with GoHighLevel"**
3. Authorize the app in your GoHighLevel account
4. You'll be redirected back — connection status shows **"Connected"**

### 2. Field mapping

Go to **Syncly → Settings → Field Mapping**. Map WordPress fields to GoHighLevel custom fields. Choose sync direction per field:

- **→ To GoHighLevel Only**
- **← From GoHighLevel Only**
- **↔ Both Ways**

Mapped fields show a green checkmark. Each GHL field can only be mapped once — the mapper prevents duplicates.

### 3. Enable user sync

Go to **Syncly → Settings **. Toggle sync on, select which events trigger sync (registration, profile update, login), and configure default tags and deletion behavior.

### 4. Role-based tags

Go to **Syncly → Settings → Role Tags**. For each WordPress role, configure which tags to auto-apply and auto-remove. Supports global tags, tag prefixes, WooCommerce customer tags, and bulk operations.

### 5. Content restrictions

Edit any page, post, product, or course. Use the **"Syncly Restrictions"** meta box to set tag-based access rules (ANY / ALL / NONE) with optional redirect URLs. Configure bypass tags and archive protection in **Settings → Restrictions**.

### 6. Webhooks

Go to **Syncly → Settings → Webhooks**. The plugin registers contact.create, contact.update, and contact.delete webhooks automatically. Incoming webhooks are verified by signature.

### 7. Form integrations

Go to **Syncly → Settings → Integrations** to enable:

- **Gravity Forms** — map form fields to GHL contact fields, with conditional submission rules and delivery controls on Pro
- **Contact Form 7** — per-form sync with tag assignment
- **Elementor Forms** — drag a connected form into any Elementor layout

### 8. Custom objects

Go to **Syncly → Custom Objects** to map and sync GoHighLevel custom objects (forms, surveys, BuddyBoss groups, etc.) to WordPress.

## Usage

| Feature | Location |
|---|---|
| Dashboard & queue status | **Syncly → Dashboard** |
| Connection & general settings | **Syncly → Settings** |
| Field mapping | **Syncly → Settings → Field Mapping** |
| Role-based tags | **Syncly → Settings → Role Tags** |
| Form integrations (Gravity Forms, CF7) | **Syncly → Settings → Integrations** |
| Content restrictions | Edit any post/page → sidebar meta box |
| Sync logs | **Syncly → Sync Logs** |
| Queue controls | **Syncly → Dashboard** (pause, resume, retry failed) |
| User login links | **Users → All Users → Edit user** |
| System health | **Syncly → Settings → Advanced → Run Health Check** |

## Troubleshooting

**OAuth connection fails** — Verify your site uses HTTPS. Disconnect and reconnect. Clear browser cache.

**Data not syncing** — Check Sync Logs for errors. Verify OAuth is connected (green checkmark). Confirm User Sync is enabled and field mappings are saved.

**Duplicate contacts** — The plugin prevents duplicates by email. Existing contacts are updated. If you see duplicates, they likely have different email addresses.

**Rate limit errors** — The plugin throttles automatically (100/10s burst, 200k/day). Large bulk operations are queued. Check queue status in the dashboard.

**Webhooks not receiving** — Confirm webhooks show "Active" in Settings → Webhooks. Enable Debug Mode. Check that your server allows incoming HTTPS requests.

**Tags not applying** — Verify User Sync is on, Auto-Apply is enabled for the role, and the user has been synced to GHL (has a `_ghl_contact_id` meta). Use bulk operations to retroactively tag users.

**Multisite** — Each site has its own OAuth connection. Connect each site separately.

## Security

- OAuth client secret is never stored in the plugin — it lives exclusively on the proxy server
- All AJAX requests are verified with WordPress nonces
- Capability checks (`manage_options`) on every settings operation
- All user input is sanitized; all output is escaped
- Tokens are stored in `wp_options` via the WordPress Options API
- The proxy enforces rate limiting on token refresh operations

### Reporting vulnerabilities

Email yahyadard@gmail.com. Do not open public GitHub issues for security reports.

## Architecture

```
syncly/
├── src/
│   ├── Core/                  # Plugin bootstrap, menus, assets, AJAX
│   ├── API/
│   │   ├── Client/            # HTTP client (talks to proxy)
│   │   ├── OAuth/             # Token storage, refresh, reconnect
│   │   └── Resources/         # GoHighLevel API resource classes
│   ├── Integrations/
│   │   ├── Users/             # WordPress user sync
│   │   ├── WooCommerce/       # Order & customer sync
│   │   └── BuddyBoss/         # XProfile field sync
│   └── Utilities/             # Helpers, logging
├── templates/admin/           # Admin page templates
├── assets/admin/              # CSS & JS
└── syncly.php                 # Main plugin file
```

## Future plans

### AWS Lambda token management

The current OAuth proxy at synclyforgohighlevel.com runs on a WordPress site. The long-term plan is to move token exchange, refresh, and reconnect operations to **AWS Lambda functions**:

- **No server to maintain** — Lambda scales automatically and has no uptime cost at low volume
- **Client secret in AWS Secrets Manager** — not in WordPress `wp_options`
- **Per-site rate limiting** via DynamoDB or Cloudflare KV
- **CloudWatch logging** — full observability without plugin-level file logging

This eliminates the proxy WordPress site as a dependency and removes a single point of failure.

### Optional Syncly connectivity

In addition to the self-hosted proxy, a future version may offer an optional **Syncly-hosted relay** as a fallback:

- If the user's own proxy or Lambda is unreachable, the plugin could optionally route through `synclyforgohighlevel.com`
- This keeps end users unaffected even if the primary token management infrastructure has issues
- Opt-in only — users who run their own proxy or Lambda are not affected

Both features are planned but not yet scheduled.

## Development

No Composer install needed — the plugin bundles all dependencies.

```bash
# Clone the repo
git clone https://github.com/Cottonnion/gohighlevel-integration-plugin.git

# Symlink into your WordPress install
ln -s /path/to/repo /path/to/wordpress/wp-content/plugins/syncly
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full release history.

## License

GPL v2 or later. See [LICENSE](LICENSE).

## Credits

**Yahya Eddaqqaq** — [yahyawordpress.com](https://yahyawordpress.com/) · [@Cottonnion](https://github.com/Cottonnion)

---

[Website](http://synclyforgohighlevel.com) · [GitHub](https://github.com/Cottonnion/gohighlevel-integration-plugin)
