<?php
/**
 * AI HTTP Client - OpenAI Continuation Handler
 * 
 * Single Responsibility: Handle OpenAI-specific continuation logic
 * Manages OpenAI Responses API continuation using response IDs
 *
 * @package AIHttpClient\Providers\OpenAI
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenAI_Continuation_Handler {

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
     * Handle OpenAI continuation with response ID
     *
     * @param array $continuation_data Stored continuation data
     * @param array $tool_results Tool results from user interactions
     * @param callable|null $callback Completion callback
     * @return mixed Provider response
     */
    public function handle_continuation($continuation_data, $tool_results, $callback = null) {
        // Validate continuation data
        if (!isset($continuation_data['response_id'])) {
            throw new Exception('OpenAI continuation requires response_id');
        }

        $response_id = $continuation_data['response_id'];
        
        error_log("AI HTTP Client: OpenAI continuation with response ID: {$response_id}");

        // Create OpenAI provider instance
        $provider = $this->provider_factory->create_provider('openai', $this->config);
        
        if (!$provider) {
            throw new Exception('Failed to create OpenAI provider instance');
        }

        // Use provider's continuation method
        return $provider->continue_with_tool_results($response_id, $tool_results, $callback);
    }

    /**
     * Extract continuation data from OpenAI response
     *
     * @param array $response_data OpenAI response data
     * @param array $original_request Original request data
     * @return array Continuation data
     */
    public static function extract_continuation_data($response_data, $original_request = array()) {
        $continuation_data = array();

        // Extract response ID for Responses API continuation
        if (isset($response_data['id'])) {
            $continuation_data['response_id'] = $response_data['id'];
        }

        // Store model info for consistency
        if (isset($response_data['model'])) {
            $continuation_data['model'] = $response_data['model'];
        }

        // Store creation timestamp
        $continuation_data['created'] = $response_data['created'] ?? time();

        // Store original request info that might be needed
        if (isset($original_request['max_tokens'])) {
            $continuation_data['max_tokens'] = $original_request['max_tokens'];
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
        return isset($continuation_data['response_id']) && !empty($continuation_data['response_id']);
    }
}