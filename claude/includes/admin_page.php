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

    /* Loads CSS and JS files */
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

    
    //   FRONTEND OF ADMIN MENU -------------------------------------
   public function render_page() { 
        // FETCHING FROM DB TO DISPLAY
    $options        = get_option('claude_settings', array());
    $api_key        = !empty($options['api_key'])        ? esc_attr($options['api_key']) : '';
    $content_access = !empty($options['content_access']) ? 1 : 0;
    $file_access    = !empty($options['file_access'])    ? 1 : 0;
    $db_access      = !empty($options['db_access'])      ? 1 : 0;
    $groq_api_key   = !empty($options['groq_api_key'])   ? esc_attr($options['groq_api_key']) : '';
    ?>


        <!-- CHAT INTERFACE -->
        <h2>Chat with Claude</h2>
        <p style="color:#666;">Type an instruction below. Make sure the right toggles are ON before sending.</p>

        <div id="claude_chat_box" style="
            background: #1e1e1e;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            min-height: 150px;
            color: #fff;
            font-size: 14px;
        ">
            <p style="color:#888;">Conversation will appear here...</p>
        </div>

        <textarea 
            id="claude_chat_input" 
            rows="3" 
            placeholder="e.g. Create a post called Hello World..."
            style="width:100%; padding:10px; font-size:14px; border-radius:6px; border:1px solid #ccc; resize:vertical;"
        ></textarea>

        <br><br>
        <button type="button" id="claude_send_btn">Send Instruction</button>
        <span id="claude_chat_loading" style="display:none; margin-left:10px; color:#666;">Thinking...</span>
        <hr style="margin: 30px 0;">

    <!-- ---------------API FIELDS -------------------------------------  -->
    <div class="wrap">
        <h1>Claude by Hafi</h1>

        <div id="claude_notice" style="display:none;"></div>

        <table class="form-table">
            <tr>
                <th>API Key</th>
                <td><input type="password" id="claude_api_key" value="<?php echo $api_key; ?>" /></td>
            </tr>
            <tr>
                <th>Groq API Key</th>
                <td><input type="password" id="claude_groq_api_key" value="<?php echo $groq_api_key; ?>" /></td>
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

        <!-- TEST GROQ -->
<button type="button" id="claude_test_groq">Test Groq Connection</button>
        <div id="claude_test_result" style="display:none;"></div>

    </div> <!-- wrap closes here -->



<!-- LOG UI------------------------------------->
 <!-- LOG VIEWER -->
<hr style="margin: 30px 0;">
<h2>Chat History</h2>

<div style="margin-bottom: 10px;">
    <button type="button" id="claude_clear_log">Clear All Log</button>
</div>

<div id="claude_log_box" style="
    background: #1e1e1e;
    border-radius: 8px;
    padding: 20px;
    min-height: 100px;
    color: #fff;
    font-size: 13px;
    font-family: monospace;
">
    <?php
    $history = get_option('claude_chat_history', array());
    if (empty($history)) {
        echo '<p style="color:#888;">No history yet.</p>';
    } else {
        // show newest first
        foreach (array_reverse($history) as $entry) {
            echo '<p style="margin:0 0 10px; border-bottom: 1px solid #333; padding-bottom:8px;">';
            echo '<span style="color:#888;">' . esc_html($entry['time']) . '</span><br>';
            echo '<span style="color:#7c6aff;">You: </span>' . esc_html($entry['instruction']) . '<br>';
            echo '<span style="color:#aaa;">Action: </span>' . esc_html($entry['action']) . '<br>';
            echo '<span style="color:#38a169;">Result: </span>' . esc_html($entry['result']);
            echo '</p>';
        }
    }
    ?>
</div>

    <?php
}


    
    /* SAVE SETTIGN INTO DB FROM JS  */
    public function save_settings() {
        check_ajax_referer('claude_nonce', 'nonce');

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
    check_ajax_referer('claude_nonce', 'nonce');

    $options      = get_option('claude_settings', array());
    $groq_api_key = !empty($options['groq_api_key']) ? $options['groq_api_key'] : ''; //grab key if not empty

        // EMPTY KEY
        if (empty($groq_api_key)) {
            wp_send_json_error(array('message' => 'Groq API key is missing!'));
        }

    $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
        'timeout'   => 30, // add timeout
        'sslverify' => false, // fix SSL issues on local/staging
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
    check_ajax_referer('claude_nonce', 'nonce');

    $instruction = sanitize_text_field($_POST['instruction']);
    $options     = get_option('claude_settings', array());

    if (empty($options['content_access'])) {
        wp_send_json_error(array('message' => 'Content access is OFF. Enable it in settings first.'));
    }

    // send to groq — now returns array of actions
    $actions = claude_ask_groq($instruction, $options['groq_api_key']);

    if (!$actions || !is_array($actions)) {
        wp_send_json_error(array('message' => 'Failed to get response from Groq.'));
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
}

}    //end of class