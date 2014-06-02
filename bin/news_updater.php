<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2014
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

ini_set("memory_limit", "1300M");

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

/** We do want logging, but crawl model and other will try to turn off
 *  if we don't set this
 */
define("NO_LOGGING", false);

/**
 * Shortest time through one iteration of news updater's loop
 */
define("MINIMUM_UPDATE_LOOP_TIME", 10);

/** for crawlDaemon function */
require_once BASE_DIR."/lib/crawl_daemon.php";

/** To guess language based on page encoding */
require_once BASE_DIR."/lib/locale_functions.php";

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/**Load base model class used by source model */
require_once BASE_DIR."/models/model.php";

/** Source model is used to manage news feed ites*/
if(file_exists(APP_DIR."/models/source_model.php")) {
    require_once APP_DIR."/models/source_model.php";
}  else {
    require_once BASE_DIR."/models/source_model.php";
}
/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

if (function_exists('lcfirst') === false) {
    /**
     *  Lower cases the first letter in a string
     *
     *  This function is only defined if the PHP version is before 5.3
     *  @param string $str  string to be lower cased
     *  @return string the lower cased string
     */
    function lcfirst( $str )
    {
        return (string)(strtolower(substr($str, 0, 1)).substr($str, 1));
    }
}

/**
 *  Separate process/command-line script which can be used to update
 *  news sources for Yioop. This is as an alternative to using the web app
 *  for updating. Makes use of the web-apps code.
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 */
class NewsUpdater implements CrawlConstants
{
    /**
     * The last time feeds were checked for updates
     * @var int
     */
    var $update_time;
    /**
     * Sets up the field variables so that newsupdating can begin
     *
     */
    function __construct()
    {
        $this->delete_time = 0;
        $this->retry_time = 0;
        $this->update_time = 0;
    }

    /**
     *  This is the function that should be called to get the newsupdater to
     *  start to start updating. Calls init to handle the command-line
     *  arguments then enters news_updaters main loop
     */
    function start()
    {
        global $argv;
        CrawlDaemon::init($argv, "news_updater");
        crawlLog("\n\nInitialize logger..", "news_updater", true);
        $this->sourceModel = new SourceModel();
        $this->loop();
    }

    /**
     * Main loop for the news updater.
     */
    function loop()
    {
        crawlLog("In News Update Loop");

        $info[self::STATUS] = self::CONTINUE_STATE;
        $local_archives = array("");
        while (CrawlDaemon::processHandler()) {
            $start_time = microtime();

            crawlLog("Checking if news feeds should be updated...");
            $this->newsUpdate();
            $sleep_time = max(0, ceil(
                MINIMUM_UPDATE_LOOP_TIME - changeInMicrotime($start_time)));
            if($sleep_time > 0) {
                crawlLog("Ensure minimum loop time by sleeping...".$sleep_time);
                sleep($sleep_time);
            }
        } //end while

        crawlLog("News Updater shutting down!!");
    }

    /**
     *  If news_update time has passed, then updates news feeds associated with
     *  this Yioop instance
     *
     *  @param array $data used by view to render itself. In this case, if there
     *      is a problem updating the news then we will flash a message
     *  @param bool $no_news_process if true than assume news_updater.php is
     *      not running. If false, assume being run from news_updater.php so
     *      update news_process cron time.
     */
    function newsUpdate()
    {
        if(!defined(SUBSEARCH_LINK)|| !SUBSEARCH_LINK) {
            crawlLog("No news update as SUBSEARCH_LINK define false.");
            return;
        }
        $time = time();
        $rss_feeds = $this->sourceModel->getMediaSources("rss");
        if(!$rss_feeds || count($rss_feeds) == 0) {
            crawlLog("No news update as no news feeds.");
            return;
        }
        $something_updated = false;

        $delta = $time - $this->update_time;
        // every hour get items from twenty feeds whose newest items are oldest
        if($delta > SourceModel::ONE_HOUR) {
            $this->update_time = $time;
            crawlLog("Performing news feeds update");
            if(!$this->sourceModel->updateFeedItems(
                SourceModel::ONE_WEEK, false)) {
                crawlLog("News feeds item update failed.");
            }
            $something_updated = true;
        }

        /*
            if anything changed rebuild shard
         */
        if($something_updated) {
            crawlLog("Deleting feed items and rebuild shard...");
            $this->sourceModel->rebuildFeedShard(SourceModel::ONE_WEEK);
            crawlLog("... delete complete, shard rebuilt");
        } else {
            crawlLog("No updates needed.");
        }
    }
}
/*
 *  Instantiate and runs the NewsUpdater program
 */
$news_updater =  new NewsUpdater();
$news_updater->start();

?>
