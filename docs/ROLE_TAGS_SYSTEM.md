# Role-Based Tags System

## Overview

The role-based tags system automatically assigns and removes GoHighLevel tags based on WordPress user roles. This allows you to maintain synchronized tag management between WordPress roles and GHL contact tags, enabling better segmentation and automation workflows.

---

## Features

✅ **Automatic Tag Assignment** - Tags are automatically added when users are assigned a role  
✅ **Automatic Tag Removal** - Tags can be removed when users lose a role  
✅ **Global Tags** - Apply tags to all synced users regardless of role  
✅ **Tag Prefix** - Add a prefix to all WordPress-generated tags  
✅ **Special Tags** - Registration source and WooCommerce customer tags  
✅ **Bulk Operations** - Add or remove tags for all users with a specific role  
✅ **Queue-Based** - All tag operations are queued for reliable background processing  
✅ **Multisite Compatible** - Works with WordPress Multisite installations

---

## Configuration

### Location
**Settings → Role Tags** (in plugin admin menu)

### Settings Structure

#### 1. Role Tag Mappings
For each WordPress role, configure:
- **Tags**: Comma-separated list of tags to assign
- **Auto-Apply**: Automatically add tags when user gets this role
- **Remove on Role Change**: Remove tags when user loses this role

Example:
- **Subscriber** → Tags: `subscriber, free-member` (Auto-apply: Yes, Remove: Yes)
- **Customer** → Tags: `customer, paying-member` (Auto-apply: Yes, Remove: No)
- **Administrator** → Tags: `admin, staff` (Auto-apply: Yes, Remove: Yes)

#### 2. Additional Tag Settings

**Global Tags**
- Comma-separated tags applied to ALL synced contacts
- Example: `wordpress, site-member`
- Applied regardless of user role

**Registration Source Tag**
- When enabled, adds `wordpress-registration` tag on new user sync
- Useful for tracking registration source in GHL

**WooCommerce Customer Tag**
- When enabled, adds `woocommerce-customer` tag for users with orders
- Requires WooCommerce to be active
- Automatically detects users with purchase history

**Preserve Existing Tags**
- When enabled (default), keeps existing GHL tags when syncing
- When disabled, replaces all tags with role-based tags
- Recommendation: Keep enabled to preserve manual tags in GHL

**Tag Prefix**
- Optional prefix added to all WordPress-generated tags
- Example: `wp-` → produces `wp-subscriber`, `wp-administrator`
- Helps identify WordPress-originated tags in GHL

#### 3. Bulk Tag Operations

**Add Tags to Role**
1. Select a WordPress role
2. Enter comma-separated tags
3. Click "Add Tags to Role"
4. All users with that role are queued for tag addition

**Remove Tags from Role**
1. Select a WordPress role
2. Enter comma-separated tags
3. Click "Remove Tags from Role"
4. All users with that role are queued for tag removal

> **Note**: Bulk operations only affect users already synced with GHL (have `_ghl_contact_id` meta)

---

## How It Works

### Automatic Tag Assignment

#### On User Registration
1. User registers in WordPress
2. User is assigned a role (default: Subscriber)
3. System checks role tag configuration
4. If auto-apply is enabled, tags are queued for addition
5. Registration tags (if configured) are also added
6. Global tags and special tags are included
7. All tags are sent to GHL when contact is created

#### On Role Change
**When role is added**:
1. WordPress fires `add_user_role` action
2. `RoleTagsManager` checks if auto-apply is enabled for the new role
3. Tags are queued for addition via `QueueManager`
4. Background processor adds tags to GHL contact

**When role is removed**:
1. WordPress fires `remove_user_role` action
2. `RoleTagsManager` checks if remove-on-change is enabled
3. Tags are queued for removal via `QueueManager`
4. Background processor removes tags from GHL contact

**When role is changed (set_user_role)**:
1. WordPress fires `set_user_role` action with old and new roles
2. System removes tags from old role (if configured)
3. System adds tags for new role (if configured)
4. Both operations are queued independently

#### On Profile Update
1. User profile is updated in WordPress
2. System recalculates all role-based tags
3. Tags are included in sync payload
4. GHL contact is updated with current tag set

### Tag Calculation

The `get_user_role_tags()` method combines tags from:
1. All user's current roles
2. Global tags (from settings)
3. Registration source tag (if enabled)
4. WooCommerce customer tag (if eligible)
5. Tag prefix applied to role tags

---

## Technical Architecture

### Classes

#### `RoleTagsManager` (`src/Integrations/Users/RoleTagsManager.php`)

**Responsibilities**:
- Hook into WordPress role change actions
- Calculate tags for users based on roles
- Queue tag addition/removal operations
- Handle bulk tag operations via AJAX

**Key Methods**:
- `handle_role_change($user_id, $new_role, $old_roles)` - Main role change handler
- `handle_role_added($user_id, $role)` - Handle role addition
- `handle_role_removed($user_id, $role)` - Handle role removal
- `get_user_role_tags($user_id)` - Calculate all tags for a user
- `ajax_bulk_add_role_tags()` - AJAX handler for bulk add
- `ajax_bulk_remove_role_tags()` - AJAX handler for bulk remove

**WordPress Hooks**:
```php
add_action('set_user_role', [$this, 'handle_role_change'], 10, 3);
add_action('add_user_role', [$this, 'handle_role_added'], 10, 2);
add_action('remove_user_role', [$this, 'handle_role_removed'], 10, 2);
```

#### `QueueProcessor` (`src/Sync/QueueProcessor.php`)

**New Actions Supported**:
- `add_tags` - Add tags to a GHL contact
- `remove_tags` - Remove tags from a GHL contact

