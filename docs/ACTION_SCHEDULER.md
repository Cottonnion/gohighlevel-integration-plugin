# Action Scheduler Integration

## Overview
The plugin uses **WooCommerce Action Scheduler** for queue processing instead of WP-Cron, providing more reliable background task execution.

---

## Why Action Scheduler?

### Problems with WP-Cron
- ❌ Only runs when someone visits the site
- ❌ Doesn't work on low-traffic sites
- ❌ Broken by caching plugins
- ❌ Unreliable timing
- ❌ Can be disabled by `DISABLE_WP_CRON`
- ❌ No admin UI to monitor jobs

### Benefits of Action Scheduler
- ✅ Runs on actual web requests (more reliable)
- ✅ Works on low-traffic sites
- ✅ Compatible with all caching plugins
- ✅ Automatic retry on failure
- ✅ Built-in admin UI (WooCommerce → Status → Scheduled Actions)
- ✅ Database-backed (survives server restarts)
- ✅ Distributed processing (handles large queues)
- ✅ Used by WooCommerce (battle-tested)

---

## Implementation

### Queue Processing Action
```php
// Scheduled every 60 seconds
as_schedule_recurring_action( 
    time(), 
    60, 
    'ghl_crm_process_queue', 
    [], 
    'ghl-crm' // Group name
);
```

**Parameters:**
- **Timestamp:** Starts immediately
- **Interval:** 60 seconds (1 minute)
- **Hook:** `ghl_crm_process_queue`
- **Args:** Empty array (no arguments needed)
- **Group:** `ghl-crm` (for filtering in admin)

### Database Cleanup Action
```php
// Scheduled daily at midnight
as_schedule_recurring_action( 
    strtotime( 'tomorrow midnight' ), 
    DAY_IN_SECONDS, 
    'ghl_crm_cleanup_database', 
    [], 
    'ghl-crm'
);
```

**Parameters:**
- **Timestamp:** Tomorrow at midnight (00:00)
- **Interval:** 86400 seconds (24 hours)
- **Hook:** `ghl_crm_cleanup_database`
- **Args:** Empty array
- **Group:** `ghl-crm`

---

## Fallback to WP-Cron

The plugin automatically falls back to WP-Cron if Action Scheduler is not available:

```php
if ( function_exists( 'as_schedule_recurring_action' ) ) {
    // Use Action Scheduler
    as_schedule_recurring_action( ... );
} else {
    // Fallback to WP-Cron
    wp_schedule_event( time(), 'every_minute', 'ghl_crm_process_queue' );
}
```

**When fallback occurs:**
- WooCommerce not installed
- WooCommerce version < 3.5 (before Action Scheduler bundled)
- Action Scheduler manually disabled

**Checking scheduler type:**
```php
$type = \GHL_CRM\Sync\QueueManager::get_scheduler_type();
// Returns: 'action_scheduler' or 'wp_cron'
```

---

## Admin UI

### Viewing Scheduled Actions

**Location:** WooCommerce → Status → Scheduled Actions

**Filter by plugin:**
1. Click "Filter" dropdown
2. Select group: `ghl-crm`
3. View all plugin actions

**Actions visible:**
- `ghl_crm_process_queue` - Runs every minute
- `ghl_crm_cleanup_database` - Runs daily at midnight

**Status indicators:**
- 🔵 **Pending** - Scheduled, waiting to run
- 🟢 **Complete** - Successfully executed
- 🔴 **Failed** - Execution failed (will retry)
- 🟡 **Canceled** - Manually canceled

### Action Details

Click any action to view:
- **Next run time**
- **Arguments** (if any)
- **Group** (ghl-crm)
- **Schedule** (recurring)
- **Status**
- **Claim ID** (if processing)
- **Log messages** (errors)

### Manual Actions

**Run immediately:**
1. Find the action
2. Click "Run"
3. Action executes instantly

**Cancel action:**
1. Find the action
2. Click "Cancel"
3. Will not run again (removes from schedule)

**View logs:**
1. Click action to view details
2. See execution history
3. Debug errors

---

## Activation & Deactivation

### On Activation
```php
// Schedule queue processing (every minute)
as_schedule_recurring_action( time(), 60, 'ghl_crm_process_queue', [], 'ghl-crm' );

// Schedule cleanup (daily at midnight)
as_schedule_recurring_action( strtotime('tomorrow midnight'), DAY_IN_SECONDS, 'ghl_crm_cleanup_database', [], 'ghl-crm' );
```

### On Deactivation
```php
// Unschedule all actions
as_unschedule_all_actions( 'ghl_crm_process_queue', [], 'ghl-crm' );
as_unschedule_all_actions( 'ghl_crm_cleanup_database', [], 'ghl-crm' );
```

**Important:** Actions are automatically removed on plugin deactivation to prevent ghost jobs.

---

## Multisite Behavior

### Single Site vs Multisite

**Single Site:**
- 1 recurring action per hook
- Processes current site's queue

**Multisite:**
- 1 recurring action per hook (network-wide)
- Action switches between sites automatically
- Processes each site's queue in sequence

**Example (3 sites):**
```
00:00 - Action runs
  └─ Switch to Site 1 → Process 10 items
  └─ Switch to Site 2 → Process 10 items
  └─ Switch to Site 3 → Process 10 items
  └─ Restore current blog

01:00 - Action runs again (repeats)
```

