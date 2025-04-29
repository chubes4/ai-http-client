<?php
/**
 * Admin page for the bbPress Forum AI Bot plugin.
 *
 * @package Bbpress_Forum_AI_Bot
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include settings registration file - Update path if needed
require_once plugin_dir_path( __FILE__ ) . 'register-settings.php';

/**
 * Add options page for AI Bot for bbPress
 */
function ai_bot_add_options_page() {
	add_options_page(
		__( 'AI Bot for bbPress Settings', 'ai-bot-for-bbpress' ), // Page title
		__( 'Forum AI Bot', 'ai-bot-for-bbpress' ),            // Menu title (Keep short?)
		'manage_options',
		'ai-bot-for-bbpress-settings',                        // Menu slug
		'ai_bot_options_page_html'                          // Callback function
	);
}
add_action( 'admin_menu', 'ai_bot_add_options_page' );

/**
 * Register settings for AI Bot for bbPress
 */
function ai_bot_register_settings() {
	// This function now delegates to the function in register-settings.php
	ai_bot_register_all_settings(); // Call the function from the included file
}
add_action( 'admin_init', 'ai_bot_register_settings' );

/**
 * Render the options page HTML
 */
function ai_bot_options_page_html() {
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			// Output security fields for the registered setting group
			settings_fields( 'ai_bot_settings_group' ); // Use the new settings group name
			// Output setting sections and fields
			do_settings_sections( 'ai-bot-for-bbpress-settings' ); // Use the new menu slug
			// Output save settings button
			submit_button( __( 'Save Settings', 'ai-bot-for-bbpress' ) );
			?>
		</form>
	</div>
	<?php
}

/**
 * Section content callback function.
 */
