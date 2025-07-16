<?php
/**
 * AI HTTP Client - OpenRouter Provider
 * 
 * Single Responsibility: Handle ONLY OpenRouter API communication
 * OpenRouter provides unified access to hundreds of AI models from different providers
 * Uses OpenAI-compatible API with automatic model routing and fallbacks
 *
 * @package AIHttpClient\Providers\OpenRouter
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenRouter_Provider extends AI_HTTP_Provider_Base {

    protected $provider_name = 'openrouter';
    
    private $api_key;
    private $base_url = 'https://openrouter.ai/api/v1';
    private $http_referer;
    private $app_title;

    protected function init() {
        $this->api_key = $this->get_config('api_key');
        $this->http_referer = $this->get_config('http_referer');
        $this->app_title = $this->get_config('app_title', 'AI HTTP Client');
    }

    public function send_request($request) {
        $request = $this->sanitize_request($request);
        
        $url = $this->get_api_endpoint();
        
        return $this->make_request($url, $request);
    }

    public function send_streaming_request($request, $callback) {
        $request = $this->sanitize_request($request);
        $request['stream'] = true;
        
        $url = $this->get_api_endpoint();
        
        return AI_HTTP_OpenRouter_Streaming_Module::send_streaming_request(
            $url,
            $request,
            $this->get_auth_headers(),
            $callback,
            $this->timeout
        );
    }

    public function get_available_models() {
        if (!$this->is_configured()) {
            return array();
        }

        try {
            return AI_HTTP_OpenRouter_Model_Fetcher::fetch_models(
                $this->base_url,
                $this->get_auth_headers()
            );
        } catch (Exception $e) {
            return array();
        }
    }

    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'OpenRouter API key not configured'
            );
        }

        try {
            // Check if model is configured
            if (!isset($this->config['model'])) {
                return array(
                    'success' => false,
                    'message' => 'No model configured for OpenRouter provider'
                );
            }
            
            $test_request = array(
                'model' => $this->config['model'],
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Test'
                    )
                ),
                'max_tokens' => 5
            );

            $response = $this->send_request($test_request);
            
            return array(
                'success' => true,
                'message' => 'Successfully connected to OpenRouter API'
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            );
        }
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    protected function get_api_endpoint($model = null) {
        return $this->base_url . '/chat/completions';
    }

    protected function get_auth_headers() {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );

        if (!empty($this->http_referer)) {
            $headers['HTTP-Referer'] = $this->http_referer;
        }

        if (!empty($this->app_title)) {
            $headers['X-Title'] = $this->app_title;
        }

        return $headers;
    }
}