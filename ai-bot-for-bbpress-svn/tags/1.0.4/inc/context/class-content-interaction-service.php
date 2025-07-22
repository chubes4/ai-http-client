<?php

namespace AiBot\Context;

use AiBot\API\ChatGPT_API;
use AiBot\Context\Database_Agent;
use AiBot\Context\Local_Context_Retriever; // Add use statement for Local_Context_Retriever
use AiBot\Context\Remote_Context_Retriever; // Add use statement for Remote_Context_Retriever
use AiBot\Core\System_Prompt_Builder;

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
     * System Prompt Builder instance
     *
     * @var System_Prompt_Builder
     */
    private $system_prompt_builder;

    /**
     * Constructor
     *
     * @param Database_Agent $database_agent Instance of the database agent.
     * @param Local_Context_Retriever $local_context_retriever Instance of the local context retriever.
     * @param Remote_Context_Retriever $remote_context_retriever Instance of the remote context retriever.
     * @param ChatGPT_API $chatgpt_api Instance of the ChatGPT API.
     * @param System_Prompt_Builder $system_prompt_builder Instance of the system prompt builder.
     */
    public function __construct(
        Database_Agent $database_agent,
        Local_Context_Retriever $local_context_retriever,
        Remote_Context_Retriever $remote_context_retriever,
        ChatGPT_API $chatgpt_api,
        System_Prompt_Builder $system_prompt_builder
    ) {
        $this->database_agent           = $database_agent;
        $this->local_context_retriever  = $local_context_retriever; // Assign the injected instance
        $this->remote_context_retriever = $remote_context_retriever;
        $this->chatgpt_api              = $chatgpt_api; // Assign the injected instance
        $this->system_prompt_builder    = $system_prompt_builder;
    }

    /**
     * Initialize the service
     */
    public function init() {
        // Add hooks or other initialization steps
    }


    /**
     * Get the hostname from the configured remote endpoint URL.
     *
     * @return string The hostname or a default label 'Remote Source'.
     */
    public function get_remote_hostname() {
        // Use new option name
        $remote_url = get_option( 'ai_bot_remote_endpoint_url' );
        $remote_host = 'Remote Source'; // Default label
        if ( ! empty( $remote_url ) ) {
            $parsed_parts = wp_parse_url( $remote_url ); // Use wp_parse_url
            $parsed_host = $parsed_parts['host'] ?? null; // Get host from parsed parts
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

        // Initialize context sections
        $current_interaction_context = "";
        $local_history_context = ""; // Ensure this is initialized
        $local_knowledge_context = "";
        $remote_knowledge_context = "";
        $context_string = ""; // Initialize final string

        // Use new option name
        $bot_user_id = get_option( 'ai_bot_user_id' ); // Get bot user ID
        $bot_username = 'Bot'; // Default display name if not found
        if ( $bot_user_id ) {
             $bot_user_data = get_userdata( $bot_user_id );
             if ( $bot_user_data ) {
                 $bot_username = $bot_user_data->user_login; // Get the actual username
             }
        }

        // --- Section 1: Current Interaction ---
        $current_interaction_context .= "--- CURRENT INTERACTION ---\n";
        $forum_title = bbp_get_forum_title( $forum_id );
        $topic_title = bbp_get_topic_title( $topic_id );
        $current_interaction_context .= "Forum: " . $forum_title . "\n";
        $current_interaction_context .= "Topic: " . $topic_title . "\n";

        // Get author of the current post (the one that triggered the bot)
        $triggering_post_author_id = get_post_field( 'post_author', $post_id );
        $triggering_author_slug = 'anonymous'; // Default
        if ( $triggering_post_author_id ) {
            $triggering_author_data = get_userdata( $triggering_post_author_id );
            if ( $triggering_author_data ) {
                $triggering_author_slug = "@" . $triggering_author_data->user_nicename;
            }
        }
        $current_interaction_context .= "Author of Current Post (who triggered this response): " . $triggering_author_slug . "\n";

        // Add the actual post content that triggered the bot *here*
        $cleaned_post_content = html_entity_decode( wp_strip_all_tags( $post_content ), ENT_QUOTES, 'UTF-8' );
        $cleaned_post_content = str_replace( "\u{00A0}", " ", $cleaned_post_content ); // Replace non-breaking spaces
        $current_interaction_context .= "Current Post Content:\n" . trim($cleaned_post_content) . "\n";
        $current_interaction_context .= "--- END CURRENT INTERACTION ---\n\n";

        // --- Section 2: Conversation History (Chronological) ---
        $conversation_history_context = ""; // New variable for clarity
        $conversation_history_context .= "--- CONVERSATION HISTORY (Oldest First) ---\n";

        // --- Topic Starter Post ---
        $topic_starter_post = get_post($topic_id);
        if ($topic_starter_post) {
             $starter_author_obj = get_userdata( $topic_starter_post->post_author );
             // Use user_nicename (slug) for consistency and mentions
             $starter_author_name = $starter_author_obj ? $starter_author_obj->user_nicename : 'anonymous'; // Changed to user_nicename and lowercase anonymous
             $starter_post_content = bbp_get_topic_content( $topic_id );
             // Format with slug
             $conversation_history_context .= "[Author: @" . $starter_author_name . "] " . trim(html_entity_decode( wp_strip_all_tags( $starter_post_content ), ENT_QUOTES, 'UTF-8' )) . "\n\n"; // Added trim, strip_tags, decode, and @ prefix
        } else {
             $conversation_history_context .= "[Could not retrieve topic starter post.]\n\n";
        }


        // --- Chronological Replies (Excluding Trigger Post) ---
        // Use a limit, e.g., 10 most recent relevant posts before the trigger
        $reply_limit = (int) get_option('ai_bot_reply_history_limit', 10); // Use option
        $chronological_replies = $this->database_agent->get_chronological_topic_replies( $topic_id, $reply_limit, array( $post_id ) );

        if ( ! empty( $chronological_replies ) ) {
            // The query now returns newest first (DESC). Reverse this to get oldest first for display.
            $ordered_replies_for_display = array_reverse( $chronological_replies );

            $conversation_history_context .= "Relevant Previous Replies (Oldest First):\n";
            // Loop through the correctly ordered replies
            foreach ( $ordered_replies_for_display as $reply ) {
                $reply_content    = bbp_get_reply_content( $reply->ID );
                $reply_author_obj = get_userdata( $reply->post_author );
                $reply_author_slug = 'anonymous'; // Default to lowercase anonymous slug
                $is_bot = false;

                if ($reply_author_obj) {
                    $reply_author_slug = $reply_author_obj->user_nicename; // Use user_nicename (slug)
                    // Check if this author is the bot
                    if ( $bot_user_id && $reply->post_author == $bot_user_id ) {
                         // Ensure bot username is fetched correctly if needed here, or use a generic 'You'/'Bot' identifier
                         // Using the bot's slug for consistency is ideal if the bot user has a meaningful slug
                         $bot_user_data = get_userdata( $bot_user_id );
                         $bot_slug = $bot_user_data ? $bot_user_data->user_nicename : 'ai-bot'; // Fallback slug
                         $reply_author_slug = "You (@" . $bot_slug . ")"; // Identify bot replies clearly using slug
                         $is_bot = true;
                    } else {
                         // Format regular user with slug
                         $reply_author_slug = "@" . $reply_author_slug;
                    }
                } else {
                    // Handle anonymous case - already defaulted to 'anonymous'
                     $reply_author_slug = "@anonymous";
                }


                // Decode HTML entities like &nbsp; and clean content
                $cleaned_content = trim(html_entity_decode( wp_strip_all_tags( $reply_content ), ENT_QUOTES, 'UTF-8' ));
                // Include the author slug in the output
                $conversation_history_context .= "[Author: " . $reply_author_slug . "]: " . $cleaned_content . "\n";
            }
            $conversation_history_context .= "\n"; // Add a newline after replies
        } else {
            $conversation_history_context .= "No other replies found in this topic before the current post.\n\n";
        }

        $conversation_history_context .= "--- END CONVERSATION HISTORY ---\n\n";

        // --- Extract Keywords for Context Search using centralized System_Prompt_Builder ---
        $keywords_comma_separated = $this->system_prompt_builder->extract_keywords( $post_content, $bot_username );

        // --- Section 3: Relevant Knowledge Base Search (Coordinator Logic) ---

        // Use new option names for limits
        $local_limit = max(1, intval(get_option( 'ai_bot_local_search_limit', 3 )));
        $remote_limit = max(1, intval(get_option( 'ai_bot_remote_search_limit', 3 )));

        // Initialize results storage
        $local_knowledge_string = ""; // Will hold the formatted string from local retriever
        $remote_knowledge_results = []; // Will hold formatted strings from remote retriever
        $fetched_remote_urls = []; // Track fetched remote URLs for deduplication

        // --- Local Search (Single Query) ---
        if ( ! empty( $keywords_comma_separated ) ) {
            // error_log("AI Bot Info: Searching LOCAL with keywords: '{$keywords_comma_separated}' and limit {$local_limit}");
            // Call local retriever with the full comma-separated string
            // It handles its own limit internally and returns a formatted string
            $local_knowledge_string = $this->local_context_retriever->get_relevant_local_content( $keywords_comma_separated, $post_id, $topic_id );
        }

        // --- Remote Search (Iterative Keyword Search) ---
        $ordered_keywords = [];
        if ( ! empty( $keywords_comma_separated ) ) {
            $ordered_keywords = array_map('trim', explode(',', $keywords_comma_separated));
            $ordered_keywords = array_filter($ordered_keywords); // Remove any empty elements
        }

        if ( ! empty( $ordered_keywords ) ) {
            // error_log('AI Bot Info: Starting iterative REMOTE search with keywords: ' . implode(', ', $ordered_keywords));

            foreach ( $ordered_keywords as $keyword ) {
                $remote_needed = $remote_limit - count($remote_knowledge_results);
                if ( $remote_needed <= 0 ) {
                    // error_log('AI Bot Info: Remote limit reached. Stopping keyword iteration for remote search.');
                    break; // Stop if remote limit is already met
                }

                // error_log("AI Bot Info: Searching REMOTE for keyword '{$keyword}' with limit {$remote_needed}");
                // Call remote retriever with SINGLE keyword and NEEDED limit
                $results_this_keyword_remote = $this->remote_context_retriever->get_remote_context( $keyword, $remote_needed );

                // Process remote results (returned as array ['url' => ..., 'string' => ...])
                if ( is_array($results_this_keyword_remote) && ! empty( $results_this_keyword_remote ) ) {
                    foreach ($results_this_keyword_remote as $remote_result) {
                        // Check if we still need results and if result has expected structure
                        if ( count($remote_knowledge_results) < $remote_limit && isset($remote_result['url'], $remote_result['string']) ) {
                            if ( ! in_array($remote_result['url'], $fetched_remote_urls) ) {
                                $remote_knowledge_results[] = $remote_result['string'];
                                $fetched_remote_urls[] = $remote_result['url'];
                            }
                        }
                        if (count($remote_knowledge_results) >= $remote_limit) break; // Break inner loop if limit reached
                    }
                }
                // Check limit again after processing results for the keyword
                if (count($remote_knowledge_results) >= $remote_limit) {
                    break;
                }
            } // End foreach keyword for remote search

        } else {
            // error_log('AI Bot Info: No keywords extracted, skipping remote knowledge base search.');
        }

        // --- Format Combined Knowledge Base Context ---
        // Initialize the final context section variables if they weren't before (e.g., if no keywords)
        if (!isset($local_knowledge_context)) { $local_knowledge_context = ""; }
        if (!isset($remote_knowledge_context)) { $remote_knowledge_context = ""; }

        $local_knowledge_context .= "--- RELEVANT KNOWLEDGE BASE (LOCAL) ---\n";
        if ( ! empty( $local_knowledge_string ) ) {
             // The string from the retriever already includes the 'Relevant Local Content:' header and formatting
             $local_knowledge_context .= $local_knowledge_string;
        } else {
            $local_knowledge_context .= "No relevant local content found matching keywords.\n";
        }
        $local_knowledge_context .= "\n--- END RELEVANT KNOWLEDGE BASE (LOCAL) ---\n\n";

        // Use the requested, clearer heading for the remote section
        $remote_knowledge_context .= "--- RELEVANT KNOWLEDGE BASE (From the Remote Site) ---\n";
        if ( ! empty( $remote_knowledge_results ) ) {
            // The remote retriever already includes source info in each string
            $remote_knowledge_context .= implode( "\n---\n", $remote_knowledge_results ); // Use simple separator
        } else {
             $remote_host = $this->get_remote_hostname();
             $remote_knowledge_context .= sprintf("No relevant remote content found matching keywords on %s.\n", $remote_host);
        }
        // Update the end delimiter to match the start
        $remote_knowledge_context .= "--- END RELEVANT KNOWLEDGE BASE (From the Remote Site) ---\n\n";

        // --- Combine all context sections ---
        // Ensure base sections are initialized
        if (!isset($current_interaction_context)) { $current_interaction_context = ""; }
        // Use the new conversation history variable
        if (!isset($conversation_history_context)) { $conversation_history_context = ""; }

        $context_string = $current_interaction_context
                        . $conversation_history_context // Use the new variable here
                        . $local_knowledge_context
                        . $remote_knowledge_context;

        return $context_string;
    }

    /**
     * Get conversation history as structured array for proper OpenAI message flow
     *
     * @param int $post_id      The ID of the current post/reply.
     * @param string $post_content The content of the current post/reply.
     * @param int $topic_id     The ID of the topic.
     * @param int $forum_id     The ID of the forum.
     * @return array Array of conversation messages with role/content structure
     */
    public function get_conversation_messages( $post_id, $post_content, $topic_id, $forum_id ) {
        $messages = array();
        
        $bot_user_id = get_option( 'ai_bot_user_id' );
        
        // --- Add Topic Starter as First User Message ---
        $topic_starter_post = get_post($topic_id);
        if ($topic_starter_post && $topic_starter_post->post_author != $bot_user_id) {
            $starter_content = bbp_get_topic_content( $topic_id );
            $starter_author_obj = get_userdata( $topic_starter_post->post_author );
            $starter_author_name = $starter_author_obj ? '@' . $starter_author_obj->user_nicename : '@anonymous';
            
            $cleaned_starter_content = trim(html_entity_decode( wp_strip_all_tags( $starter_content ), ENT_QUOTES, 'UTF-8' ));
            
            $messages[] = array(
                'role' => 'user',
                'content' => $starter_author_name . ': ' . $cleaned_starter_content
            );
        }
        
        // --- Add Chronological Replies ---
        $reply_limit = (int) get_option('ai_bot_reply_history_limit', 10);
        $chronological_replies = $this->database_agent->get_chronological_topic_replies( $topic_id, $reply_limit, array( $post_id ) );
        
        if ( ! empty( $chronological_replies ) ) {
            // Reverse to get oldest first
            $ordered_replies = array_reverse( $chronological_replies );
            
            foreach ( $ordered_replies as $reply ) {
                $reply_content = bbp_get_reply_content( $reply->ID );
                $reply_author_obj = get_userdata( $reply->post_author );
                $is_bot = ( $bot_user_id && $reply->post_author == $bot_user_id );
                
                // Clean content
                $cleaned_content = trim(html_entity_decode( wp_strip_all_tags( $reply_content ), ENT_QUOTES, 'UTF-8' ));
                
                if ( $is_bot ) {
                    // Bot's previous response
                    $messages[] = array(
                        'role' => 'assistant',
                        'content' => $cleaned_content
                    );
                } else {
                    // User message
                    $author_name = $reply_author_obj ? '@' . $reply_author_obj->user_nicename : '@anonymous';
                    $messages[] = array(
                        'role' => 'user',
                        'content' => $author_name . ': ' . $cleaned_content
                    );
                }
            }
        }
        
        // --- Add Current Triggering Message ---
        $triggering_author_obj = get_userdata( get_post($post_id)->post_author );
        $triggering_author_name = $triggering_author_obj ? '@' . $triggering_author_obj->user_nicename : '@anonymous';
        $cleaned_current_content = trim(html_entity_decode( wp_strip_all_tags( $post_content ), ENT_QUOTES, 'UTF-8' ));
        
        $messages[] = array(
            'role' => 'user',
            'content' => $triggering_author_name . ': ' . $cleaned_current_content
        );
        
        return $messages;
    }

} // End class Content_Interaction_Service