<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * Web page used to display test results for the available unit tests of
 * the SeekQuarry/Yioop Search engine
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
/** Calculate base directory of script
 * @ignore
 */
define("BASE_DIR", substr($_SERVER['SCRIPT_FILENAME'], 0,
    -strlen("tests/index.php")));
header("X-FRAME-OPTIONS: DENY"); //prevent click jacking
/** Load search engine wide configuration file */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE || !DISPLAY_TESTS) {echo "BAD REQUEST"; exit();}
/**
 * NO_CACHE means don't try to use memcache
 * @ignore
 */
define("NO_CACHE", true);
/**  Draw head of the html page */
?>
<!DOCTYPE html>
<html lang="en-US" dir="ltr">
    <head>
        <title>Seekquarry Search Engine Tests</title>
        <meta name="Author" content="Christopher Pollett" />
        <meta name="description"
            content="Displays unit tests for search engine" />
        <meta charset="utf-8" />
        <link rel="shortcut icon"   href="../favicon.ico" />
        <style type="text/css">
            .green
            {
                background-color: lightgreen;
            }
            .red
            {
                background-color: red;
            }
            table,
            tr,
            td,
            th
            {
                border: 1px ridge black;
                padding: 2px;
            }
        </style>
    </head>
    <body>
<?php
/**
 * Load the base unit test class
 */
require_once BASE_DIR."/lib/unit_test.php";
/**
 * Load the base unit test class for Javascript tests
 */
require_once BASE_DIR."/lib/javascript_unit_test.php";
/**
 * Load the YioopPhantomRunner that is used to execute PhantomJs Shell.
 */
require_once 'yioop_phantom_runner.php';
/**
 * Do not send output to log files
 * @ignore
 */
define("LOG_TO_FILES", false);
$allowed_activities =
    array("listTests", "runAllTests", "runTestBasedOnRequest","runPhantomTests");
if(isset($_REQUEST['activity']) &&
    in_array($_REQUEST['activity'], $allowed_activities)) {
    $activity = $_REQUEST['activity'];
} else {
    $activity = "listTests";
}
?>
<h1>SeekQuarry Tests</h1>
<?php
$activity();
/**
 * This function runs the phantomJS tests by calling th phantomJs shell to
 * execute the PhantomJs tests written in JavaScript.
 */
function runPhantomTests()
{
    ob_clean();
    $path_url = str_replace(basename(__DIR__) . "/", "", getFullURL(true));
    $mode = "";
    $resp_code = "";
    $u = $_REQUEST['u'];
    $p = $_REQUEST['p'];
    if(isset($_REQUEST['mode'])) {
        if(isset($_REQUEST['debug']) && $_REQUEST['debug'] == "true") {
            $debug = true;
        }else{
            $debug = "false";
        }
        $mode = htmlentities($_REQUEST['mode'], ENT_QUOTES, "UTF-8");
        $resp = array();
        if(!in_array($mode, array("web", "mobile"))) {
            $resp_code = "HTTP/1.1 400 Bad Request";
        } else {
            try {
                $yioop_phantom_runner = new YioopPhantomRunner();
                $test_results = ($yioop_phantom_runner->execute(
                    "phantomjs_runner.js", $path_url, $mode, $debug, $u, $p));
                if(!$test_results) {
                    $resp_code = "HTTP/1.1 500";
                } else {
                    $resp['results'] = $test_results;
                    $resp_code = "HTTP/1.1 200 OK";
                    echo(json_encode($resp));
                }
            }catch(Exception $e){
                $resp_code = "HTTP/1.1 500";
                $resp['error'] = $e->getMessage();
                echo(json_encode($resp));
            }
        }
    } else {
        $resp_code = "HTTP/1.1 500";
        $resp['error'] = "Bad Request";
        echo(json_encode($resp));
    }
    header($resp_code);
    header("Content-Type : application/json");
    exit();
}

/**
 * This is a utility function to get the Full URL of the current page.
 */
