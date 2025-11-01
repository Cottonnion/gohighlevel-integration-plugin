# Advanced Settings Implementation

## Overview
Added configurable advanced settings that control plugin performance, caching, and data retention. All settings are saved via AJAX and immediately take effect across the plugin.

---

## Settings Implemented

### 1. **Cache Duration** ⚡
- **Default:** 3600 seconds (1 hour)
- **Range:** 0 - 86400 seconds (0 = disabled, max = 24 hours)
- **Purpose:** Controls how long GHL contact data is cached
- **Impact:** 
  - Lower values = more API calls, fresher data
  - Higher values = fewer API calls, potentially stale data
  - Set to 0 to disable caching entirely

**Used by:**
- `ContactCache` class (`src/Sync/ContactCache.php`)
- Reduces duplicate API lookups during sync operations

---

### 2. **Batch Size** 📦
- **Default:** 50 items
- **Range:** 1 - 500 items
- **Purpose:** Number of queue items processed per cron run
- **Impact:**
  - Lower values = slower processing, less server load
  - Higher values = faster processing, more server load
  - Balance between speed and resource usage

**Used by:**
- `QueueManager` class (`src/Sync/QueueManager.php`)
- Controls how many sync operations run per batch

---

### 3. **Log Retention Period** 📅
- **Default:** 30 days
- **Range:** 1 - 365 days
- **Purpose:** How long to keep sync logs and completed queue items
- **Impact:**
  - Lower values = less database bloat, less historical data
  - Higher values = more debugging history, larger database
  - Automatic cleanup runs daily via Action Scheduler/WP-Cron

**Used by:**
- `Database` class (`src/Core/Database.php`)
- Cleanup runs daily at midnight
- Also cleans up completed queue items (1 day) and failed items (7 days)

---

## Data Management Tools

### Clear Cache Button
- Deletes all cached GHL contact data
- Deletes all rate limit tracking transients
- Flushes WordPress object cache (if available)
- Useful when:
  - Contact data in GHL has changed
  - Testing sync behavior
  - Troubleshooting cache issues

### Reset to Defaults Button
- Resets all plugin settings to default values
- **Preserves OAuth connection** (tokens, location ID, company ID)
- **Preserves manual API key** (if used)
- Confirmation required (cannot be undone)
- Page auto-reloads after reset

---

## Technical Implementation

### File Changes

#### 1. Template (`templates/admin/partials/settings/advanced.php`)
- Added "Log Retention Period" field
- All three settings now displayed in form
- Proper validation attributes (min, max)
- Save button triggers AJAX

#### 2. Settings Manager (`src/Core/SettingsManager.php`)
**Added AJAX Handlers:**
- `ghl_crm_clear_cache` - Clear all transients
- `ghl_crm_reset_settings` - Reset to defaults

**Methods Added:**
- `clear_cache()` - Clears GHL transients from database
- `reset_settings()` - Resets settings while preserving OAuth

#### 3. Database Cleanup (`src/Core/Database.php`)
**Updated `cleanup()` method:**
- Now reads `log_retention_days` from settings
- Uses configured value instead of hardcoded 30 days
- Validates and clamps value (1-365 days)

#### 4. Queue Manager (`src/Sync/QueueManager.php`)
**Updated `process_site_queue()` method:**
- Reads `batch_size` from settings
- Uses configured value instead of hardcoded 50
- Clamps between 1-500 items

#### 5. Contact Cache (`src/Sync/ContactCache.php`)
**Updated caching logic:**
- Added `get_cache_ttl()` private method
- Reads `cache_duration` from settings
- Uses configured value instead of hardcoded 15 minutes
- If set to 0, skips caching entirely
- Clamps between 0-86400 seconds

#### 6. JavaScript (`assets/admin/js/settings.js`)
**Added `initAdvancedSettings()` function:**
- Handles form submission via AJAX
- Clear Cache button handler
- Reset Settings button handler
- Success/error notifications
- Button state management (disabled during processing)

---

## Settings Storage

All settings stored in `wp_options` table:
```php
'ghl_crm_settings' => [
    'cache_duration' => 3600,      // seconds
    'batch_size' => 50,             // items
    'log_retention_days' => 30,    // days
    // ... other settings
]
```

