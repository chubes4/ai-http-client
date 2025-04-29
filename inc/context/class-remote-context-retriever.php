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
        $response = wp_remote_get( $search_url, $args );

        // --- Handle Response ---
        if ( is_wp_error( $response ) ) {
            // error_log( 'AI Bot Error: Failed to connect to remote endpoint (' . $search_url . '). Error: ' . $response->get_error_message() );
            return [];
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $response_code !== 200 ) {
             // error_log( 'AI Bot Error: Remote endpoint returned HTTP status ' . $response_code . ' for URL: ' . $search_url );
            return [];
        }

        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) || ! isset($data['results']) || !is_array($data['results']) ) {
             // error_log( 'AI Bot Error: Invalid JSON or unexpected format received from remote endpoint: ' . $response_body );
             // error_log( 'AI Bot Error: Endpoint URL queried: ' . $search_url );
             // error_log( 'AI Bot Error: JSON decode error: ' . json_last_error_msg() );
            return [];
        }

        // --- Format Results ---
        $formatted_results = [];
        $hostname = $this->get_remote_hostname(); // Get hostname once

        if ( empty($data['results']) ) {
            // error_log( 'AI Bot Info: Remote endpoint returned 0 results for keyword: ' . $keyword );
        } else {
            // error_log( 'AI Bot Info: Processing ' . count($data['results']) . ' results from remote endpoint for keyword: ' . $keyword );
        }

        foreach ( $data['results'] as $result ) {
            // Basic validation of expected keys
            if ( isset( $result['title'], $result['url'], $result['content'], $result['date'] ) ) {
                $formatted_string = sprintf(
                    "Source: Remote (%s)\nTitle: %s\nDate: %s\nURL: %s\nContent:\n%s",
                    esc_html($hostname),
                    esc_html( $result['title'] ),
                    esc_html( $result['date'] ), // Display the date
                    esc_url( $result['url'] ),
                    esc_html( $result['content'] )
                );
                 $formatted_results[] = [
                     'url' => $result['url'], // Keep the original URL for duplicate checking
                     'string' => $formatted_string
                 ];
            } else {
                 // error_log("AI Bot Warning: Skipping remote result due to missing keys: " . print_r($result, true) );
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