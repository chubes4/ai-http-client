<?php
/**
 * AI HTTP Client - OpenAI Function Calling Module
 * 
 * Single Responsibility: Handle ONLY OpenAI function calling functionality
 * Based on Wordsurf and Data Machine's working implementations
 *
 * @package AIHttpClient\Providers\OpenAI
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenAI_Function_Calling {

    /**
     * Sanitize function/tool definitions for OpenAI Responses API
     *
     * @param array $tools Tools array
     * @return array Sanitized tools
     */
    public static function sanitize_tools($tools) {
        $sanitized = array();

        foreach ($tools as $tool) {
            try {
                $sanitized[] = self::normalize_single_tool($tool);
            } catch (Exception $e) {
                error_log('OpenAI tool normalization error: ' . $e->getMessage());
            }
        }

        return $sanitized;
    }

    /**
     * Normalize a single tool to OpenAI format
     *
     * @param array $tool Tool definition
     * @return array OpenAI-formatted tool
     */
    private static function normalize_single_tool($tool) {
        // Handle if tool is already in OpenAI format
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return array(
                'type' => 'function',
                'function' => array(
                    'name' => sanitize_text_field($tool['function']['name']),
                    'description' => sanitize_textarea_field($tool['function']['description']),
                    'parameters' => $tool['function']['parameters'] ?? array()
                )
            );
        }
        
        // Handle direct function definition
        if (isset($tool['name']) && isset($tool['description'])) {
            return array(
                'type' => 'function',
                'function' => array(
                    'name' => sanitize_text_field($tool['name']),
                    'description' => sanitize_textarea_field($tool['description']),
                    'parameters' => $tool['parameters'] ?? array()
                )
            );
        }
        
        
        throw new Exception('Invalid tool definition for OpenAI format');
    }

    /**
     * Validate tool choice parameter
     *
     * @param mixed $tool_choice Tool choice specification
     * @return mixed Validated tool choice
     */
    public static function validate_tool_choice($tool_choice) {
        if (is_string($tool_choice)) {
            // Allow 'auto', 'none', or 'required'
            $valid_choices = array('auto', 'none', 'required');
            if (in_array($tool_choice, $valid_choices)) {
                return $tool_choice;
            }
        }
        
        if (is_array($tool_choice)) {
            // Allow specific tool selection format
            if (isset($tool_choice['type'])) {
                return $tool_choice;
            }
        }
        
        // Default to auto if invalid
        return 'auto';
    }

    /**
     * Build tool calls for conversation history
     * Converts tool results back to OpenAI message format
     *
     * @param array $tool_calls Array of tool call results
     * @return array OpenAI-formatted tool call messages
     */
    public static function build_tool_call_messages($tool_calls) {
        $messages = array();
        
        foreach ($tool_calls as $tool_call) {
            if (isset($tool_call['tool_call_id']) && isset($tool_call['result'])) {
                $messages[] = array(
                    'role' => 'tool',
                    'tool_call_id' => $tool_call['tool_call_id'],
                    'content' => is_string($tool_call['result']) ? $tool_call['result'] : wp_json_encode($tool_call['result'])
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
     * Get available function calling models
     * Returns models that support function calling
     *
     * @return array Array of model IDs that support function calling
     */
    public static function get_function_calling_models() {
        return array(
            'gpt-4',
            'gpt-4-turbo',
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-3.5-turbo'
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
}