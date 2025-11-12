# Conditional Menu Items - GHL Tag Visibility

## Overview
Enterprise-grade WordPress navigation menu visibility control based on GoHighLevel contact tags. Menu items are filtered server-side before rendering, ensuring secure and performant tag-based access control.

## Features
- **Tag ID-Based Storage**: Stores GHL tag IDs (not names) to prevent duplicates
- **Multiple Rule Types**: ANY, ALL, or NOT tag matching
- **Login Status Rules**: Show to logged-in or logged-out users only
- **Select2 AJAX Integration**: Real-time tag search with local caching
- **PHP-Rendered UI**: Server-side HTML generation for security and performance
- **Zero Client-Side Filtering**: Menu items removed before rendering (no CSS hiding)

## How to Use

### 1. Navigate to Menu Editor
Go to **Appearance → Menus** in WordPress admin

### 2. Expand a Menu Item
Click the down arrow on any menu item to expand its settings

### 3. Configure Visibility
Find the **"GHL Tag Visibility"** section with these options:

#### Visibility Rules:
- **Show to Everyone (Default)** - No restrictions, visible to all
- **Show to Logged-In Users Only** - Hide from logged-out visitors
- **Show to Logged-Out Users Only** - Hide from logged-in users
- **Has ANY of these tags** - Show if user has at least one of the selected tags
- **Has ALL of these tags** - Show only if user has all selected tags
- **Does NOT have these tags** - Hide if user has any of the selected tags

#### Tag Selection:
When you select a tag-based rule, a tag picker appears:
- Search existing tags from GHL
- Type to create new tags
- Select multiple tags
- Tags are case-insensitive when matching

### 4. Save Menu
Click **Save Menu** to apply your visibility rules

## Use Cases

### Example 1: VIP Members Menu
**Goal**: Show "VIP Resources" menu item only to VIP members

**Setup**:
- Rule: "Has ANY of these tags"
- Tags: `VIP`, `Premium Member`

### Example 2: Free Trial Menu
**Goal**: Show "Upgrade" menu only to free trial users

**Setup**:
- Rule: "Has ALL of these tags"
- Tags: `Trial User`, `Active`

### Example 3: Hide from Cancelled
**Goal**: Hide "Members Area" from cancelled users

**Setup**:
- Rule: "Does NOT have these tags"
- Tags: `Cancelled`, `Suspended`

### Example 4: Login/Logout Toggle
**Goal**: Show "Login" to logged-out, "Dashboard" to logged-in

**Setup**:
- Login Item: "Show to Logged-Out Users Only"
- Dashboard Item: "Show to Logged-In Users Only"

## How It Works

### Architecture Overview
The system uses a **storage-display separation** pattern: stores GHL tag IDs for uniqueness, converts to names for display/comparison.

### Backend (PHP) - Server-Side Filtering

#### 1. Data Storage
Visibility rules stored in post meta using class constants:
- `_ghl_visibility_rule`: Rule type (has_any_tag, has_all_tags, logged_in, logged_out, not_has_tags)
- `_ghl_required_tags`: Array of GHL tag IDs (e.g., `["3Rp2UbTr3qcJRlzmh08m"]`)

#### 2. Menu Rendering (wp_nav_menu_item_custom_fields hook)
- PHP generates complete HTML structure
- Outputs `data-saved-tags` with tag IDs array
- Outputs `data-tag-names` with ID=>name map for JavaScript
- Pre-selects visibility rule dropdown
- No HTML generation in JavaScript

#### 3. Menu Filtering (wp_get_nav_menu_items filter)
- Fetches user's tags from `_ghl_contact_tags` user meta
- Converts stored tag IDs to tag names via cached transient
- Case-insensitive comparison (all lowercase)
- Removes items from array before rendering (not CSS hiding)
- Skips filtering in admin area

#### 4. Tag Caching System
```php
// Transient key pattern
'ghl_tags_{locationId}_site_{siteId}'

// Cache structure
[
  ['id' => '3Rp2UbTr3qcJRlzmh08m', 'name' => 'VIP'],
  ['id' => 'aB2cD3eF4gH5iJ6kL7mN', 'name' => 'Premium']
]
```

### Frontend (JavaScript) - UI Enhancement Only

#### 1. Select2 Initialization
- Reads `data-saved-tags` and `data-tag-names` from PHP-rendered HTML
- Converts to Select2 format: `{id: tagId, text: tagName}`
- Loads saved selections using tag IDs as values
- No HTML string generation or manipulation

#### 2. AJAX Tag Loading
- Calls `wp_ajax_ghl_crm_get_tags` endpoint
- Returns tags from site-specific transient
- Select2 uses tag.id for value, tag.name for display
- Searches tags client-side after AJAX load

