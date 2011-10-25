<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
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
 * Main web interface entry point for Yioop!
 * search site. Used to both get and display
 * search results. Also used for inter-machine
 * communication during crawling
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

/** Calculate base directory of script
 *  @ignore
 */
define("BASE_DIR", substr($_SERVER['SCRIPT_FILENAME'], 0,-strlen("index.php")));

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
 * Load global functions related to localization
 */
require_once BASE_DIR."/locale_functions.php";

/**
 * Load FileCache class in case used
 */
require_once(BASE_DIR."/lib/file_cache.php");

if(USE_MEMCACHE) {
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

mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

if ( false === function_exists('lcfirst') ) {
    /**
     *  Lower cases the first letter in a string
     *
     *  This function is only defined if the PHP version is before 5.3
     *  @param string $str  string to be lower cased
     *  @return string the lower cased string
     */
    function lcfirst( $str )
    { return (string)(strtolower(substr($str,0,1)).substr($str,1));}
}

$available_controllers = array("search", "fetch", "cache",
    "settings", "admin", "archive");
if(!WEB_ACCESS) {
$available_controllers = array("fetch", "cache",
    "admin", "archive");
}

//the request variable c is used to determine the controller
if(!isset($_REQUEST['c'])) {
    $controller_name = "search";
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
if(!PROFILE ) {
    $controller_name = "admin";
}

/* the request variable l and the browser's HTTP_ACCEPT_LANGUAGE
   are used to determine the locale */
if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $l_parts = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    if(count($l_parts) > 0) {
        $guess_l = $l_parts[0];
    }
    $guess_map = array(
        "en" => "en-US",
        "en-US" => "en-US",
        "fr" => "fr-FR",
        "ko" => "ko",
        "in" => "in-ID",
        "ja" => "ja",
        "vi" => "vi-VN",
        "vi-VN" => "vi-VN",
        "zh" => "zh-CN",
        "zh-CN" => "zh-CN"
    );
    if(isset($guess_map[$guess_l])) {
        $guess_l = $guess_map[$guess_l];
    }

}

if(isset($_SESSION['l']) || isset($_REQUEST['l']) || isset($guess_l)) {
    $l = (isset($_REQUEST['l'])) ? $_REQUEST['l'] : 
        ((isset($_SESSION['l'])) ? $_SESSION['l'] : $guess_l);
    if(strlen($l) < 10) {
        $l= addslashes($l);
        if(is_dir(LOCALE_DIR."/$l")) {

            $locale_tag = $l;
        }
    }
}

if(!isset($locale_tag)) {
    $locale_tag = DEFAULT_LOCALE;
}

if(upgradeLocaleCheck()) {
    upgradeLocale();
}

if(upgradeDatabaseCheck()) {
    upgradeDatabase();
}

$locale = NULL;
setLocaleObject($locale_tag);

/**
 * Loads controller responsible for calculating
 * the data needed to render the scene
 *
 */
require_once(BASE_DIR."/controllers/".$controller_name."_controller.php");
$controller_class = ucfirst($controller_name)."Controller";
$controller = new $controller_class($INDEXING_PLUGINS);

$controller->processRequest();

/**
 * Verifies that the supplied controller string is a controller for the
 * SeekQuarry app
 *
 * @param string $controller_name  name of controller
 *      (this usually come from the query string)
 * @return bool  whether it is a valid controller
 */
function checkAllowedController($controller_name)
{
    global $available_controllers;

    return in_array($controller_name, $available_controllers) ;
}

/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}


/**
 * Checks to see if the locale data of Yioop! in the work dir is older than the
 * currently running Yioop!
 */
function upgradeLocaleCheck()
{
    global $locale_tag;
    $config_name = LOCALE_DIR."/$locale_tag/configure.ini";
    $fallback_config_name = 
        FALLBACK_LOCALE_DIR."/$locale_tag/configure.ini";
    if(filemtime($fallback_config_name) > filemtime($config_name)) {
        return "locale";
    }
    return false;
}

/**
 * If the locale data of Yioop! in the work directory is older than the
 * currently running Yioop! then this function is called to at least
 * try to copy the new strings into the old profile.
 */
function upgradeLocale()
{
    global $locale;
    $locale = new LocaleModel();
    $locale->extractMergeLocales();
}

/**
 * Checks to see if the database data of Yioop! is from an older version
 * of Yioop! than the currently running Yioop!
 */
function upgradeDatabaseCheck()
{
    $model = new Model();
    $model->db->selectDB(DB_NAME);
    $sql = "SELECT ID FROM VERSION";

    $result = @$model->db->execute($sql);
    if($result !== false) {
        $row = $model->db->fetchArray($result);
        if($row['ID'] == 1) {
            return false;
        }
    }
    return true;
}

/**
 * If the database data of Yioop!  is older than the version of the
 * currently running Yioop! then this function is called to try
 * upgrade the database to the new version
 */
function upgradeDatabase()
{
    $versions = array(0, 1);
    $model = new Model();
    $model->db->selectDB(DB_NAME);
    $sql = "SELECT ID FROM VERSION";
    $result = @$model->db->execute($sql);
    if($result !== false) {
        $row = $this->db->fetchArray($result);
        if(isset($row['ID']) && in_array($row['ID'], $versions)) {
            $current_version = $row['ID'];
        } else {
            $current_version = 0;
        }
    } else {
        $current_version = 0;
    }
    $key = array_search($current_version, $versions);
    $versions = array_slice($versions, $current_version + 1);
    foreach($versions as $version) {
        $upgradeDB = "upgradeDatabaseVersion$version";
        $upgradeDB($model->db);
    }
}

/**
 * Upgrades a Version 0 version of the Yioop! database to a Version 1 version
 * @param resource $db database handle to use to upgrade 
 */
function upgradeDatabaseVersion1($db)
{
    $db->execute("CREATE TABLE VERSION( ID INTEGER PRIMARY KEY)");
    $db->execute("INSERT INTO VERSION VALUES (1)");
    $db->execute("CREATE TABLE USER_SESSION( USER_ID INTEGER PRIMARY KEY, ".
        "SESSION VARCHAR(4096))");
}
?>
