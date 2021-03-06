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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined("UNIT_TEST_MODE")) {
    if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
    /** Calculate base directory of script
     * @ignore
     */
    define("BASE_DIR", substr(
        dirname(realpath($_SERVER['PHP_SELF'])), 0,
        -strlen("/bin")));
    /** NO_CACHE means don't try to use memcache
     * @ignore
     */
    define("NO_CACHE", true);
}
ini_set("memory_limit","2000M"); //so have enough memory to crawl big pages
/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance ".
        "by visiting its web interface on localhost.\n";
    exit();
}
/** Get the database library based on the current database type */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";

/** Load the class that maintains our URL queue */
require_once BASE_DIR."/lib/web_queue_bundle.php";

/** Load word->{array of docs with word} index class */
require_once BASE_DIR."/lib/index_archive_bundle.php";

/** Used for manipulating urls*/
require_once BASE_DIR."/lib/url_parser.php";

/**  For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/** For CrawlDaemon::processHandler function  */
require_once BASE_DIR."/lib/crawl_daemon.php";
/**  */
require_once BASE_DIR."/lib/fetch_url.php";
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";
/** */
require_once BASE_DIR."/lib/phrase_parser.php";
/** Include marker interface to say we support join() method*/
require_once BASE_DIR."/lib/join.php";

/** get any indexing plugins */
foreach(glob(BASE_DIR."/lib/indexing_plugins/*_plugin.php") as $filename) {
    require_once $filename;
}
/** get any local indexing plugins */
foreach(glob(APP_DIR."/lib/indexing_plugins/*_plugin.php") as $filename) {
    require_once $filename;
}
/*
 * We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/*
 * If Memcache is available queueserver can be used to load
 * index dictionaries and shards into memcache. Note in only
 * this situation is NO_CACHE ignored
 */
if(USE_MEMCACHE) {
    $MEMCACHE = new Memcache();
    foreach($MEMCACHES as $mc) {
        $MEMCACHE->addServer($mc['host'], $mc['port']);
    }
    unset($mc);
}
/**
 * Command line program responsible for managing Yioop crawls.
 *
 * It maintains a queue of urls that are going to be scheduled to be seen.
 * It also keeps track of what has been seen and robots.txt info.
 * Its last responsibility is to create and populate the IndexArchiveBundle
 * that is used by the search front end.
 *
 * @author Chris Pollett
 * @package seek_quarry
 */
class QueueServer implements CrawlConstants, Join
{
    /**
     * Reference to a database object. Used since has directory manipulation
     * functions
     * @var object
     */
    var $db;
    /**
     * Web-sites that crawler can crawl. If used, ONLY these will be crawled
     * @var array
     */
    var $allowed_sites;
    /**
     * Web-sites that the crawler must not crawl
     * @var array
     */
    var $disallowed_sites;
    /**
     * Used to cache $allowed_sites and $disallowed_sites filtering data
     * structures
     * @var int
     */
    var $allow_disallow_cache_time;
    /**
     * Timestamp of lst time download from site quotas were cleared
     * @var int
     */
    var $quota_clear_time;
    /**
     * Web-sites that have an hourly crawl quota
     * @var array
     */
    var $quota_sites;
    /**
     * Cache of array_keys of $quota_sites
     * @var array
     */
    var $quota_sites_keys;
    /**
     * Constant saying the method used to order the priority queue for the crawl
     * @var string
     */
    var $crawl_order;
    /**
     * Stores the name of the summarizer used for crawling.
     * Possible values are Basic and Centroid
     * @var string
     */
    var $summarizer_option;
    /**
     * Maximum number of bytes to download of a webpage
     * @var int
     */
    var $page_range_request;
    /**
     * Max number of chars to extract for description from a page to index.
     * Only words in the description are indexed.
     * @var int
     */
    var $max_description_len;
    /**
     * Number of days between resets of the page url filter
     * If nonpositive, then never reset filter
     * @var int
     */
    var $page_recrawl_frequency;
    /**
     * Indicates the kind of crawl being performed: self::WEB_CRAWL indicates
     * a new crawl of the web; self::ARCHIVE_CRAWL indicates a crawl of an
     * existing web archive
     * @var string
     */
    var $crawl_type;
    /**
     * If the crawl_type is self::ARCHIVE_CRAWL, then crawl_index is the
     * timestamp of the existing archive to crawl
     * @var string
     */
    var $crawl_index;
    /**
     * Says whether the $allowed_sites array is being used or not
     * @var bool
     */
    var $restrict_sites_by_url;
    /**
     * List of file extensions supported for the crawl
     * @var array
     */
    var $indexed_file_types;
    /**
     * List of all known file extensions including those not used for crawl
     * @var array
     */
    var $all_file_types;
    /**
     * Used in schedules to tell the fetcher whether or not to cache pages
     * @var bool
     */
    var $cache_pages;
    /**
     * Used to add page rules to be applied to downloaded pages to schedules
     * that the fetcher will use (and hence apply the page )
     * @var array
     */
    var $page_rules;
    /**
     * Holds the WebQueueBundle for the crawl. This bundle encapsulates
     * the priority queue of urls that specifies what to crawl next
     * @var object
     */
    var $web_queue;
    /**
     * Holds the IndexArchiveBundle for the current crawl. This encapsulates
     * the inverted index word-->documents for the crawls as well as document
     * summaries of each document.
     * @var object
     */
    var $index_archive;
    /**
     * The timestamp of the current active crawl
     * @var int
     */
    var $crawl_time;
    /**
     * This is a list of hosts whose robots.txt file had a Crawl-delay directive
     * and which we have produced a schedule with urls for, but we have not
     * heard back from the fetcher who was processing those urls. Hosts on
     * this list will not be scheduled for more downloads until the fetcher
     * with earlier urls has gotten back to the queue_server.
     * @var array
     */
    var $waiting_hosts;
    /**
     * IP address as a string of the fetcher that most recently spoke with the
     * queue_server.
     * @var string
     */
    var $most_recent_fetcher;
    /**
     * Last time index was saved to disk
     * @var int
     */
    var $last_index_save_time;
    /**
     * flags for whether the index has data to be written to disk
     * @var int
     */
     var $index_dirty;

    /**
     * This keeps track of the time the current archive info was last modified
     * This way the queue_server knows if the user has changed the crawl
     * parameters during the crawl.
     * @var int
     */
    var $archive_modified_time;

    /**
     * This is a list of indexing_plugins which might do
     * post processing after the crawl. The plugins postProcessing function
     * is called if it is selected in the crawl options page.
     * @var array
     */
    var $indexing_plugins;

