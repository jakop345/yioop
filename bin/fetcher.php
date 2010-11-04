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

ini_set("memory_limit","700M"); //so have enough memory to crawl big pages

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}

/** get the database library based on the current database type */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php"; 
/** caches of web pages are stored in a 
 *  web archive bundle, so we load in its definition 
 */
require_once BASE_DIR."/lib/web_archive_bundle.php"; 

/** get processors for different file types */
foreach(glob(BASE_DIR."/lib/processors/*_processor.php") as $filename) { 
    require_once $filename;
}

/** To support English language stemming of words (jumps, jumping --> jump)*/
require_once BASE_DIR."/lib/porter_stemmer.php";
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
     * Contains each of the file extenstions this fetcher will try to process
     * @var array
     */
    var $indexed_file_types;
    /**
     * An associative array of (mimetype => name of processor class to handle)
     * pairs.
     * @var array
     */
    var $page_processors;
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
     * Urls of duplicate sites that the fetcher hasn't sent to 
     * the queue_server yet
     * @var array
     */
    var $found_duplicates;
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
     * Last time index was saved to disk
     * @var int
     */
    var $last_filter_save_time;
    /**
     * Keeps track if we've added and not saved 
     * anything to the page filter bundle
     * @var bool
     */
    var $filter_dirty;

    /**
     * Sets up the field variables for that crawling can begin
     *
     * @param array $indexed_file_types file extensions to index
     * @param array $page_processors (mimetype => name of processor) pairs
     * @param string $queue_server URL or IP address of the queue server
     */
    function __construct($indexed_file_types, $page_processors, $queue_server) 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();

        $this->indexed_file_types = $indexed_file_types;
        $this->queue_server = $queue_server;
        $this->page_processors = $page_processors;
        $this->meta_words = array();

        $this->web_archive = NULL;
        $this->crawl_time = NULL;
        $this->schedule_time = NULL;

        $this->to_crawl = array();
        $this->to_crawl_again = array();
        $this->found_sites = array();
        $this->found_duplicates = array();

        $this->sum_seen_title_length = 0;
        $this->sum_seen_description_length = 0;
        $this->sum_seen_site_link_length = 0;
        $this->num_seen_sites = 0;

        $this->last_filter_save_time = 0;
        $this->filter_dirty = false;
        
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

        declare(ticks=1);
        CrawlDaemon::init($argv, "fetcher");

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
        crawlLog("In Fetch Loop", "fetcher");
        
        $info[self::STATUS] = self::CONTINUE_STATE;
        $this->checkCrawlTime();
        
        while ($info[self::STATUS] != self::STOP_STATE) {

            if(time() - $this->last_filter_save_time > FORCE_SAVE_TIME &&
                isset($this->web_archive->page_exists_filter_bundle) &&
                $this->filter_dirty == true){
                $this->web_archive->page_exists_filter_bundle->forceSave();
                $this->filter_dirty = false;
                $this->last_filter_save_time = time();
            }

            $fetcher_message_file = CRAWL_DIR."/schedules/fetcher_messages.txt";
            if(file_exists($fetcher_message_file)) {
                $info = unserialize(file_get_contents($fetcher_message_file));
                unlink($fetcher_message_file);
                if(isset($info[self::STATUS]) && 
                    $info[self::STATUS] == self::STOP_STATE) {continue;}
            }

            $info = $this->checkScheduler();
            if(!isset($info[self::STATUS])) {
                $info[self::STATUS] = self::CONTINUE_STATE;
            }

            if($info[self::STATUS] == self::NO_DATA_STATE) {
                crawlLog("No data from queue server. Sleeping...");
                sleep(5);
                continue;
            }

            $tmp_base_name = (isset($info[self::CRAWL_TIME])) ? 
                CRAWL_DIR."/cache/" . self::archive_base_name .
                    $info[self::CRAWL_TIME] : "";
            if(isset($info[self::CRAWL_TIME]) && ($this->web_archive == NULL || 
                    $this->web_archive->dir_name != $tmp_base_name)) {
                if(isset($this->web_archive->dir_name)) {
                    crawlLog("Old name: ".$this->web_archive->dir_name);
                }
                if(is_object($this->web_archive)) {
                    $this->web_archive->page_exists_filter_bundle = NULL;
                    $this->web_archive = NULL;
                }
                $this->to_crawl_again = array();
                $this->found_sites = array();
                $this->found_duplicates = array();
                gc_collect_cycles();
                $this->web_archive = new WebArchiveBundle($tmp_base_name, 
                    URL_FILTER_SIZE);
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
                continue;
            }

            $site_pages = FetchUrl::getPages($sites, true);
            
            list($deduplicated_pages, $schedule_again_pages, $duplicates) = 
                $this->deduplicateAndReschedulePages($site_pages);

            $this->found_duplicates = array_merge($this->found_duplicates,
                $duplicates);
            if($can_schedule_again == true) {
                //only schedule to crawl again on fail sites without crawl-delay
                foreach($schedule_again_pages as $schedule_again_page) {
                    if($schedule_again_page[self::CRAWL_DELAY] == 0) {
                        $this->to_crawl_again[] = 
                            array($schedule_again_page[self::URL], 
                                $schedule_again_page[self::WEIGHT],
                                $schedule_again_page[self::CRAWL_DELAY]
                            );
                    }
                }
            }

            $start_time = microtime();
            $summarized_site_pages = 
                $this->processFetchPages($deduplicated_pages);

            crawlLog("Number summarize pages".count($summarized_site_pages));

            $this->updateFoundSites($summarized_site_pages);

            sleep(max(0, ceil(
                MINIMUM_FETCH_LOOP_TIME - changeInMicrotime($start_time))));
        } //end while

        crawlLog("Fetcher shutting down!!");
    }
    
    /**
     * Deletes any crawl web archive bundles not in the provided array of crawls
     *
     * @param array $still_active_crawls those crawls which should be deleted,
     *      so all others will be deleted
     * @see loop()
     */
    function deleteOldCrawls(&$still_active_crawls)
    {
        $dirs = glob(CRAWL_DIR.'/cache/*', GLOB_ONLYDIR);

        foreach($dirs as $dir) {
            if(strlen(
                $pre_timestamp = strstr($dir, self::archive_base_name)) > 0) {
                $time = substr($pre_timestamp, strlen(self::archive_base_name));
                if(!in_array($time, $still_active_crawls) ){
                    $this->db->unlinkRecursive($dir);
                }
            }
        }

    }

    /**
     * Makes a request of the queue server machine to get the timestamp of the 
     * currently running crawl to see if changed
     *
     * Get the timestamp from queue_server of the currently running crawl, 
     * if the timestamp has changed drop the rest of the current fetch batch.
     */
   function checkCrawlTime()
    {
        $queue_server = $this->queue_server;

        $start_time = microtime();
        $time = time();
        $session = md5($time . AUTH_KEY);

        /* if just restarted, check to make sure the crawl hasn't changed, 
           if it has bail
        */
        $request =  
            $queue_server."?c=fetch&a=crawlTime&time=$time&session=$session";

        $info_string = FetchUrl::getPage($request);
        $info = @unserialize(trim($info_string));

        if(isset($info[self::CRAWL_TIME]) 
            && $info[self::CRAWL_TIME] != $this->crawl_time) {
            $this->to_crawl = array(); // crawl has changed. Dump rest of batch.
        }
    
    }
    
    /**
     * Get status, current crawl, crawl order, and new site information from 
     * the queue_server.
     *
     * @return array containing this info
     */
    function checkScheduler() 
    {
        $info = array();
        
        if(count($this->to_crawl) > 0 || count($this->to_crawl_again) > 0) {
            $info[self::STATUS]  = self::CONTINUE_STATE;
            return; 
        }

        $queue_server = $this->queue_server;

        $start_time = microtime();
        $time = time();
        $session = md5($time . AUTH_KEY);

        $request =  
            $queue_server."?c=fetch&a=schedule&time=$time&session=$session";

        $info_string = trim(FetchUrl::getPage($request));
        $tok = strtok($info_string, "\n");
        $info = unserialize(base64_decode($tok));

        if(isset($info[self::CRAWL_ORDER])) {
            $this->crawl_order = $info[self::CRAWL_ORDER];
        }
        if(isset($info[self::META_WORDS])) {
            $this->meta_words = $info[self::META_WORDS];
        }
        if(isset($info[self::SITES])) {
            $this->to_crawl = array();
            while($tok !== false) {
                $string = base64_decode($tok);
                $tmp = unpack("f", substr($string, 0 , 4));
                $weight = $tmp[1];
                $tmp = unpack("N", substr($string, 4 , 4));
                $delay = $tmp[1];
                $url = substr($string, 8);
                $this->to_crawl[] = array($url, $weight, $delay);
                
                $tok = strtok("\n");
            }
        }

        if(isset($info[self::SCHEDULE_TIME])) {
              $this->schedule_time = $info[self::SCHEDULE_TIME];
        }

        crawlLog("  Time to check Scheduler ".(changeInMicrotime($start_time)));

        return $info; 
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
        while ($i < $num_items) {
            if(!isset($site_pair)) {
                $site_pair = each($crawl_source);
                $old_pair['key'] = $site_pair['key'] - 1;

            } else {
                $old_pair =  $site_pair;
                $site_pair = each($crawl_source);
            }

            if($old_pair['key'] + 1 == $site_pair['key']) {
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
                } else {
                    $num_items--;
                }
                $i++;
            } else {
             $i = $num_items;
            }
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
     * Does page deduplication on an array of downloaded pages using a
     * BloomFilterBundle of $this->web_archive. Deduplication based
     * on summaries is also done on the queue server. Also, sorts out pages
     * for which no content was downloaded so that they can be scheduled
     * to be crawled again.
     *
     * @param array &$site_pages pages to deduplicate
     * @return an array conisting of the deduclicated pages, the not_downloaded
     *      sites, and the urls of duplicate pages.
     */
    function deduplicateAndReschedulePages(&$site_pages)
    {
        $start_time = microtime();

        $deduplicated_pages = array();
        $not_downloaded = array();
        $duplicates = array();

        /*
            Time to Deduplicate!
            $unseen_page_hashes array to check against all before this batch, 
            $seen_pages to check against this batch
        */
        $unseen_page_hashes = 
            $this->web_archive->differencePageKeysFilter($site_pages, 
            self::HASH);
        $seen_pages = array();

        foreach($site_pages as $site) {
            if( isset($site[self::ROBOT_PATHS])) {
                $deduplicated_pages[] = $site;
            } else if (isset($site[self::HASH]) && in_array($site[self::HASH], 
                $unseen_page_hashes) && !in_array($site[self::HASH], 
                $seen_pages)) {
                $this->web_archive->addPageFilter(self::HASH, $site);
                $this->filter_dirty = true;
                $deduplicated_pages[] = $site;
                $seen_pages[] = $site[self::HASH];
            } else if(!isset($site[self::HASH])){
                $not_downloaded[] = $site;
            } else {
                $duplicates[] = $site[self::URL];
                crawlLog("Deduplicated:".$site[self::URL]);
            }

        }
        crawlLog("  Delete duplicated pages time".
            (changeInMicrotime($start_time)));

        return array($deduplicated_pages, $not_downloaded, $duplicates);
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

        $start_time = microtime();

        $stored_site_pages = array();
        $summarized_site_pages = array();

        $num_items = $this->web_archive->count;

        $i = 0;
        
        foreach($site_pages as $site) {
            $response_code = $site[self::HTTP_CODE]; 

            //process robot.txt files separately
            if(isset($site[self::ROBOT_PATHS])) {
                if($response_code >= 200 && $response_code < 300) {
                    $site = $this->processRobotPage($site);
                }
                $site[self::GOT_ROBOT_TXT] = true;
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
            } else {
                continue;
            }

            $processor = new $page_processor();

            $doc_info = $processor->process($site[self::PAGE], 
                $site[self::URL]);

            if($doc_info) {
                $site[self::DOC_INFO] =  $doc_info;

                if(!is_dir(CRAWL_DIR."/cache")) {
                    mkdir(CRAWL_DIR."/cache");
                    $htaccess = "Options None\nphp_flag engine off\n";
                    file_put_contents(CRAWL_DIR."/cache/.htaccess", $htaccess);

                }

                if($site[self::TYPE] != "text/html" ) {
                    if(isset($doc_info[self::PAGE])) {
                        $site[self::PAGE] = $doc_info[self::PAGE];
                    } else {
                        $site[self::PAGE] = NULL;
                    }

                }


                $stored_site_pages[$i][self::URL] = $site[self::URL];
                $stored_site_pages[$i][self::TIMESTAMP] = 
                    $site[self::TIMESTAMP];
                $stored_site_pages[$i][self::TYPE] = $site[self::TYPE];
                if(isset($site[self::ENCODING])) {
                    $encoding = $site[self::ENCODING];
                } else {
                    $encoding = "UTF-8";
                }
                $stored_site_pages[$i][self::ENCODING] = $encoding;
                $stored_site_pages[$i][self::HTTP_CODE] = 
                    $site[self::HTTP_CODE];
                $stored_site_pages[$i][self::HASH] = $site[self::HASH];
                $stored_site_pages[$i][self::PAGE] = $site[self::PAGE];

                $summarized_site_pages[$i][self::URL] = 
                    strip_tags($site[self::URL]);
                $summarized_site_pages[$i][self::TITLE] = strip_tags(
                    $site[self::DOC_INFO][self::TITLE]); 
                    // stripping html to be on the safe side
                $summarized_site_pages[$i][self::DESCRIPTION] = 
                    strip_tags($site[self::DOC_INFO][self::DESCRIPTION]);
                $summarized_site_pages[$i][self::TIMESTAMP] = 
                    $site[self::TIMESTAMP];
                $summarized_site_pages[$i][self::ENCODING] = $encoding;
                $summarized_site_pages[$i][self::HASH] = $site[self::HASH];
                $summarized_site_pages[$i][self::TYPE] = $site[self::TYPE];
                $summarized_site_pages[$i][self::HTTP_CODE] = 
                    $site[self::HTTP_CODE];
                $summarized_site_pages[$i][self::WEIGHT] = $site[self::WEIGHT];

                if(isset($site[self::DOC_INFO][self::LINKS])) {
                    $summarized_site_pages[$i][self::LINKS] = 
                        $site[self::DOC_INFO][self::LINKS];
                }

                if(isset($site[self::DOC_INFO][self::THUMB])) {
                    $summarized_site_pages[$i][self::THUMB] = 
                        $site[self::DOC_INFO][self::THUMB];
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
            } else {
                $this->found_sites[self::SEEN_URLS][] = $site;
                if(isset($site[self::LINKS])) {
                    if(!isset($this->found_sites[self::TO_CRAWL])) {
                        $this->found_sites[self::TO_CRAWL] = array();
                    }
                    $link_urls = array_keys($site[self::LINKS]);
                    $num_links = count($link_urls);

                    switch($this->crawl_order) {
                        case self::BREADTH_FIRST:
                            $weight= $site[self::WEIGHT] + 1;
                        break;

                        case self::PAGE_IMPORTANCE:
                        default:
                            if($num_links > 0 ) {
                                $weight= $site[self::WEIGHT]/$num_links;
                            } else {
                                $weight= $site[self::WEIGHT];
                            }
                        break;
                    }

                    foreach($link_urls as $link_url) {
                        if(strlen($link_url) > 0) {
                            $this->found_sites[self::TO_CRAWL][] = 
                                array($link_url, $weight);
                        }
                    }

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
            SEEN_URLS_BEFORE_UPDATE_SCHEDULER)) {
            $this->updateScheduler();
        }

        crawlLog("  Update Found Sites Time ".(changeInMicrotime($start_time)));
    
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
            'crawl_time' => $this->crawl_time, 'machine_uri' => WEB_URI);

        //handle robots.txt data
        if(isset($this->found_sites[self::ROBOT_TXT])) {
            $post_data['robot_data'] = urlencode(base64_encode(
                gzcompress(serialize($this->found_sites[self::ROBOT_TXT]))));
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

        if(isset($this->found_sites[self::SEEN_URLS]) && 
            count($this->found_sites[self::SEEN_URLS]) > 0 ) {
            $hash_seen_urls = array();
            $recent_urls = array();
            $cnt = 0;
            foreach($this->found_sites[self::SEEN_URLS] as $site) {
                $hash_seen_urls[] =
                    crawlHash($site[self::URL], true);
                if(strpos($site[self::URL], "url|") !== 0) {
                    array_push($recent_urls, $site[self::URL]);
                    if($cnt >= NUM_RECENT_URLS_TO_DISPLAY)
                    {
                        array_shift($recent_urls);
                    }
                    $cnt++;
                }
            }
            $schedule_data[self::HASH_SEEN_URLS] = & $hash_seen_urls;
            unset($hash_seen_urls);
            $schedule_data[self::RECENT_URLS] = & $recent_urls;
            unset($recent_urls);
        }
        if(!empty($schedule_data)) {
            if(isset($schedule_time)) {
                $schedule_data[self::SCHEDULE_TIME] = $schedule_time;
            }
            $post_data['schedule_data'] = urlencode(base64_encode(
                gzcompress(serialize($schedule_data))));
            $bytes_to_send += strlen($post_data['schedule_data']);
        }
        unset($schedule_data);

        //handle mini inverted index
        if(isset($this->found_sites[self::SEEN_URLS]) && 
            count($this->found_sites[self::SEEN_URLS]) > 0 ) {
            $this->buildMiniInvertedIndex();
        }
        if(isset($this->found_sites[self::INVERTED_INDEX])) {
            $index_data = array();
            $index_data[self::SEEN_URLS] = & 
                $this->found_sites[self::SEEN_URLS];
            unset($this->found_sites[self::SEEN_URLS]);
            $index_data[self::INVERTED_INDEX] = & 
                $this->found_sites[self::INVERTED_INDEX];
            unset($this->found_sites[self::INVERTED_INDEX]);
            $post_data['index_data'] = urlencode(base64_encode(
                gzcompress(serialize($index_data))));
            unset($index_data);
            $bytes_to_send += strlen($post_data['index_data']);
        }

        $this->found_sites = array(); // reset found_sites so have more space.
        if($bytes_to_send <= 0) {
            crawlLog("No data to send aborting update scheduler...");
            return;
        }

        //try to send to queue server
        $sleep = false;
        do {

            if($sleep == true) {
                crawlLog("Trouble sending to the scheduler\n $info_string...");
                sleep(5);
            }
            $sleep = true;

            $time = time();
            $session = md5($time . AUTH_KEY);
            $post_data['time'] = $time;
            $post_data['session'] = $session;
            $post_data['fetcher_peak_memory'] = memory_get_peak_usage();

            $info_string = FetchUrl::getPage($queue_server, $post_data);
            crawlLog(
                "Updating Queue Server, sending approximately" .
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

        if(isset($info[self::CRAWL_TIME]) && 
            $info[self::CRAWL_TIME] != $this->crawl_time) {
            $this->to_crawl = array(); // crawl has changed. Dump rest of batch.
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
            $doc_key = crawlHash($site[self::URL], true);
            $word_counts = array();
            $phrase_string = 
                mb_ereg_replace("[[:punct:]]", " ", $site[self::TITLE] .
                   " ". $site[self::DESCRIPTION]); 
            $word_counts = 
                PhraseParser::extractPhrasesAndCount($phrase_string); 

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
            // store the filetype info
            $url_type = UrlParser::getDocumentType($site[self::URL]);
            if(strlen($url_type) > 0) {
                $meta_ids[] = 'filetype:'.$url_type;
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

            $link_phrase_string = "";
            $link_urls = array(); 
            //store inlinks so they can be searched by
            $num_links = count($site[self::LINKS]);
            if($num_links > 0) {
                $link_weight = $site[self::WEIGHT]/$num_links;
            } else {
                $link_weight = 0;
            }
            $had_links = false;

            $link_shard = new IndexShard("link_shard");
            foreach($site[self::LINKS] as $url => $link_text) {
                if(strlen($url) > 0) {
                    $summary = array();
                    $had_links = true;
                    $link_text = strip_tags($link_text);
                    $link_id = 
                        "url|".$url."|text|$link_text|ref|".$site[self::URL];
                    $link_key =  crawlHash($link_id, true).":".
                        crawlHash($url, true).":"
                        .crawlHash("info:".$url, "true");
                    $summary[self::URL] =  $link_id;
                    $summary[self::TITLE] = $url; 
                        // stripping html to be on the safe side
                    $summary[self::DESCRIPTION] =  $link_text;
                    $summary[self::TIMESTAMP] =  $site[self::TIMESTAMP];
                    $summary[self::ENCODING] = $site[self::ENCODING];
                    $summary[self::HASH] =  false; /* 
                        link id's will always be unique so no sense 
                        deduplicating them
                    */
                    $summary[self::TYPE] = "link";
                    $summary[self::HTTP_CODE] = "link";
                    $this->found_sites[self::SEEN_URLS][] = $summary;

                    $link_text = 
                        mb_ereg_replace("[[:punct:]]", " ", $link_text);
                    $link_word_counts = 
                        PhraseParser::extractPhrasesAndCount($link_text);
                    $link_shard->addDocumentWords($link_key, 
                        self::NEEDS_OFFSET_FLAG, 
                        $link_word_counts, array());

                    $meta_ids[] = 'link:'.$url;
                }

            }
            $index_shard->addDocumentWords($doc_key, self::NEEDS_OFFSET_FLAG, 
                $word_counts, $meta_ids);

            $index_shard->appendIndexShard($link_shard);

        }
        $index_shard->markDuplicateDocs($this->found_duplicates);

        $this->found_duplicates = array();

        $this->found_sites[self::INVERTED_INDEX] = & $index_shard;

        crawlLog("  Build mini inverted index time ".
            (changeInMicrotime($start_time)));
    }
}

/*
 *  Instantiate and runs the Fetcher
 */
$fetcher =  new Fetcher($INDEXED_FILE_TYPES, $PAGE_PROCESSORS, QUEUE_SERVER);
$fetcher->start();

?>
