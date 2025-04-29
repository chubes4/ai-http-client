# bbPress Forum AI Bot: Refactoring Complete

This document outlines the steps taken to refactor the bbPress Forum AI Bot plugin. It also considers the steps required for multi-bot support.

## Steps Taken

The following steps were taken to refactor the plugin:

1.  Plugin directory renamed: `sarai-chinwag-bot` to `bbpress-forum-ai-bot`
2.  Main plugin file renamed: `sarai-chinwag.php` to `bbpress-forum-ai-bot.php`
3.  Main plugin class file renamed: `inc/class-sarai-chinwag-bot.php` to `inc/class-bbpress-forum-ai-bot.php`
4.  Main plugin class name updated.
5.  Plugin path constant updated.
6.  Option names updated.
7.  Text domain updated.
8.  Error logging prefix updated.
9.  `@saraichinwag` references updated.
10. `extrachill.com` reference updated.
11. Unnecessary code removed: The `eliza/` directory was removed.

## Multi-Bot Support

Implementing multi-bot support would require a more significant refactor. Here are the steps that would need to be taken:

1.  **Database Changes:**
    *   Create a new database table to store bot settings. This table should include columns for:
        *   `bot_user_id` (INT, primary key, foreign key to the `wp_users` table)
        *   `setting_name` (VARCHAR)
        *   `setting_value` (TEXT)
    *   Modify the existing code to use this table to retrieve bot settings based on the current bot's user ID.
2.  **Settings Management:**
    *   Create a new admin page or modify the existing one to allow administrators to create and manage multiple bots.
    *   For each bot, allow administrators to configure the settings that are currently stored as options (e.g., system prompt, trigger keywords, etc.).
    *   Store these settings in the new database table, associated with the bot's user ID.
3.  **Bot Instantiation:**
    *   Modify the plugin to allow multiple bots to be instantiated.
    *   For each bot, retrieve the settings from the database and use them to configure the bot.
    *   Register each bot with WordPress so that it can respond to mentions and perform other actions.
4.  **Code Modifications:**
    *   Modify the `get_bot_user_id()` method in `inc/class-bbpress-forum-ai-bot.php` to accept a bot user ID as a parameter and retrieve the settings from the database based on that user ID.
    *   Modify the `post_bot_reply()` method in `inc/class-bbpress-forum-ai-bot.php` to accept a bot user ID as a parameter and use that user ID to post the reply.
    *   Modify the cron event registration in `inc/class-bbpress-forum-ai-bot.php` to include the bot user ID as a parameter so that the correct settings can be used when generating the bot response.
    *   Update the regex in `inc/context/class-content-interaction-service.php` to match the bot's username.
    *   Update the reply content in `inc/context/class-content-interaction-service.php` to match the bot's username.
5.  **OpenAI API Key:**
    *   The OpenAI API key will remain a global option for the entire site and does not need to be stored per bot.