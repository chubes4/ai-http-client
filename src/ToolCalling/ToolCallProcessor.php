<?php
/**
 * AI HTTP Client - Tool Call Processor
 * 
 * Single Responsibility: Process and extract tool calls from streaming responses
 * Based on Wordsurf's tool calling architecture
 *
 * @package AIHttpClient\FunctionCalling
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Tool_Call_Processor {

    /**
     * Process streaming response and extract tool calls
     * Based on Wordsurf's process_and_execute_tool_calls method
     *
     * @param string $full_response Complete streaming response
     * @param string $provider Provider name (openai, anthropic, etc.)
     * @return array Array of tool calls found in response
     */
    public static function extract_tool_calls($full_response, $provider = 'openai') {
        $tool_calls = [];
        
        switch ($provider) {
            case 'openai':
                return self::extract_openai_tool_calls($full_response);
            case 'anthropic':
                return self::extract_anthropic_tool_calls($full_response);
            default:
                return $tool_calls;
        }
    }

    /**
     * Extract tool calls from OpenAI streaming response
     *
     * @param string $full_response Complete OpenAI streaming response
     * @return array Tool calls in standardized format
     */
    private static function extract_openai_tool_calls($full_response) {
        $tool_calls = [];
        
        // Parse SSE events (like Wordsurf does)
        $event_blocks = explode("\n\n", trim($full_response));
        
        foreach ($event_blocks as $block) {
            if (empty(trim($block))) {
                continue;
            }
            
            $lines = explode("\n", $block);
            $current_data = '';
            
            foreach ($lines as $line) {
                if (preg_match('/^data: (.+)$/', trim($line), $matches)) {
                    $current_data .= trim($matches[1]);
                }
            }
            
            if (!empty($current_data) && $current_data !== '[DONE]') {
                $decoded = json_decode($current_data, true);
                if ($decoded && isset($decoded['choices'][0]['delta']['tool_calls'])) {
                    $delta_tool_calls = $decoded['choices'][0]['delta']['tool_calls'];
                    
                    foreach ($delta_tool_calls as $tool_call) {
                        if (isset($tool_call['function']['name'])) {
                            $tool_calls[] = [
                                'id' => $tool_call['id'] ?? uniqid('tool_'),
                                'type' => 'function',
                                'function' => [
                                    'name' => $tool_call['function']['name'],
                                    'arguments' => $tool_call['function']['arguments'] ?? '{}'
                                ]
                            ];
                        }
                    }
                }
            }
        }
        
        return self::normalize_tool_calls($tool_calls, 'openai');
    }

    /**
     * Extract tool calls from Anthropic streaming response
     *
     * @param string $full_response Complete Anthropic streaming response
     * @return array Tool calls in standardized format
     */
    private static function extract_anthropic_tool_calls($full_response) {
        $tool_calls = [];
        
        // Parse SSE events for Anthropic
        $event_blocks = explode("\n\n", trim($full_response));
        
        foreach ($event_blocks as $block) {
            if (empty(trim($block))) {
                continue;
            }
            
            $lines = explode("\n", $block);
            $current_data = '';
            
            foreach ($lines as $line) {
                if (preg_match('/^data: (.+)$/', trim($line), $matches)) {
                    $current_data .= trim($matches[1]);
                }
            }
            
            if (!empty($current_data)) {
                $decoded = json_decode($current_data, true);
                if ($decoded && isset($decoded['content'])) {
                    foreach ($decoded['content'] as $content_block) {
                        if (isset($content_block['type']) && $content_block['type'] === 'tool_use') {
                            $tool_calls[] = [
                                'id' => $content_block['id'] ?? uniqid('tool_'),
                                'type' => 'function',
                                'function' => [
                                    'name' => $content_block['name'],
                                    'arguments' => json_encode($content_block['input'] ?? [])
                                ]
                            ];
                        }
                    }
                }
            }
        }
        
        return self::normalize_tool_calls($tool_calls, 'anthropic');
    }

    /**
     * Normalize tool calls to standardized format
     *
     * @param array $tool_calls Raw tool calls
     * @param string $provider Provider name
     * @return array Normalized tool calls
     */
    private static function normalize_tool_calls($tool_calls, $provider) {
        $normalized = [];
        
        foreach ($tool_calls as $tool_call) {
            $normalized[] = [
                'id' => $tool_call['id'],
                'type' => 'function',
                'function' => [
                    'name' => $tool_call['function']['name'],
                    'arguments' => $tool_call['function']['arguments']
                ],
                'provider' => $provider,
                'raw' => $tool_call
            ];
        }
        
        return $normalized;
    }

    /**
     * Validate tool definition format
     *
     * @param array $tool Tool definition
     * @return bool True if valid
     */
    public static function validate_tool_definition($tool) {
        if (!is_array($tool)) {
            return false;
        }
        
        // Check required fields
        if (!isset($tool['type']) || $tool['type'] !== 'function') {
            return false;
        }
        
        if (!isset($tool['function']['name']) || !isset($tool['function']['description'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Convert tool definition to OpenAI format
     *
     * @param array $tool Standard tool definition
     * @return array OpenAI-formatted tool
     */
    public static function to_openai_format($tool) {
        if (!self::validate_tool_definition($tool)) {
            throw new Exception('Invalid tool definition');
        }
        
        return [
            'type' => 'function',
            'function' => [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'],
                'parameters' => $tool['function']['parameters'] ?? []
            ]
        ];
    }

    /**
     * Convert tool definition to Anthropic format
     *
     * @param array $tool Standard tool definition
     * @return array Anthropic-formatted tool
     */
    public static function to_anthropic_format($tool) {
        if (!self::validate_tool_definition($tool)) {
            throw new Exception('Invalid tool definition');
        }
        
        return [
            'name' => $tool['function']['name'],
            'description' => $tool['function']['description'],
            'input_schema' => $tool['function']['parameters'] ?? []
        ];
    }

    /**
     * Sanitize tool arguments for security
     *
     * @param string $arguments JSON string of arguments
     * @return array Sanitized arguments
     */
    public static function sanitize_tool_arguments($arguments) {
        $decoded = json_decode($arguments, true);
        if (!is_array($decoded)) {
            return [];
        }
        
        // Recursively sanitize all string values
        return self::sanitize_array_recursive($decoded);
    }

    /**
     * Recursively sanitize array values
     *
     * @param array $array Array to sanitize
     * @return array Sanitized array
     */
    private static function sanitize_array_recursive($array) {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            $sanitized_key = sanitize_text_field($key);
            
            if (is_array($value)) {
                $sanitized[$sanitized_key] = self::sanitize_array_recursive($value);
            } elseif (is_string($value)) {
                $sanitized[$sanitized_key] = sanitize_textarea_field($value);
            } else {
                $sanitized[$sanitized_key] = $value;
            }
        }
        
        return $sanitized;
    }
}