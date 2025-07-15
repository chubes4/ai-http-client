<?php
/**
 * AI HTTP Client - Normalizer Factory
 * 
 * Single Responsibility: Create provider-specific normalizers
 * Follows SRP by delegating to specialized normalizer classes
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Normalizer_Factory {

    private static $request_normalizers = array();
    private static $response_normalizers = array();

    /**
     * Get request normalizer for specific provider
     *
     * @param string $provider_name Provider name
     * @return object|null Provider-specific request normalizer
     */
    public static function get_request_normalizer($provider_name) {
        if (!isset(self::$request_normalizers[$provider_name])) {
            self::$request_normalizers[$provider_name] = self::create_request_normalizer($provider_name);
        }

        return self::$request_normalizers[$provider_name];
    }

    /**
     * Get response normalizer for specific provider
     *
     * @param string $provider_name Provider name
     * @return object|null Provider-specific response normalizer
     */
    public static function get_response_normalizer($provider_name) {
        if (!isset(self::$response_normalizers[$provider_name])) {
            self::$response_normalizers[$provider_name] = self::create_response_normalizer($provider_name);
        }

        return self::$response_normalizers[$provider_name];
    }

    /**
     * Create request normalizer for provider
     *
     * @param string $provider_name Provider name
     * @return object|null Request normalizer instance
     */
    private static function create_request_normalizer($provider_name) {
        $class_name = 'AI_HTTP_' . ucfirst($provider_name) . '_Request_Normalizer';
        
        if (class_exists($class_name)) {
            return new $class_name();
        }

        // Fallback to generic normalizer
        return new AI_HTTP_Generic_Request_Normalizer();
    }

    /**
     * Create response normalizer for provider
     *
     * @param string $provider_name Provider name
     * @return object|null Response normalizer instance
     */
    private static function create_response_normalizer($provider_name) {
        $class_name = 'AI_HTTP_' . ucfirst($provider_name) . '_Response_Normalizer';
        
        if (class_exists($class_name)) {
            return new $class_name();
        }

        // Fallback to generic normalizer
        return new AI_HTTP_Generic_Response_Normalizer();
    }

    /**
     * Register custom normalizer for provider
     *
     * @param string $provider_name Provider name
     * @param object $request_normalizer Request normalizer instance
     * @param object $response_normalizer Response normalizer instance
     */
    public static function register_normalizers($provider_name, $request_normalizer, $response_normalizer) {
        self::$request_normalizers[$provider_name] = $request_normalizer;
        self::$response_normalizers[$provider_name] = $response_normalizer;
    }

    /**
     * Clear normalizer cache
     */
    public static function clear_cache() {
        self::$request_normalizers = array();
        self::$response_normalizers = array();
    }
}