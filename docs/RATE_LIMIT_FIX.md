# Rate Limiting Fix: Location-Based Tracking

## The Problem (Before Fix)

**Issue:** Rate limits were tracked per WordPress site ID, not per GHL location ID.

**Scenario:**
- Site A (blog_id: 1) using GHL Location "abc123"
- Site B (blog_id: 2) using GHL Location "abc123"

**Old Behavior:**
```
Site A: ghl_rate_burst_1 → Counts up to 100
Site B: ghl_rate_burst_2 → Counts up to 100
Total API calls: 200 (WRONG! GHL only allows 100)
```

**Result:** Both sites could make 100 requests each (200 total), exceeding GHL's actual limit of 100 per location. This would trigger API throttling errors.

---

## The Solution (After Fix)

**Fixed:** Rate limits now track by GHL location ID (sub-account), shared across all sites using that location.

**New Behavior:**
```
Site A: ghl_rate_burst_{hash_of_location_id} → Counts to 100
Site B: ghl_rate_burst_{hash_of_location_id} → SAME counter!
Total API calls: 100 (CORRECT!)
```

**Result:** All sites sharing the same GHL location share the rate limit. When combined requests hit 100, all sites pause until the limit resets.

---

## How It Works Now

### 1. Location ID Retrieval

```php
private function get_ghl_location_id(): ?string {
    // Get from settings
    $location_id = $settings_manager->get_setting('ghl_location_id');
    
    // Fallback to OAuth if available
    if (empty($location_id)) {
        $location_id = $oauth_handler->get_location_id();
    }
    
    return $location_id;
}
```

### 2. Rate Limit Keys

**Old (per site):**
```php
$burst_key = 'ghl_rate_burst_' . get_current_blog_id(); // 1, 2, 3...
```

**New (per location):**
```php
$burst_key = 'ghl_rate_burst_' . md5($location_id); // abc123 hashed
```

### 3. Storage Method

**Old:** `get_transient()` / `set_transient()` - Per site
**New:** `get_site_transient()` / `set_site_transient()` - Network-wide

This ensures all sites in a multisite network see the same counters.

---

## Examples

### Example 1: Two Sites, Same Location

**Setup:**
- Site A (blog_id: 1) → Location ID: "abc123"
- Site B (blog_id: 2) → Location ID: "abc123"

**Processing:**
```
00:00 - Site A makes 60 API calls → Counter: 60/100
00:05 - Site B makes 30 API calls → Counter: 90/100
00:08 - Site A makes 15 API calls → Counter: 100/100 (LIMIT HIT)
00:09 - Site B tries → BLOCKED (counter at 100)
00:10 - Counter resets → Both sites can continue
```

**Result:** ✅ Correct - Combined 100 requests respected

### Example 2: Two Sites, Different Locations

**Setup:**
- Site A (blog_id: 1) → Location ID: "abc123"
- Site B (blog_id: 2) → Location ID: "xyz789"

**Processing:**
```
Site A: ghl_rate_burst_{hash_abc123} → Can make 100
Site B: ghl_rate_burst_{hash_xyz789} → Can make 100 (separate)
```

**Result:** ✅ Correct - Independent limits per location

### Example 3: No Location ID Configured

**Setup:**
- Site A → No location ID set

**Processing:**
```
check_rate_limits() → Returns true (allows processing)
track_api_request() → Does nothing (no tracking)
Logs warning: "No location ID configured for site 1"
```

**Result:** ⚠️ Processing continues but no rate limit protection

---

## Code Changes Summary

### Changed Methods

1. **check_rate_limits()**
   - Now gets location ID instead of site ID
   - Uses `get_site_transient()` for network-wide access
   - Includes location ID in error logs

2. **track_api_request()**
   - Tracks by location ID instead of site ID
   - Uses `set_site_transient()` for network-wide storage
   - Skips tracking if no location ID

3. **get_rate_limit_status()**
   - Returns status for location, not site
   - Shows if limits are shared across sites
   - Includes location ID in response

4. **get_ghl_location_id()** (NEW)
   - Retrieves location ID from settings
   - Falls back to OAuth handler
   - Returns null if not configured

