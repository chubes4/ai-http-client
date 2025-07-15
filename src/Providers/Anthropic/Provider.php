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

    protected function get_api_endpoint($model = null) {
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

    /**
     * Continue conversation with tool results using Anthropic's message history pattern
     * Anthropic handles continuation by rebuilding the conversation with tool_use and tool_result content blocks
     *
     * @param array $conversation_history Previous conversation messages
     * @param array $tool_results Array of tool results to continue with
     * @param callable|null $callback Completion callback for streaming
     * @return array Response from continuation request
     */
    public function continue_with_tool_results($conversation_history, $tool_results, $callback = null) {
        if (empty($conversation_history)) {
            throw new Exception('Conversation history is required for Anthropic continuation');
        }
        
        if (empty($tool_results)) {
            throw new Exception('Tool results are required for continuation');
        }
        
        // Rebuild conversation with tool results
        $messages = $this->rebuild_conversation_with_tool_results($conversation_history, $tool_results);
        
        // Create continuation request
        $continuation_request = array(
            'messages' => $messages,
            'max_tokens' => 1000 // Default, can be overridden
        );
        
        // Extract system message if present in original conversation
        $continuation_request = $this->extract_system_message($continuation_request);
        
        if ($callback) {
            return $this->send_streaming_request($continuation_request, $callback);
        } else {
            return $this->send_request($continuation_request);
        }
    }

    /**
     * Rebuild conversation history with tool results in Anthropic format
     * Converts standardized tool results to Anthropic's tool_use/tool_result content blocks
     *
     * @param array $conversation_history Original conversation messages
     * @param array $tool_results Tool execution results
     * @return array Rebuilt conversation with tool results
     */
    private function rebuild_conversation_with_tool_results($conversation_history, $tool_results) {
        $messages = array();
        
        // Process conversation history
        foreach ($conversation_history as $message) {
            if (isset($message['role']) && $message['role'] === 'system') {
                // System messages will be extracted separately
                continue;
            }
            
            // Add regular message
            $messages[] = array(
                'role' => $message['role'],
                'content' => $message['content']
            );
        }
        
        // Find the last assistant message and add tool results
        $last_message_index = count($messages) - 1;
        if ($last_message_index >= 0 && $messages[$last_message_index]['role'] === 'assistant') {
            // Convert last assistant message content to array format if it's a string
            $last_content = $messages[$last_message_index]['content'];
            if (is_string($last_content)) {
                $messages[$last_message_index]['content'] = array(
                    array(
                        'type' => 'text',
                        'text' => $last_content
                    )
                );
            }
            
            // Add tool_use blocks for each tool call that was made
            foreach ($tool_results as $result) {
                if (isset($result['tool_call_id']) && isset($result['tool_name'])) {
                    // Add the tool_use block to assistant message
                    $messages[$last_message_index]['content'][] = array(
                        'type' => 'tool_use',
                        'id' => $result['tool_call_id'],
                        'name' => $result['tool_name'],
                        'input' => $result['tool_input'] ?? array()
                    );
                }
            }
        }
        
        // Add user message with tool results
        $tool_result_content = array();
        foreach ($tool_results as $result) {
            $tool_result_content[] = array(
                'type' => 'tool_result',
                'tool_use_id' => $result['tool_call_id'],
                'content' => $result['content']
            );
        }
        
        if (!empty($tool_result_content)) {
            $messages[] = array(
                'role' => 'user',
                'content' => $tool_result_content
            );
        }
        
        return $messages;
    }

    /**
     * Get the last response ID (not applicable for Anthropic - uses conversation rebuilding)
     * Anthropic doesn't have response IDs like OpenAI, so this returns null
     *
     * @return null Always returns null for Anthropic
     */
    public function get_last_response_id() {
        return null;
    }

    /**
     * Set the last response ID (not applicable for Anthropic)
     * Anthropic doesn't use response IDs, so this is a no-op
     *
     * @param string $response_id Response ID (ignored)
     */
    public function set_last_response_id($response_id) {
        // Anthropic doesn't use response IDs - no-op
    }


}