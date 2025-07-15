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

## Quick Start

### 1. Include the Library
```php
// In your plugin
require_once plugin_dir_path(__FILE__) . 'lib/ai-http-client/ai-http-client.php';
```

### 2. Add Admin UI Component
```php
// In your admin page
echo AI_HTTP_ProviderManager_Component::render();
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

## Supported Providers

- **OpenAI** (GPT-4, GPT-3.5 Turbo)
- **Anthropic** (Claude 3.5 Sonnet, Claude 3 Opus)
- **Google Gemini** (Coming Soon)
- **Grok** (Coming Soon)
- **OpenRouter** (Coming Soon)

## Architecture

**"Round Plug" Design** - Standardized input → Black box processing → Standardized output

**Auto-Discovery** - New providers automatically discovered by scanning `/src/Providers/ProviderName/`

**WordPress-Native** - Uses WordPress HTTP API, options system, and admin patterns

## Distribution Model

Designed for **git subtree inclusion** like Action Scheduler:
- No external dependencies
- Version conflict resolution
- Multiple plugins can include different versions safely

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