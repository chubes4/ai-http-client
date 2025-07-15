<?php
/**
 * AI HTTP Client - OpenRouter Model Fetcher
 * 
 * Single Responsibility: Handle ONLY OpenRouter model fetching and parsing
 * Pure dynamic fetching with no fallbacks or name mapping
 *
 * @package AIHttpClient\Providers\OpenRouter
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenRouter_Model_Fetcher {

    /**
     * Fetch available models from OpenRouter API
     *
     * @param string $base_url OpenRouter API base URL
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
            throw new Exception('Invalid JSON response from OpenRouter models API');
        }

        return self::parse_models_response($data);
    }

    /**
     * Parse OpenRouter models API response
     *
     * @param array $response Raw API response
     * @return array Parsed models list (model_id => model_id)
     */
    public static function parse_models_response($response) {
        $models = array();

        if (!isset($response['data']) || !is_array($response['data'])) {
            return $models;
        }

        foreach ($response['data'] as $model) {
            if (!isset($model['id'])) {
                continue;
            }

            $model_id = $model['id'];
            
            // Include all models - OpenRouter provides access to many different providers
            $models[$model_id] = $model_id;
        }

        return $models;
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