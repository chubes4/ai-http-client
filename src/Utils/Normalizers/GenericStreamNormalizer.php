<?php
/**
 * AI HTTP Client - Generic Stream Normalizer
 * 
 * Single Responsibility: Convert all provider streaming formats to universal standard
 * This creates a "round plug in round hole" system for streaming responses
 *
 * @package AIHttpClient\Utils\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Generic_Stream_Normalizer {

    /**
     * Standard streaming response format
     *
     * @var array
     */
    public static $standard_format = array(
        'content' => '',        // Text content from AI
        'done' => false,        // Whether response is complete
        'provider' => '',       // Provider name (openai, gemini, anthropic, etc.)
        'tool_calls' => array(), // Tool/function calls if any
        'metadata' => array(    // Additional provider-specific data
            'model' => '',
            'tokens' => 0,
            'response_id' => '',
            'usage' => array()
        )
    );

    /**
     * Normalize streaming data from any provider to standard format
     *
     * @param string $data Raw streaming data from provider
     * @param string $provider Provider name
     * @return array Array of normalized chunks
     */
    public static function normalize_streaming_data($data, $provider) {
        $chunks = array();
        
        // Parse SSE events from the data
        $lines = explode("\n", $data);
        
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $json_data = trim(substr($line, 6));
                
                if ($json_data === '[DONE]') {
                    // Standard completion marker
                    $chunks[] = self::create_done_chunk($provider);
                    continue;
                }
                
                $decoded = json_decode($json_data, true);
                if ($decoded && json_last_error() === JSON_ERROR_NONE) {
                    $normalized = self::normalize_chunk_by_provider($decoded, $provider);
                    if ($normalized) {
                        $chunks[] = $normalized;
                    }
                }
            }
        }
        
        return $chunks;
    }
    
    /**
     * Normalize tool call to universal format
     *
     * @param array $tool_call Provider-specific tool call data
     * @param string $provider Provider name
     * @return array Normalized tool call
     */
    private static function normalize_tool_call($tool_call, $provider) {
        switch ($provider) {
            case 'openai':
                return array(
                    'call_id' => $tool_call['id'] ?? '',
                    'name' => $tool_call['function']['name'] ?? '',
                    'arguments' => $tool_call['function']['arguments'] ?? '{}'
                );
            case 'gemini':
                return array(
                    'call_id' => $tool_call['name'] ?? uniqid('gemini_'),
                    'name' => $tool_call['name'] ?? '',
                    'arguments' => json_encode($tool_call['args'] ?? array())
                );
            case 'anthropic':
                return array(
                    'call_id' => $tool_call['id'] ?? '',
                    'name' => $tool_call['name'] ?? '',
                    'arguments' => json_encode($tool_call['input'] ?? array())
                );
            case 'grok':
                return array(
                    'call_id' => $tool_call['id'] ?? '',
                    'name' => $tool_call['function']['name'] ?? '',
                    'arguments' => $tool_call['function']['arguments'] ?? '{}'
                );
            case 'openrouter':
                return array(
                    'call_id' => $tool_call['id'] ?? '',
                    'name' => $tool_call['function']['name'] ?? '',
                    'arguments' => $tool_call['function']['arguments'] ?? '{}'
                );
            default:
                // Generic format - try to match common structures
                return array(
                    'call_id' => $tool_call['id'] ?? $tool_call['call_id'] ?? uniqid('tool_'),
                    'name' => $tool_call['name'] ?? $tool_call['function']['name'] ?? '',
                    'arguments' => $tool_call['arguments'] ?? $tool_call['function']['arguments'] ?? json_encode($tool_call['args'] ?? array())
                );
        }
    }
    
    /**
     * Create a standard "done" chunk
     *
     * @param string $provider Provider name
     * @return array Done chunk
     */
    private static function create_done_chunk($provider) {
        return array(
            'content' => '',
            'done' => true,
            'provider' => $provider,
            'tool_calls' => array(),
            'metadata' => array()
        );
    }
    
    /**
     * Normalize a single chunk based on provider format
     *
     * @param array $chunk Decoded chunk data
     * @param string $provider Provider name
     * @return array|null Normalized chunk or null if invalid
     */
    public static function normalize_chunk_by_provider($chunk, $provider) {
        error_log('AI HTTP Client: normalize_chunk_by_provider called with provider: ' . $provider);
        switch ($provider) {
            case 'openai':
                return self::normalize_openai_chunk($chunk);
            case 'gemini':
                error_log('AI HTTP Client: About to call normalize_gemini_chunk');
                $result = self::normalize_gemini_chunk($chunk);
                error_log('AI HTTP Client: normalize_gemini_chunk returned: ' . json_encode($result));
                return $result;
            case 'anthropic':
                return self::normalize_anthropic_chunk($chunk);
            case 'grok':
                return self::normalize_grok_chunk($chunk);
            case 'openrouter':
                return self::normalize_openrouter_chunk($chunk);
            default:
                // Try to guess the format
                return self::normalize_unknown_chunk($chunk, $provider);
        }
    }
    
    /**
     * Normalize OpenAI streaming chunk
     *
     * @param array $chunk OpenAI chunk data
     * @return array Normalized chunk
     */
    private static function normalize_openai_chunk($chunk) {
        $content = '';
        $done = false;
        $tool_calls = array();
        
        if (isset($chunk['choices'][0]['delta']['content'])) {
            $content = $chunk['choices'][0]['delta']['content'];
        }
        
        if (isset($chunk['choices'][0]['finish_reason']) && $chunk['choices'][0]['finish_reason'] === 'stop') {
            $done = true;
        }
        
        if (isset($chunk['choices'][0]['delta']['tool_calls'])) {
            foreach ($chunk['choices'][0]['delta']['tool_calls'] as $tool_call) {
                $tool_calls[] = self::normalize_tool_call($tool_call, 'openai');
            }
        }
        
        return array(
            'content' => $content,
            'done' => $done,
            'provider' => 'openai',
            'tool_calls' => $tool_calls,
            'metadata' => array(
                'model' => $chunk['model'] ?? '',
                'tokens' => $chunk['usage']['total_tokens'] ?? 0,
                'response_id' => $chunk['id'] ?? '',
                'usage' => $chunk['usage'] ?? array()
            )
        );
    }
    
    /**
     * Normalize Gemini streaming chunk
     *
     * @param array $chunk Gemini chunk data
     * @return array Normalized chunk
     */
    private static function normalize_gemini_chunk($chunk) {
        $content = '';
        $done = false;
        $tool_calls = array();
        
        if (isset($chunk['candidates'][0]['content']['parts'])) {
            foreach ($chunk['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $content .= $part['text'];
                }
                if (isset($part['functionCall'])) {
                    $tool_calls[] = self::normalize_tool_call($part['functionCall'], 'gemini');
                }
            }
        }
        
        if (isset($chunk['candidates'][0]['finishReason'])) {
            $done = true;
        }
        
        return array(
            'content' => $content,
            'done' => $done,
            'provider' => 'gemini',
            'tool_calls' => $tool_calls,
            'metadata' => array(
                'model' => $chunk['modelVersion'] ?? '',
                'tokens' => $chunk['usageMetadata']['totalTokenCount'] ?? 0,
                'response_id' => $chunk['responseId'] ?? '',
                'usage' => $chunk['usageMetadata'] ?? array()
            )
        );
    }
    
    /**
     * Normalize Anthropic streaming chunk
     *
     * @param array $chunk Anthropic chunk data
     * @return array Normalized chunk
     */
    private static function normalize_anthropic_chunk($chunk) {
        $content = '';
        $done = false;
        $tool_calls = array();
        
        if (isset($chunk['delta']['text'])) {
            $content = $chunk['delta']['text'];
        }
        
        if (isset($chunk['type']) && $chunk['type'] === 'message_stop') {
            $done = true;
        }
        
        if (isset($chunk['delta']['tool_calls'])) {
            foreach ($chunk['delta']['tool_calls'] as $tool_call) {
                $tool_calls[] = self::normalize_tool_call($tool_call, 'anthropic');
            }
        }
        
        return array(
            'content' => $content,
            'done' => $done,
            'provider' => 'anthropic',
            'tool_calls' => $tool_calls,
            'metadata' => array(
                'model' => $chunk['model'] ?? '',
                'tokens' => $chunk['usage']['output_tokens'] ?? 0,
                'response_id' => $chunk['message']['id'] ?? '',
                'usage' => $chunk['usage'] ?? array()
            )
        );
    }
    
    /**
     * Normalize Grok streaming chunk
     *
     * @param array $chunk Grok chunk data
     * @return array Normalized chunk
     */
    private static function normalize_grok_chunk($chunk) {
        // Grok uses OpenAI-compatible format
        $normalized = self::normalize_openai_chunk($chunk);
        $normalized['provider'] = 'grok';
        return $normalized;
    }
    
    /**
     * Normalize OpenRouter streaming chunk
     *
     * @param array $chunk OpenRouter chunk data
     * @return array Normalized chunk
     */
    private static function normalize_openrouter_chunk($chunk) {
        // OpenRouter uses OpenAI-compatible format
        $normalized = self::normalize_openai_chunk($chunk);
        $normalized['provider'] = 'openrouter';
        return $normalized;
    }
    
    /**
     * Try to normalize unknown chunk format
     *
     * @param array $chunk Unknown chunk data
     * @param string $provider Provider name
     * @return array Normalized chunk
     */
    private static function normalize_unknown_chunk($chunk, $provider) {
        // Basic fallback - try to extract content from common fields
        $content = '';
        
        if (isset($chunk['content'])) {
            $content = $chunk['content'];
        } elseif (isset($chunk['text'])) {
            $content = $chunk['text'];
        } elseif (isset($chunk['message'])) {
            $content = $chunk['message'];
        }
        
        return array(
            'content' => $content,
            'done' => false,
            'provider' => $provider,
            'tool_calls' => array(),
            'metadata' => array()
        );
    }
    
    /**
     * Validate that a chunk matches the standard format
     *
     * @param array $chunk Chunk to validate
     * @return bool True if valid
     */
    public static function validate_standard_chunk($chunk) {
        $required_fields = array('content', 'done', 'provider', 'tool_calls', 'metadata');
        
        foreach ($required_fields as $field) {
            if (!isset($chunk[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get the standard format specification
     *
     * @return array Standard format specification
     */
    public static function get_standard_format() {
        return self::$standard_format;
    }
}