# Elementor Integration

## Overview
Complete Elementor page builder integration providing a drag-and-drop widget for embedding GoHighLevel forms.

## Features

### Form Widget
- **Widget Name:** GoHighLevel Form
- **Category:** GoHighLevel CRM
- **Icon:** Form Horizontal icon

### Widget Controls

#### Content Tab
1. **Settings Notice**
   - Informative banner explaining backend configuration
   - Direct link to Forms Settings page
   - Blue info-style alert

2. **Form Selection**
   - Dropdown populated with all available GHL forms
   - Real-time sync from GoHighLevel account
   - Connection status detection
   - Empty state handling

3. **Refresh Notice**
   - Helper text for missing forms
   - Quick link to refresh forms list
   - Styled button with refresh icon

#### Style Tab
1. **Width Control**
   - Responsive slider
   - Units: px, %
   - Default: 100%
   - Range: 10-100% or 200-1200px

2. **Minimum Height**
   - Pixel-based slider
   - Default: 500px
   - Range: 300-1200px
   - Auto-expanding behavior

3. **Margin Control**
   - Responsive dimensions
   - Units: px, %, em
   - All sides configurable

4. **Padding Control**
   - Responsive dimensions
   - Units: px, %, em
   - All sides configurable

## Technical Implementation

### Files Created

1. **src/Integrations/Elementor/ElementorIntegration.php**
   - Main integration class
   - Widget registration
   - Custom category creation
   - Editor styles

2. **src/Integrations/Elementor/FormWidget.php**
   - Widget implementation
   - Extends `\Elementor\Widget_Base`
   - Form selection logic
   - Rendering methods

### Integration Points

#### Loader Registration
```php
'integrations.elementor' => \GHL_CRM\Integrations\Elementor\ElementorIntegration::class,
```

#### Widget Registration Hook
```php
add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
```

#### Category Registration Hook
```php
add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
```

## Usage

### For Users

1. **Install Elementor**
   - Ensure Elementor plugin is active
   - Works with both free and Pro versions

2. **Connect to GoHighLevel**
   - Complete OAuth connection in plugin settings
   - Forms will auto-sync

3. **Add Widget to Page**
   - Edit page with Elementor
   - Find "GoHighLevel Form" widget in panel
   - Drag onto canvas
   - Select form from dropdown

4. **Configure Settings**
   - Adjust width and height
   - Set margins and padding
   - Advanced settings managed from backend

### Editor Experience

**Empty State:**
- Placeholder message when no form selected
- Shows only in editor mode
- Clean dashed border styling

**Live Preview:**
- Form renders immediately after selection
- Full iframe embed display
- Responsive preview

## Backend Integration

### Form Settings Link
Widget includes direct link to:
```
admin.php?page=ghl-crm-admin#forms
```

All advanced settings managed there:
- Submission limits (once per user, unlimited)
- Logged-in only restrictions
- Custom submission messages
- Form visibility rules

## Reusability

### Leverages Existing Code
Widget calls existing `ShortcodeManager::render_form_shortcode()`:
```php
$output = $shortcode_manager->render_form_shortcode([
    'id'     => $form_id,
    'width'  => '100%',
    'height' => 'auto',
]);
```

No duplicate rendering logic needed.

### Forms Loading
Uses `FormsResource` API client:
```php
$forms_resource = new FormsResource( $client );
$forms = $forms_resource->get_forms();
```

Same data source as admin panel.

## Error Handling

### Connection States
- **Not Connected:** Shows "Not Connected to GoHighLevel"
- **No Forms:** Shows "No Forms Found"
- **Error Loading:** Shows "Error Loading Forms"
- **Empty Selection:** Placeholder in editor

### Form Display
- Uses existing shortcode error handling
- Logged-in restrictions honored
- Submission limits enforced
- Custom messages displayed

## Styling

### Default CSS
Inherits from:
- `assets/public/css/forms.css`
- `assets/frontend/css/forms.css`

### Elementor Controls
All standard Elementor CSS controls work:
- Typography (if needed)
- Border controls
- Box shadow
- Background
- Custom CSS

### Responsive Design
- Mobile breakpoints supported
- Elementor's responsive controls
- Form auto-adjusts height

## Compatibility

### Requirements
- WordPress 5.0+
- Elementor (any version)
- GoHighLevel CRM Integration plugin
- Active GHL connection

### Tested With
- Elementor Free
- Elementor Pro
- WordPress 6.0+
- PHP 7.4+

## Future Enhancements

### Possible Additions
1. **Multiple Form Layouts**
   - Inline forms
   - Popup/modal forms
   - Slide-in forms

2. **Advanced Styling**
   - Custom form colors
   - Button styling override
   - Field spacing controls

3. **Conditional Display**
   - Show/hide based on user role
   - Device-specific visibility
   - Time-based display

4. **Analytics Integration**
   - Form view tracking
   - Conversion tracking
   - A/B testing support

## Security

### Nonce Verification
Not needed - widget reads data only.

### Permission Checks
- `is_connection_verified()` check
- API credentials validated
- Admin-only settings access

### Data Sanitization
- Form IDs sanitized
- Shortcode attributes escaped
- HTML output properly escaped

## Performance

### Caching
- Forms list cached (30 min default)
- Uses existing `FormsResource` cache
- No additional API calls per widget

### Loading
- Lazy-loaded in editor
- No frontend performance impact
- Async iframe loading

## Support

### Documentation
Users referred to:
- Backend Forms Settings page
- Plugin documentation
- GoHighLevel support

### Troubleshooting
Common issues handled:
- Elementor not active → Integration silent
- Not connected → Clear message
- No forms → Helpful prompt
- Missing form ID → Placeholder

## Code Quality

- ✅ PSR-4 autoloading
- ✅ WordPress coding standards
- ✅ PHPDoc blocks
- ✅ Type declarations
- ✅ Singleton pattern
- ✅ Dependency injection
- ✅ ABSPATH security check
- ✅ Clean separation of concerns
