# Notification System Documentation

## Overview
The NotificationManager provides enterprise-grade email notifications for the GoHighLevel CRM Integration plugin. It includes throttling, templating, scheduling, and comprehensive alert types.

## Features
- ✅ Critical alert notifications (connection lost, sync errors, etc.)
- ✅ Daily summary reports with statistics
- ✅ Email throttling to prevent spam
- ✅ Beautiful HTML email templates
- ✅ AJAX test notification functionality
- ✅ Configurable notification types
- ✅ Automatic scheduling for daily summaries

## Usage

### Accessing the NotificationManager

```php
$notification_manager = \GHL_CRM\Core\NotificationManager::get_instance();
```

### Sending Notifications

#### Connection Lost Alert
```php
$notification_manager->send_connection_lost( 
    'OAuth token expired' 
);
```

#### Sync Error Alert
```php
$notification_manager->send_sync_error(
    'WooCommerce Order',           // Sync type
    'Failed to create contact',    // Error message
    [                              // Optional metadata
        'order_id' => 12345,
        'customer_email' => 'user@example.com'
    ]
);
```

#### Queue Backlog Warning
```php
$notification_manager->send_queue_backlog( 1250 ); // Number of pending items
```

#### Rate Limit Alert
```php
$notification_manager->send_rate_limit( 300 ); // Retry after 300 seconds
```

#### Webhook Failure Alert
```php
$notification_manager->send_webhook_failure(
    'Contact Updated',           // Webhook type
    'Signature verification failed'  // Error message
);
```

## Integration Points

### 1. Connection Manager
When OAuth/API connection fails:
```php
// In src/API/ConnectionManager.php or OAuth/OAuthHandler.php
if ( ! $this->is_connected() ) {
    NotificationManager::get_instance()->send_connection_lost( 
        'API authentication failed' 
    );
}
```

### 2. Queue Manager
When queue processing fails:
```php
// In src/Sync/QueueManager.php
public function process_item( $item ) {
    try {
        // ... processing logic
    } catch ( \Exception $e ) {
        NotificationManager::get_instance()->send_sync_error(
            $item['sync_type'],
            $e->getMessage(),
            [ 'item_id' => $item['item_id'] ]
        );
    }
}
```

When queue backlog is detected:
```php
// In src/Sync/QueueManager.php
$pending_count = $this->get_pending_count();
if ( $pending_count > 1000 ) {
    NotificationManager::get_instance()->send_queue_backlog( $pending_count );
}
```

### 3. API Client
When rate limits are hit:
```php
// In src/API/Client/APIClient.php
if ( $response_code === 429 ) {
    $retry_after = $response_headers['Retry-After'] ?? 60;
    NotificationManager::get_instance()->send_rate_limit( (int) $retry_after );
}
```

### 4. Webhook Handler
When webhooks fail:
```php
// In src/API/Webhooks/WebhookHandler.php
try {
    $this->process_webhook( $data );
} catch ( \Exception $e ) {
    NotificationManager::get_instance()->send_webhook_failure(
        $data['type'] ?? 'Unknown',
        $e->getMessage()
    );
}
```

## Configuration

All notification settings are managed in the WordPress admin:
**Dashboard → GoHighLevel → Settings → Notifications**

### Available Settings:
- `notification_email` - Email address to receive notifications
- `notify_connection_lost` - Enable/disable connection lost alerts
- `notify_sync_errors` - Enable/disable sync error alerts
- `notify_queue_backlog` - Enable/disable queue backlog warnings
- `notify_rate_limit` - Enable/disable rate limit alerts
- `notify_webhook_failures` - Enable/disable webhook failure alerts
- `notify_daily_summary` - Enable/disable daily summary emails
- `daily_summary_time` - Time of day to send daily summary (e.g., '09:00')
- `notification_throttle` - Minimum seconds between duplicate alerts (default: 3600)

## Throttling

The notification system includes intelligent throttling to prevent email spam:

1. **How it works**: Same notification type + context won't send more than once per throttle period
2. **Configurable**: Set throttle duration in settings (0 = no throttling)
3. **Smart grouping**: Similar errors are grouped by sync_type, webhook_type, etc.

Example:
```php
// If throttle is set to 1 hour (3600 seconds):
// First call sends email
$notification_manager->send_sync_error( 'User', 'API Error' );

// Second call within 1 hour is throttled (no email sent)
$notification_manager->send_sync_error( 'User', 'API Error' );

// But different sync types are not throttled
$notification_manager->send_sync_error( 'Order', 'API Error' ); // Sends email
```

## Daily Summary

Automatic daily summary emails include:
- Total syncs in last 24 hours
- Success vs. failure count
- Success rate percentage
- Breakdown by type (users, orders, LearnDash, BuddyBoss)
- Current queue status
- Webhooks received
- Top 5 errors with occurrence counts

The summary is sent via WP-Cron at the configured time.

## Email Template

All emails use a professional HTML template with:
- Responsive design
- Brand colors (customizable)
- Site name and branding
- Clear call-to-action buttons
- Footer with unsubscribe context

## Testing

Send a test notification to verify email delivery:
1. Go to **Dashboard → GoHighLevel → Settings → Notifications**
2. Click "Send Test Notification" button
3. Check your inbox (and spam folder)

Or programmatically:
```php
NotificationManager::get_instance()->handle_test_notification();
```

## Best Practices

### 1. Use Appropriate Alert Types
- **Connection Lost**: Only for critical auth failures
- **Sync Errors**: For failed sync operations
- **Queue Backlog**: When queue exceeds 1,000 items
- **Rate Limit**: When API limits are hit
- **Webhook Failures**: When webhooks fail to process

### 2. Provide Context
Always include relevant metadata:
```php
$notification_manager->send_sync_error(
    'WooCommerce Order',
    'Failed to create contact',
    [
        'order_id' => $order->get_id(),
        'customer_email' => $order->get_billing_email(),
        'error_code' => $response['code'],
        'retry_count' => $item['attempts']
    ]
);
```

### 3. Don't Override Throttling
The throttling system prevents spam. Don't bypass it unless absolutely necessary.

### 4. Check Settings Before Critical Operations
```php
if ( $settings_manager->get_setting( 'notify_connection_lost', false ) ) {
    // Only send if enabled in settings
}
```

## Multisite Compatibility

The notification system is fully multisite-compatible:
- Notifications are site-specific (not network-wide)
- Each site has its own notification email
- Daily summaries include only the current site's data
- Settings are stored per-site in `wp_options`

## Performance Considerations

1. **Async Email Sending**: Consider using wp_mail with queue for heavy traffic sites
2. **Database Queries**: Daily summary queries are optimized with prepared statements
3. **Throttle Storage**: Uses transients for lightweight, expiring storage
4. **Hook Efficiency**: All hooks use `wp_ajax_` prefix for admin-only execution

## Troubleshooting

### Emails Not Sending
1. Check WordPress email configuration (wp_mail)
2. Verify notification type is enabled in settings
3. Check spam/junk folder
4. Test with "Send Test Notification" button
5. Check server mail logs

### Too Many Emails
1. Increase throttle duration in settings
2. Disable less critical notification types
3. Fix underlying issues causing repeated errors

### Missing Data in Daily Summary
1. Verify sync logging is enabled
2. Check that `wp_ghl_sync_log` table exists
3. Ensure WP-Cron is functioning properly

## Future Enhancements

Potential improvements for future versions:
- SMS notifications via Twilio/similar
- Slack/Discord webhooks
- Custom notification channels
- Notification preferences per user role
- Digest mode (combine multiple alerts into one email)
- Push notifications via browser API
