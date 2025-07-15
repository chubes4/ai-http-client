<?php
/**
 * AI HTTP Client - Generic Response Normalizer
 * 
 * Single Responsibility: Handle generic/unknown provider response normalization
 * Fallback for providers without specific normalizers
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Generic_Response_Normalizer {

    /**
     * Normalize response for generic/unknown provider
     *
     * @param array $provider_response Raw provider response
     * @return array Standardized response
     */
    public function normalize($provider_response) {
        // Attempt to extract content from various common response formats
        $content = $this->extract_content($provider_response);
        
        // Create standardized response structure
        return array(
            'data' => array(
                'content' => $content,
                'usage' => array(
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ),
                'model' => 'unknown',
                'finish_reason' => 'unknown',
                'tool_calls' => null
            ),
            'error' => null,
            'raw_response' => $provider_response
        );
    }

    /**
     * Extract content from unknown response format
     *
     * @param array $response Raw response
     * @return string Extracted content
     */
    private function extract_content($response) {
        // Try common content field names
        $content_fields = array('content', 'text', 'message', 'response', 'output', 'result');
        
        foreach ($content_fields as $field) {
            if (isset($response[$field])) {
                if (is_string($response[$field])) {
                    return $response[$field];
                }
                
                // Handle nested structures
                if (is_array($response[$field]) && isset($response[$field]['text'])) {
                    return $response[$field]['text'];
                }
            }
        }

        // Try to extract from choices (OpenAI-style)
        if (isset($response['choices']) && is_array($response['choices']) && !empty($response['choices'])) {
            $choice = $response['choices'][0];
            if (isset($choice['message']['content'])) {
                return $choice['message']['content'];
            }
            if (isset($choice['text'])) {
                return $choice['text'];
            }
        }

        // Try to extract from candidates (Gemini-style)
        if (isset($response['candidates']) && is_array($response['candidates']) && !empty($response['candidates'])) {
            $candidate = $response['candidates'][0];
            if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
                $content = '';
                foreach ($candidate['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $content .= $part['text'];
                    }
                }
                if (!empty($content)) {
                    return $content;
                }
            }
        }

        // Last resort: convert entire response to string
        if (is_string($response)) {
            return $response;
        }

        return 'Unable to extract content from response';
    }
}