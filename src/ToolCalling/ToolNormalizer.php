<?php
/**
 * AI HTTP Client - Tool Definition Normalizer
 * 
 * Single Responsibility: Normalize tool definitions between providers
 * Handles differences between OpenAI and Anthropic tool formats
 *
 * @package AIHttpClient\FunctionCalling
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Tool_Normalizer {

    /**
     * Normalize tools array for specific provider
     *
     * @param array $tools Array of tool definitions
     * @param string $provider Target provider (openai, anthropic)
     * @return array Provider-specific tool definitions
     */
    public static function normalize_for_provider($tools, $provider) {
        if (!is_array($tools)) {
            return [];
        }
        
        $normalized = [];
        
        foreach ($tools as $tool) {
            try {
                switch ($provider) {
                    case 'openai':
                        $normalized[] = self::to_openai_format($tool);
                        break;
                    case 'anthropic':
                        $normalized[] = self::to_anthropic_format($tool);
                        break;
                    default:
                        $normalized[] = $tool; // Pass through for unknown providers
                        break;
                }
            } catch (Exception $e) {
                error_log("AI HTTP Client: Tool normalization error for {$provider}: " . $e->getMessage());
            }
        }
        
        return $normalized;
    }

    /**
     * Convert tool to OpenAI function calling format
     *
     * @param array $tool Tool definition
     * @return array OpenAI-formatted tool
     */
    public static function to_openai_format($tool) {
        // Handle if tool is already in OpenAI format
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => sanitize_text_field($tool['function']['name']),
                    'description' => sanitize_textarea_field($tool['function']['description']),
                    'parameters' => $tool['function']['parameters'] ?? []
                ]
            ];
        }
        
        // Handle direct function definition
        if (isset($tool['name']) && isset($tool['description'])) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => sanitize_text_field($tool['name']),
                    'description' => sanitize_textarea_field($tool['description']),
                    'parameters' => $tool['parameters'] ?? $tool['input_schema'] ?? []
                ]
            ];
        }
        
        throw new Exception('Invalid tool definition for OpenAI format');
    }

    /**
     * Convert tool to Anthropic tool format
     *
     * @param array $tool Tool definition
     * @return array Anthropic-formatted tool
     */
    public static function to_anthropic_format($tool) {
        // Handle if tool is in OpenAI format
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return [
                'name' => sanitize_text_field($tool['function']['name']),
                'description' => sanitize_textarea_field($tool['function']['description']),
                'input_schema' => $tool['function']['parameters'] ?? []
            ];
        }
        
        // Handle direct function definition
        if (isset($tool['name']) && isset($tool['description'])) {
            return [
                'name' => sanitize_text_field($tool['name']),
                'description' => sanitize_textarea_field($tool['description']),
                'input_schema' => $tool['parameters'] ?? $tool['input_schema'] ?? []
            ];
        }
        
        throw new Exception('Invalid tool definition for Anthropic format');
    }

    /**
     * Validate tool definition structure
     *
     * @param array $tool Tool definition to validate
     * @return bool True if valid
     */
    public static function validate_tool_definition($tool) {
        if (!is_array($tool)) {
            return false;
        }
        
        // Check for OpenAI format
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return isset($tool['function']['name']) && isset($tool['function']['description']);
        }
        
        // Check for direct format
        if (isset($tool['name']) && isset($tool['description'])) {
            return true;
        }
        
        return false;
    }

    /**
     * Get standard tool definition format
     * Returns a normalized format that can be converted to any provider
     *
     * @param array $tool Tool in any format
     * @return array Standardized tool definition
     */
    public static function to_standard_format($tool) {
        if (!self::validate_tool_definition($tool)) {
            throw new Exception('Invalid tool definition');
        }
        
        // If it's in OpenAI format, extract the function
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'],
                'parameters' => $tool['function']['parameters'] ?? []
            ];
        }
        
        // If it's already in standard format
        if (isset($tool['name']) && isset($tool['description'])) {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['parameters'] ?? $tool['input_schema'] ?? []
            ];
        }
        
        throw new Exception('Unable to normalize tool to standard format');
    }

    /**
     * Create tool schema for WordPress parameter validation
     *
     * @param array $parameters Tool parameters schema
     * @return array WordPress-safe parameter validation
     */
    public static function create_wordpress_schema($parameters) {
        if (!isset($parameters['properties']) || !is_array($parameters['properties'])) {
            return [];
        }
        
        $wp_schema = [];
        
        foreach ($parameters['properties'] as $param_name => $param_def) {
            $wp_schema[sanitize_text_field($param_name)] = [
                'type' => $param_def['type'] ?? 'string',
                'description' => sanitize_textarea_field($param_def['description'] ?? ''),
                'required' => in_array($param_name, $parameters['required'] ?? []),
                'default' => $param_def['default'] ?? null
            ];
        }
        
        return $wp_schema;
    }

    /**
     * Generate JSON schema for tool parameters
     *
     * @param array $wp_schema WordPress schema format
     * @return array JSON schema format
     */
    public static function to_json_schema($wp_schema) {
        $json_schema = [
            'type' => 'object',
            'properties' => [],
            'required' => []
        ];
        
        foreach ($wp_schema as $param_name => $param_def) {
            $json_schema['properties'][$param_name] = [
                'type' => $param_def['type'],
                'description' => $param_def['description']
            ];
            
            if ($param_def['required']) {
                $json_schema['required'][] = $param_name;
            }
            
            if (isset($param_def['default'])) {
                $json_schema['properties'][$param_name]['default'] = $param_def['default'];
            }
        }
        
        return $json_schema;
    }
}