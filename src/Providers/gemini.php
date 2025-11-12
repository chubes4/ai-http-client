<?php
/**
 * AI HTTP Client - Gemini Provider
 * 
 * Single Responsibility: Pure Google Gemini API communication only
 * No normalization logic - just sends/receives raw data
 * This is a "dumb" API client that the unified normalizers use
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Self-register Gemini provider with complete configuration
 * Self-contained provider architecture - no external normalizers needed
 */
add_filter('ai_providers', function($providers) {
    $providers['gemini'] = [
        'class' => 'AI_HTTP_Gemini_Provider',
        'type' => 'llm',
        'name' => 'Google Gemini'
    ];
    return $providers;
});

class AI_HTTP_Gemini_Provider {

    private $api_key;
    private $base_url;
    private $files_api_callback = null;

    public function __construct($config = []) {
        $this->api_key = isset($config['api_key']) ? $config['api_key'] : '';
        
        if (isset($config['base_url']) && !empty($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        } else {
            $this->base_url = 'https://generativelanguage.googleapis.com/v1beta';
        }
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    private function get_auth_headers() {
        return array(
            'x-goog-api-key' => $this->api_key
        );
    }

    /**
     * Gemini requires model in URL path, not request body
     */
    private function build_gemini_url_and_request($provider_request, $endpoint_suffix) {
        $model = isset($provider_request['model']) ? $provider_request['model'] : 'gemini-pro';
        $url = $this->base_url . '/models/' . $model . $endpoint_suffix;
        
        // Remove model from request body (it's in the URL)
        unset($provider_request['model']);
        
        return array($url, $provider_request);
    }

    /**
     * Send request to Gemini API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function request($standard_request) {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured - missing API key');
        }

        // Convert standard format to Gemini format internally
        $provider_request = $this->format_request($standard_request);
        
        list($url, $modified_request) = $this->build_gemini_url_and_request($provider_request, ':generateContent');
        
        // Use centralized ai_http filter
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($modified_request)
        ], 'Gemini');
        
        if (!$result['success']) {
            AIHttpError::trigger_api_error('gemini', ':generateContent', $result, [
                'request' => $modified_request
            ]);
            throw new Exception('Gemini API request failed: ' . esc_html($result['error']));
        }
        
        $raw_response = json_decode($result['data'], true);
        
        // Convert Gemini format to standard format
        return $this->format_response($raw_response);
    }

    /**
     * Send streaming request to Gemini API
     * Handles all format conversion internally - receives and returns standard format
     *
     * @param array $standard_request Standard request format
     * @param callable $callback Optional callback for each chunk
     * @return array Standard response format
     * @throws Exception If request fails
     */
    public function streaming_request($standard_request, $callback = null) {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured - missing API key');
        }

        // Convert standard format to Gemini format internally
        $provider_request = $this->format_request($standard_request);
        
        list($url, $modified_request) = $this->build_gemini_url_and_request($provider_request, ':streamGenerateContent');
        
        // Use centralized ai_http filter with streaming=true
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($modified_request)
        ], 'Gemini Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception('Gemini streaming request failed: ' . esc_html($result['error']));
        }

