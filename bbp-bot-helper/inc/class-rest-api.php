<?php

namespace BBP_Bot_Helper\Inc;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Handler Class
 */
class REST_API {

	/**
	 * Namespace for the REST API route.
	 *
	 * @var string
	 */
	private $namespace = 'bbp-bot-helper/v1';

	/**
	 * Registers the necessary hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the custom REST API routes.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/search', array(
			'methods'             => \WP_REST_Server::READABLE, // GET requests
			'callback'            => array( $this, 'handle_search_request' ),
			'permission_callback' => '__return_true', // Publicly accessible, adjust if auth needed
			'args'                => array(
				'query' => array(
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param ) && ! empty( $param );
					},
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => esc_html__( 'The search keywords.', 'bbp-bot-helper' ),
				),
				'limit' => array(
					'required'          => false,
					'default'           => 3,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param ) && $param > 0;
					},
					'sanitize_callback' => 'absint',
					'description'       => esc_html__( 'Maximum number of results to return.', 'bbp-bot-helper' ),
				),
			),
		) );
	}

	/**
	 * Handles the search request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error The response object or error.
	 */
	public function handle_search_request( \WP_REST_Request $request ) {
		// Get comma-separated keywords from the 'query' parameter
		$keywords_comma_separated = $request->get_param( 'query' );
		$limit                    = $request->get_param( 'limit' );

		if ( empty( $keywords_comma_separated ) ) {
			return new \WP_Error( 'missing_query', esc_html__( 'Missing required parameter: query.', 'bbp-bot-helper' ), array( 'status' => 400 ) );
		}

		// Extract the first keyword/phrase to use with WP's core search 's' parameter
		$search_terms = array_map( 'trim', explode( ',', $keywords_comma_separated ) );
		$search_terms = array_filter( $search_terms );
		$primary_search_term = ! empty( $search_terms ) ? $search_terms[0] : '';

		if ( empty( $primary_search_term ) ) {
			// If somehow the first term is empty after filtering, return no results
			return new \WP_REST_Response( [], 200 );
		}

		$args = array(
			'post_type'      => array( 'post', 'page' ), // Consider adding other relevant CPTs if needed
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			's'              => $primary_search_term, // Use WP core search with the primary term
			// No custom 'orderby' or 'order' needed here - rely on WP relevance
		);

		// Remove the custom filters as we are now using WP's 's' parameter
		// add_filter( 'posts_where', array( $this, 'build_or_search_where_clause' ), 10, 2 );
		// add_filter( 'posts_orderby', array( $this, 'prioritize_title_matches_orderby' ), 10, 2 );

		$query = new \WP_Query( $args );
		$results = array();

		// No need to remove filters as they were not added
		// remove_filter( 'posts_where', array( $this, 'build_or_search_where_clause' ), 10 );
		// remove_filter( 'posts_orderby', array( $this, 'prioritize_title_matches_orderby' ), 10 );
		// unset( $this->current_search_keywords ); // No longer needed

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$post_content = get_the_content();
				// Clean up content: strip tags, decode entities, trim whitespace
				$cleaned_content = trim( html_entity_decode( wp_strip_all_tags( $post_content ), ENT_QUOTES, 'UTF-8' ) );

				// Optional: Limit content length if needed
				// $cleaned_content = wp_trim_words( $cleaned_content, 100, '...' );

				$results[] = array(
					'title'   => get_the_title(),
					'content' => $cleaned_content,
					'url'     => get_permalink(),
					'date'    => get_the_date( '', get_the_ID() )
				);
			}
			wp_reset_postdata(); // Restore original post data
		}

		// Return the results
		return new \WP_REST_Response( $results, 200 );
	}

	/**
	 * Builds a custom WHERE clause for WP_Query to search for OR matches across comma-separated phrases.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @param string   $where The WHERE clause of the query.
	 * @param \WP_Query $query The WP_Query object.
	 * @return string The modified WHERE clause.
	 */
	public function build_or_search_where_clause( $where, $query ) {
		global $wpdb;

		if ( empty( $this->current_search_keywords ) ) {
			return $where;
		}

		$search_terms = array_map( 'trim', explode( ',', $this->current_search_keywords ) );
		$search_terms = array_filter( $search_terms );

		if ( empty( $search_terms ) ) {
			return $where;
		}

		$or_clauses = [];
		foreach ( $search_terms as $term ) {
			$like_term = '%' . $wpdb->esc_like( $term ) . '%';
			// Attempt to create a version replacing smart quotes and standard quotes for title matching
			$term_no_quotes = str_replace( ['"', '“', '”'], '', $term );
			$like_term_no_quotes = '%' . $wpdb->esc_like( $term_no_quotes ) . '%';

			// Build clause for this term
			// For title, check original term OR term with quotes removed
			// For excerpt and content, just check original term
			$or_clauses[] = $wpdb->prepare(
				"(({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_title LIKE %s) OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s)",
				$like_term,             // Original term for title
				$like_term_no_quotes, // Term with quotes removed for title
				$like_term,             // Original term for excerpt
				$like_term              // Original term for content
			);
		}

		if ( ! empty( $or_clauses ) ) {
			$search_where = ' ( ' . implode( ' OR ', $or_clauses ) . ' ) ';
			$where .= " AND " . $search_where;
		}

		return $where;
	}

	/**
	 * Modify the ORDER BY clause to prioritize title matches.
	 *
	 * @param string   $orderby The ORDER BY clause.
	 * @param \WP_Query $query   The WP_Query object.
	 * @return string Modified ORDER BY clause.
	 */
	public function prioritize_title_matches_orderby( $orderby, $query ) {
		global $wpdb;

		if ( empty( $this->current_search_keywords ) ) {
			return $orderby; // Do nothing if keywords aren't set for this context
		}

		$search_terms = array_map( 'trim', explode( ',', $this->current_search_keywords ) );
		$search_terms = array_filter( $search_terms );

		if ( empty( $search_terms ) ) {
			return $orderby;
		}

		$case_sql = "CASE\n";
		$priority = 1;
		foreach ( $search_terms as $term ) {
			$like_term = '%' . $wpdb->esc_like( $term ) . '%';
			// Assign higher priority (lower number) if the term is in the title
			$case_sql .= $wpdb->prepare( " WHEN {$wpdb->posts}.post_title LIKE %s THEN %d\n", $like_term, $priority );
			$priority++;
		}
		$case_sql .= " ELSE " . ($priority + count($search_terms)) . "\nEND"; // Lower priority for non-title matches

		// Prepend the CASE statement to the existing ORDER BY clause
		// Keep the original orderby (like post_date DESC) as a secondary sort criterion
		$new_orderby = $case_sql . ", " . $orderby;

		return $new_orderby;
	}

	// Property to hold keywords temporarily for the filter closure
	private $current_search_keywords = '';
} 