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
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

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
 * @subpackage iterator
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
     */
    function __construct($index_bundle_iterators)
    {
        $this->index_bundle_iterators = $index_bundle_iterators;

        $this->num_iterators = count($index_bundle_iterators);
        $this->num_docs = -1;
        $this->results_per_block = 1;
        
        /*
             the most results we can return is the size of the least num_docs
             of what we are iterating over. We are also setting up here
             that we return at most one posting at a time from each
             iterator
        */
        for($i = 0; $i < $this->num_iterators; $i++) {
            if( $this->num_docs < 0 ||
                $this->index_bundle_iterators[$i]->num_docs < $this->num_docs) {
                $this->num_docs = $this->index_bundle_iterators[$i]->num_docs;
            }
            $this->index_bundle_iterators[$i]->setResultsPerBlock(1);
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

        $status = $this->syncDocOffsetsAmongstIterators();
        if($status == -1) {
            return -1;
        }
        $docs = $this->index_bundle_iterators[0]->currentDocsWithWord();
        $this->count_block = count($docs);
        $this->pages = $docs;
        return $docs;
    }

    /**
     *
     */
    function syncDocOffsetsAmongstIterators()
    {
        $biggest_offset = 0;
        $all_same = true;
        do{
            for($i = 0; $i < $this->num_iterators; $i++) {
                $new_doc_offset = 
                    $this->index_bundle_iterators[$i]->currentDocOffsetWithWord();
                if($i == 0) {
                    $biggest_offset = $new_doc_offset;
                }
                if($new_doc_offset == -1) {
                    return -1;
                }
                if($new_doc_offset > $biggest_offset) {
                    $biggest_offset = $new_doc_offset;
                    $all_same = false;
                }
            }
            if($all_same) {
                return 1;
            }
            for($i = 0; $i < $this->num_iterators; $i++) {
                $this->index_bundle_iterators[$i]->advance($biggest_offset);
            }
        } while(!$all_same);
    }

    /**
     * Forwards the iterator one group of docs
     * @param $doc_offset if set the next block must all have $doc_offsets 
     *      larger than or equal to this value
     */
    function advance($doc_offset = null) 
    {
        $this->advanceSeenDocs();

        $this->seen_docs_unfiltered = 0;

        //num_docs can change when advance() called so that's why we recompute
        $total_num_docs = 0;
        for($i = 0; $i < $this->num_iterators; $i++) {
             $this->seen_docs_unfiltered += 
                $this->index_bundle_iterators[$i]->seen_docs;
            $total_num_docs = $this->index_bundle_iterators[$i]->num_docs;
        }
        if($this->seen_docs_unfiltered > 0) {
            $this->num_docs = 
                floor(($this->seen_docs * $total_num_docs) /
                $this->seen_docs_unfiltered);
        } else {
            $this->num_docs = 0;
        }
        
        $this->index_bundle_iterators[0]->advance($doc_offset);

    }

    /**
     * Gets the doc_offset for the next document that would be return by
     * this iterator
     *
     * @return int the desired document offset
     */
    function currentDocOffsetWithWord() {
        $this->syncDocOffsetsAmongstIterators();
        $this->index_bundle_iterators[0]->currentDocOffsetWithWord();
    }

    /**
     * Returns the index associated with this iterator
     * @return object the index
     */
    function &getIndex($key = NULL)
    {
        return $this->index_bundle_iterators[0]->getIndex($key = NULL);
    }

    /**
     * This method is supposed to set
     * the value of the result_per_block field. This field controls
     * the maximum number of results that can be returned in one go by
     * currentDocsWithWord(). This method cannot be consistently
     * implemented for this iterator and expect it to behave nicely
     * it this iterator is used together with union_iterator. So
     * to prevent a user for doing this, calling this method results
     * in a user defined error
     *
     * @param int $num the maximum number of results that can be returned by
     *      a block
     */
     function setResultsPerBlock($num) {
        trigger_error("Cannot set the results per block of
            an intersect iterator", E_USER_ERROR);
     }
}
?>
