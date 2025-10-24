# Queue System Safety Measures

## Overview
Comprehensive safety mechanisms to prevent database bloat, race conditions, and site performance issues.

---

## 🛡️ Duplicate Prevention

### 1. Database-Level Constraint
```sql
UNIQUE KEY unique_pending_item (item_type, item_id, action, site_id, status)
```
**What it does:**
- Prevents duplicate rows at database level
- Enforces uniqueness on: item type + item ID + action + site + status
- MySQL will reject duplicate inserts automatically

**Example:**
- User 123 profile update queued → ✅ Added
- User 123 profile update queued again → ❌ Blocked (duplicate)
- User 123 profile update queued after first completes → ✅ Added (status changed)

### 2. Application-Level Check + Payload Update
```php
// Check for existing pending item before insert
$existing = $wpdb->get_var("SELECT id FROM queue WHERE ... AND status = 'pending'");
if ( $existing ) {
    // UPDATE payload with latest data (ensures newest data synced)
    $wpdb->update( $table, ['payload' => $new_payload], ['id' => $existing] );
    return (int) $existing;
}
```

**Benefits:**
- Faster than relying on MySQL error
- **Updates payload with latest data** (critical for rapid changes)
- Returns existing queue ID for tracking
- Prevents unnecessary database operations

**Example - Rapid Updates:**
- User 123 updates email → Queued with `email: old@example.com`
- User 123 updates email again → **Payload updated to** `email: new@example.com`
- Queue processes → Syncs latest email `new@example.com` ✅

---

## � Payload Update Strategy

### Latest Data Always Wins
When a user makes rapid changes (e.g., updates profile twice before queue processes), the system ensures the **newest data** gets synced, not the first queued data.

**How it works:**
1. User updates email to `old@example.com` → Item queued
2. User updates email to `new@example.com` → **Payload updated in queue**
3. Queue processes → Syncs `new@example.com` ✅

**Without this feature:**
- ❌ First update queued with stale data
- ❌ Second update ignored
- ❌ API syncs outdated information

**With payload updates:**
- ✅ Duplicate detected
- ✅ Payload replaced with latest data
- ✅ API syncs most recent information

**Performance:**
- Only 1 database UPDATE instead of failed INSERT
- No duplicate queue items
- Same queue ID returned

**Use cases:**
- Rapid profile edits
- Form submission corrections
- Admin bulk updates
- Import/sync operations

### Visual Example

```
Timeline: User rapid profile updates

00:00 - User clicks "Save" with email: old@example.com
        └─ Queue: [{ id: 1, payload: {email: "old@example.com"}, status: "pending" }]

00:02 - User corrects mistake, clicks "Save" with email: new@example.com
        └─ Queue: [{ id: 1, payload: {email: "new@example.com"}, status: "pending" }]
        └─ NOTE: Same queue ID, payload UPDATED ✅

00:05 - User changes again to: final@example.com
        └─ Queue: [{ id: 1, payload: {email: "final@example.com"}, status: "pending" }]
        └─ NOTE: Still same queue ID, payload UPDATED again ✅

01:00 - Cron processes queue
        └─ API Call: update_contact({ email: "final@example.com" })
        └─ Result: Latest data synced correctly! 🎉
```

**Key Points:**
- ✅ Only 1 queue item created (id: 1)
- ✅ Payload updated 2 times
- ✅ Final sync uses most recent data
- ✅ No API spam (only 1 call)
- ✅ No stale data synced

---

## �📊 Queue Size Limits

### Per-Site Limit: 10,000 Pending Items
```php
if ( $queue_count >= 10000 ) {
    error_log('Queue limit reached');
    return false; // Reject new items
}
```

**Protection:**
- Prevents runaway queue growth
- Per-site isolation (multisite safe)
- Logs warning when limit reached

**What happens when limit reached:**
1. New queue additions rejected
2. Error logged to WordPress error log
3. Existing items continue processing
4. Admin should investigate failed items

---

## 🔒 Race Condition Prevention

### WooCommerce Action Scheduler
The plugin uses **WooCommerce Action Scheduler** instead of WP-Cron for reliability:

**Benefits:**
- ✅ Runs on actual web requests (not dependent on site traffic)
- ✅ Better for low-traffic sites
- ✅ Automatic retry on failure
- ✅ Admin UI to view scheduled actions
- ✅ Works with caching plugins
- ✅ Fallback to WP-Cron if not available

### Concurrent Processing Lock
```php
// Transient lock prevents multiple jobs running simultaneously
$lock_key = 'ghl_crm_queue_processing';
if ( get_transient( $lock_key ) ) {
    return; // Already processing
}
set_transient( $lock_key, time(), 2 * MINUTE_IN_SECONDS );
```

**Prevents:**
- Multiple Action Scheduler jobs processing same items
- Duplicate API calls
- Database deadlocks
- Race conditions in multisite

**Lock Duration:** 2 minutes
- Long enough for batch processing
- Short enough to recover from crashes

---

