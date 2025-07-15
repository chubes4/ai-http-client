<?php
/**
 * AI HTTP Client - Grok/X.AI Request Normalizer
 * 
 * Single Responsibility: Handle ONLY Grok request normalization
 * Converts standard format to Grok's OpenAI-compatible requirements
 * Supports multi-modal content, function calling, and reasoning effort
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Grok_Request_Normalizer {

    /**
     * Normalize request for Grok API
     *
     * @param array $standard_request Standardized request
     * @return array Grok-formatted request
     */
    public function normalize($standard_request) {
        $normalized = array();

        // Model will be set by automatic model detection if not provided
        $normalized['model'] = $standard_request['model'] ?? 'grok-3';

        // Convert messages to Grok format (OpenAI-compatible)
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
            $normalized['stop'] = array_slice($standard_request['stop'], 0, 4); // Grok supports up to 4 stop sequences
        }

        // Handle streaming
        if (isset($standard_request['stream'])) {
            $normalized['stream'] = (bool) $standard_request['stream'];
        }

        // Handle tools for function calling
        if (isset($standard_request['tools']) && is_array($standard_request['tools'])) {
            $normalized['tools'] = AI_HTTP_Grok_Function_Calling::sanitize_tools($standard_request['tools']);
        }

        // Handle tool choice
        if (isset($standard_request['tool_choice'])) {
            $normalized['tool_choice'] = AI_HTTP_Grok_Function_Calling::validate_tool_choice($standard_request['tool_choice']);
        }

        // Handle parallel tool calls
        if (isset($standard_request['parallel_tool_calls'])) {
            $normalized['parallel_tool_calls'] = (bool) $standard_request['parallel_tool_calls'];
        }

        // Handle Grok-specific reasoning effort
        if (isset($standard_request['reasoning_effort'])) {
            $valid_efforts = array('low', 'medium', 'high');
            if (in_array($standard_request['reasoning_effort'], $valid_efforts)) {
                $normalized['reasoning_effort'] = $standard_request['reasoning_effort'];
            }
        }

        // Handle user identifier (Grok-specific)
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

        return $normalized;
    }

    /**
     * Normalize messages for Grok API (OpenAI-compatible format)
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
     * Normalize role names for Grok (OpenAI-compatible)
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
     * Build multi-modal content for Grok (OpenAI-compatible format)
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
                        'detail' => 'auto' // Grok supports auto, low, high
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

        // Handle file references (for future file upload support)
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
                    // Note: Grok doesn't currently support non-image file uploads via API
                }
            }
        }

        return $content;
    }

    /**
     * Normalize response format for Grok
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
     * Validate Grok request format
     *
     * @param array $request Grok request
     * @return bool True if valid
     */
    public function validate_grok_request($request) {
        // Must have model
        if (!isset($request['model']) || empty($request['model'])) {
            return false;
        }

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
     * Get Grok-specific request limits
     *
     * @return array Request limits
     */
    public function get_request_limits() {
        return array(
            'max_tokens' => 131072, // Maximum context length
            'max_messages' => 1000,
            'max_tools' => 128,
            'max_stop_sequences' => 4,
            'max_images_per_message' => 20, // Grok vision supports up to 20 images
            'supported_image_formats' => array('jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp'),
            'max_image_size' => 20971520 // 20MB
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
                            // Images use approximately 85-170 tokens depending on size
                            $token_count += 170; // Conservative estimate
                        }
                    }
                }
            }
        }

        return intval($token_count);
    }
}