# System Health Check Implementation

## Overview
The System Health Check feature provides comprehensive diagnostics for the GoHighLevel CRM Integration plugin, checking all critical system components and configurations.

## Location
**Tools Tab** → System Diagnostics → Run Health Check

## Implementation Details

### Backend (SettingsManager.php)

**AJAX Action**: `ghl_crm_system_health_check`

**Method**: `system_health_check()`

**Security**:
- Nonce verification: `ghl_crm_settings_nonce`
- Capability check: `manage_options`

### Diagnostic Checks

#### 1. WordPress Environment
- **WordPress Version**: Checks if >= 5.8 (warning if below)
- **PHP Version**: Checks if >= 7.4 (error if below)
- **Multisite Status**: Displays Yes/No

#### 2. Database Tables
Verifies existence of required tables:
- `{prefix}ghl_sync_queue`
- `{prefix}ghl_sync_logs`

**Status**: Error if any table is missing

#### 3. API Connection
- **Connection Type**: OAuth, Manual API, or Not Connected
- **Connection Verified**: Uses `is_connection_verified()` method
- **Location ID**: Shows first 10 characters (masked)

**Status Logic**:
- ✓ Success: Connected AND verified
- ⚠ Warning: Connected but NOT verified
- ✗ Error: Not connected

#### 4. PHP Extensions
Checks for required extensions:
- **cURL**: For API requests
- **JSON**: For data serialization
- **Multibyte String**: For string handling

**Status**: Error if any extension is missing

#### 5. Sync Queue Status
Queries sync queue table for current site:
- **Pending Items**: Count of pending jobs
- **Processing Items**: Count of active jobs
- **Failed Items**: Count of failed jobs

**Status Logic**:
- ✓ Success: Failed items <= 10
- ⚠ Warning: Failed items > 10

#### 6. File Permissions
- **Upload Directory**: Checks if writable
- **Plugin Directory**: Checks if readable

**Status**: Warning if either check fails

#### 7. Performance Settings
Informational display:
- **PHP Memory Limit**: From `ini_get('memory_limit')`
- **Max Execution Time**: From `ini_get('max_execution_time')`
- **Cache Duration**: From plugin settings
- **Batch Size**: From plugin settings

**Status**: Info only (no pass/fail)

### Response Format

```json
{
  "success": true,
  "data": {
    "overall_status": "success|warning|error",
    "checks": {
      "wordpress": {
        "label": "WordPress Environment",
        "status": "success|warning|error",
        "items": [
          {
            "label": "WordPress Version",
            "value": "6.4.0",
            "status": "success"
          }
        ]
      }
    },
    "timestamp": "2024-01-15 10:30:45",
    "message": "All system checks passed!"
  }
}
```

### Frontend (settings.js)

**Button ID**: `#health-check-btn`

**Handler**: `click.ghlHealthCheck` event

**Features**:
1. **Loading State**: Shows rotating spinner with "Running Diagnostics..." text
2. **CSS Animation**: Adds `@keyframes rotation` for spinner
3. **Results Display**: SweetAlert2 modal with formatted health report

**UI Components**:
- Color-coded status badges (green/amber/red)
- Status icons (✓/⚠/✗/ℹ)
- Organized sections with left border matching status color
- Scrollable results area (max-height: 500px)
- Timestamp footer
- Responsive table layout

**Modal Styling**:
- Width: 700px
- Custom class: `health-check-modal`
- Icon matches overall status
- Close button styled with primary color

### Status Colors

| Status | Color | Hex |
|--------|-------|-----|
| Success | Green | #10b981 |
| Warning | Amber | #f59e0b |
| Error | Red | #ef4444 |
| Info | Gray | #6b7280 |

### Overall Status Logic

The overall status is determined by the worst status across all checks:
1. If ANY check is **error** → Overall = **error**
2. Else if ANY check is **warning** → Overall = **warning**
3. Else → Overall = **success**

## Files Modified

1. **src/Core/SettingsManager.php**
   - Added `system_health_check()` method
   - Registered AJAX action in `init()`

2. **assets/admin/js/settings.js**
   - Added `#health-check-btn` click handler
   - Version bumped to 1.0.7

3. **templates/admin/partials/settings/tools.php**
   - Enabled health check button (removed `disabled` attribute)

4. **src/Core/AssetsManager.php**
   - Updated settings.js version to 1.0.7

## Usage

1. Navigate to **GoHighLevel CRM** → **Settings** → **Tools** tab
2. Scroll to **System Diagnostics** section
3. Click **Run Health Check** button
4. Review the comprehensive health report in the modal
5. Address any warnings or errors shown

## Multisite Compatibility

- Uses `get_current_blog_id()` for site-specific queue checks
- Database table checks use current site's table prefix
- All settings retrieved via `get_settings_array()` (multisite-aware)

## Error Handling

- **AJAX Errors**: Shows error toast notification
- **Missing SweetAlert2**: Falls back to toast notification
- **Backend Errors**: Returns JSON error with 403 status for permission issues
- **Button State**: Always restored after completion (success or error)

## Performance Considerations

- All checks execute quickly (< 1 second typically)
- Database queries limited to COUNT operations
- No external API calls unless verifying connection
- Results cached in browser session (modal display)

## Future Enhancements

Potential additions:
- [ ] Export health report as PDF
- [ ] Schedule automatic health checks
- [ ] Email notifications for critical issues
- [ ] Historical health check logs
- [ ] Specific fix recommendations for each issue
- [ ] One-click fixes for common problems
