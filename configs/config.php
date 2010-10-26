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
 * Used to set the configuration settings of the SeekQuarry project.
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
/** bit of DEBUG_LEVEL used to indicate test cases should be displayable*/
define('TEST_INFO', 1);
/** bit of DEBUG_LEVEL used to indicate query statistics should be displayed*/
define('QUERY_INFO', 2);
/** bit of DEBUG_LEVEL used to indicate php messages should be displayed*/
define('ERROR_INFO', 4);
date_default_timezone_set('America/Los_Angeles');

/*+++ The next block of code is machine edited, change at 
your own risk, please use configure web page instead +++*/
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
    define('USE_MEMCACHE', false);
}

if((DEBUG_LEVEL & ERROR_INFO) == ERROR_INFO) {
    error_reporting(-1);
} else {
    error_reporting(0);
}

/** if true tests are diplayable*/
define('DISPLAY_TESTS', ((DEBUG_LEVEL & TEST_INFO) == TEST_INFO));

/** if true query statistics are diplayed */
define('QUERY_STATISTICS', ((DEBUG_LEVEL & QUERY_INFO) == QUERY_INFO));

if(!PROFILE) {
    return;
}
/*+++ End machine generated code, feel free to edit the below as desired +++*/

/** this is the User-Agent names the crawler provides 
 * a web-server it is crawling
 */
define('USER_AGENT', 
    'Mozilla/5.0 (compatible; '.USER_AGENT_SHORT.'  +'.QUEUE_SERVER.'bot.php)');

/** name of the cookie used to manage the session 
 * (store language and perpage settings)
 */
define ('SESSION_NAME', "yioopbiscuit"); 

/**
 * @global array addresses of memcached servers to use assuming memcached is
 * available
 */
if(USE_MEMCACHE) {
    $memcache_hosts = explode("|Z|", MEMCACHE_SERVERS);
    foreach($memcache_hosts as $host)
    $MEMCACHES[] = array("host" => $host, "port" => "11211", 
        "persistent" => true, "weight" => 1, "timeout" => 1, "retry" => 5
    );
    unset($memcache_hosts);
    unset($host);
}

/** maximum size of a log file before it is rotated */
define("MAX_LOG_FILE_SIZE", 5000000); 

/** number of log files to rotate amongst */
define("NUMBER_OF_LOG_FILES", 5); 

/**
 * how long in seconds to keep a cache of a robot.txt 
 * file before re-requesting it
 */
define('CACHE_ROBOT_TXT_TIME', 86400); 

/**
 * if the robots.txt has a Crawl-delay larger than this 
 * value don't crawl the site.
 * maximum value for this is 255
 */
define('MAXIMUM_CRAWL_DELAY', 64);

/** maximum number of active crawl-delayed hosts */
define('MAX_WAITING_HOSTS', 1000); 


 
/** 
 * bloom filters are used to keep track of which urls are visited, 
 * this parameter determines up to how many
 * urls will be stored in a single filter. Additional filters are 
 * read to and from disk.
 */
define('URL_FILTER_SIZE', 10000000);

/** number of fetchers that will be used in a given crawl */
define('NUM_FETCHERS', 4); 

/**
 * maximum number of urls that will be held in ram
 * (as opposed to in files) in the priority queue
 */
define('NUM_URLS_QUEUE_RAM', 300000); 

/** Minimum weight in priority queue before rebuilt*/
define('MIN_QUEUE_WEIGHT', 1/100000);

/**  number of web archive files to use to store web pages in */
define('NUM_ARCHIVE_PARTITIONS', 10);

/** number of documents before next gen */
define('NUM_DOCS_PER_GENERATION', 100000);

/** precision to round floating points document scores */
define('PRECISION', 10); 

/** maximum number of links to consider on any given page */
define('MAX_LINKS_PER_PAGE', 50); 

/**  maximum number of words from links to consider on any given page */
define('MAX_LINKS_WORD_TEXT', 100);

/**  maximum length of urls to try to queue, this is important for
 *   memory when creating schedule, since the amount of memory is
 *   going to be greater than the product MAX_URL_LENGTH*MAX_FETCH_SIZE
 *   text_processors need to promise to implement this check or rely
 *   on the base class which does implement it in extractHttpHttpsUrls
 */
define('MAX_URL_LENGTH', 512); 

/** request this many bytes out of a page */
define('PAGE_RANGE_REQUEST', 50000);

/** maximum length +1 exact phrase matches */
define('MAX_PHRASE_LEN', 2); 

/** number of multi curl page requests in one go */
define('NUM_MULTI_CURL_PAGES', 100); 

/** time in seconds before we give up on a page */
define('PAGE_TIMEOUT', 30); 

/** how often should we make in OPIC the sum of weights totals MAX_URLS */
define('NORMALIZE_FREQUENCY', 10000); 

/**
 * @global array file extensions which can be handled by the search engine, 
 * other extensions will be ignored
 */
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

/**
 * @global array associates mimetypes that can be processed by the search 
 * engine with the processor class that can process them
 */
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



/**
 * How many non robot urls the fetcher successfully downloads before
 * between times data sent back to queue server
 */
define ('SEEN_URLS_BEFORE_UPDATE_SCHEDULER', 500);

/** maximum number of urls to schedule to a given fetcher in one go */
define ('MAX_FETCH_SIZE', 5000);

/** fetcher must wait at least this long between multi-curl requests */
define ('MINIMUM_FETCH_LOOP_TIME', 5); 

/** Max time before dirty index saved in seconds*/
define('INDEX_SAVE_TIME', 300);

/** default number of search results to display per page */
define ('NUM_RESULTS_PER_PAGE', 10); 

/** Number of recently crawled urls to display on admin screen */
define ('NUM_RECENT_URLS_TO_DISPLAY', 10); 
?>