## 🧹 Aggressive Cleanup

### Automatic Cleanup (Daily via Action Scheduler)

**Schedule:** Runs daily at midnight via Action Scheduler

#### 1. Completed Items → 1 Day Retention
```php
DELETE completed items older than 1 day
```
**Reason:** Keep table lean, completed items rarely needed

#### 2. Failed Items → 7 Days Retention
```php
DELETE failed items older than 7 days
```
**Reason:** Allow time for admin review/debugging

#### 3. Logs → 30 Days Retention
```php
DELETE logs older than 30 days
```
**Reason:** Historical data for troubleshooting

#### 4. Emergency Purge → 50,000 Row Threshold
```php
if ( $queue_count > 50000 ) {
    DELETE oldest 25k completed/failed items (regardless of age)
}
```
**Trigger:** Queue table exceeds 50k rows  
**Action:** Immediate purge of 25k oldest items  
**Purpose:** Prevent database performance degradation

---

## ⏱️ Stale Item Recovery

### 5-Minute Timeout Protection
```php
// Reset items stuck in processing for >5 minutes
UPDATE status = 'pending' 
WHERE updated_at < NOW() - 5 minutes 
AND attempts > 0 
AND attempts < 3
```

**Handles:**
- Crashed cron jobs
- PHP timeouts
- Server restarts
- Network failures

**Safety:**
- Only resets items under max attempts (3)
- Preserves attempt count
- Prevents infinite loops

---

## 📈 Health Monitoring

### Queue Status with Health Indicators
```php
$status = $queue_manager->get_queue_status();
```

**Returns:**
```php
[
    'pending'         => 150,
    'failed'          => 5,
    'completed_24h'   => 1200,
    'total_items'     => 200,
    'oldest_pending_minutes' => 2,
    'health'          => 'good', // good|warning|critical
    'warnings'        => [],
    'site_id'         => 1,
    'max_queue_limit' => 10000,
]
```

### Health Levels

#### 🟢 Good
- Pending < 1,000
- Failed < 100
- Oldest pending < 60 minutes

#### 🟡 Warning
- Pending > 1,000
- Failed > 100
- Oldest pending > 60 minutes
- Total items > 50,000

#### 🔴 Critical
- Pending > 5,000
- Multiple warning conditions

### Warning Messages
- "High pending count: X items"
- "High failure rate: X failed items"
- "Oldest pending item: X minutes old"
- "Large queue table: X total rows (cleanup recommended)"

---

## 🔄 Processing Safety

### Batch Size Limit: 10 Items
```php
LIMIT 10 // Process only 10 items per cron run
```
**Benefits:**
- Prevents PHP timeouts
- Distributes load over time
- Allows other processes to run

### Max Attempts: 3
```php
const MAX_ATTEMPTS = 3;
```
**After 3 failures:**
- Item marked as `failed`
- Stops retrying
- Preserved for admin review

### Execution Order: FIFO
```php
ORDER BY created_at ASC
```
**Ensures:**
- Oldest items processed first
- Fair queue processing
- Predictable behavior

---

## 🌐 Multisite Isolation

### Per-Site Protection
```php
WHERE site_id = %d // All queries filtered by site_id
```

**Guarantees:**
- Site A's queue doesn't affect Site B
- Independent limits per site
- Isolated cleanup schedules
- Separate health monitoring

### Blog Context Switching
```php
switch_to_blog( $site->blog_id );
// Process queue
restore_current_blog();
```
**Ensures:**
- Correct database prefix ($wpdb->prefix)
- Proper transient namespacing
- Right API credentials loaded

---

## 🚨 Error Handling

### Database Insertion Failures
```php
if ( ! $inserted ) {
    return false; // Graceful failure
}
```
**Possible causes:**
- Duplicate key violation (UNIQUE constraint)
- Database connection lost
- Table locked
- Disk space full

