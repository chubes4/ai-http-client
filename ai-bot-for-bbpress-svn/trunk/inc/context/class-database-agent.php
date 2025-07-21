<?php

namespace AiBot\Context;

/**
 * Database Agent Class
 *
 * Handles data retrieval from the WordPress database.
 */
class Database_Agent {

    /**
     * Constructor
     */
    public function __construct() {
        // Constructor logic if needed (currently empty)
    }

    /**
     * Get forum replies for a topic, sorted by upvote count.
     *
     * @param int $topic_id The topic ID.
     * @param int $limit    Maximum number of replies to retrieve (optional, default: 5).
     * @return array Array of forum reply post objects, sorted by upvote count (descending).
     */
    public function get_forum_replies_sorted_by_upvotes( $topic_id, $limit = 5 ) {
        $replies = [];

        $reply_ids = bbp_get_all_child_ids( $topic_id, bbp_get_reply_post_type() );

        if ( empty( $reply_ids ) ) {
            return $replies; // Return empty array if no replies
        }

        // Array to store replies with upvote counts
        $replies_with_upvotes = [];
        foreach ( $reply_ids as $reply_id ) {
            $upvote_count = get_upvote_count( $reply_id ); // Use the upvote count function from theme
            $replies_with_upvotes[] = [
                'reply_id'     => $reply_id,
                'upvote_count' => $upvote_count,
            ];
        }

        // Sort replies by upvote count in descending order
        usort( $replies_with_upvotes, function( $a, $b ) {
            return $b['upvote_count'] <=> $a['upvote_count']; // Descending sort
        } );

        // Get the top N reply IDs based on the limit
        $top_reply_ids = array_slice( array_column( $replies_with_upvotes, 'reply_id' ), 0, $limit );

        if ( ! empty( $top_reply_ids ) ) {
            $replies = get_posts( [
                'post_type'   => bbp_get_reply_post_type(),
                'post__in'    => $top_reply_ids,
                'orderby'     => 'post__in', // Preserve order from $top_reply_ids
                'order'       => 'ASC',
                'numberposts' => -1, // Retrieve all matching replies
            ] );
        }

        return $replies;
    }

    /**
     * Get the most recent forum replies for a topic.
     *
     * @param int   $topic_id         The topic ID.
     * @param int   $limit            Maximum number of replies to retrieve (optional, default: 3).
     * @param array $exclude_post_ids Array of reply IDs to exclude (optional).
     * @param array $exclude_author_ids Array of author IDs to exclude (optional).
     * @return array Array of forum reply post objects, sorted by date (descending).
     */
    public function get_recent_forum_replies( $topic_id, $limit = 3, $exclude_post_ids = array(), $exclude_author_ids = array() ) {
        $args = [
            'post_type'      => bbp_get_reply_post_type(),
            'post_parent'    => $topic_id,
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $exclude_post_ids ) ) {
            $args['post__not_in'] = $exclude_post_ids;
        }

        if ( ! empty( $exclude_author_ids ) ) {
            // Ensure it's an array for the query parameter
            $args['author__not_in'] = (array) $exclude_author_ids;
        }

