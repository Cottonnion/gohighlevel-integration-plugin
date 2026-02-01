# OAuth Proxy Setup Guide

## Overview

To keep OAuth credentials secure and comply with WordPress.org guidelines, the GoHighLevel CRM Integration plugin uses a proxy server at **labgenz.com** to handle all OAuth token operations. This prevents the client secret from being exposed in the distributed plugin code.

## Architecture

```
User WordPress Site → labgenz.com Proxy → GoHighLevel API
```

**Benefits:**
- ✅ Client secret never exposed in plugin code
- ✅ WordPress.org distribution compliant
- ✅ Centralized credential management
- ✅ Easy to rotate credentials without plugin updates
- ✅ Additional security validations possible

## Required Proxy Endpoints

You need to create three REST API endpoints on **labgenz.com**:

### 1. Exchange Authorization Code for Tokens

**Endpoint:** `POST /wp-json/ghl-proxy/v1/exchange-token`

**Purpose:** Exchange authorization code for access/refresh tokens during initial OAuth connection.

**Request Body:**
```json
{
  "code": "auth_code_from_callback",
  "redirect_uri": "https://labgenz.com/wp-json/ghl/v1/callback"
}
```

**Response:**
```json
{
  "access_token": "eyJ...",
  "refresh_token": "def...",
  "token_type": "Bearer",
  "expires_in": 86400,
  "scope": "contacts.readonly contacts.write ...",
  "userType": "Location",
  "locationId": "xyz123"
}
```

**Implementation:**
```php
public function exchange_token( WP_REST_Request $request ) {
    $code         = sanitize_text_field( $request->get_param( 'code' ) );
    $redirect_uri = esc_url_raw( $request->get_param( 'redirect_uri' ) );
    
    if ( empty( $code ) || empty( $redirect_uri ) ) {
        return new WP_Error( 'missing_params', 'Missing required parameters', [ 'status' => 400 ] );
    }
    
    // Call GoHighLevel token endpoint with client secret
    $response = wp_remote_post(
        'https://services.leadconnectorhq.com/oauth/token',
        [
            'body' => [
                'client_id'     => '68ff9baa25051d0ca83341e9-mh9cljcg',
                'client_secret' => get_option( 'ghl_oauth_client_secret' ), // Stored securely
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $redirect_uri,
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]
    );
    
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'exchange_failed', $response->get_error_message(), [ 'status' => 500 ] );
    }
    
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
    return rest_ensure_response( $body );
}
```

---

### 2. Refresh Access Token

**Endpoint:** `POST /wp-json/ghl-proxy/v1/refresh-token`

**Purpose:** Refresh expired access token using refresh token.

**Request Body:**
```json
{
  "refresh_token": "def50200..."
}
```

**Response:**
```json
{
  "access_token": "eyJ...",
  "refresh_token": "def...",
  "token_type": "Bearer",
  "expires_in": 86400,
  "scope": "contacts.readonly contacts.write ..."
}
```

**Implementation:**
```php
public function refresh_token( WP_REST_Request $request ) {
    $refresh_token = sanitize_text_field( $request->get_param( 'refresh_token' ) );
    
    if ( empty( $refresh_token ) ) {
        return new WP_Error( 'missing_token', 'Missing refresh token', [ 'status' => 400 ] );
    }
    
    // Rate limiting: prevent abuse
    $cache_key = 'ghl_refresh_' . md5( $refresh_token );
    if ( get_transient( $cache_key ) ) {
        return new WP_Error( 'rate_limited', 'Too many refresh attempts', [ 'status' => 429 ] );
    }
    set_transient( $cache_key, true, 10 ); // 10 second cooldown
    
    // Call GoHighLevel token endpoint
    $response = wp_remote_post(
        'https://services.leadconnectorhq.com/oauth/token',
        [
            'body' => [
                'client_id'     => '68ff9baa25051d0ca83341e9-mh9cljcg',
                'client_secret' => get_option( 'ghl_oauth_client_secret' ),
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
            ],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]
    );
    
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'refresh_failed', $response->get_error_message(), [ 'status' => 500 ] );
    }
    
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
    return rest_ensure_response( $body );
}
```

