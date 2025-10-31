# Membership & Access Control System

## Overview

The membership system allows you to control access to pages, posts, products, and courses based on GoHighLevel contact tags. Users must have specific tags to view restricted content.

---

## Features

✅ **Tag-Based Access Control** - Restrict content based on GHL tags  
✅ **Flexible Rules** - ANY tag, ALL tags, or NONE of these tags  
✅ **Custom Redirects** - Redirect denied users to a specific URL  
✅ **Archive Protection** - Hide restricted content in archives/excerpts  
✅ **Multiple Post Types** - Pages, Posts, WooCommerce Products, LearnDash Courses  
✅ **Admin Override** - Administrators always have access  
✅ **Multisite Compatible** - Works with WordPress Multisite

---

## How to Restrict Content

### 1. Edit a Page, Post, or Product

Go to the edit screen for any page, post, WooCommerce product, or LearnDash course.

### 2. Find the "GHL Membership Restrictions" Meta Box

Look for the meta box in the sidebar (usually on the right side).

### 3. Choose a Restriction Type

**No Restrictions (Default)**  
- Content is accessible to everyone

**User has ANY of these tags**  
- User needs at least ONE of the selected tags to access  
- Example: User has either "Premium" OR "VIP" tag

**User has ALL of these tags**  
- User needs ALL selected tags to access  
- Example: User must have both "Premium" AND "Active" tags

**User does NOT have these tags**  
- User must NOT have any of the selected tags  
- Example: Block users with "Suspended" or "Cancelled" tags

### 4. Select Tags

Use the tag selector (Select2) to:
- Search for existing GHL tags
- Type new tag names
- Select multiple tags

### 5. Set Redirect URL (Optional)

If you want to redirect denied users to a specific page:
- Enter the full URL (e.g., `https://example.com/membership`)
- Leave empty to show a default "Access Denied" message

### 6. Publish/Update

Click "Publish" or "Update" to save the restrictions.

---

## Access Rules Examples

### Example 1: Premium Membership Page
**Restriction:** User has ANY of these tags  
**Tags:** `premium`, `vip`, `pro`  
**Result:** User needs at least one of these tags to access

### Example 2: Advanced Course
**Restriction:** User has ALL of these tags  
**Tags:** `active`, `advanced-tier`, `paid`  
**Result:** User must have all three tags to access

### Example 3: Block Suspended Users
**Restriction:** User does NOT have these tags  
**Tags:** `suspended`, `cancelled`, `banned`  
**Result:** Access denied if user has any of these tags

---

## User Experience

### For Logged-In Users (With Access)
- Content displays normally
- No restrictions applied

### For Logged-In Users (Without Access)
- If redirect URL is set: Redirected to that URL
- If no redirect: Shows "Access Restricted" message
- Message includes back link

### For Logged-Out Users
- If redirect URL is set: Redirected to that URL
- If no redirect: Shows login prompt with message:  
  _"Please log in to access this content"_

### On Archive Pages
- Restricted content shows lock icon 🔒
- Message: "This content is restricted"
- Login link for logged-out users

---

## Supported Post Types

✅ **Pages** - WordPress pages  
✅ **Posts** - WordPress blog posts  
✅ **Products** - WooCommerce products (if WooCommerce active)  
✅ **Courses** - LearnDash courses (if LearnDash active)  
✅ **Lessons** - LearnDash lessons (if LearnDash active)  
✅ **Topics** - LearnDash topics (if LearnDash active)

---

## Technical Details

### Post Meta Fields
- `_ghl_restriction_type` - Restriction type (has_any_tag, has_all_tags, not_has_tags)
- `_ghl_required_tags` - Array of required tags
- `_ghl_redirect_url` - Custom redirect URL (overrides global default)

### User Meta Fields
- `_ghl_contact_tags` - Array of user's GHL tags (synced automatically)

### Global Settings
- `restrictions_enabled` - Master toggle for entire restrictions system
- `restrictions_default_redirect` - Global default redirect URL
- `restrictions_denied_title` - Title for access denied page
- `restrictions_denied_message` - Message for logged-in users without access
- `restrictions_login_message` - Message for logged-out users
- `restrictions_archive_message` - Short message for archive listings
- `restrictions_show_login_link` - Whether to show login link
- `restrictions_allow_admins` - Allow administrators to bypass restrictions
- `restrictions_hide_archives` - Completely hide restricted posts from archives

### Filters

**`ghl_crm_should_deny_access`**  
Modify access denial behavior
```php
add_filter('ghl_crm_should_deny_access', function($should_deny, $post_id, $reason) {
    // Custom logic
    return $should_deny;
}, 10, 3);
```

**`ghl_crm_access_denial_message`**  
Customize denial message
```php
add_filter('ghl_crm_access_denial_message', function($message, $post_id) {
    return 'Custom message here';
}, 10, 2);
```

**`ghl_crm_denial_page_content`**  
Customize full denial page
```php
add_filter('ghl_crm_denial_page_content', function($content, $post_id, $reason) {
    return '<h1>Access Denied</h1><p>Custom content</p>';
}, 10, 3);
```

**`ghl_crm_restricted_content_message`**  
Customize archive excerpt message
```php
add_filter('ghl_crm_restricted_content_message', function($message, $post_id, $reason) {
    return '<div>Custom restricted message</div>';
}, 10, 3);
```

---

## Tag Syncing

Tags are automatically synced when:
- User is created/updated in WordPress
- User's GHL contact is updated
- Manual sync is triggered from user profile

Users' tags are stored in `_ghl_contact_tags` user meta.

---

## Admin Override

Users with `manage_options` capability (usually Administrators) can **always access restricted content**, regardless of their tags. This ensures admins can manage content without restrictions.

---

## Multisite Notes

- Restrictions are **site-specific** (each site has its own rules)
- Tags are synced per-site
- Network admins have access to all content

---

## Best Practices

1. **Test Before Publishing** - Create a test page and verify restrictions work
2. **Clear Tag Names** - Use descriptive tag names (e.g., "Premium Member" not "PM")
3. **Document Your Rules** - Keep a list of which tags grant access to which content
4. **Use Redirects Wisely** - Redirect to membership/login pages, not external sites
5. **Regular Tag Audits** - Review and clean up unused tags periodically

---

## Troubleshooting

**User has the correct tag but can't access**
- Check tag spelling (case-insensitive but spelling must match)
- Verify tag sync: Go to Users → Edit User → GHL Data section
- Trigger manual sync if needed

**Restrictions not working**
- Verify GHL connection is active (Settings → Main Settings)
- Check that Loader registered membership components
- Clear cache if using caching plugin

**Tags not showing in selector**
- Verify GHL API connection
- Check that user profile AJAX endpoint works
- Look for JavaScript errors in browser console

---

## Classes Overview

### `MetaBoxes.php`
- Renders admin meta box
- Handles saving restrictions
- Enqueues Select2 and custom JS/CSS

### `AccessControl.php`
- Core logic for checking user access
- Compares user tags against requirements
- Tag normalization (case-insensitive)

### `Restrictions.php`
- Frontend enforcement
- Hooks into `template_redirect`
- Handles redirects and denial pages
- Filters archive content

---

## Future Enhancements

- ⏳ Time-based access (expires after X days)
- ⏳ Drip content (unlock after X days)
- ⏳ Access analytics and reporting
- ⏳ Bulk restriction management
- ⏳ BuddyBoss group restrictions
- ⏳ Group-based access control

---

## Support

For issues or feature requests, please check:
- Plugin settings for connection status
- WordPress debug.log for errors
- GHL sync logs for sync issues
