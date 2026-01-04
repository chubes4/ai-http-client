<?php
/**
 * AI HTTP Client - Gemini Provider
 * 
 * Google Gemini API implementation extending BaseProvider.
 * Handles unique URL structure (model in path) and Files API.
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

add_filter('chubes_ai_providers', function($providers) {
    $providers['gemini'] = [
        'class' => 'AI_HTTP_Gemini_Provider',
        'type' => 'llm',
        'name' => 'Google Gemini'
    ];
    return $providers;
});

class AI_HTTP_Gemini_Provider extends AI_HTTP_BaseProvider {

    protected function get_default_base_url() {
        return 'https://generativelanguage.googleapis.com/v1beta';
    }

    protected function get_auth_headers() {
        return ['x-goog-api-key' => $this->api_key];
    }

    protected function get_provider_name() {
        return 'Gemini';
    }

    protected function get_chat_endpoint() {
        return ':generateContent';
    }

    protected function get_models_endpoint() {
        return '/models';
    }

    protected function build_request_url($provider_request) {
        $model = $provider_request['model'] ?? 'gemini-pro';
        unset($provider_request['model']);
        return $this->base_url . '/models/' . $model . ':generateContent';
    }

    protected function build_streaming_url($provider_request) {
        $model = $provider_request['model'] ?? 'gemini-pro';
        return $this->base_url . '/models/' . $model . ':streamGenerateContent';
    }

    public function request($standard_request) {
        $this->validate_configured();
        
        $provider_request = $this->format_request($standard_request);
        
        $model = $provider_request['model'] ?? 'gemini-pro';
        unset($provider_request['model']);
        $url = $this->base_url . '/models/' . $model . ':generateContent';
        
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('chubes_ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], $this->get_provider_name());

        if (!$result['success']) {
            $this->handle_request_error($result, $provider_request);
        }
        
        $raw_response = json_decode($result['data'], true);
        return $this->format_response($raw_response);
    }

    public function streaming_request($standard_request, $callback = null) {
        $this->validate_configured();
        
        $provider_request = $this->format_request($standard_request);
        
        $model = $provider_request['model'] ?? 'gemini-pro';
        unset($provider_request['model']);
        $url = $this->base_url . '/models/' . $model . ':streamGenerateContent';
        
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('chubes_ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], $this->get_provider_name() . ' Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception($this->get_provider_name() . ' streaming request failed: ' . esc_html($result['error']));
        }

        return $this->build_streaming_response($standard_request);
    }

    protected function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        if (isset($request['messages'])) {
            $processed_messages = $this->process_multimodal_messages($request['messages']);
            $request['contents'] = $this->convert_to_contents($processed_messages);
            unset($request['messages']);
        }

        if (isset($request['max_tokens']) && !empty($request['max_tokens'])) {
            $request['generationConfig']['maxOutputTokens'] = max(1, intval($request['max_tokens']));
            unset($request['max_tokens']);
        }

        if (isset($request['temperature']) && !empty($request['temperature'])) {
            $request['generationConfig']['temperature'] = max(0, min(1, floatval($request['temperature'])));
            unset($request['temperature']);
        }

        if (isset($request['tools']) && is_array($request['tools'])) {
            $request['tools'] = $this->normalize_tools($request['tools']);
        }

        if (isset($request['tool_choice']) && !empty($request['tool_choice'])) {
            if ($request['tool_choice'] === 'required') {
                $request['toolConfig'] = ['functionCallingConfig' => ['mode' => 'ANY']];
            }
            unset($request['tool_choice']);
        }

        return $request;
    }
    
    protected function format_response($gemini_response) {
        $content = '';
        $tool_calls = [];

        if (isset($gemini_response['candidates']) && is_array($gemini_response['candidates'])) {
            $candidate = $gemini_response['candidates'][0] ?? [];
            
            if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $content .= $part['text'];
                    }
                    if (isset($part['functionCall'])) {
                        $tool_calls[] = [
                            'name' => $part['functionCall']['name'] ?? '',
                            'parameters' => $part['functionCall']['args'] ?? []
                        ];
                    }
                }
            }
        }

        $usage = [
            'prompt_tokens' => $gemini_response['usageMetadata']['promptTokenCount'] ?? 0,
            'completion_tokens' => $gemini_response['usageMetadata']['candidatesTokenCount'] ?? 0,
            'total_tokens' => $gemini_response['usageMetadata']['totalTokenCount'] ?? 0
        ];

        return [
            'success' => true,
            'data' => [
                'content' => $content,
                'usage' => $usage,
                'model' => $gemini_response['modelVersion'] ?? '',
                'finish_reason' => $gemini_response['candidates'][0]['finishReason'] ?? 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null
            ],
            'error' => null,
            'provider' => 'gemini',
            'raw_response' => $gemini_response
        ];
    }

    protected function normalize_models_response($raw_models) {
        $models = [];
        
        $data = $raw_models['models'] ?? $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['name'])) {
                    $model_id = str_replace('models/', '', $model['name']);
                    $display_name = $model['displayName'] ?? $model_id;
                    $models[$model_id] = $display_name;
                }
            }
        }
        
        return $models;
    }

    public function upload_file($file_path, $purpose = 'user_data') {
        $this->validate_configured();
        $this->validate_file_exists($file_path);

        $url = 'https://generativelanguage.googleapis.com/upload/v1beta/files?uploadType=multipart&key=' . $this->api_key;
        
        $boundary = wp_generate_uuid4();
        $headers = ['Content-Type' => 'multipart/form-data; boundary=' . $boundary];

        $metadata = json_encode([
            'file' => ['display_name' => basename($file_path)]
        ]);
        
        $body = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"metadata\"\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= $metadata . "\r\n";
        
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="data"; filename="' . basename($file_path) . "\"\r\n";
        $body .= "Content-Type: " . mime_content_type($file_path) . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $result = apply_filters('chubes_ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => $body
        ], $this->get_provider_name() . ' File Upload');

        if (!$result['success']) {
            throw new Exception($this->get_provider_name() . ' file upload failed: ' . esc_html($result['error']));
        }

        $data = json_decode($result['data'], true);
        if (!isset($data['file']['uri'])) {
            throw new Exception('Gemini file upload response missing file URI');
        }

        return $data['file']['uri'];
    }

    public function delete_file($file_uri) {
        $this->validate_configured();

        $file_name = basename(parse_url($file_uri, PHP_URL_PATH));
        $url = "https://generativelanguage.googleapis.com/v1beta/files/{$file_name}?key=" . $this->api_key;
        
        $result = apply_filters('chubes_ai_http', [], 'DELETE', $url, [], $this->get_provider_name() . ' File Delete');

        if (!$result['success']) {
            throw new Exception($this->get_provider_name() . ' file delete failed: ' . esc_html($result['error']));
        }

        return $result['status_code'] === 200;
    }

    private function convert_to_contents($messages) {
        $contents = [];

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                continue;
            }

            $role = $message['role'] === 'assistant' ? 'model' : 'user';

            if ($message['role'] === 'system') {
                continue;
            }

            $parts = [];

            if (is_array($message['content'])) {
                foreach ($message['content'] as $content_item) {
                    if (isset($content_item['type'])) {
                        switch ($content_item['type']) {
                            case 'text':
                                if (!empty($content_item['content'])) {
                                    $parts[] = ['text' => $content_item['content']];
                                }
                                break;
                            case 'file':
                                if (!empty($content_item['file_uri'])) {
                                    $parts[] = ['file_data' => ['file_uri' => $content_item['file_uri']]];
                                }
                                break;
                        }
                    }
                }
            } else {
                $parts[] = ['text' => $message['content']];
            }

            if (!empty($parts)) {
                $contents[] = ['role' => $role, 'parts' => $parts];
            }
        }

        return $contents;
    }

    private function normalize_tools($standard_tools) {
        $gemini_tools = [];
        
        foreach ($standard_tools as $tool) {
            if (isset($tool['name'], $tool['description'])) {
                $gemini_function = [
                    'name' => $tool['name'],
                    'description' => $tool['description']
                ];
                
                if (isset($tool['parameters']) && is_array($tool['parameters'])) {
                    $gemini_function['parameters'] = $this->convert_parameters_to_json_schema($tool['parameters']);
                }
                
                $gemini_tools[] = ['functionDeclarations' => [$gemini_function]];
            }
        }
        
        return $gemini_tools;
    }

    private function process_multimodal_messages($messages) {
        $processed_messages = [];

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                $processed_messages[] = $this->sanitize_message_fields($message);
                continue;
            }

            $sanitized = $this->sanitize_message_fields($message);
            $processed_message = ['role' => $sanitized['role']];

            if (is_array($message['content'])) {
                $processed_message['content'] = $this->build_multimodal_content($message['content']);
            } else {
                $processed_message['content'] = $sanitized['content'];
            }

            if (isset($sanitized['name'])) {
                $processed_message['name'] = $sanitized['name'];
            }
            if (isset($sanitized['tool_call_id'])) {
                $processed_message['tool_call_id'] = $sanitized['tool_call_id'];
            }

            $processed_messages[] = $processed_message;
        }

        return $processed_messages;
    }

    private function build_multimodal_content($content_items) {
        $mixed_content = [];

        foreach ($content_items as $content_item) {
            if (isset($content_item['type'])) {
                switch ($content_item['type']) {
                    case 'text':
                        $mixed_content[] = [
                            'type' => 'text',
                            'content' => $content_item['text'] ?? ''
                        ];
                        break;

                    case 'file':
                        try {
                            $file_path = $content_item['file_path'] ?? '';
                            $mime_type = $content_item['mime_type'] ?? '';

                            if (empty($file_path) || !file_exists($file_path)) {
                                continue 2;
                            }

                            if (empty($mime_type)) {
                                $mime_type = mime_content_type($file_path);
                            }

                            if (!$this->is_supported_file_type($mime_type)) {
                                continue 2;
                            }

                            $file_uri = $this->upload_file_via_callback($file_path);

                            $mixed_content[] = [
                                'type' => 'file',
                                'file_uri' => $file_uri
                            ];

                        } catch (Exception $e) {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('Gemini file upload failed: ' . $e->getMessage());
                            }
                        }
                        break;

                    default:
                        $mixed_content[] = $content_item;
                        break;
                }
            }
        }

        return $mixed_content;
    }

    private function is_supported_file_type($mime_type) {
        $supported_types = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'audio/wav',
            'audio/mp3',
            'audio/mpeg',
            'audio/aiff',
            'audio/aac',
            'audio/ogg',
            'audio/flac',
            'video/mp4',
            'video/mpeg',
            'video/mov',
            'video/avi',
            'video/x-flv',
            'video/mpg',
            'video/webm',
            'video/wmv',
            'video/3gpp',
            'application/pdf',
            'text/plain',
        ];

        return in_array($mime_type, $supported_types, true);
    }
}
