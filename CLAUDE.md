# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI HTTP Client - WordPress library for unified AI provider communication.

## Core Architecture (Unified)

### Distribution Pattern
- **Production**: Git subtree in plugin's `/lib/ai-http-client/`
- **Integration**: `require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';`

### Unified Architecture
The library uses a fully unified architecture with shared normalizers:

1. **AI_HTTP_Client** - Main orchestrator using unified normalizers
2. **Unified Normalizers** - Shared logic for all providers:
   - `UnifiedRequestNormalizer` - Standard → Provider format conversion
   - `UnifiedResponseNormalizer` - Provider → Standard format conversion  
   - `UnifiedStreamingNormalizer` - Streaming request/response handling
   - `UnifiedToolResultsNormalizer` - Tool results continuation handling
   - `UnifiedConnectionTestNormalizer` - Connection testing logic
   - `UnifiedModelFetcher` - Model fetching for all providers
3. **Simple Providers** - Pure API communication only (no normalization logic)

### Provider Structure (Simplified)
Each provider is now a single file in `src/Providers/`:
- `openai.php` - `AI_HTTP_OpenAI_Provider`
- `anthropic.php` - `AI_HTTP_Anthropic_Provider`
- `gemini.php` - `AI_HTTP_Gemini_Provider`
- `grok.php` - `AI_HTTP_Grok_Provider`
- `openrouter.php` - `AI_HTTP_OpenRouter_Provider`

All normalization logic is centralized in unified normalizers.

## Standardized Data Formats

### Request Format
```php
[
    'messages' => [['role' => 'user|assistant|system', 'content' => 'text']],
    'model' => 'provider-model-name', // Optional - falls back to configured model
    'max_tokens' => 1000,
    'temperature' => 0.7,
    'stream' => false,
    'tools' => [] // Optional tool definitions
]
```

### Response Format
```php
[
    'success' => true|false,
    'data' => [
        'content' => 'response text',
        'usage' => ['prompt_tokens' => X, 'completion_tokens' => Y, 'total_tokens' => Z],
        'model' => 'actual-model-used',
        'finish_reason' => 'stop|length|tool_calls|error',
        'tool_calls' => null|[...], // Present when AI wants to call tools
        'response_id' => 'response-id' // For OpenAI continuation (when available)
    ],
    'error' => null|'error message',
    'provider' => 'openai|anthropic|gemini|grok|openrouter',
    'raw_response' => null|[...] // Present on errors for debugging
]
```

## Key Components

### Core Architecture
- **AI_HTTP_Client** - Main orchestrator (`src/class-client.php`)
- **UnifiedRequestNormalizer** - Converts standard format to any provider format
- **UnifiedResponseNormalizer** - Converts any provider format to standard format
- **UnifiedStreamingNormalizer** - Handles streaming for all providers
- **UnifiedToolResultsNormalizer** - Handles tool results continuation
- **UnifiedConnectionTestNormalizer** - Handles connection testing
- **UnifiedModelFetcher** - Fetches models from all providers

### Provider Classes
- **AI_HTTP_OpenAI_Provider** - Pure OpenAI API communication
- **AI_HTTP_Anthropic_Provider** - Pure Anthropic API communication
- **AI_HTTP_Gemini_Provider** - Pure Google Gemini API communication
- **AI_HTTP_Grok_Provider** - Pure Grok/X.AI API communication
- **AI_HTTP_OpenRouter_Provider** - Pure OpenRouter API communication

### WordPress Integration
- **OptionsManager** - WordPress options storage (`src/Utils/OptionsManager.php`)
- **PromptManager** - Modular prompt building (`src/Utils/PromptManager.php`)
- **ProviderManagerComponent** - Complete admin UI (`src/Components/ProviderManagerComponent.php`)

## Development Commands

### Testing
- No automated test framework configured
- Testing requires WordPress environment with library loaded
- Use the TestConnection component (`src/Components/Extended/TestConnection.php`) for provider connectivity testing

### Development Workflow
```php
// Basic testing setup in WordPress
require_once 'ai-http-client.php';
$client = new AI_HTTP_Client();
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'test']]
    // Model automatically uses configured model from WordPress options
]);
var_dump($response);

// Test streaming
$client->send_streaming_request([
    'messages' => [['role' => 'user', 'content' => 'test']]
    // Model automatically uses configured model
]);

// Test connection
$test_result = $client->test_connection('openai');
var_dump($test_result);

// Get available models
$models = $client->get_available_models('openai');
var_dump($models);

// Override configured model per request
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'test']],
    'model' => 'gpt-4o' // Override configured model
]);
```

