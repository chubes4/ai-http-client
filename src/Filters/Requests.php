<?php
/**
 * AI HTTP Client - Request Processing Filters
 * 
 * Complete AI request processing system via WordPress filter system.
 * Handles HTTP communication, AI request processing, provider management,
 * and response formatting in a unified pipeline.
 *
 * @package AIHttpClient\Filters
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Register AI request processing filters
 */
function ai_http_client_register_provider_filters() {

    // Universal file-to-base64 conversion filter
    // Usage: $base64_data_url = apply_filters('chubes_ai_file_to_base64', '', $file_path, $options);
    // Returns: "data:image/jpeg;base64,/9j/4AAQ..." format or empty string on failure
    add_filter('chubes_ai_file_to_base64', function($default, $file_path, $options = []) {
        if (empty($file_path) || !is_string($file_path)) {
            return '';
        }

        if (!file_exists($file_path) || !is_readable($file_path)) {
            return '';
        }
        
        $max_size = $options['max_size'] ?? (10 * 1024 * 1024);
        $file_size = filesize($file_path);
        if ($file_size > $max_size) {
            return '';
        }
        
        $mime_type = mime_content_type($file_path);
        if (!$mime_type) {
            $file_info = wp_check_filetype($file_path);
            $mime_type = $file_info['type'] ?? 'application/octet-stream';
        }
        
        $supported_types = $options['supported_types'] ?? [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'
        ];

        if (!in_array($mime_type, $supported_types)) {
            return '';
        }
        
        $file_content = file_get_contents($file_path);
        if ($file_content === false) {
            return '';
        }

        $base64_content = base64_encode($file_content);
        return "data:{$mime_type};base64,{$base64_content}";
    }, 10, 3);
    
    // Internal HTTP request handling for AI API calls
    // Usage: $result = apply_filters('chubes_ai_http', [], 'POST', $url, $args, 'Provider Context', false, $callback);
    // Streaming: $result = apply_filters('chubes_ai_http', [], 'POST', $url, $args, 'Provider Context', true, $callback);
    add_filter('chubes_ai_http', function($default, $method, $url, $args, $context, $streaming = false, $callback = null) {
        $valid_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = strtoupper($method);
        if (!in_array($method, $valid_methods)) {
            return ['success' => false, 'error' => 'Invalid HTTP method'];
        }

        $args = wp_parse_args($args, [
            'user-agent' => sprintf('AI-HTTP-Client/%s (+WordPress)',
                defined('AI_HTTP_CLIENT_VERSION') ? AI_HTTP_CLIENT_VERSION : '1.0'),
            'timeout' => 120
        ]);

        if ($method !== 'GET') {
            $args['method'] = $method;
        }


        if ($streaming) {
            $headers = $args['headers'] ?? [];
            $body = $args['body'] ?? '';

            $formatted_headers = [];
            foreach ($headers as $key => $value) {
                $formatted_headers[] = $key . ': ' . $value;
            }

            // Add stream=true to JSON requests
            if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json' && !empty($body)) {
                $decoded_body = json_decode($body, true);
                if (is_array($decoded_body)) {
                    $decoded_body['stream'] = true;
                    $body = json_encode($decoded_body);
                }
            }
            
            $response_body = '';
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => ($method !== 'GET'),
                CURLOPT_POSTFIELDS => ($method !== 'GET') ? $body : null,
                CURLOPT_HTTPHEADER => $formatted_headers,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_WRITEFUNCTION => function($ch, $data) use ($callback, &$response_body) {
                    $response_body .= $data;
                    if ($callback && is_callable($callback)) {
                        call_user_func($callback, $data);
                    } else {
                        echo esc_html($data);
                        flush();
                    }
                    return strlen($data);
                },
                CURLOPT_RETURNTRANSFER => false
            ]);

            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if (!empty($error)) {
                AIHttpError::trigger_error('Requests', "Connection error to {$context}: {$error}", [
                    'provider' => $context,
                    'error_type' => 'curl_error',
                    'details' => $error
                ]);
            } elseif ($http_code >= 400) {
                AIHttpError::trigger_error('Requests', "HTTP {$http_code} error from {$context}", [
                    'provider' => $context,
                    'http_code' => $http_code,
                    'error_type' => 'http_error'
                ]);
            }

            if ($result === false || !empty($error)) {
                return ['success' => false, 'error' => "Streaming request failed: {$error}"];
            }

            if ($http_code < 200 || $http_code >= 300) {
                return ['success' => false, 'error' => "HTTP {$http_code} response from {$context}"];
            }

            return [
                'success' => true,
                'data' => '', // Streaming outputs directly, no data returned
                'status_code' => $http_code,
                'headers' => [],
                'error' => ''
            ];
        }

        $response = ($method === 'GET') ? wp_remote_get($url, $args) : wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = "Failed to connect to {$context}: " . $response->get_error_message();

            AIHttpError::trigger_error('Requests', $error_message, [
                'provider' => $context,
                'error_type' => 'connection_error',
                'wp_error' => $response->get_error_message()
            ]);

            return ['success' => false, 'error' => $error_message];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        if ($status_code >= 400) {
            AIHttpError::trigger_error('Requests', "HTTP {$status_code} error from {$context}", [
                'provider' => $context,
                'http_code' => $status_code,
                'response_body' => $body,
                'error_type' => 'api_error'
            ]);
        }

        $success = ($status_code >= 200 && $status_code < 300);
        
        return [
            'success' => $success,
            'data' => $body,
            'status_code' => $status_code,
            'headers' => $headers,
            'error' => $success ? '' : "HTTP {$status_code} response from {$context}"
        ];
    }, 10, 7);
    

    // Public AI Request filter - high-level plugin interface
    // Usage: $response = apply_filters('chubes_ai_request', $request, $provider_name, $streaming_callback, $tools, $conversation_data);
    add_filter('chubes_ai_request', function($request, $provider_name = null, $streaming_callback = null, $tools = null, $conversation_data = null) {
        

        if (!is_array($request)) {
            return ai_http_create_error_response('Request must be an array');
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return ai_http_create_error_response('Request must include messages array');
        }

        if (empty($request['messages'])) {
            return ai_http_create_error_response('Messages array cannot be empty');
        }
        
        if ($tools && is_array($tools)) {
            if (!isset($request['tools'])) {
                $request['tools'] = [];
            }
            $request['tools'] = array_merge($request['tools'], $tools);
        }
        
        if ($conversation_data && is_array($conversation_data)) {
            $shared_api_keys = apply_filters('chubes_ai_provider_api_keys', null);
            $api_key = $shared_api_keys[$provider_name] ?? '';
            if (!empty($api_key)) {
                $provider_config = ['api_key' => $api_key];
                $provider = ai_http_create_provider($provider_name, $provider_config);
                
                if ($provider && method_exists($provider, 'get_conversation_continuation')) {
                    $continuation_info = $provider->get_conversation_continuation();
                    
                    if ($continuation_info && isset($continuation_info['type'])) {
                        switch ($continuation_info['type']) {
                            case 'stateful':
                                if (isset($conversation_data['previous_response_id'])) {
                                    $request['previous_response_id'] = $conversation_data['previous_response_id'];
                                }
                                break;

                            case 'stateless':
                                if (isset($conversation_data['conversation_history'])) {
                                    $request['messages'] = $conversation_data['conversation_history'];
                                }
                                break;
                        }
                    }
                }
            }
        }
        
        try {
            // Provider name is now required - library no longer auto-discovers providers
            if (!$provider_name) {
                return ai_http_create_error_response('Provider name must be specified for AI requests');
            }
            
            // Build provider config from shared API keys
            $shared_api_keys = apply_filters('chubes_ai_provider_api_keys', null);
            $api_key = $shared_api_keys[$provider_name] ?? '';
            
            if (empty($api_key)) {
                return ai_http_create_error_response("No API key configured for provider '{$provider_name}'");
            }
            
            $provider_config = ['api_key' => $api_key];
            
            // Get provider instance
            $provider = ai_http_create_provider($provider_name, $provider_config);
            if (!$provider) {
                return ai_http_create_error_response("Failed to create provider instance for '{$provider_name}'");
            }
            
            // Handle streaming vs standard requests - clean interface
            if ($streaming_callback) {
                // Streaming request - provider handles all format conversion internally
                $standard_response = $provider->streaming_request($request, $streaming_callback);
            } else {
                // Standard request - provider handles all format conversion internally
                $standard_response = $provider->request($request);
            }
            
            return $standard_response;
            
        } catch (Exception $e) {
            return ai_http_create_error_response($e->getMessage(), $provider_name);
        }
    }, 99, 6); // Priority 99: Execute after directive filters complete
}

