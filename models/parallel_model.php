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
 * Crawl data is stored in an IndexArchiveBundle, 
 * so load the definition of this class
 */
require_once BASE_DIR."/lib/index_archive_bundle.php";
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
     *  {@inheritdoc}
     */
    function __construct($db_name = DB_NAME) 
    {
        parent::__construct($db_name);
    }

    /**
     * Get a summary of a document by the generation it is in
     * and its offset into the corresponding WebArchive.
     *
     * @param string $url of summary we are trying to look-up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array summary data of the matching document
     */
    function getCrawlItem($url, $machine_urls = NULL)
    {
        $results = $this->getCrawlItems(array($url), $machine_urls);
        $hash_url = crawlHash($url, true);
        if(isset($results[$hash_url])) {
            return $results[$hash_url];
        }
        return $results;
    }

    /**
     * Gets summaries for a set of document by their url
     *
     * @param string $urls whose summaries we are trying to look up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array of summary data for the matching documents
     */
    function getCrawlItems($urls, $machine_urls = NULL)
    {
        $summaries = array();
        if($machine_urls != NULL && !$this->isSingleLocalhost($machine_urls)) {
            $num_machines = count($machine_urls);
            $machines = array();
            foreach($urls as $url) {
                $index = calculatePartition($url, $num_machines, 
                    "UrlParser::getHost");
                $machines[] = $machine_urls[$index];
            }
            $page_set = $this->execMachines("getCrawlItems", 
                $machines, serialize(array($urls, $this->index_name) ) );
            if(is_array($page_set)) {

                foreach($page_set as $elt) {
                    $summaries = array_merge($summaries, unserialize(webdecode(
                        $elt[self::PAGE])));
                }
            }
            return $summaries;
        }

        $index_archive_name =self::index_data_base_name . $this->index_name;
        $index_archive = 
            new IndexArchiveBundle(CRAWL_DIR.'/cache/'.$index_archive_name);

        foreach($urls as $url) {
            list($summary_offset, $generation, $cache_partition) = 
                $this->lookupSummaryOffsetGeneration($url, $index_archive);
            $summary = $index_archive->getPage($summary_offset, $generation);
            $summary[self::CACHE_PAGE_PARTITION] = $cache_partition;
            $summaries[crawlHash($url, true)] = $summary;
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
    function lookupSummaryOffsetGeneration($url, $index_archive = NULL)
    {
        if($index_archive == NULL) {
            $index_archive_name =self::index_data_base_name . $this->index_name;
            $index_archive = new IndexArchiveBundle(
                CRAWL_DIR.'/cache/'.$index_archive_name);
        }
        $num_retrieved = 0;
        $pages = array();
        $summary_offset = NULL;
        $num_generations = $index_archive->generation_info['ACTIVE'];
        $word_iterator =
            new WordIterator(crawlHash("info:$url"), $index_archive);

        if(is_array($next_docs = $word_iterator->nextDocsWithWord())) {
             foreach($next_docs as $doc_key => $doc_info) {
                 $summary_offset =
                    $doc_info[CrawlConstants::SUMMARY_OFFSET];
                 $generation = $doc_info[CrawlConstants::GENERATION];
                 $cache_partition = $doc_info[CrawlConstants::SUMMARY][
                    CrawlConstants::CACHE_PAGE_PARTITION];
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
        return array($summary_offset, $generation, $cache_partition);
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
        foreach($machine_urls as $machine_url) {
            $sites[$i][CrawlConstants::URL] =  $machine_url;
            $post_data[$i] = $query."&i=$i";
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
