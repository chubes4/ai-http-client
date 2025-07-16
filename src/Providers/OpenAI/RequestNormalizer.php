<?php
/**
 * AI HTTP Client - OpenAI Request Normalizer
 * 
 * Single Responsibility: Handle ONLY OpenAI request normalization
 * Follows SRP by focusing on one provider only
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Openai_Request_Normalizer {

    /**
     * Normalize request for OpenAI API
     *
     * @param array $standard_request Standardized request
     * @return array OpenAI-formatted request
     */
    public function normalize($standard_request) {
        // OpenAI uses our standard format, just validate and set defaults
        $normalized = $standard_request;

        // Inject model from provider configuration if not provided
        if (!isset($normalized['model'])) {
            $options_manager = new AI_HTTP_Options_Manager();
            $model = $options_manager->get_provider_setting('openai', 'model');
            if ($model) {
                $normalized['model'] = $model;
            }
        }

        // Validate and constrain parameters
        if (isset($normalized['temperature'])) {
            $normalized['temperature'] = max(0, min(2, floatval($normalized['temperature'])));
        }

        if (isset($normalized['max_tokens'])) {
            $normalized['max_tokens'] = max(1, intval($normalized['max_tokens']));
        }

        if (isset($normalized['top_p'])) {
            $normalized['top_p'] = max(0, min(1, floatval($normalized['top_p'])));
        }

        // Handle multi-modal content (images, files) in messages
        if (isset($normalized['messages']) && is_array($normalized['messages'])) {
            $normalized['messages'] = $this->normalize_messages($normalized['messages']);
        }

        // Handle function calling tools
        if (isset($normalized['tools']) && is_array($normalized['tools'])) {
            $normalized['tools'] = $this->normalize_tools($normalized['tools']);
        }

        return $normalized;
    }

    /**
     * Normalize messages to handle multi-modal content (images, files)
     * Based on Data Machine's working implementation
     *
     * @param array $messages Array of message objects
     * @return array OpenAI-formatted messages with multi-modal support
     */
    private function normalize_messages($messages) {
        $normalized = array();

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                $normalized[] = $message; // Pass through if missing required fields
                continue;
            }

            $normalized_message = array(
                'role' => $message['role']
            );

            // Handle multi-modal content
            if (isset($message['images']) || isset($message['image_urls']) || isset($message['files'])) {
                $normalized_message['content'] = $this->build_multimodal_content($message);
            } else {
                // Standard text content
                $normalized_message['content'] = $message['content'];
            }

            // Preserve other OpenAI-specific fields (tool_calls, tool_call_id, etc.)
            foreach ($message as $key => $value) {
                if (!in_array($key, array('role', 'content', 'images', 'image_urls', 'files'))) {
                    $normalized_message[$key] = $value;
                }
            }

            $normalized[] = $normalized_message;
        }

        return $normalized;
    }

    /**
     * Build multi-modal content array for OpenAI vision models
     * Based on Data Machine's image handling patterns
     *
     * @param array $message Message with multi-modal content
     * @return array OpenAI multi-modal content format
     */
    private function build_multimodal_content($message) {
        $content = array();

        // Add text content first
        if (!empty($message['content'])) {
            $content[] = array(
                'type' => 'text',
                'text' => $message['content']
            );
        }

        // Handle image URLs (Data Machine pattern)
        if (isset($message['image_urls']) && is_array($message['image_urls'])) {
            foreach ($message['image_urls'] as $image_url) {
                $content[] = array(
                    'type' => 'image_url',
                    'image_url' => array(
                        'url' => $image_url,
                        'detail' => 'auto' // Can be 'low', 'high', or 'auto'
                    )
                );
            }
        }

        // Handle base64 images (alternative format)
        if (isset($message['images']) && is_array($message['images'])) {
            foreach ($message['images'] as $image) {
                if (is_string($image)) {
                    // Direct base64 or URL
                    $url = strpos($image, 'data:') === 0 ? $image : $image;
                    $content[] = array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => $url,
                            'detail' => 'auto'
                        )
                    );
                } elseif (is_array($image) && isset($image['url'])) {
                    // Structured image object
                    $content[] = array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => $image['url'],
                            'detail' => isset($image['detail']) ? $image['detail'] : 'auto'
                        )
                    );
                }
            }
        }

        // Handle file references (for PDF processing like Data Machine)
        if (isset($message['files']) && is_array($message['files'])) {
            foreach ($message['files'] as $file) {
                if (isset($file['type']) && $file['type'] === 'file_url') {
                    // File URL reference (for uploaded files)
                    $content[] = array(
                        'type' => 'text',
                        'text' => '[File: ' . (isset($file['name']) ? $file['name'] : 'uploaded file') . ']'
                    );
                }
            }
        }

        return $content;
    }

    /**
     * Normalize tools for OpenAI function calling format
     *
     * @param array $tools Array of tool definitions
     * @return array OpenAI-formatted tools
     */
    private function normalize_tools($tools) {
        $normalized = array();

        foreach ($tools as $tool) {
            try {
                $normalized[] = $this->normalize_single_tool($tool);
            } catch (Exception $e) {
                error_log('OpenAI tool normalization error: ' . $e->getMessage());
            }
        }

        return $normalized;
    }

    /**
     * Normalize a single tool to OpenAI format
     *
     * @param array $tool Tool definition
     * @return array OpenAI-formatted tool
     */
    private function normalize_single_tool($tool) {
        // Handle if tool is already in OpenAI format
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return array(
                'type' => 'function',
                'function' => array(
                    'name' => sanitize_text_field($tool['function']['name']),
                    'description' => sanitize_textarea_field($tool['function']['description']),
                    'parameters' => $tool['function']['parameters'] ?? array()
                )
            );
        }
        
        // Handle direct function definition
        if (isset($tool['name']) && isset($tool['description'])) {
            return array(
                'type' => 'function',
                'function' => array(
                    'name' => sanitize_text_field($tool['name']),
                    'description' => sanitize_textarea_field($tool['description']),
                    'parameters' => $tool['parameters'] ?? array()
                )
            );
        }
        
        throw new Exception('Invalid tool definition for OpenAI format');
    }

}