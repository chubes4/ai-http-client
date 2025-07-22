<?php

namespace AiBot\Core;

use AiBot\Context\Forum_Structure_Provider;
use AiBot\API\ChatGPT_API;

/**
 * System Prompt Builder Class
 * 
 * Centralizes all logic related to constructing prompts and instructions sent to the AI model.
 */
class System_Prompt_Builder {

    /**
     * @var Forum_Structure_Provider
     */
    private $forum_structure_provider;

    /**
     * @var ChatGPT_API
     */
    private $chatgpt_api;

    /**
     * Constructor
     *
     * @param Forum_Structure_Provider $forum_structure_provider The forum structure provider instance.
     * @param ChatGPT_API $chatgpt_api The ChatGPT API instance for keyword extraction.
     */
    public function __construct(
        Forum_Structure_Provider $forum_structure_provider,
        ChatGPT_API $chatgpt_api
    ) {
        $this->forum_structure_provider = $forum_structure_provider;
        $this->chatgpt_api = $chatgpt_api;
    }

    /**
     * Build complete system prompt with all context and instructions
     *
     * @param string $bot_username The bot's username for identity instructions
     * @return string Complete system prompt ready for API
     */
    public function build_system_prompt( $bot_username ) {
        $system_prompt = get_option( 'ai_bot_system_prompt' );
        
        // Build all instruction blocks
        $date_instruction = $this->build_date_time_instruction();
        $bot_identity_instruction = $this->build_bot_identity_instruction( $bot_username );
        $forum_context = $this->build_forum_context_instruction();
        
        // Combine: Date + Identity + Forum Context + Base System Prompt
        return $date_instruction . $bot_identity_instruction . $forum_context . "\n\n" . $system_prompt;
    }

    /**
     * Build response format instructions for the user prompt
     *
     * @param string $bot_username The bot's username
     * @param string $triggering_username_slug The username slug of the user who triggered the bot
     * @param string $remote_host The hostname of the remote knowledge source
     * @return string Formatted response instructions
     */
    public function build_response_instructions( $bot_username, $triggering_username_slug, $remote_host ) {
        return sprintf(
            "\n--- Your Response Instructions ---\n".
            "1. Understand the user query from the 'CURRENT INTERACTION' section. The user who wrote this (and who you are replying to) is @%s.\n".
            "2. Use the conversation history provided in the message structure above to understand the flow of discussion and maintain context.\n".
            "3. Primarily use information from 'RELEVANT KNOWLEDGE BASE (LOCAL)' and 'RELEVANT KNOWLEDGE BASE (REMOTE)' to answer the query, if they contain relevant information.\n".
            "4. You are @%s. Address @%s directly in your reply if appropriate (e.g., 'Hi @%s, ...').\n".
            "5. **Mentioning Users:** When you need to mention users from the conversation, use the `@username-slug` format from their message prefixes. If you are addressing the user who triggered you, use @%s.\n".
            "6. Cite the source URL (if provided in the context) primarily when presenting specific facts, figures, or direct quotes from the 'RELEVANT KNOWLEDGE BASE' sections (local or remote) to support your response. For general discussion drawing on the context, citation is less critical.\n".
            "7. Your *entire* response must be formatted using only HTML tags suitable for direct rendering in a web page (e.g., <p>, <b>, <i>, <a>, <ul>, <ol>, <li>). \n".
            "8. **Important:** Do NOT wrap your response in Markdown code fences (like ```html) or any other non-HTML wrappers.\n".
            "9. Prefer local knowledge base information if available and relevant.\n".
            "10. Use the remote knowledge base (from %s) to supplement local information or when local context is insufficient.",
            $triggering_username_slug, // For instruction 1
            $bot_username,             // For instruction 4 (who the bot is)
            $triggering_username_slug, // For instruction 4 (who to address)
            $triggering_username_slug, // For instruction 4 (example)
            $triggering_username_slug, // For instruction 5
            $remote_host               // For instruction 10
        );
    }

