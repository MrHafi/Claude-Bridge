=== Claude by Hafi ===
Contributors: hafi
Tags: ai, claude, groq, wordpress assistant, automation
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered WordPress assistant that lets you manage your site using natural language instructions.

== Description ==

Claude by Hafi connects your WordPress dashboard to Groq AI, allowing you to manage your site by simply typing instructions in plain English.

**What you can do:**
* Create and delete posts and pages
* Create, delete and update users
* Install, activate, deactivate and delete plugins
* Install, activate and delete themes
* Manage menus
* Update WordPress settings
* Delete comments

**External Services:**
This plugin sends data to the Groq API (https://groq.com) to process your instructions. The following data is sent:
* Your typed instruction
* A system prompt describing available WordPress actions

Please review Groq's privacy policy at https://groq.com/privacy before using this plugin. You will need a Groq API key to use this plugin.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Claude by Hafi in your admin sidebar
4. Enter your Groq API key in the Integration tab
5. Enable Content Access toggle
6. Start typing instructions in the Chat tab

== Frequently Asked Questions ==

= Where do I get a Groq API key? =
Sign up for free at https://console.groq.com and create an API key.

= Is Groq free to use? =
Groq offers a free tier with generous limits. Check https://groq.com for current pricing.

= Is my data safe? =
Your API key is stored securely in the WordPress database. Instructions are sent to Groq for processing. No data is stored on any third party server permanently.

== Screenshots ==

1. Chat interface — type instructions in plain English
2. Integration tab — enter your API key and manage access toggles
3. Chat history — view and clear past instructions

== Changelog ==

= 1.0.0 =
* Initial release
* Groq AI integration with llama-4-scout model
* Plugin and theme management
* User management
* Post and page management
* Menu management
* Chat history logging
* Rate limiting (20 requests per hour)

== Upgrade Notice ==

= 1.0.0 =
Initial release.