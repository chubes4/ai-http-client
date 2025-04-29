<?php

namespace BbpressForumAiBot\Triggers;

use BbpressForumAiBot\Context\Content_Interaction_Service; // Update use statement namespace

/**
 * bbPress Forum AI Bot - Handle Mention/Trigger Class
 */
class Handle_Mention {

    /**
     * Content Interaction Service instance
     *
     * @var Content_Interaction_Service
     */
    private $content_interaction_service;

    /**
     * Constructor
     *
     * @param Content_Interaction_Service $content_interaction_service The content interaction service instance.
     */
    public function __construct( Content_Interaction_Service $content_interaction_service ) {
        $this->content_interaction_service = $content_interaction_service;
    }

    /**
     * Initialize the handler
     */
    public function init() {
        add_action( 'plugins_loaded', array( $this, 'register_mention_handler' ) );
    }

    /**
     * Handle mention or keyword trigger
     */
    public function handle_bot_trigger( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author = 0 ) {
        $post_content = ($reply_author == 0) ? bbp_get_topic_content( $post_id ) : bbp_get_reply_content( $post_id );

        // Check if the interaction should be triggered using the injected service
        if ( $this->content_interaction_service->should_trigger_interaction( $post_id, $post_content, $topic_id, $forum_id ) ) {

            // Log that a matching condition for a bot reply was triggered
            error_log( 'bbPress Forum AI Bot: Matching condition for bot reply triggered for post ID: ' . $post_id );

            // Schedule cron event to generate and post AI response - Use new event name
            $scheduled = wp_schedule_single_event(
                time(),
                'bbpress_forum_ai_bot_generate_bot_response_event',
                array( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author )
            );

            // Log whether the event scheduling was successful
            if ( $scheduled ) {
                error_log( 'bbPress Forum AI Bot: Successfully scheduled response event for post ID: ' . $post_id );
            } else {
                error_log( 'bbPress Forum AI Bot: FAILED to schedule response event for post ID: ' . $post_id . ' (Might already be scheduled)' );
            }

        }
    }

    /**
     * Register mention handler after plugins loaded
     */
    public function register_mention_handler() {
        // Update method name used in add_action calls
        add_action( 'bbp_new_reply', array( $this, 'handle_bot_trigger' ), 11, 5 ); // Priority 11, after extrachill_capture_mention_notifications (priority 12)
        add_action( 'bbp_new_topic', array( $this, 'handle_bot_trigger' ), 11, 4 );
    }
}

?>