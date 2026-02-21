# Gutenberg Blocks Integration

## Overview

The GoHighLevel CRM Integration plugin includes two powerful Gutenberg blocks for seamless content management and form embedding.

## Available Blocks

### 1. GoHighLevel Form Block

Embed GoHighLevel forms directly into your posts and pages using the Gutenberg editor.

**Features:**
- Visual form selector in block settings
- Customizable width and height
- Preview placeholder in editor
- Inherits all form settings (logged-in restrictions, submission limits)
- Responsive design

**How to Use:**
1. Add a new block and search for "GoHighLevel Form"
2. Select a form from the dropdown in the sidebar
3. Adjust width and height settings (optional)
4. Publish/update your page

**Settings:**
- **Select Form**: Choose from synced GHL forms
- **Width**: Set form width (e.g., 100%, 600px)
- **Height**: Set form height (e.g., auto, 800px)

### 2. Restricted Content Block

Control content visibility based on user's GoHighLevel tags. This is a **container block** that wraps other blocks.

**Features:**
- Tag-based access control
- Three rule types: ANY, ALL, or NONE
- Custom fallback content for denied users
- Visual indicators in editor
- Server-side rendering for security
- Admin always has access

**How to Use:**
1. Add a new block and search for "Restricted Content"
2. Add any content blocks INSIDE the restricted content block
3. Configure access rules in the sidebar:
   - Choose rule type (ANY/ALL/NONE)
   - Select required tags
   - Set fallback message (optional)
4. Publish/update your page

**Access Rules:**
- **User has ANY of these tags**: User needs at least one tag to access
- **User has ALL of these tags**: User must have all selected tags
- **User does NOT have these tags**: User must not have any of these tags

**Example Use Cases:**
```
// Show premium content only to VIP members
[Restricted Content - Rule: ANY tag "premium, vip"]
  - Pricing Table
  - Download Button
  - Exclusive Video

// Hide upgrade CTA from existing customers
[Restricted Content - Rule: NONE tag "customer"]
  - Upgrade Button
  - Sales Message

// Show advanced content to qualified users
[Restricted Content - Rule: ALL tags "active, advanced, verified"]
  - Advanced Course Content
  - Premium Resources
```

## Technical Implementation

### Block Registration

Blocks are registered in `src/Integrations/Gutenberg/BlocksManager.php`:

```php
add_action('init', [$this, 'register_blocks']);
```

### Block Files Structure

```
assets/blocks/
├── ghl-form/
│   ├── block.json         # Block metadata
│   ├── index.js           # React component
│   └── editor.css         # Editor styles
├── restricted-content/
│   ├── block.json         # Block metadata
│   ├── index.js           # React component (with InnerBlocks)
│   └── editor.css         # Editor styles
└── blocks-frontend.css    # Frontend styles
```

### REST API Endpoints

The blocks use internal REST API endpoints (requires `edit_posts` capability):

- **GET** `/wp-json/ghl-crm/v1/connection/status` - Check GHL connection
- **GET** `/wp-json/ghl-crm/v1/forms` - Get available forms
- **GET** `/wp-json/ghl-crm/v1/tags` - Get available tags

### Server-Side Rendering

Both blocks use server-side rendering for:
- **Security**: Access checks happen server-side
- **Performance**: No heavy JavaScript on frontend
- **SEO**: Content is rendered in HTML

The render callbacks are in `BlocksManager.php`:
- `render_form_block()` - Renders GHL forms (reuses ShortcodeManager)
- `render_restricted_content_block()` - Checks access and renders content

### Access Control Integration

The Restricted Content Block uses existing `AccessControl` class:
```php
$access_control = \GHL_CRM\Membership\AccessControl::get_instance();
$user_tags = $access_control->get_user_tags($user_id);
```

No additional API calls - uses cached tag data for performance.

## Development

### Building Blocks

WordPress blocks require build tools. For development:

1. **Without Build Tools** (Current Setup):
   - Blocks use `wp.element`, `wp.blocks`, `wp.blockEditor` globals
   - No JSX compilation needed
   - Works immediately without build process

2. **With Build Tools** (Optional for Advanced Features):
   ```bash
   npm install @wordpress/scripts --save-dev
   npm run build
   ```

### Adding New Blocks

1. Create block folder: `assets/blocks/your-block/`
2. Add `block.json` with metadata
3. Create `index.js` with block registration
4. Add render callback in `BlocksManager.php`
5. Add frontend styles if needed

## Block Category

All blocks appear under **"GoHighLevel CRM"** category in the block inserter.

## Requirements

- WordPress 5.8+ (Block API v2)
- GoHighLevel connection active
- Gutenberg editor enabled

## Comparison with Other Methods

| Feature | Shortcode | Elementor Widget | Gutenberg Block |
|---------|-----------|------------------|-----------------|
| Visual editor | ❌ | ✅ | ✅ |
| Live preview | ❌ | ✅ | ⚠️ Placeholder |
| Container blocks | ❌ | ✅ | ✅ |
| Native WordPress | ✅ | ❌ | ✅ |
| Page builders | ✅ | Elementor only | All |

## Troubleshooting

### Blocks not appearing in editor

1. Clear browser cache
2. Check if Gutenberg is enabled
3. Verify GoHighLevel connection is active
4. Check browser console for JavaScript errors

### Forms not loading in block

1. Go to GHL CRM Settings → Forms
2. Click "Refresh Forms List"
3. Verify forms appear in list
4. Try selecting the form again in block settings

### Restricted content not working

1. Check if tags are properly synced
2. Verify user has GHL contact ID in WordPress
3. Test with admin user (admins always have access)
4. Check tag IDs match in block settings

## Future Enhancements

Potential additions:
- Contact Info Display Block
- Tag-Based CTA Block
- LearnDash Progress Block
- Opportunity Display Block
- BuddyBoss Profile Integration Block

## Support

For issues or feature requests, see plugin documentation or contact support.