#### 3. Visibility Toggle
- Shows/hides tag selector based on rule selection
- Pure event-driven logic (no DOM manipulation)
- No data processing or validation

## Technical Details

### Files Structure
```
src/Core/ConditionalMenus.php          # Main singleton class (enterprise-grade)
assets/admin/js/menu-editor.js         # Select2 initialization only
```

### Class Constants (ConditionalMenus.php)
```php
private const META_VISIBILITY_RULE = '_ghl_visibility_rule';
private const META_REQUIRED_TAGS = '_ghl_required_tags';
private const META_USER_TAGS = '_ghl_contact_tags';
private const TRANSIENT_TAG_PATTERN = 'ghl_tags_%s_site_%d';
```

### Database Storage
Menu items are custom post types (`nav_menu_item`):
```php
// Visibility rule
update_post_meta($menu_item_id, '_ghl_visibility_rule', 'has_any_tag');

// Required tag IDs (NOT names - IDs are unique)
update_post_meta($menu_item_id, '_ghl_required_tags', [
    '3Rp2UbTr3qcJRlzmh08m',  // VIP
    'aB2cD3eF4gH5iJ6kL7mN'   // Premium
]);
```

### Saving Process (save_menu_item_settings)
```php
// Sanitize, de-duplicate, re-index
$tag_ids = array_values(array_unique(array_filter($raw_tags)));
update_post_meta($menu_item_db_id, self::META_REQUIRED_TAGS, $tag_ids);
```

### User Tag Storage
User tags synced from GHL stored in user meta:
```php
get_user_meta($user_id, '_ghl_contact_tags', true);
// Returns: ['VIP', 'Customer', 'Active'] (tag names)
```

### Tag Conversion System
```php
// Single method retrieves cached tags
private function get_cached_tags(): array {
    $transient_key = sprintf(
        self::TRANSIENT_TAG_PATTERN, 
        $location_id, 
        $site_id
    );
    return get_transient($transient_key);
}

// Convert IDs to names for comparison
private function convert_tag_ids_to_names(array $tag_ids): array {
    // Uses get_cached_tags() internally
    // Falls back to ID if name not found
}

// Get ID=>name map for JavaScript
private function get_tag_names_map(array $tag_ids): array {
    // Used in render_menu_item_fields()
    // Filters cached tags for requested IDs only
}
```

### Visibility Logic (Optimized)
```php
private function should_display_menu_item($item, int $user_id, array $user_tags): bool {
    $rule = get_post_meta($item->ID, self::META_VISIBILITY_RULE, true);
    
    // Default: show to everyone
    if (empty($rule)) return true;
    
    $is_logged_in = $user_id > 0;
    
    // Fast path for login-based rules (no tag processing)
    if ('logged_in' === $rule) return $is_logged_in;
    if ('logged_out' === $rule) return !$is_logged_in;
    
    // Tag-based rules: convert IDs to names, normalize to lowercase
    $required_tags = array_map('strtolower', 
        $this->convert_tag_ids_to_names($required_tag_ids)
    );
    
    // Simplified logic with fail-open default
    switch ($rule) {
        case 'has_any_tag':
            return $is_logged_in && $this->user_has_any_tag($user_tags, $required_tags);
        case 'has_all_tags':
            return $is_logged_in && $this->user_has_all_tags($user_tags, $required_tags);
        case 'not_has_tags':
            return !$is_logged_in || $this->user_not_has_tags($user_tags, $required_tags);
        default:
            return true; // Unknown rule, fail-open for safety
    }
}
```

### Tag Comparison Methods
```php
// ANY: at least one match
private function user_has_any_tag(array $user_tags, array $required_tags): bool {
    return !empty(array_intersect($user_tags, $required_tags));
}

// ALL: count matches equals required count
private function user_has_all_tags(array $user_tags, array $required_tags): bool {
    return count(array_intersect($required_tags, $user_tags)) === count($required_tags);
}

// NOT: no matches at all
private function user_not_has_tags(array $user_tags, array $required_tags): bool {
    return empty(array_intersect($user_tags, $required_tags));
}
```

### Admin Area Behavior
- Menu items **never filtered in admin** (`is_admin()` check)
- Admins always see all items for editing
- Prevents accidentally hiding items from editor

## WordPress Compatibility
- Works with all WordPress themes
- Compatible with any menu location (primary, footer, sidebar, etc.)
- Works with menu widgets and `wp_nav_menu()`
- No theme modifications required

## GHL Integration

### Tag Storage Strategy
**Why Store IDs Instead of Names?**
- GHL tag IDs are immutable and unique
- Tag names can be changed in GHL dashboard
- Prevents duplicate tags from being saved
- Allows tag renaming without breaking menu rules

