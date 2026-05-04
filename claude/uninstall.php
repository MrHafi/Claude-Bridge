<?php
// block direct access — only WordPress can call this file
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// delete all plugin options from database
delete_option('claude_settings');
delete_option('claude_chat_history');
delete_option('claude_version');

// delete all rate limit transients for all users
$users = get_users(array('fields' => 'ID'));
foreach ($users as $user_id) {
    delete_transient('claude_rate_' . $user_id);
}

// delete the log file and folder from uploads
$upload   = wp_upload_dir();
$log_dir  = $upload['basedir'] . '/claude-logs/';
$log_file = $log_dir . 'groq_chat_log.txt';

if (file_exists($log_file)) {
    unlink($log_file); // delete the log file
}

if (is_dir($log_dir) && count(scandir($log_dir)) == 2) {
    rmdir($log_dir); // delete the folder only if it is empty
}