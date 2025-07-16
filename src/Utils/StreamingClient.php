<?php
/**
 * AI HTTP Client - Streaming HTTP Client
 * 
 * Single Responsibility: Handle basic streaming HTTP requests with cURL
 * Provider-agnostic - each provider handles its own SSE parsing
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Streaming_Client {

    /**
     * Stream HTTP POST request using cURL with real-time output
     * Provider-agnostic - streams raw data directly to output buffer
     *
     * @param string $url Request URL
     * @param array $body Request body
     * @param array $headers Request headers
     * @param callable|null $completion_callback Called when stream completes with full response
     * @param int $timeout Request timeout in seconds
     * @return string Full raw response from API
     * @throws Exception If cURL is not available or request fails
     */
    public static function stream_post($url, $body, $headers, $completion_callback = null, $timeout = 60) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL is required for streaming requests');
        }

        // Comprehensive request logging
        error_log('AI HTTP Client: Starting streaming request to: ' . $url);
        error_log('AI HTTP Client: Request body: ' . wp_json_encode($body));
        error_log('AI HTTP Client: Request headers: ' . wp_json_encode($headers));
        error_log('AI HTTP Client: cURL version: ' . curl_version()['version']);
        error_log('AI HTTP Client: CURLOPT_RETURNTRANSFER will be set to: false');

        // Ensure streaming is enabled
        $body['stream'] = true;
        
        $full_response = '';
        $chunk_count = 0;
        
        // Convert headers array to cURL format
        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }
        
        // Add required headers for SSE
        $curl_headers[] = 'Accept: text/event-stream';

        $ch = curl_init($url);
        
        // Try the WordPress HTTP API approach first (like original Wordsurf)
        if (function_exists('wp_remote_post') && !$completion_callback) {
            error_log('AI HTTP Client: Using WordPress HTTP API for non-streaming request');
            
            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => wp_json_encode($body),
                'timeout' => $timeout,
                'stream' => false
            ));
            
            if (is_wp_error($response)) {
                throw new Exception('WordPress HTTP error: ' . $response->get_error_message());
            }
            
            $full_response = wp_remote_retrieve_body($response);
            return $full_response;
        }
        
        // For streaming, use the working approach: stream to frontend, capture via output buffering
        ob_start();
        
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => wp_json_encode($body),
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$full_response, &$chunk_count) {
                error_log("AI HTTP Client: WRITEFUNCTION CALLED!");
                
                $chunk_count++;
                $data_length = strlen($data);
                
                error_log("AI HTTP Client: Received chunk {$chunk_count}, {$data_length} bytes");
                
                // Stream to frontend AND capture for callback
                echo $data;
                self::flush_output();
                
                $full_response .= $data;
                return $data_length;
            },
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
        ]);
        
        error_log('AI HTTP Client: Using cURL with output buffering for response capture');

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curl_info = curl_getinfo($ch);
        curl_close($ch);

        // Capture output buffer as fallback if WRITEFUNCTION didn't work
        $output_buffer = ob_get_contents();
        ob_end_clean();
        
        // If WRITEFUNCTION didn't accumulate data, use output buffer
        if (strlen($full_response) === 0 && strlen($output_buffer) > 0) {
            error_log('AI HTTP Client: WRITEFUNCTION failed, using output buffer fallback');
            $full_response = $output_buffer;
        }

        // Comprehensive response logging
        error_log('AI HTTP Client: cURL completed. HTTP code: ' . $http_code);
        error_log('AI HTTP Client: cURL error: ' . ($error ?: 'none'));
        error_log('AI HTTP Client: Total chunks received: ' . $chunk_count);
        error_log('AI HTTP Client: WRITEFUNCTION response length: ' . strlen($full_response) . ' bytes');
        error_log('AI HTTP Client: Output buffer length: ' . strlen($output_buffer) . ' bytes');
        error_log('AI HTTP Client: Final response length: ' . strlen($full_response) . ' bytes');

        if ($result === false) {
            error_log('AI HTTP Client: cURL exec failed with error: ' . $error);
            throw new Exception('cURL streaming error: ' . $error);
        }

        if ($http_code >= 400) {
            // Log the actual error response for debugging
            if (function_exists('error_log')) {
                error_log("AI HTTP Client: HTTP {$http_code} error response: " . $full_response);
            }
            throw new Exception("HTTP {$http_code} streaming error");
        }
        
        // Call completion callback if provided
        if ($completion_callback && is_callable($completion_callback)) {
            try {
                error_log('AI HTTP Client: Calling completion callback with ' . strlen($full_response) . ' bytes of data');
                error_log('AI HTTP Client: Full response preview: ' . substr($full_response, 0, 200) . '...');
                
                // Log the COMPLETE response if it's not too large (for debugging tool calls)
                if (strlen($full_response) > 0 && strlen($full_response) < 50000) {
                    error_log('AI HTTP Client: COMPLETE API RESPONSE: ' . $full_response);
                } else {
                    error_log('AI HTTP Client: Response too large to log completely (' . strlen($full_response) . ' bytes)');
                }
                
                call_user_func($completion_callback, $full_response);
            } catch (Exception $e) {
                error_log('AI HTTP Client streaming completion callback error: ' . $e->getMessage());
            }
        }

        return $full_response;
    }

    /**
     * Flush output buffer immediately
     */
    private static function flush_output() {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Test if streaming is available on this system
     *
     * @return bool True if streaming is supported
     */
    public static function is_streaming_available() {
        return function_exists('curl_init') && function_exists('curl_setopt');
    }
}