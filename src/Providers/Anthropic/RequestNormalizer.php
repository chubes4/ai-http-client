<?php
/**
 * AI HTTP Client - Anthropic Request Normalizer
 * 
 * Single Responsibility: Handle ONLY Anthropic request normalization
 * Converts standard format to Anthropic's specific requirements
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Anthropic_Request_Normalizer {

    /**
     * Normalize request for Anthropic API
     *
     * @param array $standard_request Standardized request
     * @return array Anthropic-formatted request
     */
    public function normalize($standard_request) {
        $normalized = $standard_request;

        // Set default model if not provided
        if (!isset($normalized['model'])) {
            $normalized['model'] = 'claude-3-haiku-20240307';
        }

        // Anthropic requires max_tokens
        if (!isset($normalized['max_tokens'])) {
            $normalized['max_tokens'] = 1000;
        }

        // Validate and constrain parameters for Anthropic (0.0 to 1.0)
        if (isset($normalized['temperature'])) {
            $normalized['temperature'] = max(0, min(1, floatval($normalized['temperature'])));
        }

        if (isset($normalized['max_tokens'])) {
            $normalized['max_tokens'] = max(1, min(4096, intval($normalized['max_tokens'])));
        }

        if (isset($normalized['top_p'])) {
            $normalized['top_p'] = max(0, min(1, floatval($normalized['top_p'])));
        }

        // Extract system messages to separate system field
        $normalized = $this->extract_system_message($normalized);

        // Handle tools for function calling
        if (isset($normalized['tools']) && is_array($normalized['tools'])) {
            $normalized['tools'] = AI_HTTP_Tool_Normalizer::normalize_for_provider($normalized['tools'], 'anthropic');
        }

        return $normalized;
    }

    /**
     * Extract system message from messages array to system field
     * Anthropic uses a separate system field instead of system role in messages
     *
     * @param array $request Request data
     * @return array Request with system message extracted
     */
    private function extract_system_message($request) {
        if (!isset($request['messages']) || !is_array($request['messages'])) {
            return $request;
        }

        $system_content = '';
        $filtered_messages = array();

        foreach ($request['messages'] as $message) {
            if (isset($message['role']) && $message['role'] === 'system') {
                $system_content .= $message['content'] . "\n";
            } else {
                $filtered_messages[] = $message;
            }
        }

        if (!empty($system_content)) {
            $request['system'] = trim($system_content);
        }

        $request['messages'] = $filtered_messages;

        return $request;
    }

}