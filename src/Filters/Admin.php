<?php
/**
 * AI HTTP Client - API Key Management
 *
 * Centralized API key storage and management via WordPress filter system.
 * Provides shared API key storage for all AI providers.
 *
 * @package AIHttpClient\Filters
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

// Filter to get/set all provider API keys.
// Usage: apply_filters('ai_provider_api_keys', null) to get all keys.
//        apply_filters('ai_provider_api_keys', $keys) to update all keys.
add_filter('ai_provider_api_keys', function($keys = null) {
    $option_name = 'ai_http_shared_api_keys';
    if (is_null($keys)) {
        // Get all keys (network-wide in multisite, per-site in single-site)
        return get_site_option($option_name, []);
    } else {
        // Set all keys (network-wide in multisite, per-site in single-site)
        update_site_option($option_name, $keys);
        return $keys;
    }
});