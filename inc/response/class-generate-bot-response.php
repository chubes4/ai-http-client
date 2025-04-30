<?php

namespace AiBot\Response;

use AiBot\API\ChatGPT_API;
use AiBot\Context\Content_Interaction_Service;
use AiBot\Core\AiBot_Service_Container; // Update use statement for namespaced container
use AiBot\Core\AiBot; // Add use statement for the main bot class

/**
 * Generate Bot Response Class
 */
class Generate_Bot_Response {

    /**
     * @var ChatGPT_API
     */
    private $chatgpt_api;

    /**
     * @var Content_Interaction_Service
     */
    private $content_interaction_service;

    /**
     * @var AiBot_Service_Container
     */
    private $container; // Store the container

    /**
     * Constructor
     *
     * @param ChatGPT_API $chatgpt_api The ChatGPT API instance.
     * @param Content_Interaction_Service $content_interaction_service The content interaction service instance.
     * @param AiBot_Service_Container $container The service container instance.
     */
    public function __construct( ChatGPT_API $chatgpt_api, Content_Interaction_Service $content_interaction_service, AiBot_Service_Container $container ) {
        $this->chatgpt_api                 = $chatgpt_api;
        $this->content_interaction_service = $content_interaction_service;
        $this->container                   = $container; // Store the container
    }

    /**
     * Cron function to generate and post AI response
     */
    public function generate_and_post_ai_response_cron( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {

        // Log the start of the cron function
        // error_log('AI Bot Info: generate_and_post_ai_response_cron started for post ID: ' . $post_id);

        // Get the Bot User ID from options - Use new option name
        $bot_user_id = get_option( 'ai_bot_user_id' );

        // Check if Bot User ID is set
        if ( ! $bot_user_id ) {
            // error_log('AI Bot Error: Bot User ID option (ai_bot_user_id) is not set. Cannot generate response.');
            return; // Stop execution if no bot user is configured
        }

        // Get the user data based on the ID
        $bot_user_data = get_userdata( $bot_user_id );

        // Check if user data was retrieved successfully
        if ( ! $bot_user_data ) {
            // error_log('AI Bot Error: Could not find user data for Bot User ID: ' . $bot_user_id);
            return; // Stop execution if user doesn't exist
        }

        // Get the username (user_login) to use for mentions/prompts
        $bot_username = $bot_user_data->user_login;

        try {
            $post_content = ($reply_author == 0) ? bbp_get_topic_content( $post_id ) : bbp_get_reply_content( $post_id );

            // Generate AI response using the dynamic username
            $response_content = $this->generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id );

            // Check if response generation resulted in an error
            if ( is_wp_error( $response_content ) ) {
                 // error_log('AI Bot Error: Error generating AI response: ' . $response_content->get_error_message());
                 // Optionally, post a generic error reply or just log and exit
                 return;
            }

            // Get bot instance from container *now* and call the post method
            $bot_instance = $this->container->get('bot.main');
            // Use the namespaced class name for the type check
            if ($bot_instance instanceof AiBot) { // Check against the imported AiBot class
                 $bot_instance->post_bot_reply( $topic_id, $response_content );
            } else {
                 // error_log('AI Bot Error: Could not retrieve valid bot instance from container.');
            }
        } catch (\Exception $e) {
            // Catch any exceptions and log them
            // error_log('AI Bot Error: Error in generate_and_post_ai_response_cron for post ID ' . $post_id . ': ' . $e->getMessage());
            // Optionally, you could attempt to post a generic error reply here
        }
    }

