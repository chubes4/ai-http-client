<?php
/**
 * AI HTTP Client - OpenAI Response Normalizer
 * 
 * Single Responsibility: Handle ONLY OpenAI response normalization
 * Follows SRP by focusing on one provider only
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Openai_Response_Normalizer {

    private $provider;

    public function __construct($provider = null) {
        $this->provider = $provider;
    }

    /**
     * Normalize OpenAI response to standard format
     * Handles both Chat Completions API and Responses API formats
     *
     * @param array $openai_response Raw OpenAI API response
     * @return array Standardized response
     */
    public function normalize($openai_response) {
        // Handle OpenAI Responses API format (used by Wordsurf)
        if (isset($openai_response['response'])) {
            return $this->normalize_responses_api($openai_response);
        }
        
        // Handle standard Chat Completions API format
        if (isset($openai_response['choices'])) {
            return $this->normalize_chat_completions_api($openai_response);
        }
        
        throw new Exception('Invalid OpenAI response: unrecognized format');
    }

    /**
     * Normalize OpenAI Responses API format
     * Based on Wordsurf's implementation
     *
     * @param array $response Raw Responses API response
     * @return array Standardized response
     */
    private function normalize_responses_api($response) {
        $response_data = $response['response'];
        
        // Extract response ID for continuation
        $response_id = isset($response_data['id']) ? $response_data['id'] : null;
        if ($response_id && $this->provider) {
            $this->provider->set_last_response_id($response_id);
        }
        
        // Extract content from output
        $content = '';
        $tool_calls = array();
        
        if (isset($response_data['output']) && is_array($response_data['output'])) {
            foreach ($response_data['output'] as $output_item) {
                if (isset($output_item['type'])) {
                    switch ($output_item['type']) {
                        case 'content':
                            $content .= isset($output_item['text']) ? $output_item['text'] : '';
                            break;
                        case 'function_call':
                            if (isset($output_item['status']) && $output_item['status'] === 'completed') {
                                $tool_calls[] = array(
                                    'id' => $output_item['id'] ?? uniqid('tool_'),
                                    'type' => 'function',
                                    'function' => array(
                                        'name' => $output_item['function_call']['name'],
                                        'arguments' => wp_json_encode($output_item['function_call']['arguments'] ?? array())
                                    )
                                );
                            }
                            break;
                    }
                }
            }
        }

        // Extract usage information
        $usage = array(
            'prompt_tokens' => isset($response_data['usage']['prompt_tokens']) ? $response_data['usage']['prompt_tokens'] : 0,
            'completion_tokens' => isset($response_data['usage']['completion_tokens']) ? $response_data['usage']['completion_tokens'] : 0,
            'total_tokens' => isset($response_data['usage']['total_tokens']) ? $response_data['usage']['total_tokens'] : 0
        );

        // Extract model
        $model = isset($response_data['model']) ? $response_data['model'] : '';

        return array(
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => $model,
                'finish_reason' => isset($response_data['status']) ? $response_data['status'] : 'unknown',
                'tool_calls' => !empty($tool_calls) ? $tool_calls : null,
                'response_id' => $response_id
            ),
            'error' => null,
            'raw_response' => $response
        );
    }

    /**
     * Normalize standard Chat Completions API format
     *
     * @param array $response Raw Chat Completions API response
     * @return array Standardized response
     */
    private function normalize_chat_completions_api($response) {
        if (empty($response['choices'])) {
            throw new Exception('Invalid OpenAI response: missing choices');
        }

        $choice = $response['choices'][0];
        $message = $choice['message'];

        // Extract content
        $content = isset($message['content']) ? $message['content'] : '';
        
        // Extract tool calls if present
        $tool_calls = isset($message['tool_calls']) ? $message['tool_calls'] : null;

        // Extract usage information
        $usage = array(
            'prompt_tokens' => isset($response['usage']['prompt_tokens']) ? $response['usage']['prompt_tokens'] : 0,
            'completion_tokens' => isset($response['usage']['completion_tokens']) ? $response['usage']['completion_tokens'] : 0,
            'total_tokens' => isset($response['usage']['total_tokens']) ? $response['usage']['total_tokens'] : 0
        );

        // Extract model and finish reason
        $model = isset($response['model']) ? $response['model'] : '';
        $finish_reason = isset($choice['finish_reason']) ? $choice['finish_reason'] : 'unknown';

        return array(
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => $model,
                'finish_reason' => $finish_reason,
                'tool_calls' => $tool_calls
            ),
            'error' => null,
            'raw_response' => $response
        );
    }
}