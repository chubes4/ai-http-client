<?php
/**
 * AI HTTP Client - Google Gemini Request Normalizer
 * 
 * Single Responsibility: Handle ONLY Gemini request normalization
 * Converts standard format to Gemini's specific requirements (contents, parts format)
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Gemini_Request_Normalizer {

    /**
     * Normalize request for Gemini API
     *
     * @param array $standard_request Standardized request
     * @return array Gemini-formatted request
     */
    public function normalize($standard_request) {
        $normalized = array();

        // Model must be explicitly provided - no defaults
        if (!isset($standard_request['model'])) {
            throw new Exception('Model parameter is required for Gemini requests');
        }
        $model = $standard_request['model'];
        
        // Preserve model parameter for Provider URL construction
        $normalized['model'] = $model;

        // Convert messages to Gemini contents format
        if (isset($standard_request['messages']) && is_array($standard_request['messages'])) {
            $normalized['contents'] = $this->convert_messages_to_contents($standard_request['messages']);
        }

        // Handle generation config parameters
        $generation_config = array();
        
        if (isset($standard_request['temperature'])) {
            $generation_config['temperature'] = max(0, min(2, floatval($standard_request['temperature'])));
        }

        if (isset($standard_request['max_tokens'])) {
            $generation_config['maxOutputTokens'] = max(1, intval($standard_request['max_tokens']));
        }

        if (isset($standard_request['top_p'])) {
            $generation_config['topP'] = max(0, min(1, floatval($standard_request['top_p'])));
        }

        if (isset($standard_request['top_k'])) {
            $generation_config['topK'] = max(1, intval($standard_request['top_k']));
        }

        if (isset($standard_request['stop_sequences']) && is_array($standard_request['stop_sequences'])) {
            $generation_config['stopSequences'] = $standard_request['stop_sequences'];
        }

        if (!empty($generation_config)) {
            $normalized['generationConfig'] = $generation_config;
        }

        // Handle tools for function calling
        if (isset($standard_request['tools']) && is_array($standard_request['tools'])) {
            $normalized['tools'] = AI_HTTP_Gemini_Function_Calling::sanitize_tools($standard_request['tools']);
        }

        // Handle tool choice -> tool config
        if (isset($standard_request['tool_choice'])) {
            $normalized['toolConfig'] = AI_HTTP_Gemini_Function_Calling::validate_tool_choice($standard_request['tool_choice']);
        }

        // Handle safety settings if provided
        if (isset($standard_request['safety_settings'])) {
            $normalized['safetySettings'] = $this->normalize_safety_settings($standard_request['safety_settings']);
        }

        // Handle system instructions (Gemini's equivalent of system messages)
        if (isset($standard_request['system_instruction'])) {
            $normalized['systemInstruction'] = array(
                'parts' => array(
                    array('text' => $standard_request['system_instruction'])
                )
            );
        }

        return $normalized;
    }

    /**
     * Convert standard messages format to Gemini contents format
     * Gemini uses contents array with role and parts structure
     *
     * @param array $messages Array of messages
     * @return array Gemini contents format
     */
    private function convert_messages_to_contents($messages) {
        $contents = array();

        foreach ($messages as $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                continue;
            }

            $role = $message['role'];
            $content = $message['content'];

            // Skip system messages - they should be handled as systemInstruction
            if ($role === 'system') {
                continue;
            }

            // Convert role names to Gemini format
            $gemini_role = $this->convert_role_to_gemini($role);
            
            // Build content entry
            $content_entry = array(
                'role' => $gemini_role
            );

            // Handle multi-modal content
            if (isset($message['images']) || isset($message['image_urls']) || isset($message['files'])) {
                $content_entry['parts'] = $this->build_multimodal_parts($message);
            } else {
                // Standard text content
                $content_entry['parts'] = array(
                    array('text' => $content)
                );
            }

            $contents[] = $content_entry;
        }

        return $contents;
    }

    /**
     * Convert role names to Gemini format
     *
     * @param string $role Standard role name
     * @return string Gemini role name
     */
    private function convert_role_to_gemini($role) {
        switch ($role) {
            case 'user':
                return 'user';
            case 'assistant':
                return 'model';
            case 'tool':
                return 'function'; // Gemini uses 'function' for tool responses
            default:
                return 'user'; // Default to user for unknown roles
        }
    }

    /**
     * Build multi-modal parts for Gemini
     * Handles images, files, and other media types
     *
     * @param array $message Message with multi-modal content
     * @return array Gemini parts format
     */
    private function build_multimodal_parts($message) {
        $parts = array();

        // Add text content first if present
        if (!empty($message['content'])) {
            $parts[] = array('text' => $message['content']);
        }

        // Handle image URLs
        if (isset($message['image_urls']) && is_array($message['image_urls'])) {
            foreach ($message['image_urls'] as $image_url) {
                $parts[] = array(
                    'fileData' => array(
                        'fileUri' => $image_url,
                        'mimeType' => $this->guess_mime_type_from_url($image_url)
                    )
                );
            }
        }

        // Handle base64 images
        if (isset($message['images']) && is_array($message['images'])) {
            foreach ($message['images'] as $image) {
                if (is_string($image)) {
                    if (strpos($image, 'data:') === 0) {
                        // Extract media type and base64 data
                        if (preg_match('/^data:([^;]+);base64,(.+)$/', $image, $matches)) {
                            $parts[] = array(
                                'inlineData' => array(
                                    'mimeType' => $matches[1],
                                    'data' => $matches[2]
                                )
                            );
                        }
                    } else {
                        // Assume it's a URL
                        $parts[] = array(
                            'fileData' => array(
                                'fileUri' => $image,
                                'mimeType' => $this->guess_mime_type_from_url($image)
                            )
                        );
                    }
                } elseif (is_array($image) && isset($image['url'])) {
                    $parts[] = array(
                        'fileData' => array(
                            'fileUri' => $image['url'],
                            'mimeType' => $image['mime_type'] ?? $this->guess_mime_type_from_url($image['url'])
                        )
                    );
                }
            }
        }

        // Handle file references
        if (isset($message['files']) && is_array($message['files'])) {
            foreach ($message['files'] as $file) {
                if (isset($file['uri'])) {
                    $parts[] = array(
                        'fileData' => array(
                            'fileUri' => $file['uri'],
                            'mimeType' => $file['mime_type'] ?? 'application/octet-stream'
                        )
                    );
                } elseif (isset($file['url'])) {
                    $parts[] = array(
                        'fileData' => array(
                            'fileUri' => $file['url'],
                            'mimeType' => $file['mime_type'] ?? $this->guess_mime_type_from_url($file['url'])
                        )
                    );
                }
            }
        }

        return $parts;
    }

    /**
     * Guess MIME type from URL
     *
     * @param string $url File URL
     * @return string MIME type
     */
    private function guess_mime_type_from_url($url) {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        $mime_types = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac'
        );

        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }

    /**
     * Normalize safety settings for Gemini
     *
     * @param array $safety_settings Safety settings
     * @return array Gemini safety settings format
     */
    private function normalize_safety_settings($safety_settings) {
        $normalized = array();

        foreach ($safety_settings as $setting) {
            if (isset($setting['category']) && isset($setting['threshold'])) {
                $normalized[] = array(
                    'category' => $setting['category'],
                    'threshold' => $setting['threshold']
                );
            }
        }

        return $normalized;
    }

    /**
     * Convert function call results to Gemini format
     * For tool result handling in conversations
     *
     * @param array $tool_results Array of tool results
     * @return array Gemini function response parts
     */
    public function convert_tool_results_to_parts($tool_results) {
        return AI_HTTP_Gemini_Function_Calling::build_tool_result_parts($tool_results);
    }

    /**
     * Validate Gemini request format
     *
     * @param array $request Gemini request
     * @return bool True if valid
     */
    public function validate_gemini_request($request) {
        // Must have contents
        if (!isset($request['contents']) || !is_array($request['contents']) || empty($request['contents'])) {
            return false;
        }

        // Each content must have role and parts
        foreach ($request['contents'] as $content) {
            if (!isset($content['role']) || !isset($content['parts']) || !is_array($content['parts'])) {
                return false;
            }
        }

        return true;
    }
}