/**
 * Create standardized error response
 *
 * @param string $error_message Error message
 * @param string $provider_name Provider name
 * @return array Standardized error response
 */
function ai_http_create_error_response($error_message, $provider_name = 'unknown') {
    return array(
        'success' => false,
        'data' => null,
        'error' => $error_message,
        'provider' => $provider_name,
        'raw_response' => null
    );
}

// Note: Normalizer initialization removed - providers now self-contained

/**
 * Create provider instance
 *
 * @param string $provider_name Provider name
 * @param array $provider_config Provider configuration (required)
 * @return object|false Provider instance or false on failure
 */
function ai_http_create_provider($provider_name, $provider_config) {
    // Use filter-based provider discovery
    $all_providers = apply_filters('chubes_ai_providers', []);
    $provider_info = $all_providers[strtolower($provider_name)] ?? null;
    if (!$provider_info) {
        return false;
    }

    // Get provider class and create instance
    $provider_class = $provider_info['class'];
    $provider = new $provider_class($provider_config);

    // Set up Files API callback for file uploads in self-contained providers
    if (method_exists($provider, 'set_files_api_callback')) {
        $provider->set_files_api_callback(function($file_path, $purpose = 'user_data') use ($provider_name, $provider_config) {
            return ai_http_upload_file_to_provider($file_path, $purpose, $provider_name, $provider_config);
        });
    }

    return $provider;
}

/**
 * Upload file to provider's Files API
 *
 * @param string $file_path Path to file to upload
 * @param string $purpose Purpose for upload
 * @param string $provider_name Provider to upload to
 * @param array $provider_config Provider configuration
 * @return string File ID from provider's Files API
 * @throws Exception If upload fails
 */
function ai_http_upload_file_to_provider($file_path, $purpose = 'user_data', $provider_name = 'openai', $provider_config = []) {
    $provider = ai_http_create_provider($provider_name, $provider_config);

    if (!$provider) {
        throw new Exception(esc_html($provider_name) . ' provider not available for Files API upload');
    }

    return $provider->upload_file($file_path, $purpose);
}

// Initialize provider filters on WordPress init
add_action('init', 'ai_http_client_register_provider_filters');