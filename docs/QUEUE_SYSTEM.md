# Queue System Architecture

## Overview
The plugin now uses a centralized, reusable queue system for all integrations (Users, WooCommerce, BuddyBoss, LearnDash, etc.). This prevents blocking API calls and improves performance significantly.

## Key Features

### ✅ Multisite Compatible
- All tables include `site_id` column for data isolation
- Queue processor handles all sites automatically
- Each site has its own queue and logs
- Settings and caching are per-site

### ✅ Reusable Across Integrations
- `QueueManager` handles all item types: `user`, `order`, `group`, `course`, etc.
- Extensible via WordPress filters
- Future integrations can add items without modifying core queue logic

### ✅ Performance Optimizations
- **Transient-based caching**: Contact data cached for 15 minutes (no database table needed)
- **Debouncing**: 10-second lock prevents duplicate profile update API calls
- **Throttling**: Login tracking limited to once per hour
- **Batch processing**: Processes 10 items per cron run
- **Retry logic**: Max 3 attempts for failed items

### ✅ Automatic Cleanup
- Completed queue items deleted after 7 days
- Failed queue items deleted after 30 days
- Sync logs deleted after 30 days
- Runs daily via WP-Cron

## Architecture

### Components

#### 1. QueueManager (`/src/Sync/QueueManager.php`)
**Purpose**: Central queue management for all integrations

**Methods**:
- `add_to_queue($item_type, $item_id, $action, $payload)` - Add items to queue
- `process_queue()` - Process queued items (WP-Cron)
- `execute_sync()` - Route to appropriate integration handler
- `get_queue_status()` - Get pending/failed/completed counts
- `get_cached_contact($email)` - Get cached contact (transients)
- `cache_contact($email, $contact)` - Cache contact for 15 minutes

**Supported Item Types**:
- `user` - WordPress users (implemented)
- `order` - WooCommerce orders (future)
- `group` - BuddyBoss groups (future)
- `course` - LearnDash courses (future)
- Extensible via `ghl_crm_execute_sync` filter

#### 2. Database (`/src/Core/Database.php`)
**Purpose**: Manage custom database tables

**Tables**:
```sql
-- Queue table (all integrations)
wp_ghl_sync_queue
- id, item_type, item_id, action, payload
- status (pending/completed/failed)
- attempts, error_message
- created_at, updated_at, processed_at
- site_id (multisite support)

-- Log table (execution history)
wp_ghl_sync_log
- id, user_id, action, status
- contact_id, error_message, execution_time
- created_at, site_id
```

**Note**: Contact cache table removed - using transients instead.

#### 3. UserHooks (`/src/Integrations/Users/UserHooks.php`)
**Updated**: Now uses `QueueManager::add_to_queue()` instead of direct API calls

**Hooks**:
- `on_user_register()` - Queue contact creation
- `on_user_update()` - Queue profile updates (debounced)
- `on_user_delete()` - Queue contact deletion
- `on_user_login()` - Queue login tracking (throttled)

**Removed**:
- `on_user_role_change()` - No setting exists for this
- `log_sync_event()` - Moved to QueueManager

## Usage Examples

### Adding to Queue (Users)
```php
$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
$queue_manager->add_to_queue( 'user', $user_id, 'user_register', $contact_data );
```

### Adding to Queue (WooCommerce - Future)
```php
$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
$queue_manager->add_to_queue( 'order', $order_id, 'order_completed', $order_data );
```

### Extending Queue for Custom Integration
```php
// Add custom handler via filter
add_filter( 'ghl_crm_execute_sync', function( $result, $item_type, $action, $item_id, $payload ) {
    if ( $item_type === 'custom_type' ) {
        // Your custom sync logic here
        return true; // Return true on success
    }
    return $result;
}, 10, 5 );

// Add to queue
$queue_manager->add_to_queue( 'custom_type', 123, 'custom_action', [ 'data' => 'value' ] );
```

## Cron Jobs

### Queue Processor
- **Hook**: `ghl_crm_process_queue`
- **Schedule**: Every minute
- **Function**: Processes pending queue items in batches

### Database Cleanup
- **Hook**: `ghl_crm_cleanup_database`
- **Schedule**: Daily
- **Function**: Removes old queue items and logs

## Caching Strategy

