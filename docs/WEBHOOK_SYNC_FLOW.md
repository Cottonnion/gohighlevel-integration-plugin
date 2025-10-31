# Webhook to WordPress Sync Flow

## Overview

This document explains the complete flow when a GoHighLevel webhook triggers a contact sync to WordPress, respecting field mapping directions.

---

## Complete Flow Diagram

```
GHL Webhook → WebhookHandler → Queue → QueueManager → GHLToWordPressSync → WordPress User
```

---

## Step-by-Step Process

### 1. **Webhook Arrives** (WebhookHandler.php)

```php
POST /wp-json/ghl-crm/v1/webhooks
↓
handle_webhook()
  - Returns 200 OK immediately
  - Schedules: wp_schedule_single_event('ghl_process_webhook_async')
```

### 2. **Async Processing** (process_webhook_async)

```php
process_webhook_async($body, $headers)
  ↓
normalize_webhook_payload($body)
  - Converts GHL format → internal format
  - Maps: contact_id → data.id, first_name → data.firstName, etc.
  - Converts tags string → array
  ↓
route_webhook_event($normalized)
  - Routes by type: ContactCreate, ContactUpdate, ContactDelete
```

### 3. **Queue Contact Sync** (handle_contact_create/update/delete)

```php
handle_contact_create($payload)
  ↓
Check: is_sync_direction_enabled('ghl_to_wp')
  ↓
QueueManager::add_to_queue(
    item_type: 'contact',           // ← KEY: Uses 'contact' not 'user'
    item_id: $contact_data['id'],   // GHL contact ID
    action: 'contact_create',       // or 'contact_update', 'contact_delete'
    payload: $contact_data          // Normalized contact data
)
```

**Queue Table Entry:**
```sql
INSERT INTO wp_ghl_sync_queue (
    item_type = 'contact',
    item_id = 'GHL_CONTACT_ID_HERE',
    action = 'contact_create',
    payload = '{"id":"...","email":"...","firstName":"...",...}',
    status = 'pending',
    site_id = 1
)
```

### 4. **Queue Processing** (QueueManager.php)

Every 10 seconds via Action Scheduler:

```php
process_queue()
  ↓
Get pending items (LIMIT 50)
  ↓
For each item:
  ↓
check_rate_limits() // GHL API limits
  ↓
execute_sync($item_type, $action, $item_id, $payload)
    ↓
    Switch on $item_type:
      case 'user':    → execute_user_sync()      // WP→GHL sync
      case 'contact': → execute_contact_sync()   // GHL→WP sync ← OUR NEW HANDLER
```

### 5. **Contact Sync Execution** (execute_contact_sync)

```php
execute_contact_sync('contact_create', $contact_id, $payload)
  ↓
$ghl_sync = GHLToWordPressSync::get_instance()
  ↓
Switch on $action:
  
  case 'contact_create':
  case 'contact_update':
    ↓
    $ghl_sync->sync_contact_to_wordpress($contact_id, $payload)
      - Checks field mappings
      - Only syncs fields with direction: 'ghl_to_wp' or 'both'
      - Creates or updates WordPress user
      - Returns: user_id
    ↓
    Mark queue item: status='completed'
    
  case 'contact_delete':
    ↓
    $ghl_sync->delete_wordpress_user($contact_id)
      - Finds WP user by _ghl_contact_id meta
      - Deletes or unlinks based on settings
    ↓
    Mark queue item: status='completed'
```

### 6. **Field Mapping Respect** (GHLToWordPressSync.php)

```php
sync_contact_to_wordpress($contact_id, $contact_data)
  ↓
get_reverse_field_mappings()
  - Returns ONLY fields with direction: 'ghl_to_wp' or 'both'
  - Example: ['firstName' => 'first_name', 'email' => 'user_email']
  ↓
For each mapped field:
  ↓
  should_sync_field($wp_field, 'ghl_to_wp')
    - Checks: field_mappings[$wp_field]['direction']
    - Returns: true if 'ghl_to_wp' or 'both'
    - Returns: false if 'wp_to_ghl' or not mapped
  ↓
  IF should_sync:
    - Update WordPress user field
  ELSE:
    - Skip field (WordPress is source of truth)
```

---

## Key Differences: User vs Contact Item Types

### `item_type='user'` (WordPress → GHL)
- **Triggered by:** WP user actions (register, profile_update, login, delete)
- **Direction:** WordPress → GoHighLevel
- **Handler:** `execute_user_sync()`
- **Purpose:** Push WP user changes to GHL contact

### `item_type='contact'` (GHL → WordPress) ← NEW
- **Triggered by:** GHL webhooks (ContactCreate, ContactUpdate, ContactDelete)
- **Direction:** GoHighLevel → WordPress
- **Handler:** `execute_contact_sync()`
- **Purpose:** Pull GHL contact changes to WP user

---

## Field Mapping Direction Examples