---

### 3. Reconnect API (Emergency Recovery)

**Endpoint:** `POST /wp-json/ghl-proxy/v1/reconnect`

**Purpose:** Emergency token recovery when refresh token fails.

**Request Body:**
```json
{
  "location_id": "xyz123abc"
}
```

**Response:**
```json
{
  "authorizationCode": "abc123..."
}
```

**Implementation:**
```php
public function reconnect( WP_REST_Request $request ) {
    $location_id = sanitize_text_field( $request->get_param( 'location_id' ) );
    
    if ( empty( $location_id ) ) {
        return new WP_Error( 'missing_location', 'Missing location ID', [ 'status' => 400 ] );
    }
    
    // Call GoHighLevel reconnect endpoint
    $response = wp_remote_post(
        'https://services.leadconnectorhq.com/oauth/reconnect',
        [
            'body'    => wp_json_encode([
                'clientKey'    => '68ff9baa25051d0ca83341e9-mh9cljcg',
                'clientSecret' => get_option( 'ghl_oauth_client_secret' ),
                'locationId'   => $location_id,
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]
    );
    
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'reconnect_failed', $response->get_error_message(), [ 'status' => 500 ] );
    }
    
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
    return rest_ensure_response( $body );
}
```

---

## Complete Proxy Plugin Example

Create a plugin on **labgenz.com** to register these endpoints:

```php
<?php
/**
 * Plugin Name: GoHighLevel OAuth Proxy
 * Description: Secure OAuth proxy for GoHighLevel integrations
 * Version: 1.0.0
 */

namespace LabGenz\GHL_Proxy;

defined( 'ABSPATH' ) || exit;

class OAuth_Proxy {
    
    private const CLIENT_ID     = '68ff9baa25051d0ca83341e9-mh9cljcg';
    private const CLIENT_SECRET = '17bd923c-13df-4198-8f78-0675a4b2e99a'; // Store securely
    
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }
    
    public function register_routes() {
        register_rest_route( 'ghl-proxy/v1', '/exchange-token', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'exchange_token' ],
            'permission_callback' => '__return_true', // Add security checks
        ] );
        
        register_rest_route( 'ghl-proxy/v1', '/refresh-token', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'refresh_token' ],
            'permission_callback' => '__return_true',
        ] );
        
        register_rest_route( 'ghl-proxy/v1', '/reconnect', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'reconnect' ],
            'permission_callback' => '__return_true',
        ] );
    }
    
    public function exchange_token( \WP_REST_Request $request ) {
        $code         = sanitize_text_field( $request->get_param( 'code' ) );
        $redirect_uri = esc_url_raw( $request->get_param( 'redirect_uri' ) );
        
        if ( empty( $code ) || empty( $redirect_uri ) ) {
            return new \WP_Error( 'missing_params', 'Missing required parameters', [ 'status' => 400 ] );
        }
        
        $response = wp_remote_post(
            'https://services.leadconnectorhq.com/oauth/token',
            [
                'body'    => [
                    'client_id'     => self::CLIENT_ID,
                    'client_secret' => self::CLIENT_SECRET,
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirect_uri,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'timeout' => 30,
            ]
        );
        
        return $this->handle_response( $response );
    }
    
    public function refresh_token( \WP_REST_Request $request ) {
        $refresh_token = sanitize_text_field( $request->get_param( 'refresh_token' ) );
        
        if ( empty( $refresh_token ) ) {
            return new \WP_Error( 'missing_token', 'Missing refresh token', [ 'status' => 400 ] );
        }
        
        // Rate limiting
        $cache_key = 'ghl_refresh_' . md5( $refresh_token );
        if ( get_transient( $cache_key ) ) {
            return new \WP_Error( 'rate_limited', 'Too many refresh attempts', [ 'status' => 429 ] );
        }
        set_transient( $cache_key, true, 10 );
        
        $response = wp_remote_post(
            'https://services.leadconnectorhq.com/oauth/token',
            [
                'body'    => [
                    'client_id'     => self::CLIENT_ID,
                    'client_secret' => self::CLIENT_SECRET,
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refresh_token,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'timeout' => 30,
            ]
        );
        
        return $this->handle_response( $response );
    }
    
    public function reconnect( \WP_REST_Request $request ) {
        $location_id = sanitize_text_field( $request->get_param( 'location_id' ) );
        
        if ( empty( $location_id ) ) {
            return new \WP_Error( 'missing_location', 'Missing location ID', [ 'status' => 400 ] );
        }
        
        $response = wp_remote_post(
            'https://services.leadconnectorhq.com/oauth/reconnect',
            [
                'body'    => wp_json_encode([
                    'clientKey'    => self::CLIENT_ID,
                    'clientSecret' => self::CLIENT_SECRET,
                    'locationId'   => $location_id,
                ]),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 15,
            ]
        );
        
        return $this->handle_response( $response );
    }
    
    private function handle_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return new \WP_Error(
                'proxy_error',
                $response->get_error_message(),
                [ 'status' => 500 ]
            );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $decoded     = json_decode( $body, true );
        
        if ( $status_code !== 200 ) {
            return new \WP_Error(
                'ghl_error',
                $decoded['message'] ?? 'Unknown error',
                [ 'status' => $status_code ]
            );
        }
        
        return rest_ensure_response( $decoded );
    }
}

new OAuth_Proxy();
```

