<?php
/**
 * Plugin Name: Claude by Hafi
 * Description: Integrates Claude AI with WordPress
 * Version: 1.0.0
 * Author: Hafi
 */
if (!defined('ABSPATH')) exit;

// Constants
define('CLAUDE_VERSION',    '1.0.0');
define('CLAUDE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLAUDE_PLUGIN_URL', plugin_dir_url(__FILE__));

// FILES
require_once CLAUDE_PLUGIN_DIR . 'includes/claude_activator.php';
require_once CLAUDE_PLUGIN_DIR . 'includes/claude_deactivator.php';
require_once CLAUDE_PLUGIN_DIR . 'includes/admin_page.php';
require_once CLAUDE_PLUGIN_DIR . 'includes/claude_content.php';

/**
 * Main plugin class — acts as the manager.
 * Its only job is to initialize everything.
 */
class Claude {

    public function __construct() {

        // Activation & deactivation hooks
        register_activation_hook(__FILE__,   array('Claude_Activator',   'activate'));
        register_deactivation_hook(__FILE__, array('Claude_Deactivator', 'deactivate'));

        // Initialize the admin class
        $admin = new Claude_Admin(); //instance of class
        $admin->init();
    }
}

// Create an instance of the main class to kick everything off
// This is the single line that starts the whole plugin
new Claude();