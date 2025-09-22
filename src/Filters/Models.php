<?php
/**
 * AI HTTP Client - Model Filters
 * 
 * Centralized model fetching via WordPress filter system.
 * All model-related filters organized in this file.
 *
 * @package AIHttpClient\Filters
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Generate secure cache key for model caching
 */
function ai_http_generate_cache_key($provider_name, $api_key = '') {
    // Create consistent hash for API key (empty string if no key)
    $api_key_hash = empty($api_key) ? 'nokey' : substr(md5($api_key), 0, 8);
    return AIHttpCache::MODEL_CACHE_PREFIX . $provider_name . '_' . $api_key_hash;
}

// AI Models filter - with 24-hour caching for performance
// Usage: $models = apply_filters('ai_models', $provider_name);
add_filter('ai_models', function($provider_name = null) {

    $args = func_get_args();
    $provider_config = $args[1] ?? null;

    if (empty($provider_name)) {
        return [];
    }

    // Extract API key from provider config for secure cache isolation
    $api_key = '';
    if (is_array($provider_config) && isset($provider_config['api_key'])) {
        $api_key = $provider_config['api_key'];
    }

    // Check cache first (24-hour TTL) with secure key including API key hash
    $cache_key = ai_http_generate_cache_key($provider_name, $api_key);
    $cached_models = get_transient($cache_key);

    if ($cached_models !== false) {
        return $cached_models;
    }

    try {
        // Create provider instance directly, always passing config if present
        $provider = ai_http_create_provider($provider_name, $provider_config);
        if (!$provider) {
            return [];
        }

        // Get models directly from provider (now returns full array of model objects)
        $models = $provider->get_normalized_models();

        // Cache the results for 24 hours
        set_transient($cache_key, $models, AIHttpCache::CACHE_TTL);

        return $models;
    } catch (Exception $e) {
        // Trigger error event for debugging
        AIHttpError::trigger_api_error($provider_name, 'get_models', [
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ], [
            'provider_config' => $provider_config,
            'cache_key' => $cache_key
        ]);

        return [];
    }
}, 10, 2);