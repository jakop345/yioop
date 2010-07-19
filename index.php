<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * @copyright 2009, 2010
 * @filesource
 */

/** Calculate base directory of script */
define("BASE_DIR", substr($_SERVER['DOCUMENT_ROOT'].$_SERVER['PWD'].
    $_SERVER["SCRIPT_NAME"], 0, -strlen("index.php")));

/**
 * Load the configuration file
 */
require_once(BASE_DIR.'configs/config.php');

ini_set("memory_limit","200M");
session_name(SESSION_NAME); 
session_start();

/**
 * Sets up DB to be used
 */
require_once(BASE_DIR."/models/datasources/".DBMS."_manager.php"); 

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

//the request variable c is used to determine the controller
if(!isset($_REQUEST['c'])) {
    $controller_name = "search";
} else {
   $controller_name = $_REQUEST['c'];
}

if(!checkAllowedController($controller_name))
{
    $controller_name = "search";
}

// if no profile exists we force the page to be the configuration page
if(!PROFILE ) {
    $controller_name = "admin";
}

//the request variable l is used to determine the locale 
if(isset($_SESSION['l']) ||isset($_REQUEST['l'])) {
    $l = (isset($_SESSION['l'])) ? $_SESSION['l'] :$_REQUEST['l'];
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

/**
 * Used to contain information about the current language and regional settings
 */
require_once BASE_DIR."/models/locale_model.php";

$locale = NULL;
setLocaleObject($locale_tag);


/**
 * Loads controller responsible for calculating 
 * the data needed to render the scene
 * 
 */
require_once(BASE_DIR."/controllers/".$controller_name."_controller.php");
$controller_class = ucfirst($controller_name)."Controller";
$controller = new $controller_class();


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

    return in_array($controller_name, $available_controllers);
}

/**
 * shorthand for echo
 *
 * @param string $text   string to send to the current output
 */
function e($text)
{
    echo $text;
}

/**
 * Translate the supplied arguments into the current locale.
 * This function takes a variable number of arguments. The first
 * being an identifier to translate. Additional arguments
 * are used to interpolate values in for %s's in the translation.
 *
 * @param string string_identifier  identifier to be translated
 * @param mixed additional_args  used for interpolation in translated string
 * @return string  translated string
 */
function tl()
{
    global $locale;

    $args = func_get_args();

    $translation = $locale->translate($args);
    if($translation == "") {
        $translation = $args[0];
    }
    return $translation;
}

/**
 * Sets the language to be used for locale settings
 *
 * @param string $locale_tag the tag of the language to use to determine 
 *      locale settings
 */
function setLocaleObject($locale_tag)
{
    global $locale;
    $locale = new LocaleModel();
    $locale->initialize($locale_tag);
}

/**
 * Gets the language tag (for instance, en_US for American English) of the 
 * locale that is currently being used.
 *
 * @return string  the tag of the language currently being used for locale 
 *      settings
 */
function getLocaleTag()
{
    global $locale;
    return $locale->getLocaleTag();
}

/**
 * Returns the current language directions. 
 *
 * @return string ltr or rtl depending on if the language is left-to-right 
 * or right-to-left
 */
function getLocaleDirection()
{
    global $locale;
    return $locale->getLocaleDirection();
}

/**
 * Returns the current locales method of writing blocks (things like divs or 
 * paragraphs).A language like English puts blocks one after another from the 
 * top of the page to the bottom. Other languages like classical Chinese list 
 * them from right to left.
 *
 *  @return string  tb lr rl depending on the current locales block progression
 */
function getBlockProgression()
{
    global $locale;
    return $locale->getBlockProgression();

}

/**
 * Returns the writing mode of the current locale. This is a combination of the 
 * locale direction and the block progression. For instance, for English the 
 * writing mode is lr-tb (left-to-right top-to-bottom).
 *
 *  @return string   the locales writing mode
 */
function getWritingMode()
{
    global $locale;
    return $locale->getWritingMode();

}


?>