**Handling:**
- Returns false (doesn't crash)
- Logs error if needed
- User action completes normally

### API Call Failures
```php
catch ( \Exception $e ) {
    // Mark as failed or retry
    $status = ( $attempts >= 3 ) ? 'failed' : 'pending';
}
```
**Retry Logic:**
- Attempt 1: Retry immediately
- Attempt 2: Retry next cron run
- Attempt 3: Mark as failed

### Payload Decoding Errors
```php
$payload = json_decode( $item->payload, true );
if ( json_last_error() !== JSON_ERROR_NONE ) {
    throw new \Exception('Invalid payload');
}
```

---

## 📝 Logging Strategy

### Success Logging
```php
// Only log to database (not error_log)
$this->log_sync_event( $user_id, $action, 'success' );
```
**Reduces:** Log file bloat

### Error Logging
```php
// Log to both database AND error_log
error_log("GHL CRM Sync Error [Site X]: ...");
$this->log_sync_event( $user_id, $action, 'error', $message );
```
**Ensures:** Critical errors always visible

### Queue Limit Warnings
```php
error_log('GHL CRM Queue Limit Reached [Site X]: Cannot add more items');
```

### Emergency Cleanup Alerts
```php
error_log('GHL CRM Emergency Cleanup [Site X]: Queue exceeded 50k rows');
```

---

## 🎯 Best Practices

### For Plugin Developers

1. **Always check return value**
   ```php
   $queue_id = $queue_manager->add_to_queue(...);
   if ( ! $queue_id ) {
       // Handle failure (queue full or duplicate)
   }
   ```

2. **Monitor queue health**
   ```php
   $status = $queue_manager->get_queue_status();
   if ( $status['health'] === 'critical' ) {
       // Alert admin
   }
   ```

3. **Don't bypass the queue**
   ```php
   // ❌ BAD: Direct API call
   $contact_resource->create( $data );
   
   // ✅ GOOD: Use queue
   $queue_manager->add_to_queue( 'user', $user_id, 'create', $data );
   ```

### For Site Admins

1. **Monitor failed items**
   - Check queue status regularly
   - Investigate why items fail
   - Fix API credentials if needed

2. **Watch for warnings**
   - High pending count = slow processing or API issues
   - High failure rate = configuration problem
   - Old pending items = cron not running

3. **Emergency actions**
   ```php
   // Manually trigger queue processing
   do_action( 'ghl_crm_process_queue' );
   
   // Manually trigger cleanup
   \GHL_CRM\Core\Database::get_instance()->cleanup();
   
   // View scheduled actions in admin
   // Navigate to: WooCommerce → Status → Scheduled Actions
   // Filter by group: ghl-crm
   ```

---

## 🧪 Testing Safety Measures

### Test Duplicate Prevention
```php
// Add same item twice
$id1 = $queue_manager->add_to_queue( 'user', 1, 'update', ['email' => 'old@test.com'] );
$id2 = $queue_manager->add_to_queue( 'user', 1, 'update', ['email' => 'new@test.com'] );

// Should return same ID
assert( $id1 === $id2 );

// Verify payload was updated to latest data
global $wpdb;
$item = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}ghl_sync_queue WHERE id = $id1" );
$payload = json_decode( $item->payload, true );
assert( $payload['email'] === 'new@test.com' ); // Latest data wins!
```

### Test Queue Limit
```php
// Try to add 10,001 items
for ( $i = 0; $i < 10001; $i++ ) {
    $result = $queue_manager->add_to_queue( 'user', $i, 'test', [] );
}
// Last item should fail
assert( $result === false );
```

### Test Stale Cleanup
```php
// Manually set old updated_at
$wpdb->update( $table, ['updated_at' => '2020-01-01'], ['id' => 1] );

// Run cleanup
$queue_manager->cleanup_stale_items();

// Item should be reset to pending
$item = $wpdb->get_row("SELECT * FROM $table WHERE id = 1");
assert( $item->status === 'pending' );
```

---

## 📊 Performance Impact

### Database Queries Per Request
- **Add to queue:** 2 queries (check + insert)
- **Process batch:** ~21 queries (1 select + 10 items × 2 updates)
- **Health check:** 5 queries (status, counts)

### Memory Usage
- **Per queue item:** ~1KB (JSON payload)
- **Max batch:** 10KB (10 items)
- **Transient cache:** 15KB per contact (15min)

### Action Scheduler Impact
- **Queue Processing:** Every minute (60-second interval)
- **Cleanup:** Daily at midnight
- **Duration:** <1 second for 10 items
- **Load:** Minimal (background async)
- **View Actions:** WooCommerce → Status → Scheduled Actions

---

## 🔧 Maintenance Commands

### Clear All Queue Items (Emergency)
```php
global $wpdb;
$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}ghl_sync_queue");
```

### Reset Failed Items to Pending
```php
global $wpdb;
$wpdb->query("UPDATE {$wpdb->prefix}ghl_sync_queue SET status = 'pending', attempts = 0 WHERE status = 'failed'");
```

### Get Table Sizes
```php
global $wpdb;
$size = $wpdb->get_var("
    SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) 
    FROM information_schema.TABLES 
    WHERE TABLE_NAME = '{$wpdb->prefix}ghl_sync_queue'
");
echo "Queue table: {$size} MB";
```

---

## ✅ Summary Checklist

- [x] Unique index prevents database-level duplicates
- [x] Application checks before insert
- [x] **Payload updates on duplicate (latest data always wins)**
- [x] Per-site queue limit (10k items)
- [x] Concurrent processing lock (2min)
- [x] Aggressive cleanup (1 day completed, 7 days failed)
- [x] Emergency purge (50k row threshold)
- [x] Stale item recovery (5min timeout)
- [x] Health monitoring (good/warning/critical)
- [x] Batch processing (10 items)
- [x] Max retry attempts (3)
- [x] Multisite isolation (per-site protection)
- [x] Comprehensive error handling
- [x] Smart logging (errors to error_log)

**Result:** Production-ready queue system that won't bloat or crash sites, and always syncs the latest data! 🎉
