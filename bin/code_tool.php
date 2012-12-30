<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * Tool used to update copyright to a give year
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}

/** Calculate base directory of script @ignore*/
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/bin")));

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}

/** Used to get @see traverseDirectory() */
require_once BASE_DIR.'/models/model.php';

/** Used to get @see readInput() */
require_once BASE_DIR.'/lib/utility.php';
/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

$no_instructions = false;
$model = new Model();
$db = $model->db;

$commands = array("copyright", "clean", "search", "replace");
$change_extensions = array("php", "js", "ini", "css", "thtml", "xml");
$exclude_paths_containing = array("/.", "/extensions/");
$num_spaces_tab = 4;

if(isset($argv[1]) && in_array($argv[1], $commands)) {
    $command = $argv[1];
    array_shift($argv);
    array_shift($argv);
    $no_instructions = $command($argv);
}


if(!$no_instructions) {
    echo "This program changes trims stray white-space from code\n".
        "files and updates the copyright year to the supplied year\n".
        "for files in a folder. It should be run from the command line as:\n\n";
    echo "php copyright.php path_to_update_dir year\n\n";
}

/**
 *
 */
function copyright($args)
{
    $no_instructions = false;

    if(isset($args[0])) {
        $path = realpath($args[0]);
        $year = date("Y");
        $out_year = "2009 - ".$year;
        replaceFile("", "/2009 \- \d\d\d\d/", $out_year, "change"); 
            // initialize callback
        mapPath($path, "replaceFile");
        $no_instructions = true;
    }
    return $no_instructions;
}

/**
 *
 */
function clean($args)
{
    global $num_spaces_tab;
    
    $no_instructions = false;
    if(isset($args[0])) {
        $path = realpath($args[0]);
        $no_instructions = true;
        mapPath($path, "cleanLinesFile");
    }
    return $no_instructions;
}

/**
 *
 */
function search($args)
{
    $no_instructions = false;

    if(isset($args[0]) && isset($args[1])) {
        $path = realpath($args[0]);
        $no_instructions = true;
        $pattern = $args[1];
        $len = strlen($pattern);
        if($len >= 2) {
            if($pattern[0] != $pattern[$len - 1]) {
                $pattern = "@$pattern@";
            }
            searchFile("", $pattern); // initialize callback
            mapPath($path, "searchFile");
        }
    }
    return $no_instructions;
}

/**
 *
 */
function replace($args)
{
    $no_instructions = false;

    if(isset($args[0]) && isset($args[1]) && isset($args[2])) {
        $path = realpath($args[0]);
        $no_instructions = true;
        $pattern = $args[1];
        $replace = $args[2];
        $mode = (isset($args[3])) ? $args[3] : "effect";
        $len = strlen($pattern);
        if($len >= 2) {
            if($pattern[0] != $pattern[$len - 1]) {
                $pattern = "@$pattern@";
            }
            replaceFile("", $pattern, $replace, $mode); // initialize callback

            mapPath($path, "replaceFile");
        }
    }
    return $no_instructions;
}

/**
 * Callback function applied to each file in the directory being traversed
 * by copyright.php. It checks if the files is of the extension of a code file
 * and if so trims whitespace from its lines and then updates the lines
 * of the form 2009 - \d\d\d\d to the supplied copyright year
 *
 * @param string $filename name of file to check if needs to be updated
 */
function changeCopyrightFile($filename, $set_year = false)
{
    global $change_extensions;
    static $year = 2012;

    if($set_year) {
        $year = $set_year;
    }

    $path_parts = pathinfo($filename);
    $extension = $path_parts['extension'];
    if(!excludedPath($filename) && in_array($extension, $change_extensions)) {
        $lines = file($filename);
        $out_lines = array();
        $num_lines = count($lines);

        foreach($lines as $line) {
            $out_lines[] = preg_replace("/2009 \- \d\d\d\d/", $out_year,
                $line);
        }
        $out_file = implode("\n", $out_lines);
        file_put_contents($filename, $out_file);
    }
}

function cleanLinesFile($filename)
{
    global $change_extensions;
    global $num_spaces_tab;

    $spaces = str_repeat(" ", $num_spaces_tab);
    $path_parts = pathinfo($filename);
    $extension = $path_parts['extension'];
    if(!excludedPath($filename) && in_array($extension, $change_extensions)) {

        $lines = file($filename);
        $out_lines = array();
        foreach($lines as $line) {
            $new_line = preg_replace("/\t/", $spaces, $line);
            $new_line = rtrim($new_line);
            $out_lines[] = $new_line;
        }
        $out_file = implode("\n", $out_lines);
        file_put_contents($filename, $out_file);
    }
}

function searchFile($filename, $set_pattern = false)
{
    global $change_extensions;
    static $pattern = "/";

    if($set_pattern) {
        $pattern = $set_pattern;
    }
    $path_parts = pathinfo($filename);
    if(!isset($path_parts['extension'])) {
        return;
    }
    $extension = $path_parts['extension'];
    if(!excludedPath($filename) && in_array($extension, $change_extensions)) {
        $lines = file($filename);
        $no_output = true;
        $num = 0;
        foreach($lines as $line) {
            $num++;
            if(preg_match($pattern, $line)) {
                if($no_output) {
                    $no_output = false;
                    echo "\nIn $filename:\n";
                }
                echo "  Line $num: $line";
            }
        }
    }
}

function replaceFile($filename, $set_pattern = false,
    $set_replace = false, $set_mode = false)
{
    global $change_extensions;
    static $pattern = "/";
    static $replace = "";
    static $mode = "effect";

    $pattern = ($set_pattern) ? $set_pattern : $pattern;
    $replace = ($set_replace) ? $set_replace : $replace;
    $mode = ($set_mode) ? $set_mode : $mode;

    $path_parts = pathinfo($filename);
    if(!isset($path_parts['extension'])) {
        return;
    }
    $extension = $path_parts['extension'];
    if(!excludedPath($filename) && in_array($extension, $change_extensions)) {
        $lines = file($filename);
        $out_lines = "";
        $no_output = true;
        $silent = false;
        if($mode == "change") {
            $silent = true;
        }
        $num = 0;
        foreach($lines as $line) {
            $num++;
            $new_line = $line;
            if(preg_match($pattern, $line)) {
                if($no_output && !$silent) {
                    $no_output = false;
                    echo "\nIn $filename:\n";
                }
                $new_line = preg_replace($pattern, $replace, $line);
                if(!$silent) {
                    echo "  Line $num: $line";
                    echo "  Changes to: $new_line";
                }
                if($mode == "interactive") {
                    echo "Do replacement? (Yy - yes, anything else no): ";
                    $confirm = strtolower(readInput());
                    if($confirm != "y") {
                        $new_line = $line;
                    }
                }
            }
            $out_lines .= $new_line;
        }
        if(in_array($mode, array("change", "interactive"))) {;
            file_put_contents($filename, $out_lines);
        }
    }
}

function mapPath($path, $callback)
{
    global $db;

    if(is_dir($path)) {
        $db->traverseDirectory($path, $callback, true);
    } else {
        $callback($path);
    }
}

function excludedPath($path)
{
    global $exclude_paths_containing;

    foreach($exclude_paths_containing as $exclude) {
        if(strstr($path, $exclude)) {
            return true;
        }
    }

    return false;
}
?>
