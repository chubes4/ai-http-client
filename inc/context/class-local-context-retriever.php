<?php

namespace AiBot\Context;

use AiBot\API\ChatGPT_API;
use AiBot\Context\Database_Agent;

/**
 * Local Context Retriever Class
 *
 * Handles intelligent keyword extraction and local database content retrieval.
 */
class Local_Context_Retriever {

    /**
     * ChatGPT API instance
     *
     * @var ChatGPT_API
     */
    private $chatgpt_api;

    /**
     * Database Agent instance
     *
     * @var Database_Agent
     */
    private $database_agent;

    /**
     * Constructor
     *
     * @param ChatGPT_API $chatgpt_api The ChatGPT API instance.
     * @param Database_Agent $database_agent The database agent instance.
     */
    public function __construct( ChatGPT_API $chatgpt_api, Database_Agent $database_agent ) {
        $this->chatgpt_api    = $chatgpt_api;
        $this->database_agent = $database_agent;
    }

    /**
     * Get relevant local content based on search keywords, excluding a specific post and topic replies.
     *
     * @param string $keywords_comma_separated Comma-separated keywords/phrases from OpenAI.
     * @param int    $exclude_post_id Optional. The ID of the post to exclude from search results.
     * @param int    $topic_id The ID of the current topic to exclude replies from.
     * @return string Formatted string containing relevant local content, or empty string if none found.
     */
    public function get_relevant_local_content( $keywords_comma_separated, $exclude_post_id = null, $topic_id = 0 ) {
        if ( empty( $keywords_comma_separated ) ) {
            return []; // Return empty array if no keywords
        }

        $limit = get_option('ai_bot_local_search_limit', 3); // Get limit from settings, default to 3
        // Ensure limit is an integer and at least 1
        $limit = max(1, intval($limit));

        $search_results = $this->database_agent->search_local_content_by_keywords( $keywords_comma_separated, $limit, $exclude_post_id, $topic_id );

        // Log the count of database search results found (before potential truncation by limit)
        $initial_results_count = count($search_results);
        // error_log('AI Bot Info: Database Search Found ' . $initial_results_count . ' potentially relevant local results before limit.');

        // Format the relevant search results for context
        $formatted_context = '';
        if ( ! empty( $search_results ) ) {
            $formatted_context .= "Relevant Context (From This Forum):\n";
            $result_counter = 1;
            foreach ( $search_results as $post ) {
                $formatted_context .= "--- Forum Result " . $result_counter++ . " ---\n";
                $formatted_context .= "Title: " . get_the_title( $post ) . "\n";

                // --- Add Forum Name if applicable ---
                $forum_name = null;
                $topic_post_type = \bbp_get_topic_post_type(); // Global prefix
                $reply_post_type = \bbp_get_reply_post_type(); // Global prefix

                if ($post->post_type === $topic_post_type) {
                    $forum_id = \bbp_get_topic_forum_id($post->ID); // Global prefix
                    if ($forum_id) {
                        $forum_name = \bbp_get_forum_title($forum_id); // Global prefix
                    }
                } elseif ($post->post_type === $reply_post_type) {
                    $forum_id = \bbp_get_reply_forum_id($post->ID); // Global prefix
                    if ($forum_id) {
                        $forum_name = \bbp_get_forum_title($forum_id); // Global prefix
                    }
                }

                if ($forum_name) {
                    $formatted_context .= "Forum: " . esc_html($forum_name) . "\n";
                }
                // --- End Add Forum Name ---

                $formatted_context .= "Date: " . get_the_date( '', $post ) . "\n"; // Include date
                $formatted_context .= "URL: " . get_permalink( $post ) . "\n";
                // Get full post content, strip tags, and decode entities
                $full_content = get_post_field('post_content', $post);
                $cleaned_full_content = html_entity_decode( wp_strip_all_tags( $full_content ), ENT_QUOTES, 'UTF-8' );
                // Provide a snippet instead of full content if desired (optional optimization)
                // For now, keep full cleaned content
                $formatted_context .= "Content Snippet:\n" . trim($cleaned_full_content) . "\n";
                $formatted_context .= "--- End Forum Result ---\n\n";
            }
        }

        return $formatted_context;
    }
}