<?php
/**
 * AI HTTP Client - Server-Sent Events Parser
 * 
 * Single Responsibility: Parse and convert SSE streams to standardized format
 * Universal module for all providers to handle SSE event parsing and conversion
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_SSE_Parser {

    /**
     * Parse raw SSE response into structured events
     *
     * @param string $raw_response Raw SSE response data
     * @return array Array of parsed events with type and data
     */
    public static function parse_events($raw_response) {
        $events = array();
        $event_blocks = explode("\n\n", trim($raw_response));
        
        foreach ($event_blocks as $block) {
            if (empty(trim($block))) {
                continue;
            }
            
            $parsed_event = self::parse_single_event($block);
            if ($parsed_event) {
                $events[] = $parsed_event;
            }
        }
        
        return $events;
    }

    /**
     * Parse a single SSE event block
     *
     * @param string $block Single event block
     * @return array|null Parsed event or null if invalid
     */
    private static function parse_single_event($block) {
        $lines = explode("\n", $block);
        $event_type = '';
        $event_data = '';
        $event_id = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (preg_match('/^event: (.+)$/', $line, $matches)) {
                $event_type = trim($matches[1]);
            } elseif (preg_match('/^data: (.+)$/', $line, $matches)) {
                $event_data .= trim($matches[1]);
            } elseif (preg_match('/^id: (.+)$/', $line, $matches)) {
                $event_id = trim($matches[1]);
            }
        }
        
        if (empty($event_type) && empty($event_data)) {
            return null;
        }
        
        return array(
            'type' => $event_type ?: 'message',
            'data' => $event_data,
            'id' => $event_id,
            'parsed_data' => self::try_parse_json($event_data)
        );
    }

    /**
     * Convert provider-specific events to standard frontend SSE format
     * This allows each provider to define their own mapping while maintaining consistency
     *
     * @param array $events Array of parsed events
     * @param array $mapping Provider-specific event mapping configuration
     * @return void Outputs SSE events directly to response stream
     */
    public static function convert_to_frontend_sse($events, $mapping = array()) {
        foreach ($events as $event) {
            $converted_event = self::apply_event_mapping($event, $mapping);
            if ($converted_event) {
                self::output_sse_event($converted_event['type'], $converted_event['data']);
            }
        }
    }

    /**
     * Apply provider-specific event mapping
     *
     * @param array $event Parsed event
     * @param array $mapping Mapping configuration
     * @return array|null Converted event or null if should be skipped
     */
    private static function apply_event_mapping($event, $mapping) {
        $event_type = $event['type'];
        $parsed_data = $event['parsed_data'];
        
        // Apply provider-specific mapping rules
        if (isset($mapping[$event_type])) {
            $map_config = $mapping[$event_type];
            
            // Skip event if configured to do so
            if (isset($map_config['skip']) && $map_config['skip']) {
                return null;
            }
            
            // Map to new event type
            $new_type = isset($map_config['to']) ? $map_config['to'] : $event_type;
            
            // Extract specific data fields if configured
            $new_data = $event['data'];
            if (isset($map_config['extract']) && $parsed_data) {
                $extracted = self::extract_nested_data($parsed_data, $map_config['extract']);
                $new_data = function_exists('wp_json_encode') ? wp_json_encode($extracted) : json_encode($extracted);
            }
            
            return array(
                'type' => $new_type,
                'data' => $new_data
            );
        }
        
        // Default: pass through unchanged
        return array(
            'type' => $event_type,
            'data' => $event['data']
        );
    }

    /**
     * Extract nested data using dot notation
     *
     * @param array $data Data array
     * @param string $path Dot notation path (e.g., 'delta.content')
     * @return mixed Extracted value or null
     */
    private static function extract_nested_data($data, $path) {
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }

    /**
     * Output a single SSE event to the response stream
     *
     * @param string $type Event type
     * @param string $data Event data
     */
    public static function output_sse_event($type, $data) {
        echo "event: {$type}\n";
        echo "data: {$data}\n\n";
        self::flush_output();
    }

    /**
     * Try to parse JSON data
     *
     * @param string $data Raw data string
     * @return array|null Parsed data or null if not valid JSON
     */
    private static function try_parse_json($data) {
        if (empty($data)) {
            return null;
        }
        
        $decoded = json_decode($data, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    /**
     * Flush output buffer immediately
     */
    private static function flush_output() {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Get OpenAI-specific event mapping for Responses API
     * This can be used by the OpenAI provider to convert events properly
     *
     * @return array Event mapping configuration
     */
    public static function get_openai_responses_mapping() {
        return array(
            'response.content.delta' => array(
                'to' => 'text',
                'extract' => 'delta.content'
            ),
            'response.completed' => array(
                'to' => 'stream_end',
                'extract' => 'status'
            ),
            'response.function_call.completed' => array(
                'skip' => true // Tool calls handled separately
            )
        );
    }

    /**
     * Get Anthropic-specific event mapping for Messages API
     * Example mapping for future Anthropic integration
     *
     * @return array Event mapping configuration
     */
    public static function get_anthropic_messages_mapping() {
        return array(
            'content_block_delta' => array(
                'to' => 'text',
                'extract' => 'delta.text'
            ),
            'message_stop' => array(
                'to' => 'stream_end'
            )
        );
    }
}