**Multisite Support:**
- Settings are per-site (`get_option` automatically site-aware)
- Each subsite has independent settings
- Cleanup runs per-site with proper `site_id` filtering

---

## User Workflow

### Saving Settings
1. User modifies values in Advanced Settings tab
2. Clicks "Save Advanced Settings" button
3. AJAX call to `ghl_crm_save_settings`
4. Settings merged with existing settings (preserves other tabs)
5. Success notification displayed
6. Settings immediately active (no page reload needed)

### Clearing Cache
1. User clicks "Clear Cache" button
2. Confirmation dialog (optional, currently skipped)
3. AJAX call to `ghl_crm_clear_cache`
4. All GHL transients deleted from database
5. Success notification displayed

### Resetting Settings
1. User clicks "Reset to Defaults" button
2. **Confirmation required** (shows warning about OAuth preservation)
3. AJAX call to `ghl_crm_reset_settings`
4. Settings reset to defaults
5. OAuth tokens preserved
6. Page auto-reloads after 1.5 seconds

---

## Default Values Reference

```php
// Advanced Settings Defaults
const DEFAULTS = [
    'cache_duration'     => 3600,  // 1 hour
    'batch_size'         => 50,    // items per batch
    'log_retention_days' => 30,    // days
];

// QueueManager Constants (can now be overridden)
const MAX_ATTEMPTS = 5;           // Retry limit (still hardcoded)
const BATCH_SIZE   = 50;          // Now uses setting

// ContactCache Constants
const DEFAULT_CACHE_TTL = 900;    // 15 minutes (now uses setting)

// Database Cleanup
- Completed queue: 1 day (hardcoded)
- Failed queue: 7 days (hardcoded)
- Sync logs: configurable via setting
```

---

## Performance Impact

### Cache Duration
- **3600s (1 hour):** Good balance for most sites
- **Increase to 7200s (2 hours):** For sites with stable contact data
- **Decrease to 1800s (30 min):** For sites with frequently changing data
- **Set to 0:** Disable caching for real-time accuracy (more API calls)

### Batch Size
- **50 items:** Good balance for most sites
- **Increase to 100:** For sites with powerful servers
- **Decrease to 25:** For shared hosting or resource-constrained sites
- **Max 500:** Only for dedicated servers with high performance

### Log Retention
- **30 days:** Good balance for debugging
- **Increase to 90 days:** For compliance/audit requirements
- **Decrease to 7 days:** For sites with tight database limits
- **Minimum 1 day:** Not recommended (loses debugging history)

---

## Future Enhancements

### Potential Additions (Not Yet Implemented)
1. **Max Retry Attempts** - Currently hardcoded at 5
2. **Queue Processing Interval** - Currently 10s (Action Scheduler)
3. **Enable Debug Logging** - Toggle verbose error_log output
4. **Enable Rate Limiting** - Toggle rate limit enforcement
5. **Processing Timeout** - Max execution time per queue run
6. **Export/Import Settings** - JSON config backup/restore
7. **Manual Cleanup Runner** - Force cleanup without waiting for daily cron

See `docs/IMPLEMENTATION_SUMMARY.md` for full roadmap.

---

## Testing

### Verify Settings Work
1. Change **Cache Duration** to 60 seconds
2. Trigger a user sync
3. Check transient expiration in database:
   ```sql
   SELECT * FROM wp_options 
   WHERE option_name LIKE '_transient_timeout_ghl_contact_%'
   LIMIT 1;
   ```
4. Verify TTL is ~60 seconds from now

### Verify Batch Size
1. Change **Batch Size** to 10
2. Add 50 items to queue
3. Trigger queue processor
4. Check logs - should process 10 items, not 50

### Verify Log Retention
1. Change **Log Retention Period** to 1 day
2. Run manual cleanup:
   ```php
   \GHL_CRM\Core\Database::get_instance()->cleanup();
   ```
3. Check sync_log table - old logs deleted

---

## Notes

- All settings have proper validation (min/max enforcement)
- Settings save immediately via universal AJAX handler
- No page reload needed (except Reset to Defaults)
- Settings are properly escaped and sanitized
- Works with both OAuth and manual API key connections
- Fully multisite compatible
- No fatal errors on edge cases (missing settings use defaults)