---

## API Response Changes

### Old Response
```json
{
  "rate_limits": {
    "burst": { "used": 23, "limit": 100 },
    "daily": { "used": 1543, "limit": 200000 },
    "throttled": false
  }
}
```

### New Response
```json
{
  "rate_limits": {
    "burst": { "used": 23, "limit": 100 },
    "daily": { "used": 1543, "limit": 200000 },
    "throttled": false,
    "location_id": "abc123",
    "shared_across_sites": true
  }
}
```

**New Fields:**
- `location_id` - The GHL location being tracked
- `shared_across_sites` - True if multisite (limits shared)

---

## Testing

### Test 1: Same Location, Multiple Sites

```php
// Site 1
switch_to_blog(1);
$queue->process_queue(); // Makes 60 calls
$status = $queue->get_queue_status();
echo $status['rate_limits']['burst']['used']; // 60

// Site 2 (same location)
switch_to_blog(2);
$queue->process_queue(); // Makes 50 calls
$status = $queue->get_queue_status();
echo $status['rate_limits']['burst']['used']; // 110 (shared!)
```

**Expected:** Site 2 sees 110, gets throttled

### Test 2: Different Locations

```php
// Site 1 (location abc)
switch_to_blog(1);
for ($i = 0; $i < 100; $i++) { api_call(); }

// Site 2 (location xyz)
switch_to_blog(2);
for ($i = 0; $i < 100; $i++) { api_call(); } // Should work
```

**Expected:** Both succeed with independent counters

---

## Migration Notes

### For Existing Installations

**Old transients (per site):**
- `ghl_rate_burst_1`
- `ghl_rate_burst_2`
- `ghl_rate_daily_1_2025-10-25`

**New transients (per location):**
- `ghl_rate_burst_{md5_hash}`
- `ghl_rate_daily_{md5_hash}_2025-10-25`

**Migration:** No action needed! Old transients will expire naturally (10s/24h TTL). New tracking starts immediately.

### Configuration Required

**Each site must have location ID configured:**

1. **Via Settings:**
   ```php
   update_option('ghl_location_id', 'abc123');
   ```

2. **Via OAuth:** (Automatic if using OAuth)
   Location ID extracted from OAuth tokens

3. **Manual Check:**
   ```php
   $location_id = get_option('ghl_location_id');
   if (empty($location_id)) {
       echo '⚠️ Configure GHL Location ID';
   }
   ```

---

## Benefits

✅ **Accurate Rate Limiting** - Matches GHL's actual limits
✅ **Prevents API Throttling** - No more 429 errors from double-counting  
✅ **Multisite Compatible** - Sites sharing location share limits correctly
✅ **Independent Locations** - Different locations have separate limits
✅ **Transparent** - Status shows if limits are shared

---

## Potential Issues & Solutions

### Issue 1: No Location ID Configured

**Symptom:** Warning logs "No location ID configured"

**Solution:**
```php
// In admin settings or during OAuth setup
update_option('ghl_location_id', 'YOUR_LOCATION_ID');
```

### Issue 2: Sites Still Hitting Limits

**Cause:** Combined traffic from multiple sites exceeds location limit

**Solutions:**
1. Reduce BATCH_SIZE on some sites
2. Stagger queue processing times
3. Implement priority queuing
4. Upgrade GHL plan for higher limits

### Issue 3: Wrong Location ID

**Symptom:** Rate limits shared with wrong sites

**Check:**
```php
$queue = QueueManager::get_instance();
$status = $queue->get_queue_status();
echo $status['rate_limits']['location_id']; // Verify this
```

**Fix:** Update location ID in settings

---

## Summary

| Aspect | Before Fix | After Fix |
|--------|-----------|-----------|
| **Tracking Key** | Site ID | Location ID |
| **Storage** | Per-site transient | Network-wide transient |
| **Multiple Sites** | Separate counters | Shared counter |
| **API Compliance** | ❌ Could exceed limits | ✅ Respects limits |
| **Status** | Per-site only | Shows location & sharing |

**Result:** Rate limiting now correctly matches GHL's API behavior! 🎉
