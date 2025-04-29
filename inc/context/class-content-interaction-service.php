<?php

namespace BbpressForumAiBot\Context;

use BbpressForumAiBot\API\ChatGPT_API;
use BbpressForumAiBot\Context\Database_Agent;
use BbpressForumAiBot\Context\Local_Context_Retriever; // Add use statement for Local_Context_Retriever
use BbpressForumAiBot\Context\Remote_Context_Retriever; // Add use statement for Remote_Context_Retriever

// Ensure the class files are included. Use require_once to prevent multiple inclusions.
// These require_once calls might become redundant if using an autoloader, but are kept for now.
require_once plugin_dir_path( __FILE__ ) . '../api/class-chatgpt-api.php';
require_once plugin_dir_path( __FILE__ ) . 'class-database-agent.php';
require_once plugin_dir_path( __FILE__ ) . 'class-local-context-retriever.php';
require_once plugin_dir_path( __FILE__ ) . 'class-remote-context-retriever.php';

/**
 * Content Interaction Service Class
 *
 * Handles interactions between OpenAI and the database for context retrieval.
 */
class Content_Interaction_Service {

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
     * Local Context Retriever instance
     *
     * @var Local_Context_Retriever
     */
    private $local_context_retriever; // Add property for Local_Context_Retriever

    /**
     * Remote Context Retriever instance
     *
     * @var Remote_Context_Retriever
     */
    private $remote_context_retriever;

    /**
     * Constructor
     *
     * @param Database_Agent $database_agent Instance of the database agent.
     * @param Local_Context_Retriever $local_context_retriever Instance of the local context retriever.
     * @param Remote_Context_Retriever $remote_context_retriever Instance of the remote context retriever.
     * @param ChatGPT_API $chatgpt_api Instance of the ChatGPT API.
     */
    public function __construct(
        Database_Agent $database_agent,
        Local_Context_Retriever $local_context_retriever,
        Remote_Context_Retriever $remote_context_retriever,
        ChatGPT_API $chatgpt_api
    ) {
        $this->database_agent           = $database_agent;
        $this->local_context_retriever  = $local_context_retriever; // Assign the injected instance
        $this->remote_context_retriever = $remote_context_retriever;
        $this->chatgpt_api              = $chatgpt_api; // Assign the injected instance
    }

    /**
     * Initialize the service
     */
    public function init() {
        // Add hooks or other initialization steps
    }

    /**
     * Check if the interaction should be triggered based on various criteria.
     *
     * @param int    $post_id      The ID of the current post/reply.
     * @param string $post_content The content of the current post/reply.
     * @param int    $topic_id     The ID of the topic.
     * @param int    $forum_id     The ID of the forum.
     * @return bool True if interaction should be triggered, false otherwise.
     */
    public function should_trigger_interaction( $post_id, $post_content, $topic_id, $forum_id ) {

        // Get the Bot User ID and Username - Use new option name
        $bot_user_id = get_option( 'bbpress_forum_ai_bot_user_id' );
        $bot_username = null;
        if ( $bot_user_id ) {
            $bot_user_data = get_userdata( $bot_user_id );
            if ( $bot_user_data ) {
                $bot_username = $bot_user_data->user_login;
            }
        }

        // 1. Check for mention (only if bot username is configured)
        if ( $bot_username && preg_match( '/@' . preg_quote( $bot_username, '/' ) . '/i', $post_content ) ) {
            // Use new logging prefix
            error_log( "bbPress Forum AI Bot: Mention detected for user @{$bot_username}" );
            return true;
        }

        // 2. Check for keywords - Use new option name
        $keywords_string = get_option( 'bbpress_forum_ai_bot_trigger_keywords', '' );
        if ( ! empty( $keywords_string ) ) {
            // Split keywords by comma or newline, trim whitespace, remove empty entries
            $keywords = preg_split( '/[\s,]+/', $keywords_string, -1, PREG_SPLIT_NO_EMPTY );
            $keywords = array_map( 'trim', $keywords );
            $keywords = array_filter( $keywords );

            if ( ! empty( $keywords ) ) {
                // Create a regex pattern to match any keyword (case-insensitive)
                $pattern = '/\b(' . implode( '|', array_map( 'preg_quote', $keywords, ['/'] ) ) . ')\b/i';
                if ( preg_match( $pattern, $post_content ) ) {
                    return true;
                }
            }
        }

        // Add other trigger conditions here (e.g., scheduled tasks)

        return false; // No trigger condition met
    }

    /**
     * Get the hostname from the configured remote endpoint URL.
     *
     * @return string The hostname or a default label 'Remote Source'.
     */
    public function get_remote_hostname() {
        // Use new option name
        $remote_url = get_option( 'bbpress_forum_ai_bot_remote_endpoint_url' );
        $remote_host = 'Remote Source'; // Default label
        if ( ! empty( $remote_url ) ) {
            $parsed_host = parse_url( $remote_url, PHP_URL_HOST );
            if ( $parsed_host ) {
                $remote_host = $parsed_host;
            }
        }
        return $remote_host;
    }