    /**
     * Generate AI response using ChatGPT API
     */
    private function generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id ) { // Ensure parameter name matches
        // Use new option names
        $system_prompt = get_option( 'ai_bot_system_prompt' );
        $custom_prompt = get_option( 'ai_bot_custom_prompt' );
        $temperature   = get_option( 'ai_bot_temperature', 0.5 ); // Default temperature

        // --- Add Current Date/Time to System Prompt ---
        // Get the current time according to WordPress settings.
        $current_datetime = current_time( 'mysql' ); // Format: YYYY-MM-DD HH:MM:SS
        $current_date = current_time( 'Y-m-d' ); // Format: YYYY-MM-DD

        // Construct the detailed date/time instruction block
        $date_instruction = <<<PROMPT
--- MANDATORY TIME CONTEXT ---
CURRENT DATE & TIME: {$current_datetime}
RULE: You MUST treat {$current_date} as the definitive 'today' for determining past/present/future tense.
ACTION: Frame all events relative to {$current_date}. Use past tense for completed events. Use present/future tense appropriately ONLY for events happening on or after {$current_date}.
CONSTRAINT: DO NOT discuss events completed before {$current_date} as if they are still upcoming.
KNOWLEDGE CUTOFF: Your internal knowledge cutoff is irrelevant; operate solely based on this date and provided context.
--- END TIME CONTEXT ---
PROMPT;

        // Prepend the date/time instruction block to the system prompt.
        $system_prompt = $date_instruction . "\n\n" . $system_prompt;
        // --- End Date/Time Addition ---

        // Get relevant context using the injected Content Interaction Service
        $context_string = $this->content_interaction_service->get_relevant_context( $post_id, $post_content, $topic_id, $forum_id );

        // Get remote hostname using the centralized method
        $remote_host = $this->content_interaction_service->get_remote_hostname();

        // Strip HTML tags from the current post content
        $cleaned_post_content = wp_strip_all_tags( $post_content );
        // Decode HTML entities like &nbsp;
        $cleaned_post_content = html_entity_decode( $cleaned_post_content, ENT_QUOTES, 'UTF-8' );
        // Explicitly replace non-breaking spaces with regular spaces
        $cleaned_post_content = str_replace( "\u{00A0}", " ", $cleaned_post_content );
        $cleaned_post_content = trim($cleaned_post_content);


        // Append context and instructions
        // Make instructions more directive about using the provided context, HTML formatting, and source relevance
        $instruction = sprintf(
            "\n--- Your Response Instructions ---\n".
            "1. Respond to the 'Current Post' above as @%s.\n".
            "2. Use the 'Additional Relevant Context' (from %s) to inform your answer.\n".
            "3. If quoting information found specifically within the context, quote it directly.\n".
            "4. Your *entire* response must be formatted using only HTML tags suitable for direct rendering in a web page (e.g., <p>, <b>, <i>, <a>, <ul>, <ol>, <li>). \n".
            "5. **Important:** Do NOT wrap your response in Markdown code fences (like ```html) or any other non-HTML wrappers.\n".
            "6. Provide the 'Remote Context Source URL' as an HTML link (using <a href='...'>) only if it is directly relevant to the specific information being discussed or quoted.",
            $bot_username, // Use the passed dynamic username
            $remote_host   // Use the dynamically fetched hostname
        );

        $prompt = $context_string . "\nCurrent Post: " . $cleaned_post_content . $instruction; // Append instructions

        // Prepare the final prompt for the API
        $final_prompt = $prompt; // Use the combined prompt
        // error_log("AI Bot Debug: System Prompt: " . $system_prompt);
        // error_log("AI Bot Debug: Custom Prompt: " . $custom_prompt);
        // error_log("AI Bot Debug: Final Prompt (Context + Instructions) being sent to API: " . $final_prompt);

        // 2. Generate Response using ChatGPT API
        $response = $this->chatgpt_api->generate_response( $final_prompt, $system_prompt, $custom_prompt, $temperature );

        if ( is_wp_error( $response ) ) {
            // Log error (for future improvement)
            // error_log('AI Bot Error: ChatGPT API Error: ' . $response->get_error_message());
            // Consider using a translatable string here with the new text domain
            return new \WP_Error('api_error', __('Sorry, I\'m having trouble generating a response right now. Please try again later.', 'ai-bot-for-bbpress'));
        } else {
            return $response;
        }
    }
}
