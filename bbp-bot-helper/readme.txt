=== BBP Bot Helper ===
Contributors: chubes
Tags: bbpress, rest api, api, helper, bot, ai, context
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides a REST API endpoint for the bbPress Forum AI Bot plugin to securely query content for context.

== Description ==

**Important:** This plugin is a companion for the [bbPress Forum AI Bot](https://wordpress.org/plugins/bbpress-forum-ai-bot/) plugin and does not provide functionality on its own. It should be installed on the WordPress site that the bbPress Forum AI Bot needs to *query* for remote context.

BBP Bot Helper creates a custom WordPress REST API endpoint (`/wp-json/bbp-bot-helper/v1/search`). When queried by the bbPress Forum AI Bot plugin (running on a different site, typically where the bbPress forum resides), this endpoint searches the site's content (posts, pages, etc.) based on the provided keywords and returns relevant text snippets.

This allows the AI bot in your bbPress forum to incorporate knowledge and context from your main website or another relevant WordPress site into its responses.

**Key Features:**

*   Creates a secure REST API endpoint for content searching.
*   Designed specifically to work with the bbPress Forum AI Bot plugin.
*   Searches published posts and pages by default.
*   Returns content snippets relevant to search keywords.
*   Simple and lightweight.

== Installation ==

**Install this plugin on the WordPress site you want the bbPress Forum AI Bot to search for context (the *remote* site), NOT necessarily the site where your bbPress forum is located.**

1.  Upload the `bbp-bot-helper` folder to the `/wp-content/plugins/` directory on the remote WordPress site.
2.  Activate the plugin through the 'Plugins' menu in that site's WordPress admin.
3.  There are no settings for this plugin. Once activated, the endpoint is available.
4.  Go to the settings page of the **bbPress Forum AI Bot** plugin (on your forum site) and enter the full URL of this endpoint (e.g., `https://your-remote-site.com/wp-json/bbp-bot-helper/v1/search`) into the "Remote REST Endpoint URL" field.

== Frequently Asked Questions ==

= What does this plugin do? =

It provides a REST API endpoint that allows the bbPress Forum AI Bot plugin (running on another site) to search this site's content for relevant context.

= Where should I install this plugin? =

Install it on the WordPress site whose content you want the bbPress Forum AI Bot to be able to search. This is often your main website, separate from where the bbPress forum itself is hosted.

= Does this plugin work on its own? =

No. It only provides an API endpoint for use by the bbPress Forum AI Bot plugin.

= Is the endpoint secure? =

The endpoint itself is public by default, typical of WordPress REST API endpoints. Security relies on the fact that only the bbPress Forum AI Bot knows to query it, and it only returns publicly available content (published posts/pages). Access control could be added in future versions if needed.

== Screenshots ==

1.  No screenshots needed as there is no admin interface.

== Changelog ==

= 1.0.0 =
*   Initial release. 