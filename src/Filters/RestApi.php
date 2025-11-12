<?php
/**
 * AI HTTP Client - REST API Filters
 *
 * REST API endpoints for AI provider management, replacing AJAX functionality.
 * All REST API endpoints organized in this file with proper WordPress REST API standards.
 *
 * @package AIHttpClient\Filters
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

/**
 * Register REST API routes for AI HTTP Client
 */
function ai_http_register_rest_routes() {
    register_rest_route('ai-http-client/v1', '/api-keys/(?P<provider>[a-zA-Z0-9_-]+)', [
        [
            'methods' => 'GET',
            'callback' => 'ai_http_rest_get_api_key',
            'permission_callback' => 'ai_http_rest_permission_check',
            'args' => [
                'provider' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ]
            ]
        ],
        [
            'methods' => 'POST',
            'callback' => 'ai_http_rest_save_api_key',
            'permission_callback' => 'ai_http_rest_permission_check',
            'args' => [
                'provider' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'api_key' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ]
            ]
        ]
    ]);

    register_rest_route('ai-http-client/v1', '/models/(?P<provider>[a-zA-Z0-9_-]+)', [
        'methods' => 'GET',
        'callback' => 'ai_http_rest_get_models',
        'permission_callback' => 'ai_http_rest_permission_check',
        'args' => [
            'provider' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_string($param) && !empty($param);
                }
            ]
        ]
    ]);

    register_rest_route('ai-http-client/v1', '/providers', [
        'methods' => 'GET',
        'callback' => 'ai_http_rest_get_providers',
        'permission_callback' => 'ai_http_rest_permission_check'
    ]);
}
add_action('rest_api_init', 'ai_http_register_rest_routes');

/**
 * Permission check for REST API endpoints
 *
 * @return bool|WP_Error True if user has permission, WP_Error otherwise
 */
function ai_http_rest_permission_check() {
    if (!current_user_can('manage_options')) {
        return new WP_Error(
            'rest_forbidden',
            __('Insufficient permissions', 'ai-http-client'),
            ['status' => 403]
        );
    }

    // Verify nonce if provided
    $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error(
            'rest_forbidden',
            __('Security verification failed', 'ai-http-client'),
            ['status' => 403]
        );
    }

    return true;
}

/**
 * Get API key for a provider via REST API
 *
 * @param WP_REST_Request $request REST request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function ai_http_rest_get_api_key($request) {
    $provider = sanitize_text_field($request->get_param('provider'));

    if (empty($provider)) {
        return new WP_Error(
            'rest_invalid_param',
            __('Provider is required', 'ai-http-client'),
            ['status' => 400]
        );
    }

    try {
        // Get API key using ai_provider_api_keys filter
        $all_keys = apply_filters('ai_provider_api_keys', null);
        $api_key = $all_keys[$provider] ?? '';

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'provider' => $provider,
                'api_key' => $api_key
            ]
        ], 200);

    } catch (Exception $e) {
        AIHttpError::trigger_error('RestApi', 'Failed to get API key: ' . $e->getMessage(), [
            'provider' => $provider,
            'exception' => $e
        ]);

        return new WP_Error(
            'rest_internal_error',
            __('Failed to retrieve API key', 'ai-http-client'),
            ['status' => 500]
        );
    }
}

/**
 * Save API key for a provider via REST API
 *
 * @param WP_REST_Request $request REST request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function ai_http_rest_save_api_key($request) {
    $provider = sanitize_text_field($request->get_param('provider'));
    $api_key = sanitize_text_field($request->get_param('api_key'));

    if (empty($provider)) {
        return new WP_Error(
            'rest_invalid_param',
            __('Provider is required', 'ai-http-client'),
            ['status' => 400]
        );
    }

    try {
        // Save API key using ai_provider_api_keys filter
        $all_keys = apply_filters('ai_provider_api_keys', null);
        $all_keys[$provider] = $api_key;
        apply_filters('ai_provider_api_keys', $all_keys);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('API key saved successfully', 'ai-http-client'),
            'data' => [
                'provider' => $provider
            ]
        ], 200);

    } catch (Exception $e) {
        AIHttpError::trigger_error('RestApi', 'Failed to save API key: ' . $e->getMessage(), [
            'provider' => $provider,
            'exception' => $e
        ]);

        return new WP_Error(
            'rest_internal_error',
            __('Failed to save API key', 'ai-http-client'),
            ['status' => 500]
        );
    }
}

/**
 * Get available models for a provider via REST API
 *
 * @param WP_REST_Request $request REST request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function ai_http_rest_get_models($request) {
    $provider = sanitize_text_field($request->get_param('provider'));

    if (empty($provider)) {
        return new WP_Error(
            'rest_invalid_param',
            __('Provider is required', 'ai-http-client'),
            ['status' => 400]
        );
    }

    try {
        // Get API key using ai_provider_api_keys filter
        $all_keys = apply_filters('ai_provider_api_keys', null);
        $api_key = $all_keys[$provider] ?? '';

        // Get models using ai_models filter
        $models = apply_filters('ai_models', $provider, ['api_key' => $api_key]);

        return new WP_REST_Response([
            'success' => true,
            'data' => $models
        ], 200);

    } catch (Exception $e) {
        AIHttpError::trigger_error('RestApi', 'Failed to get models: ' . $e->getMessage(), [
            'provider' => $provider,
            'exception' => $e
        ]);

        return new WP_Error(
            'rest_internal_error',
            __('Failed to retrieve models', 'ai-http-client'),
            ['status' => 500]
        );
    }
}

/**
 * Get list of available providers via REST API
 *
 * @param WP_REST_Request $request REST request object
 * @return WP_REST_Response|WP_Error Response object or error
 */
function ai_http_rest_get_providers($request) {
    try {
        // Get providers using ai_providers filter
        $providers = apply_filters('ai_providers', []);

        return new WP_REST_Response([
            'success' => true,
            'data' => $providers
        ], 200);

    } catch (Exception $e) {
        AIHttpError::trigger_error('RestApi', 'Failed to get providers: ' . $e->getMessage(), [
            'exception' => $e
        ]);

        return new WP_Error(
            'rest_internal_error',
            __('Failed to retrieve providers', 'ai-http-client'),
            ['status' => 500]
        );
    }
}