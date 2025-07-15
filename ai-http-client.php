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
        
        // 2.5. Shared utilities
        require_once AI_HTTP_CLIENT_PATH . '/src/Utils/StreamingClient.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Utils/FileUploadClient.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Utils/ToolExecutor.php';
        
        
        // 3. Provider implementations (organized by provider)
        // OpenAI Provider
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenAI/StreamingModule.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenAI/ModelFetcher.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenAI/FunctionCalling.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenAI/Provider.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenAI/RequestNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenAI/ResponseNormalizer.php';
        
        // Anthropic Provider
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Anthropic/StreamingModule.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Anthropic/ModelFetcher.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Anthropic/FunctionCalling.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Anthropic/Provider.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Anthropic/RequestNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Anthropic/ResponseNormalizer.php';
        
        // Google Gemini Provider
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Gemini/StreamingModule.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Gemini/ModelFetcher.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Gemini/FunctionCalling.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Gemini/Provider.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Gemini/RequestNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Gemini/ResponseNormalizer.php';
        
        // Grok/X.AI Provider
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Grok/StreamingModule.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Grok/ModelFetcher.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Grok/FunctionCalling.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Grok/Provider.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Grok/RequestNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/Grok/ResponseNormalizer.php';
        
        // OpenRouter Provider
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenRouter/StreamingModule.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenRouter/ModelFetcher.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenRouter/FunctionCalling.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenRouter/Provider.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenRouter/RequestNormalizer.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Providers/OpenRouter/ResponseNormalizer.php';
        
        // Additional providers can be added here or auto-discovered
        
        // 4. Main orchestrator client
        require_once AI_HTTP_CLIENT_PATH . '/src/class-client.php';
        
        // 4.5. WordPress management components
        require_once AI_HTTP_CLIENT_PATH . '/src/OptionsManager.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/PromptManager.php';
        
        // 4.6. UI Components system
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/ComponentInterface.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/ComponentRegistry.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/Core/ProviderSelector.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/Core/ApiKeyInput.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/Core/ModelSelector.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/Extended/TemperatureSlider.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/Extended/SystemPromptField.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/Extended/TestConnection.php';
        require_once AI_HTTP_CLIENT_PATH . '/src/Components/ProviderManagerComponent.php';
        
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