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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

/** Calculate base directory of script */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0, 
    -strlen("/bin")));

ini_set("memory_limit","900M"); //so have enough memory to crawl big pages

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

/** For crawlDaemon function  */
require_once BASE_DIR."/lib/crawl_daemon.php"; 
/**  */
require_once BASE_DIR."/lib/fetch_url.php";
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");


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
class QueueServer implements CrawlConstants
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
     * Constant saying the method used to order the priority queue for the crawl
     * @var string
     */
    var $crawl_order;
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
     *
     */
    function __construct($indexed_file_types) 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();
        $this->indexed_file_types = $indexed_file_types;
        $this->most_recent_fetcher = "No Fetcher has spoken with me";

        //the next values be set for real in startCrawl
        $this->crawl_order = self::PAGE_IMPORTANCE; 
        $this->restrict_sites_by_url = true;
        $this->allowed_sites = array();
        $this->disallowed_sites = array();
    }

    /**
     * This is the function that should be called to get the queue_server 
     * to start. Calls init to handle the command line arguments then enters 
     * the queue_server's main loop
     */
    function start()
    {
        global $argv;

        declare(ticks=1);
        CrawlDaemon::init($argv, "queue_server");

        $this->loop();

    }

    /**
     *
     */
    function loop()
    {
        $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
        crawlLog("In queue loop!!", "queue_server");

        while ($info[self::STATUS] != self::STOP_STATE) {
            crawlLog("Peak memory usage so far".memory_get_peak_usage()."!!");

            $info = $this->handleAdminMessages($info);

            if( $info[self::STATUS] == self::WAITING_START_MESSAGE_STATE) {
                crawlLog("Waiting for start message\n");
                sleep(5);
                continue;
            }

            if($info[self::STATUS] == self::STOP_STATE) {
                continue;
            }
            
            //check for orphaned queue bundles
            $this->deleteOrphanedBundles();

            $count = $this->web_queue->to_crawl_queue->count;

            $this->processIndexData();

            $this->processRobotUrls();

            if($count < NUM_URLS_QUEUE_RAM - 
                SEEN_URLS_BEFORE_UPDATE_SCHEDULER * MAX_LINKS_PER_PAGE) {
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

                if(!file_exists(CRAWL_DIR."/schedules/schedule.txt")) {
                    $this->produceFetchBatch();
                }
            }

            crawlLog("Taking five second sleep...");
            sleep(5);
        }

        crawlLog("Queue Server shutting down!!");
    }

    /**
     *
     */
    function handleAdminMessages($info) 
    {
        if(file_exists(CRAWL_DIR."/schedules/queue_server_messages.txt")) {
            $info = unserialize(file_get_contents(
                CRAWL_DIR."/schedules/queue_server_messages.txt"));

            unlink(CRAWL_DIR."/schedules/queue_server_messages.txt");
            switch($info[self::STATUS])
            {
                case "NEW_CRAWL":
                    $this->startCrawl($info);
                    crawlLog(
                        "Starting new crawl. Timestamp:".$this->crawl_time);
                break;

                case "STOP_CRAWL":
                    if(file_exists(CRAWL_DIR."/schedules/crawl_status.txt")) {
                        unlink(CRAWL_DIR."/schedules/crawl_status.txt");
                    }
                    if(isset($this->index_archive)) {
                        $this->index_archive->forceSave();
                        // chmod so apahce can also write to these directories
                        $this->db->setWorldPermissionsRecursive(
                            CRAWL_DIR.'/cache/'.
                            self::index_data_base_name.$this->crawl_time);
                    }
                    crawlLog("Stopping crawl !!\n");
                    $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                break;

                case "RESUME_CRAWL":
                    if(isset($info[self::CRAWL_TIME]) && 
                        file_exists(CRAWL_DIR.'/cache/'.
                            self::queue_base_name.$info[self::CRAWL_TIME])) {
                        $this->startCrawl($info);
                        crawlLog("Resuming crawl");
                    } else {
                        $msg = "Restart failed!!!  ";
                        if(!isset($info[self::CRAWL_TIME])) {
                            $msg .="crawl time of crawl to restart not given\n";
                        }
                        if(!file_exists(CRAWL_DIR.'/cache/'.
                            self::queue_base_name.$info[self::CRAWL_TIME])) {
                            $msg .= "bundle for crawl restart doesn't exist\n";
                        }
                        $info["MESSAGE"] = $msg;
                        $crawl_status = array();
                        $crawl_status['MOST_RECENT_FETCHER'] = "";
                        $crawl_status['MOST_RECENT_URLS_SEEN'] = array();
                        $crawl_status['CRAWL_TIME'] = $this->crawl_time;
                        $crawl_status['COUNT'] = 0;
                        $crawl_status['DESCRIPTION'] = $msg;
                        crawlLog($msg);
                        file_put_contents(
                            CRAWL_DIR."/schedules/crawl_status.txt", 
                            serialize($crawl_status));
                        chmod(CRAWL_DIR."/schedules/crawl_status.txt", 0777);
                        $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                    }
                break;

            }
        }

        return $info;
    }

    /**
     *
     */
    function startCrawl($info)
    {
        $this->crawl_time = $info[self::CRAWL_TIME];
        $this->crawl_order = $info[self::CRAWL_ORDER];
        $this->restrict_sites_by_url = $info[self::RESTRICT_SITES_BY_URL];
        $this->allowed_sites = $info[self::ALLOWED_SITES];
        $this->disallowed_sites = $info[self::DISALLOWED_SITES];
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
        $this->web_queue = new WebQueueBundle(
            CRAWL_DIR.'/cache/'.self::queue_base_name.
            $this->crawl_time, URL_FILTER_SIZE, 
                NUM_URLS_QUEUE_RAM, $min_or_max);

        if(!file_exists(
            CRAWL_DIR.'/cache/'.self::index_data_base_name.$this->crawl_time)) {
            $this->index_archive = new IndexArchiveBundle(
                CRAWL_DIR.'/cache/'.
                    self::index_data_base_name.$this->crawl_time,
                URL_FILTER_SIZE, NUM_ARCHIVE_PARTITIONS, 
                NUM_INDEX_PARTITIONS, $info['DESCRIPTION']);
        } else {
            $this->index_archive = new IndexArchiveBundle(
                CRAWL_DIR.'/cache/'.
                    self::index_data_base_name.$this->crawl_time,
                URL_FILTER_SIZE);
        }

        // chmod so web server can also write to these directories
        $this->db->setWorldPermissionsRecursive(
            CRAWL_DIR.'/cache/'.self::queue_base_name.$this->crawl_time);
        $this->db->setWorldPermissionsRecursive(
            CRAWL_DIR.'/cache/'.self::index_data_base_name.$this->crawl_time);
        // initialize, store the description of this crawl in the index archive


        $info[self::STATUS] = self::CONTINUE_STATE;
        return $info;
    }

    /**
     *
     */
    function deleteOrphanedBundles()
    {
        $dirs = glob(CRAWL_DIR.'/cache/*', GLOB_ONLYDIR);

        foreach($dirs as $dir) {
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
        }
    }

    /**
     *
     */
    function processDataFile($base_dir, $callback_method)
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
                    $this->$callback_method($file);
                    return;
                }
            }
            $old_dir = $dir;
        }

    }

    /**
     *
     */
    function processIndexData()
    {
        crawlLog("Checking for index data files to process...");

        $index_dir =  CRAWL_DIR."/schedules/".
            self::index_data_base_name.$this->crawl_time;
        $this->processDataFile($index_dir, "processIndexArchive");
        crawlLog("done.");
    }

    /**
     *
     */
    function processIndexArchive($file)
    {
        static $first = true;
        crawlLog(
            "Start processing index data memory usage".
            memory_get_usage() . "...");
        crawlLog("Processing index data in $file...");

        $start_time = microtime();

        $index_archive = $this->index_archive;
        $sites = unserialize(file_get_contents($file));

        crawlLog("A memory usage".memory_get_usage() .
          " time: ".(changeInMicrotime($start_time)));
        $start_time = microtime();

        $machine = $sites[self::MACHINE];
        $machine_uri = $sites[self::MACHINE_URI];

        if(isset($sites[self::SEEN_URLS]) && 
            count($sites[self::SEEN_URLS]) > 0) {
            $seen_sites = $sites[self::SEEN_URLS];
            $index_archive->differenceContainsPages($seen_sites, self::HASH);
            $seen_sites = array_values($seen_sites);
            $num_seen = count($seen_sites);
        } else {
            $num_seen = 0;
        }


        for($i = 0; $i < $num_seen; $i++) {
            $index_archive->addPageFilter(self::HASH, $seen_sites[$i]);
            $seen_sites[$i][self::MACHINE] = $machine;
            $seen_sites[$i][self::MACHINE_URI] = $machine_uri;
            $seen_sites[$i][self::HASH_URL] = 
                crawlHash($seen_sites[$i][self::URL]);
        }

        if(isset($seen_sites)) {
            $seen_sites = 
                $index_archive->addPages(
                    self::HASH_URL, self::SUMMARY_OFFSET, $seen_sites);

            $summary_offsets = array();
            foreach($seen_sites as $site) {
                $summary_offsets[$site[self::HASH_URL]] = 
                    $site[self::SUMMARY_OFFSET];
            }
            crawlLog("B memory usage".memory_get_usage() .
                " time: ".(changeInMicrotime($start_time)));
            $start_time = microtime();
            // added summary offset info to inverted index data
            if(isset($sites[self::INVERTED_INDEX])) {
                $index_data = & $sites[self::INVERTED_INDEX];
                foreach( $index_data as $word_key => $docs_info) {
                    foreach($docs_info as $doc_key => $info) {
                        if(isset($summary_offsets[$doc_key])) {
                            $index_data[$word_key][$doc_key][
                                self::SUMMARY_OFFSET] = 
                                    $summary_offsets[$doc_key];
                        }
                    }
                }
            }
        }
        crawlLog("C memory usage".memory_get_usage() .
            " time: ".(changeInMicrotime($start_time)));
        $start_time = microtime();
        $index_archive->forceSave();
        crawlLog("D memory usage".memory_get_usage() .
            " time: ".(changeInMicrotime($start_time)));
        $start_time = microtime();

        if(isset($index_data)) {
            $index_archive->addIndexData($index_data);
        }
        crawlLog("E memory usage".memory_get_usage(). 
            " time: ".(changeInMicrotime($start_time)));


        crawlLog("Done Processing File: $file");

        unlink($file);
    }

    /**
     *
     */
    function processRobotUrls()
    {
        crawlLog("Checking age of robot data in queue server ");

        if($this->web_queue->getRobotTxtAge() > CACHE_ROBOT_TXT_TIME) {
            $this->deleteRobotData();
        } else {
            crawlLog("... less than max age\n");
        }

        crawlLog("Checking for Robot.txt files to process...");
        $robot_dir = 
            CRAWL_DIR."/schedules/".
                self::robot_data_base_name.$this->crawl_time;

        $this->processDataFile($robot_dir, "processRobotArchive");
        crawlLog("done. ");
    }

    /**
     *
     */
    function processRobotArchive($file)
    {
        crawlLog("Processing Robots data in $file");
        $start_time = microtime();

        $sites = unserialize(file_get_contents($file));

         
        if(isset($sites)) {
            foreach($sites as $robot_host => $robot_info) {
                $this->web_queue->addGotRobotTxtFilter($robot_host);
                $robot_url = $robot_host."/robots.txt";
                if($this->web_queue->containsUrlQueue($robot_url)) {
                    crawlLog("Removing $robot_url from Queue");
                    $this->web_queue->removeQueue($robot_url);
                }

                if(isset($robot_info[self::CRAWL_DELAY])) {
                    $this->web_queue->setCrawlDelay($robot_host,
                        $robot_info[self::CRAWL_DELAY]);
                }

                if(isset($robot_info[self::ROBOT_PATHS])) {
                    foreach($robot_info[self::ROBOT_PATHS] as $path) {
                        $this->web_queue->addDisallowedRobotFilter(
                            $robot_host.$path); 
                    }
                }
            }
        }

        crawlLog(" time: ".(changeInMicrotime($start_time))."\n");

        crawlLog("Done Processing File: $file");

        unlink($file);
    }

    /**
     *
     */
    function deleteRobotData()
    {
        crawlLog("... unlinking robot schedule files ...");

        $robot_schedules = CRAWL_DIR.'/schedules/'.
            self::robot_data_base_name.$this->crawl_time;
        $this->db->unlinkRecursive($robot_schedules, true);

        crawlLog("... reseting robot bloom filters ...");
        $this->web_queue->emptyRobotFilters();
    }

    /**
     *
     */
    function processQueueUrls()
    {
        crawlLog("Start checking for new URLs data memory usage".
            memory_get_usage());

        $info = array();
        $info[self::STATUS] = self::CONTINUE_STATE;

        if(file_exists(CRAWL_DIR."/schedules/".self::schedule_start_name)) {
            crawlLog(
                "Start schedule urls".CRAWL_DIR.
                    "/schedules/".self::schedule_start_name);
            $info = array_merge($info, 
                $this->processDataArchive(
                    CRAWL_DIR."/schedules/".self::schedule_start_name));
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
     *
     */
    function processDataArchive($file)
    {
        crawlLog("Processing File: $file");

        $sites = unserialize(file_get_contents($file));

        $info = array();

        if(isset($sites[self::MACHINE])) {
            $this->most_recent_fetcher = $sites[self::MACHINE];
        }

        crawlLog("...Updating Delayed Hosts Array ...");
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
            }
        }
        crawlLog(" time: ".(changeInMicrotime($start_time)));

        crawlLog("...Seen Urls ...");
        $start_time = microtime();
        $most_recent_urls = array();

        if(isset($sites[self::SEEN_URLS])) {
            $cnt = 0;
            foreach($sites[self::SEEN_URLS] as $url) {
                if($this->web_queue->containsUrlQueue($url)) {
                    crawlLog(
                        "Removing $url from Queue (shouldn't still be there!)");
                    $this->web_queue->removeQueue($url);
                }

                array_push($most_recent_urls, $url);
                if($cnt >= NUM_RECENT_URLS_TO_DISPLAY)
                {
                    array_shift($most_recent_urls);
                }
                $cnt++;
            }
        }

        crawlLog(" time: ".(changeInMicrotime($start_time)));


        crawlLog("... To Crawl ...");
        $start_time = microtime();
        if(isset($sites[self::TO_CRAWL])) {

            crawlLog("A..");
            $to_crawl_sites = & $sites[self::TO_CRAWL];
            $this->deleteSeenUrls($to_crawl_sites);
            crawlLog(" time: ".(changeInMicrotime($start_time)));

            crawlLog("B..");
            $start_time = microtime();

            $added_urls = array();
            $added_pairs = array();
            $contains_host = array();
            foreach($to_crawl_sites as $pair) {
                $url = & $pair[0];
                $weight = $pair[1];
                $host_url = UrlParser::getHost($url);
                $host_with_robots = $host_url."/robots.txt";
                $robots_in_queue = 
                    $this->web_queue->containsUrlQueue($host_with_robots);

                if($this->web_queue->containsUrlQueue($url)) {

                    if($robots_in_queue) {
                        $this->web_queue->adjustQueueWeight(
                            $host_with_robots, $weight);
                    }
                    $this->web_queue->adjustQueueWeight($url, $weight);
                } else if($this->allowedToCrawlSite($url) && 
                    !$this->disallowedToCrawlSite($url)  ) {

                    if(!$this->web_queue->containsGotRobotTxt($host_url) 
                        && !$robots_in_queue
                        && !isset($added_urls[$host_with_robots])
                        ) {
                            $added_pairs[] = array($host_with_robots, $weight);
                            $added_urls[$host_with_robots] = true;
                    }

                    if(!isset($added_urls[$url])) {
                        $added_pairs[] = $pair;
                        $added_urls[$url] = true;
                    }

                }
            }
            
            crawlLog(" time: ".(changeInMicrotime($start_time)));

            crawlLog("C..");
            $start_time = microtime();
            $this->web_queue->addUrlsQueue($added_pairs);

        }
        crawlLog(" time: ".(changeInMicrotime($start_time)));

        crawlLog("Done Processing File: $file");

        unlink($file);

        $crawl_status = array();
        $crawl_status['MOST_RECENT_FETCHER'] = $this->most_recent_fetcher;
        $crawl_status['MOST_RECENT_URLS_SEEN'] = $most_recent_urls; 
        $crawl_status['CRAWL_TIME'] = $this->crawl_time;
        $info_bundle = IndexArchiveBundle::getArchiveInfo(
            CRAWL_DIR.'/cache/'.self::index_data_base_name.$this->crawl_time);
        $crawl_status['COUNT'] = $info_bundle['COUNT'];
        $crawl_status['DESCRIPTION'] = $info_bundle['DESCRIPTION'];
        file_put_contents(
            CRAWL_DIR."/schedules/crawl_status.txt", serialize($crawl_status));
        chmod(CRAWL_DIR."/schedules/crawl_status.txt", 0777);
        crawlLog(
            "End checking for new URLs data memory usage".memory_get_usage());

        crawlLog(
            "The current crawl description is: ".$info_bundle['DESCRIPTION']);
        crawlLog("Total seen urls so far: ".$info_bundle['COUNT']); 
        crawlLog("Of these, the most recent urls are:");
        foreach($most_recent_urls as $url) {
            crawlLog("URL: $url");
        }

        return $info;

    }

    /**
     *
     */
    function deleteSeenUrls(&$sites)
    {
        $this->web_queue->differenceSeenUrls($sites, 0);
    }


    /**
     *
     */
    function produceFetchBatch()
    {

        $i = 1; // array implementation of priority queue starts at 1 not 0
        $fetch_size = 0;

        crawlLog("Start Produce Fetch Batch Memory usage".memory_get_usage() );

        $count = $this->web_queue->to_crawl_queue->count;

        $sites[self::CRAWL_TIME] = $this->crawl_time;
        $sites[self::SCHEDULE_TIME] = time();
        $sites[self::SAVED_CRAWL_TIMES] =  $this->getCrawlTimes(); 
            // fetcher should delete any crawl time not listed here
        $sites[self::CRAWL_ORDER] = $this->crawl_order;
        $sites[self::SITES] = array();

        $delete_urls = array();
        $crawl_delay_hosts = array();
        $time_per_request_guess = MINIMUM_FETCH_LOOP_TIME ; 
            // it would be impressive if we can achieve this speed

        $current_crawl_index = -1;

        crawlLog("Trying to Produce Fetch Batch; Queue Size $count");
        $start_time = microtime();

        $fh = $this->web_queue->openUrlArchive();
        while ($i <= $count && $fetch_size < MAX_FETCH_SIZE) {
            $tmp = $this->web_queue->peekQueue($i, $fh);
            list($url, $weight) = $tmp;
            if($tmp === false || strcmp($url, "LOOKUP ERROR") == 0) {
                $this->web_queue->to_crawl_queue->poll($i);
                crawlLog("Removing lookup error index");
                $i++;
                continue;
            }

            $host_url = UrlParser::getHost($url);
            
            if(strcmp($host_url."/robots.txt", $url) == 0) {
                if($this->web_queue->containsGotRobotTxt($host_url)) {
                    $delete_urls[$i] = $url;
                    $i++;
                } else {

                    $next_slot = $this->getEarliestSlot($current_crawl_index, 
                        $sites[self::SITES]);
                    
                    if($next_slot < MAX_FETCH_SIZE) {
                        $sites[self::SITES][$next_slot] = array($url, $weight);
                        $delete_urls[$i] = $url;
                        /* note don't add to seen url filter 
                           since check robots every 24 hours as needed
                         */
                        $current_crawl_index = $next_slot;
                        $fetch_size++;
                        $i++;
                      } else { //no more available slots so prepare to bail
                        $i = $count;
                    }
                }
                continue;
            }
            
            $robots_okay = true;

            if($this->web_queue->containsGotRobotTxt($host_url)) {
                $host_paths = UrlParser::getHostPaths($url);


                foreach($host_paths as $host_path) {
                    if($this->web_queue->containsDisallowedRobot($host_path)) {
                        $robots_okay = false;
                        $delete_urls[$i] = $url; 
                            //want to remove from queue since robots forbid it
                        $this->web_queue->addSeenUrlFilter($url); 
                            /* at this point we might miss some sites by marking
                               them seen: the robot url might change in 24 hours
                             */
                        break;
                    }
                }

                if(!$robots_okay) {
                    $i++;
                    continue;
                }

                $delay = $this->web_queue->getCrawlDelay($host_url);
                $num_waiting = count($this->waiting_hosts);
                
                if($delay > 0 ) { 
                    // handle adding a url if there is a crawl delay
                    if((!isset($this->waiting_hosts[crawlHash($host_url)])
                        && $num_waiting < MAX_WAITING_HOSTS)
                        || (isset($this->waiting_hosts[crawlHash($host_url)]) &&
                            $this->waiting_hosts[crawlHash($host_url) ] == 
                            $sites[self::SCHEDULE_TIME])) {

                        $this->waiting_hosts[crawlHash($host_url)] = 
                            $sites[self::SCHEDULE_TIME];
                        $this->waiting_hosts[$sites[self::SCHEDULE_TIME]][] = 
                            crawlHash($host_url);
                        $request_batches_per_delay = 
                            ceil($delay/$time_per_request_guess); 
                        
                        if(!isset($crawl_delay_hosts[$host_url])) {
                            $next_earliest_slot = $current_crawl_index;
                            $crawl_delay_hosts[$host_url] = $next_earliest_slot;
                        } else {
                            $next_earliest_slot = $crawl_delay_hosts[$host_url] 
                                + $request_batches_per_delay
                                * NUM_MULTI_CURL_PAGES;
                        }
                        
                        if(($next_slot = 
                            $this->getEarliestSlot( $next_earliest_slot, 
                                $sites[self::SITES])) < MAX_FETCH_SIZE) {
                            $crawl_delay_hosts[$host_url] = $next_slot;
                            $sites[self::SITES][$next_slot] = 
                                array($url, $weight);
                            $delete_urls[$i] = $url;
                            $this->web_queue->addSeenUrlFilter($url); 
                                /* we might miss some sites by marking them 
                                   seen after only scheduling them
                                 */

                            $fetch_size++;
                        }
                    }
                } else { // add a url no crawl delay
                    $next_slot = $this->getEarliestSlot(
                        $current_crawl_index, $sites[self::SITES]);
                    if($next_slot < MAX_FETCH_SIZE) {
                        $sites[self::SITES][$next_slot] = array($url, $weight);
                        $delete_urls[$i] = $url;
                        $this->web_queue->addSeenUrlFilter($url); 
                            /* we might miss some sites by marking them 
                               seen after only scheduling them
                             */

                        $current_crawl_index = $next_slot;
                        $fetch_size++;
                    } else { //no more available slots so prepare to bail
                        $i = $count;
                    }
                } //if delay else
            } // if containsGotRobotTxt

            // handle robot.txt urls


            $i++;
        } //end while
        $this->web_queue->closeUrlArchive($fh);

        foreach($delete_urls as $delete_url) {
            $this->web_queue->removeQueue($delete_url);
        }

        if(isset($sites[self::SITES]) && count($sites[self::SITES]) > 0 ) {
            $dummy_slot = array(self::DUMMY, 0.0);  
            /* dummy's are used for crawl delays of sites with longer delays
               when we don't have much else to crawl
             */
            $cnt = 0;
            for($j = 0; $j < MAX_FETCH_SIZE; $j++) {
                if(isset( $sites[self::SITES][$j])) {
                    $cnt++;
                    if($cnt == $fetch_size) {break; }
                } else {
                    if($j % NUM_MULTI_CURL_PAGES == 0) {
                        $sites[self::SITES][$j] = $dummy_slot;
                    }
                }
            }
            ksort($sites[self::SITES]);

            file_put_contents(CRAWL_DIR."/schedules/schedule.txt", 
                serialize($sites));
            crawlLog("End Produce Fetch Memory usage".memory_get_usage() );
            crawlLog("Created fetch batch... Queue size is now ".
                $this->web_queue->to_crawl_queue->count.
                "...Time to create batch: ".
                (changeInMicrotime($start_time)));
        } else {
            crawlLog("No fetch batch created!!");
        }

    }

    /**
     *
     */
    function getEarliestSlot($index, $arr)
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
     *
     */
    function allowedToCrawlSite($url) 
    {
        $doc_type = UrlParser::getDocumentType($url);
        if(!in_array($doc_type, $this->indexed_file_types)) {
            return false;
        }

        if($this->restrict_sites_by_url) {
           return $this->urlMemberSiteArray($url, $this->allowed_sites);
        }
        return true;
    }

    /**
     *
     */
    function disallowedToCrawlSite($url)
    {
        return $this->urlMemberSiteArray($url, $this->disallowed_sites);
    }

    /**
     *
     */
    function urlMemberSiteArray($url, $site_array)
    {
        $flag = false;
        foreach($site_array as $site) {
            $site_parts = mb_split("domain:", $site);
            if(isset($site_parts[1]) && 
                mb_strstr(UrlParser::getHost($url), $site_parts[1]) ) {
                $flag = true;
                break;
            }


            if(mb_strstr($url, $site)) { 
                $flag = true;
                break;
            }
        }
        return $flag;
    }

    /**
     *
     */
    function getCrawlTimes()
    {
        $list = array();
        $dirs = glob(CRAWL_DIR.'/cache/*', GLOB_ONLYDIR);

        foreach($dirs as $dir) {
            if(strlen($pre_timestamp = strstr($dir, 
                self::index_data_base_name)) > 0) {
                $list[] = substr($pre_timestamp, 
                    strlen(self::index_data_base_name));
            }
        }

        return $list;
    }
}

/*
 *  Instantiate and runs the QueueSever
 */
$queue_server =  new QueueServer($INDEXED_FILE_TYPES);
$queue_server->start();


?>
