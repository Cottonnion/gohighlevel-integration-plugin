# OAuth Scopes Configuration

## Overview
This document explains the GoHighLevel API scopes used by this plugin and why each scope is required.

---

## Current Scopes (Production)

The plugin uses the following OAuth scopes:

```
contacts.readonly
contacts.write
locations/tags.readonly
locations/tags.write
locations/customFields.readonly
locations/customFields.write
```

---

## Scope Breakdown

### 1. `contacts.readonly` ✅
**Required for:**
- Searching contacts by email/phone
- Reading contact details
- Getting contact information before updates
- Checking if contact exists (duplicate prevention)
- Reading contact notes
- Reading contact tasks
- Reading contact appointments

**Used in:**
- `ContactResource::find_by_email()`
- `ContactResource::find_by_phone()`
- `ContactResource::get_notes()`
- `ContactResource::get_tasks()`
- `ContactResource::get_appointments()`
- `QueueManager::get_cached_contact()`

---

### 2. `contacts.write` ✅
**Required for:**
- Creating new contacts
- Updating existing contacts
- Deleting contacts
- Adding notes to contacts
- Adding contacts to workflows

**Used in:**
- `ContactResource::create()`
- `ContactResource::update()`
- `ContactResource::delete()`
- `ContactResource::upsert()`
- `ContactResource::add_note()`
- `ContactResource::add_to_workflow()`
- `UserHooks::on_user_register()`
- `UserHooks::on_user_update()`
- `UserHooks::on_user_delete()`

---

### 3. `locations/tags.readonly` ✅
**Required for:**
- Reading existing tags on contacts
- Listing available tags in location
- Checking which tags are already assigned

**Used in:**
- Tag management (before adding/removing)
- Field mapping UI (showing available tags)
- Role-based tag assignments

---

### 4. `locations/tags.write` ✅
**Required for:**
- Adding tags to contacts
- Removing tags from contacts
- Managing contact tags based on:
  - WordPress user roles
  - Registration source
  - WooCommerce purchases
  - BuddyBoss group membership

**Used in:**
- `ContactResource::add_tags()`
- `ContactResource::remove_tags()`
- `UserHooks::maybe_add_tags()`
- Role-based tag sync
- Integration-specific tagging

---

### 5. `locations/customFields.readonly` ✅
**Required for:**
- Reading custom field definitions
- Getting list of available custom fields
- Displaying custom fields in field mapping UI
- Validating custom field data types

**Used in:**
- Field mapping page
- Settings page (showing available fields)
- Custom field dropdown population
- Field type validation

---

### 6. `locations/customFields.write` ✅
**Required for:**
- Creating custom fields dynamically (if needed)
- Updating custom field values on contacts
- Syncing WordPress user meta to GHL custom fields
- BuddyBoss XProfile field sync

**Used in:**
- `UserHooks::prepare_contact_data()` - Adds `customField` array
- Contact sync with custom data:
  - `wp_user_id`
  - `wp_user_login`
  - `wp_user_role`
  - `last_login`
  - Any mapped custom fields

---

## Why These Scopes?

### Essential for Core Functionality
The plugin's **primary purpose** is to sync WordPress users with GoHighLevel contacts. This requires:

1. **Reading contacts** - Check if user already exists in GHL
2. **Writing contacts** - Create/update user data
3. **Managing tags** - Tag users by role, registration source, etc.
4. **Managing custom fields** - Store WordPress-specific data (user ID, login, role, etc.)

### Privacy & Security
- ✅ **Minimal permissions** - Only requests scopes actually used
- ✅ **No user data access** - Doesn't request `users.readonly` or `users.write`
- ✅ **No location settings** - Doesn't request `locations.write`
- ✅ **No billing access** - Doesn't request payment-related scopes

---

## Scopes NOT Used (But Available)

These scopes are available in GoHighLevel but **not requested** by this plugin:

### Not Required:
- ❌ `locations.readonly` - We get location info from OAuth token response
- ❌ `locations.write` - Don't modify location settings
- ❌ `users.readonly` - Don't need GHL user data
- ❌ `users.write` - Don't modify GHL users
- ❌ `workflows.readonly` - Don't need to read workflow definitions
- ❌ `workflows.write` - Use `contacts.write` to add contacts to workflows
- ❌ `calendars.readonly` - Not implemented yet
- ❌ `calendars.write` - Not implemented yet
- ❌ `opportunities.readonly` - Planned for WooCommerce orders
- ❌ `opportunities.write` - Planned for WooCommerce orders
- ❌ `companies.readonly` - Planned for BuddyBoss groups
- ❌ `companies.write` - Planned for BuddyBoss groups

---

## Future Scope Requirements

### Planned Features (Not Yet Implemented)

#### WooCommerce Orders → Opportunities
```
+ opportunities.readonly
+ opportunities.write
```

**Will enable:**
- Create GHL opportunities from WooCommerce orders
- Track order value, products, status
- Link opportunities to customer contacts

#### BuddyBoss Groups → GHL Companies
```
+ companies.readonly
+ companies.write
```

