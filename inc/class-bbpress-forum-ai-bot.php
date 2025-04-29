<?php

use BbpressForumAiBot\Triggers\Handle_Mention;
use BbpressForumAiBot\Response\Generate_Bot_Response;
use BbpressForumAiBot\Context\Content_Interaction_Service;
use BbpressForumAiBot\Context\Database_Agent;

/**
 * bbPress Forum AI Bot Class
 */
class Bbpress_Forum_AI_Bot {

    /**
     * Handle Mention instance
     *
     * @var Handle_Mention
     */
    private $handle_mention;

    /**
     * Generate Bot Response instance
     *
     * @var Generate_Bot_Response
     */
    private $generate_bot_response;

    /**
     * Database Agent instance
     *
     * @var Database_Agent
     */
    public $database_agent; // Keep public if accessed externally, otherwise make private

    /**
     * Content Interaction Service instance
     *
     * @var Content_Interaction_Service
     */
    public $content_interaction_service; // Keep public if accessed externally, otherwise make private


    /**
     * Constructor
     *
     * @param Handle_Mention $handle_mention The mention handler instance.
     * @param Generate_Bot_Response $generate_bot_response The response generator instance.
     * @param Content_Interaction_Service $content_interaction_service The content interaction service instance.
     * @param Database_Agent $database_agent The database agent instance.
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
     * Initialize the bot - Register hooks
     */
    public function init() {
        // Debug log removed

        // Register the cron event hook with the new name
        add_action( 'bbpress_forum_ai_bot_generate_bot_response_event', array( $this->generate_bot_response, 'generate_and_post_ai_response_cron' ), 10, 5 );

        // Initialize the mention handler (which registers its own hooks)
        $this->handle_mention->init();

        // Note: Content_Interaction_Service and Database_Agent init() methods were empty,
        // so no need to call them here. If they had initialization logic (like adding hooks),
        // it should be called here or handled within their constructors/service registration.
    }

    // Removed register_mention_handler method as its logic is now handled by Handle_Mention::init()

    /**
     * Post reply as the configured bot user
     * This method is now used as the callback for Generate_Bot_Response
     */
    public function post_bot_reply( $topic_id, $response_content ) {
        // Post reply logic here
        $bot_user_id = $this->get_bot_user_id(); // Get configured bot user ID

        if ( $bot_user_id ) {
            $reply_data = array(
                'post_content'  => $response_content,
                'post_parent'   => $topic_id,
                'post_author'   => $bot_user_id,
                'post_status'   => 'publish',
            );

            $reply_id = bbp_insert_reply( $reply_data );

            if ( is_wp_error( $reply_id ) ) {
                // Log the reply data only if there was an error inserting the reply
                error_log( 'bbPress Forum AI Bot: Error posting reply. Reply Data: ' . print_r( $reply_data, true ) );
                error_log( 'bbPress Forum AI Bot: Error posting reply: ' . $reply_id->get_error_message() );
            }
        } else {
             // Use the new logging prefix
             error_log( 'bbPress Forum AI Bot: Bot User ID not found. Cannot post reply.' );
        }
    }

    /**
     * Get the configured bot user ID from options
     */
    public function get_bot_user_id() {
        // Use the new option name
        return get_option( 'bbpress_forum_ai_bot_user_id' );
    }
}
