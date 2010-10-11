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
 *Loads common constants for word indexing
 */
require_once BASE_DIR.'/lib/indexing_constants.php';

/**
 * Abstract classed used to model iterating documents indexed in 
 * an IndexArchiveBundle or set of such bundles. 
 *
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
abstract class IndexBundleIterator implements IndexingConstants, CrawlConstants
{

    /**
     * Estimate of the number of documents that this iterator can return
     * @var int
     */
    var $num_docs;

    /**
     * The number of documents already iterated over
     * @var int
     */
    var $seen_docs;

    /**
     * The number of documents in the current block
     * @var int
     */
    var $count_block;

    /**
     * Cache of what currentDocsWithWord returns
     * @var array
     */
    var $pages;
    
    /**
     * Says whether the value in $this->count_block is up to date
     * @var bool
     */
    var $current_block_fresh;



    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    abstract function reset();

    /**
     * Forwards the iterator one group of docs
     */
    abstract function advance();
    /**
     * Returns the index associated with this iterator
     * @return object the index
     */
    abstract function &getIndex($key = NULL);

    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
     abstract function findDocsWithWord();
     
    /**
     * Gets the current block of doc ids and score associated with the
     * this iterators word
     *
     * @param bool $with_summaries specifies whether or not to return the
     *      summaries associated with the document
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function currentDocsWithWord()
    {
        if($this->current_block_fresh == true) {
            return $this->pages;
        }
        $this->current_block_fresh = true;
        return $this->findDocsWithWord();
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

        $index = $this->getIndex();
        if($this->current_block_fresh == false) {
            $pages = $this->currentDocsWithWord();
            if(!is_array($pages)) {
                return $pages;
            }
        } else {
            $pages = & $this->pages;
        }
        if($keys == NULL) {
            if(is_array($pages)) {
                $keys = array_keys($pages);
            } else {
                return NULL;
            }
        }
        $out_pages = array();

        foreach($keys as $doc_key) {
            if(!isset($pages[$doc_key])) {
                continue;
            } else {
                $doc_info = $pages[$doc_key];
            }
            if(isset($doc_info[self::SUMMARY_OFFSET])) {
                $page = $index->getPage(
                    $doc_key, $doc_info[self::SUMMARY_OFFSET]);
                $out_pages[$doc_key] = $doc_info;
                $out_pages[$doc_key][self::SUMMARY] = $page;
            }
        };
        return $out_pages;
    }

    /**
     * Get the current block of doc summaries for the word iterator and advances
     * the current pointer to the next block
     *
     * @return array doc summaries matching the $this->restrict_phrases
     */
    function nextDocsWithWord()
    {
        $doc_block = $this->getSummariesFromCurrentDocs();
        
        if($doc_block == -1 || !is_array($doc_block) ) {
            return NULL;
        }

        $this->advance();
        
        return $doc_block;

    }

    /**
     * Updates the seen_docs count during an advance() call
     */
    function advanceSeenDocs()
    {

        if($this->current_block_fresh != true) {
            $doc_block = $this->currentDocsWithWord();
            if($doc_block == -1 || !is_array($doc_block) ) {
                return;
            }
        }
        $this->current_block_fresh = false;
        $this->seen_docs += $this->count_block;
    }

}
?>
