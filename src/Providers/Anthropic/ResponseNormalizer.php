<?php
/**
 * AI HTTP Client - Anthropic Response Normalizer
 * 
 * Single Responsibility: Handle ONLY Anthropic response normalization
 * Converts Anthropic's response format to standardized format
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Anthropic_Response_Normalizer {

    /**
     * Normalize Anthropic response to standard format
     *
     * @param array $anthropic_response Raw Anthropic API response
     * @return array Standardized response
     */
    public function normalize($anthropic_response) {
        if (!isset($anthropic_response['content']) || empty($anthropic_response['content'])) {
            throw new Exception('Invalid Anthropic response: missing content');
        }

        // Anthropic returns content as an array of content blocks
        $content_text = '';
        $tool_calls = null;

        foreach ($anthropic_response['content'] as $content_block) {
            if (isset($content_block['type'])) {
                switch ($content_block['type']) {
                    case 'text':
                        $content_text .= $content_block['text'];
                        break;
                    case 'tool_use':
                        // Convert Anthropic tool use to OpenAI format for consistency
                        if (!$tool_calls) {
                            $tool_calls = array();
                        }
                        $tool_calls[] = array(
                            'id' => $content_block['id'],
                            'type' => 'function',
                            'function' => array(
                                'name' => $content_block['name'],
                                'arguments' => json_encode($content_block['input'])
                            )
                        );
                        break;
                }
            }
        }

        // Extract usage information
        $usage = array(
            'prompt_tokens' => isset($anthropic_response['usage']['input_tokens']) ? $anthropic_response['usage']['input_tokens'] : 0,
            'completion_tokens' => isset($anthropic_response['usage']['output_tokens']) ? $anthropic_response['usage']['output_tokens'] : 0
        );
        $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];

        // Extract model and finish reason
        $model = isset($anthropic_response['model']) ? $anthropic_response['model'] : '';
        $finish_reason = isset($anthropic_response['stop_reason']) ? $this->map_stop_reason($anthropic_response['stop_reason']) : 'unknown';

        return array(
            'data' => array(
                'content' => $content_text,
                'usage' => $usage,
                'model' => $model,
                'finish_reason' => $finish_reason,
                'tool_calls' => $tool_calls
            ),
            'error' => null,
            'raw_response' => $anthropic_response
        );
    }

    /**
     * Map Anthropic stop reasons to OpenAI-compatible format
     *
     * @param string $anthropic_stop_reason Anthropic stop reason
     * @return string Standardized stop reason
     */
    private function map_stop_reason($anthropic_stop_reason) {
        $mapping = array(
            'end_turn' => 'stop',
            'max_tokens' => 'length',
            'stop_sequence' => 'stop',
            'tool_use' => 'tool_calls'
        );

        return isset($mapping[$anthropic_stop_reason]) ? $mapping[$anthropic_stop_reason] : $anthropic_stop_reason;
    }
}