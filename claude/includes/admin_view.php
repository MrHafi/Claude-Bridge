<?php if (!defined('ABSPATH')) exit;

// THIS FILE IS FOR FRONTEND OF OF ADMIN MENUS

$options        = get_option('claude_settings', array());
$api_key        = !empty($options['api_key'])        ? esc_attr($options['api_key'])      : '';
$groq_api_key   = !empty($options['groq_api_key'])   ? esc_attr($options['groq_api_key']) : '';
$content_access = !empty($options['content_access']) ? 1 : 0;
$file_access    = !empty($options['file_access'])    ? 1 : 0;
$db_access      = !empty($options['db_access'])      ? 1 : 0;
$history        = get_option('claude_chat_history', array());
?>

<div class="wrap claude-wrap">
    <h1>AI Site Manager</h1>

    <!-- TAB BUTTONS -->
    <div class="claude-tabs">
        <button class="claude-tab-btn active" data-tab="chat">Chat</button>
        <button class="claude-tab-btn" data-tab="integration">Integration & Test</button>
        <button class="claude-tab-btn" data-tab="history">Chat History</button>
    </div>

   <!-- TAB 1: CHAT -->
<div class="claude-tab-content active" id="claude-tab-chat">
    <div class="claude-chat-layout">

        <!-- LEFT: 60% area for chat -->
        <div class="claude-chat-left">
            <p class="claude-desc">Type an instruction or click an action from the panel.</p>

            <div id="claude_chat_box" class="claude-chat-box">
                <p class="claude-chat-placeholder">Conversation will appear here...</p>
            </div>

            <textarea
                id="claude_chat_input"
                rows="3"
                placeholder="e.g. Install updraftplus and activate it..."
                class="claude-textarea"
            ></textarea>

            <div class="claude-chat-actions">
                <button type="button" id="claude_send_btn" class="button button-primary">Send Instruction</button>
                <span id="claude_chat_loading" class="claude-loading">Thinking...</span>
            </div>
        </div>

        <!-- RIGHT: 40% actions panel -->
        <div class="claude-actions-panel">
            <div class="claude-panel-header">
                <span class="claude-panel-title">Quick Actions</span>
                <button type="button" id="claude_toggle_all" class="claude-toggle-all-btn">Collapse All</button>
            </div>

            <?php
            $action_groups = array(
                array(
                    'label'   => 'Posts & Pages',
                    'color'   => 'purple',
                    'actions' => array(
                        'Create a post',
                        'Delete a post',
                        'Create a page',
                        'Delete a page',
                        'Draft all posts',
                        'Publish all posts',
                        'Delete all posts',
                        'Draft all pages',
                        'Publish all pages',
                        'Delete all pages',
                        'Empty trash',
                    )
                ),
                array(
                    'label'   => 'Users',
                    'color'   => 'teal',
                    'actions' => array(
                        'Create a user',
                        'Delete a user',
                        'Update user role',
                    )
                ),
                array(
                    'label'   => 'Plugins',
                    'color'   => 'amber',
                    'actions' => array(
                        'Install a plugin',
                        'Activate a plugin',
                        'Deactivate a plugin',
                        'Delete a plugin',
                        'Update a plugin',
                    )
                ),
                array(
                    'label'   => 'Themes',
                    'color'   => 'blue',
                    'actions' => array(
                        'Install a theme',
                        'Activate a theme',
                        'Delete a theme',
                        'Update a theme',
                    )
                ),
                array(
                    'label'   => 'Menus',
                    'color'   => 'pink',
                    'actions' => array(
                        'Create a menu',
                        'Add a page to menu',
                        'Add a custom link to menu',
                        'Delete a menu',
                    )
                ),
                array(
                    'label'   => 'Comments',
                    'color'   => 'red',
                    'actions' => array(
                        'Delete a comment',
                        'Delete all spam comments',
                        'Delete all comments',
                    )
                ),
                array(
                    'label'   => 'Settings',
                    'color'   => 'green',
                    'actions' => array(
                        'Change site title',
                        'Change tagline',
                        'Change admin email',
                        'Flush permalinks',
                        'Change timezone',
                        'Change posts per page',
                    )
                ),
            );

            foreach ($action_groups as $group) : ?>
                <div class="claude-action-group">
                    <button type="button" class="claude-group-header claude-color-<?php echo $group['color']; ?>">
                        <span><?php echo $group['label']; ?></span>
                        <span class="claude-group-arrow">▾</span>
                    </button>
                    <div class="claude-group-body">
                        <?php foreach ($group['actions'] as $action) : ?>
                            <button type="button" class="claude-action-btn claude-color-<?php echo $group['color']; ?>" data-action="<?php echo esc_attr($action); ?>">
                                <?php echo esc_html($action); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>

    <!-- TAB 2: INTEGRATION & TEST -->
    <div class="claude-tab-content" id="claude-tab-integration">

        <div id="claude_notice" class="claude-notice" style="display:none;"></div>

        <table class="form-table">
            <tr>
                <th>Claude API Key</th>
                <td><input type="password" id="claude_api_key" value="<?php echo $api_key; ?>" class="regular-text"/></td>
            </tr>
            <tr>
                <th>Groq API Key</th>
                <td><input type="password" id="claude_groq_api_key" value="<?php echo $groq_api_key; ?>" class="regular-text"/></td>
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

        <div class="claude-integration-actions">
            <button type="button" id="claude_save_btn" class="button button-primary">Save Settings</button>
            <button type="button" id="claude_test_groq" class="button">Test Groq Connection</button>
        </div>

        <div id="claude_test_result" class="claude-test-result" style="display:none;"></div>

    </div>

    <!-- TAB 3: CHAT HISTORY -->
    <div class="claude-tab-content" id="claude-tab-history">

        

        <div id="claude_log_box" class="claude-log-box">
            <?php if (empty($history)) : ?>
                <p class="claude-log-empty">No history yet.</p>
            <?php else : ?>
                <?php foreach (array_reverse($history) as $entry) : ?>
                    <div class="claude-log-entry">
                        <span class="claude-log-time"><?php echo esc_html($entry['time']); ?></span>
                        <span class="claude-log-you">You: <?php echo esc_html($entry['instruction']); ?></span>
                        <span class="claude-log-action">Action: <?php echo esc_html($entry['action']); ?></span>
                        <span class="claude-log-result">Result: <?php echo esc_html($entry['result']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>