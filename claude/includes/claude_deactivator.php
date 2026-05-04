<?php
if (!defined('ABSPATH')) exit;

class Claude_Deactivator {

    public static function deactivate() {

        // clear rate limit transients for all users
        $users = get_users(array('fields' => 'ID'));
        foreach ($users as $user_id) {
            delete_transient('claude_rate_' . $user_id);
        }

        // WordPress recommendation on deactivation
        flush_rewrite_rules();
    }
}