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
     * @return array Sanitized tools with error tracking
     */
    public static function sanitize_tools($tools) {
        $sanitized = array();
        $errors = array();

        foreach ($tools as $index => $tool) {
            try {
                $normalized_tool = self::normalize_single_tool($tool);
                
                // Validate the normalized tool
                if (self::validate_tool_definition($normalized_tool)) {
                    $sanitized[] = $normalized_tool;
                } else {
                    $error = 'Tool validation failed for tool at index ' . $index;
                    $errors[] = $error;
                    error_log('OpenAI tool validation error: ' . $error);
                }
                
            } catch (Exception $e) {
                $error = 'Tool normalization failed at index ' . $index . ': ' . $e->getMessage();
                $errors[] = $error;
                error_log('OpenAI tool normalization error: ' . $error);
            }
        }

        // Log summary if there were errors
        if (!empty($errors)) {
            error_log('OpenAI tool sanitization completed with ' . count($errors) . ' errors. Successfully processed ' . count($sanitized) . ' tools.');
        }

        return $sanitized;
    }
    
    /**
     * Validate a tool definition for completeness and correctness
     *
     * @param array $tool Tool definition
     * @return bool True if valid
     */
    private static function validate_tool_definition($tool) {
        // Check required fields
        if (!isset($tool['name']) || empty($tool['name'])) {
            return false;
        }
        
        if (!isset($tool['description']) || empty($tool['description'])) {
            return false;
        }
        
        // Check name format (must be valid function name)
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tool['name'])) {
            return false;
        }
        
        // Validate parameters if present
        if (isset($tool['parameters'])) {
            if (!is_array($tool['parameters'])) {
                return false;
            }
            
            // Check for required schema structure
            if (isset($tool['parameters']['type']) && $tool['parameters']['type'] !== 'object') {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Normalize a single tool to OpenAI Responses API format
     *
     * @param array $tool Tool definition
     * @return array OpenAI Responses API formatted tool
     */
    private static function normalize_single_tool($tool) {
        // Handle if tool is in Chat Completions format (nested function object)
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return array(
                'name' => sanitize_text_field($tool['function']['name']),
                'type' => 'function',
                'description' => sanitize_textarea_field($tool['function']['description']),
                'parameters' => $tool['function']['parameters'] ?? array()
            );
        }
        
        // Handle direct function definition (already flat)
        if (isset($tool['name']) && isset($tool['description'])) {
            return array(
                'name' => sanitize_text_field($tool['name']),
                'type' => 'function',
                'description' => sanitize_textarea_field($tool['description']),
                'parameters' => $tool['parameters'] ?? array()
            );
        }
        
        throw new Exception('Invalid tool definition for OpenAI Responses API format');
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
            try {
                if (isset($tool_call['tool_call_id']) && isset($tool_call['result'])) {
                    $content = self::format_tool_result_content($tool_call['result']);
                    
                    if ($content !== null) {
                        $messages[] = array(
                            'role' => 'tool',
                            'tool_call_id' => sanitize_text_field($tool_call['tool_call_id']),
                            'content' => $content
                        );
                    } else {
                        error_log('OpenAI tool result formatting failed for tool_call_id: ' . $tool_call['tool_call_id']);
                    }
                }
            } catch (Exception $e) {
                error_log('OpenAI tool call message building error: ' . $e->getMessage());
            }
        }
        
        return $messages;
    }
    
    /**
     * Format tool result content for OpenAI consumption
     *
     * @param mixed $result Tool execution result
     * @return string|null Formatted content or null if formatting fails
     */
    private static function format_tool_result_content($result) {
        try {
            if (is_string($result)) {
                return $result;
            }
            
            if (is_array($result)) {
                // Handle structured tool results
                if (isset($result['success']) && isset($result['error']) && !$result['success']) {
                    return 'Error: ' . $result['error'];
                }
                
                if (isset($result['content'])) {
                    return is_string($result['content']) ? $result['content'] : wp_json_encode($result['content']);
                }
                
                if (isset($result['results'])) {
                    return is_string($result['results']) ? $result['results'] : wp_json_encode($result['results']);
                }
                
                // Fallback to JSON encoding
                return wp_json_encode($result);
            }
            
            // Convert other types to string
            return (string) $result;
            
        } catch (Exception $e) {
            error_log('OpenAI tool result content formatting error: ' . $e->getMessage());
            return null;
        }
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