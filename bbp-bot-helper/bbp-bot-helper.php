<?php
/**
 * Plugin Name:       BBP Bot Helper
 * Plugin URI:        https://github.com/your-repo/bbpress-forum-ai-bot # Replace with actual plugin URL
 * Description:       Provides a custom REST API endpoint to search content for the bbPress Chinwag Bot.
 * Version:           1.0.0
 * Author:            Chubes
 * Author URI:        https://chubes.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bbp-bot-helper
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'BBP_BOT_HELPER_VERSION', '1.0.0' );
define( 'BBP_BOT_HELPER_PATH', plugin_dir_path( __FILE__ ) );
define( 'BBP_BOT_HELPER_URL', plugin_dir_url( __FILE__ ) );

// Include the REST API class
require_once BBP_BOT_HELPER_PATH . 'inc/class-rest-api.php';

/**
 * Initialize the plugin
 */
function bbp_bot_helper_init() {
    // Instantiate the REST API handler
    $rest_api = new BBP_Bot_Helper\Inc\REST_API();
    $rest_api->register_hooks(); // Register the REST API endpoint
}
add_action( 'plugins_loaded', 'bbp_bot_helper_init' ); 