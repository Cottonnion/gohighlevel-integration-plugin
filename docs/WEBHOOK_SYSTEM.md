# Two-Way Sync & Webhook System

## Overview

The plugin now supports **bidirectional synchronization** between WordPress and GoHighLevel using webhooks. This enables real-time updates when contacts are modified in GoHighLevel.

## Architecture

### Components

1. **WebhookHandler** (`src/API/Webhooks/WebhookHandler.php`)
   - Registers REST API endpoint: `/wp-json/ghl-crm/v1/webhooks`
   - Handles incoming webhook events from GoHighLevel
   - Manages webhook creation/deletion via GHL API
   - Routes events to appropriate processors

2. **GHLToWordPressSync** (`src/Sync/GHLToWordPressSync.php`)
   - Processes GHL contact data → WordPress users
   - Handles contact create, update, and delete operations
   - Respects field mapping directions
   - Generates unique usernames and handles conflicts

3. **QueueManager** (Updated)
   - Processes both WP→GHL and GHL→WP sync operations
   - Handles `contact_create`, `contact_update`, `contact_delete` actions
   - Maintains reliable queue with retry logic

## Sync Directions

### 1. WordPress to GoHighLevel Only (Default)
- WordPress user changes → synced to GHL
- GHL changes → ignored

### 2. GoHighLevel to WordPress Only
- GHL contact changes → synced to WordPress
- WordPress changes → ignored

### 3. Two-Way (Bidirectional)
- Changes in either system sync to the other
- Conflict resolution: Last write wins
- Prevents infinite loops with metadata tracking

## Webhook Events

### Supported Events

| Event | Description | Action |
|-------|-------------|--------|
| `ContactCreate` | New contact created in GHL | Creates WordPress user |
| `ContactUpdate` | Contact updated in GHL | Updates WordPress user |
| `ContactDelete` | Contact deleted in GHL | Deletes or unlinks WordPress user |

### Event Flow

```
GoHighLevel → Webhook → WordPress REST API
                ↓
         WebhookHandler (validates & logs)
                ↓
         QueueManager (adds to queue)
                ↓
         GHLToWordPressSync (processes)
                ↓
         WordPress User Created/Updated
```

## Setup Instructions

### Automatic Setup (Recommended)

1. Navigate to **Settings → Webhooks**
2. Click **"Create Webhook in GoHighLevel"**
3. Webhook is automatically created with correct events
4. Webhook ID is stored in settings

### Manual Setup

1. Copy webhook URL from **Settings → Webhooks**
2. In GoHighLevel, go to **Settings → Integrations → Webhooks**
3. Click **"Add Webhook"**
4. Paste URL and select events:
   - Contact Created
   - Contact Updated
   - Contact Delete (optional)
5. Save webhook

## Field Mapping & Sync Direction

Each field can have its own sync direction:

- **Bidirectional**: Syncs both ways
- **wp_to_ghl**: Only WordPress → GHL
- **ghl_to_wp**: Only GHL → WordPress

Configure in **Settings → Field Mapping** (future feature).

## User Creation from GHL

When a contact is created in GHL and synced to WordPress:

1. **Email** is required (contact must have email)
2. **Username** is auto-generated from email
3. **Password** is randomly generated (user must reset)
4. **Role** is set from `default_user_role` setting (default: subscriber)
5. **GHL Contact ID** is stored in `_ghl_contact_id` user meta
6. **Tags** are stored in `_ghl_tags` user meta
7. **Sync timestamp** stored in `_ghl_synced_at`

## User Deletion Behavior

Controlled by `allow_user_deletion` setting:

- **Enabled**: WordPress user is deleted when GHL contact is deleted
- **Disabled** (default): User is unlinked but not deleted
  - `_ghl_contact_id` meta is removed
  - `_ghl_deleted_at` timestamp is added

## Security

- Webhook endpoint is public (no authentication required)
- Future: Implement signature verification using GHL webhook secrets
- All data is sanitized and validated before processing
- Rate limiting via QueueManager

