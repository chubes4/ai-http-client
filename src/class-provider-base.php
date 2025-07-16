<?php
/**
 * AI HTTP Client - Base Provider Class
 * 
 * Abstract base class that all AI providers must extend.
 * Defines the interface and common functionality for all providers.
 *
 * @package AIHttpClient
 */

defined('ABSPATH') || exit;

abstract class AI_HTTP_Provider_Base {

    /**
     * Provider configuration
     */
    protected $config = array();
    
    /**
     * Provider name
     */
    protected $provider_name = '';
    
    /**
     * Default timeout for requests
     */
    protected $timeout = 30;

    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        $this->config = $config;
        $this->init();
    }

    /**
     * Initialize provider-specific settings
     * Override in child classes
     */
    protected function init() {
        // Override in child classes
    }

    /**
     * Send HTTP request to the provider's API
     * Must be implemented by each provider
     *
     * @param array $request Normalized request data for this provider
     * @return array Raw response from provider
     */
    abstract public function send_request($request);

    /**
     * Send streaming HTTP request to the provider's API
     * Must be implemented by each provider that supports streaming
     *
     * @param array $request Normalized request data for this provider
     * @param callable $callback Function to call for each streaming chunk
     * @return string Full response from streaming request
     */
    public function send_streaming_request($request, $callback) {
        throw new Exception('Streaming not supported by this provider');
    }

    /**
     * Get available models for this provider
     * Must be implemented by each provider
     *
     * @return array List of available models
     */
    abstract public function get_available_models();

    /**
     * Test connection to the provider
     * Must be implemented by each provider
     *
     * @return array Test result with success status and message
     */
    abstract public function test_connection();

    /**
     * Check if provider is properly configured
     * Must be implemented by each provider
     *
     * @return bool True if configured, false otherwise
     */
    abstract public function is_configured();

    /**
     * Get the API endpoint URL for this provider
     * Must be implemented by each provider
     *
     * @return string API endpoint URL
     */
    abstract protected function get_api_endpoint($model = null);

    /**
     * Get authentication headers for requests
     * Must be implemented by each provider
     *
     * @return array Authentication headers
     */
    abstract protected function get_auth_headers();

    /**
     * Make HTTP POST request using WordPress HTTP API
     *
     * @param string $url Request URL
     * @param array $body Request body
     * @param array $additional_headers Additional headers
     * @return array Response
     */
    protected function make_request($url, $body, $additional_headers = array()) {
        $headers = array_merge(
            array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
            ),
            $this->get_auth_headers(),
            $additional_headers
        );

        $args = array(
            'method' => 'POST',
            'timeout' => $this->timeout,
            'headers' => $headers,
            'body' => is_array($body) ? wp_json_encode($body) : $body,
            'data_format' => 'body'
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('HTTP Request failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 400) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : "HTTP {$response_code} error";
            throw new Exception($error_message);
        }

        $decoded_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from provider');
        }

        return $decoded_response;
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    protected function get_config($key, $default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Set configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    public function set_config($key, $value) {
        $this->config[$key] = $value;
    }

    /**
     * Set full configuration array (required by Factory)
     *
     * @param array $config Full configuration array
     */
    public function set_configuration($config) {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
            $this->init(); // Re-initialize with new config
        }
    }

    /**
     * Get provider name
     *
     * @return string Provider name
     */
    public function get_provider_name() {
        return $this->provider_name;
    }

    /**
     * Get parameter requirements for this provider
     * Override in child classes to define provider-specific requirements
     *
     * @return array Parameter requirements with keys:
     *   - 'required': array of required parameters
     *   - 'optional': array of optional parameters  
     *   - 'defaults': array of default values for required parameters without UI component
     *   - 'minimums': array of minimum values for numeric parameters
     *   - 'maximums': array of maximum values for numeric parameters
     */
    protected function get_parameter_requirements() {
        return array(
            'required' => array(),
            'optional' => array('temperature', 'max_tokens', 'top_p'),
            'defaults' => array(),
            'minimums' => array(),
            'maximums' => array()
        );
    }

    /**
     * Handle parameter requirements based on provider specifications
     * 
     * @param array $request Request data
     * @return array Request with proper parameter handling
     */
    protected function handle_parameter_requirements($request) {
        $requirements = $this->get_parameter_requirements();
        
        // Handle required parameters
        foreach ($requirements['required'] as $param) {
            if (!isset($request[$param])) {
                // Check if there's a default value
                if (isset($requirements['defaults'][$param])) {
                    $request[$param] = $requirements['defaults'][$param];
                }
                // If no default and required, throw error
                else {
                    throw new Exception("Required parameter '{$param}' is missing");
                }
            }
        }
        
        // Apply minimums and maximums
        foreach ($request as $param => $value) {
            if (isset($requirements['minimums'][$param]) && is_numeric($value)) {
                $request[$param] = max($requirements['minimums'][$param], $value);
            }
            if (isset($requirements['maximums'][$param]) && is_numeric($value)) {
                $request[$param] = min($requirements['maximums'][$param], $value);
            }
        }
        
        return $request;
    }

    /**
     * Sanitize and validate request data
     *
     * @param array $request Request data
     * @return array Sanitized request
     */
    protected function sanitize_request($request) {
        // Handle parameter requirements first
        $request = $this->handle_parameter_requirements($request);
        
        // Basic sanitization - override in child classes for provider-specific needs
        if (isset($request['messages'])) {
            error_log('AI HTTP Client Provider Base DEBUG: Sanitizing messages - count: ' . count($request['messages']));
            foreach ($request['messages'] as $index => &$message) {
                error_log('AI HTTP Client Provider Base DEBUG: Message ' . $index . ' before sanitization: ' . json_encode($message));
                if (isset($message['content'])) {
                    $original_content = $message['content'];
                    $message['content'] = sanitize_textarea_field($message['content']);
                    if ($original_content !== $message['content']) {
                        error_log('AI HTTP Client Provider Base DEBUG: Content sanitization changed content for message ' . $index);
                    }
                }
                if (isset($message['role'])) {
                    $message['role'] = sanitize_text_field($message['role']);
                }
                error_log('AI HTTP Client Provider Base DEBUG: Message ' . $index . ' after sanitization: ' . json_encode($message));
            }
            error_log('AI HTTP Client Provider Base DEBUG: All messages after sanitization: ' . json_encode($request['messages']));
        }

        if (isset($request['model'])) {
            $request['model'] = sanitize_text_field($request['model']);
        }

        return $request;
    }
}