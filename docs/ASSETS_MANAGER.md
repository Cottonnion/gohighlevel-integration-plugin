# Assets Directory

This directory contains all CSS and JavaScript files for the GoHighLevel CRM Integration plugin.

## Structure

```
assets/
├── admin/           # Admin area assets
│   ├── css/        # Admin stylesheets
│   │   ├── settings.css
│   │   ├── sync-logs.css
│   │   └── field-mapping.css
│   └── js/         # Admin scripts
│       └── settings.js
└── public/          # Frontend assets
    ├── css/        # Frontend stylesheets
    └── js/         # Frontend scripts
```

## Asset Management

Assets are managed by the `AssetsManager` class (`src/Core/AssetsManager.php`).

### How It Works

1. **Page-Specific Loading**: Assets are only loaded on the pages where they're needed
2. **Automatic Detection**: The system detects whether a file is CSS or JS by extension
3. **Version Control**: All assets use plugin version for cache busting
4. **Localization Support**: Scripts can have data localized for AJAX calls

### Adding New Assets

**Admin Assets:**
```php
$this->add_admin_asset(
    'handle-name',              // Unique handle
    [ 'page-screen-id' ],       // Array of admin page screen IDs
    'filename.css',             // File name (css or js)
    [ 'dependencies' ],         // Dependencies (e.g., ['jquery'])
    [ 'localized_data' => [] ], // Data to pass to script
    GHL_CRM_VERSION,            // Version
    true                        // Enqueue in footer (JS only)
);
```

**Public Assets:**
```php
$this->add_public_asset(
    'handle-name',              // Unique handle
    'filename.css',             // File name
    [ 'dependencies' ],         // Dependencies
    [ 'localized_data' => [] ], // Data to pass to script
    GHL_CRM_VERSION,            // Version
    true                        // Enqueue in footer (JS only)
);
```

## Screen IDs for Admin Pages

- Settings: `toplevel_page_syncly`
- Sync Logs: `ghl-crm_page_ghl-crm-sync-logs`
- Field Mapping: `ghl-crm_page_ghl-crm-field-mapping`

To find a screen ID, add this to your page:
```php
$screen = get_current_screen();
echo $screen->id;
```
