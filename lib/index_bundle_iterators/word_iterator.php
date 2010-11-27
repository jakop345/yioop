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
 *Loads base class for iterating
 */
require_once BASE_DIR.'/lib/index_bundle_iterators/index_bundle_iterator.php';

/**
 * Used to iterate through the documents associated with a word in
 * an IndexArchiveBundle. It also makes it easy to get the summaries
 * of these documents.
 *
 * A description of how words and the documents containing them are stored 
 * is given in the documentation of IndexArchiveBundle. To iterate over
 * all documents containng a word, its hash, work_key, is formed. Then using
 * the Bloom filter for that partition, it is determined if the word is stored
 * at all, and if it is, which generations it occurs in. Then the iterator
 * is set to point to the first block of the first generation the word appears
 * in that is greater than the limit of the WordIterator. Thereafter, 
 * nextDocsWithWord will advance $this->current_pointer by one per call.
 * $this->current_pointer keeps track of which block of documents containing
 * the word to return. If it is less than COMMON_WORD_THRESHOLD/BLOCK_SIZE and 
 * there are still more blocks, then the corresponding block_pointer of the word 
 * from the generation's partition info_block is used to look up the offset to
 * the doc block. If it is greater than this value then the linked list 
 * of doc blocks pointed to for the partition is followed to get the appropriate
 * block. This list is in the order that words were stored in the index so
 * LIST_OFFSET points to the last block stored, which in turn points to the
 * next to last block, etc. Finally, when all the blocks in the linked-list are
 * exhausted, the remaining docs for that generation for that word are stored 
 * in the info block for the word itself (this will always be less than 
 * BLOCK_SIZE many). Once all the docs for a word for a generation have been
 * iterated through, than iteration proceeds to the next generation containing
 * the word.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 * @see IndexArchiveBundle
 */
class WordIterator extends IndexBundleIterator
{
    /**
     * hash of word that the iterator iterates over 
     * @var string
     */
    var $word_key;
    /**
     * The IndexArchiveBundle this index is associated with
     * @var object
     */
    var $index;

    /**
     * The next byte offset in the IndexShard
     * @var int
     */
    var $next_offset;

    /**
     * The current byte offset in the IndexShard
     * @var int
     */
    var $current_offset;

    /**
     * Starting Offset of word occurence in the IndexShard
     * @var int
     */
    var $start_offset;

    /**
     * Last Offset of word occurence in the IndexShard
     * @var int
     */
    var $last_offset;

    /**
     * Keeps track of whether the word_iterator list is empty becuase the
     * word does not appear in the index shard
     * @var int
     */
    var $empty;




    /**
     * Creates a word iterator with the given parameters.
     *
     * @param string $word_key hash of word or phrase to iterate docs of 
     * @param object $index the IndexArchiveBundle to use
     * @param int $limit the first element to return from the list of docs
     *      iterated over
     * @param bool $raw whether the $word_key is our variant of base64 encoded
     */
    function __construct($word_key, $index, $raw = false)
    {
        $this->word_key = $word_key;
        $this->index =  $index;
        $this->current_block_fresh = false;

        $tmp = $index->getCurrentShard()->getWordInfo($word_key, $raw);
        if ($tmp === false) {
            $this->empty = true;
        } else {
            list($this->start_offset, $this->last_offset, $this->num_docs) 
                = $tmp;
            $this->current_offset = $this->start_offset;
            $this->empty = false;

            $this->reset();
        }
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     *
     */
    function reset()
    {
        $this->count_block = 0;
        $this->seen_docs = 0;

    }


    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        if($this->current_offset > $this->last_offset || $this->empty) {
            return -1;
        }
        $this->next_offset = $this->current_offset;
        $results = $this->index->getCurrentShard()->getPostingsSlice(
            $this->next_offset, $this->last_offset, $this->results_per_block);
        return $results;
    }


    /**
     * Forwards the iterator one group of docs
     * @param $doc_offset if set the next block must all have $doc_offsets 
     *      larger than or equal to this value
     */
    function advance($doc_offset = null) 
    {
        $this->advanceSeenDocs();
        if($this->current_offset < $this->next_offset) {
            $this->current_offset = $this->next_offset;
            if($doc_offset !== null) {
                $this->current_offset = 
                    $this->index->getCurrentShard(
                        )->nextPostingOffsetDocOffset($this->next_offset, 
                            $this->last_offset, $doc_offset);
                $this->seen_docs = 
                    ($this->current_offset - $this->start_offset)/
                        IndexShard::POSTING_LEN;
            }
        } else {
            $this->current_offset = $this->last_offset + 1;
        }
    }


    /**
     * Gets the doc_offset for the next document that would be return by
     * this iterator
     *
     * @return int the desired document offset
     */
    function currentDocOffsetWithWord() {
        if($this->current_offset > $this->last_offset) {
            return -1;
        }
        return $this->index->getCurrentShard(
                        )->docOffsetFromPostingOffset($this->current_offset);
    }

    /**
     * Returns the index associated with this iterator
     * @return &object the index
     */
    function &getIndex($key = NULL)
    {
        return $this->index;
    }
}
?>