### Example 1: Phone Field (GHL is Source)
```php
Field Mapping:
  'phone' => ['ghl_field' => 'phone', 'direction' => 'ghl_to_wp']

Webhook arrives with phone: "+1234567890"
  ↓
should_sync_field('phone', 'ghl_to_wp') → TRUE
  ↓
Update WP user_meta: update_user_meta($user_id, 'phone', '+1234567890')
```

### Example 2: Billing Address (WP is Source)
```php
Field Mapping:
  'billing_address' => ['ghl_field' => 'customField.address', 'direction' => 'wp_to_ghl']

Webhook arrives with customField.address: "123 Main St"
  ↓
should_sync_field('billing_address', 'ghl_to_wp') → FALSE
  ↓
Skip update (WordPress billing_address NOT updated from GHL)
```

### Example 3: Email (Bidirectional)
```php
Field Mapping:
  'user_email' => ['ghl_field' => 'email', 'direction' => 'both']

Webhook arrives with email: "new@example.com"
  ↓
should_sync_field('user_email', 'ghl_to_wp') → TRUE
  ↓
Update WP user: wp_update_user(['ID' => $user_id, 'user_email' => 'new@example.com'])
```

---

## Error Handling

### Rate Limits
```php
check_rate_limits()
  - Burst: 100 requests / 10 seconds
  - Daily: 200,000 requests / day
  - Tracked by GHL location_id (shared across multisite)
  
If exceeded:
  - Item stays status='pending'
  - Retries on next queue run
```

### Sync Failures
```php
If sync fails (exception thrown):
  - Increment attempts counter
  - If attempts < 5:
      status='pending' (retry later)
  - If attempts >= 5:
      status='failed' (give up)
  - Log error to sync_logs table
```

### Missing Email
```php
If contact_data['email'] is empty:
  - Return WP_Error('missing_email')
  - Mark queue item as failed
  - Log error
```

---

## Database Tables

### Queue Table (wp_ghl_sync_queue)
```sql
| id | item_type | item_id             | action         | payload        | status    | attempts |
|----|-----------|---------------------|----------------|----------------|-----------|----------|
| 1  | contact   | 61fL5xkSitiNNHAcO  | contact_create | {"id":"..."... | pending   | 0        |
| 2  | contact   | 61fL5xkSitiNNHAcO  | contact_update | {"id":"..."... | completed | 1        |
| 3  | user      | 42                  | user_register  | {"email":"...  | completed | 1        |
```

### Sync Logs Table (wp_ghl_sync_logs)
```sql
| operation              | item_id | sync_type | status  | message                    |
|------------------------|---------|-----------|---------|----------------------------|
| webhook_received       | 0       | ghl_to_wp | success | Webhook received from GHL  |
| wp_user_created_from...| ABC123  | ghl_to_wp | success | User created from contact  |
| wp_user_updated_from...| ABC123  | ghl_to_wp | success | User updated from contact  |
```

---

## Logging

### Webhook Receipt
```php
SyncLogger::log('webhook_received', 0, 'ghl_to_wp', 'success', ...)
  - Logs: raw_payload, normalized_payload, type
```

### Queue Add
```php
error_log('GHL CRM QueueManager: add_to_queue() - Type: contact, ID: ABC123, Action: contact_create')
```

### Sync Execution
```php
error_log('📥 GHL CRM: execute_contact_sync() START - Action: contact_create')
error_log('🔄 GHL CRM: Syncing contact to WordPress...')
error_log('✅ GHL CRM: Contact synced successfully. User ID: 42')
```

### Field Skipped
```php
// (Implicit: no log when should_sync_field returns false)
// Fields are silently skipped if direction doesn't allow GHL→WP
```

---

## Testing the Flow

### 1. Trigger Test Webhook
```bash
curl -X POST https://yoursite.com/wp-json/ghl-crm/v1/webhooks \
  -H "Content-Type: application/json" \
  -d '{
    "contact_id": "test123",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "tags": "new user",
    "location": {"id": "LOC123"}
  }'
```

### 2. Check Sync Logs
```
WP Admin → GHL CRM → Sync Logs
  - Look for: webhook_received
  - Look for: wp_user_created_from_ghl or wp_user_updated_from_ghl
```

### 3. Check Queue
```sql
SELECT * FROM wp_ghl_sync_queue 
WHERE item_type='contact' 
ORDER BY created_at DESC 
LIMIT 10;
```

### 4. Verify User Created/Updated
```
WP Admin → Users
  - Look for user with email: john@example.com
  - Check user meta: _ghl_contact_id = test123
```

### 5. Verify Field Mapping Respected
```
Check user fields:
  - Fields with direction='ghl_to_wp' or 'both': SHOULD be updated
  - Fields with direction='wp_to_ghl': SHOULD NOT be updated
```

---

## Summary

✅ **Webhooks now properly queue as `item_type='contact'`**
✅ **QueueManager routes to `execute_contact_sync()`**
✅ **`execute_contact_sync()` calls `GHLToWordPressSync`**
✅ **Field mapping directions are respected**
✅ **Complete logging at every step**
✅ **Proper error handling with retries**
✅ **Rate limiting shared across multisite**
