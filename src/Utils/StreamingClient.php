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
     * Provider-agnostic - normalizes all provider responses to standard format
     *
     * @param string $url Request URL
     * @param array $body Request body
     * @param array $headers Request headers
     * @param callable|null $completion_callback Called when stream completes with full response
     * @param int $timeout Request timeout in seconds
     * @param string $provider Provider name for normalization
     * @return string Full raw response from API
     * @throws Exception If cURL is not available or request fails
     */
    public static function stream_post($url, $body, $headers, $streaming_callback = null, $timeout = 60, $provider = null) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL is required for streaming requests');
        }
        
        if (!$provider) {
            throw new Exception('Provider parameter is required for streaming normalization');
        }

        // Comprehensive request logging
        error_log('AI HTTP Client: Starting streaming request to: ' . $url);
        error_log('AI HTTP Client: Request body: ' . wp_json_encode($body));
        error_log('AI HTTP Client: Request headers: ' . wp_json_encode($headers));
        error_log('AI HTTP Client: cURL version: ' . curl_version()['version']);
        error_log('AI HTTP Client: CURLOPT_RETURNTRANSFER will be set to: false');

        // Note: Streaming parameters are handled by individual providers
        // Some providers use 'stream: true' in body, others use URL parameters
        
        $full_response = '';
        $chunk_count = 0;
        
        // Convert headers array to cURL format
        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }
        
        // Add required headers for SSE
        $curl_headers[] = 'Accept: text/event-stream';
        
        // Debug log all headers being sent
        error_log('AI HTTP Client: All cURL headers: ' . print_r($curl_headers, true));

        $ch = curl_init($url);
        
        // Encode JSON data
        $json_data = function_exists('wp_json_encode') ? wp_json_encode($body) : json_encode($body);
        
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$full_response, &$chunk_count, $streaming_callback) {
                $chunk_count++;
                
                // If a streaming callback is provided, use it to process the data
                if ($streaming_callback && is_callable($streaming_callback)) {
                    // Let the callback handle the streaming output
                    call_user_func($streaming_callback, $data);
                } else {
                    // Fallback: just pass through the data
                    echo $data;
                    
                    // Flush the output buffer to ensure the data is sent immediately
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
                
                // Append the data to our buffer to capture the full response
                $full_response .= $data;
                error_log("AI HTTP Client: Streaming chunk " . $chunk_count . ": " . strlen($data) . " bytes");
                error_log("AI HTTP Client: Raw chunk data: " . $data);
                
                // Return the number of bytes written
                return strlen($data);
            }
        ]);
        
        error_log('AI HTTP Client: Using simple streaming pattern (like original working system)');

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curl_info = curl_getinfo($ch);
        curl_close($ch);

        error_log('AI HTTP Client: Stream completed. HTTP code: ' . $http_code);
        error_log('AI HTTP Client: Full response length: ' . strlen($full_response) . ' bytes');
        error_log('AI HTTP Client: Full response content: ' . $full_response);

        if ($result === false) {
            throw new Exception('cURL streaming error: ' . $error);
        }

        if ($http_code >= 400) {
            error_log("AI HTTP Client: HTTP {$http_code} error response: " . substr($full_response, 0, 1000));
            error_log("AI HTTP Client: Full error response length: " . strlen($full_response) . " bytes");
            error_log("AI HTTP Client: cURL error: " . $error);
            error_log("AI HTTP Client: Response info: " . print_r($curl_info, true));
            throw new Exception("HTTP {$http_code} streaming error: " . substr($full_response, 0, 200));
        }

        // Simple pattern: just return the full response for external processing
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