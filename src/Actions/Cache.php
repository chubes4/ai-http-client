<?php
/**
 * AI HTTP Client - Cache Management
 *
 * Centralized model caching using WordPress transients and action hooks.
 * Provides 24-hour caching for AI model lists with granular cache clearing.
 *
 * @package AIHttpClient\Actions
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AIHttpCache {

    /**
     * Cache key constants for WordPress transient management.
     */
    const MODEL_CACHE_PREFIX = 'ai_models_';
    const CACHE_TTL = 24 * HOUR_IN_SECONDS; // 24 hours

    /**
     * Supported AI providers for cache management.
     */
    const SUPPORTED_PROVIDERS = ['openai', 'anthropic', 'gemini', 'grok', 'openrouter'];

    /**
     * Register cache clearing action hooks.
     */
    public static function register() {
        $instance = new self();

        add_action('ai_clear_model_cache', [$instance, 'handle_clear_model_cache'], 10, 1);
        add_action('ai_clear_all_cache', [$instance, 'handle_clear_all_cache'], 10, 0);
    }

    /**
     * Clear model cache for specific provider.
     */
    public function handle_clear_model_cache($provider) {
        if (empty($provider)) {
            return;
        }

        $this->clear_model_cache($provider);

        // Signal that provider cache was cleared
        do_action('ai_model_cache_cleared', $provider);
    }

    /**
     * Clear all model caches for all providers.
     */
    public function handle_clear_all_cache() {
        foreach (self::SUPPORTED_PROVIDERS as $provider) {
            $this->clear_model_cache($provider);
        }

        // Signal that all caches were cleared
        do_action('ai_all_model_cache_cleared');
    }

    /**
     * Clear model cache for specific provider (all API keys).
     */
    private function clear_model_cache($provider) {
        global $wpdb;

        // Clear all cache keys matching pattern: ai_models_{provider}_*
        $cache_pattern = self::MODEL_CACHE_PREFIX . $provider . '_';
        $sql_pattern = str_replace('*', '%', $cache_pattern . '*');

        // Delete transient keys
        $transient_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $sql_pattern
        ));

        foreach ($transient_keys as $transient_key) {
            $transient_name = str_replace('_transient_', '', $transient_key);
            delete_transient($transient_name);
        }

        // Delete timeout keys
        $timeout_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_' . $sql_pattern
        ));

        foreach ($timeout_keys as $timeout_key) {
            delete_option($timeout_key);
        }
    }
}