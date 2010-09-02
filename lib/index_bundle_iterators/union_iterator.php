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
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** 
 *Loads common constants for word indexing
 */
require_once BASE_DIR.'/lib/indexing_constants.php';

/** 
 *Loads BloomFilterFile to remember things we've already grouped
 */
require_once BASE_DIR.'/lib/bloom_filter_file.php';


/** 
 *Loads base class for iterating
 */
require_once BASE_DIR.'/lib/index_bundle_iterators/index_bundle_iterator.php';

/**
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 * @see IndexArchiveBundle
 */
class UnionIterator extends IndexBundleIterator
{
    /**
     * An array of iterators whose interection we  get documents from
     * @var array
     */
    var $index_bundle_iterators;
    /**
     * Number of elements in $this->index_bundle_iterators
     * @var int
     */
    var $num_iterators;

    /**
     * The number of documents in the current block before filtering
     * by restricted words
     * @var int
     */
    var $count_block_unfiltered;
    /**
     * The number of documents in the current block after filtering
     * by restricted words
     * @var int
     */
    var $count_block;

    /**
     * The number of iterated docs before the restriction test
     * @var int
     */
    var $seen_docs_unfiltered;


    /**
     * Creates a union iterator with the given parameters.
     *
     * @param object $index_bundle_iterator to use as a source of documents
     *      to iterate over
     * @param int $limit the first element to return from the list of docs
     *      iterated over
     */
    function __construct($index_bundle_iterators, $limit = 0)
    {
        $this->index_bundle_iterators = $index_bundle_iterators;
        $this->limit = $limit;
        /*
            estimate number of results by sum of all iterator counts,
            then improve estimate as iterate
        */
        $this->num_iterators = count($index_bundle_iterators);
        $this->num_docs = 0;
        for($i = 0; $i < $this->num_iterators; $i++) {
            $this->num_docs += $this->index_bundle_iterators[$i]->num_docs;
        }
        $this->reset();
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    function reset()
    {
        foreach($this->index_bundle_iterators as $iterator) {
            $iterator->reset();
        }

        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
        $beneath_limit = true;
        while($beneath_limit == true) {
            $doc_block = $this->currentDocsWithWord();
            if($doc_block == -1 || !is_array($doc_block)) {
                $beneath_limit = false;
                continue;
            }
            if($this->seen_docs + $this->count_block >= $this->limit) {
                $beneath_limit = false;
                continue;
            }
            $this->advance();
        }
    }

    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        $pages = array();
        $docs = array();
        $high_score = array();
        $high_score = array();
        $found_docs = false;
        for($i = 0; $i < $this->num_iterators; $i++) {
            $docs =  $this->index_bundle_iterators[$i]->currentDocsWithWord();
            if(is_array($docs)) {
                $doc_keys = array_keys($docs);
                foreach($doc_keys as $key) {
                    $docs[$key]["ITERATOR"] = $i;
                }
                $pages = array_merge($pages, $docs);
                $found_docs = true;
            }

        }
        if($found_docs == false) {
            $this->pages = $docs;
            return $docs;
        }
        $this->count_block_unfiltered = count($pages);
        $this->pages = $pages;
        $this->count_block = count($pages);
        return $pages;
    }

    /**
     * Gets the summaries associated with the keys provided the keys
     * can be found in the current block of docs returned by this iterator
     * @param array $keys keys to try to find in the current block of returned
     *      results
     * @return array doc summaries that match provided keys
     */
    function getSummariesFromCurrentDocs($keys = NULL) 
    {
        if($this->current_block_fresh == false) {
            $result = $this->currentDocsWithWord();
            if(!is_array($result)) {
                return $result;
            }
        }
        if(!is_array($this->pages)) {
            return $this->pages;
        }
        if($keys == NULL) {
            $keys = array_keys($this->pages);
        }
        $out_pages = array();
        echo "hello".$this->pages[$key[0]]["ITERATOR"]."<br/>";
        foreach($keys as $doc_key) {
            if(!isset($this->pages[$doc_key]["ITERATOR"])) {
                continue;
            } else {
                $out_pages[$doc_key] = $this->index_bundle_iterators[
                    $this->pages[
                        $doc_key]["ITERATOR"]]->getSummariesFromCurrentDocs(
                            array($doc_key));
            }
        }
        return $out_pages;
    }

    /**
     * Forwards the iterator one group of docs
     */
    function advance() 
    {
        $this->advanceSeenDocs();

        	$this->seen_docs_unfiltered += $this->count_block_unfiltered;

        $total_num_docs = 0;
        for($i = 0; $i < $this->num_iterators; $i++) {
            $total_num_docs += $this->index_bundle_iterators[$i]->num_docs;
            $this->index_bundle_iterators[$i]->advance();
        }
        if($this->seen_docs_unfiltered > 0) {
            $this->num_docs = 
                floor(($this->seen_docs * $total_num_docs) /
                $this->seen_docs_unfiltered);
        } else {
            $this->num_docs = 0;
        }
    }

    /**
     * Returns the index associated with this iterator
     * @return object the index
     */
    function getIndex($key = NULL)
    {
        if($key != NULL) {
            if($this->current_block_fresh == false) {
                $result = $this->currentDocsWithWord();
                if(!is_array($result)) {
                    return $this->index_bundle_iterators[0]->getIndex($key);
                }
            }
            if(!isset($this->pages[$key]["ITERATOR"])) {
                return $this->index_bundle_iterators[0]->getIndex($key);
            }
            return $this->index_bundle_iterators[
                $this->pages[$key]["ITERATOR"]]->getIndex($key);
        } else {
            return $this->index_bundle_iterators[0]->getIndex($key);
        }
    }
}
?>
