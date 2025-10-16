# GoHighLevel CRM Integration

A powerful WordPress plugin that seamlessly integrates GoHighLevel CRM with WordPress, WooCommerce, BuddyBoss, and LearnDash, providing true two-way synchronization and advanced automation capabilities.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-6.0%2B-brightgreen.svg)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-red.svg)

## 🎯 Overview

This plugin bridges the gap between your WordPress ecosystem and GoHighLevel CRM, offering features that competitive plugins lack—including **BuddyBoss integration**, **full multisite support**, and **advanced field mapping**.

### What Makes This Plugin Different?

- ✅ **BuddyBoss Integration** - The ONLY plugin that syncs BuddyBoss groups and members with GHL
- ✅ **True Two-Way Sync** - Data flows both directions automatically
- ✅ **WordPress Multisite Ready** - Perfect for enterprise and network installations
- ✅ **LearnDash Support** - Sync course enrollments and progress
- ✅ **WooCommerce Deep Integration** - Orders, customers, and product data
- ✅ **Advanced Field Mapping** - Complete control over data synchronization
- ✅ **Action Scheduler** - Reliable background processing, not WP-Cron
- ✅ **Comprehensive Logging** - Track every sync operation
- ✅ **Modern UI/UX** - Clean, minimal dashboard inspired by Google Analytics & Stripe

## 🚀 Features

### Core Integrations

#### WordPress Users
- Automatic user creation in GHL when users register
- Sync user meta data, roles, and custom fields
- Tag-based segmentation
- Real-time updates

#### WooCommerce
- Customer sync on order completion
- Order data tracking in GHL
- Product purchase history
- Abandoned cart recovery
- Custom order meta fields

#### BuddyBoss
- **Group synchronization with GHL companies**
- Member to contact mapping
- Activity tracking
- Group roles and permissions
- Community engagement metrics

#### LearnDash
- Course enrollment synchronization
- Progress tracking
- Quiz and assignment completion
- Certificate issuance tracking
- Student performance metrics

### Advanced Features

- **Webhooks Support** - Real-time bidirectional updates
- **Retry Logic** - Failed syncs automatically retry
- **Queue Management** - Built on WooCommerce Action Scheduler
- **Error Logging** - Detailed error tracking and reporting
- **Manual Sync Controls** - Trigger syncs on-demand
- **Bulk Operations** - Process large datasets efficiently
- **Custom Field Mapping** - Map any WordPress field to any GHL field
- **Conditional Sync** - Rules-based synchronization
- **API Rate Limiting** - Smart throttling to respect API limits

## 📋 Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- GoHighLevel account with API access
- WooCommerce 5.0+ (optional, for eCommerce features)
- BuddyBoss 1.0+ (optional, for community features)
- LearnDash 3.0+ (optional, for LMS features)

## 🔧 Installation

### From WordPress Admin

1. Download the plugin ZIP file
2. Navigate to **Plugins → Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Activate the plugin

### Manual Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress

### Via Composer

```bash
composer require your-vendor/gohighlevel-crm-integration
```

## ⚙️ Configuration

### 1. Get Your GoHighLevel API Credentials

1. Log into your GoHighLevel account
2. Navigate to **Settings → Integrations → Private Integrations**
3. Create a new Private Integration Token
4. Copy your **API Token** and **Location ID**

### 2. Configure the Plugin

1. In WordPress admin, go to **GHL CRM → Settings**
2. Enter your **API Token**
3. Enter your **Location ID**
4. Click **Save Settings**
5. Click **Test Connection** to verify

### 3. Configure Field Mapping

1. Navigate to **GHL CRM → Field Mapping**
2. Map WordPress fields to GoHighLevel fields
3. Set up custom field mappings
4. Save your configuration

### 4. Enable Sync Modules

Choose which integrations to enable:
- WordPress Users
- WooCommerce Orders
- BuddyBoss Groups
- LearnDash Courses

## 📖 Usage

### Basic Sync Operations

#### Automatic Sync
The plugin automatically syncs data based on WordPress events:
- User registration
- Order completion
- Course enrollment
- Group membership changes

#### Manual Sync
Force a sync from the admin panel:
1. Go to **GHL CRM → Sync Logs**
2. Click **Run Manual Sync**
3. Select the data type to sync
4. Monitor progress in real-time

### Advanced Configuration

#### Webhooks Setup

Enable real-time updates from GoHighLevel:

1. Go to GHL Settings → Webhooks
2. Add the endpoint above
3. Select events to monitor
4. Save configuration

#### Custom Field Mapping

```php
// Example: Map custom user meta to GHL custom field
add_filter('ghl_crm_user_field_map', function($fields, $user_id) {
    $fields['custom_field_key'] = get_user_meta($user_id, 'your_meta_key', true);
    return $fields;
}, 10, 2);
```

#### Conditional Sync

```php
// Example: Only sync users with specific role
add_filter('ghl_crm_should_sync_user', function($should_sync, $user_id) {
    $user = get_user_by('id', $user_id);
    return in_array('customer', $user->roles);
}, 10, 2);
```

## 🎨 Screenshots

