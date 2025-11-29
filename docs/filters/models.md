# Model Management Filter

Centralized model fetching and caching via WordPress filter system with 24-hour caching for performance.

## Primary Filter: chubes_ai_models

Retrieves available models for a provider with automatic caching.

```php
$models = apply_filters('chubes_ai_models', $provider_name, $provider_config);
```

### Parameters

- **$provider_name** (string): Provider identifier
- **$provider_config** (array, optional): Provider configuration including API key

### Response Format

Returns associative array mapping model IDs to display names:

```php
[
    'gpt-4o' => 'GPT-4o',
    'gpt-4o-mini' => 'GPT-4o Mini',
    'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
    // ...
]
```

## Caching Behavior

- **TTL**: 24 hours (86400 seconds)
- **Cache Key**: Secure hash including provider name and API key hash
- **Isolation**: Different API keys have separate caches
- **Automatic Cleanup**: Expired entries removed automatically

## Cache Key Generation

```php
$cache_key = 'chubes_ai_models_' . $provider_name . '_' . md5($api_key);
```

## Cache Clearing

Clear cache programmatically:

```php
// Clear specific provider cache
do_action('chubes_ai_clear_model_cache', 'openai');

// Clear all provider caches
do_action('chubes_ai_clear_all_cache');
```

## Provider Configuration

Pass API key for authenticated model fetching:

```php
$config = ['api_key' => 'your-api-key'];
$models = apply_filters('chubes_ai_models', 'openai', $config);
```

## Error Handling

Returns empty array on errors. Errors logged via `chubes_ai_library_error` action for debugging.

## Performance Benefits

- Reduces API calls to model endpoints
- Faster response times for repeated requests
- Automatic cache invalidation on provider changes
- Memory efficient with WordPress transients