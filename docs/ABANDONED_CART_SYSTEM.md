# Abandoned Cart Tracking System

## Overview
The abandoned cart system tracks incomplete WooCommerce checkouts and automatically syncs them to GoHighLevel with tags. This enables automated recovery campaigns through GHL workflows.

## How It Works

### 1. Cart Tracking
- **Cart Updates**: System tracks when items are added/removed from cart
- **Email Capture**: Captures customer email when entered during checkout
- **Storage**: Cart data stored in WordPress transients (7-day expiry)
- **Cart Key**: Uses user ID for logged-in users, session ID for guests

### 2. Abandonment Detection
- **Scheduled Check**: WP-Cron runs every 15 minutes to check for abandoned carts
- **Time Threshold**: Configurable (15-1440 minutes, default 30 minutes)
- **Conditions**: Cart marked as abandoned if:
  - Email has been captured
  - Time threshold exceeded
  - Not already abandoned or recovered

### 3. GHL Sync
When cart is marked as abandoned:
- Contact upserted in GoHighLevel with email, name, phone
- Configured tags automatically applied
- Cart data preserved in transient for analytics

### 4. Recovery Tracking
- **Order Completion**: Cart marked as recovered when order placed
- **Time to Recovery**: Calculated for analytics
- **Recovery Method**: Tracked (organic vs workflow-driven)

## Data Structure

### Cart Transient Data
```php
[
    'cart_total'      => 99.99,           // Total cart value
    'item_count'      => 3,               // Number of items
    'items'           => [...],           // Product details array
    'email'           => 'customer@example.com',
    'first_name'      => 'John',
    'last_name'       => 'Doe',
    'phone'           => '+1234567890',
    'created_at'      => 1234567890,      // Unix timestamp
    'updated_at'      => 1234567890,      // Unix timestamp
    'checkout_started' => true,            // Email captured flag
    'abandoned'       => false,            // Abandoned flag
    'abandoned_at'    => null,             // Abandonment timestamp
    'recovered'       => false,            // Recovery flag
    'recovered_at'    => null,             // Recovery timestamp
    'order_id'        => null,             // Recovered order ID
    'ghl_contact_id'  => 'contact_xxx',   // GHL contact ID
    'ghl_tags_applied' => ['Abandoned Cart'], // Applied tags
]
```

## Settings

### Admin Configuration
Located in: **Integrations → WooCommerce → Abandoned Cart Tracking**

1. **Enable/Disable**: Toggle abandoned cart tracking
2. **Time Threshold**: Minutes before cart considered abandoned (15-1440)
3. **Tags**: Select one or multiple tags to apply to abandoned carts

## Hooks & Filters

### Actions
- `woocommerce_add_to_cart` - Track cart updates
- `woocommerce_cart_item_removed` - Track cart updates
- `woocommerce_checkout_update_order_review` - Capture email
- `ghl_crm_check_abandoned_carts` - Cron job (every 15 min)
- `woocommerce_thankyou` - Mark cart as recovered

### Cron Schedule
- **Name**: `ghl_crm_15min`
- **Interval**: 15 minutes (900 seconds)
- **Action**: `ghl_crm_check_abandoned_carts`

## Analytics & Reporting (Future)

The system stores data for future analytics:
- Abandonment rate by time period
- Average cart value of abandoned carts
- Recovery rate and time-to-recovery
- Most abandoned products/categories
- Device type analysis (mobile vs desktop)

## Technical Details

### Transient Keys
- **Pattern**: `ghl_cart_{cart_key}`
- **Cart Key Format**:
  - Logged-in: `user_{user_id}`
  - Guest: `guest_{session_id}`
- **Expiry**: 7 days (DAY_IN_SECONDS * 7)

### Performance
- **Lookup**: O(1) transient get/set operations
- **Cron Check**: Scans all cart transients every 15 minutes
- **Cleanup**: Automatic via transient expiry
- **No Custom Tables**: Uses WordPress transients API

### Edge Cases Handled
- Guest checkout (requires email capture)
- Cart modifications after initial creation
- Multiple devices (keyed by session/user)
- Order completion marking recovery
- Duplicate abandonment prevention
- Tag application idempotency

## Example Workflow

### Typical Flow
1. Customer adds items to cart (tracked)
2. Customer starts checkout, enters email (captured)
3. Customer leaves site without completing (30 minutes pass)
4. Cron job detects abandonment
5. Contact synced to GHL with "Abandoned Cart" tag
6. GHL workflow sends recovery email
7. Customer returns and completes order
8. Cart marked as recovered with analytics

## Troubleshooting

### Common Issues

**Cart not detected as abandoned:**
- Check WP-Cron is running (`wp cron event list`)
- Verify email was captured during checkout
- Confirm time threshold exceeded
- Check abandoned cart toggle is enabled

**Tags not applied:**
- Verify GHL connection is active
- Check tags are configured in settings
- Review error logs for API failures

**Recovery not tracked:**
- Ensure cart key matches (same session/user)
- Check order completed successfully
- Verify `woocommerce_thankyou` hook fired

### Debug Logging
All operations logged to `error_log`:
```
GHL Abandoned Cart: Tagged cart for customer@example.com - Cart Value: $99.99, Items: 3, Tags: Abandoned Cart
GHL Abandoned Cart: Cart recovered - Email: customer@example.com, Time: 2 hours, Order: #1234
```

## Future Enhancements
- Custom post type for long-term analytics
- Recovery email preview in admin
- A/B testing for recovery timing
- Product-specific recovery campaigns
- Multi-currency support
- SMS recovery option
