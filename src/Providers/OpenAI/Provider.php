<?php
/**
 * AI HTTP Client - OpenAI Provider
 * 
 * Single Responsibility: Handle OpenAI API communication
 * Uses Responses API (/v1/responses) as the primary endpoint.
 * Based on Wordsurf's working implementation.
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
    private $last_response_id;

    protected function init() {
        $this->api_key = $this->get_config('api_key');
        $this->organization = $this->get_config('organization');
        
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

    public function send_streaming_request($request, $callback = null) {
        error_log('AI HTTP Client OpenAI Provider: Starting simple streaming request');
        error_log('AI HTTP Client OpenAI Provider DEBUG: Request before sanitization: ' . json_encode($request));
        
        $request = $this->sanitize_request($request);
        error_log('AI HTTP Client OpenAI Provider DEBUG: Request after sanitization: ' . json_encode($request));
        $url = $this->get_api_endpoint();
        $headers = $this->get_auth_headers();
        
        if (empty($this->api_key)) {
            throw new Exception('OpenAI API key not configured');
        }
        
        // Use OpenAI StreamingModule which properly handles headers and Content-Type
        $full_response = AI_HTTP_OpenAI_Streaming_Module::send_streaming_request(
            $url,
            $request,
            $headers,
            null, // No completion callback - external processing
            $this->timeout
        );
        
        // If callback provided, call it with the full response (for backward compatibility)
        if ($callback && is_callable($callback)) {
            call_user_func($callback, $full_response);
        }
        
        return $full_response;
    }

    public function get_available_models() {
        if (!$this->is_configured()) {
            return array();
        }

        try {
            // Fetch live models from OpenAI API using dedicated module
            return AI_HTTP_OpenAI_Model_Fetcher::fetch_models(
                $this->base_url,
                $this->get_auth_headers()
            );

        } catch (Exception $e) {
            // Log error for debugging
            if (function_exists('error_log')) {
                error_log('AI HTTP Client: OpenAI model fetch failed: ' . $e->getMessage());
            }
            return array();
        }
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
                )
                // No max_tokens - let OpenAI use default (unlimited)
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
        $configured = !empty($this->api_key);
        
        if (!$configured && function_exists('error_log')) {
            error_log('AI HTTP Client: OpenAI provider not configured - missing API key');
        }
        
        return $configured;
    }

    protected function get_api_endpoint($model = null) {
        return $this->base_url . '/responses';
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
     * Define OpenAI Responses API parameter requirements
     */
    protected function get_parameter_requirements() {
        return array(
            'required' => array(), // No required parameters for OpenAI
            'optional' => array('temperature', 'max_tokens', 'top_p', 'tools', 'tool_choice'),
            'defaults' => array(
                // Only set defaults if parameter is provided but too low
                // Don't force parameters that aren't required
            ),
            'minimums' => array(
                'temperature' => 0,
                'max_tokens' => 16, // Responses API minimum
                'top_p' => 0
            ),
            'maximums' => array(
                'temperature' => 2,
                'top_p' => 1
            )
        );
    }

    /**
     * OpenAI Responses API specific request sanitization
     * Based on Wordsurf's working implementation
     *
     * @param array $request Request data
     * @return array Sanitized request
     */
    protected function sanitize_request($request) {
        $request = parent::sanitize_request($request);

        // Convert standard format to Responses API format
        if (isset($request['messages'])) {
            $request['input'] = $request['messages'];
            unset($request['messages']);
        }

        // Convert max_tokens to max_output_tokens for Responses API
        if (isset($request['max_tokens'])) {
            $request['max_output_tokens'] = $request['max_tokens'];
            unset($request['max_tokens']);
        }

        // Handle function calling
        if (isset($request['tools']) && is_array($request['tools'])) {
            $request['tools'] = AI_HTTP_OpenAI_Function_Calling::sanitize_tools($request['tools']);
        }

        // Handle tool choice
        if (isset($request['tool_choice'])) {
            $request['tool_choice'] = AI_HTTP_OpenAI_Function_Calling::validate_tool_choice($request['tool_choice']);
        }

        return $request;
    }


    /**
     * Continue conversation with tool results using OpenAI Responses API
     * Based on Wordsurf's continuation pattern
     *
     * @param string $response_id Previous response ID from OpenAI
     * @param array $tool_results Array of tool results to continue with
     * @param callable|null $callback Completion callback for streaming
     * @param array|null $continuation_data Additional continuation data (model, etc.)
     * @return string Full response from continuation request
     */
    public function continue_with_tool_results($response_id, $tool_results, $callback = null, $continuation_data = null) {
        if (empty($response_id)) {
            throw new Exception('Response ID is required for continuation');
        }
        
        // Format tool results as function_call_outputs for OpenAI Responses API
        $function_call_outputs = array();
        foreach ($tool_results as $result) {
            $function_call_outputs[] = array(
                'type' => 'function_call_output',
                'call_id' => $result['tool_call_id'],
                'output' => $result['content']
            );
        }
        
        $continuation_request = array(
            'previous_response_id' => $response_id,
            'input' => $function_call_outputs
        );
        
        // Add model parameter if available in continuation data
        if ($continuation_data && isset($continuation_data['model'])) {
            $continuation_request['model'] = $continuation_data['model'];
        } else {
            // Fallback to provider config if no model in continuation data
            $continuation_request['model'] = $this->config['model'] ?? 'gpt-4o-mini';
        }
        
        $url = $this->get_api_endpoint();
        
        if ($callback) {
            return AI_HTTP_OpenAI_Streaming_Module::send_streaming_request(
                $url,
                $continuation_request,
                $this->get_auth_headers(),
                $callback,
                $this->timeout
            );
        } else {
            return $this->make_request($url, $continuation_request);
        }
    }

    /**
     * Get the response ID from the last response
     * Used for continuation tracking
     *
     * @return string|null Response ID or null if not available
     */
    public function get_last_response_id() {
        // This will be set by the response normalizer after each request
        return $this->last_response_id ?? null;
    }

    /**
     * Set the response ID from a response
     * Called by response normalizer
     *
     * @param string $response_id Response ID to store
     */
    public function set_last_response_id($response_id) {
        $this->last_response_id = $response_id;
    }


}