### Tag Synchronization
```php
// User tags stored as names (for backward compatibility)
update_user_meta($user_id, '_ghl_contact_tags', ['VIP', 'Premium', 'Active']);

// Menu items store tag IDs (for uniqueness)
update_post_meta($menu_item_id, '_ghl_required_tags', [
    '3Rp2UbTr3qcJRlzmh08m',
    'aB2cD3eF4gH5iJ6kL7mN'
]);

// System converts IDs to names at runtime
$tag_names = $this->convert_tag_ids_to_names($tag_ids);
```

### Cache Workflow
1. **Tag Sync** (Webhook/Queue): Stores tags in transient `ghl_tags_{locationId}_site_{siteId}`
2. **Menu Editor** (Admin): AJAX loads tags from transient, displays in Select2
3. **Menu Save** (Admin): Stores tag IDs (from Select2 value) in post meta
4. **Menu Display** (Frontend): Converts tag IDs to names, compares with user tags
5. **Cache Refresh** (Manual): "Clear Cache" button invalidates transient

### API Dependency
- **Menu Editor**: Requires tag cache for Select2 picker
- **Menu Display**: Gracefully degrades if cache missing (uses IDs as fallback)
- **Zero API Calls**: Never calls GHL API during menu rendering
- **Site-Specific**: Each site has independent tag cache

## Performance Optimizations

### Zero Runtime Overhead
- **No API calls**: Uses cached tags from transient (ghl_tags_{locationId}_site_{siteId})
- **No extra queries**: User tags already loaded in user meta
- **Fast-path logic**: Login-based rules skip tag processing entirely
- **Single transient read**: get_cached_tags() called once per request

### Memory Efficiency
- **Array filtering**: Items removed before rendering (not hidden with CSS)
- **Lazy tag conversion**: Only converts IDs to names when tag rules are active
- **Selective caching**: Tag names map only built for saved tag IDs

### Frontend Performance
- **Server-side filtering**: Menu array filtered before wp_nav_menu() renders
- **No JavaScript filtering**: JS only handles Select2 UI in admin
- **No DOM manipulation**: Clean HTML output, no client-side hiding

### Caching Strategy
```php
// Tags cached per location per site
Transient Key: ghl_tags_{locationId}_site_{siteId}
Expiration: Set by tag sync process
Structure: [['id' => '...', 'name' => '...'], ...]

// Fallback behavior
If transient missing: Returns IDs as-is (graceful degradation)
If tag not found: Uses ID as fallback name
```

## Multisite Support
- Works independently on each site in network
- Menu items per-site (standard WP behavior)
- User tags are site-specific
- No network-wide menu support (WordPress limitation)

## Security (Enterprise-Grade)

### Access Control
- **Capability checks**: Only users with `edit_theme_options` can modify menu settings
- **Nonce verification**: Uses WordPress native `update-nav_menu` nonce
- **Admin-only UI**: Menu editor only loads on `nav-menus.php` admin page

### Data Sanitization
```php
// Input sanitization on save
$rule = sanitize_text_field(wp_unslash($_POST['menu-item-ghl-visibility-rule'][$id]));
$tag_ids = array_map('sanitize_text_field', $raw_tags);
$tag_ids = array_values(array_unique(array_filter($tag_ids))); // De-duplicate
```

### Output Escaping
```php
// All output escaped
data-saved-tags='<?php echo esc_attr(wp_json_encode($tag_ids)); ?>'
<option value="<?php echo esc_attr($rule); ?>">
<?php esc_html_e('Show to Everyone', 'ghl-crm-integration'); ?>
```

### Array Filtering (Not CSS Hiding)
- **Server-side removal**: Items removed from `$items` array before rendering
- **No client-side exposure**: Hidden menu items never sent to browser
- **No DOM inspection**: Users cannot view hidden menu HTML in DevTools
- **True access control**: Not cosmetic hiding, actual data filtering

### Fail-Safe Behavior
- **Unknown rules**: Default to visible (fail-open to prevent accidental lockout)
- **Missing tags**: Empty tags array treated as "show to everyone"
- **Cache miss**: Falls back to tag IDs (allows basic functionality)
- **Admin bypass**: Never filter in admin area (prevents editor lockout)

## Limitations & Design Decisions

### By Design
- **Tag IDs only**: System uses GHL tag IDs (not custom meta) for consistency
- **Server-side filtering**: Items removed from array (no CSS hiding - this is a security feature)
- **Logged-out users**: Have no tags by definition (cannot match tag-based rules, only logged_out rule)
- **Case-insensitive matching**: All comparisons lowercase (prevents tag name case issues)

