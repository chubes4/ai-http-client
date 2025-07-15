<?php
/**
 * AI HTTP Client - Grok/X.AI Provider
 * 
 * Single Responsibility: Handle ONLY Grok/X.AI API communication
 * Based on X.AI's API which is fully compatible with OpenAI format
 * Uses api.x.ai base URL and supports streaming, function calling, and vision
 *
 * @package AIHttpClient\Providers\Grok
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Grok_Provider extends AI_HTTP_Provider_Base {

    /**
     * Provider name
     * @var string
     */
    protected $name = 'grok';

    /**
     * Base URL for Grok API
     * @var string
     */
    protected $base_url = 'https://api.x.ai/v1';

    /**
     * Available models for Grok
     * @var array
     */
    protected $available_models = array(
        'grok-3' => 'Grok 3 - Latest flagship model with superior reasoning',
        'grok-3-fast' => 'Grok 3 Fast - Optimized for speed',
        'grok-3-mini' => 'Grok 3 Mini - Compact and efficient',
        'grok-3-mini-fast' => 'Grok 3 Mini Fast - Fastest option',
        'grok-2-1212' => 'Grok 2 - Previous generation',
        'grok-2-vision-1212' => 'Grok 2 Vision - Multi-modal capabilities',
        'grok-beta' => 'Grok Beta - Development version',
        'grok-vision-beta' => 'Grok Vision Beta - Vision capabilities'
    );

    /**
     * API key for authentication
     * @var string
     */
    private $api_key;

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct($config = array()) {
        parent::__construct($config);
        
        $this->api_key = $config['api_key'] ?? '';
        
        // Set default model if not provided
        if (empty($this->model)) {
            $this->model = 'grok-3';
        }
    }

    /**
     * Send request to Grok API
     *
     * @param array $request Request data
     * @return array Response from API
     */
    public function send_request($request) {
        if (!$this->is_configured()) {
            return $this->error_response('Grok provider is not configured');
        }

        // Normalize the request using Grok request normalizer
        $normalizer = new AI_HTTP_Grok_Request_Normalizer();
        $normalized_request = $normalizer->normalize($request);

        // Build the URL - Grok uses OpenAI-compatible chat/completions endpoint
        $url = $this->base_url . '/chat/completions';

        // Prepare headers
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );

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
        $response_normalizer = new AI_HTTP_Grok_Response_Normalizer();
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
        
        return AI_HTTP_Grok_Streaming_Module::send_streaming_request(
            $url, 
            $request, 
            $headers
        );
    }

    /**
     * Get available models
     *
     * @return array Available models
     */
    public function get_available_models() {
        return $this->available_models;
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
        $model = $model ?: $this->model;
        
        $config = array(
            'max_tokens' => 4096,
            'supports_streaming' => true,
            'supports_function_calling' => true,
            'supports_vision' => false,
            'context_length' => 128000
        );

        // Vision models support multi-modal
        if (strpos($model, 'vision') !== false) {
            $config['supports_vision'] = true;
        }

        // Different models have different capabilities
        switch ($model) {
            case 'grok-3':
                $config['max_tokens'] = 8192;
                $config['context_length'] = 131072;
                break;
            case 'grok-3-fast':
                $config['max_tokens'] = 4096;
                $config['context_length'] = 131072;
                break;
            case 'grok-3-mini':
            case 'grok-3-mini-fast':
                $config['max_tokens'] = 2048;
                $config['context_length'] = 131072;
                break;
            case 'grok-2-1212':
            case 'grok-2-vision-1212':
                $config['max_tokens'] = 4096;
                $config['context_length'] = 128000;
                break;
        }

        return $config;
    }

    /**
     * Validate model supports feature
     *
     * @param string $model Model name
     * @param string $feature Feature to check
     * @return bool True if supported
     */
    public function model_supports_feature($model, $feature) {
        $config = $this->get_model_config($model);
        
        switch ($feature) {
            case 'streaming':
                return $config['supports_streaming'] ?? false;
            case 'function_calling':
                return $config['supports_function_calling'] ?? false;
            case 'vision':
                return $config['supports_vision'] ?? false;
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
                'label' => 'X.AI API Key',
                'type' => 'password',
                'required' => true,
                'description' => 'Your X.AI API key from console.x.ai'
            ),
            'model' => array(
                'label' => 'Default Model',
                'type' => 'select',
                'options' => $this->available_models,
                'default' => 'grok-3'
            )
        );
    }

    /**
     * Get provider display name
     *
     * @return string Display name
     */
    public function get_display_name() {
        return 'Grok (X.AI)';
    }

    /**
     * Get provider description
     *
     * @return string Description
     */
    public function get_description() {
        return 'Grok AI by X.AI - Advanced reasoning, function calling, and vision capabilities';
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

        $test_request = array(
            'messages' => array(
                array('role' => 'user', 'content' => 'Hello')
            ),
            'model' => 'grok-3-mini',
            'max_tokens' => 10
        );

        $response = $this->send_request($test_request);
        
        return array(
            'success' => $response['success'],
            'message' => $response['success'] ? 'Connection successful' : ($response['error'] ?? 'Unknown error')
        );
    }
}