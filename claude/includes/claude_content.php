<?php
if (!defined('ABSPATH')) exit;

function claude_ask_groq($instruction, $groq_api_key) {

    $system_prompt = 'You are a WordPress assistant with full dashboard access. Based on the user instruction, return ONLY a valid JSON array of action objects. Even for a single action, always return an array. No markdown, no explanation.

POSTS & PAGES:
Create post:   {"action": "create_post", "title": "Title", "content": "Content", "status": "publish", "category": ""}
Delete post:   {"action": "delete_post", "title": "Title"}
Create page:   {"action": "create_page", "title": "Title", "content": "Content"}
Delete page:   {"action": "delete_page", "title": "Title"}

USERS:
Create user:   {"action": "create_user", "username": "john", "first_name": "", "last_name": "", "email": "john@email.com", "role": "subscriber", "password": ""}
Delete user:   {"action": "delete_user", "email": "john@email.com"}
Update role:   {"action": "update_user_role", "email": "john@email.com", "role": "editor"}

PLUGINS:
Install plugin:    {"action": "install_plugin", "slug": "woocommerce"}
Activate plugin:   {"action": "activate_plugin", "slug": "woocommerce"}
Deactivate plugin: {"action": "deactivate_plugin", "slug": "woocommerce"}
Delete plugin:     {"action": "delete_plugin", "slug": "woocommerce"}
Update plugin:     {"action": "update_plugin", "slug": "woocommerce"}

THEMES:
Install theme:   {"action": "install_theme", "slug": "astra"}
Activate theme:  {"action": "activate_theme", "slug": "astra"}
Delete theme:    {"action": "delete_theme", "slug": "astra"}
Update theme:    {"action": "update_theme", "slug": "astra"}

WORDPRESS SETTINGS:
Update setting:    {"action": "update_setting", "key": "admin_email", "value": "new@email.com"}
Flush permalinks:  {"action": "flush_permalinks"}

COMMENTS:
Delete comment:        {"action": "delete_comment", "id": 5}
Delete spam:           {"action": "delete_spam_comments"}
Delete all comments:   {"action": "delete_all_comments"}

MENUS:
Create menu:        {"action": "create_menu", "name": "Main Menu"}
Add page to menu:   {"action": "add_page_to_menu", "menu": "Main Menu", "title": "About"}
Add custom link:    {"action": "add_link_to_menu", "menu": "Main Menu", "label": "Google", "url": "https://google.com"}
Delete menu:        {"action": "delete_menu", "name": "Main Menu"}

IMPORTANT: Always return a JSON array even for one action. Example: [{"action":"create_post","title":"Hello","content":"World","status":"publish","category":""}]
If password is not mentioned, set "password" to empty string.
Return ONLY the JSON array. Nothing else.';

    $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
        'timeout'   => 30,
        'sslverify' => false,
        'headers'   => array(
            'Authorization' => 'Bearer ' . $groq_api_key,
            'Content-Type'  => 'application/json',
        ),
        'body' => json_encode(array(
            'model'    => 'meta-llama/llama-4-scout-17b-16e-instruct',
            'messages' => array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user',   'content' => $instruction),
            )
        )),
    ));

    if (is_wp_error($response)) {
        return null;
    }

    $body    = json_decode(wp_remote_retrieve_body($response), true);
    $content = $body['choices'][0]['message']['content'];

    // decode groq reply — should be an array of actions
    $decoded = json_decode($content, true);

    // if groq returned a single object instead of array, wrap it
    if (isset($decoded['action'])) {
        return array($decoded);
    }

    return $decoded;
}


