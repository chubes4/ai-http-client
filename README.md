# AI HTTP Client for WordPress

A professional WordPress library for unified AI provider communication. Supports OpenAI, Anthropic, Google Gemini, Grok, and OpenRouter with standardized request/response formats.

**Key Features:**
- WordPress filter-based architecture with self-contained provider classes
- Unified request/response format across all AI providers
- Comprehensive caching system with 24-hour model cache TTL
- Multi-modal support (text, images, files) via Files API integration
- Streaming and standard request modes with proper error handling
- Template-based admin UI components with WordPress-native patterns

## Installation

**Composer** (recommended for standalone use):
```bash
composer require chubes4/ai-http-client
```

**Git Subtree** (recommended for plugin embedding):
```bash
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
```

**Manual Installation**: Download and place in your plugin directory

**Requirements**: PHP 7.4+, WordPress environment

## Usage

**Include Library**:
```php
// Composer: Auto-loads via Composer (no includes needed)

// Git Subtree/Manual: Include in your plugin
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';
```

**Basic Request**:
```php
$response = apply_filters('ai_request', [
    'messages' => [['role' => 'user', 'content' => 'Hello AI!']]
], 'openai'); // Provider name is now required
```

**Advanced Options**:
```php
// Specific provider (required parameter)
$response = apply_filters('ai_request', $request, 'anthropic');

// With streaming callback
$response = apply_filters('ai_request', $request, 'openai', $streaming_callback);

// With function calling tools
$response = apply_filters('ai_request', $request, 'openai', null, $tools);

// With conversation continuation
$response = apply_filters('ai_request', $request, 'openai', null, $tools, $conversation_data);
```

## Providers

Comprehensive AI provider support with dynamic model discovery:

- **OpenAI** - GPT models, OpenAI Responses API, streaming, function calling, Files API upload/delete
- **Anthropic** - Claude models, streaming, function calling, multi-modal content
- **Google Gemini** - Gemini models, streaming, function calling, image processing
- **Grok/X.AI** - Grok models, streaming support
- **OpenRouter** - 200+ models via unified API gateway

## Architecture

- **Filter-Based**: WordPress-native provider registration via `ai_providers` filter
- **Self-Contained**: Each provider handles format conversion internally (standard ↔ provider format)
- **Unified Interface**: All providers accept standard format, return normalized responses
- **WordPress-Native**: Uses wp_remote_* for HTTP, WordPress transients for caching
- **Modular Design**: Provider files self-register, no central coordination needed
- **Error Handling**: Comprehensive error hooks via `ai_api_error` action
- **Performance**: 24-hour model caching with granular cache clearing

### Multi-Plugin Support

- Plugin-isolated configurations via filter-based settings
- Centralized API key storage in `ai_http_shared_api_keys` option
- No provider conflicts through self-contained architecture
- Independent AI settings per consuming plugin

### Core Components

- **Providers**: Self-contained classes with unified interface (OpenAI, Anthropic, Gemini, Grok, OpenRouter)
- **Request Processing**: Complete pipeline via `ai_request` filter with error handling
- **HTTP Layer**: Centralized `ai_http` filter supporting streaming and standard requests
- **Caching System**: Model caching via `AIHttpCache` class with WordPress transients
- **Admin Interface**: Template-based UI via `ai_render_component` filter
- **Error Management**: Centralized logging via `AIHttpError` class

## Core Filters

```php
// Provider Discovery
$providers = apply_filters('ai_providers', []);

// API Keys Management
$keys = apply_filters('ai_provider_api_keys', null); // Get all keys
apply_filters('ai_provider_api_keys', $new_keys);     // Update all keys

// Dynamic Model Fetching (with 24-hour cache)
$models = apply_filters('ai_models', $provider_name, $config);

// AI Tools Registration
$tools = apply_filters('ai_tools', []);

// Template Rendering
echo apply_filters('ai_render_component', '', $config);

// File Operations
$base64 = apply_filters('ai_file_to_base64', '', $file_path, $options);

// HTTP Requests (internal use)
$result = apply_filters('ai_http', [], 'POST', $url, $args, 'Context');
```

## Multi-Plugin Configuration

**Shared API Keys Storage**:
```php
// WordPress option: 'ai_http_shared_api_keys'
$shared_keys = apply_filters('ai_provider_api_keys', null);
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
add_filter('ai_tools', function($tools) {
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
$all_tools = apply_filters('ai_tools', []);

// Pass tools to AI request
$response = apply_filters('ai_request', $request, 'openai', null, $tools);
// Note: Tool execution is handled by consuming plugins
```

## Distribution

- **Packagist**: Available via `composer require chubes4/ai-http-client`
- **GitHub**: https://github.com/chubes4/ai-http-client
- **Version**: 1.0.0 - Professional WordPress library architecture
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
add_filter('ai_providers', function($providers) {
    $providers['myprovider'] = [
        'class' => 'AI_HTTP_MyProvider',
        'type' => 'llm',
        'name' => 'My Provider'
    ];
    return $providers;
});
```

## Version 1.0.0 Features

**Core Architecture**:
- WordPress filter-based provider registration with self-contained classes
- Unified request/response format across all providers
- Comprehensive caching system with 24-hour model cache
- Multi-modal content support (text, images, files)
- Streaming and standard request modes
- Template-based admin UI components

**AI Provider Support**:
- OpenAI Responses API integration with Files API support
- Anthropic Claude models with streaming
- Google Gemini with multi-modal capabilities
- Grok/X.AI integration
- OpenRouter gateway access to 200+ models

**WordPress Integration**:
- Native WordPress HTTP API usage
- WordPress transients for model caching
- WordPress options API for settings
- Action hooks for error handling and cache management

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
- HTTP request/response cycles via `ai_http` filter
- Provider-specific API interactions
- Model caching operations and cache hits/misses
- Streaming request handling
- Error conditions via `ai_api_error` action hook
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
