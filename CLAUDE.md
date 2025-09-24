# CLAUDE.md

This file provides guidance to Claude Code when working with the AI HTTP Client library codebase.

## Project Overview

The AI HTTP Client is a WordPress library providing unified AI provider communication. It serves as the centralized AI interface for multiple WordPress plugins in Chris Huber's development environment.

## Architecture Principles

### Filter-Based WordPress Integration
- All functionality exposed through WordPress filters for maximum extensibility
- Self-contained provider classes that handle format conversion internally
- No external dependencies beyond WordPress core functions
- WordPress-native patterns for HTTP, caching, options, and admin interfaces

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
- Granular cache clearing via action hooks: `ai_clear_model_cache`, `ai_clear_all_cache`
- HTTP requests use appropriate timeouts (120 seconds for AI operations)

## Core Components

### Providers (src/Providers/)
- **OpenAI**: Responses API integration, Files API, function calling, streaming
- **Anthropic**: Claude models, streaming, function calling
- **Gemini**: Google AI models, multi-modal support
- **Grok**: X.AI integration
- **OpenRouter**: Gateway to 200+ models

### Filters System (src/Filters/)
- **Requests.php**: Main `ai_request` filter pipeline with error handling
- **Models.php**: Model fetching and caching via `ai_models` filter
- **Admin.php**: UI components and API key management
- **Tools.php**: AI tools registration and discovery

### Actions System (src/Actions/)
- **Cache.php**: Model cache management with WordPress transients
- **Error.php**: Centralized error logging and handling

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
- All providers must use try/catch blocks and throw exceptions for API failures
- Use `ai_api_error` action hook for centralized error logging
- Validate all input parameters before API calls
- Sanitize all user input using WordPress functions

### Code Organization
- One provider per file in src/Providers/
- Filter registration grouped by functionality in src/Filters/
- WordPress action hooks in src/Actions/
- Template files in src/templates/
- No external composer dependencies - WordPress-native only

## Integration Patterns

### Multi-Plugin Support
- Shared API key storage via `ai_provider_api_keys` filter
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

// Render admin component
echo apply_filters('ai_render_component', '', $template_config);

// Convert file to base64
$base64 = apply_filters('ai_file_to_base64', '', $file_path, $options);
```

### WordPress Integration Standards
- Use `wp_remote_*` functions for HTTP requests
- Use WordPress transients for caching with HOUR_IN_SECONDS constants
- Use WordPress options API for persistent settings
- Use WordPress nonces for admin form security
- Use WordPress capability checks for admin access

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

### Integration Testing
- Multi-plugin installation scenarios
- API key sharing across plugins
- Cache behavior with multiple providers
- Error handling across filter pipeline

## Library Usage Context

This library is the foundation for AI functionality across multiple WordPress plugins:
- **Data Machine**: Content processing pipelines
- **WordSurf**: AI content editor
- **AI Bot for bbPress**: Forum responses

All implementations must maintain backward compatibility and follow the established patterns to ensure seamless integration across the plugin ecosystem.