<?php
/**
 * AI HTTP Client - Grok/X.AI Function Calling Module
 * 
 * Single Responsibility: Handle ONLY Grok function calling functionality
 * Based on X.AI's function calling API which is OpenAI-compatible
 * Supports tools, tool_choice, and parallel function calling
 *
 * @package AIHttpClient\Providers\Grok
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Grok_Function_Calling {

    /**
     * Sanitize function/tool definitions for Grok format
     * Grok uses OpenAI-compatible format: tools array with type and function
     *
     * @param array $tools Tools array
     * @return array Sanitized tools in Grok format
     */
    public static function sanitize_tools($tools) {
        $sanitized = array();

        foreach ($tools as $tool) {
            try {
                $normalized_tool = self::normalize_single_tool($tool);
                if ($normalized_tool) {
                    $sanitized[] = $normalized_tool;
                }
            } catch (Exception $e) {
                error_log('Grok tool normalization error: ' . $e->getMessage());
            }
        }

        return $sanitized;
    }

    /**
     * Normalize a single tool to Grok format
     * Grok uses OpenAI format: type: "function", function: {name, description, parameters}
     *
     * @param array $tool Tool definition
     * @return array Grok-formatted tool
     */
    private static function normalize_single_tool($tool) {
        // Handle if tool is already in OpenAI/Grok format
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
        
        // Handle if tool is in Anthropic format - convert to Grok
        if (isset($tool['name']) && isset($tool['description']) && isset($tool['input_schema'])) {
            return array(
                'type' => 'function',
                'function' => array(
                    'name' => sanitize_text_field($tool['name']),
                    'description' => sanitize_textarea_field($tool['description']),
                    'parameters' => $tool['input_schema']
                )
            );
        }
        
        // Handle if tool is in Gemini format - convert to Grok
        if (isset($tool['name']) && isset($tool['description']) && isset($tool['parameters'])) {
            return array(
                'type' => 'function',
                'function' => array(
                    'name' => sanitize_text_field($tool['name']),
                    'description' => sanitize_textarea_field($tool['description']),
                    'parameters' => $tool['parameters']
                )
            );
        }
        
        // Handle specialized tools (like Data Machine web search)
        if (isset($tool['type']) && $tool['type'] === 'web_search_preview') {
            $search_tool = AI_HTTP_Web_Search_Client::get_search_tool_definition();
            return array(
                'type' => 'function',
                'function' => array(
                    'name' => $search_tool['name'],
                    'description' => $search_tool['description'],
                    'parameters' => $search_tool['parameters']
                )
            );
        }
        
        // Handle standard web search tool
        if (isset($tool['name']) && $tool['name'] === 'web_search') {
            $search_tool = AI_HTTP_Web_Search_Client::get_search_tool_definition();
            return array(
                'type' => 'function',
                'function' => array(
                    'name' => $search_tool['name'],
                    'description' => $search_tool['description'],
                    'parameters' => $search_tool['parameters']
                )
            );
        }
        
        throw new Exception('Invalid tool definition for Grok format');
    }

    /**
     * Build tool result messages for conversation history
     * Grok uses OpenAI format for tool results
     *
     * @param array $tool_calls Array of tool call results
     * @return array Grok-formatted tool result messages
     */
    public static function build_tool_result_messages($tool_calls) {
        $messages = array();
        
        foreach ($tool_calls as $tool_call) {
            if (isset($tool_call['tool_call_id']) && isset($tool_call['result'])) {
                // OpenAI/Grok format for tool results
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
     * Get available function calling models for Grok
     * Returns Grok models that support function calling
     *
     * @return array Array of model IDs that support function calling
     */
    public static function get_function_calling_models() {
        return array(
            'grok-3',
            'grok-3-fast',
            'grok-3-mini',
            'grok-3-mini-fast',
            'grok-2-1212',
            'grok-2-vision-1212',
            'grok-beta',
            'grok-vision-beta'
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
     * Validate tool choice parameter for Grok
     * Grok supports OpenAI-compatible tool_choice options
     *
     * @param mixed $tool_choice Tool choice specification
     * @return mixed Validated tool choice
     */
    public static function validate_tool_choice($tool_choice) {
        if (is_string($tool_choice)) {
            $valid_choices = array('none', 'auto', 'required');
            if (in_array($tool_choice, $valid_choices)) {
                return $tool_choice;
            }
        }
        
        if (is_array($tool_choice)) {
            // Allow specific tool selection format
            if (isset($tool_choice['type']) && $tool_choice['type'] === 'function' && isset($tool_choice['function']['name'])) {
                return $tool_choice;
            }
        }
        
        // Default to auto if invalid
        return 'auto';
    }

    /**
     * Extract function calls from Grok response
     * Helper method for parsing Grok responses (OpenAI format)
     *
     * @param array $response Grok response data
     * @return array Extracted tool calls
     */
    public static function extract_function_calls_from_response($response) {
        $tool_calls = array();
        
        if (isset($response['choices']) && is_array($response['choices'])) {
            foreach ($response['choices'] as $choice) {
                if (isset($choice['message']['tool_calls']) && is_array($choice['message']['tool_calls'])) {
                    $tool_calls = array_merge($tool_calls, $choice['message']['tool_calls']);
                }
            }
        }
        
        return $tool_calls;
    }

    /**
     * Format tool call for Grok API request
     * Converts standard format to Grok function call format
     *
     * @param array $tool_call Standard tool call format
     * @return array Grok function call format
     */
    public static function format_tool_call_for_request($tool_call) {
        if (!isset($tool_call['function']['name'])) {
            return null;
        }
        
        return array(
            'id' => $tool_call['id'] ?? $tool_call['function']['name'] . '_' . uniqid(),
            'type' => 'function',
            'function' => array(
                'name' => $tool_call['function']['name'],
                'arguments' => $tool_call['function']['arguments'] ?? '{}'
            )
        );
    }

    /**
     * Get Grok-specific tool configuration
     * Returns configuration options specific to Grok
     *
     * @return array Grok tool configuration
     */
    public static function get_tool_configuration() {
        return array(
            'max_tools_per_request' => 128,
            'supports_parallel_calls' => true,
            'supports_tool_choice' => true,
            'tool_choice_options' => array('none', 'auto', 'required'),
            'response_format' => 'openai_compatible',
            'supports_reasoning_effort' => true
        );
    }

    /**
     * Validate function definition schema
     * Ensures function definitions meet Grok requirements
     *
     * @param array $function Function definition
     * @return bool True if valid
     */
    public static function validate_function_definition($function) {
        // Required fields
        if (!isset($function['name']) || !isset($function['description'])) {
            return false;
        }
        
        // Name validation
        if (!is_string($function['name']) || empty($function['name'])) {
            return false;
        }
        
        // Description validation
        if (!is_string($function['description']) || empty($function['description'])) {
            return false;
        }
        
        // Parameters should be JSON Schema if present
        if (isset($function['parameters'])) {
            if (!is_array($function['parameters'])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Handle Grok-specific reasoning effort for function calling
     * Grok supports reasoning_effort parameter for enhanced tool use
     *
     * @param array $request Request data
     * @param string $effort Reasoning effort level
     * @return array Modified request with reasoning effort
     */
    public static function apply_reasoning_effort($request, $effort = 'medium') {
        $valid_efforts = array('low', 'medium', 'high');
        
        if (in_array($effort, $valid_efforts)) {
            $request['reasoning_effort'] = $effort;
        }
        
        return $request;
    }

    /**
     * Get function calling usage statistics from response
     *
     * @param array $response Grok response
     * @return array Usage statistics
     */
    public static function get_function_calling_usage($response) {
        $usage = array(
            'tool_calls_count' => 0,
            'reasoning_tokens' => 0,
            'function_tokens' => 0
        );
        
        if (isset($response['choices'])) {
            foreach ($response['choices'] as $choice) {
                if (isset($choice['message']['tool_calls'])) {
                    $usage['tool_calls_count'] += count($choice['message']['tool_calls']);
                }
            }
        }
        
        if (isset($response['usage'])) {
            $usage['reasoning_tokens'] = $response['usage']['reasoning_tokens'] ?? 0;
            $usage['function_tokens'] = $response['usage']['function_tokens'] ?? 0;
        }
        
        return $usage;
    }

    /**
     * Check if response contains function calls
     *
     * @param array $response Grok response
     * @return bool True if response contains function calls
     */
    public static function response_has_function_calls($response) {
        if (isset($response['choices']) && is_array($response['choices'])) {
            foreach ($response['choices'] as $choice) {
                if (isset($choice['message']['tool_calls']) && !empty($choice['message']['tool_calls'])) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Process function call results for multi-turn conversation
     *
     * @param array $tool_calls Tool calls from previous response
     * @param array $tool_results Results from tool execution
     * @return array Messages to add to conversation
     */
    public static function process_function_results($tool_calls, $tool_results) {
        $messages = array();
        
        // Add assistant message with tool calls
        if (!empty($tool_calls)) {
            $messages[] = array(
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => $tool_calls
            );
        }
        
        // Add tool result messages
        foreach ($tool_results as $result) {
            if (isset($result['tool_call_id'])) {
                $messages[] = array(
                    'role' => 'tool',
                    'tool_call_id' => $result['tool_call_id'],
                    'content' => is_string($result['result']) ? $result['result'] : wp_json_encode($result['result'])
                );
            }
        }
        
        return $messages;
    }
}