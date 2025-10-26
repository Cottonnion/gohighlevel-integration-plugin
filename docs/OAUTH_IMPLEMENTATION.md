# OAuth Implementation

## Overview
This document describes the OAuth2 implementation for connecting GoHighLevel accounts using the plugin's centralized OAuth app.

## Architecture

### Centralized OAuth App
The plugin uses a **single OAuth app** owned by the plugin developer:
- OAuth Client ID and Client Secret are **hardcoded** as constants in `Client.php`
- All users connect through the same OAuth app
- No user configuration required - just click "Connect to GoHighLevel"
- Simpler user experience - one-click connection

### Files Modified

#### 1. `src/API/Client/Client.php`
**Current State:**
- Uses hardcoded constants: `OAUTH_CLIENT_ID` and `OAUTH_CLIENT_SECRET`
- These are private constants that you replace with your actual OAuth app credentials
- All OAuth methods use these constants:
  - `get_oauth_authorization_url()`
  - `exchange_code_for_token()`
  - `refresh_access_token()`
  - `reconnect_api()`

**Setup Required:**
Replace the placeholder values in Client.php:
```php
private const OAUTH_CLIENT_ID = 'your-actual-client-id-here';
private const OAUTH_CLIENT_SECRET = 'your-actual-client-secret-here';
```

#### 2. `templates/admin/dashboard.php`
**Features:**
- OAuth connection status display
- **"Connect to GoHighLevel" button** (always shown when not connected)
- Disconnect button (with confirmation)
- Connection details table (Location ID, Location Name, Token Status)
- Sync statistics (Total, Successful, Failed syncs, Last sync time)
- AJAX-powered disconnect functionality

**User Flow:**
1. If not connected → Show "Connect to GoHighLevel" button
2. If connected → Show connection details, sync stats, and disconnect button

#### 3. `src/Core/MenuManager.php`
**Changes:**
- Added `handle_oauth_disconnect()` AJAX handler
- Dashboard loads from template file
- Added hook for `wp_ajax_ghl_crm_oauth_disconnect` action

#### 4. `src/API/OAuth/OAuthHandler.php`
**Features:**
- Generates authorization URL using Client constants
- Handles OAuth callback and token exchange
- Manages token refresh
- Provides connection status

## OAuth Flow

### No Setup Required for Users
Users don't need to create their own OAuth app or configure credentials. The plugin developer does this once.

### Authorization Flow (End User)
1. User navigates to **Dashboard**
2. User clicks **"Connect to GoHighLevel"** button
3. User is redirected to GoHighLevel authorization page
4. User selects their location and authorizes the plugin
5. GoHighLevel redirects back with authorization code
6. Plugin exchanges code for access token and refresh token
7. Tokens are saved to WordPress options
8. User is redirected to Dashboard (now showing connected state)

### Token Refresh
- Automatic token refresh happens when API requests receive 401/403 errors
- Client's `handle_http_response()` filter catches auth errors globally
- Refresh token is used to get new access token
- New tokens are saved automatically

### Disconnection
1. User clicks **"Disconnect Account"** button
2. Confirmation dialog appears
3. On confirm, AJAX request calls `ghl_crm_oauth_disconnect` action
4. `OAuthHandler::disconnect()` removes OAuth tokens from settings
5. Dashboard reloads showing disconnected state

## Settings Structure

### Plugin Developer Settings (Hardcoded in Client.php)
```php
private const OAUTH_CLIENT_ID = 'your-client-id';
private const OAUTH_CLIENT_SECRET = 'your-client-secret';
```

### OAuth Token Settings (Auto-managed per user)
```php
[
    'oauth_access_token'  => 'access-token',          // Current access token
    'oauth_refresh_token' => 'refresh-token',         // Refresh token
    'oauth_expires_at'    => 1234567890,              // Token expiry timestamp
    'oauth_connected_at'  => '2025-10-25 12:00:00',  // Connection datetime
    'location_id'         => 'location-id',           // GHL location ID
    'location_name'       => 'My Location',           // GHL location name
]
```

## Security Considerations

### State Parameter
- Generated using `wp_create_nonce()`
- Stored in transient with 1-hour expiry
- Verified on callback to prevent CSRF attacks

### Nonce Verification
- All AJAX requests verify WordPress nonces
- Disconnect action requires `ghl_crm_oauth_disconnect` nonce

### Capability Checks
- All OAuth actions require `manage_options` capability
- Only administrators can connect/disconnect accounts

### Token Storage
- Tokens stored in WordPress options table
- No tokens in client-side JavaScript
- Refresh happens server-side only

## Multisite Compatibility

The OAuth implementation is **multisite-aware**:
- Each site in a network can have different OAuth credentials
- SettingsManager handles network vs. site options automatically
- Rate limiting tracks by GHL location ID (shared across sites if using same location)

## Error Handling

### Missing OAuth Credentials
- Dashboard shows warning message with link to API Settings
- Connect button is hidden until credentials are configured

### Token Expiry
- Automatic refresh via `handle_http_response()` filter
- If refresh fails, user sees admin notice to reconnect

### API Errors
- 401/403 errors trigger automatic token refresh
- If refresh fails, tokens are cleared and user must reconnect

## Testing Checklist

- [ ] Configure OAuth credentials in API Settings
- [ ] Click "Connect to GoHighLevel" button on Dashboard
- [ ] Verify redirect to GoHighLevel authorization page
- [ ] Complete authorization and verify redirect back
- [ ] Confirm Dashboard shows connected state
- [ ] Verify connection details are displayed
- [ ] Test disconnect functionality
- [ ] Confirm reconnection works
- [ ] Test with expired access token (should auto-refresh)
- [ ] Test with invalid refresh token (should show reconnect notice)

## Future Enhancements

- [ ] Add OAuth app setup instructions in documentation
- [ ] Add visual indicator for token refresh in progress
- [ ] Add "Test Connection" button on Dashboard
- [ ] Store and display more connection metadata
- [ ] Add connection history/audit log
