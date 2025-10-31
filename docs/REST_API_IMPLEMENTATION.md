# REST API Implementation

## Overview
The REST API feature allows external services to interact with the GHL CRM Integration plugin through secure, authenticated endpoints.

## Features

### ✅ Implemented

1. **Authentication**
   - Bearer token authentication
   - Secure API key generation
   - Key regeneration capability

2. **Security**
   - IP whitelisting (CIDR support)
   - Rate limiting (configurable requests per minute)
   - Permission callbacks for all endpoints

3. **Endpoints**
   - `/ghl-crm/v1/contacts` - Create/Update contacts
   - `/ghl-crm/v1/sync` - Trigger manual sync
   - `/ghl-crm/v1/status` - Get sync status
   - `/ghl-crm/v1/webhooks` - Receive webhook events

4. **Admin UI**
   - Enable/disable REST API
   - API key management (generate, copy, regenerate)
   - IP whitelist configuration
   - Rate limiting settings
   - Endpoint selection
   - API documentation display

## Architecture

### Classes

#### `RestAPIController` (`src/API/RestAPIController.php`)
Main controller handling REST API routes and authentication.

**Key Methods:**
- `register_routes()` - Register WP REST API routes
- `check_api_permission()` - Validate API key, IP, and rate limits
- `create_or_update_contact()` - Handle contact creation/updates
- `trigger_sync()` - Trigger manual sync operations
- `get_status()` - Return queue status
- `handle_webhook()` - Delegate webhook handling

**Security Features:**
- Bearer token validation
- IP whitelist checking (supports CIDR notation)
- Rate limiting per IP address
- Request tracking via transients

### Settings Structure

```php
[
    'rest_api_enabled' => bool,                    // Master toggle
    'rest_api_key' => string,                      // 32-character API key
    'rest_api_ip_whitelist' => string,             // Newline-separated IPs/CIDRs
    'rest_api_rate_limit' => bool,                 // Enable rate limiting
    'rest_api_requests_per_minute' => int,         // Default: 60
    'rest_api_endpoints' => array,                 // ['contacts', 'sync', 'status', 'webhooks']
]
```

## Usage

### Enable REST API

1. Navigate to **GoHighLevel CRM** → **Settings** → **REST API**
2. Check "Enable REST API endpoints"
3. Copy the generated API key
4. (Optional) Configure IP whitelist
5. (Optional) Adjust rate limiting
6. Select allowed endpoints
7. Click "Save REST API Settings"

### Making API Requests

#### Authentication
All requests must include the Authorization header:

```bash
Authorization: Bearer YOUR_API_KEY
```

#### Base URL
```
https://yoursite.com/wp-json/ghl-crm/v1
```

### Endpoint Examples

#### 1. Create/Update Contact

**Endpoint:** `POST /ghl-crm/v1/contacts`

**Request:**
```bash
curl -X POST https://yoursite.com/wp-json/ghl-crm/v1/contacts \
  -H "Authorization: Bearer your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "first_name": "John",
    "last_name": "Doe"
  }'
```

**Response (201):**
```json
{
  "success": true,
  "message": "User created successfully",
  "user_id": 123
}
```

#### 2. Trigger Manual Sync

**Endpoint:** `POST /ghl-crm/v1/sync`

**Request:**
```bash
curl -X POST https://yoursite.com/wp-json/ghl-crm/v1/sync \
  -H "Authorization: Bearer your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "users"
  }'
```

**Response (200):**
```json
{
  "success": true,
  "message": "Users sync triggered successfully"
}
```

#### 3. Get Sync Status

**Endpoint:** `GET /ghl-crm/v1/status`

**Request:**
```bash
curl -X GET https://yoursite.com/wp-json/ghl-crm/v1/status \
  -H "Authorization: Bearer your_api_key_here"
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "pending": 5,
    "failed": 2,
    "completed_24h": 150,
    "health": "good",
    "rate_limits": {
      "burst": {
        "limit": 100,
        "used": 15,
        "remaining": 85
      },
      "daily": {
        "limit": 200000,
        "used": 1543,
        "remaining": 198457
      }
    }
  }
}
```

#### 4. Webhook Verification

**Endpoint:** `GET /ghl-crm/v1/webhooks`

**Request:**
```bash
curl -X GET https://yoursite.com/wp-json/ghl-crm/v1/webhooks
```

**Response (200):**
```json
{
  "success": true,
  "message": "Webhook endpoint is active"
}
```

## Security Features

### 1. Bearer Token Authentication
- All requests require valid API key
- Keys are 32-character cryptographically secure strings
- Keys can be regenerated at any time