1. **Settings Page** - Clean, modern interface for API configuration
2. **Field Mapping** - Visual field mapper with drag-and-drop
3. **Sync Logs** - Comprehensive sync history and error tracking
4. **Dashboard** - Quick stats and sync status overview

## 🔍 Troubleshooting

### Connection Issues

**Problem:** "API Connection Failed"

**Solutions:**
1. Verify your API token is correct
2. Check your Location ID
3. Ensure your GoHighLevel account is active
4. Check server firewall settings

### Sync Not Working

**Problem:** Data not syncing to GoHighLevel

**Solutions:**
1. Check **GHL CRM → Sync Logs** for errors
2. Verify field mappings are correct
3. Ensure sync is enabled for that module
4. Check Action Scheduler queue: **Tools → Scheduled Actions**

### Rate Limit Errors

**Problem:** "API rate limit exceeded"

**Solutions:**
1. The plugin automatically throttles requests
2. Reduce sync frequency in settings
3. Enable queue batching for large datasets

### Multisite Issues

**Problem:** Settings not saving on specific sites

**Solutions:**
1. Verify multisite is properly configured
2. Check site-specific settings vs network settings
3. Ensure proper user capabilities

## 🛠️ Development

### Local Development Setup

```bash
# Clone the repository
git clone https://github.com/Cottonnion/gohighlevel-integration-plugin.git

# Install dependencies
composer install

# Set up local WordPress environment
# Configure wp-config.php with database credentials
```

### Running Tests

```bash
# Run PHPUnit tests
composer test

# Run code standards check
composer phpcs

# Fix code standards
composer phpcbf
```

### Project Structure

```
crm-integration/
├── src/
│   ├── Core/           # Core functionality
│   ├── Admin/          # Admin UI
│   ├── API/            # REST API & Webhooks
│   ├── Integrations/   # Platform integrations
│   ├── Sync/           # Sync services
│   ├── Database/       # Data layer
│   └── Utilities/      # Helper functions
├── assets/
│   ├── admin/          # Admin CSS/JS
│   └── public/         # Frontend assets
├── templates/          # PHP templates
├── vendor/             # Composer dependencies
└── tests/              # Unit tests
```

### Coding Standards

This plugin follows:
- WordPress Coding Standards
- PSR-4 Autoloading
- Domain-Driven Design principles
- SOLID principles

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Contribution Guidelines

- Follow WordPress coding standards
- Write PHPUnit tests for new features
- Update documentation as needed
- Ensure backward compatibility
- Add inline documentation for complex logic

## 📝 Changelog

### Version 1.0.0 - 2025-10-16

**Initial Release**

- ✨ GoHighLevel API integration
- ✨ WordPress user synchronization
- ✨ WooCommerce integration
- ✨ BuddyBoss integration (exclusive feature)
- ✨ LearnDash integration
- ✨ Advanced field mapping
- ✨ Comprehensive logging
- ✨ WordPress Multisite support
- ✨ Action Scheduler integration
- ✨ Modern, minimal UI design
- ✨ Webhook support
- ✨ Two-way sync capabilities

## 🔐 Security

### Reporting Security Issues

If you discover a security vulnerability, please email security@yourcompany.com. Do not create public issues for security vulnerabilities.

### Security Features

- ✅ Nonce verification on all forms
- ✅ Capability checks for admin functions
- ✅ Input sanitization
- ✅ Output escaping
- ✅ Prepared SQL statements
- ✅ Secure API token storage
- ✅ Rate limiting protection

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🙏 Credits

### Built With

- [WordPress](https://wordpress.org/) - CMS Platform
- [Action Scheduler](https://actionscheduler.org/) - Background Processing
- [GoHighLevel API](https://highlevel.stoplight.io/) - CRM Integration
- [Composer](https://getcomposer.org/) - Dependency Management

### Inspired By

- Google Analytics Dashboard Design
- Stripe Dashboard UX
- Modern SaaS Application Patterns

### Professional Support

Need help with setup or custom development?
- Email: support@yourcompany.com
- Website: https://yourcompany.com/support
- Priority Support: Available for Pro users

## 🌟 Roadmap

### Upcoming Features

- [ ] Gravity Forms integration
- [ ] Elementor Forms integration
- [ ] Contact Form 7 integration
- [ ] Advanced automation workflows
- [ ] Custom dashboard widgets
- [ ] Mobile app integration
- [ ] Advanced reporting & analytics
- [ ] Multi-location support
- [ ] A/B testing capabilities
- [ ] GDPR compliance tools

### Version 1.1.0 (Q1 2026)
- Enhanced field mapping UI
- Improved performance optimizations
- Additional platform integrations
- Advanced filtering options

### Version 2.0.0 (Q2 2026)
- Complete UI redesign
- Advanced automation engine
- Machine learning predictions
- Enhanced multisite features

## 💼 Enterprise Features

Contact us for enterprise licensing:
- Dedicated support
- Custom integrations
- White-label options
- Priority feature requests
- SLA guarantees

---

**Made with ❤️ for the WordPress and GoHighLevel communities**

[Website](https://labgenz.com)  | [GitHub](https://github.com/Cottonnion/gohighlevel-integration-plugin)
