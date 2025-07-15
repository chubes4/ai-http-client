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
            return $this->get_fallback_models();
        }

        try {
            // Gemini has a models list endpoint
            $models = $this->model_fetcher->fetch_models(
                'gemini',
                $this->base_url . '/models',
                $this->get_auth_headers(),
                array($this, 'parse_models_response')
            );

            return !empty($models) ? $models : $this->get_fallback_models();

        } catch (Exception $e) {
            // Return fallback models if API call fails
            return $this->get_fallback_models();
        }
    }

    /**
     * Parse Gemini models API response
     *
     * @param array $response Raw API response
     * @return array Parsed models list
     */
    public function parse_models_response($response) {
        $models = array();

        if (!isset($response['models']) || !is_array($response['models'])) {
            return $this->get_fallback_models();
        }

        foreach ($response['models'] as $model) {
            if (!isset($model['name'])) {
                continue;
            }

            // Extract model ID from full name (e.g., "models/gemini-2.0-flash" -> "gemini-2.0-flash")
            $model_id = str_replace('models/', '', $model['name']);
            
            // Only include generative models (filter out embedding models)
            if ($this->is_generative_model($model_id)) {
                $models[$model_id] = $this->get_model_display_name($model_id);
            }
        }

        // Ensure we have some models, fallback if API returned empty
        return !empty($models) ? $models : $this->get_fallback_models();
    }

    /**
     * Check if model ID is a generative model
     *
     * @param string $model_id Model ID
     * @return bool True if it's a generative model
     */
    private function is_generative_model($model_id) {
        $generative_patterns = array('gemini-', 'bison-', 'chat-bison');
        $exclude_patterns = array('embedding', 'aqa');

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
     * Get display name for model
     *
     * @param string $model_id Model ID
     * @return string Display name
     */
    private function get_model_display_name($model_id) {
        $display_names = array(
            'gemini-2.0-flash' => 'Gemini 2.0 Flash',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro',
            'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B',
            'gemini-pro' => 'Gemini Pro',
            'gemini-pro-vision' => 'Gemini Pro Vision'
        );

        return isset($display_names[$model_id]) ? $display_names[$model_id] : ucfirst(str_replace('-', ' ', $model_id));
    }

    /**
     * Get fallback models when API is unavailable
     *
     * @return array Fallback models list
     */
    private function get_fallback_models() {
        return array(
            'gemini-2.0-flash' => 'Gemini 2.0 Flash',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro',
            'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B'
        );
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

    /**
     * Get pricing information for Gemini models
     *
     * @param string $model Model name
     * @return array Pricing info
     */
    public function get_model_pricing($model = null) {
        $pricing = array(
            'gemini-2.0-flash' => array(
                'input' => 0.00015,   // per 1K tokens (estimated)
                'output' => 0.0006
            ),
            'gemini-2.5-flash' => array(
                'input' => 0.00015,
                'output' => 0.0006
            ),
            'gemini-1.5-flash' => array(
                'input' => 0.000075,
                'output' => 0.0003
            ),
            'gemini-1.5-flash-8b' => array(
                'input' => 0.0000375,
                'output' => 0.00015
            ),
            'gemini-1.5-pro' => array(
                'input' => 0.00125,
                'output' => 0.005
            )
        );

        if ($model) {
            return isset($pricing[$model]) ? $pricing[$model] : null;
        }

        return $pricing;
    }
}