    /**
     * This is a array of crawl parameters for indexing_plugins which might do
     * post processing after the crawl.
     * @var array
     */
    var $indexing_plugins_data;
    /**
     * List of media sources mainly to determine the value of the media:
     * meta word (in particular, if it should be video or not)
     * @var array
     */
    var $video_sources;
    /**
     * This is a list of hourly (timestamp, number_of_urls_crawled) statistics
     * @var array
     */
    var $hourly_crawl_data;
    /**
     * Used to say what kind of queue_server this is (one of BOTH, INDEXER,
     * SCHEDULER)
     * @var mixed
     */
    var $server_type;
    /**
     * String used to describe this kind of queue server (Indexer, Scheduler,
     * etc. in the log files.
     * @var mixed
     */
    var $server_name;
    /**
     * Creates a Queue Server Daemon
     */
    function __construct()
    {
        global $INDEXED_FILE_TYPES;
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();
        $this->indexed_file_types = $INDEXED_FILE_TYPES;
        $this->all_file_types = $INDEXED_FILE_TYPES;
        $this->most_recent_fetcher = "No Fetcher has spoken with me";

        //the next values will be set for real in startCrawl
        $this->crawl_order = self::PAGE_IMPORTANCE;
        $this->summarizer_option = self::CENTROID_SUMMARIZER;
        $this->restrict_sites_by_url = true;
        $this->allowed_sites = array();
        $this->disallowed_sites = array();
        $this->allow_disallow_cache_time = microtime();
        $this->quota_sites = array();
        $this->quota_sites_keys = array();
        $this->quota_clear_time = time();
        $this->page_rules = array();
        $this->last_index_save_time = 0;
        $this->index_dirty = false;
        $this->hourly_crawl_data = array();
        $this->archive_modified_time = 0;
        $this->crawl_time = 0;
        $this->cache_pages = true;
        $this->page_recrawl_frequency = PAGE_RECRAWL_FREQUENCY;
        $this->page_range_request = PAGE_RANGE_REQUEST;
        $this->max_description_len = MAX_DESCRIPTION_LEN;
        $this->server_type = self::BOTH;
        $this->indexing_plugins = array();
        $this->indexing_plugins_data = array();
        $this->video_sources = array();
        $this->server_name = "IndexerAndScheduler";
    }
    /**
     * This is the function that should be called to get the queue_server
     * to start. Calls init to handle the command line arguments then enters
     * the queue_server's main loop
     */
    function start()
    {
        global $argv;
        if(isset($argv[1]) && $argv[1] == "start") {
            if(isset($argv[2]) && in_array($argv[2], array(
                self::INDEXER, self::SCHEDULER)) ) {
                $argv[3] = $argv[2];
                $argv[2] = "none";
                // 3 indicates force start
                CrawlDaemon::init($argv, "queue_server", 3);
            } else {
                $argv[2] = "none";
                $argv[3] = self::INDEXER;
                CrawlDaemon::init($argv, "queue_server", 0);
                $argv[2] = "none";
                $argv[3] = self::SCHEDULER;
                CrawlDaemon::init($argv, "queue_server", 2);
            }
        } else {
            CrawlDaemon::init($argv, "queue_server");
        }
        crawlLog("\n\nInitialize logger..", "queue_server", true);
        $this->server_name = "IndexerAndScheduler";
        if(isset($argv[3]) && $argv[1] == "child" &&
            in_array($argv[3], array(self::INDEXER, self::SCHEDULER))) {
            $this->server_type = $argv[3];
            $this->server_name = $argv[3];
            crawlLog($argv[3]." logging started.");
        }
        $remove = false;
        $old_message_names = array("queue_server_messages.txt",
            "scheduler_messages.txt", "crawl_status.txt",
            "schedule_status.txt");
        foreach($old_message_names as $name) {
            if(file_exists(CRAWL_DIR."/schedules/$name")) {
                @unlink(CRAWL_DIR."/schedules/$name");
                $remove = true;
            }
        }

        if($remove == true) {
            crawlLog("Remove old messages..", "queue_server");
        }
        $this->loop();
    }
    /**
     * Main runtime loop of the queue_server.
     *
     * Loops until a stop message received, check for start, stop, resume
     * crawl messages, deletes any WebQueueBundle for which an
     * IndexArchiveBundle does not exist. Processes
     */
    function loop()
    {
        $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
        crawlLog("In queue loop!! {$this->server_name}", "queue_server");
        if($this->isAIndexer()) {
            $this->deleteOrphanedBundles();
        }
        while (CrawlDaemon::processHandler()) {
            crawlLog("{$this->server_name} peak memory usage so far: ".
                memory_get_peak_usage()."!!");
            $info = $this->handleAdminMessages($info);
            if( $info[self::STATUS] == self::WAITING_START_MESSAGE_STATE) {
                crawlLog("{$this->server_name} is waiting for start message\n");
                sleep(QUEUE_SLEEP_TIME);
                continue;
            }
            if($info[self::STATUS] == self::STOP_STATE) {
                continue;
            }
            crawlLog("{$this->server_name} active crawl is ".
                "{$this->crawl_time}.");
            if($this->isAScheduler()) {
                crawlLog("Current queue size is:".
                    $this->web_queue->to_crawl_queue->count);
            }
            $start_loop_time = time();

            //check and update if necessary the crawl params of current crawl
            $this->checkUpdateCrawlParameters();

            $this->updateMostRecentFetcher();

            $this->processCrawlData();

            $time_diff = time() - $start_loop_time;
            if( $time_diff < QUEUE_SLEEP_TIME) {
                crawlLog("Sleeping...");
                sleep(QUEUE_SLEEP_TIME - $time_diff);
            }
        }
        crawlLog("{$this->server_name} shutting down!!");
    }
    /**
     * Main body of queue_server loop where indexing, scheduling,
     * robot file processing is done.
     *
     * @param bool $blocking this method might be called by the indexer
     *     subcomponent when a merge tier phase is ongoing to allow for
     *     other processing to occur. If so, we don't want a regress
     *     where the indexer calls this code calls the indexer etc. If
     *     the blocking flag is set then the indexer subcomponent won't
     *     be called
     */
    function processCrawlData($blocking = false)
    {
        crawlLog("{$this->server_name} Entering Process Crawl Data Method ");
        if($this->isAIndexer()) {
            /* check for orphaned queue bundles
               also check to make sure indexes closed properly for stopped
               crawls
             */
            $this->deleteOrphanedBundles();
            $this->processIndexData($blocking);
            if(time() - $this->last_index_save_time > FORCE_SAVE_TIME){
                crawlLog("Periodic Index Save... \n");
                $start_time = microtime();
                $this->indexSave();
                crawlLog("... Save time".(changeInMicrotime($start_time)));
            }
        }
        if($this->isAScheduler()) {
            $info = array();
            $info[self::TYPE] = $this->server_type;
            $info[self::MEMORY_USAGE] = memory_get_peak_usage();
            file_put_contents(CRAWL_DIR."/schedules/schedule_status.txt",
                serialize($info));
        }

        switch($this->crawl_type)
        {
            case self::WEB_CRAWL:
                if($this->isOnlyIndexer()) { return; }
                $this->processRobotUrls();
                if(USE_ETAG_EXPIRES) {
                    $this->processEtagExpires();
                }
                $count = $this->web_queue->to_crawl_queue->count;
                $max_links = max(MAX_LINKS_PER_PAGE, MAX_LINKS_PER_SITEMAP);
                if($count < NUM_URLS_QUEUE_RAM -
                    SEEN_URLS_BEFORE_UPDATE_SCHEDULER * $max_links){
                    $info = $this->processQueueUrls();
                }
                if($count > 0) {
                    $top = $this->web_queue->peekQueue();
                    if($top[1] < MIN_QUEUE_WEIGHT) {
                        crawlLog("Normalizing Weights!!\n");
                        $this->web_queue->normalize();
                        /* this will undercount the weights of URLS
                           from fetcher data that have not completed
                         */
                     }
                    /*
                        only produce fetch batch if the last one has already
                        been taken by some fetcher
                     */
                    if(!file_exists(
                        CRAWL_DIR."/schedules/".self::schedule_name.
                        $this->crawl_time.".txt")) {
                            $this->produceFetchBatch();
                    }
                }
            break;
            case self::ARCHIVE_CRAWL:
                if($this->isAIndexer()) {
                    $this->processRecrawlRobotUrls();
                    if(!file_exists(CRAWL_DIR."/schedules/".self::schedule_name.
                            $this->crawl_time.".txt")) {
                        $this->writeArchiveCrawlInfo();
                    }
                }
            break;
        }
    }
    /**
     * Used to check if the current queue_server process is acting a
     * url scheduler for fetchers
     *
     * @return bool whether it is or not
     */
    function isAScheduler()
    {
        return strcmp($this->server_type, self::SCHEDULER) == 0 ||
            strcmp($this->server_type, self::BOTH) == 0;
    }
    /**
     * Used to check if the current queue_server process is acting a
     * indexer of data coming from fetchers
     *
     * @return bool whether it is or not
     */
    function isAIndexer()
    {
        return strcmp($this->server_type, self::INDEXER) == 0 ||
            strcmp($this->server_type, self::BOTH) == 0;
    }
    /**
     * Used to check if the current queue_server process is acting only as a
     * indexer of data coming from fetchers (and not some other activity
     * like scheduler as well)
     *
     * @return bool whether it is or not
     */
    function isOnlyIndexer()
    {
        return strcmp($this->server_type, self::INDEXER) == 0;
    }
    /**
     * Used to check if the current queue_server process is acting only as a
     * indexer of data coming from fetchers (and not some other activity
     * like indexer as well)
     *
     * @return bool whether it is or not
     */
    function isOnlyScheduler()
    {
        return strcmp($this->server_type, self::SCHEDULER) == 0;
    }
    /**
     * Used to write info about the current recrawl to file as well as to
     * process any recrawl data files received
     */
    function writeArchiveCrawlInfo()
    {
        $schedule_time = time();
        $first_line = $this->calculateScheduleMetaInfo($schedule_time);
        $fh = fopen(CRAWL_DIR."/schedules/".self::schedule_name.
            $this->crawl_time.".txt", "wb");
        fwrite($fh, $first_line);
        fclose($fh);

        $schedule_dir =
            CRAWL_DIR."/schedules/".
                self::schedule_data_base_name.$this->crawl_time;
        $this->processDataFile($schedule_dir, "processRecrawlDataArchive");
    }
    /**
     * Even during a recrawl the fetcher may send robot data to the
     * queue_server. This function prints a log message and calls another
     * function to delete this useless robot file.
     */
    function processRecrawlRobotUrls()
    {
        crawlLog("Checking for robots.txt files to process...");
        $robot_dir =
            CRAWL_DIR."/schedules/".
                self::robot_data_base_name.$this->crawl_time;

        $this->processDataFile($robot_dir, "processRecrawlRobotArchive");
        crawlLog("done. ");
    }
    /**
     * Even during a recrawl the fetcher may send robot data to the
     * queue_server. This function delete the passed robot file.
     *
     * @param string $file robot file to delete
     */
    function processRecrawlRobotArchive($file)
    {
        crawlLog("Deleting unneeded robot schedule files");
        unlink($file);
    }
    /**
     * Used to get a data archive file (either during a normal crawl or a
     * recrawl). After uncompressing this file (which comes via the web server
     * through fetch_controller, from the fetcher), it sets which fetcher
     * sent it and also returns the sites contained in it.
     *
     * @param string $file name of archive data file
     * @return array sites contained in the file from the fetcher
     */
    function &getDataArchiveFileData($file)
    {
        crawlLog("Processing File: $file");
        $decode = file_get_contents($file);
        $decode = webdecode($decode);
        $decode = gzuncompress($decode);
        $sites = unserialize($decode);
        return $sites;
    }
    /**
     * Processes fetcher data file information during a recrawl
     *
     * @param String $file a file which contains the info to process
     */
    function processRecrawlDataArchive($file)
    {
        $sites = & $this->getDataArchiveFileData($file);
        unlink($file);
        $this->writeCrawlStatus($sites);
    }
    /**
     * Handles messages passed via files to the QueueServer.
     *
     * These files are typically written by the CrawlDaemon::init()
     * when QueueServer is run using command-line argument
     *
     * @param array $info associative array with info about current state of
     *     queue_server
     * @return array an updates version $info reflecting changes that occurred
     *     during the handling of the admin messages files.
     */
    function handleAdminMessages($info)
    {
        $old_info = $info;
        $is_scheduler = $this->isOnlyScheduler();
        $message_file = CRAWL_DIR."/schedules/queue_server_messages.txt";
        if(file_exists($message_file)) {
            $info = unserialize(file_get_contents($message_file));
            if(!isset($info[$this->server_type])) {
                $info[$this->server_type] = true;
                if($this->server_type == self::BOTH ||
                  (isset($info[self::INDEXER]) && isset($info[self::SCHEDULER]))
                  ) {
                    unlink($message_file);
                } else {
                    file_put_contents($message_file, serialize($info), LOCK_EX);
                }
            } else {
                return $old_info;
            }
            switch($info[self::STATUS])
            {
                case "NEW_CRAWL":
                   if($old_info[self::STATUS] == self::CONTINUE_STATE) {
                        if(!$is_scheduler) {
                        crawlLog("Stopping previous crawl before start".
                            " new crawl!");
                        } else {
                        crawlLog("Scheduler stopping for previous crawl before".
                            " new crawl!");
                        }
                        $this->stopCrawl();
                    }
                    $this->startCrawl($info);
                    if(!$is_scheduler) {
                        crawlLog(
                            "Starting new crawl. Timestamp:".$this->crawl_time);
                        if($this->crawl_type == self::WEB_CRAWL) {
                            crawlLog("Performing a web crawl!");
                        } else {
                            crawlLog("Performing an archive crawl of ".
                                "archive with timestamp ".$this->crawl_index);
                        }
                        $this->writeAdminMessage("BEGIN_CRAWL");
                    } else {
                        crawlLog("Scheduler started for new crawl");
                    }
                break;

                case "STOP_CRAWL":
                    if(!$is_scheduler) {
                        crawlLog(
                            "Stopping crawl !! This involves multiple steps!");
                    } else {
                        crawlLog("Scheduler received stop message!");
                    }
                    $this->stopCrawl();
                    if(file_exists(CRAWL_DIR."/schedules/crawl_status.txt")) {
                        unlink(CRAWL_DIR."/schedules/crawl_status.txt");
                    }
                    $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                    if(!$is_scheduler) {
                        crawlLog("Crawl has been successfully stopped!!");
                    } else {
                        crawlLog("Scheduler has been successfully stopped!!");
                    }
                break;

                case "RESUME_CRAWL":
                    if(isset($info[self::CRAWL_TIME]) &&
                        (file_exists(CRAWL_DIR.'/cache/'.
                        self::queue_base_name.$info[self::CRAWL_TIME]) ||
                        $info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL) ) {
                        if($old_info[self::STATUS] == self::CONTINUE_STATE) {
                            if(!$is_scheduler) {
                                crawlLog("Resuming old crawl...".
                                    " Stopping current crawl first!");
                            } else {
                                crawlLog("Sheduler resuming old crawl...".
                                    " Stopping scheduling current first!");
                            }
                            $this->stopCrawl();
                        }
                        $this->startCrawl($info);
                        if(!$is_scheduler) {
                            crawlLog("Resuming crawl");
                            $this->writeAdminMessage("RESUME_CRAWL");
                        } else {
                            crawlLog("Scheduler Resuming crawl");
                        }
                    } else if(!$is_scheduler) {
                        $msg = "Restart failed!!!  ";
                        if(!isset($info[self::CRAWL_TIME])) {
                            $msg .="crawl time of crawl to restart not given\n";
                        }
                        if(!file_exists(CRAWL_DIR.'/cache/'.
                            self::queue_base_name.$info[self::CRAWL_TIME])) {
                            $msg .= "bundle for crawl restart doesn't exist\n";
                        }
                        $info["MESSAGE"] = $msg;
                        $this->writeAdminMessage($msg);
                        crawlLog($msg);
                        $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                    }
                break;
            }
        }
        return $info;
    }
    /**
     * Used to stop the currently running crawl gracefully so that it can
     * be restarted. This involved writing the queue's contents back to
     * schedules, making the crawl's dictionary all the same tier and running
     * any indexing_plugins.
     */
    function stopCrawl()
    {
        if($this->isAScheduler()) {
            $this->dumpQueueToSchedules();
            //Write B-Tree root node to disk before before exiting
            if(USE_ETAG_EXPIRES &&
                isset($this->web_queue) && $this->web_queue != NULL &&
                is_object($this->web_queue->etag_btree)) {
                $this->web_queue->etag_btree->writeRoot();
            }
        }
        if($this->isAIndexer()) {
            $this->shutdownDictionary();
            //Calling post processing function if the processor is
            //selected in the crawl options page.
            $this->runPostProcessingPlugins();
        }
        $close_file = CRAWL_DIR.'/schedules/'.self::index_closed_name.
            $this->crawl_time.".txt";

        if(!file_exists($close_file) &&
            strcmp($this->server_type, self::BOTH) != 0) {
            file_put_contents($close_file, "2");
            $start_time = microtime();
            do {
                if($this->isAIndexer()) {
                    crawlLog("Indexer waiting for Scheduler to stop.");
                } else {
                    crawlLog("Scheduler waiting for Indexer to stop.");
                }
                sleep(5);
                $contents = trim(file_get_contents($close_file));
            } while(file_exists($close_file) && strcmp($contents, "1") != 0 );
        } else {
            file_put_contents($close_file, "1");
        }
    }
    /**
     * Used to write an admin crawl status message during a start or stop
     * crawl.
     *
     * @param string $message to write into crawl_status.txt this will show
     *     up in the web crawl status element.
     */
    function writeAdminMessage($message)
    {
        $crawl_status = array();
        $crawl_status['MOST_RECENT_FETCHER'] = "";
        $crawl_status['MOST_RECENT_URLS_SEEN'] = array();
        $crawl_status['CRAWL_TIME'] = $this->crawl_time;
        $crawl_status['COUNT'] = 0;
        $crawl_status['DESCRIPTION'] = $message;
        file_put_contents(
            CRAWL_DIR."/schedules/crawl_status.txt",
            serialize($crawl_status));
        chmod(CRAWL_DIR."/schedules/crawl_status.txt", 0777);
    }
    /**
     * When a crawl is being shutdown, this function is called to write
     * the contents of the web queue bundle back to schedules. This allows
     * crawls to be resumed without losing urls. This function can also be
     * called if the queue gets clogged to reschedule its contents for a later
     * time.
     *
     * @param bool $for_reschedule if the call was to reschedule the urls
     *     to be crawled at a later time as opposed to being used to
     *     save the urls because the crawl is being halted.
     */
    function dumpQueueToSchedules($for_reschedule = false)
    {
        if(!$for_reschedule) {
            $this->writeAdminMessage("SHUTDOWN_QUEUE");
        }
        if(!isset($this->web_queue->to_crawl_queue)) {
            crawlLog("URL queue appears to be empty or NULL");
            return;
        }
        crawlLog("Writing queue contents back to schedules...");
        $dir = CRAWL_DIR."/schedules/".self::schedule_data_base_name.
            $this->crawl_time;
        if(!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $now = time();
        if($for_reschedule) {
            $day = floor($now/ONE_DAY);
            $note_string = "Reschedule";
        } else {
            $day = floor($this->crawl_time/ONE_DAY) - 1;
                //want before all other schedules, so will be reloaded first
            $note_string = "";
        }

        $dir .= "/$day";
        if(!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        //get rid of previous restart attempts, if present
        if(!$for_reschedule) {
            $this->db->unlinkRecursive($dir, false);
        }
        $count = $this->web_queue->to_crawl_queue->count;
        $old_time = 1;
        $schedule_data = array();
        $schedule_data[self::SCHEDULE_TIME] = $this->crawl_time;
        $schedule_data[self::TO_CRAWL] = array();
        $fh = $this->web_queue->openUrlArchive();
        for($time = 1; $time < $count; $time++) {
            crawlTimeoutLog("..have written %s urls of %s urls so far", $time,
                $count);
            $tmp =  $this->web_queue->peekQueue($time, $fh);
            list($url, $weight, , ) = $tmp;
            // if queue error skip
            if($tmp === false || strcmp($url, "LOOKUP ERROR") == 0) {
                continue;
            }
            /* for fetcher hash is  a hash of link_num . hash_of_page_link_on
             * in the case below. Either the url or hash can be used to
             * determine if the page has been seen. In the case, of a dump
             * we choose hash to be something so only url affects whether
             * dedup.
             */
            $hash = crawlHash($now . $url);
            if($for_reschedule) {
                $schedule_time = $time + $now;
            } else {
                $schedule_time = $time;
            }
            $schedule_data[self::TO_CRAWL][] = array($url, $weight, $hash);
            if($time - $old_time >= MAX_FETCH_SIZE) {
                if(count($schedule_data[self::TO_CRAWL]) > 0) {
                    $data_string = webencode(
                        gzcompress(serialize($schedule_data)));
                    $data_hash = crawlHash($data_string);
                    file_put_contents($dir."/At".$schedule_time."From127-0-0-1".
                        $note_string. "WithHash$data_hash.txt", $data_string);
                    $data_string = "";
                    $schedule_data[self::TO_CRAWL] = array();
                }
                $old_time = $time;
            }
        }
        $this->web_queue->closeUrlArchive($fh);
        if(count($schedule_data[self::TO_CRAWL]) > 0) {
            $data_string = webencode(
                gzcompress(serialize($schedule_data)));
            $data_hash = crawlHash($data_string);
            if($for_reschedule) {
                $schedule_time = $time + $now;
            } else {
                $schedule_time = $time;
            }
            file_put_contents($dir."/At".$schedule_time."From127-0-0-1".
                $note_string . "WithHash$data_hash.txt", $data_string);
        }
        $this->db->setWorldPermissionsRecursive(
            CRAWL_DIR.'/cache/'.
            self::queue_base_name.$this->crawl_time);
        $this->db->setWorldPermissionsRecursive($dir);
    }
    /**
     * During crawl shutdown, this function is called to do a final save and
     * merge of the crawl dictionary, so that it is ready to serve queries.
     */
    function shutdownDictionary()
    {
        $this->writeAdminMessage("SHUTDOWN_DICTIONARY");
        if(is_object($this->index_archive)) {
            $this->
                index_archive->forceSave();
            $this->
                index_archive->addCurrentShardDictionary();
            $this->index_archive->dictionary->mergeAllTiers();
        }
        $this->db->setWorldPermissionsRecursive(
            CRAWL_DIR.'/cache/'.
            self::index_data_base_name.$this->crawl_time);
    }
    /**
     * During crawl shutdown this is called to run any post processing plugins
     */
    function runPostProcessingPlugins()
    {
        if(isset($this->indexing_plugins)) {
            crawlLog("Post Processing....");
            $this->writeAdminMessage("SHUTDOWN_RUNPLUGINS");
            crawlLog("... Wrote Run Plugin shutdown Message");
            $num_plugins = count($this->indexing_plugins);
            if($num_plugins > 0) {
                crawlLog("... Will now shutdown $num_plugins plugins.");
            }
            foreach($this->indexing_plugins as $plugin) {
                $plugin_instance_name =
                    lcfirst($plugin)."Plugin";
                $plugin_name = $plugin."Plugin";
                $this->$plugin_instance_name =
                    new $plugin_name();
                if(method_exists($plugin_name, "setConfiguration") &&
                    isset($this->indexing_plugins_data[$plugin])) {
                    $this->$plugin_instance_name->setConfiguration(
                        $this->indexing_plugins_data[$plugin]);
                }
                if($this->$plugin_instance_name) {
                    crawlLog(
                        "... executing $plugin_instance_name");
                    $this->$plugin_instance_name->
                        postProcessing($this->crawl_time);
                }
            }
        }
    }
    /**
     * Saves the index_archive and, in particular, its current shard to disk
     */
    function indexSave()
    {
        $this->last_index_save_time = time();
        if(isset($this->index_archive) && $this->index_dirty) {
            $this->index_archive->forceSave();
            $this->index_dirty = false;
            // chmod so apache can also write to these directories
            $this->db->setWorldPermissionsRecursive(
                CRAWL_DIR.'/cache/'.
                self::index_data_base_name.$this->crawl_time);
        }
    }
    /**
     * Begins crawling base on time, order, restricted site $info
     * Setting up a crawl involves creating a queue bundle and an
     * index archive bundle
     *
     * @param array $info parameter for the crawl
     */
    function startCrawl($info)
    {
        //to get here we at least have to have a crawl_time
        $this->crawl_time = $info[self::CRAWL_TIME];

        $read_from_info = array(
            "crawl_order" => self::CRAWL_ORDER,
            "crawl_type" => self::CRAWL_TYPE,
            "crawl_index" => self::CRAWL_INDEX,
            "cache_pages" => self::CACHE_PAGES,
            "page_range_request" => self::PAGE_RANGE_REQUEST,
            "max_description_len" => self::MAX_DESCRIPTION_LEN,
            "page_recrawl_frequency" => self::PAGE_RECRAWL_FREQUENCY,
            "indexed_file_types" => self::INDEXED_FILE_TYPES,
            "restrict_sites_by_url" => self::RESTRICT_SITES_BY_URL,
            "allowed_sites" => self::ALLOWED_SITES,
            "disallowed_sites" => self::DISALLOWED_SITES,
            "page_rules" => self::PAGE_RULES,
            "indexing_plugins" => self::INDEXING_PLUGINS,
            "indexing_plugins_data" => self::INDEXING_PLUGINS_DATA,
            "video_sources" => self::VIDEO_SOURCES,
        );
        $try_to_set_from_old_index = array();
        $update_disallow = false;
        foreach($read_from_info as $index_field => $info_field) {
            if(isset($info[$info_field])) {
                if($index_field == "disallowed_sites") {
                    $update_disallow = true;
                }
                $this->$index_field = $info[$info_field];
            } else {
                array_push($try_to_set_from_old_index,  $index_field);
            }
        }
        /* We now do further processing or disallowed sites to see if any
           of them are really quota sites
         */
        if($update_disallow == true) {  $this->updateDisallowedQuotaSites(); }
        $this->initializeWebQueue();
        $dir = CRAWL_DIR.'/cache/'.self::index_data_base_name.$this->crawl_time;
        if(!file_exists($dir)) {
            if($this->isAIndexer()) {
                $this->index_archive = new IndexArchiveBundle($dir, false,
                    serialize($info));
                $this->last_index_save_time = time();
            }
        } else {
            $this->index_archive = new IndexArchiveBundle($dir,
                false);
            $archive_info = IndexArchiveBundle::getArchiveInfo($dir);
            $index_info = unserialize($archive_info['DESCRIPTION']);
            foreach($try_to_set_from_old_index as $index_field) {
                if(isset($index_info[$read_from_info[$index_field]]) ) {
                    $this->$index_field =
                        $index_info[$read_from_info[$index_field]];
                }
            }
        }
        if($this->isAIndexer()) {
            if(file_exists(CRAWL_DIR.'/schedules/'. self::index_closed_name.
                $this->crawl_time.".txt")) {
                unlink(CRAWL_DIR.'/schedules/'. self::index_closed_name.
                    $this->crawl_time.".txt");
            }
            $this->db->setWorldPermissionsRecursive($dir);
        }
        //Get modified time of initial setting of crawl params
        $this->archive_modified_time =
            IndexArchiveBundle::getParamModifiedTime($dir);

        $info[self::STATUS] = self::CONTINUE_STATE;
        return $info;
    }
    /**
     * This is called whenever the crawl options are modified to parse
     * from the disallowed sites, those sites of the format:
     * site#quota
     * where quota is the number of urls allowed to be downloaded in an hour
     * from the site. These sites are then deleted from disallowed_sites
     * and added to $this->quota sites. An entry in $this->quota_sites
     * has the format: $quota_site => array($quota,$num_urls_downloaded_this_hr)
     */
    function updateDisallowedQuotaSites()
    {
        $num_sites = count($this->disallowed_sites);
        $active_quota_sites = array();
        for($i = 0; $i < $num_sites; $i++) {
            $site_parts = explode("#", $this->disallowed_sites[$i]);
            if(count($site_parts) > 1) {
                $quota = intval(array_pop($site_parts));
                if($quota <= 0) continue;
                $this->disallowed_sites[$i] = false;
                $quota_site = implode("#", $site_parts);
                $active_quota_sites[] = $quota_site;
                if(isset($this->quota_sites[$quota_site])) {
                    $this->quota_sites[$quota_site] = array($quota,
                        $this->quota_sites[$quota_site][1]);
                } else {
                    //($quota, $num_scheduled_this_last_hour)
                    $this->quota_sites[$quota_site] = array($quota, 0);
                }
            }
        }
        foreach($this->quota_sites as $site => $info) {
            if(!in_array($site, $active_quota_sites)) {
                $this->quota_sites[$site] = false;
            }
        }
        $this->disallowed_sites = array_filter($this->disallowed_sites);
        $this->quota_sites = array_filter($this->quota_sites);
        $this->quota_sites_keys = array_keys($this->quota_sites);
    }
    /**
     * This method sets up a WebQueueBundle according to the current crawl
     * order so that it can receive urls and prioritize them.
     */
    function initializeWebQueue()
    {
        switch($this->crawl_order)
        {
            case self::BREADTH_FIRST:
                $min_or_max = self::MIN;
            break;

            case self::PAGE_IMPORTANCE:
            default:
                $min_or_max = self::MAX;
            break;
        }
        $this->web_queue = NULL;
        $this->index_archive = NULL;

        gc_collect_cycles(); // garbage collect old crawls

        if($this->isAScheduler()) {
            if($this->crawl_type == self::WEB_CRAWL ||
                !isset($this->crawl_type)) {
                $this->web_queue = new WebQueueBundle(
                    CRAWL_DIR.'/cache/'.self::queue_base_name.
                    $this->crawl_time, URL_FILTER_SIZE,
                        NUM_URLS_QUEUE_RAM, $min_or_max);
            }
            // chmod so web server can also write to these directories
            if($this->crawl_type == self::WEB_CRAWL) {
                $this->db->setWorldPermissionsRecursive(
                   CRAWL_DIR.'/cache/'.self::queue_base_name.$this->crawl_time);
            }
        }
    }
    /**
     * Delete all the urls from the web queue does not affect filters
     */
    function clearWebQueue()
    {
        $count = $this->web_queue->to_crawl_queue->count;
        $fh = $this->web_queue->openUrlArchive();
        for($i = $count; $i > 0; $i--) {
            crawlTimeoutLog("..Removing least url %s of %s ".
                "from queue.", ($count - $i), floor($count/2));
            $tmp = $this->web_queue->peekQueue($i, $fh);
            list($url, $weight, $flag, $probe) = $tmp;
            if($url) {
                $this->web_queue->removeQueue($url);
            }
        }
        $this->web_queue->closeUrlArchive($fh);
    }
    /**
     * Checks to see if the parameters by which the active crawl are being
     * conducted have been modified since the last time the values were put
     * into queue server field variables. If so, it updates the values to
     * to their new values
     */
    function checkUpdateCrawlParameters()
    {
        crawlLog("Check for update in crawl parameters...");
        $dir = CRAWL_DIR.'/cache/'.self::index_data_base_name.$this->crawl_time;
        $modified_time = IndexArchiveBundle::getParamModifiedTime($dir);
        if($this->archive_modified_time == $modified_time) {
            crawlLog("...none.");
            return;
        }
        $updatable_info = array(
            "page_range_request" => self::PAGE_RANGE_REQUEST,
            "max_description_len" => self::MAX_DESCRIPTION_LEN,
            "page_recrawl_frequency" => self::PAGE_RECRAWL_FREQUENCY,
            "restrict_sites_by_url" => self::RESTRICT_SITES_BY_URL,
            "cache_pages" => self::CACHE_PAGES,
            "allowed_sites" => self::ALLOWED_SITES,
            "disallowed_sites" => self::DISALLOWED_SITES,
            "page_rules" => self::PAGE_RULES,
            "indexed_file_types" => self::INDEXED_FILE_TYPES,
            "indexing_plugins" => self::INDEXING_PLUGINS,
            "indexing_plugins_data" => self::INDEXING_PLUGINS_DATA,
            "video_sources" => self::VIDEO_SOURCES,
        );
        $keys = array_keys($updatable_info);
        $archive_info = IndexArchiveBundle::getArchiveInfo($dir);
        $index_info = unserialize($archive_info['DESCRIPTION']);
        $check_cull_fields = array("restrict_sites_by_url", "allowed_sites",
            "disallowed_sites");
        $cull_now_non_crawlable = false;
        foreach($keys as $index_field) {
            if(isset($index_info[$updatable_info[$index_field]]) ) {
                if($index_field == "disallowed_sites") {
                    $update_disallow = true;
                }
                if(in_array($index_field, $check_cull_fields) &&
                    (!isset($this->$index_field) ||
                    $this->$index_field !=
                    $index_info[$updatable_info[$index_field]]) ) {
                    $cull_now_non_crawlable = true;
                }
                $this->$index_field =
                    $index_info[$updatable_info[$index_field]];
                if($this->isOnlyScheduler()) {
                    crawlLog("Scheduler Updating ...$index_field.");
                } else {
                    crawlLog("Updating ...$index_field.");
                }
            }
        }
        /* We now do further processing or disallowed sites to see if any
           of them are really quota sites
         */
        if($update_disallow == true) {  $this->updateDisallowedQuotaSites(); }
        if($this->isAScheduler() && $cull_now_non_crawlable) {
            crawlLog("Scheduler: Allowed/Disallowed Urls have changed");
            crawlLog("Scheduler: Checking if urls in queue need to be culled");
            $this->cullNoncrawlableSites();
        }
        $this->archive_modified_time = $modified_time;
    }
    /**
     * Delete all the queue bundles and schedules that don't have an
     * associated index bundle as this means that crawl has been deleted.
     */
    function deleteOrphanedBundles()
    {
        $dirs = glob(CRAWL_DIR.'/cache/*', GLOB_ONLYDIR);
        $num_dirs = count($dirs);
        $living_stamps = array();
        $dir_cnt = 0;
        foreach($dirs as $dir) {
            $dir_cnt++;
            crawlTimeoutLog("..Indexer looking through directory %s of %s".
                " to see if orphaned", $dir_cnt, $num_dirs);
            if(strlen(
                $pre_timestamp = strstr($dir, self::queue_base_name)) > 0) {
                $timestamp =
                    substr($pre_timestamp, strlen(self::queue_base_name));
                if(!file_exists(
                    CRAWL_DIR.'/cache/'.
                        self::index_data_base_name.$timestamp)) {
                    $this->db->unlinkRecursive($dir, true);
                }
            }
            if(strlen(
                $pre_timestamp = strstr($dir, self::index_data_base_name)) > 0){
                $timestamp =
                    substr($pre_timestamp, strlen(self::index_data_base_name));
                $close_file = CRAWL_DIR.'/schedules/'. self::index_closed_name.
                    $timestamp.".txt";
                $close_exists = file_exists($close_file);
                $state = "1";
                if($close_exists) {
                    $state = trim(file_get_contents($close_file));
                }
                if((!$close_exists || strcmp($state, "1") != 0) &&
                    $this->crawl_time != $timestamp) {
                    crawlLog("Properly closing Index with Timestamp ".
                        $timestamp. ". Will contain all but last shard before ".
                        "crash.");
                    $index_archive = new IndexArchiveBundle($dir, false);
                    //do a fast merge all
                    $index_archive->dictionary->mergeAllTiers(NULL, -1, true);
                    crawlLog("... index fast merge all tiers completed!");
                    touch(CRAWL_DIR."/schedules/crawl_status.txt", time());
                    file_put_contents($close_file, "1");
                }
                $living_stamps[] = $timestamp;
            }
        }
        $files = glob(CRAWL_DIR.'/schedules/*');
        $file_cnt = 0;
        $names_dir = array(self::schedule_data_base_name,
            self::index_data_base_name, self::robot_data_base_name,
            self::name_archive_iterator, self::fetch_archive_iterator);
        $name_files = array(self::schedule_name, self::index_closed_name,
            self::save_point);
        $names = array_merge($name_files, $names_dir);
        foreach($files as $file) {
            $file_cnt++;
            crawlTimeoutLog("..Indexer still deleting %s of %s orphaned files",
                $file_cnt, $file_cnt);
            $timestamp = "";
            foreach($names as $name) {
                $unlink_flag = false;
                if(strlen(
                    $pre_timestamp = strstr($file, $name)) > 0) {
                    $timestamp =  substr($pre_timestamp, strlen($name), 10);
                    if(in_array($name, $name_files)) {
                        $unlink_flag = true;
                    }
                    break;
                }
            }
            if($timestamp !== "" && !in_array($timestamp, $living_stamps)) {
                if($unlink_flag) {
                    unlink($file);
                } else {
                    $this->db->unlinkRecursive($file, true);
                }
            }
        }
    }
    /**
     * This is a callback method that IndexArchiveBundle will periodically
     * call when it processes a method that take a long time. This
     * allows for instance continued processing of index data while say
     * a dictionary merge is being performed.
     */
    function join()
    {
        /*
           touch crawl_status so it doesn't stop the crawl right
           after it just restarted
         */
        touch(CRAWL_DIR."/schedules/crawl_status.txt", time());
        crawlLog("Rejoin Crawl {{{{");
        $this->processCrawlData(true);
        crawlLog("}}}} End Rejoin Crawl");
    }
    /**
     * Generic function used to process Data, Index, and Robot info schedules
     * Finds the first file in the the direcotry of schedules of the given
     * type, and calls the appropriate callback method for that type.
     *
     * @param string $base_dir directory for of schedules
     * @param string $callback_method what method should be called to handle
     *     a schedule
     * @param boolean $blocking this method might be called by the indexer
     *     subcomponent when a merge tier phase is ongoing to allow for
     *     other processing to occur. If so, we don't want a regress
     *     where the indexer calls this code calls the indexer etc. If
     *     the blocking flag is set then the indexer subcomponent won't
     *     be called
     */
    function processDataFile($base_dir, $callback_method, $blocking = false)
    {
        $dirs = glob($base_dir.'/*', GLOB_ONLYDIR);

        foreach($dirs as $dir) {
            $files = glob($dir.'/*.txt');
            if(isset($old_dir)) {
                crawlLog("Deleting $old_dir\n");
                $this->db->unlinkRecursive($old_dir);
                /* The idea is that only go through outer loop more than once
                   if earlier data directory empty.
                   Note: older directories should only have data dirs or
                   deleting like this might cause problems!
                 */
            }
            foreach($files as $file) {
                $path_parts = pathinfo($file);
                $base_name = $path_parts['basename'];
                $len_name =  strlen(self::data_base_name);
                $file_root_name = substr($base_name, 0, $len_name);

                if(strcmp($file_root_name, self::data_base_name) == 0) {
                    $this->$callback_method($file, $blocking);
                    return;
                }
            }
            $old_dir = $dir;
        }
    }
    /**
     * Determines the most recent fetcher that has spoken with the
     * web server of this queue_server and stored the result in the
     * field variable most_recent_fetcher
     */
    function updateMostRecentFetcher()
    {
        $robot_table_name = CRAWL_DIR."/".self::robot_table_name;
        if(file_exists($robot_table_name)) {
            $robot_table = unserialize(file_get_contents($robot_table_name));
            if(!is_array($robot_table)) { return; }
            $recent = 0 ;
            foreach($robot_table as $robot_name => $robot_data) {
                if($robot_data[2] > $recent) {
                    $this->most_recent_fetcher =  $robot_name;
                    $recent = $robot_data[2];
                }
            }
        }
    }
    /**
     * Sets up the directory to look for a file of unprocessed
     * index archive data from fetchers then calls the function
     * processDataFile to process the oldest file found
     * @param bool $blocking this method might be called by the indexer
     *     subcomponent when a merge tier phase is ongoing to allow for
     *     other processing to occur. If so, we don't want a regress
     *     where the indexer calls this code calls the indexer etc. If
     *     the blocking flag is set then the indexer subcomponent won't
     *     be called
     */
    function processIndexData($blocking)
    {
        crawlLog("Indexer: Checking for index data files to process...");
        $index_dir =  CRAWL_DIR."/schedules/".
            self::index_data_base_name.$this->crawl_time;
        $this->processDataFile($index_dir, "processIndexArchive", $blocking);
        crawlLog("Indexer: done index data check and process.");
    }
    /**
     * Adds the summary and index data in $file to summary bundle and word index
     *
     * @param string $file containing web pages summaries and a mini-inverted
     *     index for their content
     * @param bool $blocking this method might be called by the indexer
     *     subcomponent when a merge tier phase is ongoing to allow for
     *     other processing to occur. If so, we don't want a regress
     *     where the indexer calls this code calls the indexer etc. If
     *     the blocking flag is set then the indexer subcomponent won't
     *     be called
     */
    function processIndexArchive($file, $blocking)
    {
        static $blocked = false;
        if($blocking && $blocked) {
            crawlLog("Indexer waiting for merge tiers to ".
                "complete before write partition. B");
            return;
        }
        if(!$blocking) {
            $blocked = false;
        }
        crawlLog(
            "{$this->server_name} is starting to process index data,".
            " memory usage: ".memory_get_usage() . "...");
        crawlLog("Indexer: Processing index data in $file...");
        $start_time = microtime();
        $start_total_time = microtime();
        $pre_sites = webdecode(file_get_contents($file));
        $len_urls = unpackInt(substr($pre_sites, 0, 4));
        $seen_urls_string = substr($pre_sites, 4, $len_urls);
        $pre_sites = substr($pre_sites, 4 + $len_urls);
        $sites[self::SEEN_URLS] = array();
        $pos = 0;
        $num = 0;
        $bad = false;
        $max_batch_sites_and_links = SEEN_URLS_BEFORE_UPDATE_SCHEDULER *
            (max(MAX_LINKS_PER_PAGE, MAX_LINKS_PER_SITEMAP) + 1);
        while($pos < $len_urls && $num <= $max_batch_sites_and_links) {
            crawlTimeoutLog("..Indexer still processing index data at position".
                " %s of out of %s", $pos, $len_urls);
            $len_site = unpackInt(substr($seen_urls_string, $pos, 4));
            if($len_site > 2 * $this->page_range_request) {
                crawlLog("Indexer: Site string too long, $len_site,".
                    " data file may be corrupted? Skip rest.");
                $bad = true;
                break;
            }
            $pos += 4;
            $site_string = substr($seen_urls_string, $pos, $len_site);
            $pos += strlen($site_string);
            $tmp = unserialize(gzuncompress($site_string));
            if(!$tmp  || !is_array($tmp)) {
                crawlLog("Compressed array null,".
                    " data file may be corrupted? Skip rest.");
                $bad = true;
                break;
            }
            $sites[self::SEEN_URLS][] = $tmp;
            $num++;
        }
        if($num > $max_batch_sites_and_links *
            SEEN_URLS_BEFORE_UPDATE_SCHEDULER || $bad) {
            crawlLog("Index data file len_urls was $len_urls num was $num, ".
                "may be corrupt so skipping this file.");
            crawlLog("Indexer Done Index Processing File: $file. Total time: ".
            changeInMicrotime($start_total_time));
            unlink($file);
            return;
        }
        crawlLog("A. Indexer Load SEEN_URLS. Memory usage:".
            memory_get_usage() ." time: ".(changeInMicrotime($start_time)));
        $sites[self::INVERTED_INDEX] = IndexShard::load("fetcher_shard",
            $pre_sites);
        unset($pre_sites);
        crawlLog("B. Indexer Load Sent shard. Memory usage:".
            memory_get_usage() ." time: ".(changeInMicrotime($start_time)));
        $start_time = microtime();
        //do deduplication of summaries
        if(isset($sites[self::SEEN_URLS]) &&
            count($sites[self::SEEN_URLS]) > 0) {
            $seen_sites = $sites[self::SEEN_URLS];
            $seen_sites = array_values($seen_sites);
            unset($sites[self::SEEN_URLS]);
            $num_seen = count($seen_sites);
            crawlLog("Indexer: SEEN_URLS array had $num_seen sites.");
        } else {
            $num_seen = 0;
        }
        $visited_urls_count = 0;
        $recent_urls_count = 0;
        $recent_urls = array();
        for($i = 0; $i < $num_seen; $i++) {
            $seen_sites[$i][self::HASH_URL] =
                crawlHash($seen_sites[$i][self::URL], true);
            $link_url_parts = explode("|", $seen_sites[$i][self::URL]);
            if(strcmp("url", $link_url_parts[0]) == 0) {
                $reftype =  (strcmp("eref", $link_url_parts[4]) == 0) ?
                    "e" : "i";
                $seen_sites[$i][self::HASH_URL] =
                    crawlHash($link_url_parts[1], true)
                    . crawlHash($seen_sites[$i][self::URL], true)
                    . $reftype. substr(crawlHash(
                      UrlParser::getHost($link_url_parts[5])."/", true), 1);
                $seen_sites[$i][self::IS_DOC] = false;
            } else {
                $seen_sites[$i][self::IS_DOC] = true;
                $visited_urls_count++;
                array_push($recent_urls, $seen_sites[$i][self::URL]);
                if($recent_urls_count >= NUM_RECENT_URLS_TO_DISPLAY) {
                    array_shift($recent_urls);
                }
                $recent_urls_count++;
            }
        }
        if(isset($sites[self::INVERTED_INDEX])) {
            $index_shard =  & $sites[self::INVERTED_INDEX];
            $generation = $this->index_archive->initGenerationToAdd(
                    $index_shard->num_docs, $this, $blocking);
            if($generation == -1) {
                crawlLog("Indexer waiting for merge tiers to ".
                    "complete before write partition. A");
                $blocked = true;
                return;
            }
            $summary_offsets = array();
            if(isset($seen_sites)) {
                $this->index_archive->addPages(
                    $generation, self::SUMMARY_OFFSET, $seen_sites,
                    $visited_urls_count);
                foreach($seen_sites as $site) {
                    if($site[self::IS_DOC]){ // so not link
                        $site_url = str_replace('|', "%7C", $site[self::URL]);
                        $host = UrlParser::getHost($site_url);
                        $hash = crawlHash($site_url, true).
                            $site[self::HASH] .
                            "d". substr(crawlHash($host."/",true), 1);
                    } else {
                        $hash = $site[self::HASH_URL];
                    }
                    $summary_offsets[$hash] = $site[self::SUMMARY_OFFSET];
                }
                unset($seen_sites);
            }
            crawlLog("C. Indexer init local shard, store ".
                "Summaries memory usage:". memory_get_usage() .
                " time: ".(changeInMicrotime($start_time)));
            $start_time = microtime();
            // added summary offset info to inverted index data
            $index_shard->changeDocumentOffsets($summary_offsets);
            crawlLog("D. Indexer Update shard offsets. Memory usage:".
                memory_get_usage() ." time: ".(changeInMicrotime($start_time)));
            $start_time = microtime();
            $this->index_archive->addIndexData($index_shard);
            $this->index_dirty = true;
        }
        crawlLog("E. Indexer Add index shard. Memory usage:".memory_get_usage().
            " time: ".(changeInMicrotime($start_time)));
        crawlLog("Indexer Done Index Processing File: $file. Total time: ".
            changeInMicrotime($start_total_time));
        if(isset($recent_urls)) {
            $sites[self::RECENT_URLS] = & $recent_urls;
            $this->writeCrawlStatus($sites);
        }
        if(file_exists($file)) {
            //Haven't tracked down yet, but can try to delete twice giving warn
            unlink($file);
        }
    }
    /**
     * Checks how old the oldest robot data is and dumps if older then a
     * threshold, then sets up the path to the robot schedule directory
     * and tries to process a file of robots.txt robot paths data from there
     */
    function processRobotUrls()
    {
        if(!isset($this->web_queue) ) {return;}
        crawlLog("Scheduler Checking age of robot data in queue server ");
        if($this->web_queue->getRobotTxtAge() > CACHE_ROBOT_TXT_TIME) {
            $this->deleteRobotData();
            crawlLog("Scheduler: Deleting DNS Cache data..");
            $message = $this->web_queue->emptyDNSCache();
            crawlLog("Scheduler: Delete task responded:\n $message");
        } else {
            crawlLog("Scheduler: ... less than max age\n");
            crawlLog("Scheduler: Number of Crawl-Delayed Hosts: ".floor(count(
                $this->waiting_hosts)/2));
        }
        crawlLog("Scheduler: Checking for robots.txt files to process...");
        $robot_dir =
            CRAWL_DIR."/schedules/".
                self::robot_data_base_name.$this->crawl_time;
        $this->processDataFile($robot_dir, "processRobotArchive");
        crawlLog("Scheduler done robot check and process. ");
    }
    /**
     * Reads in $file of robot data adding host-paths to the disallowed
     * robot filter and setting the delay in the delay filter of
     * crawled delayed hosts
     * @param string $file file to read of robot data, is removed after
     *     processing
     */
    function processRobotArchive($file)
    {
        crawlLog("Scheduler Processing Robots data in $file");
        $start_time = microtime();
        $sites = unserialize(gzuncompress(webdecode(file_get_contents($file))));
        crawlLog("Scheduler done decompressing robot file");
        if(isset($sites)) {
            $num_sites = count($sites);
            $i = 0;
            foreach($sites as $robot_host => $robot_info) {
                crawlTimeoutLog("..Scheduler processing robot item %s of %s.",
                    $i, $num_sites);
                $i++;
                $this->web_queue->addGotRobotTxtFilter($robot_host);
                $scheme = UrlParser::getScheme($robot_host);
                if($scheme == "gopher") {
                    $robot_url = $robot_host."/0/robots.txt";
                } else {
                    $robot_url = $robot_host."/robots.txt";
                }
                if($this->web_queue->containsUrlQueue($robot_url)) {
                    crawlLog("Scheduler Removing $robot_url from queue");
                    $this->web_queue->removeQueue($robot_url);
                }
                if(isset($robot_info[self::CRAWL_DELAY])) {
                    $this->web_queue->setCrawlDelay($robot_host,
                        $robot_info[self::CRAWL_DELAY]);
                }
                if(isset($robot_info[self::ROBOT_PATHS])) {
                    $this->web_queue->addRobotPaths($robot_host,
                        $robot_info[self::ROBOT_PATHS]);
                }
                if(isset($robot_info[self::IP_ADDRESSES])) {
                    $final_ip = array_pop($robot_info[self::IP_ADDRESSES]);
                    $this->web_queue->addDNSCache($robot_host, $final_ip);
                }
            }
        }
        crawlLog("Scheduler Robot time: ".
            (changeInMicrotime($start_time))."\n");
        crawlLog("Scheduler Done Robots Processing File: $file");
        unlink($file);
    }
    /**
     * Process cache page validation data files sent by Fetcher
     */
    function processEtagExpires()
    {
        crawlLog("Scheduler Checking for etag expires http header data");
        $etag_expires_dir = CRAWL_DIR."/schedules/".
            self::etag_expires_data_base_name.$this->crawl_time;
        $this->processDataFile($etag_expires_dir,
            "processEtagExpiresArchive");
        crawlLog("Scheduler done etag check and process.");
    }
    /**
     * Processes a cache page validation data file. Extracts key-value pairs
     * from the file and inserts into the B-Tree used for storing cache
     * page validation data.
     * @param string $file is the cache page validation data file written by
     * Fetchers.
     */
    function processEtagExpiresArchive($file)
    {
        crawlLog("Scheduler Processing etag expires http header data in $file");
        $start_time = microtime();
        $etag_expires_data =
            unserialize(gzuncompress(webdecode(file_get_contents($file))));
        crawlLog("Scheduler Done uncompressing etag data.".
            " Starting to add to btree");
        $num_entries = count($etag_expires_data);
        $i = 0;
        foreach($etag_expires_data as $data) {
            crawlTimeoutLog("..Scheduler still etag processing on item %s of %s.",
                $i, $num_entries);
            $i++;
            $link = $data[0];
            $value = $data[1];
            $key = crawlHash($link, true);
            $entry = array($key, $value);
            $this->web_queue->etag_btree->insert($entry);
        }
        crawlLog(" time: ".(changeInMicrotime($start_time))."\n");
        crawlLog("Scheduler Done processing etag expires http".
            " header data file: $file");
        unlink($file);
    }
    /**
     * Deletes all Robot informations stored by the QueueServer.
     *
     * This function is called roughly every CACHE_ROBOT_TXT_TIME.
     * It forces the crawler to redownload robots.txt files before hosts
     * can be continued to be crawled. This ensures if the cache robots.txt
     * file is never too old. Thus, if someone changes it to allow or disallow
     * the crawler it will be noticed reasonably promptly.
     */
    function deleteRobotData()
    {
        crawlLog("... Scheduler: unlinking robot schedule files ...");

        $robot_schedules = CRAWL_DIR.'/schedules/'.
            self::robot_data_base_name.$this->crawl_time;
        $this->db->unlinkRecursive($robot_schedules, true);

        crawlLog("... Scheduler: resetting robot data files ...");
        $message = $this->web_queue->emptyRobotData();
        crawlLog("... Scheduler: resetting robot task answered:\n$message\n");

        crawlLog("...Scheduler: Clearing Waiting Hosts");
        $this->waiting_hosts = array();
    }
    /**
     * Checks for a new crawl file or a schedule data for the current crawl and
     * if such a exists then processes its contents adding the relevant urls to
     * the priority queue
     *
     * @return array info array with continue status
     */
    function processQueueUrls()
    {
        crawlLog("Scheduler Start checking for new URLs data memory usage: ".
            memory_get_usage());
        $info = array();
        $info[self::STATUS] = self::CONTINUE_STATE;
        if(file_exists(CRAWL_DIR."/schedules/".self::schedule_start_name)) {
            crawlLog(
                "Scheduler Start schedule urls".CRAWL_DIR.
                    "/schedules/".self::schedule_start_name);
            $this->processDataArchive(
                CRAWL_DIR."/schedules/".self::schedule_start_name);
            return $info;
        }
        $schedule_dir =
            CRAWL_DIR."/schedules/".
                self::schedule_data_base_name.$this->crawl_time;
        $this->processDataFile($schedule_dir, "processDataArchive");
        crawlLog("done.");
        return $info;
    }
    /**
     * Process a file of to-crawl urls adding to or adjusting the weight in
     * the PriorityQueue of those which have not been seen. Also
     * updates the queue with seen url info
     *
     * @param string $file containing serialized to crawl and seen url info
     */
    function processDataArchive($file)
    {
        $sites = & $this->getDataArchiveFileData($file);
        crawlLog("...Scheduler Updating Delayed Hosts Array Queue ...");
        $start_time = microtime();
        if(isset($sites[self::SCHEDULE_TIME])) {
            if(isset($this->waiting_hosts[$sites[self::SCHEDULE_TIME]])) {
                $delayed_hosts =
                    $this->waiting_hosts[$sites[self::SCHEDULE_TIME]];
                unset($this->waiting_hosts[$sites[self::SCHEDULE_TIME]]);
                foreach($delayed_hosts as $hash_host) {
                    unset($this->waiting_hosts[$hash_host]);
                        //allows crawl-delayed host to be scheduled again
                }
                crawlLog("Scheduler Done removing host delayed for schedule ".
                    $sites[self::SCHEDULE_TIME]);
                $now = time(); /* no schedule should take more than one hour
                    on the other hand schedule data might be waiting for days
                    before being processed. So we clear out waiting hosts
                    that have waited more than an hour
                */
                foreach($this->waiting_hosts as $key => $value) {
                    crawlTimeoutLog(
                        "..still scheduler removing waiting hosts..");
                    if(is_array($value)) {
                        if(intval($key) + ONE_HOUR < $now) {
                            unset($this->waiting_hosts[$key]);
                        }
                    } else {
                        if(intval($value) + ONE_HOUR < $now) {
                            unset($this->waiting_hosts[$key]);
                        }
                    }
                }
            }
        }
        crawlLog(" time: ".(changeInMicrotime($start_time)));
        crawlLog("...Remove Seen Urls Queue...");
        $start_time = microtime();
        if(isset($sites[self::HASH_SEEN_URLS])) {
            $cnt = 0;
            foreach($sites[self::HASH_SEEN_URLS] as $hash_url) {
                if($this->web_queue->lookupHashTable($hash_url)) {
                    crawlLog("Scheduler: Removing hash ".
                        base64_encode($hash_url).
                        " from Queue");
                    $this->web_queue->removeQueue($hash_url, true);
                }
            }
        }
        crawlLog(" time: ".(changeInMicrotime($start_time)));
        crawlLog("... Scheduler Queueing To Crawl ...");
        $start_time = microtime();
        if(isset($sites[self::TO_CRAWL])) {
            crawlLog("A.. Scheduler: Queue delete previously  ".
                "seen urls from add set");
            $to_crawl_sites = & $sites[self::TO_CRAWL];
            $this->deleteSeenUrls($to_crawl_sites);
            crawlLog(" time: ".(changeInMicrotime($start_time)));
            crawlLog("B.. Scheduler: Queue insert unseen robots.txt urls;".
                "adjust changed weights");
            $start_time = microtime();
            $cnt = 0;
            $num_triples = count($to_crawl_sites);
            $added_urls = array();
            $added_pairs = array();
            $contains_host = array();
            /* if we allow web page recrawls then check here to see if delete
               url filter*/
            if($this->page_recrawl_frequency > 0 &&
                $this->web_queue->getUrlFilterAge() >
                ONE_DAY * $this->page_recrawl_frequency) {
                crawlLog("Scheduler: Emptying queue page url filter!!!!!!");
                $this->web_queue->emptyUrlFilter();
            }
            $this->allow_disallow_cache_time = microtime();
            foreach($to_crawl_sites as $triple) {
                $url = & $triple[0];
                $cnt++;
                crawlTimeoutLog("..Scheduler: Processing url %s of %s ",
                    $cnt, $num_triples);
                if(strlen($url) < 7) { // strlen("http://")
                    continue;
                }
                if($url[0] != 'h' && trim($url) == "localhost") {
                    $url = "http://localhost/";
                }
                $weight = $triple[1];
                $this->web_queue->addSeenUrlFilter($triple[2]); //add for dedup
                unset($triple[2]); // so triple is now a pair
                $host_url = UrlParser::getHost($url);
                if(strlen($host_url) < 7) { // strlen("http://")
                    continue;
                }
                $scheme = UrlParser::getScheme($host_url);
                if($scheme == "gopher") {
                    $host_with_robots = $host_url."/0/robots.txt";
                } else {
                    $host_with_robots = $host_url."/robots.txt";
                }
                $robots_in_queue =
                    $this->web_queue->containsUrlQueue($host_with_robots);
                if($this->web_queue->containsUrlQueue($url)) {
                    if($robots_in_queue) {
                        $this->web_queue->adjustQueueWeight(
                            $host_with_robots, $weight, false);
                    }
                    $this->web_queue->adjustQueueWeight($url,
                        $weight, false);
                } else if($this->allowedToCrawlSite($url) &&
                    !$this->disallowedToCrawlSite($url)) {
                    if(!$this->web_queue->containsGotRobotTxt($host_url)
                        && !$robots_in_queue
                        && !isset($added_urls[$host_with_robots])
                        ) {
                            $added_pairs[] = array($host_with_robots, $weight);
                            $added_urls[$host_with_robots] = true;
                    }
                    if(!isset($added_urls[$url])) {
                        $added_pairs[] = $triple; // see comment above
                        $added_urls[$url] = true;
                    }
                }
            }
            $this->web_queue->notifyFlush();

            crawlLog(" time: ".(changeInMicrotime($start_time)));

            crawlLog("C.. Scheduler: Add urls to queue");
            $start_time = microtime();
            /*
                 adding urls to queue involves disk contains and adjust do not
                 so group and do last
             */
            $this->web_queue->addUrlsQueue($added_pairs);
        }
        crawlLog("Scheduler: time: ".(changeInMicrotime($start_time)));
        crawlLog("Scheduler: Done queue schedule file: $file");
        unlink($file);
    }
    /**
     * Writes status information about the current crawl so that the webserver
     * app can use it for its display.
     *
     * @param array $sites contains the most recently crawled sites
     */
    function writeCrawlStatus(&$sites)
    {
        $crawl_status = array();
        $stat_file = CRAWL_DIR."/schedules/crawl_status.txt";
        if(file_exists($stat_file) ) {
            $crawl_status = unserialize(file_get_contents($stat_file));
            if(!isset($crawl_status['CRAWL_TIME']) ||
                $crawl_status['CRAWL_TIME'] != $this->crawl_time) {
                $crawl_status = array(); // status of some other crawl
            }
        }
        $crawl_status['MOST_RECENT_FETCHER'] = $this->most_recent_fetcher;
        if(isset($sites[self::RECENT_URLS])) {
            $crawl_status['MOST_RECENT_URLS_SEEN'] = $sites[self::RECENT_URLS];
        }
        $crawl_status['CRAWL_TIME'] = $this->crawl_time;
        $info_bundle = IndexArchiveBundle::getArchiveInfo(
            CRAWL_DIR.'/cache/'.self::index_data_base_name.$this->crawl_time);
        $index_archive_info = unserialize($info_bundle['DESCRIPTION']);
        $crawl_status['COUNT'] = $info_bundle['COUNT'];
        $now = time();
        $change_in_time = ONE_HOUR + 1;
        while (count($this->hourly_crawl_data) > 0 &&
            $change_in_time > ONE_HOUR) {
            $least_recent_hourly_pair = array_pop($this->hourly_crawl_data);
            $change_in_time =
                ($now - $least_recent_hourly_pair[0]);
        }
        if($change_in_time <= ONE_HOUR) {
            $this->hourly_crawl_data[] = $least_recent_hourly_pair;
        }
        array_unshift($this->hourly_crawl_data,
            array($now, $info_bundle['VISITED_URLS_COUNT']));
        $crawl_status['VISITED_COUNT_HISTORY'] = $this->hourly_crawl_data;
        $crawl_status['VISITED_URLS_COUNT'] =$info_bundle['VISITED_URLS_COUNT'];
        $crawl_status['DESCRIPTION'] = $index_archive_info['DESCRIPTION'];
        $crawl_status['QUEUE_PEAK_MEMORY'] = memory_get_peak_usage();
        file_put_contents($stat_file, serialize($crawl_status), LOCK_EX);
        chmod($stat_file, 0777);
        crawlLog(
            "End checking for new URLs data memory usage".memory_get_usage());
        crawlLog(
            "The current crawl description is: ".
                $index_archive_info['DESCRIPTION']);
        crawlLog("Number of unique pages so far: ".
            $info_bundle['VISITED_URLS_COUNT']);
        crawlLog("Total urls extracted so far: ".$info_bundle['COUNT']);
        if(isset($sites[self::RECENT_URLS])) {
            crawlLog("Of these, the most recent urls are:");
            foreach($sites[self::RECENT_URLS] as $url) {
                crawlLog("URL: ".iconv("UTF-8", "ISO-8859-1//IGNORE", $url));
            }
        }
    }
    /**
     * Removes the already seen urls from the supplied array
     *
     * @param array& $sites url data to check if seen
     */
    function deleteSeenUrls(&$sites)
    {
        $this->web_queue->differenceSeenUrls($sites, array(0, 2));
    }
    /**
     * Used to create encode a string representing with meta info for
     * a fetcher schedule.
     *
     * @param int $schedule_time timestamp of the schedule
     * @return string base64 encoded meta info
     */
    function calculateScheduleMetaInfo($schedule_time)
    {
        //notice does not contain self::QUEUE_SERVERS
        $sites = array();
        $sites[self::CRAWL_TIME] = $this->crawl_time;
        $sites[self::SCHEDULE_TIME] = $schedule_time;
        $sites[self::CRAWL_ORDER] = $this->crawl_order;
        $sites[self::CRAWL_TYPE] = $this->crawl_type;
        $sites[self::CRAWL_INDEX] = $this->crawl_index;
        $sites[self::CACHE_PAGES] = $this->cache_pages;
        $sites[self::PAGE_RULES] = $this->page_rules;
        $sites[self::RESTRICT_SITES_BY_URL] = $this->restrict_sites_by_url;
        $sites[self::INDEXED_FILE_TYPES] = $this->indexed_file_types;
        $sites[self::ALLOWED_SITES] = $this->allowed_sites;
        $sites[self::DISALLOWED_SITES] = $this->disallowed_sites;
        $sites[self::INDEXING_PLUGINS] =  $this->indexing_plugins;
        $sites[self::INDEXING_PLUGINS_DATA] =  $this->indexing_plugins_data;
        $sites[self::VIDEO_SOURCES] = $this->video_sources;
        $sites[self::PAGE_RANGE_REQUEST] = $this->page_range_request;
        $sites[self::MAX_DESCRIPTION_LEN] = $this->max_description_len;
        $sites[self::POST_MAX_SIZE] = metricToInt(ini_get("post_max_size"));
        $sites[self::SITES] = array();

        return base64_encode(serialize($sites))."\n";
    }
    /**
     * Produces a schedule.txt file of url data for a fetcher to crawl next.
     *
     * The hard part of scheduling is to make sure that the overall crawl
     * process obeys robots.txt files. This involves checking the url is in
     * an allowed path for that host and it also involves making sure the
     * Crawl-delay directive is respected. The first fetcher that contacts the
     * server requesting data to crawl will get the schedule.txt
     * produced by produceFetchBatch() at which point it will be unlinked
     * (these latter thing are controlled in FetchController).
     *
     * @see FetchController
     */
    function produceFetchBatch()
    {
        $i = 1; // array implementation of priority queue starts at 1 not 0
        $fetch_size = 0;
        crawlLog("Scheduler: Start Produce Fetch Batch Memory usage".
            memory_get_usage() );
        $count = $this->web_queue->to_crawl_queue->count;
        $schedule_time = time();
        $first_line = $this->calculateScheduleMetaInfo($schedule_time);
        $sites = array();
        $delete_urls = array();
        $crawl_delay_hosts = array();
        $time_per_request_guess = MINIMUM_FETCH_LOOP_TIME ;
            // it would be impressive if we can achieve this speed
        $current_crawl_index = -1;
        crawlLog("Scheduler: Trying to Produce Fetch Batch; Queue Size $count");
        $start_time = microtime();
        $fh = $this->web_queue->openUrlArchive();
        /*
            $delete - array of items we will delete from the queue after
                we have selected all of the items for fetch batch
            $sites - array of urls for fetch batch indices in this array we'll
                call slots. Crawled-delayed host urls are spaced by a certain
                number of slots
        */
        while ($i <= $count && $fetch_size < MAX_FETCH_SIZE) {
            crawlTimeoutLog("..Scheduler: still producing fetch batch. ".
                "Examining location %s in queue of %s.", $i, $count);
            //look in queue for url and its weight
            $tmp = $this->web_queue->peekQueue($i, $fh);
            list($url, $weight, $flag, $probe) = $tmp;
            // if queue error remove entry any loop
            if($tmp === false || strcmp($url, "LOOKUP ERROR") == 0) {
                $delete_urls[$i] = false;
                crawlLog("Scheduler: Removing lookup error at".
                    " $i during produce fetch");
                $i++;
                continue;
            }
            $no_flags = false;
            $hard_coded = false;
            $host_url = UrlParser::getHost($url);
            if($flag ==  WebQueueBundle::NO_FLAGS) {
                $hard_coded_pos = strpos($url, "###!");
                if($hard_coded_pos > 0 ) {
                    $has_robots = true;
                    $hard_coded = true;
                    $is_robot = false;
                } else {
                    $has_robots =
                        $this->web_queue->containsGotRobotTxt($host_url);
                    $scheme = UrlParser::getScheme($host_url);
                    if($scheme == "gopher") {
                        $is_robot =
                            (strcmp($host_url."/0/robots.txt", $url) == 0);
                    } else {
                        $is_robot =
                            (strcmp($host_url."/robots.txt", $url) == 0);
                    }
                }
                $no_flags = true;
            } else {
                $is_robot = ($flag == WebQueueBundle::ROBOT);
                if($flag >= WebQueueBundle::SCHEDULABLE) {
                    $has_robots = true;
                    if($flag > WebQueueBundle::SCHEDULABLE) {
                        $delay = $flag - WebQueueBundle::SCHEDULABLE;
                    }
                }
            }
            //if $url is a robots.txt url see if we need to schedule or not
            if($is_robot) {
                if($has_robots) {
                    $delete_urls[$i] = $url;
                    $i++;
                } else {
                    $next_slot = $this->getEarliestSlot($current_crawl_index,
                        $sites);
                    if($next_slot < MAX_FETCH_SIZE) {
                        $sites[$next_slot] =
                            array($url, $weight, 0);
                        $delete_urls[$i] = $url;
                        /* note don't add to seen url filter
                           since check robots every 24 hours as needed
                         */
                        $current_crawl_index = $next_slot;
                        $fetch_size++;
                        $i++;
                    } else { //no more available slots so prepare to bail
                        $i = $count;
                        if($no_flags) {
                            $this->web_queue->setQueueFlag($url,
                                WebQueueBundle::ROBOT);
                        }
                    }
                }
                continue;
            }
            //Now handle the non-robots.txt url case
            $robots_okay = true;
            if($has_robots) {
                if($no_flags) {
                    if(!isset($hard_coded) || !$hard_coded) {
                        $robots_okay = $this->web_queue->checkRobotOkay($url);
                    } else {
                        $robots_okay = true;
                    }
                    if(!$robots_okay) {
                        $delete_urls[$i] = $url;
                        $this->web_queue->addSeenUrlFilter($url);
                        $i++;
                        continue;
                    }
                    $delay = $this->web_queue->getCrawlDelay($host_url);
                }

                if(!$this->withinQuota($url)) {
                    //we've not allowed to schedule $url till next hour
                    $delete_urls[$i] = $url;
                    //delete from queue (so no clog) but don't mark seen
                    $i++;
                    continue;
                }
                //each host has two entries in $this->waiting_hosts
                $num_waiting = floor(count($this->waiting_hosts)/2);
                if($delay > 0 ) {
                    // handle adding a url if there is a crawl delay
                    $hash_host = crawlHash($host_url);
                    $is_waiting_host = isset($this->waiting_hosts[$hash_host]);
                    if((!$is_waiting_host
                        && $num_waiting < MAX_WAITING_HOSTS) ||
                        $is_waiting_host && $this->waiting_hosts[$hash_host] ==
                        $schedule_time) {
                        $this->waiting_hosts[$hash_host] =
                           $schedule_time;
                        $this->waiting_hosts[$schedule_time][] =
                            $hash_host;
                        $request_batches_per_delay =
                            ceil($delay/$time_per_request_guess);

                        if(!isset($crawl_delay_hosts[$hash_host])) {
                            $next_earliest_slot = $current_crawl_index;
                            $crawl_delay_hosts[$hash_host]= $next_earliest_slot;
                        } else {
                            $next_earliest_slot = $crawl_delay_hosts[$hash_host]
                                + $request_batches_per_delay
                                * NUM_MULTI_CURL_PAGES;
                        }
                        if(($next_slot =
                            $this->getEarliestSlot( $next_earliest_slot,
                                $sites)) < MAX_FETCH_SIZE) {
                            $crawl_delay_hosts[$hash_host] = $next_slot;
                            $delete_urls[$i] = $url;
                            $sites[$next_slot] =
                                array($url, $weight, $delay);
                            $this->web_queue->addSeenUrlFilter($url);
                            /* we might miss some sites by marking them
                               seen after only scheduling them
                             */
                            $fetch_size++;
                        } else if ($no_flags) {
                            $this->web_queue->setQueueFlag($url,
                                $delay + WebQueueBundle::SCHEDULABLE);
                        }
                    } else if(!$is_waiting_host) {
                        // has crawl delay but too many already waiting
                        $delete_urls[$i] = $url;
                        //delete from queue (so no clog) but don't mark seen
                        $i++;
                        continue;
                    }
                } else { // add a url no crawl delay
                    $next_slot = $this->getEarliestSlot(
                        $current_crawl_index, $sites);
                    if($next_slot < MAX_FETCH_SIZE) {
                        $sites[$next_slot] =
                            array($url, $weight, 0);
                        $delete_urls[$i] = $url;
                        $this->web_queue->addSeenUrlFilter($url);
                            /* we might miss some sites by marking them
                               seen after only scheduling them
                             */
                        $current_crawl_index = $next_slot;
                        $fetch_size++;
                    } else { //no more available slots so prepare to bail
                        $i = $count;
                        if($no_flags) {
                            $this->web_queue->setQueueFlag($url,
                                WebQueueBundle::SCHEDULABLE);
                        }
                    }
                } //if delay else
            } // if containsGotRobotTxt
            // handle robots.txt urls
            $i++;
        } //end while
        $this->web_queue->closeUrlArchive($fh);
        $new_time = microtime();
        crawlLog("...Scheduler: Done selecting URLS for fetch batch time ".
            "so far:". (changeInMicrotime($start_time)));
        $num_deletes = count($delete_urls);
        $k = 0;
        foreach($delete_urls as $delete_url) {
            $k++;
            crawlTimeoutLog("..Removing selected url %s of %s ".
                "from queue.", $k, $num_deletes);
            if($delete_url) {
                $this->web_queue->removeQueue($delete_url);
            } else {
                /*  if there was a hash table look up error still get rid of
                    index from priority queue */
                $this->web_queue->to_crawl_queue->poll($k);
            }
        }
        crawlLog("...Scheduler: Removed $k URLS for fetch batch from ".
            "queue in time: ".(changeInMicrotime($new_time)));
        $new_time = microtime();
        if(isset($sites) && count($sites) > 0 ) {
            $dummy_slot = array(self::DUMMY, 0.0, 0);
            /* dummy's are used for crawl delays of sites with longer delays
               when we don't have much else to crawl.
             */
            $cnt = 0;
            for($j = 0; $j < MAX_FETCH_SIZE; $j++) {
                if(isset( $sites[$j])) {
                    $cnt++;
                    if($cnt == $fetch_size) {break; }
                } else {
                    if($j % NUM_MULTI_CURL_PAGES == 0) {
                        $sites[$j] = $dummy_slot;
                    }
                }
            }
            ksort($sites);
            //write schedule to disk
            $fh = fopen(CRAWL_DIR.
                "/schedules/".
                self::schedule_name.$this->crawl_time.".txt", "wb");
            fwrite($fh, $first_line);
            $num_sites = count($sites);
            $k = 0;
            foreach($sites as $site) {
                crawlTimeoutLog("..Scheduler: Still Writing fetch schedule %s".
                    " of %s.", $k, $num_sites);
                $k++;
                $extracted_etag = NULL;
                list($url, $weight, $delay) = $site;
                $key = crawlHash($url, true);
                if(USE_ETAG_EXPIRES) {
                /*check if we have cache validation data for a URL. If both
                  ETag and Expires timestamp are found or only an expires
                  timestamp is found, the timestamp is compared with the current
                  time. If the current time is less than the expires timestamp,
                  the URL is not added to the fetch batch. If only an ETag is
                  found, the ETag is appended to the URL so that it can be
                  processed by the fetcher.
                 */
                    $value = $this->web_queue->etag_btree->findValue($key);
                    if($value !== NULL) {
                        $cache_validation_data = $value[1];
                        if($cache_validation_data['etag'] !== -1 &&
                            $cache_validation_data['expires'] !== -1) {
                            $expires_timestamp =
                                $cache_validation_data['expires'];
                            $current_time = time();
                            if($current_time < $expires_timestamp) {
                                continue;
                            } else {
                                $etag = $cache_validation_data['etag'];
                                $extracted_etag = "ETag: ".$etag;
                            }
                        } else if($cache_validation_data['etag'] !== -1) {
                            $etag = $cache_validation_data['etag'];
                            $extracted_etag = "ETag: ".$etag;
                        } else if($cache_validation_data['expires'] !== -1){
                            $expires_timestamp =
                                $cache_validation_data['expires'];
                            $current_time = time();
                            if($current_time < $expires_timestamp) {
                                continue;
                            }
                        }
                    }
                }
                $host_url = UrlParser::getHost($url);
                $dns_lookup = $this->web_queue->dnsLookup($host_url);
                if($dns_lookup) {
                    $url .= "###".urlencode($dns_lookup);
                }
                if($extracted_etag !== NULL) {
                    $url .= $extracted_etag;
                }
                $out_string = base64_encode(
                    packFloat($weight).packInt($delay).$url)."\n";
                fwrite($fh, $out_string);
            }
            fclose($fh);
            crawlLog("...Scheduler: Sort URLS and write schedule time: ".
                (changeInMicrotime($new_time)));
            crawlLog("Scheduler: End Produce Fetch Batch Memory usage".
                memory_get_usage() );
            crawlLog("Scheduler: Created fetch batch of size $num_sites.".
                " $num_deletes urls were deleted.".
                " Queue size is now ". $this->web_queue->to_crawl_queue->count.
                "...Total Time to create batch: ".
                (changeInMicrotime($start_time)));
        } else {
            crawlLog("Scheduler: No fetch batch created!! " .
                "Time failing to make a fetch batch:" .
                (changeInMicrotime($start_time)).". Loop properties:$i $count".
                " $num_deletes urls were deleted in failed attempt.");
            $max_links = max(MAX_LINKS_PER_PAGE, MAX_LINKS_PER_SITEMAP);
            if($num_deletes < 5 && $i >= $count &&
                    $count >= NUM_URLS_QUEUE_RAM -
                    SEEN_URLS_BEFORE_UPDATE_SCHEDULER * $max_links) {
                crawlLog("Scheduler: Queue Full and Couldn't produce Fetch ".
                    "Batch!! Or Delete any URLS!!!");
                crawlLog("Scheduler: Rescheduling Queue Contents ".
                    "(not marking seen) to try to unjam!");
                $this->dumpQueueToSchedules(true);
                $this->clearWebQueue();
            }
        }
    }
    /**
     * Gets the first unfilled schedule slot after $index in $arr
     *
     * A schedule of sites for a fetcher to crawl consists of MAX_FETCH_SIZE
     * many slots earch of which could eventually hold url information.
     * This function is used to schedule slots for crawl-delayed host.
     *
     * @param int $index location to begin searching for an empty slot
     * @param array& $arr list of slots to look in
     * @return int index of first available slot
     */
    function getEarliestSlot($index, &$arr)
    {
        $cnt = count($arr);
        for( $i = $index + 1; $i < $cnt; $i++) {
            if(!isset($arr[$i] ) ) {
                break;
            }
        }
        return $i;
    }
    /**
     * Used to remove from the queue urls that are no longer crawlable
     * because the allowed and disallowed sites have changed.
     */
    function cullNoncrawlableSites()
    {
        $count = $this->web_queue->to_crawl_queue->count;
        crawlLog("Scheduler: ".
            " Culling noncrawlable urls after change in crawl parameters;".
            " Queue Size $count");
        $start_time = microtime();
        $fh = $this->web_queue->openUrlArchive();
        $delete_urls = array();
        $i = 1;
        while($i < $count) {
            crawlTimeoutLog("..Scheduler: ".
                "still culling noncrawlable urls. Examining ".
                "location %s in queue of %s.", $i, $count);
            $tmp = $this->web_queue->peekQueue($i, $fh);
            list($url, $weight, $flag, $probe) = $tmp;
            if(!$this->allowedToCrawlSite($url) ||
                $this->disallowedToCrawlSite($url)) {
                $delete_urls[] = $url;
            }
            $i++;
        }
        $this->web_queue->closeUrlArchive($fh);
        $new_time = microtime();
        crawlLog("...Scheduler: Done selecting cullable URLS, time so far:".
            (changeInMicrotime($start_time)));
        $this->web_queue->closeUrlArchive($fh);
        $new_time = microtime();
        $num_deletes = count($delete_urls);
        $k = 0;
        foreach($delete_urls as $delete_url) {
            $k++;
            crawlTimeoutLog("..Scheduler: Removing selected url %s of %s ".
                "from queue.", $k, $num_deletes);
            if($delete_url) {
                $this->web_queue->removeQueue($delete_url);
            } else {
                /*  if there was a hash table look up error still get rid of
                    index from priority queue */
                $this->web_queue->to_crawl_queue->poll($k);
            }
        }
        crawlLog("...Scheduler: Removed $k cullable URLS  from queue in time: ".
            (changeInMicrotime($new_time)));
    }
    /**
     * Checks if url belongs to a list of sites that are allowed to be
     * crawled and that the file type is crawlable
     *
     * @param string $url url to check
     * @return bool whether is allowed to be crawled or not
     */
    function allowedToCrawlSite($url)
    {
        $doc_type = UrlParser::getDocumentType($url);
        if(!in_array($doc_type, $this->all_file_types)) {
            $doc_type = "unknown";
        }
        if(!in_array($doc_type, $this->indexed_file_types)) {
            return false;
        }
        if($this->restrict_sites_by_url) {
           return UrlParser::urlMemberSiteArray($url, $this->allowed_sites,
                "a".$this->allow_disallow_cache_time);
        }
        return true;
    }
    /**
     * Checks if url belongs to a list of sites that aren't supposed to be
     * crawled
     *
     * @param string $url url to check
     * @return bool whether is shouldn't be crawled
     */
    function disallowedToCrawlSite($url)
    {
        return UrlParser::urlMemberSiteArray($url, $this->disallowed_sites,
            "d".$this->allow_disallow_cache_time);
    }
    /**
     * Checks if the $url is from a site which has an hourly quota to download.
     * If so, it bumps the quota count and return true; false otherwise.
     * This method also resets the quota queue every over
     *
     * @param string $url to check if within quota
     * @return bool whether $url exceeds the hourly quota of the site it is from
     */
    function withinQuota($url)
    {
        if(!($site = UrlParser::urlMemberSiteArray(
            $url, $this->quota_sites_keys, "q".$this->allow_disallow_cache_time,
            true))) {
            return true;
        }
        list($quota, $current_count) = $this->quota_sites[$site];
        if($current_count < $quota) {
            $this->quota_sites[$site] = array($quota, $current_count + 1);
            $flag = true;
        } else {
            $flag = false;
        }
        if($this->quota_clear_time + ONE_HOUR < time()) {
            $this->quota_clear_time = time();
            foreach ($this->quota_sites as $site => $info) {
                list($quota,) = $info;
                $this->quota_sites[$site] = array($quota, 0);
            }
        }
        return $flag;
    }
}
if(!defined("UNIT_TEST_MODE")) {
    /*
     * Instantiate and runs the QueueSever
     */
    $queue_server =  new QueueServer($INDEXED_FILE_TYPES);
    $queue_server->start();
}
?>
