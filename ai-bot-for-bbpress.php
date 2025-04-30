<?php
/**
 * Plugin Name: AI Bot for bbPress
 * Plugin URI:  https://wordpress.org/plugins/ai-bot-for-bbpress/
 * Description: AI bot for bbPress forums that can be configured to reply to mentions or keywords.
 * Version:     1.0.1
 * Author:      Chubes
 * Author URI:  https://chubes.net
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Text Domain: ai-bot-for-bbpress
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define root path for convenience
define( 'AI_BOT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Include Service Container
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-ai-bot-service-container.php';

// Include Namespaced Classes
require_once AI_BOT_PLUGIN_PATH . 'inc/api/class-chatgpt-api.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/triggers/class-handle-mention.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/response/class-generate-bot-response.php'; // Corrected path
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-database-agent.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-local-context-retriever.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-remote-context-retriever.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-content-interaction-service.php';
// Include the main bot class
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-ai-bot.php';

// Include admin file
require_once AI_BOT_PLUGIN_PATH . 'inc/admin/admin-central.php';

// --- Service Container Setup ---

// Instantiate the service container class using its full namespace
$container = new AiBot\Core\AiBot_Service_Container();

// Register Services
$container->register( 'api.chatgpt', function( $c ) {
    return new AiBot\API\ChatGPT_API();
} );

$container->register( 'context.database_agent', function( $c ) {
    return new AiBot\Context\Database_Agent();
} );

// Register the local context retriever
$container->register( 'context.local_retriever', function( $c ) {
    return new AiBot\Context\Local_Context_Retriever(
        $c->get( 'api.chatgpt' ),
        $c->get( 'context.database_agent' )
    );
} );

// Register the remote context retriever
$container->register( 'context.remote_retriever', function( $c ) {
    return new AiBot\Context\Remote_Context_Retriever();
} );


// Register the content interaction service
$container->register( 'context.interaction_service', function( $c ) {
    return new AiBot\Context\Content_Interaction_Service(
        $c->get( 'context.database_agent' ),
        $c->get( 'context.local_retriever' ),
        $c->get( 'context.remote_retriever' ),
        $c->get( 'api.chatgpt' )
    );
} );

$container->register( 'triggers.handle_mention', function( $c ) {
    return new AiBot\Triggers\Handle_Mention(
        $c->get( 'context.interaction_service' )
    );
} );

// Register the response generation service
$container->register( 'response.generate_bot', function( $c ) {
    return new AiBot\Response\Generate_Bot_Response(
        $c->get( 'api.chatgpt' ),
        $c->get( 'context.interaction_service' ),
        $c // Pass the container itself
    );
} );

// Register the main bot class
$container->register( 'bot.main', function( $c ) {
    return new AiBot\Core\AiBot(
        $c->get( 'triggers.handle_mention' ),
        $c->get( 'response.generate_bot' ),
        $c->get( 'context.interaction_service' ),
        $c->get( 'context.database_agent' )
    );
} );


// Instantiate and Initialize the Bot via the Container
$ai_bot_instance = $container->get( 'bot.main' );
// Check the init method exists on the main bot class
if (method_exists($ai_bot_instance, 'init')) {
    $ai_bot_instance->init();
} else {
    // error_log('AI Bot for bbPress Error: init() method not found on main bot class.');
}


// --- End Service Container Setup ---

?>