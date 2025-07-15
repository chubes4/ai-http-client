<?php
/**
 * AI HTTP Client - OpenAI Request Normalizer
 * 
 * Single Responsibility: Handle ONLY OpenAI request normalization
 * Follows SRP by focusing on one provider only
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Openai_Request_Normalizer {

    /**
     * Normalize request for OpenAI API
     *
     * @param array $standard_request Standardized request
     * @return array OpenAI-formatted request
     */
    public function normalize($standard_request) {
        // OpenAI uses our standard format, just validate and set defaults
        $normalized = $standard_request;

        // Set default model if not provided
        if (!isset($normalized['model'])) {
            $normalized['model'] = 'gpt-3.5-turbo';
        }

        // Validate and constrain parameters
        if (isset($normalized['temperature'])) {
            $normalized['temperature'] = max(0, min(2, floatval($normalized['temperature'])));
        }

        if (isset($normalized['max_tokens'])) {
            $normalized['max_tokens'] = max(1, intval($normalized['max_tokens']));
        }

        if (isset($normalized['top_p'])) {
            $normalized['top_p'] = max(0, min(1, floatval($normalized['top_p'])));
        }

        // Handle function calling tools
        if (isset($normalized['tools']) && is_array($normalized['tools'])) {
            $normalized['tools'] = $this->normalize_tools($normalized['tools']);
        }

        return $normalized;
    }

    /**
     * Normalize tools for OpenAI function calling format
     *
     * @param array $tools Array of tool definitions
     * @return array OpenAI-formatted tools
     */
    private function normalize_tools($tools) {
        $normalized = array();

        foreach ($tools as $tool) {
            try {
                $normalized[] = $this->normalize_single_tool($tool);
            } catch (Exception $e) {
                error_log('OpenAI tool normalization error: ' . $e->getMessage());
            }
        }

        return $normalized;
    }

    /**
     * Normalize a single tool to OpenAI format
     *
     * @param array $tool Tool definition
     * @return array OpenAI-formatted tool
     */
    private function normalize_single_tool($tool) {
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

}