**Payload Structure**:
```php
[
    'action'      => 'add_tags', // or 'remove_tags'
    'user_id'     => 123,
    'contact_id'  => 'ghl_contact_id',
    'tags'        => ['tag1', 'tag2'],
    'source'      => 'role_change',
]
```

#### Integration with `UserHooks`

**Modified Methods**:
- `on_user_register()` - Now includes role-based tags
- `on_user_update()` - Now includes role-based tags

**Tag Combination**:
```php
// Registration tags + Role-based tags
$all_tags = array_merge($register_tags, $role_based_tags);
$all_tags = array_unique($all_tags);
$contact_data['tags'] = $all_tags;
```

---

## Database Schema

### Settings Storage

All role tag settings stored in `wp_options` table (or `wp_sitemeta` for multisite):

```php
'role_tags' => [
    'subscriber' => [
        'role' => 'subscriber',
        'tags' => 'subscriber, free-member',
        'auto_apply' => true,
        'remove_on_change' => true,
    ],
    'customer' => [
        'role' => 'customer',
        'tags' => 'customer, paying-member',
        'auto_apply' => true,
        'remove_on_change' => false,
    ],
    // ... other roles
],
'global_tags' => 'wordpress, site-member',
'registration_source_tag' => true,
'woocommerce_customer_tag' => true,
'sync_existing_tags' => true,
'tag_prefix' => 'wp-',
```

### User Meta

No additional user meta fields required. Uses existing:
- `_ghl_contact_id` - GHL contact ID (from existing sync)
- `_ghl_contact_tags` - Current tags (synced from GHL)

---

## API Integration

### GHL API Endpoints Used

**Add Tags**:
```
POST /contacts/{contactId}/tags
Body: { "tags": ["tag1", "tag2"] }
```

**Remove Tags**:
```
DELETE /contacts/{contactId}/tags
Body: { "tags": ["tag1", "tag2"] }
```

### Queue Integration

Tag operations use the same queue system as other sync operations:
1. Operation is added to queue via `QueueManager::add_to_queue()`
2. Action Scheduler picks up the job
3. `QueueProcessor::execute_sync()` routes to tag handler
4. `ContactResource::add_tags()` or `remove_tags()` is called
5. Result is logged via `QueueLogger`

---

## Best Practices

### 1. Tag Naming
- Use descriptive, lowercase tags: `premium-member` not `PM`
- Use hyphens for multi-word tags: `gold-subscriber`
- Be consistent with naming across all roles

### 2. Auto-Apply vs Manual
- **Auto-Apply**: Use for tags that should always be added
- **Manual**: Disable for tags that need approval

### 3. Remove on Role Change
- **Enable**: For exclusive role tags (subscriber, customer, admin)
- **Disable**: For cumulative tags (purchased-course, attended-webinar)

### 4. Tag Prefix Usage
- **Use prefix**: When managing multiple WordPress sites in one GHL account
- **Skip prefix**: When WordPress is the only contact source

### 5. Global Tags
- Use for site-wide identification: `wordpress`, `site-member`
- Avoid role-specific tags in global settings

### 6. Bulk Operations
- Test on a small role first (e.g., Administrator role with 1-2 users)
- Monitor sync logs after bulk operation
- Use during off-peak hours for large user bases

---

## Troubleshooting

### Tags Not Being Added

**Check**:
1. ✅ GHL connection is active (Settings → Main Settings)
2. ✅ User has `_ghl_contact_id` meta (synced with GHL)
3. ✅ Auto-apply is enabled for the role
4. ✅ Tags field is not empty
5. ✅ Queue is processing (check Sync Logs)

**Debug**:
```php
// Check if user has contact ID
$contact_id = get_user_meta($user_id, '_ghl_contact_id', true);

// Check role tag settings
$settings = get_option('ghl_crm_settings');
$role_tags = $settings['role_tags']['subscriber'] ?? [];
```

### Tags Not Being Removed

**Check**:
1. ✅ "Remove on Role Change" is enabled
2. ✅ User actually lost the role (not just changed)
3. ✅ Tags match exactly (case-sensitive in some GHL accounts)

### Bulk Operations Not Working

**Check**:
1. ✅ Users have `_ghl_contact_id` meta
2. ✅ Action Scheduler is running (Tools → Scheduled Actions)
3. ✅ No API rate limit errors in logs

### Duplicate Tags

**Cause**: Multiple systems adding same tags  
**Solution**: Use tag prefix to identify WordPress tags

---

## Use Cases

### 1. Membership Site
```
Subscriber    → free-member
Customer      → paid-member, premium
Administrator → staff, admin
```

### 2. Online Course Platform
```
Student       → student, enrolled
Instructor    → instructor, teacher
Administrator → staff, support
```

### 3. WooCommerce Store
```
Customer      → customer
Subscriber    → newsletter
Shop Manager  → staff, manager
```

### 4. Multisite Network
```
Global Tags: site-1, wordpress
Prefix: s1-
Results: s1-subscriber, s1-customer
```

---

## Future Enhancements

- ⏳ Conditional tags based on user meta
- ⏳ Time-based tag expiration
- ⏳ Tag rules based on purchase history
- ⏳ Integration with BuddyBoss groups
- ⏳ Integration with LearnDash courses
- ⏳ Tag analytics dashboard

---

## Support

For issues or questions:
1. Check WordPress debug log for errors
2. Review Sync Logs in plugin admin
3. Verify GHL connection status
4. Check Action Scheduler status

---

## Related Documentation

- [Queue System](QUEUE_SYSTEM.md)
- [Field Mapping](FIELD_MAPPING_IMPLEMENTATION.md)
- [Membership System](MEMBERSHIP_SYSTEM.md)
- [Webhook System](WEBHOOK_SYSTEM.md)