function getFullURL($stripQueryParams = false)
{
    $pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
    if(!in_array($_SERVER["SERVER_PORT"], array("80", "443"))) {
        $pageURL .= $_SERVER["SERVER_NAME"] . ":" .
            $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
    }
    //return full URL with query params stripped, if requested.
    return $stripQueryParams ? strtok($pageURL, '?') : $pageURL;
}
/**
 * This function is responsible for listing out HTML links to the available
 * unit tests a user can run
 */
function listTests()
{
    $names = getTestNames();
    ?>
    <p><a href="?activity=runAllTests">Run All Tests</a>.</p>
    <h2>Available Tests</h2>
    <ul>
    <?php
    foreach($names as $name) {
        $stem = substr($name, 0, strlen($name) - strlen("_test.php"));
        echo "<li><a href='?activity=runTestBasedOnRequest&test=$stem'>".
            getClassNameFromFileName($name)."</a></li>";
    }
    ?>
    </ul>
    <?php

}
/**
 * Runs all the unit_tests in the current directory and displays the results
 */
function runAllTests()
{
    $names = getTestNames();
    echo "<p><a href='?activity=listTests'>See test case list</a>.</p>";
    foreach($names as $name) {
        runTest($name);
    }
}
/**
 * Run the single unit test whose name is given in $_REQUEST['test'] and
 * display the results. If the unit test file was blah_test.php, then
 * $_REQUEST['test'] should be blah.
 */
function runTestBasedOnRequest()
{
    echo "<p><a href='?activity=listTests'>See test case list</a>.</p>";
    if(isset($_REQUEST['test'])) {
        $name = preg_replace("/[^A-Za-z_0-9]/", '', $_REQUEST['test']).
            "_test.php";
        if(file_exists($name)) {
            runTest($name);
        }
    }
}
/**
 * Uses $name to load a unit test class, run the tests in it and display the
 * results
 *
 * @param string $name  the name of a unit test file in the current directory
 */
function runTest($name)
{
    $class_name = getClassNameFromFileName($name);
    echo "<h2>$class_name</h2>";
    require_once $name;
    $test = new $class_name();
    if($test instanceof JavascriptUnitTest) {
        $test->run();
    } else {
        $results = $test->run();
    ?>
    <table class="wikitable"
        summary="Displays info about this test case">
    <?php
        foreach($results as $test_case_name => $data) {
            echo "<tr><th>$test_case_name</th>";
            $passed = 0;
            $count = 0;
            $failed_items = array();
            foreach ($data as $item) {
                if($item['PASS']) {
                    $passed++;
                } else {
                    $failed_items[] = $item;
                }
                $count++;
            }
            if ($count == $passed) {
                $color = "green";
            } else {
                $color = "red";
            }
            echo "<td class='$color'>$passed/$count Tests Passed<br />";
            if(count($failed_items) > 0 ) {
                foreach($failed_items as $item) {
                    echo "  FAILED: ".$item['NAME']."<br />";
                }
            }
            echo "</td></tr>";
        }
    ?>
    </table>
    <?php
    }
}
/**
 * Gets the names of all the unit test files in the current directory.
 * Doesn't really check for this explicitly, just checks if the file
 * end with _test.php
 *
 * @return array   an array of unit test files
 */
function getTestNames()
{
    return glob('*_test.php');
}
/**
 * Convert the convention for unit test file names into our convention
 * for unit test class names
 *
 * @param string $name  a file name with words separated by underscores, ending
 * in .php
 *
 * @return string  a camel-cased name ending with Test
 */
function getClassNameFromFileName($name)
{
    $name_parts = explode('_', $name);

    $class_name = "";
    foreach($name_parts as $part) {
        $class_name .= ucfirst($part);
    }
    //strip .php
    $class_name = substr($class_name, 0, strlen($class_name) - strlen(".php"));

    return $class_name;
}
?>
    </body>
</html>
