<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2013
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

ini_set("memory_limit", "250M");  //so have enough memory to crawl sitemaps

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
define("MINIMUM_UPDATE_LOOP_TIME", 30);

/** for crawlDaemon function */
require_once BASE_DIR."/lib/crawl_daemon.php";

/** To guess language based on page encoding */
require_once BASE_DIR."/lib/locale_functions.php";

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/locale_functions.php";

/**Load base controller class, if needed. */
require_once BASE_DIR."/controllers/search_controller.php";

/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/**
 *
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 */
class NewsUpdater implements CrawlConstants
{

    /**
     * Sets up the field variables so that newsupdating can begin
     *
     */
    function __construct()
    {
        $locale_tag = guessLocale();
        setLocaleObject($locale_tag);
        $this->searchController = NULL;
    }

    /**
     *  This is the function that should be called to get the fetcher to start
     *  fetching. Calls init to handle the command-line arguments then enters
     *  the fetcher's main loop
     */
    function start()
    {
        global $argv;
        global $INDEXING_PLUGINS;

        // To use CrawlDaemon need to declare ticks first
        declare(ticks=200);
        CrawlDaemon::init($argv, "news_updater");
        crawlLog("\n\nInitialize logger..", "news_updater");
        $this->searchController = new SearchController($INDEXING_PLUGINS);
        $this->loop();
    }

    /**
     * Main loop for the news updater.
     *
     */
    function loop()
    {
        crawlLog("In News Update Loop");

        $info[self::STATUS] = self::CONTINUE_STATE;
        $local_archives = array("");
        while ($info[self::STATUS] != self::STOP_STATE) {
            $start_time = microtime();

            crawlLog("Checking if news feeds should be updated...");
            $data = array();
            $this->searchController->newsUpdate($data, false);
            if(isset($data['LOG_MESSAGES'])) {
                crawlLog($data['LOG_MESSAGES']);
            }
            $sleep_time = max(0, ceil(
                MINIMUM_UPDATE_LOOP_TIME - changeInMicrotime($start_time)));
            if($sleep_time > 0) {
                crawlLog("Ensure minimum loop time by sleeping...".$sleep_time);
                sleep($sleep_time);
            }
        } //end while

        crawlLog("News Updater shutting down!!");
    }


}


/*
 *  Instantiate and runs the Fetcher
 */
$news_updater =  new NewsUpdater();
$news_updater->start();

?>
