    <?php
    if (!defined('ABSPATH')) exit;
    /*
    Search file
    If not found → stop
    If many → ask user
    If one → continue
    Validate path
    Read file
    Find includes
    Send to AI
    Return summary
    */
    //----------------------- MAX LINES LIMIT ----------------------------------------------

    define('CLAUDE_MAX_FILE_LINES', 1000);

    //----------------------- MAIN READ FILE FUNCTION ----------------------------------------------

    function claude_read_file($filename, $groq_api_key)
    {

        // step 1 — find the file by name across theme and plugin folders
        $matches = claude_find_file_by_name($filename);

        // no file found anywhere
        if (empty($matches)) {

            // list all the places we searched so user knows exactly where we looked
            $searched = array(
                'Active Theme',
                'Parent Theme',
                'All Plugins',
                'All Themes',
            );

            return 'File not found: ' . $filename . '. Searched in: ' . implode(', ', $searched) . '. Please check the filename and try again.';
        }

        // multiple files found — ask user to pick one
        if (count($matches) > 1) {
            $locations = array();
            foreach ($matches as $index => $match) {
                $locations[] = ($index + 1) . ') ' . $match['label'];
            }
            return 'Found ' . $filename . ' in multiple locations: ' . implode(', ', $locations) . '. Please specify which one e.g. "read functions.php from active theme"';
        }

        // exactly one match found — proceed
        $file_path = $matches[0]['path'];
        $location  = $matches[0]['label'];

        // step 2 — validate the path is safe
        $validation = claude_validate_file_path($file_path);
        if (!$validation['valid']) {
            return $validation['error'];
        }

        // step 3 — read the file content
                // Just clearing the content for any warm comments in it
                $content = preg_replace('/\/\/.*$/m', '', $content);       // strip single line comments
                $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content); // strip block comments
                $content = claude_get_file_content($file_path);
        if (!$content['success']) {
            return $content['error'];
        }

        // step 4 — detect included files
        $includes = claude_detect_includes($content['content']);

        // step 5 — send to groq for smart summary
        $summary = claude_summarize_file($filename, $content['content'], $includes, $groq_api_key);

        return $summary;
    }





    //----------------------- READ FILE CONTENT WITH LINE LIMIT ----------------------------------------------

    function claude_get_file_content($path)
    {

        // check file is readable
        if (!is_readable($path)) {
            return array('success' => false, 'error' => 'File exists but cannot be read. Check file permissions.');
        }

        // read all lines
        $lines       = file($path, FILE_IGNORE_NEW_LINES); //remove new line /n
        $total_lines = count($lines);
        $truncated   = false;

        // trim to max lines if file is too large
        if ($total_lines > CLAUDE_MAX_FILE_LINES) {
            $lines     = array_slice($lines, 0, CLAUDE_MAX_FILE_LINES); //CUT ARRAY AND KEEP ONLY FIRST 1000 LINES
            $truncated = true; //JUST A FLAGGED, WE DIDNT READ COMPLETE FILE 
        }

        return array(
            'success'      => true,
            'content'      => implode("\n", $lines),     // join lines back to string
            'total_lines'  => $total_lines,
            'read_lines'   => count($lines),
            'truncated'    => $truncated,
        );
    }


    //----------------------- DETECT INCLUDED FILES ----------------------------------------------

    function claude_detect_includes($content)
    {

        $includes = array();

        // match require, include, require_once, include_once patterns
        preg_match_all(
            '/(?:require|include)(?:_once)?\s*[\(\s][\'"]([^\'"]+)[\'"]\s*[\)];?/i',
            $content,  // where to look
            $matches //store
        );

        if (!empty($matches[1])) {
            foreach ($matches[1] as $included_file) {
                $includes[] = basename($included_file); // show filename only not full path
            }
        }

        return array_unique($includes); // remove duplicates
    }


    //----------------------- SEND FILE TO GROQ FOR SMART SUMMARY ----------------------------------------------

    function claude_summarize_file($filename, $content, $includes, $groq_api_key)
    {

        $include_note = '';
        if (!empty($includes)) {
            $include_note = '\n\nAlso mention at the end that this file includes these files: ' . implode(', ', $includes) . '. Tell the user they can ask to read any of them.';
        }

        $prompt = 'You are a WordPress developer assistant. Analyze this file called "' . $filename . '" and return the summary in this exact format with no bullet points, no markdown symbols, just plain text paragraphs:

PURPOSE
Write 1 sentence about what this file does.

FUNCTIONS
Write all function names separated by commas, with a 4 word description each. Example: hello_setup (sets up theme support), hello_scripts (loads css and js)

HOOKS
Write each hook name and what it does in 4 words, separated by commas.

CONSTANTS
Write constant name and value only, separated by commas.

INCLUDED FILES
Write filenames separated by commas.

No bullet points. No asterisks. No markdown. Plain text only. Max 15 lines total.

File content:
' . $content; 

        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
            'timeout'   => 60,
            'sslverify' => true,
            'headers'   => array(
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
            return 'Failed to analyze file: ' . $response->get_error_message();
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body      = json_decode(wp_remote_retrieve_body($response), true);

        if ($http_code !== 200) {
            return 'Groq error while reading file: ' . ($body['error']['message'] ?? 'Unknown error');
        }

        $summary  = $body['choices'][0]['message']['content'];

        // add truncation warning if file was too large — we pass this via a trick below
        // truncation note is added in claude_read_file before this function is called
        return $summary;
    }


    //----------------------- READ FILE BY LOCATION WHEN USER SPECIFIES ----------------------------------------------

    function claude_read_file_from_location($filename, $location_keyword, $groq_api_key)
    {

        // map user friendly keywords to actual paths
        $location_map = array(
            'theme'         => get_stylesheet_directory(),
            'active theme'  => get_stylesheet_directory(),
            'parent theme'  => get_template_directory(),
            'plugin'        => CLAUDE_PLUGIN_DIR,
            'this plugin'   => CLAUDE_PLUGIN_DIR,
            'plugins'       => WP_CONTENT_DIR . '/plugins/',
            'themes'        => WP_CONTENT_DIR . '/themes/',
        );

        // NEXR: Match The Keyword
        $base_path = null;
        $location_keyword = strtolower($location_keyword);

        foreach ($location_map as $keyword => $path) {
            if (strpos($location_keyword, $keyword) !== false) { //strpos: position of text in another file 
                $base_path = $path;
                break; //stop loop
            }
        }

        if (!$base_path) {
            return claude_read_file($filename, $groq_api_key); // fallback to normal search
        }

        // search only in specified location
        $found = claude_search_file_recursive($base_path, $filename);

        if (!$found) {
            return 'File ' . $filename . ' not found in ' . $location_keyword . '.';
        }

        // validate and read
        $validation = claude_validate_file_path($found);
        if (!$validation['valid']) {
            return $validation['error'];
        }

        $content = claude_get_file_content($found);
        if (!$content['success']) {
            return $content['error'];
        }

        $includes = claude_detect_includes($content['content']);
        $summary  = claude_summarize_file($filename, $content['content'], $includes, $groq_api_key);

        // add truncation note if needed
        if ($content['truncated']) {
            $summary .= "\n\n⚠️ Note: This file has " . $content['total_lines'] . " lines. Only first " . CLAUDE_MAX_FILE_LINES . " lines were analyzed.";        }

        return $summary;
    }
