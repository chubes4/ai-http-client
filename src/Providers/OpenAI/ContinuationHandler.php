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

        // Use provider's continuation method with streaming support
        return $provider->continue_with_tool_results($response_id, $tool_results, $callback);
    }
    
    /**
     * Get provider instance for testing
     *
     * @return AI_HTTP_OpenAI_Provider Provider instance
     */
    public function get_provider() {
        return $this->provider_factory->create_provider('openai', $this->config);
    }

    /**
     * Extract continuation data from OpenAI response
     *
     * @param mixed $response_data OpenAI response data (string for streaming, array for non-streaming)
     * @param array $original_request Original request data
     * @return array Continuation data
     */
    public static function extract_continuation_data($response_data, $original_request = array()) {
        $continuation_data = array();

        // Handle streaming response (SSE format)
        if (is_string($response_data)) {
            $response_id = self::extract_response_id_from_stream($response_data);
            if ($response_id) {
                $continuation_data['response_id'] = $response_id;
            }
        } elseif (is_array($response_data)) {
            // Handle non-streaming response
            if (isset($response_data['id'])) {
                $continuation_data['response_id'] = $response_data['id'];
            }
        }

        // Store model info for consistency
        if (isset($response_data['model'])) {
            $continuation_data['model'] = $response_data['model'];
        } elseif (isset($original_request['model'])) {
            $continuation_data['model'] = $original_request['model'];
        }

        // Store creation timestamp
        $continuation_data['created'] = isset($response_data['created']) ? $response_data['created'] : time();

        // Store original request info that might be needed
        if (isset($original_request['max_tokens'])) {
            $continuation_data['max_tokens'] = $original_request['max_tokens'];
        }

        return $continuation_data;
    }
    
    /**
     * Extract response ID from OpenAI streaming response
     *
     * @param string $stream_response Full SSE response
     * @return string|null Response ID or null if not found
     */
    private static function extract_response_id_from_stream($stream_response) {
        // Parse SSE events to find response.created event with ID
        $event_blocks = explode("\n\n", trim($stream_response));
        
        foreach ($event_blocks as $block) {
            if (empty(trim($block))) {
                continue;
            }
            
            $lines = explode("\n", $block);
            $event_type = '';
            $current_data = '';
            
            foreach ($lines as $line) {
                if (preg_match('/^event: (.+)$/', trim($line), $matches)) {
                    $event_type = trim($matches[1]);
                } elseif (preg_match('/^data: (.+)$/', trim($line), $matches)) {
                    $current_data .= trim($matches[1]);
                }
            }
            
            // Look for response.created event which contains the response ID
            if ($event_type === 'response.created' && !empty($current_data)) {
                $decoded = json_decode($current_data, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                    return $decoded['id'];
                }
            }
        }
        
        return null;
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
    
    /**
     * Extract continuation data - static method for ContinuationManager
     *
     * @param mixed $response_data Response data from provider
     * @param array $original_request Original request data
     * @return array Continuation data
     */
    public static function extract_data($response_data, $original_request = array()) {
        return self::extract_continuation_data($response_data, $original_request);
    }
}