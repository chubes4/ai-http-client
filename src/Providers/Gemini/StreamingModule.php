<?php
/**
 * AI HTTP Client - Google Gemini Streaming Module
 * 
 * Single Responsibility: Handle ONLY Google Gemini streaming functionality
 * Based on Gemini's SSE (Server-Sent Events) streaming implementation
 * Uses streamGenerateContent endpoint with alt=sse parameter
 *
 * @package AIHttpClient\Providers\Gemini
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Gemini_Streaming_Module {

    /**
     * Send streaming request to Google Gemini API
     *
     * @param string $url API endpoint URL
     * @param array $request Sanitized request data
     * @param array $headers Authentication headers
     * @param callable|null $completion_callback Called when stream completes
     * @param int $timeout Request timeout
     * @return string Full response from streaming request
     */
    public static function send_streaming_request($url, $request, $headers, $completion_callback = null, $timeout = 60) {
        // Add SSE parameter for Gemini streaming
        $url = add_query_arg('alt', 'sse', $url);
        
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
                        'provider' => 'gemini'
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
            $timeout
        );
    }

    /**
     * Extract tool calls from Gemini streaming response
     * Based on Gemini's function calling format in streaming responses
     *
     * @param string $full_response Complete streaming response
     * @return array Tool calls found in response
     */
    public static function extract_tool_calls($full_response) {
        $tool_calls = array();
        
        // Parse SSE events for Gemini
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
            
            // Process Gemini streaming data
            if (!empty($current_data)) {
                $decoded = json_decode($current_data, true);
                
                if ($decoded && isset($decoded['candidates'])) {
                    foreach ($decoded['candidates'] as $candidate) {
                        if (isset($candidate['content']['parts'])) {
                            foreach ($candidate['content']['parts'] as $part) {
                                // Check for function call parts
                                if (isset($part['functionCall'])) {
                                    $tool_calls[] = self::format_gemini_tool_call($part['functionCall']);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $tool_calls;
    }

    /**
     * Format Gemini function call to standard format
     *
     * @param array $function_call Gemini function call format
     * @return array Standardized tool call format
     */
    private static function format_gemini_tool_call($function_call) {
        return array(
            'id' => $function_call['name'] . '_' . uniqid(),
            'type' => 'function',
            'function' => array(
                'name' => $function_call['name'],
                'arguments' => wp_json_encode($function_call['args'] ?? array())
            )
        );
    }

    /**
     * Check if streaming is available for Gemini
     *
     * @return bool True if streaming is supported
     */
    public static function is_streaming_available() {
        return AI_HTTP_Streaming_Client::is_streaming_available();
    }

    /**
     * Parse Gemini streaming events for debugging
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
     * Handle Gemini-specific streaming errors
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
                'status' => $decoded['error']['status'] ?? 'unknown'
            );
        }
        
        return null;
    }

    /**
     * Extract content from Gemini streaming response
     * Handles partial content accumulation during streaming
     *
     * @param string $response_chunk Single response chunk
     * @return string|null Extracted content or null
     */
    public static function extract_content_from_chunk($response_chunk) {
        $decoded = json_decode($response_chunk, true);
        
        if (!$decoded || !isset($decoded['candidates'])) {
            return null;
        }
        
        $content = '';
        foreach ($decoded['candidates'] as $candidate) {
            if (isset($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $content .= $part['text'];
                    }
                }
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
        
        if (!$decoded || !isset($decoded['candidates'])) {
            return false;
        }
        
        foreach ($decoded['candidates'] as $candidate) {
            if (isset($candidate['finishReason'])) {
                return true;
            }
        }
        
        return false;
    }
}