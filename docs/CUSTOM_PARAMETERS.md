# Custom URL Parameters Feature

## Overview
The Custom URL Parameters feature allows administrators to dynamically add custom URL parameters to GoHighLevel form iframes using variable placeholders. This enables passing contextual data from WordPress to GHL forms automatically.

## Feature Components

### Backend (PHP)
**File**: `src/Core/FormSettings.php`

#### Data Structure
Custom parameters are stored as an array in form settings:
```php
[
    'custom_params' => [
        ['key' => 'source', 'value' => '{user_email}'],
        ['key' => 'campaign', 'value' => '{current_url}'],
        ['key' => 'user_role', 'value' => '{user_role}']
    ]
]
```

#### Available Variables
- **User Variables**:
  - `{user_email}` - User's email address
  - `{user_first_name}` - User's first name
  - `{user_last_name}` - User's last name
  - `{user_display_name}` - User's display name
  - `{user_login}` - User's login username
  - `{user_id}` - User's ID
  - `{user_role}` - User's role

- **Site Variables**:
  - `{site_url}` - Site URL
  - `{site_name}` - Site name

- **Client-Side Variables** (replaced by JavaScript):
  - `{current_url}` - Current page URL
  - `{current_title}` - Current page title

- **Custom Meta Fields**:
  - `{meta:field_name}` - User meta field value (e.g., `{meta:phone_number}`)

#### Key Methods

**`sanitize_custom_params(array $params): array`**
- Validates and sanitizes custom parameter key/value pairs
- Ensures both key and value are non-empty strings
- Returns sanitized array

**`get_available_variables(): array`**
- Returns list of available variable placeholders with descriptions
- Used by admin UI to display available variables

**`resolve_custom_params(int $form_id, int $user_id): array`**
- Processes custom params for a specific form and user
- Replaces server-side variables with actual values
- Returns associative array of resolved parameters

**`replace_variables(string $value, int $user_id): string`**
- Replaces variable placeholders in a value string
- Handles {user_*}, {site_*}, and {meta:*} variables
- Returns string with variables replaced

### Data Flow (AssetsManager)
**File**: `src/Core/AssetsManager.php`

In `define_public_assets()`:
```php
foreach ($all_form_settings as $form_id => $settings) {
    // Resolve custom params with variables replaced
    $resolved_params = $form_settings->resolve_custom_params($form_id, $user_id);
    $all_form_settings[$form_id]['resolved_params'] = $resolved_params;
}
```

Data is passed to frontend via `wp_localize_script`:
```javascript
ghl_form_autofill_data = {
    userData: {...},
    formSettings: {
        'form-123': {
            autofill_enabled: true,
            logged_only: false,
            custom_params: [...],
            resolved_params: {
                'source': 'john@example.com',
                'user_role': 'subscriber'
            }
        }
    },
    whiteLabelDomain: '...'
}
```

### Frontend (JavaScript)
**File**: `assets/public/js/form-autofill.js`

**`getCustomParams(formId): object`**
- Retrieves resolved parameters for a form
- Replaces client-side variables (`{current_url}`, `{current_title}`)
- Returns object with final parameter values

**`modifyIframeSrc(iframe)`**
- Extracts form ID from iframe URL
- Checks if autofill is enabled
- Builds pre-fill data from user data
- Gets custom parameters via `getCustomParams()`
- **Merges both**: Auto-fill data first, then custom parameters (custom params override auto-fill)
- Appends all parameters to iframe URL

Example flow:
```javascript
// Auto-fill data (from user):
preFillData = {
    'email': 'john@example.com',
    'first_name': 'John',
    'display_name': 'John Doe'
}

// User configures in admin:
custom_params: [
    {key: 'source', value: '{user_email}'},
    {key: 'display_name', value: 'CustomName'}  // Overrides auto-fill
]

// Backend resolves:
resolved_params: {
    'source': 'john@example.com',
    'display_name': 'CustomName'
}

// Frontend merges (custom params win conflicts):
finalParams = {
    'email': 'john@example.com',      // from auto-fill
    'first_name': 'John',             // from auto-fill
    'display_name': 'CustomName',     // OVERRIDDEN by custom param
    'source': 'john@example.com'      // from custom param
}

// Final iframe URL:
https://ghl.domain/form?email=john@example.com&first_name=John&display_name=CustomName&source=john@example.com
```

### Admin UI
**File**: `assets/admin/js/forms.js`

#### UI Components

