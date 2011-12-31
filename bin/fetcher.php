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
 * This class is responsible for fetching web pages for the 
 * SeekQuarry/Yioop search engine
 *
 * Fetcher periodically queries the queue server asking for web pages to fetch. 
 * It gets at most MAX_FETCH_SIZE many web pages from the queue_server in one 
 * go. It then fetches these  pages. Pages are fetched in batches of 
 * NUM_MULTI_CURL_PAGES many pages. Each SEEN_URLS_BEFORE_UPDATE_SCHEDULER many 
 * downloaded pages (not including robot pages), the fetcher sends summaries 
 * back to the machine on which the queue_server lives. It does this by making a
 * request of the web server on that machine and POSTs the data to the 
 * yioop web app. This data is handled by the FetchController class. The 
 * summary data can include up to four things: (1) robot.txt data, (2) summaries
 * of each web page downloaded in the batch, (3), a list of future urls to add 
 * to the to-crawl queue, and (4) a partial inverted index saying for each word 
 * that occurred in the current SEEN_URLS_BEFORE_UPDATE_SCHEDULER documents 
 * batch, what documents it occurred in. The inverted index also associates to 
 * each word document pair several scores. More information on these scores can 
 * be found in the documentation for {@link buildMiniInvertedIndex()}
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @see buildMiniInvertedIndex()
 */