### 2. IP Whitelisting
Configure allowed IP addresses or CIDR ranges:

```
192.168.1.100
10.0.0.0/8
172.16.0.0/12
```

**CIDR Support:**
- Single IPs: `192.168.1.100`
- Class C: `192.168.1.0/24` (256 addresses)
- Class B: `172.16.0.0/16` (65,536 addresses)
- Class A: `10.0.0.0/8` (16,777,216 addresses)

### 3. Rate Limiting
- Per-IP rate limiting
- Configurable requests per minute (default: 60)
- Returns HTTP 429 when exceeded
- Response includes `retry_after`, `limit`, `remaining` headers

### 4. Permission Callbacks
Each endpoint validates:
1. API key present and valid
2. IP address allowed (if whitelist configured)
3. Rate limit not exceeded

## Error Responses

### 401 Unauthorized
```json
{
  "code": "rest_forbidden",
  "message": "Authorization header missing. Include: Authorization: Bearer YOUR_API_KEY",
  "data": {
    "status": 401
  }
}
```

### 403 Forbidden
```json
{
  "code": "rest_forbidden",
  "message": "Invalid API key",
  "data": {
    "status": 403
  }
}
```

### 429 Rate Limit Exceeded
```json
{
  "code": "rate_limit_exceeded",
  "message": "Rate limit exceeded. Maximum 60 requests per minute allowed.",
  "data": {
    "status": 429,
    "retry_after": 60,
    "limit": 60,
    "remaining": 0
  }
}
```

### 400 Bad Request
```json
{
  "code": "missing_email",
  "message": "Email is required",
  "data": {
    "status": 400
  }
}
```

## Best Practices

1. **HTTPS Only**: Always use HTTPS in production
2. **Secure Storage**: Never commit API keys to version control
3. **IP Restrictions**: Use IP whitelisting for production environments
4. **Rate Limiting**: Enable rate limiting to prevent abuse
5. **Key Rotation**: Regenerate API keys regularly
6. **Monitoring**: Check sync logs for API usage patterns
7. **Error Handling**: Implement retry logic with exponential backoff

## Integration with Existing Features

### Webhook System
The REST API webhooks endpoint delegates to the existing `WebhookHandler`:
```php
public function handle_webhook( \WP_REST_Request $request ) {
    $webhook_handler = \GHL_CRM\API\Webhooks\WebhookHandler::get_instance();
    return $webhook_handler->handle_request( $request );
}
```

### Queue System
Contact creation/updates automatically queue sync operations:
```php
$queue_manager = \GHL_CRM\Sync\QueueManager::get_instance();
$queue_manager->add_to_queue( 'user', $user_id, 'user_register', $params );
```

### Settings Manager
All REST API settings stored via `SettingsManager`:
```php
$settings = $this->settings_manager->get_settings_array();
$rest_api_enabled = $settings['rest_api_enabled'] ?? false;
```

## Testing

### Manual Testing
```bash
# Test authentication
curl -X GET https://yoursite.com/wp-json/ghl-crm/v1/status \
  -H "Authorization: Bearer your_key"

# Test rate limiting (run multiple times quickly)
for i in {1..100}; do
  curl -X GET https://yoursite.com/wp-json/ghl-crm/v1/status \
    -H "Authorization: Bearer your_key"
done

# Test IP whitelist (from different IPs)
curl -X GET https://yoursite.com/wp-json/ghl-crm/v1/status \
  -H "Authorization: Bearer your_key"
```

### Expected Behavior
1. ✅ Valid requests succeed with 200/201 status
2. ✅ Missing auth returns 401
3. ✅ Invalid key returns 403
4. ✅ Blocked IP returns 403
5. ✅ Rate limit exceeded returns 429
6. ✅ Invalid endpoints return 404
7. ✅ Disabled endpoints return 404

## Future Enhancements

- [ ] API usage analytics dashboard
- [ ] Multiple API keys with different permissions
- [ ] Webhook signature verification
- [ ] Request/response logging
- [ ] API versioning (v2, v3)
- [ ] GraphQL endpoint
- [ ] OAuth2 authentication option
- [ ] Per-endpoint rate limiting
- [ ] Custom rate limit windows (hourly, daily)
- [ ] API key expiration dates

## Changelog

### Version 1.0.0
- Initial REST API implementation
- Bearer token authentication
- IP whitelisting with CIDR support
- Rate limiting per IP
- Four core endpoints (contacts, sync, status, webhooks)
- Admin UI for configuration
- Integration with existing webhook system
