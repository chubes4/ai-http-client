<?php

namespace AiBot\API;

/**
 * ChatGPT API Class
 */
class ChatGPT_API {

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize API key and other settings
    }

    /**
     * Get ChatGPT API Key from options
     */
    private function get_api_key() {
        return get_option( 'ai_bot_api_key' );
    }

    /**
     * Generate AI response using ChatGPT API
     */
    public function generate_response( $prompt, $system_prompt, $custom_prompt, $temperature ) {
        $api_key = $this->get_api_key();
        $model = 'gpt-4.1-mini';

        if ( empty( $api_key ) ) {
            // error_log('AI Bot Error: OpenAI API Key is not set.');
            return new WP_Error( 'no_api_key', __( 'ChatGPT API key not set.', 'ai-bot-for-bbpress' ) );
        }

        $api_url = 'https://api.openai.com/v1/chat/completions';

        $messages = [];
        if ( !empty( $system_prompt ) ) {
            $messages[] = [
                'role' => 'system',
                'content' => $system_prompt,
            ];
        }
        if ( !empty( $custom_prompt ) ) {
            $messages[] = [
                'role' => 'user',
                'content' => $custom_prompt,
            ];
        }
        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];


        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body'    => wp_json_encode(
                array(
                    'model'       => $model,
                    'messages'    => $messages,
                    'temperature' => floatval( $temperature ), // Ensure temperature is a float
                )
            ),
            'method'  => 'POST',
            'timeout' => 20, // seconds
        );

        // Log the request and response only on error or for debugging
        $log_api_details = true; // Set to true for debugging if needed

        $response = wp_remote_post( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            // error_log( 'AI Bot Error: OpenAI API request failed. Error: ' . $response->get_error_message() );
            return $response; // Return WP_Error object
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Check for non-200 response code
        if ( 200 !== $response_code ) {
            // Log details on non-200 response
            // error_log( 'AI Bot Error: OpenAI API HTTP Error Code: ' . $response_code );
            // error_log( 'AI Bot Error: OpenAI API Request Args on HTTP Error: ' . print_r( $args, true ) );
            // error_log( 'AI Bot Error: OpenAI API Response Body on HTTP Error: ' . $response_body );
            /* translators: %d: HTTP error code */
            return new WP_Error( 'api_error', sprintf( __( 'ChatGPT API error: %d', 'ai-bot-for-bbpress' ), $response_code ) ); // Simplify error message
        }

        // Optional: Log details even on success if the debug flag is set
        if ( $log_api_details ) {
            // error_log( 'AI Bot Info: OpenAI API Request Args (Debug): ' . print_r( $args, true ) );
            // error_log( 'AI Bot Info: OpenAI API Response Body (Success): ' . $response_body );
        }

        $response_data = json_decode( $response_body, true );

        // Check if response format is valid
        if ( json_last_error() !== JSON_ERROR_NONE || !isset( $response_data['choices'][0]['message']['content'] ) ) {
            // Log details on invalid response format
             // error_log( 'AI Bot Error: OpenAI API Invalid Response Format.' );
             // error_log( 'AI Bot Error: OpenAI API Request Args on Invalid Format: ' . print_r( $args, true ) );
             // error_log( 'AI Bot Error: OpenAI API Response Body on Invalid Format: ' . $response_body );
            return new WP_Error( 'api_response_error', __( 'Invalid ChatGPT API response format.', 'ai-bot-for-bbpress' ) );
        }

        // Success case
        $final_content = wp_kses_post( $response_data['choices'][0]['message']['content'] ); // Sanitize output
        // error_log( 'AI Bot Info: Final Extracted Content: ' . $final_content );
        return $final_content;
    }
}