## Logging

All webhook events are logged:
- **webhook_received**: Initial receipt
- **webhook_skipped**: Sync direction disabled
- **webhook_unsupported**: Unknown event type
- **wp_user_created_from_ghl**: User created
- **wp_user_updated_from_ghl**: User updated
- **wp_user_deleted**: User deleted
- **wp_user_unlinked**: User unlinked (not deleted)

View logs in **Sync Logs** page.

## Troubleshooting

### Webhook Not Receiving Events

1. Verify webhook exists in GoHighLevel
2. Check webhook URL is accessible (not localhost)
3. Test with public URL (use ngrok for local dev)
4. Check sync logs for errors

### Users Not Creating

1. Verify sync direction includes `ghl_to_wp` or `bidirectional`
2. Check that contact has email address
3. Review queue for failed items
4. Check error logs for duplicate username issues

### Conflicts (Both Systems Updated)

- Last write wins (determined by `updated_at` timestamps)
- Use `_ghl_synced_at` meta to track last sync
- Future: Add conflict resolution strategies

## Database Schema

### User Meta Keys

| Meta Key | Description | Example |
|----------|-------------|---------|
| `_ghl_contact_id` | GHL contact ID | `xYz123...` |
| `_ghl_tags` | Array of GHL tags | `['customer', 'vip']` |
| `_ghl_synced_at` | Last sync timestamp | `2025-10-31 12:34:56` |
| `_ghl_deleted_at` | Contact deletion timestamp | `2025-10-31 15:00:00` |

## API Endpoints

### Webhook Endpoint

**POST** `/wp-json/ghl-crm/v1/webhooks`

**Headers:**
```
Content-Type: application/json
```

**Payload:**
```json
{
  "type": "ContactCreate",
  "contact": {
    "id": "contact_abc123",
    "email": "user@example.com",
    "firstName": "John",
    "lastName": "Doe",
    "tags": ["customer", "active"]
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Webhook processed successfully"
}
```

### Management Endpoints (AJAX)

1. **Create Webhook**: `wp_ajax_ghl_crm_create_webhook`
2. **Delete Webhook**: `wp_ajax_ghl_crm_delete_webhook`
3. **Get Status**: `wp_ajax_ghl_crm_get_webhook_status`

## Future Enhancements

- [ ] Webhook signature verification
- [ ] Per-field sync direction configuration
- [ ] Conflict resolution strategies
- [ ] Webhook retry mechanism
- [ ] Webhook statistics dashboard
- [ ] Support for additional GHL objects (opportunities, tasks)
- [ ] WooCommerce order webhooks
- [ ] LearnDash course enrollment webhooks

## Testing

### Test Webhook Locally

1. Use ngrok: `ngrok http 80`
2. Use ngrok HTTPS URL as webhook endpoint
3. Create/update contact in GHL
4. Monitor WordPress logs

### Test Queue Processing

```php
// Manually add to queue
$queue = \GHL_CRM\Sync\QueueManager::get_instance();
$queue->add_to_queue(
    'contact_update',
    'contact_abc123',
    'ghl_to_wp',
    ['email' => 'test@example.com', 'firstName' => 'Test']
);

// Manually process queue
do_action('ghl_crm_process_queue');
```

## Code Examples

### Get User's GHL Contact ID

```php
$user_id = 123;
$contact_id = get_user_meta($user_id, '_ghl_contact_id', true);
```

### Check if User is Synced from GHL

```php
$synced_at = get_user_meta($user_id, '_ghl_synced_at', true);
if ($synced_at) {
    echo "Last synced: " . $synced_at;
}
```

### Get User's GHL Tags

```php
$tags = get_user_meta($user_id, '_ghl_tags', true);
if (is_array($tags)) {
    foreach ($tags as $tag) {
        echo $tag . ', ';
    }
}
```

---

**Status**: ✅ Fully Implemented
**Version**: 1.0.0
**Last Updated**: October 31, 2025
