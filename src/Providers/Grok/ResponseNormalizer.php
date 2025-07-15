<?php
/**
 * AI HTTP Client - Grok/X.AI Response Normalizer
 * 
 * Single Responsibility: Handle ONLY Grok response normalization
 * Converts Grok's OpenAI-compatible format to standardized format
 * Handles tool calls, usage statistics, and reasoning tokens
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Grok_Response_Normalizer {

    /**
     * Normalize response from Grok API
     *
     * @param array $grok_response Raw Grok response
     * @return array Standardized response format
     */
    public function normalize($grok_response) {
        if (is_wp_error($grok_response)) {
            return array(
                'success' => false,
                'data' => null,
                'error' => $grok_response->get_error_message(),
                'provider' => 'grok',
                'raw_response' => null
            );
        }

        $response_code = wp_remote_retrieve_response_code($grok_response);
        $response_body = wp_remote_retrieve_body($grok_response);
        $decoded_response = json_decode($response_body, true);

        if ($response_code >= 400) {
            return $this->handle_error_response($decoded_response, $response_code);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'data' => null,
                'error' => 'Invalid JSON response from Grok API',
                'provider' => 'grok',
                'raw_response' => $response_body
            );
        }

        return $this->format_success_response($decoded_response);
    }

    /**
     * Handle error response from Grok
     *
     * @param array $response Decoded error response
     * @param int $status_code HTTP status code
     * @return array Standardized error response
     */
    private function handle_error_response($response, $status_code) {
        $error_message = 'Grok API error';
        
        if (isset($response['error']['message'])) {
            $error_message = $response['error']['message'];
        } elseif (isset($response['error']['code'])) {
            $error_message = 'Grok API error: ' . $response['error']['code'];
        }

        return array(
            'success' => false,
            'data' => null,
            'error' => $error_message,
            'provider' => 'grok',
            'raw_response' => $response,
            'status_code' => $status_code
        );
    }

    /**
     * Format successful response from Grok
     *
     * @param array $response Decoded Grok response
     * @return array Standardized success response
     */
    private function format_success_response($response) {
        $content = '';
        $tool_calls = array();
        $usage = array();
        $model = '';
        $finish_reason = null;

        // Extract content from choices
        if (isset($response['choices']) && is_array($response['choices'])) {
            foreach ($response['choices'] as $choice) {
                if (isset($choice['message']['content'])) {
                    $content .= $choice['message']['content'];
                }
                
                // Extract tool calls
                if (isset($choice['message']['tool_calls'])) {
                    $tool_calls = array_merge($tool_calls, $choice['message']['tool_calls']);
                }
                
                // Extract finish reason
                if (isset($choice['finish_reason'])) {
                    $finish_reason = $choice['finish_reason'];
                }
            }
        }

        // Extract usage information
        if (isset($response['usage'])) {
            $usage = array(
                'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $response['usage']['total_tokens'] ?? 0
            );
            
            // Grok-specific usage fields
            if (isset($response['usage']['reasoning_tokens'])) {
                $usage['reasoning_tokens'] = $response['usage']['reasoning_tokens'];
            }
            
            if (isset($response['usage']['function_tokens'])) {
                $usage['function_tokens'] = $response['usage']['function_tokens'];
            }
        }

        // Extract model information
        if (isset($response['model'])) {
            $model = $response['model'];
        }

        $result = array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => $model,
                'finish_reason' => $finish_reason
            ),
            'error' => null,
            'provider' => 'grok',
            'raw_response' => $response
        );

        // Add tool calls if any were found
        if (!empty($tool_calls)) {
            $result['data']['tool_calls'] = $tool_calls;
        }

        // Add Grok-specific metadata
        if (isset($response['id'])) {
            $result['data']['response_id'] = $response['id'];
        }

        if (isset($response['created'])) {
            $result['data']['created'] = $response['created'];
        }

        if (isset($response['object'])) {
            $result['data']['object'] = $response['object'];
        }

        return $result;
    }

    /**
     * Normalize streaming response chunk
     * For handling individual streaming chunks
     *
     * @param string $chunk_data Raw chunk data
     * @return array|null Normalized chunk or null if invalid
     */
    public function normalize_streaming_chunk($chunk_data) {
        $decoded = json_decode($chunk_data, true);
        
        if (!$decoded || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $result = array(
            'content' => '',
            'done' => false,
            'tool_calls' => array(),
            'finish_reason' => null
        );

        // Extract content from choices
        if (isset($decoded['choices']) && is_array($decoded['choices'])) {
            foreach ($decoded['choices'] as $choice) {
                // Handle delta content
                if (isset($choice['delta']['content'])) {
                    $result['content'] .= $choice['delta']['content'];
                }
                
                // Handle tool calls in delta
                if (isset($choice['delta']['tool_calls'])) {
                    $result['tool_calls'] = array_merge($result['tool_calls'], $choice['delta']['tool_calls']);
                }
                
                // Check if streaming is complete
                if (isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
                    $result['done'] = true;
                    $result['finish_reason'] = $choice['finish_reason'];
                }
            }
        }

        return $result;
    }

    /**
     * Extract tool calls from response
     *
     * @param array $response Grok response
     * @return array Tool calls
     */
    public function extract_tool_calls($response) {
        $tool_calls = array();

        if (isset($response['choices']) && is_array($response['choices'])) {
            foreach ($response['choices'] as $choice) {
                if (isset($choice['message']['tool_calls']) && is_array($choice['message']['tool_calls'])) {
                    $tool_calls = array_merge($tool_calls, $choice['message']['tool_calls']);
                }
            }
        }

        return $tool_calls;
    }

    /**
     * Get usage statistics from response
     *
     * @param array $response Grok response
     * @return array Usage statistics
     */
    public function get_usage_statistics($response) {
        $usage = array(
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'reasoning_tokens' => 0,
            'function_tokens' => 0
        );

        if (isset($response['usage'])) {
            $usage['prompt_tokens'] = $response['usage']['prompt_tokens'] ?? 0;
            $usage['completion_tokens'] = $response['usage']['completion_tokens'] ?? 0;
            $usage['total_tokens'] = $response['usage']['total_tokens'] ?? 0;
            $usage['reasoning_tokens'] = $response['usage']['reasoning_tokens'] ?? 0;
            $usage['function_tokens'] = $response['usage']['function_tokens'] ?? 0;
        }

        return $usage;
    }

    /**
     * Check if response indicates rate limiting
     *
     * @param array $response Grok response
     * @return bool True if rate limited
     */
    public function is_rate_limited($response) {
        if (isset($response['error']['code'])) {
            return $response['error']['code'] === 'rate_limit_exceeded';
        }
        
        return false;
    }

    /**
     * Get rate limit information from response headers
     *
     * @param array $response_headers HTTP response headers
     * @return array Rate limit information
     */
    public function get_rate_limit_info($response_headers) {
        $rate_limit = array(
            'requests_remaining' => null,
            'requests_limit' => null,
            'tokens_remaining' => null,
            'tokens_limit' => null,
            'reset_time' => null
        );

        // Extract rate limit headers if present
        foreach ($response_headers as $header => $value) {
            $header_lower = strtolower($header);
            
            switch ($header_lower) {
                case 'x-ratelimit-remaining-requests':
                    $rate_limit['requests_remaining'] = intval($value);
                    break;
                case 'x-ratelimit-limit-requests':
                    $rate_limit['requests_limit'] = intval($value);
                    break;
                case 'x-ratelimit-remaining-tokens':
                    $rate_limit['tokens_remaining'] = intval($value);
                    break;
                case 'x-ratelimit-limit-tokens':
                    $rate_limit['tokens_limit'] = intval($value);
                    break;
                case 'x-ratelimit-reset-requests':
                    $rate_limit['reset_time'] = intval($value);
                    break;
            }
        }

        return $rate_limit;
    }

    /**
     * Validate Grok response structure
     *
     * @param array $response Grok response
     * @return bool True if valid structure
     */
    public function validate_response_structure($response) {
        // Basic structure validation
        if (!is_array($response)) {
            return false;
        }

        // Should have choices for successful responses
        if (isset($response['choices'])) {
            if (!is_array($response['choices'])) {
                return false;
            }
            
            foreach ($response['choices'] as $choice) {
                if (!isset($choice['message']) || !is_array($choice['message'])) {
                    return false;
                }
            }
        }

        // Should have error for error responses
        if (isset($response['error'])) {
            if (!is_array($response['error']) || !isset($response['error']['message'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get model capabilities from response
     *
     * @param array $response Grok response
     * @return array Model capabilities
     */
    public function get_model_capabilities($response) {
        $capabilities = array(
            'supports_function_calling' => false,
            'supports_reasoning' => false,
            'supports_vision' => false,
            'max_tokens' => 4096
        );

        if (isset($response['model'])) {
            $model = $response['model'];
            
            // All Grok models support function calling
            $capabilities['supports_function_calling'] = true;
            
            // Check for reasoning capabilities
            if (isset($response['usage']['reasoning_tokens'])) {
                $capabilities['supports_reasoning'] = true;
            }
            
            // Check for vision capabilities
            if (strpos($model, 'vision') !== false) {
                $capabilities['supports_vision'] = true;
            }
            
            // Set max tokens based on model
            switch ($model) {
                case 'grok-3':
                    $capabilities['max_tokens'] = 8192;
                    break;
                case 'grok-3-fast':
                    $capabilities['max_tokens'] = 4096;
                    break;
                case 'grok-3-mini':
                case 'grok-3-mini-fast':
                    $capabilities['max_tokens'] = 2048;
                    break;
                default:
                    $capabilities['max_tokens'] = 4096;
            }
        }

        return $capabilities;
    }

    /**
     * Calculate estimated costs from usage
     *
     * @param array $usage Usage statistics
     * @param string $model Model name
     * @return array Cost information
     */
    public function calculate_estimated_costs($usage, $model = '') {
        // Note: These are example rates - actual Grok pricing may vary
        $pricing = array(
            'grok-3' => array('input' => 0.01, 'output' => 0.02),
            'grok-3-fast' => array('input' => 0.005, 'output' => 0.01),
            'grok-3-mini' => array('input' => 0.002, 'output' => 0.004),
            'grok-3-mini-fast' => array('input' => 0.001, 'output' => 0.002),
            'default' => array('input' => 0.01, 'output' => 0.02)
        );

        $rates = $pricing[$model] ?? $pricing['default'];
        
        $input_cost = ($usage['prompt_tokens'] ?? 0) * $rates['input'] / 1000;
        $output_cost = ($usage['completion_tokens'] ?? 0) * $rates['output'] / 1000;
        
        return array(
            'input_cost' => $input_cost,
            'output_cost' => $output_cost,
            'total_cost' => $input_cost + $output_cost,
            'currency' => 'USD'
        );
    }
}