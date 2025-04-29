<?php
/**
 * Plugin Name: AI Bot for bbPress
 * Plugin URI:  https://github.com/chubes4/bbpress-forum-ai-bot # Replace with actual URL or leave blank
 * Description: AI bot for bbPress forums that can be configured to reply to mentions or keywords.
 * Version:     1.0.0
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

// Include Service Container - Updated path & name
require_once AI_BOT_PLUGIN_PATH . 'inc/class-ai-bot-service-container.php';

// Include Namespaced Classes (Namespaces will be updated later)
require_once AI_BOT_PLUGIN_PATH . 'inc/api/class-chatgpt-api.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/triggers/class-handle-mention.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/class-generate-bot-response.php'; // Corrected path
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-database-agent.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-local-context-retriever.php'; // Include the local retriever class
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-remote-context-retriever.php'; // Include the remote retriever class
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-content-interaction-service.php';
// Update require path for the main bot class - file will be renamed
require_once AI_BOT_PLUGIN_PATH . 'inc/class-ai-bot.php';

// Include admin file
require_once AI_BOT_PLUGIN_PATH . 'inc/admin/admin-central.php';

// --- Service Container Setup ---

// Instantiate the renamed service container class
$container = new AiBot_Service_Container();

// Register Services (Order matters less now for the circular dependency)
$container->register( 'api.chatgpt', function( $c ) {
    // Use new namespace (placeholder for now, will update class later)
    return new AiBot\API\ChatGPT_API();
} );

$container->register( 'context.database_agent', function( $c ) {
    // Use new namespace (placeholder for now, will update class later)
    return new AiBot\Context\Database_Agent();
} );

// Register the new local context retriever
$container->register( 'context.local_retriever', function( $c ) {
    // Use new namespace (placeholder for now, will update class later)
    return new AiBot\Context\Local_Context_Retriever(
        $c->get( 'api.chatgpt' ), // Local_Context_Retriever needs the API for keyword extraction
        $c->get( 'context.database_agent' )
    );
} );

// Register the new remote context retriever
$container->register( 'context.remote_retriever', function( $c ) {
    // Use new namespace (placeholder for now, will update class later)
    return new AiBot\Context\Remote_Context_Retriever();
} );


// Updated registration for context.interaction_service
$container->register( 'context.interaction_service', function( $c ) {
    // Use new namespace (placeholder for now, will update class later)
    return new AiBot\Context\Content_Interaction_Service(
        $c->get( 'context.database_agent' ),
        $c->get( 'context.local_retriever' ),
        $c->get( 'context.remote_retriever' ),
        $c->get( 'api.chatgpt' ) // Pass the ChatGPT API instance
    );
} );

$container->register( 'triggers.handle_mention', function( $c ) {
    // Use new namespace (placeholder for now, will update class later)
    return new AiBot\Triggers\Handle_Mention(
        $c->get( 'context.interaction_service' )
    );
} );

// Updated registration for response.generate_bot
$container->register( 'response.generate_bot', function( $c ) {
    // Use new namespace (placeholder for now, will update class later)
    return new AiBot\Response\Generate_Bot_Response(
        $c->get( 'api.chatgpt' ),
        $c->get( 'context.interaction_service' ),
        $c // Pass the container itself
    );
} );

// Registration for bot.main (now straightforward) - Update class name here
$container->register( 'bot.main', function( $c ) {
    // Use the new class name (now in the global namespace)
    return new AiBot(
        $c->get( 'triggers.handle_mention' ),
        $c->get( 'response.generate_bot' ),
        $c->get( 'context.interaction_service' ),
        $c->get( 'context.database_agent' )
    );
} );


// Instantiate and Initialize the Bot via the Container
$ai_bot_instance = $container->get( 'bot.main' ); // Renamed variable for clarity
// Assuming the init method exists on the new class
if (method_exists($ai_bot_instance, 'init')) {
    $ai_bot_instance->init();
} else {
    // Log an error if the init method is missing (should not happen if class rename is consistent)
    error_log('AI Bot for bbPress Error: init() method not found on main bot class.');
}


// --- End Service Container Setup ---

?>