---

## Security Recommendations

### 1. Rate Limiting
Implement rate limiting to prevent abuse:
```php
// Max 5 requests per minute per IP
$ip = $_SERVER['REMOTE_ADDR'];
$key = 'ghl_proxy_' . md5( $ip );
$count = get_transient( $key ) ?: 0;

if ( $count > 5 ) {
    return new WP_Error( 'rate_limited', 'Too many requests', [ 'status' => 429 ] );
}

set_transient( $key, $count + 1, 60 );
```

### 2. Request Validation
Validate incoming requests:
```php
// Check User-Agent contains plugin signature
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if ( strpos( $user_agent, 'GHL-CRM-Integration' ) === false ) {
    return new WP_Error( 'invalid_client', 'Unauthorized', [ 'status' => 401 ] );
}
```

### 3. Logging
Log all proxy requests for debugging and security monitoring:
```php
error_log( sprintf(
    '[GHL-Proxy] %s request from %s - Status: %d',
    $endpoint,
    $_SERVER['REMOTE_ADDR'],
    $status_code
) );
```

### 4. Store Client Secret Securely
Never hardcode in files. Use WordPress options or environment variables:
```php
// In wp-config.php or .env
define( 'GHL_OAUTH_CLIENT_SECRET', '17bd923c-13df-4198-8f78-0675a4b2e99a' );

// In plugin
private const CLIENT_SECRET = GHL_OAUTH_CLIENT_SECRET;
```

---

## Testing the Proxy

Test each endpoint with cURL:

```bash
# Test exchange token
curl -X POST https://labgenz.com/wp-json/ghl-proxy/v1/exchange-token \
  -H "Content-Type: application/json" \
  -d '{"code":"test_code","redirect_uri":"https://labgenz.com/wp-json/ghl/v1/callback"}'

# Test refresh token
curl -X POST https://labgenz.com/wp-json/ghl-proxy/v1/refresh-token \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"def50200..."}'

# Test reconnect
curl -X POST https://labgenz.com/wp-json/ghl-proxy/v1/reconnect \
  -H "Content-Type: application/json" \
  -d '{"location_id":"xyz123"}'
```

---

## Monitoring

Set up monitoring for:
- Request volume and response times
- Error rates and types
- Failed authentication attempts
- GoHighLevel API status

---

## Maintenance

### Rotating Client Secret
1. Generate new secret in GoHighLevel
2. Update on labgenz.com proxy
3. No plugin code changes needed
4. Existing users automatically use new secret on next refresh

### Debugging
Enable detailed logging temporarily:
```php
add_filter( 'ghl_proxy_debug_mode', '__return_true' );
```

---

## Support

For issues with the proxy setup, contact:
- **Email:** yahyadard@gmail.com
- **Website:** https://labgenz.com/
