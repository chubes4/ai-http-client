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

            // Use configured model or fail if not set
            $model = $this->config['model'] ?? null;
            if (!$model) {
                return array(
                    'success' => false,
                    'message' => 'No model configured for Gemini provider'
                );
            }
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

    protected function get_api_endpoint($model = null) {
        return $this->base_url . '/models/' . $model . ':generateContent';
    }

    protected function get_streaming_api_endpoint($model = null) {
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

        // Model must be explicitly provided - no defaults
        if (!isset($request['model'])) {
            throw new Exception('Model parameter is required for Gemini requests');
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
     * Continue conversation with tool results using Gemini's contents history pattern
     * Gemini handles continuation by rebuilding the conversation with functionCall and functionResponse parts
     *
     * @param array $conversation_history Previous conversation messages
     * @param array $tool_results Array of tool results to continue with
     * @param callable|null $callback Completion callback for streaming
     * @return array Response from continuation request
     */
    public function continue_with_tool_results($conversation_history, $tool_results, $callback = null) {
        if (empty($conversation_history)) {
            throw new Exception('Conversation history is required for Gemini continuation');
        }
        
        if (empty($tool_results)) {
            throw new Exception('Tool results are required for continuation');
        }
        
        // Rebuild conversation with tool results in Gemini format
        $contents = $this->rebuild_gemini_conversation_with_tool_results($conversation_history, $tool_results);
        
        // Create continuation request in Gemini format
        $continuation_request = array(
            'contents' => $contents,
            'generationConfig' => array(
                'maxOutputTokens' => 1000 // Default, can be overridden
            )
        );
        
        // Add tools if they were in the original conversation
        if ($this->conversation_has_tools($conversation_history)) {
            $continuation_request['tools'] = $this->extract_tools_from_conversation($conversation_history);
        }
        
        if ($callback) {
            return $this->send_streaming_request($continuation_request, $callback);
        } else {
            return $this->send_request($continuation_request);
        }
    }

    /**
     * Rebuild conversation history with tool results in Gemini format
     * Converts standardized tool results to Gemini's functionCall/functionResponse format
     *
     * @param array $conversation_history Original conversation messages
     * @param array $tool_results Tool execution results
     * @return array Rebuilt conversation in Gemini contents format
     */
    private function rebuild_gemini_conversation_with_tool_results($conversation_history, $tool_results) {
        $contents = array();
        
        // Convert conversation history to Gemini contents format
        foreach ($conversation_history as $message) {
            if (isset($message['role']) && $message['role'] === 'system') {
                // System messages are handled separately in Gemini - skip for now
                continue;
            }
            
            $role = $message['role'] === 'assistant' ? 'model' : 'user';
            
            $contents[] = array(
                'role' => $role,
                'parts' => array(
                    array('text' => $message['content'])
                )
            );
        }
        
        // Find the last model message and add functionCall parts
        $last_content_index = count($contents) - 1;
        if ($last_content_index >= 0 && $contents[$last_content_index]['role'] === 'model') {
            // Add functionCall parts for each tool that was called
            foreach ($tool_results as $result) {
                if (isset($result['tool_call_id']) && isset($result['tool_name'])) {
                    $contents[$last_content_index]['parts'][] = array(
                        'functionCall' => array(
                            'name' => $result['tool_name'],
                            'args' => $result['tool_input'] ?? array()
                        )
                    );
                }
            }
        }
        
        // Add user message with functionResponse parts
        $function_response_parts = array();
        foreach ($tool_results as $result) {
            $function_response_parts[] = array(
                'functionResponse' => array(
                    'name' => $result['tool_name'],
                    'response' => array(
                        'content' => $result['content']
                    )
                )
            );
        }
        
        if (!empty($function_response_parts)) {
            $contents[] = array(
                'role' => 'user',
                'parts' => $function_response_parts
            );
        }
        
        return $contents;
    }

    /**
     * Check if conversation history contains tools
     *
     * @param array $conversation_history Conversation messages
     * @return bool True if tools were used in conversation
     */
    private function conversation_has_tools($conversation_history) {
        // This is a simplified check - in practice, you'd want to track if tools were used
        // For now, assume if we're doing continuation, tools were probably involved
        return true;
    }

    /**
     * Extract tools from conversation history (placeholder)
     * In practice, you'd need to store and retrieve the original tool schemas
     *
     * @param array $conversation_history Conversation messages
     * @return array Tool schemas
     */
    private function extract_tools_from_conversation($conversation_history) {
        // Placeholder - in practice, tool schemas should be stored with conversation
        // For now, return empty array and let the calling code provide tools
        return array();
    }

    /**
     * Get the last response ID (not applicable for Gemini - uses conversation rebuilding)
     * Gemini doesn't have response IDs like OpenAI, so this returns null
     *
     * @return null Always returns null for Gemini
     */
    public function get_last_response_id() {
        return null;
    }

    /**
     * Set the last response ID (not applicable for Gemini)
     * Gemini doesn't use response IDs, so this is a no-op
     *
     * @param string $response_id Response ID (ignored)
     */
    public function set_last_response_id($response_id) {
        // Gemini doesn't use response IDs - no-op
    }

}