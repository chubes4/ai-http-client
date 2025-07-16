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
     * @param int $max_retries Maximum retries for connection issues
     * @return string Full response from streaming request
     * @throws Exception If streaming fails after retries
     */
    public static function send_streaming_request($url, $request, $headers, $completion_callback = null, $timeout = 60, $max_retries = 2) {
        $last_error = null;
        
        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            try {
                // Detect if this is a continuation request (has previous_response_id)
                $is_continuation = isset($request['previous_response_id']);
                
                // For continuation requests, we need to process the response differently
                if ($is_continuation) {
                    return self::send_continuation_streaming_request($url, $request, $headers, $completion_callback, $timeout);
                }
                
                // Let the API validate the request format - cleaner than duplicating validation logic
                
                // Pass the full response to the completion callback so consumers can handle their own tool processing
                $wrapped_callback = function($full_response) use ($completion_callback) {
                    try {
                        // Validate response before processing
                        if (empty($full_response)) {
                            error_log('AI HTTP Client WARNING: Empty streaming response received');
                            return;
                        }
                        
                        // Let the consumer (like Wordsurf) handle tool processing themselves
                        if (is_callable($completion_callback)) {
                            call_user_func($completion_callback, $full_response);
                        }
                    } catch (Exception $e) {
                        error_log('AI HTTP Client ERROR: Completion callback failed: ' . $e->getMessage());
                    }
                };
                
                $result = AI_HTTP_Streaming_Client::stream_post(
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
                
                // If we get here without exception, request succeeded
                if ($attempt > 0) {
                    error_log("AI HTTP Client: Streaming request succeeded on attempt " . ($attempt + 1));
                }
                
                return $result;
                
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                error_log("AI HTTP Client: Streaming attempt " . ($attempt + 1) . " failed: " . $last_error);
                
                // Don't retry for certain error types
                if (!self::is_retryable_streaming_error($last_error)) {
                    throw $e;
                }
                
                // Wait before retry (exponential backoff)
                if ($attempt < $max_retries) {
                    $wait_time = pow(2, $attempt); // 1s, 2s, 4s...
                    sleep($wait_time);
                }
            }
        }
        
        // All attempts failed
        throw new Exception('Streaming request failed after ' . ($max_retries + 1) . ' attempts. Last error: ' . $last_error);
    }
    
    /**
     * Validate streaming request format
     *
     * @param array $request Request data
     * @return bool True if valid
     */
    private static function validate_streaming_request($request) {
        // OpenAI Responses API uses 'input' instead of 'messages'
        $messages_field = isset($request['input']) ? 'input' : 'messages';
        
        // Check required fields
        if (!isset($request[$messages_field]) || !is_array($request[$messages_field])) {
            error_log("AI HTTP Client DEBUG: Request validation failed - missing or invalid {$messages_field} array");
            return false;
        }
        
        if (empty($request[$messages_field])) {
            error_log("AI HTTP Client DEBUG: Request validation failed - empty {$messages_field} array");
            return false;
        }
        
        // Validate message format - be flexible with additional fields
        foreach ($request[$messages_field] as $index => $message) {
            if (!isset($message['role'])) {
                error_log("AI HTTP Client DEBUG: Request validation failed - {$messages_field}[{$index}] missing role");
                return false;
            }
            
            if (!isset($message['content'])) {
                error_log("AI HTTP Client DEBUG: Request validation failed - {$messages_field}[{$index}] missing content");
                return false;
            }
            
            // Validate role is one of the expected values
            $valid_roles = array('user', 'assistant', 'system', 'tool');
            if (!in_array($message['role'], $valid_roles)) {
                error_log("AI HTTP Client DEBUG: Request validation failed - {$messages_field}[{$index}] has invalid role: {$message['role']}");
                return false;
            }
            
            // Content should be string or array (for multi-modal)
            if (!is_string($message['content']) && !is_array($message['content'])) {
                error_log("AI HTTP Client DEBUG: Request validation failed - {$messages_field}[{$index}] content is not string or array");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if streaming error is retryable
     *
     * @param string $error Error message
     * @return bool True if retryable
     */
    private static function is_retryable_streaming_error($error) {
        $retryable_patterns = array(
            'connection',
            'timeout',
            'network',
            'temporary',
            'unavailable',
            'rate limit',
            '502',
            '503',
            '504'
        );
        
        $error_lower = strtolower($error);
        foreach ($retryable_patterns as $pattern) {
            if (strpos($error_lower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract tool calls from OpenAI Responses API streaming response
     * Based on Wordsurf's SSE parsing patterns with enhanced error handling
     *
     * @param string $full_response Complete streaming response
     * @return array Tool calls found in response with extraction metadata
     */
    public static function extract_tool_calls($full_response) {
        $tool_calls = array();
        $extraction_info = array(
            'events_processed' => 0,
            'completed_events_found' => 0,
            'tool_calls_extracted' => 0,
            'errors' => array()
        );
        
        try {
            if (empty($full_response)) {
                $extraction_info['errors'][] = 'Empty response provided for tool extraction';
                return array('tool_calls' => $tool_calls, 'extraction_info' => $extraction_info);
            }
            
            // Parse SSE events (like Wordsurf does)
            $event_blocks = explode("\n\n", trim($full_response));
            
            foreach ($event_blocks as $block_index => $block) {
                if (empty(trim($block))) {
                    continue;
                }
                
                $extraction_info['events_processed']++;
                
                try {
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
                    
                    // Process response.output_item.done events (individual function call completion)
                    if ($event_type === 'response.output_item.done' && !empty($current_data)) {
                        $extraction_info['completed_events_found']++;
                        
                        $decoded = json_decode($current_data, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $extraction_info['errors'][] = 'JSON decode error in output_item.done block ' . $block_index . ': ' . json_last_error_msg();
                            continue;
                        }
                        
                        if ($decoded && isset($decoded['item'])) {
                            $item = $decoded['item'];
                            
                            // Process individual function call item
                            if (isset($item['type']) && $item['type'] === 'function_call' && 
                                isset($item['status']) && $item['status'] === 'completed') {
                                
                                // Parse arguments from JSON string if needed
                                $arguments = isset($item['arguments']) ? $item['arguments'] : '{}';
                                if (is_string($arguments)) {
                                    $arguments_array = json_decode($arguments, true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $arguments = $arguments_array;
                                    }
                                }
                                
                                // Safely encode arguments back to JSON
                                $arguments_json = function_exists('wp_json_encode') ? 
                                    wp_json_encode($arguments) : 
                                    json_encode($arguments);
                                    
                                if ($arguments_json === false) {
                                    $extraction_info['errors'][] = "Failed to encode arguments for function '{$item['name']}' in output_item.done event";
                                    $arguments_json = '{}';
                                }
                                
                                $tool_calls[] = array(
                                    'id' => isset($item['call_id']) ? $item['call_id'] : (isset($item['id']) ? $item['id'] : uniqid('tool_')),
                                    'type' => 'function',
                                    'function' => array(
                                        'name' => $item['name'],
                                        'arguments' => $arguments_json
                                    )
                                );
                                
                                $extraction_info['tool_calls_extracted']++;
                                error_log("AI HTTP Client: Extracted tool call '{$item['name']}' from response.output_item.done event");
                            }
                        }
                    }
                    
                    // Process response.completed events (Wordsurf pattern)
                    if ($event_type === 'response.completed' && !empty($current_data)) {
                        $extraction_info['completed_events_found']++;
                        
                        $decoded = json_decode($current_data, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $extraction_info['errors'][] = 'JSON decode error in block ' . $block_index . ': ' . json_last_error_msg();
                            continue;
                        }
                        
                        if ($decoded && isset($decoded['response']['output'])) {
                            $output_items = $decoded['response']['output'];
                            
                            if (!is_array($output_items)) {
                                $extraction_info['errors'][] = 'Invalid output items format in block ' . $block_index;
                                continue;
                            }
                            
                            foreach ($output_items as $item_index => $item) {
                                try {
                                    if (isset($item['type']) && $item['type'] === 'function_call' && 
                                        isset($item['status']) && $item['status'] === 'completed') {
                                        
                                        // Validate required function call fields
                                        if (!isset($item['function_call']['name'])) {
                                            $extraction_info['errors'][] = "Missing function name in item {$item_index} of block {$block_index}";
                                            continue;
                                        }
                                        
                                        $tool_call_id = isset($item['id']) ? $item['id'] : uniqid('tool_');
                                        $function_name = $item['function_call']['name'];
                                        $arguments = isset($item['function_call']['arguments']) ? $item['function_call']['arguments'] : array();
                                        
                                        // Safely encode arguments
                                        $arguments_json = function_exists('wp_json_encode') ? 
                                            wp_json_encode($arguments) : 
                                            json_encode($arguments);
                                            
                                        if ($arguments_json === false) {
                                            $extraction_info['errors'][] = "Failed to encode arguments for function '{$function_name}' in item {$item_index}";
                                            $arguments_json = '{}'; // Fallback to empty object
                                        }
                                        
                                        $tool_calls[] = array(
                                            'id' => $tool_call_id,
                                            'type' => 'function',
                                            'function' => array(
                                                'name' => $function_name,
                                                'arguments' => $arguments_json
                                            )
                                        );
                                        
                                        $extraction_info['tool_calls_extracted']++;
                                        
                                    }
                                } catch (Exception $e) {
                                    $extraction_info['errors'][] = "Error processing item {$item_index} in block {$block_index}: " . $e->getMessage();
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $extraction_info['errors'][] = "Error processing block {$block_index}: " . $e->getMessage();
                }
            }
            
        } catch (Exception $e) {
            $extraction_info['errors'][] = "Fatal error during tool extraction: " . $e->getMessage();
        }
        
        // Log extraction summary
        if (!empty($extraction_info['errors'])) {
            error_log('AI HTTP Client: Tool extraction completed with ' . count($extraction_info['errors']) . ' errors. Extracted ' . $extraction_info['tool_calls_extracted'] . ' tool calls.');
        }
        
        return array(
            'tool_calls' => $tool_calls,
            'extraction_info' => $extraction_info
        );
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
                // Accumulate data for completion callback
                $full_response .= $data;
                
                // Echo data to frontend EventSource for streaming
                echo $data;
                
                // Flush output to ensure immediate streaming
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
                
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