class Fetcher implements CrawlConstants
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
     * An associative array of (mimetype => name of processor class to handle)
     * pairs.
     * @var array
     */
    var $page_processors;

    /**
     * An associative array of (page processor => array of
     * indexing plugin name associated with the page processor). It is used
     * to determine after a page is processed which plugins'
     * pageProcessing($page, $url) method should be called
     * @var array
     */
    var $plugin_processors;

    /**
     * Holds an array of word -> url patterns which are used to 
     * add meta words to the words that are extracted from any given doc
     * @var array
     */
    var $meta_words;
    /**
     * WebArchiveBundle  used to store complete web pages and auxiliary data
     * @var object
     */
    var $web_archive;
    /**
     * Timestamp of the current crawl
     * @var int
     */
    var $crawl_time;
    /**
     * Contains the list of web pages to crawl from the queue_server
     * @var array
     */
    var $to_crawl;
    /**
     * Contains the list of web pages to crawl that failed on first attempt
     * (we give them one more try before bailing on them)
     * @var array
     */
    var $to_crawl_again;
    /**
     * Summary information for visited sites that the fetcher hasn't sent to 
     * the queue_server yet
     * @var array
     */
    var $found_sites;
    /**
     * Timestamp from the queue_server of the current schedule of sites to
     * download. This is sent back to the server once this schedule is completed
     * to help the queue server implement crawl-delay if needed.
     * @var int
     */
    var $schedule_time;
    /**
     * The sum of the number of words of all the page description for the current
     * crawl. This is used in computing document statistics.
     * @var int
     */
    var $sum_seen_site_description_length;
    /**
     * The sum of the number of words of all the page titles for the current
     * crawl. This is used in computing document statistics.
     * @var int
     */
    var $sum_seen_title_length;
    /**
     * The sum of the number of words in all the page links for the current
     * crawl. This is used in computing document statistics.
     * @var int
     */
    var $sum_seen_site_link_length;
    /**
     * Number of sites crawled in the current crawl
     * @var int
     */
    var $num_seen_sites;
    /**
     * Stores the name of the ordering used to crawl pages. This is used in a
     * switch/case when computing weights of urls to be crawled before sending
     * these new urls back to the queue_server.
     * @var string
     */
    var $crawl_order;

    /**
     * Indicates the kind of crawl being performed: self::WEB_CRAWL indicates
     * a new crawl of the web; self::ARCHIVE_CRAWL indicates a crawl of an 
     * existing web archive
     * @var string
     */
    var $crawl_type;

    /**
     * Maximum number of bytes to download of a webpage
     * @var int
     */
    var $page_range_request;

    /**
     * If self::ARCHIVE_CRAWL is being down, then this field holds the iterator
     * object used to iterate over the archive
     * @var object
     */
    var $archive_iterator;

    /**
     * Keeps track of whether during the recrawl we should notify the 
     * queue_server scheduler about our progress in mini-indexing documents
     * in the archive
     * @var bool
     */
    var $recrawl_check_scheduler;

    /**
     * If the crawl_type is self::ARCHIVE_CRAWL, then crawl_index is the 
     * timestamp of the existing archive to crawl
     * @var string
     */
    var $crawl_index;

    /**
     * Which fetcher instance we are (if fetcher run as a job and more that one)
     * @var string
     */
     var $fetcher_num;

    /**
     * Sets up the field variables for that crawling can begin
     *
     * @param array $page_processors (mimetype => name of processor) pairs
     * @param string $queue_server URL or IP address of the queue server
     * @param int $page_range_request maximum number of bytes to download from
     *      a webpage; <=0 -- unlimited
     */
    function __construct($page_processors, $queue_server,
        $page_range_request) 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();

        $this->queue_server = $queue_server;
        $this->page_processors = $page_processors;
        $this->meta_words = array();

        $this->web_archive = NULL;
        $this->crawl_time = NULL;
        $this->schedule_time = NULL;

        $this->crawl_type = self::WEB_CRAWL;
        $this->crawl_index = NULL;
        $this->recrawl_check_scheduler = false;

        $this->to_crawl = array();
        $this->to_crawl_again = array();
        $this->found_sites = array();
        $this->page_range_request = $page_range_request;
        $this->fetcher_num = false;

        $this->sum_seen_title_length = 0;
        $this->sum_seen_description_length = 0;
        $this->sum_seen_site_link_length = 0;
        $this->num_seen_sites = 0;
       
        //we will get the correct crawl order from the queue_server
        $this->crawl_order = self::PAGE_IMPORTANCE;
    }

    /**
     *  This is the function that should be called to get the fetcher to start 
     *  fetching. Calls init to handle the command line arguments then enters 
     *  the fetcher's main loop
     */
    function start()
    {
        global $argv;

        // To use CrawlDaemon need to declare ticks first
        declare(ticks=200);
        CrawlDaemon::init($argv, "fetcher");
        if(isset($argv[2]) ) {
            $this->fetcher_num = intval($argv[2]);
            crawlLog("\n\nInitialize logger..", $this->fetcher_num."-fetcher");
            
        } else {
            crawlLog("\n\nInitialize logger..", "fetcher");
        }

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
        crawlLog("In Fetch Loop");

        $prefix = "";
        if($this->fetcher_num !== false) {
            $prefix = $this->fetcher_num."-";
        }
        if(!file_exists(CRAWL_DIR."/{$prefix}temp")) {
            mkdir(CRAWL_DIR."/{$prefix}temp");
        }
        $info[self::STATUS] = self::CONTINUE_STATE;
        
        while ($info[self::STATUS] != self::STOP_STATE) {
            $fetcher_message_file = CRAWL_DIR.
                "/schedules/{$prefix}fetcher_messages.txt";
            if(file_exists($fetcher_message_file)) {
                $info = unserialize(file_get_contents($fetcher_message_file));
                unlink($fetcher_message_file);
                if(isset($info[self::STATUS]) && 
                    $info[self::STATUS] == self::STOP_STATE) {continue;}
            }

            $switch_to_old_fetch = $this->checkCrawlTime();
            if($switch_to_old_fetch) {
                $info[self::CRAWL_TIME] = $this->crawl_time;
                if($info[self::CRAWL_TIME] == 0) {
                    $info[self::STATUS] =self::NO_DATA_STATE;
                }
            } else {
                $info = $this->checkScheduler();
            }

            if($info === false) {
                crawlLog("Cannot connect to queue server...".
                    " will try again in ".FETCH_SLEEP_TIME." seconds.");
                sleep(FETCH_SLEEP_TIME);
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

            $tmp_base_name = (isset($info[self::CRAWL_TIME])) ? 
                CRAWL_DIR."/cache/{$prefix}" . self::archive_base_name .
                    $info[self::CRAWL_TIME] : "";
            if(isset($info[self::CRAWL_TIME]) && ($this->web_archive == NULL || 
                    $this->web_archive->dir_name != $tmp_base_name)) {
                if(isset($this->web_archive->dir_name)) {
                    crawlLog("Old name: ".$this->web_archive->dir_name);
                }
                if(is_object($this->web_archive)) {
                    $this->web_archive = NULL;
                }
                $this->to_crawl_again = array();
                $this->found_sites = array();

                gc_collect_cycles();
                $this->web_archive = new WebArchiveBundle($tmp_base_name, 
                    false);
                $this->crawl_time = $info[self::CRAWL_TIME];
                $this->sum_seen_title_length = 0;
                $this->sum_seen_description_length = 0;
                $this->sum_seen_site_link_length = 0;
                $this->num_seen_sites = 0;

                crawlLog("New name: ".$this->web_archive->dir_name);
                crawlLog("Switching archive...");

            }

            if(isset($info[self::SAVED_CRAWL_TIMES])) {
                $this->deleteOldCrawls($info[self::SAVED_CRAWL_TIMES]);
            }

            switch($this->crawl_type)
            {
                case self::WEB_CRAWL:
                    $downloaded_pages =  $this->downloadPagesWebCrawl();
                break;

                case self::ARCHIVE_CRAWL:
                    $downloaded_pages =  $this->downloadPagesArchiveCrawl();
                break;
            }

            $start_time = microtime();

            $summarized_site_pages = 
                $this->processFetchPages($downloaded_pages);

            crawlLog("Number summarize pages".count($summarized_site_pages));

            $this->updateFoundSites($summarized_site_pages);

            $sleep_time = max(0, ceil(
                MINIMUM_FETCH_LOOP_TIME - changeInMicrotime($start_time)));
            if($sleep_time > 0) {
                crawlLog("Ensure minimum loop time by sleeping...".$sleep_time);
                sleep($sleep_time);
            }
        } //end while

        crawlLog("Fetcher shutting down!!");
    }

    /**
     * Get a list of urls from the current fetch batch provided by the queue 
     * server. Then downloads these pages. Finally, reschedules, if 
     * possible, pages that did not successfully get downloaded.
     *
     * @return array an associative array of web pages and meta data 
     *  fetched from the internet
     */
    function downloadPagesWebCrawl()
    {
        $start_time = microtime();
        $can_schedule_again = false;
        if(count($this->to_crawl) > 0)  {
            $can_schedule_again = true;
        }
        $sites = $this->getFetchSites();

        if(!$sites) {
            crawlLog("No seeds to fetch...");
            sleep(max(0, ceil(
                MINIMUM_FETCH_LOOP_TIME - changeInMicrotime($start_time))));
            return array();
        }
        
        $prefix = "";
        if($this->fetcher_num !== false) {
            $prefix = $this->fetcher_num."-";
        }
        $tmp_dir = CRAWL_DIR."/{$prefix}temp";
        $site_pages = FetchUrl::getPages($sites, true, 
            $this->page_range_request, $tmp_dir
            );

        list($downloaded_pages, $schedule_again_pages) = 
            $this->reschedulePages($site_pages);

        if($can_schedule_again == true) {
            //only schedule to crawl again on fail sites without crawl-delay
            crawlLog("  Scheduling again..");
            foreach($schedule_again_pages as $schedule_again_page) {
                if(isset($schedule_again_page[self::CRAWL_DELAY]) && 
                    $schedule_again_page[self::CRAWL_DELAY] == 0) {
                    $this->to_crawl_again[] = 
                        array($schedule_again_page[self::URL], 
                            $schedule_again_page[self::WEIGHT],
                            $schedule_again_page[self::CRAWL_DELAY]
                        );
                }
            }
            crawlLog("....done.");
        }

        return $downloaded_pages;
    }

    /**
     * Extracts NUM_MULTI_CURL_PAGES from the cureen Archive Bundle that is 
     * being recrawled.
     *
     * @return array an associative array of web pages and meta data from
     *      the archive bundle being iterated over
     */
    function downloadPagesArchiveCrawl()
    {
        $prefix = "";
        if($this->fetcher_num !== false) {
            $prefix = $this->fetcher_num."-";
        }
        $base_name = CRAWL_DIR.'/cache/{$prefix}'.self::archive_base_name.
            $this->crawl_index;
        $pages = array();
        if(!isset($this->archive_iterator->iterate_timestamp) || 
            $this->archive_iterator->iterate_timestamp != $this->crawl_index ||
            $this->archive_iterator->result_timestamp != $this->crawl_time) {
            if(!file_exists($base_name)){
                crawlLog("Recrawl archive with timestamp" .
                    " {$this->crawl_index} does not exist!");
                return $pages;
            } else {
                if(file_exists("$base_name/arc_type.txt")) {
                    $arctype = trim(file_get_contents(
                        "$base_name/arc_type.txt"));
                } else {
                    $arctype = "WebArchiveBundle";
                }
                $iterator_name = $arctype."Iterator";
                $this->archive_iterator = 
                    new $iterator_name($this->crawl_index, $this->crawl_time);
                if($this->archive_iterator == NULL) {
                    crawlLog("Error creating archive iterator!!");
                    return $pages;
                }
            }
        }
        if(!$this->archive_iterator->end_of_iterator) {
            $pages = $this->archive_iterator->nextPages(NUM_MULTI_CURL_PAGES);
        } 
        return $pages;
    }

    /**
     * Deletes any crawl web archive bundles not in the provided array of crawls
     *
     * @param array $still_active_crawls those crawls which should not 
     *  be deleted, so all others will be deleted
     * @see loop()
     */
    function deleteOldCrawls(&$still_active_crawls)
    {
        $prefix = "";
        if($this->fetcher_num !== false) {
            $prefix = $this->fetcher_num."-";
        }
        $dirs = glob(CRAWL_DIR.'/cache/*', GLOB_ONLYDIR);

        $full_base_name = $prefix . self::archive_base_name; 
        foreach($dirs as $dir) {
            if(strlen(
                $pre_timestamp = strstr($dir, $full_base_name)) > 0) {
                $time = substr($pre_timestamp, 
                    strlen($full_base_name));
                if(!in_array($time, $still_active_crawls) ){
                    $this->db->unlinkRecursive($dir);
                }
            }
        }
        $files = glob(CRAWL_DIR.'/schedules/*');
        $names = array(self::fetch_batch_name, self::fetch_crawl_info);
        foreach($files as $file) {
            $timestamp = "";
            foreach($names as $name) {
                $full_name = $prefix. $name;
                if(strlen(
                    $pre_timestamp = strstr($file, $full_name)) > 0) {
                    $timestamp =  substr($pre_timestamp, 
                        strlen($full_name), 10);
                    break;
                }
            }

            if($timestamp !== "" && !in_array($timestamp,$still_active_crawls)){
                unlink($file);
            }
        }
    }

    /**
     * Makes a request of the queue server machine to get the timestamp of the 
     * currently running crawl to see if it changed
     *
     * If the timestamp has changed save the rest of the current fetch batch,
     * then load any existing fetch from the new crawl otherwise set to crawl
     * to empty
     *
     * @return bool true if loaded a fetch batch due to time change
     */
   function checkCrawlTime()
    {
        $queue_server = $this->queue_server;

        $start_time = microtime();
        $time = time();
        $session = md5($time . AUTH_KEY);

        $prefix = "";
        if($this->fetcher_num !== false) {
            $prefix = $this->fetcher_num."-";
        }

        /* if just restarted, check to make sure the crawl hasn't changed, 
           if it has bail
        */
        $robot_instance = $prefix . ROBOT_INSTANCE;
        $request =  
            $queue_server."?c=fetch&a=crawlTime&time=$time&session=$session".
            "&robot_instance=".$robot_instance."&machine_uri=".WEB_URI;

        $info_string = FetchUrl::getPage($request);
        $info = @unserialize(trim($info_string));
        if(isset($info[self::CRAWL_TIME]) 
            && ($info[self::CRAWL_TIME] != $this->crawl_time
            || $info[self::CRAWL_TIME] == 0)) {
            $dir = CRAWL_DIR."/schedules";
            /*
               Zero out the crawl. If haven't done crawl before scheduler
               will be called
             */
            $this->to_crawl = array(); 
            $this->to_crawl_again = array();
            $this->found_sites = array();
            if($this->crawl_time > 0) {
                file_put_contents("$dir/$prefix".self::fetch_closed_name.
                    "{$this->crawl_time}.txt", "1");
            }
            $this->crawl_time = $info[self::CRAWL_TIME];
            //load any batch that might exist for changed-to crawl
            if(file_exists("$dir/$prefix".self::fetch_crawl_info.
                "{$this->crawl_time}.txt") && file_exists(
                "$dir/$prefix".self::fetch_batch_name.
                    "{$this->crawl_time}.txt")) {
                $info = unserialize(file_get_contents(
                    "$dir/$prefix".self::fetch_crawl_info.
                        "{$this->crawl_time}.txt"));
                $this->setCrawlParamsFromArray($info);
                unlink("$dir/$prefix".self::fetch_crawl_info.
                    "{$this->crawl_time}.txt");
                $this->to_crawl = unserialize(file_get_contents(
                    "$dir/$prefix".
                        self::fetch_batch_name."{$this->crawl_time}.txt"));
                unlink("$dir/$prefix".self::fetch_batch_name.
                    "{$this->crawl_time}.txt");
                if(file_exists("$dir/$prefix".self::fetch_closed_name.
                    "{$this->crawl_time}.txt")) {
                    unlink("$dir/$prefix".self::fetch_closed_name.
                    "{$this->crawl_time}.txt");
                } else {
                    $update_num = SEEN_URLS_BEFORE_UPDATE_SCHEDULER;
                    crawlLog("Fetch on crawl {$this->crawl_time} was not ".
                        "halted properly, dumping $update_num from old fetch ".
                        "to try to make a clean re-start");
                    $count = count($this->to_crawl);
                    if($count > SEEN_URLS_BEFORE_UPDATE_SCHEDULER) {
                        $this->to_crawl = array_slice($this->to_crawl,
                            SEEN_URLS_BEFORE_UPDATE_SCHEDULER);
                    } else {
                        $this->to_crawl = array();
                    }
                }
            }
        }

        return (count($this->to_crawl) > 0);
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
        $prefix = "";
        if($this->fetcher_num !== false) {
            $prefix = $this->fetcher_num."-";
        }

        $info = array();
        if((count($this->to_crawl) > 0 || count($this->to_crawl_again) > 0) &&
           (!$this->recrawl_check_scheduler)) {
            $info[self::STATUS]  = self::CONTINUE_STATE;
            return true; 
        }

        $this->recrawl_check_scheduler = false;
        $queue_server = $this->queue_server;

        $start_time = microtime();
        $time = time();
        $session = md5($time . AUTH_KEY);

        $request =  
            $queue_server."?c=fetch&a=schedule&time=$time&session=$session".
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

    /**
     * Sets parameters for fetching based on provided info struct
     * ($info typically would come from the queue server)
     *
     * @param array &$info struct with info about the kind of crawl, timestamp
     *  of index, crawl order, etc.
     */
    function setCrawlParamsFromArray(&$info)
    {
        if(isset($info[self::CRAWL_TYPE])) {
            $this->crawl_type = $info[self::CRAWL_TYPE];
        }
        if(isset($info[self::CRAWL_INDEX])) {
            $this->crawl_index = $info[self::CRAWL_INDEX];
        }
        if(isset($info[self::CRAWL_ORDER])) {
            $this->crawl_order = $info[self::CRAWL_ORDER];
        }
        if(isset($info[self::META_WORDS])) {
            $this->meta_words = $info[self::META_WORDS];
        }
        if(isset($info[self::INDEXING_PLUGINS])) {
            foreach($info[self::INDEXING_PLUGINS] as $plugin) {
                $plugin_name = $plugin."Plugin";
                $processors = $plugin_name::getProcessors();
                foreach($processors as $processor) {
                    $this->plugin_processors[$processor][] = $plugin_name;
                }
            }
        }
        if(isset($info[self::SCHEDULE_TIME])) {
              $this->schedule_time = $info[self::SCHEDULE_TIME];
        }

        if(isset($info[self::PAGE_RANGE_REQUEST])) {
            $this->page_range_request = $info[self::PAGE_RANGE_REQUEST];
        }
    }

    /**
     * Prepare an array of up to NUM_MULTI_CURL_PAGES' worth of sites to be
     * downloaded in one go using the to_crawl array. Delete these sites 
     * from the to_crawl array.
     *
     * @return array sites which are ready to be downloaded
     */
    function getFetchSites() 
    {

        $web_archive = $this->web_archive;

        $start_time = microtime();

        $seeds = array();
        $delete_indices = array();
        $num_items = count($this->to_crawl);
        if($num_items > 0) {
            $crawl_source = & $this->to_crawl;
            $to_crawl_flag = true;
        } else {
            crawlLog("...Trying to crawl sites which failed the first time");
            $num_items = count($this->to_crawl_again);
            $crawl_source = & $this->to_crawl_again;
            $to_crawl_flag = false;
        }
        reset($crawl_source);

        if($num_items > NUM_MULTI_CURL_PAGES) {
            $num_items = NUM_MULTI_CURL_PAGES;
        }

        $i = 0;
        $site_pair = each($crawl_source);

        while ($site_pair !== false && $i < $num_items) {

            $delete_indices[] = $site_pair['key'];
            if($site_pair['value'][0] != self::DUMMY) {
                $seeds[$i][self::URL] = $site_pair['value'][0];
                $seeds[$i][self::WEIGHT] = $site_pair['value'][1];
                $seeds[$i][self::CRAWL_DELAY] = $site_pair['value'][2];
                /*
                  Crawl delay is only used in scheduling on the queue_server
                  on the fetcher, we only use crawl-delay to determine
                  if we will give a page a second try if it doesn't
                  download the first time
                */
                
                if(UrlParser::getDocumentFilename($seeds[$i][self::URL]).
                    ".".UrlParser::getDocumentType($seeds[$i][self::URL]) 
                    == "robots.txt") {
                    $seeds[$i][self::ROBOT_PATHS] = array();
                }
                $i++;
            } else {
                break;
            }
            $site_pair = each($crawl_source);
        } //end while

        foreach($delete_indices as $delete_index) {
            if($to_crawl_flag == true) {
                unset($this->to_crawl[$delete_index]);
            } else {
                unset($this->to_crawl_again[$delete_index]);
            }
        }

        crawlLog("  Fetch Seed Time ".(changeInMicrotime($start_time)));

        return $seeds;
    }

    /**
     * Sorts out pages
     * for which no content was downloaded so that they can be scheduled
     * to be crawled again.
     *
     * @param array &$site_pages pages to sort
     * @return an array conisting of two array downloaded pages and 
     *  not downloaded pages.
     */
    function reschedulePages(&$site_pages)
    {
        $start_time = microtime();

        $downloaded = array();
        $not_downloaded = array();

        foreach($site_pages as $site) {
            if( isset($site[self::ROBOT_PATHS]) || isset($site[self::PAGE])) {
                $downloaded[] = $site;
            }  else {
                $not_downloaded[] = $site;
            } 
        }
        crawlLog("  Sort downloaded/not downloaded".
            (changeInMicrotime($start_time)));

        return array($downloaded, $not_downloaded);
    }

    /**
     * Processes an array of downloaded web pages with the appropriate page
     * processor.
     *
     * Summary data is extracted from each non robots.txt file in the array.
     * Disallowed paths and crawl-delays are extracted from robots.txt files.
     *
     * @param array $site_pages a collection of web pages to process
     * @return array summary data extracted from these pages
     */
    function processFetchPages($site_pages)
    {
        $PAGE_PROCESSORS = $this->page_processors;
        crawlLog("  Start process pages...");
        $start_time = microtime();

        $prefix = "";
        if($this->fetcher_num !== false) {
            $prefix = $this->fetcher_num."-";
        }

        $stored_site_pages = array();
        $summarized_site_pages = array();

        $num_items = $this->web_archive->count;

        $i = 0;

        foreach($site_pages as $site) {
            $response_code = $site[self::HTTP_CODE]; 
            //deals with short URLs and directs them to the original link
            if(isset($site[self::LOCATION]) && 
                count($site[self::LOCATION]) > 0) {
                array_unshift($site[self::LOCATION], $site[self::URL]);
                $tmp_loc = array_pop($site[self::LOCATION]);
                $tmp_loc = UrlParser::canonicalLink(
                    $tmp_loc, $site[self::URL]);
                $tmp_host = UrlParser::getHost($tmp_loc)."/";
                if($tmp_host != $site[self::URL]) {
                    $site[self::URL] = $tmp_loc;
                }
            }

            //process robot.txt files separately
            if(isset($site[self::ROBOT_PATHS])) {
                if($response_code >= 200 && $response_code < 300) {
                    $site = $this->processRobotPage($site);
                }
                $site[self::GOT_ROBOT_TXT] = true;
                $site[self::HASH] = FetchUrl::computePageHash(
                    $site[self::PAGE]);
                $stored_site_pages[$i] = $site;
                $summarized_site_pages[$i] = $site;
                $i++;
                continue;
            }

            if($response_code < 200 || $response_code >= 300) {
                crawlLog($site[self::URL]." response code $response_code");

                /* we print out errors to std output. We still go ahead and
                   process the page. Maybe it is a cool error page, also
                   this makes sure we don't crawl it again 
                */
            }

            $type =  $site[self::TYPE];

            if(isset($PAGE_PROCESSORS[$type])) { 
                $page_processor = $PAGE_PROCESSORS[$type];
                if($page_processor == "TextProcessor" ||
                    get_parent_class($page_processor) == "TextProcessor") {
                    $text_data =true;
                } else {
                    $text_data =false;
                }
                    
            } else {
                continue;
            }
            if(isset($this->plugin_processors[$page_processor])) {
                $processor = new $page_processor(
                    $this->plugin_processors[$page_processor]);
            } else {
                $processor = new $page_processor();
            }

            if(isset($site[self::PAGE])) {
                if(!isset($site[self::ENCODING])) {
                    $site[self::ENCODING] = "UTF-8";
                }
                //if not UTF-8 convert before doing anything else
                if(isset($site[self::ENCODING]) && 
                    $site[self::ENCODING] != "UTF-8" && 
                    $site[self::ENCODING] != "" &&
                    ($page_processor == "TextProcessor" ||
                    is_subclass_of($page_processor, "TextProcessor"))) {
                    if(!@mb_check_encoding($site[self::PAGE], 
                        $site[self::ENCODING])) {
                        crawlLog("  NOT VALID ENCODING DETECTED!!");
                    }
                    crawlLog("  Converting from encoding ".
                        $site[self::ENCODING]."...");
                    //if HEBREW WINDOWS-1255 use ISO-8859 instead
                    if(stristr($site[self::ENCODING], "1255")) {
                        $site[self::ENCODING]= "ISO-8859-8";
                    }
                    $site[self::PAGE] = @mb_convert_encoding($site[self::PAGE],
                        "UTF-8", $site[self::ENCODING]);
                }
                crawlLog("  Using Processor...".$page_processor);
                $doc_info = $processor->handle($site[self::PAGE], 
                    $site[self::URL]);
            } else {
                $doc_info = false;
            }

            $not_loc = true;
            if($doc_info) {
                $site[self::DOC_INFO] =  $doc_info;
                if(isset($doc_info[self::LOCATION])) {
                    $site[self::HASH] = crawlHash(
                        crawlHash($site[self::URL], true). "LOCATION", true);
                        $not_loc = false;
                } 
                $site[self::ROBOT_INSTANCE] = $prefix.ROBOT_INSTANCE;

                if(!is_dir(CRAWL_DIR."/cache")) {
                    mkdir(CRAWL_DIR."/cache");
                    $htaccess = "Options None\nphp_flag engine off\n";
                    file_put_contents(CRAWL_DIR."/cache/.htaccess", 
                        $htaccess);
                }

                if($text_data) {
                    if(isset($doc_info[self::PAGE])) {
                        $site[self::PAGE] = $doc_info[self::PAGE];
                    } else {
                        $site[self::PAGE] = NULL;
                    }
                    if($not_loc) {
                        $content = 
                            $doc_info[self::DESCRIPTION];
                        $site[self::HASH] = FetchUrl::computePageHash(
                            $content);
                    }
                } else {
                    $site[self::HASH] = FetchUrl::computePageHash(
                        $site[self::PAGE]);
                }

                $this->copySiteFields($i, $site, $summarized_site_pages, 
                    $stored_site_pages);

                $summarized_site_pages[$i][self::URL] = 
                    strip_tags($site[self::URL]);
                $summarized_site_pages[$i][self::TITLE] = strip_tags(
                    $site[self::DOC_INFO][self::TITLE]); 
                    // stripping html to be on the safe side
                $summarized_site_pages[$i][self::DESCRIPTION] = 
                    strip_tags($site[self::DOC_INFO][self::DESCRIPTION]);
                if(isset($site[self::DOC_INFO][self::JUST_METAS])) {
                    $summarized_site_pages[$i][self::JUST_METAS] = true;
                }
                if(isset($site[self::DOC_INFO][self::LANG])) {
                    if($site[self::DOC_INFO][self::LANG] == 'en' &&
                        $site[self::ENCODING] != "UTF-8") {
                        $site[self::DOC_INFO][self::LANG] =
                            guessLangEncoding($site[self::ENCODING]);
                    }
                    $summarized_site_pages[$i][self::LANG] = 
                        $site[self::DOC_INFO][self::LANG];
                }
                if(isset($site[self::DOC_INFO][self::LINKS])) {
                    $summarized_site_pages[$i][self::LINKS] = 
                        $site[self::DOC_INFO][self::LINKS];
                }

                if(isset($site[self::DOC_INFO][self::THUMB])) {
                    $summarized_site_pages[$i][self::THUMB] = 
                        $site[self::DOC_INFO][self::THUMB];
                }

                if(isset($site[self::DOC_INFO][self::SUBDOCS])) {
                    $this->processSubdocs($i, $site, $summarized_site_pages,
                       $stored_site_pages);
                }
                $i++;
            }
        } // end for
        $cache_page_partition = $this->web_archive->addPages(
            self::OFFSET, $stored_site_pages);

        $num_pages = count($stored_site_pages);

        for($i = 0; $i < $num_pages; $i++) {
            $summarized_site_pages[$i][self::INDEX] = $num_items + $i;
            if(isset($stored_site_pages[$i][self::OFFSET])) {
                $summarized_site_pages[$i][self::OFFSET] = 
                    $stored_site_pages[$i][self::OFFSET];
                $summarized_site_pages[$i][self::CACHE_PAGE_PARTITION] = 
                    $cache_page_partition;
            }
        }
        crawlLog("  Process pages time".(changeInMicrotime($start_time)));

        return $summarized_site_pages;
    }

    /**
     * Copies fields from the array of site data to the $i indexed 
     * element of the $summarized_site_pages and $stored_site_pages array
     *
     * @param int &$i index to copy to
     * @param array &$site web page info to copy
     * @param array &$summarized_site_pages array of summaries of web pages
     * @param array &$stored_site_pages array of cache info of web pages
     */
    function copySiteFields(&$i, &$site,
        &$summarized_site_pages, &$stored_site_pages)
    {
        $stored_fields = array(self::URL, self::HEADER, self::PAGE);
        $summary_fields = array(self::IP_ADDRESSES, self::WEIGHT,
            self::TIMESTAMP, self::TYPE, self::ENCODING, self::HTTP_CODE,
            self::HASH, self::SERVER, self::SERVER_VERSION,
            self::OPERATING_SYSTEM, self::MODIFIED, self::ROBOT_INSTANCE,
            self::LOCATION);

        foreach($summary_fields as $field) {
            if(isset($site[$field])) {
                $stored_site_pages[$i][$field] = $site[$field];
                $summarized_site_pages[$i][$field] = $site[$field];
            }
        }
        foreach($stored_fields as $field) {
            if(isset($site[$field])) {
                $stored_site_pages[$i][$field] = $site[$field];
            }
        }
    }

    /**
     * The pageProcessing method of an IndexingPlugin generates 
     * a self::SUBDOCS array of additional "micro-documents" that
     * might have been in the page. This methods adds these
     * documents to the summaried_size_pages and stored_site_pages
     * arrays constructed during the execution of processFetchPages()
     *
     * @param int &$i index to begin adding subdocs at
     * @param array &$site web page that subdocs were from and from 
     *      which some subdoc summary info is copied
     * @param array &$summarized_site_pages array of summaries of web pages
     * @param array &$stored_site_pages array of cache info of web pages
     */
    function processSubdocs(&$i, &$site,
        &$summarized_site_pages, &$stored_site_pages)
    {
        $subdocs = $site[self::DOC_INFO][self::SUBDOCS];
        foreach($subdocs as $subdoc) {
            $i++;
            
            $this->copySiteFields($i, $site, $summarized_site_pages, 
                $stored_site_pages);

            $summarized_site_pages[$i][self::URL] = 
                strip_tags($site[self::URL]);

            $summarized_site_pages[$i][self::TITLE] = 
                strip_tags($subdoc[self::TITLE]); 

            $summarized_site_pages[$i][self::DESCRIPTION] = 
                strip_tags($subdoc[self::DESCRIPTION]);

            if(isset($site[self::JUST_METAS])) {
                $summarized_site_pages[$i][self::JUST_METAS] = true;
            }
            
            if(isset($subdoc[self::LANG])) {
                $summarized_site_pages[$i][self::LANG] = 
                    $subdoc[self::LANG];
            }

            if(isset($subdoc[self::LINKS])) {
                $summarized_site_pages[$i][self::LINKS] = 
                    $subdoc[self::LINKS];
            }

            if(isset($subdoc[self::SUBDOCTYPE])) {
                $summarized_site_pages[$i][self::SUBDOCTYPE] =
                    $subdoc[self::SUBDOCTYPE];
            }
        }
    }


    /**
     * Parses the contents of a robots.txt page extracting disallowed paths and
     * Crawl-delay
     *
     * @param array $robot_site array containing info about one robots.txt page
     * @return array the $robot_site array with two new fields: one containing
     *      an array of disallowed paths, the other containing the crawl-delay
     *      if any
     */
    function processRobotPage($robot_site)
    {
        $web_archive = $this->web_archive;

        $host_url = UrlParser::getHost($robot_site[self::URL]);

        if(isset($robot_site[self::PAGE])) {
            $robot_page = $robot_site[self::PAGE];
            $lines = explode("\n", $robot_page);

            $add_rule_state = false;
            $rule_added_flag = false;
            $delay_flag = false;

            $robot_rows = array();
            foreach($lines as $line) {
                if(stristr($line, "User-agent") && (stristr($line, ":*") 
                    || stristr($line, " *") || stristr($line, USER_AGENT_SHORT) 
                    || $add_rule_state)) {
                    $add_rule_state = ($add_rule_state) ? false : true;
                }
                
                if($add_rule_state) {
                    if(stristr($line, "Disallow")) {
                        $path = trim(preg_replace('/Disallow\:/i', "", $line));

                        $rule_added_flag = true;

                        if(strlen($path) > 0) {
                        $robot_site[self::ROBOT_PATHS][] = $path; 
                        }
                    }
                    
                    if(stristr($line, "Crawl-delay")) {
                      
                        $delay_string = trim(
                            preg_replace('/Crawl\-delay\:/i', "", $line));
                        $delay_flag = true;
                    }

                }

                if(stristr($line, "Sitemap")) {
                    $tmp_url = UrlParser::canonicalLink(trim(
                        preg_replace('/Sitemap\:/i', "", $line)), 
                        $host_url);
                    if(!UrlParser::checkRecursiveUrl($tmp_url) 
                        && strlen($tmp_url) < MAX_URL_LENGTH) {
                        $robot_site[self::LINKS][] = $tmp_url;
                    }
                }
            }
            
            if($delay_flag) {
                $delay = intval($delay_string);
                if($delay > MAXIMUM_CRAWL_DELAY)  {
                    $robot_site[self::ROBOT_PATHS][] = "/";
                } else {
                    $robot_site[self::CRAWL_DELAY] = $delay;
                }
            }
        }
        return $robot_site;
    
    }
    
    /**
     * Updates the $this->found_sites array with data from the most recently
     * downloaded sites. This means updating the following sub arrays:
     * the self::ROBOT_PATHS, self::TO_CRAWL. It checks if there are still
     * more urls to crawl or if self::SEEN_URLS has grown larger than
     * SEEN_URLS_BEFORE_UPDATE_SCHEDULER. If so, a mini index is built and,
     * the queue server is called with the data.
     *
     * @param array $sites site data to use for the update
     */
    function updateFoundSites($sites) 
    {
        $start_time = microtime();


        for($i = 0; $i < count($sites); $i++) {
            $site = $sites[$i];
            if(isset($site[self::ROBOT_PATHS])) {
                $host = UrlParser::getHost($site[self::URL]);
                $this->found_sites[self::ROBOT_TXT][$host][self::PATHS] = 
                    $site[self::ROBOT_PATHS];
                if(isset($site[self::CRAWL_DELAY])) {
                    $this->found_sites[self::ROBOT_TXT][$host][
                        self::CRAWL_DELAY] = $site[self::CRAWL_DELAY];
                }
                if(isset($site[self::LINKS]) 
                    && $this->crawl_type == self::WEB_CRAWL) {
                    $num_links = count($site[self::LINKS]);
                    //robots pages might have sitemaps links on them
                    $this->addToCrawlSites($site[self::LINKS], 
                        $site[self::WEIGHT], $site[self::HASH]);
                }
            } else {
                $this->found_sites[self::SEEN_URLS][] = $site;
                if(isset($site[self::LINKS])
                    && $this->crawl_type == self::WEB_CRAWL) {
                    if(!isset($this->found_sites[self::TO_CRAWL])) {
                        $this->found_sites[self::TO_CRAWL] = array();
                    }
                    $link_urls = array_keys($site[self::LINKS]);

                    $this->addToCrawlSites($link_urls, $site[self::WEIGHT],
                        $site[self::HASH]);

                }
            } //end else

            if(isset($this->found_sites[self::TO_CRAWL])) {
                $this->found_sites[self::TO_CRAWL] = 
                    array_filter($this->found_sites[self::TO_CRAWL]);
            }
            crawlLog($site[self::INDEX].". ".$site[self::URL]);
         
        } // end for


        if((count($this->to_crawl) <= 0 && count($this->to_crawl_again) <= 0) ||
            ( isset($this->found_sites[self::SEEN_URLS]) && 
            count($this->found_sites[self::SEEN_URLS]) > 
            SEEN_URLS_BEFORE_UPDATE_SCHEDULER) || 
            ($this->crawl_type == self::ARCHIVE_CRAWL && 
            $this->archive_iterator->end_of_iterator)) {
            $this->updateScheduler();
        }

        crawlLog("  Update Found Sites Time ".(changeInMicrotime($start_time)));
    
    }

    /**
     * Used to add a set of links from a web page to the array of sites which
     * need to be crawled. 
     *
     * @param array $link_urls an array of urls to be crawled
     * @param int $old_weight the weight of the web page the links came from
     * @param string $site_hash a hash of the web_page on which the link was 
     *      found, for use in deduplication
     */
    function addToCrawlSites($link_urls, $old_weight, $site_hash) 
    {
        $num_links = count($link_urls);
        switch($this->crawl_order) {
            case self::BREADTH_FIRST:
                $weight= $old_weight + 1;
            break;

            case self::PAGE_IMPORTANCE:
            default:
                if($num_links > 0 ) {
                    $weight= $old_weight/$num_links;
                } else {
                    $weight= $old_weight;
                }
            break;
        }
        $count = count($link_urls);
        for($i = 0; $i < $count; $i++) {
            if(strlen($link_urls[$i]) > 0) {
                $this->found_sites[self::TO_CRAWL][] = 
                    array($link_urls[$i], $weight, $site_hash.$i);
            }
        }
    }

    /**
     * Updates the queue_server about sites that have been crawled.
     *
     * This method is called if there are currently no more sites to crawl or
     * if SEEN_URLS_BEFORE_UPDATE_SCHEDULER many pages have been processed. It
     * creates a inverted index of the non robot pages crawled and then compresses
     * and does a post request to send the page summary data, robot data, 
     * to crawl url data, and inverted index back to the server. In the event
     * that the server doesn't acknowledge it loops and tries again after a
     * delay until the post is successful. At this point, memory for this data
     * is freed.
     */
    function updateScheduler() 
    {
        $queue_server = $this->queue_server;

        $prefix = "";
        if($this->fetcher_num !== false) {
            $prefix = $this->fetcher_num."-";
        }

        if(count($this->to_crawl) <= 0) {
            $schedule_time = $this->schedule_time;
        }

        /* 
            In what follows as we generate post data we delete stuff
            from $this->found_sites, to try to minimize our memory
            footprint.
         */
        $bytes_to_send = 0;
        $post_data = array('c'=>'fetch', 'a'=>'update', 
            'crawl_time' => $this->crawl_time, 'machine_uri' => WEB_URI,
            'robot_instance' => $prefix.ROBOT_INSTANCE);

        //handle robots.txt data
        if(isset($this->found_sites[self::ROBOT_TXT])) {
            $post_data['robot_data'] = webencode(
                gzcompress(serialize($this->found_sites[self::ROBOT_TXT])));
            unset($this->found_sites[self::ROBOT_TXT]);
            $bytes_to_send += strlen($post_data['robot_data']);
        }

        //handle schedule data
        $schedule_data = array();
        if(isset($this->found_sites[self::TO_CRAWL])) {
            $schedule_data[self::TO_CRAWL] = &
                $this->found_sites[self::TO_CRAWL];
        }
        unset($this->found_sites[self::TO_CRAWL]);

        $seen_cnt = 0;
        if(isset($this->found_sites[self::SEEN_URLS]) && 
            ($seen_cnt = count($this->found_sites[self::SEEN_URLS])) > 0 ) {
            $hash_seen_urls = array();
            foreach($this->found_sites[self::SEEN_URLS] as $site) {
                $hash_seen_urls[] =
                    crawlHash($site[self::URL], true);
            }
            $schedule_data[self::HASH_SEEN_URLS] = & $hash_seen_urls;
            unset($hash_seen_urls);
        }
        if(!empty($schedule_data)) {
            if(isset($schedule_time)) {
                $schedule_data[self::SCHEDULE_TIME] = $schedule_time;
            }
            $post_data['schedule_data'] = webencode(
                gzcompress(serialize($schedule_data)));
            $bytes_to_send += strlen($post_data['schedule_data']);
        }
        unset($schedule_data);

        //handle mini inverted index
        if($seen_cnt > 0 ) {
            $this->buildMiniInvertedIndex();
        }
        crawlLog("...");
        if(isset($this->found_sites[self::INVERTED_INDEX])) {
            $compress_urls = "";
            while($this->found_sites[self::SEEN_URLS] != array()) {
                $site = array_shift($this->found_sites[self::SEEN_URLS]);
                $site_string = gzcompress(serialize($site));
                $compress_urls .= packInt(strlen($site_string)).$site_string;
            }
            unset($this->found_sites[self::SEEN_URLS]);
            $len_urls =  strlen($compress_urls);
            crawlLog("...Finish Compressing seen URLs.");
            $post_data['index_data'] = webencode( packInt($len_urls).
                $compress_urls. $this->found_sites[self::INVERTED_INDEX]
                ); // don't compress index data
            unset($compress_urls);
            unset($this->found_sites[self::INVERTED_INDEX]);
            $bytes_to_send += strlen($post_data['index_data']);
        }

        $this->found_sites = array(); // reset found_sites so have more space.
        if($bytes_to_send <= 0) {
            crawlLog("No data to send aborting update scheduler...");
            return;
        }
        crawlLog("...");
        //try to send to queue server
        $sleep = false;
        do {

            if($sleep == true) {
                crawlLog("Trouble sending to the scheduler\n $info_string...");
                crawlLog("Maybe post_max_size too small in php.ini file? ...");
                crawlLog("Or maybe the queue server url address or server key".
                    " is wrong?");
                sleep(5);
            }
            $sleep = true;

            $time = time();
            $session = md5($time . AUTH_KEY);
            $post_data['time'] = $time;
            $post_data['session'] = $session;
            $post_data['fetcher_peak_memory'] = memory_get_peak_usage();
            crawlLog(
                "Sending Queue Server" .
                " $bytes_to_send bytes...");
            $info_string = FetchUrl::getPage($queue_server, $post_data);
            crawlLog(
                "Updated Queue Server, sent approximately" .
                " $bytes_to_send bytes:");

            $info = unserialize(trim($info_string));
            crawlLog("Queue Server info response code: ".$info[self::STATUS]);
            crawlLog("Queue Server's crawl time is: ".$info[self::CRAWL_TIME]);
            crawlLog("Web Server peak memory usage: ".
                $info[self::MEMORY_USAGE]);
            crawlLog("This fetcher peak memory usage: ".
                memory_get_peak_usage());
        } while(!isset($info[self::STATUS]) || 
            $info[self::STATUS] != self::CONTINUE_STATE);
        if($this->crawl_type == self::WEB_CRAWL) {
            $dir = CRAWL_DIR."/schedules";
            file_put_contents("$dir/$prefix".self::fetch_batch_name.
                "{$this->crawl_time}.txt",
                serialize($this->to_crawl));
            $this->db->setWorldPermissionsRecursive("$dir/$prefix".
                self::fetch_batch_name."{$this->crawl_time}.txt");
        }
    }

    /**
     * Builds an inverted index shard (word --> {docs it appears in}) 
     * for the current batch of SEEN_URLS_BEFORE_UPDATE_SCHEDULER many pages. 
     * This inverted index shard is then merged by the queue_server 
     * into the inverted index of the current generation of the crawl. 
     * The complete inverted index for the whole crawl is built out of these 
     * inverted indexes for generations. The point of computing a partial 
     * inverted index on the fetcher is to reduce some of the computational 
     * burden on the queue server. The resulting mini index computed by 
     * buildMiniInvertedIndex() is stored in
     * $this->found_sites[self::INVERTED_INDEX]
     *
     */
    function buildMiniInvertedIndex()
    {
        global $IMAGE_TYPES;

        $start_time = microtime();

        $num_seen = count($this->found_sites[self::SEEN_URLS]);
        $this->num_seen_sites += $num_seen;
        /*
            for the fetcher we are not saving the index shards so
            name doesn't matter.
        */
        $index_shard = new IndexShard("fetcher_shard");
        for($i = 0; $i < $num_seen; $i++) {
            $site = $this->found_sites[self::SEEN_URLS][$i];
            if(!isset($site[self::HASH])) {continue; }
            $doc_keys = crawlHash($site[self::URL], true) . 
                $site[self::HASH]."d". substr(crawlHash(
                UrlParser::getHost($site[self::URL])."/",true), 1);

            $doc_rank = false;
            if($this->crawl_type == self::ARCHIVE_CRAWL && 
                isset($this->archive_iterator)) {
                $doc_rank = $this->archive_iterator->weight($site);
            }

            $meta_ids = $this->calculateMetas($site);

            $word_lists = array();
            /* 
                self::JUST_METAS check to avoid getting sitemaps in results for 
                popular words
             */
            $lang = NULL;
            if(!isset($site[self::JUST_METAS])) {
                $phrase_string = 
                    mb_ereg_replace(PUNCT, " ", $site[self::TITLE] .
                       " ". $site[self::DESCRIPTION]);
                if(isset($site[self::LANG])) {
                    $lang = $site[self::LANG];
                }
                
                $word_lists = 
                    PhraseParser::extractPhrasesInLists($phrase_string,
                        MAX_PHRASE_LEN, $lang);
            }

            $link_phrase_string = "";
            $link_urls = array(); 
            //store inlinks so they can be searched by
            $num_links = count($site[self::LINKS]);
            if($num_links > 0) {
                $weight = (isset($site[self::WEIGHT])) ? $site[self::WEIGHT] :1;
                $link_weight = $weight/$num_links;
                $link_rank = false;
                if($doc_rank !== false) {
                    $link_rank = max($doc_rank - 1, 1);
                }
            } else {
                $link_weight = 0;
                $link_rank = false;
            }
            $had_links = false;

            foreach($site[self::LINKS] as $url => $link_text) {
                $link_meta_ids = array();
                $location_link = false;
                if(strlen($url) > 0) {
                    $summary = array();
                    if(substr($link_text, 0, 9) == "location:") {
                        $location_link = true;
                        $link_meta_ids[] = $link_text;
                        $link_meta_ids[] = "location:".
                            crawlHash($site[self::URL]);
                    }
                    $elink_flag = (UrlParser::getHost($url) != 
                        UrlParser::getHost($site[self::URL])) ? true : false;
                    $had_links = true;
                    $link_text = strip_tags($link_text);
                    $ref = ($elink_flag) ? "eref" : "iref";
                    $link_id = 
                        "url|".$url."|text|$link_text|$ref|".$site[self::URL];
                    $elink_flag_string = ($elink_flag) ? "e" :
                        "i";
                    $link_keys = crawlHash($url, true) .
                        crawlHash($link_id, true) . 
                        $elink_flag_string.
                        substr(crawlHash(
                            UrlParser::getHost($site[self::URL])."/", true), 1);
                    $summary[self::URL] =  $link_id;
                    $summary[self::TITLE] = $url; 
                        // stripping html to be on the safe side
                    $summary[self::DESCRIPTION] =  $link_text;
                    $summary[self::TIMESTAMP] =  $site[self::TIMESTAMP];
                    $summary[self::ENCODING] = $site[self::ENCODING];
                    $summary[self::HASH] =  $link_id;
                    $summary[self::TYPE] = "link";
                    $summary[self::HTTP_CODE] = "link";
                    $this->found_sites[self::SEEN_URLS][] = $summary;
                    $link_type = UrlParser::getDocumentType($url);
                    if(in_array($link_type, $IMAGE_TYPES)) {
                        $link_meta_ids[] = "media:image";
                    } else {
                        $link_meta_ids[] = "media:text";
                    }
                    $link_text = 
                        mb_ereg_replace(PUNCT, " ", $link_text);
                    $link_word_lists = 
                        PhraseParser::extractPhrasesInLists($link_text,
                        MAX_PHRASE_LEN, $lang);

                    $index_shard->addDocumentWords($link_keys, 
                        self::NEEDS_OFFSET_FLAG, 
                        $link_word_lists, $link_meta_ids, false, $link_rank);

                    $meta_ids[] = 'link:'.$url;
                    $meta_ids[] = 'link:'.crawlHash($url);

                }

            }

            $index_shard->addDocumentWords($doc_keys, self::NEEDS_OFFSET_FLAG, 
                $word_lists, $meta_ids, true, $doc_rank);

        }

        $this->found_sites[self::INVERTED_INDEX] = $index_shard->save(true);

        if($this->crawl_type == self::ARCHIVE_CRAWL) {
            $this->recrawl_check_scheduler = true;
        }
        crawlLog("  Build mini inverted index time ".
            (changeInMicrotime($start_time)));
    }

    /**
     * Calculates the meta words to be associated with a given downloaded 
     * document. These words will be associated with the document in the
     * index for (server:apache) even if the document itself did not contain
     * them.
     *
     * @param array &$site associated array containing info about a downloaded
     *      (or read from archive) document.
     * @return array of meta words to be associate with this document
     */
    function calculateMetas(&$site)
    {
        $meta_ids = array();

        /*
            Handle the built-in meta words. For example
            store the sites the doc_key belongs to, 
            so you can search by site
        */
        $url_sites = UrlParser::getHostPaths($site[self::URL]);
        $url_sites = array_merge($url_sites, 
            UrlParser::getHostSubdomains($site[self::URL]));
        foreach($url_sites as $url_site) {
            if(strlen($url_site) > 0) {
                $meta_ids[] = 'site:'.$url_site;
            }
        }
        $meta_ids[] = 'info:'.$site[self::URL];
        $meta_ids[] = 'info:'.crawlHash($site[self::URL]);
        $meta_ids[] = 'site:all';
        if(isset($site[self::LOCATION]) && count($site[self::LOCATION]) > 0){
            foreach($site[self::LOCATION] as $location) {
                $meta_ids[] = 'info:'.$location;
                $meta_ids[] = 'info:'.crawlHash($location);
                $meta_ids[] = 'location:'.$location;
            }
        }

        foreach($site[self::IP_ADDRESSES] as $address) {
            $meta_ids[] = 'ip:'.$address;
        }
        $meta_ids[] = (stripos($site[self::TYPE], "image") !== false) ? 
            'media:image' : 'media:text';

        // store the filetype info
        $url_type = UrlParser::getDocumentType($site[self::URL]);
        if(strlen($url_type) > 0) {
            $meta_ids[] = 'filetype:'.$url_type;
        }
        if(isset($site[self::SERVER])) {
            $meta_ids[] = 'server:'.strtolower($site[self::SERVER]);
        }
        if(isset($site[self::SERVER_VERSION])) {
            $meta_ids[] = 'version:'.
                $site[self::SERVER_VERSION];
        }
        if(isset($site[self::OPERATING_SYSTEM])) {
            $meta_ids[] = 'os:'.strtolower($site[self::OPERATING_SYSTEM]);
        }
        if(isset($site[self::MODIFIED])) {
            $modified = $site[self::MODIFIED];
            $meta_ids[] = 'modified:'.date('Y', $modified);
            $meta_ids[] = 'modified:'.date('Y-m', $modified);
            $meta_ids[] = 'modified:'.date('Y-m-d', $modified);
        }
        if(isset($site[self::TIMESTAMP])) {
            $date = $site[self::TIMESTAMP];
            $meta_ids[] = 'date:'.date('Y', $date);
            $meta_ids[] = 'date:'.date('Y-m', $date);
            $meta_ids[] = 'date:'.date('Y-m-d', $date);
        }
        if(isset($site[self::LANG])) {
            $lang_parts = explode("-", $site[self::LANG]);
            $meta_ids[] = 'lang:'.$lang_parts[0];
            if(isset($lang_parts[1])){
                $meta_ids[] = 'lang:'.$site[self::LANG];
            }
        }
        
        //Add all meta word for subdoctype
        if(isset($site[self::SUBDOCTYPE])){
            $meta_ids[] = $site[self::SUBDOCTYPE].':all';
        }
        
        // handles user added meta words
        if(isset($this->meta_words)) {
            $matches = array();
            $url = $site[self::URL];
            foreach($this->meta_words as $word => $url_pattern) {
                $meta_word = 'u:'.$word;
                if(strlen(stristr($url_pattern, "@")) > 0) {
                    continue; // we are using "@" as delimiter, so bail
                }
                preg_match_all("@".$url_pattern."@", $url, $matches);
                if(isset($matches[0][0]) && strlen($matches[0][0]) > 0){
                    unset($matches[0]);
                    foreach($matches as $match) {
                        $meta_word .= ":".$match[0];
                        $meta_ids[] = $meta_word;
                    }

                }
            }
        }

        return $meta_ids;
    }

}

/*
 *  Instantiate and runs the Fetcher
 */
$fetcher =  new Fetcher($PAGE_PROCESSORS, QUEUE_SERVER,
    PAGE_RANGE_REQUEST);
$fetcher->start();

?>
