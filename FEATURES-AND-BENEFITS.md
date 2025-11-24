=== GoHighLevel CRM Integration ===
Contributors: cottonnion
Tags: gohighlevel, crm, woocommerce, buddyboss, learndash, membership, webhooks, automation, oauth2, forms
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The most powerful GoHighLevel integration for WordPress. Deep WooCommerce, BuddyBoss, LearnDash integrations. Custom objects, family relationships, forms, webhooks, and complete automation.

== Description ==

# GoHighLevel CRM Integration - The Complete WordPress Automation Solution

Transform your WordPress site into a fully automated CRM powerhouse with the most comprehensive GoHighLevel integration available. While other plugins offer basic contact sync, this plugin delivers enterprise-level automation with deep integrations across your entire WordPress ecosystem.

## What Makes This Different

**Compared to LeadConnector Plugin**: Basic contact sync vs complete custom objects integration, family relationships, BuddyBoss groups sync, and conditional menu displays.

**Compared to Wizard Plugin**: Simple field mapping vs bi-directional sync with webhook automation, role-based tagging, and real-time updates.

**Compared to WP Fusion for GHL**: Tag-based access control vs everything WP Fusion offers PLUS custom objects, OAuth2 security, native BuddyBoss integration, and LearnDash deep integration.

This plugin combines the power of WP Fusion, Memberium, and custom development - all built specifically for GoHighLevel users.

---

## 🚀 Complete Feature List

### Core Foundation

#### OAuth2 Secure Authentication
- One-click secure connection to GoHighLevel
- Automatic token refresh with reconnection fallback
- Multi-location support for agencies
- HTTPS-secured connections
- Per-site connections for WordPress Multisite

#### Advanced Field Mapping
- Visual drag-and-drop field mapper
- Duplicate prevention system
- Bi-directional sync control (To GHL, From GHL, Both Ways)
- BuddyBoss XProfile field mapping
- WooCommerce order and customer field mapping
- LearnDash course and progress field mapping
- Custom plugin field detection
- Real-time field list refresh

#### WordPress User Synchronization
- Automatic contact creation on registration
- Profile update syncing
- User deletion handling (keep or delete contact)
- Login tracking
- Password reset tracking
- Custom field synchronization
- Default tags on registration
- Bulk user import/export

---

### Advanced Automation Features

#### Role-Based Tagging System
- Automatic tag assignment when users get roles
- Automatic tag removal when users lose roles
- Global tags for all synced users
- Custom tag prefixes for organization
- Registration source tracking tags
- WooCommerce customer auto-tagging
- Bulk tag operations (add/remove tags for entire roles)
- Queue-based reliable processing

#### Membership & Content Restrictions
- Tag-based content access control
- Lock pages, posts, products, courses, custom post types
- Flexible access rules (ANY, ALL, NONE tag logic)
- Custom redirect URLs for unauthorized users
- Custom access denied messages
- Archive and search result protection
- REST API access protection
- Admin override capability
- Bypass tags for VIP access

#### White Label Domain Support
- Configure custom GoHighLevel white label domains
- Links to GHL records use your branded domain
- Professional client-facing URLs
- Agency-ready branding

---

### Deep Platform Integrations

#### WooCommerce Integration
- Customer sync on order completion
- Product-based tagging (tag customers by products purchased)
- Order value tracking
- Purchase history synchronization
- Order status tracking
- Abandoned cart tracking
- Customer lifetime value calculation
- Product categories to tags mapping
- Subscription status sync (WooCommerce Subscriptions)
- Custom order meta field mapping

#### BuddyBoss / BuddyPress Integration
**ONLY plugin with native BuddyBoss XProfile sync**
- XProfile field synchronization to GHL custom fields
- Profile photo syncing
- Member type tagging
- Activity tracking and engagement metrics
- BuddyBoss Groups to GHL Custom Objects sync
- Group membership tracking
- Private message tracking
- Friend connections tracking

#### LearnDash Integration
- Course enrollment synchronization
- Course progress tracking by percentage
- Lesson completion tracking
- Topic completion tracking
- Quiz score and results sync
- Certificate achievement tracking
- Assignment submission tracking
- Course category to tags mapping
- Student engagement metrics
- Drip content access control via GHL tags

---

### Custom Objects & Forms

