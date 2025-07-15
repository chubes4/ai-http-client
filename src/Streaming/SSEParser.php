<?php
/**
 * AI HTTP Client - SSE Parser
 * 
 * Single Responsibility: Parse Server-Sent Events from AI providers
 * Based on Wordsurf's proven SSE parsing implementation
 *
 * @package AIHttpClient\Streaming
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_SSE_Parser {

    /**
     * Parse raw SSE response and call callback for each event
     * Based on Wordsurf's efficient SSE parsing approach
     *
     * @param string $raw_response The raw SSE response
     * @param callable $event_callback Callback function for each parsed event
     * @param string $provider Provider name for parsing format
     */
    public static function parse_and_stream($raw_response, $event_callback, $provider = 'openai') {
        if (!is_callable($event_callback)) {
            error_log('AI HTTP Client: Event callback must be callable');
            return;
        }
        
        // Use Wordsurf's efficient approach: split by double newlines to get event blocks
        $event_blocks = explode("\n\n", trim($raw_response));
        
        foreach ($event_blocks as $block_number => $block) {
            if (empty(trim($block))) {
                continue;
            }
            
            $current_event = '';
            $current_data = '';
            $lines = explode("\n", $block);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                
                // Parse SSE format: "event: type" or "data: json"
                if (preg_match('/^event: (.+)$/', $line, $matches)) {
                    $current_event = trim($matches[1]);
                } elseif (preg_match('/^data: (.+)$/', $line, $matches)) {
                    // Handle multi-line data blocks by appending
                    $data_chunk = trim($matches[1]);
                    $current_data .= $data_chunk;
                }
            }
            
            // Process the event if we have complete data
            if (!empty($current_event) && !empty($current_data)) {
                self::process_event($current_event, $current_data, $event_callback, $provider);
            } elseif (!empty($current_data) && strpos($current_data, 'data: ') === 0) {
                // Handle simple data-only events (like OpenAI streaming)
                self::process_openai_data_event($current_data, $event_callback);
            }
        }
    }

    /**
     * Process a single SSE event
     *
     * @param string $event_type The event type
     * @param string $event_data The event data
     * @param callable $event_callback Callback for processed event
     * @param string $provider Provider name
     */
    private static function process_event($event_type, $event_data, $event_callback, $provider) {
        // Clean the data to remove control characters (from Wordsurf)
        $cleaned_data = preg_replace('/[\x00-\x1F\x7F]/', '', $event_data);
        
        // Ensure the data is properly formatted JSON
        $decoded = json_decode($cleaned_data, true);
        if ($decoded !== null) {
            $normalized_event = [
                'type' => $event_type,
                'data' => $decoded,
                'provider' => $provider,
                'raw' => $event_data
            ];
            
            call_user_func($event_callback, $normalized_event);
        } else {
            error_log("AI HTTP Client: Failed to decode JSON for event '{$event_type}': " . substr($cleaned_data, 0, 200));
        }
    }

    /**
     * Process OpenAI-style data-only events (data: {json})
     *
     * @param string $data_line The data line
     * @param callable $event_callback Callback for processed event
     */
    private static function process_openai_data_event($data_line, $event_callback) {
        // Handle OpenAI streaming format: "data: {json}" or "data: [DONE]"
        if (preg_match('/^data: (.+)$/', $data_line, $matches)) {
            $data_content = trim($matches[1]);
            
            if ($data_content === '[DONE]') {
                call_user_func($event_callback, [
                    'type' => 'done',
                    'data' => null,
                    'provider' => 'openai',
                    'raw' => $data_line
                ]);
                return;
            }
            
            $decoded = json_decode($data_content, true);
            if ($decoded !== null) {
                call_user_func($event_callback, [
                    'type' => 'data',
                    'data' => $decoded,
                    'provider' => 'openai',
                    'raw' => $data_line
                ]);
            }
        }
    }

    /**
     * Parse and normalize streaming chunk for different providers
     *
     * @param array $event_data Parsed event data
     * @param string $provider Provider name
     * @return array Normalized chunk data
     */
    public static function normalize_chunk($event_data, $provider) {
        $normalized = [
            'type' => 'content',
            'content' => '',
            'finish_reason' => null,
            'usage' => null,
            'tool_calls' => null,
            'provider' => $provider,
            'raw' => $event_data
        ];

        switch ($provider) {
            case 'openai':
                return self::normalize_openai_chunk($event_data, $normalized);
            case 'anthropic':
                return self::normalize_anthropic_chunk($event_data, $normalized);
            default:
                return $normalized;
        }
    }

    /**
     * Normalize OpenAI streaming chunk
     */
    private static function normalize_openai_chunk($chunk, $normalized) {
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
     * Normalize Anthropic streaming chunk
     */
    private static function normalize_anthropic_chunk($chunk, $normalized) {
        if (!isset($chunk['type'])) {
            return $normalized;
        }

        switch ($chunk['type']) {
            case 'content_block_delta':
                if (isset($chunk['delta']['text'])) {
                    $normalized['content'] = $chunk['delta']['text'];
                }
                break;

            case 'message_delta':
                if (isset($chunk['delta']['stop_reason'])) {
                    $normalized['finish_reason'] = $chunk['delta']['stop_reason'];
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
}