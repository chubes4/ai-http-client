<?php
/**
 * AI HTTP Client - Anthropic Continuation Handler
 * 
 * Single Responsibility: Handle Anthropic-specific continuation logic
 * Manages Anthropic continuation using conversation history
 *
 * @package AIHttpClient\Providers\Anthropic
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Anthropic_Continuation_Handler {

    /**
     * @var AI_HTTP_Provider_Factory Provider factory instance
     */
    private $provider_factory;

    /**
     * @var array Configuration
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
     * Handle Anthropic continuation with conversation history
     *
     * @param array $continuation_data Stored continuation data
     * @param array $tool_results Tool results from user interactions
     * @param callable|null $callback Completion callback
     * @return mixed Provider response
     */
    public function handle_continuation($continuation_data, $tool_results, $callback = null) {
        // Validate continuation data
        if (!isset($continuation_data['conversation_history'])) {
            throw new Exception('Anthropic continuation requires conversation_history');
        }

        $conversation_history = $continuation_data['conversation_history'];
        
        error_log("AI HTTP Client: Anthropic continuation with " . count($conversation_history) . " messages");

        // Create Anthropic provider instance
        $provider = $this->provider_factory->create_provider('anthropic', $this->config);
        
        if (!$provider) {
            throw new Exception('Failed to create Anthropic provider instance');
        }

        // Use provider's continuation method
        return $provider->continue_with_tool_results($conversation_history, $tool_results, $callback);
    }

    /**
     * Extract continuation data from Anthropic response
     *
     * @param array $response_data Anthropic response data
     * @param array $original_request Original request data
     * @return array Continuation data
     */
    public static function extract_continuation_data($response_data, $original_request = array()) {
        $continuation_data = array();

        // Anthropic uses conversation history for continuation
        if (isset($original_request['messages'])) {
            $continuation_data['conversation_history'] = $original_request['messages'];
        }

        // Store model info for consistency
        if (isset($response_data['model'])) {
            $continuation_data['model'] = $response_data['model'];
        } elseif (isset($original_request['model'])) {
            $continuation_data['model'] = $original_request['model'];
        }

        // Store usage info if available
        if (isset($response_data['usage'])) {
            $continuation_data['usage'] = $response_data['usage'];
        }

        return $continuation_data;
    }

    /**
     * Validate that continuation is possible
     *
     * @param array $continuation_data Continuation data to validate
     * @return bool True if continuation is possible
     */
    public function can_continue($continuation_data) {
        return isset($continuation_data['conversation_history']) && 
               is_array($continuation_data['conversation_history']) && 
               !empty($continuation_data['conversation_history']);
    }
}