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
     * API key for authentication
     * @var string
     */
    private $api_key;

    /**
     * Initialize provider-specific settings
     * Override in child classes
     */
    protected function init() {
        $this->api_key = $this->get_config('api_key', '');
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
        if (!$this->is_configured()) {
            return array();
        }

        try {
            // Fetch live models from Grok API using dedicated module
            return AI_HTTP_Grok_Model_Fetcher::fetch_models(
                $this->base_url,
                $this->get_auth_headers()
            );

        } catch (Exception $e) {
            // Return empty array if API call fails - no fallbacks
            return array();
        }
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
     * Get authentication headers for API requests
     *
     * @return array Authentication headers
     */
    protected function get_auth_headers() {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key
        );
    }

    /**
     * Get the API endpoint URL for this provider
     *
     * @return string API endpoint URL
     */
    protected function get_api_endpoint() {
        return $this->base_url . '/chat/completions';
    }

    /**
     * Get model-specific configuration
     *
     * @param string $model Model name
     * @return array Model configuration
     */
    public function get_model_config($model = null) {
        
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

    /**
     * Continue conversation with tool results using OpenAI-compatible message history pattern
     * Grok uses OpenAI-compatible API, so we rebuild conversation with standard tool messages
     *
     * @param array $conversation_history Previous conversation messages
     * @param array $tool_results Array of tool results to continue with
     * @param callable|null $callback Completion callback for streaming
     * @return array Response from continuation request
     */
    public function continue_with_tool_results($conversation_history, $tool_results, $callback = null) {
        if (empty($conversation_history)) {
            throw new Exception('Conversation history is required for Grok continuation');
        }
        
        if (empty($tool_results)) {
            throw new Exception('Tool results are required for continuation');
        }
        
        // Rebuild conversation with tool results in OpenAI-compatible format
        $messages = $this->rebuild_openai_compatible_conversation($conversation_history, $tool_results);
        
        // Create continuation request
        $continuation_request = array(
            'messages' => $messages,
            'max_tokens' => 1000 // Default, can be overridden
        );
        
        if ($callback) {
            return $this->send_streaming_request($continuation_request, $callback);
        } else {
            return $this->send_request($continuation_request);
        }
    }

    /**
     * Rebuild conversation history with tool results in OpenAI-compatible format
     * Converts standardized tool results to OpenAI-style tool messages
     *
     * @param array $conversation_history Original conversation messages
     * @param array $tool_results Tool execution results
     * @return array Rebuilt conversation with tool results
     */
    private function rebuild_openai_compatible_conversation($conversation_history, $tool_results) {
        $messages = array();
        
        // Add all conversation history
        foreach ($conversation_history as $message) {
            $messages[] = array(
                'role' => $message['role'],
                'content' => $message['content']
            );
        }
        
        // Add tool result messages in OpenAI format
        foreach ($tool_results as $result) {
            $messages[] = array(
                'role' => 'tool',
                'tool_call_id' => $result['tool_call_id'],
                'content' => $result['content']
            );
        }
        
        return $messages;
    }

    /**
     * Get the last response ID (not applicable for Grok - uses conversation rebuilding)
     * Grok doesn't have response IDs like OpenAI Responses API, so this returns null
     *
     * @return null Always returns null for Grok
     */
    public function get_last_response_id() {
        return null;
    }

    /**
     * Set the last response ID (not applicable for Grok)
     * Grok doesn't use response IDs, so this is a no-op
     *
     * @param string $response_id Response ID (ignored)
     */
    public function set_last_response_id($response_id) {
        // Grok doesn't use response IDs - no-op
    }
}