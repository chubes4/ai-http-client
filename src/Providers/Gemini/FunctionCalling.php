<?php
/**
 * AI HTTP Client - Google Gemini Function Calling Module
 * 
 * Single Responsibility: Handle ONLY Gemini function calling functionality
 * Based on Gemini's function calling API and tool configuration format
 *
 * @package AIHttpClient\Providers\Gemini
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Gemini_Function_Calling {

    /**
     * Sanitize function/tool definitions for Gemini format
     * Gemini uses functionDeclarations within tools array
     *
     * @param array $tools Tools array
     * @return array Sanitized tools in Gemini format
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
                error_log('Gemini tool normalization error: ' . $e->getMessage());
            }
        }

        // Gemini wraps function declarations in a tools array
        if (!empty($sanitized)) {
            return array(
                array(
                    'functionDeclarations' => $sanitized
                )
            );
        }

        return array();
    }

    /**
     * Normalize a single tool to Gemini format
     * Gemini uses name, description, parameters (JSON Schema)
     *
     * @param array $tool Tool definition
     * @return array Gemini-formatted tool
     */
    private static function normalize_single_tool($tool) {
        // Handle if tool is in OpenAI format - convert to Gemini
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return array(
                'name' => sanitize_text_field($tool['function']['name']),
                'description' => sanitize_textarea_field($tool['function']['description']),
                'parameters' => $tool['function']['parameters'] ?? array()
            );
        }
        
        // Handle if tool is in Anthropic format - convert to Gemini
        if (isset($tool['name']) && isset($tool['description']) && isset($tool['input_schema'])) {
            return array(
                'name' => sanitize_text_field($tool['name']),
                'description' => sanitize_textarea_field($tool['description']),
                'parameters' => $tool['input_schema']
            );
        }
        
        // Handle if tool is already in Gemini format
        if (isset($tool['name']) && isset($tool['description'])) {
            return array(
                'name' => sanitize_text_field($tool['name']),
                'description' => sanitize_textarea_field($tool['description']),
                'parameters' => $tool['parameters'] ?? array()
            );
        }
        
        // Handle specialized tools (like Data Machine web search)
        if (isset($tool['type']) && $tool['type'] === 'web_search_preview') {
            $search_tool = AI_HTTP_Web_Search_Client::get_search_tool_definition();
            return array(
                'name' => $search_tool['name'],
                'description' => $search_tool['description'],
                'parameters' => $search_tool['parameters']
            );
        }
        
        // Handle standard web search tool
        if (isset($tool['name']) && $tool['name'] === 'web_search') {
            $search_tool = AI_HTTP_Web_Search_Client::get_search_tool_definition();
            return array(
                'name' => $search_tool['name'],
                'description' => $search_tool['description'],
                'parameters' => $search_tool['parameters']
            );
        }
        
        throw new Exception('Invalid tool definition for Gemini format');
    }

    /**
     * Build tool result messages for conversation history
     * Gemini uses functionResponse format
     *
     * @param array $tool_calls Array of tool call results
     * @return array Gemini-formatted tool result parts
     */
    public static function build_tool_result_parts($tool_calls) {
        $parts = array();
        
        foreach ($tool_calls as $tool_call) {
            if (isset($tool_call['tool_call_id']) && isset($tool_call['result'])) {
                // Gemini format for tool results
                $parts[] = array(
                    'functionResponse' => array(
                        'name' => $tool_call['tool_name'] ?? 'unknown_function',
                        'response' => array(
                            'result' => is_string($tool_call['result']) ? $tool_call['result'] : wp_json_encode($tool_call['result'])
                        )
                    )
                );
            }
        }
        
        return $parts;
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
     * Get available function calling models for Gemini
     * Returns Gemini models that support function calling
     *
     * @return array Array of model IDs that support function calling
     */
    public static function get_function_calling_models() {
        return array(
            'gemini-2.0-flash',
            'gemini-2.5-flash',
            'gemini-1.5-flash',
            'gemini-1.5-pro',
            'gemini-1.5-flash-8b',
            'gemini-pro'
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
     * Validate tool choice parameter for Gemini
     * Gemini uses toolConfig with functionCallingConfig
     *
     * @param mixed $tool_choice Tool choice specification
     * @return array Validated tool config
     */
    public static function validate_tool_choice($tool_choice) {
        if (is_string($tool_choice)) {
            switch ($tool_choice) {
                case 'auto':
                    return array(
                        'functionCallingConfig' => array(
                            'mode' => 'AUTO'
                        )
                    );
                case 'any':
                case 'required':
                    return array(
                        'functionCallingConfig' => array(
                            'mode' => 'ANY'
                        )
                    );
                case 'none':
                    return array(
                        'functionCallingConfig' => array(
                            'mode' => 'NONE'
                        )
                    );
            }
        }
        
        if (is_array($tool_choice)) {
            // Allow specific tool selection format
            if (isset($tool_choice['type']) && $tool_choice['type'] === 'function') {
                return array(
                    'functionCallingConfig' => array(
                        'mode' => 'ANY',
                        'allowedFunctionNames' => array($tool_choice['function']['name'])
                    )
                );
            }
        }
        
        // Default to auto if invalid
        return array(
            'functionCallingConfig' => array(
                'mode' => 'AUTO'
            )
        );
    }

    /**
     * Extract function calls from Gemini content parts
     * Helper method for parsing Gemini responses
     *
     * @param array $parts Array of content parts from Gemini response
     * @return array Extracted tool calls
     */
    public static function extract_function_calls_from_parts($parts) {
        $tool_calls = array();
        
        if (!is_array($parts)) {
            return $tool_calls;
        }
        
        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $tool_calls[] = array(
                    'id' => $part['functionCall']['name'] . '_' . uniqid(),
                    'type' => 'function',
                    'function' => array(
                        'name' => $part['functionCall']['name'],
                        'arguments' => wp_json_encode($part['functionCall']['args'] ?? array())
                    )
                );
            }
        }
        
        return $tool_calls;
    }

    /**
     * Format tool call for Gemini API request
     * Converts standard format to Gemini functionCall format
     *
     * @param array $tool_call Standard tool call format
     * @return array Gemini functionCall format
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
            'functionCall' => array(
                'name' => $tool_call['function']['name'],
                'args' => $arguments ?? array()
            )
        );
    }

    /**
     * Get Gemini-specific tool configuration
     * Returns configuration options specific to Gemini
     *
     * @return array Gemini tool configuration
     */
    public static function get_tool_configuration() {
        return array(
            'max_functions_per_request' => 50,
            'supports_parallel_calls' => true,
            'supports_tool_choice' => true,
            'tool_choice_modes' => array('AUTO', 'ANY', 'NONE'),
            'response_format' => 'functionCall',
            'supports_function_calling_config' => true
        );
    }

    /**
     * Validate function declaration schema
     * Ensures function declarations meet Gemini requirements
     *
     * @param array $function_declaration Function declaration
     * @return bool True if valid
     */
    public static function validate_function_declaration($function_declaration) {
        // Required fields
        if (!isset($function_declaration['name']) || !isset($function_declaration['description'])) {
            return false;
        }
        
        // Name validation
        if (!is_string($function_declaration['name']) || empty($function_declaration['name'])) {
            return false;
        }
        
        // Description validation
        if (!is_string($function_declaration['description']) || empty($function_declaration['description'])) {
            return false;
        }
        
        // Parameters should be JSON Schema if present
        if (isset($function_declaration['parameters'])) {
            if (!is_array($function_declaration['parameters'])) {
                return false;
            }
        }
        
        return true;
    }
}