### Contact Cache (Transients)
```php
// Cache key format
$cache_key = 'ghl_contact_' . md5( strtolower( $email ) );

// TTL: 15 minutes
set_transient( $cache_key, $contact_data, 15 * MINUTE_IN_SECONDS );

// Get cached
$contact = get_transient( $cache_key );
```

**Benefits**:
- Leverages WordPress object cache (Redis/Memcached if available)
- No custom table maintenance
- Automatic expiration and cleanup
- Per-site isolation in multisite

### Debounce/Throttle (Transients)
```php
// Debounce profile updates (10 seconds)
$lock_key = "ghl_sync_lock_{$user_id}";
set_transient( $lock_key, 1, 10 );

// Throttle logins (1 hour)
$last_login_key = "ghl_last_login_{$user_id}";
set_transient( $last_login_key, time(), HOUR_IN_SECONDS );
```

## Multisite Support

### Table Isolation
- Each site has its own tables via `$wpdb->prefix`
- `site_id` column provides additional isolation
- Queue processor switches blog context: `switch_to_blog()` / `restore_current_blog()`

### Activation
- Creates tables for **all existing sites** on activation
- New sites added later will need manual table creation (future: `wpmu_new_blog` hook)

### Settings
- Per-site settings via `get_option()`
- Network settings via `get_site_option()` (if needed)

### Cleanup
- Runs per-site (respects current site context)
- Can drop all sites' tables: `Database::drop_all_sites_tables()`

## Performance Impact

### Before Queue System
- ❌ Direct API calls blocked page loads
- ❌ profile_update fired multiple times → API spam
- ❌ Login tracking added notes every time → thousands of API calls
- ❌ No retry on failure
- ❌ wp_options used for logging (huge performance hit)

### After Queue System
- ✅ Async processing via WP-Cron
- ✅ Debouncing prevents duplicate API calls
- ✅ Throttling reduces login API spam to 1/hour
- ✅ Contact caching reduces lookups
- ✅ Retry logic (max 3 attempts)
- ✅ Custom tables for queue and logs
- ✅ Transient-based caching (fast, auto-cleanup)

## Future Enhancements

### WooCommerce Integration
```php
// Hook into order completion
add_action( 'woocommerce_order_status_completed', function( $order_id ) {
    $order = wc_get_order( $order_id );
    $queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
    $queue_manager->add_to_queue( 'order', $order_id, 'order_completed', [
        'email' => $order->get_billing_email(),
        'total' => $order->get_total(),
        // ... order data
    ] );
} );
```

### BuddyBoss Integration
```php
// Hook into group creation
add_action( 'groups_create_group', function( $group_id ) {
    $queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
    $queue_manager->add_to_queue( 'group', $group_id, 'group_created', [
        // ... group data
    ] );
} );
```

### LearnDash Integration
```php
// Hook into course completion
add_action( 'learndash_course_completed', function( $data ) {
    $queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
    $queue_manager->add_to_queue( 'course', $data['course']->ID, 'course_completed', [
        'user_id' => $data['user']->ID,
        // ... course data
    ] );
} );
```

## Admin UI (Future)

### Queue Status Dashboard
- Display pending/failed/completed counts
- Retry failed items manually
- View queue items and their status
- Clear completed items

### Sync Logs Viewer
- Filter by user, action, status
- View execution time
- Export logs
- Real-time sync monitoring

## Testing

### Test Queue Processing
```php
// Add test item
$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
$queue_id = $queue_manager->add_to_queue( 'user', 1, 'test_action', [ 'test' => 'data' ] );

// Manually trigger cron
do_action( 'ghl_crm_process_queue' );

// Check status
$status = $queue_manager->get_queue_status();
var_dump( $status );
```

### Test Multisite
```php
// Switch between sites
switch_to_blog( 2 );
$status = $queue_manager->get_queue_status();
restore_current_blog();
```

## Security

- ✅ All database queries use prepared statements
- ✅ Payload data JSON-encoded before storage
- ✅ Error messages sanitized in logs
- ✅ Site ID validation in multisite
- ✅ Capability checks for admin actions

## Best Practices

1. **Always use the queue** - Never make direct API calls from user-facing hooks
2. **Cache aggressively** - Use transients for frequently accessed data
3. **Debounce/throttle** - Prevent API spam on rapid-fire events
4. **Log everything** - Track success and failures for debugging
5. **Test multisite** - Ensure proper site isolation
6. **Monitor queue** - Watch for growing failed items
7. **Cleanup regularly** - Keep tables lean with cron jobs
