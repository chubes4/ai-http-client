<?php
/**
 * AI HTTP Client - Google Gemini Response Normalizer
 * 
 * Single Responsibility: Handle ONLY Gemini response normalization
 * Converts Gemini's response format to standardized format
 *
 * @package AIHttpClient\Providers\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Gemini_Response_Normalizer {

    /**
     * Normalize response from Gemini API
     *
     * @param array $gemini_response Raw Gemini response
     * @return array Standardized response format
     */
    public function normalize($gemini_response) {
        if (is_wp_error($gemini_response)) {
            return array(
                'success' => false,
                'data' => null,
                'error' => $gemini_response->get_error_message(),
                'provider' => 'gemini',
                'raw_response' => null
            );
        }

        $response_code = wp_remote_retrieve_response_code($gemini_response);
        $response_body = wp_remote_retrieve_body($gemini_response);
        $decoded_response = json_decode($response_body, true);

        if ($response_code >= 400) {
            return $this->handle_error_response($decoded_response, $response_code);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'data' => null,
                'error' => 'Invalid JSON response from Gemini API',
                'provider' => 'gemini',
                'raw_response' => $response_body
            );
        }

        return $this->format_success_response($decoded_response);
    }

    /**
     * Handle error response from Gemini
     *
     * @param array $response Decoded error response
     * @param int $status_code HTTP status code
     * @return array Standardized error response
     */
    private function handle_error_response($response, $status_code) {
        $error_message = 'Gemini API error';
        
        if (isset($response['error']['message'])) {
            $error_message = $response['error']['message'];
        } elseif (isset($response['error']['details'])) {
            $error_message = is_array($response['error']['details']) 
                ? implode(', ', $response['error']['details'])
                : $response['error']['details'];
        }

        return array(
            'success' => false,
            'data' => null,
            'error' => $error_message,
            'provider' => 'gemini',
            'raw_response' => $response,
            'status_code' => $status_code
        );
    }

    /**
     * Format successful response from Gemini
     *
     * @param array $response Decoded Gemini response
     * @return array Standardized success response
     */
    private function format_success_response($response) {
        $content = '';
        $tool_calls = array();
        $usage = array();
        $model = '';

        // Extract content from candidates
        if (isset($response['candidates']) && is_array($response['candidates'])) {
            foreach ($response['candidates'] as $candidate) {
                if (isset($candidate['content']['parts'])) {
                    foreach ($candidate['content']['parts'] as $part) {
                        if (isset($part['text'])) {
                            $content .= $part['text'];
                        }
                        
                        // Extract function calls
                        if (isset($part['functionCall'])) {
                            $tool_calls[] = $this->format_function_call($part['functionCall']);
                        }
                    }
                }
            }
        }

        // Extract usage information
        if (isset($response['usageMetadata'])) {
            $usage = array(
                'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens' => $response['usageMetadata']['totalTokenCount'] ?? 0
            );
        }

        // Extract model information
        if (isset($response['modelVersion'])) {
            $model = $response['modelVersion'];
        }

        $result = array(
            'success' => true,
            'data' => array(
                'content' => $content,
                'usage' => $usage,
                'model' => $model
            ),
            'error' => null,
            'provider' => 'gemini',
            'raw_response' => $response
        );

        // Add tool calls if any were found
        if (!empty($tool_calls)) {
            $result['data']['tool_calls'] = $tool_calls;
        }

        return $result;
    }

    /**
     * Format Gemini function call to standard format
     *
     * @param array $function_call Gemini function call
     * @return array Standardized tool call format
     */
    private function format_function_call($function_call) {
        return array(
            'id' => $function_call['name'] . '_' . uniqid(),
            'type' => 'function',
            'function' => array(
                'name' => $function_call['name'],
                'arguments' => wp_json_encode($function_call['args'] ?? array())
            )
        );
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
            'tool_calls' => array()
        );

        // Extract content from candidates
        if (isset($decoded['candidates']) && is_array($decoded['candidates'])) {
            foreach ($decoded['candidates'] as $candidate) {
                // Check if streaming is complete
                if (isset($candidate['finishReason'])) {
                    $result['done'] = true;
                    $result['finish_reason'] = $candidate['finishReason'];
                }

                if (isset($candidate['content']['parts'])) {
                    foreach ($candidate['content']['parts'] as $part) {
                        if (isset($part['text'])) {
                            $result['content'] .= $part['text'];
                        }
                        
                        // Extract function calls from streaming
                        if (isset($part['functionCall'])) {
                            $result['tool_calls'][] = $this->format_function_call($part['functionCall']);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Extract safety ratings from response
     *
     * @param array $response Gemini response
     * @return array Safety ratings
     */
    public function extract_safety_ratings($response) {
        $safety_ratings = array();

        if (isset($response['candidates']) && is_array($response['candidates'])) {
            foreach ($response['candidates'] as $candidate) {
                if (isset($candidate['safetyRatings'])) {
                    $safety_ratings = array_merge($safety_ratings, $candidate['safetyRatings']);
                }
            }
        }

        return $safety_ratings;
    }

    /**
     * Check if response was blocked by safety filters
     *
     * @param array $response Gemini response
     * @return bool True if blocked
     */
    public function is_blocked_by_safety($response) {
        if (isset($response['candidates']) && is_array($response['candidates'])) {
            foreach ($response['candidates'] as $candidate) {
                if (isset($candidate['finishReason']) && $candidate['finishReason'] === 'SAFETY') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract citation metadata from response
     *
     * @param array $response Gemini response
     * @return array Citation information
     */
    public function extract_citations($response) {
        $citations = array();

        if (isset($response['candidates']) && is_array($response['candidates'])) {
            foreach ($response['candidates'] as $candidate) {
                if (isset($candidate['citationMetadata']['citationSources'])) {
                    $citations = array_merge($citations, $candidate['citationMetadata']['citationSources']);
                }
            }
        }

        return $citations;
    }

    /**
     * Validate Gemini response structure
     *
     * @param array $response Gemini response
     * @return bool True if valid structure
     */
    public function validate_response_structure($response) {
        // Basic structure validation
        if (!is_array($response)) {
            return false;
        }

        // Should have candidates array for successful responses
        if (isset($response['candidates'])) {
            if (!is_array($response['candidates'])) {
                return false;
            }
            
            foreach ($response['candidates'] as $candidate) {
                if (!isset($candidate['content']) || !is_array($candidate['content'])) {
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
}