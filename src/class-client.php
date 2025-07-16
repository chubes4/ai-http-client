<?php
/**
 * AI HTTP Client - Main Orchestrator
 * 
 * Single Responsibility: Orchestrate the AI request pipeline
 * Acts as the "round plug" interface - standardized input/output
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Client {

    /**
     * Provider factory for creating provider instances
     */
    private $provider_factory;
    
    /**
     * Continuation manager for provider-agnostic continuation
     */
    private $continuation_manager;
    
    /**
     * Client configuration
     */
    private $config = array();

    /**
     * Constructor with dependency injection
     *
     * @param array $config Client configuration
     * @param AI_HTTP_Provider_Factory $provider_factory Optional factory injection
     */
    public function __construct($config = array(), $provider_factory = null) {
        // Set default configuration
        $this->config = wp_parse_args($config, array(
            'default_provider' => 'openai',
            'fallback_enabled' => true,
            'fallback_order' => array('openai', 'anthropic', 'openrouter'),
            'timeout' => 30,
            'retry_attempts' => 3,
            'logging_enabled' => false
        ));

        // Dependency injection with fallbacks
        $this->provider_factory = $provider_factory ?: new AI_HTTP_Provider_Factory(null, $this->config);
        
        // Initialize continuation manager for provider-agnostic continuation
        $this->continuation_manager = new AI_HTTP_Continuation_Manager($this->provider_factory, $this->config);
        
        // Normalizers are now handled by the factory on a per-provider basis
        // This follows SRP - each provider gets its own specialized normalizers
    }

    /**
     * Main pipeline: Send standardized request through the system
     * 
     * This is the "round plug" interface - accepts standardized input,
     * returns standardized output, hiding all internal complexity
     *
     * @param array $request Standardized "round plug" input
     * @param string $provider_name Optional specific provider to use
     * @return array Standardized "round plug" output
     */
    public function send_request($request, $provider_name = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        
        try {
            // Step 1: Validate the round plug input
            $this->validate_request($request);
            
            // Step 2: Try primary provider
            $response = $this->process_request($request, $provider_name);
            
            // Step 3: Try fallback providers if enabled and primary failed
            if (!$response['success'] && $this->config['fallback_enabled']) {
                $response = $this->try_fallback_providers($request, $provider_name);
            }
            
            // Step 4: Add metadata and return standardized response
            $response['provider'] = $provider_name;
            $response['success'] = isset($response['success']) ? $response['success'] : !isset($response['error']);
            
            return $response;
            
        } catch (Exception $e) {
            return $this->create_error_response($e->getMessage());
        }
    }

    /**
     * Send streaming request through the system
     * 
     * Real-time streaming version of send_request for chat applications
     * Streams directly to output buffer like Wordsurf for maximum compatibility
     *
     * @param array $request Standardized "round plug" input
     * @param string $provider_name Optional specific provider to use
     * @param callable $completion_callback Optional callback when streaming completes
     * @return string Full response from streaming request
     * @throws Exception If streaming is not supported or fails
     */
    public function send_streaming_request($request, $provider_name = null, $completion_callback = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];

        // Step 1: Validate the round plug input
        $this->validate_request($request);
        
        // Step 2: Create provider instance
        $provider = $this->provider_factory->create_provider($provider_name, $this->config);
        
        if (!$provider) {
            throw new Exception("Provider '{$provider_name}' not available");
        }
        
        if (!$provider->is_configured()) {
            throw new Exception("Provider '{$provider_name}' not configured");
        }
        
        // Step 3: Transform input through provider-specific request normalizer
        $request_normalizer = AI_HTTP_Normalizer_Factory::get_request_normalizer($provider_name);
        $provider_request = $request_normalizer->normalize($request);
        error_log('AI HTTP Client DEBUG: Request after normalization: ' . json_encode($provider_request));
        
        // Step 4: Send streaming request through provider (streams to output buffer)
        $full_response = $provider->send_streaming_request($provider_request, $completion_callback);
        
        // Step 5: Store continuation state for provider-agnostic continuation
        if ($full_response) {
            $this->continuation_manager->store_continuation_state($provider_name, $full_response, $provider_request);
        }
        
        return $full_response;
    }

    /**
     * Continue conversation with tool results (provider-agnostic)
     * 
     * This method provides a simplified interface that automatically handles
     * provider-specific continuation patterns behind the scenes:
     * - OpenAI: Uses response_id with Responses API continuation
     * - Anthropic: Uses conversation_history array
     * - Others: Use conversation_history rebuilding pattern
     *
     * @param array $tool_results Array of tool results to continue with
     * @param string $provider_name Optional specific provider to use
     * @param callable $completion_callback Optional callback for streaming
     * @return array|string Response from continuation request
     * @throws Exception If continuation is not supported or fails
     */
    public function continue_with_tool_results($tool_results, $provider_name = null, $completion_callback = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        
        // Use centralized continuation manager
        return $this->continuation_manager->continue_with_tool_results($provider_name, $tool_results, $completion_callback);
    }

    /**
     * Check if continuation is possible for current provider
     * 
     * @param string $provider_name Optional specific provider to use
     * @return bool True if continuation state exists
     */
    public function can_continue($provider_name = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        return $this->continuation_manager->can_continue($provider_name);
    }
    
    /**
     * Clear continuation state for provider
     * 
     * @param string $provider_name Optional specific provider to use
     */
    public function clear_continuation_state($provider_name = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        $this->continuation_manager->clear_continuation_state($provider_name);
    }

    /**
     * Check if streaming is available
     *
     * @return bool True if streaming is supported
     */
    public function is_streaming_available() {
        return AI_HTTP_Streaming_Client::is_streaming_available();
    }


    /**
     * Process request through the modular pipeline
     *
     * @param array $request Standardized request
     * @param string $provider_name Provider name
     * @return array Response
     */
    private function process_request($request, $provider_name) {
        // Create provider instance using factory
        $provider = $this->provider_factory->create_provider($provider_name, $this->config);
        
        if (!$provider) {
            return $this->create_error_response("Provider '{$provider_name}' not available");
        }
        
        if (!$provider->is_configured()) {
            return $this->create_error_response("Provider '{$provider_name}' not configured");
        }
        
        // Transform input through provider-specific request normalizer
        $request_normalizer = AI_HTTP_Normalizer_Factory::get_request_normalizer($provider_name);
        $provider_request = $request_normalizer->normalize($request);
        
        // Send request through provider
        $provider_response = $provider->send_request($provider_request);
        
        // Transform output through provider-specific response normalizer
        // Pass provider instance for continuation support (OpenAI response ID tracking)
        $response_normalizer = AI_HTTP_Normalizer_Factory::get_response_normalizer($provider_name, $provider);
        $normalized_response = $response_normalizer->normalize($provider_response);
        
        return $normalized_response;
    }

    /**
     * Try fallback providers if primary fails
     *
     * @param array $request Standardized request
     * @param string $primary_provider Primary provider that failed
     * @return array Response from successful fallback or error
     */
    private function try_fallback_providers($request, $primary_provider) {
        foreach ($this->config['fallback_order'] as $fallback_provider) {
            if ($fallback_provider !== $primary_provider) {
                $response = $this->process_request($request, $fallback_provider);
                if ($response && !isset($response['error'])) {
                    // Mark that we used a fallback
                    $response['used_fallback'] = true;
                    $response['original_provider'] = $primary_provider;
                    $response['provider'] = $fallback_provider;
                    return $response;
                }
            }
        }
        
        return $this->create_error_response('All providers failed');
    }

    /**
     * Validate standardized request format ("round plug" validation)
     *
     * @param array $request Request data to validate
     * @throws Exception If request format is invalid
     */
    private function validate_request($request) {
        if (!is_array($request)) {
            throw new Exception('Request must be an array');
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            throw new Exception('Request must include messages array');
        }

        if (empty($request['messages'])) {
            throw new Exception('Messages array cannot be empty');
        }

        // Additional validation is delegated to RequestNormalizer
    }

    /**
     * Create standardized error response
     *
     * @param string $message Error message
     * @return array Standardized error response
     */
    private function create_error_response($message) {
        return array(
            'success' => false,
            'data' => null,
            'error' => $message,
            'provider' => null,
            'raw_response' => null
        );
    }

    /**
     * Get available providers from registry
     *
     * @return array Available provider names
     */
    public function get_providers() {
        $registry = AI_HTTP_Provider_Registry::get_instance();
        return $registry->get_available_providers();
    }

    /**
     * Get available models for a provider
     *
     * @param string $provider_name Provider name (optional)
     * @return array Available models
     */
    public function get_models($provider_name = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        
        $provider = $this->provider_factory->create_provider($provider_name, $this->config);
        
        if (!$provider) {
            return array();
        }

        return $provider->get_available_models();
    }

    /**
     * Test connection to a provider
     *
     * @param string $provider_name Provider name (optional)
     * @return array Test result with success status and message
     */
    public function test_connection($provider_name = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        
        $provider = $this->provider_factory->create_provider($provider_name, $this->config);
        
        if (!$provider) {
            return array('success' => false, 'message' => 'Provider not found');
        }

        return $provider->test_connection();
    }

    /**
     * Check if a provider is available and configured
     *
     * @param string $provider_name Provider name
     * @return bool True if provider is ready to use
     */
    public function is_provider_ready($provider_name) {
        return $this->provider_factory->can_create_provider($provider_name, $this->config);
    }

    /**
     * Extract tool calls from streaming response in provider-agnostic way
     * 
     * This provides the "round plug in round hole" interface for tool extraction
     * regardless of which AI provider generated the response
     *
     * @param string $full_response Full streaming response from provider
     * @param string $provider_name Optional specific provider name
     * @return array Standardized tool calls array
     */
    public function extract_tool_calls($full_response, $provider_name = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        
        // Get provider-specific streaming module
        $module_class = 'AI_HTTP_' . ucfirst($provider_name) . '_Streaming_Module';
        
        if (class_exists($module_class) && method_exists($module_class, 'extract_tool_calls')) {
            $extraction_result = call_user_func([$module_class, 'extract_tool_calls'], $full_response);
            
            // Standardize the result format - some providers return array, others return object with tool_calls key
            if (is_array($extraction_result)) {
                // If it's a simple array, assume it's the tool calls
                if (isset($extraction_result['tool_calls'])) {
                    return $extraction_result['tool_calls'];
                }
                return $extraction_result;
            }
            
            return array();
        }
        
        // Log warning if provider doesn't support tool extraction
        error_log("AI HTTP Client: Provider '{$provider_name}' does not support tool call extraction");
        return array();
    }

    /**
     * Get client configuration
     *
     * @return array Current configuration
     */
    public function get_config() {
        return $this->config;
    }
}