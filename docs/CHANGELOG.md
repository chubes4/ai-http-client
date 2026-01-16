# Changelog

All notable changes to the AI HTTP Client library will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.12] - 2026-01-16

### Fixed

- **Invalid JSON Response Handling**: Added validation for JSON decoding errors in `BaseProvider::request()`. When an AI provider returns invalid JSON (e.g., Cloudflare HTML error page with HTTP 200), the library now triggers an error and throws an exception with diagnostic information including the provider name, JSON error message, and response preview.

## [2.0.11] - 2026-01-15

### Fixed

- **Tool Call Arguments Parsing**: Fixed `tool_call` arguments parsing to JSON decode like the `function_call` path, ensuring consistent argument handling across different response formats.

## [2.0.10] - 2026-01-14

### Added

- **Non-WordPress Environment Support**: Added defensive checks for non-WordPress environments to support testing scenarios where WordPress functions may not be available.

## [2.0.9] - 2026-01-13

### Added

- **Message Field Sanitization**: Added whitelist-based message field sanitization in `BaseProvider::sanitize_message_fields()` to filter out internal application fields (like metadata) that AI providers don't recognize, preventing provider errors.

## [2.0.8] - 2026-01-12

### Fixed

- **Empty Properties Serialization**: Fixed empty properties serializing as array `[]` instead of object `{}` in JSON schema conversion, which caused provider validation errors.

## [2.0.7] - 2025-11-28

### Removed

- **Inappropriate Build Script**: Removed `build.sh` as this is a Composer library, not a ZIP-distributed WordPress plugin. The script created unused ZIP artifacts that don't align with the library's distribution method.

### Changed

- **Composer Archive Exclusions**: Added `archive.exclude` configuration to `composer.json` to prevent documentation and development files from being included in Composer package installations, while keeping them visible in the GitHub repository.

### Technical Details

**Archive Exclusions Added:**
- `/docs` - User documentation (keeps GitHub visible, excludes from vendor/)
- `/.claude` - Development AI context files
- `/CLAUDE.md` - Development guidelines
- `/README.md` - Repository documentation
- `/build.sh` - Removed build script

**Impact:**
- Composer users get clean vendor directories without documentation bloat
- GitHub repository maintains full documentation visibility
- Aligns distribution method with Composer library standards

## [2.0.6] - 2025-11-28

### Added

- **BaseProvider Architecture**: Introduced `AI_HTTP_BaseProvider` abstract class centralizing common provider functionality including validation, sanitization, HTTP patterns, and Files API operations
- **Unified Provider Interface**: All providers now extend BaseProvider, ensuring consistent implementation patterns and reducing code duplication
- **Enhanced File Management**: Centralized file upload/delete operations with provider-specific implementations
- **Improved Error Handling**: Standardized error handling across all providers with consistent error triggering and context

### Changed

- **Provider Refactoring**: All provider classes (OpenAI, Anthropic, Gemini, Grok, OpenRouter) refactored to extend BaseProvider
- **Code Consolidation**: Removed ~1700 lines of duplicated code across providers by leveraging inheritance
- **Initialization Order**: BaseProvider now loads first in ai-http-client.php to ensure proper class availability

### Technical Details

**New BaseProvider Features**:
- Abstract methods for provider-specific implementations (endpoints, formatting, authentication)
- Centralized request/response handling with unified error management
- Built-in Files API support with multipart upload capabilities
- Streaming request infrastructure with callback support
- Model fetching and normalization with caching integration

**Affected Files** (6 total):
- `/ai-http-client.php` - BaseProvider loading order
- `/src/Providers/BaseProvider.php` - NEW: Abstract base class
- `/src/Providers/anthropic.php` - Refactored to extend BaseProvider
- `/src/Providers/gemini.php` - Refactored to extend BaseProvider
- `/src/Providers/grok.php` - Refactored to extend BaseProvider
- `/src/Providers/openai.php` - Refactored to extend BaseProvider
- `/src/Providers/openrouter.php` - Refactored to extend BaseProvider

**No Functional Changes**: All AI provider functionality remains identical. This is purely an architectural improvement for maintainability and code consistency.

## [2.0.5] - 2025-11-25

### Fixed

- **URL Encoding Bug**: Removed `sanitize_textarea_field()` from message content sanitization across all providers. This WordPress function was URL-encoding special characters in message content, corrupting structured data. Role sanitization retained; tool descriptions unchanged.

## [2.0.4] - 2025-11-20

### Fixed

- **Streaming Request Bug**: Removed premature `curl_close()` call in streaming request function that was interrupting data transmission. Streaming requests now complete properly without connection interruption.

## [2.0.3] - 2025-11-20

### Fixed

- **Multi-Plugin Compatibility**: Added initialization guard to prevent conflicts when multiple plugins include the AI HTTP Client library simultaneously. The library now properly handles being loaded by multiple plugins without losing provider registrations.

## [2.0.1] - 2025-11-16

### Fixed

- **Critical Fix**: Corrected function call in `Models.php` line 41 - changed `chubes_ai_http_generate_cache_key()` to `ai_http_generate_cache_key()` to match actual function definition
- **Context**: Utility functions use `ai_http_` prefix, while filter hooks use `chubes_ai_` prefix. The CHANGELOG entry in 2.0.0 incorrectly stated the function was renamed - only filter hooks were renamed, not utility functions.

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

## [1.2.3] - 2025-01-14

### Added
- Dynamic model fetching for Anthropic provider
- Models now discovered from Anthropic's /v1/models API endpoint instead of hardcoded values
- Improved model availability with automatic updates as Anthropic releases new models

### Changed
- Updated `get_raw_models()` method to call Anthropic's models API
- Added proper error handling for API failures during model discovery
- Maintains backward compatibility with existing model caching system

---

## [1.2.2] - 2025-01-13

### Fixed
- Fixed initialization timing issue that prevented providers from registering
- Changed from conditional check to reliable hook-based initialization using `plugins_loaded` action
- Ensures provider files load and register properly regardless of plugin load order

### Changed
- Replaced `did_action('plugins_loaded')` conditional with `add_action('plugins_loaded', 'ai_http_client_init', 1)`
- Eliminates race condition between library loading and WordPress initialization
- All AI providers now register correctly and are available through REST API endpoints

---

## [1.2.1] - 2025-01-12

### Added
- Auto-initialization when WordPress is ready - no manual `ai_http_client_init()` calls required in plugins
- Improved plugin integration with seamless loading on `plugins_loaded` action

---

## [1.2.0] - 2025-01-11

### Added
- Native Files API integration for OpenAI, Anthropic, and Gemini providers
- Multisite network-wide API key storage support
- REST API endpoints replacing admin components for configuration and management
- Consolidated error handling: single `ai_library_error` hook for all errors (API + library)
- Enhanced error monitoring with unified data format across all components
- Improved model caching system with granular cache management
- Automatic file upload and management via provider-specific Files APIs
- Multi-modal content support (images, documents, files)
- Seamless integration with existing request/response formats
- File lifecycle management with upload/delete operations

### Changed
- Removed admin components, jQuery, AJAX, and provider manager UI
- All configuration now handled via REST API endpoints
- REST API endpoints added via RestApi.php for configuration and management
- Consolidated error hooks: `ai_api_error` removed, all errors now use `ai_library_error`
