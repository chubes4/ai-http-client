<?php
/**
 * AI HTTP Client - Anthropic Provider
 * 
 * Single Responsibility: Handle Anthropic Claude API communication
 * Supports Messages API with system prompts and function calling.
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Anthropic_Provider extends AI_HTTP_Provider_Base {

    protected $provider_name = 'anthropic';
    
    private $api_key;
    private $base_url = 'https://api.anthropic.com/v1';
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
        
        $url = $this->get_api_endpoint();
        
        return $this->make_request($url, $request);
    }

    public function send_streaming_request($request, $callback) {
        $request = $this->sanitize_request($request);
        
        $url = $this->get_api_endpoint();
        
        return AI_HTTP_Anthropic_Streaming_Module::send_streaming_request(
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
            // Anthropic doesn't have a models endpoint, ModelFetcher will throw exception
            return AI_HTTP_Anthropic_Model_Fetcher::fetch_models(
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
                'message' => 'Anthropic API key not configured'
            );
        }

        try {
            $test_request = array(
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 5,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Test connection'
                    )
                )
            );

            $response = $this->send_request($test_request);
            
            return array(
                'success' => true,
                'message' => 'Successfully connected to Anthropic API',
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
        return $this->base_url . '/messages';
    }

    protected function get_auth_headers() {
        return array(
            'x-api-key' => $this->api_key,
            'anthropic-version' => '2023-06-01'
        );
    }

    /**
     * Anthropic-specific request sanitization
     *
     * @param array $request Request data
     * @return array Sanitized request
     */
    protected function sanitize_request($request) {
        $request = parent::sanitize_request($request);

        // Model will be set by automatic model detection if not provided

        // Anthropic requires max_tokens
        if (!isset($request['max_tokens'])) {
            $request['max_tokens'] = 1000;
        }

        // Validate temperature (0.0 to 1.0 for Anthropic)
        if (isset($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }

        // Validate max_tokens
        if (isset($request['max_tokens'])) {
            $request['max_tokens'] = max(1, min(4096, intval($request['max_tokens'])));
        }

        // Validate top_p
        if (isset($request['top_p'])) {
            $request['top_p'] = max(0, min(1, floatval($request['top_p'])));
        }

        // Handle system prompts - Anthropic uses separate system field
        $request = $this->extract_system_message($request);

        // Handle function calling tools
        if (isset($request['tools']) && is_array($request['tools'])) {
            $request['tools'] = AI_HTTP_Anthropic_Function_Calling::sanitize_tools($request['tools']);
        }

        // Handle tool choice
        if (isset($request['tool_choice'])) {
            $request['tool_choice'] = AI_HTTP_Anthropic_Function_Calling::validate_tool_choice($request['tool_choice']);
        }

        return $request;
    }

    /**
     * Extract system message from messages array to system field
     *
     * @param array $request Request data
     * @return array Request with system message extracted
     */
    private function extract_system_message($request) {
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $system_content = '';
        $filtered_messages = array();

        foreach ($request['messages'] as $message) {
            if (isset($message['role']) && $message['role'] === 'system') {
                $system_content .= $message['content'] . "\n";
            } else {
                $filtered_messages[] = $message;
            }
        }

        if (!empty($system_content)) {
            $request['system'] = trim($system_content);
        }

        $request['messages'] = $filtered_messages;

        return $request;
    }


}