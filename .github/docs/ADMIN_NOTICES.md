# Admin Notices System

The plugin includes a centralized admin notices system that allows you to display messages to users from anywhere in the codebase.

## Features

- **Action Hook Integration**: Uses `do_action('ghl_crm_settings_notices')` on the settings page
- **Transient Storage**: Notices persist across redirects using WordPress transients
- **Multiple Types**: Support for success, error, warning, and info notices
- **Global or Local**: Display notices on all admin pages or just the settings page
- **Auto-dismiss**: All notices are dismissible by default

## Basic Usage

### Get the AdminNotices instance

```php
$notices = \GHL_CRM\Core\AdminNotices::get_instance();
```

### Display success notices

```php
// Show on settings page only
$notices->success( 'Settings saved successfully!' );

// Show on all admin pages
$notices->success( 'OAuth connection established!', true );
```

### Display error notices

```php
// Show on settings page only
$notices->error( 'Failed to save settings.' );

// Show on all admin pages (useful for critical errors)
$notices->error( 'GoHighLevel API connection failed!', true );
```

### Display warning notices

```php
$notices->warning( 'Your API token will expire soon.' );
```

### Display info notices

```php
$notices->info( 'Syncing contacts in the background.' );
```

### Display exception messages

```php
try {
    // Some operation that might fail
    $client->sync_contacts();
} catch ( \Exception $e ) {
    $notices->from_exception( $e );
}
```

## Advanced Usage

### Custom notice with options

```php
$notices->add_notice(
    'Custom message here',
    'warning',      // Type: success, error, warning, info
    true,           // Dismissible: true/false
    false           // Global: show on all admin pages (true) or just settings (false)
);
```

### Using in action hooks

```php
// Add your own custom notice via the action hook
add_action( 'ghl_crm_settings_notices', function() {
    if ( some_condition() ) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>Custom warning message</p>';
        echo '</div>';
    }
});
```

## Examples

### Example 1: Display notice after OAuth connection

```php
// In OAuthHandler.php
public function connect() {
    try {
        // OAuth connection logic...
        
        $notices = \GHL_CRM\Core\AdminNotices::get_instance();
        $notices->success( 
            __( 'Successfully connected to GoHighLevel!', 'ghl-crm-integration' ),
            true // Show on all admin pages
        );
        
    } catch ( \Exception $e ) {
        $notices = \GHL_CRM\Core\AdminNotices::get_instance();
        $notices->error( 
            __( 'Failed to connect: ', 'ghl-crm-integration' ) . $e->getMessage(),
            true
        );
    }
}
```

### Example 2: Display notice after sync operation

```php
// In sync handler
public function sync_users() {
    $synced = 0;
    $errors = 0;
    
    // Sync logic...
    
    $notices = \GHL_CRM\Core\AdminNotices::get_instance();
    
    if ( $errors > 0 ) {
        $notices->warning(
            sprintf(
                __( 'Synced %d users, but %d errors occurred.', 'ghl-crm-integration' ),
                $synced,
                $errors
            )
        );
    } else {
        $notices->success(
            sprintf(
                __( 'Successfully synced %d users.', 'ghl-crm-integration' ),
                $synced
            )
        );
    }
}
```

### Example 3: Using the action hook directly

```php
// In your custom integration code
add_action( 'ghl_crm_settings_notices', function() {
    $pending_syncs = get_option( 'ghl_pending_syncs', 0 );
    
    if ( $pending_syncs > 100 ) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e( 'Warning:', 'ghl-crm-integration' ); ?></strong>
                <?php 
                printf(
                    esc_html__( 'You have %d contacts pending sync.', 'ghl-crm-integration' ),
                    $pending_syncs
                );
                ?>
            </p>
        </div>
        <?php
    }
});
```

## How It Works

1. **Storage**: Notices are stored in user-specific transients (valid for 1 hour)
2. **Display**: The `ghl_crm_settings_notices` action hook is called in the settings template
3. **Cleanup**: Notices are automatically deleted after being displayed
4. **Global Notices**: If `$global = true`, notices appear via `admin_notices` hook on all admin pages

## Notice Types

| Type | CSS Class | Description |
|------|-----------|-------------|
| `success` | `notice-success` | Green - for successful operations |
| `error` | `notice-error` | Red - for errors and failures |
| `warning` | `notice-warning` | Yellow/Orange - for warnings |
| `info` | `notice-info` | Blue - for informational messages |

## Best Practices

1. **Use appropriate types**: Success for completions, errors for failures, warnings for potential issues
2. **Keep messages short**: Users should understand the issue at a glance
3. **Be specific**: Include relevant details (what failed, what succeeded)
4. **Use global notices sparingly**: Reserve for critical issues that need immediate attention
5. **Translate messages**: Always use `__()` for internationalization
6. **Handle exceptions**: Use `from_exception()` for consistent error display
