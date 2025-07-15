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
     * Normalize tool/function definitions for OpenAI
     *
     * @param array $tools Tools array
     * @return array Normalized tools
     */
    private function normalize_tools($tools) {
        $normalized = array();

        foreach ($tools as $tool) {
            if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
                $normalized[] = array(
                    'type' => 'function',
                    'function' => array(
                        'name' => sanitize_text_field($tool['function']['name']),
                        'description' => sanitize_textarea_field($tool['function']['description']),
                        'parameters' => $tool['function']['parameters'] // JSON schema - minimal sanitization
                    )
                );
            }
        }

        return $normalized;
    }
}