<?php
/**
 * AI HTTP Client - Gemini Model Fetcher
 * 
 * Single Responsibility: Handle ONLY Gemini model fetching and parsing
 * Pure dynamic fetching with no fallbacks or name mapping
 *
 * @package AIHttpClient\Providers\Gemini
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Gemini_Model_Fetcher {

    /**
     * Fetch available models from Google Gemini API
     *
     * @param string $base_url Gemini API base URL
     * @param array $auth_headers Authentication headers
     * @return array Available models list
     * @throws Exception If API call fails
     */
    public static function fetch_models($base_url, $auth_headers) {
        $models_url = $base_url . '/models';
        
        $args = array(
            'method' => 'GET',
            'timeout' => 10,
            'headers' => array_merge(
                array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
                ),
                $auth_headers
            )
        );

        $response = wp_remote_get($models_url, $args);

        if (is_wp_error($response)) {
            throw new Exception('Model fetch failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new Exception('Model fetch failed with HTTP ' . $response_code);
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Gemini models API');
        }

        return self::parse_models_response($data);
    }

    /**
     * Parse Gemini models API response
     *
     * @param array $response Raw API response
     * @return array Parsed models list (model_id => model_id)
     */
    public static function parse_models_response($response) {
        $models = array();

        if (!isset($response['models']) || !is_array($response['models'])) {
            return $models;
        }

        foreach ($response['models'] as $model) {
            if (!isset($model['name'])) {
                continue;
            }

            $model_name = $model['name'];
            
            // Extract model ID from full name (e.g., "models/gemini-pro" -> "gemini-pro")
            $model_id = str_replace('models/', '', $model_name);
            
            // Only include generative models
            if (self::is_generative_model($model_id)) {
                $models[$model_id] = $model_id;
            }
        }

        return $models;
    }

    /**
     * Check if model ID is a generative model
     *
     * @param string $model_id Model ID
     * @return bool True if it's a generative model
     */
    private static function is_generative_model($model_id) {
        $generative_patterns = array('gemini-', 'gemini-pro', 'gemini-flash');
        $exclude_patterns = array('embedding', 'vision', 'text-embedding');

        // Exclude non-generative models
        foreach ($exclude_patterns as $pattern) {
            if (strpos($model_id, $pattern) !== false) {
                return false;
            }
        }

        // Include known generative model patterns
        foreach ($generative_patterns as $pattern) {
            if (strpos($model_id, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if model fetching is available
     *
     * @return bool True if model fetching is supported
     */
    public static function is_model_fetching_available() {
        return true;
    }
}