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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}


/** 
 * Loads common constants for web crawling, used for index_data_base_name and 
 * schedule_data_base_name 
 */
require_once BASE_DIR."/lib/crawl_constants.php";
/** 
 * Crawl data is stored in an IndexArchiveBundle, which are managed by the
 * IndexManager so load the definition of this class
 */
require_once BASE_DIR."/lib/index_manager.php";
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** 
 * Needed for getHost
 */
require_once BASE_DIR.'/lib/url_parser.php';
/** 
 * Needed to be able to send data via http to remote queue_servers
 */
require_once BASE_DIR.'/lib/fetch_url.php';

/**
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage model
 */
class ParallelModel extends Model implements CrawlConstants
{
    /**
     * Stores the name of the current index archive to use to get search 
     * results from
     * @var string
     */
    var $index_name;
    /**
     * If known the id of the queue_server this belongs to
     * @var int
     */
    var $current_machine;
    /**
     * the minimum length of a description before we stop appending
     * additional link doc summaries
     */
    const MIN_DESCRIPTION_LENGTH = 100;
    /**
     *  {@inheritdoc}
     */
    function __construct($db_name = DB_NAME) 
    {
        parent::__construct($db_name);
        $this->current_machine = 0;//if known, controller will set later
    }

    /**
     * Get a summary of a document by the generation it is in
     * and its offset into the corresponding WebArchive.
     *
     * @param string $url of summary we are trying to look-up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @param string $index_name timestamp of the index to do the lookup in
     * @return array summary data of the matching document
     */
    function getCrawlItem($url, $machine_urls = NULL, $index_name = "")
    {
        $hash_url = crawlHash($url, true);
        if($index_name == "") {
            $index_name = $this->index_name;
        }
        $results = $this->getCrawlItems(
            array($hash_url =>array($url, $index_name)), $machine_urls);
        if(isset($results[$hash_url])) {
            return $results[$hash_url];
        }
        return $results;
    }

    /**
     * Gets summaries for a set of document by their url, or by group of 
     * 4-tuples of the form (key, index, generation, offset).
     *
     * @param string $lookups things whose summaries we are trying to look up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array of summary data for the matching documents
     */
    function getCrawlItems($lookups, $machine_urls = NULL)
    {
        $summaries = array();
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $num_machines = count($machine_urls);
            $machines = array();
            foreach($lookups as $lookup => $lookup_info) {
                if(count($lookup_info) == 2 && $lookup_info[0][0] === 'h') {
                    list($url, $index_name) = $lookup_info;
                    $index = calculatePartition($url, $num_machines, 
                        "UrlParser::getHost");
                    $machines[$index] = $machine_urls[$index];
                } else {
                    foreach($lookup_info as $lookup_item) {
                        list($index, , , , ) = $lookup_item;
                        $machines[$index] = $machine_urls[$index];
                    }
                }
                
            }

            $page_set = $this->execMachines("getCrawlItems", 
                $machines, serialize($lookups));

            if(is_array($page_set)) {
                foreach($page_set as $elt) {
                    $result = unserialize(webdecode($elt[self::PAGE]));
                    foreach($result as $lookup => $summary) {
                        if(isset($summaries[$lookup])) {
                            if(isset($summary[self::DESCRIPTION])) {
                                if(!isset($summaries[$lookup][
                                    self::DESCRIPTION])){
                                    $summaries[$lookup][self::DESCRIPTION] = "";
                                }
                                $summaries[$lookup][self::DESCRIPTION] = " .. ".
                                     $summary[self::DESCRIPTION];
                            }
                            if(isset($summary[self::THUMB])) {
                                $summaries[$lookup][self::THUMB] = 
                                    $summary[self::THUMB];
                            }
                        } else {
                            $summaries[$lookup] =  $summary;
                        }
                    }
                }
            }
            return $summaries;
        }
        foreach($lookups as $lookup => $lookup_info) {
            if(count($lookup_info) == 2 && $lookup_info[0][0] === 'h') {
                list($url, $index_name) = $lookup_info;
                $index_archive = IndexManager::getIndex($index_name);
                list($summary_offset, $generation) = 
                    $this->lookupSummaryOffsetGeneration($url, $index_name);
                $summary = 
                    $index_archive->getPage($summary_offset, $generation);
            } else {
                $summary = array();
                foreach($lookup_info as $lookup_item) {
                    list($machine, $key, $index_name, $generation, 
                        $summary_offset) = 
                        $lookup_item;
                    $index = IndexManager::getIndex($index_name);
                    $index->setCurrentShard($generation, true);
                    $page = @$index->getPage($summary_offset);
                    if(!$page || $page == array()) {continue;}
                    $ellipsis_used = false;
                    if($summary == array()) {
                        $summary = $page;
                    } else if (isset($page[self::DESCRIPTION])) {
                        if(!isset($summary[self::DESCRIPTION])) {
                            $summary[
                                self::DESCRIPTION] = "";
                        }
                        $summary[self::DESCRIPTION].=
                            " .. ".$page[self::DESCRIPTION];
                        $ellipsis_used = true;
                    }
                    if($ellipsis_used && strlen($summary[self::DESCRIPTION]) > 
                        self::MIN_DESCRIPTION_LENGTH) {
                        /* want at least one ellipsis in case terms only
                           appear in links
                         */
                        break;
                    }
                }
            }
            $summaries[$lookup] = $summary;
        }

