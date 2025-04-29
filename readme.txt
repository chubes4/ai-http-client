=== bbPress Forum AI Bot ===
Contributors: chubes
Tags: bbpress, ai, bot, forum, chatgpt, openai
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

bbPress Forum AI Bot integrates seamlessly with your bbPress forums, allowing a configurable AI bot user to participate in discussions. The bot can be triggered by direct mentions (@YourBotUsername) or specific keywords within forum posts, leveraging context from the forum and optionally a remote WordPress site.

== Installation ==

1.  Upload the `bbpress-forum-ai-bot` folder to the `/wp-content/plugins/` directory
2.  Activate the plugin through the 'Plugins' menu in WordPress
3.  Configure the plugin settings under the 'Forum AI Bot' menu in the WordPress admin panel (Settings > Forum AI Bot).

== Configuration ==

After activation, navigate to Settings > Forum AI Bot in your WordPress admin area to configure the following options:

*   **OpenAI API Key:** Your secret API key from OpenAI.
*   **Bot User ID:** The WordPress user ID of the account the bot will use to post replies.
*   **System Prompt:** Instructions defining the bot's personality, role, and general behavior (e.g., "You are a helpful assistant for the Example Community forum.").
*   **Custom Prompt:** Additional instructions appended to every API request, useful for guiding specific response formats or context usage.
*   **Temperature:** Controls the creativity/randomness of the AI's responses (0 = deterministic, 1 = max creativity). Default is 0.5.
*   **Trigger Keywords:** A comma-separated list of keywords (in addition to mentions) that will trigger the bot to respond.
*   **Local Search Limit:** Maximum number of relevant posts/topics to retrieve from the local forum database for context. Default is 3.
*   **Remote REST Endpoint URL:** The full URL to the search endpoint provided by the BBP Bot Helper plugin installed on your remote site (e.g., `https://your-main-site.com/wp-json/bbp-bot-helper/v1/search`). Leave blank to disable remote context.
*   **Remote Search Limit:** Maximum number of relevant posts to retrieve from the remote endpoint for context. Default is 3.

== Frequently Asked Questions ==

= What does this plugin do? =

This plugin allows you to integrate an AI bot into your bbPress forums. The bot can respond to mentions and keywords, providing automated assistance and engaging in discussions.

= How do I configure the bot? =

You can configure the bot's behavior and settings under the 'Forum AI Bot' menu in the WordPress admin panel.

= What is the "Remote REST Endpoint URL" setting? =

The remote context feature allows the bot to search for relevant information on a separate WordPress installation (like your main website). To enable this, you need to install the companion plugin, **BBP Bot Helper**, on that *other* WordPress site. This helper plugin creates a secure REST API endpoint. You then enter the URL of this endpoint into the "Remote REST Endpoint URL" setting in *this* plugin (bbPress Forum AI Bot).

= What is the BBP Bot Helper plugin? =

It provides a REST API endpoint for the bbPress Forum AI Bot plugin to securely query content for context.

= Where do I install the BBP Bot Helper plugin? =

Install it on the WordPress site whose content you want the bbPress Forum AI Bot to be able to search. This is often your main website, separate from where the bbPress forum itself is hosted.

= Does the BBP Bot Helper plugin do anything on its own? =

No. It only provides an API endpoint for use by the bbPress Forum AI Bot plugin.

= Is the BBP Bot Helper endpoint secure? =

The endpoint itself is public by default, typical of WordPress REST API endpoints. Security relies on the fact that only the bbPress Forum AI Bot knows to query it, and it only returns publicly available content (published posts/pages). Access control could be added in future versions if needed.

== Changelog ==

= 1.0.0 =
*   Major Refactor: Generalized codebase for broader use.
*   Feature: Added optional remote context retrieval via BBP Bot Helper companion plugin.
*   Feature: Added Local and Remote search limit settings.
*   Feature: Added Trigger Keywords setting.
*   Feature: Added date/timestamp information to remote context results.
*   Fix: Resolved issues with local and remote context not being correctly passed to the AI model.
*   Fix: Improved reliability of cron job scheduling and execution logging.
*   Update: Added configuration options for temperature and prompts.
*   Update: Tested compatibility up to WordPress 6.8.

= 0.1.2 =
*   Updated plugin name and description.
*   Added helper plugin for remote context.

= 0.1.1 =
*   Bug fixes and minor improvements.

= 0.1.0 =
*   Initial release.