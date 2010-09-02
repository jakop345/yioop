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
 * Used to iterate over the documents which occur in all of a set of
 * WordIterator results
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 * @see IndexArchiveBundle
 */
class IntersectIterator extends IndexBundleIterator
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
     * Index of the iterator amongst those we are intersecting to advance
     * next
     * @var int
     */
    var $to_advance_index;

    /**
     * Creates an intersect iterator with the given parameters.
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

        $this->num_iterators = count($index_bundle_iterators);
        $this->num_docs = -1;
        
        /*
             the most results we can return is the size of the least num_docs
             of what we are itrerating over
        */
        for($i = 0; $i < $this->num_iterators; $i++) {
            if( $this->num_docs < 0 ||
                $this->index_bundle_iterators[$i]->num_docs < $this->num_docs) {
                $this->num_docs = $this->index_bundle_iterators[$i]->num_docs;
            }
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
     * @return mixed doc ids and rank if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        $pages = array();
        $high_ranks = array();
        $last = $this->num_iterators - 1;
        for($i = 0; $i < $this->num_iterators; $i++) {
            $pages[$i] = 
                $this->index_bundle_iterators[$i]->currentDocsWithWord();
            if(!is_array($pages[$i]) || count($pages[$i]) == 0) {
                $this->to_advance_index = $i;
                return $pages[$i];
            }
            list($low_ranks[$i], $high_ranks[$i]) = 
                $this->lowHighRanks($pages[$i], $i);
        }
        uasort($low_ranks, "docRankOrderCallback");

       $low_ranks = array_values($low_ranks);

       $low_rank = $low_ranks[$last][self::DOC_RANK];

       $this->to_advance_index = $low_ranks[0]["INDEX"];
       $this->count_block_unfiltered = count($pages[$this->to_advance_index]);

        $docs = array();
        $looping = true;

        while ($looping == true) {
            for($i = 0; $i <= $last; $i++) {
            list( ,$high_ranks[$i]) = 
                $this->lowHighRanks($pages[$i], $i, false);
            }
            $broke = false;
            $score = 0;
            $high_rank = $high_ranks[0][self::DOC_RANK];
            $high_key = $high_ranks[0]["KEY"];
            $high_index = $high_ranks[0]["INDEX"];
            $to_deletes = array();
            for($i = 1; $i <= $last; $i++) {
                if($high_ranks[$i][self::DOC_RANK] < $low_rank ) {
                    $looping = false;
                    break 2;
                }
                if($high_ranks[$i][self::DOC_RANK] > $high_rank ||
                    ($high_ranks[$i][self::DOC_RANK] == $high_rank &&
                        strcmp($high_ranks[$i]["KEY"], $high_key) > 0)
                    ) {
                    $broke = true;
                    $high_rank = $high_ranks[$i][self::DOC_RANK];
                    $high_index = $high_ranks[$i]["INDEX"];
                    $high_key = $high_ranks[$i]["KEY"];
                    $to_deletes[$high_index] = $high_key;
                } 
                $score += $high_ranks[$i][self::SCORE];
            }
            if($broke == false) {
                $docs[$high_key] = $pages[$high_index][$high_key];
                $docs[$high_key][self::SCORE] = $score;
                $to_deletes[$high_index] = $high_key;
            }

            foreach($to_deletes as $index => $key) {
                unset($pages[$index][$key]);
                if(count($pages[$index]) == 0) {
                    $looping = false;
                }
            }

        }
        $this->count_block = count($docs);
        $this->pages = $docs;
        return $docs;
    }

    /**
     * Given a collection of documents, returns info about the low and high
     * ranking documents. Namely, their ranks, keys, 
     * index in word iterator array, and scores
     *
     * @param array &$docs documents to get low high info from
     * @param int $index which word iterator these docs came from
     * @param boo $sort_flag whether to sort the docs (if true) or to assume
     *      the docs are already sorted by rank
     * @return array desired info
     */
    function lowHighRanks(&$docs, $index, $sort_flag = true)
    {
        if($sort_flag == true) {
            uasort($docs, "docRankOrderCallback");
        }
        reset($docs);
        $high = array();
        $high["KEY"] = key($docs);
        $high[self::DOC_RANK] = $docs[$high["KEY"]][self::DOC_RANK];
        $high[self::SCORE] = $docs[$high["KEY"]][self::SCORE];
        $high["INDEX"] = $index;
        end($docs);
        $low = array();
        $low["KEY"] = key($docs);
        $low[self::DOC_RANK] =  $docs[$low["KEY"]][self::DOC_RANK];
        $low[self::SCORE] =  $docs[$low["KEY"]][self::SCORE];
        $low["INDEX"] = $index;
        return array($low, $high);
    }

    /**
     * Forwards the iterator one group of docs
     */
    function advance() 
    {
        $this->advanceSeenDocs();

        	$this->seen_docs_unfiltered += $this->count_block_unfiltered;

        $min_num_docs = 10000000000;
        for($i = 0; $i < $this->num_iterators; $i++) {
            if($this->index_bundle_iterators[$i]->num_docs < $min_num_docs) {
                $min_num_docs = $this->index_bundle_iterators[$i]->num_docs;
            }
        }
        if($this->seen_docs_unfiltered > 0) {
            $this->num_docs = 
                floor(($this->seen_docs * $min_num_docs) /
                $this->seen_docs_unfiltered);
        } else {
            $this->num_docs = 0;
        }
        $this->index_bundle_iterators[$this->to_advance_index]->advance();

    }

    /**
     * Returns the index associated with this iterator
     * @return object the index
     */
    function getIndex($key = NULL)
    {
        return $this->index_bundle_iterators[0]->getIndex($key = NULL);
    }
}
?>
