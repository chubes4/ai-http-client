<?php
/**
 * AI HTTP Client - Continuation Manager
 * 
 * Single Responsibility: Orchestrate provider-agnostic continuation
 * Centralizes continuation logic and delegates to provider-specific handlers
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Continuation_Manager {

    /**
     * @var AI_HTTP_Provider_Factory Provider factory instance
     */
    private $provider_factory;

    /**
     * @var array Default configuration
     */
    private $config;

    /**
     * Constructor
     *
     * @param AI_HTTP_Provider_Factory $provider_factory Provider factory
     * @param array $config Configuration array
     */
    public function __construct($provider_factory, $config = array()) {
        $this->provider_factory = $provider_factory;
        $this->config = $config;
    }

    /**
     * Store continuation state after a request
     *
     * @param string $provider_name Provider name
     * @param array $response_data Response data from provider
     * @param array $original_request Original request data
     */
    public function store_continuation_state($provider_name, $response_data, $original_request = array()) {
        $continuation_data = $this->extract_continuation_data($provider_name, $response_data, $original_request);
        
        if ($continuation_data) {
            AI_HTTP_Continuation_State::store($provider_name, $continuation_data);
            error_log("AI HTTP Client: Stored continuation data for provider '{$provider_name}': " . json_encode(array_keys($continuation_data)));
        }
    }

    /**
     * Continue conversation with tool results
     *
     * @param string $provider_name Provider name
     * @param array $tool_results Tool results from user interactions
     * @param callable|null $callback Completion callback
     * @return mixed Provider response
     */
    public function continue_with_tool_results($provider_name, $tool_results, $callback = null) {
        // Get stored continuation state
        $continuation_data = AI_HTTP_Continuation_State::get($provider_name);
        
        if (!$continuation_data) {
            throw new Exception("No continuation state found for provider '{$provider_name}'. Cannot continue conversation.");
        }

        // Create provider instance
        $provider = $this->provider_factory->create_provider($provider_name, $this->config);
        
        if (!$provider) {
            throw new Exception("Failed to create provider instance for '{$provider_name}'");
        }

        // Check if provider has a continuation handler
        $continuation_handler = $this->get_continuation_handler($provider_name);
        
        if ($continuation_handler) {
            // Use provider-specific continuation handler
            return $continuation_handler->handle_continuation($continuation_data, $tool_results, $callback);
        } else {
            // Fallback to provider's direct continuation method
            return $this->fallback_continuation($provider, $continuation_data, $tool_results, $callback);
        }
    }

    /**
     * Extract continuation data from response based on provider
     *
     * @param string $provider_name Provider name
     * @param array $response_data Response data
     * @param array $original_request Original request
     * @return array|null Continuation data or null if none needed
     */
    private function extract_continuation_data($provider_name, $response_data, $original_request) {
        switch ($provider_name) {
            case 'openai':
                // OpenAI needs response ID from Responses API
                if (isset($response_data['id'])) {
                    return array(
                        'response_id' => $response_data['id'],
                        'model' => $response_data['model'] ?? null,
                        'created' => $response_data['created'] ?? time()
                    );
                }
                break;
                
            case 'anthropic':
            case 'gemini':
                // Anthropic/Gemini need conversation history
                if (isset($original_request['messages'])) {
                    return array(
                        'conversation_history' => $original_request['messages'],
                        'model' => $original_request['model'] ?? null
                    );
                }
                break;
                
            case 'grok':
            case 'openrouter':
                // These may need different continuation approaches
                if (isset($original_request['messages'])) {
                    return array(
                        'conversation_history' => $original_request['messages'],
                        'model' => $original_request['model'] ?? null
                    );
                }
                break;
        }
        
        return null;
    }

    /**
     * Get continuation handler for a provider
     *
     * @param string $provider_name Provider name
     * @return object|null Continuation handler instance or null if not found
     */
    private function get_continuation_handler($provider_name) {
        $handler_class = 'AI_HTTP_' . ucfirst($provider_name) . '_Continuation_Handler';
        
        if (class_exists($handler_class)) {
            return new $handler_class($this->provider_factory, $this->config);
        }
        
        return null;
    }

    /**
     * Fallback continuation method for providers without dedicated handlers
     *
     * @param object $provider Provider instance
     * @param array $continuation_data Continuation data
     * @param array $tool_results Tool results
     * @param callable|null $callback Completion callback
     * @return mixed Provider response
     */
    private function fallback_continuation($provider, $continuation_data, $tool_results, $callback) {
        // Use the provider's continue_with_tool_results method directly
        if (method_exists($provider, 'continue_with_tool_results')) {
            // Determine what to pass based on continuation data structure
            if (isset($continuation_data['response_id'])) {
                // OpenAI-style: pass response ID
                return $provider->continue_with_tool_results($continuation_data['response_id'], $tool_results, $callback);
            } elseif (isset($continuation_data['conversation_history'])) {
                // Anthropic/Gemini-style: pass conversation history
                return $provider->continue_with_tool_results($continuation_data['conversation_history'], $tool_results, $callback);
            }
        }
        
        throw new Exception("Provider does not support continuation or invalid continuation data");
    }

    /**
     * Clear continuation state for a provider
     *
     * @param string $provider_name Provider name
     */
    public function clear_continuation_state($provider_name) {
        AI_HTTP_Continuation_State::clear($provider_name);
    }

    /**
     * Check if continuation is possible for a provider
     *
     * @param string $provider_name Provider name
     * @return bool True if continuation is possible
     */
    public function can_continue($provider_name) {
        return AI_HTTP_Continuation_State::has($provider_name);
    }
}