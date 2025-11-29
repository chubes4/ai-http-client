# AI HTTP Client Library

A professional WordPress library providing unified AI provider communication. Supports OpenAI, Anthropic, Google Gemini, Grok, and OpenRouter with standardized request/response formats.

## Library Initialization

Include the library in your WordPress plugin or theme by requiring the main file:

```php
require_once 'path/to/ai-http-client.php';
```

The library automatically initializes on WordPress `plugins_loaded` hook with priority 1, loading all providers and registering filter hooks.

## Core Architecture

### Filter-Based Integration
All functionality exposed through WordPress filters for maximum extensibility. Self-contained provider classes handle format conversion internally.

### Unified Interface
All requests use standard format:
```php
[
    'messages' => [
        ['role' => 'user', 'content' => 'text']
    ],
    'model' => 'model-name'
]
```

All responses use standard format:
```php
[
    'success' => bool,
    'data' => [...],
    'error' => null|string,
    'provider' => 'name'
]
```

### Provider Self-Registration
Each provider registers via `chubes_ai_providers` filter in its own file, enabling plugin isolation and no provider conflicts.

## Supported Providers

All providers extend the `AI_HTTP_BaseProvider` abstract class (introduced v2.0.6), which centralizes common functionality including validation, sanitization, HTTP patterns, and Files API operations.

- **OpenAI**: Responses API, native Files API, function calling, streaming
- **Anthropic**: Claude models, native Files API with vision, streaming, function calling
- **Gemini**: Google AI models, native Files API with multi-modal support, streaming, function calling
- **Grok**: X.AI integration, streaming support
- **OpenRouter**: Gateway to 200+ models

## Key Features

- **Standardized Format**: Unified request/response format across all providers
- **File Upload Support**: Native Files API integration for OpenAI, Anthropic, and Gemini
- **Streaming Support**: Real-time response streaming for compatible providers
- **Function Calling**: Tool/function calling support across all providers
- **Model Caching**: 24-hour caching of model lists for performance
- **Error Handling**: Centralized error logging via WordPress action hooks
- **REST API**: Configuration and management endpoints
- **Multisite Support**: Network-wide API key storage support

## Requirements

- PHP 7.4+
- WordPress 5.0+
- No external dependencies beyond WordPress core

## Security

- All user input sanitized using WordPress functions
- API keys stored securely using WordPress options API
- File uploads validated for type and size
- All output escaped using WordPress functions