function claude_execute_content($data) {

    if (empty($data['action'])) {
        return 'Could not understand this action.';
    }

    $action = $data['action'];

    // helper: create post or page
    if ($action === 'create_post' || $action === 'create_page') {
        $type        = $action === 'create_post' ? 'post' : 'page';
        $category_id = 0;

        if ($type === 'post' && !empty($data['category'])) {
            $cat         = get_term_by('name', $data['category'], 'category');
            $category_id = $cat ? $cat->term_id : wp_create_category($data['category']);
        }

        $id = wp_insert_post(array(
            'post_title'    => sanitize_text_field($data['title']),
            'post_content'  => wp_kses_post($data['content']),
            'post_status'   => 'publish',
            'post_type'     => $type,
            'post_category' => $category_id ? array($category_id) : array(),
        ));

        return $id ? ucfirst($type) . ' created: ' . $data['title'] : 'Failed to create ' . $type;
    }

    // helper: delete post or page
    if ($action === 'delete_post' || $action === 'delete_page') {
        $type  = $action === 'delete_post' ? 'post' : 'page';
        $posts = get_posts(array('post_title' => $data['title'], 'post_type' => $type, 'numberposts' => 1));
        if ($posts) {
            wp_delete_post($posts[0]->ID, true);
            return ucfirst($type) . ' deleted: ' . $data['title'];
        }
        return ucfirst($type) . ' not found: ' . $data['title'];
    }

    if ($action === 'create_user') {
        $password = !empty($data['password']) ? $data['password'] : 'abbc*groq5';
        $user_id  = wp_create_user(
            sanitize_user($data['username']),
            $password,
            sanitize_email($data['email'])
        );
        if (!is_wp_error($user_id)) {
            wp_update_user(array(
                'ID'         => $user_id,
                'role'       => $data['role'],
                'first_name' => sanitize_text_field($data['first_name'] ?? ''),
                'last_name'  => sanitize_text_field($data['last_name'] ?? ''),
            ));
            return 'User created: ' . $data['username'] . ' | Password: ' . $password;
        }
        return 'Failed to create user: ' . $user_id->get_error_message();
    }

    if ($action === 'delete_user') {
        $user = get_user_by('email', $data['email']);
        if ($user) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user->ID);
            return 'User deleted: ' . $data['email'];
        }
        return 'User not found: ' . $data['email'];
    }

    if ($action === 'update_user_role') {
        $user = get_user_by('email', $data['email']);
        if ($user) {
            wp_update_user(array('ID' => $user->ID, 'role' => $data['role']));
            return 'Role updated: ' . $data['email'] . ' → ' . $data['role'];
        }
        return 'User not found: ' . $data['email'];
    }

    if ($action === 'install_plugin') {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result   = $upgrader->install('https://downloads.wordpress.org/plugin/' . $data['slug'] . '.latest-stable.zip');
        return $result ? 'Plugin installed: ' . $data['slug'] : 'Failed to install: ' . $data['slug'];
    }

    if ($action === 'activate_plugin') {
        $file   = $data['slug'] . '/' . $data['slug'] . '.php';
        $result = activate_plugin($file);
        return is_wp_error($result) ? 'Failed to activate: ' . $result->get_error_message() : 'Plugin activated: ' . $data['slug'];
    }

  if ($action === 'deactivate_plugin') {
    $all_plugins = get_plugins(); // get all installed plugins
    $target      = null;

    // search through all plugins to find one matching our slug
    foreach ($all_plugins as $file => $info) {
        if (strpos($file, $data['slug']) !== false) {
            $target = $file; // found the real file path
            break;
        }
    }

    if (!$target) return 'Plugin not found: ' . $data['slug'];
    deactivate_plugins($target);
    return 'Plugin deactivated: ' . $data['slug'];
}

