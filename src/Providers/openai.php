<?php
/**
 * AI HTTP Client - OpenAI Provider
 * 
 * OpenAI Responses API implementation extending BaseProvider.
 * Supports vision, file uploads, and function calling.
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

add_filter('chubes_ai_providers', function($providers) {
    $providers['openai'] = [
        'class' => 'AI_HTTP_OpenAI_Provider',
        'type' => 'llm',
        'name' => 'OpenAI'
    ];
    return $providers;
});

class AI_HTTP_OpenAI_Provider extends AI_HTTP_BaseProvider {

    private $organization;

    protected function get_default_base_url() {
        return 'https://api.openai.com/v1';
    }

    protected function configure($config) {
        $this->organization = $config['organization'] ?? '';
    }

    protected function get_auth_headers() {
        $headers = ['Authorization' => 'Bearer ' . $this->api_key];

        if (!empty($this->organization)) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        return $headers;
    }

    protected function get_provider_name() {
        return 'OpenAI';
    }

    protected function get_chat_endpoint() {
        return '/responses';
    }

    protected function get_models_endpoint() {
        return '/models';
    }

    protected function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        if (isset($request['messages'])) {
            $request['input'] = $this->normalize_messages($request['messages']);
            unset($request['messages']);
        }

        if (isset($request['max_tokens']) && !empty($request['max_tokens'])) {
            $request['max_output_tokens'] = intval($request['max_tokens']);
            unset($request['max_tokens']);
        }

        if (isset($request['tools'])) {
            $request['tools'] = $this->normalize_tools($request['tools']);
        }

        if (isset($request['temperature']) && !empty($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }

        return $request;
    }
    
    protected function format_response($openai_response) {
        if (isset($openai_response['object']) && $openai_response['object'] === 'response') {
            return $this->normalize_responses_api($openai_response);
        }

        if (isset($openai_response['content']) && !isset($openai_response['choices'])) {
            return $this->normalize_streaming($openai_response);
        }
        
        throw new Exception('Invalid OpenAI response format');
    }

    protected function normalize_models_response($raw_models) {
        $models = [];
        
        $data = $raw_models['data'] ?? $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['id'])) {
                    $models[$model['id']] = $model['id'];
                }
            }
        }
        
        return $models;
    }

    private function normalize_messages($messages) {
        $normalized = [];

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                $normalized[] = $message;
                continue;
            }

            $normalized_message = ['role' => $message['role']];

            if (isset($message['images']) || isset($message['image_urls']) || isset($message['files']) || is_array($message['content'])) {
                $normalized_message['content'] = $this->build_multimodal_content($message);
            } else {
                $normalized_message['content'] = $message['content'];
            }

            foreach ($message as $key => $value) {
                if (!in_array($key, ['role', 'content', 'images', 'image_urls', 'files', 'tool_calls'])) {
                    $normalized_message[$key] = $value;
                }
            }

            $normalized[] = $normalized_message;
        }

        return $normalized;
    }
    
    private function build_multimodal_content($message) {
        $content = [];

        if (is_array($message['content'])) {
            foreach ($message['content'] as $content_item) {
                if (isset($content_item['type'])) {
                    switch ($content_item['type']) {
                        case 'text':
                            $content[] = [
                                'type' => 'input_text',
                                'text' => $content_item['text']
                            ];
                            break;
                        case 'file':
                            try {
                                $file_path = $content_item['file_path'];
                                $file_id = $this->upload_file_via_callback($file_path);
                                
                                $mime_type = $content_item['mime_type'] ?? mime_content_type($file_path);
                                
                                if (strpos($mime_type, 'image/') === 0) {
                                    $content[] = [
                                        'type' => 'input_image',
                                        'file_id' => $file_id
                                    ];
                                } else {
                                    $content[] = [
                                        'type' => 'input_file',
                                        'file_id' => $file_id
                                    ];
                                }
                            } catch (Exception $e) {
                                AIHttpError::trigger_error('OpenAI', 'File upload failed: ' . $e->getMessage(), [
                                    'file_path' => $file_path ?? 'unknown',
                                    'provider' => 'openai'
                                ]);
                            }
                            break;
                        default:
                            $content[] = $content_item;
                            break;
                    }
                }
            }
        } else {
            if (!empty($message['content'])) {
                $content[] = [
                    'type' => 'input_text',
                    'text' => $message['content']
                ];
            }
        }

        return $content;
    }
    
    private function normalize_tools($tools) {
        $normalized = [];

        foreach ($tools as $tool) {
            if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
                $normalized[] = [
                    'name' => sanitize_text_field($tool['function']['name']),
                    'type' => 'function',
                    'description' => sanitize_textarea_field($tool['function']['description']),
                    'parameters' => $this->convert_parameters_to_json_schema($tool['function']['parameters'] ?? [])
                ];
            } elseif (isset($tool['name']) && isset($tool['description'])) {
                $normalized[] = [
                    'name' => sanitize_text_field($tool['name']),
                    'type' => 'function',
                    'description' => sanitize_textarea_field($tool['description']),
                    'parameters' => $this->convert_parameters_to_json_schema($tool['parameters'] ?? [])
                ];
            }
        }

        return $normalized;
    }
    
    private function normalize_responses_api($response) {
        $content = '';
        $tool_calls = [];
        
        if (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $output_item) {
                if (isset($output_item['type']) && $output_item['type'] === 'message') {
                    if (isset($output_item['content']) && is_array($output_item['content'])) {
                        foreach ($output_item['content'] as $content_item) {
                            if (isset($content_item['type'])) {
                                switch ($content_item['type']) {
                                    case 'output_text':
                                        $content .= $content_item['text'] ?? '';
                                        break;
                                    case 'tool_call':
                                        $function_name = $content_item['name'] ?? '';
                                        $function_arguments = $content_item['arguments'] ?? [];
                                        
                                        if (!empty($function_name)) {
                                            $tool_calls[] = [
                                                'name' => $function_name,
                                                'parameters' => $function_arguments
                                            ];
                                        }
                                        break;
                                }
                            }
                        }
                    }
                } elseif (isset($output_item['type']) && $output_item['type'] === 'function_call') {
                    $function_name = $output_item['name'] ?? '';
                    $function_arguments_json = $output_item['arguments'] ?? '{}';
                    
                    $function_arguments = json_decode($function_arguments_json, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $function_arguments = [];
                    }
                    
                    if (!empty($function_name)) {
                        $tool_calls[] = [
                            'name' => $function_name,
                            'parameters' => $function_arguments
                        ];
                    }
                } elseif (isset($output_item['type'])) {
                    switch ($output_item['type']) {
                        case 'content':
                        case 'output_text':
                            $content .= $output_item['text'] ?? '';
                            break;
                    }
                }
            }
        }

        $usage = [
            'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
            'total_tokens' => $response['usage']['total_tokens'] ?? 0
        ];

        return [
            'success' => true,
            'data' => [
                'content' => $content,
                'usage' => $usage,
                'model' => $response['model'] ?? '',
                'finish_reason' => $response['status'] ?? 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null
            ],
            'error' => null,
            'provider' => 'openai',
            'raw_response' => $response
        ];
    }
    
    private function normalize_streaming($response) {
        $content = $response['content'] ?? '';
        
        return [
            'success' => true,
            'data' => [
                'content' => $content,
                'usage' => [
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ],
                'model' => $response['model'] ?? '',
                'finish_reason' => 'stop',
                'tool_calls' => null
            ],
            'error' => null,
            'provider' => 'openai',
            'raw_response' => $response
        ];
    }
}
