<?php
if (!defined('ABSPATH')) exit;

/**
 * Claude_Deactivator class
 * Handles everything that runs when the plugin is deactivated
 */
class Claude_Deactivator {

    /**
     * 'static' method — same reason as activator
     * called directly without creating an instance
     */
    public static function deactivate() {

        // Check if we have any scheduled cron jobs and remove them
        $timestamp = wp_next_scheduled('claude_scheduled_task');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'claude_scheduled_task');
        }

        // WordPress recommendation on deactivation
        flush_rewrite_rules();
    }
}