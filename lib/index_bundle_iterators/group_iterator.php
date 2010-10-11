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
     * Creates a group iterator with the given parameters.
     *
     * @param object $index_bundle_iterator to use as a source of documents
     *      to iterate over

     */
    function __construct($index_bundle_iterator)
    {
        $this->index_bundle_iterator = $index_bundle_iterator;
        $this->num_docs = $this->index_bundle_iterator->num_docs;
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
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    function findDocsWithWord()
    {
        $pages = 
            $this->index_bundle_iterator->currentDocsWithWord();

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
                        $keys = array_keys($doc_array);
                        $key = $keys[0];
                        if(!isset($doc_array[$key][self::DUPLICATE]) ) {
                            $pre_out_pages[$hash_url][$key] = $doc_array[$key];
                            $pre_out_pages[$hash_url][$key]['IS_PAGE'] = true;
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
                    if(!isset($out_pages[$hash_url])) {
                        $out_pages[$hash_url] = $doc_info;
                        $out_pages[$hash_url][self::SUMMARY_OFFSET] = array();
                        if(isset($doc_info[self::SUMMARY_OFFSET]) ) {
                            $out_pages[$hash_url][self::SUMMARY_OFFSET] = 
                                array(array($doc_info["KEY"], 
                                    $doc_info[self::SUMMARY_OFFSET]));
                            unset($out_pages[$hash_url]["KEY"]);
                        }
                    } else {
                        $fields = array_keys($out_pages[$hash_url]);
                        foreach($fields as $field) {
                            if(isset($doc_info[$field]) && 
                                $field != self::SUMMARY_OFFSET) {
                                $out_pages[$hash_url][$field] += 
                                    $doc_info[$field];
                            } else if($field == self::SUMMARY_OFFSET &&
                                $is_page == true) {
                                array_unshift($out_pages[$hash_url][$field],
                                    array($hash_url, $doc_info[$field]));
                            } else if($field == self::SUMMARY_OFFSET) {
                                $out_pages[$hash_url][$field][] = 
                                    array($doc_info["KEY"], $doc_info[$field]);
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
                    list($key, $summary_offset) = $offset_array;
                    $index = & $this->getIndex($key);
                    $page = $index->getPage(
                        $key, $summary_offset);
                    if(!isset($out_pages[$doc_key][self::SUMMARY])) {
                        $out_pages[$doc_key][self::SUMMARY] = $page;
                    } else if (isset($page[self::DESCRIPTION])) {
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
     */
    function advance() 
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

        $this->index_bundle_iterator->advance();

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
