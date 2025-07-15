<?php
/**
 * AI HTTP Client - Prompt Manager
 * 
 * Single Responsibility: Provide generic prompt building utilities
 * Extension points for plugins to customize prompt construction
 *
 * @package AIHttpClient\Components
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Prompt_Manager {
    
    /**
     * Build system prompt with filters
     *
     * @param string $base_prompt Base system prompt
     * @param array $context Context data
     * @return string Enhanced system prompt
     */
    public static function build_system_prompt($base_prompt, $context = []) {
        $prompt = apply_filters('ai_http_client_system_prompt', $base_prompt, $context);
        return apply_filters('ai_http_client_inject_context', $prompt, $context);
    }
    
    /**
     * Build user prompt with filters
     *
     * @param string $base_prompt Base user prompt
     * @param array $context Context data
     * @return string Enhanced user prompt
     */
    public static function build_user_prompt($base_prompt, $context = []) {
        $prompt = apply_filters('ai_http_client_user_prompt', $base_prompt, $context);
        return apply_filters('ai_http_client_replace_variables', $prompt, $context);
    }
    
    /**
     * Build message history array
     *
     * @param string $system_prompt System prompt
     * @param string $user_prompt User prompt
     * @param array $history Previous message history
     * @return array Complete message array
     */
    public static function build_messages($system_prompt, $user_prompt, $history = []) {
        $messages = [];
        
        // Add system message if provided
        if (!empty($system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }
        
        // Add history messages
        if (!empty($history)) {
            $messages = array_merge($messages, $history);
        }
        
        // Add user message
        if (!empty($user_prompt)) {
            $messages[] = ['role' => 'user', 'content' => $user_prompt];
        }
        
        return apply_filters('ai_http_client_build_messages', $messages);
    }
    
    /**
     * Generic variable replacement
     *
     * @param string $text Text with variables
     * @param array $variables Variable replacements
     * @return string Text with variables replaced
     */
    public static function replace_variables($text, $variables = []) {
        if (empty($variables)) {
            return $text;
        }
        
        // Allow plugins to modify variables
        $variables = apply_filters('ai_http_client_prompt_variables', $variables);
        
        // Perform replacement
        return str_replace(
            array_keys($variables),
            array_values($variables),
            $text
        );
    }
    
    /**
     * Generic context injection
     *
     * @param string $prompt Original prompt
     * @param array $context Context data
     * @return string Prompt with context injected
     */
    public static function inject_context($prompt, $context = []) {
        if (empty($context)) {
            return $prompt;
        }
        
        // Allow plugins to format context
        $context_string = apply_filters('ai_http_client_format_context', '', $context);
        
        if (!empty($context_string)) {
            $prompt .= "\n\n" . $context_string;
        }
        
        return $prompt;
    }
    
    /**
     * Add response formatting instructions
     *
     * @param string $prompt Original prompt
     * @param array $format_config Format configuration
     * @return string Prompt with format instructions
     */
    public static function add_response_formatting($prompt, $format_config = []) {
        if (empty($format_config)) {
            return $prompt;
        }
        
        $format_instructions = apply_filters('ai_http_client_format_instructions', '', $format_config);
        
        if (!empty($format_instructions)) {
            $prompt .= "\n\n" . $format_instructions;
        }
        
        return $prompt;
    }
    
    /**
     * Build complete prompt configuration
     *
     * @param array $config Prompt configuration
     * @return array Complete prompt setup
     */
    public static function build_prompt_config($config = []) {
        $defaults = [
            'system_prompt' => '',
            'user_prompt' => '',
            'context' => [],
            'variables' => [],
            'format_config' => [],
            'history' => []
        ];
        
        $config = array_merge($defaults, $config);
        
        // Build system prompt
        if (!empty($config['system_prompt'])) {
            $config['system_prompt'] = self::build_system_prompt(
                $config['system_prompt'],
                $config['context']
            );
        }
        
        // Build user prompt
        if (!empty($config['user_prompt'])) {
            $config['user_prompt'] = self::build_user_prompt(
                $config['user_prompt'],
                $config['context']
            );
            
            // Apply variable replacement
            $config['user_prompt'] = self::replace_variables(
                $config['user_prompt'],
                $config['variables']
            );
            
            // Add response formatting
            $config['user_prompt'] = self::add_response_formatting(
                $config['user_prompt'],
                $config['format_config']
            );
        }
        
        // Build final messages
        $config['messages'] = self::build_messages(
            $config['system_prompt'],
            $config['user_prompt'],
            $config['history']
        );
        
        return $config;
    }
    
    /**
     * Default context formatter
     *
     * @param array $context Context data
     * @return string Formatted context string
     */
    public static function format_context($context) {
        if (empty($context)) {
            return '';
        }
        
        $formatted = "Context:\n";
        
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            
            $formatted .= "- {$key}: {$value}\n";
        }
        
        return $formatted;
    }
    
    /**
     * Default variable formatter
     *
     * @param array $variables Variables array
     * @return array Formatted variables with brackets
     */
    public static function format_variables($variables) {
        $formatted = [];
        
        foreach ($variables as $key => $value) {
            // Add brackets if not already present
            $formatted_key = strpos($key, '{') === false ? "{{$key}}" : $key;
            $formatted[$formatted_key] = $value;
        }
        
        return $formatted;
    }
}