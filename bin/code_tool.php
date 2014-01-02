<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * Tool used to help coding with Yioop. Has commands to update copyright info,
 * clean trailing spaces, find long lines, and do global file searches and
 * replaces.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}

/** Calculate base directory of script @ignore */
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

$commands = array("copyright", "clean", "longlines", "search", "replace");
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
    echo <<< EOD
code_tool.php has the following command formats:

php code_tool.php clean path
    Replaces all tabs with four spaces and trims all whitespace off ends of
    lines in the folder or file path.

php code_tool.php copyright path
    Adjusts all lines in the files in the folder at path (or if
    path is a file just that) of the form 2009 - \d\d\d\d to
    the form 2009 - this_year where this_year is the current year.

php code_tool.php longlines path
    Prints out all lines in files in the folder or file path which are
    longer than 80 characters.

php code_tool.php replace path pattern replace_string
  or
php code_tool.php replace path pattern replace_string effect
    Prints all lines matching the regular expression pattern followed
    by the result of replacing pattern with replace_string in the
    folder or file path. Does not change files.

php code_tool.php replace path pattern replace_string interactive
    Prints each line matching the regular expression pattern followed
    by the result of replacing pattern with replace_string in the
    folder or file path. Then it asks if you want to update the line.
    Lines you choose for updating will be modified in the files.

php code_tool.php replace path pattern replace_string change
    Each line matching the regular expression pattern is update
    by replacing pattern with replace_string in the
    folder or file path. This format doe not echo anything, it does a global
    replace without interaction.

php code_tool.php search path pattern
    Prints all lines matching the regular expression pattern in the
    folder or file path.

EOD;
}

/**
 *
 * @param array $args $args[0] contains path to sub-folder/file
 * @return bool $no_instructions false if should output code_tool.php
 *      instructions
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
 * Updates the copyright info (assuming in Yioop docs format) on files
 * in supplied sub-folder/file. That is, it changes strings matching
 * /2009 - \d\d\d\d/ to 2009 - current_year in those files/file.
 *
 * @param array $args $args[0] contains path to sub-folder/file
 * @return bool $no_instructions false if should output code_tool.php
 *      instructions
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
 * Search and echos line numbers and lines for lines of length greater than 80
 * characters in files in supplied sub-folder/file,
 *
 * @param array $args $args[0] contains path to sub-folder/file
 * @return bool $no_instructions false if should output code_tool.php
 *      instructions
 */
function longlines($args)
{
    global $change_extensions;
    $no_instructions = false;
    $change_extensions = array_diff($change_extensions, array("ini", "xml"));

    if(isset($args[0])) {
        $path = realpath($args[0]);
        searchFile("", "/([^\n]){81}/");// initialize callback
        mapPath($path, "searchFile");
        $no_instructions = true;
    }
    return $no_instructions;
}

/**
 * Performs a search and replace for given pattern in files in supplied
 * sub-folder/file
 *
 * @param array $args $args[0] contains path to sub-folder/file,
 *      $args[1] contains the regex searching for, $args[2] contains
 *      what it should be replaced with, $args[3] (defaults to effect)
 *      controls the mode of operation. One of "effect", "change", or
 *      "interactive". effect shows line number and lines matching pattern,
 *      but commits no changes; interactive for each match, prompts user
 *      if should do the change, change does a global search and replace
 *      without output
 * @return bool $no_instructions false if should output code_tool.php
 *      instructions
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
        if(strlen(stristr($pattern, "@")) > 0) {
            echo "Search pattern should not contain @, bailing !!!";
            return false;
        }
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
 * Performs a search for given pattern in files in supplied sub-folder/file
 *
 * @param array $args $args[0] contains path to sub-folder/file,
 *      $args[1] contains the regex searching for
 * @return bool $no_instructions false if should output code_tool.php
 *      instructions
 */
function search($args)
{
    $no_instructions = false;

    if(isset($args[0]) && isset($args[1])) {
        $path = realpath($args[0]);
        $no_instructions = true;
        $pattern = $args[1];
        $len = strlen($pattern);
        if(strlen(stristr($pattern, "@")) > 0) {
            echo "Search pattern should not contain @, bailing !!!";
            return false;
        }
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
 * Callback function applied to each file in the directory being traversed
 * by @see copyright(). It checks if the files is of the extension of a code file
 * and if so trims whitespace from its lines and then updates the lines
 * of the form 2009 - \d\d\d\d to the supplied copyright year
 *
 * @param string $filename name of file to check for copyright lines and updated
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

        $change = false;
        foreach($lines as $line) {
            $new_line = preg_replace("/2009 \- \d\d\d\d/", $out_year,
                $line);
            $out_lines[] = $new_line;
            if(strcmp($new_line, $line) != 0) {
                $change = true;
            }
        }
        $out_file = implode("\n", $out_lines);
        if($change) {
            file_put_contents($filename, $out_file);
        }
    }
}

/**
 * Callback function applied to each file in the directory being traversed
 * by @see clean().
 *
 * @param string $filename name of file to clean lines for
 */
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
        $change = false;
        foreach($lines as $line) {
            $new_line = preg_replace("/\t/", $spaces, $line);
            $new_line = rtrim($new_line);
            $out_lines[] = $new_line;
            if(strcmp($new_line."\n", $line) != 0) {
                $change = true;
            }
        }
        $out_file = implode("\n", $out_lines);
        if($change) {
            file_put_contents($filename, $out_file);
        }
    }
}

/**
 * Callback function applied to each file in the directory being traversed
 * by @see search(). Searches $filename matching $pattern and outputs line
 *      numbers and lines
 *
 * @param string $filename name of file to search in
 * @param mixed $set_pattern if not false, then sets $set_pattern in $pattern to
 *      initialize the callback on subsequent calls. $pattern here is the
 *      search pattern
 */
function searchFile($filename, $set_pattern = false)
{
    global $change_extensions;
    static $pattern = "/";

    if($set_pattern) {
        $pattern = preg_quote($set_pattern);
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

/**
 * Callback function applied to each file in the directory being traversed
 * by @see replace(). Searches $filename matching $pattern. Depending
 *      on $mode ($arg[2] as described in replace()), it outputs and
 *      replaces with $replace
 *
 * @param string $filename name of file to search and replace in
 * @param mixed $set_pattern if not false, then sets $set_pattern in $pattern to
 *      initialize the callback on subsequent calls. $pattern here is the
 *      search pattern
 * @param mixed $set_replace if not false, then sets $set_replace in $replace to
 *      initialize the callback on subsequent calls.
 * @param mixed $set_mode if not false, then sets $set_mode in $mode to
 *      initialize the callback on subsequent calls.
 */
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
        $change = false;
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
                if(strcmp($new_line, $line) != 0) {
                    $change = true;
                }
            }
            $out_lines .= $new_line;
        }
        if(in_array($mode, array("change", "interactive"))) {
            if($change) {
                file_put_contents($filename, $out_lines);
            }
        }
    }
}

/**
 * Applies the function $callback to each file in $path
 *
 * @param string $path to apply map $callback to
 * @param string $callback function name to call with filename of each file
 *      in path
 */
function mapPath($path, $callback)
{
    global $db;

    if(is_dir($path)) {
        $db->traverseDirectory($path, $callback, true);
    } else {
        $callback($path);
    }
}

/**
 * Checks if $path is amongst a list of paths which should be ignored
 *
 * @param $path a directory path
 * @return bool whether or not it should be ignored (true == ignore)
 */
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