    /**
     * Retrieve relevant context from the database.
     *
     * @param int    $post_id      The ID of the current post/reply.
     * @param string $post_content The content of the current post/reply.
     * @param int    $topic_id     The ID of the topic.
     * @param int    $forum_id     The ID of the forum.
     * @return string Formatted string containing relevant context.
     */
    public function get_relevant_context( $post_id, $post_content, $topic_id, $forum_id ) {

        $context_string = '';
        // Use new option name
        $bot_user_id = get_option( 'bbpress_forum_ai_bot_user_id' ); // Get bot user ID
        $bot_username = 'Bot'; // Default display name if not found
        if ( $bot_user_id ) {
             $bot_user_data = get_userdata( $bot_user_id );
             if ( $bot_user_data ) {
                 $bot_username = $bot_user_data->user_login; // Get the actual username
             }
        }

        // --- Basic Topic Info ---
        $forum_title = bbp_get_forum_title( $forum_id );
        $topic_title = bbp_get_topic_title( $topic_id );
        $starter_post_content = bbp_get_topic_content( $topic_id ); // Get the original post content

        $context_string .= "Forum: " . $forum_title . "\n";
        $context_string .= "Topic: " . $topic_title . "\n\n";
        $context_string .= "Topic Starter Post:\n" . wp_strip_all_tags( $starter_post_content ) . "\n\n";

        // --- Bot's Previous Replies ---
        if ( $bot_user_id ) {
            $bot_replies = $this->database_agent->get_bot_replies_in_topic( $topic_id, $bot_user_id, 5, array( $post_id ) );
            if ( ! empty( $bot_replies ) ) {
                $context_string .= "Your Previous Replies in this Topic (most recent first):\n";
                foreach ( $bot_replies as $reply ) {
                    $reply_content    = bbp_get_reply_content( $reply->ID );
                    // Use the dynamic bot username here
                    $context_string  .= sprintf("[You (@%s)]: %s\n", $bot_username, wp_strip_all_tags( $reply_content ) );
                }
                $context_string .= "\n"; // Add a newline after bot replies
            }
        }

        // --- Recent Replies (Excluding Bot and Current Post) ---
        $exclude_authors = $bot_user_id ? array( $bot_user_id ) : array();
        $recent_replies = $this->database_agent->get_recent_forum_replies( $topic_id, 3, array( $post_id ), $exclude_authors );

        if ( ! empty( $recent_replies ) ) {
            $context_string .= "Recent Replies in this Topic (excluding yours, oldest first):\n";
            // Reverse the order to show oldest first for better conversational flow
            $recent_replies = array_reverse( $recent_replies );
            foreach ( $recent_replies as $reply ) {
                $reply_id         = $reply->ID;
                $reply_content    = bbp_get_reply_content( $reply_id );
                $reply_author_obj = get_userdata( $reply->post_author );
                $reply_author_name = $reply_author_obj ? $reply_author_obj->display_name : 'Anonymous';
                // Decode HTML entities like &nbsp;
                $cleaned_content = html_entity_decode( wp_strip_all_tags( $reply_content ), ENT_QUOTES, 'UTF-8' );
                $context_string  .= "[" . $reply_author_name . "]: " . trim($cleaned_content) . "\n";
            }
            $context_string .= "\n"; // Add a newline after recent replies
        } else {
            $context_string .= "No other recent replies in this topic.\n\n";
        }

        // --- Extract Keywords for Context Search ---
        $keywords_string = '';
        $keywords = [];
        $keyword_extraction_prompt = "Analyze the following forum post content. Extract up to 3 main keywords or topics useful for searching a knowledge base. Order the keywords starting with the most specific and central topic discussed, followed by related but less central topics. Provide the keywords as a single comma-separated list.\n\nPost Content: " . wp_strip_all_tags( $post_content );
        $keywords_response = $this->chatgpt_api->generate_response( $keyword_extraction_prompt, '', '', 0.2 );

        if ( ! is_wp_error( $keywords_response ) && ! empty( $keywords_response ) ) {
            // Keep the raw comma-separated string from OpenAI
            $keywords_comma_separated = trim( $keywords_response );
            if ( ! empty( $keywords_comma_separated ) ) {
                 // Use new logging prefix
                error_log('bbPress Forum AI Bot: Extracted Keywords for Context Search: ' . $keywords_comma_separated);
            } else {
                 // Use new logging prefix
                error_log('bbPress Forum AI Bot: OpenAI returned empty keyword list for context search.');
            }
        } else {
             // Use new logging prefix
            error_log('bbPress Forum AI Bot: Failed to extract keywords for context search: ' . (is_wp_error($keywords_response) ? $keywords_response->get_error_message() : 'Empty response'));
        }

        // --- Initialize Context Variables ---
        $local_content = '';
        // $remote_content = []; // <<< Will be built iteratively

        $final_remote_results = []; // Holds the final formatted strings
        $fetched_remote_urls = []; // Track fetched URLs to prevent duplicates
        $remote_results_count = 0;
        // Use new option name
        $configured_remote_limit = get_option( 'bbpress_forum_ai_bot_remote_search_limit', 3 );

        // Proceed only if we have keywords
        if ( ! empty( $keywords_comma_separated ) ) {
        // --- Relevant Local Content (from search) ---
            // Ensure local content is retrieved before attempting remote fallback
            $local_content = $this->local_context_retriever->get_relevant_local_content( $keywords_comma_separated, $post_id, $topic_id );

            // --- Relevant Remote Content (via Helper Plugin or Direct API) ---
            // Split keywords from the single comma-separated string for iterative search
            $ordered_keywords = array_map('trim', explode(',', $keywords_comma_separated));
            $ordered_keywords = array_filter($ordered_keywords); // Remove any empty elements

            foreach ( $ordered_keywords as $keyword ) {
                if ( $remote_results_count >= $configured_remote_limit ) {
                    error_log("bbPress Forum AI Bot: Reached remote limit ($configured_remote_limit), stopping fallback search.");
                    break; // Stop searching if we've hit the configured limit
                }

                // Calculate how many more results we need
                $needed_limit = $configured_remote_limit - $remote_results_count;

                // Use new logging prefix
                error_log("bbPress Forum AI Bot: Attempting remote context for keyword '{$keyword}' with needed limit {$needed_limit}");

                // Pass the keyword and needed limit to the remote retriever
                $results_this_keyword = $this->remote_context_retriever->get_remote_context( $keyword, $needed_limit );

                if ( is_array( $results_this_keyword ) && ! empty( $results_this_keyword ) ) {
                    // Use new logging prefix
                    error_log( "bbPress Forum AI Bot: Received " . count($results_this_keyword) . " results for keyword '{$keyword}'." );

                    foreach ( $results_this_keyword as $result ) {
                        if ( $remote_results_count >= $configured_remote_limit ) {
                            // Use new logging prefix
                            error_log("bbPress Forum AI Bot: Reached remote limit ({$configured_remote_limit}) within keyword '{$keyword}', breaking inner loop.");
                            break 2; // Break out of both loops if limit reached
                        }

                        // Basic check for expected structure and URL
                        if ( isset( $result['url'] ) && isset( $result['string'] ) && ! empty( $result['url'] ) ) {
                            $url = $result['url'];
                            // Check if this URL has already been added
                            if ( ! in_array( $url, $fetched_remote_urls ) ) {
                                $final_remote_results[] = $result['string']; // Add the formatted string
                                $fetched_remote_urls[] = $url; // Track the URL
                                $remote_results_count++;
                                // Use new logging prefix
                                error_log("bbPress Forum AI Bot: Added remote result #{$remote_results_count} (URL: {$url})");
                            } else {
                                // Use new logging prefix
                                error_log("bbPress Forum AI Bot: Skipped duplicate remote URL: {$url}");
                            }
                        } else {
                            // Use new logging prefix
                            error_log("bbPress Forum AI Bot: Received invalid remote result format for keyword '{$keyword}': " . print_r($result, true));
                        }
                    }
                } else {
                    // Use new logging prefix
                    error_log("bbPress Forum AI Bot: No valid remote results found for keyword '{$keyword}'.");
                }
            } // End foreach keyword
        } else {
            // Use new logging prefix
            error_log("bbPress Forum AI Bot: Skipping context search due to empty keywords.");
        }

        // --- Combine Context ---
        $additional_context = "\nAdditional Relevant Context:\n";
        $has_additional_context = false;

        if ( ! empty( $local_content ) ) {
            $additional_context .= $local_content . "\n"; // Add local content first
            $has_additional_context = true;
        }

        if ( ! empty( $final_remote_results ) ) {
            // Join the formatted remote result strings
            $additional_context .= implode( "\n\n", $final_remote_results ) . "\n";
            $has_additional_context = true;
        }

        // Append the combined additional context if any was found
        if ( $has_additional_context ) {
             $context_string .= $additional_context;
        } else {
            $context_string .= "\nNo additional relevant context was found based on keywords.\n";
        }

        // Log the final context string being returned (optional, can be verbose)
        // error_log("bbPress Forum AI Bot: Final Context String for AI: " . $context_string);

        return $context_string;
    }

} // End class Content_Interaction_Service

?>