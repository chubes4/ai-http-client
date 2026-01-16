<?php
/**
 * AI HTTP Client - Base Provider
 * 
 * Abstract base class for all AI provider implementations.
 * Centralizes common validation, sanitization, and HTTP patterns.
 *
 * @package AIHttpClient\Providers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

abstract class AI_HTTP_BaseProvider {

    protected $api_key;
    protected $base_url;
    protected $files_api_callback = null;

    abstract protected function get_default_base_url();
    abstract protected function get_auth_headers();
    abstract protected function get_provider_name();
    abstract protected function format_request($unified_request);
    abstract protected function format_response($raw_response);
    abstract protected function normalize_models_response($raw_models);
    abstract protected function get_chat_endpoint();
    abstract protected function get_models_endpoint();

    public function __construct($config = []) {
        $this->api_key = $config['api_key'] ?? '';
        
        if (isset($config['base_url']) && !empty($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        } else {
            $this->base_url = $this->get_default_base_url();
        }
        
        $this->configure($config);
    }

    protected function configure($config) {}

    public function is_configured() {
        return !empty($this->api_key);
    }

    public function set_files_api_callback($callback) {
        $this->files_api_callback = $callback;
    }

    public function request($standard_request) {
        $this->validate_configured();
        
        $provider_request = $this->format_request($standard_request);
        $url = $this->build_request_url($provider_request);
        
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('chubes_ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], $this->get_provider_name());

        if (!$result['success']) {
            $this->handle_request_error($result, $provider_request);
        }

        $raw_response = json_decode($result['data'], true);

        if ($raw_response === null && json_last_error() !== JSON_ERROR_NONE) {
            AIHttpError::trigger_error($this->get_provider_name(), 'Invalid JSON response', [
                'provider' => strtolower($this->get_provider_name()),
                'json_error' => json_last_error_msg(),
                'response_preview' => substr($result['data'] ?? '', 0, 500)
            ]);

            throw new Exception(sprintf(
                '%s returned invalid JSON: %s',
                $this->get_provider_name(),
                json_last_error_msg()
            ));
        }

        return $this->format_response($raw_response);
    }

    public function streaming_request($standard_request, $callback = null) {
        $this->validate_configured();
        
        $provider_request = $this->format_request($standard_request);
        $url = $this->build_streaming_url($provider_request);
        
        $headers = $this->get_auth_headers();
        $headers['Content-Type'] = 'application/json';
        
        $result = apply_filters('chubes_ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => wp_json_encode($provider_request)
        ], $this->get_provider_name() . ' Streaming', true, $callback);
        
        if (!$result['success']) {
            throw new Exception($this->get_provider_name() . ' streaming request failed: ' . esc_html($result['error']));
        }

        return $this->build_streaming_response($standard_request);
    }

    protected function build_request_url($provider_request) {
        return $this->base_url . $this->get_chat_endpoint();
    }

    protected function build_streaming_url($provider_request) {
        return $this->build_request_url($provider_request);
    }

    protected function build_streaming_response($standard_request) {
        return [
            'success' => true,
            'data' => [
                'content' => '',
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                'model' => $standard_request['model'] ?? '',
                'finish_reason' => 'stop',
                'tool_calls' => null
            ],
            'error' => null,
            'provider' => strtolower($this->get_provider_name())
        ];
    }

    public function get_raw_models() {
        if (!$this->is_configured()) {
            return [];
        }

        $url = $this->base_url . $this->get_models_endpoint();
        
        $result = apply_filters('chubes_ai_http', [], 'GET', $url, [
            'headers' => $this->get_auth_headers()
        ], $this->get_provider_name());

        if (!$result['success']) {
            AIHttpError::trigger_error($this->get_provider_name(), 'API request failed: ' . esc_html($result['error']), [
                'provider' => strtolower($this->get_provider_name()),
                'endpoint' => $this->get_models_endpoint(),
                'response' => $result
            ]);
            throw new Exception($this->get_provider_name() . ' API request failed: ' . esc_html($result['error']));
        }

        return json_decode($result['data'], true);
    }

    public function get_normalized_models() {
        $raw_models = $this->get_raw_models();
        return $this->normalize_models_response($raw_models);
    }

    public function upload_file($file_path, $purpose = 'user_data') {
        $this->validate_configured();
        $this->validate_file_exists($file_path);

        $url = $this->build_file_upload_url();
        
        $boundary = wp_generate_uuid4();
        $headers = array_merge($this->get_auth_headers(), [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        ]);

        $body = $this->build_multipart_body($file_path, $purpose, $boundary);

        $result = apply_filters('chubes_ai_http', [], 'POST', $url, [
            'headers' => $headers,
            'body' => $body
        ], $this->get_provider_name() . ' File Upload');

        if (!$result['success']) {
            throw new Exception($this->get_provider_name() . ' file upload failed: ' . esc_html($result['error']));
        }

        return $this->extract_file_id($result['data']);
    }

    public function delete_file($file_id) {
        $this->validate_configured();

        $url = $this->build_file_delete_url($file_id);
        
        $result = apply_filters('chubes_ai_http', [], 'DELETE', $url, [
            'headers' => $this->get_auth_headers()
        ], $this->get_provider_name() . ' File Delete');

        if (!$result['success']) {
            throw new Exception($this->get_provider_name() . ' file delete failed: ' . esc_html($result['error']));
        }

        return $result['status_code'] === 200;
    }

    protected function get_files_endpoint() {
        return '/files';
    }

    protected function build_file_upload_url() {
        return $this->base_url . $this->get_files_endpoint();
    }

    protected function build_file_delete_url($file_id) {
        return $this->base_url . $this->get_files_endpoint() . '/' . $file_id;
    }

    protected function build_multipart_body($file_path, $purpose, $boundary) {
        $body = '';
        
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n";
        $body .= $purpose . "\r\n";
        
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . "\"\r\n";
        $body .= "Content-Type: " . mime_content_type($file_path) . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        return $body;
    }

    protected function extract_file_id($response_body) {
        $data = json_decode($response_body, true);
        if (!isset($data['id'])) {
            throw new Exception($this->get_provider_name() . ' file upload response missing file ID');
        }
        return $data['id'];
    }

    protected function validate_configured() {
        if (!$this->is_configured()) {
            throw new Exception($this->get_provider_name() . ' provider not configured - missing API key');
        }
    }

    protected function validate_file_exists($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception('File not found: ' . esc_html($file_path));
        }
    }

    protected function validate_unified_request($request) {
        if (!is_array($request)) {
            throw new Exception('Request must be an array');
        }

        if (!isset($request['messages']) || !is_array($request['messages'])) {
            throw new Exception('Request must include messages array');
        }

        if (empty($request['messages'])) {
            throw new Exception('Messages array cannot be empty');
        }
    }

    protected function sanitize_common_fields($request) {
        if (isset($request['messages'])) {
            foreach ($request['messages'] as &$message) {
                if (isset($message['role'])) {
                    $message['role'] = sanitize_text_field($message['role']);
                }
            }
        }

        if (isset($request['model'])) {
            $request['model'] = sanitize_text_field($request['model']);
        }

        return $request;
    }

    /**
     * Sanitize message to only include provider-safe fields.
     *
     * Filters out internal application fields (like metadata) that providers
     * don't recognize. Uses a whitelist approach for safety.
     *
     * @param array $message Raw message with potential internal fields
     * @return array Sanitized message with only provider-safe fields
     */
    protected function sanitize_message_fields(array $message): array {
        $allowed_fields = [
            'role',
            'content',
            'name',
            'tool_call_id',
            'tool_calls',
            'images',
            'image_urls',
            'files',
        ];

        return array_intersect_key($message, array_flip($allowed_fields));
    }

    protected function handle_request_error($result, $provider_request) {
        AIHttpError::trigger_error($this->get_provider_name(), 'API request failed: ' . esc_html($result['error']), [
            'provider' => strtolower($this->get_provider_name()),
            'endpoint' => $this->get_chat_endpoint(),
            'response' => $result,
            'request' => $provider_request
        ]);
        throw new Exception($this->get_provider_name() . ' API request failed: ' . esc_html($result['error']));
    }

    protected function convert_parameters_to_json_schema($parameters) {
        if (isset($parameters['type']) && $parameters['type'] === 'object') {
            return $parameters;
        }
        
        $properties = [];
        $required = [];
        
        foreach ($parameters as $param_name => $param_config) {
            if (!is_array($param_config)) {
                continue;
            }
            
            $properties[$param_name] = [];
            
            if (isset($param_config['type'])) {
                $properties[$param_name]['type'] = $param_config['type'];
                
                if ($param_config['type'] === 'array' && !isset($param_config['items'])) {
                    $properties[$param_name]['items'] = ['type' => 'string'];
                }
            }
            if (isset($param_config['description'])) {
                $properties[$param_name]['description'] = $param_config['description'];
            }
            if (isset($param_config['enum'])) {
                $properties[$param_name]['enum'] = $param_config['enum'];
            }
            if (isset($param_config['items'])) {
                $properties[$param_name]['items'] = $param_config['items'];
            }
            if (isset($param_config['required']) && $param_config['required']) {
                $required[] = $param_name;
            }
        }
        
        $schema = [
            'type' => 'object',
            'properties' => (object) $properties
        ];
        
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        
        return $schema;
    }

    protected function upload_file_via_callback($file_path) {
        if (!$this->files_api_callback) {
            throw new Exception('Files API callback not set - cannot upload files');
        }

        if (!file_exists($file_path)) {
            throw new Exception('File not found: ' . esc_html($file_path));
        }

        return call_user_func($this->files_api_callback, $file_path, 'user_data', strtolower($this->get_provider_name()));
    }
}
