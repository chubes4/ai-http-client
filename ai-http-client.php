<?php
/**
 * AI HTTP Client Library
 * 
 * A professional WordPress library for unified AI provider communication.
 * Supports OpenAI, Anthropic, Google Gemini, Grok, and OpenRouter with
 * standardized request/response formats.
 *
 * @package AIHttpClient
 * @version 2.0.0
 * @author Chris Huber <https://chubes.net>
 * @link https://github.com/chubes4/ai-http-client
 */

defined('ABSPATH') || exit;

if (!defined('AI_HTTP_CLIENT_PATH')) {
    define('AI_HTTP_CLIENT_PATH', __DIR__);
}

if (!defined('AI_HTTP_CLIENT_URL')) {
    define('AI_HTTP_CLIENT_URL', plugin_dir_url(__FILE__));
}

/**
 * Initialize AI HTTP Client library with WordPress-native loading
 */
function ai_http_client_init() {
    // Load providers and filters
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/openai.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/gemini.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/anthropic.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/grok.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Providers/openrouter.php';

    require_once AI_HTTP_CLIENT_PATH . '/src/Actions/Cache.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Actions/Error.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Actions/Migration.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Filters/Requests.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Filters/Models.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Filters/Tools.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Filters/Admin.php';
    require_once AI_HTTP_CLIENT_PATH . '/src/Filters/RestApi.php';

    if (function_exists('add_action')) {
        add_action('init', 'ai_http_client_wordpress_init', 1);
    }
}

function ai_http_client_wordpress_init() {
    AIHttpCache::register();
    AIHttpError::register();

    if (function_exists('do_action')) {
        do_action('chubes_ai_http_client_loaded');
    }
}

add_action('plugins_loaded', 'ai_http_client_init', 1);