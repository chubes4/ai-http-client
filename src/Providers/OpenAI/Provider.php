<?php
/**
 * AI HTTP Client - OpenAI Provider
 * 
 * Single Responsibility: Handle OpenAI API communication
 * Supports Chat Completions API with streaming and function calling.
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenAI_Provider extends AI_HTTP_Provider_Base {

    protected $provider_name = 'openai';
    
    private $api_key;
    private $organization;
    private $base_url = 'https://api.openai.com/v1';
    private $model_fetcher;

    protected function init() {
        $this->api_key = $this->get_config('api_key');
        $this->organization = $this->get_config('organization');
        $this->model_fetcher = new AI_HTTP_Model_Fetcher();
        
        // Allow custom base URL for OpenAI-compatible APIs
        if ($this->get_config('base_url')) {
            $this->base_url = rtrim($this->get_config('base_url'), '/');
        }
    }

    public function send_request($request) {
        $request = $this->sanitize_request($request);
        
        $url = $this->get_api_endpoint();
        
        return $this->make_request($url, $request);
    }

    public function get_available_models() {
        if (!$this->is_configured()) {
            return $this->get_fallback_models();
        }

        try {
            // Fetch live models from OpenAI API
            $models = $this->model_fetcher->fetch_models(
                'openai',
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
     * Parse OpenAI models API response
     *
     * @param array $response Raw API response
     * @return array Parsed models list
     */
    public function parse_models_response($response) {
        $models = array();

        if (!isset($response['data']) || !is_array($response['data'])) {
            return $this->get_fallback_models();
        }

        foreach ($response['data'] as $model) {
            if (!isset($model['id'])) {
                continue;
            }

            $model_id = $model['id'];
            
            // Only include chat models (filter out embeddings, whisper, etc.)
            if ($this->is_chat_model($model_id)) {
                $models[$model_id] = $this->get_model_display_name($model_id);
            }
        }

        // Ensure we have some models, fallback if API returned empty
        return !empty($models) ? $models : $this->get_fallback_models();
    }

    /**
     * Check if model ID is a chat model
     *
     * @param string $model_id Model ID
     * @return bool True if it's a chat model
     */
    private function is_chat_model($model_id) {
        $chat_patterns = array('gpt-', 'gpt4', 'chatgpt');
        $exclude_patterns = array('embedding', 'whisper', 'tts', 'dall-e', 'davinci-edit');

        // Exclude non-chat models
        foreach ($exclude_patterns as $pattern) {
            if (strpos($model_id, $pattern) !== false) {
                return false;
            }
        }

        // Include known chat model patterns
        foreach ($chat_patterns as $pattern) {
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
            'gpt-4' => 'GPT-4',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K'
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
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
        );
    }

    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'OpenAI API key not configured'
            );
        }

        try {
            $test_request = array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Test connection'
                    )
                ),
                'max_tokens' => 5
            );

            $response = $this->send_request($test_request);
            
            return array(
                'success' => true,
                'message' => 'Successfully connected to OpenAI API',
                'model_used' => isset($response['model']) ? $response['model'] : 'unknown'
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

    protected function get_api_endpoint() {
        return $this->base_url . '/chat/completions';
    }

    protected function get_auth_headers() {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key
        );

        if (!empty($this->organization)) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        return $headers;
    }

    /**
     * OpenAI-specific request sanitization
     *
     * @param array $request Request data
     * @return array Sanitized request
     */
    protected function sanitize_request($request) {
        $request = parent::sanitize_request($request);

        // Ensure required fields
        if (!isset($request['model'])) {
            $request['model'] = 'gpt-3.5-turbo';
        }

        // Validate temperature
        if (isset($request['temperature'])) {
            $request['temperature'] = max(0, min(2, floatval($request['temperature'])));
        }

        // Validate max_tokens
        if (isset($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        // Validate top_p
        if (isset($request['top_p'])) {
            $request['top_p'] = max(0, min(1, floatval($request['top_p'])));
        }

        // Handle function calling
        if (isset($request['tools']) && is_array($request['tools'])) {
            $request['tools'] = $this->sanitize_tools($request['tools']);
        }

        return $request;
    }

    /**
     * Sanitize function/tool definitions
     *
     * @param array $tools Tools array
     * @return array Sanitized tools
     */
    private function sanitize_tools($tools) {
        $sanitized = array();

        foreach ($tools as $tool) {
            if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
                $sanitized[] = array(
                    'type' => 'function',
                    'function' => array(
                        'name' => sanitize_text_field($tool['function']['name']),
                        'description' => sanitize_textarea_field($tool['function']['description']),
                        'parameters' => $tool['function']['parameters'] // JSON schema - minimal sanitization
                    )
                );
            }
        }

        return $sanitized;
    }

    /**
     * Get pricing information for OpenAI models
     *
     * @param string $model Model name
     * @return array Pricing info
     */
    public function get_model_pricing($model = null) {
        $pricing = array(
            'gpt-4' => array(
                'input' => 0.03,   // per 1K tokens
                'output' => 0.06
            ),
            'gpt-4-turbo' => array(
                'input' => 0.01,
                'output' => 0.03
            ),
            'gpt-4o' => array(
                'input' => 0.005,
                'output' => 0.015
            ),
            'gpt-4o-mini' => array(
                'input' => 0.00015,
                'output' => 0.0006
            ),
            'gpt-3.5-turbo' => array(
                'input' => 0.0015,
                'output' => 0.002
            )
        );

        if ($model) {
            return isset($pricing[$model]) ? $pricing[$model] : null;
        }

        return $pricing;
    }
}