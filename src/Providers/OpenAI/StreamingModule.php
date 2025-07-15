<?php
/**
 * AI HTTP Client - OpenAI Streaming Module
 * 
 * Single Responsibility: Handle ONLY OpenAI streaming functionality
 * Based on Wordsurf's working SSE streaming implementation
 *
 * @package AIHttpClient\Providers\OpenAI
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenAI_Streaming_Module {

    /**
     * Send streaming request to OpenAI Responses API
     *
     * @param string $url API endpoint URL
     * @param array $request Sanitized request data
     * @param array $headers Authentication headers
     * @param callable|null $completion_callback Called when stream completes
     * @param int $timeout Request timeout
     * @return string Full response from streaming request
     */
    public static function send_streaming_request($url, $request, $headers, $completion_callback = null, $timeout = 60) {
        // Use completion callback for tool processing (like Wordsurf)
        $wrapped_callback = function($full_response) use ($completion_callback) {
            // Process tool calls if any were found in the response
            $tool_calls = self::extract_tool_calls($full_response);
            
            if (!empty($tool_calls)) {
                // Send tool results as SSE events (like Wordsurf)
                foreach ($tool_calls as $tool_call) {
                    $tool_result = array(
                        'tool_call_id' => $tool_call['id'],
                        'tool_name' => $tool_call['function']['name'],
                        'arguments' => $tool_call['function']['arguments'],
                        'provider' => 'openai'
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
     * Extract tool calls from OpenAI Responses API streaming response
     * Based on Wordsurf's SSE parsing patterns
     *
     * @param string $full_response Complete streaming response
     * @return array Tool calls found in response
     */
    public static function extract_tool_calls($full_response) {
        $tool_calls = array();
        
        // Parse SSE events (like Wordsurf does)
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
            
            // Process response.completed events (Wordsurf pattern)
            if ($event_type === 'response.completed' && !empty($current_data)) {
                $decoded = json_decode($current_data, true);
                if ($decoded && isset($decoded['response']['output'])) {
                    $output_items = $decoded['response']['output'];
                    
                    foreach ($output_items as $item) {
                        if (isset($item['type']) && $item['type'] === 'function_call' && 
                            isset($item['status']) && $item['status'] === 'completed') {
                            
                            $tool_calls[] = array(
                                'id' => $item['id'] ?? uniqid('tool_'),
                                'type' => 'function',
                                'function' => array(
                                    'name' => $item['function_call']['name'],
                                    'arguments' => wp_json_encode($item['function_call']['arguments'] ?? array())
                                )
                            );
                        }
                    }
                }
            }
        }
        
        return $tool_calls;
    }

    /**
     * Check if streaming is available for OpenAI
     *
     * @return bool True if streaming is supported
     */
    public static function is_streaming_available() {
        return AI_HTTP_Streaming_Client::is_streaming_available();
    }
}