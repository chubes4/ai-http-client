<?php
/**
 * AI HTTP Client - Anthropic Streaming Module
 * 
 * Single Responsibility: Handle ONLY Anthropic streaming functionality
 * Based on Claude's Messages API streaming patterns
 *
 * @package AIHttpClient\Providers\Anthropic
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Anthropic_Streaming_Module {

    /**
     * Send streaming request to Anthropic Messages API
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
                        'provider' => 'anthropic'
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
        
        // Anthropic requires stream: true parameter
        $request['stream'] = true;
        
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
            'anthropic'
        );
    }

    /**
     * Extract tool calls from Anthropic streaming response
     * Based on Claude's tool_use content blocks
     *
     * @param string $full_response Complete streaming response
     * @return array Tool calls found in response
     */
    public static function extract_tool_calls($full_response) {
        $tool_calls = array();
        
        // Parse SSE events for Anthropic
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
            
            // Process various Anthropic streaming events
            if (!empty($current_data)) {
                $decoded = json_decode($current_data, true);
                
                // Handle content_block_delta events for tool use
                if ($event_type === 'content_block_delta' && $decoded) {
                    if (isset($decoded['delta']['type']) && $decoded['delta']['type'] === 'tool_use') {
                        // Tool use block detected - will be completed in content_block_stop
                        continue;
                    }
                }
                
                // Handle content_block_stop events - where tool calls are finalized
                if ($event_type === 'content_block_stop' && $decoded) {
                    // Tool call completed - extract from previous content blocks
                    continue;
                }
                
                // Handle message_delta events for complete tool calls
                if ($event_type === 'message_delta' && $decoded && isset($decoded['delta']['content'])) {
                    foreach ($decoded['delta']['content'] as $content_block) {
                        if (isset($content_block['type']) && $content_block['type'] === 'tool_use') {
                            $tool_calls[] = self::format_anthropic_tool_call($content_block);
                        }
                    }
                }
                
                // Handle complete message events
                if (($event_type === 'message_stop' || empty($event_type)) && $decoded && isset($decoded['content'])) {
                    foreach ($decoded['content'] as $content_block) {
                        if (isset($content_block['type']) && $content_block['type'] === 'tool_use') {
                            $tool_calls[] = self::format_anthropic_tool_call($content_block);
                        }
                    }
                }
            }
        }
        
        return $tool_calls;
    }

    /**
     * Format Anthropic tool_use block to standard format
     *
     * @param array $content_block Anthropic tool_use content block
     * @return array Standardized tool call format
     */
    private static function format_anthropic_tool_call($content_block) {
        return array(
            'id' => $content_block['id'] ?? uniqid('tool_'),
            'type' => 'function',
            'function' => array(
                'name' => $content_block['name'],
                'arguments' => wp_json_encode($content_block['input'] ?? array())
            )
        );
    }

    /**
     * Check if streaming is available for Anthropic
     *
     * @return bool True if streaming is supported
     */
    public static function is_streaming_available() {
        return AI_HTTP_Streaming_Client::is_streaming_available();
    }

    /**
     * Parse Anthropic streaming events for debugging
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
}