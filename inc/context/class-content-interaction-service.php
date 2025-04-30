<?php

namespace AiBot\Context;

use AiBot\API\ChatGPT_API;
use AiBot\Context\Database_Agent;
use AiBot\Context\Local_Context_Retriever; // Add use statement for Local_Context_Retriever
use AiBot\Context\Remote_Context_Retriever; // Add use statement for Remote_Context_Retriever

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
        $bot_user_id = get_option( 'ai_bot_user_id' );
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
            // error_log( "AI Bot Info: Mention detected for user @{$bot_username}" );
            return true;
        }

        // 2. Check for keywords - Use new option name
        $keywords_string = get_option( 'ai_bot_trigger_keywords', '' );
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

        // --- Extract Keywords for Context Search ---
        // Prepare list of terms to exclude from keywords
        $excluded_terms = ['forum', 'topic', 'post', 'reply', 'thread', 'discussion', 'message', 'conversation'];
        if (isset($bot_username) && $bot_username !== 'Bot') { // Make sure we have a specific bot username
             $excluded_terms[] = '@' . $bot_username; // Add the @mention format
             $excluded_terms[] = $bot_username; // Add the username itself
        }
        $exclude_list_string = implode(', ', $excluded_terms);

        $keywords_comma_separated = ''; // Initialize
        // Refined prompt for keyword extraction V3
        $keyword_extraction_prompt = sprintf(
            "Analyze the following forum post content. Extract the most relevant keywords or phrases for searching a knowledge base to answer the user's core query. Provide the results as a single comma-separated list.\\n\\n".
            "**Guidelines:**\\n".
            "1. Prioritize specific, multi-word phrases (e.g., 'Grateful Dead Ripple', 'artist story sharing') over single generic words (e.g., 'music', 'artists').\\n".
            "2. If the user asks about a specific person, band, song, place, event name, or other named entity, ensure that entity's name is the core part of your primary extracted phrase.\\n".
            "3. Order the results from most central to least central to the user's query.\\n".
            "4. Extract *up to* 3 distinct phrases/keywords. If the user's post is short or very specific, providing only 1 or 2 highly relevant phrases/keywords is preferable to adding less relevant ones.\\n".
            "5. **CRITICAL:** Avoid appending generic terms like 'discussion', 'event', 'forecast', 'update', 'info', 'details', 'question', 'help', or the forum terms (%s) to the core subject. For example, if the post is about 'High Water 2025', extract 'High Water 2025', NOT 'High Water 2025 discussion' or 'High Water 2025 event'. Only include such terms if they are part of a specific official name or title explicitly mentioned in the post content.\\n\\n". // V3 refinement
            "Post Content:\\n%s",
            $exclude_list_string,
            wp_strip_all_tags( $post_content )
        );
        $keywords_response = $this->chatgpt_api->generate_response( $keyword_extraction_prompt, '', '', 0.2 );

        if ( ! is_wp_error( $keywords_response ) && ! empty( $keywords_response ) ) {
            // Keep the raw comma-separated string from OpenAI
            $keywords_comma_separated = trim( $keywords_response );
            if ( ! empty( $keywords_comma_separated ) ) {
                 // Use new logging prefix
                 // error_log('AI Bot Info: Extracted Keywords for Context Search: ' . $keywords_comma_separated);
            } else {
                 // Use new logging prefix
                 // error_log('AI Bot Warning: OpenAI returned empty keyword list for context search.');
            }
        } else {
             // Use new logging prefix
            // error_log('AI Bot Error: Failed to extract keywords for context search: ' . (is_wp_error($keywords_response) ? $keywords_response->get_error_message() : 'Empty response'));
        }

        // *** DEBUG LOG: Extracted Keywords ***
        error_log("AI Bot Debug: Keywords extracted for search: [" . $keywords_comma_separated . "]");
        // *** END DEBUG LOG ***

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

} // End class Content_Interaction_Service

?>