#### Custom Objects Integration
**Unique feature not available in competing plugins**
- Map GHL custom objects to WordPress custom post types
- Sync GHL form submissions to WordPress
- Sync survey responses
- Create custom object associations
- BuddyBoss Groups sync to custom objects
- Visual field mapper for custom objects
- Bi-directional custom object sync
- Bulk operations for custom object records
- Family relationships system (like Memberium)
  - Parent/child relationships
  - Spouse relationships
  - Family member management
  - Household grouping

#### GoHighLevel Forms Integration
- Embed GHL forms with shortcodes
- Pre-fill form fields for logged-in users
- Show forms only to logged-in users
- Show forms only to users with specific tags
- Conditional form display rules
- Form submission tracking
- Custom success/error messages
- Form analytics and conversion tracking

---

### Webhook & Real-Time Sync

#### Webhook Automation
- Bidirectional WordPress ↔ GoHighLevel real-time sync
- Automatic webhook registration via API
- Signature-based webhook verification
- Event filtering (choose which events trigger webhooks)
- Automatic retry on failures
- Debug mode with detailed logging
- Webhook activity monitoring
- Custom webhook endpoints

#### Sync Queue Management
- WordPress Action Scheduler integration
- Priority-based queue (High, Medium, Low priority)
- Automatic retry with exponential backoff
- Rate limiting compliance (100 requests/10 seconds, 200k/day)
- Queue status dashboard with real-time statistics
- Manual queue controls (pause, resume, clear, retry)
- Detailed sync operation logging
- Performance optimization with batch processing

---

### Advanced User Management

#### User Profile Enhancements
- Generate secure auto-login links (1-72 hour expiration)
- One-time use tokens for security
- Copy-to-clipboard functionality
- Token expiration management
- Visual feedback on link generation
- User activity tracking
- Last login tracking
- Registration date tracking

#### REST API Endpoints
- Custom REST API for third-party integrations
- Secure authentication
- Rate limiting
- Webhook endpoints for external services
- Custom endpoint creation
- API documentation included

---

### System Management & Monitoring

#### Comprehensive Sync Logs
- Categorized sync activity (User, Contact, LearnDash, WooCommerce, BuddyBoss, Custom Objects)
- Priority-based logging (Critical, High, Medium, Low)
- Real-time sync status monitoring
- Detailed error reporting with stack traces
- Search and filter functionality
- Date range filtering
- Export logs for analysis
- Sync success/failure statistics
- Performance metrics

#### System Health & Diagnostics
- Connection status verification
- API connectivity testing
- OAuth token validation
- Queue health monitoring
- Database integrity checks
- Server requirement validation
- Plugin conflict detection
- Performance recommendations
- Automated health checks

#### Tools & Utilities
- Settings export/import for backup
- Cache management (clear API response cache)
- Settings reset (preserves OAuth connection)
- Bulk user operations
- Database optimization
- Error log viewer
- System information display
- Debug mode toggle

---

### User Interface & Experience

#### Modern SPA Interface
- Single-page application design
- No page reloads for settings changes
- Instant feedback on all actions
- SweetAlert2 toast notifications
- Modal confirmations
- Tabbed settings organization
- Mobile-responsive design
- Dark mode compatible

#### Dashboard & Analytics
- Real-time statistics
- Sync activity graphs
- Queue status overview
- Recent sync activity feed
- Error rate monitoring
- API usage statistics
- Performance metrics
- Quick action buttons

---

### WordPress Multisite Support

**Enterprise-Ready Multi-Site Features**
- Per-site OAuth connections
- Site-specific settings and field mappings
- Isolated sync queues per site
- Network admin controls
- Bulk site configuration
- Site switching support
- Shared settings option
- Multi-location agency support

---

### Developer Features

#### Extensibility
- Comprehensive hook system
- Filters for modifying sync data
- Actions for custom automation
- REST API for integrations
- Custom field registration API
- Integration framework for plugins
- Clean code architecture
- PSR-4 autoloading
- WordPress coding standards compliant

#### Performance Optimization
- Efficient database queries
- Caching layer for API responses
- Batch processing for bulk operations
- Rate limit compliance
- Memory usage optimization
- Background processing
- Lazy loading for admin interface
- Minimal front-end impact

---

### Security Features

- OAuth2 industry-standard authentication
- Automatic token encryption
- Nonce verification on all AJAX requests
- Capability checks for all operations
- Input sanitization and validation
- Output escaping for XSS protection
- HTTPS requirement for OAuth
- Secure webhook signature verification
- Rate limiting protection
- No external dependencies (zero third-party PHP libraries)
- Regular security audits

---