if ($action === 'delete_plugin') {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $all_plugins = get_plugins();
    $target      = null;

    foreach ($all_plugins as $file => $info) {
        if (strpos($file, $data['slug']) !== false) {
            $target = $file;
            break;
        }
    }

    if (!$target) return 'Plugin not found: ' . $data['slug'];
    deactivate_plugins($target);
    delete_plugins(array($target));
    return 'Plugin deleted: ' . $data['slug'];
}

    if ($action === 'update_plugin') {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result   = $upgrader->upgrade($data['slug'] . '/' . $data['slug'] . '.php');
        return $result ? 'Plugin updated: ' . $data['slug'] : 'Failed to update: ' . $data['slug'];
    }

    // helper: install theme or plugin — themes share same upgrade pattern
    if ($action === 'install_theme') {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $upgrader = new Theme_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result   = $upgrader->install('https://downloads.wordpress.org/theme/' . $data['slug'] . '.latest-stable.zip');
        return $result ? 'Theme installed: ' . $data['slug'] : 'Failed to install theme: ' . $data['slug'];
    }

    if ($action === 'activate_theme') {
        switch_theme($data['slug']);
        return 'Theme activated: ' . $data['slug'];
    }

    if ($action === 'delete_theme') {
        delete_theme($data['slug']);
        return 'Theme deleted: ' . $data['slug'];
    }

    if ($action === 'update_theme') {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $upgrader = new Theme_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result   = $upgrader->upgrade($data['slug']);
        return $result ? 'Theme updated: ' . $data['slug'] : 'Failed to update theme: ' . $data['slug'];
    }

    if ($action === 'update_setting') {
        update_option($data['key'], sanitize_text_field($data['value']));
        return 'Setting updated: ' . $data['key'] . ' → ' . $data['value'];
    }

    if ($action === 'flush_permalinks') {
        flush_rewrite_rules();
        return 'Permalinks flushed.';
    }

    if ($action === 'delete_comment') {
        wp_delete_comment($data['id'], true);
        return 'Comment deleted: #' . $data['id'];
    }

    if ($action === 'delete_spam_comments' || $action === 'delete_all_comments') {
        $status   = $action === 'delete_spam_comments' ? 'spam' : 'any'; // spam only or all
        $comments = get_comments(array('status' => $status));
        foreach ($comments as $comment) {
            wp_delete_comment($comment->comment_ID, true);
        }
        return $action === 'delete_spam_comments' ? 'Spam comments deleted.' : 'All comments deleted.';
    }

    if ($action === 'create_menu') {
        $menu_id = wp_create_nav_menu($data['name']);
        return is_wp_error($menu_id) ? 'Failed to create menu.' : 'Menu created: ' . $data['name'];
    }

    if ($action === 'add_page_to_menu') {
        $menu  = get_term_by('name', $data['menu'], 'nav_menu');
        $pages = get_posts(array('post_title' => $data['title'], 'post_type' => 'page', 'numberposts' => 1));
        if ($menu && $pages) {
            wp_update_nav_menu_item($menu->term_id, 0, array(
                'menu-item-title'     => $pages[0]->post_title,
                'menu-item-object'    => 'page',
                'menu-item-object-id' => $pages[0]->ID,
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            ));
            return 'Page added to menu: ' . $data['title'];
        }
        return 'Menu or page not found.';
    }

    if ($action === 'add_link_to_menu') {
        $menu = get_term_by('name', $data['menu'], 'nav_menu');
        if ($menu) {
            wp_update_nav_menu_item($menu->term_id, 0, array(
                'menu-item-title'  => $data['label'],
                'menu-item-url'    => esc_url($data['url']),
                'menu-item-type'   => 'custom',
                'menu-item-status' => 'publish',
            ));
            return 'Link added to menu: ' . $data['label'];
        }
        return 'Menu not found: ' . $data['menu'];
    }

    if ($action === 'delete_menu') {
        $menu = get_term_by('name', $data['name'], 'nav_menu');
        if ($menu) {
            wp_delete_nav_menu($menu->term_id);
            return 'Menu deleted: ' . $data['name'];
        }
        return 'Menu not found: ' . $data['name'];
    }

    return 'Action not recognized: ' . $action;
}


// saves last 20 to DB and full history to log file
function claude_log_action($instruction, $action, $result) {

    $log_dir  = CLAUDE_PLUGIN_DIR . 'includes/';
    $log_file = $log_dir . '/groq_chat_log.txt';
    $time     = current_time('Y-m-d H:i:s');
    $entry    = "[{$time}] Instruction: {$instruction} | Action: {$action} | Result: {$result}" . PHP_EOL;

    // create folder if it doesn't exist
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }

    // append to full log file
    file_put_contents($log_file, $entry, FILE_APPEND);

    // save last 20 to database
    $history   = get_option('claude_chat_history', array());
    $history[] = array(
        'time'        => $time,
        'instruction' => $instruction,
        'action'      => $action,
        'result'      => $result,
    );

    // keep only last 20
    if (count($history) > 20) {
        $history = array_slice($history, -20);
    }

    update_option('claude_chat_history', $history);
}