        // Return standardized streaming response
        return [
            'success' => true,
            'data' => [
                'content' => '',
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'model' => $standard_request['model'] ?? '',
                'finish_reason' => 'stop',
                'tool_calls' => null
            ],
            'error' => null,
            'provider' => 'gemini'
        ];
    }

    /**
     * Get available models from Gemini API
     *
     * @return array Raw models response
     * @throws Exception If request fails
     */
    public function get_raw_models() {
        if (!$this->is_configured()) {
            return array();
        }

        $url = $this->base_url . '/models';
        
        // Use centralized ai_http filter
        $result = apply_filters('ai_http', [], 'GET', $url, [
            'headers' => $this->get_auth_headers()
        ], 'Gemini');

        if (!$result['success']) {
            AIHttpError::trigger_api_error('gemini', ':generateContent', $result, [
                'request' => $modified_request
            ]);
            throw new Exception('Gemini API request failed: ' . esc_html($result['error']));
        }

        return json_decode($result['data'], true);
    }

    /**
     * Upload file to Google Gemini File API
     * 
     * @param string $file_path Path to file to upload
     * @param string $purpose Purpose for upload (default: 'user_data')
     * @return string File URI from Google
     * @throws Exception If upload fails
     */
    public function upload_file($file_path, $purpose = 'user_data') {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured');
        }

        if (!file_exists($file_path)) {
            throw new Exception('File not found: ' . esc_html($file_path));
        }

        // Google Gemini file upload endpoint
        $url = 'https://generativelanguage.googleapis.com/upload/v1beta/files?uploadType=multipart&key=' . $this->api_key;
        
        // Prepare multipart form data
        $boundary = wp_generate_uuid4();
        $headers = [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        ];

        // Build multipart body with metadata and file
        $body = '';
        
        // Metadata part
        $metadata = json_encode([
            'file' => [
                'display_name' => basename($file_path)
            ]
        ]);
        
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"metadata\"\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= $metadata . "\r\n";
        
        // File part
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="data"; filename="' . basename($file_path) . "\"\r\n";
        $body .= "Content-Type: " . mime_content_type($file_path) . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => $body
        ], 'Gemini File Upload');

        if (!$result['success']) {
            throw new Exception('Gemini file upload failed: ' . esc_html($result['error']));
        }

        $response_body = $result['data'];

        $data = json_decode($response_body, true);
        if (!isset($data['file']['uri'])) {
            throw new Exception('Gemini file upload response missing file URI');
        }

        return $data['file']['uri'];
    }

    /**
     * Delete file from Google Gemini File API
     * 
     * @param string $file_uri Gemini file URI to delete
     * @return bool Success status
     * @throws Exception If delete fails
     */
    public function delete_file($file_uri) {
        if (!$this->is_configured()) {
            throw new Exception('Gemini provider not configured');
        }

        // Extract file name from URI
        $file_name = basename(parse_url($file_uri, PHP_URL_PATH));
        $url = "https://generativelanguage.googleapis.com/v1beta/files/{$file_name}?key=" . $this->api_key;
        
        // Send request using centralized ai_http filter
        $result = apply_filters('ai_http', [], 'DELETE', $url, [], 'Gemini File Delete');

        if (!$result['success']) {
            throw new Exception('Gemini file delete failed: ' . esc_html($result['error']));
        }

        return $result['status_code'] === 200;
    }

    /**
     * Get normalized models for UI components
     * 
     * @return array Key-value array of model_id => display_name
     * @throws Exception If API call fails
     */
    public function get_normalized_models() {
        $raw_models = $this->get_raw_models();
        return $this->normalize_models_response($raw_models);
    }
    
    /**
     * Normalize Gemini models API response
     * 
     * @param array $raw_models Raw API response
     * @return array Normalized models array
     */
    private function normalize_models_response($raw_models) {
        $models = [];
        
        // Gemini returns: { "models": [{"name": "models/gemini-pro", "displayName": "Gemini Pro", ...}, ...] }
        $data = isset($raw_models['models']) ? $raw_models['models'] : $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['name'])) {
                    $model_id = str_replace('models/', '', $model['name']);
                    $display_name = isset($model['displayName']) ? $model['displayName'] : $model_id;
                    $models[$model_id] = $display_name;
                }
            }
        }
        
        return $models;
    }

    /**
     * Set files API callback
     *
     * @param callable $callback Callback function for file uploads
     */
    public function set_files_api_callback($callback) {
        $this->files_api_callback = $callback;
    }

    /**
     * Format unified request to Gemini API format
     *
     * @param array $unified_request Standard request format
     * @return array Gemini-formatted request
     * @throws Exception If validation fails
     */
    private function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        // Convert messages to Gemini contents format
        if (isset($request['messages'])) {
            // Process multimodal content before converting to Gemini format
            $processed_messages = $this->process_gemini_multimodal_messages($request['messages']);
            $request['contents'] = $this->convert_to_gemini_contents($processed_messages);
            unset($request['messages']);
        }

        // Process optional parameters (only if explicitly provided)
        // Max tokens parameter (OPTIONAL - only if explicitly provided)
        if (isset($request['max_tokens']) && !empty($request['max_tokens'])) {
            $request['generationConfig']['maxOutputTokens'] = max(1, intval($request['max_tokens']));
            unset($request['max_tokens']);
        }

        // Temperature parameter (OPTIONAL - only if explicitly provided) 
        if (isset($request['temperature']) && !empty($request['temperature'])) {
            $request['generationConfig']['temperature'] = max(0, min(1, floatval($request['temperature'])));
            unset($request['temperature']);
        }

        // Handle tools (OPTIONAL - only if explicitly provided)
        if (isset($request['tools']) && is_array($request['tools'])) {
            $request['tools'] = $this->normalize_gemini_tools($request['tools']);
        }

        // Handle tool_choice (OPTIONAL - only if explicitly provided)
        if (isset($request['tool_choice']) && !empty($request['tool_choice'])) {
            // Gemini uses toolConfig for tool selection
            if ($request['tool_choice'] === 'required') {
                $request['toolConfig'] = array('functionCallingConfig' => array('mode' => 'ANY'));
            }
            unset($request['tool_choice']);
        }

        return $request;
    }
    
    /**
     * Format Gemini response to unified standard format
     *
     * @param array $gemini_response Raw Gemini response
     * @return array Standard response format
     */
    private function format_response($gemini_response) {
        $content = '';
        $tool_calls = [];

        // Extract content from candidates
        if (isset($gemini_response['candidates']) && is_array($gemini_response['candidates'])) {
            $candidate = $gemini_response['candidates'][0] ?? array();
            
            if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $content .= $part['text'];
                    }
                    if (isset($part['functionCall'])) {
                        // Convert Gemini functionCall to standard format
                        $tool_calls[] = array(
                            'name' => $part['functionCall']['name'] ?? '',
                            'parameters' => $part['functionCall']['args'] ?? array()
                        );
                    }
                }
            }
        }

        // Extract usage (Gemini format)
        $usage = array(
            'prompt_tokens' => isset($gemini_response['usageMetadata']['promptTokenCount']) ? $gemini_response['usageMetadata']['promptTokenCount'] : 0,
            'completion_tokens' => isset($gemini_response['usageMetadata']['candidatesTokenCount']) ? $gemini_response['usageMetadata']['candidatesTokenCount'] : 0,
            'total_tokens' => isset($gemini_response['usageMetadata']['totalTokenCount']) ? $gemini_response['usageMetadata']['totalTokenCount'] : 0
        );

        return array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => $gemini_response['modelVersion'] ?? '',
                'finish_reason' => isset($gemini_response['candidates'][0]['finishReason']) ? $gemini_response['candidates'][0]['finishReason'] : 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null
            ),
            'error' => null,
            'provider' => 'gemini',
            'raw_response' => $gemini_response
        );
    }
    
    /**
     * Validate unified request format
     *
     * @param array $request Request to validate
     * @throws Exception If invalid
     */
    private function validate_unified_request($request) {
        if (!is_array($request)) {
            throw new Exception('Request must be an array');
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            throw new Exception('Request must include messages array');
        }

        if (empty($request['messages'])) {
            throw new Exception('Messages array cannot be empty');
        }
    }
    
    /**
     * Sanitize common fields
     *
     * @param array $request Request to sanitize
     * @return array Sanitized request
     */
    private function sanitize_common_fields($request) {
        // Sanitize messages
        if (isset($request['messages'])) {
            foreach ($request['messages'] as &$message) {
                if (isset($message['role'])) {
                    $message['role'] = sanitize_text_field($message['role']);
                }
                if (isset($message['content']) && is_string($message['content'])) {
                    $message['content'] = sanitize_textarea_field($message['content']);
                }
            }
        }

        // Sanitize other common fields
        if (isset($request['model'])) {
            $request['model'] = sanitize_text_field($request['model']);
        }

        return $request;
    }
    
    /**
     * Convert messages to Gemini contents format
     *
     * @param array $messages Standard messages (may contain mixed content)
     * @return array Gemini contents format
     */
    private function convert_to_gemini_contents($messages) {
        $contents = [];

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                continue;
            }

            // Map roles
            $role = $message['role'] === 'assistant' ? 'model' : 'user';

            // Skip system messages for now (Gemini handles differently)
            if ($message['role'] === 'system') {
                continue;
            }

            $parts = [];

            // Handle mixed content (text + files)
            if (is_array($message['content'])) {
                foreach ($message['content'] as $content_item) {
                    if (isset($content_item['type'])) {
                        switch ($content_item['type']) {
                            case 'text':
                                if (!empty($content_item['content'])) {
                                    $parts[] = array('text' => $content_item['content']);
                                }
                                break;
                            case 'file':
                                if (!empty($content_item['file_uri'])) {
                                    $parts[] = array(
                                        'file_data' => array(
                                            'file_uri' => $content_item['file_uri']
                                        )
                                    );
                                }
                                break;
                        }
                    }
                }
            } else {
                // Simple text content
                $parts[] = array('text' => $message['content']);
            }

            if (!empty($parts)) {
                $contents[] = array(
                    'role' => $role,
                    'parts' => $parts
                );
            }
        }

        return $contents;
    }

    /**
     * Convert standard tools format to Gemini tools format
     *
     * @param array $standard_tools Standard tools array
     * @return array Gemini-formatted tools
     */
    private function normalize_gemini_tools($standard_tools) {
        $gemini_tools = [];
        
        foreach ($standard_tools as $tool) {
            if (isset($tool['name'], $tool['description'])) {
                $gemini_function = array(
                    'name' => $tool['name'],
                    'description' => $tool['description']
                );
                
                // Convert parameters to Gemini parameters format
                if (isset($tool['parameters']) && is_array($tool['parameters'])) {
                    $properties = [];
                    $required = [];
                    
                    foreach ($tool['parameters'] as $param_name => $param_config) {
                        $properties[$param_name] = [];
                        
                        if (isset($param_config['type'])) {
                            $properties[$param_name]['type'] = $param_config['type'];
                        }
                        if (isset($param_config['description'])) {
                            $properties[$param_name]['description'] = $param_config['description'];
                        }
                        if (isset($param_config['required']) && $param_config['required']) {
                            $required[] = $param_name;
                        }
                    }
                    
                    $gemini_function['parameters'] = array(
                        'type' => 'object',
                        'properties' => $properties
                    );
                    
                    if (!empty($required)) {
                        $gemini_function['parameters']['required'] = $required;
                    }
                }
                
                $gemini_tools[] = array('functionDeclarations' => array($gemini_function));
            }
        }
        
        return $gemini_tools;
    }

    /**
     * Process messages for multimodal content (files/images)
     *
     * @param array $messages Array of messages
     * @return array Processed messages with file uploads
     */
    private function process_gemini_multimodal_messages($messages) {
        $processed_messages = [];

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                $processed_messages[] = $message;
                continue;
            }

            $processed_message = array('role' => $message['role']);

            // Handle multimodal content (files) or content arrays
            if (is_array($message['content'])) {
                $processed_message['content'] = $this->build_gemini_multimodal_content($message['content']);
            } else {
                $processed_message['content'] = $message['content'];
            }

            // Preserve other message fields
            foreach ($message as $key => $value) {
                if (!in_array($key, array('role', 'content'))) {
                    $processed_message[$key] = $value;
                }
            }

            $processed_messages[] = $processed_message;
        }

        return $processed_messages;
    }

    /**
     * Build Gemini multimodal content with Files API integration
     *
     * @param array $content_items Array of content items
     * @return array Mixed content (text + file URIs)
     */
    private function build_gemini_multimodal_content($content_items) {
        $mixed_content = [];

        foreach ($content_items as $content_item) {
            if (isset($content_item['type'])) {
                switch ($content_item['type']) {
                    case 'text':
                        $mixed_content[] = array(
                            'type' => 'text',
                            'content' => $content_item['text'] ?? ''
                        );
                        break;

                    case 'file':
                        try {
                            $file_path = $content_item['file_path'] ?? '';
                            $mime_type = $content_item['mime_type'] ?? '';

                            if (empty($file_path) || !file_exists($file_path)) {
                                continue 2; // Skip this content item
                            }

                            if (empty($mime_type)) {
                                $mime_type = mime_content_type($file_path);
                            }

                            // Validate file type against Gemini's supported formats
                            if (!$this->is_supported_file_type($mime_type)) {
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log("Gemini: Unsupported file type: {$mime_type} for file: {$file_path}");
                                }
                                continue 2; // Skip this content item
                            }

                            // Upload file via Files API and get URI
                            $file_uri = $this->upload_file_via_files_api($file_path);

                            $mixed_content[] = array(
                                'type' => 'file',
                                'file_uri' => $file_uri
                            );

                        } catch (Exception $e) {
                            // Log error but continue processing other content
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('Gemini file upload failed: ' . $e->getMessage());
                            }
                        }
                        break;

                    default:
                        // Pass through other content types
                        $mixed_content[] = $content_item;
                        break;
                }
            }
        }

        return $mixed_content;
    }

    /**
     * Upload file via Files API callback
     *
     * @param string $file_path Path to file to upload
     * @return string File URI from Files API
     * @throws Exception If upload fails
     */
    private function upload_file_via_files_api($file_path) {
        if (!$this->files_api_callback) {
            throw new Exception('Files API callback not set - cannot upload files');
        }

        if (!file_exists($file_path)) {
            throw new Exception('File not found: ' . esc_html($file_path));
        }

        return call_user_func($this->files_api_callback, $file_path, 'user_data', 'gemini');
    }

    /**
     * Check if file type is supported by Gemini Files API
     *
     * @param string $mime_type MIME type of the file
     * @return bool True if supported
     */
    private function is_supported_file_type($mime_type) {
        $supported_types = [
            // Images
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            // Audio
            'audio/wav',
            'audio/mp3',
            'audio/mpeg',
            'audio/aiff',
            'audio/aac',
            'audio/ogg',
            'audio/flac',
            // Video
            'video/mp4',
            'video/mpeg',
            'video/mov',
            'video/avi',
            'video/x-flv',
            'video/mpg',
            'video/webm',
            'video/wmv',
            'video/3gpp',
            // Documents
            'application/pdf',
            'text/plain',
            // Add other Gemini supported types as needed
        ];

        return in_array($mime_type, $supported_types, true);
    }


}