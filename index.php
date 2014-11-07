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
 * Main web interface entry point for Yioop!
 * search site. Used to both get and display
 * search results. Also used for inter-machine
 * communication during crawling
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
/** Calculate base directory of script
 * @ignore
 */
$pathinfo = pathinfo($_SERVER['SCRIPT_FILENAME']);
define("BASE_DIR", $pathinfo["dirname"].'/');
$pathinfo = pathinfo($_SERVER['SCRIPT_NAME']);
$http = isset($_SERVER['HTTPS']) ? "https://" : "http://";
//used in register controller to create links back to server
define("BASE_URL", $http.$_SERVER['SERVER_NAME'].$pathinfo["dirname"]."/");
/**
 * Check for paths of the form index.php/something which yioop doesn't support
 */
$s_name = $_SERVER['SCRIPT_NAME']."/";
$path_name = substr($_SERVER["REQUEST_URI"], 0, strlen($s_name));
if(strcmp($path_name, $s_name) == 0) {
    $_SERVER["PATH_TRANSLATED"] = BASE_DIR;
    $scriptinfo = pathinfo($s_name);
    $_SERVER["PATH_INFO"] = ($scriptinfo["dirname"] == "/") ? "" :
        $scriptinfo["dirname"] ;
    include(BASE_DIR."/error.php");
    exit();
}
if(!isset($_SERVER["PATH_INFO"])) {
    $_SERVER["PATH_INFO"] = ".";
}
/**
 * Make an initial setting of controllers. This can be overridden in
 * local_config
 */
$available_controllers = array( "admin", "api", "archive",  "cache",
    "classifier", "crawl", "fetch", "group", "machine", "resource", "search",
    "settings", "statistics", "static");
/**
 * Load the configuration file
 */
require_once(BASE_DIR.'configs/config.php');
ini_set("memory_limit","500M");
header("X-FRAME-OPTIONS: DENY"); //prevent click-jacking
session_name(SESSION_NAME);
session_start();
/**
 * Sets up DB to be used
 */
require_once(BASE_DIR."/models/datasources/".DBMS."_manager.php");
/**
 * Load e() function
 */
require_once BASE_DIR."/lib/utility.php";
if((DEBUG_LEVEL & ERROR_INFO) == ERROR_INFO) {
    set_error_handler("yioop_error_handler");
}
/**
 * Load global functions related to localization
 */
require_once BASE_DIR."/lib/locale_functions.php";

/**
 * Load global functions related to checking Yioop! version
 */
require_once BASE_DIR."/lib/upgrade_functions.php";

/**
 * Load FileCache class in case used
 */
require_once(BASE_DIR."/lib/file_cache.php");
if(USE_MEMCACHE && class_exists("Memcache")) {
    $CACHE = new Memcache();
    foreach($MEMCACHES as $mc) {
        $CACHE->addServer($mc['host'], $mc['port']);
    }
    unset($mc);
    define("USE_CACHE", true);
} else if (USE_FILECACHE) {
    $CACHE = new FileCache(WORK_DIRECTORY."/cache/queries");
    define("USE_CACHE", true);
} else {
    define("USE_CACHE", false);
}
if(!function_exists('mb_internal_encoding')) {
    echo "PHP Zend Multibyte Support must be enabled for Yioop! to run.";
    exit();
}
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");
if (function_exists('lcfirst') === false) {
    /**
     * Lower cases the first letter in a string
     *
     * This function is only defined if the PHP version is before 5.3
     * @param string $str  string to be lower cased
     * @return string the lower cased string
     */
    function lcfirst( $str )
    {
        return (string)(strtolower(substr($str, 0, 1)).substr($str, 1));
    }
}
if(in_array(REGISTRATION_TYPE, array('no_activation', 'email_registration',
    'admin_activation'))) {
    $available_controllers[] = "register";
}
if(!WEB_ACCESS) {
    $available_controllers = array("admin", "archive", "cache", "crawl","fetch",
        "machine");
}
//the request variable c is used to determine the controller
if(!isset($_REQUEST['c'])) {
    $controller_name = "search";
    if(defined('LANDING_PAGE') && LANDING_PAGE && !isset($_REQUEST['q'])) {
        $controller_name = "static";
        $_REQUEST['c'] = "static";
        $_REQUEST['p'] = "Main";
    }
} else {
    $controller_name = $_REQUEST['c'];
}
if(!checkAllowedController($controller_name))
{
    if(WEB_ACCESS) {
        $controller_name = "search";
    } else {
        $controller_name = "admin";
    }
}
// if no profile exists we force the page to be the configuration page
if(!PROFILE || (defined("FIX_NAME_SERVER") && FIX_NAME_SERVER)) {
    $controller_name = "admin";
}
$locale_tag = guessLocale();

if(upgradeDatabaseWorkDirectoryCheck()) {
    upgradeDatabaseWorkDirectory();
}
if(upgradeLocalesCheck()) {
    upgradeLocales();
}
$locale = NULL;
setLocaleObject($locale_tag);
if(file_exists(APP_DIR."/index.php")) {
    require_once(APP_DIR."/index.php");
}
/**
 * Loads controller responsible for calculating
 * the data needed to render the scene
 *
 */
if(file_exists(APP_DIR."/controllers/".$controller_name."_controller.php")) {
    require_once(APP_DIR."/controllers/".$controller_name."_controller.php");
} else {
    require_once(BASE_DIR."/controllers/".$controller_name."_controller.php");
}
$controller_class = ucfirst($controller_name)."Controller";
$controller = new $controller_class($INDEXING_PLUGINS);
$controller->processRequest();
/**
 * Verifies that the supplied controller string is a controller for the
 * SeekQuarry app
 *
 * @param string $controller_name  name of controller
 *     (this usually come from the query string)
 * @return bool  whether it is a valid controller
 */
function checkAllowedController($controller_name)
{
    global $available_controllers;
    return in_array($controller_name, $available_controllers) ;
}

?>
