# AI HTTP Client for WordPress

A professional WordPress library for unified AI provider communication. Supports OpenAI, Anthropic, Google Gemini, Grok, and OpenRouter with standardized request/response formats.

**Key Features:**
- WordPress filter-based architecture with self-contained provider classes
- Unified request/response format across all AI providers
- Comprehensive caching system with 24-hour model cache TTL
- Multi-modal support (text, images, files) via native Files API integration
- Streaming and standard request modes with proper error handling
- REST API endpoints for configuration and management
- Multisite network-wide API key storage support
- Comprehensive error handling with WordPress action hooks

## Installation

**Composer** (recommended for standalone use):
```bash
composer require chubes4/ai-http-client
```

**Git Subtree** (recommended for plugin embedding):
```bash
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
```

**Manual Installation**: Download and include in your WordPress plugin or theme

**Requirements**: PHP 7.4+, WordPress environment

## Upgrading from v1.x to v2.0

**Breaking Changes**: All filter/action hooks renamed from `ai_*` to `chubes_ai_*` for WordPress.org compliance.

### Automatic Migration

API keys are automatically migrated on first admin page load:
- Old option: `ai_http_shared_api_keys`
- New option: `chubes_ai_http_shared_api_keys`
- Old option deleted after 30 days

### Hook Migration

Update all references in your code:

```php
// OLD (v1.x)
apply_filters('ai_providers', [])
apply_filters('ai_provider_api_keys', null)
apply_filters('ai_models', $provider)
apply_filters('ai_tools', [])
apply_filters('ai_request', $request)
apply_filters('ai_file_to_base64', '', $path)
apply_filters('ai_http', [], $method, $url, $args)

// NEW (v2.0)
apply_filters('chubes_ai_providers', [])
apply_filters('chubes_ai_provider_api_keys', null)
apply_filters('chubes_ai_models', $provider)
apply_filters('chubes_ai_tools', [])
apply_filters('chubes_ai_request', $request)
apply_filters('chubes_ai_file_to_base64', '', $path)
apply_filters('chubes_ai_http', [], $method, $url, $args)
```

See [docs/CHANGELOG.md](docs/CHANGELOG.md) for complete migration details.

## Usage

**Include Library**:
```php
// Composer: Auto-loads via Composer (no includes needed)

// Git Subtree/Manual: Include in your plugin
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';
```

**Basic Request**:
```php
$response = apply_filters('chubes_ai_request', [
    'messages' => [['role' => 'user', 'content' => 'Hello AI!']]
], 'openai'); // Provider name is now required
```

**Advanced Options**:
```php
// Specific provider (required parameter)
$response = apply_filters('chubes_ai_request', $request, 'anthropic');

// With streaming callback
$response = apply_filters('chubes_ai_request', $request, 'openai', $streaming_callback);

// With function calling tools
$response = apply_filters('chubes_ai_request', $request, 'openai', null, $tools);

// With conversation continuation
$response = apply_filters('chubes_ai_request', $request, 'openai', null, $tools, $conversation_data);
```

## Providers

Comprehensive AI provider support with dynamic model discovery:

- **OpenAI** - GPT models, OpenAI Responses API, streaming, function calling, native Files API integration
- **Anthropic** - Claude models, streaming, function calling, native Files API integration with vision support
- **Google Gemini** - Gemini models, streaming, function calling, native Files API integration with vision support
- **Grok/X.AI** - Grok models, streaming support
- **OpenRouter** - 200+ models via unified API gateway

## Architecture

- **Filter-Based**: WordPress-native provider registration via `chubes_ai_providers` filter
- **Self-Contained**: Each provider handles format conversion internally (standard ↔ provider format)
- **Unified Interface**: All providers accept standard format, return normalized responses
- **WordPress-Native**: Uses wp_remote_* for HTTP, WordPress transients for caching
- **Modular Design**: Provider files self-register, no central coordination needed
- **Error Handling**: Comprehensive error hook via `chubes_ai_library_error` action
- **Performance**: 24-hour model caching with granular cache clearing

### Multi-Plugin Support

- Plugin-isolated configurations via filter-based settings
- Centralized API key storage in `chubes_ai_http_shared_api_keys` option
- Multisite network-wide API key storage support
- No provider conflicts through self-contained architecture
- Independent AI settings per consuming plugin

### Core Components

- **Providers**: Self-contained classes with unified interface (OpenAI, Anthropic, Gemini, Grok, OpenRouter)
- **Request Processing**: Complete pipeline via `chubes_ai_request` filter with error handling
- **HTTP Layer**: Centralized `chubes_ai_http` filter supporting streaming and standard requests
- **Caching System**: Model caching via `AIHttpCache` class with WordPress transients
- **REST API**: Configuration and management endpoints via `ai_http_client` namespace
- **Error Management**: Centralized logging via `AIHttpError` class

## Core Filters

```php
// Provider Discovery
$providers = apply_filters('chubes_ai_providers', []);

// API Keys Management
$keys = apply_filters('chubes_ai_provider_api_keys', null); // Get all keys
apply_filters('chubes_ai_provider_api_keys', $new_keys);     // Update all keys

// Dynamic Model Fetching (with 24-hour cache)
$models = apply_filters('chubes_ai_models', $provider_name, $config);

// AI Tools Registration
$tools = apply_filters('chubes_ai_tools', []);

// File Operations
$base64 = apply_filters('chubes_ai_file_to_base64', '', $file_path, $options);

// HTTP Requests (internal use)
$result = apply_filters('chubes_ai_http', [], 'POST', $url, $args, 'Context');
```

