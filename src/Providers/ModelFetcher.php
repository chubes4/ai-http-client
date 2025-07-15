<?php
/**
 * AI HTTP Client - Model Fetcher
 * 
 * Single Responsibility: Fetch live model lists from provider APIs
 * Reusable across all providers with caching support
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Model_Fetcher {

    private $cache_duration = 3600; // 1 hour cache
    private $cache_prefix = 'ai_http_models_';

    /**
     * Fetch models from provider API with caching
     *
     * @param string $provider_name Provider name
     * @param string $api_endpoint Models API endpoint
     * @param array $headers Request headers (including auth)
     * @param callable $parser Optional parser function for response
     * @return array Models list or cached fallback
     */
    public function fetch_models($provider_name, $api_endpoint, $headers = array(), $parser = null) {
        $cache_key = $this->cache_prefix . $provider_name;
        
        // Try cache first
        $cached_models = $this->get_cached_models($cache_key);
        if ($cached_models !== false) {
            return $cached_models;
        }

        try {
            // Fetch from API
            $models = $this->fetch_from_api($api_endpoint, $headers, $parser);
            
            // Cache successful response
            $this->cache_models($cache_key, $models);
            
            return $models;

        } catch (Exception $e) {
            // Log error but don't fail completely
            error_log("AI HTTP Client: Failed to fetch models for {$provider_name}: " . $e->getMessage());
            
            // Return cached fallback or empty array
            $fallback = $this->get_cached_models($cache_key, true); // ignore expiration
            return $fallback !== false ? $fallback : array();
        }
    }

    /**
     * Fetch models from API endpoint
     *
     * @param string $api_endpoint API URL
     * @param array $headers Request headers
     * @param callable $parser Response parser function
     * @return array Parsed models
     */
    private function fetch_from_api($api_endpoint, $headers, $parser) {
        $default_headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
        );

        $request_headers = array_merge($default_headers, $headers);

        $args = array(
            'method' => 'GET',
            'timeout' => 15, // Shorter timeout for model fetching
            'headers' => $request_headers
        );

        $response = wp_remote_get($api_endpoint, $args);

        if (is_wp_error($response)) {
            throw new Exception('HTTP Request failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 400) {
            throw new Exception("HTTP {$response_code} error from models API");
        }

        $decoded_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from models API');
        }

        // Use custom parser if provided, otherwise return raw data
        if (is_callable($parser)) {
            return call_user_func($parser, $decoded_response);
        }

        return $decoded_response;
    }

    /**
     * Get cached models
     *
     * @param string $cache_key Cache key
     * @param bool $ignore_expiration Ignore cache expiration
     * @return array|false Cached models or false if not found/expired
     */
    private function get_cached_models($cache_key, $ignore_expiration = false) {
        if (!function_exists('get_transient')) {
            return false;
        }

        $cached = get_transient($cache_key);
        
        if ($cached === false) {
            return false;
        }

        // Check if we should ignore expiration (for fallback scenarios)
        if ($ignore_expiration) {
            return isset($cached['models']) ? $cached['models'] : false;
        }

        // Check if cache is still valid
        if (isset($cached['timestamp']) && 
            (time() - $cached['timestamp']) < $this->cache_duration) {
            return isset($cached['models']) ? $cached['models'] : false;
        }

        return false;
    }

    /**
     * Cache models
     *
     * @param string $cache_key Cache key
     * @param array $models Models to cache
     */
    private function cache_models($cache_key, $models) {
        if (!function_exists('set_transient')) {
            return;
        }

        $cache_data = array(
            'models' => $models,
            'timestamp' => time()
        );

        // Cache for 2x the duration to allow for fallback scenarios
        set_transient($cache_key, $cache_data, $this->cache_duration * 2);
    }

    /**
     * Clear cached models for a provider
     *
     * @param string $provider_name Provider name
     */
    public function clear_cache($provider_name) {
        if (!function_exists('delete_transient')) {
            return;
        }

        $cache_key = $this->cache_prefix . $provider_name;
        delete_transient($cache_key);
    }

    /**
     * Clear all cached models
     */
    public function clear_all_cache() {
        if (!function_exists('delete_transient')) {
            return;
        }

        $providers = array('openai', 'anthropic', 'gemini', 'grok', 'openrouter');
        
        foreach ($providers as $provider) {
            $this->clear_cache($provider);
        }
    }

    /**
     * Set cache duration
     *
     * @param int $seconds Cache duration in seconds
     */
    public function set_cache_duration($seconds) {
        $this->cache_duration = max(300, intval($seconds)); // Minimum 5 minutes
    }

    /**
     * Get cache duration
     *
     * @return int Cache duration in seconds
     */
    public function get_cache_duration() {
        return $this->cache_duration;
    }

    /**
     * Force refresh models for a provider
     *
     * @param string $provider_name Provider name
     * @param string $api_endpoint API endpoint
     * @param array $headers Request headers
     * @param callable $parser Response parser
     * @return array Fresh models list
     */
    public function refresh_models($provider_name, $api_endpoint, $headers = array(), $parser = null) {
        $this->clear_cache($provider_name);
        return $this->fetch_models($provider_name, $api_endpoint, $headers, $parser);
    }
}