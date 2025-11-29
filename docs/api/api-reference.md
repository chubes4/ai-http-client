# API Reference

Complete reference of all filters, actions, functions, and endpoints available in the AI HTTP Client library.

## Core Filters

### chubes_ai_request
Primary filter for making AI requests.

**Parameters:**
- `$request` (array): Unified request format
- `$provider_name` (string): Provider identifier
- `$streaming_callback` (callable, optional): Streaming callback function
- `$tools` (array, optional): Tools/functions array
- `$conversation_data` (array, optional): Conversation continuation data

**Returns:** Array with success status, data, error, and provider info

### chubes_ai_models
Retrieves available models for a provider with caching.

**Parameters:**
- `$provider_name` (string): Provider identifier
- `$provider_config` (array, optional): Provider configuration

**Returns:** Associative array mapping model IDs to display names

### chubes_ai_provider_api_keys
Manages shared API key storage.

**Parameters:**
- `$keys` (array|null): API keys array to set, or null to get

**Returns:** Array of all stored API keys

### chubes_ai_providers
Retrieves list of available providers.

**Returns:** Array of provider configurations

### chubes_ai_file_to_base64
Converts files to base64 data URLs.

**Parameters:**
- `$default` (string): Default return value
- `$file_path` (string): Path to file to convert
- `$options` (array, optional): Conversion options

**Returns:** Base64 data URL string

### chubes_ai_http
Internal HTTP request handling.

**Parameters:**
- `$default` (mixed): Default return value
- `$method` (string): HTTP method
- `$url` (string): Request URL
- `$args` (array): Request arguments
- `$context` (string): Context description
- `$streaming` (bool, optional): Whether streaming request
- `$callback` (callable, optional): Streaming callback

**Returns:** HTTP response array

## Action Hooks

### chubes_ai_clear_model_cache
Clear model cache for specific provider.

**Parameters:**
- `$provider` (string): Provider name

### chubes_ai_clear_all_cache
Clear all model caches.

### chubes_ai_library_error
Unified error event for all library errors.

**Parameters:**
- `$error_data` (array): Structured error information

### chubes_ai_http_client_loaded
Fired when library is fully loaded.

### chubes_ai_model_cache_cleared
Fired after model cache is cleared for a provider.

**Parameters:**
- `$provider` (string): Provider name

### chubes_ai_all_model_cache_cleared
Fired after all model caches are cleared.

### chubes_ai_http_cleanup_old_keys
Manual trigger for cleaning up old API keys.

## REST API Endpoints

### GET /wp-json/ai-http-client/v1/api-keys/{provider}
Get API key for a provider.

**Parameters:**
- `provider` (string): Provider name

**Returns:** API key data

### POST /wp-json/ai-http-client/v1/api-keys/{provider}
Save API key for a provider.

**Parameters:**
- `provider` (string): Provider name
- `api_key` (string): API key value

**Returns:** Success confirmation

### GET /wp-json/ai-http-client/v1/models/{provider}
Get available models for a provider.

**Parameters:**
- `provider` (string): Provider name

**Returns:** Models array

### GET /wp-json/ai-http-client/v1/providers
Get list of available providers.

**Returns:** Providers array

## Provider Classes

### AI_HTTP_OpenAI_Provider
OpenAI provider implementation.

**Methods:**
- `request($request)`: Make standard request
- `streaming_request($request, $callback)`: Make streaming request
- `get_normalized_models()`: Get available models
- `upload_file($file_path, $purpose)`: Upload file to Files API
- `delete_file($file_id)`: Delete file from Files API

### AI_HTTP_Anthropic_Provider
Anthropic provider implementation.

**Methods:** Same as OpenAI provider

### AI_HTTP_Gemini_Provider
Gemini provider implementation.

**Methods:** Same as OpenAI provider

### AI_HTTP_Grok_Provider
Grok provider implementation.

**Methods:** Same as OpenAI provider (except file operations)

### AI_HTTP_OpenRouter_Provider
OpenRouter provider implementation.

**Methods:** Same as OpenAI provider (except file operations)

## Helper Functions

### ai_http_create_provider($provider_name, $config)
Create provider instance.

**Parameters:**
- `$provider_name` (string): Provider identifier
- `$config` (array): Provider configuration

**Returns:** Provider instance or false

### ai_http_create_error_response($message, $provider)
Create standardized error response.

**Parameters:**
- `$message` (string): Error message
- `$provider` (string, optional): Provider name

**Returns:** Error response array

### ai_http_upload_file_to_provider($file_path, $purpose, $provider_name, $config)
Upload file to provider Files API.

**Parameters:**
- `$file_path` (string): Path to file
- `$purpose` (string): Upload purpose
- `$provider_name` (string): Provider name
- `$provider_config` (array): Provider configuration

**Returns:** File ID from provider

### ai_http_generate_cache_key($provider_name, $api_key)
Generate secure cache key.

**Parameters:**
- `$provider_name` (string): Provider name
- `$api_key` (string): API key

**Returns:** Cache key string

### ai_http_get_tools($category)
Get registered AI tools.

**Parameters:**
- `$category` (string, optional): Tool category filter

**Returns:** Tools array

## Constants

### AI_HTTP_CLIENT_VERSION
Current library version.

### AI_HTTP_CLIENT_PATH
Absolute path to library directory.

### AI_HTTP_CLIENT_URL
URL to library directory.

### AIHttpCache::MODEL_CACHE_PREFIX
Cache key prefix for model caching.

### AIHttpCache::CACHE_TTL
Cache time-to-live (24 hours).

## Request/Response Formats

### Unified Request Format
```php
[
    'messages' => [
        ['role' => 'user', 'content' => 'text']
    ],
    'model' => 'model-name',
    'max_tokens' => 1000,
    'temperature' => 0.7,
    'tools' => [...],
    'system' => 'system message'
]
```

### Unified Response Format
```php
[
    'success' => true,
    'data' => [
        'content' => 'response text',
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30
        ],
        'model' => 'model-name',
        'finish_reason' => 'stop',
        'tool_calls' => [...]
    ],
    'error' => null,
    'provider' => 'provider-name'
]
```

## Error Types

- `connection_error`: Network connectivity issues
- `api_error`: Provider API returned error
- `validation_error`: Input validation failures
- `curl_error`: cURL-specific errors
- `http_error`: HTTP protocol errors

## File Types Supported

### OpenAI
- Images, documents, various formats

### Anthropic
- Images: JPEG, PNG, GIF, WebP
- Documents: PDF, plain text

### Gemini
- Images, audio, video, documents (extensive support)

## Cache Configuration

- **TTL**: 24 hours
- **Key Format**: `chubes_ai_models_{provider}_{api_key_hash}`
- **Isolation**: Per-API-key caching
- **Cleanup**: Automatic expiration