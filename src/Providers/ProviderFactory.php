<?php
/**
 * AI HTTP Client - Provider Factory
 * 
 * Single Responsibility: Create and configure provider instances
 * Uses dependency injection for configuration
 *
 * @package AIHttpClient\Utils
 */

defined('ABSPATH') || exit;

class AI_HTTP_Provider_Factory {

    private $registry;
    private $default_config;

    /**
     * Constructor with dependency injection
     *
     * @param AI_HTTP_Provider_Registry $registry Provider registry
     * @param array $default_config Default configuration
     */
    public function __construct($registry = null, $default_config = array()) {
        $this->registry = $registry ?: AI_HTTP_Provider_Registry::get_instance();
        $this->default_config = $default_config;
    }

    /**
     * Create a configured provider instance
     *
     * @param string $provider_name Provider name
     * @param array $config Provider-specific configuration
     * @return object|null Configured provider instance
     */
    public function create_provider($provider_name, $config = array()) {
        if (!$this->registry->has_provider($provider_name)) {
            return null;
        }

        $provider = $this->registry->get_provider($provider_name);
        
        if (!$provider) {
            return null;
        }

        // Merge configurations: default + provider-specific + passed config
        $final_config = array_merge(
            $this->default_config,
            $this->get_provider_config($provider_name),
            $config
        );

        // Configure the provider
        $provider->set_configuration($final_config);

        return $provider;
    }

    /**
     * Create providers for fallback chain
     *
     * @param array $provider_names Array of provider names in fallback order
     * @param array $config Configuration array
     * @return array Array of configured provider instances
     */
    public function create_fallback_chain($provider_names, $config = array()) {
        $providers = array();

        foreach ($provider_names as $provider_name) {
            $provider = $this->create_provider($provider_name, $config);
            if ($provider && $provider->is_configured()) {
                $providers[$provider_name] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Get all available and configured providers
     *
     * @param array $config Configuration array
     * @return array Array of configured provider instances
     */
    public function create_all_configured_providers($config = array()) {
        $providers = array();
        $available_providers = $this->registry->get_available_providers();

        foreach ($available_providers as $provider_name) {
            $provider = $this->create_provider($provider_name, $config);
            if ($provider && $provider->is_configured()) {
                $providers[$provider_name] = $provider;
            }
        }

        return $providers;
    }

    /**
     * Get provider-specific configuration
     * Allows for per-provider settings from WordPress options or filters
     *
     * @param string $provider_name Provider name
     * @return array Provider configuration
     */
    private function get_provider_config($provider_name) {
        // Allow plugins to filter provider configurations
        $config = apply_filters("ai_http_client_{$provider_name}_config", array());
        $config = apply_filters('ai_http_client_provider_config', $config, $provider_name);

        return is_array($config) ? $config : array();
    }

    /**
     * Set default configuration for all providers
     *
     * @param array $config Default configuration
     */
    public function set_default_config($config) {
        $this->default_config = is_array($config) ? $config : array();
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration
     */
    public function get_default_config() {
        return $this->default_config;
    }

    /**
     * Check if a provider can be created and configured
     *
     * @param string $provider_name Provider name
     * @param array $config Configuration to test
     * @return bool True if provider can be created and configured
     */
    public function can_create_provider($provider_name, $config = array()) {
        if (!$this->registry->has_provider($provider_name)) {
            return false;
        }

        $provider = $this->create_provider($provider_name, $config);
        return $provider && $provider->is_configured();
    }
}