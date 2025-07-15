<?php
/**
 * AI HTTP Client - OpenRouter Provider
 * 
 * Single Responsibility: Handle ONLY OpenRouter API communication
 * OpenRouter provides unified access to hundreds of AI models from different providers
 * Uses OpenAI-compatible API with automatic model routing and fallbacks
 *
 * @package AIHttpClient\Providers\OpenRouter
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenRouter_Provider extends AI_HTTP_Provider_Base {

    /**
     * Provider name
     * @var string
     */
    protected $name = 'openrouter';

    /**
     * Base URL for OpenRouter API
     * @var string
     */
    protected $base_url = 'https://openrouter.ai/api/v1';

    /**
     * API key for authentication
     * @var string
     */
    private $api_key;

    /**
     * HTTP Referer header (optional, for OpenRouter rankings)
     * @var string
     */
    private $http_referer;

    /**
     * App title header (optional, for OpenRouter rankings)
     * @var string
     */
    private $app_title;

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct($config = array()) {
        parent::__construct($config);
        
        $this->api_key = $config['api_key'] ?? '';
        $this->http_referer = $config['http_referer'] ?? '';
        $this->app_title = $config['app_title'] ?? 'AI HTTP Client';
        
        // Note: No default model - OpenRouter fetches available models dynamically
    }

    /**
     * Send request to OpenRouter API
     *
     * @param array $request Request data
     * @return array Response from API
     */
    public function send_request($request) {
        if (!$this->is_configured()) {
            return $this->error_response('OpenRouter provider is not configured');
        }

        // Normalize the request using OpenRouter request normalizer
        $normalizer = new AI_HTTP_OpenRouter_Request_Normalizer();
        $normalized_request = $normalizer->normalize($request);

        // Build the URL - OpenRouter uses OpenAI-compatible chat/completions endpoint
        $url = $this->base_url . '/chat/completions';

        // Prepare headers
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );

        // Add optional headers for OpenRouter rankings
        if (!empty($this->http_referer)) {
            $headers['HTTP-Referer'] = $this->http_referer;
        }

        if (!empty($this->app_title)) {
            $headers['X-Title'] = $this->app_title;
        }

        // Handle streaming requests
        if (isset($request['stream']) && $request['stream'] === true) {
            return $this->handle_streaming_request($url, $normalized_request, $headers);
        }

        // Send regular request
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => wp_json_encode($normalized_request),
            'timeout' => 120,
            'sslverify' => true
        ));

        // Normalize the response
        $response_normalizer = new AI_HTTP_OpenRouter_Response_Normalizer();
        return $response_normalizer->normalize($response);
    }

    /**
     * Handle streaming request
     *
     * @param string $url API endpoint URL
     * @param array $request Normalized request data
     * @param array $headers Request headers
     * @return string Streaming response
     */
    private function handle_streaming_request($url, $request, $headers) {
        // Ensure stream is enabled
        $request['stream'] = true;
        
        return AI_HTTP_OpenRouter_Streaming_Module::send_streaming_request(
            $url, 
            $request, 
            $headers
        );
    }

    /**
     * Get available models from OpenRouter API
     *
     * @return array Available models
     */
    public function get_available_models() {
        if (!$this->is_configured()) {
            return array();
        }

        // Use cached models if available
        $cached_models = get_transient('ai_http_openrouter_models');
        if ($cached_models !== false) {
            return $cached_models;
        }

        // Fetch models from OpenRouter API
        $models_url = $this->base_url . '/models';
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );

        $response = wp_remote_get($models_url, array(
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            error_log('OpenRouter models fetch error: ' . $response->get_error_message());
            return array();
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        if (!$decoded_response || !isset($decoded_response['data'])) {
            return array();
        }

        // Format models for our system
        $formatted_models = array();
        foreach ($decoded_response['data'] as $model) {
            if (isset($model['id']) && isset($model['name'])) {
                $formatted_models[$model['id']] = $model['name'];
            }
        }

        // Cache for 1 hour
        set_transient('ai_http_openrouter_models', $formatted_models, HOUR_IN_SECONDS);

        return $formatted_models;
    }

    /**
     * Check if provider is configured
     *
     * @return bool True if configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Get model-specific configuration
     *
     * @param string $model Model name
     * @return array Model configuration
     */
    public function get_model_config($model = null) {
        // OpenRouter normalizes capabilities across models
        $config = array(
            'max_tokens' => 4096,
            'supports_streaming' => true,
            'supports_function_calling' => true,
            'supports_vision' => false,
            'context_length' => 4096
        );

        // Since we don't hardcode models, return generic config
        // OpenRouter handles model-specific limitations internally
        return $config;
    }

    /**
     * Validate model supports feature
     *
     * @param string $model Model name
     * @param string $feature Feature to check
     * @return bool True if supported (OpenRouter handles compatibility)
     */
    public function model_supports_feature($model, $feature) {
        // OpenRouter abstracts model capabilities, so assume most features are supported
        switch ($feature) {
            case 'streaming':
                return true; // OpenRouter supports streaming for all models
            case 'function_calling':
                return true; // OpenRouter normalizes tool calling across providers
            case 'vision':
                // Would need to check model capabilities from API
                return false; // Conservative default
            default:
                return false;
        }
    }

    /**
     * Get provider-specific settings for admin
     *
     * @return array Settings configuration
     */
    public function get_admin_settings() {
        return array(
            'api_key' => array(
                'label' => 'OpenRouter API Key',
                'type' => 'password',
                'required' => true,
                'description' => 'Your OpenRouter API key from openrouter.ai'
            ),
            'http_referer' => array(
                'label' => 'HTTP Referer (Optional)',
                'type' => 'url',
                'required' => false,
                'description' => 'Your site URL for OpenRouter rankings'
            ),
            'app_title' => array(
                'label' => 'App Title (Optional)',
                'type' => 'text',
                'required' => false,
                'description' => 'Your app name for OpenRouter rankings',
                'default' => 'AI HTTP Client'
            )
        );
    }

    /**
     * Get provider display name
     *
     * @return string Display name
     */
    public function get_display_name() {
        return 'OpenRouter';
    }

    /**
     * Get provider description
     *
     * @return string Description
     */
    public function get_description() {
        return 'OpenRouter - Unified access to hundreds of AI models with automatic routing and fallbacks';
    }

    /**
     * Test API connection
     *
     * @return array Test result
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'API key not configured'
            );
        }

        // Test with a simple request using auto model selection
        $test_request = array(
            'messages' => array(
                array('role' => 'user', 'content' => 'Hello')
            ),
            'max_tokens' => 10
        );

        $response = $this->send_request($test_request);
        
        return array(
            'success' => $response['success'],
            'message' => $response['success'] ? 'Connection successful' : ($response['error'] ?? 'Unknown error')
        );
    }

    /**
     * Get credit information from OpenRouter
     *
     * @return array Credit information
     */
    public function get_credit_info() {
        if (!$this->is_configured()) {
            return array('error' => 'Not configured');
        }

        $auth_url = $this->base_url . '/auth/key';
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );

        $response = wp_remote_get($auth_url, array(
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        return $decoded_response ?: array('error' => 'Invalid response');
    }

    /**
     * Clear models cache
     *
     * @return bool True if cache cleared
     */
    public function clear_models_cache() {
        return delete_transient('ai_http_openrouter_models');
    }

    /**
     * Get generation stats for a specific generation ID
     *
     * @param string $generation_id Generation ID from response
     * @return array Generation statistics
     */
    public function get_generation_stats($generation_id) {
        if (!$this->is_configured() || empty($generation_id)) {
            return array('error' => 'Not configured or invalid generation ID');
        }

        $stats_url = $this->base_url . '/generation?id=' . urlencode($generation_id);
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );

        $response = wp_remote_get($stats_url, array(
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        return $decoded_response ?: array('error' => 'Invalid response');
    }
}