<?php

namespace BbpressForumAiBot\Context;

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

        $args = [
            'post_type'      => array( 'post', 'page', bbp_get_topic_post_type(), bbp_get_reply_post_type() ), // Search in posts, pages, topics, and replies
            's'              => '', // Set search term to empty, logic is handled by posts_where filter
            'posts_per_page' => $limit,
            'post_status'    => 'publish', // Only retrieve published content
            'orderby'        => 'relevance', // Order by relevance - Note: relevance might be less meaningful with custom WHERE
            'order'          => 'DESC',
        ];

        if ( ! empty( $exclude_post_id ) ) {
            $args['post__not_in'] = array( $exclude_post_id );
        }

        // Add the filter to build the custom WHERE clause
        add_filter( 'posts_where', array( $this, 'build_or_search_where_clause' ), 10, 2 );

        // Use WP_Query directly to access the generated SQL query
        $query = new \WP_Query( $args );
        $initial_search_results = $query->posts; // Get the posts from the query result

        // Filter out replies from the current topic
        $filtered_results = [];
        if ( ! empty( $initial_search_results ) && $topic_id > 0 ) {
            foreach ( $initial_search_results as $post ) {
                // Keep the post if it's NOT a reply OR if it IS a reply but NOT from the current topic
                // AND also ensure it's not the topic starter post itself.
                if ( ($post->post_type !== bbp_get_reply_post_type() || $post->post_parent != $topic_id) && $post->ID != $topic_id ) {
                    $filtered_results[] = $post;
                }
            }
        } else {
            // If no topic ID provided or no initial results, keep all results
            $filtered_results = $initial_search_results;
        }

        // Remove the filter immediately after the query
        remove_filter( 'posts_where', array( $this, 'build_or_search_where_clause' ), 10 );
        unset( $this->current_search_keywords ); // Clean up stored keywords

        return $filtered_results;
    }

    /**
     * Builds a custom WHERE clause for WP_Query to search for OR matches across comma-separated phrases.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @param string   $where The WHERE clause of the query.
     * @param WP_Query $query  The WP_Query object.
     * @return string The modified WHERE clause.
     */
    public function build_or_search_where_clause( $where, $query ) {
        global $wpdb;

        // Check if our keywords are set for the current query context
        if ( empty( $this->current_search_keywords ) ) {
            return $where; // Do nothing if keywords aren't set
        }

        // Split comma-separated keywords/phrases, trim whitespace
        $search_terms = array_map( 'trim', explode( ',', $this->current_search_keywords ) );
        $search_terms = array_filter( $search_terms ); // Remove empty entries

        if ( empty( $search_terms ) ) {
            return $where; // Do nothing if no valid terms
        }

        $or_clauses = [];
        foreach ( $search_terms as $term ) {
            // Prepare the term for LIKE comparison
            $like_term = '%' . $wpdb->esc_like( $term ) . '%';
            // Build clause for this term (searching title OR content)
            $or_clauses[] = $wpdb->prepare(
                "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s)",
                $like_term,
                $like_term
            );
        }

        if ( ! empty( $or_clauses ) ) {
            // Combine all term clauses with OR
            $search_where = ' ( ' . implode( ' OR ', $or_clauses ) . ' ) ';
            // Append our custom OR conditions to the existing WHERE clause
            $where .= " AND " . $search_where;
        }

        return $where;
    }

    // Property to hold keywords temporarily for the filter closure
    private $current_search_keywords = '';
}
