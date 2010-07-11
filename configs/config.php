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
 * Used to set the configuration settings of the three components of the
 * SeekQuarry project: the crawler (crawl.php), the ranker (rank.php), 
 * and the search results web page (index.php).
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage config
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
define('TEST_INFO', 1);
define('QUERY_INFO', 2);
define('ERROR_INFO', 4);
date_default_timezone_set('America/Los_Angeles');

/////////////////////////////////////////*+++ The next block of code is machine edited, change at your own risk, please use configure web page instead +++*/
define('WORK_DIRECTORY', '/Applications/xampp/xamppfiles/htdocs/crawls');
/*++++++*/

if(file_exists(WORK_DIRECTORY."/profile.php")) {
    require_once(WORK_DIRECTORY."/profile.php");
    define('PROFILE', true);
    define('CRAWL_DIR', WORK_DIRECTORY);
    if(is_dir(WORK_DIRECTORY."/locale")) {
        define('LOCALE_DIR', WORK_DIRECTORY."/locale");
    } else {
        define('LOCALE_DIR', BASE_DIR."/locale");
    }
    define('LOG_DIR', WORK_DIRECTORY."/log");
} else {
    if($_SERVER['SERVER_NAME'] !== 'localhost') {
        echo "SERVICE AVAILABLE ONLY VIA LOCALHOST UNTIL CONFIGURED"; 
        exit();
    }
    error_reporting(-1);
    define('PROFILE', false);
    define('DBMS', 'sqlite3');
    define('DEBUG_LEVEL', "NO_DEBUG_INFO");
    define('DB_NAME', "default");
    define('DB_USER', '');
    define('DB_PASSWORD', '');
    define('DB_URL', '');
    define('CRAWL_DIR', BASE_DIR);
    define('LOCALE_DIR', BASE_DIR."/locale");
    define('LOG_DIR', BASE_DIR."/log");
    define('QUEUE_SERVER', "http://localhost/");
    define('USER_AGENT_SHORT', "NeedsNameBot");
    define('SESSION_NAME', "yioopbiscuit");
    define('DEFAULT_LOCALE', "en-US");
    define('AUTH_KEY', 0);
}

if((DEBUG_LEVEL & ERROR_INFO) == ERROR_INFO) {
    error_reporting(-1);
} else {
    error_reporting(0);
}

if( (DEBUG_LEVEL & TEST_INFO) == TEST_INFO) {
    define('DISPLAY_TESTS', true);
} else {
    define('DISPLAY_TESTS', false);
}

if( (DEBUG_LEVEL & QUERY_INFO) == QUERY_INFO) {
    define('QUERY_STATISTICS', true);
} else {
    define('QUERY_STATISTICS', false);
}

if(!PROFILE) {
    return;
}
/*+++ End machine generated code, feel free to edit the settings below as desired +++*/

define('USER_AGENT', 'Mozilla/5.0 (compatible; '.USER_AGENT_SHORT.'  +'.QUEUE_SERVER.'bot.php)'); 
    //this is the User-Agent names the crawler provides a web-server it is crawling
define ('SESSION_NAME', "yioopbiscuit"); //name of the cookie used to manage the session (store language and perpage settings)

define("MAX_LOG_FILE_SIZE", 5000000); // maximum size of a log file before it is rotated
define("NUMBER_OF_LOG_FILES", 5); // number of log files to rotate amongst

define('CACHE_ROBOT_TXT_TIME', 86400); // how long in seconds to keep a cache of a robot.txt file before re-requesting it
define('MAXIMUM_CRAWL_DELAY', 64);  // if the robots.txt has a Crawl-delay larger than this value don't crawl the site
                                    // maximum value for this is 255
define('MAX_WAITING_HOSTS', 1000); //maximum number of active crawl-delayed hosts  


define('URL_FILTER_SIZE', 10000000);    // bloom filters are used to keep track of which urls are visited, this parameter determines up to how many
                                        // urls will be stored in a single filter. Additional filters are read to and from disk.
define('NUM_FETCHERS', 3); // number of fetchers that will be used in a given crawl
define('NUM_URLS_QUEUE_RAM', 300000); // maximum number of urls that will be held in ram (as opposed to in files) in the priority queue

define('MIN_QUEUE_WEIGHT', 1/100000);
define('NUM_ARCHIVE_PARTITIONS', 10); // number of web archive files to use to store web pages in
define('NUM_INDEX_PARTITIONS', 250); // number of web archive files to use for the inverted index of word->docs in a given generation
define('NUM_WORDS_PER_GENERATION', 6*URL_FILTER_SIZE/NUM_INDEX_PARTITIONS); // number of words before next gen

define('SAMPLE_GENERATIONS', 3); // number of generations to sample in estimating number of urls in a query


define('STORE_INLINKS_IN_DICTIONARY', false); //store inlink data in word inverted index
define('PRECISION', 10); // precision to round floating points document scores
define('BLOCK_SIZE', 50); //when index data from relatively uncommon words, how many docs should be grouped together in a block
define('COMMON_WORD_THRESHOLD', 1000); // how many documents a word needs to be to get its own index file.

define('MAX_LINKS_PER_PAGE', 50); // maximum number of links to consider on any given page
define('MAX_LINKS_WORD_TEXT', 200); // maximum number of words from links to consider on any given page
define('PAGE_RANGE_REQUEST', 50000); // request this many bytes out of a page
define('MAX_PHRASE_LEN', 2); //maximum length +1 exact phrase matches

define('NUM_MULTI_CURL_PAGES', 100); //number of multi curl page requests in one go
define('PAGE_TIMEOUT', 30); //time in seconds before we give up on a page

define('NORMALIZE_FREQUENCY', 10000); // how often should we make in OPIC the sum of weights totals MAX_URLS


$INDEXED_FILE_TYPES =
    array(  "html",
            "htm", 
            "jsp", 
            "cgi",
            "pl",
            "cfm",
            "cfml", 
            "shtml", 
            "php", 
            "txt", 
            "csv", 
            "tab", 
            "tsv", 
            "asp", 
            "pdf", 
            "doc", 
            "rtf", 
            "ppt",  
            "jpg", 
            "jpeg", 
            "gif", 
            "png");

$PAGE_PROCESSORS = array(   "text/html" => "HtmlProcessor", 
                            "text/asp" => "HtmlProcessor",

                            "application/xhtml+xml" => "HtmlProcessor",

                            "application/pdf" => "PdfProcessor",

                            "application/msword" => "DocProcessor",
                            "application/vnd.ms-powerpoint" => "PptProcessor",

                            "text/rtf" => "RtfProcessor",  
                            "text/plain" => "TextProcessor", 
                            "text/csv" => "TextProcessor",
                            "text/tab-separated-values" => "TextProcessor",

                            "image/jpeg" => "JpgProcessor",
                            "image/gif" => "GifProcessor", 
                            "image/png" => "PngProcessor"
);




define ('SEEN_URLS_BEFORE_UPDATE_SCHEDULER', 500);
define ('MAX_FETCH_SIZE', 5000); //maximum number of urls to schedule to a given fetcher in one go
define ('MINIMUM_FETCH_LOOP_TIME', 5); //fetcher must wait at least this long between multi-curl requests

//searching and admin
define ('NUM_RESULTS_PER_PAGE', 10); //default number of search results to display per page

define ('NUM_RECENT_URLS_TO_DISPLAY', 10); // Number of recently crawled urls to display on admin screen




?>