## 📋 Use Cases

### For Marketing Agencies
- Manage 50+ client sites with individual GHL locations
- Per-site OAuth connections and settings
- White-label domain support
- Bulk operations across multiple sites
- Network-level controls

### For eCommerce Stores
- Tag customers by products purchased
- Sync order values and purchase history
- Abandoned cart automation
- Customer lifetime value tracking
- Product recommendations based on tags

### For Course Creators
- Tag students by course progress
- Lock lessons via GHL tags
- Track quiz scores and certificates
- Drip content based on GHL triggers
- Student engagement analytics

### For Membership Sites
- Restrict content by GHL tags
- Flexible access rules (ANY/ALL/NONE tags)
- Custom redirects for unauthorized users
- Archive and search protection
- Membership level management from GHL

### For Community Platforms
- Sync BuddyBoss XProfile fields (ONLY plugin to do this)
- Map BuddyBoss Groups to GHL Custom Objects
- Track member engagement
- Member type tagging
- Activity-based automation

---

## 📦 Requirements

- WordPress 5.8+ (6.0+ recommended)
- PHP 7.4+ (8.0+ recommended)
- GoHighLevel account with OAuth app configured
- HTTPS (required for OAuth2)
- WordPress Cron or server cron

### Optional Requirements
- WooCommerce 5.0+ for eCommerce features
- BuddyBoss/BuddyPress for community features
- LearnDash 3.0+ for LMS features

### Recommended Server Settings
- PHP Memory Limit: 256MB+
- Max Execution Time: 300 seconds
- WordPress Cron enabled

---

## 🔧 Installation

1. Upload plugin to `/wp-content/plugins/crm-integration`
2. Activate through WordPress admin
3. Navigate to **GHL CRM** menu
4. Click **"Connect with GoHighLevel"**
5. Authorize the OAuth connection
6. Configure field mappings
7. Enable desired integrations
8. Start automating!

Full setup guide included in plugin documentation.

---

## ⚙️ Configuration Overview

### Quick Setup Steps
1. **OAuth Connection** - One-click secure connection
2. **Field Mapping** - Map WordPress fields to GHL fields
3. **Enable User Sync** - Turn on automatic synchronization
4. **Role Tags** (optional) - Configure role-based tagging
5. **Content Restrictions** (optional) - Set up tag-based access control
6. **Webhooks** (optional) - Enable real-time sync
7. **Custom Objects** (optional) - Map custom data structures
8. **Forms** (optional) - Embed and configure GHL forms

---

## 📊 What's Included

- Complete OAuth2 authentication system
- Visual field mapping interface
- Role-based tagging automation
- Content restriction engine
- WordPress user sync
- WooCommerce deep integration
- BuddyBoss XProfile sync
- LearnDash progress tracking
- Custom objects integration
- Family relationships system
- GoHighLevel forms integration
- Webhook automation
- Queue management system
- Comprehensive sync logs
- System health monitoring
- REST API endpoints
- WordPress Multisite support
- White label domain support
- Setup wizard (coming soon)

---

## 🎯 Why Choose This Plugin

✅ **Most comprehensive** GHL integration available
✅ **Only plugin** with BuddyBoss XProfile sync
✅ **Only plugin** with custom objects integration
✅ **Only plugin** with family relationships
✅ **Deep integrations** - not just basic contact sync
✅ **OAuth2 security** - no API keys needed
✅ **Real-time sync** - webhook automation
✅ **Enterprise ready** - multisite support
✅ **Modern UI** - clean SPA interface
✅ **Reliable** - Action Scheduler queue system
✅ **Well documented** - extensive documentation included
✅ **Regular updates** - active development
✅ **Priority support** - Pro version available

---

## 🚀 Roadmap

### Coming Soon
- Setup wizard for easier onboarding
- Gravity Forms integration
- Elementor Forms integration
- Contact Form 7 integration
- Multi-location switcher
- Advanced automation builder
- Enhanced analytics dashboard

---

## 📄 License

GPL v2 or later

---

## 👨‍💻 Developer

**Yahya Eddaqqaq**
- Email: yahyadard@gmail.com
- Website: [yahyadev.com](https://yahyadev.online/)
- GitHub: [@Cottonnion](https://github.com/Cottonnion)
- Company: [LabGenz](https://labgenz.com/)

Specialized in WordPress plugin development, CRM integrations, and enterprise solutions.

---

**Transform your WordPress site into a CRM automation powerhouse. Get started today.**