**Will enable:**
- Create GHL companies from BuddyBoss groups
- Sync group members as company contacts
- Track group membership

#### Appointment Booking
```
+ calendars.readonly
+ calendars.write
```

**Will enable:**
- Create appointments in GHL calendars
- Sync WordPress events to GHL
- Book appointments from WordPress forms

---

## OAuth App Configuration

### Creating Your OAuth App

1. **Login to GoHighLevel** (Agency account)
2. **Go to Settings → OAuth**
3. **Click "Create New OAuth App"**
4. **Fill in details:**
   - **App Name**: `WordPress CRM Integration`
   - **Redirect URL**: `https://labgenz.com/` (or your actual redirect URL)
   - **Scopes**: Select these 6 scopes:
     - ✅ `contacts.readonly`
     - ✅ `contacts.write`
     - ✅ `locations/tags.readonly`
     - ✅ `locations/tags.write`
     - ✅ `locations/customFields.readonly`
     - ✅ `locations/customFields.write`
5. **Click "Create"**
6. **Copy Client ID and Client Secret**

### Current Configuration

**Client ID:**
```
68ff9baa25051d0ca83341e9-mh9cljcg
```

**Client Secret:**
```
(Stored securely in Client.php constant)
```

**Redirect URL:**
```
https://labgenz.com/
```

**Authorization URL:**
```
https://marketplace.gohighlevel.com/oauth/chooselocation?response_type=code&redirect_uri=https%3A%2F%2Flabgenz.com%2F&client_id=68ff9baa25051d0ca83341e9-mh9cljcg&scope=contacts.readonly+contacts.write+locations%2Ftags.readonly+locations%2Ftags.write+locations%2FcustomFields.readonly+locations%2FcustomFields.write
```

---

## Testing Scopes

### Verify All Scopes Work

**1. Test Contact Operations:**
```php
// Create contact (requires contacts.write)
$contact = $contact_resource->create([
    'email' => 'test@example.com',
    'firstName' => 'Test',
]);

// Read contact (requires contacts.readonly)
$existing = $contact_resource->find_by_email('test@example.com');

// Update contact (requires contacts.write)
$updated = $contact_resource->update($contact['id'], [
    'lastName' => 'User',
]);
```

**2. Test Tag Operations:**
```php
// Add tags (requires locations/tags.write)
$contact_resource->add_tags($contact['id'], ['wordpress-user', 'subscriber']);

// Remove tags (requires locations/tags.write)
$contact_resource->remove_tags($contact['id'], ['test-tag']);
```

**3. Test Custom Fields:**
```php
// Write custom fields (requires locations/customFields.write)
$contact_resource->update($contact['id'], [
    'customField' => [
        'wp_user_id' => '123',
        'wp_user_role' => 'subscriber',
    ],
]);
```

---

## Troubleshooting

### "Insufficient Scopes" Error

**Error:**
```
The token does not have the required scope
```

**Solutions:**
1. Check OAuth app configuration in GHL
2. Verify all 6 scopes are enabled
3. Reconnect the plugin (tokens refresh with new scopes)
4. Clear any cached tokens

### "Invalid Scope" Error

**Error:**
```
Invalid scope requested
```

**Cause:** Scope name typo or unavailable scope

**Fix:**
- Verify scope names exactly match GHL API
- Use forward slashes: `locations/tags.write` (not `locations.tags.write`)
- Check your GHL plan supports requested scopes

### Tags Not Syncing

**Check:**
1. ✅ `locations/tags.write` scope enabled
2. ✅ OAuth token has correct permissions
3. ✅ Tag names are valid (no special characters)
4. ✅ Location allows tag creation

### Custom Fields Not Saving

**Check:**
1. ✅ `locations/customFields.write` scope enabled
2. ✅ Custom field exists in GHL location
3. ✅ Field names match exactly (case-sensitive)
4. ✅ Data type matches field definition

---

## Security Best Practices

### 1. Scope Minimization ✅
- Only request scopes you actually use
- Current scopes are minimal for plugin functionality

### 2. Token Security ✅
- Client Secret stored as PHP constant (not in database)
- Access tokens encrypted in WordPress options
- Refresh tokens used for long-term access

### 3. Regular Audits
- Review scope usage quarterly
- Remove unused scopes
- Monitor API access logs in GHL

### 4. User Transparency
- Display required scopes in plugin documentation
- Explain why each scope is needed
- Allow users to review permissions

---

## Summary

### Current Scopes: 6 Total ✅

| Scope | Purpose | Status |
|-------|---------|--------|
| `contacts.readonly` | Read contact data | ✅ Active |
| `contacts.write` | Create/update contacts | ✅ Active |
| `locations/tags.readonly` | Read tags | ✅ Active |
| `locations/tags.write` | Manage tags | ✅ Active |
| `locations/customFields.readonly` | Read custom fields | ✅ Active |
| `locations/customFields.write` | Update custom fields | ✅ Active |

### Scope Benefits:
- ✅ **Minimal permissions** - Only what's needed
- ✅ **Full functionality** - All current features work
- ✅ **Secure** - No unnecessary data access
- ✅ **Future-ready** - Easy to add scopes for new features