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
     * Trigger API error event with context data.
     *
     * @param string $provider Provider name (openai, anthropic, etc.)
     * @param string $endpoint API endpoint that failed
     * @param array $response Error response data
     * @param array $context Additional context (request data, etc.)
     */
    public static function trigger_api_error($provider, $endpoint, $response, $context = []) {
        $error_data = [
            'provider' => $provider,
            'endpoint' => $endpoint,
            'response' => $response,
            'context' => $context,
            'timestamp' => time()
        ];

        do_action('ai_api_error', $error_data);
    }

    /**
     * Trigger general library error event.
     *
     * @param string $component Component that errored (cache, models, etc.)
     * @param string $message Error message
     * @param array $context Additional context data
     */
    public static function trigger_library_error($component, $message, $context = []) {
        $error_data = [
            'component' => $component,
            'message' => $message,
            'context' => $context,
            'timestamp' => time()
        ];

        do_action('ai_library_error', $error_data);
    }
}