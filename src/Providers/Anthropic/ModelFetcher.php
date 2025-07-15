<?php
/**
 * AI HTTP Client - Anthropic Model Fetcher
 * 
 * Single Responsibility: Handle ONLY Anthropic model fetching and parsing
 * Pure dynamic fetching with no fallbacks or name mapping
 *
 * @package AIHttpClient\Providers\Anthropic
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Anthropic_Model_Fetcher {

    /**
     * Fetch available models from Anthropic
     * Note: Anthropic doesn't provide a models API, so this throws an exception
     *
     * @param string $base_url Anthropic API base URL
     * @param array $auth_headers Authentication headers
     * @return array Available models list
     * @throws Exception Always throws since Anthropic has no models API
     */
    public static function fetch_models($base_url, $auth_headers) {
        // Anthropic doesn't provide a models endpoint
        throw new Exception('Anthropic does not provide a models API endpoint');
    }

    /**
     * Check if model fetching is available
     *
     * @return bool False - Anthropic doesn't provide models API
     */
    public static function is_model_fetching_available() {
        return false;
    }
}