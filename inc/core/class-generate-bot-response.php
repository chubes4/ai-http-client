<?php

namespace AiBot\Core;

use AiBot\API\ChatGPT_API;
use AiBot\Context\Content_Interaction_Service;
use AiBot\Core\System_Prompt_Builder;
use AiBot\Core\AiBot_Service_Container;
use AiBot\Core\AiBot;

/**
 * Generate Bot Response Class
 * 
 * Orchestrates AI response generation by coordinating context retrieval, 
 * prompt construction, and API communication.
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
     * @var System_Prompt_Builder
     */
    private $system_prompt_builder;

    /**
     * @var AiBot_Service_Container
     */
    private $container;

    /**
     * Constructor
     *
     * @param ChatGPT_API $chatgpt_api The ChatGPT API instance.
     * @param Content_Interaction_Service $content_interaction_service The content interaction service instance.
     * @param System_Prompt_Builder $system_prompt_builder The system prompt builder instance.
     * @param AiBot_Service_Container $container The service container instance.
     */
    public function __construct(
        ChatGPT_API $chatgpt_api,
        Content_Interaction_Service $content_interaction_service,
        System_Prompt_Builder $system_prompt_builder,
        AiBot_Service_Container $container
    ) {
        $this->chatgpt_api = $chatgpt_api;
        $this->content_interaction_service = $content_interaction_service;
        $this->system_prompt_builder = $system_prompt_builder;
        $this->container = $container;
    }

    /**
     * Cron function to generate and post AI response
     */
    public function generate_and_post_ai_response_cron( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {

        // Get the Bot User ID from options
        $bot_user_id = get_option( 'ai_bot_user_id' );

        // Check if Bot User ID is set
        if ( ! $bot_user_id ) {
            return; // Stop execution if no bot user is configured
        }

        // Get the user data based on the ID
        $bot_user_data = get_userdata( $bot_user_id );

        // Check if user data was retrieved successfully
        if ( ! $bot_user_data ) {
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

            // Generate AI response using the simplified orchestration
            $response_content = $this->generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id, $triggering_username_slug );

            // Check if response generation resulted in an error
            if ( is_wp_error( $response_content ) ) {
                 // Post a user-friendly error message so users know the bot received their message
                 $fallback_message = __( 'I received your message but I\'m having some technical difficulties right now. Please try again in a few minutes!', 'ai-bot-for-bbpress' );
                 
                 // Get bot instance from container and post the fallback message
                 $bot_instance = $this->container->get('bot.main');
                 if ($bot_instance instanceof AiBot) {
                     $bot_instance->post_bot_reply( $topic_id, $fallback_message );
                 }
                 return;
            }

            // Get bot instance from container and call the post method
            $bot_instance = $this->container->get('bot.main');
            if ($bot_instance instanceof AiBot) {
                 $bot_instance->post_bot_reply( $topic_id, $response_content );
            }
        } catch (\Exception $e) {
            // Catch any exceptions - fallback error handling could go here
        }
    }

    /**
     * Generate AI response using centralized prompt building and context retrieval
     */
    private function generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id, $triggering_username_slug ) {
        // Get configuration
        $custom_prompt = get_option( 'ai_bot_custom_prompt' );
        $temperature = get_option( 'ai_bot_temperature', 0.5 );

        // Build complete system prompt using centralized builder
        $system_prompt = $this->system_prompt_builder->build_system_prompt( $bot_username );

        // Get relevant context and remote hostname from content service
        $context_string = $this->content_interaction_service->get_relevant_context( $post_id, $post_content, $topic_id, $forum_id );
        $remote_host = $this->content_interaction_service->get_remote_hostname();

        // Build response instructions using centralized builder
        $response_instructions = $this->system_prompt_builder->build_response_instructions( 
            $bot_username, 
            $triggering_username_slug, 
            $remote_host 
        );

        // Combine context with instructions
        $final_prompt = $context_string . $response_instructions;

        // Get structured conversation messages for proper OpenAI flow
        $conversation_messages = $this->content_interaction_service->get_conversation_messages( $post_id, $post_content, $topic_id, $forum_id );
        
        // Generate Response using ChatGPT API with proper conversation history
        $response = $this->chatgpt_api->generate_response( $final_prompt, $system_prompt, $custom_prompt, $temperature, $conversation_messages );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error('api_error', __('Sorry, I\'m having trouble generating a response right now. Please try again later.', 'ai-bot-for-bbpress'));
        } else {
            return $response;
        }
    }
}