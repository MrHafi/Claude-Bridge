<?php
if (!defined('ABSPATH')) exit; // block direct access

/*
 * file_writer.php
 * Handles all file write operations — create, edit, append
 * 
 * Functions in this file:
 * claude_write_file()    - main manager, coordinates everything
 * claude_create_file()   - creates a brand new file
 * claude_edit_file()     - edits existing file content via Groq
 * claude_append_file()   - appends code to end of existing file
 * claude_syntax_check()  - checks PHP syntax before saving
 * claude_health_check()  - pings site after saving to confirm no crash
 */


//---------- checks PHP syntax on generated code before touching any real file -------------------------
function claude_syntax_check($content) {

    $temp = tempnam(sys_get_temp_dir(), 'claude_'); //  temp file 
    file_put_contents($temp, $content);             // write generated code to temp file

    $output = shell_exec('php -l ' . escapeshellarg($temp)); // run PHP syntax check on temp file
    unlink($temp);                                           // delete temp file whether pass or fail

    if ($output === null) {                                                     // shell_exec returned null — command didnt run
        return array('valid' => false, 'error' => 'Syntax check unavailable.'); // tell caller we couldnt check
    }

        // IF FILE GOT ERROR
    if (strpos($output, 'No syntax errors') === false) {          
        return array('valid' => false, 'error' => $output);       
    }

    return array('valid' => true); // all good — syntax is clean
}


// ---------------------------  CHECKING IF EVERYTHING ON SITE IS FINE  home page, wp-json---------------------
function claude_health_check() {

    $endpoints = array(
        home_url('/'),        
        home_url('/wp-json/'), 
    );

    foreach ($endpoints as $url) {

        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            return array('valid' => false, 'error' => 'Site unreachable after file write: ' . $url);
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) { // anything other than 200 means something broke
            return array('valid' => false, 'error' => 'Site returned ' . $code . ' after file write. File restored.');
        }
    }

    return array('valid' => true);
}



//----------------------- MAIN FILE WRITE FUNCTION ----------------------------------------------
function claude_create_file($filename, $location, $content) {

    // build full path using location keyword
    $file_path = claude_resolve_location($location) . '/' . $filename; // filename: usere want to create file

    if (file_exists($file_path)) {
        return array('success' => false, 'error' => 'File already exists: ' . $filename . '. Use edit instead.');
    }

    // run syntax check before creating anything
    $syntax = claude_syntax_check($content);
    if (!$syntax['valid']) {
        return array('success' => false, 'error' => 'Syntax error in generated code: ' . $syntax['error']);
    }

    $written = file_put_contents($file_path, $content); // write content to new file

    if ($written === false) {
        return array('success' => false, 'error' => 'Failed to create file. Check folder permissions.');
    }

    // health check after writing
    $health = claude_health_check();
    if (!$health['valid']) {
        unlink($file_path); // delete the file we just created — it broke something
        return array('success' => false, 'error' => $health['error']);
    }

    return array('success' => true, 'message' => 'File created successfully: ' . $filename);
}


//  -----------------  Append code in real  file ----------------------
function claude_append_file($filename, $location, $content) {

    $file_path = claude_resolve_location($location) . '/' . $filename;

    if (!file_exists($file_path)) {
        return array('success' => false, 'error' => 'File not found: ' . $filename . '. Use create instead.');
    }

    // validate path is safe before touching anything
    $validation = claude_validate_file_path($file_path);
    if (!$validation['valid']) {
        return array('success' => false, 'error' => $validation['error']);
    }

    // syntax check on new content only — not entire file
    $syntax = claude_syntax_check($content);
    if (!$syntax['valid']) {
        return array('success' => false, 'error' => 'Syntax error in generated code: ' . $syntax['error']);
    }

    // backup before touching real file
    $backup = claude_create_backup($file_path);
    if (!$backup['success']) {
        return array('success' => false, 'error' => 'Backup failed. File not modified.');
    }

    file_put_contents($file_path, "\n\n" . $content, FILE_APPEND); // append new content at end of file

    // health check after appending
    $health = claude_health_check();
    if (!$health['valid']) {
        claude_restore_backup($file_path); // restore backup if site broke
        return array('success' => false, 'error' => $health['error']);
    }

    return array('success' => true, 'message' => 'Code appended successfully to: ' . $filename);
}


//------------ reads current file, sends to Groq with instruction, syntax checks, backups, overwrites, health checks, restores if broken--------------------

