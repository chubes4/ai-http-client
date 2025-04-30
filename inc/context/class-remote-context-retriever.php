<?php

namespace AiBot\Context;

/**
 * Handles retrieving context from a remote WordPress site via REST API.
 *
 * Communicates with the BBP Bot Helper plugin on the remote site.
 *
 * @package Ai_Bot_For_Bbpress
 * @subpackage Context
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Remote_Context_Retriever
 *
 * Handles fetching and formatting context data from a configured remote REST API.
 */
class Remote_Context_Retriever {

    /**
     * Retrieves relevant context from the remote endpoint based on a single keyword and limit.
     *
     * @param string $keyword The single keyword/phrase to search for.
     * @param int    $limit   The maximum number of results to request for this specific keyword.
     * @return array An array of formatted context strings, or an empty array if no context is found or an error occurs.
     */
    public function get_remote_context( $keyword, $limit ) {
        $helper_url = get_option( 'ai_bot_remote_endpoint_url' );
        if ( empty( $helper_url ) ) {
             // No URL configured, skip remote search
             // error_log('AI Bot Info: Remote Endpoint URL not configured. Skipping remote search.');
            return [];
        }

        // Append the keyword to the URL as a query parameter
        // $search_url = add_query_arg( 'keyword', urlencode( $keyword ), $helper_url );
        $search_url = add_query_arg( [
            'keyword' => urlencode( $keyword ),
            'limit'   => intval( $limit ) // Pass the limit
        ], $helper_url );

        // error_log("AI Bot Info: Querying remote endpoint: " . $search_url);

        // --- Make the API Request ---
        $args = [
            'timeout' => 15 // Set a reasonable timeout
        ];
        // error_log("AI Bot Debug: Making remote GET request to: " . $search_url); // REMOVED
        $response = wp_remote_get( $search_url, $args );
        // error_log("AI Bot Debug: wp_remote_get raw response object: " . print_r($response, true)); // REMOVED

        // --- Handle Response ---
        if ( is_wp_error( $response ) ) {
            // error_log( 'AI Bot Error: wp_remote_get failed for ' . $search_url . '. Error: ' . $response->get_error_message() );
            return []; // Exit 2
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        // error_log("AI Bot Debug: Response Code: " . $response_code . " for URL: " . $search_url); // REMOVED
        // error_log("AI Bot Debug: Raw Response Body: " . $response_body); // REMOVED

        if ( $response_code !== 200 ) {
             // error_log( 'AI Bot Error: Non-200 Response Code received (' . $response_code . ') for URL: ' . $search_url );
            return []; // Exit 3
        }

        $data = json_decode( $response_body, true );
        $json_error = json_last_error();

        if ( $json_error !== JSON_ERROR_NONE || ! is_array( $data ) || ! isset($data['results']) || !is_array($data['results']) ) {
             // error_log( 'AI Bot Error: Invalid JSON or unexpected format received. JSON Error: ' . json_last_error_msg() . ' | is_array(data): ' . (is_array($data)?'Yes':'No') . ' | isset(data[results]): ' . (isset($data['results'])?'Yes':'No') . ' | is_array(data[results]): ' . (isset($data['results']) && is_array($data['results'])?'Yes':'No') );
             // error_log( 'AI Bot Debug: Raw Body on JSON Error: ' . $response_body ); // REMOVED
             return []; // Exit 4
        }

        // --- Format Results ---
        $formatted_results = [];
        $hostname = $this->get_remote_hostname(); // Get hostname once

        if ( empty($data['results']) ) {
            // error_log( 'AI Bot Info: Remote endpoint returned 0 results for keyword: ' . $keyword );
        } else {
            // error_log( 'AI Bot Info: Received ' . count($data['results']) . ' results for keyword: ' . $keyword ); // Simplified log
        }

        foreach ( $data['results'] as $result ) {
            // error_log("AI Bot Debug: Checking individual remote result: " . print_r($result, true)); // REMOVED
            // Basic validation of expected keys - now including author
            if ( isset( $result['title'], $result['author'], $result['url'], $result['content'], $result['date'] ) ) {
                $formatted_string = sprintf(
                    "Source: Remote (%s)\nTitle: %s\nAuthor: %s\nDate: %s\nURL: %s\nContent:\n%s",
                    esc_html($hostname),
                    esc_html( $result['title'] ),
                    esc_html( $result['author'] ), // Add author
                    esc_html( $result['date'] ),
                    esc_url( $result['url'] ),
                    esc_html( $result['content'] )
                );
                 $formatted_results[] = [
                     'url' => $result['url'], // Keep the original URL for duplicate checking
                     'string' => $formatted_string
                 ];
                 // error_log( 'AI Bot Info: Added remote result #' . count($formatted_results) . ' (URL: ' . $result['url'] . ')' ); // Added success log
            } else {
                 // error_log("AI Bot Warning: Skipping remote result due to missing keys (expecting title, author, url, content, date): " . print_r($result, true) ); // Updated log
            }
        }

        if (empty($formatted_results)) {
             // error_log('AI Bot Info: No valid/formattable results after processing remote response for keyword: ' . $keyword . ' Raw response: ' . print_r($data, true));
         } else {
             // error_log('AI Bot Info: Successfully formatted ' . count($formatted_results) . ' remote results for keyword: ' . $keyword);
        }

        return $formatted_results;
    }

    /**
     * Get the hostname from the configured remote endpoint URL.
     *
     * @return string The hostname or a default label 'Remote Source'.
     */
    private function get_remote_hostname() {
        $remote_url = get_option( 'ai_bot_remote_endpoint_url' );
        $remote_host = 'Remote Source'; // Default label
        if ( ! empty( $remote_url ) ) {
            $parsed_parts = wp_parse_url( $remote_url );
            $parsed_host = $parsed_parts['host'] ?? null;
            if ( $parsed_host ) {
                $remote_host = $parsed_host;
            }
        }
        return $remote_host;
    }
} 