Here’s the updated version — includes **domain-driven architecture** instructions and **WordPress multisite compliance**.

---

```
applyTo: '**'
ask me about logic before applying it, cause in some cases the logic already exists but you missed it
# 🎯 Plugin Spec & Requirements (for Claude or dev AI)

## 1. WordPress.org Publishing Requirements  
- Must be licensed under **GPL v2 or later** (or GPL-compatible).  
- Code must be fully open, human-readable (no obfuscation).  
- No trialware or crippled functionality after installation.  
- Plugin may connect to external services (like GoHighLevel) only if clearly disclosed.  
- No external data tracking without explicit opt-in.  
- No forced front-end links, ads, or credits.  
- Must respect trademarks and naming rules.  
- Must include valid plugin header and `readme.txt` following WP standards.  
- Tested for proper sanitization, escaping, and nonce verification.  
- SVN submission once approved — no spammy updates.

## 2. High-Level Goals  
- Integrate **WordPress + WooCommerce + BuddyBoss + LearnDash + WP Users** with **GoHighLevel (GHL)**.  
- Support **two-way sync** (WordPress ↔ GHL) for users, orders, courses, groups, tags, etc.  
- Allow **BuddyBoss group creation** from GHL companies.  
- Provide field mapping, logs, retry logic, and modular sync management.  
- Fully compatible with **WordPress Multisite** — isolated site data, proper network options, and per-site settings.

## 3. Architecture & Code Guidelines  
- Use **OOP**, **namespaces**, and **PSR-4 autoloading**.  
- Each class has one clear responsibility.  
- Security: sanitize, escape, verify nonces, capability checks.  
- Include `defined('ABSPATH') || exit;` in all PHP files.  
- Optimize performance with object caching (Redis/transients).  
- Avoid redundant queries or logic duplication.  
- Consistent naming and docblocks.  
- Handle exceptions and log errors gracefully.

## 4. Domain-Driven Architecture  
Organize under `/src` using clear domains:  
```

src/
├── Core/           → plugin loader, service container, settings
├── Admin/          → admin pages, settings UI
├── API/            → REST endpoints, webhooks
├── Integrations/   → external systems (WooCommerce, BuddyBoss, LearnDash, GHL)
├── Sync/           → synchronization services, queues, logs
├── Database/       → repositories, migrations, models
├── Utilities/      → helpers, validators, caching wrappers
└── Assets/         → JS/CSS loaded via enqueue hooks

```
- Each domain manages its own logic, dependencies, and hooks.  
- Shared utilities live in `Core` or `Utilities`.  
- Maintain **strict separation of concerns** between domains.  
- Integration modules must be toggleable (enable/disable from admin).

## 5. Multisite Compatibility  
- Use `is_multisite()` checks and site-specific options (`get_blog_option`, etc.).  
- Network-wide settings should store in `wp_sitemeta` if relevant.  
- Sync jobs must respect current site context (`switch_to_blog()` where needed).  
- Avoid global state that breaks isolation.  
- Admin UI should behave properly for both single and multisite installations.

## 6. GHL API Integration  
- Use secure API key or OAuth2 authentication.  
- Endpoints: contacts, tags, workflows, companies, webhooks.  
- Handle rate limits, retries, and failures.  
- Map fields dynamically from UI.  
- Log sync events, show errors in admin.  
- Allow manual resync and background jobs (WP-Cron / async).

## 7. UI / UX Requirements  
- Settings page for connecting GHL, managing integrations, viewing logs.  
- Field mapping screen (WP ↔ GHL).  
- Sync logs viewer with status, last sync time, errors.  
- Manual sync controls per module.  
- Clean, minimal design following WP UI standards.

## 8. Testing, Security & Quality  
- Escape and sanitize all inputs/outputs.  
- Nonce verification for all admin actions.  
- Capability checks (`manage_options`, etc.).  
- Use prepared statements for DB queries.  
- Validate remote data before inserting/updating.  
- No fatal errors on activation/deactivation.  
- Fully functional in multisite environments.

## 9. Release, Docs & Privacy  
- Semantic versioning (major.minor.patch).  
- Update changelog in `readme.txt`.  
- Include screenshots, setup guide, FAQs.  
- Document all data sent to/from GHL and require explicit user consent.  
- Allow full opt-out and data deletion.  
- Validate code via WordPress coding standards before release.
