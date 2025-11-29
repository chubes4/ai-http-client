<?php
/**
 * AI HTTP Client - OpenRouter Provider
 * 
 * OpenRouter API implementation extending BaseProvider.
 * Gateway to 200+ models with OpenAI-compatible format.
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

add_filter('chubes_ai_providers', function($providers) {
    $providers['openrouter'] = [
        'class' => 'AI_HTTP_OpenRouter_Provider',
        'type' => 'llm',
        'name' => 'OpenRouter'
    ];
    return $providers;
});

class AI_HTTP_OpenRouter_Provider extends AI_HTTP_BaseProvider {

    private $http_referer;
    private $app_title;

    protected function get_default_base_url() {
        return 'https://openrouter.ai/api/v1';
    }

    protected function configure($config) {
        $this->http_referer = $config['http_referer'] ?? '';
        $this->app_title = $config['app_title'] ?? 'AI HTTP Client';
    }

    protected function get_auth_headers() {
        $headers = ['Authorization' => 'Bearer ' . $this->api_key];

        if (!empty($this->http_referer)) {
            $headers['HTTP-Referer'] = $this->http_referer;
        }

        if (!empty($this->app_title)) {
            $headers['X-Title'] = $this->app_title;
        }

        return $headers;
    }

    protected function get_provider_name() {
        return 'OpenRouter';
    }

    protected function get_chat_endpoint() {
        return '/chat/completions';
    }

    protected function get_models_endpoint() {
        return '/models';
    }

    protected function format_request($unified_request) {
        $this->validate_unified_request($unified_request);
        
        $request = $this->sanitize_common_fields($unified_request);
        
        if (isset($request['messages']) && is_array($request['messages'])) {
            foreach ($request['messages'] as &$message) {
                if (isset($message['content']) && is_array($message['content'])) {
                    foreach ($message['content'] as &$content_part) {
                        if (isset($content_part['type']) && $content_part['type'] === 'file' && 
                            isset($content_part['file_path'])) {
                            
                            $file_path = $content_part['file_path'];
                            $base64_data_url = apply_filters('chubes_ai_file_to_base64', '', $file_path);
                            
                            if (!empty($base64_data_url)) {
                                $content_part = [
                                    'type' => 'image_url',
                                    'image_url' => ['url' => $base64_data_url]
                                ];
                            } else {
                                $content_part = [
                                    'type' => 'text',
                                    'text' => '[File could not be processed: ' . basename($file_path) . ']'
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        if (isset($request['temperature']) && !empty($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }

        if (isset($request['max_tokens']) && !empty($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        return $request;
    }
    
    protected function format_response($openrouter_response) {
        if (empty($openrouter_response['choices'])) {
            throw new Exception('Invalid OpenRouter response: missing choices');
        }

        $choice = $openrouter_response['choices'][0];
        $message = $choice['message'];

        $content = $message['content'] ?? '';
        $tool_calls = $message['tool_calls'] ?? null;

        $usage = [
            'prompt_tokens' => $openrouter_response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $openrouter_response['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $openrouter_response['usage']['total_tokens'] ?? 0
        ];

        return [
            'success' => true,
            'data' => [
                'content' => $content,
                'usage' => $usage,
                'model' => $openrouter_response['model'] ?? '',
                'finish_reason' => $choice['finish_reason'] ?? 'unknown',
                'tool_calls' => $tool_calls
            ],
            'error' => null,
            'provider' => 'openrouter',
            'raw_response' => $openrouter_response
        ];
    }

    protected function normalize_models_response($raw_models) {
        $models = [];
        
        $data = $raw_models['data'] ?? $raw_models;
        if (is_array($data)) {
            foreach ($data as $model) {
                if (isset($model['id'])) {
                    $display_name = $model['name'] ?? $model['id'];
                    $models[$model['id']] = $display_name;
                }
            }
        }
        
        return $models;
    }
}
