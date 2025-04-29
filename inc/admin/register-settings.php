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
 * Registers the plugin settings.
 */
function bbpress_forum_ai_bot_register_settings() {
	register_setting(
		'bbpress_forum_ai_bot_options_group', // Option group
		'bbpress_forum_ai_bot_user_id', // Option name
		'sanitize_text_field' // Sanitize callback
	);
    register_setting(
		'bbpress_forum_ai_bot_options_group',
		'bbpress_forum_ai_bot_chatgpt_api_key',
		'sanitize_text_field'
	);
    register_setting(
		'bbpress_forum_ai_bot_options_group',
		'bbpress_forum_ai_bot_system_prompt',
		'sanitize_textarea_field'
	);
    register_setting(
		'bbpress_forum_ai_bot_options_group',
		'bbpress_forum_ai_bot_custom_prompt',
		'sanitize_textarea_field'
	);
    register_setting(
		'bbpress_forum_ai_bot_options_group',
		'bbpress_forum_ai_bot_temperature',
		'sanitize_text_field' // Or 'sanitize_text_field' and validate/sanitize in callback
	);
	register_setting(
		'bbpress_forum_ai_bot_options_group',
		'bbpress_forum_ai_bot_trigger_keywords',
		'sanitize_textarea_field' // Store as potentially multi-line, sanitize later
	);
    // Register new setting for local search limit
    register_setting(
        'bbpress_forum_ai_bot_options_group',
        'bbpress_forum_ai_bot_local_search_limit',
        'absint' // Sanitize as absolute integer
    );
	// Register new setting for remote REST endpoint URL
    register_setting(
        'bbpress_forum_ai_bot_options_group',
        'bbpress_forum_ai_bot_remote_endpoint_url',
        'sanitize_url' // Sanitize as a URL
    );
    // Register new setting for remote search limit
    register_setting(
        'bbpress_forum_ai_bot_options_group',
        'bbpress_forum_ai_bot_remote_search_limit',
        'absint' // Sanitize as absolute integer
    );
}

/**
 * Adds settings sections and fields to the admin page.
 */
