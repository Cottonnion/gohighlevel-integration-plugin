# Scope Checking System Implementation

## Overview
Implemented a comprehensive scope checking system that verifies if the connected API token (OAuth or manual API key) has access to required GoHighLevel API scopes. The system provides real-time feedback to users about missing permissions.

## Components Created

### 1. ScopeChecker Class (`src/Core/ScopeChecker.php`)

A utility class that checks API scope permissions and provides user feedback.

**Key Features:**
- **Automatic Detection**: Tests actual API endpoints to verify access (401 errors indicate missing scopes)
- **Smart Caching**: Results cached for 5 minutes to avoid excessive API calls
- **Visual Feedback**: Two display modes (notices and inline badges)
- **Multi-scope Support**: Can check multiple scopes at once

**Feature to Scope Mapping:**
```php
'contacts'       => ['contacts.readonly', 'contacts.write']
'tags'           => ['contacts/tags.readonly', 'contacts/tags.write']
'custom_fields'  => ['locations/customFields.readonly', 'locations/customFields.write']
'custom_objects' => ['objects/schema.readonly', 'objects/records.readonly', 'objects/records.write']
'associations'   => ['associations.readonly', 'associations.write', 'associations/relations.readonly', 'associations/relations.write']
'forms'          => ['forms.readonly']
'locations'      => ['locations.readonly']
'tasks'          => ['locations/tasks.write']
```

**Public Methods:**
- `check_scope($scope_name)` - Check single scope access
- `check_multiple_scopes($scope_names)` - Check multiple scopes
- `clear_cache($scope_name = null)` - Clear cached results
- `render_scope_notice($feature_name, $check_now = false)` - Display error notice
- `render_scope_badge($feature_name, $check_now = false)` - Display inline badge

### 2. AJAX Endpoints (SettingsManager)

Added two new AJAX handlers for scope checking:

**`ghl_crm_check_scope`**
- Check a single scope
- Parameters: `scope` (required), `force` (optional boolean)
- Response: `{ has_access, message, checked_at }`

**`ghl_crm_check_all_scopes`**
- Check all defined scopes at once
- Parameters: `force` (optional boolean to bypass cache)
- Response: Full results + summary statistics

### 3. Template Integration

Updated all admin templates to display scope warnings:

**`templates/admin/custom-objects.php`**
- Checks: `custom_objects`, `associations`

**`templates/admin/forms.php`**
- Checks: `forms`

**`templates/admin/field-mapping.php`**
- Checks: `contacts`, `custom_fields`

**`templates/admin/integrations.php`**
- Checks: `contacts`, `tags`

**`templates/admin/dashboard.php`**
- Displays all 17 required scopes in OAuth tab
- Visual scope tags matching GHL UI design

## Required OAuth Scopes

The plugin requires these 17 scopes to function properly:

1. **View Contacts** - Read contact data
2. **Edit Contacts** - Create/update contacts
3. **View Tags** - Read contact tags
4. **Edit Tags** - Add/remove tags
5. **View Locations** - Read location settings
6. **Edit Location Tasks** - Manage tasks
7. **View Custom Fields** - Read custom field definitions
8. **Edit Custom Fields** - Create/update custom fields
9. **View Objects Schema** - Read custom object schemas
10. **Edit Objects Schema** - Modify custom object schemas
11. **View Objects Record** - Read custom object records
12. **Edit Objects Record** - Create/update records
13. **View Associations** - Read object associations
14. **Write Associations** - Create object associations
15. **View Associations Relation** - Read association relationships
16. **Write Associations Relation** - Create association relationships
17. **View Forms** - Read GHL forms

## Error Detection

The system detects scope errors by:

1. Making a minimal API request to relevant endpoint
2. Catching exceptions with HTTP 401 status code
3. Parsing error message: `"The token is not authorized for this scope."`
4. Caching the result to avoid repeated failures

## User Experience

