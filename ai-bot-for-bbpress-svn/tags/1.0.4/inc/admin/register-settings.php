<?php
/**
 * Registers and defines settings for the AI Bot for bbPress admin page.
 *
 * @package Ai_Bot_For_Bbpress
 * @subpackage Admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all settings, sections, and fields for AI Bot for bbPress.
 */
function ai_bot_register_all_settings() {
    $settings_group = 'ai_bot_settings_group';
    $page_slug      = 'ai-bot-for-bbpress-settings';

    // Register settings (Each option needs to be registered)
    register_setting( $settings_group, 'ai_bot_api_key', 'sanitize_text_field' );
    register_setting( $settings_group, 'ai_bot_user_id', 'absint' );
    register_setting( $settings_group, 'ai_bot_system_prompt', 'wp_kses_post' );
    register_setting( $settings_group, 'ai_bot_custom_prompt', 'wp_kses_post' );
    register_setting( $settings_group, 'ai_bot_temperature', 'ai_bot_sanitize_temperature' );
    register_setting( $settings_group, 'ai_bot_trigger_keywords', 'sanitize_textarea_field' );
    register_setting( $settings_group, 'ai_bot_forum_restriction', 'sanitize_text_field' );
    register_setting( $settings_group, 'ai_bot_allowed_forums', 'ai_bot_sanitize_forum_array' );
    register_setting( $settings_group, 'ai_bot_local_search_limit', 'absint' );
    register_setting( $settings_group, 'ai_bot_remote_endpoint_url', 'esc_url_raw' );
    register_setting( $settings_group, 'ai_bot_remote_search_limit', 'absint' );

    // API Settings Section
    add_settings_section(
        'ai_bot_api_settings_section', // ID
        __( 'API Configuration', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_api_settings_section_callback', // Callback
        $page_slug // Page
    );

    // Bot Behavior Section
    add_settings_section(
        'ai_bot_behavior_settings_section', // ID
        __( 'Bot Behavior & Triggers', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_behavior_settings_section_callback', // Callback
        $page_slug // Page
    );

    // Context Settings Section
    add_settings_section(
        'ai_bot_context_settings_section', // ID
        __( 'Context & Knowledge Base', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_context_settings_section_callback', // Callback
        $page_slug // Page
    );

    // Add Fields
    // API Key Field
    add_settings_field(
        'ai_bot_api_key', // ID
        __( 'OpenAI API Key', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_api_key_callback', // Callback
        $page_slug, // Page
        'ai_bot_api_settings_section' // Section
    );

    // Bot User ID Field
    add_settings_field(
        'ai_bot_user_id', // ID
        __( 'Bot User ID', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_user_id_callback', // Callback
        $page_slug, // Page
        'ai_bot_api_settings_section' // Section
    );

    // System Prompt Field
    add_settings_field(
        'ai_bot_system_prompt', // ID
        __( 'System Prompt', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_system_prompt_callback', // Callback
        $page_slug, // Page
        'ai_bot_behavior_settings_section' // Section
    );

    // Custom Prompt Field
    add_settings_field(
        'ai_bot_custom_prompt', // ID
        __( 'Custom Prompt (Instructions)', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_custom_prompt_callback', // Callback
        $page_slug, // Page
        'ai_bot_behavior_settings_section' // Section
    );

    // Temperature Field
    add_settings_field(
        'ai_bot_temperature', // ID
        __( 'Temperature', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_temperature_callback', // Callback
        $page_slug, // Page
        'ai_bot_behavior_settings_section' // Section
    );

    // Trigger Keywords Field
    add_settings_field(
        'ai_bot_trigger_keywords', // ID
        __( 'Trigger Keywords', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_trigger_keywords_callback', // Callback
        $page_slug, // Page
        'ai_bot_behavior_settings_section' // Section
    );

    // Forum Restriction Field
    add_settings_field(
        'ai_bot_forum_restriction', // ID
        __( 'Forum Access Control', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_forum_restriction_callback', // Callback
        $page_slug, // Page
        'ai_bot_behavior_settings_section' // Section
    );

    // Local Search Limit Field
    add_settings_field(
        'ai_bot_local_search_limit', // ID
        __( 'Local Search Limit', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_local_search_limit_callback', // Callback
        $page_slug, // Page
        'ai_bot_context_settings_section' // Section
    );

    // Remote Endpoint URL Field
    add_settings_field(
        'ai_bot_remote_endpoint_url', // ID
        __( 'Remote REST Endpoint URL', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_remote_endpoint_url_callback', // Callback
        $page_slug, // Page
        'ai_bot_context_settings_section' // Section
    );

    // Remote Search Limit Field
    add_settings_field(
        'ai_bot_remote_search_limit', // ID
        __( 'Remote Search Limit', 'ai-bot-for-bbpress' ), // Title
        'ai_bot_remote_search_limit_callback', // Callback
        $page_slug, // Page
        'ai_bot_context_settings_section' // Section
    );

}

// Section Callbacks
function ai_bot_api_settings_section_callback() {
    echo '<p>' . esc_html__( 'Configure API access and the bot user.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_behavior_settings_section_callback() {
    echo '<p>' . esc_html__( 'Define how the bot behaves, its personality, and what triggers responses.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_context_settings_section_callback() {
    echo '<p>' . esc_html__( 'Configure how the bot accesses local and remote information to provide contextually relevant answers.', 'ai-bot-for-bbpress' ) . '</p>';
}

// Field Callbacks
function ai_bot_api_key_callback() {
    $api_key = get_option( 'ai_bot_api_key' );
    echo '<input type="password" name="ai_bot_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__( 'Enter your OpenAI API key.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_user_id_callback() {
    $user_id = get_option( 'ai_bot_user_id' );
    echo '<input type="number" name="ai_bot_user_id" value="' . esc_attr( $user_id ) . '" class="small-text" min="1" step="1" />';
    echo '<p class="description">' . esc_html__( 'Enter the WordPress User ID for the bot account.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_system_prompt_callback() {
    $prompt = get_option( 'ai_bot_system_prompt', 'You are a helpful forum assistant.' );
    echo '<textarea name="ai_bot_system_prompt" rows="5" class="large-text">' . esc_textarea( $prompt ) . '</textarea>';
    echo '<p class="description">' . esc_html__( 'Define the base personality and role of the bot.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_custom_prompt_callback() {
    $prompt = get_option( 'ai_bot_custom_prompt' );
    echo '<textarea name="ai_bot_custom_prompt" rows="5" class="large-text">' . esc_textarea( $prompt ) . '</textarea>';
    echo '<p class="description">' . esc_html__( 'Add specific instructions to guide the bot\'s responses (e.g., formatting rules, tone adjustments). Appended to every request.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_temperature_callback() {
    $temperature = get_option( 'ai_bot_temperature', 0.5 );
    echo '<input type="number" name="ai_bot_temperature" value="' . esc_attr( $temperature ) . '" class="small-text" min="0" max="1" step="0.1" />';
    echo '<p class="description">' . esc_html__( 'Controls randomness (0.0 = deterministic, 1.0 = max creativity). Default: 0.5', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_trigger_keywords_callback() {
    $keywords = get_option( 'ai_bot_trigger_keywords', '' );
    echo '<textarea name="ai_bot_trigger_keywords" rows="3" class="large-text">' . esc_textarea( $keywords ) . '</textarea>';
    echo '<p class="description">' . esc_html__( 'Comma-separated list of keywords that trigger the bot (case-insensitive). Mentioning the bot user always triggers it.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_local_search_limit_callback() {
    $limit = get_option( 'ai_bot_local_search_limit', 3 );
    echo '<input type="number" name="ai_bot_local_search_limit" value="' . esc_attr( $limit ) . '" min="0" step="1" class="small-text"/>';
    echo '<p class="description">' . esc_html__( 'Max number of relevant posts/topics from this forum to use as context. Default: 3.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_remote_endpoint_url_callback() {
    $url = get_option( 'ai_bot_remote_endpoint_url', '' );
    echo '<input type="url" name="ai_bot_remote_endpoint_url" value="' . esc_attr( $url ) . '" class="regular-text" placeholder="https://your-site.com/wp-json/ai-bot-for-bbpress-helper/v1/search" />';
    echo '<p class="description">' . esc_html__( 'URL of the AI Bot for bbPress Helper plugin\'s REST endpoint on your remote site. Leave blank to disable remote context.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_remote_search_limit_callback() {
    $limit = get_option( 'ai_bot_remote_search_limit', 3 );
    echo '<input type="number" name="ai_bot_remote_search_limit" value="' . esc_attr( $limit ) . '" min="0" step="1" class="small-text"/>';
    echo '<p class="description">' . esc_html__( 'Max number of relevant posts from the remote site to use as context. Default: 3.', 'ai-bot-for-bbpress' ) . '</p>';
}

// Forum restriction callback
function ai_bot_forum_restriction_callback() {
    $restriction_mode = get_option( 'ai_bot_forum_restriction', 'all' );
    $allowed_forums = get_option( 'ai_bot_allowed_forums', array() );
    
    echo '<div id="ai-bot-forum-restriction">';
    
    // Radio buttons
    echo '<p>';
    echo '<label><input type="radio" name="ai_bot_forum_restriction" value="all" ' . checked( $restriction_mode, 'all', false ) . ' /> ';
    echo esc_html__( 'All Forums (bot responds in any forum)', 'ai-bot-for-bbpress' ) . '</label><br>';
    echo '<label><input type="radio" name="ai_bot_forum_restriction" value="selected" ' . checked( $restriction_mode, 'selected', false ) . ' /> ';
    echo esc_html__( 'Selected Forums Only', 'ai-bot-for-bbpress' ) . '</label>';
    echo '</p>';
    
    // Forum selection box
    echo '<div id="ai-bot-forum-selection" style="margin-left: 25px; border: 1px solid #ddd; padding: 10px; max-height: 200px; overflow-y: auto; background: #fafafa;">';
    
    // Get forums using WP_Query (bbPress stores forums as 'forum' post type)
    if ( function_exists( 'bbp_get_forum_post_type' ) ) {
        // Get all forums with hierarchy support
        $forums = new WP_Query( array(
            'post_type'      => bbp_get_forum_post_type(),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC'
        ) );
        
        if ( $forums->have_posts() ) {
            // Build hierarchical array
            $forum_hierarchy = array();
            $all_forums = array();
            
            while ( $forums->have_posts() ) {
                $forums->the_post();
                $forum_id = get_the_ID();
                $forum_data = array(
                    'id' => $forum_id,
                    'title' => get_the_title(),
                    'parent' => get_post()->post_parent,
                    'children' => array()
                );
                $all_forums[$forum_id] = $forum_data;
            }
            wp_reset_postdata();
            
            // Build hierarchy
            foreach ( $all_forums as $forum_id => $forum_data ) {
                if ( $forum_data['parent'] == 0 ) {
                    $forum_hierarchy[$forum_id] = $forum_data;
                } else {
                    if ( isset( $all_forums[$forum_data['parent']] ) ) {
                        $all_forums[$forum_data['parent']]['children'][$forum_id] = $forum_data;
                    } else {
                        // Parent doesn't exist, treat as root level
                        $forum_hierarchy[$forum_id] = $forum_data;
                    }
                }
            }
            
            // Update hierarchy with children
            foreach ( $all_forums as $forum_id => $forum_data ) {
                if ( isset( $forum_hierarchy[$forum_id] ) ) {
                    $forum_hierarchy[$forum_id]['children'] = $forum_data['children'];
                }
            }
            
            // Display hierarchical forum list
            ai_bot_display_forum_hierarchy( $forum_hierarchy, $allowed_forums, 0 );
            
        } else {
            echo '<p><em>' . esc_html__( 'No forums found. Create some forums in bbPress to enable forum restriction.', 'ai-bot-for-bbpress' ) . '</em></p>';
        }
    } else {
        echo '<p><em>' . esc_html__( 'bbPress functions not yet loaded. Please refresh the page.', 'ai-bot-for-bbpress' ) . '</em></p>';
    }
    
    echo '</div>';
    echo '<p class="description">' . esc_html__( 'Choose "Selected Forums Only" to restrict the bot to specific forums. This helps prevent spam and keeps the bot focused on professional sections.', 'ai-bot-for-bbpress' ) . '</p>';
    echo '</div>';
    
    // JavaScript for enabling/disabling checkboxes
    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const radioButtons = document.querySelectorAll(\'input[name="ai_bot_forum_restriction"]\');
        const checkboxes = document.querySelectorAll(\'.ai-bot-forum-checkbox\');
        const selectionDiv = document.getElementById(\'ai-bot-forum-selection\');
        
        function updateCheckboxState() {
            const isSelected = document.querySelector(\'input[name="ai_bot_forum_restriction"]:checked\').value === "selected";
            checkboxes.forEach(checkbox => checkbox.disabled = !isSelected);
            selectionDiv.style.opacity = isSelected ? "1" : "0.5";
        }
        
        radioButtons.forEach(radio => radio.addEventListener("change", updateCheckboxState));
        updateCheckboxState(); // Initial state
    });
    </script>';
}

// Sanitization callback for temperature
function ai_bot_sanitize_temperature( $input ) {
    $input = floatval($input);
    if ($input < 0) return 0;
    if ($input > 1) return 1;
    return $input;
}

// Sanitization callback for forum array
function ai_bot_sanitize_forum_array( $input ) {
    if ( ! is_array( $input ) ) {
        return array();
    }
    return array_map( 'absint', $input );
}

/**
 * Display hierarchical forum list with proper indentation
 *
 * @param array $forums Array of forums in hierarchical structure
 * @param array $allowed_forums Array of currently selected forum IDs
 * @param int $depth Current depth level for indentation
 */
function ai_bot_display_forum_hierarchy( $forums, $allowed_forums, $depth = 0 ) {
    foreach ( $forums as $forum ) {
        $forum_id = $forum['id'];
        $forum_title = $forum['title'];
        $checked = in_array( $forum_id, (array) $allowed_forums ) ? 'checked' : '';
        
        // Calculate indentation based on depth
        $indent_style = $depth > 0 ? 'margin-left: ' . ( $depth * 20 ) . 'px;' : '';
        $depth_indicator = $depth > 0 ? str_repeat( '— ', $depth ) : '';
        
        echo '<label style="display: block; margin: 3px 0; ' . esc_attr( $indent_style ) . '">';
        echo '<input type="checkbox" name="ai_bot_allowed_forums[]" value="' . esc_attr( $forum_id ) . '" ' . esc_attr( $checked ) . ' class="ai-bot-forum-checkbox" /> ';
        echo esc_html( $depth_indicator . $forum_title );
        echo '</label>';
        
        // Recursively display child forums
        if ( ! empty( $forum['children'] ) ) {
            ai_bot_display_forum_hierarchy( $forum['children'], $allowed_forums, $depth + 1 );
        }
    }
}