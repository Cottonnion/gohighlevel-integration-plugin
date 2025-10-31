# GoHighLevel Webhook Setup Guide

## Overview

This plugin provides a webhook endpoint that you must manually configure in your GoHighLevel account. Simply copy the webhook URL from the plugin settings and create automations in GHL to send contact events to WordPress.

## 🔗 How It Works

1. **Plugin provides webhook URL**: `https://yoursite.com/wp-json/ghl-crm/v1/webhooks`
2. **You create automations in GHL**: Set up workflows that send contact data to this URL
3. **Plugin processes webhooks**: Automatically syncs contact changes from GHL to WordPress

## 📋 Setup Instructions

### Step 1: Get Your Webhook URL

1. Go to your WordPress admin dashboard
2. Navigate to "GHL CRM" → "Webhooks" 
3. Copy the webhook URL displayed

### Step 2: Create Automations in GoHighLevel

For each event you want to sync (contact created, updated, deleted):

1. **Log into your GoHighLevel account**
2. **Go to Automation → Workflows**
3. **Create a new workflow** (or edit existing)
4. **Set trigger**: Choose "Contact Created", "Contact Updated", or "Contact Deleted"
5. **Add action**: "Outbound Webhook"
6. **Configure webhook**:
   - **URL**: Paste your webhook URL from Step 1
   - **Method**: POST
   - **Headers**: `Content-Type: application/json`
   - **Body**: Use the JSON templates below

### Step 3: JSON Body Templates

**For Contact Created/Updated:**
```json
{
  "type": "ContactCreate",
  "locationId": "{{location.id}}",
  "data": {
    "id": "{{contact.id}}",
    "email": "{{contact.email}}",
    "name": "{{contact.name}}",
    "firstName": "{{contact.first_name}}",
    "lastName": "{{contact.last_name}}",
    "phone": "{{contact.phone}}",
    "tags": ["{{contact.tags}}"]
  }
}
```

**For Contact Deleted:**
```json
{
  "type": "ContactDelete",
  "locationId": "{{location.id}}",
  "data": {
    "id": "{{contact.id}}"
  }
}
```

**Note**: Change `"type": "ContactCreate"` to `"ContactUpdate"` for update events.

## 🧪 Testing Your Setup

### Built-in Test
1. Go to "GHL CRM" → "Webhooks"
2. Click "Test Webhook Endpoint"
3. Check that you get a success message

### Manual Test
You can test your webhook manually using curl:

```bash
curl -X POST https://yoursite.com/wp-json/ghl-crm/v1/webhooks \
  -H "Content-Type: application/json" \
  -d '{
    "type": "ContactCreate",
    "data": {
      "id": "test_123",
      "email": "test@example.com",
      "name": "Test Contact"
    }
  }'
```

### Check Status
- Go to "GHL CRM" → "Webhooks" to see if webhooks are being received
- Check "GHL CRM" → "Sync Logs" for detailed processing information

## 📊 Webhook Status

The plugin tracks webhook activity and shows:
- **Active**: Webhooks received in last 24 hours
- **Not Configured**: No recent webhooks (setup needed)
- **Last webhook received**: Timestamp of most recent webhook

## 🔧 Supported Events

| Event Type | Description | When to Use |
|------------|-------------|-------------|
| `ContactCreate` | New contact created in GHL | Sync new contacts to WordPress |
| `ContactUpdate` | Existing contact updated in GHL | Keep contact data in sync |
| `ContactDelete` | Contact deleted from GHL | Remove or mark contacts in WordPress |

## ⚙️ Settings Integration

### Sync Direction
Webhooks respect your sync direction settings:
- **WordPress to GHL**: Webhooks are ignored
- **GHL to WordPress**: Webhooks are processed
- **Bidirectional**: Webhooks are processed

### Field Mapping
Webhook data is processed according to your configured field mappings in the plugin settings.

## 🚨 Troubleshooting

### Webhook Not Working

1. **Check URL accessibility**
   - Ensure your WordPress site is publicly accessible
   - Verify SSL certificate is valid (HTTPS required)

2. **Test the endpoint**
   - Use the built-in test function
   - Try manual curl test
   - Check WordPress REST API is working

3. **Verify automation setup**
   - Ensure workflow is active in GHL
   - Check webhook URL is correct
   - Verify JSON payload format

### Check Logs

1. **Plugin Logs**
   - Go to "GHL CRM" → "Sync Logs"
   - Look for `webhook_received` entries
   - Check for errors or warnings

2. **WordPress Debug Logs**
   - Enable WP_DEBUG in wp-config.php
   - Check `/wp-content/debug.log`

### Common Issues

| Issue | Solution |
|-------|----------|
| Webhook returns 403/404 | Check WordPress REST API is enabled |
| No webhooks received | Verify GHL automation is active |
| Contacts not syncing | Check sync direction settings |
| JSON errors | Verify webhook payload format |

## 🔒 Security Notes

- The webhook endpoint accepts all POST requests for simplicity
- You can implement additional security measures if needed
- All webhook activity is logged for monitoring
- Consider IP allowlisting for production use

## 💡 Best Practices

1. **Test First**: Always test with sample data before going live
2. **Monitor Logs**: Regularly check sync logs for issues
3. **Start Simple**: Begin with just ContactCreate events
4. **Gradual Rollout**: Add more event types once basic setup works
5. **Backup Data**: Keep backups of your contact data

## 📞 Support

If you encounter issues:

1. **Check Status**: Go to "GHL CRM" → "Webhooks" for current status
2. **Review Logs**: Check "GHL CRM" → "Sync Logs" for detailed information
3. **Test Endpoint**: Use built-in test to verify webhook is working
4. **Verify Setup**: Double-check GHL automation configuration

---

*This simplified approach puts you in full control of when and how contact data is synced from GoHighLevel to WordPress.*
