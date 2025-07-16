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
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => wp_json_encode($body),
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$full_response) {
                // Stream directly to output (frontend gets real-time data)
                echo $data;
                self::flush_output();
                
                // Accumulate for return value (simple pattern like original)
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
        
        error_log('AI HTTP Client: Using simple streaming pattern (like original working system)');

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curl_info = curl_getinfo($ch);
        curl_close($ch);

        error_log('AI HTTP Client: Stream completed. HTTP code: ' . $http_code);
        error_log('AI HTTP Client: Full response length: ' . strlen($full_response) . ' bytes');

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