**When Missing Scopes:**
```
┌─────────────────────────────────────────────────┐
│ ⚠ Missing Permissions                           │
│ Error: The token is not authorized for this     │
│ scope.                                          │
│                                                 │
│ Required Scopes: contacts.readonly,             │
│ contacts.write                                  │
│                                                 │
│ Please reconnect your GoHighLevel account...   │
│ [Go to Connection Settings]                     │
└─────────────────────────────────────────────────┘
```

**Inline Badge (Success):**
`✓ Access Granted` (green badge)

**Inline Badge (Denied):**
`✗ No Access` (red badge with tooltip)

## Dashboard Improvements

### OAuth Tab
Displays all 17 required scopes in a professional UI:
- Info icon with explanation that these scopes are **necessary for the plugin to function**
- Tag-style scope badges (gray background)
- Matches GoHighLevel's design language
- Clear visual hierarchy
- Message: "These scopes are necessary for the plugin to function properly"

### Manual API Key Tab
Enhanced with detailed setup instructions:
- **Step-by-step guide** for creating a Private Integration
- Shows all 17 required scopes in **amber/yellow** badges (warning style)
- Instructions include:
  1. Settings → Integrations → Private Integrations
  2. Create new integration with name
  3. Select all required scopes
  4. Copy generated API Key
  5. Get Location ID from Settings → Business Profile
- Warning message: "These scopes are necessary for the plugin to function properly. Make sure to select all of them when creating your private integration."

## Cache Strategy

- **Duration**: 5 minutes
- **Storage**: WordPress transients API
- **Per-Scope**: Each feature cached independently
- **Manual Clear**: Available via AJAX or `ScopeChecker::clear_cache()`

## Usage Examples

### Template Usage
```php
// Check and display error notice if missing
\GHL_CRM\Core\ScopeChecker::render_scope_notice('contacts');

// Display inline badge
\GHL_CRM\Core\ScopeChecker::render_scope_badge('custom_objects');

// Force fresh check (bypass cache)
\GHL_CRM\Core\ScopeChecker::render_scope_notice('forms', true);
```

### JavaScript Usage
```javascript
// Check single scope
jQuery.post(ajaxurl, {
    action: 'ghl_crm_check_scope',
    nonce: ghlCrmAdmin.nonce,
    scope: 'contacts',
    force: true
}, function(response) {
    if (response.success) {
        console.log('Has access:', response.data.has_access);
    }
});

// Check all scopes
jQuery.post(ajaxurl, {
    action: 'ghl_crm_check_all_scopes',
    nonce: ghlCrmAdmin.nonce,
    force: false
}, function(response) {
    console.log('Summary:', response.data.summary);
    console.log('Results:', response.data.scopes);
});
```

### Programmatic Usage
```php
// Check access
$result = \GHL_CRM\Core\ScopeChecker::check_scope('contacts');
if ($result['has_access']) {
    // Proceed with operation
}

// Get required scopes for a feature
$scopes = \GHL_CRM\Core\ScopeChecker::get_required_scopes('custom_objects');
// Returns: ['objects/schema.readonly', 'objects/records.readonly', 'objects/records.write']

// Clear cache after reconnection
\GHL_CRM\Core\ScopeChecker::clear_cache(); // Clear all
\GHL_CRM\Core\ScopeChecker::clear_cache('contacts'); // Clear specific
```

## Best Practices

1. **Cache First**: Always use cached results unless explicitly refreshing
2. **Check on Load**: Display scope warnings when pages load (not on every action)
3. **User Guidance**: Always provide clear path to fix issues (reconnect link)
4. **Graceful Degradation**: Show warnings but don't break UI entirely
5. **Performance**: Avoid checking scopes in loops or frequent operations

## Future Enhancements

Potential improvements:
- Admin dashboard widget showing scope status summary
- Email notifications when scopes become invalid
- Automatic scope refresh for OAuth connections
- Bulk scope verification on connection
- Settings page to customize required vs. optional scopes
