# GoHighLevel CRM Integration - Copilot Instructions

## Project Purpose
This is a WordPress plugin that integrates GoHighLevel CRM with WordPress, WooCommerce, BuddyBoss, and LearnDash. It provides OAuth2 authentication, bi-directional contact synchronization, role-based tagging, membership restrictions, webhook automation, custom objects support, and comprehensive queue management for seamless CRM integration.

## Tech Stack

### Core Technologies
- **PHP 7.4+** (PHP 8.0+ recommended)
- **WordPress 5.8+** (6.0+ recommended)
- **Composer** for dependency management
- **PSR-4 Autoloading** for modern PHP class loading

### Optional Integrations
- **WooCommerce 5.0+** - eCommerce features
- **BuddyBoss/BuddyPress** - community features
- **LearnDash 3.0+** - LMS features
- **Action Scheduler** - background processing (included via Composer)

### Frontend
- JavaScript (vanilla JS)
- SweetAlert2 for notifications
- Select2 for enhanced dropdowns
- CSS for styling

### External Services
- **GoHighLevel API** - CRM integration via OAuth2

## Coding Conventions

### PHP Standards
- Follow **WordPress Coding Standards** (WPCS)
- Use **PSR-4 autoloading** for all classes
- All classes must use proper **namespaces** under `GHL_CRM\`
- Use **strict types** declaration: `declare(strict_types=1);`
- Every PHP file must start with `defined('ABSPATH') || exit;` for security
- Use **short array syntax**: `[]` instead of `array()`
- Prefer **modern PHP features** (type hints, return types, etc.)

### Naming Conventions
- **Classes**: PascalCase (e.g., `SettingsManager`, `OAuthClient`)
- **Functions**: snake_case (WordPress convention)
- **Constants**: UPPER_SNAKE_CASE with `GHL_CRM_` prefix
- **Variables**: snake_case
- **Global functions**: Prefix with `ghl_crm_`

### Architecture
- **Domain-Driven Design** with clear separation of concerns
- **Single Responsibility Principle** - each class has one clear purpose
- Organize code under `/src` in domain folders:
  - `Core/` - plugin loader, service container, settings
  - `Admin/` - admin pages, settings UI
  - `API/` - REST endpoints, webhooks, GoHighLevel API client
  - `Integrations/` - external systems (WooCommerce, BuddyBoss, LearnDash, GHL)
  - `Sync/` - synchronization services, queues, logs
  - `Database/` - repositories, migrations, models
  - `Utilities/` - helpers, validators, caching wrappers
  - `Assets/` - JS/CSS loaded via enqueue hooks

### Security Requirements (Critical)
- **Always sanitize** user inputs using WordPress functions
- **Always escape** outputs using `esc_html()`, `esc_attr()`, `esc_url()`, etc.
- **Always verify nonces** for all AJAX requests and form submissions
- **Always check capabilities** using `current_user_can('manage_options')` or appropriate capability
- Use **prepared statements** for all database queries
- Store sensitive data (OAuth tokens) securely using WordPress options API
- Validate all data from external APIs before use
- Never trust user input or external data

### WordPress Multisite Support
- Use `is_multisite()` checks for multisite-specific functionality
- Use site-specific options: `get_blog_option()`, `update_blog_option()`
- Network-wide settings should use `wp_sitemeta` if relevant
- Sync jobs must respect current site context (use `switch_to_blog()` when needed)
- Avoid global state that breaks site isolation
- Test all features in both single-site and multisite environments

### Comments and Documentation
- Use **PHPDoc blocks** for all classes, methods, and functions
- Include `@param`, `@return`, and `@throws` annotations
- Inline comments only when logic is complex or non-obvious
- Keep comments concise and up-to-date with code changes

## Project Structure

```
gohighlevel-integration-plugin/
├── .github/
│   ├── instructions/           # Path-based Copilot instructions
│   └── copilot-instructions.md # This file
├── src/
│   ├── Core/                   # Core functionality
│   ├── Admin/                  # Admin interface
│   ├── API/                    # API integration
│   ├── Integrations/           # Third-party integrations
│   ├── Sync/                   # Synchronization logic
│   ├── Database/               # Data layer
│   └── Utilities/              # Helper functions
├── templates/                  # PHP template files
├── assets/                     # JavaScript and CSS
├── vendor/                     # Composer dependencies (not committed)
├── tests/                      # Unit tests
├── composer.json               # PHP dependencies
├── phpcs.xml                   # PHP CodeSniffer configuration
├── phpunit.xml.dist           # PHPUnit configuration
└── gohighlevel-crm-integration.php  # Main plugin file
```

## Build and Testing

### Install Dependencies
```bash
composer install
```

### Code Quality
```bash
# Check code standards (MUST pass before committing)
composer check
# Or use the wrapper script:
./check