**Custom Parameters Section**
```html
<div class="ghl-settings-section ghl-settings-card">
    <div class="ghl-settings-header">
        <h3>Custom URL Parameters</h3>
        <p>Add custom parameters to the form URL using dynamic variables</p>
    </div>
    
    <!-- Parameter rows -->
    <div class="ghl-custom-params-container">
        <!-- Each row has: -->
        <input type="text" class="ghl-param-key" placeholder="Parameter name">
        <input type="text" class="ghl-param-value" placeholder="Value">
        <button class="ghl-remove-custom-param">Remove</button>
    </div>
    
    <!-- Add button -->
    <button class="ghl-add-custom-param">Add Parameter</button>
    
    <!-- Available variables list -->
    <div class="ghl-available-variables">
        <h4>Available Variables:</h4>
        <ul>
            <li><code>{user_email}</code> - User's email address</li>
            <!-- ... -->
        </ul>
    </div>
</div>
```

#### JavaScript Functions

**`buildCustomParamRow(index, key, value): string`**
- Generates HTML for a parameter row
- Escapes key/value for security
- Returns HTML string

**`bindCustomParamEvents()`**
- Binds click handlers to remove buttons
- Removes parameter row on click

**Add Parameter Handler**
```javascript
$('.ghl-add-custom-param').on('click', function() {
    const $container = $('#ghl-custom-params-{formId}');
    const index = $container.find('.ghl-custom-param-row').length;
    const rowHtml = FormsManager.buildCustomParamRow(index, '', '');
    $container.append(rowHtml);
    FormsManager.bindCustomParamEvents();
});
```

**Save Settings Handler**
```javascript
$('.ghl-save-form-settings').on('click', function() {
    // Collect custom parameters
    const custom_params = [];
    $('.ghl-custom-param-row').each(function() {
        const key = $(this).find('.ghl-param-key').val().trim();
        const value = $(this).find('.ghl-param-value').val().trim();
        if (key && value) {
            custom_params.push({ key, value });
        }
    });
    
    // Save via AJAX
    $.ajax({
        url: ajaxUrl,
        method: 'POST',
        data: {
            action: 'ghl_crm_save_form_settings',
            nonce: nonce,
            form_id: formId,
            settings: {
                autofill_enabled: ...,
                logged_only: ...,
                custom_params: custom_params
            }
        }
    });
});
```

### Styling
**File**: `assets/admin/css/forms.css`

Key CSS classes:
- `.ghl-custom-params-container` - Container for parameter rows
- `.ghl-custom-param-row` - Individual parameter row
- `.ghl-param-inputs` - Flexbox layout for inputs and button
- `.ghl-param-key` / `.ghl-param-value` - Input fields
- `.ghl-remove-custom-param` - Remove button
- `.ghl-available-variables` - Variables reference box

## Usage Example

### Admin Configuration
1. Go to **GHL Forms** page
2. Click **Settings** (gear icon) on any form
3. Scroll to **Custom URL Parameters** section
4. Click **Add Parameter**
5. Enter parameter name (e.g., `source`)
6. Enter value with variables (e.g., `{user_email}`)
7. Click **Save Settings**

### Result
When a logged-in user views the form:
- Backend replaces `{user_email}` with actual email (e.g., `john@example.com`)
- Frontend receives `resolved_params: { source: 'john@example.com' }`
- Iframe URL becomes: `https://ghl.domain/form?source=john@example.com`

## Security

### Sanitization
- All parameter keys and values are sanitized in `sanitize_custom_params()`
- Empty keys or values are filtered out
- HTML special characters are escaped in admin UI

### Variable Replacement
- User data is properly escaped before replacement
- Invalid meta field names return empty string
- Client-side variables use `window.location.href` and `document.title` (safe)

### AJAX Security
- Nonce verification: `ghl_crm_forms_nonce`
- Capability check: `manage_options`
- Data validation before saving

## WordPress Multisite Support
- Form settings are stored per-site using `get_option()`
- Custom parameters respect current site context
- No cross-site data leakage

## Performance Considerations
- Variable replacement happens once during data localization
- Client-side replacement only for `{current_url}` and `{current_title}`
- No additional AJAX calls for parameter resolution
- Parameters are cached with form settings

## Extensibility
To add new variables:

1. Add to `get_available_variables()` in FormSettings.php
2. Add replacement logic in `replace_variables()` method
3. Update admin UI variables list in forms.js

Example:
```php
// In get_available_variables()
'{custom_var}' => __('Description of custom variable', 'syncly'),

// In replace_variables()
if (strpos($value, '{custom_var}') !== false) {
    $custom_value = get_custom_value($user_id);
    $value = str_replace('{custom_var}', $custom_value, $value);
}
```

## Troubleshooting

### Parameters not appearing in URL
- Check if custom_params are saved (view form settings)
- Verify variables are spelled correctly
- Check browser console for JavaScript errors
- Ensure autofill is enabled for the form

### Variables not replaced
- Server-side vars: Check if user is logged in
- Client-side vars: Check if JavaScript is loaded
- Meta fields: Verify field name exists in user meta

### Settings not saving
- Check AJAX nonce is correct
- Verify user has `manage_options` capability
- Check browser console for errors
- Review PHP error logs for backend issues
