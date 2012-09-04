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
 * Base class of models that need access to data from multiple queue servers
 * Subclasses include @see CrawlModel and @see PhraseModel. 
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
     * 5-tuples of the form (machine, key, index, generation, offset).
     *
     * @param string $lookups things whose summaries we are trying to look up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array of summary data for the matching documents
     */
    function getCrawlItems($lookups, $machine_urls = NULL)
    {
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $summaries = $this->networkGetCrawlItems($lookups, $machine_urls);
        } else {
            $summaries = $this->nonNetworkGetCrawlItems($lookups);
        }
        return $summaries;
    }

    /**
     * In a multiple queue server setting, gets summaries for a set of document
     * by their url, or by group of 5-tuples of the form
     * (machine, key, index, generation, offset). This makes an execMachines
     * call to make a network request to the CrawlController's on each machine
     * which in turn calls getCrawlItems (and thence nonNetworkGetCrawlItems)
     * on each machine. The results are then sent back to networkGetCrawlItems
     * and aggregated.
     *
     * @param string $lookups things whose summaries we are trying to look up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array of summary data for the matching documents
     */
    function networkGetCrawlItems($lookups, $machine_urls)
    {
        //Set-up network request
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
                    if(count($lookup_item) == 5) {
                        list($index, , , , ) = $lookup_item;
                        $machines[$index] = $machine_urls[$index];
                    } else {
                        $machines = $machine_urls;
                        break;
                    }
                }
            }
            
        }
        //Make request
        $page_set = $this->execMachines("getCrawlItems", 
            $machines, serialize($lookups), $num_machines);

        //Aggregate results
        $summaries = array();
        if(is_array($page_set)) {
            foreach($page_set as $elt) {
                $description_hash = array();
                $result = unserialize(webdecode($elt[self::PAGE]));
                if(!is_array($result)) continue;
                $ellipsis = "";
                foreach($result as $lookup => $summary) {
                    if(isset($summaries[$lookup])) {
                        if(isset($summary[self::DESCRIPTION])) {
                            $description = trim($summary[self::DESCRIPTION]);
                            if(!isset($summaries[$lookup][self::DESCRIPTION])){
                                $summaries[$lookup][self::DESCRIPTION] = "";
                            }
                            if(!isset($description_hash[$description])){
                                $summaries[$lookup][self::DESCRIPTION] = 
                                    $ellipsis . $description;
                                $ellipsis = " .. ";
                                $description_hash[$description] = true;
                            }
                        }
                        foreach($summary as $attr => $value){
                            if($attr !=self::DESCRIPTION && 
                                !isset($summaries[$lookup][$attr])) {
                                $summaries[$lookup][$attr] = $value;
                            }
                        }
                    } else {
                        $summaries[$lookup] =  $summary;
                    }
                }
            }
        }
        return $summaries;
    }

    /**
     * Gets summaries on a particular machine for a set of document by 
     * their url, or by group of 5-tuples of the form
     * (machine, key, index, generation, offset) 
     * This may be used in either the single queue_server setting or
     * it may be called indirectly by a particular machine's 
     * CrawlController as part of fufilling a network-based getCrawlItems 
     * request. $lookups contains items which are to be grouped (as came
     * from same url or site with the same cache). So this function aggregates
     * their descriptions.
     *
     * @param string $lookups things whose summaries we are trying to look up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array of summary data for the matching documents
     */
    function nonNetworkGetCrawlItems($lookups)
    {
        $summaries = array();
        $db = $this->db;
        $db->selectDB(DB_NAME);
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
                $ellipsis = "";
                $description_hash = array();
                foreach($lookup_info as $lookup_item) {
                    if(count($lookup_item) == 2) {
                        list($word_key, $index_name) = $lookup_item;
                        $offset_info = 
                            $this->lookupSummaryOffsetGeneration(
                                $word_key, $index_name, true);
                        if(is_array($offset_info)) {
                            list($summary_offset, $generation) = $offset_info;
                        } else {
                            continue;
                        }
                    } else {
                        list($machine, $key, $index_name, $generation, 
                            $summary_offset) = $lookup_item;
                    }
                    if(strcmp($index_name, "feed") != 0) {
                        $index = IndexManager::getIndex($index_name);
                        $index->setCurrentShard($generation, true);
                        $page = @$index->getPage($summary_offset);
                    } else {
                        $guid = base64Hash(substr($key, 
                            IndexShard::DOC_KEY_LEN, 
                            IndexShard::DOC_KEY_LEN));
                        $sql = "SELECT * FROM FEED_ITEM WHERE GUID=".
                            "'$guid'";
                        $result = $db->execute($sql);
                        $page = false;
                        if($result) {
                            $row = $db->fetchArray($result);
                            if($row) {
                                $page[self::TITLE] = $row["TITLE"];
                                $page[self::DESCRIPTION] = $row["DESCRIPTION"];
                                $page[self::URL] = $row["LINK"];
                                $page[self::SOURCE_NAME] = $row["SOURCE_NAME"];
                            }
                        }
                    }
                    if(!$page || $page == array()) {continue;}
                    $copy = false;
                    if($summary == array()) {
                        if(isset($page[self::DESCRIPTION])) {
                            $description = trim($page[self::DESCRIPTION]);
                            $page[self::DESCRIPTION] = $description;
                            $description_hash[$description] = true;
                        }
                        $summary = $page;
                    } else if (isset($page[self::DESCRIPTION])) {
                        $description = trim($page[self::DESCRIPTION]);
                        if(!isset($summary[self::DESCRIPTION])) {
                            $summary[
                                self::DESCRIPTION] = "";
                        }
                        if(!isset($description_hash[$description])){
                            $summary[self::DESCRIPTION] .=
                                $ellipsis . $description;
                            $ellipsis = " .. ";
                            $description_hash[$description] = true;
                        }
                        $copy = true;
                    } else {
                        $copy = true;
                    }
                    if(strlen($summary[self::DESCRIPTION]) > 
                        self::MIN_DESCRIPTION_LENGTH) {
                        break;
                    }
                    if($copy) {
                        foreach($page as $attr => $value){
                            if($attr !=self::DESCRIPTION && 
                                !isset($summary[$attr])) {
                                $summary[$attr] = $value;
                            }
                        }
                    }
                }
            }
            if($summary != array()) {
                $summaries[$lookup] = $summary;
            }
        }

        return $summaries;
    }

    /**
     * Determines the offset into the summaries WebArchiveBundle and generation 
     * of the provided url (or hash_url) so that the info:url 
     * (info:base64_hash_url) summary can be retrieved. This assumes of course 
     * that the info:url  meta word has been stored.
     *
     * @param string $url_or_key either info:base64_hash_url or just a url to
     *      lookup
     * @param string $index_name index into which to do the lookup
     * @param bool $is_key whether the string is info:base64_hash_url or just a
     *      url
     * @return array (offset, generation) into the web archive bundle
     */
    function lookupSummaryOffsetGeneration($url_or_key, $index_name = "", 
        $is_key = false)
    {
        if($index_name == "") {
            $index_name = $this->index_name;
        }
        $index_archive = IndexManager::getIndex($index_name);
        $num_retrieved = 0;
        $pages = array();
        $summary_offset = NULL;
        $num_generations = $index_archive->generation_info['ACTIVE'];
        $hash_key = ($is_key) ? crawlHash($url_or_key) : 
            crawlHash("info:$url_or_key");
        $word_iterator = new WordIterator($hash_key, $index_name);

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
     *  This method is invoked by other ParallelModel (@see CrawlModel 
     *  for examples) methods when they want to have their method performed 
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
     *  @param string $command the ParallelModel method to invoke on the remote 
     *      Yioop instances
     *  @param array $machine_urls machines to invoke this command on
     *  @param string additional arguments to be passed to the remote machine
     *  @param int $num_machines the integer to be used in calculating partition
     *  @return array a list of outputs from each machine that was called.
     */
    function execMachines($command, $machine_urls, $arg = NULL, 
        $num_machines = 0)
    {
        if($num_machines == 0) {
            $num_machines = count($machine_urls);
        }
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
