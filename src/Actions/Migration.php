<?php
/**
 * AI HTTP Client - Migration for v2.0.0
 *
 * Migrates API keys from v1.x (ai_http_shared_api_keys) to v2.0 (chubes_ai_http_shared_api_keys).
 * Runs automatically on admin_init and only executes once.
 *
 * @package AIHttpClient\Actions
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AIHttpMigration {

    /**
     * Register migration hooks
     */
    public static function register() {
        add_action('admin_init', [__CLASS__, 'migrate_v2'], 1);
        add_action('chubes_ai_http_cleanup_old_keys', [__CLASS__, 'cleanup_old_keys']);
    }

    /**
     * Migrate v1.x API keys to v2.0 format
     *
     * This migration:
     * 1. Checks if migration has already run
     * 2. Copies API keys from old option to new option
     * 3. Marks migration as complete
     * 4. Schedules cleanup of old option after 30 days
     */
    public static function migrate_v2() {
        // Check if already migrated
        $migrated = get_site_option('chubes_ai_http_v2_migrated', false);

        if ($migrated) {
            return;
        }

        // Get old API keys
        $old_keys = get_site_option('ai_http_shared_api_keys', []);

        // Only proceed if old keys exist
        if (!empty($old_keys) && is_array($old_keys)) {
            // Copy to new option
            update_site_option('chubes_ai_http_shared_api_keys', $old_keys);

            // Log migration
            if (function_exists('error_log')) {
                error_log(sprintf(
                    '[AI HTTP Client] v2.0 Migration: Migrated %d API keys from v1.x to v2.0 format',
                    count($old_keys)
                ));
            }

            // Schedule cleanup of old option after 30 days
            if (function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(
                    time() + (30 * DAY_IN_SECONDS),
                    'chubes_ai_http_cleanup_old_keys'
                );
            }
        }

        // Mark migration complete (even if no keys were found)
        update_site_option('chubes_ai_http_v2_migrated', true);
    }

    /**
     * Cleanup old API keys option (scheduled for 30 days after migration)
     *
     * This gives users time to rollback if needed.
     */
    public static function cleanup_old_keys() {
        delete_site_option('ai_http_shared_api_keys');

        if (function_exists('error_log')) {
            error_log('[AI HTTP Client] v2.0 Migration: Cleaned up old v1.x API keys option');
        }
    }
}

// Register migration
AIHttpMigration::register();
