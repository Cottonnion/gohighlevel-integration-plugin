=== GHL Sync Bridge ===
Contributors: cottonnion
Tags: gohighlevel, crm, woocommerce, buddyboss, learndash, membership, webhooks
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful WordPress plugin that seamlessly integrates GoHighLevel CRM with WordPress, WooCommerce, BuddyBoss, and LearnDash with advanced membership restrictions, role-based tagging, and webhook automation.

== Description ==

# GHL Sync Bridge

A powerful WordPress plugin that seamlessly integrates GoHighLevel CRM with WordPress, WooCommerce, BuddyBoss, and LearnDash. Features OAuth2 authentication, intelligent field mapping, automatic contact synchronization, role-based tagging, membership restrictions, webhook automation, custom objects, and comprehensive queue management.

![Version](https://img.shields.io/badge/version-1.3.9-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-5.8%2B-brightgreen.svg)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-red.svg)
![Multisite](https://img.shields.io/badge/multisite-ready-green.svg)

## 🎯 Overview

This plugin bridges the gap between your WordPress ecosystem and GoHighLevel CRM, offering features that competitive plugins lack—including **OAuth2 authentication**, **BuddyBoss integration**, **full multisite support**, and **intelligent field mapping with duplicate prevention**.

### What Makes This Plugin Different?

- ✅ **OAuth2 Authentication** - One-click secure connection to your GoHighLevel account
- ✅ **Automatic Token Refresh** - Seamless token management with fallback reconnection
- ✅ **Role-Based Tagging** - Automatically assign/remove tags based on WordPress roles
- ✅ **Membership & Access Control** - Restrict content based on GoHighLevel tags
- ✅ **BuddyBoss Integration** - The ONLY plugin that syncs BuddyBoss XProfile fields with GHL
- ✅ **Smart Field Mapping** - Visual interface with duplicate field prevention
- ✅ **Bi-directional Sync** - Choose sync direction per field (→ To GHL, ← From GHL, ↔ Both Ways)
- ✅ **Webhook Automation** - Real-time bidirectional sync with GoHighLevel webhooks
- ✅ **Custom Objects Support** - Map and sync GHL custom objects (forms, surveys, etc.)
- ✅ **User Profile Login Links** - Generate secure auto-login links for users
- ✅ **Queue Management** - Reliable background processing with Action Scheduler
- ✅ **Rate Limiting** - Automatic API rate limit compliance (100/10s, 200k/day)
- ✅ **Duplicate Contact Prevention** - Automatically updates existing contacts instead of creating duplicates
- ✅ **WordPress Multisite Ready** - Perfect for enterprise and network installations with per-site isolation
- ✅ **WooCommerce Deep Integration** - Orders, customers, and product data
- ✅ **Comprehensive Logging** - Track every sync operation with detailed error reporting
- ✅ **Modern SPA UI** - Clean single-page application interface with instant feedback
- ✅ **System Health Checks** - Built-in diagnostics for connection, settings, and performance

## 🚀 Features

### Core Features

#### OAuth2 Authentication
- **One-Click Connection** - Securely connect to GoHighLevel with OAuth2
- **Automatic Token Refresh** - Tokens refresh automatically in the background
- **Reconnect API Fallback** - Seamless reconnection if refresh fails
- **Multi-Location Support** - Switch between different GoHighLevel locations
- **Secure Storage** - Tokens stored securely with WordPress options API
- **Connection Status** - Real-time connection verification with location details

#### Role-Based Tagging System
- **Automatic Tag Assignment** - Tags auto-apply when users get roles
- **Automatic Tag Removal** - Tags removed when users lose roles
- **Global Tags** - Apply tags to all synced users regardless of role
- **Tag Prefixes** - Add prefixes to organize WordPress-generated tags
- **Registration Tags** - Track registration source with special tags
- **WooCommerce Customer Tags** - Automatically tag paying customers
- **Bulk Operations** - Add/remove tags for all users with a specific role
- **Queue-Based Processing** - Reliable background tag operations

#### Membership & Access Control
- **Tag-Based Restrictions** - Restrict content based on GHL tags
- **Flexible Access Rules** - ANY, ALL, or NONE tag logic
- **Custom Redirects** - Redirect unauthorized users to specific URLs
- **Post Type Support** - Pages, posts, products, courses, and custom post types
- **Archive Protection** - Hide restricted content from archives and search
- **Admin Override** - Administrators always bypass restrictions
- **Custom Messages** - Configurable access denied messages

#### WordPress Users
- Automatic contact creation when users register
- Real-time profile updates sync to GHL
- User deletion options (archive or delete contact)
- Configurable sync triggers (registration, profile update, login, etc.)
- Custom field synchronization
- Default tags on registration
- BuddyBoss XProfile field sync

#### Smart Field Mapping
- **Visual Mapper** - Intuitive interface for field mapping
- **Duplicate Prevention** - Shows which GHL fields are already mapped (✓ checkmark)
- **Bi-directional Sync** - Choose direction per field:
  - → To GoHighLevel Only
  - ← From GoHighLevel Only
  - ↔ Both Ways
- **BuddyBoss XProfile Support** - Map BuddyBoss profile fields to GHL custom fields
- **Dynamic Field Detection** - Automatically detects custom fields from plugins
- **Green Highlighting** - Visual feedback for mapped fields
- **Live Reload** - Refresh field lists without page reload

#### Webhook Automation
- **Bidirectional Sync** - WordPress ↔ GoHighLevel real-time updates
- **Automatic Setup** - Register webhooks automatically via API
- **Secure Verification** - Signature-based webhook validation
- **Event Filtering** - Choose which events trigger webhooks
- **Error Handling** - Automatic retry on failures
- **Debug Mode** - Detailed webhook logging for troubleshooting

#### Custom Objects Support
- **Form Submissions** - Sync GHL form submissions to WordPress
- **Survey Responses** - Track survey data
- **Custom Data** - Map any GHL custom object
- **Field Mapping** - Visual mapper for custom object fields
- **Association Management** - Link custom objects to contacts
- **Bulk Operations** - Sync multiple records efficiently

#### User Profile Enhancements
- **Auto-Login Links** - Generate secure login links for users
- **Token Expiration** - Configurable link expiration (1-72 hours)
- **One-Time Use** - Links expire after first use
- **Copy to Clipboard** - Easy link sharing
- **Visual Feedback** - Real-time copy confirmation
- **Error Handling** - Clear error messages for expired/invalid links

#### Queue Management
- **Action Scheduler** - WordPress-native background processing
- **Priority Queue** - High-priority items processed first
- **Automatic Retry** - Failed items retry with exponential backoff
- **Rate Limiting** - Automatic API rate limit compliance
- **Status Dashboard** - Real-time queue monitoring
- **Manual Controls** - Pause, resume, or clear queue
- **Detailed Logging** - Track every queue operation

#### WooCommerce Integration
- Customer sync on order completion
- Order data tracking in GHL
- Product purchase history
- Custom order meta fields
- Automatic customer tagging
- Order status tracking

#### BuddyBoss/BuddyPress
- **XProfile field synchronization** - Map BuddyBoss profile fields to GHL
- **Profile Photos** - Sync avatars to GHL
- **Member Types** - Tag based on member types
- **Activity Tracking** - Monitor user engagement

#### LearnDash Integration
- Course enrollment synchronization
- Progress tracking
- Completion tracking
- Certificate tracking
- Quiz results sync

## 📋 Requirements

- WordPress 5.8 or higher (6.0+ recommended)
- PHP 7.4 or higher (PHP 8.0+ recommended)
- GoHighLevel account with OAuth App configured
- HTTPS (required for OAuth2)
- WooCommerce 5.0+ (optional, for eCommerce features)
- BuddyBoss/BuddyPress (optional, for community features)
- LearnDash 3.0+ (optional, for LMS features)
- Action Scheduler (included via Composer)

### Recommended Server Settings
- PHP Memory Limit: 256MB or higher
- Max Execution Time: 300 seconds
- WordPress Cron enabled OR server cron configured
- HTTPS/SSL certificate (required for OAuth)

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
5. **Default Tags on Registration** (optional):
   - Enable the checkbox to apply tags on user registration
   - Select tags from the dropdown (loads from your GHL account)
   - These tags will be applied to all new user registrations
6. Choose deletion behavior:
   - Keep contact in GoHighLevel when user is deleted, OR
   - Delete contact from GoHighLevel when user is deleted
7. Click **Save Integration Settings**

### 5. Configure Role-Based Tags (Optional but Recommended)

1. Go to **GHL CRM → Settings** and click the **Role Tags** tab
2. For each WordPress role you want to configure:
   - Select tags to apply when users get this role
   - Enable **Auto-Apply** to automatically add tags
   - Enable **Remove on Role Change** to remove tags when role is lost
3. Configure **Global Tags**:
   - These tags apply to ALL synced users
   - Great for tagging all users as "WordPress" or "Site Member"
4. Enable special tags:
   - **Registration Source Tag**: Adds "wordpress-registration" to new users
   - **WooCommerce Customer Tag**: Adds "woocommerce-customer" to buyers
5. Set **Tag Prefix** (optional):
   - Example: "wp-" will create tags like "wp-subscriber", "wp-customer"
6. Use **Bulk Operations** to:
   - Add tags to all users with a specific role
   - Remove tags from all users with a specific role
7. Click **Save Role Tags Settings**

### 6. Set Up Membership Restrictions (Optional)

1. Edit any page, post, product, or course
2. Find the **"GHL Membership Restrictions"** meta box (sidebar)
3. Choose restriction type:
   - **No Restrictions**: Content accessible to everyone (default)
   - **User has ANY of these tags**: User needs at least one tag
   - **User has ALL of these tags**: User needs all selected tags
   - **User does NOT have these tags**: Block users with these tags
4. Select tags using the tag picker
5. Set **Redirect URL** (optional):
   - Enter full URL to redirect unauthorized users
   - Leave empty for default "Access Denied" message
6. Configure global restriction settings:
   - Go to **GHL CRM → Settings → Restrictions** tab
   - Choose tags that bypass all restrictions
   - Enable archive protection to hide restricted content
   - Enable REST API protection
7. Update/Publish your content

### 7. Configure Webhooks (Optional but Recommended for Real-Time Sync)

1. Go to **GHL CRM → Settings** and click the **Webhooks** tab
2. Click **"Register Webhooks"** button
3. The plugin will automatically:
   - Create webhook endpoints in your GHL location
   - Configure events (contact.create, contact.update, contact.delete)
   - Set up secure webhook verification
4. View webhook status and recent activity
5. Use **Debug Mode** for troubleshooting (logs all webhook requests)

### 8. Map Custom Objects (Optional)

1. Go to **GHL CRM → Custom Objects**
2. Click **"Add New Mapping"**
3. Select the custom object type (forms, surveys, etc.)
4. Map GHL fields to WordPress fields
5. Configure sync direction and triggers
6. Save mapping
7. View sync history and status

## 📖 Usage

### Viewing Sync Activity

1. Navigate to **GHL CRM → Sync Logs**
2. View all synchronization activity in real-time
3. Filter by date, status, or sync type
4. Click on any log entry to see detailed information
5. Use the search function to find specific contacts or operations
6. Export logs for reporting or troubleshooting

### Testing the Connection

After connecting via OAuth, you can test the connection:

1. Go to **GHL CRM → Settings** (General Settings tab)
2. Scroll down to the **Test Connection** section
3. Click **"Test API Connection"**
4. You'll see a success message with your location name if connected properly
5. If connection fails, check error details in the response

### System Health Check

Monitor plugin health and performance:

1. Go to **GHL CRM → Settings** (Advanced tab)
2. Click **"Run System Health Check"**
3. View diagnostic information:
   - OAuth connection status
   - API connectivity
   - Queue status
   - Database health
   - Server requirements
   - Installed integrations
4. Address any warnings or errors shown

### Managing OAuth Connection

**To Disconnect:**
1. Go to **GHL CRM → Settings**
2. Click the **"Disconnect"** button in the OAuth status section
3. Confirm the disconnection
4. Note: This doesn't delete your settings, only disconnects OAuth

**To Reconnect:**
1. Simply click **"Connect with GoHighLevel"** again
2. Authorize the app in GoHighLevel
3. You'll be redirected back automatically
4. Previous settings and mappings are preserved

### Queue Management

Monitor and control the sync queue:

1. Go to **GHL CRM → Dashboard** or **Queue Status** section
2. View queue statistics:
   - Pending items
   - Processing items
   - Completed items
   - Failed items
3. **Queue Controls**:
   - **Pause Queue**: Stop processing temporarily
   - **Resume Queue**: Continue processing
   - **Clear Failed**: Remove failed items
   - **Retry Failed**: Retry all failed items
4. View rate limit status (100/10s, 200k/day limits)

### Generating User Login Links

Create secure auto-login links for users:

1. Go to **Users → All Users** in WordPress
2. Click on a user to edit
3. Find the **"GoHighLevel Integration"** section
4. Set link expiration time (1-72 hours)
5. Click **"Generate Login Link"**
6. Copy the link using the copy button
7. Share the link with the user
8. Link expires after first use or time limit

### Using Role-Based Tags

Automatically manage tags based on user roles:

1. **Automatic Assignment**:
   - Configure role tags in settings
   - Tags apply automatically when users register or role changes
2. **Bulk Operations**:
   - Go to **Settings → Role Tags**
   - Select a role
   - Enter tags to add or remove
   - Click bulk add/remove button
   - All users with that role are queued for update
3. **Monitor tag operations** in Sync Logs

### Restricting Content

Control access to content based on GHL tags:

1. **Per-Content Restrictions**:
   - Edit page/post/product/course
   - Use "GHL Membership Restrictions" meta box
   - Select restriction type and tags
   - Set optional redirect URL
2. **Global Restriction Settings**:
   - Go to **Settings → Restrictions**
   - Set bypass tags (admin tags that bypass all restrictions)
   - Enable archive protection
   - Enable REST API protection
3. **User Experience**:
   - Authorized users see content normally
   - Unauthorized users are redirected or see access denied message
   - Logged-out users are prompted to log in

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

1. **Dashboard** - Overview of sync status, queue statistics, and recent activity
2. **OAuth Connection** - One-click secure connection to GoHighLevel with connection status
3. **Settings Page** - Clean tabbed SPA interface with instant feedback
4. **Field Mapping** - Visual field mapper with duplicate prevention, green highlighting, and sync direction control
5. **Role Tags** - Configure automatic tag assignment based on WordPress roles
6. **Membership Restrictions** - Tag-based content access control settings
7. **Integrations** - Toggle WordPress Users, WooCommerce, BuddyBoss sync modules
8. **Custom Objects** - Map and sync GHL custom objects (forms, surveys, etc.)
9. **Webhooks** - Configure real-time bidirectional sync with webhook automation
10. **Sync Logs** - Comprehensive sync history with filtering and detailed error tracking
11. **Queue Management** - Monitor and control background sync operations
12. **System Health** - Diagnostic dashboard showing system status and recommendations
13. **User Profile** - Generate secure auto-login links for users
14. **Content Restrictions** - Per-content meta box for setting access rules
15. **Bulk Operations** - Add/remove tags for all users with a specific role

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
1. The plugin automatically throttles requests (100/10s burst, 200k/day)
2. GoHighLevel OAuth apps have higher rate limits than API keys
3. If syncing many users at once, the plugin will queue them automatically
4. Check queue status to see rate limit compliance
5. Rate limits reset automatically (burst: 10 seconds, daily: midnight)

### Multisite Issues

**Problem:** Settings not saving on specific sites

**Solutions:**
1. Each site in a multisite network has its own OAuth connection
2. Connect each site separately via **GHL CRM → Settings**
3. Field mappings are per-site and can differ between sites
4. Ensure proper user capabilities (`manage_options`) on each site
5. Check that SettingsManager is properly handling site switching

### Role Tags Not Applying

**Problem:** Tags not being added when users register or change roles

**Solutions:**
1. Verify **Enable User Sync** is turned on in Integrations tab
2. Check **Role Tags** tab settings:
   - Auto-Apply is enabled for the role
   - Tags are properly entered (comma-separated)
3. Check **Sync Logs** for errors
4. Ensure OAuth connection is active
5. Verify user has been synced to GHL (has `_ghl_contact_id`)
6. Use bulk operations to retroactively add tags

### Membership Restrictions Not Working

**Problem:** Users can access restricted content despite not having required tags

**Solutions:**
1. Verify restrictions are enabled:
   - Go to **Settings → Restrictions**
   - Check "Enable Restrictions" is ON
2. Check content restrictions:
   - Edit the content
   - Verify restriction type and tags are set correctly
3. Verify user's GHL contact has the required tags
4. Check if user has bypass tags (configured in Restrictions settings)
5. Remember: Administrators always bypass restrictions
6. Clear cache if using caching plugin
7. Check Sync Logs for tag synchronization

### Webhook Issues

**Problem:** Webhooks not receiving updates from GoHighLevel

**Solutions:**
1. Verify webhooks are registered:
   - Go to **Settings → Webhooks**
   - Check webhook status shows "Active"
2. Check your GoHighLevel webhook settings:
   - Log into GHL → Settings → Webhooks
   - Verify webhooks are pointing to your WordPress URL
   - Check webhook events are enabled
3. Enable **Debug Mode** in Webhooks tab
4. Check webhook logs for incoming requests
5. Verify your site is accessible via HTTPS
6. Check server firewall isn't blocking incoming requests
7. Test with manual webhook trigger in GHL

### Auto-Login Links Not Working

**Problem:** Generated login links don't work

**Solutions:**
1. Check link hasn't expired (default: 24 hours)
2. Remember links are one-time use only
3. Verify user hasn't been deleted or disabled
4. Check WordPress permalinks are properly configured
5. Ensure `_ghl_login_token` is being stored correctly
6. Try generating a new link
7. Check error message for specific issue

### Custom Objects Not Syncing

**Problem:** GHL custom objects not appearing in WordPress

**Solutions:**
1. Verify custom object mapping exists:
   - Go to **GHL CRM → Custom Objects**
   - Check mapping is active
2. Ensure webhook or manual sync is configured
3. Check Sync Logs for custom object errors
4. Verify GHL custom object has proper field structure
5. Check field mapping is correct
6. Test with manual sync first

### Queue Not Processing

**Problem:** Queue items stuck in "pending" status

**Solutions:**
1. Check WordPress Cron is working:
   - Use plugin like "WP Crontrol" to test
   - Or configure server cron job
2. Check queue status in Dashboard
3. Try manually running: `wp action-scheduler run`
4. Check for PHP errors in error logs
5. Verify Action Scheduler is properly installed
6. Check server resources (memory, execution time)
7. Use queue controls to pause/resume or retry failed items

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

### Version 1.1.3 - 2026-03-22

**OAuth Stability & Dashboard UX**

- 🐛 Fixed Action Scheduler timing race — scheduled token refresh was never registering (AS init at priority 1, plugin at priority 0)
- 🐛 Fixed cron/CLI OAuth timeout — cron and WP-CLI now get 15s timeout instead of 8s frontend timeout
- 🔄 Widened scheduled refresh window from 2h → 12h buffer before token expiry
- 🔄 Added auto-retry on cURL timeout (error 28) with 25s timeout before fallback
- 🔄 Bumped reconnect endpoint timeout from 15s → 25s
- 🛡️ Added Action Scheduler readiness guards to NotificationManager, ReportingManager, QueueManager
- 🛡️ Added per-request refresh failure flag to prevent cascading retry loops
- 🛡️ Added log deduplication (30s window) in FileLogger
- ✨ Added "Reconnect Account" quick action button on dashboard
- ✨ Token expiry now shows exact HH:MM:SS instead of approximate "24 hours"
- 🎨 Elementor widget renamed from "GHL Restricted Content" to "GHL Content"
- 🎨 Added GHL Quick Links to reports dashboard
- ✨ Gutenberg & Elementor compound AND/OR/NOT tag conditions

### Version 1.1.2 - 2026-03-21

**Maintenance Release**

- 🔄 Version bump for Pro add-on 1.1.2 compatibility (LearnDash sync reliability fixes)

### Version 1.1.1 - 2026-03-19

**Quality & Reliability**

- ✨ Centralized UserMetaSync class for cleaner post-sync logic
- ✨ Asset auto-minification pipeline (`composer build`)
- 🐛 Fixed WooCommerce product-tags queue routing
- 🐛 Fixed empty tag sync failure
- 🐛 Fixed tag overwrite on WooCommerce sync
- 🧹 Removed dead code from QueueManager
- 🛡️ Improved error catching with `\Throwable`

### Version 1.0.2 - 2026-03-18

**Stability Fixes**

- 🔄 Version constants used everywhere for consistency
- 🐛 Fixed user deletion sync (WP→GHL)
- 🐛 Fixed webhook delete sync (GHL→WP)
- 🐛 Fixed CF7 submission timing race condition
- 🐛 Fixed Settings page jQuery crash on special characters
- 🐛 Fixed WooCommerce tag overwrite
- 🐛 Fixed tag display with special characters across 10 PHP + 4 JS files

### Version 1.0.1 - 2026-03-17

**New Features & Integrations**

- ✨ LearnDash Course Progress Sync with debounce
- ✨ Contact Form 7 Integration with per-form mapping
- ✨ Sync Preview / Dry Run mode
- ✨ Email Notification System (6 notification types)
- ✨ Elementor Widget Restrictions
- ✨ Gutenberg Restricted Content Block
- 🐛 Fixed duplicate contact recovery
- 🐛 Fixed ping-pong sync loops

### Version 1.0.0 - 2026-01-01

**Initial Release - Feature-Complete**

#### Core Features
- ✨ **OAuth2 Authentication** - Secure one-click connection to GoHighLevel
- ✨ **Automatic Token Refresh** - Seamless token management with reconnect fallback
- ✨ **WordPress User Sync** - Real-time contact synchronization
- ✨ **Smart Field Mapping** - Visual mapper with duplicate prevention and green highlighting
- ✨ **Bi-directional Sync** - Choose sync direction per field (→, ←, ↔)
- ✨ **BuddyBoss XProfile Support** - Map BuddyBoss profile fields to GHL
- ✨ **Duplicate Contact Prevention** - Auto-update existing contacts
- ✨ **WooCommerce Integration** - Customer and order sync
- ✨ **Multisite Support** - Per-site OAuth connections with proper data isolation
- ✨ **Comprehensive Logging** - Track all sync operations with detailed error reporting

#### Advanced Features
- ✨ **Role-Based Tagging** - Automatic tag assignment/removal based on WordPress roles
- ✨ **Global Tags** - Apply tags to all synced users
- ✨ **Tag Prefixes** - Organize WordPress-generated tags with custom prefixes
- ✨ **Bulk Tag Operations** - Add/remove tags for all users with a specific role
- ✨ **Default Registration Tags** - Apply tags to new user registrations
- ✨ **Membership Restrictions** - Tag-based content access control
- ✨ **Flexible Access Rules** - ANY, ALL, or NONE tag logic for restrictions
- ✨ **Custom Redirects** - Redirect unauthorized users to specific URLs
- ✨ **Archive Protection** - Hide restricted content from archives and search

#### Webhook & Automation
- ✨ **Webhook Automation** - Real-time bidirectional sync with GoHighLevel
- ✨ **Automatic Webhook Setup** - Register webhooks via API automatically
- ✨ **Secure Verification** - Signature-based webhook validation
- ✨ **Debug Mode** - Detailed webhook logging for troubleshooting
- ✨ **Event Filtering** - Choose which events trigger webhooks

#### Custom Objects & Data
- ✨ **Custom Objects Support** - Map and sync GHL custom objects
- ✨ **Form Submissions** - Sync form data from GHL to WordPress
- ✨ **Survey Responses** - Track survey data
- ✨ **Visual Object Mapper** - Easy field mapping for custom objects
- ✨ **Association Management** - Link custom objects to contacts

#### User Experience
- ✨ **Auto-Login Links** - Generate secure login links for users (1-72hr expiration)
- ✨ **One-Time Use Tokens** - Links expire after first use for security
- ✨ **Copy to Clipboard** - Easy link sharing with visual feedback
- ✨ **Modern SPA UI** - Single-page application interface with instant feedback
- ✨ **SweetAlert2 Notifications** - Beautiful toast and modal notifications
- ✨ **AJAX-Based Settings** - No page reloads for all operations
- ✨ **Mobile-Responsive** - Works perfectly on all devices

#### Queue & Performance
- ✨ **Action Scheduler Integration** - WordPress-native background processing
- ✨ **Priority Queue** - High-priority items processed first
- ✨ **Automatic Retry** - Failed items retry with exponential backoff
- ✨ **Rate Limiting** - Automatic API rate limit compliance (100/10s, 200k/day)
- ✨ **Queue Dashboard** - Real-time monitoring and controls
- ✨ **Manual Controls** - Pause, resume, clear, or retry queue
- ✨ **Performance Optimization** - Efficient batch processing

#### System Tools
- ✨ **System Health Check** - Built-in diagnostics for connection, settings, and performance
- ✨ **Connection Testing** - Verify OAuth and API connectivity
- ✨ **Settings Export/Import** - Backup and restore configuration
- ✨ **Cache Management** - Clear API response cache
- ✨ **Settings Reset** - Reset to defaults while preserving OAuth connection
- ✨ **Detailed Error Logging** - Comprehensive sync and error logs

#### Developer Features
- ✨ **Clean Code Architecture** - Domain-driven design with clear separation of concerns
- ✨ **PSR-4 Autoloading** - Modern PHP standards
- ✨ **WordPress Coding Standards** - PHPCS/WPCS compliant
- ✨ **Extensible Hooks** - Filters and actions for customization
- ✨ **Comprehensive Documentation** - 20+ markdown documentation files
- ✨ **Select2 Enhancements** - Keep dropdowns open when selecting multiple items

#### Security
- ✨ **Nonce Verification** - CSRF protection on all AJAX requests
- ✨ **Capability Checks** - Proper user permission validation
- ✨ **Input Sanitization** - All user inputs sanitized and validated
- ✨ **Output Escaping** - XSS protection throughout
- ✨ **Secure Token Storage** - OAuth tokens stored securely
- ✨ **HTTPS Required** - OAuth requires secure connections

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

### ✅ Version 1.0.0 - COMPLETED (November 2025)
- ✅ OAuth2 Authentication
- ✅ Role-Based Tagging System
- ✅ Membership & Access Control
- ✅ Webhook Automation
- ✅ Custom Objects Support
- ✅ Auto-Login Links
- ✅ Queue Management
- ✅ Rate Limiting
- ✅ System Health Checks
- ✅ Modern SPA UI
- ✅ WordPress Multisite Support
- ✅ Comprehensive Documentation

### Upcoming Features

#### Version 1.1.0 (Q1 2026)
- [ ] Gravity Forms integration
- [ ] Elementor Forms integration
- [ ] Contact Form 7 integration
- [ ] Ninja Forms integration
- [ ] Enhanced reporting dashboard
- [ ] Advanced filtering in sync logs
- [ ] Bulk user import/export
- [ ] CSV import/export for contacts
- [ ] Scheduled sync jobs
- [ ] Performance analytics

#### Version 1.2.0 (Q2 2026)
- [ ] Multi-location support (switch between GHL locations)
- [ ] Advanced automation workflows
- [ ] Conditional field mapping
- [ ] Custom dashboard widgets
- [ ] Email campaign tracking
- [ ] SMS tracking integration
- [ ] Call tracking integration
- [ ] Pipeline stage tracking
- [ ] Opportunity management
- [ ] Task management sync

#### Version 2.0.0 (Q3 2026)
- [ ] Complete UI redesign with React
- [ ] Advanced automation engine with visual builder
- [ ] Machine learning for tag suggestions
- [ ] Predictive analytics
- [ ] A/B testing capabilities
- [ ] Enhanced multisite features
- [ ] Mobile app companion
- [ ] REST API for third-party integrations
- [ ] GDPR compliance tools
- [ ] Data encryption at rest

#### Future Considerations
- [ ] Integration with popular page builders
- [ ] Advanced membership tiers
- [ ] Drip content functionality
- [ ] Gamification features
- [ ] Points and rewards system
- [ ] Social proof widgets
- [ ] Real-time notifications
- [ ] Advanced analytics dashboard

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