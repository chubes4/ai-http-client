<?php
/**
 * AI HTTP Client - Main Orchestrator
 * 
 * Single Responsibility: Orchestrate AI requests using unified normalizers
 * Acts as the "round plug" interface with simplified provider architecture
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Client {

    /**
     * Unified normalizers
     */
    private $request_normalizer;
    private $response_normalizer;
    private $streaming_normalizer;
    private $tool_results_normalizer;
    private $connection_test_normalizer;
    
    /**
     * Client configuration
     */
    private $config = array();

    /**
     * Provider instances cache
     */
    private $providers = array();

    /**
     * Constructor with unified normalizers
     *
     * @param array $config Client configuration (optional - will auto-read from WordPress options if empty)
     */
    public function __construct($config = array()) {
        // Auto-read from WordPress options if no config provided
        if (empty($config) && class_exists('AI_HTTP_Options_Manager')) {
            $options_manager = new AI_HTTP_Options_Manager();
            $config = $options_manager->get_client_config();
        }
        
        // Set default configuration
        $this->config = wp_parse_args($config, array(
            'default_provider' => 'openai',
            'timeout' => 30,
            'retry_attempts' => 3,
            'logging_enabled' => false
        ));

        // Initialize unified normalizers
        $this->request_normalizer = new AI_HTTP_Unified_Request_Normalizer();
        $this->response_normalizer = new AI_HTTP_Unified_Response_Normalizer();
        $this->streaming_normalizer = new AI_HTTP_Unified_Streaming_Normalizer();
        $this->tool_results_normalizer = new AI_HTTP_Unified_Tool_Results_Normalizer();
        $this->connection_test_normalizer = new AI_HTTP_Unified_Connection_Test_Normalizer();
    }

    /**
     * Main pipeline: Send standardized request through unified architecture
     *
     * @param array $request Standardized "round plug" input
     * @param string $provider_name Optional specific provider to use
     * @return array Standardized "round plug" output
     */
    public function send_request($request, $provider_name = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        
        try {
            // Step 1: Validate standard input
            $this->validate_request($request);
            
            // Step 2: Get or create provider instance
            $provider = $this->get_provider($provider_name);
            
            // Step 3: Normalize request for provider
            $provider_config = $this->get_provider_config($provider_name);
            $provider_request = $this->request_normalizer->normalize($request, $provider_name, $provider_config);
            
            // Step 4: Send raw request to provider
            $raw_response = $provider->send_raw_request($provider_request);
            
            // Step 5: Normalize response to standard format
            $standard_response = $this->response_normalizer->normalize($raw_response, $provider_name);
            
            // Step 6: Add metadata
            $standard_response['provider'] = $provider_name;
            $standard_response['success'] = true;
            
            return $standard_response;
            
        } catch (Exception $e) {
            return $this->create_error_response($e->getMessage(), $provider_name);
        }
    }

    /**
     * Send streaming request through unified architecture
     *
     * @param array $request Standardized request
     * @param string $provider_name Optional specific provider to use
     * @param callable $completion_callback Optional callback when streaming completes
     * @return string Full response from streaming request
     * @throws Exception If streaming fails
     */
    public function send_streaming_request($request, $provider_name = null, $completion_callback = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];

        try {
            // Step 1: Validate standard input
            $this->validate_request($request);
            
            // Step 2: Get or create provider instance
            $provider = $this->get_provider($provider_name);
            
            // Step 3: Normalize request for streaming
            $provider_config = $this->get_provider_config($provider_name);
            $provider_request = $this->request_normalizer->normalize($request, $provider_name, $provider_config);
            $streaming_request = $this->streaming_normalizer->normalize_streaming_request($provider_request, $provider_name);
            
            // Step 4: Send streaming request with chunk processor
            return $provider->send_raw_streaming_request($streaming_request, function($chunk) use ($provider_name) {
                $processed = $this->streaming_normalizer->process_streaming_chunk($chunk, $provider_name);
                if ($processed && isset($processed['content'])) {
                    echo $processed['content'];
                    flush();
                }
            });
            
        } catch (Exception $e) {
            throw new Exception("Streaming failed for {$provider_name}: " . $e->getMessage());
        }
    }

    /**
     * Continue conversation with tool results using unified architecture
     *
     * @param string|array $context_data Provider-specific context (response_id, conversation_history, etc.)
     * @param array $tool_results Array of tool results
     * @param string $provider_name Optional specific provider to use
     * @param callable $completion_callback Optional callback for streaming
     * @return array|string Response from continuation request
     * @throws Exception If continuation fails
     */
    public function continue_with_tool_results($context_data, $tool_results, $provider_name = null, $completion_callback = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        
        try {
            // Step 1: Get provider instance
            $provider = $this->get_provider($provider_name);
            
            // Step 2: Normalize tool results for continuation
            $continuation_request = $this->tool_results_normalizer->normalize_for_continuation(
                $tool_results, 
                $provider_name, 
                $context_data
            );
            
            // Step 3: Send continuation request
            if ($completion_callback) {
                // Streaming continuation
                return $provider->send_raw_streaming_request($continuation_request, $completion_callback);
            } else {
                // Non-streaming continuation
                $raw_response = $provider->send_raw_request($continuation_request);
                return $this->response_normalizer->normalize($raw_response, $provider_name);
            }
            
        } catch (Exception $e) {
            throw new Exception("Tool continuation failed for {$provider_name}: " . $e->getMessage());
        }
    }

    /**
     * Test connection to provider using unified architecture
     *
     * @param string $provider_name Provider to test
     * @return array Standardized test result
     */
    public function test_connection($provider_name = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        
        try {
            // Step 1: Validate provider configuration
            $provider_config = $this->get_provider_config($provider_name);
            $validation = $this->connection_test_normalizer->validate_provider_config($provider_name, $provider_config);
            
            if (!$validation['valid']) {
                return $this->connection_test_normalizer->create_error_response($validation['message'], $provider_name);
            }
            
            // Step 2: Get provider instance
            $provider = $this->get_provider($provider_name);
            
            // Step 3: Create test request
            $test_request = $this->connection_test_normalizer->create_test_request($provider_name, $provider_config);
            
            // Step 4: Send test request
            $raw_response = $provider->send_raw_request($test_request);
            
            // Step 5: Normalize test response
            return $this->connection_test_normalizer->normalize_test_response($raw_response, $provider_name);
            
        } catch (Exception $e) {
            return $this->connection_test_normalizer->create_error_response($e->getMessage(), $provider_name);
        }
    }

    /**
     * Get available models for provider
     *
     * @param string $provider_name Provider name
     * @return array Available models
     */
    public function get_available_models($provider_name = null) {
        $provider_name = $provider_name ?: $this->config['default_provider'];
        
        try {
            $provider = $this->get_provider($provider_name);
            return $provider->get_raw_models();
            
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('AI HTTP Client: Model fetch failed for ' . $provider_name . ': ' . $e->getMessage());
            }
            return array();
        }
    }

    /**
     * Get or create provider instance
     *
     * @param string $provider_name Provider name
     * @return object Provider instance
     * @throws Exception If provider not supported
     */
    private function get_provider($provider_name) {
        if (isset($this->providers[$provider_name])) {
            return $this->providers[$provider_name];
        }
        
        $provider_config = $this->get_provider_config($provider_name);
        
        switch (strtolower($provider_name)) {
            case 'openai':
                $this->providers[$provider_name] = new AI_HTTP_OpenAI_Provider($provider_config);
                break;
            
            case 'gemini':
                $this->providers[$provider_name] = new AI_HTTP_Gemini_Provider($provider_config);
                break;
            
            case 'anthropic':
                $this->providers[$provider_name] = new AI_HTTP_Anthropic_Provider($provider_config);
                break;
            
            case 'grok':
                $this->providers[$provider_name] = new AI_HTTP_Grok_Provider($provider_config);
                break;
            
            case 'openrouter':
                $this->providers[$provider_name] = new AI_HTTP_OpenRouter_Provider($provider_config);
                break;
            
            default:
                throw new Exception("Provider '{$provider_name}' not supported in unified architecture");
        }
        
        return $this->providers[$provider_name];
    }

    /**
     * Get provider configuration
     *
     * @param string $provider_name Provider name
     * @return array Provider configuration
     */
    private function get_provider_config($provider_name) {
        $options_manager = new AI_HTTP_Options_Manager();
        $all_providers = $options_manager->get_all_providers();
        
        return isset($all_providers[$provider_name]) ? $all_providers[$provider_name] : array();
    }


    /**
     * Validate standard request format
     *
     * @param array $request Request to validate
     * @throws Exception If invalid
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
    }

    /**
     * Create standardized error response
     *
     * @param string $error_message Error message
     * @param string $provider_name Provider name
     * @return array Standardized error response
     */
    private function create_error_response($error_message, $provider_name = 'unknown') {
        return array(
            'success' => false,
            'data' => null,
            'error' => $error_message,
            'provider' => $provider_name,
            'raw_response' => null
        );
    }
}