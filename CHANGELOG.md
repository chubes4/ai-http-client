# Changelog

All notable changes to the AI HTTP Client library will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-11-15

### Breaking Changes

**WordPress.org Compliance**: All filter/action hooks renamed from `ai_*` to `chubes_ai_*` prefix for WordPress Plugin Directory compliance and to prevent naming conflicts with other AI-related plugins.

#### Hook Migrations

**Filters**:
- `ai_providers` → `chubes_ai_providers`
- `ai_provider_api_keys` → `chubes_ai_provider_api_keys`
- `ai_models` → `chubes_ai_models`
- `ai_tools` → `chubes_ai_tools`
- `ai_request` → `chubes_ai_request`
- `ai_file_to_base64` → `chubes_ai_file_to_base64`
- `ai_http` → `chubes_ai_http`

**Actions**:
- `ai_http_client_loaded` → `chubes_ai_http_client_loaded`
- `ai_library_error` → `chubes_ai_library_error`
- `ai_clear_model_cache` → `chubes_ai_clear_model_cache`
- `ai_clear_all_cache` → `chubes_ai_clear_all_cache`
- `ai_model_cache_cleared` → `chubes_ai_model_cache_cleared`
- `ai_all_model_cache_cleared` → `chubes_ai_all_model_cache_cleared`

#### Storage Migrations

**WordPress Options**:
- `ai_http_shared_api_keys` → `chubes_ai_http_shared_api_keys` (auto-migrated)

**Cache Keys** (Transients):
- `ai_models_{provider}_{hash}` → `chubes_ai_models_{provider}_{hash}` (rebuilds automatically)

### Added

- **Automatic Migration System**: API keys are automatically migrated on first admin page load after upgrading to v2.0
- **Migration Tracking**: `chubes_ai_http_v2_migrated` option tracks migration completion
- **Scheduled Cleanup**: Old API keys option automatically deleted 30 days after migration

### Changed

- **Cache Key Constant**: `AIHttpCache::MODEL_CACHE_PREFIX` updated from `'ai_models_'` to `'chubes_ai_models_'`
- **Function Naming**: `ai_http_generate_cache_key()` renamed to `chubes_ai_http_generate_cache_key()`
- **Database Queries**: Transient pattern matching updated to use new `chubes_ai_models_*` pattern

### Migration Guide

#### For Plugin Developers

If your plugin uses the ai-http-client library, update all hook references:

```php
// OLD (v1.x)
$providers = apply_filters('ai_providers', []);
$models = apply_filters('ai_models', $provider);
$response = apply_filters('ai_request', $request);
add_filter('ai_tools', function($tools) { ... });

// NEW (v2.0)
$providers = apply_filters('chubes_ai_providers', []);
$models = apply_filters('chubes_ai_models', $provider);
$response = apply_filters('chubes_ai_request', $request);
add_filter('chubes_ai_tools', function($tools) { ... });
```

#### For End Users

**No manual intervention required**. API keys are automatically migrated when:
1. You visit any WordPress admin page after upgrading
2. The migration runs once and marks itself complete
3. Old API keys are preserved for 30 days, then automatically cleaned up

#### Rollback Instructions

If you need to rollback to v1.x within 30 days:
1. Downgrade library to v1.2.3
2. Delete the `chubes_ai_http_v2_migrated` option
3. Your original `ai_http_shared_api_keys` will still be intact

### Technical Details

**Affected Files** (17 total):
- `/ai-http-client.php` - Version bump, migration loader, action name
- `/src/Actions/Migration.php` - NEW: Auto-migration script
- `/src/Actions/Cache.php` - Constant, actions, database queries
- `/src/Actions/Error.php` - Action name
- `/src/Filters/Admin.php` - Option name, filter name
- `/src/Filters/Models.php` - Function name, filter name
- `/src/Filters/Requests.php` - 3 filters, 4 apply_filters calls
- `/src/Filters/RestApi.php` - 4 apply_filters calls
- `/src/Filters/Tools.php` - Filter name, apply_filters call
- `/src/Providers/anthropic.php` - Filter name, 5 apply_filters calls
- `/src/Providers/gemini.php` - Filter name, 3 apply_filters calls
- `/src/Providers/grok.php` - Filter name, 3 apply_filters calls
- `/src/Providers/openai.php` - Filter name, 5 apply_filters calls
- `/src/Providers/openrouter.php` - Filter name, 6 apply_filters calls

**No Functional Changes**: All AI provider functionality remains identical. This is purely a prefix migration for WordPress.org compliance.

---

## [1.2.3] - Previous Release

_(Previous changelog entries would go here)_