function claude_edit_file($filename, $location, $instruction, $groq_api_key) {

    $file_path = claude_resolve_location($location) . '/' . $filename;

    if (!file_exists($file_path)) {
        return array('success' => false, 'error' => 'File not found: ' . $filename);
    }

    // validate path is safe
    $validation = claude_validate_file_path($file_path);
    if (!$validation['valid']) {
        return array('success' => false, 'error' => $validation['error']);
    }

    // read current file content to send to Groq
    $current = claude_get_file_content($file_path);
    if (!$current['success']) {
        return array('success' => false, 'error' => $current['error']);
    }

    // send current file + instruction to Groq — get back updated full file content
    $new_content = claude_generate_edit($filename, $current['content'], $instruction, $groq_api_key);
    if (!$new_content) {
        return array('success' => false, 'error' => 'Groq failed to generate edit.');
    }

    // syntax check on new content before touching real file
    $syntax = claude_syntax_check($new_content);
    if (!$syntax['valid']) {
        return array('success' => false, 'error' => 'Syntax error in generated code: ' . $syntax['error']);
    }

    // backup original before writing
    $backup = claude_create_backup($file_path);
    if (!$backup['success']) {
        return array('success' => false, 'error' => 'Backup failed. File not modified.');
    }

    file_put_contents($file_path, $new_content); // overwrite file with new content

    // health check after writing
    $health = claude_health_check();
    if (!$health['valid']) {
        claude_restore_backup($file_path); // restore if site broke
        return array('success' => false, 'error' => $health['error']);
    }

    return array('success' => true, 'message' => 'File edited successfully: ' . $filename);
}



//  ----------GENERATE EDITED CONTENT USING GROQ----------------------
function claude_generate_edit($filename, $current_content, $instruction, $groq_api_key) {

    $prompt = 'You are a WordPress developer. You will be given a file called "' . $filename . '" and an instruction.
    
Your job:
- Make ONLY the change the instruction asks for
- Return the COMPLETE updated file content
- No explanations, no markdown, no backticks
- Return raw PHP code only
- Do not remove or modify anything that was not mentioned in the instruction

Current file content:
' . $current_content . '

Instruction: ' . $instruction;

    $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
        'timeout' => 60,
        'headers' => array(
            'Authorization' => 'Bearer ' . $groq_api_key,
            'Content-Type'  => 'application/json',
        ),
        'body' => json_encode(array(
            'model'    => 'meta-llama/llama-4-scout-17b-16e-instruct',
            'messages' => array(
                array('role' => 'user', 'content' => $prompt),
            )
        )),
    ));

    if (is_wp_error($response)) {
        return null; // groq request failed
    }

    $body    = json_decode(wp_remote_retrieve_body($response), true);
    $content = $body['choices'][0]['message']['content'] ?? null;

    if (empty($content)) {
        return null; // groq returned empty response
    }

    // strip markdown code fences if Groq wrapped response in ```php ... ```
    $content = preg_replace('/^```(?:php)?\s*/i', '', trim($content));
    $content = preg_replace('/```$/', '', trim($content));

    return $content;
}



// -------------- converts user friendly keyword to real server path-----------------
function claude_resolve_location($location) {

    $location_map = array(
        'theme'        => get_stylesheet_directory(),
        'active theme' => get_stylesheet_directory(),
        'parent theme' => get_template_directory(),
        'plugin'       => CLAUDE_PLUGIN_DIR,
        'this plugin'  => CLAUDE_PLUGIN_DIR,
        'plugins'      => WP_CONTENT_DIR . '/plugins/',
        'themes'       => WP_CONTENT_DIR . '/themes/',
    );

    $location = strtolower(trim($location)); // clean up what user typed

    foreach ($location_map as $keyword => $path) {
        if (strpos($location, $keyword) !== false) {
            return $path; // return real path as soon as keyword matches
        }
    }

    return get_stylesheet_directory(); // default to active theme if nothing matched
}



// - -------------------------main manager — receives action from Groq and routes to correct function
function claude_write_file($data, $groq_api_key) {

    $action   = $data['action']   ?? '';  // what operation to perform
    $filename = $data['filename'] ?? '';  // which file to touch
    $location = $data['location'] ?? 'active theme'; // where the file is, default to active theme
    $content  = $data['content']  ?? '';  // new code to write (for create and append)
    $instruction = $data['instruction'] ?? ''; // what to change (for edit only)

    if (empty($filename)) {
        return 'Please specify a filename.';
    }

    if ($action === 'create_file') {
        $result = claude_create_file($filename, $location, $content);
        return $result['success'] ? $result['message'] : $result['error'];
    }

    if ($action === 'edit_file') {
        $result = claude_edit_file($filename, $location, $instruction, $groq_api_key);
        return $result['success'] ? $result['message'] : $result['error'];
    }

    if ($action === 'append_file') {
        $result = claude_append_file($filename, $location, $content);
        return $result['success'] ? $result['message'] : $result['error'];
    }

    return 'Unknown file write action: ' . $action;
}