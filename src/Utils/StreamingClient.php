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

        // Ensure streaming is enabled
        $body['stream'] = true;
        
        $full_response = '';
        
        // Convert headers array to cURL format
        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }
        
        // Add required headers for SSE
        $curl_headers[] = 'Accept: text/event-stream';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => wp_json_encode($body),
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$full_response) {
                // Stream the raw data directly to output buffer
                echo $data;
                
                // Flush immediately for real-time streaming
                self::flush_output();
                
                // Accumulate for completion callback
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