    /**
     * Build keyword extraction prompt for context search
     *
     * @param string $post_content The post content to extract keywords from
     * @param string $bot_username The bot's username to exclude from keywords
     * @return string Formatted keyword extraction prompt
     */
    public function build_keyword_extraction_prompt( $post_content, $bot_username ) {
        // Prepare list of terms to exclude from keywords
        $excluded_terms = ['forum', 'topic', 'post', 'reply', 'thread', 'discussion', 'message', 'conversation'];
        if ( $bot_username !== 'Bot' ) { // Make sure we have a specific bot username
             $excluded_terms[] = '@' . $bot_username; // Add the @mention format
             $excluded_terms[] = $bot_username; // Add the username itself
        }
        $exclude_list_string = implode(', ', $excluded_terms);

        return sprintf(
            "Analyze the following forum post content. Extract the most relevant keywords or phrases for searching a knowledge base to answer the user's core query. Provide the results as a single comma-separated list.\\n\\n".
            "**Guidelines:**\\n".
            "1. Prioritize specific, multi-word phrases (e.g., 'Grateful Dead Ripple', 'artist story sharing') over single generic words (e.g., 'music', 'artists').\\n".
            "2. If the user asks about a specific person, band, song, place, event name, or other named entity, ensure that entity's name is the core part of your primary extracted phrase.\\n".
            "3. Order the results from most central to least central to the user's query.\\n".
            "4. Extract *up to* 3 distinct phrases/keywords. If the user's post is short or very specific, providing only 1 or 2 highly relevant phrases/keywords is preferable to adding less relevant ones.\\n".
            "5. **CRITICAL:** Avoid appending generic terms like 'discussion', 'event', 'forecast', 'update', 'info', 'details', 'question', 'help', or the forum terms (%s) to the core subject. For example, if the post is about 'High Water 2025', extract 'High Water 2025', NOT 'High Water 2025 discussion' or 'High Water 2025 event'. Only include such terms if they are part of a specific official name or title explicitly mentioned in the post content.\\n\\n".
            "Post Content:\\n%s",
            $exclude_list_string,
            wp_strip_all_tags( $post_content )
        );
    }

    /**
     * Extract keywords using ChatGPT API
     *
     * @param string $post_content The post content to extract keywords from
     * @param string $bot_username The bot's username to exclude from keywords
     * @return string Comma-separated keywords or empty string on failure
     */
    public function extract_keywords( $post_content, $bot_username ) {
        $keyword_extraction_prompt = $this->build_keyword_extraction_prompt( $post_content, $bot_username );
        $keywords_response = $this->chatgpt_api->generate_response( $keyword_extraction_prompt, '', '', 0.2 );

        if ( ! is_wp_error( $keywords_response ) && ! empty( $keywords_response ) ) {
            $keywords_comma_separated = trim( $keywords_response );
            if ( ! empty( $keywords_comma_separated ) ) {
                return $keywords_comma_separated;
            }
        }

        return '';
    }

    /**
     * Build date/time instruction block
     *
     * @return string Formatted date/time instructions
     */
    private function build_date_time_instruction() {
        // Get the current time according to WordPress settings
        $current_datetime = current_time( 'mysql' ); // Format: YYYY-MM-DD HH:MM:SS
        $current_date = current_time( 'Y-m-d' ); // Format: YYYY-MM-DD

        return sprintf(
            "--- MANDATORY TIME CONTEXT ---\n".
            "CURRENT DATE & TIME: %s\n".
            "RULE: You MUST treat %s as the definitive 'today' for determining past/present/future tense.\n".
            "ACTION: Frame all events relative to %s. Use past tense for completed events. Use present/future tense appropriately ONLY for events happening on or after %s.\n".
            "CONSTRAINT: DO NOT discuss events completed before %s as if they are still upcoming.\n".
            "KNOWLEDGE CUTOFF: Your internal knowledge cutoff is irrelevant; operate solely based on this date and provided context.\n".
            "--- END TIME CONTEXT ---",
            $current_datetime,
            $current_date,
            $current_date,
            $current_date,
            $current_date
        );
    }

    /**
     * Build bot identity instruction block
     *
     * @param string $bot_username The bot's username
     * @return string Formatted bot identity instructions
     */
    private function build_bot_identity_instruction( $bot_username ) {
        return sprintf(
            "\n--- YOUR IDENTITY ---\n".
            "YOUR USERNAME: @%s\n".
            "IMPORTANT: You are @%s in this forum. When users mention @%s, they are talking TO YOU, not about someone else.\n".
            "WHEN MENTIONED: Recognize that @%s mentions are directed at you personally and respond accordingly.\n".
            "SELF-REFERENCE: You may refer to yourself as @%s when appropriate in conversations.\n".
            "--- END IDENTITY ---",
            $bot_username,
            $bot_username,
            $bot_username,
            $bot_username,
            $bot_username
        );
    }

    /**
     * Build forum structure context instruction block
     *
     * @return string Formatted forum structure context or empty string
     */
    private function build_forum_context_instruction() {
        $forum_structure_json = $this->forum_structure_provider->get_forum_structure_json();
        
        // Check if the provider returned valid JSON
        if ( ! is_null($forum_structure_json) && json_decode($forum_structure_json) !== null ) {
            $forum_context_header = "--- FORUM CONTEXT ---\n";
            $forum_context_header .= "The following JSON object describes the structure of this forum site. Use this information to understand the site's overall organization and purpose when formulating your responses:\n";
            $forum_context_footer = "\n--- END FORUM CONTEXT ---\n";

            return $forum_context_header . $forum_structure_json . $forum_context_footer;
        }
        
        return '';
    }
}