<?php
/**
 * AI HTTP Client - Unified Model Fetcher
 * 
 * Single Responsibility: Fetch ALL available models from ANY provider
 * Pure API fetching - NO defaults, NO fallbacks, NO filtering, NO sorting
 * Let it error out if it fails - that's the intention
 *
 * @package AIHttpClient\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Unified_Model_Fetcher {

    /**
     * Fetch models for any provider
     * Pure API fetching - will throw exception if it fails
     *
     * @param string $provider_name Provider name (openai, anthropic, gemini, etc.)
     * @param array $provider_config Provider configuration (API keys, etc.)
     * @return array Raw models response from API
     * @throws Exception If provider not supported or API fails
     */
    public static function fetch_models($provider_name, $provider_config = array()) {
        switch (strtolower($provider_name)) {
            case 'openai':
                return self::fetch_openai_models($provider_config);
            
            case 'anthropic':
                return self::fetch_anthropic_models($provider_config);
            
            case 'gemini':
                return self::fetch_gemini_models($provider_config);
            
            case 'grok':
                return self::fetch_grok_models($provider_config);
            
            case 'openrouter':
                return self::fetch_openrouter_models($provider_config);
            
            default:
                throw new Exception("Unsupported provider for model fetching: {$provider_name}");
        }
    }

    /**
     * Fetch OpenAI models via API
     * Returns raw API response - NO filtering, NO fallbacks
     *
     * @param array $config OpenAI configuration
     * @return array Raw API response
     * @throws Exception If API key missing or API fails
     */
    private static function fetch_openai_models($config) {
        if (empty($config['api_key'])) {
            throw new Exception('OpenAI API key is required to fetch models');
        }

        // Use simple OpenAI provider to get raw models
        if (!class_exists('AI_HTTP_OpenAI_Provider')) {
            require_once dirname(__DIR__) . '/Providers/openai.php';
        }
        
        $provider = new AI_HTTP_OpenAI_Provider($config);
        return $provider->get_raw_models();
    }

    /**
     * Fetch Anthropic models
     * Anthropic doesn't have a models API endpoint - will throw exception
     *
     * @param array $config Anthropic configuration
     * @return array Never returns - always throws
     * @throws Exception Always throws since Anthropic has no models API
     */
    private static function fetch_anthropic_models($config) {
        throw new Exception('Anthropic does not provide a models API endpoint');
    }

    /**
     * Fetch Gemini models via API
     * Returns raw API response - NO filtering, NO fallbacks
     *
     * @param array $config Gemini configuration
     * @return array Raw API response
     * @throws Exception If API key missing or API fails
     */
    private static function fetch_gemini_models($config) {
        if (empty($config['api_key'])) {
            throw new Exception('Gemini API key is required to fetch models');
        }

        // Use simple Gemini provider to get raw models
        if (!class_exists('AI_HTTP_Gemini_Provider')) {
            require_once dirname(__DIR__) . '/Providers/gemini.php';
        }
        
        $provider = new AI_HTTP_Gemini_Provider($config);
        return $provider->get_raw_models();
    }

    /**
     * Fetch Grok models via API
     * Returns raw API response - NO filtering, NO fallbacks
     *
     * @param array $config Grok configuration
     * @return array Raw API response
     * @throws Exception If API key missing or API fails
     */
    private static function fetch_grok_models($config) {
        if (empty($config['api_key'])) {
            throw new Exception('Grok API key is required to fetch models');
        }

        // Use simple Grok provider to get raw models
        if (!class_exists('AI_HTTP_Grok_Provider')) {
            require_once dirname(__DIR__) . '/Providers/grok.php';
        }
        
        $provider = new AI_HTTP_Grok_Provider($config);
        return $provider->get_raw_models();
    }

    /**
     * Fetch OpenRouter models via API
     * Returns raw API response - NO filtering, NO fallbacks
     *
     * @param array $config OpenRouter configuration
     * @return array Raw API response
     * @throws Exception If API key missing or API fails
     */
    private static function fetch_openrouter_models($config) {
        if (empty($config['api_key'])) {
            throw new Exception('OpenRouter API key is required to fetch models');
        }

        // Use simple OpenRouter provider to get raw models
        if (!class_exists('AI_HTTP_OpenRouter_Provider')) {
            require_once dirname(__DIR__) . '/Providers/openrouter.php';
        }
        
        $provider = new AI_HTTP_OpenRouter_Provider($config);
        return $provider->get_raw_models();
    }
}