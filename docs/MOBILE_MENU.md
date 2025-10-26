# Mobile Responsive Settings Menu

## Overview
The settings page now features a responsive sidebar menu that adapts to different screen sizes with special behavior on mobile devices.

## Features

### Desktop (> 782px)
- Full sidebar visible at all times
- Icons + labels always shown
- Fixed width: 220px
- Standard navigation

### Tablet (≤ 782px)
- **Collapsed by default**: Shows only icons (60px width)
- **Expandable**: Click floating action button to expand to full width (220px)
- **Smooth transitions**: Menu smoothly expands/collapses
- Content area adjusts margin automatically
- Floating action button (FAB) in bottom-right corner

### Mobile (≤ 600px)
- **Hidden by default**: Menu off-screen (left: -220px)
- **Slide-in overlay**: Expands over content with shadow
- **Dark overlay**: Semi-transparent backdrop when menu is open
- Floating action button toggles menu
- Auto-closes after tab selection

## User Interactions

### Opening Menu
- **Click FAB**: Floating button in bottom-right corner
- **Icon changes**: Menu icon (☰) when closed, X icon when open

### Closing Menu
- **Click FAB again**: Toggle button
- **Click outside**: Tap anywhere outside menu
- **Press Escape**: Keyboard shortcut
- **Select tab**: Auto-closes on mobile after selection

## Implementation Details

### Files Modified

#### 1. `templates/admin/settings.php`
Added:
- `id="ghl-settings-nav"` to navigation
- Mobile toggle button with dual icons
- Proper ARIA labels for accessibility

#### 2. `assets/admin/css/settings-menu.css`
Added:
- `.ghl-settings-menu-toggle`: Floating action button styles
- Responsive breakpoints for tablet and mobile
- `.expanded` class for open state
- Body overlay for mobile
- Smooth transitions for all states

#### 3. `assets/admin/js/settings-menu.js`
Added functions:
- `initMobileMenuToggle()`: Initialize toggle functionality
- `toggleMobileMenu()`: Toggle menu open/closed
- `closeMobileMenu()`: Close menu programmatically
- Event handlers for click outside, Escape key, and tab selection
- Cleanup handlers in `cleanupSettingsMenu()`

## CSS Classes

### Navigation States
- `.ghl-settings-nav`: Base navigation
- `.ghl-settings-nav.expanded`: Menu is open/expanded

### Body States
- `.ghl-menu-open`: Added to body when menu is open on mobile (creates overlay)

### Button Icons
- `.dashicons-menu`: Hamburger icon (shown when closed)
- `.dashicons-no-alt`: X icon (shown when open)

## Responsive Behavior

### Tablet (782px - 600px)
```css
.ghl-settings-nav {
    width: 60px;              /* Collapsed */
    transition: width 0.3s;
}

.ghl-settings-nav.expanded {
    width: 220px;             /* Expanded */
}

.ghl-tab-label {
    opacity: 0;               /* Hidden when collapsed */
}

.ghl-settings-nav.expanded .ghl-tab-label {
    opacity: 1;               /* Visible when expanded */
}
```

### Mobile (≤ 600px)
```css
.ghl-settings-nav {
    left: -220px;             /* Off-screen */
    width: 0;
}

.ghl-settings-nav.expanded {
    left: 0;                  /* Slide in */
    width: 220px;
}

body.ghl-menu-open::before {
    /* Dark overlay */
    background: rgba(0, 0, 0, 0.5);
}
```

## Accessibility

### ARIA Attributes
- `aria-label`: Descriptive label for toggle button
- `aria-expanded`: Dynamically updated based on menu state

### Keyboard Support
- **Escape key**: Closes menu
- **Tab navigation**: Full keyboard navigation support

### Focus Management
- Focus outline on interactive elements
- Proper focus handling when menu opens/closes

## Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Uses CSS3 transitions
- Fallback for browsers without transition support

## Performance
- CSS transitions for smooth animations
- Minimal JavaScript overhead
- Event delegation for efficiency
- Proper cleanup prevents memory leaks

## Testing Checklist
- [ ] Desktop: Sidebar always visible
- [ ] Tablet: Collapsed by default, expands on button click
- [ ] Mobile: Hidden by default, slides in on button click
- [ ] Click outside closes menu
- [ ] Escape key closes menu
- [ ] Tab selection closes menu on mobile
- [ ] Icons visible when collapsed
- [ ] Labels visible when expanded
- [ ] Smooth transitions
- [ ] No layout shift on expand/collapse
- [ ] Overlay appears on mobile
- [ ] Button icon changes correctly

## Future Enhancements
- [ ] Remember menu state in localStorage
- [ ] Swipe gestures for mobile
- [ ] Animation preferences (respect prefers-reduced-motion)
- [ ] Touch feedback improvements
