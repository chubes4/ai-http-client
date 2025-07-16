<?php
/**
 * AI HTTP Client - OpenAI Model Fetcher
 * 
 * Single Responsibility: Handle ONLY OpenAI model fetching and parsing
 * Pure dynamic fetching with no fallbacks or name mapping
 *
 * @package AIHttpClient\Providers\OpenAI
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenAI_Model_Fetcher {

    /**
     * Fetch available models from OpenAI API
     *
     * @param string $base_url OpenAI API base URL
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
            throw new Exception('Invalid JSON response from OpenAI models API');
        }

        return self::parse_models_response($data);
    }

    /**
     * Parse OpenAI models API response
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
            
            // Only include chat models (filter out embeddings, whisper, etc.)
            if (self::is_chat_model($model_id)) {
                $models[$model_id] = $model_id;
            }
        }

        return $models;
    }

    /**
     * Check if model ID is a chat/reasoning model compatible with Responses API
     *
     * @param string $model_id Model ID
     * @return bool True if it's a compatible model
     */
    private static function is_chat_model($model_id) {
        // Responses API compatible models (2025)
        $chat_patterns = array(
            'gpt-',        // GPT-4o series, GPT-4.1 series
            'gpt4',        // Legacy GPT-4 variants
            'chatgpt',     // ChatGPT models
            'o1',          // o1 reasoning models
            'o3',          // o3 reasoning models  
            'o4'           // o4-mini reasoning models
        );
        
        $exclude_patterns = array('embedding', 'whisper', 'tts', 'dall-e', 'davinci-edit');

        // Exclude non-chat models
        foreach ($exclude_patterns as $pattern) {
            if (strpos($model_id, $pattern) !== false) {
                return false;
            }
        }

        // Include known chat/reasoning model patterns
        foreach ($chat_patterns as $pattern) {
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