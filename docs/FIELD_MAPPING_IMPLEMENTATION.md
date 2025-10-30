# Field Mapping Implementation

## Overview
Updated the user sync to **properly respect field mapping configuration**. Only fields explicitly mapped in the Field Mapping page will be synced to GoHighLevel.

## Changes Made

### File: `src/Integrations/Users/UserHooks.php`

**Method: `prepare_contact_data()`**

#### Before:
- Hardcoded field mappings:
  - `email` → always sent
  - `firstName` → hardcoded from `first_name`
  - `lastName` → hardcoded from `last_name`
  - `phone` → hardcoded lookup
- Field mapping only applied to **user meta fields**
- All standard WP fields synced regardless of mapping

#### After:
- **No hardcoded fields** (except required `source: WordPress`)
- All fields must be **explicitly mapped** in Field Mapping page
- Respects sync direction:
  - ✅ `both` - syncs WP → GHL
  - ✅ `to_ghl` - syncs WP → GHL
  - ❌ `from_ghl` - skips (only syncs GHL → WP)
- Supports both **standard user properties** and **user meta fields**
- Email fallback: If not mapped, email is still included (required by GHL API)

## Field Mapping Structure

```php
'user_field_mapping' => [
    'wp_field_name' => [
        'ghl_field' => 'ghl_field_name',  // Empty = "Do Not Sync"
        'direction' => 'both'              // 'both' | 'to_ghl' | 'from_ghl'
    ]
]
```

## Supported WordPress Fields

### Standard User Properties:
- `user_email`
- `first_name`
- `last_name`
- `display_name`
- `user_login`
- `user_url`
- `description`

### User Meta Fields:
Any custom field stored via `update_user_meta()`:
- `billing_phone`
- `phone`
- BuddyBoss profile fields
- WooCommerce customer fields
- Custom meta keys

## Example Mapping

If user maps in admin:
```
WordPress Field    → GoHighLevel Field
-----------------------------------------
user_email        → email
first_name        → firstName
last_name         → lastName
billing_phone     → phone
description       → [Do Not Sync]
```

Resulting API call:
```json
{
  "source": "WordPress",
  "email": "user@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "phone": "+1234567890",
  "customFields": [
    {"key": "wp_user_id", "value": "123"},
    {"key": "wp_user_login", "value": "johndoe"},
    {"key": "wp_user_role", "value": "subscriber"}
  ]
}
```

**Note:** `description` is NOT sent because it's not mapped.

## Debug Logging

Added logging to show which fields are synced:
```
GHL CRM: Preparing contact data for user 123 - Syncing 4 fields based on field mapping
GHL CRM: Mapped fields: source, email, firstName, lastName, phone
```

## Custom Fields (Always Included)

These WordPress-specific fields are **always sent** as custom fields:
- `wp_user_id` - WordPress user ID
- `wp_user_login` - WordPress username
- `wp_user_role` - User roles (comma-separated)

These allow you to track and identify WP users in GoHighLevel.

## Migration Notes

**Existing Installations:**
- If `user_field_mapping` is empty, **NO standard fields will sync**
- Users must configure field mapping in admin
- Email will still sync as fallback (required by GHL)

**Recommended Default Mappings:**
```php
[
    'user_email'   => ['ghl_field' => 'email', 'direction' => 'both'],
    'first_name'   => ['ghl_field' => 'firstName', 'direction' => 'both'],
    'last_name'    => ['ghl_field' => 'lastName', 'direction' => 'both'],
    'billing_phone' => ['ghl_field' => 'phone', 'direction' => 'to_ghl'],
]
```

## Testing

1. Go to **Field Mapping** page
2. Map desired fields
3. Create/update a user
4. Check error logs for: `GHL CRM: Mapped fields: ...`
5. Verify only mapped fields appear in GoHighLevel contact

## Benefits

✅ **Privacy**: Don't sync unnecessary data  
✅ **Control**: Admin decides what syncs  
✅ **Flexibility**: Different mappings per site (multisite)  
✅ **Performance**: Only send needed fields  
✅ **Compliance**: GDPR-friendly (explicit opt-in per field)
