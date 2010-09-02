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
     * If iterating through the linked-list portions of the documents
     * the next byte offset in the WebArchive based linked-list
     * @var int
     */
    var $next_offset;
    /**
     * Block number of the last block of docs
     * @var int
     */
    var $last_pointed_block;
    /**
     * @var int
     */
    var $list_offset;

    /**
     * Pointers to offsets for blocks containing docs with the given word 
     * for the current generation
     * @var array
     */
    var $block_pointers;
    /**
     * Number of completely full blocks of documents for the current generation
     * @var int
     */
    var $num_full_blocks;
    /**
     * Number of generations word appears in
     * @var int
     */
    var $num_generations;
    /**
     * Used to store the contents of the last partially full block
     * @var int
     */
    var $last_block;
    /**
     * the info block of the WebArchive that the word lives in
     * @var object
     */
    var $info_block;
    /**
     * Stores the number of the current block of documents we are at in the
     * set of all blocks of BLOCK_SIZE many documents
     * @var int
     */
    var $current_pointer;

    /**
     * Creates a word iterator with the given parameters.
     *
     * @param string $word_key hash of word or phrase to iterate docs of 
     * @param object $index the IndexArchiveBundle to use
     * @param int $limit the first element to return from the list of docs
     *      iterated over
     * @param object $info_block the info block of the WebArchive
     *      associated with the word in the index. If NULL, then this will
     *      loaded in WordIterator::reset()
     */
    function __construct($word_key, $index, $limit = 0, $info_block = NULL)
    {
        $this->word_key = $word_key;
        $this->index = $index;
        $this->limit = $limit;
        $this->info_block = $info_block;
        $this->current_block_fresh = false;
        $this->reset();
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
        
        $partition = 
            WebArchiveBundle::selectPartition($this->word_key, 
                $this->index->num_partitions_index);
        if($this->info_block == NULL) {
            $this->info_block = 
                $this->index->getPhraseIndexInfo($this->word_key);
        }
        if($this->info_block !== NULL) {
            $this->num_generations = count($this->info_block['GENERATIONS']);
            $count_till_generation = $this->info_block[self::COUNT];

            while($this->limit >= $count_till_generation) {
                $this->info_block['CURRENT_GENERATION_INDEX']++;
                if($this->num_generations <= 
                    $this->info_block['CURRENT_GENERATION_INDEX']) {
                    $this->num_docs = 0;
                    $this->current_pointer = -1;
                    return;
                }
                $info_block = $this->index->getPhraseIndexInfo(
                    $this->word_key, 
                    $this->info_block['CURRENT_GENERATION_INDEX'], 
                    $this->info_block);
                if($info_block !== NULL) {
                    $this->info_block = $info_block;
                }
                $count_till_generation += $this->info_block[self::COUNT];
            }
            $this->seen_docs = $count_till_generation - 
                $this->info_block[self::COUNT];

        }


        $this->initGeneration();


    }

    /**
     * Sets up the iterator to iterate through the current generation.
     *
     * @return bool whether the initialization succeeds
     */
    function initGeneration()
    {

        if($this->info_block !== NULL) {
            $info_block = $this->index->getPhraseIndexInfo(
                $this->word_key, $this->info_block['CURRENT_GENERATION_INDEX'], 
                $this->info_block);
            if($info_block === NULL) {
                return false;
            }
            $this->info_block = & $info_block;
            $this->num_docs = $info_block['TOTAL_COUNT'];
            $this->num_docs_generation = $info_block[self::COUNT];

            $this->current_pointer = 
                max(floor(($this->limit - $this->seen_docs) / BLOCK_SIZE), 0);
            $this->seen_docs += $this->current_pointer*BLOCK_SIZE;
            $this->last_block = $info_block[self::END_BLOCK];
            $this->num_full_blocks = 
                floor($this->num_docs_generation / BLOCK_SIZE);
            if($this->num_docs_generation > COMMON_WORD_THRESHOLD) {
                $this->last_pointed_block = 
                    floor(COMMON_WORD_THRESHOLD / BLOCK_SIZE);
            } else {
                $this->last_pointed_block = $this->num_full_blocks;
            }

            for($i = 0; $i < $this->last_pointed_block; $i++) {
                if(isset($info_block[$i])) {
                    $this->block_pointers[$i] = $info_block[$i];
                }
            }
            
            if($this->num_docs_generation > COMMON_WORD_THRESHOLD) {
                if($info_block[self::LIST_OFFSET] === NULL) {
                    $this->list_offset = NULL;
                } else {
                    $this->list_offset = $info_block[self::LIST_OFFSET];
                }
            }

        } else {
            $this->num_docs = 0;
            $this->num_docs_generation = 0;
            $this->current_pointer = -1;
        }
        return true;
    }

    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        if($this->num_generations <= 
            $this->info_block['CURRENT_GENERATION_INDEX']) {
            $this->pages = NULL;
            return -1;
        }
        $generation = 
            $this->info_block['GENERATIONS'][
                $this->info_block['CURRENT_GENERATION_INDEX']];
        if($this->current_pointer >= 0) {
            if($this->current_pointer == $this->num_full_blocks) {
                $pages = $this->last_block;
            } else if ($this->current_pointer >= $this->last_pointed_block) {
                /* if there are more than COMMON_WORD_THRESHOLD many 
                   results and we're not at the last block yet
                 */
                if($this->list_offset === NULL) {
                    $this->pages = NULL;
                    return -1;
                }
                $offset = $this->list_offset;
                $found = false;
                do {
                    /* the link list is actually backwards to the order we want
                       For now, we cycle along the list from the last data
                       stored until we find the block we want. This is slow
                       but we are relying on the fact that each generation is
                       not too big.
                     */
                    $doc_block = $this->index->getWordDocBlock($this->word_key, 
                        $offset, $generation);
                    $word_keys = array_keys($doc_block);
                    $found_key = NULL;
                    foreach($word_keys as $word_key) {
                        if(strstr($word_key, $this->word_key.":")) {
                            $found_key = $word_key;
                            if(isset($doc_block[
                                $found_key][self::LIST_OFFSET])) {
                                //only one list offset/docblock
                                break;
                            }
                        }
                    }
                    if($found_key === NULL) {
                        break;
                    }
                    if(isset($doc_block[
                        $this->word_key.":".$this->current_pointer])) {
                        $found = true;
                        break;
                    }
                    $offset = $doc_block[$found_key][self::LIST_OFFSET];
                } while($offset != NULL);
                if($found != true) {
                    $pages = array();
                } else {
                    $pages = & $doc_block[
                        $this->word_key.":".$this->current_pointer];
                }
            } else {
                //first COMMON_WORD_THRESHOLD many results fast
                if(isset($this->block_pointers[$this->current_pointer])) {
                    $doc_block = $this->index->getWordDocBlock($this->word_key, 
                        $this->block_pointers[$this->current_pointer], 
                        $generation);
                    if(isset(
                        $doc_block[$this->word_key.":".$this->current_pointer]
                        )) {
                        $pages = &
                            $doc_block[
                                $this->word_key.":".$this->current_pointer];
                    } else {
                        $pages = array();
                    }
                } else {
                    $pages = array();
                }
            }

            if($this->seen_docs < $this->limit) {
                $diff_offset = $this->limit - $this->seen_docs;

                $pages = array_slice($pages, $diff_offset);
            }
            $this->pages = & $pages;
            $this->count_block = count($pages);
            return $pages;
        } else {
            $this->pages = NULL;
            return -1;
        }
    }


    /**
     * Forwards the iterator one group of docs
     */
    function advance() 
    {
        if($this->current_pointer < 0) {return;}

        $this->advanceSeenDocs();

        $this->current_pointer ++;
        if($this->current_pointer > $this->num_full_blocks) {
            $flag = false;
            while ($this->info_block['CURRENT_GENERATION_INDEX'] < 
                $this->num_generations - 1 && !$flag) {
                $this->info_block['CURRENT_GENERATION_INDEX']++;
                $flag = $this->initGeneration();
            } 
            if ($this->info_block['CURRENT_GENERATION_INDEX'] >= 
                $this->num_generations - 1) {
                $this->current_pointer = - 1;
            }
        }
    }
    
    /**
     * Returns the index associated with this iterator
     * @return object the index
     */
    function getIndex($key = NULL)
    {
        return $this->index;
    }
}
?>
