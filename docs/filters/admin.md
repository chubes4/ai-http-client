# API Key Management Filter

Centralized API key storage and management via WordPress filter system for all AI providers.

## Primary Filter: chubes_ai_provider_api_keys

Manages shared API key storage across all providers.

```php
// Get all API keys
$all_keys = apply_filters('chubes_ai_provider_api_keys', null);

// Set all API keys
$all_keys = ['openai' => 'sk-...', 'anthropic' => 'sk-ant-...'];
apply_filters('chubes_ai_provider_api_keys', $all_keys);
```

## Storage Details

- **Location**: WordPress site options (`chubes_ai_http_shared_api_keys`)
- **Scope**: Network-wide in multisite, per-site in single site
- **Format**: Associative array mapping provider names to API keys
- **Security**: Stored securely using WordPress options API

## Usage Examples

### Setting API Keys
```php
// Set multiple API keys at once
$api_keys = [
    'openai' => 'sk-your-openai-key',
    'anthropic' => 'sk-ant-your-anthropic-key',
    'gemini' => 'your-gemini-key',
    'grok' => 'your-grok-key',
    'openrouter' => 'sk-or-your-openrouter-key'
];

apply_filters('chubes_ai_provider_api_keys', $api_keys);
```

### Getting API Keys
```php
// Get all stored API keys
$stored_keys = apply_filters('chubes_ai_provider_api_keys', null);

// Get specific provider key
$openai_key = $stored_keys['openai'] ?? '';
```

### Updating Single Key
```php
// Get current keys, update one, save back
$current_keys = apply_filters('chubes_ai_provider_api_keys', null);
$current_keys['openai'] = 'sk-new-openai-key';
apply_filters('chubes_ai_provider_api_keys', $current_keys);
```

## Integration with Requests

The requests filter automatically retrieves API keys using this filter:

```php
// This happens automatically in ai_http_create_provider()
$shared_api_keys = apply_filters('chubes_ai_provider_api_keys', null);
$api_key = $shared_api_keys[$provider_name] ?? '';
```

## Multisite Support

- **Network Activation**: Keys stored in network-wide options
- **Site-Specific**: Each site can have different API keys
- **Fallback**: Network keys used as fallback if site keys not set

## Security Considerations

- API keys stored encrypted in database
- Access controlled by WordPress capabilities
- No keys exposed in frontend code
- Automatic key validation on provider requests

## Migration Support

Automatic migration from v1.x format (`ai_http_shared_api_keys`) to v2.0 format on plugin activation.