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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Load base controller class if needed */
require_once BASE_DIR."/controllers/controller.php";

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/** Need getHost to partition urls to different queue_servers*/
require_once BASE_DIR."/lib/url_parser.php";


/**
 * 
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class CrawlController extends Controller implements CrawlConstants
{ 
    /**
     * No models used by this controller
     * @var array
     */
    var $models = array("crawl");
    /**
     * Only outputs JSON data so don't need view
     * @var array
     */
    var $views = array();
    /**
     * These are the activities supported by this controller
     * @var array
     */
    var $activities = array("sendStartCrawlMessage", "sendStopCrawlMessage", 
        "crawlStalled", "crawlStatus", "deleteCrawl", "injectUrlsCurrentCrawl",
        "getCrawlList", "combinedCrawlInfo", "getInfoTimestamp");

    /**
     * Number of characters from end of most recent log file to return
     * on a log request
     */
    const LOG_LISTING_LEN = 100000;
    /**
     * Checks that the request seems to be coming from a legitimate fetcher then
     * determines which activity the fetcher is requesting and calls that
     * activity for processing.
     *
     */
    function processRequest() 
    {
        $data = array();

        /* do a quick test to see if this is a request seems like 
           from a legitimate machine
         */
        if(!$this->checkRequest()) {return; }

        $activity = $_REQUEST['a'];
        if(in_array($activity, $this->activities)) {$this->$activity();}
    }

    function crawlStalled()
    {
        echo webencode(serialize($this->crawlModel->crawlStalled()));
    }

    function crawlStatus()
    {
        echo webencode(serialize($this->crawlModel->crawlStatus()));
    }

    function getInfoTimestamp()
    {
        $timestamp = 0;
        if(isset($_REQUEST["arg"]) ) {
            $timestamp = unserialize(webdecode($_REQUEST["arg"]));
        }
        echo webencode(serialize($this->crawlModel->getInfoTimestamp(
            $timestamp)));
    }

    function getCrawlList()
    {
        $return_arc_bundles = false;
        $return_recrawls = false;
        if(isset($_REQUEST["arg"]) ) {
            $arg = $this->clean($_REQUEST["arg"], "int");
            if($arg == 3 || $arg == 1) {$return_arc_bundles = true; }
            if($arg == 3 || $arg == 2) {$return_recrawls = true; }
        }
        echo webencode(serialize($this->crawlModel->getCrawlList(
            $return_arc_bundles, $return_recrawls)));
    }

    function combinedCrawlInfo()
    {
        $combined =  $this->crawlModel->combinedCrawlInfo();
        echo webencode(serialize($combined));
    }

    function deleteCrawl()
    {
        if(!isset($_REQUEST["arg"]) ) {
            return;
        }
        $timestamp = unserialize(webdecode($_REQUEST["arg"]));
        $this->crawlModel->deleteCrawl($timestamp);
    }

    function injectUrlsCurrentCrawl()
    {
        if(!isset($_REQUEST["arg"]) || !isset($_REQUEST["num"])
            || !isset($_REQUEST["i"])) {
            return;
        }
        $inject_urls = unserialize(webdecode($_REQUEST["arg"]));
        $inject_urls = partitionByHash($inject_urls,
            NULL, $num, $i, "ParseUrl::getHost");
        $this->crawlModel->injectUrlsCurrentCrawl($inject_urls, NULL);
    }

    function sendStopCrawlMessage()
    {
        $this->crawlModel->sendStopCrawlMessage();
    }


    function sendStartCrawlMessage()
    {

        if(!isset($_REQUEST["arg"]) || !isset($_REQUEST["num"])
            || !isset($_REQUEST["i"])) {
            return;
        }
        $num = $this->clean($_REQUEST["num"], "int");
        $i = $this->clean($_REQUEST["i"], "int");
        list($crawl_params, 
            $seed_info) = unserialize(webdecode($_REQUEST["arg"]));
        $seed_info['seed_sites']['url'] = 
            partitionByHash($seed_info['seed_sites']['url'],
            NULL, $num, $i, "UrlParser::getHost");
        $this->crawlModel->sendStartCrawlMessage($crawl_params, $seed_info, 
            NULL);
    }


}
?>
