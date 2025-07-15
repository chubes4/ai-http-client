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

    /**
     * Normalize OpenAI response to standard format
     *
     * @param array $openai_response Raw OpenAI API response
     * @return array Standardized response
     */
    public function normalize($openai_response) {
        if (!isset($openai_response['choices']) || empty($openai_response['choices'])) {
            throw new Exception('Invalid OpenAI response: missing choices');
        }

        $choice = $openai_response['choices'][0];
        $message = $choice['message'];

        // Extract content
        $content = isset($message['content']) ? $message['content'] : '';
        
        // Extract tool calls if present
        $tool_calls = isset($message['tool_calls']) ? $message['tool_calls'] : null;

        // Extract usage information
        $usage = array(
            'prompt_tokens' => isset($openai_response['usage']['prompt_tokens']) ? $openai_response['usage']['prompt_tokens'] : 0,
            'completion_tokens' => isset($openai_response['usage']['completion_tokens']) ? $openai_response['usage']['completion_tokens'] : 0,
            'total_tokens' => isset($openai_response['usage']['total_tokens']) ? $openai_response['usage']['total_tokens'] : 0
        );

        // Extract model and finish reason
        $model = isset($openai_response['model']) ? $openai_response['model'] : '';
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
            'raw_response' => $openai_response
        );
    }
}