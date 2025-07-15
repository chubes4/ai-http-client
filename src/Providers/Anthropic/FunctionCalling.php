<?php
/**
 * AI HTTP Client - Anthropic Function Calling Module
 * 
 * Single Responsibility: Handle ONLY Anthropic function calling functionality
 * Based on Claude's tool use patterns and data from working implementations
 *
 * @package AIHttpClient\Providers\Anthropic
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Anthropic_Function_Calling {

    /**
     * Sanitize function/tool definitions for Anthropic format
     * Anthropic uses a different tool schema format than OpenAI
     *
     * @param array $tools Tools array
     * @return array Sanitized tools in Anthropic format
     */
    public static function sanitize_tools($tools) {
        $sanitized = array();

        foreach ($tools as $tool) {
            try {
                $sanitized[] = self::normalize_single_tool($tool);
            } catch (Exception $e) {
                error_log('Anthropic tool normalization error: ' . $e->getMessage());
            }
        }

        return $sanitized;
    }

    /**
     * Normalize a single tool to Anthropic format
     * Anthropic uses name, description, input_schema instead of OpenAI's function wrapper
     *
     * @param array $tool Tool definition
     * @return array Anthropic-formatted tool
     */
    private static function normalize_single_tool($tool) {
        // Handle if tool is in OpenAI format - convert to Anthropic
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return array(
                'name' => sanitize_text_field($tool['function']['name']),
                'description' => sanitize_textarea_field($tool['function']['description']),
                'input_schema' => $tool['function']['parameters'] ?? array()
            );
        }
        
        // Handle if tool is already in Anthropic format
        if (isset($tool['name']) && isset($tool['description'])) {
            return array(
                'name' => sanitize_text_field($tool['name']),
                'description' => sanitize_textarea_field($tool['description']),
                'input_schema' => $tool['input_schema'] ?? $tool['parameters'] ?? array()
            );
        }
        
        // Handle specialized tools (like Data Machine web search)
        if (isset($tool['type']) && $tool['type'] === 'web_search_preview') {
            $search_tool = AI_HTTP_Web_Search_Client::get_search_tool_definition();
            return array(
                'name' => $search_tool['name'],
                'description' => $search_tool['description'],
                'input_schema' => $search_tool['parameters']
            );
        }
        
        // Handle standard web search tool
        if (isset($tool['name']) && $tool['name'] === 'web_search') {
            $search_tool = AI_HTTP_Web_Search_Client::get_search_tool_definition();
            return array(
                'name' => $search_tool['name'],
                'description' => $search_tool['description'],
                'input_schema' => $search_tool['parameters']
            );
        }
        
        throw new Exception('Invalid tool definition for Anthropic format');
    }

    /**
     * Build tool result messages for conversation history
     * Anthropic uses a specific format for tool results
     *
     * @param array $tool_calls Array of tool call results
     * @return array Anthropic-formatted tool result messages
     */
    public static function build_tool_result_messages($tool_calls) {
        $messages = array();
        
        foreach ($tool_calls as $tool_call) {
            if (isset($tool_call['tool_call_id']) && isset($tool_call['result'])) {
                // Anthropic format for tool results
                $messages[] = array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'tool_result',
                            'tool_use_id' => $tool_call['tool_call_id'],
                            'content' => is_string($tool_call['result']) ? $tool_call['result'] : wp_json_encode($tool_call['result'])
                        )
                    )
                );
            }
        }
        
        return $messages;
    }

    /**
     * Check if request includes function calling
     *
     * @param array $request Request data
     * @return bool True if request includes tools
     */
    public static function has_function_calling($request) {
        return isset($request['tools']) && is_array($request['tools']) && !empty($request['tools']);
    }

    /**
     * Get available function calling models for Anthropic
     * Returns Claude models that support function calling
     *
     * @return array Array of model IDs that support function calling
     */
    public static function get_function_calling_models() {
        return array(
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307'
        );
    }

    /**
     * Validate model supports function calling
     *
     * @param string $model Model ID
     * @return bool True if model supports function calling
     */
    public static function model_supports_function_calling($model) {
        $supported_models = self::get_function_calling_models();
        
        foreach ($supported_models as $supported_model) {
            if (strpos($model, $supported_model) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate tool choice parameter for Anthropic
     * Anthropic has different tool choice options than OpenAI
     *
     * @param mixed $tool_choice Tool choice specification
     * @return mixed Validated tool choice
     */
    public static function validate_tool_choice($tool_choice) {
        if (is_string($tool_choice)) {
            // Anthropic supports 'auto', 'any', or 'tool'
            $valid_choices = array('auto', 'any', 'tool');
            if (in_array($tool_choice, $valid_choices)) {
                return $tool_choice;
            }
        }
        
        if (is_array($tool_choice)) {
            // Allow specific tool selection format
            if (isset($tool_choice['type']) && $tool_choice['type'] === 'tool') {
                return $tool_choice;
            }
        }
        
        // Default to auto if invalid
        return 'auto';
    }

    /**
     * Extract tool use from Anthropic content blocks
     * Helper method for parsing Anthropic responses
     *
     * @param array $content_blocks Array of content blocks from Anthropic response
     * @return array Extracted tool calls
     */
    public static function extract_tool_use_from_content($content_blocks) {
        $tool_calls = array();
        
        if (!is_array($content_blocks)) {
            return $tool_calls;
        }
        
        foreach ($content_blocks as $block) {
            if (isset($block['type']) && $block['type'] === 'tool_use') {
                $tool_calls[] = array(
                    'id' => $block['id'] ?? uniqid('tool_'),
                    'type' => 'function',
                    'function' => array(
                        'name' => $block['name'],
                        'arguments' => wp_json_encode($block['input'] ?? array())
                    )
                );
            }
        }
        
        return $tool_calls;
    }

    /**
     * Format tool call for Anthropic API request
     * Converts standard format to Anthropic tool_use format
     *
     * @param array $tool_call Standard tool call format
     * @return array Anthropic tool_use format
     */
    public static function format_tool_call_for_request($tool_call) {
        if (!isset($tool_call['function']['name'])) {
            return null;
        }
        
        $arguments = array();
        if (isset($tool_call['function']['arguments'])) {
            $arguments = is_string($tool_call['function']['arguments']) 
                ? json_decode($tool_call['function']['arguments'], true) 
                : $tool_call['function']['arguments'];
        }
        
        return array(
            'type' => 'tool_use',
            'id' => $tool_call['id'] ?? uniqid('tool_'),
            'name' => $tool_call['function']['name'],
            'input' => $arguments ?? array()
        );
    }

    /**
     * Get Anthropic-specific tool configuration
     * Returns configuration options specific to Claude
     *
     * @return array Anthropic tool configuration
     */
    public static function get_tool_configuration() {
        return array(
            'max_tools_per_request' => 20,
            'supports_parallel_calls' => true,
            'supports_tool_choice' => true,
            'tool_choice_options' => array('auto', 'any', 'tool'),
            'response_format' => 'tool_use_blocks'
        );
    }
}