<?php
/**
 * Registers and defines settings for the bbPress Forum AI Bot admin page.
 *
 * @package Bbpress_Forum_AI_Bot
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
    echo '<p>' . __( 'Configure API access and the bot user.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_behavior_settings_section_callback() {
    echo '<p>' . __( 'Define how the bot behaves, its personality, and what triggers responses.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_context_settings_section_callback() {
    echo '<p>' . __( 'Configure how the bot accesses local and remote information to provide contextually relevant answers.', 'ai-bot-for-bbpress' ) . '</p>';
}

// Field Callbacks
function ai_bot_api_key_callback() {
    $api_key = get_option( 'ai_bot_api_key' );
    echo '<input type="password" name="ai_bot_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
    echo '<p class="description">' . __( 'Enter your OpenAI API key.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_user_id_callback() {
    $user_id = get_option( 'ai_bot_user_id' );
    echo '<input type="number" name="ai_bot_user_id" value="' . esc_attr( $user_id ) . '" class="small-text" min="1" step="1" />';
    echo '<p class="description">' . __( 'Enter the WordPress User ID for the bot account.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_system_prompt_callback() {
    $prompt = get_option( 'ai_bot_system_prompt', 'You are a helpful forum assistant.' );
    echo '<textarea name="ai_bot_system_prompt" rows="5" class="large-text">' . esc_textarea( $prompt ) . '</textarea>';
    echo '<p class="description">' . __( 'Define the base personality and role of the bot.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_custom_prompt_callback() {
    $prompt = get_option( 'ai_bot_custom_prompt' );
    echo '<textarea name="ai_bot_custom_prompt" rows="5" class="large-text">' . esc_textarea( $prompt ) . '</textarea>';
    echo '<p class="description">' . __( 'Add specific instructions to guide the bot\'s responses (e.g., formatting rules, tone adjustments). Appended to every request.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_temperature_callback() {
    $temperature = get_option( 'ai_bot_temperature', 0.5 );
    echo '<input type="number" name="ai_bot_temperature" value="' . esc_attr( $temperature ) . '" class="small-text" min="0" max="1" step="0.1" />';
    echo '<p class="description">' . __( 'Controls randomness (0.0 = deterministic, 1.0 = max creativity). Default: 0.5', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_trigger_keywords_callback() {
    $keywords = get_option( 'ai_bot_trigger_keywords', '' );
    echo '<textarea name="ai_bot_trigger_keywords" rows="3" class="large-text">' . esc_textarea( $keywords ) . '</textarea>';
    echo '<p class="description">' . __( 'Comma-separated list of keywords that trigger the bot (case-insensitive). Mentioning the bot user always triggers it.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_local_search_limit_callback() {
    $limit = get_option( 'ai_bot_local_search_limit', 3 );
    echo '<input type="number" name="ai_bot_local_search_limit" value="' . esc_attr( $limit ) . '" min="0" step="1" class="small-text"/>';
    echo '<p class="description">' . __( 'Max number of relevant posts/topics from this forum to use as context. Default: 3.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_remote_endpoint_url_callback() {
    $url = get_option( 'ai_bot_remote_endpoint_url', '' );
    echo '<input type="url" name="ai_bot_remote_endpoint_url" value="' . esc_attr( $url ) . '" class="regular-text" placeholder="https://your-site.com/wp-json/bbp-bot-helper/v1/search" />';
    echo '<p class="description">' . __( 'URL of the BBP Bot Helper plugin\'s REST endpoint on your remote site. Leave blank to disable remote context.', 'ai-bot-for-bbpress' ) . '</p>';
}

function ai_bot_remote_search_limit_callback() {
    $limit = get_option( 'ai_bot_remote_search_limit', 3 );
    echo '<input type="number" name="ai_bot_remote_search_limit" value="' . esc_attr( $limit ) . '" min="0" step="1" class="small-text"/>';
    echo '<p class="description">' . __( 'Max number of relevant posts from the remote site to use as context. Default: 3.', 'ai-bot-for-bbpress' ) . '</p>';
}

// Sanitization callback for temperature
function ai_bot_sanitize_temperature( $input ) {
    $input = floatval($input);
    if ($input < 0) return 0;
    if ($input > 1) return 1;
    return $input;
}