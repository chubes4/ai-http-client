<?php
namespace AiBot\Core;

use AiBot\Core\Generate_Bot_Response;
use AiBot\Core\Bot_Trigger_Service;
use AiBot\Context\Content_Interaction_Service;
use AiBot\Context\Database_Agent;

/**
 * Main AI Bot for bbPress Class
 */
class AiBot {

    /**
     * @var Bot_Trigger_Service
     */
    private $bot_trigger_service;

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
     * @param Bot_Trigger_Service $bot_trigger_service
     * @param Generate_Bot_Response $generate_bot_response
     * @param Content_Interaction_Service $content_interaction_service
     * @param Database_Agent $database_agent
     */
    public function __construct(
        Bot_Trigger_Service $bot_trigger_service,
        Generate_Bot_Response $generate_bot_response,
        Content_Interaction_Service $content_interaction_service,
        Database_Agent $database_agent
    ) {
        $this->bot_trigger_service         = $bot_trigger_service;
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
        // Register bbPress hooks for bot triggers
        add_action( 'bbp_new_reply', array( $this, 'handle_bot_trigger' ), 9, 5 );
        add_action( 'bbp_new_topic', array( $this, 'handle_bot_trigger' ), 9, 4 );
        
        // Add the cron action hook
        add_action( 'ai_bot_generate_bot_response_event', array( $this->generate_bot_response, 'generate_and_post_ai_response_cron' ), 10, 5 );

        // Initialize admin settings (assuming Admin_Central is procedural)
        // Ensure the functions in admin-central.php are loaded via require_once in the main plugin file
        if ( function_exists('ai_bot_add_options_page') ) {
            // Hooks for admin menu and settings are already added in admin-central.php
            // No need to add them again here.
        } else {
            // error_log('AI Bot Error: Admin functions not loaded.');
        }

    }

    /**
     * Handle mention or keyword trigger
     */
    public function handle_bot_trigger( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author = 0 ) {
        $post_content = ($reply_author == 0) ? bbp_get_topic_content( $post_id ) : bbp_get_reply_content( $post_id );

        // Check if the interaction should be triggered using the injected service
        if ( $this->bot_trigger_service->should_trigger_interaction( $post_id, $post_content, $topic_id, $forum_id ) ) {

            // Schedule cron event to generate and post AI response
            $scheduled = wp_schedule_single_event(
                time(),
                'ai_bot_generate_bot_response_event',
                array( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author )
            );
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
            // error_log( 'AI Bot Error: Cannot post reply, Bot User ID is not set.' );
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
            // error_log( 'AI Bot Error: Failed to insert bbPress reply for topic ' . $topic_id . '. Error: ' . $reply_id->get_error_message() );
            return $reply_id; // Return the WP_Error object
        } else {
            // error_log( 'AI Bot Info: Successfully posted reply ID ' . $reply_id . ' to topic ID ' . $topic_id );
            return $reply_id;
        }
    }

}
