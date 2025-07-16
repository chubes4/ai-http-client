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
        $state_with_timestamp = array(
            'data' => $state_data,
            'timestamp' => time()
        );
        
        // Store in memory for current request
        self::$states[$provider_name] = $state_with_timestamp;
        
        // Store in WordPress transient for persistence across requests
        $transient_key = 'ai_http_continuation_' . $provider_name . '_' . get_current_user_id();
        set_transient($transient_key, $state_with_timestamp, 300); // 5 minutes expiry
        
        error_log("AI HTTP Client: Stored continuation state for provider '{$provider_name}' (transient: {$transient_key})");
        error_log("AI HTTP Client: Stored state data: " . json_encode($state_data));
    }

    /**
     * Retrieve continuation state for a provider
     *
     * @param string $provider_name Provider name
     * @return array|null Provider-specific continuation data or null if not found
     */
    public static function get($provider_name) {
        // Check memory first
        if (isset(self::$states[$provider_name])) {
            return self::$states[$provider_name]['data'];
        }
        
        // Check WordPress transient
        $transient_key = 'ai_http_continuation_' . $provider_name . '_' . get_current_user_id();
        $state = get_transient($transient_key);
        
        if ($state && isset($state['data'])) {
            // Restore to memory for current request
            self::$states[$provider_name] = $state;
            error_log("AI HTTP Client: Retrieved continuation state for provider '{$provider_name}' from transient");
            return $state['data'];
        }
        
        error_log("AI HTTP Client: No continuation state found for provider '{$provider_name}' (transient: {$transient_key})");
        return null;
    }

    /**
     * Check if continuation state exists for a provider
     *
     * @param string $provider_name Provider name
     * @return bool True if state exists
     */
    public static function has($provider_name) {
        // Check memory first
        if (isset(self::$states[$provider_name])) {
            return true;
        }
        
        // Check WordPress transient
        $transient_key = 'ai_http_continuation_' . $provider_name . '_' . get_current_user_id();
        return get_transient($transient_key) !== false;
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