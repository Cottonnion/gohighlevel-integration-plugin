# Dashboard Connection Options

## Overview
The dashboard now provides **two methods** to connect to GoHighLevel, giving users flexibility based on their needs and technical expertise.

---

## Connection Methods

### 1. API Key (Recommended) - Default Tab ⭐

**Best for:**
- Individual location setup
- Direct sub-account access
- Simple, straightforward connection
- Users who prefer manual configuration

**How it works:**
1. User logs into their GoHighLevel location (sub-account)
2. Goes to Settings → Integrations → API Key
3. Copies API Key and Location ID
4. Pastes into WordPress dashboard form
5. Connection is verified and saved

**Advantages:**
✅ Simple and straightforward  
✅ No OAuth app configuration needed  
✅ Works immediately  
✅ Full control over credentials  
✅ Easy to troubleshoot  

**Disadvantages:**
❌ Manual token entry required  
❌ Need to update if token changes  
❌ One location per setup  

---

### 2. OAuth Connection - Second Tab

**Best for:**
- Agency managing multiple locations
- Users who want automatic token refresh
- Clients who don't want to handle API keys
- Professional deployments

**How it works:**
1. User clicks "Connect with GoHighLevel"
2. Redirected to GoHighLevel authorization page
3. Selects location to connect
4. Approves permissions
5. Redirected back to WordPress
6. Connection established automatically

**Advantages:**
✅ One-click connection  
✅ Automatic token refresh  
✅ More secure (OAuth2 standard)  
✅ Works across multiple locations  
✅ Professional user experience  

**Disadvantages:**
❌ Requires OAuth app setup  
❌ More complex for beginners  
❌ Depends on redirect URL configuration  

---

## User Interface

### Tab Navigation

```
┌─────────────────────────────────────────────────┐
│ [API Key (Recommended)] [OAuth Connection]      │
├─────────────────────────────────────────────────┤
│                                                  │
│  Tab Content Here                                │
│                                                  │
└─────────────────────────────────────────────────┘
```

**Default:** API Key tab is active by default  
**Switching:** Click tab buttons to switch views  
**Responsive:** Stacks vertically on mobile devices  

---

## API Key Tab (Manual Connection)

### Form Fields

**1. API Key** (required)
- **Type:** Text input
- **Format:** Long alphanumeric string
- **Example:** `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...`
- **Validation:** Required, sanitized

**2. Location ID** (required)
- **Type:** Text input
- **Format:** Alphanumeric ID
- **Example:** `ve9EPM428h8vShlRW1KT`
- **Validation:** Required, sanitized

### Help Section

Displays step-by-step instructions:
1. Log into GoHighLevel location
2. Navigate to Settings → Integrations → API Key
3. Generate or copy existing API key
4. Copy Location ID from same page

**Visual:** Blue info box with numbered steps

### Form Submission

**Process:**
1. Form validation (client-side)
2. AJAX request to `ghl_crm_manual_connect`
3. Server-side validation
4. API connection test (GET /locations/:id)
5. Save settings if successful
6. Display success message
7. Reload page to show connected state

**Error Handling:**
- Empty fields: Validation error
- Invalid credentials: Connection test fails
- API error: Error message displayed
- Network error: Generic error message

---

## OAuth Tab

### Information Sections

**1. Description**
- Explains OAuth connection benefits
- Targeted at agencies and multi-location users

**2. Benefits List** (yellow info box)
- One-click connection
- Automatic token refresh
- Multi-location support
- Enhanced security

**3. Required Permissions** (gray box with checkmarks)
- Read and write contacts
- Manage contact tags
- Manage custom fields

### Connect Button

**Appearance:**
- Large primary button (hero size)
- Cloud icon
- Centered in tab

**Behavior:**
- Opens in new tab (`target="_blank"`)
- Redirects to GoHighLevel OAuth page
- User authorizes app
- Redirects back to WordPress
- Connection established automatically

**URL:**
```
https://marketplace.gohighlevel.com/oauth/chooselocation
?response_type=code
&redirect_uri=...
&client_id=68ff9baa25051d0ca83341e9-mh9cljcg
&scope=contacts.readonly+contacts.write+...
```

