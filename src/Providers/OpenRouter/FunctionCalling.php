<?php
/**
 * AI HTTP Client - OpenRouter Function Calling Module
 * 
 * Single Responsibility: Handle ONLY OpenRouter function calling functionality
 * Based on OpenRouter's normalized function calling API which supports multiple providers
 * Uses OpenAI-compatible format with automatic provider adaptation
 *
 * @package AIHttpClient\Providers\OpenRouter
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenRouter_Function_Calling {

    /**
     * Sanitize function/tool definitions for OpenRouter format
     * OpenRouter uses OpenAI-compatible format and normalizes across providers
     *
     * @param array $tools Tools array
     * @return array Sanitized tools in OpenRouter format
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
                error_log('OpenRouter tool normalization error: ' . $e->getMessage());
            }
        }

        return $sanitized;
    }

    /**
     * Normalize a single tool to OpenRouter format
     * OpenRouter uses OpenAI format: type: "function", function: {name, description, parameters}
     *
     * @param array $tool Tool definition
     * @return array OpenRouter-formatted tool
     */
    private static function normalize_single_tool($tool) {
        // Handle if tool is already in OpenAI/OpenRouter format
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
        
        // Handle if tool is in Anthropic format - convert to OpenRouter
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
        
        // Handle if tool is in Gemini format - convert to OpenRouter
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
        
        throw new Exception('Invalid tool definition for OpenRouter format');
    }

    /**
     * Build tool result messages for conversation history
     * OpenRouter uses OpenAI format for tool results
     *
     * @param array $tool_calls Array of tool call results
     * @return array OpenRouter-formatted tool result messages
     */
    public static function build_tool_result_messages($tool_calls) {
        $messages = array();
        
        foreach ($tool_calls as $tool_call) {
            if (isset($tool_call['tool_call_id']) && isset($tool_call['result'])) {
                // OpenAI/OpenRouter format for tool results
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
     * Validate tool choice parameter for OpenRouter
     * OpenRouter supports OpenAI-compatible tool_choice options
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
     * Extract function calls from OpenRouter response
     * Helper method for parsing OpenRouter responses (OpenAI format)
     *
     * @param array $response OpenRouter response data
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
     * Format tool call for OpenRouter API request
     * Converts standard format to OpenRouter function call format
     *
     * @param array $tool_call Standard tool call format
     * @return array OpenRouter function call format
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
     * Get OpenRouter-specific tool configuration
     * Returns configuration options specific to OpenRouter
     *
     * @return array OpenRouter tool configuration
     */
    public static function get_tool_configuration() {
        return array(
            'max_tools_per_request' => 128,
            'supports_parallel_calls' => true,
            'supports_tool_choice' => true,
            'tool_choice_options' => array('none', 'auto', 'required'),
            'response_format' => 'openai_compatible',
            'normalized_across_providers' => true,
            'supports_provider_routing' => true
        );
    }

    /**
     * Validate function definition schema
     * Ensures function definitions meet OpenRouter requirements
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
     * Get function calling usage statistics from response
     *
     * @param array $response OpenRouter response
     * @return array Usage statistics
     */
    public static function get_function_calling_usage($response) {
        $usage = array(
            'tool_calls_count' => 0,
            'native_provider' => null,
            'routing_info' => array()
        );
        
        if (isset($response['choices'])) {
            foreach ($response['choices'] as $choice) {
                if (isset($choice['message']['tool_calls'])) {
                    $usage['tool_calls_count'] += count($choice['message']['tool_calls']);
                }
            }
        }
        
        // OpenRouter-specific provider routing information
        if (isset($response['provider'])) {
            $usage['native_provider'] = $response['provider'];
        }
        
        if (isset($response['model'])) {
            $usage['actual_model'] = $response['model'];
        }
        
        if (isset($response['usage'])) {
            $usage['tokens'] = $response['usage'];
        }
        
        return $usage;
    }

    /**
     * Check if response contains function calls
     *
     * @param array $response OpenRouter response
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

    /**
     * Get provider-specific model capabilities for function calling
     * OpenRouter normalizes capabilities but may have provider-specific limitations
     *
     * @param string $model_id Model identifier
     * @return array Model capabilities for function calling
     */
    public static function get_model_function_calling_capabilities($model_id) {
        // OpenRouter abstracts provider differences
        // Most models support function calling through OpenRouter's normalization
        return array(
            'supports_function_calling' => true,
            'supports_parallel_calls' => true,
            'max_tools' => 128,
            'normalized_by_openrouter' => true,
            'provider_adaptation' => true
        );
    }

    /**
     * Handle OpenRouter provider preferences for function calling
     *
     * @param array $request Request data
     * @param array $provider_preferences Provider routing preferences
     * @return array Modified request with provider preferences
     */
    public static function apply_provider_preferences($request, $provider_preferences = array()) {
        if (!empty($provider_preferences)) {
            // OpenRouter allows specifying provider preferences
            if (isset($provider_preferences['order'])) {
                $request['provider'] = $provider_preferences;
            }
        }
        
        return $request;
    }

    /**
     * Extract provider routing information from function call response
     *
     * @param array $response OpenRouter response
     * @return array Provider routing information
     */
    public static function extract_provider_routing_info($response) {
        $routing_info = array();
        
        if (isset($response['provider'])) {
            $routing_info['used_provider'] = $response['provider'];
        }
        
        if (isset($response['model'])) {
            $routing_info['actual_model'] = $response['model'];
        }
        
        if (isset($response['id'])) {
            $routing_info['generation_id'] = $response['id'];
        }
        
        if (isset($response['usage'])) {
            $routing_info['usage'] = $response['usage'];
        }
        
        return $routing_info;
    }

    /**
     * Validate tool calls against OpenRouter model capabilities
     *
     * @param array $tool_calls Tool calls to validate
     * @param string $model_id Model being used
     * @return array Validation results
     */
    public static function validate_tool_calls_for_model($tool_calls, $model_id) {
        $validation = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array()
        );
        
        // OpenRouter handles most validation internally
        // Basic validation for our system
        if (count($tool_calls) > 128) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Too many tool calls (max 128)';
        }
        
        foreach ($tool_calls as $tool_call) {
            if (!isset($tool_call['function']['name'])) {
                $validation['valid'] = false;
                $validation['errors'][] = 'Tool call missing function name';
            }
        }
        
        return $validation;
    }
}