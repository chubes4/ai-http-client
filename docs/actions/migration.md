# Migration Action

Automatic migration system for v2.0.0 that handles API key migration from v1.x format to v2.0 format.

## Migration Overview

- **Trigger**: Runs automatically on `admin_init` hook
- **Source**: `ai_http_shared_api_keys` (v1.x)
- **Target**: `chubes_ai_http_shared_api_keys` (v2.0)
- **Safety**: One-time migration with rollback window

## Migration Process

1. **Check Completion**: Verify migration hasn't already run
2. **Copy Keys**: Transfer API keys from old to new option
3. **Schedule Cleanup**: Queue old option removal after 30 days
4. **Mark Complete**: Prevent re-running migration

## Automatic Execution

Migration runs automatically when:
- Administrator visits wp-admin
- Plugin is activated
- Migration hasn't been completed before

## Safety Features

### Rollback Window
- Old API keys preserved for 30 days
- Allows recovery if migration causes issues
- Automatic cleanup prevents data accumulation

### One-Time Execution
- Migration flag prevents re-running
- Even runs if no keys exist (marks as complete)
- Safe to run multiple times without side effects

## Manual Cleanup

Force immediate cleanup of old keys:

```php
do_action('chubes_ai_http_cleanup_old_keys');
```

## Migration Data

### Old Format (v1.x)
```php
// Stored in: ai_http_shared_api_keys
[
    'openai' => 'sk-old-key',
    'anthropic' => 'sk-ant-old-key'
]
```

### New Format (v2.0)
```php
// Stored in: chubes_ai_http_shared_api_keys
[
    'openai' => 'sk-new-key',
    'anthropic' => 'sk-ant-new-key'
]
```

## Multisite Compatibility

- **Network Migration**: Keys migrated at network level
- **Site Isolation**: Each site maintains separate keys
- **Global Option**: Uses `get_site_option`/`update_site_option`

## Error Handling

Migration failures are logged but don't prevent plugin operation. Failed migrations can be manually re-triggered by:

1. Deleting the migration flag: `delete_site_option('chubes_ai_http_v2_migrated')`
2. Re-running migration on next admin visit

## Backward Compatibility

- **No Breaking Changes**: Existing functionality continues working
- **Graceful Degradation**: Falls back gracefully if migration fails
- **Data Preservation**: Original keys never deleted without confirmation