---

## Connected State

### OAuth Connected

**Displays:**
- ✅ Connected status with checkmark
- Location name (if available)
- Connection date
- Disconnect button

**Connection Details Table:**
- Location ID
- Location Name
- Token Status (expires in X time / will auto-refresh)

**Sync Statistics Table:**
- Total syncs
- Successful syncs (green)
- Failed syncs (red)
- Last sync time

### Manual API Key Connected

**Displays:**
- ✅ Connected (API Key) status
- Note about manual configuration
- Disconnect/update option

**Connection Details Table:**
- Location ID
- API Token (first 20 chars + ...)

**Action Button:**
- "Update Connection Settings" → Goes to settings page

---

## JavaScript Functionality

### Tab Switching
```javascript
$('.ghl-tab-button').on('click', function() {
    var tabId = $(this).data('tab');
    
    // Update active states
    $('.ghl-tab-button').removeClass('active');
    $(this).addClass('active');
    
    // Show/hide content
    $('.ghl-tab-content').removeClass('active');
    $('#' + tabId + '-tab').addClass('active');
});
```

### Manual Connection Form
```javascript
$('#ghl-manual-connection-form').on('submit', function(e) {
    e.preventDefault();
    
    // Show loading state
    // Submit via AJAX
    // Handle success/error
    // Reload page on success
});
```

### Disconnect Handler
```javascript
$('#ghl-disconnect-btn').on('click', function(e) {
    e.preventDefault();
    
    // Confirm action
    // Submit disconnect request
    // Reload page on success
});
```

---

## PHP AJAX Handlers

### `handle_manual_connect()`

**Location:** `src/Core/MenuManager.php`

**Process:**
1. Verify nonce (`ghl_crm_manual_connect`)
2. Check user capability (`manage_options`)
3. Sanitize inputs (API key, Location ID)
4. Validate inputs (not empty)
5. Create API client instance
6. Test connection (GET /locations/:id)
7. Extract location name from response
8. Save settings if successful
9. Return JSON success/error

**Success Response:**
```json
{
  "success": true,
  "data": {
    "message": "Successfully connected to GoHighLevel!"
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "data": {
    "message": "Connection failed: Invalid credentials"
  }
}
```

---

## Styling

### CSS Classes

**`.ghl-connection-tabs`**
- Container for tab interface

**`.ghl-tab-nav`**
- Tab button container
- Flexbox layout
- Bottom border

**`.ghl-tab-button`**
- Individual tab button
- Gray background when inactive
- White background when active
- Border matches active state

**`.ghl-tab-content`**
- Tab content container
- Hidden by default (`display: none`)
- Shown when `.active` class added

**`.ghl-tab-inner`**
- Inner padding for content
- Consistent spacing

**`.ghl-info-box`**
- Colored info boxes
- Blue for instructions
- Yellow for benefits

### Responsive Design

**Desktop (>782px):**
- Tabs displayed horizontally
- Side-by-side layout

**Mobile (≤782px):**
- Tabs stacked vertically
- Full-width buttons
- Reduced padding

---

## User Experience Flow

### First-Time Setup (Not Connected)

1. User navigates to **Dashboard**
2. Sees "Connection Setup" heading
3. **API Key tab is active by default** ⭐
4. Reads instructions in blue info box
5. Logs into GoHighLevel in separate tab
6. Copies API Key and Location ID
7. Pastes into form fields
8. Clicks "Connect Now" button
9. Sees loading spinner
10. Gets success message
11. Page reloads showing connected state

**Alternative Path (OAuth):**
1. User clicks **"OAuth Connection"** tab
2. Reads about OAuth benefits
3. Clicks **"Connect with GoHighLevel"**
4. Authorizes in GoHighLevel
5. Redirected back to WordPress
6. Connection established

### Changing Connection Method

**From API Key to OAuth:**
1. Disconnect current connection
2. Page reloads showing tabs
3. Switch to OAuth tab
4. Click "Connect with GoHighLevel"

**From OAuth to API Key:**
1. Disconnect current connection
2. Page reloads showing tabs
3. Fill in API Key form (default tab)
4. Submit connection

