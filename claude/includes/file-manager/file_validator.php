<?php
if (!defined('ABSPATH')) exit;

/*
Blocks dangerous files and directories
Blocks path traversal attacks
Allows only safe file extensions
Allows only wp-content folder
Smart file search by name across theme and plugin folders
*/
//----------------------- BLOCKED FILES - NEVER ALLOW ACCESS ----------------------------------------------

define('CLAUDE_BLOCKED_FILES', array(
    'wp-config.php',
    'wp-login.php',
    'wp-settings.php',
    'wp-cron.php',
    'wp-blog-header.php',
    '.htaccess',
    '.env',
));

//----------------------- BLOCKED DIRECTORIES - NEVER ALLOW ACCESS ----------------------------------------------

define('CLAUDE_BLOCKED_DIRS', array(
    'wp-admin',
    'wp-includes',
));

//----------------------- ALLOWED FILE EXTENSIONS ONLY ----------------------------------------------

define('CLAUDE_ALLOWED_EXTENSIONS', array(
    'php', 'css', 'js', 'html', 'txt', 'json', 'md'
));

//----------------------- ALLOWED BASE PATHS - ONLY WP-CONTENT ----------------------------------------------
define('CLAUDE_ALLOWED_BASE', WP_CONTENT_DIR);


//----------------------- MAIN VALIDATION FUNCTION ----------------------------------------------
function claude_validate_file_path($path) {

    // resolve real path — converts relative paths and removes ../ tricks
    $real_path = realpath($path); //path from server : /var/www/html/wp-content/abc.php

    // block path traversal — if realpath fails file doesnt exist or path is manipulated
    if (!$real_path) {
        return array('valid' => false, 'error' => 'File not found or invalid path.');
    }

    // block anything outside wp-content
    if (strpos($real_path, CLAUDE_ALLOWED_BASE) !== 0) {
        return array('valid' => false, 'error' => 'Access denied. Only files inside wp-content are allowed.');
    }

    // block blocked directories
    foreach (CLAUDE_BLOCKED_DIRS as $blocked_dir) { //DIRECTORY_SEPARATOR = '/'
        if (strpos($real_path, DIRECTORY_SEPARATOR . $blocked_dir . DIRECTORY_SEPARATOR) !== false) { // BLOCKING: /var/www/html/wp-content/wp-admin/file.php 
            return array('valid' => false, 'error' => 'Access denied. This directory is protected.');
        }
    }

    // get just the filename for blocked files check
    $filename = basename($real_path);

    // block blocked files by name
    if (in_array(strtolower($filename), CLAUDE_BLOCKED_FILES)) {
        return array('valid' => false, 'error' => 'Access denied. This file is protected.');
    }

    // check file extension is allowed. pathinfo = filename in 2 parts, name+extension
    $extension = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
    if (!in_array($extension, CLAUDE_ALLOWED_EXTENSIONS)) {
        return array('valid' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', CLAUDE_ALLOWED_EXTENSIONS));
    }

    // all checks passed
    return array('valid' => true, 'path' => $real_path);
}


//----------------------- SMART FILE SEARCH BY FILENAME -Where to Search ----------------------------------------------
function claude_find_file_by_name($filename) {

// Get list of folders to search
    $search_locations = array(
    'Active Theme'  => get_stylesheet_directory(),        // specific — just active theme
    'Parent Theme'  => get_template_directory(),          // specific — just parent theme
    'This Plugin'   => CLAUDE_PLUGIN_DIR,                 // specific — just this plugin
);

    $matches = array();

    // Loop each folder
    foreach ($search_locations as $label => $base_path) { //label =name base_path = path


        // search recursively inside each location |Call recursive search inside it
        $found = claude_search_file_recursive($base_path, $filename);

        // If found, save label + full path 
        if ($found) {
            $matches[] = array(
                'label' => $label,
                'path'  => $found,
            );
        }
    }

    return $matches; // Return all matches
}


//----------------------- RECURSIVE FILE SEARCH HELPER - how to search----------------------------------------------

function claude_search_file_recursive($dir, $filename) {

    if (!is_dir($dir)) return null; //Check folder exists

    // limit search depth to 4 levels to avoid scanning entire server
    static $depth = 0;
    if ($depth > 4) return null;

    $depth++;

    $items = scandir($dir);//reading all file and folder inside 

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue; //skips sys folders like current and parent 

        $full_path = $dir . DIRECTORY_SEPARATOR . $item;

                // found exact FILE NAME 
                if (is_file($full_path) && strtolower($item) === strtolower($filename)) {
                    $depth = 0;
                    return $full_path;
                }

        // FIND ANOTHER FOLDER,  go deeper into subdirectory
        if (is_dir($full_path)) {
            $result = claude_search_file_recursive($full_path, $filename);
            if ($result) {
                $depth = 0;
                return $result;
            }
        }
    }

    // NOTHING FOUND
    $depth--;
    return null;
}