<?php
if (!defined('ABSPATH')) exit;

/**
 * Claude_Activator class
 * Handles everything that runs when the plugin is activated
 */
class Claude_Activator {

    /**
     * 'static' means we can call this method without creating an instance
     * That's why in main file we used array('Claude_Activator', 'activate')
     * instead of creating a new object first
     */
    public static function activate() {

        // Save default settings only if they don't exist yet
        if (!get_option('claude_settings')) {
            add_option('claude_settings', array(
                'api_key'        => '',
                'groq_api_key'   => '', // Groq API key
                'content_access' => 0,
                'file_access'    => 0,
                'db_access'      => 0,
            ));
        }

        // Save plugin version to database
        update_option('claude_version', CLAUDE_VERSION);

        // WordPress recommendation on activation
        flush_rewrite_rules();
    }
}