---

## Security Features

### Nonce Verification
- ✅ Manual connect: `ghl_crm_manual_connect`
- ✅ OAuth disconnect: `ghl_crm_oauth_disconnect`

### Capability Checks
- ✅ `manage_options` required for all actions

### Input Sanitization
- ✅ API Key: `sanitize_text_field()`
- ✅ Location ID: `sanitize_text_field()`
- ✅ All outputs escaped

### Connection Validation
- ✅ Test API call before saving credentials
- ✅ Verify location exists
- ✅ Extract location name
- ✅ Only save if test successful

---

## Error Handling

### Client-Side Errors

**Empty Fields:**
```
Browser validation: "Please fill out this field"
```

**Network Error:**
```
"An error occurred while connecting"
```

### Server-Side Errors

**Invalid Credentials:**
```
"Connection failed: The token does not have access to this location"
```

**Missing Fields:**
```
"API Key and Location ID are required"
```

**Permission Error:**
```
"You do not have permission to manage connections"
```

**API Error:**
```
"Connection failed: [API error message]"
```

---

## Testing Checklist

### Manual Connection
- [ ] Default tab is API Key
- [ ] Form fields are required
- [ ] Help text displays correctly
- [ ] Form submits via AJAX
- [ ] Loading state shows spinner
- [ ] Success message displays
- [ ] Page reloads after success
- [ ] Connection test validates credentials
- [ ] Error messages display properly
- [ ] Invalid credentials rejected

### OAuth Connection
- [ ] Tab switches correctly
- [ ] OAuth benefits display
- [ ] Connect button opens in new tab
- [ ] Correct OAuth URL generated
- [ ] Redirects to GoHighLevel
- [ ] Authorization works
- [ ] Redirects back to WordPress
- [ ] Connection established

### Connected State
- [ ] Shows correct connection type
- [ ] Displays location details
- [ ] Shows sync statistics
- [ ] Disconnect button works
- [ ] Confirmation prompt appears
- [ ] Page reloads after disconnect

### Responsive Design
- [ ] Tabs stack on mobile
- [ ] Form is usable on mobile
- [ ] Buttons are tappable
- [ ] Text is readable
- [ ] Padding adjusts properly

---

## Migration Notes

### Existing Users

**With OAuth Connection:**
- No action needed
- Dashboard shows "Connected (OAuth)"
- Can continue using OAuth

**With Manual API Key:**
- No action needed
- Dashboard shows "Connected (API Key)"
- Can continue using manual key

**No Connection:**
- Sees tab interface
- API Key tab active by default
- Can choose either method

### Upgrading Plugin

**From Previous Version:**
1. Existing connections preserved
2. Dashboard layout updates
3. New tab interface appears
4. No re-connection needed

---

## Troubleshooting

### Tabs Not Switching

**Check:**
- JavaScript console for errors
- jQuery is loaded
- Tab buttons have correct `data-tab` attribute

**Fix:**
- Clear browser cache
- Reload page
- Check for JavaScript conflicts

### Form Not Submitting

**Check:**
- Nonce field exists
- AJAX URL is correct
- User has `manage_options` capability

**Fix:**
- Verify nonce generation
- Check AJAX handler is registered
- Test with browser network tab

### Connection Test Failing

**Check:**
- API key is valid
- Location ID is correct
- API endpoint is accessible
- Server can reach GoHighLevel API

**Fix:**
- Verify credentials in GoHighLevel
- Test API key manually
- Check server firewall settings
- Review error logs

---

## Summary

✅ **Two Connection Methods**
- API Key (default, recommended for most users)
- OAuth (advanced, for agencies)

✅ **User-Friendly Interface**
- Tabbed layout
- Clear instructions
- Helpful information boxes

✅ **Robust Validation**
- Client-side form validation
- Server-side credential testing
- Secure error handling

✅ **Flexible Setup**
- Users choose preferred method
- Easy to switch between methods
- No configuration conflicts

🎯 **Next Steps:**
1. Test both connection methods
2. Verify error handling
3. Test responsive design
4. Gather user feedback
