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
require_once __DIR__ . '/register-settings.php';

/**
 * Registers the admin menu page.
 */
function bbpress_forum_ai_bot_admin_menu() {
	add_menu_page(
		__( 'bbPress Forum AI Bot Settings', 'bbpress-forum-ai-bot' ), // Page title
		'Forum AI Bot', // Menu title
		'manage_options', // Capability
		'bbpress-forum-ai-bot', // Menu slug
		'bbpress_forum_ai_bot_admin_page', // Callback function
		'dashicons-format-chat', // Icon slug (changed for relevance)
		60 // Position
	);
}
add_action( 'admin_menu', 'bbpress_forum_ai_bot_admin_menu' );

/**
 * Renders the admin page content.
 */
function bbpress_forum_ai_bot_admin_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'bbPress Forum AI Bot Settings', 'bbpress-forum-ai-bot' ); ?></h1>
		<form method="post" action="options.php">
			<?php
				settings_fields( 'bbpress_forum_ai_bot_options_group' );
				do_settings_sections( 'bbpress-forum-ai-bot' );
				submit_button( __( 'Save Settings', 'bbpress-forum-ai-bot' ) );
			?>
		</form>
	</div>
	<?php
}
/**
 * Initializes admin settings and fields.
 */
function bbpress_forum_ai_bot_admin_init() {
    bbpress_forum_ai_bot_register_settings();
    bbpress_forum_ai_bot_add_settings_fields();
}
add_action( 'admin_init', 'bbpress_forum_ai_bot_admin_init' );

/**
 * Registers settings and fields.
 */

/**
 * Section content callback function.
 */