function bbpress_forum_ai_bot_add_settings_fields() {
	add_settings_section(
		'setting_section_id', // ID
		__( 'Bot User Settings', 'bbpress-forum-ai-bot' ), // Title
		'bbpress_forum_ai_bot_section_info', // Callback
		'bbpress-forum-ai-bot' // Page
	);
    add_settings_section(
		'chatgpt_api_settings_section', // ID
		__( 'ChatGPT API Settings', 'bbpress-forum-ai-bot' ), // Title
		'bbpress_forum_ai_bot_chatgpt_api_section_info', // Callback
		'bbpress-forum-ai-bot' // Page
	);
	add_settings_section(
		'trigger_settings_section', // ID
		__( 'Trigger Settings', 'bbpress-forum-ai-bot' ), // Title
		'bbpress_forum_ai_bot_trigger_section_info', // Callback
		'bbpress-forum-ai-bot' // Page
	);
    // Add new section for Context Retrieval Settings
    add_settings_section(
        'context_retrieval_settings_section', // ID
        __( 'Context Retrieval Settings', 'bbpress-forum-ai-bot' ), // Title
        'bbpress_forum_ai_bot_context_retrieval_section_info', // Callback
        'bbpress-forum-ai-bot' // Page
    );


	add_settings_field(
		'bbpress_forum_ai_bot_user_id', // ID
		__( 'Bot User ID', 'bbpress-forum-ai-bot' ), // Title
		'bbpress_forum_ai_bot_user_id_callback', // Callback
		'bbpress-forum-ai-bot', // Page
		'setting_section_id' // Section
	);
    add_settings_field(
		'bbpress_forum_ai_bot_chatgpt_api_key', // ID
		__( 'ChatGPT API Key', 'bbpress-forum-ai-bot' ), // Title
		'bbpress_forum_ai_bot_chatgpt_api_key_callback', // Callback
		'bbpress-forum-ai-bot', // Page
		'chatgpt_api_settings_section' // Section
	);
    add_settings_field(
		'bbpress_forum_ai_bot_system_prompt', // ID
		__( 'System Prompt', 'bbpress-forum-ai-bot' ), // Title
		'bbpress_forum_ai_bot_system_prompt_callback', // Callback
		'bbpress-forum-ai-bot', // Page
		'chatgpt_api_settings_section' // Section
	);
    add_settings_field(
		'bbpress_forum_ai_bot_custom_prompt', // ID
		__( 'Custom Prompt', 'bbpress-forum-ai-bot' ), // Title
		'bbpress_forum_ai_bot_custom_prompt_callback', // Callback
		'bbpress-forum-ai-bot', // Page
		'chatgpt_api_settings_section' // Section
	);
    add_settings_field(
		'bbpress_forum_ai_bot_temperature', // ID
		__( 'Temperature', 'bbpress-forum-ai-bot' ), // Title
		'bbpress_forum_ai_bot_temperature_callback', // Callback
		'bbpress-forum-ai-bot', // Page
		'chatgpt_api_settings_section' // Section
	);
    add_settings_field(
        'bbpress_forum_ai_bot_trigger_keywords', // ID
        __( 'Trigger Keywords', 'bbpress-forum-ai-bot' ), // Title
        'bbpress_forum_ai_bot_trigger_keywords_callback', // Callback
        'bbpress-forum-ai-bot', // Page
        'trigger_settings_section' // Section
    );
    add_settings_field(
        'bbpress_forum_ai_bot_local_search_limit', // ID
        __( 'Local Search Limit', 'bbpress-forum-ai-bot' ), // Title
        'bbpress_forum_ai_bot_local_search_limit_callback', // Callback
        'bbpress-forum-ai-bot', // Page
        'context_retrieval_settings_section' // Section
    );
    add_settings_field(
        'bbpress_forum_ai_bot_remote_endpoint_url', // ID
        __( 'Remote REST Endpoint URL', 'bbpress-forum-ai-bot' ), // Title
        'bbpress_forum_ai_bot_remote_endpoint_url_callback', // Callback
        'bbpress-forum-ai-bot', // Page
        'context_retrieval_settings_section' // Section
    );
    add_settings_field(
        'bbpress_forum_ai_bot_remote_search_limit', // ID
        __( 'Remote Search Limit', 'bbpress-forum-ai-bot' ), // Title
        'bbpress_forum_ai_bot_remote_search_limit_callback', // Callback
        'bbpress-forum-ai-bot', // Page
        'context_retrieval_settings_section' // Section
    );
}

/*
 * Section content callback function.
 */
function bbpress_forum_ai_bot_section_info() {
	esc_html_e( 'Set the user ID for the bot to use when interacting with the chat service.', 'bbpress-forum-ai-bot' );
}
function bbpress_forum_ai_bot_chatgpt_api_section_info() {
	esc_html_e( 'Configure the ChatGPT API settings to enable AI-powered responses.', 'bbpress-forum-ai-bot' );
}
function bbpress_forum_ai_bot_trigger_section_info() {
	esc_html_e( 'Configure keywords that will trigger the bot to respond, in addition to mentions.', 'bbpress-forum-ai-bot' );
}
// New section info callback for Context Retrieval Settings
function bbpress_forum_ai_bot_context_retrieval_section_info() {
    esc_html_e( 'Configure settings related to retrieving context from local and external sources.', 'bbpress-forum-ai-bot' );
}

/**
 * User ID field callback function.
 */
