# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **AI HTTP Client** - a WordPress library for unified AI provider communication. It's designed to be included in WordPress plugins as a standalone component, similar to how Action Scheduler is distributed. The library abstracts multiple AI providers (OpenAI, Anthropic, Google Gemini, Grok/X.AI, OpenRouter) behind a single, standardized interface.

## Core Architecture

### Library Distribution Pattern
- **Development**: Can use symlinks across multiple plugins for local development
- **Production**: Intended to be included via git subtree in each plugin's `/lib/ai-http-client/` directory  
- **Integration**: Single include file (`ai-http-client.php`) loads all modular components
- **Usage**: `require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';`

### "Round Plug" Design Philosophy
The library acts as a **"square box with round plugs"** - any plugin that provides standardized input gets standardized output, regardless of internal AI provider complexity.

**Input Side (Round Plug):** Standardized request format  
**Black Box:** AI HTTP Client handles all provider-specific logic  
**Output Side (Round Plug):** Standardized response format

### Modular Pipeline Architecture
Following **Single Responsibility Principle**, each component has one clear job:

1. **AI_HTTP_Client** - Pure orchestrator, no business logic
2. **ProviderRegistry** - Discovers available providers automatically  
3. **ProviderFactory** - Creates and configures provider instances via dependency injection
4. **RequestNormalizer** - Converts standard input → provider-specific format
5. **ResponseNormalizer** - Converts provider output → standard format
6. **Provider Classes** - Handle only their specific AI service communication

### Key Components

**`AI_HTTP_Client`** (src/class-client.php)
- Pure orchestrator with dependency injection
- Coordinates the modular pipeline: validate → normalize → send → normalize
- Handles fallback logic and error responses
- No provider-specific code

**`AI_HTTP_Provider_Registry`** (src/Providers/ProviderRegistry.php)
- Single responsibility: Discover and register providers
- Auto-scans `/src/Providers/` directory for new implementations
- Singleton pattern for global provider management
- Supports runtime provider registration via filters

**`AI_HTTP_Provider_Factory`** (src/Providers/ProviderFactory.php)  
- Single responsibility: Create configured provider instances
- Uses dependency injection for configuration
- Supports fallback chains and bulk provider creation
- Integrates with WordPress filters for per-provider config

**`AI_HTTP_Normalizer_Factory`** (src/Providers/NormalizerFactory.php)
- Creates provider-specific request and response normalizers
- Follows factory pattern for normalizer instantiation
- Caches normalizer instances for performance

**`AI_HTTP_Options_Manager`** (src/OptionsManager.php)
- Manages WordPress options storage for provider settings
- Stores single nested array option: `ai_http_client_providers`
- Handles provider selection via: `ai_http_client_selected_provider`
- No styling - pure data management

**`AI_HTTP_ProviderManager_Component`** (src/ProviderManagerComponent.php)
- Renders complete provider configuration interface
- Self-contained admin component for provider selection and API keys
- Includes AJAX handlers for dynamic model loading
- **No styles included** - plugin developers handle all CSS
- Single static render method: `AI_HTTP_ProviderManager_Component::render()`

