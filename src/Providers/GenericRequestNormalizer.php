<?php
/**
 * AI HTTP Client - Generic Request Normalizer
 * 
 * Single Responsibility: Handle generic/unknown provider request normalization
 * Fallback for providers without specific normalizers
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Generic_Request_Normalizer {

    /**
     * Normalize request for generic/unknown provider
     *
     * @param array $standard_request Standardized request
     * @return array Normalized request (passes through with basic validation)
     */
    public function normalize($standard_request) {
        // Validate standardized input
        $this->validate_request($standard_request);

        // Basic sanitization
        $normalized = $this->sanitize_request($standard_request);

        // Apply generic defaults
        if (!isset($normalized['model'])) {
            $normalized['model'] = 'default';
        }

        if (!isset($normalized['max_tokens'])) {
            $normalized['max_tokens'] = 1000;
        }

        return $normalized;
    }

    /**
     * Validate request format
     *
     * @param array $request Request to validate
     * @throws Exception If invalid
     */
    private function validate_request($request) {
        if (!is_array($request)) {
            throw new Exception('Request must be an array');
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            throw new Exception('Request must include messages array');
        }

        if (empty($request['messages'])) {
            throw new Exception('Messages array cannot be empty');
        }
    }

    /**
     * Sanitize request data
     *
     * @param array $request Request to sanitize
     * @return array Sanitized request
     */
    private function sanitize_request($request) {
        // Sanitize messages
        if (isset($request['messages'])) {
            foreach ($request['messages'] as &$message) {
                if (isset($message['role'])) {
                    $message['role'] = sanitize_text_field($message['role']);
                }
                if (isset($message['content'])) {
                    $message['content'] = sanitize_textarea_field($message['content']);
                }
            }
        }

        // Sanitize other common fields
        if (isset($request['model'])) {
            $request['model'] = sanitize_text_field($request['model']);
        }

        if (isset($request['max_tokens'])) {
            $request['max_tokens'] = max(1, intval($request['max_tokens']));
        }

        if (isset($request['temperature'])) {
            $request['temperature'] = max(0, min(2, floatval($request['temperature'])));
        }

        return $request;
    }
}