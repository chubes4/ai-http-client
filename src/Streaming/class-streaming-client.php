<?php
/**
 * AI HTTP Client - Streaming HTTP Client
 * 
 * Single Responsibility: Handle streaming HTTP requests with cURL
 * WordPress wp_remote_post doesn't support streaming, so we use cURL directly
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Streaming_Client {

    /**
     * Make streaming HTTP POST request using cURL
     *
     * @param string $url Request URL
     * @param array $body Request body
     * @param array $headers Request headers
     * @param callable $callback Function to call for each streaming chunk
     * @param int $timeout Request timeout in seconds
     * @throws Exception If cURL is not available or request fails
     */
    public static function stream_post($url, $body, $headers, $callback, $timeout = 60) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL is required for streaming requests');
        }

        if (!is_callable($callback)) {
            throw new Exception('Callback must be callable');
        }

        $ch = curl_init();

        // Convert headers array to cURL format
        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => is_array($body) ? wp_json_encode($body) : $body,
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use ($callback) {
                return self::handle_streaming_chunk($data, $callback);
            },
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
            throw new Exception('cURL error: ' . $error);
        }

        if ($http_code >= 400) {
            throw new Exception("HTTP {$http_code} error");
        }
    }

    /**
     * Handle individual streaming chunks
     *
     * @param string $data Raw chunk data
     * @param callable $callback User callback function
     * @return int Length of data processed (required by cURL)
     */
    private static function handle_streaming_chunk($data, $callback) {
        static $buffer = '';
        
        // Accumulate data in buffer
        $buffer .= $data;
        
        // Process complete SSE chunks (ending with \n\n)
        while (($pos = strpos($buffer, "\n\n")) !== false) {
            $chunk = substr($buffer, 0, $pos + 2);
            $buffer = substr($buffer, $pos + 2);
            
            // Call user callback with the chunk
            try {
                call_user_func($callback, trim($chunk));
            } catch (Exception $e) {
                // Log error but continue processing
                error_log('AI HTTP Client streaming callback error: ' . $e->getMessage());
            }
        }
        
        return strlen($data);
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