## REST API Endpoints

The library provides REST API endpoints for configuration and management:

```php
// Configure API keys via REST API
wp_remote_post('/wp-json/ai-http-client/v1/api-keys/openai', [
    'body' => wp_json_encode([
        'api_key' => 'your-api-key'
    ]),
    'headers' => [
        'Content-Type' => 'application/json',
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);

// Get provider configuration
$config = wp_remote_get('/wp-json/ai-http-client/v1/api-keys/openai', [
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);

// Get available models for a provider
$models = wp_remote_get('/wp-json/ai-http-client/v1/models/openai', [
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);

// Get all available providers
$providers = wp_remote_get('/wp-json/ai-http-client/v1/providers', [
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);
```

**Available Endpoints:**
- **GET/POST** `/wp-json/ai-http-client/v1/api-keys/{provider}` - Get/set API key for specific provider
- **GET** `/wp-json/ai-http-client/v1/models/{provider}` - Get available models for a provider
- **GET** `/wp-json/ai-http-client/v1/providers` - List all available providers

## Multi-Plugin Configuration

**Shared API Keys Storage**:
```php
// WordPress option: 'chubes_ai_http_shared_api_keys'
$shared_keys = apply_filters('chubes_ai_provider_api_keys', null);
// Returns: ['openai' => 'sk-...', 'anthropic' => 'sk-ant-...', ...]
```

**Provider Configuration**:
```php
// Each provider accepts configuration in constructor
$provider = new AI_HTTP_OpenAI_Provider([
    'api_key' => 'sk-...',
    'organization' => 'org-...',
    'base_url' => 'https://api.openai.com/v1' // Optional custom endpoint
]);
```

## AI Tools System

**Tool Registration**:
```php
add_filter('chubes_ai_tools', function($tools) {
    $tools['file_processor'] = [
        'class' => 'FileProcessor_Tool',
        'category' => 'file_handling',
        'description' => 'Process files and extract content',
        'parameters' => [
            'file_path' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Path to file to process'
            ]
        ]
    ];
    return $tools;
});
```

**Tool Discovery and Usage**:
```php
// Get all registered tools
$all_tools = apply_filters('chubes_ai_tools', []);

// Pass tools to AI request
$response = apply_filters('chubes_ai_request', $request, 'openai', null, $tools);
// Note: Tool execution is handled by consuming plugins
```

## Distribution

- **Packagist**: Available via `composer require chubes4/ai-http-client`
- **GitHub**: https://github.com/chubes4/ai-http-client
- **Version**: 2.0.7 - Professional WordPress library with BaseProvider architecture
- **License**: GNU GPL v3
- **Dependencies**: None (pure WordPress integration)
- **Multi-plugin**: Safe for concurrent use by multiple WordPress plugins

### Adding Providers

```php
class AI_HTTP_MyProvider {
    public function __construct($config = []) { /* Provider setup */ }
    public function is_configured() { /* Check if ready */ }
    public function request($standard_request) { /* Standard → Provider → Standard */ }
    public function streaming_request($standard_request, $callback) { /* Streaming support */ }
    public function get_normalized_models() { /* Get models for UI */ }
    public function get_raw_models() { /* Get raw API response */ }
}

// Self-register via filter
add_filter('chubes_ai_providers', function($providers) {
    $providers['myprovider'] = [
        'class' => 'AI_HTTP_MyProvider',
        'type' => 'llm',
        'name' => 'My Provider'
    ];
    return $providers;
});
```

## Version 2.0.0 Features

**WordPress.org Compliance**:
- All filter/action hooks renamed from `ai_*` to `chubes_ai_*` prefix
- Automatic migration system for API keys and settings
- Backward compatibility migration for 30-day rollback window

**Core Architecture**:
- WordPress filter-based provider registration with self-contained classes
- Unified request/response format across all providers
- Comprehensive caching system with 24-hour model cache TTL
- Native Files API integration for multi-modal content (text, images, files)
- Streaming and standard request modes with proper error handling
- REST API endpoints for configuration and management
- Multisite network-wide API key storage support

**AI Provider Support**:
- OpenAI Responses API integration with native Files API support
- Anthropic Claude models with dynamic model discovery and native Files API
- Google Gemini with native Files API and multi-modal support
- Grok/X.AI integration with streaming support
- OpenRouter gateway access to 200+ models

**WordPress Integration**:
- Native WordPress HTTP API usage with centralized `chubes_ai_http` filter
- WordPress transients for model caching with granular cache clearing
- WordPress options API for settings with multisite support
- Comprehensive error handling via `chubes_ai_library_error` action hook

## Production Usage

This library is actively used in production WordPress plugins:

- **Data Machine** - AI-powered content processing pipelines with multi-provider support
- **WordSurf** - AI content editor with streaming responses and function calling
- **AI Bot for bbPress** - Forum AI responses with contextual conversation management

## Debug

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**Debug Logging Covers**:
- HTTP request/response cycles via `chubes_ai_http` filter
- Provider-specific API interactions
- Model caching operations and cache hits/misses
- Streaming request handling
- Error conditions via `chubes_ai_library_error` action hook
- File upload operations to provider APIs

## Contributing

Pull requests welcome for:
- Additional AI provider integrations
- Performance optimizations and caching improvements
- WordPress compatibility enhancements
- Template component additions
- Documentation improvements

## License

GNU GPL v3 - **[Chris Huber](https://chubes.net)**
