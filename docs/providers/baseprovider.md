# BaseProvider Architecture

The `AI_HTTP_BaseProvider` abstract class serves as the foundation for all AI provider implementations in the AI HTTP Client library. Introduced in version 2.0.6, it centralizes common functionality including validation, sanitization, HTTP patterns, and Files API operations.

## Architecture Overview

All provider classes (`AI_HTTP_OpenAI`, `AI_HTTP_Anthropic`, `AI_HTTP_Gemini`, `AI_HTTP_Grok`, `AI_HTTP_OpenRouter`) now extend `AI_HTTP_BaseProvider`, ensuring consistent implementation patterns and reducing code duplication by ~1700 lines across the codebase.

## Core Responsibilities

### Abstract Methods (Provider-Specific Implementation)

Each provider must implement these abstract methods:

```php
abstract protected function get_default_base_url();
abstract protected function get_auth_headers();
abstract protected function get_provider_name();
abstract protected function format_request($unified_request);
abstract protected function format_response($raw_response);
abstract protected function normalize_models_response($raw_models);
abstract protected function get_chat_endpoint();
abstract protected function get_models_endpoint();
```

### Centralized Functionality

#### Request Processing
- **Validation**: Unified input validation and sanitization
- **HTTP Handling**: Centralized `wp_remote_*` calls via `chubes_ai_http` filter
- **Error Management**: Consistent error triggering and context logging
- **Streaming Support**: Built-in streaming infrastructure with callback handling

#### File Operations
- **Upload Management**: Multipart form-data handling for file uploads
- **Delete Operations**: Standardized file deletion across providers
- **Callback Integration**: Support for Files API callbacks when available

#### Model Management
- **Caching Integration**: Seamless integration with WordPress transients
- **Normalization**: Consistent model format across all providers
- **Error Handling**: Robust error handling for API failures

## Provider Implementation Pattern

```php
class AI_HTTP_OpenAI extends AI_HTTP_BaseProvider {

    protected function get_default_base_url() {
        return 'https://api.openai.com/v1';
    }

    protected function get_auth_headers() {
        return [
            'Authorization' => 'Bearer ' . $this->api_key
        ];
    }

    protected function get_provider_name() {
        return 'OpenAI';
    }

    protected function format_request($unified_request) {
        // Convert unified format to OpenAI-specific format
        return $openai_request;
    }

    protected function format_response($raw_response) {
        // Convert OpenAI response to unified format
        return $unified_response;
    }

    protected function normalize_models_response($raw_models) {
        // Convert OpenAI models API to normalized format
        return $normalized_models;
    }

    protected function get_chat_endpoint() {
        return '/chat/completions';
    }

    protected function get_models_endpoint() {
        return '/models';
    }
}
```

## Key Features

### Validation & Sanitization
- **Input Validation**: Ensures request structure meets requirements
- **WordPress Sanitization**: Uses `sanitize_text_field()` for user inputs
- **File Validation**: Checks file existence before upload operations

### HTTP Abstraction
- **Unified HTTP Layer**: All requests flow through `chubes_ai_http` filter
- **Timeout Management**: Appropriate timeouts for AI operations (120 seconds)
- **Error Context**: Rich error information for debugging

### Files API Integration
- **Multipart Uploads**: Handles complex multipart form-data construction
- **Provider-Specific Endpoints**: Configurable file endpoints per provider
- **Callback Support**: Optional callback system for advanced file handling

### Streaming Infrastructure
- **Callback System**: Supports real-time streaming with custom callbacks
- **Response Building**: Standardized streaming response format
- **Error Handling**: Graceful failure handling in streaming contexts

## Benefits

### Code Consistency
- **Unified Patterns**: All providers follow identical implementation patterns
- **Reduced Duplication**: Common functionality centralized in base class
- **Maintainability**: Changes to common logic affect all providers automatically

### Reliability
- **Centralized Error Handling**: Consistent error management across providers
- **Input Validation**: Robust validation prevents malformed requests
- **Sanitization**: WordPress-native sanitization ensures security

### Performance
- **Efficient HTTP**: Optimized HTTP request patterns
- **Caching Integration**: Seamless model caching integration
- **Resource Management**: Proper cleanup and timeout handling

### Extensibility
- **Easy Provider Addition**: New providers inherit all base functionality
- **Hook Integration**: Full WordPress filter/action system integration
- **Callback Support**: Flexible file handling through callback system

## Migration Impact

The BaseProvider architecture introduced in v2.0.6 maintains 100% backward compatibility while providing architectural improvements:

- **No Functional Changes**: All AI provider functionality remains identical
- **Performance Improvements**: Better error handling and request processing
- **Code Quality**: ~1700 lines of duplicated code eliminated
- **Future-Proof**: Easier to add new providers and features

## Integration Points

### WordPress Filters
- **`chubes_ai_http`**: Centralized HTTP request handling
- **`chubes_ai_library_error`**: Unified error logging
- **`chubes_ai_providers`**: Provider registration system

### WordPress Actions
- **`chubes_ai_clear_model_cache`**: Cache clearing for specific providers
- **`chubes_ai_clear_all_cache`**: Global cache clearing
- **`chubes_ai_http_client_loaded`**: Library initialization notification

### Configuration
- **API Keys**: Retrieved via `chubes_ai_provider_api_keys` filter
- **Base URLs**: Configurable per provider instance
- **Files API Callbacks**: Optional advanced file handling</content>
<parameter name="filePath">docs/providers/baseprovider.md