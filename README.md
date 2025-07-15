# AI HTTP Client for WordPress

A professional WordPress library for unified AI provider communication. Drop-in solution for advanced WordPress plugin developers who need AI functionality with minimal integration effort.

## Why This Library?

**For Advanced WordPress Developers** - Not beginners, not general PHP projects. This is for experienced plugin developers who want to ship AI features fast.

**Complete Drop-In Solution:**
- ✅ Backend AI integration + Admin UI component
- ✅ Zero styling (you control the design)
- ✅ Auto-discovery of providers
- ✅ Standardized request/response formats
- ✅ WordPress-native (no Composer, uses `wp_remote_post`)
- ✅ Dynamic model fetching (no hardcoded models)

## Installation

### Method 1: Git Subtree (Recommended)
Install as a subtree in your plugin for automatic updates:

```bash
# From your plugin root directory
git subtree add --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash

# To update later
git subtree pull --prefix=lib/ai-http-client https://github.com/chubes4/ai-http-client.git main --squash
```

### Method 2: Direct Download
Download and place in your plugin's `/lib/ai-http-client/` directory.

## Quick Start

### 1. Include the Library
```php
// In your plugin
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';
```

### 2. Add Admin UI Component
```php
// Basic usage
echo AI_HTTP_ProviderManager_Component::render();

// Customized component
echo AI_HTTP_ProviderManager_Component::render([
    'components' => [
        'core' => ['provider_selector', 'api_key_input', 'model_selector'],
        'extended' => ['temperature_slider', 'system_prompt_field']
    ],
    'component_configs' => [
        'temperature_slider' => ['min' => 0, 'max' => 1, 'default_value' => 0.7]
    ]
]);
```

### 3. Send AI Requests
```php
$client = new AI_HTTP_Client();
$response = $client->send_request([
    'messages' => [
        ['role' => 'user', 'content' => 'Hello AI!']
    ],
    'model' => 'gpt-4o',
    'max_tokens' => 100
]);

if ($response['success']) {
    echo $response['data']['content'];
}
```

### 4. Modular Prompt System
```php
// Register tool definitions for your AI agent
AI_HTTP_Prompt_Manager::register_tool_definition(
    'edit_content',
    "Use this tool to edit content with specific instructions...",
    ['priority' => 1, 'category' => 'content']
);

// Build dynamic system prompts with context
$prompt = AI_HTTP_Prompt_Manager::build_modular_system_prompt(
    $base_prompt,
    ['post_id' => 123, 'user_role' => 'editor'],
    [
        'include_tools' => true,
        'tool_context' => 'my_plugin',
        'enabled_tools' => ['edit_content', 'read_content']
    ]
);
```

### 5. Continuation Support (For Agentic Systems)
```php
// Send initial request with tools
$response = $client->send_request([
    'messages' => [['role' => 'user', 'content' => 'What is the weather?']],
    'tools' => $tool_schemas
]);

// Continue with tool results
$response_id = $client->get_last_response_id();
$continuation = $client->continue_with_tool_results($response_id, $tool_results);
```

## Supported Providers

All providers support **dynamic model fetching** - no hardcoded model lists. Models are fetched live from each provider's API.

- **OpenAI** - All models via dynamic API fetching
- **Anthropic** - All Claude models 
- **Google Gemini** - All Gemini models via dynamic API fetching
- **Grok/X.AI** - All Grok models via dynamic API fetching
- **OpenRouter** - 200+ models via unified API

## Architecture

**"Round Plug" Design** - Standardized input → Black box processing → Standardized output

**Auto-Discovery** - New providers automatically discovered by scanning `/src/Providers/ProviderName/`

**WordPress-Native** - Uses WordPress HTTP API, options system, and admin patterns

**Modular Prompts** - Dynamic prompt building with tool registration, context injection, and granular control

## Component Configuration

The admin UI component is fully configurable:

```php
// Available core components
'core' => [
    'provider_selector',  // Dropdown to select provider
    'api_key_input',     // Secure API key input
    'model_selector'     // Dynamic model dropdown
]

// Available extended components  
'extended' => [
    'temperature_slider',    // Temperature control (0-1)
    'system_prompt_field',   // System prompt textarea
    'max_tokens_input',      // Max tokens input
    'top_p_slider'          // Top P control
]

// Component-specific configs
'component_configs' => [
    'temperature_slider' => [
        'min' => 0,
        'max' => 1, 
        'step' => 0.1,
        'default_value' => 0.7
    ]
]
```

## Modular Prompt System

Build dynamic AI prompts with context awareness and tool management:

```php
// Register tool definitions that can be dynamically included
AI_HTTP_Prompt_Manager::register_tool_definition(
    'tool_name',
    'Tool description and usage instructions...',
    ['priority' => 1, 'category' => 'content_editing']
);

// Set which tools are enabled for different contexts
AI_HTTP_Prompt_Manager::set_enabled_tools(['tool1', 'tool2'], 'my_plugin_context');

// Build complete system prompts with context and tools
$prompt = AI_HTTP_Prompt_Manager::build_modular_system_prompt(
    $base_prompt,
    $context_data,
    [
        'include_tools' => true,
        'tool_context' => 'my_plugin_context',
        'enabled_tools' => ['specific_tool'],
        'sections' => ['custom_section' => 'Additional content...']
    ]
);
```

**Features:**
- **Tool Registration** - Register tool descriptions that can be dynamically included
- **Context Awareness** - Inject dynamic context data into prompts
- **Granular Control** - Enable/disable tools per plugin or use case
- **Filter Integration** - WordPress filters for prompt customization
- **Variable Replacement** - Template variable substitution

## Distribution Model

Designed for **git subtree inclusion** like Action Scheduler:
- No external dependencies
- Version conflict resolution
- Multiple plugins can include different versions safely
- Automatic updates via `git subtree pull`

## For Advanced Developers Only

This library assumes you:
- Know WordPress plugin development
- Understand dependency injection and factory patterns
- Want backend functionality + unstyled UI component
- Need to ship AI features quickly

Not for beginners or general PHP projects.

## Contributing

Built by advanced developers, for advanced developers. PRs welcome for:
- New provider implementations
- Performance improvements
- WordPress compatibility fixes

## License

GPL v2 or later

---

**[Chris Huber](https://chubes.net)** - For advanced WordPress developers who ship fast.