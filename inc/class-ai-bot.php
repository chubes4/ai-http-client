<?php

use AiBot\Triggers\Handle_Mention;
use AiBot\Response\Generate_Bot_Response;
use AiBot\Context\Content_Interaction_Service;
use AiBot\Context\Database_Agent;

/**
 * Main AI Bot for bbPress Class
 */
class AiBot {

    /**
     * @var Handle_Mention
     */
    private $handle_mention;

    /**
     * @var Generate_Bot_Response
     */
    private $generate_bot_response;

    /**
     * @var Content_Interaction_Service
     */
    private $content_interaction_service;

    /**
     * @var Database_Agent
     */
    private $database_agent;

    /**
     * Constructor
     *
     * @param Handle_Mention $handle_mention
     * @param Generate_Bot_Response $generate_bot_response
     * @param Content_Interaction_Service $content_interaction_service
     * @param Database_Agent $database_agent
     */
    public function __construct(
        Handle_Mention $handle_mention,
        Generate_Bot_Response $generate_bot_response,
        Content_Interaction_Service $content_interaction_service,
        Database_Agent $database_agent
    ) {
        $this->handle_mention              = $handle_mention;
        $this->generate_bot_response       = $generate_bot_response;
        $this->content_interaction_service = $content_interaction_service;
        $this->database_agent              = $database_agent;
    }

    /**
     * Initialize hooks and filters
     */
    public function init() {
        // Add action hooks
        add_action( 'plugins_loaded', array( $this, 'register_hooks' ) );
    }

    /**
     * Register action and filter hooks
     */
    public function register_hooks() {
        // Ensure Handle_Mention initializes its hooks
        if (method_exists($this->handle_mention, 'init')) {
            $this->handle_mention->init();
        }
        // Add the cron action hook
        add_action( 'ai_bot_generate_bot_response_event', array( $this->generate_bot_response, 'generate_and_post_ai_response_cron' ), 10, 5 );

        // Initialize admin settings (assuming Admin_Central is procedural)
        // Ensure the functions in admin-central.php are loaded via require_once in the main plugin file
        if ( function_exists('ai_bot_add_options_page') ) {
            // Hooks for admin menu and settings are already added in admin-central.php
            // No need to add them again here.
        } else {
            error_log('AI Bot Error: Admin functions not loaded.');
        }

    }

    /**
     * Post a reply as the configured bot user.
     *
     * @param int    $topic_id The topic ID to reply to.
     * @param string $content  The content of the reply.
     * @return int|WP_Error The new reply ID or WP_Error on failure.
     */
    public function post_bot_reply( $topic_id, $content ) {
        // Get the Bot User ID from options
        $bot_user_id = get_option( 'ai_bot_user_id' );
        $forum_id = bbp_get_topic_forum_id( $topic_id );

        if ( ! $bot_user_id ) {
            error_log( 'AI Bot Error: Cannot post reply, Bot User ID is not set.' );
            return new \WP_Error( 'config_error', __( 'Bot User ID not configured.', 'ai-bot-for-bbpress' ) );
        }

        // Prepare the reply data
        $reply_data = array(
            'post_parent' => $topic_id,
            'post_author' => $bot_user_id,
            'post_content' => $content,
            'post_title' => 'Re: ' . bbp_get_topic_title( $topic_id ), // Generate a basic title
            // Other necessary meta fields can be added here if needed
        );

        // Insert the reply
        $reply_id = bbp_insert_reply( $reply_data, array('forum_id' => $forum_id) );

        if ( is_wp_error( $reply_id ) ) {
            error_log( 'AI Bot Error: Failed to insert bbPress reply for topic ' . $topic_id . '. Error: ' . $reply_id->get_error_message() );
            return $reply_id; // Return the WP_Error object
        } else {
            error_log( 'AI Bot Info: Successfully posted reply ID ' . $reply_id . ' to topic ID ' . $topic_id );
            return $reply_id;
        }
    }

}
