# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **AI HTTP Client** - a WordPress library for unified AI provider communication. It's designed to be included in WordPress plugins as a standalone component, similar to how Action Scheduler is distributed. The library abstracts multiple AI providers (OpenAI, Anthropic, Google Gemini, Grok, OpenRouter) behind a single, standardized interface.

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
    - `RequestNormalizer.php` - request transformation
    - `ResponseNormalizer.php` - response transformation

## Development Notes

### Adding New Providers (Lego Block Pattern)
1. Create new directory: `src/Providers/ProviderName/`
2. Create `Provider.php` - extend `AI_HTTP_Provider_Base`
3. Create `RequestNormalizer.php` - handle provider-specific request format
4. Create `ResponseNormalizer.php` - handle provider-specific response format
5. **No other changes needed** - auto-discovered by ProviderRegistry

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
- **Testing**: Manual testing within WordPress environment or create test WordPress plugin
- **Linting**: Use WordPress Coding Standards if available: `phpcs --standard=WordPress`
- **Integration**: Include via `require_once` in WordPress plugin development

### Local Development Setup
1. **WordPress Environment**: Requires active WordPress installation for testing
2. **Integration Testing**: Create test plugin that includes the library
3. **Provider Testing**: Each provider needs valid API keys for testing
4. **Symlink Development**: Use symlinks across multiple plugins during development

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