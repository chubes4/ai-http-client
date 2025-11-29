<?php
/**
 * AI HTTP Client - Anthropic Provider
 * 
 * Anthropic Claude API implementation extending BaseProvider.
 * Handles system message extraction and Files API integration.
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

add_filter('chubes_ai_providers', function($providers) {
    $providers['anthropic'] = [
        'class' => 'AI_HTTP_Anthropic_Provider', 
        'type' => 'llm',
        'name' => 'Anthropic'
    ];
    return $providers;
});

class AI_HTTP_Anthropic_Provider extends AI_HTTP_BaseProvider {

    protected function get_default_base_url() {
        return 'https://api.anthropic.com/v1';
    }

    protected function get_auth_headers() {
        return [
            'x-api-key' => $this->api_key,
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'files-api-2025-04-14'
        ];
    }

    protected function get_provider_name() {
        return 'Anthropic';
    }

    protected function get_chat_endpoint() {
        return '/messages';
    }

    protected function get_models_endpoint() {
        return '/models';
    }

    protected function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        if (isset($request['temperature']) && !empty($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }

        if (isset($request['max_tokens']) && !empty($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        if (isset($request['tools']) && is_array($request['tools'])) {
            $request['tools'] = $this->normalize_tools($request['tools']);
        }

        if (isset($request['tool_choice']) && !empty($request['tool_choice'])) {
            if ($request['tool_choice'] === 'required') {
                $request['tool_choice'] = 'any';
            }
        }

        if (isset($request['messages'])) {
            $request['messages'] = $this->process_multimodal_messages($request['messages']);
            $request = $this->extract_system_message($request);
        }

        return $request;
    }
    
    protected function format_response($anthropic_response) {
        $content = '';
        $tool_calls = [];

        if (isset($anthropic_response['content']) && is_array($anthropic_response['content'])) {
            foreach ($anthropic_response['content'] as $content_block) {
                if (isset($content_block['type'])) {
                    switch ($content_block['type']) {
                        case 'text':
                            $content .= $content_block['text'] ?? '';
                            break;
                        case 'tool_use':
                            $tool_calls[] = [
                                'name' => $content_block['name'] ?? '',
                                'parameters' => $content_block['input'] ?? []
                            ];
                            break;
                    }
                }
            }
        }

        $usage = [
            'prompt_tokens' => $anthropic_response['usage']['input_tokens'] ?? 0,
            'completion_tokens' => $anthropic_response['usage']['output_tokens'] ?? 0,
            'total_tokens' => 0
        ];
        $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];

        return [
            'success' => true,
            'data' => [
                'content' => $content,
                'usage' => $usage,
                'model' => $anthropic_response['model'] ?? '',
                'finish_reason' => $anthropic_response['stop_reason'] ?? 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null
            ],
            'error' => null,
            'provider' => 'anthropic',
            'raw_response' => $anthropic_response
        ];
    }

    protected function normalize_models_response($raw_models) {
        $models = [];
        
        $data = $raw_models['data'] ?? $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['id'])) {
                    $display_name = $model['display_name'] ?? $model['id'];
                    $models[$model['id']] = $display_name;
                }
            }
        }
        
        return $models;
    }

    public function upload_file($file_path, $purpose = 'user_data') {
        $this->validate_configured();
        $this->validate_file_exists($file_path);

        $url = $this->build_file_upload_url();
        
        $boundary = wp_generate_uuid4();
        $headers = array_merge($this->get_auth_headers(), [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        ]);

        $body = $this->build_multipart_body($file_path, $purpose, $boundary);

        $result = apply_filters('chubes_ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => $body
        ], $this->get_provider_name() . ' File Upload');

        if (!$result['success']) {
            $error_message = 'Anthropic file upload failed: ' . esc_html($result['error'] ?? 'Unknown error');

            if (isset($result['status_code'])) {
                switch ($result['status_code']) {
                    case 400:
                        if (strpos($result['error'] ?? '', 'Invalid file type') !== false) {
                            $error_message = 'Unsupported file type for Anthropic Files API';
                        } elseif (strpos($result['error'] ?? '', 'File too large') !== false) {
                            $error_message = 'File exceeds Anthropic\'s 500MB limit';
                        }
                        break;
                    case 403:
                        $error_message = 'Anthropic storage limit exceeded (100GB per organization)';
                        break;
                    case 413:
                        $error_message = 'File too large - maximum size is 500MB';
                        break;
                }
            }

            throw new Exception($error_message);
        }

        return $this->extract_file_id($result['data']);
    }

    private function extract_system_message($request) {
        $messages = $request['messages'];
        $system_content = '';
        $filtered_messages = [];

        foreach ($messages as $message) {
            if (isset($message['role']) && $message['role'] === 'system') {
                $system_content .= $message['content'] . "\n";
            } else {
                $filtered_messages[] = $message;
            }
        }

        $request['messages'] = $filtered_messages;
        
        if (!empty(trim($system_content))) {
            $request['system'] = trim($system_content);
        }

        return $request;
    }

    private function normalize_tools($standard_tools) {
        $anthropic_tools = [];
        
        foreach ($standard_tools as $tool) {
            if (isset($tool['name'], $tool['description'])) {
                $anthropic_tool = [
                    'name' => $tool['name'],
                    'description' => $tool['description']
                ];
                
                if (isset($tool['parameters']) && is_array($tool['parameters'])) {
                    $anthropic_tool['input_schema'] = $this->convert_parameters_to_json_schema($tool['parameters']);
                }
                
                $anthropic_tools[] = $anthropic_tool;
            }
        }
        
        return $anthropic_tools;
    }

    private function process_multimodal_messages($messages) {
        $processed_messages = [];

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                $processed_messages[] = $message;
                continue;
            }

            $processed_message = ['role' => $message['role']];

            if (is_array($message['content'])) {
                $processed_message['content'] = $this->build_multimodal_content($message['content']);
            } else {
                $processed_message['content'] = $message['content'];
            }

            foreach ($message as $key => $value) {
                if (!in_array($key, ['role', 'content'])) {
                    $processed_message[$key] = $value;
                }
            }

            $processed_messages[] = $processed_message;
        }

        return $processed_messages;
    }

    private function build_multimodal_content($content_items) {
        $content = [];

        foreach ($content_items as $content_item) {
            if (isset($content_item['type'])) {
                switch ($content_item['type']) {
                    case 'text':
                        $content[] = [
                            'type' => 'text',
                            'text' => $content_item['text'] ?? ''
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

                            $file_id = $this->upload_file_via_callback($file_path);

                            if (strpos($mime_type, 'image/') === 0) {
                                $content[] = [
                                    'type' => 'image',
                                    'source' => [
                                        'type' => 'file',
                                        'file_id' => $file_id
                                    ]
                                ];
                            } else {
                                $content[] = [
                                    'type' => 'document',
                                    'source' => [
                                        'type' => 'file',
                                        'file_id' => $file_id
                                    ]
                                ];
                            }
                        } catch (Exception $e) {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('Anthropic file upload failed: ' . $e->getMessage());
                            }
                        }
                        break;

                    default:
                        $content[] = $content_item;
                        break;
                }
            }
        }

        return $content;
    }

    private function is_supported_file_type($mime_type) {
        $supported_types = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
        ];

        return in_array($mime_type, $supported_types, true);
    }
}
