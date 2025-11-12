# CLAUDE.md

This file provides guidance to Claude Code when working with the AI HTTP Client library codebase.

## Project Overview

The AI HTTP Client is a WordPress library providing unified AI provider communication. It serves as the centralized AI interface for multiple WordPress plugins in Chris Huber's development environment.

## Architecture Principles

### Filter-Based WordPress Integration
- All functionality exposed through WordPress filters for maximum extensibility
- Self-contained provider classes that handle format conversion internally
- No external dependencies beyond WordPress core functions
- WordPress-native patterns for HTTP, caching, and options
- Library provides 0 admin components - all configuration managed via REST API endpoints

### Provider Architecture Standards
- Each provider is a self-contained PHP class with unified interface
- Providers self-register via `ai_providers` filter in their own files
- Standard format in → Provider format → API → Provider format → Standard format out
- All providers support: `request()`, `streaming_request()`, `get_normalized_models()`, `is_configured()`
- Provider classes handle their own format validation and error handling

### Unified Interface Requirements
- All requests use standard format: `['messages' => [['role' => 'user', 'content' => 'text']], 'model' => 'model-name']`
- All responses use standard format: `['success' => bool, 'data' => [...], 'error' => null|string, 'provider' => 'name']`
- Provider parameter is required in `ai_request` filter calls
- Multi-modal content supported via Files API integration

### Performance and Caching
- Model lists cached for 24 hours using WordPress transients
- Cache keys include provider name and API key hash for isolation
- Enhanced caching system with critical fixes and performance optimizations
- Granular cache clearing via action hooks: `ai_clear_model_cache`, `ai_clear_all_cache`
- HTTP requests use appropriate timeouts (120 seconds for AI operations)

## Core Components

### Providers (src/Providers/)
- **OpenAI**: Responses API integration, native Files API, function calling, streaming
- **Anthropic**: Claude models, native Files API with vision, streaming, function calling
- **Gemini**: Google AI models, native Files API with multi-modal support, streaming, function calling
- **Grok**: X.AI integration, streaming support
- **OpenRouter**: Gateway to 200+ models

### Filters System (src/Filters/)
- **Requests.php**: Main `ai_request` filter pipeline with error handling
- **Models.php**: Model fetching and caching via `ai_models` filter
- **RestApi.php**: REST API endpoints for configuration and management
- **Tools.php**: AI tools registration and discovery

### Actions System (src/Actions/)
- **Cache.php**: Model cache management with WordPress transients
- **Error.php**: Centralized error logging using WordPress action hooks (`ai_api_error`, `ai_library_error`)

## Development Standards

### Provider Implementation Requirements
```php
class AI_HTTP_NewProvider {
    private $api_key;
    private $base_url;

    public function __construct($config = []) {
        // Initialize with configuration
        $this->api_key = $config['api_key'] ?? '';
        $this->base_url = $config['base_url'] ?? 'https://api.provider.com';
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    public function request($standard_request) {
        // Convert standard → provider format → API → standard format
        $provider_request = $this->format_request($standard_request);
        $raw_response = $this->call_api($provider_request);
        return $this->format_response($raw_response);
    }

    public function streaming_request($standard_request, $callback = null) {
        // Similar pattern for streaming requests
    }

    public function get_normalized_models() {
        // Return ['model_id' => 'display_name'] array
    }
}

// Self-register the provider
add_filter('ai_providers', function($providers) {
    $providers['newprovider'] = [
        'class' => 'AI_HTTP_NewProvider',
        'type' => 'llm',
        'name' => 'New Provider'
    ];
    return $providers;
});
```

### Error Handling Requirements
- All providers trigger error events via `AIHttpError::trigger_api_error()` on API failures
- Library components trigger error events via `AIHttpError::trigger_library_error()` for internal errors
- WordPress action hooks `ai_api_error` and `ai_library_error` enable plugins to monitor failures
- Validate all input parameters before API calls
- Sanitize all user input using WordPress functions

**Error Action Hook Usage:**
```php
// Plugins can hook into error events
add_action('ai_api_error', function($error_data) {
    error_log(sprintf(
        'AI API Error: %s failed at %s - %s',
        $error_data['provider'],
        $error_data['endpoint'],
        wp_json_encode($error_data['response'])
    ));
});

add_action('ai_library_error', function($error_data) {
    error_log(sprintf(
        'AI Library Error in %s: %s',
        $error_data['component'],
        $error_data['message']
    ));
});
```

### Code Organization
- One provider per file in src/Providers/
- Filter registration grouped by functionality in src/Filters/
- WordPress action hooks in src/Actions/
- Template files in src/templates/
- No external composer dependencies - WordPress-native only

## Integration Patterns

### Multi-Plugin Support
- Shared API key storage via `ai_provider_api_keys` filter
- Multisite network-wide API key storage support via `get_site_option`/`update_site_option`
- Plugin-isolated configurations possible via namespaced filters
- No provider conflicts due to self-contained architecture

