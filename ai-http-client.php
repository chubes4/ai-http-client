<?php
/**
 * AI HTTP Client Library
 * 
 * A professional WordPress library for unified AI provider communication.
 * Supports OpenAI, Anthropic, Google Gemini, Grok, and OpenRouter with
 * standardized request/response formats and automatic fallback handling.
 *
 * Modeled after Action Scheduler for enterprise WordPress development.
 *
 * @package AIHttpClient
 * @version 1.0.0
 * @author Chris Huber <https://chubes.net>
 * @link https://github.com/chubes/ai-http-client
 */

defined('ABSPATH') || exit;

/**
 * AI HTTP Client version and compatibility checking
 * Prevents conflicts when multiple plugins include different versions
 */
if (!defined('AI_HTTP_CLIENT_VERSION')) {
    define('AI_HTTP_CLIENT_VERSION', '1.0.0');
}

// Check if we should load this version
if (!function_exists('ai_http_client_version_check')) {
    function ai_http_client_version_check() {
        global $ai_http_client_version;
        
        if (empty($ai_http_client_version) || version_compare(AI_HTTP_CLIENT_VERSION, $ai_http_client_version, '>')) {
            $ai_http_client_version = AI_HTTP_CLIENT_VERSION;
            return true;
        }
        
        return false;
    }
}

// Only load if this is the highest version
if (!ai_http_client_version_check()) {
    return;
}

// Prevent multiple inclusions of the same version
if (class_exists('AI_HTTP_Client')) {
    return;
}

// Define component constants
if (!defined('AI_HTTP_CLIENT_PATH')) {
    define('AI_HTTP_CLIENT_PATH', __DIR__);
}

if (!defined('AI_HTTP_CLIENT_URL')) {
    define('AI_HTTP_CLIENT_URL', plugin_dir_url(__FILE__));
}

/**
 * Initialize AI HTTP Client library
 * Loads all modular components in correct dependency order
 */
if (!function_exists('ai_http_client_init')) {
    function ai_http_client_init() {
        // Load in dependency order
        
        // 1. Core base classes
        require_once AI_HTTP_CLIENT_PATH . '/src/class-provider-base.php';
        
        // 2. Provider management utilities
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/ProviderRegistry.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/ProviderFactory.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/NormalizerFactory.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/ModelFetcher.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/GenericRequestNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/GenericResponseNormalizer.php';
        
        // 2.5. Streaming utilities
        require_once AI_HTTP_CLIENT_PATH . '/src/Streaming/StreamingClient.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Streaming/SSEParser.php';
        
        // 3. Provider implementations (organized by provider)
        // OpenAI Provider
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenAI/Provider.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenAI/RequestNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenAI/ResponseNormalizer.php';
        
        // Anthropic Provider
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Anthropic/Provider.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Anthropic/RequestNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Anthropic/ResponseNormalizer.php';
        
        // Additional providers can be added here or auto-discovered
        
        // 4. Main orchestrator client
        require_once AI_HTTP_CLIENT_PATH . '/src/class-client.php';
        
        // 5. Hook into WordPress for any setup needed
        if (function_exists('add_action')) {
            add_action('init', 'ai_http_client_wordpress_init', 1);
        }
    }
    
    function ai_http_client_wordpress_init() {
        // WordPress-specific initialization
        if (function_exists('do_action')) {
            do_action('ai_http_client_loaded');
        }
    }
}

// Initialize the library
ai_http_client_init();