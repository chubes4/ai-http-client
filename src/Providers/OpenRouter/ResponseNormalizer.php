<?php
/**
 * AI HTTP Client - OpenRouter Response Normalizer
 * 
 * Single Responsibility: Handle ONLY OpenRouter response normalization
 * Converts OpenRouter's OpenAI-compatible format to standardized format
 * Handles provider routing information, tool calls, and usage statistics
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenRouter_Response_Normalizer {

    /**
     * Normalize response from OpenRouter API
     *
     * @param array $openrouter_response Raw OpenRouter response
     * @return array Standardized response format
     */
    public function normalize($openrouter_response) {
        if (is_wp_error($openrouter_response)) {
            return array(
                'success' => false,
                'data' => null,
                'error' => $openrouter_response->get_error_message(),
                'provider' => 'openrouter',
                'raw_response' => null
            );
        }

        $response_code = wp_remote_retrieve_response_code($openrouter_response);
        $response_body = wp_remote_retrieve_body($openrouter_response);
        $decoded_response = json_decode($response_body, true);

        if ($response_code >= 400) {
            return $this->handle_error_response($decoded_response, $response_code);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'data' => null,
                'error' => 'Invalid JSON response from OpenRouter API',
                'provider' => 'openrouter',
                'raw_response' => $response_body
            );
        }

        return $this->format_success_response($decoded_response);
    }

    /**
     * Handle error response from OpenRouter
     *
     * @param array $response Decoded error response
     * @param int $status_code HTTP status code
     * @return array Standardized error response
     */
    private function handle_error_response($response, $status_code) {
        $error_message = 'OpenRouter API error';
        
        if (isset($response['error']['message'])) {
            $error_message = $response['error']['message'];
        } elseif (isset($response['error']['code'])) {
            $error_message = 'OpenRouter API error: ' . $response['error']['code'];
        }

        return array(
            'success' => false,
            'data' => null,
            'error' => $error_message,
            'provider' => 'openrouter',
            'raw_response' => $response,
            'status_code' => $status_code
        );
    }

    /**
     * Format successful response from OpenRouter
     *
     * @param array $response Decoded OpenRouter response
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
        }

        // Extract model information (OpenRouter shows actual model used)
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
            'provider' => 'openrouter',
            'raw_response' => $response
        );

        // Add tool calls if any were found
        if (!empty($tool_calls)) {
            $result['data']['tool_calls'] = $tool_calls;
        }

        // Add OpenRouter-specific metadata
        if (isset($response['id'])) {
            $result['data']['response_id'] = $response['id'];
        }

        if (isset($response['created'])) {
            $result['data']['created'] = $response['created'];
        }

        if (isset($response['object'])) {
            $result['data']['object'] = $response['object'];
        }

        // OpenRouter-specific provider routing information
        if (isset($response['provider'])) {
            $result['data']['actual_provider'] = $response['provider'];
        }

        // Add generation ID for post-request statistics
        if (isset($response['id'])) {
            $result['data']['generation_id'] = $response['id'];
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
            'finish_reason' => null,
            'model' => null,
            'provider' => null
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

        // Extract OpenRouter-specific metadata
        if (isset($decoded['model'])) {
            $result['model'] = $decoded['model'];
        }

        if (isset($decoded['provider'])) {
            $result['provider'] = $decoded['provider'];
        }

        return $result;
    }

    /**
     * Extract tool calls from response
     *
     * @param array $response OpenRouter response
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
     * @param array $response OpenRouter response
     * @return array Usage statistics
     */
    public function get_usage_statistics($response) {
        $usage = array(
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'native_provider' => null,
            'actual_model' => null
        );

        if (isset($response['usage'])) {
            $usage['prompt_tokens'] = $response['usage']['prompt_tokens'] ?? 0;
            $usage['completion_tokens'] = $response['usage']['completion_tokens'] ?? 0;
            $usage['total_tokens'] = $response['usage']['total_tokens'] ?? 0;
        }

        // OpenRouter-specific provider information
        if (isset($response['provider'])) {
            $usage['native_provider'] = $response['provider'];
        }

        if (isset($response['model'])) {
            $usage['actual_model'] = $response['model'];
        }

        return $usage;
    }

    /**
     * Check if response indicates rate limiting
     *
     * @param array $response OpenRouter response
     * @return bool True if rate limited
     */
    public function is_rate_limited($response) {
        if (isset($response['error']['code'])) {
            return in_array($response['error']['code'], array('rate_limit_exceeded', 'quota_exceeded'));
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
            'credits_remaining' => null,
            'reset_time' => null
        );

        // Extract rate limit headers if present
        foreach ($response_headers as $header => $value) {
            $header_lower = strtolower($header);
            
            switch ($header_lower) {
                case 'x-ratelimit-remaining':
                    $rate_limit['requests_remaining'] = intval($value);
                    break;
                case 'x-ratelimit-limit':
                    $rate_limit['requests_limit'] = intval($value);
                    break;
                case 'x-ratelimit-reset':
                    $rate_limit['reset_time'] = intval($value);
                    break;
            }
        }

        return $rate_limit;
    }

    /**
     * Extract OpenRouter provider routing information
     *
     * @param array $response OpenRouter response
     * @return array Provider routing information
     */
    public function extract_provider_routing_info($response) {
        $routing_info = array();

        if (isset($response['provider'])) {
            $routing_info['used_provider'] = $response['provider'];
        }

        if (isset($response['model'])) {
            $routing_info['actual_model'] = $response['model'];
        }

        if (isset($response['id'])) {
            $routing_info['generation_id'] = $response['id'];
        }

        if (isset($response['usage'])) {
            $routing_info['usage'] = $response['usage'];
        }

        return $routing_info;
    }

    /**
     * Validate OpenRouter response structure
     *
     * @param array $response OpenRouter response
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
     * @param array $response OpenRouter response
     * @return array Model capabilities
     */
    public function get_model_capabilities($response) {
        $capabilities = array(
            'supports_function_calling' => false,
            'supports_vision' => false,
            'supports_streaming' => true, // OpenRouter supports streaming for all models
            'normalized_by_openrouter' => true
        );

        // OpenRouter normalizes capabilities, so we can assume most features are available
        if (isset($response['model'])) {
            $capabilities['supports_function_calling'] = true; // OpenRouter normalizes this
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
        // Note: OpenRouter has dynamic pricing based on actual provider used
        // These are rough estimates - actual costs available via generation stats API
        
        $base_rate = 0.01; // Default rate per 1K tokens
        
        $input_cost = ($usage['prompt_tokens'] ?? 0) * $base_rate / 1000;
        $output_cost = ($usage['completion_tokens'] ?? 0) * $base_rate / 1000;
        
        return array(
            'input_cost' => $input_cost,
            'output_cost' => $output_cost,
            'total_cost' => $input_cost + $output_cost,
            'currency' => 'USD',
            'note' => 'Estimated - use generation stats API for actual costs'
        );
    }

    /**
     * Extract generation metadata for post-request analysis
     *
     * @param array $response OpenRouter response
     * @return array Generation metadata
     */
    public function extract_generation_metadata($response) {
        $metadata = array();

        if (isset($response['id'])) {
            $metadata['generation_id'] = $response['id'];
        }

        if (isset($response['created'])) {
            $metadata['created_at'] = $response['created'];
        }

        if (isset($response['model'])) {
            $metadata['actual_model'] = $response['model'];
        }

        if (isset($response['provider'])) {
            $metadata['actual_provider'] = $response['provider'];
        }

        if (isset($response['usage'])) {
            $metadata['token_usage'] = $response['usage'];
        }

        return $metadata;
    }

    /**
     * Handle OpenRouter fallback information
     *
     * @param array $response OpenRouter response
     * @return array|null Fallback information
     */
    public function extract_fallback_info($response) {
        // OpenRouter may include information about fallbacks used
        $fallback_info = null;

        if (isset($response['fallback_used']) || isset($response['route_fallback'])) {
            $fallback_info = array(
                'fallback_used' => $response['fallback_used'] ?? false,
                'original_model' => $response['original_model'] ?? null,
                'fallback_reason' => $response['fallback_reason'] ?? null
            );
        }

        return $fallback_info;
    }

    /**
     * Check if response contains provider-specific warnings
     *
     * @param array $response OpenRouter response
     * @return array Warnings if any
     */
    public function extract_warnings($response) {
        $warnings = array();

        // OpenRouter may include warnings about model availability, costs, etc.
        if (isset($response['warnings']) && is_array($response['warnings'])) {
            $warnings = $response['warnings'];
        }

        return $warnings;
    }
}