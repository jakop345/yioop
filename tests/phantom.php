<?php

function getFullURL()
{
    $pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
    if(!in_array($_SERVER["SERVER_PORT"], array("80", "443"))) {
        $pageURL .= $_SERVER["SERVER_NAME"] . ":" .
            $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
    }
    return $pageURL;
}
$path = pathinfo($_SERVER['PHP_SELF']);
$path_url = str_replace(basename(__DIR__) . "/"
    . basename(__FILE__), "", getFullURL());
$mode = "";
$resp_code = "";
header($resp_code);
header("Content-Type : application/json");
$debug = false;
if(isset($_GET['mode'])) {
    if(isset($_GET['debug']) && $_GET['debug'] == "true") {
        $debug = true;
    }
    $mode = htmlentities($_GET['mode'], ENT_QUOTES, "UTF-8");
    $resp = array();
    if(!in_array($mode, array("web", "mobile"))) {
        $resp_code = "HTTP/1.1 400 Bad Request";
    } else {
        require_once 'YioopPhantomRunner.php';
        $yioop_phantom_runner = new YioopPhantomRunner();
        $test_results = ($yioop_phantom_runner->execute(
            $mode . "_ui_tests.js", $path_url, $debug));
        if(!$test_results) {
            $resp_code = "HTTP/1.1 500";
        } else {
            $resp['results'] = $test_results;
            $resp_code = "HTTP/1.1 200 OK";
            echo(json_encode($resp));
        }
    }
} else {
    $resp_code = "HTTP/1.1 500";
}
exit();