        return $summaries;
    }

    /**
     * Determines the offset into the summaries WebArchiveBundle of the
     * provided url so that the info:url summary can be retrieved.
     * This assumes of course that  the info:url meta word has been stored.
     *
     * @param string $url
     * @param object $index_archive
     * @return array (offset, generation) into the web archive bundle
     */
    function lookupSummaryOffsetGeneration($url, $index_name = "")
    {
        if($index_name == "") {
            $index_name = $this->index_name;
        }
        $index_archive = IndexManager::getIndex($index_name);
        $num_retrieved = 0;
        $pages = array();
        $summary_offset = NULL;
        $num_generations = $index_archive->generation_info['ACTIVE'];
        $word_iterator =
            new WordIterator(crawlHash("info:$url"), $index_name);

        if(is_array($next_docs = $word_iterator->nextDocsWithWord())) {
             foreach($next_docs as $doc_key => $doc_info) {
                 $summary_offset =
                    $doc_info[CrawlConstants::SUMMARY_OFFSET];
                 $generation = $doc_info[CrawlConstants::GENERATION];
                 $index_archive->setCurrentShard($generation, true);
                 $page = @$index_archive->getPage($summary_offset);
                 $num_retrieved++;
                 if($num_retrieved >=  1) {
                     break;
                 }
             }
             if($num_retrieved == 0) {
                return false;
             }
        } else {
            return false;
        }
        return array($summary_offset, $generation);
    }


    /**
     *  This method is invoked by other CrawlModel (for example, CrawlModel) 
     *  methods when they want to have their method performed 
     *  on an array of other  Yioop instances. The results returned can then 
     *  be aggregated.  The invocation sequence is 
     *  crawlModelMethodA invokes execMachine with a list of 
     *  urls of other Yioop instances. execMachine makes REST requests of
     *  those instances of the given command and optional arguments
     *  This request would be handled by a CrawlController which in turn
     *  calls crawlModelMethodA on the given Yioop instance, serializes the
     *  result and gives it back to execMachine and then back to the originally
     *  calling function.
     *
     *  @param string $command the CrawlModel method to invoke on the remote 
     *      Yioop instances
     *  @param array $machine_urls machines to invoke this command on
     *  @param string additional arguments to be passed to the remote machine
     *  @return array a list of outputs from each machine that was called.
     */
    function execMachines($command, $machine_urls, $arg = NULL)
    {
        $num_machines = count($machine_urls);
        $time = time();
        $session = md5($time . AUTH_KEY);
        $query = "c=crawl&a=$command&time=$time&session=$session" . 
            "&num=$num_machines";
        if($arg != NULL) {
            $arg = webencode($arg);
            $query .= "&arg=$arg";
        }

        $sites = array();
        $post_data = array();
        $i = 0;
        foreach($machine_urls as $index => $machine_url) {
            $sites[$i][CrawlConstants::URL] =  $machine_url;
            $post_data[$i] = $query."&i=$index";
            $i++;
        }
        $outputs = array();
        if(count($sites) > 0) {
            $outputs = FetchUrl::getPages($sites, false, 0, NULL, self::URL,
                self::PAGE, true, $post_data);
        }

        return $outputs;
    }

}
?>
