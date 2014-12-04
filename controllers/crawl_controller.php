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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
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
 * Controller used to manage networked installations of Yioop where
 * there might be mutliple queue_servers and a name_server. Command
 * sent to the nameserver web page are mapped out to queue_servers
 * using this controller. Each method of the controller essentially
 * mimics one method of CrawlModel, PhraseModel, or in general anything
 * that extends ParallelModel and is used to proxy that information
 * through a result web page back to the name_server.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class CrawlController extends Controller implements CrawlConstants
{
    /**
     * These are the activities supported by this controller
     * @var array
     */
    var $activities = array("sendStartCrawlMessage", "sendStopCrawlMessage",
        "crawlStalled", "crawlStatus", "deleteCrawl", "injectUrlsCurrentCrawl",
        "getCrawlList", "combinedCrawlInfo", "getInfoTimestamp",
        "getCrawlSeedInfo", "getNewsSources", "setCrawlSeedInfo", "getCrawlItems
        ", "countWords","clearQuerySavePoint");
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
        if(in_array($activity, $this->activities)) {
            $this->call($activity);
        }
    }
    /**
     * Handles a request for whether or not the crawl is stalled on the
     * given local server (which means no fetcher has spoken to it in a while)
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function crawlStalled()
    {
        echo webencode(serialize($this->model("crawl")->crawlStalled()));
    }
    /**
     * Handles a request for the crawl status (memory use, recent fetchers
     * crawl rate, etc) data from a remote name server
     * and retrieves that the statistic about this that are held by the
     * local queue server
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function crawlStatus()
    {
        echo webencode(serialize($this->model("crawl")->crawlStatus()));
    }
    /**
     * Handles a request for the starting parameters of a crawl of a given
     * timestamp and retrieves that information from the bundle held by the
     * local queue server
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function getCrawlSeedInfo()
    {
        $timestamp = 0;
        if(isset($_REQUEST["arg"]) ) {
            $timestamp = unserialize(webdecode($_REQUEST["arg"]));
            $timestamp = substr($this->clean($timestamp, "int"), 0,
                TIMESTAMP_LEN);
        }
        echo webencode(serialize($this->model("crawl")->getCrawlSeedInfo(
            $timestamp)));
    }
    /**
     * Handles a request to change the parameters of a crawl of a given
     * timestamp on the local machine (does nothing if crawl doesn't exist)
     */
    function setCrawlSeedInfo()
    {
        if(isset($_REQUEST["arg"]) ) {
            list($timestamp, $info) = unserialize(webdecode($_REQUEST["arg"]));
            $timestamp = substr($this->clean($timestamp, "int"), 0,
                TIMESTAMP_LEN);
            if($timestamp && $info) {
                $this->model("crawl")->setCrawlSeedInfo($timestamp, $info);
            }
        }
    }
    /**
     * Handles a request for information about a crawl with a given timestamp
     * from a remote name server and retrieves statistics about this crawl
     * that are held by the local queue server (number of pages, name, etc)
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function getInfoTimestamp()
    {
        $timestamp = 0;
        if(isset($_REQUEST["arg"]) ) {
            $timestamp = unserialize(webdecode($_REQUEST["arg"]));
            $timestamp = substr($this->clean($timestamp, "int"), 0,
                TIMESTAMP_LEN);
        }
        echo webencode(serialize($this->model("crawl")->getInfoTimestamp(
            $timestamp)));
    }
    /**
     * Handles a request for the crawl list (what crawl are stored on the
     * machine) data from a remote name server and retrieves the
     * statistic about this that are held by the local queue server
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function getCrawlList()
    {
        $return_arc_bundles = false;
        $return_recrawls = false;
        if(isset($_REQUEST["arg"]) ) {
            $arg = trim(webdecode($_REQUEST["arg"]));
            $arg = $this->clean($arg, "int");
            if($arg == 3 || $arg == 1) {$return_arc_bundles = true; }
            if($arg == 3 || $arg == 2) {$return_recrawls = true; }
        }
        echo webencode(serialize($this->model("crawl")->getCrawlList(
            $return_arc_bundles, $return_recrawls)));
    }
    /**
     * Handles a request for the combined crawl list, stalled, and status
     * data from a remote name server and retrieves that the statistic about
     * this that are held by the local queue server
     * outputs this info back as body of the http response (url encoded,
     * serialized php data)
     */
    function combinedCrawlInfo()
    {
        $combined =  $this->model("crawl")->combinedCrawlInfo();
        echo webencode(serialize($combined));
    }
    /**
     * Receives a request to delete a crawl from a remote name server
     * and then deletes crawl on the local queue server
     */
    function deleteCrawl()
    {
        if(!isset($_REQUEST["arg"]) ) {
            return;
        }
        $timestamp = substr($this->clean($timestamp, "int"), 0,
            TIMESTAMP_LEN);
        $timestamp = unserialize(webdecode($_REQUEST["arg"]));
        $this->model("crawl")->deleteCrawl($timestamp);
    }

    /**
     * Receives a request to inject new urls into the active
     * crawl from a remote name server and then does this for
     * the local queue server
     */
    function injectUrlsCurrentCrawl()
    {
        if(!isset($_REQUEST["arg"]) || !isset($_REQUEST["num"])
            || !isset($_REQUEST["i"])) {
            return;
        }
        $num = $this->clean($_REQUEST["num"], "int");
        $i = $this->clean($_REQUEST["i"], "int");
        list($timestamp, $inject_urls) =
            unserialize(webdecode($_REQUEST["arg"]));
        $timestamp = substr($this->clean($timestamp, "int"), 0,
            TIMESTAMP_LEN);
        $inject_urls = partitionByHash($inject_urls,
            NULL, $num, $i, "UrlParser::getHost");
        $this->model("crawl")->injectUrlsCurrentCrawl($timestamp,
            $inject_urls, NULL);
    }
    /**
     * Receives a request to get crawl summary data for an array of urls
     * from a remote name server and then looks these up on the local
     * queue server
     */
     function getCrawlItems()
     {
        $crawl_model = $this->model("crawl");
        $start_time = microtime();
        if(!isset($_REQUEST["arg"]) || !isset($_REQUEST["num"])
            || !isset($_REQUEST["i"])) {
            return;
        }
        $num = $this->clean($_REQUEST["num"], "int");
        $i = $this->clean($_REQUEST["i"], "int");
        $crawl_model->current_machine = $i;
        $lookups = unserialize(webdecode($_REQUEST["arg"]));
        $our_lookups = array();
        foreach($lookups as $lookup => $lookup_info) {
            if(count($lookup_info) == 2 && ($lookup_info[0][0] === 'h'
                || $lookup_info[0][0] === 'r'
                || $lookup_info[0][0] === 'g')) {
                $our_lookups[$lookup] = $lookup_info;
            } else {
                $our_lookups[$lookup] = array();
                foreach($lookup_info as $lookup_item) {
                    if(count($lookup_item) == 2) {
                        $our_lookups[$lookup][] = $lookup_item;
                    } else {
                        list($index, , , , ) = $lookup_item;
                        if($index == $i) {
                            $our_lookups[$lookup][] = $lookup_item;
                        }
                    }
                }
            }
        }
        $items = $crawl_model->getCrawlItems($our_lookups);
        $items["ELAPSED_TIME"] = changeInMicrotime($start_time);
        echo webencode(serialize($items));
     }
    /**
     * Receives a request to get counts of the number of occurrences of an
     * array of words a remote name server and then
     * determines and outputs these counts for the local queue server
     */
     function countWords()
     {
        if(!isset($_REQUEST["arg"]) ) {
            return;
        }
        $crawl_model = $this->model("crawl");
        list($words, $index_name) = unserialize(webdecode($_REQUEST["arg"]));
        $crawl_model->index_name = $index_name;
        echo webencode(serialize(
            $crawl_model->countWords($words)));
     }
    /**
     * Receives a request to stop a crawl from a remote name server
     * and then stop the current crawl on the local queue server
     */
    function sendStopCrawlMessage()
    {
        $this->model("crawl")->sendStopCrawlMessage();
    }
    /**
     * Receives a request to start a crawl from a remote name server
     * and then starts the crawl process on the local queue server
     */
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
        $this->model("crawl")->sendStartCrawlMessage($crawl_params, $seed_info,
            NULL);
    }
    /**
     * A save point is used to store to disk a sequence generation-doc-offset
     * pairs of a particular mix query when doing an archive crawl of a crawl
     * mix. This is used so that the mix can remember where it was the next
     * time it is invoked by the web app on the machine in question.
     * This function deletes such a save point associated with a timestamp
     */
    function clearQuerySavePoint()
    {
        if(!isset($_REQUEST["arg"])) {
            return;
        }
        $save_timestamp = substr($this->clean($_REQUEST["arg"], "int"), 0,
            TIMESTAMP_LEN);
        $this->model("crawl")->clearQuerySavePoint($save_timestamp);
    }
    /**
     * Handles the request to get the  array of news feed sources which hash to
     * a particular value i.e. match with the index of requesting machine's
     * hashed url/name from array of available machines hash 
     */
     function getNewsSources()
    {
        if(!isset($_REQUEST["arg"])) {
            return;
        }
        $current_machine = $this->clean(webdecode($_REQUEST["arg"]), "string");
        $pre_feeds = $this->model("source")->getMediaSources("rss");
        $pre_feeds = array_merge($pre_feeds,
        $this->model("source")->getMediaSources("html"));
        if(!$pre_feeds) { return false; }
        $feeds = array();
        foreach($pre_feeds as $pre_feed) {
            if(!isset($pre_feed['NAME'])) {continue; }
            $feeds[$pre_feed['NAME']] = $pre_feed;
            if($pre_feed['TYPE'] == 'html') {
                list($pre_feed['CHANNEL_PATH'], $pre_feed['ITEM_PATH'],
                    $pre_feed['TITLE_PATH'], $pre_feed['DESCRIPTION_PATH'],
                    $pre_feed['LINK_PATH']) = 
                    explode("###", html_entity_decode($pre_feed['AUX_INFO']));
            }
        }
        $machine_urls = $this->model("source")->getMachineUrls();
        $machine_array_length = count($machine_urls);
        $feed_hash_values = array();
        $i = 0;
        foreach($feeds as $feed) {
            if($feed) {
                $hash = unpack( "N" ,(md5(substr($feed['NAME'], -2))));
                $feed_hash_values[$i] = (($hash[1])%$machine_array_length);
                $i++;
            }
        }
        $i = 0;
        $machine_index_match = 0;
        foreach($machine_urls as $url) {
            $url_hash = unpack( "N" ,(md5(substr($url['URL'], -2))));
            $current_url_hash = ($url_hash[1]);
            if(strcmp($current_url_hash, $current_machine ) == 0){
                $machine_index_match = $i;
                break;
            }
            $i++;
        }
        $hash_value = $feed_hash_values[$machine_index_match];
        $news_sources = array();
        $i = 0;
        foreach($feeds as $feed) {
            $feed_hash = unpack( "N" ,(md5(substr($feed['NAME'], -2))));
            $current_feed_hash = (($feed_hash[1])%$machine_array_length);
            if(strcmp($hash_value,$current_feed_hash) == 0){
                 $news_sources[$i] = $feed['NAME'];
                 $i++;
            }
        }
        echo webencode(serialize($news_sources));
    }
}
?>
