<?php
/**
 * AI HTTP Client - Google Gemini Provider
 * 
 * Single Responsibility: Handle Google Gemini API communication
 * Uses generativelanguage.googleapis.com API with streaming and function calling
 * Based on 2025 Gemini API documentation
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Gemini_Provider extends AI_HTTP_Provider_Base {

    protected $provider_name = 'gemini';
    
    private $api_key;
    private $base_url = 'https://generativelanguage.googleapis.com/v1beta';
    private $model_fetcher;

    protected function init() {
        $this->api_key = $this->get_config('api_key');
        $this->model_fetcher = new AI_HTTP_Model_Fetcher();
        
        // Allow custom base URL if needed
        if ($this->get_config('base_url')) {
            $this->base_url = rtrim($this->get_config('base_url'), '/');
        }
    }

    public function send_request($request) {
        $request = $this->sanitize_request($request);
        
        $url = $this->get_api_endpoint($request['model']);
        
        return $this->make_request($url, $request);
    }

    public function send_streaming_request($request, $callback) {
        $request = $this->sanitize_request($request);
        
        $url = $this->get_streaming_api_endpoint($request['model']);
        
        return AI_HTTP_Gemini_Streaming_Module::send_streaming_request(
            $url,
            $request,
            $this->get_auth_headers(),
            $callback,
            $this->timeout
        );
    }

    public function get_available_models() {
        if (!$this->is_configured()) {
            return array();
        }

        try {
            // Fetch live models from Gemini API using dedicated module
            return AI_HTTP_Gemini_Model_Fetcher::fetch_models(
                $this->base_url,
                $this->get_auth_headers()
            );

        } catch (Exception $e) {
            // Return empty array if API call fails - no fallbacks
            return array();
        }
    }


    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'Google Gemini API key not configured'
            );
        }

        try {
            $test_request = array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array('text' => 'Test connection')
                        )
                    )
                ),
                'generationConfig' => array(
                    'maxOutputTokens' => 5
                )
            );

            $model = 'gemini-1.5-flash';
            $url = $this->get_api_endpoint($model);
            $response = $this->make_request($url, $test_request);
            
            return array(
                'success' => true,
                'message' => 'Successfully connected to Google Gemini API',
                'model_used' => $model
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            );
        }
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    protected function get_api_endpoint($model) {
        return $this->base_url . '/models/' . $model . ':generateContent';
    }

    protected function get_streaming_api_endpoint($model) {
        return $this->base_url . '/models/' . $model . ':streamGenerateContent';
    }

    protected function get_auth_headers() {
        return array(
            'x-goog-api-key' => $this->api_key
        );
    }

    /**
     * Gemini-specific request sanitization
     *
     * @param array $request Request data
     * @return array Sanitized request
     */
    protected function sanitize_request($request) {
        $request = parent::sanitize_request($request);

        // Model will be set by automatic model detection if not provided
        if (!isset($request['model'])) {
            $request['model'] = 'gemini-1.5-flash';
        }

        // Convert standard format to Gemini format if needed
        if (isset($request['messages'])) {
            // This will be handled by RequestNormalizer
        }

        // Handle generation config parameters
        $generation_config = array();
        
        if (isset($request['temperature'])) {
            $generation_config['temperature'] = max(0, min(2, floatval($request['temperature'])));
        }

        if (isset($request['max_tokens'])) {
            $generation_config['maxOutputTokens'] = max(1, intval($request['max_tokens']));
        }

        if (isset($request['top_p'])) {
            $generation_config['topP'] = max(0, min(1, floatval($request['top_p'])));
        }

        if (isset($request['top_k'])) {
            $generation_config['topK'] = max(1, intval($request['top_k']));
        }

        if (!empty($generation_config)) {
            $request['generationConfig'] = $generation_config;
        }

        // Handle function calling tools
        if (isset($request['tools']) && is_array($request['tools'])) {
            $request['tools'] = AI_HTTP_Gemini_Function_Calling::sanitize_tools($request['tools']);
        }

        // Handle tool choice
        if (isset($request['tool_choice'])) {
            $request['tool_config'] = AI_HTTP_Gemini_Function_Calling::validate_tool_choice($request['tool_choice']);
            unset($request['tool_choice']); // Gemini uses tool_config instead
        }

        return $request;
    }

}