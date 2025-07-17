# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

AI HTTP Client - WordPress library for unified AI provider communication.

## Core Architecture

### Distribution Pattern
- **Production**: Git subtree in plugin's `/lib/ai-http-client/`
- **Integration**: `require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';`

### Modular Pipeline (Single Responsibility)
1. **AI_HTTP_Client** - Pure orchestrator
2. **ProviderRegistry** - Auto-discovers providers
3. **ProviderFactory** - Creates provider instances via DI
4. **RequestNormalizer** - Standard → Provider format
5. **ResponseNormalizer** - Provider → Standard format
6. **Provider Classes** - Handle specific AI service communication

### Provider Structure (5 Required Components)
Each provider in `src/Providers/{ProviderName}/`:
- `Provider.php` - Main API communication
- `StreamingModule.php` - SSE streaming
- `FunctionCalling.php` - Tool calling
- `RequestNormalizer.php` - Request transformation
- `ResponseNormalizer.php` - Response transformation

## Standardized Data Formats

### Request Format
```php
[
    'messages' => [['role' => 'user|assistant|system', 'content' => 'text']],
    'model' => 'provider-model-name',
    'max_tokens' => 1000,
    'temperature' => 0.7,
    'stream' => false
]
```

### Response Format
```php
[
    'success' => true|false,
    'data' => [
        'content' => 'response text',
        'usage' => ['prompt_tokens' => X, 'completion_tokens' => Y],
        'model' => 'actual-model-used'
    ],
    'error' => null|'error message',
    'provider' => 'openai|anthropic|etc'
]
```

## Key Components

- **AI_HTTP_Client** - Main orchestrator (src/class-client.php)
- **ProviderRegistry** - Auto-discovers providers (src/Providers/ProviderRegistry.php)
- **ProviderFactory** - Creates provider instances (src/Providers/ProviderFactory.php)
- **OptionsManager** - WordPress options storage (src/OptionsManager.php)
- **ProviderManagerComponent** - Complete admin UI (src/Components/ProviderManagerComponent.php)

## Adding New Providers

1. Create `src/Providers/ProviderName/` directory
2. Implement 5 required components (Provider, Streaming, FunctionCalling, Request/ResponseNormalizer)
3. Add to `ai-http-client.php` loader
4. **Auto-discovered by ProviderRegistry**

## Continuation Support (Agentic Systems)

```php
// OpenAI: Use response ID from Responses API
$response_id = $client->get_last_response_id();
$continuation = $client->continue_with_tool_results($response_id, $tool_results);

// Anthropic: Use conversation history
$continuation = $client->continue_with_tool_results($conversation_history, $tool_results, 'anthropic');
```

## WordPress Integration

- **HTTP API**: `wp_remote_post()` for requests, cURL for streaming
- **Options**: Single `ai_http_client_providers` option stores all settings
- **Security**: WordPress sanitization functions
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

// 3. Send request
$client = new AI_HTTP_Client();
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'Hello AI!']],
    'max_tokens' => 100
]);
```

## Critical Rules

- **Never hardcode model names** - Fetch dynamically from provider APIs
- **Single Responsibility** - Each class handles one concern
- **Provider-agnostic core** - No provider-specific logic in core classes
- **WordPress-native** - Use WordPress APIs and security practices
- **Modular providers** - Exactly 5 components per provider

## Supported Providers

1. **OpenAI** - GPT models, Responses API, streaming, function calling, vision
2. **Anthropic** - Claude models, streaming, function calling, vision
3. **Google Gemini** - 2025 API, streaming, function calling, multi-modal
4. **Grok/X.AI** - All models, reasoning_effort parameter
5. **OpenRouter** - 100+ models, provider routing, fallbacks

## Development Notes

- **No build system** - Pure PHP library
- **Manual testing** - Requires WordPress environment
- **Version checking** - Prevents conflicts when multiple plugins include library
- **Auto-discovery** - New providers automatically registered
- **Dependency injection** - All components accept dependencies for testability

## Development Commands

### Testing
- No automated test framework configured
- Testing requires WordPress environment with library loaded
- Use the TestConnection component (`src/Components/Extended/TestConnection.php`) for provider connectivity testing
- Test by requiring `ai-http-client.php` and using `AI_HTTP_Client` class directly

### Development Workflow
```php
// Basic testing setup in WordPress
require_once 'ai-http-client.php';
$client = new AI_HTTP_Client();
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'test']],
    'model' => 'gpt-4o-mini'
]);
var_dump($response);
```

### Version Management
- Version defined in `ai-http-client.php` line 24: `AI_HTTP_CLIENT_VERSION`
- Multiple plugins can include different versions safely
- Highest version wins via `ai_http_client_version_check()`

### Provider Development
- Each provider requires exactly 5 components in `src/Providers/{ProviderName}/`
- Add new providers to loader in `ai-http-client.php` lines 87-126
- Auto-discovery happens via `ProviderRegistry::discover_providers()`