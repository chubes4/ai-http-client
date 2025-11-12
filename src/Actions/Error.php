<?php
/**
 * AI HTTP Client - Error Management
 *
 * Centralized error handling using WordPress action hooks.
 * Provides developer-friendly error events for integrations.
 *
 * @package AIHttpClient\Actions
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AIHttpError {

    /**
     * Register error handling action hooks.
     */
    public static function register() {
        // Error actions are called directly by providers/components
        // No hooks to register here - this class provides static methods
    }

    /**
     * Trigger unified error event for all library errors.
     *
     * @param string $component Component that errored (Requests, RestApi, OpenAI, etc.)
     * @param string $message Error message
     * @param array $context Additional context data (provider, endpoint, http_code, etc.)
     */
    public static function trigger_error($component, $message, $context = []) {
        $error_data = [
            'component' => $component,
            'message' => $message,
            'context' => $context,
            'timestamp' => time()
        ];

        do_action('ai_library_error', $error_data);
    }
}