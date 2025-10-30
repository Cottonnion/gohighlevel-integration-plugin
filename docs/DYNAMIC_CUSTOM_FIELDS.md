# Dynamic Custom Fields Loading

## Overview
The Field Mapping page now supports **loading actual custom fields from your GoHighLevel location** instead of using hardcoded field lists.

## Features

### 1. Load Custom Fields Button
- Click **"Load Custom Fields from GoHighLevel"** button
- Fetches all custom fields from your GHL location
- Updates all dropdowns with standard + custom fields
- Shows count of loaded fields

### 2. Custom Field Format
Custom fields are prefixed with `custom.` followed by the field ID:
```
custom.abc123def  →  "Phone Type (Custom)"
custom.xyz789abc  →  "Lead Source (Custom)"
```

### 3. API Endpoint
**Endpoint:** `wp-admin/admin-ajax.php`
**Action:** `ghl_crm_get_custom_fields`
**Method:** POST
**Nonce:** `ghl_crm_field_mapping_nonce`

**Response:**
```json
{
  "success": true,
  "data": {
    "fields": {
      "": "— Do Not Sync —",
      "firstName": "First Name",
      "lastName": "Last Name",
      "custom.abc123": "Phone Type (Custom)",
      "custom.xyz789": "Lead Source (Custom)"
    },
    "count": 5
  }
}
```

## How It Works

### Step 1: Admin Loads Fields
1. Admin goes to **Field Mapping** page
2. Clicks **"Load Custom Fields from GoHighLevel"**
3. AJAX call to `ghl_crm_get_custom_fields`
4. Fetches from GHL API: `GET /locations/{locationId}/customFields`

### Step 2: Fields Are Displayed
- Dropdowns updated with all available fields
- Custom fields marked with "(Custom)" suffix
- Previous selections preserved

### Step 3: Admin Maps Fields
```
WordPress Field         → GoHighLevel Field
---------------------------------------------------
user_email             → email
first_name             → firstName
billing_phone          → phone
custom_lead_source     → custom.abc123def (Lead Source)
```

### Step 4: Data Is Synced
When user registers/updates:
```php
$contact_data = [
    'email' => 'user@example.com',
    'firstName' => 'John',
    'phone' => '+1234567890',
    'customField' => [
        [
            'id' => 'abc123def',
            'value' => 'Facebook Ad'
        ]
    ]
];
```

## Code Implementation

### SettingsManager.php
```php
// New AJAX handler
public function get_custom_fields(): void {
    $location_id = $settings['location_id'] ?? '';
    $response = $client->get("locations/{$location_id}/customFields");
    
    // Combine standard + custom fields
    $all_fields = $this->get_standard_ghl_fields();
    foreach ($response['customFields'] as $field) {
        $all_fields['custom.' . $field['id']] = $field['name'] . ' (Custom)';
    }
    
    wp_send_json_success(['fields' => $all_fields]);
}
```

### UserHooks.php
```php
private function prepare_contact_data(\WP_User $user): array {
    $ghl_custom_fields = [];
    
    foreach ($field_map as $wp_field => $mapping) {
        $ghl_field = $mapping['ghl_field'];
        
        // Check if custom field
        if (strpos($ghl_field, 'custom.') === 0) {
            $custom_field_id = str_replace('custom.', '', $ghl_field);
            $ghl_custom_fields[] = [
                'id' => $custom_field_id,
                'value' => $value
            ];
        } else {
            $contact_data[$ghl_field] = $value;
        }
    }
    
    // Add custom fields to payload
    if (!empty($ghl_custom_fields)) {
        $contact_data['customField'] = $ghl_custom_fields;
    }
}
```

## GHL API Format

### Standard Fields
Sent at root level:
```json
{
  "email": "user@example.com",
  "firstName": "John",
  "phone": "+1234567890"
}
```

### Custom Fields
Sent in `customField` array:
```json
{
  "email": "user@example.com",
  "customField": [
    {
      "id": "abc123def",
      "value": "Facebook Ad"
    },
    {
      "id": "xyz789abc",
      "value": "Premium"
    }
  ]
}
```

## Error Handling

### No Location ID
```
Error: Location ID not configured. Please save your settings first.
```
**Solution:** Configure OAuth and save settings first

### API Connection Failed
Falls back to standard fields only:
```
Could not fetch custom fields. Showing standard fields only.
```

### No Custom Fields Found
```
No custom fields found. Showing standard fields only.
```
**Normal:** Location has no custom fields yet

## Benefits

✅ **Dynamic**: Always shows latest custom fields from GHL  
✅ **Accurate**: No outdated hardcoded lists  
✅ **Flexible**: Supports any custom field you create in GHL  
✅ **Easy**: One-click to load fields  
✅ **Visual**: Custom fields clearly marked with "(Custom)"

## User Workflow

1. **Setup OAuth** → Configure location in Settings
2. **Load Fields** → Click button in Field Mapping page
3. **Map Fields** → Select WP field → GHL field (including custom)
4. **Save Mapping** → Click "Save Field Mapping"
5. **Test Sync** → Create/update user, check GHL contact

## Troubleshooting

### Button Does Nothing
- Check browser console for errors
- Verify location ID is saved in settings
- Check GHL OAuth token is valid

### Custom Fields Not Showing
- Create custom fields in GHL first
- Refresh by clicking load button again
- Check GHL API permissions

### Custom Fields Not Syncing
- Verify field mapping is saved
- Check field direction is "Both" or "To GHL"
- Review error logs for API errors

## Future Enhancements

- Auto-load fields on page load
- Cache custom fields (15 min transient)
- Show field types (text, date, select, etc.)
- Validate field types match (phone → phone)
- Bulk mapping suggestions
