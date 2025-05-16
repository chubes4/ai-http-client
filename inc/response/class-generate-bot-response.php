<?php

namespace AiBot\Response;

use AiBot\API\ChatGPT_API;
use AiBot\Context\Content_Interaction_Service;
use AiBot\Context\Forum_Structure_Provider;
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
     * @var Forum_Structure_Provider
     */
    private $forum_structure_provider;

    /**
     * @var AiBot_Service_Container
     */
    private $container; // Store the container

    /**
     * Constructor
     *
     * @param ChatGPT_API $chatgpt_api The ChatGPT API instance.
     * @param Content_Interaction_Service $content_interaction_service The content interaction service instance.
     * @param Forum_Structure_Provider $forum_structure_provider The forum structure provider instance.
     * @param AiBot_Service_Container $container The service container instance.
     */
    public function __construct(
        ChatGPT_API $chatgpt_api,
        Content_Interaction_Service $content_interaction_service,
        Forum_Structure_Provider $forum_structure_provider,
        AiBot_Service_Container $container
    ) {
        $this->chatgpt_api                 = $chatgpt_api;
        $this->content_interaction_service = $content_interaction_service;
        $this->forum_structure_provider    = $forum_structure_provider;
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

        // Get triggering user's information
        $triggering_username_slug = 'the user'; // Default
        if ( $reply_author != 0 ) {
            $triggering_user_data = get_userdata( $reply_author );
            if ( $triggering_user_data ) {
                $triggering_username_slug = $triggering_user_data->user_nicename; // Use nicename (slug)
            }
        }

        try {
            $post_content = ($reply_author == 0) ? bbp_get_topic_content( $post_id ) : bbp_get_reply_content( $post_id );

            // Generate AI response using the dynamic username and triggering user's slug
            $response_content = $this->generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id, $triggering_username_slug );

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
    private function generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id, $triggering_username_slug ) {
        // Use new option names
        $system_prompt = get_option( 'ai_bot_system_prompt' );
        $custom_prompt = get_option( 'ai_bot_custom_prompt' );
        $temperature   = get_option( 'ai_bot_temperature', 0.5 ); // Default temperature

        // --- Get Forum Structure JSON using the provider ---
        // The provider handles caching and bbPress availability checks internally
        $forum_structure_json = $this->forum_structure_provider->get_forum_structure_json();

        // --- Add Current Date/Time to System Prompt ---
        // Get the current time according to WordPress settings.
        $current_datetime = current_time( 'mysql' ); // Format: YYYY-MM-DD HH:MM:SS
        $current_date = current_time( 'Y-m-d' ); // Format: YYYY-MM-DD

        // Construct the detailed date/time instruction block using sprintf
        $date_instruction = sprintf(
            "--- MANDATORY TIME CONTEXT ---\n".
            "CURRENT DATE & TIME: %s\n".
            "RULE: You MUST treat %s as the definitive 'today' for determining past/present/future tense.\n".
            "ACTION: Frame all events relative to %s. Use past tense for completed events. Use present/future tense appropriately ONLY for events happening on or after %s.\n".
            "CONSTRAINT: DO NOT discuss events completed before %s as if they are still upcoming.\n".
            "KNOWLEDGE CUTOFF: Your internal knowledge cutoff is irrelevant; operate solely based on this date and provided context.\n".
            "--- END TIME CONTEXT ---",
            $current_datetime,
            $current_date,
            $current_date,
            $current_date,
            $current_date
        );

        // Prepend the date/time instruction block to the system prompt.
        $system_prompt = $date_instruction . "\n\n" . $system_prompt;

        // --- Prepend Forum Structure JSON to System Prompt ---
        // Check if the provider returned valid JSON (it returns null on error/bbPress unavailable)
        if ( ! is_null($forum_structure_json) && json_decode($forum_structure_json) !== null ) {
            $forum_context_header = "--- FORUM CONTEXT ---\n";
            $forum_context_header .= "The following JSON object describes the structure of this forum site. Use this information to understand the site's overall organization and purpose when formulating your responses:\n";
            $forum_context_footer = "\n--- END FORUM CONTEXT ---\n\n";

            // Prepend the structure JSON wrapped in headers/footers
            $system_prompt = $forum_context_header . $forum_structure_json . $forum_context_footer . $system_prompt;
        }
        // --- End Forum Structure Addition ---

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
        // Reference the new context section headings
        $instruction = sprintf(
            "\n--- Your Response Instructions ---\n".
            "1. Understand the user query from the 'CURRENT INTERACTION' section. The user who wrote this (and who you are replying to) is @%s.\n".
            "2. Use the chronological 'CONVERSATION HISTORY (Oldest First)' section to understand the flow of discussion and maintain context. The author of each post is identified by their username slug like `[Author: @username-slug]` or `[Author: You (@your-slug)]`.\n".
            "3. Primarily use information from 'RELEVANT KNOWLEDGE BASE (LOCAL)' and 'RELEVANT KNOWLEDGE BASE (REMOTE)' to answer the query, if they contain relevant information.\n".
            "4. You are @%s. Address @%s directly in your reply if appropriate (e.g., 'Hi @%s, ...').\n".
            "5. **Mentioning Users:** When you need to mention a user from the conversation history, use the exact `@username-slug` format provided in the `[Author: @username-slug]` tag for that user. For example, to mention the user with slug `john-doe`, write `@john-doe`. **Do not** invent slugs or use display names for mentions. If you are addressing the user who triggered you, use @%s.\n".
            "6. Cite the source URL (if provided in the context) primarily when presenting specific facts, figures, or direct quotes from the 'RELEVANT KNOWLEDGE BASE' sections (local or remote) to support your response. For general discussion drawing on the context, citation is less critical.\n".
            "7. Your *entire* response must be formatted using only HTML tags suitable for direct rendering in a web page (e.g., <p>, <b>, <i>, <a>, <ul>, <ol>, <li>). \n".
            "8. **Important:** Do NOT wrap your response in Markdown code fences (like ```html) or any other non-HTML wrappers.\n".
            "9. Prefer local knowledge base information if available and relevant.\n".
            "10. Use the remote knowledge base (from %s) to supplement local information or when local context is insufficient.",
            $triggering_username_slug, // For instruction 1
            $bot_username,             // For instruction 4 (who the bot is)
            $triggering_username_slug, // For instruction 4 (who to address)
            $triggering_username_slug, // For instruction 4 (example)
            $triggering_username_slug, // For instruction 5
            $remote_host               // For instruction 10
        );

        // The context_string now contains all structured context sections
        // We no longer need to add the cleaned_post_content here as it's included in the CURRENT INTERACTION section
        $prompt = $context_string . $instruction; // Append instructions directly to the structured context

        // Prepare the final prompt for the API
        $final_prompt = $prompt; // Use the combined prompt

        // *** DEBUG LOG: Final Prompt to API ***
        // error_log("AI Bot Debug: Final Prompt being sent to API:\n---\n" . $final_prompt . "\n---");
        // *** END DEBUG LOG ***

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
