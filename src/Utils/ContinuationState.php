<?php
/**
 * AI HTTP Client - Continuation State Management
 * 
 * Single Responsibility: Store and retrieve continuation state data
 * Provider-agnostic state management for conversation continuations
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Continuation_State {

    /**
     * @var array Provider-specific continuation states
     */
    private static $states = array();

    /**
     * Store continuation state for a provider
     *
     * @param string $provider_name Provider name (openai, anthropic, etc.)
     * @param array $state_data Provider-specific continuation data
     */
    public static function store($provider_name, $state_data) {
        self::$states[$provider_name] = array(
            'data' => $state_data,
            'timestamp' => time()
        );
        
        error_log("AI HTTP Client: Stored continuation state for provider '{$provider_name}'");
    }

    /**
     * Retrieve continuation state for a provider
     *
     * @param string $provider_name Provider name
     * @return array|null Provider-specific continuation data or null if not found
     */
    public static function get($provider_name) {
        if (isset(self::$states[$provider_name])) {
            return self::$states[$provider_name]['data'];
        }
        
        error_log("AI HTTP Client: No continuation state found for provider '{$provider_name}'");
        return null;
    }

    /**
     * Check if continuation state exists for a provider
     *
     * @param string $provider_name Provider name
     * @return bool True if state exists
     */
    public static function has($provider_name) {
        return isset(self::$states[$provider_name]);
    }

    /**
     * Clear continuation state for a provider
     *
     * @param string $provider_name Provider name
     */
    public static function clear($provider_name) {
        if (isset(self::$states[$provider_name])) {
            unset(self::$states[$provider_name]);
            error_log("AI HTTP Client: Cleared continuation state for provider '{$provider_name}'");
        }
    }

    /**
     * Clear all continuation states
     */
    public static function clear_all() {
        self::$states = array();
        error_log("AI HTTP Client: Cleared all continuation states");
    }

    /**
     * Get all stored states (for debugging)
     *
     * @return array All stored continuation states
     */
    public static function get_all() {
        return self::$states;
    }

    /**
     * Clean up expired states (older than 1 hour)
     */
    public static function cleanup_expired() {
        $expiry_time = time() - 3600; // 1 hour
        $cleaned = 0;
        
        foreach (self::$states as $provider => $state) {
            if ($state['timestamp'] < $expiry_time) {
                unset(self::$states[$provider]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            error_log("AI HTTP Client: Cleaned up {$cleaned} expired continuation states");
        }
    }
}