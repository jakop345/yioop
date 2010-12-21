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
 * This iterator is used to group together documents or document parts
 * which share the same url. For instance, a link document item and 
 * the document that it links to will both be stored in the IndexArchiveBundle
 * by the QueueServer. This iterator would combine both these items into
 * a single document result with a sum of their score, and a summary, if 
 * returned, containing text from both sources. The iterator's purpose is
 * vaguely analagous to a SQL GROUP BY clause
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see IndexArchiveBundle
 */
class GroupIterator extends IndexBundleIterator
{
    /**
     * The iterator we are using to get documents from
     * @var string
     */
    var $index_bundle_iterator;

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
     *
     */
    var $current_block_hashes;

    /**
     * The number of iterated docs before the restriction test
     * @var int
     */
    var $seen_docs_unfiltered;

    /**
     * hashed url keys used to keep track of track of groups seen so far
     * @var array
     */
    var $grouped_keys;

    /**
     * the minimum number of pages to group from a block;
     * this trumps $this->index_bundle_iterator->results_per_block
     */
    const MIN_FIND_RESULTS_PER_BLOCK = 200;

    /**
     * Creates a group iterator with the given parameters.
     *
     * @param object $index_bundle_iterator to use as a source of documents
     *      to iterate over

     */
    function __construct($index_bundle_iterator)
    {
        $this->index_bundle_iterator = $index_bundle_iterator;
        $this->num_docs = $this->index_bundle_iterator->num_docs;
        $this->results_per_block = max(
            $this->index_bundle_iterator->results_per_block,
            self::MIN_FIND_RESULTS_PER_BLOCK);
        $this->reset();
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    function reset()
    {
        $this->index_bundle_iterator->reset();
        $this->grouped_keys = array();
            // -1 == never save, so file name not used using time to be safer
        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
    }

    /**
     * Computes a relevancy score for a posting offset with respect to this
     * iterator and generation
     * @param int $generation the generation the posting offset is for
     * @param int $posting_offset an offset into word_docs to compute the
     *      relevance of
     * @return float a relevancy score based on BM25F.
     */
    function computeRelevance($generation, $posting_offset)
    {
        return $this->index_bundle_iterator->computeRelevance($generation,
                $posting_offset);
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
        $count = 0;
        $done = false;
        do {
            $new_pages = $this->index_bundle_iterator->currentDocsWithWord();
            if(!is_array($new_pages)) {
                $done = true;
                if(count($pages) == 0) {
                    $pages = -1;
                }
            } else {
                $pages = array_merge($pages, $new_pages);
                $count = count($pages);
            }
            if($count < $this->results_per_block && !$done) {
                $this->index_bundle_iterator->advance();
            } else {
                $done = true;
            }
        } while(!$done);
        $this->count_block_unfiltered = count($pages);
        if(!is_array($pages)) {
            return $pages;
        }

        $this->current_block_hashes = array();
        $pre_out_pages = array();
        
        if($this->count_block_unfiltered > 0 ) {
            $i = $this->seen_docs;
            foreach($pages as $doc_key => $doc_info) {
                if(!is_array($doc_info) || 
                    isset($doc_info[self::DUPLICATE])) {continue;}
                $doc_info['KEY'] = $doc_key;
                if(strlen($doc_key) == 8) {
                    $hash_url = $doc_key;
                    $doc_info['IS_PAGE'] = true;
                } else {
                    $doc_key_parts = array(
                        substr($doc_key, 0, 8),substr($doc_key, 9, 8),
                        substr($doc_key, 18, 8)
                    );
                    $hash_url = $doc_key_parts[1];
                    $doc_info['IS_PAGE'] = false;
                }
                if(isset($this->grouped_keys[$hash_url])) {
                    if(isset($pre_out_pages[$hash_url]) ) {
                        $pre_out_pages[$hash_url][] = $doc_info;
                        if($doc_info['IS_PAGE'] == true) {
                            $pre_out_pages[$hash_url]['IS_PAGE'] = true;
                        } else {
                            $pre_out_pages[$hash_url]['HASH_INFO_URL'] =
                                $doc_key_parts[2];
                        }
                    }
                } else {
                    $pre_out_pages[$hash_url][] = $doc_info;
                    if($doc_info['IS_PAGE'] == true) {
                        $pre_out_pages[$hash_url]['IS_PAGE'] = true;
                    } else {
                        $pre_out_pages[$hash_url]['HASH_INFO_URL'] =
                            $doc_key_parts[2];
                    }
                    $this->current_block_hashes[] = $hash_url;
                    $i++;
                }
            }
             //get summary page for groups of link data if exists and don't have
            foreach($pre_out_pages as $hash_url => $data) {
                if(!isset($data['IS_PAGE'])) {
                    $hash_info_url= $pre_out_pages[$hash_url]['HASH_INFO_URL'];
                    $word_iterator = 
                         new WordIterator($hash_info_url, 
                            $this->getIndex(), true);
                    $doc_array = $word_iterator->currentDocsWithWord();

                    if(is_array($doc_array) && count($doc_array) == 1) {
                        $relevance =  $this->computeRelevance(
                            $word_iterator->current_generation,
                            $word_iterator->current_offset);

                        $keys = array_keys($doc_array);
                        $key = $keys[0];
                        if(!isset($doc_array[$key][self::DUPLICATE]) ) {
                            $item = $doc_array[$key];
                            $item[self::RELEVANCE] += $relevance;
                            $item[self::SCORE] += $relevance;
                            $item['IS_PAGE'] = true;
                            $item['KEY'] = $key;
                            array_unshift($pre_out_pages[$hash_url], $item);
                        } else { 
                            /*
                                Deduplication: 
                                a deduplicate info: page was written, so
                                we should ignore that group. 
                            */
                            unset($pre_out_pages[$hash_url]);
                            $this->grouped_keys[$hash_url] = true;
                            //mark we have seen this group
                        }
                    } 
                } else {
                    unset($pre_out_pages[$hash_url]['IS_PAGE']);
                }
                if(isset($pre_out_pages[$hash_url]['HASH_INFO_URL'])) {
                    unset($pre_out_pages[$hash_url]['HASH_INFO_URL']);
                }
            }
            $this->count_block = count($pre_out_pages);

            $out_pages = array();
            foreach($pre_out_pages as $hash_url => $group_infos) {
                foreach($group_infos as $doc_info) {
                    $is_page = $doc_info['IS_PAGE'];
                    unset($doc_info['IS_PAGE']);
                    if(!isset($out_pages[$hash_url]) || $is_page) {
                        $out_pages[$hash_url] = $doc_info;
                        $out_pages[$hash_url][self::SUMMARY_OFFSET] = array();
                        if(isset($doc_info[self::SUMMARY_OFFSET]) && 
                          isset($doc_info[self::GENERATION])) {
                            $out_pages[$hash_url][self::SUMMARY_OFFSET] = 
                                array(array($doc_info["KEY"],
                                    $doc_info[self::GENERATION], 
                                    $doc_info[self::SUMMARY_OFFSET]));
                            unset($out_pages[$hash_url]["KEY"]);
                        }
                    } else {
                        $fields = array_keys($out_pages[$hash_url]);
                        foreach($fields as $field) {
                            if(isset($doc_info[$field]) && 
                                $field != self::SUMMARY_OFFSET &&
                                $field != self::GENERATION) {
                                $out_pages[$hash_url][$field] += 
                                    $doc_info[$field];
                            } else if($field == self::SUMMARY_OFFSET &&
                                $is_page == true) {
                                array_unshift($out_pages[$hash_url][$field],
                                    array($hash_url, 
                                    $doc_info[self::GENERATION],
                                    $doc_info[$field]));
                            } else if($field == self::SUMMARY_OFFSET) {
                                $out_pages[$hash_url][$field][] = 
                                    array($doc_info["KEY"],
                                        $doc_info[self::GENERATION],
                                        $doc_info[$field]);
                            }
                        }
                    }
                }
            }
            $pages = $out_pages;
        }
        $this->pages = $pages;
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
        foreach($keys as $doc_key) {
            if(!isset($this->pages[$doc_key])) {
                continue;
            } else {
                $doc_info = $this->pages[$doc_key];
            }
            if(isset($doc_info[self::SUMMARY_OFFSET]) && 
                is_array($doc_info[self::SUMMARY_OFFSET])) {
                $out_pages[$doc_key] = $doc_info;
                foreach($doc_info[self::SUMMARY_OFFSET] as $offset_array) {
                    list($key, $generation, $summary_offset) = $offset_array;
                    $index = & $this->getIndex($key);
                    $index->setCurrentShard($generation, true);
                    $page = $index->getPage($summary_offset);
                    if($page == array()) {continue;}
                    if(!isset($out_pages[$doc_key][self::SUMMARY])) {
                        $out_pages[$doc_key][self::SUMMARY] = $page;
                    } else if (isset($page[self::DESCRIPTION])) {
                        if(!isset($out_pages[$doc_key][
                            self::SUMMARY][self::DESCRIPTION])) {
                            $out_pages[$doc_key][self::SUMMARY][
                                self::DESCRIPTION] = "";
                        }
                        $out_pages[$doc_key][self::SUMMARY][self::DESCRIPTION].=
                            " .. ".$page[self::DESCRIPTION];
                    }
                }
            }
        }
        return $out_pages;

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

        	$this->seen_docs_unfiltered += $this->count_block_unfiltered;

        if($this->seen_docs_unfiltered > 0) {
            $this->num_docs = 
                floor(($this->seen_docs*$this->index_bundle_iterator->num_docs)/
                $this->seen_docs_unfiltered);
        } else {
            $this->num_docs = 0;
        }
        
        
        foreach($this->current_block_hashes as $hash_url) {
            $this->grouped_keys[$hash_url] = true;
        }

        $this->index_bundle_iterator->advance($gen_doc_offset);

    }

    /**
     * Gets the doc_offset and generation for the next document that 
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset 
     *  and generation; -1 on fail
     */
    function currentGenDocOffsetWithWord() {
        $this->index_bundle_iterator->currentGenDocOffsetWithWord();
    }


    /**
     * Returns the index associated with this iterator
     * @return &object the index
     */
    function &getIndex($key = NULL)
    {
        return $this->index_bundle_iterator->getIndex($key);
    }
}
?>