### Filter Usage Patterns
```php
// Get all providers
$providers = apply_filters('ai_providers', []);

// Make AI request (provider required)
$response = apply_filters('ai_request', $request, 'openai');

// Get models with caching
$models = apply_filters('ai_models', 'openai', $config);

// Convert file to base64
$base64 = apply_filters('ai_file_to_base64', '', $file_path, $options);
```

### REST API Usage Patterns
```php
// Configure API keys via REST API
wp_remote_post('/wp-json/ai-http-client/v1/config', [
    'body' => wp_json_encode([
        'provider' => 'openai',
        'api_key' => 'your-api-key'
    ]),
    'headers' => [
        'Content-Type' => 'application/json',
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);

// Get provider configuration
$config = wp_remote_get('/wp-json/ai-http-client/v1/config/openai', [
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);

// Test provider connectivity
$test = wp_remote_post('/wp-json/ai-http-client/v1/test/openai', [
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);
```

### Action Hook Patterns
```php
// Monitor API failures across all providers
add_action('ai_api_error', function($error_data) {
    // $error_data: ['provider', 'endpoint', 'response', 'context', 'timestamp']
    // Implement custom error handling, logging, or notifications
});

// Monitor library-level errors
add_action('ai_library_error', function($error_data) {
    // $error_data: ['component', 'message', 'context', 'timestamp']
    // Implement custom error handling for cache, file operations, etc.
});

// Clear model cache for specific provider
do_action('ai_clear_model_cache', 'openai');

// Clear all provider model caches
do_action('ai_clear_all_cache');
```

## REST API Endpoints

The library provides REST API endpoints for configuration and management, replacing traditional admin interfaces:

### Configuration Endpoints
- **GET/POST** `/wp-json/ai-http-client/v1/config` - Get/set provider configurations
- **GET/POST** `/wp-json/ai-http-client/v1/config/{provider}` - Get/set specific provider config
- **DELETE** `/wp-json/ai-http-client/v1/config/{provider}` - Remove provider configuration

### Testing Endpoints
- **POST** `/wp-json/ai-http-client/v1/test/{provider}` - Test provider connectivity and configuration
- **GET** `/wp-json/ai-http-client/v1/providers` - List all available providers with status

### Management Endpoints
- **POST** `/wp-json/ai-http-client/v1/cache/clear` - Clear all model caches
- **POST** `/wp-json/ai-http-client/v1/cache/clear/{provider}` - Clear specific provider cache

### Security
- All endpoints require WordPress REST API authentication
- Nonce verification for state-changing operations
- Capability checks: `manage_options` for configuration, `read` for status queries
- API keys stored securely using WordPress options API

### WordPress Integration Standards
- Use `wp_remote_*` functions for HTTP requests
- Use WordPress transients for caching with HOUR_IN_SECONDS constants
- Use WordPress options API for persistent settings
- Use WordPress REST API nonces for endpoint security
- Use WordPress capability checks for REST API access

## Critical Implementation Notes

### Security Requirements
- All user input must be sanitized using WordPress sanitization functions
- API keys stored in WordPress options with appropriate capability checks
- File uploads validated for type and size before processing
- All output escaped using WordPress escaping functions

### WordPress Compatibility
- Minimum PHP 7.4 requirement
- WordPress 5.0+ compatibility required
- No usage of deprecated WordPress functions
- Follow WordPress coding standards for PHP, JavaScript, and CSS

### Performance Considerations
- Model caching prevents repeated API calls
- HTTP timeouts set appropriately for AI operations (120 seconds)
- Streaming support for real-time responses
- Efficient transient cleanup for cache management

## Testing and Validation

### Provider Testing Requirements
- Each provider must handle API failures gracefully
- Test with invalid API keys and malformed responses
- Verify standard format conversion accuracy
- Test streaming functionality where supported
- Test Files API integration and file upload/delete operations

### Integration Testing
- Multi-plugin installation scenarios
- API key sharing across plugins with multisite support
- Cache behavior with multiple providers
- Enhanced error handling across filter pipeline
- Files API callback integration and file lifecycle management

## Library Usage Context

This library is the foundation for AI functionality across multiple WordPress plugins:
- **Data Machine**: Content processing pipelines
- **WordSurf**: AI content editor
- **AI Bot for bbPress**: Forum responses

All implementations must maintain backward compatibility and follow the established patterns to ensure seamless integration across the plugin ecosystem.

## Version 1.2.0 Updates

**New Features:**
- Native Files API integration for OpenAI, Anthropic, and Gemini providers
- Multisite network-wide API key storage support
- Enhanced error handling with comprehensive action hooks
- Improved model caching system with granular cache management
- REST API endpoints replacing admin components for configuration and management

**Breaking Changes:**
- Removed admin components, jQuery, AJAX, and provider manager UI
- All configuration now handled via REST API endpoints
- Admin.php filter replaced with RestApi.php for endpoint registration

**Files API Integration:**
- Automatic file upload and management via provider-specific Files APIs
- Multi-modal content support (images, documents, files)
- Seamless integration with existing request/response formats
- File lifecycle management with upload/delete operations