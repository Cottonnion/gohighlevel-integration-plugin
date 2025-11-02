=== GoHighLevel CRM Integration ===
Contributors: cottonnion
Tags: gohighlevel, crm, woocommerce, buddyboss, learndash
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful WordPress plugin that seamlessly integrates GoHighLevel CRM with WordPress, WooCommerce, BuddyBoss, and LearnDash.

== Description ==

# GoHighLevel CRM Integration

A powerful WordPress plugin that seamlessly integrates GoHighLevel CRM with WordPress, WooCommerce, BuddyBoss, and LearnDash, providing OAuth2 authentication, intelligent field mapping, and automatic contact synchronization.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-6.0%2B-brightgreen.svg)
![PHP](https://img.shields.io/badge/php-8.0%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-red.svg)

## 🎯 Overview

This plugin bridges the gap between your WordPress ecosystem and GoHighLevel CRM, offering features that competitive plugins lack—including **OAuth2 authentication**, **BuddyBoss integration**, **full multisite support**, and **intelligent field mapping with duplicate prevention**.

### What Makes This Plugin Different?

- ✅ **OAuth2 Authentication** - One-click secure connection to your GoHighLevel account
- ✅ **Automatic Token Refresh** - Seamless token management with fallback reconnection
- ✅ **BuddyBoss Integration** - The ONLY plugin that syncs BuddyBoss XProfile fields with GHL
- ✅ **Smart Field Mapping** - Visual interface with duplicate field prevention
- ✅ **Bi-directional Sync** - Choose sync direction per field (→ To GHL, ← From GHL, ↔ Both Ways)
- ✅ **Duplicate Contact Prevention** - Automatically updates existing contacts instead of creating duplicates
- ✅ **WordPress Multisite Ready** - Perfect for enterprise and network installations
- ✅ **WooCommerce Deep Integration** - Orders, customers, and product data
- ✅ **Comprehensive Logging** - Track every sync operation
- ✅ **Modern Tabbed UI** - Clean interface inspired by Memberium for Keap

## 🚀 Features

### Core Features

#### OAuth2 Authentication
- **One-Click Connection** - Securely connect to GoHighLevel with OAuth2
- **Automatic Token Refresh** - Tokens refresh automatically in the background
- **Reconnect API Fallback** - Seamless reconnection if refresh fails
- **Multi-Location Support** - Switch between different GoHighLevel locations
- **Secure Storage** - Tokens stored securely in WordPress options

#### WordPress Users
- Automatic contact creation when users register
- Real-time profile updates sync to GHL
- User deletion options (archive or delete contact)
- Configurable sync triggers (registration, profile update, login, etc.)
- Custom field synchronization

#### Smart Field Mapping
- **Visual Mapper** - Drag-and-drop interface for field mapping
- **Duplicate Prevention** - Shows which GHL fields are already mapped (✓ checkmark)
- **Bi-directional Sync** - Choose direction per field:
  - → To GoHighLevel Only
  - ← From GoHighLevel Only
  - ↔ Both Ways
- **BuddyBoss XProfile Support** - Map BuddyBoss profile fields to GHL custom fields
- **Dynamic Field Detection** - Automatically detects custom fields from plugins

#### WooCommerce Integration
- Customer sync on order completion
- Order data tracking in GHL
- Product purchase history
- Custom order meta fields
- Abandoned cart recovery (coming soon)

#### BuddyBoss/BuddyPress
- **XProfile field synchronization** - Map BuddyBoss profile fields to GHL
- Group member data sync (coming soon)
- Activity tracking (coming soon)
- Community engagement metrics (coming soon)

#### LearnDash (Coming Soon)
- Course enrollment synchronization
- Progress tracking
- Quiz completion tracking
- Certificate issuance tracking

## 📋 Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher (PHP 8.1+ recommended)
- GoHighLevel account with OAuth App configured
- WooCommerce 5.0+ (optional, for eCommerce features)
- BuddyBoss/BuddyPress (optional, for community features)
- LearnDash 3.0+ (optional, for LMS features - coming soon)

## 🔧 Installation

### From WordPress Admin

1. Download the plugin ZIP file
2. Navigate to **Plugins → Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Activate the plugin

### Manual Installation

1. Upload the plugin folder to `/wp-content/plugins/crm-integration`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **GHL CRM** in the admin menu to begin setup

## ⚙️ Configuration

### 1. Set Up Your GoHighLevel OAuth App

Before connecting the plugin, you need to create an OAuth app in GoHighLevel:

1. Log into your **GoHighLevel Agency account**
2. Navigate to **Settings → OAuth**
3. Click **Create New OAuth App**
4. Fill in the following details:
   - **App Name**: WordPress CRM Integration (or your preferred name)
   - **Redirect URL**: `https://yourdomain.com/wp-admin/admin.php?page=ghl-crm-settings`
   - **Scopes**: Select all required scopes:
     - `contacts.readonly`
     - `contacts.write`
     - `locations/tags.readonly`
     - `locations/tags.write`
     - `locations/customFields.readonly`
     - `locations/customFields.write`
5. Click **Create** and copy your **Client ID** and **Client Secret**

### 2. Configure the Plugin

1. In WordPress admin, go to **GHL CRM → Settings**
2. On the **General Settings** tab, you'll see the OAuth connection section
3. Click the **"Connect with GoHighLevel"** button
4. You'll be redirected to GoHighLevel to authorize the connection
5. **Authorize the app** in your GoHighLevel account
6. You'll be redirected back to WordPress automatically
7. Connection status will show as **"✓ Connected"** with your location name

That's it! The plugin is now connected and ready to use.

### 3. Configure Field Mapping

1. Navigate to **GHL CRM → Settings** and click the **Field Mapping** tab
2. You'll see three sections:
   - **Default WordPress Fields** (user_email, first_name, last_name, etc.)
   - **BuddyBoss Profile Fields** (if BuddyBoss is active)
   - **Custom & Plugin Fields** (fields added by other plugins)
3. For each WordPress field:
   - Select the corresponding **GoHighLevel field** from the dropdown
   - Choose the **Sync Direction**:
     - **↔ Both Ways** - Sync changes in both directions
     - **→ To GoHighLevel Only** - Only send data from WordPress to GHL
     - **← From GoHighLevel Only** - Only receive data from GHL to WordPress
4. Click **Save Field Mapping**
5. Fields already mapped will show with a **✓ (mapped)** indicator in other dropdowns

**Note**: Already mapped GHL fields will be disabled in other dropdowns to prevent duplicate mappings.

### 4. Enable User Synchronization

1. Go to **GHL CRM → Settings** and click the **Integrations** tab
2. Click on the **WordPress Users** card
3. Toggle **"Enable User Sync"** to ON
4. Select which WordPress events should trigger synchronization:
   - ✓ User Registration
   - ✓ Profile Update
   - ✓ User Login (optional)
   - ✓ Password Reset (optional)
5. Choose deletion behavior:
   - Keep contact in GoHighLevel when user is deleted, OR
   - Delete contact from GoHighLevel when user is deleted
6. Click **Save Integration Settings**

## 📖 Usage

### Viewing Sync Activity

1. Navigate to **GHL CRM → Sync Logs**
2. View all synchronization activity in real-time
3. Filter by date, status, or sync type
4. Click on any log entry to see detailed information

### Testing the Connection

After connecting via OAuth, you can test the connection:

1. Go to **GHL CRM → Settings** (General Settings tab)
2. Scroll down to the **Test Connection** section
3. Click **"Test API Connection"**
4. You'll see a success message with your location name if connected properly

### Managing OAuth Connection

**To Disconnect:**
1. Go to **GHL CRM → Settings**
2. Click the **"Disconnect"** button in the OAuth status section
3. Confirm the disconnection

**To Reconnect:**
1. Simply click **"Connect with GoHighLevel"** again
2. Authorize the app in GoHighLevel
3. You'll be redirected back automatically

### Advanced Configuration

#### Custom Field Mapping with Code

While the visual mapper handles most use cases, you can also programmatically add field mappings:

```php
// Example: Add custom field mapping via filter
add_filter('ghl_crm_user_contact_data', function($contact_data, $user_id) {
    // Add custom field
    $contact_data['customField.custom_field_key'] = get_user_meta($user_id, 'your_meta_key', true);
    
    return $contact_data;
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

#### Modify Sync Actions

```php
// Example: Add custom action after successful sync
add_action('ghl_crm_user_synced', function($user_id, $contact_id, $is_new) {
    if ($is_new) {
        // Do something for new contacts
        error_log("New GHL contact created: {$contact_id} for user {$user_id}");
    }
}, 10, 3);
```

## 🎨 Screenshots

1. **OAuth Connection** - One-click secure connection to GoHighLevel
2. **Settings Page** - Clean tabbed interface for settings, integrations, and field mapping
3. **Field Mapping** - Visual field mapper with duplicate prevention and sync direction control
4. **Integrations** - Toggle WordPress Users, WooCommerce, BuddyBoss sync modules
5. **Sync Logs** - Comprehensive sync history and error tracking

## 🔍 Troubleshooting

### OAuth Connection Issues

**Problem:** "OAuth authorization failed"

**Solutions:**
1. Verify your OAuth app is created correctly in GoHighLevel
2. Check that the **Redirect URL** in your GHL OAuth app matches your WordPress admin URL exactly
3. Ensure your WordPress site uses **HTTPS** (required for OAuth)
4. Try disconnecting and reconnecting
5. Clear your browser cache and try again

**Problem:** "Token refresh failed"

**Solutions:**
1. The plugin will automatically attempt to reconnect
2. If reconnection fails, simply click **"Connect with GoHighLevel"** again
3. Check that your OAuth app in GHL is still active

### Sync Not Working

**Problem:** Data not syncing to GoHighLevel

**Solutions:**
1. Check **GHL CRM → Sync Logs** for errors
2. Verify OAuth connection is active (green checkmark on Settings page)
3. Ensure field mappings are configured in **Field Mapping** tab
4. Verify **User Sync** is enabled in **Integrations** tab
5. Check that sync triggers are selected (user registration, profile update, etc.)

### Field Mapping Issues

**Problem:** "Fields not mapping correctly"

**Solutions:**
1. Ensure you've clicked **Save Field Mapping** after making changes
2. Check that GHL fields aren't already mapped (look for ✓ checkmark)
3. Verify sync direction is set correctly for each field
4. Test with a new user registration to see if data syncs

### Duplicate Contacts

**Problem:** "Creating duplicate contacts in GoHighLevel"

**Solutions:**
1. The plugin automatically prevents duplicates by email
2. Existing contacts are updated instead of creating new ones
3. If you see duplicates, they likely have different email addresses
4. Check your GoHighLevel location settings for duplicate prevention rules

### Rate Limit Errors

**Problem:** "API rate limit exceeded"

**Solutions:**
1. The plugin automatically throttles requests
2. GoHighLevel OAuth apps have higher rate limits than API keys
3. If syncing many users at once, the plugin will queue them automatically

### Multisite Issues

**Problem:** Settings not saving on specific sites

**Solutions:**
1. Each site in a multisite network has its own OAuth connection
2. Connect each site separately via **GHL CRM → Settings**
3. Field mappings are per-site and can differ between sites
4. Ensure proper user capabilities (`manage_options`) on each site

## 🛠️ Development

### Local Development Setup

```bash
# Clone the repository
git clone https://github.com/Cottonnion/gohighlevel-integration-plugin.git

# Navigate to your WordPress plugins directory
cd wp-content/plugins/

# Create symbolic link or copy the plugin folder
ln -s /path/to/gohighlevel-integration-plugin crm-integration

# No composer install needed - plugin is dependency-free
```

### Project Structure

```
crm-integration/
├── src/
│   ├── Core/              # Core functionality
│   │   ├── Loader.php           # Plugin initialization
│   │   ├── MenuManager.php      # Admin menu structure
│   │   ├── SettingsManager.php  # Settings & AJAX handlers
│   │   ├── AssetsManager.php    # CSS/JS management
│   │   ├── AdminNotices.php     # Notification system
│   │   └── AjaxHandler.php      # Legacy AJAX handlers
│   ├── API/               # GoHighLevel API integration
│   │   ├── Client/            # HTTP client
│   │   ├── OAuth/             # OAuth2 authentication
│   │   └── Resources/         # API resource classes
│   ├── Integrations/      # Platform integrations
│   │   ├── Users/             # WordPress user sync
│   │   ├── WooCommerce/       # WooCommerce integration
│   │   └── BuddyBoss/         # BuddyBoss integration
│   └── Utilities/         # Helper functions
├── templates/
│   └── admin/             # Admin page templates
│       ├── main-settings.php    # Main tabbed interface
│       ├── settings.php         # General settings tab
│       ├── integrations.php     # Integrations tab
│       ├── field-mapping.php    # Field mapping tab
│       └── sync-logs.php        # Sync logs page
├── assets/
│   ├── admin/
│   │   ├── css/              # Admin stylesheets
│   │   └── js/               # Admin JavaScript
│   └── public/               # Frontend assets (if needed)
└── gohighlevel-crm-integration.php  # Main plugin file
```
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
- Write clear, documented code
- Test thoroughly before submitting
- Update documentation as needed
- Ensure backward compatibility

## 📝 Changelog

### Version 1.0.0 - 2025-10-23

**Initial Release**

- ✨ **OAuth2 Authentication** - Secure one-click connection to GoHighLevel
- ✨ **Automatic Token Refresh** - Seamless token management with reconnect fallback
- ✨ **WordPress User Sync** - Real-time contact synchronization
- ✨ **Smart Field Mapping** - Visual mapper with duplicate prevention
- ✨ **Bi-directional Sync** - Choose sync direction per field (→, ←, ↔)
- ✨ **BuddyBoss XProfile Support** - Map BuddyBoss profile fields to GHL
- ✨ **Duplicate Contact Prevention** - Auto-update existing contacts
- ✨ **WooCommerce Integration** - Customer and order sync
- ✨ **Multisite Support** - Per-site OAuth connections
- ✨ **Comprehensive Logging** - Track all sync operations
- ✨ **Modern Tabbed UI** - Clean interface inspired by Memberium
- ✨ **AJAX-Based Settings** - No page reloads, instant feedback
- ✨ **SweetAlert2 Notifications** - Beautiful toast notifications

## 🔐 Security

### Reporting Security Issues

If you discover a security vulnerability, please email yahyadard@gmail.com. Do not create public issues for security vulnerabilities.

### Security Features

- ✅ **OAuth2 Secure Authentication** - Industry-standard authentication
- ✅ **Automatic Token Encryption** - Secure token storage
- ✅ **Nonce Verification** - CSRF protection on all AJAX requests
- ✅ **Capability Checks** - Proper user permission validation
- ✅ **Input Sanitization** - All user inputs sanitized
- ✅ **Output Escaping** - XSS protection
- ✅ **No External Dependencies** - Zero third-party PHP libraries
- ✅ **HTTPS Required** - OAuth requires secure connections

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

### Main Developer

**Yahya Eddaqqaq**
- Lead Developer & Architect
- Email: yahyadard@gmail.com
- Website: [yahyadev.com](https://yahyadev.online/)
- GitHub: [@Cottonnion](https://github.com/Cottonnion)

*Specialized in WordPress plugin development, CRM integrations, and enterprise solutions.*

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
- Email: yahyadard@gmail.com
- Website: https://labgenz.com/
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
