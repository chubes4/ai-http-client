<?php

namespace AiBot\Context;

/**
 * Retrieves context from a remote REST API endpoint.
 *
 * @package Bbpress_Forum_AI_Bot
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
        $remote_url    = get_option( 'ai_bot_remote_endpoint_url' );
        $formatted_context = [];

        if ( empty( $remote_url ) ) {
            error_log( 'AI Bot Error: Remote endpoint URL is not configured.' );
            return $formatted_context;
        }

        // Ensure keyword is not empty
        if ( empty( $keyword ) ) {
            error_log( 'AI Bot Warning: Remote context retrieval skipped due to empty keyword.' );
            return $formatted_context;
        }

        // Ensure limit is valid
        $limit = absint( $limit );
        if ( $limit <= 0 ) {
            error_log( 'AI Bot Warning: Remote context retrieval skipped due to invalid limit: ' . $limit );
            return $formatted_context;
        }

        // Construct the request URL with query parameters
        $encoded_keyword = rawurlencode( $keyword ); // Use the single keyword

        $request_url = add_query_arg(
            [
                'query' => $encoded_keyword,
                'limit' => $limit, // Use the passed limit
            ],
            $remote_url
        );

        // Log the exact request URL
        error_log( 'AI Bot Info: Remote API Request URL: ' . $request_url );

        // Make the API request
        $response = wp_remote_get( esc_url_raw( $request_url ), [ 'timeout' => 15 ] ); // 15-second timeout

        // Check for WP_Error
        if ( is_wp_error( $response ) ) {
            error_log( 'AI Bot Error: Remote API wp_remote_get failed. Error: ' . $response->get_error_message() );
            return $formatted_context; // Return empty on error
        }

        // Check for non-200 HTTP status code
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            $response_body_on_error = wp_remote_retrieve_body( $response );
            error_log( 'AI Bot Error: Remote API request returned non-200 status code: ' . $status_code );
            error_log( 'AI Bot Error: Remote API response body on error: ' . $response_body_on_error );
            return $formatted_context; // Return empty on error
        }

        // Process the response body
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true ); // Assuming JSON response from helper plugin

        // Check for JSON decoding errors
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
             error_log( 'AI Bot Error: Failed to decode JSON response or invalid format from remote API.' );
             error_log( 'AI Bot Error: JSON Decode Error: ' . json_last_error_msg() );
             error_log( 'AI Bot Error: Raw response body on decode error: ' . $body );
            return $formatted_context; // Return empty on error
        }

        // Format the context
        foreach ( $data as $item ) {
            // Check if expected keys exist (title, content, url, date)
            if ( isset( $item['title'] ) && isset( $item['content'] ) && isset( $item['url'] ) && isset( $item['date'] ) ) {
                // Basic formatting - adjust as needed for the AI model
                // Ensure the URL is extracted correctly for duplicate checking later
                $item_url = esc_url_raw( $item['url'] );
                $item_date = sanitize_text_field( $item['date'] ); // Get and sanitize the date
                $formatted_context[] = [
                    'url' => $item_url,
                    'string' => sprintf(
                        "Remote Context Source URL: %s\nRemote Context Date: %s\nRemote Context Title: %s\nRemote Context Content:\n%s", // Added Date line
                        $item_url,
                        $item_date, // Add date here
                        sanitize_text_field( $item['title'] ),
                        sanitize_textarea_field( $item['content'] ) // Use textarea sanitize for potentially longer content
                    )
                ];
            } else {
                error_log( 'AI Bot Warning: Remote API item missing title, content, url, or date key. Item: ' . print_r( $item, true ) ); // Updated error log to mention date
            }
        }

        // Log the successfully formatted context before returning
        if ( ! empty( $formatted_context ) ) {
            error_log( 'AI Bot Info: Successfully formatted remote context: ' . print_r( $formatted_context, true ) );
        } else {
             error_log( 'AI Bot Info: No valid remote context items found after processing response.' );
        }

        return $formatted_context;
    }
} 