<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */


if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}

/** 
 * Calculate base directory of script
 * @ignore
 */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0, 
    -strlen("/bin")));

ini_set("memory_limit","850M"); //so have enough memory to crawl big pages

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}

/** CRAWLING means don't try to use memcache 
 * @ignore
 */
define("NO_CACHE", true);

/** get the database library based on the current database type */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php"; 

/** caches of web pages are stored in a 
 *  web archive bundle, so we load in its definition 
 */
require_once BASE_DIR."/lib/web_archive_bundle.php"; 

/** get available archive iterators */
foreach(glob(BASE_DIR."/lib/archive_bundle_iterators/*_bundle_iterator.php") 
    as $filename) { 
    require_once $filename;
}

/** To guess language based on page encoding */
require_once BASE_DIR."/lib/locale_functions.php"; 

/** get processors for different file types */
foreach(glob(BASE_DIR."/lib/processors/*_processor.php") as $filename) { 
    require_once $filename;
}

/** get any indexing plugins */
foreach(glob(BASE_DIR."/lib/indexing_plugins/*_plugin.php") as $filename) { 
    require_once $filename;
}

/** Used to manipulate urls*/
require_once BASE_DIR."/lib/url_parser.php";
/** Used to extract summaries from web pages*/
require_once BASE_DIR."/lib/phrase_parser.php";
/** for crawlHash and crawlLog */
require_once BASE_DIR."/lib/utility.php"; 
/** for crawlDaemon function */
require_once BASE_DIR."/lib/crawl_daemon.php"; 
/** Used to fetches web pages and info from queue server*/
require_once BASE_DIR."/lib/fetch_url.php";
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";
/** used to build miniinverted index*/
require_once BASE_DIR."/lib/index_shard.php";

/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/**
 * This class is responsible for syncing crawl archives between machines using
 *  the SeekQuarry/Yioop search engine
 *
 * Syncer periodically queries the queue server asking for 
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @see buildMiniInvertedIndex()
 */
class Syncer implements CrawlConstants
{
    /**
     * Reference to a database object. Used since has directory manipulation
     * functions
     * @var object
     */
    var $db;
    /**
     * Url or IP address of the queue_server to get sites to crawl from
     * @var string
     */
    var $queue_server;

    /**
     * Sets up the field variables so that syncing can begin
     *
     * @param string $queue_server URL or IP address of the queue server
     */
    function __construct($queue_server) 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();

        $this->queue_server = $queue_server;
    }

    /**
     *  This is the function that should be called to get the syncer to start 
     *  syncing. Calls init to handle the command line arguments then enters 
     *  the syncer's main loop
     */
    function start()
    {
        global $argv;

        // To use CrawlDaemon need to declare ticks first
        declare(ticks=200);
        CrawlDaemon::init($argv, "syncer");
        crawlLog("\n\nInitialize logger..", "fetcher");
        $this->loop();
    }

    /**
     * Main loop for the fetcher.
     *
     * Checks for stop message, checks queue server if crawl has changed and
     * for new pages to crawl. Loop gets a group of next pages to crawl if 
     * there are pages left to crawl (otherwise sleep 5 seconds). It downloads
     * these pages, deplicates them, and updates the found site info with the 
     * result before looping again.
     */
    function loop()
    {
        crawlLog("In Sync Loop");

        $info[self::STATUS] = self::CONTINUE_STATE;
        
        while ($info[self::STATUS] != self::STOP_STATE) {
            $syncer_message_file = CRAWL_DIR.
                "/schedules/syncer_messages.txt";
            if(file_exists($syncer_message_file)) {
                $info = unserialize(file_get_contents($syncer_message_file));
                unlink($syncer_message_file);
                if(isset($info[self::STATUS]) && 
                    $info[self::STATUS] == self::STOP_STATE) {continue;}
            }

            $info = $this->checkScheduler();

            if($info === false) {
                crawlLog("Cannot connect to queue server...".
                    " will try again in ".SYNC_SLEEP_TIME." seconds.");
                sleep(SYNC_SLEEP_TIME);
                continue;
            }

            if(!isset($info[self::STATUS])) {
                if($info === true) {$info = array();}
                $info[self::STATUS] = self::CONTINUE_STATE;
            }

            if($info[self::STATUS] == self::NO_DATA_STATE) {
                crawlLog("No data from queue server. Sleeping...");
                sleep(FETCH_SLEEP_TIME);
                continue;
            }

        } //end while

        crawlLog("Syncer shutting down!!");
    }

    /**
     * Get status, current crawl, crawl order, and new site information from 
     * the queue_server.
     *
     * @return mixed array or bool. If we are doing
     *      a web crawl and we still have pages to crawl then true, if the
     *      schedulaer page fails to download then false, otherwise, returns
     *      an array of info from the scheduler.
     */
    function checkScheduler() 
    {

        $info = array();

        $queue_server = $this->queue_server;

        $start_time = microtime();
        $time = time();
        $session = md5($time . AUTH_KEY);

        $request =  
            $queue_server."?c=sync&a=schedule&time=$time&session=$session".
            "&robot_instance=".$prefix.ROBOT_INSTANCE."&machine_uri=".WEB_URI.
            "&crawl_time=".$this->crawl_time;

        $info_string = FetchUrl::getPage($request);
        if($info_string === false) {
            return false;
        }
        $info_string = trim($info_string);

        $tok = strtok($info_string, "\n");
        $info = unserialize(base64_decode($tok));

        $this->setCrawlParamsFromArray($info);

        if(isset($info[self::SITES])) {
            $tok = strtok("\n"); //skip meta info
            $this->to_crawl = array();
            while($tok !== false) {
                $string = base64_decode($tok);
                $weight = unpackFloat(substr($string, 0 , 4));
                $delay = unpackInt(substr($string, 4 , 4));
                $url = substr($string, 8);
                $this->to_crawl[] = array($url, $weight, $delay);
                $tok = strtok("\n");
            }
            $dir = CRAWL_DIR."/schedules";
            file_put_contents("$dir/$prefix".
                self::fetch_batch_name."{$this->crawl_time}.txt",
                serialize($this->to_crawl));
            $this->db->setWorldPermissionsRecursive("$dir/$prefix".
                self::fetch_batch_name."{$this->crawl_time}.txt");
            unset($info[self::SITES]);
            file_put_contents("$dir/$prefix".
                self::fetch_crawl_info."{$this->crawl_time}.txt",
                serialize($info));
        }

        crawlLog("  Time to check Scheduler ".(changeInMicrotime($start_time)));

        return $info; 
    }

}

/*
 *  Instantiate and runs the Fetcher
 */
$syncer =  new Fetcher(QUEUE_SERVER);
$syncer->start();

?>
