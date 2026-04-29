<?php
if (!defined('ABSPATH')) exit;

/*
// init() - registers all hooks, called from main file
// register_menu() - adds the page to WordPress sidebar
// enqueue_assets() - loads CSS and JS
// render_page() - prints the HTML of the settings page
// save_settings() - AJAX handler that saves to database
*/
class Claude_Admin {

    /**
     * init() is called from the main Claude class
     * It registers all the hooks this class needs
     */
    public function init() {
        add_action('admin_menu',             array($this, 'register_menu')); //array(instance, func name)
        add_action('admin_enqueue_scripts',  array($this, 'enqueue_assets')); 
        add_action('wp_ajax_claude_save_settings', array($this, 'save_settings')); 
    }

    /**
     * Registers theAdmin Menu in WordPress sidebar  */
    public function register_menu() {
        add_menu_page(
            'Claude by Hafi',       // Page title
            'Claude by Hafi',       // Sidebar title
            'manage_options',       // Admins only
            'claude_by_hafi',       // Slug
            array($this, 'render_page'), // Callback
            'dashicons-superhero',  // Icon
            80                      // Position
        );
    }

    /**
     * Loads CSS and JS files
     */
    public function enqueue_assets($hook) {
        if ('toplevel_page_claude_by_hafi' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'claude_admin_style',
            CLAUDE_PLUGIN_URL . 'assets/admin.css',
            array(),
            CLAUDE_VERSION
        );

        wp_enqueue_script(
            'claude_admin_script',
            CLAUDE_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            CLAUDE_VERSION,
            true
        );

        // Pass data to JS CREATING NOUNCE
        wp_localize_script('claude_admin_script', 'claude_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('claude_nonce'),
        ));
    }

    
    //   Prints the HTML of the settings page
    public function render_page() { 
        $options        = get_option('claude_settings', array());
        $api_key        = !empty($options['api_key'])        ? esc_attr($options['api_key']) : '';
        $content_access = !empty($options['content_access']) ? 1 : 0;
        $file_access    = !empty($options['file_access'])    ? 1 : 0;
        $db_access      = !empty($options['db_access'])      ? 1 : 0;
        ?>

        <div class="wrap">
            <h1>Claude by Hafi</h1>

            <div id="claude_notice" style="display:none;"></div>

            <table class="form-table">
                <tr>
                    <th>API Key</th>
                    <td><input type="password" id="claude_api_key" value="<?php echo $api_key; ?>" /></td>
                </tr>
                <tr>
                    <th>Posts, Pages & Users</th>
                    <td><input type="checkbox" id="claude_content_access" <?php checked($content_access, 1); ?> /></td>
                </tr>
                <tr>
                    <th>File Manager Access</th>
                    <td><input type="checkbox" id="claude_file_access" <?php checked($file_access, 1); ?> /></td>
                </tr>
                <tr>
                    <th>Database Access</th>
                    <td><input type="checkbox" id="claude_db_access" <?php checked($db_access, 1); ?> /></td>
                </tr>
            </table>

            <button id="claude_save_btn">Save Settings</button>
        </div>

        <?php
    }

    /**
     * AJAX handler - receives data from JS and saves to database
     */
    public function save_settings() {
        check_ajax_referer('claude_nonce', 'nonce');

        $settings = array(
            'api_key'        => sanitize_text_field($_POST['api_key']),
            'content_access' => !empty($_POST['content_access']) ? 1 : 0,
            'file_access'    => !empty($_POST['file_access'])    ? 1 : 0,
            'db_access'      => !empty($_POST['db_access'])      ? 1 : 0,
        );

        update_option('claude_settings', $settings);

        wp_send_json_success(array('message' => 'Settings saved!'));
    }
}   