**Performance:**
- Max 30 items per minute (10 per site × 3 sites)
- Each site isolated
- No cross-site interference

---

## Troubleshooting

### Action Not Running

**Check 1: Is Action Scheduler available?**
```php
if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
    echo 'Action Scheduler not available!';
}
```

**Check 2: Is action scheduled?**
```php
$timestamp = as_next_scheduled_action( 'ghl_crm_process_queue' );
if ( false === $timestamp ) {
    echo 'Action not scheduled!';
}
```

**Check 3: View in admin**
- Go to WooCommerce → Status → Scheduled Actions
- Filter by group: `ghl-crm`
- Look for status: Pending or Failed

**Check 4: Manual trigger**
```php
do_action( 'ghl_crm_process_queue' );
```

### Action Failing

**View error logs:**
1. WooCommerce → Status → Scheduled Actions
2. Find failed action
3. Click to view details
4. Check "Log" section for error message

**Common issues:**
- API credentials invalid
- Rate limit exceeded
- Network timeout
- PHP memory limit

**Automatic retry:**
- Action Scheduler retries failed actions automatically
- Max 3 attempts before marking as failed permanently

### Queue Not Processing

**Debug checklist:**
1. ✅ Action Scheduler installed (WooCommerce active)
2. ✅ Action scheduled (check admin)
3. ✅ Site receiving traffic (actions run on requests)
4. ✅ No fatal PHP errors
5. ✅ Database tables exist
6. ✅ API credentials valid

**Force processing:**
```php
// Manually trigger
\GHL_CRM\Sync\QueueManager::get_instance()->process_queue();
```

---

## Performance Considerations

### Database Impact

**Action Scheduler tables:**
- `wp_actionscheduler_actions` - Scheduled actions
- `wp_actionscheduler_claims` - Claimed actions
- `wp_actionscheduler_groups` - Action groups
- `wp_actionscheduler_logs` - Execution logs

**Auto-cleanup:**
- Completed actions: 30 days retention
- Failed actions: 45 days retention
- Logs: 10 days retention
- Configurable via filters

### Load Distribution

**Every minute processing:**
- Processes max 10 items per site
- Takes <1 second typically
- Minimal server impact
- Spreads load over time

**Daily cleanup:**
- Runs at midnight (low traffic)
- Deletes old records
- Optimizes tables
- Prevents bloat

---

## Code Examples

### Schedule Custom Action
```php
// One-time action (runs once)
as_schedule_single_action( 
    strtotime( '+1 hour' ), 
    'my_custom_action', 
    [ 'param' => 'value' ], 
    'ghl-crm' 
);

// Recurring action (repeats)
as_schedule_recurring_action( 
    time(), 
    HOUR_IN_SECONDS, 
    'my_custom_action', 
    [], 
    'ghl-crm' 
);
```

### Check Action Status
```php
// Get next scheduled time
$next = as_next_scheduled_action( 'ghl_crm_process_queue' );
if ( $next ) {
    echo 'Next run: ' . date( 'Y-m-d H:i:s', $next );
}

// Check if action exists
$exists = as_has_scheduled_action( 'ghl_crm_process_queue' );
```

### Unschedule Actions
```php
// Unschedule specific action
as_unschedule_action( 'ghl_crm_process_queue', [], 'ghl-crm' );

// Unschedule all instances
as_unschedule_all_actions( 'ghl_crm_process_queue', [], 'ghl-crm' );
```

---

## Comparison: Action Scheduler vs WP-Cron

| Feature | Action Scheduler | WP-Cron |
|---------|------------------|---------|
| **Reliability** | ✅ High | ❌ Low |
| **Low Traffic Sites** | ✅ Works | ❌ Doesn't work |
| **Caching Compatible** | ✅ Yes | ❌ No |
| **Admin UI** | ✅ Yes | ❌ No |
| **Automatic Retry** | ✅ Yes | ❌ No |
| **Distributed** | ✅ Yes | ❌ No |
| **Database-backed** | ✅ Yes | ❌ No (options table) |
| **Parallel Processing** | ✅ Yes | ❌ No |
| **Requires Plugin** | ⚠️ WooCommerce | ✅ Built-in |

---

## Best Practices

### 1. Use Groups
```php
// Always specify group for easy filtering
as_schedule_recurring_action( ..., 'ghl-crm' );
```

### 2. Monitor Failures
- Check admin regularly
- Set up alerts for failures
- Review error logs

### 3. Don't Over-Schedule
- Avoid scheduling too frequently
- Balance performance vs freshness
- 1-minute interval is aggressive (good for this use case)

### 4. Clean Up on Deactivation
```php
// Always unschedule on deactivation
as_unschedule_all_actions( 'hook_name', [], 'ghl-crm' );
```

### 5. Test Multisite
- Verify site switching works
- Check isolation between sites
- Monitor performance with many sites

---

## Summary

✅ **Action Scheduler Integrated**
- Queue processing every 60 seconds
- Cleanup daily at midnight
- Automatic fallback to WP-Cron
- Full admin UI available
- Reliable and battle-tested

🎯 **Key Benefits**
- Works on all sites (low/high traffic)
- Compatible with caching
- Automatic retry on failure
- Easy monitoring via admin
- Database-backed reliability

🚀 **Production Ready**
- Used by WooCommerce (millions of sites)
- Proven reliability
- Excellent performance
- Full WordPress integration
