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
        
        // Use completion callback for tool processing
        $completion_callback = function($full_response) use ($callback) {
            // Process tool calls if any were found in the response
            $tool_calls = $this->extract_tool_calls($full_response);
            
            if (!empty($tool_calls)) {
                // Send tool results as SSE events
                foreach ($tool_calls as $tool_call) {
                    $tool_result = [
                        'tool_call_id' => $tool_call['id'],
                        'tool_name' => $tool_call['function']['name'],
                        'arguments' => $tool_call['function']['arguments'],
                        'provider' => 'anthropic'
                    ];
                    
                    echo "event: tool_result\n";
                    echo "data: " . wp_json_encode($tool_result) . "\n\n";
                    
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }
            
            // Indicate completion
            if (is_callable($callback)) {
                call_user_func($callback, "data: [DONE]\n\n");
            }
        };
        
        return AI_HTTP_Streaming_Client::stream_post(
            $url,
            $request,
            array_merge(
                array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
                ),
                $this->get_auth_headers()
            ),
            $completion_callback,
            $this->timeout
        );
    }

    public function get_available_models() {
        if (!$this->is_configured()) {
            return $this->get_fallback_models();
        }

        // Anthropic doesn't have a models endpoint, return known models
        return $this->get_fallback_models();
    }

    /**
     * Get fallback models for Anthropic
     *
     * @return array Fallback models list
     */
    private function get_fallback_models() {
        return array(
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
            'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku',
            'claude-3-opus-20240229' => 'Claude 3 Opus',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku'
        );
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

        // Ensure required fields
        if (!isset($request['model'])) {
            $request['model'] = 'claude-3-haiku-20240307';
        }

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

    /**
     * Get pricing information for Anthropic models
     *
     * @param string $model Model name
     * @return array Pricing info
     */
    public function get_model_pricing($model = null) {
        $pricing = array(
            'claude-3-5-sonnet-20241022' => array(
                'input' => 0.003,   // per 1K tokens
                'output' => 0.015
            ),
            'claude-3-5-haiku-20241022' => array(
                'input' => 0.0008,
                'output' => 0.004
            ),
            'claude-3-opus-20240229' => array(
                'input' => 0.015,
                'output' => 0.075
            ),
            'claude-3-sonnet-20240229' => array(
                'input' => 0.003,
                'output' => 0.015
            ),
            'claude-3-haiku-20240307' => array(
                'input' => 0.00025,
                'output' => 0.00125
            )
        );

        if ($model) {
            return isset($pricing[$model]) ? $pricing[$model] : null;
        }

        return $pricing;
    }

    /**
     * Extract tool calls from Anthropic streaming response
     *
     * @param string $full_response Complete streaming response
     * @return array Tool calls found in response
     */
    private function extract_tool_calls($full_response) {
        $tool_calls = array();
        
        // Parse SSE events for Anthropic
        $event_blocks = explode("\n\n", trim($full_response));
        
        foreach ($event_blocks as $block) {
            if (empty(trim($block))) {
                continue;
            }
            
            $lines = explode("\n", $block);
            $current_data = '';
            
            foreach ($lines as $line) {
                if (preg_match('/^data: (.+)$/', trim($line), $matches)) {
                    $current_data .= trim($matches[1]);
                }
            }
            
            if (!empty($current_data)) {
                $decoded = json_decode($current_data, true);
                if ($decoded && isset($decoded['content'])) {
                    foreach ($decoded['content'] as $content_block) {
                        if (isset($content_block['type']) && $content_block['type'] === 'tool_use') {
                            $tool_calls[] = array(
                                'id' => $content_block['id'] ?? uniqid('tool_'),
                                'type' => 'function',
                                'function' => array(
                                    'name' => $content_block['name'],
                                    'arguments' => wp_json_encode($content_block['input'] ?? array())
                                )
                            );
                        }
                    }
                }
            }
        }
        
        return $tool_calls;
    }
}