        $recent_replies = get_posts( $args );
        return $recent_replies;
    }

    /**
     * Get the most recent forum replies by the bot user in a specific topic.
     *
     * @param int   $topic_id    The topic ID.
     * @param int   $bot_user_id The user ID of the bot.
     * @param int   $limit       Maximum number of replies to retrieve (optional, default: 5).
     * @param array $exclude     Array of reply IDs to exclude (optional).
     * @return array Array of forum reply post objects by the bot, sorted by date (descending).
     */
    public function get_bot_replies_in_topic( $topic_id, $bot_user_id, $limit = 5, $exclude = array() ) {
        if ( empty( $bot_user_id ) ) {
            return []; // Cannot fetch bot replies without a user ID
        }

        $args = [
            'post_type'      => bbp_get_reply_post_type(),
            'post_parent'    => $topic_id,
            'author'         => $bot_user_id, // Filter by bot user ID
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $exclude ) ) {
            $args['post__not_in'] = $exclude;
        }

        $bot_replies = get_posts( $args );
        return $bot_replies;
    }

    /**
     * Get chronological replies for a topic, excluding a specific post.
     *
     * @param int   $topic_id         The topic ID.
     * @param int   $limit            Maximum number of replies to retrieve (optional, default: 10).
     * @param array $exclude_post_ids Array of reply IDs to exclude (optional).
     * @return array Array of forum reply post objects, sorted by date (ascending).
     */
    public function get_chronological_topic_replies( $topic_id, $limit = 10, $exclude_post_ids = array() ) {
        $args = [
            'post_type'      => bbp_get_reply_post_type(),
            'post_parent'    => $topic_id,
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC', // Order newest first
            'post_status'    => 'publish' // Ensure only published replies are fetched
        ];

        if ( ! empty( $exclude_post_ids ) ) {
            $args['post__not_in'] = $exclude_post_ids;
        }

        $replies = get_posts( $args );

        // Note: get_posts already returns oldest first with order=ASC
        return $replies;
    }

    /**
     * Search for relevant local posts and pages based on keywords, excluding a specific post.
     *
     * @param string $keywords_comma_separated Comma-separated keywords/phrases from OpenAI.
     * @param int    $limit           Maximum number of results to retrieve (optional, default: 3).
     * @param int    $exclude_post_id Optional. The ID of the post to exclude from search results.
     * @param int    $topic_id        Optional. The ID of the current topic to exclude replies from.
     * @return array Array of relevant post objects.
     */
    public function search_local_content_by_keywords( $keywords_comma_separated, $limit = 3, $exclude_post_id = null, $topic_id = 0 ) {
        if ( empty( $keywords_comma_separated ) ) {
            return [];
        }

        // Store keywords for the filter closure
        $this->current_search_keywords = $keywords_comma_separated;

        // Get the first keyword to use for the 's' parameter to help relevance sorting
        $first_keyword = '';
        $keywords_array = preg_split('/\s*,\s*/', $keywords_comma_separated, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($keywords_array)) {
            $first_keyword = $keywords_array[0];
        }

        $args = [
            'post_type'      => array( 'post', 'page', bbp_get_topic_post_type(), bbp_get_reply_post_type() ), // Search in posts, pages, topics, and replies
            's'              => $first_keyword, // Use first keyword to activate relevance sorting
            'posts_per_page' => -1, // Fetch more results initially for better re-ranking before limiting
            'post_status'    => 'publish', // Only retrieve published content
            'orderby'        => 'relevance', // Change from 'date' to 'relevance'
        ];

        if ( ! empty( $exclude_post_id ) ) {
            $args['post__not_in'] = array( $exclude_post_id );
        }

        // Add the filter to build the custom WHERE clause
        add_filter( 'posts_where', array( $this, 'build_or_search_where_clause' ), 10, 2 );

        // Use WP_Query directly to access the generated SQL query
        // error_log("AI Bot Debug: Running WP_Query with args: " . print_r($args, true)); // Log Args
        $query = new \WP_Query( $args );
        // error_log("AI Bot Debug: Last SQL Query: " . $query->request); // Log full SQL query
        $initial_search_results = $query->posts; // Get the posts from the query result

        // *** DEBUG LOG: Initial Results ***
        // error_log("AI Bot Debug: Initial DB Results Count: " . count($initial_search_results));
        // if (!empty($initial_search_results)) {
            // error_log("AI Bot Debug: Initial DB Result IDs: " . print_r(wp_list_pluck($initial_search_results, 'ID'), true));
        // }
        // *** END DEBUG LOG ***

        // Filter out replies from the current topic
        $filtered_results = [];
        if ( ! empty( $initial_search_results ) && $topic_id > 0 ) {
            // error_log("AI Bot Debug: Filtering out replies from current topic (ID: {$topic_id}) and the trigger post (ID: {$exclude_post_id}) from search results.");
            // Get the reply post type once
            $reply_post_type = bbp_get_reply_post_type();
            // error_log("AI Bot Debug: Current Topic ID for filtering: " . $topic_id);
            // error_log("AI Bot Debug: Excluded Post ID for filtering: " . $exclude_post_id . " | Initial Search Results: " . print_r(wp_list_pluck($initial_search_results, 'ID'), true));

            foreach ( $initial_search_results as $post ) {
                // Keep the post if it's NOT a reply OR if it IS a reply but NOT from the current topic
                // AND also ensure it's not the topic starter post itself.
                $is_reply = ($post->post_type === $reply_post_type);
                $is_current_topic_reply = ($is_reply && $post->post_parent == $topic_id);
                $is_current_topic_post = ($post->ID == $topic_id);

                if ( !$is_current_topic_reply && !$is_current_topic_post ) {
                    $filtered_results[] = $post;
                } else {
                    // *** DEBUG LOG: Excluded Post Details ***
                    $reason = [];
                    if ($is_current_topic_reply) { $reason[] = "Is reply in current topic"; }
                    if ($is_current_topic_post) { $reason[] = "Is current topic post"; }
                    // error_log(
                        // sprintf(
                            // "AI Bot Debug: Excluding Post ID: %d | Type: %s | Parent: %s | Current Topic ID: %d | Reason: %s",
                            // $post->ID,
                            // $post->post_type,
                            // $post->post_parent ?? 'N/A',
                            // $topic_id,
                            // implode(' & ', $reason)
                        // )
                    // );
                    // *** END DEBUG LOG ***
                }
            }
        } else {
            // If no topic ID provided or no initial results, keep all results
            $filtered_results = $initial_search_results;
        }

        // *** DEBUG LOG: Filtered Results ***
        // error_log("AI Bot Debug: Filtered DB Results Count (after topic exclusion): " . count($filtered_results));
        // if (!empty($filtered_results)) {
             // error_log("AI Bot Debug: Filtered DB Result IDs: " . print_r(wp_list_pluck($filtered_results, 'ID'), true));
        // }
        // *** END DEBUG LOG ***

        // Remove the filter to avoid affecting other queries
        remove_filter( 'posts_where', array( $this, 'build_or_search_where_clause' ), 10 );
        unset( $this->current_search_keywords ); // Clean up stored keywords

        // Perform re-ranking based on keyword density if needed
        if (!empty($filtered_results)) {
            // error_log("AI Bot Debug: Re-ranking " . count($filtered_results) . " results based on keyword density.");
            $final_results_after_reranking = $this->rerank_results_by_keyword_density($filtered_results, $this->current_search_keywords);
            // error_log("AI Bot Debug: Results after re-ranking (IDs): " . print_r(wp_list_pluck($final_results_after_reranking, 'ID'), true));
            return array_slice($final_results_after_reranking, 0, $limit); // Ensure limit is respected after re-ranking
        }

        // error_log("AI Bot Debug: No results to re-rank or return.");
        return $filtered_results; // Return filtered (and implicitly limited by original query)
    }

    /**
     * Build the WHERE clause for searching multiple keywords with OR logic.
     *
     * @param string   $where The current WHERE clause.
     * @param WP_Query $query The WP_Query object.
     * @return string The modified WHERE clause.
     */
    public function build_or_search_where_clause( $where, $query ) {
        global $wpdb;

        // Ensure we only modify the main query for our specific search
        if ( empty( $this->current_search_keywords ) || ! $query->is_main_query() && ! $query->get('s') ) {
            return $where;
        }

        $search_terms = preg_split('/\s*,\s*/', $this->current_search_keywords, -1, PREG_SPLIT_NO_EMPTY);
        $search_terms = array_map( 'trim', $search_terms );
        $search_terms = array_filter( $search_terms );

        if ( empty( $search_terms ) ) {
            return $where; // No valid search terms
        }

        $search_where = '';
        foreach ( $search_terms as $term ) {
            $term_escaped = $wpdb->esc_like( $term );
            if ( ! empty( $search_where ) ) {
                $search_where .= " OR ";
            }
            // Search in post_title OR post_content
            $search_where .= $wpdb->prepare(
                "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s)",
                '%' . $term_escaped . '%',
                '%' . $term_escaped . '%'
            );
        }

        if ( ! empty( $search_where ) ) {
            $where .= " AND (" . $search_where . ")";
        }

        // *** DEBUG LOG: WHERE Clause ***
        // Log just the dynamically added part for clarity
        // if (!empty($search_where)) {
            // error_log("AI Bot Debug: SQL WHERE Clause Addition: AND " . $search_where);
        // } else {
            // error_log("AI Bot Debug: SQL WHERE Clause Addition: None (No valid search terms)");
        // }
        // *** END DEBUG LOG ***

        return $where;
    }

    private function rerank_results_by_keyword_density($results, $keywords_comma_separated) {
        if (empty($results) || empty($keywords_comma_separated)) {
            return $results;
        }

        $keywords = array_map('trim', explode(',', strtolower($keywords_comma_separated)));
        $keywords = array_filter($keywords);

        if (empty($keywords)) {
            return $results;
        }

        $scored_results = [];
        foreach ($results as $post) {
            $score = 0;
            $content_lower = strtolower(wp_strip_all_tags($post->post_title . " " . $post->post_content));

            foreach ($keywords as $keyword) {
                $score += substr_count($content_lower, $keyword);
            }
            $scored_results[] = ['post' => $post, 'score' => $score];
        }

        // Sort by score descending, then by date descending as a tie-breaker
        usort($scored_results, function ($a, $b) {
            if ($b['score'] == $a['score']) {
                // Tie-breaker: more recent posts first
                return strtotime($b['post']->post_date) - strtotime($a['post']->post_date);
            }
            return $b['score'] <=> $a['score'];
        });

        // Extract the post objects in the new order
        $reranked_posts = array_map(function($item) { return $item['post']; }, $scored_results);

        // // *** DEBUG LOG: Reranked Results ***
        // $top_result_id = !empty($reranked_posts) ? $reranked_posts[0]->ID : 'None';
        // $top_score = !empty($scored_results) ? $scored_results[0]['score'] : 'N/A';
        // // error_log("AI Bot Debug: Top result for re-ranking: Post ID " . $top_result_id . " with score " . $top_score);
        // // *** END DEBUG LOG ***

        return $reranked_posts;
    }

    // Property to hold keywords temporarily for the filter closure
    private $current_search_keywords = '';
}
