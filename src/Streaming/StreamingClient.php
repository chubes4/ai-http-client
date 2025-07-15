<?php
/**
 * AI HTTP Client - Streaming HTTP Client
 * 
 * Single Responsibility: Handle streaming HTTP requests with cURL
 * Based on proven Wordsurf streaming implementation
 *
 * @package AIHttpClient\Streaming
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Streaming_Client {

    /**
     * Stream HTTP POST request using cURL with real-time output
     * Based on Wordsurf's proven streaming implementation
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
        $stream_completed = false;
        
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
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$full_response, &$stream_completed, $completion_callback) {
                // Stream the raw data directly to output buffer (like Wordsurf)
                echo $data;
                
                // Flush immediately for real-time streaming
                self::flush_output();
                
                // Accumulate for completion callback
                $full_response .= $data;
                
                // Check for stream completion
                if (strpos($data, 'data: [DONE]') !== false) {
                    $stream_completed = true;
                }
                
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
            throw new Exception("HTTP {$http_code} streaming error");
        }
        
        // Call completion callback if provided (like Wordsurf)
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
     * Flush output buffer immediately (from Wordsurf pattern)
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

    /**
     * Get streaming capability info
     *
     * @return array Capability information
     */
    public static function get_streaming_info() {
        $info = [
            'available' => self::is_streaming_available(),
            'curl_version' => null,
            'openssl_version' => null
        ];

        if (function_exists('curl_version')) {
            $curl_info = curl_version();
            $info['curl_version'] = $curl_info['version'] ?? 'unknown';
            $info['openssl_version'] = $curl_info['ssl_version'] ?? 'unknown';
        }

        return $info;
    }
}