<?php
/**
 * AI HTTP Client - OpenRouter Request Normalizer
 * 
 * Single Responsibility: Handle ONLY OpenRouter request normalization
 * Converts standard format to OpenRouter's OpenAI-compatible requirements
 * Supports multi-modal content, function calling, and provider routing
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_OpenRouter_Request_Normalizer {

    /**
     * Normalize request for OpenRouter API
     *
     * @param array $standard_request Standardized request
     * @return array OpenRouter-formatted request
     */
    public function normalize($standard_request) {
        $normalized = array();

        // Model - OpenRouter can auto-select if not specified
        if (isset($standard_request['model'])) {
            $normalized['model'] = $standard_request['model'];
        }

        // Handle system instruction by prepending as system message
        if (isset($standard_request['system_instruction'])) {
            $system_message = [
                'role' => 'system',
                'content' => $standard_request['system_instruction']
            ];
            
            // Prepend system message to messages array
            if (isset($standard_request['messages']) && is_array($standard_request['messages'])) {
                array_unshift($standard_request['messages'], $system_message);
            } else {
                $standard_request['messages'] = [$system_message];
            }
        }

        // Convert messages to OpenRouter format (OpenAI-compatible)
        if (isset($standard_request['messages']) && is_array($standard_request['messages'])) {
            $normalized['messages'] = $this->normalize_messages($standard_request['messages']);
        }

        // Handle temperature
        if (isset($standard_request['temperature'])) {
            $normalized['temperature'] = max(0, min(2, floatval($standard_request['temperature'])));
        }

        // Handle max_tokens
        if (isset($standard_request['max_tokens'])) {
            $normalized['max_tokens'] = max(1, intval($standard_request['max_tokens']));
        }

        // Handle top_p
        if (isset($standard_request['top_p'])) {
            $normalized['top_p'] = max(0, min(1, floatval($standard_request['top_p'])));
        }

        // Handle frequency_penalty
        if (isset($standard_request['frequency_penalty'])) {
            $normalized['frequency_penalty'] = max(-2, min(2, floatval($standard_request['frequency_penalty'])));
        }

        // Handle presence_penalty
        if (isset($standard_request['presence_penalty'])) {
            $normalized['presence_penalty'] = max(-2, min(2, floatval($standard_request['presence_penalty'])));
        }

        // Handle stop sequences
        if (isset($standard_request['stop']) && is_array($standard_request['stop'])) {
            $normalized['stop'] = array_slice($standard_request['stop'], 0, 4);
        }

        // Handle streaming
        if (isset($standard_request['stream'])) {
            $normalized['stream'] = (bool) $standard_request['stream'];
        }

        // Handle tools for function calling
        if (isset($standard_request['tools']) && is_array($standard_request['tools'])) {
            $normalized['tools'] = AI_HTTP_OpenRouter_Function_Calling::sanitize_tools($standard_request['tools']);
        }

        // Handle tool choice
        if (isset($standard_request['tool_choice'])) {
            $normalized['tool_choice'] = AI_HTTP_OpenRouter_Function_Calling::validate_tool_choice($standard_request['tool_choice']);
        }

        // Handle parallel tool calls
        if (isset($standard_request['parallel_tool_calls'])) {
            $normalized['parallel_tool_calls'] = (bool) $standard_request['parallel_tool_calls'];
        }

        // Handle user identifier
        if (isset($standard_request['user'])) {
            $normalized['user'] = sanitize_text_field($standard_request['user']);
        }

        // Handle seed for reproducible outputs
        if (isset($standard_request['seed'])) {
            $normalized['seed'] = intval($standard_request['seed']);
        }

        // Handle response format
        if (isset($standard_request['response_format'])) {
            $normalized['response_format'] = $this->normalize_response_format($standard_request['response_format']);
        }

        // Handle OpenRouter-specific provider preferences
        if (isset($standard_request['provider'])) {
            $normalized['provider'] = $this->normalize_provider_preferences($standard_request['provider']);
        }

        // Handle OpenRouter-specific route preferences
        if (isset($standard_request['route'])) {
            $normalized['route'] = $standard_request['route'];
        }

        // Handle fallback models
        if (isset($standard_request['fallbacks']) && is_array($standard_request['fallbacks'])) {
            $normalized['fallbacks'] = $standard_request['fallbacks'];
        }

        return $normalized;
    }

    /**
     * Normalize messages for OpenRouter API (OpenAI-compatible format)
     *
     * @param array $messages Array of messages
     * @return array Normalized messages
     */
    private function normalize_messages($messages) {
        $normalized_messages = array();

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                continue;
            }

            $normalized_message = array(
                'role' => $this->normalize_role($message['role'])
            );

            // Handle multi-modal content
            if (isset($message['images']) || isset($message['image_urls']) || isset($message['files'])) {
                $normalized_message['content'] = $this->build_multimodal_content($message);
            } else {
                // Standard text content
                $normalized_message['content'] = $message['content'];
            }

            // Handle tool calls in assistant messages
            if (isset($message['tool_calls'])) {
                $normalized_message['tool_calls'] = $message['tool_calls'];
            }

            // Handle tool call ID for tool messages
            if (isset($message['tool_call_id'])) {
                $normalized_message['tool_call_id'] = $message['tool_call_id'];
            }

            // Handle name field for function/tool messages
            if (isset($message['name'])) {
                $normalized_message['name'] = sanitize_text_field($message['name']);
            }

            $normalized_messages[] = $normalized_message;
        }

        return $normalized_messages;
    }

    /**
     * Normalize role names for OpenRouter (OpenAI-compatible)
     *
     * @param string $role Original role
     * @return string Normalized role
     */
    private function normalize_role($role) {
        switch ($role) {
            case 'user':
                return 'user';
            case 'assistant':
                return 'assistant';
            case 'system':
                return 'system';
            case 'tool':
            case 'function':
                return 'tool';
            default:
                return 'user'; // Default fallback
        }
    }

    /**
     * Build multi-modal content for OpenRouter (OpenAI-compatible format)
     *
     * @param array $message Message with multi-modal content
     * @return array Multi-modal content array
     */
    private function build_multimodal_content($message) {
        $content = array();

        // Add text content first if present
        if (!empty($message['content'])) {
            $content[] = array(
                'type' => 'text',
                'text' => $message['content']
            );
        }

        // Handle image URLs
        if (isset($message['image_urls']) && is_array($message['image_urls'])) {
            foreach ($message['image_urls'] as $image_url) {
                $content[] = array(
                    'type' => 'image_url',
                    'image_url' => array(
                        'url' => $image_url,
                        'detail' => 'auto'
                    )
                );
            }
        }

        // Handle base64 images
        if (isset($message['images']) && is_array($message['images'])) {
            foreach ($message['images'] as $image) {
                if (is_string($image)) {
                    if (strpos($image, 'data:') === 0) {
                        // Already in data URI format
                        $content[] = array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $image,
                                'detail' => 'auto'
                            )
                        );
                    } else {
                        // Assume it's a URL
                        $content[] = array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $image,
                                'detail' => 'auto'
                            )
                        );
                    }
                } elseif (is_array($image) && isset($image['url'])) {
                    $content[] = array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => $image['url'],
                            'detail' => $image['detail'] ?? 'auto'
                        )
                    );
                }
            }
        }

        // Handle file references
        if (isset($message['files']) && is_array($message['files'])) {
            foreach ($message['files'] as $file) {
                if (isset($file['url'])) {
                    // For now, treat files as images if they appear to be images
                    $file_extension = strtolower(pathinfo($file['url'], PATHINFO_EXTENSION));
                    $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
                    
                    if (in_array($file_extension, $image_extensions)) {
                        $content[] = array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $file['url'],
                                'detail' => 'auto'
                            )
                        );
                    }
                    // Note: OpenRouter may support other file types depending on the underlying model
                }
            }
        }

        return $content;
    }

    /**
     * Normalize response format for OpenRouter
     *
     * @param mixed $response_format Response format specification
     * @return array Normalized response format
     */
    private function normalize_response_format($response_format) {
        if (is_string($response_format)) {
            switch ($response_format) {
                case 'json':
                    return array('type' => 'json_object');
                case 'text':
                    return array('type' => 'text');
                default:
                    return array('type' => 'text');
            }
        }

        if (is_array($response_format) && isset($response_format['type'])) {
            $valid_types = array('text', 'json_object');
            if (in_array($response_format['type'], $valid_types)) {
                return $response_format;
            }
        }

        // Default to text
        return array('type' => 'text');
    }

    /**
     * Normalize provider preferences for OpenRouter
     *
     * @param mixed $provider_preferences Provider routing preferences
     * @return array Normalized provider preferences
     */
    private function normalize_provider_preferences($provider_preferences) {
        if (is_string($provider_preferences)) {
            // Simple provider name
            return array('order' => array($provider_preferences));
        }

        if (is_array($provider_preferences)) {
            // Already in correct format or needs normalization
            if (isset($provider_preferences['order']) && is_array($provider_preferences['order'])) {
                return $provider_preferences;
            } elseif (array_values($provider_preferences) === $provider_preferences) {
                // Simple array of provider names
                return array('order' => $provider_preferences);
            }
        }

        return $provider_preferences;
    }

    /**
     * Validate OpenRouter request format
     *
     * @param array $request OpenRouter request
     * @return bool True if valid
     */
    public function validate_openrouter_request($request) {
        // Must have messages
        if (!isset($request['messages']) || !is_array($request['messages']) || empty($request['messages'])) {
            return false;
        }

        // Validate message format
        foreach ($request['messages'] as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get OpenRouter-specific request limits
     *
     * @return array Request limits
     */
    public function get_request_limits() {
        return array(
            'max_tokens' => 200000, // Varies by model, OpenRouter handles this
            'max_messages' => 1000,
            'max_tools' => 128,
            'max_stop_sequences' => 4,
            'max_images_per_message' => 20, // Varies by model
            'supported_image_formats' => array('jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp'),
            'max_image_size' => 20971520, // 20MB, varies by model
            'normalized_across_providers' => true
        );
    }

    /**
     * Estimate token count for request (approximate)
     *
     * @param array $request Normalized request
     * @return int Estimated token count
     */
    public function estimate_token_count($request) {
        $token_count = 0;

        if (isset($request['messages'])) {
            foreach ($request['messages'] as $message) {
                if (is_string($message['content'])) {
                    // Rough estimation: 1 token per 4 characters
                    $token_count += strlen($message['content']) / 4;
                } elseif (is_array($message['content'])) {
                    foreach ($message['content'] as $content_item) {
                        if ($content_item['type'] === 'text') {
                            $token_count += strlen($content_item['text']) / 4;
                        } elseif ($content_item['type'] === 'image_url') {
                            // Images vary by model, conservative estimate
                            $token_count += 170;
                        }
                    }
                }
            }
        }

        return intval($token_count);
    }

    /**
     * Apply OpenRouter-specific optimizations
     *
     * @param array $request Request data
     * @param array $options Optimization options
     * @return array Optimized request
     */
    public function apply_optimizations($request, $options = array()) {
        // OpenRouter-specific optimizations
        
        // Auto-fallbacks for reliability
        if (isset($options['enable_fallbacks']) && $options['enable_fallbacks'] && !isset($request['fallbacks'])) {
            $request['fallbacks'] = $this->get_recommended_fallbacks($request['model'] ?? null);
        }

        // Provider routing for cost optimization
        if (isset($options['cost_optimize']) && $options['cost_optimize'] && !isset($request['provider'])) {
            $request['route'] = 'fallback';
        }

        // Enable parallel tool calls by default if tools are present
        if (isset($request['tools']) && !isset($request['parallel_tool_calls'])) {
            $request['parallel_tool_calls'] = true;
        }

        return $request;
    }

    /**
     * Get recommended fallback models
     *
     * @param string|null $primary_model Primary model
     * @return array Recommended fallback models
     */
    private function get_recommended_fallbacks($primary_model) {
        // OpenRouter can recommend fallbacks, but we provide sensible defaults
        $fallbacks = array();

        // Note: These would typically be fetched from OpenRouter's model compatibility API
        // For now, we'll use generic fallbacks
        if (!empty($primary_model)) {
            // Add cost-effective alternatives
            $fallbacks[] = 'meta-llama/llama-2-13b-chat';
            $fallbacks[] = 'openai/gpt-3.5-turbo';
        }

        return $fallbacks;
    }

    /**
     * Convert legacy request formats to OpenRouter format
     *
     * @param array $legacy_request Legacy request format
     * @return array OpenRouter-compatible request
     */
    public function convert_legacy_format($legacy_request) {
        $converted = array();

        // Handle various legacy formats and convert to OpenRouter standard
        if (isset($legacy_request['prompt']) && !isset($legacy_request['messages'])) {
            // Convert prompt to messages format
            $converted['messages'] = array(
                array(
                    'role' => 'user',
                    'content' => $legacy_request['prompt']
                )
            );
        }

        // Merge with existing request data
        $converted = array_merge($legacy_request, $converted);

        return $converted;
    }
}