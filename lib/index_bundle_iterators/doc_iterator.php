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
 *Loads base class for iterating
 */
require_once BASE_DIR.'/lib/index_bundle_iterators/index_bundle_iterator.php';

/**
 * Used to iterate through all the documents and links associated with a
 * an IndexArchiveBundle. It iterates through each doc or link regarless of
 * the words it contains. It also makes it easy to get the summaries
 * of these documents.
 *
 * A description of how words and the documents containing them are stored
 * is given in the documentation of IndexArchiveBundle.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
class DocIterator extends IndexBundleIterator
{

    /**
     * The timestamp of the index is associated with this iterator
     * @var string
     */
    var $index_name;

    /**
     * The next byte offset of a doc in the IndexShard
     * @var int
     */
    var $next_offset;

    /**
     * Last Offset of a doc occurence in the IndexShard
     * @var int
     */
    var $last_offset;

    /**
     * The current byte offset in the IndexShard
     * @var int
     */
    var $current_offset;

    /**
     * An array of shard docids_lens
     * @var array
     */
    var $shard_lens;

    /**
     * The total number of shards that have data for this word
     * @var int
     */
    var $num_generations;

    /**
     * Numeric number of current shard
     * @var int
     */
    var $current_generation;


    /**
     * Used to keep track of docs to filter out of results
     * @var int
     */
    var $filter;

    /** Host Key position + 1 (first char says doc, inlink or eternal link)*/
    const HOST_KEY_POS = 17;

    /** Length of a doc key*/
    const KEY_LEN = 8;

    /**
     * Creates a word iterator with the given parameters.

     * @param string $index_name time_stamp of the to use
     * @param int $limit the first element to return from the list of docs
     *      iterated over
     * @param array $filter an array of hashes of domains to filter from
     *      results
     */
    function __construct($index_name, &$filter = NULL,
        $results_per_block = IndexBundleIterator::RESULTS_PER_BLOCK)
    {
        if($filter != NULL) {
            $this->filter = & $filter;
        } else {
            $this->filter = NULL;
        }

        $this->index_name =  $index_name;
        $index = IndexManager::getIndex($index_name);
        $info = $index->getArchiveInfo($index->dir_name);
        $this->num_docs = $info['COUNT'];
        $this->num_generations = (isset($index->generation_info['ACTIVE'])) ?
            $index->generation_info['ACTIVE'] + 1 : 0;

        $this->results_per_block = $results_per_block;
        $this->current_block_fresh = false;

        $this->reset();
    }

    /**
     * Computes a relevancy score for a posting offset with respect to this
     * iterator and generation. This method is required from the base class,
     * but as a doc iterator doesn't use a posting list, we just resturn 1.0
     * for this iiterator.
     *
     * @param int $generation the generation the posting offset is for
     * @param int $posting_offset an offset into word_docs to compute the
     *      relevance of
     * @return float always 1.0.
     */
    function computeRelevance($generation, $posting_offset)
    {
        return 1.0;
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     *
     */
    function reset()
    {

        $this->current_generation = 0;
        $this->count_block = 0;
        $this->seen_docs = 0;
        $this->current_offset = 0;
        $this->next_offset = 0;
        $this->getShardInfo($this->current_generation);
    }

    /**
     *
     */
    function getShardInfo($generation)
    {
        if(isset($this->shard_lens[$generation])) {
            $this->last_offset = $this->shard_lens[$generation];
        } else {
            $index = IndexManager::getIndex($this->index_name);
            $index->setCurrentShard($generation, true);
            $shard = $index->getCurrentShard();
            $this->last_offset = $shard->docids_len;
            $this->shard_lens[$generation] = $shard->docids_len;
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
        if(($this->current_generation >= $this->num_generations)
            || ($this->current_generation == $this->num_generations - 1 &&
            $this->current_offset > $this->last_offset)) {
            return -1;
        }
        $pre_results = array();
        $this->next_offset = $this->current_offset;
        $index = IndexManager::getIndex($this->index_name);
        $index->setCurrentShard($this->current_generation, true);

        //the next call also updates next offset
        $shard = $index->getCurrentShard();
        $this->getShardInfo($this->current_generation);
        $doc_key_len = IndexShard::DOC_KEY_LEN;
        $num_docs_or_links = $shard->num_docs + $shard->num_link_docs;
        $pre_results = array();
        $num_docs_so_far = 0;
        do {
            if($this->next_offset >= $this->last_offset) {break;}
            $posting = packPosting($this->next_offset >> 4, array(1));
            list($doc_id, $num_keys, $item) =
                $shard->makeItem($posting, $num_docs_or_links);
            $this->next_offset += ($num_keys + 1) * $doc_key_len;
            $pre_results[$doc_id] = $item;
            $num_docs_so_far ++;
        } while ($num_docs_so_far <  $this->results_per_block);


        $results = array();
        $doc_key_len = IndexShard::DOC_KEY_LEN;
        $filter = ($this->filter == NULL) ? array() : $this->filter;

        foreach($pre_results as $keys => $data) {
            $host_key = substr($keys, self::HOST_KEY_POS, self::KEY_LEN);
            if(in_array($host_key, $filter) ) {
                continue;
            }
            $data[self::KEY] = $keys;
            // inlinks is the domain of the inlink
            list($hash_url, $data[self::HASH], $data[self::INLINKS]) =
                str_split($keys, $doc_key_len);
            $data[self::CRAWL_TIME] = $this->index_name;
            $results[$keys] = $data;
        }
        $this->count_block = count($results);
        if($this->current_generation == $this->num_generations - 1 &&
            $results == array()) {
            $results = NULL;
        }
        $this->pages = $results;
        return $results;
    }

    /**
     * Updates the seen_docs count during an advance() call
     */
    function advanceSeenDocs()
    {
        if($this->current_block_fresh != true) {
            $doc_item_len = 4*IndexShard::DOC_KEY_LEN;
            $num_docs = min($this->results_per_block,
                ($this->last_offset - $this->next_offset)/ $doc_item_len);
            $this->next_offset = $this->current_offset;
            $this->next_offset += $doc_item_len * $num_docs;
            if($num_docs < 0) {
                return;
            }
        } else {
            $num_docs = $this->count_block;
        }
        $this->current_block_fresh = false;
        $this->seen_docs += $num_docs;
    }

    /**
     * Forwards the iterator one group of docs
     * @param array $gen_doc_offset a generation, doc_offset pair. If set,
     *      the must be of greater than or equal generation, and if equal the
     *      next block must all have $doc_offsets larger than or equal to
     *      this value
     */
    function advance($gen_doc_offset = null)
    {
        $this->advanceSeenDocs();
        if($this->current_offset < $this->next_offset) {
            $this->current_offset = $this->next_offset;
        } else {
            $this->advanceGeneration();
            $this->next_offset = $this->current_offset;
        }
        if($this->current_offset > $this->last_offset) {
            $this->advanceGeneration();
            $this->next_offset = $this->current_offset;
        }
        if($gen_doc_offset !== null) {
            if($this->current_generation < $gen_doc_offset[0]) {
                $this->advanceGeneration($gen_doc_offset[0]);
                $this->next_offset = $this->current_offset;
            }

            if($this->current_generation == $gen_doc_offset[0]) {
                $this->current_offset = max($this->current_offset,
                    $gen_doc_offset[1]);
                if($this->current_offset > $this->last_offset) {
                    $this->advanceGeneration();
                    $this->next_offset = $this->current_offset;
                }
            }
            $this->seen_docs =
                $this->current_offset/
                    4*IndexShard::DOC_KEY_LEN;
        }
    }


    /**
     * Switches which index shard is being used to return occurrences of
     * the word to the next shard containing the word
     *
     * @param int $generation generation to advance beyond
     */
    function advanceGeneration($generation = null)
    {
        if($generation === null) {
            $generation = $this->current_generation + 1;
        }
        $this->current_generation = $generation;
        $this->current_offset = 0;
        if($generation < $this->num_generations) {
            $this->getShardInfo($generation);
        }
    }


    /**
     * Gets the doc_offset and generation for the next document that
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset
     *  and generation; -1 on fail
     */
    function currentGenDocOffsetWithWord() {
        if(($this->current_offset > $this->last_offset ||
            $this->current_generation >= $this->num_generations)) {
            return -1;
        }
        return array($this->current_generation, $this->current_offset);
    }

}
?>
