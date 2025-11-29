# REST API Filter

REST API endpoints for AI provider management and configuration, replacing traditional admin interfaces.

## Endpoints

### GET/POST /wp-json/ai-http-client/v1/api-keys/{provider}

Manage API keys for specific providers.

#### GET Request
```http
GET /wp-json/ai-http-client/v1/api-keys/openai
Authorization: Bearer {wp_nonce}
```

#### Response
```json
{
    "success": true,
    "data": {
        "provider": "openai",
        "api_key": "sk-..."
    }
}
```

#### POST Request
```http
POST /wp-json/ai-http-client/v1/api-keys/openai
Content-Type: application/json
Authorization: Bearer {wp_nonce}

{
    "api_key": "sk-new-api-key"
}
```

#### Response
```json
{
    "success": true,
    "message": "API key saved successfully",
    "data": {
        "provider": "openai"
    }
}
```

### GET /wp-json/ai-http-client/v1/models/{provider}

Retrieve available models for a provider.

#### Request
```http
GET /wp-json/ai-http-client/v1/models/openai
Authorization: Bearer {wp_nonce}
```

#### Response
```json
{
    "success": true,
    "data": {
        "gpt-4o": "GPT-4o",
        "gpt-4o-mini": "GPT-4o Mini"
    }
}
```

### GET /wp-json/ai-http-client/v1/providers

List all available providers.

#### Request
```http
GET /wp-json/ai-http-client/v1/providers
Authorization: Bearer {wp_nonce}
```

#### Response
```json
{
    "success": true,
    "data": {
        "openai": {
            "class": "AI_HTTP_OpenAI_Provider",
            "type": "llm",
            "name": "OpenAI"
        },
        "anthropic": {
            "class": "AI_HTTP_Anthropic_Provider",
            "type": "llm",
            "name": "Anthropic"
        }
    }
}
```

## Authentication

All endpoints require:
- WordPress REST API authentication
- Valid nonce in `X-WP-Nonce` header
- `manage_options` capability

## Error Responses

Standard error format for all endpoints:

```json
{
    "code": "rest_forbidden",
    "message": "Insufficient permissions",
    "data": {
        "status": 403
    }
}
```

## Security Features

- **Permission Checks**: `manage_options` capability required
- **Nonce Verification**: WordPress REST API nonces
- **Input Sanitization**: All inputs sanitized using WordPress functions
- **Error Logging**: Failed operations logged via error action hooks

## Usage Examples

### JavaScript (with WordPress REST API)
```javascript
// Get API key
fetch('/wp-json/ai-http-client/v1/api-keys/openai', {
    method: 'GET',
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(data => console.log(data));

// Save API key
fetch('/wp-json/ai-http-client/v1/api-keys/openai', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        api_key: 'new-api-key'
    })
});
```

### PHP (with wp_remote_*)
```php
// Get API key
$response = wp_remote_get('/wp-json/ai-http-client/v1/api-keys/openai', [
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);

// Save API key
$response = wp_remote_post('/wp-json/ai-http-client/v1/api-keys/openai', [
    'headers' => [
        'Content-Type' => 'application/json',
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ],
    'body' => wp_json_encode([
        'api_key' => 'new-api-key'
    ])
]);
```