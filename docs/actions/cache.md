# Cache Management Action

Centralized model caching using WordPress transients and action hooks with granular cache clearing capabilities.

## Action Hooks

### chubes_ai_clear_model_cache
Clear model cache for a specific provider.

```php
do_action('chubes_ai_clear_model_cache', 'openai');
```

### chubes_ai_clear_all_cache
Clear model caches for all providers.

```php
do_action('chubes_ai_clear_all_cache');
```

## Cache Implementation

- **Storage**: WordPress transients with 24-hour TTL
- **Key Format**: `chubes_ai_models_{provider}_{api_key_hash}`
- **Isolation**: Separate caches per API key for security
- **Cleanup**: Automatic expiration and manual clearing

## Cache Clearing Process

1. **Pattern Matching**: Finds all cache keys matching provider pattern
2. **Database Query**: Uses SQL LIKE queries for efficient cleanup
3. **Complete Removal**: Deletes both transient data and timeout records

## Supported Providers

```php
const SUPPORTED_PROVIDERS = ['openai', 'anthropic', 'gemini', 'grok', 'openrouter'];
```

## Usage Examples

### Clear Single Provider Cache
```php
// Clear OpenAI model cache
do_action('chubes_ai_clear_model_cache', 'openai');

// Clear Anthropic model cache
do_action('chubes_ai_clear_model_cache', 'anthropic');
```

### Clear All Caches
```php
// Clear all provider model caches
do_action('chubes_ai_clear_all_cache');
```

### Hook into Cache Events
```php
// Listen for cache clearing events
add_action('chubes_ai_model_cache_cleared', function($provider) {
    error_log("Model cache cleared for provider: {$provider}");
});

add_action('chubes_ai_all_model_cache_cleared', function() {
    error_log("All model caches cleared");
});
```

## Cache Key Security

- **API Key Hashing**: MD5 hash of API key for cache isolation
- **No Key Exposure**: Hashed keys prevent API key leakage
- **Per-Key Caches**: Different API keys have separate cache entries

## Performance Benefits

- **Fast Cleanup**: SQL-based pattern matching for efficient clearing
- **Memory Efficient**: Uses WordPress built-in transient system
- **Automatic Expiration**: 24-hour TTL prevents stale data
- **Granular Control**: Clear individual or all provider caches