# Auto-fix code standards where possible
composer fix
# Or use the wrapper script:
./fix
```

### Testing
```bash
# Run PHPUnit tests
composer test
```

### Before Committing
1. Run `composer check` to ensure code meets WordPress standards
2. Fix any issues reported by PHPCS
3. Run tests if available
4. Verify changes don't break existing functionality

## Key Features to Preserve

### OAuth2 Authentication
- One-click secure connection to GoHighLevel
- Automatic token refresh with fallback reconnection
- Multi-location support

### Field Mapping
- Visual field mapper with duplicate prevention
- Bi-directional sync (→ To GHL, ← From GHL, ↔ Both Ways)
- Support for BuddyBoss XProfile fields
- Dynamic field detection

### Role-Based Tagging
- Automatic tag assignment/removal based on WordPress roles
- Global tags for all synced users
- Tag prefixes for organization
- Bulk operations support

### Membership & Access Control
- Tag-based content restrictions
- Flexible access rules (ANY, ALL, NONE)
- Custom redirects for unauthorized users
- Post type support (pages, posts, products, courses)

### Queue Management
- Action Scheduler for background processing
- Priority queue with automatic retry
- Rate limiting (100/10s burst, 200k/day)
- Detailed logging

## Important Conventions

### Error Handling
- Use WordPress error handling: `WP_Error` class
- Log errors using `error_log()` for debugging
- Show user-friendly error messages in admin interface
- Never expose sensitive information in error messages

### Performance
- Use WordPress transients for caching API responses
- Batch operations when possible to reduce API calls
- Use Action Scheduler for background tasks
- Optimize database queries (avoid N+1 problems)

### Compatibility
- Always maintain backward compatibility with previous versions
- Test with minimum required versions (WP 5.8, PHP 7.4)
- Handle missing optional dependencies gracefully
- Never assume WooCommerce, BuddyBoss, or LearnDash are active

### GoHighLevel API
- Use OAuth2 authentication (NOT API keys)
- Handle rate limits gracefully (100 requests per 10 seconds burst, 200k per day)
- Implement exponential backoff for failed requests
- Always validate API responses before using data
- Update existing contacts instead of creating duplicates (match by email)

## WordPress.org Requirements
- Must be GPL v2 or later licensed
- No obfuscated code
- No trialware or crippled functionality
- Clearly disclose external service connections
- No external tracking without explicit opt-in
- Respect WordPress trademark guidelines
- Follow WordPress plugin header standards

## Special Notes
- This plugin is **multisite-ready** with per-site isolation
- All settings are saved per-site in multisite environments
- OAuth connections are per-site (each site can connect to different GHL locations)
- The plugin uses Action Scheduler for reliable background processing
- Webhooks provide real-time bi-directional sync with GoHighLevel
- Custom objects support allows syncing forms, surveys, and other GHL data types

## Development Workflow
1. Make minimal, focused changes
2. Run `composer check` before committing
3. Test in both single-site and multisite environments
4. Update documentation if changing user-facing features
5. Add/update tests for new functionality
6. Verify security best practices are followed

## Contact & Support
- Developer: Yahya Eddaqqaq (yahyadard@gmail.com)
- GitHub: @Cottonnion
- Website: https://labgenz.com/