<?php
/**
 * AI HTTP Client - Grok Provider
 * 
 * Grok/X.AI API implementation extending BaseProvider.
 * Uses OpenAI-compatible format for requests and responses.
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

add_filter('chubes_ai_providers', function($providers) {
    $providers['grok'] = [
        'class' => 'AI_HTTP_Grok_Provider',
        'type' => 'llm',
        'name' => 'Grok'
    ];
    return $providers;
});

class AI_HTTP_Grok_Provider extends AI_HTTP_BaseProvider {

    protected function get_default_base_url() {
        return 'https://api.x.ai/v1';
    }

    protected function get_auth_headers() {
        return ['Authorization' => 'Bearer ' . $this->api_key];
    }

    protected function get_provider_name() {
        return 'Grok';
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
        
        if (isset($request['reasoning_effort'])) {
            $request['reasoning_effort'] = sanitize_text_field($request['reasoning_effort']);
        }

        if (isset($request['temperature']) && !empty($request['temperature'])) {
            $request['temperature'] = max(0, min(1, floatval($request['temperature'])));
        }

        if (isset($request['max_tokens']) && !empty($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        if (isset($request['tools']) && is_array($request['tools'])) {
            $request['tools'] = $this->normalize_tools($request['tools']);
        }

        return $request;
    }
    
    protected function format_response($grok_response) {
        if (empty($grok_response['choices'])) {
            throw new Exception('Invalid Grok response: missing choices');
        }

        $choice = $grok_response['choices'][0];
        $message = $choice['message'];

        $content = $message['content'] ?? '';
        $raw_tool_calls = $message['tool_calls'] ?? [];
        
        $tool_calls = [];
        if (!empty($raw_tool_calls)) {
            foreach ($raw_tool_calls as $tool_call) {
                if (isset($tool_call['function']['name'])) {
                    $arguments = $tool_call['function']['arguments'] ?? '{}';
                    $decoded_args = json_decode($arguments, true);
                    
                    $tool_calls[] = [
                        'name' => $tool_call['function']['name'],
                        'parameters' => $decoded_args ?: []
                    ];
                }
            }
        }

        $usage = [
            'prompt_tokens' => $grok_response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $grok_response['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $grok_response['usage']['total_tokens'] ?? 0
        ];

        return [
            'success' => true,
            'data' => [
                'content' => $content,
                'usage' => $usage,
                'model' => $grok_response['model'] ?? '',
                'finish_reason' => $choice['finish_reason'] ?? 'unknown',
                'tool_calls' => $tool_calls
            ],
            'error' => null,
            'provider' => 'grok',
            'raw_response' => $grok_response
        ];
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

    private function normalize_tools($standard_tools) {
        $grok_tools = [];
        
        foreach ($standard_tools as $tool) {
            if (isset($tool['name'], $tool['description'])) {
                $grok_tool = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description']
                    ]
                ];
                
                if (isset($tool['parameters']) && is_array($tool['parameters'])) {
                    $grok_tool['function']['parameters'] = $this->convert_parameters_to_json_schema($tool['parameters']);
                }
                
                $grok_tools[] = $grok_tool;
            }
        }
        
        return $grok_tools;
    }
}