**Provider Implementations** (src/Providers/*)
- Single responsibility: Handle one AI service's communication
- Auto-discovered by ProviderRegistry
- Must extend `AI_HTTP_Provider_Base`
- Must implement: `send_request()`, `get_available_models()`, `is_configured()`
- **Modular Architecture**: Each provider has 5 components:
  - `Provider.php` - Main API communication class
  - `StreamingModule.php` - SSE streaming functionality
  - `FunctionCalling.php` - Tool calling capabilities
  - `RequestNormalizer.php` - Standard→Provider format conversion
  - `ResponseNormalizer.php` - Provider→Standard format conversion

## Standardized Data Formats

### Request Format
```php
[
    'messages' => [
        ['role' => 'user|assistant|system', 'content' => 'text']
    ],
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
    'provider' => 'openai|anthropic|etc',
    'raw_response' => [...]
]
```

## File Structure Conventions

- **Entry Point**: `ai-http-client.php` - loads all modular components in dependency order
- **Core Classes**: `src/class-*.php` - base classes and main orchestrator
- **Management**: `src/OptionsManager.php` - WordPress options storage
- **UI Component**: `src/ProviderManagerComponent.php` - admin interface (no styles)
- **Providers**: `src/Providers/` - provider management utilities and implementations
  - `src/Providers/ProviderRegistry.php` - discovers and manages providers
  - `src/Providers/ProviderFactory.php` - creates provider instances
  - `src/Providers/NormalizerFactory.php` - creates provider-specific normalizers
  - `src/Providers/ModelFetcher.php` - fetches live model lists from APIs
  - `src/Providers/{ProviderName}/` - provider-specific implementations
    - `Provider.php` - main provider class
    - `StreamingModule.php` - SSE streaming functionality  
    - `FunctionCalling.php` - tool calling capabilities
    - `RequestNormalizer.php` - request transformation
    - `ResponseNormalizer.php` - response transformation
- **Utils**: `src/Utils/` - shared utility classes
  - `StreamingClient.php` - cURL-based streaming for all providers
  - `WebSearchClient.php` - universal web search integration
  - `FileUploadClient.php` - file upload handling for multi-modal
  - `ToolExecutor.php` - centralized tool execution routing

## Development Notes

### Adding New Providers (Lego Block Pattern)
1. Create new directory: `src/Providers/ProviderName/`
2. Create `Provider.php` - extend `AI_HTTP_Provider_Base`
3. Create `StreamingModule.php` - handle provider-specific SSE streaming
4. Create `FunctionCalling.php` - handle provider-specific tool calling
5. Create `RequestNormalizer.php` - handle provider-specific request format
6. Create `ResponseNormalizer.php` - handle provider-specific response format
7. Add provider to `ai-http-client.php` loader (5 files to require_once)
8. **Auto-discovered by ProviderRegistry** - no other changes needed

### Dependency Injection Pattern
- All components accept dependencies in constructor
- Defaults provided for ease of use
- Enables testing and extensibility
- Example: `new AI_HTTP_Client($config, $custom_factory, $custom_normalizer)`

### WordPress Integration
- Uses WordPress HTTP API (`wp_remote_post`) instead of cURL for compatibility
- Follows WordPress coding standards and security practices
- Designed for WordPress plugin environment with `ABSPATH` checks
- Extensible via WordPress filters for custom providers and configuration

### Configuration Approach
- **Single WordPress option**: `ai_http_client_providers` stores nested array of all provider settings
- **Provider selection**: `ai_http_client_selected_provider` stores currently selected provider
- **Options Manager**: Handles all WordPress options storage and retrieval
- **Admin Component**: Single component renders complete provider configuration interface
- **No styling**: Plugin developers handle all CSS/styling needs
- **Per-provider configuration**: Via WordPress filters for custom providers

## Development Workflow

### Project Structure Understanding
- **No build system**: Pure PHP library with no compilation step
- **No package manager**: Designed for direct inclusion in WordPress plugins
- **No testing framework**: Currently no automated tests (manual testing required)
- **Version management**: Uses version checking in main entry file to prevent conflicts

### Development Commands
Since this is a standalone PHP library for WordPress:
- **No build system**: Pure PHP library with no compilation or transpilation needed
- **No package dependencies**: Designed for direct inclusion without Composer
- **Testing**: Manual testing within WordPress environment or create test WordPress plugin
- **Linting**: Use WordPress Coding Standards if available: `phpcs --standard=WordPress`
- **Integration**: Include via `require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';`

### Critical Architecture Rules
- **Never hardcode model names** - All models must be fetched dynamically from provider APIs
- **Single Responsibility** - Each class/module handles exactly one concern
- **Provider-agnostic core** - Core classes contain no provider-specific logic
- **WordPress-native** - Use WordPress APIs, patterns, and security practices
- **Modular providers** - Each provider has exactly 5 components (Provider, Streaming, FunctionCalling, Request/ResponseNormalizer)

### Local Development Setup
1. **WordPress Environment**: Requires active WordPress installation for testing
2. **Integration Testing**: Create test WordPress plugin that includes the library
3. **Provider Testing**: Each provider needs valid API keys for testing  
4. **Symlink Development**: Use symlinks across multiple plugins during development
5. **Version Testing**: Test version conflict resolution by including different versions in multiple plugins

### Architecture Flow Understanding
**Request Pipeline**: Request → Validate → Normalize (provider-specific) → Send → Normalize (standardized) → Response

**Key Architectural Principles**:
- **Auto-discovery**: New providers are automatically discovered by scanning `/src/Providers/ProviderName/` directories
- **Dependency Injection**: All components accept dependencies in constructors for testability
- **Single Responsibility**: Each class has one clear job in the pipeline
- **Factory Pattern**: ProviderFactory creates instances, NormalizerFactory creates normalizers
- **Registry Pattern**: ProviderRegistry maintains available providers globally

### Version Compatibility System
The library includes sophisticated version checking to handle multiple plugins including different versions:
- Global version tracking prevents conflicts
- Only highest version loads
- Designed for WordPress plugin ecosystem where multiple plugins may bundle the same library

## Critical Implementation Notes

### Streaming Architecture
- **Core Streaming**: `AI_HTTP_Streaming_Client` provides provider-agnostic cURL streaming
- **Provider-Specific Parsing**: Each provider handles its own SSE event parsing in `extract_tool_calls()`
- **Real-time Output**: Streams directly to output buffer with immediate flushing
- **Tool Call Integration**: Completion callbacks handle tool execution after stream ends

### Tool Calling Pattern
- **Provider-Specific Schemas**: Each provider normalizes tool schemas to their format
- **Tool Extraction**: Providers parse their specific response formats for tool calls
- **SSE Tool Results**: Tool results sent as separate SSE events after main stream
- **Multi-turn Support**: Tool results properly formatted for continued conversation

### Complete Provider Implementation
All major AI providers now implemented with full feature support:
1. **OpenAI**: GPT models, OpenAI Responses API, streaming, function calling, vision
2. **Anthropic**: Claude models, streaming, function calling, vision, tool_use content blocks
3. **Google Gemini**: Latest 2025 API, streaming, function calling, multi-modal
4. **Grok/X.AI**: All models, reasoning_effort parameter, OpenAI-compatible
5. **OpenRouter**: Unified access to 100+ models, provider routing, fallbacks

### Advanced Features Implemented
- **Multi-modal Support**: Images, files, PDFs across all compatible providers
- **Universal Web Search**: Bing, Google, DuckDuckGo integration for fact-checking
- **File Upload System**: Large file handling with WordPress integration
- **Tool Execution Engine**: Centralized routing with plugin extensibility
- **No Hardcoded Models**: All models fetched dynamically from provider APIs

### WordPress Integration Patterns
- **HTTP API**: Uses `wp_remote_post()` for non-streaming, cURL for streaming
- **Options Storage**: Single nested array `ai_http_client_providers` for all settings  
- **Security**: All inputs sanitized with WordPress functions (`sanitize_text_field`, etc.)
- **Error Handling**: Returns `WP_Error` compatible responses for WordPress integration

## Target Plugin Integration
This library was designed to support 4 specific WordPress plugins:
1. **data-machine** - Multi-modal processing, file uploads, fact-checking tools
2. **wordsurf** - SSE streaming, OpenAI Responses API, advanced tool calling  
3. **automatic-cold-outreach** - Simple completions (already supported)
4. **ai-bot-for-bbpress** - Standard completions (already supported)

All target plugin patterns are now fully implemented and supported.