function bbpress_forum_ai_bot_user_id_callback() {
	$user_id = get_option( 'bbpress_forum_ai_bot_user_id' );
	?>
	<input type="text" name="bbpress_forum_ai_bot_user_id" value="<?php echo isset( $user_id ) ? esc_attr( $user_id ) : ''; ?>" />
	<?php
}
function bbpress_forum_ai_bot_chatgpt_api_key_callback() {
	$api_key = get_option( 'bbpress_forum_ai_bot_chatgpt_api_key' );
	?>
	<input type="text" name="bbpress_forum_ai_bot_chatgpt_api_key" value="<?php echo isset( $api_key ) ? esc_attr( $api_key ) : ''; ?>" />
	<?php
}
function bbpress_forum_ai_bot_system_prompt_callback() {
	$system_prompt = get_option( 'bbpress_forum_ai_bot_system_prompt' );
	?>
	<textarea name="bbpress_forum_ai_bot_system_prompt" rows="5" cols="50"><?php echo isset( $system_prompt ) ? esc_textarea( $system_prompt ) : ''; ?></textarea>
	<?php
}
function bbpress_forum_ai_bot_custom_prompt_callback() {
	$custom_prompt = get_option( 'bbpress_forum_ai_bot_custom_prompt' );
	?>
	<textarea name="bbpress_forum_ai_bot_custom_prompt" rows="5" cols="50"><?php echo isset( $custom_prompt ) ? esc_textarea( $custom_prompt ) : ''; ?></textarea>
	<?php
}
function bbpress_forum_ai_bot_temperature_callback() {
	$temperature = get_option( 'bbpress_forum_ai_bot_temperature' );
	if (empty($temperature)) {
		$temperature = 0.5; // Default temperature if not set
	}
	?>
	<input type="range" name="bbpress_forum_ai_bot_temperature" value="<?php echo esc_attr( $temperature ); ?>" min="0" max="1" step="0.1" />
    <span id="temperature_value"><?php echo esc_attr( $temperature ); ?></span>
    <script>
        // Use unique ID for the script if this file is included multiple times, or better, enqueue JS properly
        const slider_temp = document.querySelector('input[name="bbpress_forum_ai_bot_temperature"]');
        const output_temp = document.getElementById('temperature_value');
        if (slider_temp && output_temp) { // Check elements exist
            output_temp.innerHTML = slider_temp.value; // Display default value
            slider_temp.oninput = function() {
              output_temp.innerHTML = this.value;
            }
        }
    </script>
	<?php
}

/**
 * Trigger Keywords field callback function.
 */
function bbpress_forum_ai_bot_trigger_keywords_callback() {
	$keywords = get_option( 'bbpress_forum_ai_bot_trigger_keywords' );
	?>
	<textarea name="bbpress_forum_ai_bot_trigger_keywords" rows="5" cols="50" placeholder="<?php esc_attr_e( 'Enter keywords, separated by commas or new lines', 'bbpress-forum-ai-bot' ); ?>"><?php echo isset( $keywords ) ? esc_textarea( $keywords ) : ''; ?></textarea>
    <p class="description"><?php esc_html_e( 'The bot will respond if the post content contains any of these keywords (case-insensitive). Separate keywords with commas or new lines.', 'bbpress-forum-ai-bot' ); ?></p>
	<?php
}

// New callback function for local search limit field
function bbpress_forum_ai_bot_local_search_limit_callback() {
    $limit = get_option( 'bbpress_forum_ai_bot_local_search_limit', 3 ); // Default to 3 if not set
    ?>
    <input type="number" name="bbpress_forum_ai_bot_local_search_limit" value="<?php echo esc_attr( $limit ); ?>" min="1" step="1" />
    <p class="description"><?php esc_html_e( 'The maximum number of relevant local content results to include in the context for the AI model.', 'bbpress-forum-ai-bot' ); ?></p>
    <?php
}
// New callback function for remote REST endpoint URL field
function bbpress_forum_ai_bot_remote_endpoint_url_callback() {
    $url = get_option( 'bbpress_forum_ai_bot_remote_endpoint_url' );
    ?>
    <input type="url" name="bbpress_forum_ai_bot_remote_endpoint_url" value="<?php echo isset( $url ) ? esc_url( $url ) : ''; ?>" size="50" placeholder="https://example.com/wp-json/bbp-bot-helper/v1/context" />
    <p class="description"><?php esc_html_e( 'Enter the full URL for the remote REST API endpoint used for context retrieval.', 'bbpress-forum-ai-bot' ); ?></p>
    <?php
}
// New callback function for remote search limit field
function bbpress_forum_ai_bot_remote_search_limit_callback() {
    $limit = get_option( 'bbpress_forum_ai_bot_remote_search_limit', 3 ); // Default to 3 if not set
    ?>
    <input type="number" name="bbpress_forum_ai_bot_remote_search_limit" value="<?php echo esc_attr( $limit ); ?>" min="1" step="1" />
    <p class="description"><?php esc_html_e( 'The maximum number of relevant remote content results to fetch and include in the context.', 'bbpress-forum-ai-bot' ); ?></p>
    <?php
}