# Field Mapping Direction System

## Overview

The plugin respects field mapping directions when syncing data between WordPress and GoHighLevel. Only fields configured in the Field Mapping settings will be synced, and only in the specified direction.

## Field Mapping Structure

Field mappings are stored in WordPress options as:

```php
'user_field_mapping' => [
    'wp_field_name' => [
        'ghl_field' => 'ghlFieldName',
        'direction' => 'both' | 'ghl_to_wp' | 'wp_to_ghl'
    ]
]
```

## Sync Directions

### 1. **Both Ways** (`both`)
- Data syncs bidirectionally
- Changes in WordPress → GoHighLevel
- Changes in GoHighLevel → WordPress
- Used for fields that should always stay in sync

### 2. **GHL → WordPress** (`ghl_to_wp`)
- Data only syncs FROM GoHighLevel TO WordPress
- WordPress is updated when GHL contact changes
- WordPress changes do NOT update GHL
- **This is the direction webhooks use**
- Ideal for fields managed in GHL that should reflect in WP

### 3. **WordPress → GHL** (`wp_to_ghl`)
- Data only syncs FROM WordPress TO GoHighLevel
- GHL contact is updated when WordPress user changes
- GHL changes do NOT update WordPress
- Ideal for fields managed in WP that should reflect in GHL

## How Webhook Sync Respects Directions

When a webhook is received from GoHighLevel:

1. **Payload Normalization**: Raw GHL data is normalized to internal format
2. **Field Mapping Lookup**: System checks which WP fields are mapped to GHL fields
3. **Direction Check**: For each mapped field, checks if direction is `ghl_to_wp` or `both`
4. **Selective Sync**: Only fields with appropriate direction are synced to WordPress

### Example Scenario

**Field Mapping Configuration:**
```
first_name → firstName (direction: both)
last_name → lastName (direction: both)
phone → phone (direction: ghl_to_wp)
billing_address → customField.address (direction: wp_to_ghl)
```

**When GHL webhook received with contact update:**
- ✅ `firstName` → syncs to WP `first_name` (direction: both)
- ✅ `lastName` → syncs to WP `last_name` (direction: both)
- ✅ `phone` → syncs to WP `phone` (direction: ghl_to_wp)
- ❌ `customField.address` → NOT synced (direction: wp_to_ghl only)

**When WordPress user updated:**
- ✅ `first_name` → syncs to GHL `firstName` (direction: both)
- ✅ `last_name` → syncs to GHL `lastName` (direction: both)
- ❌ `phone` → NOT synced (direction: ghl_to_wp only)
- ✅ `billing_address` → syncs to GHL `customField.address` (direction: wp_to_ghl)

## Code Implementation

### GHLToWordPressSync::should_sync_field()

```php
private function should_sync_field( string $field, string $direction ): bool {
    $settings        = $this->settings_manager->get_settings_array();
    $field_mappings  = $settings['user_field_mapping'] ?? [];
    
    // If field not mapped, don't sync
    if ( ! isset( $field_mappings[ $field ] ) ) {
        return false;
    }
    
    $field_direction = $field_mappings[ $field ]['direction'] ?? 'both';

    // 'both' means bidirectional sync is enabled
    if ( 'both' === $field_direction ) {
        return true;
    }

    return $field_direction === $direction;
}
```

### GHLToWordPressSync::get_reverse_field_mappings()

```php
private function get_reverse_field_mappings(): array {
    $settings = $this->settings_manager->get_settings_array();
    $mappings = $settings['user_field_mapping'] ?? [];

    // Only include fields that sync from GHL to WP (direction: 'ghl_to_wp' or 'both')
    $reversed = [];
    foreach ( $mappings as $wp_field => $mapping_data ) {
        $ghl_field = $mapping_data['ghl_field'] ?? '';
        $direction = $mapping_data['direction'] ?? 'both';
        
        // Only include if direction allows GHL to WP sync
        if ( ! empty( $ghl_field ) && ( 'ghl_to_wp' === $direction || 'both' === $direction ) ) {
            $reversed[ $ghl_field ] = $wp_field;
        }
    }

    return $reversed;
}
```

## Key Points

1. **Unmapped fields are never synced** - If a field isn't in the field mapping settings, it won't sync in any direction
2. **Direction is enforced at sync time** - Each sync operation checks field direction before updating
3. **Webhooks respect mappings** - Incoming GHL webhooks only update WP fields configured with `ghl_to_wp` or `both`
4. **Bidirectional by default** - If direction is not specified, it defaults to `both` for backward compatibility
5. **Per-field control** - Each field can have its own direction independently

## Benefits

- **Data integrity**: Prevents accidental overwrites of data managed in one system
- **Flexibility**: Different fields can sync in different directions
- **User control**: Admins configure exactly which data flows where
- **Webhook optimization**: Only relevant fields are updated from webhooks
- **Clear ownership**: Makes it explicit which system is the source of truth for each field

## Testing

To test field mapping directions:

1. **Setup**: Configure fields with different directions in Field Mapping
2. **Webhook Test**: Trigger GHL webhook, verify only `ghl_to_wp` and `both` fields update
3. **WP Update Test**: Update WP user, verify only `wp_to_ghl` and `both` fields sync to GHL
4. **Logs**: Check Sync Logs to see which fields were synced and which were skipped