### External Dependencies
- **Active GHL connection**: Required for tag cache population
- **Tag sync**: User tags must be synced via webhook or queue
- **Transient cache**: Depends on `ghl_tags_{locationId}_site_{siteId}` transient

### WordPress Limitations
- **Menu editing UI**: Uses standard WordPress menu editor (no custom UI)
- **wp_nav_menu() only**: Works with standard menu functions (not custom menu code)
- **Post meta storage**: Limited by WordPress post meta table structure

## Troubleshooting

### Menu Item Not Hiding

**Check User Tags**
```php
// Verify user has tags stored
$user_tags = get_user_meta($user_id, '_ghl_contact_tags', true);
print_r($user_tags); // Should return array of tag names
```

**Check Menu Item Settings**
```php
// Verify rule is stored
$rule = get_post_meta($menu_item_id, '_ghl_visibility_rule', true);
$tag_ids = get_post_meta($menu_item_id, '_ghl_required_tags', true);
echo "Rule: $rule\n";
print_r($tag_ids); // Should return array of tag IDs
```

**Common Issues**
1. Tags not synced from GHL (User Profile → GHL Section should show tags)
2. Rule type incorrect (ANY vs ALL vs NOT)
3. Tag cache stale (transient `ghl_tags_{locationId}_site_{siteId}` expired)
4. User logged out (tag-based rules require login)
5. Full-page cache not cleared (some caching plugins cache menus)

### Tags Not Loading in Select2 Picker

**Verify Tag Cache**
```php
$settings = get_option('ghl_crm_settings', []);
$location_id = $settings['location_id'];
$site_id = get_current_blog_id();
$transient_key = "ghl_tags_{$location_id}_site_{$site_id}";
$tags = get_transient($transient_key);
print_r($tags); // Should return array of tag objects
```

**Common Issues**
1. GHL connection inactive (Settings → Dashboard shows "Not Connected")
2. Location ID not set (Settings → General)
3. AJAX endpoint blocked (Check Network tab: `/wp-admin/admin-ajax.php?action=ghl_crm_get_tags`)
4. JavaScript error (Check Console for `ghlMenuEditor is not defined`)
5. Select2 not loaded (Check Network tab for `select2.min.js`)

### Tags Show Wrong Names

**Symptom**: Tag picker shows IDs instead of names

**Cause**: Tag cache missing or malformed

**Solution**:
1. Go to GHL Settings → Dashboard
2. Click "Clear Cache" button
3. Wait for next tag sync (webhook or manual sync)
4. Refresh menu editor page

### Menu Item Stuck Visible

**Symptom**: Menu item shows to everyone despite rule

**Debug Steps**:
```php
// Check if rule is empty (defaults to visible)
$rule = get_post_meta($menu_item_id, '_ghl_visibility_rule', true);
if (empty($rule)) {
    echo "No rule set - showing to everyone (default)";
}

// Check if filter is running
add_filter('wp_get_nav_menu_items', function($items) {
    error_log('Menu filter running. Item count: ' . count($items));
    return $items;
}, 5);
```

**Common Issues**
1. Rule not saved (click "Save Menu" after changes)
2. Menu cached by theme or plugin
3. Custom menu walker bypassing filters
4. Menu item added after filter runs

## Code Quality & Enterprise Standards

### Design Patterns Used
- **Singleton Pattern**: Single instance via `get_instance()`
- **Separation of Concerns**: Storage (IDs) vs Display (names)
- **Dependency Injection**: Uses class constants for meta keys
- **Fail-Safe Defaults**: Unknown rules default to visible
- **DRY Principle**: Single `get_cached_tags()` method

### Code Standards
- **PHP 7.4+ Type Hints**: Strict types declared
- **PSR-12 Formatting**: Consistent code style
- **WordPress Coding Standards**: PHPCS compliant
- **Security First**: All inputs sanitized, outputs escaped
- **Performance Optimized**: Zero N+1 queries, cached data

### Maintainability
- **Self-Documenting**: Clear method names, comprehensive docblocks
- **Constants Over Magic Strings**: All meta keys as class constants
- **Single Responsibility**: Each method has one clear purpose
- **Testable**: Pure functions for tag comparison logic
- **Extensible**: Hook-based architecture

## Future Enhancements

### Under Consideration
- **Custom field conditions**: Show/hide based on other user meta
- **Membership integration**: Sync with MemberPress, WooCommerce Memberships
- **Time-based visibility**: Schedule menu items (date ranges)
- **User role conditions**: Combine GHL tags with WordPress roles
- **Analytics dashboard**: Track menu item visibility and clicks

### Not Planned (By Design)
- **Client-side filtering**: Security risk, defeats purpose
- **CSS hiding**: Not true access control
- **Multiple locations**: WordPress menu system limitation
- **Custom menu editor UI**: Maintains WordPress standards
