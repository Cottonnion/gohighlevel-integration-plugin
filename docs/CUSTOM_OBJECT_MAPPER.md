# Custom Object Mapper - Implementation Summary

## Overview
Phase 1 CPT Mapper for syncing any WordPress Custom Post Type to GHL Custom Objects (User-Defined only, System objects excluded).

## Features Implemented

### 1. **UI Components**
- **View Mappings Button**: Toggle between schemas view and mappings view
- **Mappings List**: Display all configured mappings with status indicators
- **Create Mapping Modal**: Full configuration form with:
  - Basic settings (name, CPT, GHL object, active status)
  - Sync triggers (publish, update, delete)
  - Contact association options
  - Field mapping table (dynamic rows)
  - Advanced options (batch sync, logging)

### 2. **Field Mapping Interface**
- Dynamic table with Select2 dropdowns
- WordPress field sources:
  - Post fields (title, content, excerpt, dates)
  - Post meta (custom fields)
  - ACF fields
  - Taxonomy terms
  - Static values
- GHL fields populated from selected custom object schema
- Transform options:
  - None
  - Sanitize HTML
  - Convert to Number
  - Format Date (ISO)
  - Strip HTML
  - JSON Encode

### 3. **Backend AJAX Handlers**
Location: `src/Core/SettingsManager.php`

#### `get_post_types()`
- Returns all public WordPress post types
- Excludes attachments and nav menu items
- **Action**: `wp_ajax_ghl_crm_get_post_types`

#### `save_mapping()`
- Saves/updates mapping configuration
- Stores in `ghl_crm_custom_object_mappings` option
- Validates and sanitizes all inputs
- **Action**: `wp_ajax_ghl_crm_save_mapping`

#### `get_mappings()`
- Retrieves all saved mappings
- **Action**: `wp_ajax_ghl_crm_get_mappings`

#### `delete_mapping()`
- Deletes mapping by ID
- **Action**: `wp_ajax_ghl_crm_delete_mapping`

### 4. **Data Structure**

```php
$mapping = [
    'id' => 'mapping_1234567890',
    'name' => 'Product Inventory Sync',
    'wp_post_type' => 'product',
    'wp_post_type_label' => 'Products',
    'ghl_object' => '6906127a53f12b340975e826',
    'ghl_object_key' => 'custom_objects.my_custom_objects',
    'active' => true,
    'triggers' => ['publish', 'update'],
    'contact_source' => 'post_author',
    'contact_field' => '',
    'contact_not_found' => 'skip',
    'field_mappings' => [
        [
            'wp_field' => 'post_title',
            'wp_field_name' => '',
            'ghl_field' => 'custom_objects.my_custom_objects.my_primary_field',
            'transform' => 'sanitize'
        ]
    ],
    'enable_batch_sync' => false,
    'log_sync_operations' => true,
    'created_at' => '2025-11-01 14:30:00',
    'updated_at' => '2025-11-01 14:30:00'
];
```

### 5. **Frontend JavaScript**
Location: `templates/admin/custom-objects.php`

**Key Functions**:
- `openMappingModal()` - Opens/resets modal
- `loadPostTypes()` - Fetches WP post types
- `loadGHLObjects()` - Filters user-defined custom objects only
- `loadGHLObjectFields()` - Populates GHL field options from schema
- `addFieldMappingRow()` - Adds dynamic mapping row
- `saveMapp

ing()` - Submits form data
- `renderMappings()` - Displays mappings list

**Select2 Integration**:
- All dropdowns use Select2 for better UX
- Width set to 100%
- Searchable options

### 6. **CSS Styling**
- WP admin-compatible design
- Responsive breakpoints at 782px
- Color-coded status indicators:
  - Active: Green (#00a32a)
  - Inactive: Gray (#999)
  - System objects: Blue border (#2271b1)
- Toggle switches for boolean options
- Clean table layout for field mappings

## User Workflow

1. **Navigate to Custom Objects page**
2. **Click "View Mappings"** button
3. **Click "Create Mapping"** button
4. **Configure mapping**:
   - Enter name
   - Select WP Post Type
   - Select GHL Custom Object (only custom objects shown)
   - Choose sync triggers
   - Configure contact association
   - Add field mappings (WordPress → GHL)
   - Set transforms if needed
5. **Save mapping**
6. **Edit/Delete** from mappings list

## System Requirements

- WordPress 5.0+
- Select2 already installed (confirmed by user)
- PHP 7.4+
- Active GHL connection

## Next Steps (Phase 2 - To Be Implemented)

### A. Sync Engine
Create `src/Sync/CustomObjectMapper.php`:
```php
class CustomObjectMapper {
    public function register_hooks() {
        // Dynamically register hooks based on saved mappings
    }
    
    public function sync_post_to_ghl($post_id, $post) {
        // Execute sync based on mapping configuration
    }
    
    private function resolve_contact($post, $mapping) {
        // Find/create GHL contact
    }
    
    private function get_wp_field_value($post, $field_config) {
        // Extract WP field value
    }
    
    private function transform_value($value, $transform) {
        // Apply transformation
    }
}
```

### B. Background Processing
- Queue sync operations
- Retry failed syncs
- Batch processing for existing posts

### C. Logging & Debugging
- Sync operation logs
- Error tracking
- Admin UI for viewing sync history

### D. Two-Way Sync (Optional)
- Webhook handler for GHL → WP updates
- Conflict resolution

## File Locations

```
templates/admin/custom-objects.php    → UI (modal, form, JavaScript)
src/Core/SettingsManager.php         → AJAX handlers
wp_options:                           → Data storage
  └─ ghl_crm_custom_object_mappings
```

## Testing Checklist

- [ ] View mappings toggle works
- [ ] Create mapping modal opens
- [ ] Post types load correctly
- [ ] GHL custom objects load (excluding system objects)
- [ ] Field mapping rows add/remove
- [ ] Select2 initializes properly
- [ ] Form validation works
- [ ] Mapping saves successfully
- [ ] Mappings list renders
- [ ] Edit mapping loads existing data
- [ ] Delete mapping works
- [ ] Responsive design on mobile

## Notes

- System objects (Contact, Company, Opportunity) are **excluded** from mapping selection
- Only `USER_DEFINED` custom objects are available
- Uses existing Select2 installation
- All AJAX uses WordPress nonces for security
- Data stored in WordPress options table
- No database migrations needed

## Browser Extension Compatibility

The mapper includes protection against interference from password managers and browser extensions (LastPass, 1Password, Bitwarden, Dashlane, iCloud Passwords, etc.) that try to inject into Select2 dropdowns.

**Implemented protections**:
- Data attributes to mark fields as ignored by extensions (`data-lpignore`, `data-1p-ignore`, `data-bwignore`, etc.)
- Console error suppression for extension `content_script.js` errors
- Global error handler to prevent extension errors from bubbling up
- CSS to hide Select2 fields from extension detection
- Removed autocomplete and form detection attributes

If you still see console errors from `content_script.js`, they are cosmetic and won't affect functionality.
