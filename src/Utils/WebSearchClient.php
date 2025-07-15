<?php
/**
 * AI HTTP Client - Web Search Client
 * 
 * Single Responsibility: Handle web search functionality for fact-checking
 * Based on Data Machine's working search implementation patterns
 *
 * @package AIHttpClient\Utils
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Web_Search_Client {

    /**
     * Search provider configurations
     */
    private static $search_providers = array(
        'bing' => array(
            'url' => 'https://api.bing.microsoft.com/v7.0/search',
            'key_header' => 'Ocp-Apim-Subscription-Key'
        ),
        'google' => array(
            'url' => 'https://www.googleapis.com/customsearch/v1',
            'key_header' => 'key'
        ),
        'duckduckgo' => array(
            'url' => 'https://api.duckduckgo.com/',
            'key_header' => null // No API key required
        )
    );

    /**
     * Perform web search using configured provider
     * Based on Data Machine's search patterns
     *
     * @param string $query Search query
     * @param array $options Search options (provider, count, context_size, etc.)
     * @return array Search results with context
     * @throws Exception If search fails
     */
    public static function search($query, $options = array()) {
        $defaults = array(
            'provider' => 'bing',
            'count' => 5,
            'context_size' => 'medium',
            'timeout' => 30
        );
        
        $options = array_merge($defaults, $options);
        
        $provider = $options['provider'];
        if (!isset(self::$search_providers[$provider])) {
            throw new Exception('Unsupported search provider: ' . $provider);
        }
        
        $api_key = self::get_search_api_key($provider);
        if (!$api_key && $provider !== 'duckduckgo') {
            throw new Exception('API key not configured for provider: ' . $provider);
        }
        
        switch ($provider) {
            case 'bing':
                return self::search_bing($query, $api_key, $options);
            case 'google':
                return self::search_google($query, $api_key, $options);
            case 'duckduckgo':
                return self::search_duckduckgo($query, $options);
            default:
                throw new Exception('Provider not implemented: ' . $provider);
        }
    }

    /**
     * Search using Bing Search API
     *
     * @param string $query Search query
     * @param string $api_key Bing API key
     * @param array $options Search options
     * @return array Search results
     */
    private static function search_bing($query, $api_key, $options) {
        $url = self::$search_providers['bing']['url'];
        
        $params = array(
            'q' => $query,
            'count' => $options['count'],
            'responseFilter' => 'Webpages',
            'textDecorations' => 'false',
            'textFormat' => 'Raw'
        );
        
        $headers = array(
            'Ocp-Apim-Subscription-Key' => $api_key,
            'User-Agent' => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
        );
        
        $response = self::make_search_request($url . '?' . http_build_query($params), $headers, $options['timeout']);
        
        return self::format_bing_results($response, $options['context_size']);
    }

    /**
     * Search using Google Custom Search API
     *
     * @param string $query Search query
     * @param string $api_key Google API key
     * @param array $options Search options
     * @return array Search results
     */
    private static function search_google($query, $api_key, $options) {
        $cx = self::get_google_search_engine_id();
        if (!$cx) {
            throw new Exception('Google Custom Search Engine ID not configured');
        }
        
        $url = self::$search_providers['google']['url'];
        
        $params = array(
            'key' => $api_key,
            'cx' => $cx,
            'q' => $query,
            'num' => $options['count']
        );
        
        $headers = array(
            'User-Agent' => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
        );
        
        $response = self::make_search_request($url . '?' . http_build_query($params), $headers, $options['timeout']);
        
        return self::format_google_results($response, $options['context_size']);
    }

    /**
     * Search using DuckDuckGo (free, no API key required)
     *
     * @param string $query Search query
     * @param array $options Search options
     * @return array Search results
     */
    private static function search_duckduckgo($query, $options) {
        // DuckDuckGo instant answer API
        $url = 'https://api.duckduckgo.com/';
        
        $params = array(
            'q' => $query,
            'format' => 'json',
            'no_html' => '1',
            'skip_disambig' => '1'
        );
        
        $headers = array(
            'User-Agent' => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
        );
        
        $response = self::make_search_request($url . '?' . http_build_query($params), $headers, $options['timeout']);
        
        return self::format_duckduckgo_results($response, $options['context_size']);
    }

    /**
     * Make HTTP request for search
     *
     * @param string $url Request URL
     * @param array $headers Request headers
     * @param int $timeout Request timeout
     * @return array Decoded response
     * @throws Exception If request fails
     */
    private static function make_search_request($url, $headers, $timeout) {
        $args = array(
            'headers' => $headers,
            'timeout' => $timeout,
            'user-agent' => 'AI-HTTP-Client/' . AI_HTTP_CLIENT_VERSION
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('Search request failed: ' . $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            throw new Exception('Search API error: HTTP ' . $code);
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from search API');
        }
        
        return $decoded;
    }

    /**
     * Format Bing search results
     *
     * @param array $response Bing API response
     * @param string $context_size Context size (low, medium, high)
     * @return array Formatted results
     */
    private static function format_bing_results($response, $context_size) {
        $results = array();
        
        if (!isset($response['webPages']['value'])) {
            return $results;
        }
        
        foreach ($response['webPages']['value'] as $item) {
            $snippet = self::truncate_snippet($item['snippet'] ?? '', $context_size);
            
            $results[] = array(
                'title' => $item['name'] ?? '',
                'url' => $item['url'] ?? '',
                'snippet' => $snippet,
                'display_url' => $item['displayUrl'] ?? '',
                'provider' => 'bing'
            );
        }
        
        return $results;
    }

    /**
     * Format Google search results
     *
     * @param array $response Google API response
     * @param string $context_size Context size (low, medium, high)
     * @return array Formatted results
     */
    private static function format_google_results($response, $context_size) {
        $results = array();
        
        if (!isset($response['items'])) {
            return $results;
        }
        
        foreach ($response['items'] as $item) {
            $snippet = self::truncate_snippet($item['snippet'] ?? '', $context_size);
            
            $results[] = array(
                'title' => $item['title'] ?? '',
                'url' => $item['link'] ?? '',
                'snippet' => $snippet,
                'display_url' => $item['displayLink'] ?? '',
                'provider' => 'google'
            );
        }
        
        return $results;
    }

    /**
     * Format DuckDuckGo search results
     *
     * @param array $response DuckDuckGo API response
     * @param string $context_size Context size (low, medium, high)
     * @return array Formatted results
     */
    private static function format_duckduckgo_results($response, $context_size) {
        $results = array();
        
        // DuckDuckGo instant answer
        if (!empty($response['Abstract'])) {
            $results[] = array(
                'title' => $response['AbstractSource'] ?? 'DuckDuckGo',
                'url' => $response['AbstractURL'] ?? '',
                'snippet' => self::truncate_snippet($response['Abstract'], $context_size),
                'display_url' => $response['AbstractSource'] ?? '',
                'provider' => 'duckduckgo'
            );
        }
        
        // Related topics
        if (isset($response['RelatedTopics']) && is_array($response['RelatedTopics'])) {
            foreach (array_slice($response['RelatedTopics'], 0, 3) as $topic) {
                if (isset($topic['Text']) && isset($topic['FirstURL'])) {
                    $results[] = array(
                        'title' => 'Related: ' . substr($topic['Text'], 0, 60) . '...',
                        'url' => $topic['FirstURL'],
                        'snippet' => self::truncate_snippet($topic['Text'], $context_size),
                        'display_url' => parse_url($topic['FirstURL'], PHP_URL_HOST),
                        'provider' => 'duckduckgo'
                    );
                }
            }
        }
        
        return $results;
    }

    /**
     * Truncate snippet based on context size
     *
     * @param string $snippet Original snippet
     * @param string $context_size Context size (low, medium, high)
     * @return string Truncated snippet
     */
    private static function truncate_snippet($snippet, $context_size) {
        $max_length = array(
            'low' => 150,
            'medium' => 300,
            'high' => 500
        );
        
        $length = isset($max_length[$context_size]) ? $max_length[$context_size] : $max_length['medium'];
        
        if (strlen($snippet) <= $length) {
            return $snippet;
        }
        
        return substr($snippet, 0, $length) . '...';
    }

    /**
     * Get search API key for provider
     *
     * @param string $provider Provider name
     * @return string|null API key
     */
    private static function get_search_api_key($provider) {
        $option_key = 'ai_http_client_search_' . $provider . '_api_key';
        return get_option($option_key);
    }

    /**
     * Get Google Custom Search Engine ID
     *
     * @return string|null Search engine ID
     */
    private static function get_google_search_engine_id() {
        return get_option('ai_http_client_search_google_cx');
    }

    /**
     * Generate search tool definition for AI providers
     * This creates the tool schema that providers can use
     *
     * @return array Tool definition
     */
    public static function get_search_tool_definition() {
        return array(
            'name' => 'web_search',
            'description' => 'Search the web for current information and facts',
            'parameters' => array(
                'type' => 'object',
                'properties' => array(
                    'query' => array(
                        'type' => 'string',
                        'description' => 'The search query to find information'
                    ),
                    'context_size' => array(
                        'type' => 'string',
                        'enum' => array('low', 'medium', 'high'),
                        'description' => 'Amount of context to return from search results'
                    )
                ),
                'required' => array('query')
            )
        );
    }

    /**
     * Execute search tool call
     * This is called when AI providers receive a search tool request
     *
     * @param array $arguments Tool call arguments
     * @return array Search results formatted for AI consumption
     */
    public static function execute_search_tool($arguments) {
        try {
            $query = $arguments['query'] ?? '';
            $context_size = $arguments['context_size'] ?? 'medium';
            
            if (empty($query)) {
                return array(
                    'success' => false,
                    'error' => 'Search query is required'
                );
            }
            
            $results = self::search($query, array(
                'context_size' => $context_size,
                'count' => 5
            ));
            
            if (empty($results)) {
                return array(
                    'success' => true,
                    'results' => 'No search results found for query: ' . $query
                );
            }
            
            // Format results for AI consumption
            $formatted_results = "Search results for: {$query}\n\n";
            foreach ($results as $i => $result) {
                $formatted_results .= ($i + 1) . ". {$result['title']}\n";
                $formatted_results .= "   {$result['snippet']}\n";
                $formatted_results .= "   Source: {$result['url']}\n\n";
            }
            
            return array(
                'success' => true,
                'results' => $formatted_results
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Search failed: ' . $e->getMessage()
            );
        }
    }
}