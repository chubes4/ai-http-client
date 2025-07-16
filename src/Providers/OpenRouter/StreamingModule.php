<?php
/**
 * AI HTTP Client - OpenRouter Streaming Module
 * 
 * Single Responsibility: Handle ONLY OpenRouter streaming functionality
 * Based on OpenRouter's SSE (Server-Sent Events) streaming implementation
 * Uses OpenAI-compatible streaming format with normalized responses
 *
 * @package AIHttpClient\Providers\OpenRouter
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenRouter_Streaming_Module {

    /**
     * Send streaming request to OpenRouter API
     *
     * @param string $url API endpoint URL
     * @param array $request Sanitized request data
     * @param array $headers Authentication headers
     * @param callable|null $completion_callback Called when stream completes
     * @param int $timeout Request timeout
     * @return string Full response from streaming request
     */
    public static function send_streaming_request($url, $request, $headers, $completion_callback = null, $timeout = 60) {
        // Use completion callback for tool processing
        $wrapped_callback = function($full_response) use ($completion_callback) {
            // Process tool calls if any were found in the response
            $tool_calls = self::extract_tool_calls($full_response);
            
            if (!empty($tool_calls)) {
                // Send tool results as SSE events
                foreach ($tool_calls as $tool_call) {
                    $tool_result = array(
                        'tool_call_id' => $tool_call['id'],
                        'tool_name' => $tool_call['function']['name'],
                        'arguments' => $tool_call['function']['arguments'],
                        'provider' => 'openrouter'
                    );
                    
                    echo "event: tool_result\n";
                    echo "data: " . wp_json_encode($tool_result) . "\n\n";
                    
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }
            
            // Indicate completion
            if (is_callable($completion_callback)) {
                call_user_func($completion_callback, "data: [DONE]\n\n");
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
                $headers
            ),
            $wrapped_callback,
            $timeout,
            'openrouter'
        );
    }

    /**
     * Extract tool calls from OpenRouter streaming response
     * OpenRouter uses OpenAI-compatible format for tool calls in streaming
     *
     * @param string $full_response Complete streaming response
     * @return array Tool calls found in response
     */
    public static function extract_tool_calls($full_response) {
        $tool_calls = array();
        
        // Parse SSE events for OpenRouter (OpenAI format)
        $event_blocks = explode("\n\n", trim($full_response));
        
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
            
            // Process OpenRouter streaming data (OpenAI format)
            if (!empty($current_data) && $current_data !== '[DONE]') {
                $decoded = json_decode($current_data, true);
                
                if ($decoded && isset($decoded['choices'])) {
                    foreach ($decoded['choices'] as $choice) {
                        if (isset($choice['delta']['tool_calls'])) {
                            foreach ($choice['delta']['tool_calls'] as $tool_call) {
                                if (isset($tool_call['function']['name'])) {
                                    $tool_calls[] = self::format_openrouter_tool_call($tool_call);
                                }
                            }
                        }
                        
                        // Handle complete tool calls in non-delta format
                        if (isset($choice['message']['tool_calls'])) {
                            foreach ($choice['message']['tool_calls'] as $tool_call) {
                                $tool_calls[] = self::format_openrouter_tool_call($tool_call);
                            }
                        }
                    }
                }
            }
        }
        
        return $tool_calls;
    }

    /**
     * Format OpenRouter tool call to standard format
     *
     * @param array $tool_call OpenRouter tool call format
     * @return array Standardized tool call format
     */
    private static function format_openrouter_tool_call($tool_call) {
        return array(
            'id' => $tool_call['id'] ?? $tool_call['function']['name'] . '_' . uniqid(),
            'type' => $tool_call['type'] ?? 'function',
            'function' => array(
                'name' => $tool_call['function']['name'],
                'arguments' => $tool_call['function']['arguments'] ?? '{}'
            )
        );
    }

    /**
     * Check if streaming is available for OpenRouter
     *
     * @return bool True if streaming is supported
     */
    public static function is_streaming_available() {
        return AI_HTTP_Streaming_Client::is_streaming_available();
    }

    /**
     * Parse OpenRouter streaming events for debugging
     *
     * @param string $full_response Complete streaming response
     * @return array Parsed events for debugging
     */
    public static function parse_streaming_events($full_response) {
        $events = array();
        $event_blocks = explode("\n\n", trim($full_response));
        
        foreach ($event_blocks as $block) {
            if (empty(trim($block))) {
                continue;
            }
            
            $lines = explode("\n", $block);
            $event = array();
            
            foreach ($lines as $line) {
                if (preg_match('/^event: (.+)$/', trim($line), $matches)) {
                    $event['type'] = trim($matches[1]);
                } elseif (preg_match('/^data: (.+)$/', trim($line), $matches)) {
                    $event['data'] = trim($matches[1]);
                    $event['parsed'] = json_decode($event['data'], true);
                }
            }
            
            if (!empty($event)) {
                $events[] = $event;
            }
        }
        
        return $events;
    }

    /**
     * Handle OpenRouter-specific streaming errors
     *
     * @param string $response_data Raw response data
     * @return array|null Error information or null if no error
     */
    public static function parse_streaming_error($response_data) {
        $decoded = json_decode($response_data, true);
        
        if ($decoded && isset($decoded['error'])) {
            return array(
                'code' => $decoded['error']['code'] ?? 'unknown',
                'message' => $decoded['error']['message'] ?? 'Unknown error',
                'type' => $decoded['error']['type'] ?? 'unknown'
            );
        }
        
        return null;
    }

    /**
     * Extract content from OpenRouter streaming response
     * Handles partial content accumulation during streaming
     *
     * @param string $response_chunk Single response chunk
     * @return string|null Extracted content or null
     */
    public static function extract_content_from_chunk($response_chunk) {
        $decoded = json_decode($response_chunk, true);
        
        if (!$decoded || !isset($decoded['choices'])) {
            return null;
        }
        
        $content = '';
        foreach ($decoded['choices'] as $choice) {
            if (isset($choice['delta']['content'])) {
                $content .= $choice['delta']['content'];
            }
        }
        
        return $content ?: null;
    }

    /**
     * Check if streaming response indicates completion
     *
     * @param string $response_chunk Single response chunk
     * @return bool True if response indicates completion
     */
    public static function is_streaming_complete($response_chunk) {
        $decoded = json_decode($response_chunk, true);
        
        if (!$decoded || !isset($decoded['choices'])) {
            return false;
        }
        
        foreach ($decoded['choices'] as $choice) {
            if (isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get OpenRouter-specific streaming configuration
     *
     * @return array Streaming configuration
     */
    public static function get_streaming_config() {
        return array(
            'supports_sse' => true,
            'event_format' => 'openai_compatible',
            'supports_tool_calls' => true,
            'chunk_format' => 'delta',
            'completion_indicator' => '[DONE]',
            'normalized_across_providers' => true
        );
    }

    /**
     * Validate streaming request for OpenRouter
     *
     * @param array $request Request data
     * @return bool True if valid for streaming
     */
    public static function validate_streaming_request($request) {
        // Must have stream enabled
        if (!isset($request['stream']) || $request['stream'] !== true) {
            return false;
        }
        
        // Must have messages
        if (!isset($request['messages']) || !is_array($request['messages']) || empty($request['messages'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Format streaming response for real-time output
     *
     * @param string $chunk Raw streaming chunk
     * @return array Formatted chunk data
     */
    public static function format_streaming_chunk($chunk) {
        $decoded = json_decode($chunk, true);
        
        if (!$decoded) {
            return null;
        }
        
        $result = array(
            'content' => '',
            'done' => false,
            'tool_calls' => array(),
            'finish_reason' => null,
            'model' => null,
            'provider' => null
        );
        
        if (isset($decoded['choices'])) {
            foreach ($decoded['choices'] as $choice) {
                if (isset($choice['delta']['content'])) {
                    $result['content'] .= $choice['delta']['content'];
                }
                
                if (isset($choice['delta']['tool_calls'])) {
                    foreach ($choice['delta']['tool_calls'] as $tool_call) {
                        if (isset($tool_call['function']['name'])) {
                            $result['tool_calls'][] = self::format_openrouter_tool_call($tool_call);
                        }
                    }
                }
                
                if (isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
                    $result['done'] = true;
                    $result['finish_reason'] = $choice['finish_reason'];
                }
            }
        }
        
        // Extract OpenRouter-specific metadata
        if (isset($decoded['model'])) {
            $result['model'] = $decoded['model'];
        }
        
        if (isset($decoded['provider'])) {
            $result['provider'] = $decoded['provider'];
        }
        
        return $result;
    }

    /**
     * Extract generation ID for post-request statistics
     *
     * @param string $response_chunk Single response chunk
     * @return string|null Generation ID if found
     */
    public static function extract_generation_id($response_chunk) {
        $decoded = json_decode($response_chunk, true);
        
        if ($decoded && isset($decoded['id'])) {
            return $decoded['id'];
        }
        
        return null;
    }

    /**
     * Handle OpenRouter provider routing information
     *
     * @param string $response_chunk Single response chunk
     * @return array|null Provider routing info
     */
    public static function extract_provider_info($response_chunk) {
        $decoded = json_decode($response_chunk, true);
        
        if (!$decoded) {
            return null;
        }
        
        $provider_info = array();
        
        if (isset($decoded['provider'])) {
            $provider_info['used_provider'] = $decoded['provider'];
        }
        
        if (isset($decoded['model'])) {
            $provider_info['actual_model'] = $decoded['model'];
        }
        
        if (isset($decoded['usage'])) {
            $provider_info['usage'] = $decoded['usage'];
        }
        
        return !empty($provider_info) ? $provider_info : null;
    }
}