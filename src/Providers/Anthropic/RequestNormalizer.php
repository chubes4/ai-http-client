<?php
/**
 * AI HTTP Client - Anthropic Request Normalizer
 * 
 * Single Responsibility: Handle ONLY Anthropic request normalization
 * Converts standard format to Anthropic's specific requirements
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Anthropic_Request_Normalizer {

    /**
     * Normalize request for Anthropic API
     *
     * @param array $standard_request Standardized request
     * @return array Anthropic-formatted request
     */
    public function normalize($standard_request) {
        $normalized = $standard_request;

        // Model will be set by automatic model detection if not provided

        // Note: Anthropic requires max_tokens, but let user decide when to set it
        // Only validate if provided, don't force a default

        // Validate and constrain parameters for Anthropic (0.0 to 1.0)
        if (isset($normalized['temperature'])) {
            $normalized['temperature'] = max(0, min(1, floatval($normalized['temperature'])));
        }

        if (isset($normalized['max_tokens'])) {
            $normalized['max_tokens'] = max(1, min(4096, intval($normalized['max_tokens'])));
        }

        if (isset($normalized['top_p'])) {
            $normalized['top_p'] = max(0, min(1, floatval($normalized['top_p'])));
        }

        // Handle multi-modal content (images, files) in messages
        if (isset($normalized['messages']) && is_array($normalized['messages'])) {
            $normalized['messages'] = $this->normalize_messages($normalized['messages']);
        }

        // Extract system messages to separate system field
        $normalized = $this->extract_system_message($normalized);

        // Handle tools for function calling
        if (isset($normalized['tools']) && is_array($normalized['tools'])) {
            $normalized['tools'] = $this->normalize_tools($normalized['tools']);
        }

        return $normalized;
    }

    /**
     * Normalize messages to handle multi-modal content (images, files)
     * Based on Anthropic's vision capabilities and content block format
     *
     * @param array $messages Array of message objects
     * @return array Anthropic-formatted messages with multi-modal support
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

            // Handle multi-modal content for Anthropic
            if (isset($message['images']) || isset($message['image_urls']) || isset($message['files'])) {
                $normalized_message['content'] = $this->build_anthropic_multimodal_content($message);
            } else {
                // Standard text content
                $normalized_message['content'] = $message['content'];
            }

            // Preserve other Anthropic-specific fields
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
     * Build multi-modal content array for Anthropic Vision models
     * Anthropic uses content blocks format
     *
     * @param array $message Message with multi-modal content
     * @return array Anthropic multi-modal content format
     */
    private function build_anthropic_multimodal_content($message) {
        $content = array();

        // Add text content first
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
                    'type' => 'image',
                    'source' => array(
                        'type' => 'url',
                        'url' => $image_url
                    )
                );
            }
        }

        // Handle base64 images
        if (isset($message['images']) && is_array($message['images'])) {
            foreach ($message['images'] as $image) {
                if (is_string($image)) {
                    // Check if it's base64 data URL
                    if (strpos($image, 'data:') === 0) {
                        // Extract media type and base64 data
                        if (preg_match('/^data:([^;]+);base64,(.+)$/', $image, $matches)) {
                            $content[] = array(
                                'type' => 'image',
                                'source' => array(
                                    'type' => 'base64',
                                    'media_type' => $matches[1],
                                    'data' => $matches[2]
                                )
                            );
                        }
                    } else {
                        // Assume it's a URL
                        $content[] = array(
                            'type' => 'image',
                            'source' => array(
                                'type' => 'url',
                                'url' => $image
                            )
                        );
                    }
                } elseif (is_array($image) && isset($image['url'])) {
                    // Structured image object
                    $content[] = array(
                        'type' => 'image',
                        'source' => array(
                            'type' => 'url',
                            'url' => $image['url']
                        )
                    );
                }
            }
        }

        // Handle file references (Anthropic doesn't support direct file uploads like OpenAI)
        if (isset($message['files']) && is_array($message['files'])) {
            foreach ($message['files'] as $file) {
                if (isset($file['type']) && $file['type'] === 'file_url') {
                    // Convert file reference to text mention
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
     * Extract system message from messages array to system field
     * Anthropic uses a separate system field instead of system role in messages
     *
     * @param array $request Request data
     * @return array Request with system message extracted
     */
    private function extract_system_message($request) {
        $system_content = '';
        
        // Handle system_instruction field first (takes precedence)
        if (isset($request['system_instruction'])) {
            $system_content = $request['system_instruction'];
            unset($request['system_instruction']);
        }
        
        // Handle system messages in messages array
        if (isset($request['messages']) && is_array($request['messages'])) {
            $filtered_messages = array();

            foreach ($request['messages'] as $message) {
                if (isset($message['role']) && $message['role'] === 'system') {
                    $system_content .= ($system_content ? "\n" : '') . $message['content'];
                } else {
                    $filtered_messages[] = $message;
                }
            }

            $request['messages'] = $filtered_messages;
        }

        if (!empty($system_content)) {
            $request['system'] = trim($system_content);
        }

        return $request;
    }

    /**
     * Normalize tools for Anthropic format
     *
     * @param array $tools Array of tool definitions
     * @return array Anthropic-formatted tools
     */
    private function normalize_tools($tools) {
        $normalized = array();

        foreach ($tools as $tool) {
            try {
                $normalized[] = $this->normalize_single_tool($tool);
            } catch (Exception $e) {
                error_log('Anthropic tool normalization error: ' . $e->getMessage());
            }
        }

        return $normalized;
    }

    /**
     * Normalize a single tool to Anthropic format
     *
     * @param array $tool Tool definition
     * @return array Anthropic-formatted tool
     */
    private function normalize_single_tool($tool) {
        // Handle if tool is in OpenAI format
        if (isset($tool['type']) && $tool['type'] === 'function' && isset($tool['function'])) {
            return array(
                'name' => sanitize_text_field($tool['function']['name']),
                'description' => sanitize_textarea_field($tool['function']['description']),
                'input_schema' => $tool['function']['parameters'] ?? array()
            );
        }
        
        // Handle direct function definition
        if (isset($tool['name']) && isset($tool['description'])) {
            return array(
                'name' => sanitize_text_field($tool['name']),
                'description' => sanitize_textarea_field($tool['description']),
                'input_schema' => $tool['parameters'] ?? $tool['input_schema'] ?? array()
            );
        }
        
        throw new Exception('Invalid tool definition for Anthropic format');
    }

}