### Version Management
- Version defined in `ai-http-client.php` line 24: `AI_HTTP_CLIENT_VERSION`
- Multiple plugins can include different versions safely
- Highest version wins via `ai_http_client_version_check()`

## Adding New Providers

1. Create simple provider class in `src/Providers/` (e.g., `newprovider.php`)
2. Add normalization logic to `UnifiedRequestNormalizer` and `UnifiedResponseNormalizer`
3. Add provider case to `AI_HTTP_Client::get_provider()`
4. Add provider loading to `ai-http-client.php`

### New Provider Template
```php
// src/Providers/newprovider.php
class AI_HTTP_NewProvider_Provider {
    public function __construct($config) { /* ... */ }
    public function send_raw_request($provider_request) { /* ... */ }
    public function send_raw_streaming_request($provider_request, $callback) { /* ... */ }
    public function get_raw_models() { /* ... */ }
    public function is_configured() { /* ... */ }
}
```

## Loading System

The library uses a streamlined loading system in `ai-http-client.php`:
1. Shared utilities (FileUploadClient, ToolExecutor)
2. **Unified Normalizers** - All normalization logic
3. **Simple Providers** - Pure API communication
4. **Main Client** - Unified orchestrator
5. WordPress management components
6. UI Components system

## Critical Rules

- **Use Unified Architecture** - All providers use the same normalizers
- **No Provider-Specific Logic in Core** - All provider differences handled in unified normalizers
- **Single Responsibility** - Providers only handle API communication, normalizers handle format conversion
- **WordPress-Native** - Use WordPress APIs and security practices
- **No Hardcoded Defaults** - All configuration should come from WordPress options

## Supported Providers

All providers are fully refactored and support the complete feature set:

1. **OpenAI** - GPT models, Responses API, streaming, function calling, vision
2. **Anthropic** - Claude models, streaming, function calling, vision
3. **Google Gemini** - 2025 API, streaming, function calling, multi-modal
4. **Grok/X.AI** - All models, reasoning_effort parameter, streaming
5. **OpenRouter** - 100+ models, provider routing, fallbacks

## WordPress Integration

- **HTTP API**: `wp_remote_post()` for requests, cURL for streaming
- **Options**: Single `ai_http_client_providers` option stores all settings
- **Security**: WordPress sanitization functions throughout
- **Filters**: Extensible via WordPress filter system

## Git Subtree Commands

```bash
# Add library to plugin (first time)
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# Update library in plugin
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# Push changes back to library
git subtree push --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main
```

## Quick Integration

```php
// 1. Include library
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';

// 2. Add admin UI
echo AI_HTTP_ProviderManager_Component::render();

// 3. Send request (model automatically uses configured model)
$client = new AI_HTTP_Client();
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'Hello AI!']],
    'max_tokens' => 100
    // Model automatically uses configured model from WordPress options
]);
```

## Continuation Support (Agentic Systems)

```php
// OpenAI: Use response ID from Responses API
$response_id = $client->get_last_response_id();
$continuation = $client->continue_with_tool_results($response_id, $tool_results);

// Anthropic: Use conversation history
$continuation = $client->continue_with_tool_results($conversation_history, $tool_results, 'anthropic');

// All providers support continuation through unified architecture
```

## Error Handling

- **Logging Requirements**: All providers log their raw error responses for debugging
- **Fallback System**: `AI_HTTP_Client` supports automatic fallback to other providers
- **Standardized Errors**: All errors return consistent format via `create_error_response()`
- **WordPress Integration**: Uses WordPress error logging functions

## API-Specific Notes

### OpenAI
- Uses **Responses API** (not Chat Completions)
- Converts `messages` → `input` and `max_tokens` → `max_output_tokens`
- Handles tool calling via flat format (not nested)

### Anthropic
- Extracts system messages from messages array to `system` parameter
- Temperature constrained to 0-1 range

### Google Gemini
- Converts messages to `contents` format with `role` and `parts`
- Uses `generationConfig` for temperature and maxOutputTokens

### Grok/X.AI
- OpenAI-compatible format with optional `reasoning_effort` parameter
- Supports all standard OpenAI features

### OpenRouter
- OpenAI-compatible format with provider routing
- Supports 100+ models from multiple providers