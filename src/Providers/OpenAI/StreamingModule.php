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
        // Detect if this is a continuation request (has previous_response_id)
        $is_continuation = isset($request['previous_response_id']);
        
        // For continuation requests, we need to process the response differently
        if ($is_continuation) {
            return self::send_continuation_streaming_request($url, $request, $headers, $completion_callback, $timeout);
        }
        
        // Pass the full response to the completion callback so consumers can handle their own tool processing
        $wrapped_callback = function($full_response) use ($completion_callback) {
            // Let the consumer (like Wordsurf) handle tool processing themselves
            if (is_callable($completion_callback)) {
                call_user_func($completion_callback, $full_response);
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
                                    'arguments' => function_exists('wp_json_encode') ? wp_json_encode($item['function_call']['arguments'] ?? array()) : json_encode($item['function_call']['arguments'] ?? array())
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
     * Send continuation streaming request with proper SSE formatting
     * Uses dedicated SSE parser for clean separation of concerns
     *
     * @param string $url API endpoint URL
     * @param array $request Continuation request data
     * @param array $headers Authentication headers
     * @param callable|null $completion_callback Called when stream completes
     * @param int $timeout Request timeout
     * @return string Full response from continuation request
     */
    private static function send_continuation_streaming_request($url, $request, $headers, $completion_callback = null, $timeout = 60) {
        // Use a callback that processes the OpenAI response through the SSE parser
        $sse_wrapper_callback = function($full_response) use ($completion_callback) {
            // Parse events and convert to frontend format using dedicated SSE parser
            $events = AI_HTTP_SSE_Parser::parse_events($full_response);
            $mapping = AI_HTTP_SSE_Parser::get_openai_responses_mapping();
            AI_HTTP_SSE_Parser::convert_to_frontend_sse($events, $mapping);
            
            // Call the original completion callback
            if (is_callable($completion_callback)) {
                call_user_func($completion_callback, $full_response);
            }
        };
        
        // Use the dedicated continuation streaming method
        return self::stream_continuation_post($url, $request, array_merge(
            array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
            ),
            $headers
        ), $sse_wrapper_callback, $timeout);
    }

    /**
     * Custom streaming POST for continuations that processes response before echoing
     *
     * @param string $url Request URL
     * @param array $body Request body
     * @param array $headers Request headers
     * @param callable|null $completion_callback Called when stream completes
     * @param int $timeout Request timeout
     * @return string Full raw response from API
     */
    private static function stream_continuation_post($url, $body, $headers, $completion_callback = null, $timeout = 60) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL is required for streaming requests');
        }

        error_log('AI HTTP Client: Starting continuation streaming request to: ' . $url);
        
        // Ensure streaming is enabled
        $body['stream'] = true;
        
        $full_response = '';
        
        // Convert headers array to cURL format
        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }
        $curl_headers[] = 'Accept: text/event-stream';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => function_exists('wp_json_encode') ? wp_json_encode($body) : json_encode($body),
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$full_response) {
                // Only accumulate data, don't echo raw (we'll process it later)
                $full_response .= $data;
                return strlen($data);
            },
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
        ]);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new Exception('cURL continuation streaming error: ' . $error);
        }

        if ($http_code >= 400) {
            error_log("AI HTTP Client: HTTP {$http_code} continuation error: " . substr($full_response, 0, 500));
            throw new Exception("HTTP {$http_code} continuation streaming error");
        }

        // Call completion callback with full response
        if (is_callable($completion_callback)) {
            call_user_func($completion_callback, $full_response);
        }

        return $full_response;
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