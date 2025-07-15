<?php
/**
 * AI HTTP Client - Provider Registry
 * 
 * Single Responsibility: Discover and register AI providers
 * Enables automatic provider discovery for extensibility
 *
 * @package AIHttpClient\Utils
 */

defined('ABSPATH') || exit;

class AI_HTTP_Provider_Registry {

    private static $instance = null;
    private $providers = array();
    private $provider_classes = array();

    /**
     * Singleton pattern for global provider registry
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->discover_providers();
    }

    /**
     * Discover available provider classes
     * Scans provider subdirectories for Provider.php files
     */
    private function discover_providers() {
        $providers_dir = AI_HTTP_CLIENT_PATH . '/src/Providers/';
        
        if (!is_dir($providers_dir)) {
            return;
        }

        // Look for provider subdirectories (e.g., OpenAI/, Anthropic/)
        $subdirs = glob($providers_dir . '*', GLOB_ONLYDIR);
        
        foreach ($subdirs as $subdir) {
            $provider_file = $subdir . '/Provider.php';
            if (file_exists($provider_file)) {
                $provider_name = strtolower(basename($subdir));
                $class_name = 'AI_HTTP_' . ucfirst($provider_name) . '_Provider';
                
                if ($this->is_valid_provider_class($class_name)) {
                    $this->provider_classes[$provider_name] = $class_name;
                }
            }
        }

        // Allow other plugins to register additional providers
        $this->provider_classes = apply_filters('ai_http_client_provider_classes', $this->provider_classes);
    }

    /**
     * Register a provider instance
     *
     * @param string $name Provider name
     * @param object $provider Provider instance
     */
    public function register_provider($name, $provider) {
        if (!$this->is_valid_provider($provider)) {
            throw new Exception("Invalid provider: {$name}");
        }
        
        $this->providers[$name] = $provider;
    }

    /**
     * Get provider instance by name
     *
     * @param string $name Provider name
     * @return object|null Provider instance or null if not found
     */
    public function get_provider($name) {
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        // Try to create from discovered classes
        if (isset($this->provider_classes[$name])) {
            $class_name = $this->provider_classes[$name];
            if (class_exists($class_name)) {
                $provider = new $class_name();
                $this->providers[$name] = $provider;
                return $provider;
            }
        }

        return null;
    }

    /**
     * Get all available provider names
     *
     * @return array Provider names
     */
    public function get_available_providers() {
        return array_unique(array_merge(
            array_keys($this->providers),
            array_keys($this->provider_classes)
        ));
    }

    /**
     * Check if a provider is available
     *
     * @param string $name Provider name
     * @return bool
     */
    public function has_provider($name) {
        return isset($this->providers[$name]) || isset($this->provider_classes[$name]);
    }

    /**
     * Get class name from file path
     *
     * @param string $file File path
     * @return string Class name
     */
    private function get_class_name_from_file($file) {
        $filename = basename($file, '.php');
        
        // Convert filename like "OpenAI.php" to "AI_HTTP_OpenAI_Provider"
        return 'AI_HTTP_' . $filename . '_Provider';
    }

    /**
     * Get provider name from class name
     *
     * @param string $class_name Class name
     * @return string Provider name
     */
    private function get_provider_name_from_class($class_name) {
        // Convert "AI_HTTP_OpenAI_Provider" to "openai"
        $name = str_replace(['AI_HTTP_', '_Provider'], '', $class_name);
        return strtolower($name);
    }

    /**
     * Check if class is a valid provider
     *
     * @param string $class_name Class name
     * @return bool
     */
    private function is_valid_provider_class($class_name) {
        return class_exists($class_name) && 
               is_subclass_of($class_name, 'AI_HTTP_Provider_Base');
    }

    /**
     * Check if instance is a valid provider
     *
     * @param object $provider Provider instance
     * @return bool
     */
    private function is_valid_provider($provider) {
        return is_object($provider) && 
               is_subclass_of($provider, 'AI_HTTP_Provider_Base');
    }

    /**
     * Clear registry (useful for testing)
     */
    public function clear() {
        $this->providers = array();
        $this->provider_classes = array();
    }
}