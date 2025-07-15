<?php
/**
 * AI HTTP Client - Streaming Parser
 * 
 * Single Responsibility: Parse Server-Sent Events (SSE) from AI providers
 * Handles different provider streaming formats and normalizes them
 *
 * @package AIHttpClient
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Streaming_Parser {

    /**
     * Parse OpenAI streaming response
     *
     * @param string $chunk Raw SSE chunk from OpenAI
     * @return array|null Parsed chunk data or null if not a data chunk
     */
    public static function parse_openai_chunk($chunk) {
        // OpenAI sends: "data: {json}\n\n" or "data: [DONE]\n\n"
        if (strpos($chunk, 'data: ') !== 0) {
            return null;
        }

        $data = trim(substr($chunk, 6)); // Remove "data: " prefix
        
        if ($data === '[DONE]') {
            return ['type' => 'done'];
        }

        $parsed = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return self::normalize_openai_chunk($parsed);
    }

    /**
     * Parse Anthropic streaming response
     *
     * @param string $chunk Raw SSE chunk from Anthropic
     * @return array|null Parsed chunk data or null if not a data chunk
     */
    public static function parse_anthropic_chunk($chunk) {
        // Anthropic sends: "data: {json}\n\n" with different format
        if (strpos($chunk, 'data: ') !== 0) {
            return null;
        }

        $data = trim(substr($chunk, 6)); // Remove "data: " prefix
        
        $parsed = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return self::normalize_anthropic_chunk($parsed);
    }

    /**
     * Normalize OpenAI streaming chunk to standard format
     *
     * @param array $chunk OpenAI chunk data
     * @return array Normalized chunk
     */
    private static function normalize_openai_chunk($chunk) {
        $normalized = [
            'type' => 'content',
            'content' => '',
            'finish_reason' => null,
            'usage' => null,
            'tool_calls' => null,
            'raw' => $chunk
        ];

        if (!isset($chunk['choices'][0])) {
            return $normalized;
        }

        $choice = $chunk['choices'][0];
        $delta = isset($choice['delta']) ? $choice['delta'] : array();

        // Content delta
        if (isset($delta['content'])) {
            $normalized['content'] = $delta['content'];
        }

        // Finish reason
        if (isset($choice['finish_reason'])) {
            $normalized['finish_reason'] = $choice['finish_reason'];
            $normalized['type'] = 'finish';
        }

        // Tool calls
        if (isset($delta['tool_calls'])) {
            $normalized['tool_calls'] = $delta['tool_calls'];
            $normalized['type'] = 'tool_calls';
        }

        // Usage (usually only in final chunk)
        if (isset($chunk['usage'])) {
            $normalized['usage'] = $chunk['usage'];
        }

        return $normalized;
    }

    /**
     * Normalize Anthropic streaming chunk to standard format
     *
     * @param array $chunk Anthropic chunk data
     * @return array Normalized chunk
     */
    private static function normalize_anthropic_chunk($chunk) {
        $normalized = [
            'type' => 'content',
            'content' => '',
            'finish_reason' => null,
            'usage' => null,
            'tool_calls' => null,
            'raw' => $chunk
        ];

        // Anthropic has different event types
        if (!isset($chunk['type'])) {
            return $normalized;
        }

        switch ($chunk['type']) {
            case 'content_block_start':
                if (isset($chunk['content_block']['type']) && $chunk['content_block']['type'] === 'text') {
                    $normalized['type'] = 'start';
                }
                break;

            case 'content_block_delta':
                if (isset($chunk['delta']['text'])) {
                    $normalized['content'] = $chunk['delta']['text'];
                }
                break;

            case 'content_block_stop':
                $normalized['type'] = 'content_end';
                break;

            case 'message_delta':
                if (isset($chunk['delta']['stop_reason'])) {
                    $normalized['finish_reason'] = self::map_anthropic_stop_reason($chunk['delta']['stop_reason']);
                    $normalized['type'] = 'finish';
                }
                if (isset($chunk['usage'])) {
                    $normalized['usage'] = [
                        'prompt_tokens' => $chunk['usage']['input_tokens'] ?? 0,
                        'completion_tokens' => $chunk['usage']['output_tokens'] ?? 0,
                        'total_tokens' => ($chunk['usage']['input_tokens'] ?? 0) + ($chunk['usage']['output_tokens'] ?? 0)
                    ];
                }
                break;

            case 'message_stop':
                $normalized['type'] = 'done';
                break;
        }

        return $normalized;
    }

    /**
     * Map Anthropic stop reasons to OpenAI-compatible format
     *
     * @param string $anthropic_stop_reason Anthropic stop reason
     * @return string Standardized stop reason
     */
    private static function map_anthropic_stop_reason($anthropic_stop_reason) {
        $mapping = [
            'end_turn' => 'stop',
            'max_tokens' => 'length',
            'stop_sequence' => 'stop',
            'tool_use' => 'tool_calls'
        ];

        return isset($mapping[$anthropic_stop_reason]) ? $mapping[$anthropic_stop_reason] : $anthropic_stop_reason;
    }

    /**
     * Split SSE stream into individual chunks
     *
     * @param string $stream Raw SSE stream data
     * @return array Array of individual SSE chunks
     */
    public static function split_sse_stream($stream) {
        // SSE chunks are separated by double newlines
        $chunks = explode("\n\n", $stream);
        
        // Filter out empty chunks
        return array_filter($chunks, function($chunk) {
            return !empty(trim($chunk));
        });
    }
}