<?php
/**
 * AI HTTP Client - File Upload Client
 * 
 * Single Responsibility: Handle file uploads to AI provider APIs
 * Based on Data Machine's working PDF upload implementation
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_File_Upload_Client {

    /**
     * Upload file to provider's file API
     * Based on Data Machine's PDF upload patterns
     *
     * @param string $file_path Local file path to upload
     * @param string $upload_url Provider's file upload endpoint
     * @param array $headers Authentication headers
     * @param string $purpose Purpose for the file (e.g., 'assistants', 'fine-tune')
     * @param int $timeout Request timeout in seconds
     * @return array Upload response with file ID
     * @throws Exception If upload fails
     */
    public static function upload_file($file_path, $upload_url, $headers, $purpose = 'assistants', $timeout = 120) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL is required for file uploads');
        }

        if (!file_exists($file_path)) {
            throw new Exception('File not found: ' . $file_path);
        }

        if (!is_readable($file_path)) {
            throw new Exception('File not readable: ' . $file_path);
        }

        $file_size = filesize($file_path);
        if ($file_size === false) {
            throw new Exception('Could not determine file size: ' . $file_path);
        }

        // Convert headers array to cURL format
        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }

        // Create multipart form data
        $post_fields = array(
            'file' => curl_file_create($file_path, self::get_mime_type($file_path), basename($file_path)),
            'purpose' => $purpose
        );

        $ch = curl_init($upload_url);
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $post_fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('cURL file upload error: ' . $error);
        }

        if ($http_code >= 400) {
            throw new Exception("HTTP {$http_code} file upload error: " . $response);
        }

        $decoded_response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from file upload: ' . $response);
        }

        return $decoded_response;
    }

    /**
     * Upload file from WordPress media library
     * WordPress-specific wrapper for file uploads
     *
     * @param int $attachment_id WordPress attachment ID
     * @param string $upload_url Provider's file upload endpoint
     * @param array $headers Authentication headers
     * @param string $purpose Purpose for the file
     * @param int $timeout Request timeout in seconds
     * @return array Upload response with file ID
     * @throws Exception If attachment not found or upload fails
     */
    public static function upload_attachment($attachment_id, $upload_url, $headers, $purpose = 'assistants', $timeout = 120) {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path) {
            throw new Exception('Attachment not found: ' . $attachment_id);
        }

        return self::upload_file($file_path, $upload_url, $headers, $purpose, $timeout);
    }

    /**
     * Get MIME type for file
     *
     * @param string $file_path File path
     * @return string MIME type
     */
    private static function get_mime_type($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        $mime_types = array(
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'json' => 'application/json',
            'csv' => 'text/csv',
            'md' => 'text/markdown',
            'html' => 'text/html',
            'htm' => 'text/html',
            'xml' => 'text/xml'
        );

        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }

    /**
     * Check if file size is suitable for upload vs embedding
     * Based on Data Machine's size thresholds
     *
     * @param string $file_path File path
     * @param int $max_embed_size Maximum size for embedding (default 5MB like Data Machine)
     * @return bool True if file should be uploaded, false if it can be embedded
     */
    public static function should_upload_file($file_path, $max_embed_size = 5242880) {
        if (!file_exists($file_path)) {
            return false;
        }

        $file_size = filesize($file_path);
        return $file_size === false || $file_size > $max_embed_size;
    }

    /**
     * Encode file to base64 for embedding
     * For smaller files that don't need upload API
     *
     * @param string $file_path File path
     * @return string Base64 encoded file content
     * @throws Exception If file cannot be read
     */
    public static function encode_file_base64($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception('File not found: ' . $file_path);
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            throw new Exception('Could not read file: ' . $file_path);
        }

        $mime_type = self::get_mime_type($file_path);
        return 'data:' . $mime_type . ';base64,' . base64_encode($content);
    }

    /**
     * Delete uploaded file from provider
     *
     * @param string $file_id File ID from upload response
     * @param string $delete_url Provider's file delete endpoint
     * @param array $headers Authentication headers
     * @param int $timeout Request timeout in seconds
     * @return bool True if deletion successful
     */
    public static function delete_file($file_id, $delete_url, $headers, $timeout = 30) {
        if (!function_exists('curl_init')) {
            return false;
        }

        $url = rtrim($delete_url, '/') . '/' . $file_id;
        
        // Convert headers array to cURL format
        $curl_headers = array();
        foreach ($headers as $key => $value) {
            $curl_headers[] = $key . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_HTTPHEADER => $curl_headers,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $response !== false && $http_code >= 200 && $http_code < 300;
    }
}