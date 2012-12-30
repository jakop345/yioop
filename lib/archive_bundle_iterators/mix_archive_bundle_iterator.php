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
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load FileCache class in case used
 */
require_once(BASE_DIR."/lib/file_cache.php");

/**
 *Loads base class for iterating
 */
require_once BASE_DIR.
    '/lib/archive_bundle_iterators/archive_bundle_iterator.php';

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/locale_functions.php";

/**Load base controller class, if needed. */
require_once BASE_DIR."/controllers/search_controller.php";

/**
 * Used to do an archive crawl based on the results of a crawl mix.
 * the query terms for this crawl mix will have site:any raw 1 appended to them
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 */
class MixArchiveBundleIterator extends ArchiveBundleIterator
    implements CrawlConstants
{
    /**
     * Used to hold timestamp of the crawl mix being used to iterate over
     *
     * @var int
     */
    var $mix_timestamp;

    /**
     * Used to hold timestamp of the index archive bundle of output results
     *
     * @var int
     */
    var $result_timestamp;

    /**
     * count of how far our into the crawl mix we've gone.
     *
     * @var int
     */
    var $limit;


    /**
     * Creates a web archive iterator with the given parameters.
     *
     * @param string $imix_timestamp timestamp of the crawl mix to
     *      iterate over the pages of
     * @param string $result_timestamp timestamp of the web archive bundle
     *      results are being stored in
     * @param
     */
    function __construct($mix_timestamp, $result_timestamp)
    {
        global $INDEXING_PLUGINS;
        setLocaleObject(getLocaleTag());

        $this->mix_timestamp = $mix_timestamp;
        $this->result_timestamp = $result_timestamp;
        $this->query = "site:any m:".$mix_timestamp;
        $this->searchController = new SearchController($INDEXING_PLUGINS);
        $archive_name = $this->getArchiveName($result_timestamp);
        if(!file_exists($archive_name)) {
            mkdir($archive_name);
        }
        if(file_exists("$archive_name/iterate_status.txt")) {
            $this->restoreCheckpoint();
        } else {
            $this->reset();
        }
    }

    /**
     *  Get the filename of the file that says information about the
     *  current archive iterator (such as whether the end of the iterator
     *  has been reached)
     *
     *  @param int $timestamp of current archive crawl
     */
    function getArchiveName($timestamp)
    {
        return CRAWL_DIR."/schedules/".self::archive_iterator.$timestamp;
    }

    /**
     * Saves the current state so that a new instantiation can pick up just
     * after the last batch of pages extracted.
     */
    function saveCheckpoint($info = array())
    {
        if($info == array()) {
            $info["LIMIT"] = $this->limit;
            $info["END_OF_ITERATOR"] = $this->end_of_iterator;
        }
        $archive_name = $this->getArchiveName($this->result_timestamp);
        file_put_contents("$archive_name/iterate_status.txt",
            serialize($info));
    }

    /**
     * Restores state from a previous instantiation, after the last batch of
     * pages extracted.
     */
    function restoreCheckpoint()
    {
        $archive_name = $this->getArchiveName($this->result_timestamp);
        $info = unserialize(
            file_get_contents("$archive_name/iterate_status.txt"));
        if(isset($info["LIMIT"])) {
            $this->limit = $info["LIMIT"];
        }
        if(isset($info["END_OF_ITERATOR"])) {
            $this->end_of_iterator = $info["END_OF_ITERATOR"];
        } else {
            $this->end_of_iterator = false;
        }
    }

    /**
     * Estimates the importance of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return bool false we assume files were crawled roughly according to
     *      page importance so we use default estimate of doc rank
     */
    function weight(&$site)
    {
        return false;
    }

    /**
     * Gets the next $num many docs from the iterator
     *
     * @param int $num number of docs to get
     * @return array associative arrays for $num pages
     */
    function nextPages($num)
    {
        if($this->end_of_iterator) {
            $objects = array("NO_PROCESS" => false);
            return $objects;
        }
        $results = $this->searchController->queryRequest($this->query,
            $num, $this->limit, 1, $this->result_timestamp);
        $num_results = count($results["PAGES"]);
        if(isset($results["PAGES"]) && $num_results > 0 ) {
            $objects = $results["PAGES"];
            $this->limit += $num_results;
            $objects["NO_PROCESS"] = true;
            if($num_results < $num - 1) {
                $this->end_of_iterator = true;
            }
        } else {
            $objects = array("NO_PROCESS" => $results);
        }
        if(isset($results["SAVE_POINT"]) ){
            $end = true;
            foreach($results["SAVE_POINT"] as $save_point)  {
                if($save_point != -1) {
                    $end = false;
                }
            }
            $this->save_points = $results["SAVE_POINT"];
            if($end) {
                $this->end_of_iterator = true;
            }
        }

        $this->saveCheckpoint();
        return $objects;
    }

    /**
     * Resets the iterator to the start of the archive bundle
     */
    function reset()
    {
        $this->limit = 0;
        $this->end_of_iterator = false;
        $this->searchController->clearQuerySavepoint($this->result_timestamp);
        $this->saveCheckpoint();
    }
}
?>