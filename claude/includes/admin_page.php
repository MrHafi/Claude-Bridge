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

    /*  It registers all the hooks this class needs*/
    public function init() {
        add_action('admin_menu',             array($this, 'register_menu')); //array(instance, func name)
        add_action('admin_enqueue_scripts',  array($this, 'enqueue_assets')); 
        add_action('wp_ajax_claude_save_settings', array($this, 'save_settings'));
        // HooK FOR GROQ CONNECTION TEST
        add_action('wp_ajax_claude_test_groq', array($this, 'test_groq_connection')); 
        //CLAUD.GROQ HANDLE INSTRUCTIONS
        add_action('wp_ajax_claude_handle_instruction', array($this, 'handle_instruction'));


        // clear log 
        add_action('wp_ajax_claude_clear_log', array($this, 'clear_log'));
    }

    /* Registers theAdmin Menu in WordPress sidebar  */
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

    /* Loads CSS and JS files */
    public function enqueue_assets($hook) {
        if ('toplevel_page_claude_by_hafi' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'claude_admin_style',
            CLAUDE_PLUGIN_URL . 'assets/style.css',
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

    
    //   FRONTEND OF ADMIN MENU -------------------------------------public function render_page() {
    public function render_page() {
    require_once CLAUDE_PLUGIN_DIR . 'includes/admin_view.php';
}


    
    /* SAVE SETTIGN INTO DB FROM JS  */
    public function save_settings() {
        check_ajax_referer('claude_nonce', 'nonce');

        // sec check
        if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'));
        }

        $settings = array(
            'api_key'        => sanitize_text_field($_POST['api_key']),
            'groq_api_key' => sanitize_text_field($_POST['groq_api_key']),
            'content_access' => !empty($_POST['content_access']) ? 1 : 0,
            'file_access'    => !empty($_POST['file_access'])    ? 1 : 0,
            'db_access'      => !empty($_POST['db_access'])      ? 1 : 0    
            
        );

        update_option('claude_settings', $settings); //UPDATING IN DB

        wp_send_json_success(array('message' => 'Settings saved!'));
    }



// test groq connection
public function test_groq_connection() {

        // sec check
        if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'));
        }

    check_ajax_referer('claude_nonce', 'nonce');

    $options      = get_option('claude_settings', array());
    $groq_api_key = !empty($options['groq_api_key']) ? $options['groq_api_key'] : ''; //grab key if not empty

        // EMPTY KEY
        if (empty($groq_api_key)) {
            wp_send_json_error(array('message' => 'Groq API key is missing!'));
        }

    $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
        'timeout'   => 30, // add timeout
        'sslverify' => true, 
        'headers'   => array(
            'Authorization' => 'Bearer ' . $groq_api_key, 
            'Content-Type'  => 'application/json',
        ),
            // ACTUAKL DATA SENT TO GROQ
        'body' => json_encode(array(
            'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
            'messages' => array(
                array(
                    'role'    => 'user', //MESAGE FROM SUER SIDE
                    'content' => 'Just reply with: Groq is connected successfully!'
                )
            )
        )),
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Request failed: ' . $response->get_error_message()));
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body      = json_decode(wp_remote_retrieve_body($response), true); //GET RESPONSE FROM GROQ

    // If not 200 show exact error from Groq
    if ($http_code !== 200) {
        wp_send_json_error(array('message' => 'Groq error: ' . ($body['error']['message'] ?? 'Unknown error') ));
    }

    $message = $body['choices'][0]['message']['content'];
    wp_send_json_success(array('message' => $message));
}




// TRIGGERED THE ASK GROK FUNCTION 
public function handle_instruction() {


        // sec check
        if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'));
        }

    check_ajax_referer('claude_nonce', 'nonce');

    $instruction = sanitize_text_field($_POST['instruction']);
    $options     = get_option('claude_settings', array());


    // MAX LENTGH FOR USER CHECK    
    if (strlen($instruction) > 500) {
        wp_send_json_error(array('message' => 'Instruction too long. Keep it under 500 characters.'));
    }

    if (empty($options['content_access'])) {
        wp_send_json_error(array('message' => 'Content access is OFF. Enable it in settings first.'));
    }

    // send to groq — now returns array of actions
    $actions = claude_ask_groq($instruction, $options['groq_api_key']);

    if (!$actions || !is_array($actions)) {
        wp_send_json_error(array('message' => 'Failed to get response from Groq.'));
    }

                    // PREVENTING GROQ FROM RUNNMING SOMETHING MALLECIOUS. ONLY LIMITED CONCEPT WILL GO
                    $allowed_actions = array(
                        'create_post', 'delete_post', 'create_page', 'delete_page',
                        'create_user', 'delete_user', 'update_user_role',
                        'install_plugin', 'activate_plugin', 'deactivate_plugin', 'delete_plugin', 'update_plugin',
                        'install_theme', 'activate_theme', 'delete_theme', 'update_theme',
                        'update_setting', 'flush_permalinks',
                        'delete_comment', 'delete_spam_comments', 'delete_all_comments',
                        'create_menu', 'add_page_to_menu', 'add_link_to_menu', 'delete_menu',
                        'unclear'
                    );

                    foreach ($actions as $action_data) {
                        if (!in_array($action_data['action'] ?? '', $allowed_actions)) {
                            wp_send_json_error(array('message' => 'Invalid action returned. Please try again and stay in WordPress Dashboard Teritory.'));
                        }
                    }


    $results = array();

    // loop through each action and execute one by one
    foreach ($actions as $action_data) {
        $result    = claude_execute_content($action_data);
        $results[] = $result;

        // log each action separately
        claude_log_action($instruction, $action_data['action'] ?? 'unknown', $result);
    }

    // join all results and send back to JS
    wp_send_json_success(array('message' => implode('<br>', $results)));


// rate limit check 20/hour
if (!claude_check_rate_limit()) {
    wp_send_json_error(array('message' => 'Rate limit reached. You can send 20 instructions per hour. Please wait before trying again.'));
}

}


// LOG FILE .//////////////////////////
public function clear_log() {
    check_ajax_referer('claude_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized.'));
    }
    delete_option('claude_chat_history');
    $log_file = CLAUDE_PLUGIN_DIR . 'includes/groq_chat_log.txt';
    if (file_exists($log_file)) {
        file_put_contents($log_file, '');
    }
    wp_send_json_success(array('message' => 'Log cleared.'));
}
}    //end of class