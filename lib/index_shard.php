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
 * Read in base class, if necessary
 */
require_once "persistent_structure.php";

/**
 * Load charCopy
 */
require_once "utility.php";

/** 
 *Loads common constants for web crawling
 */
require_once  BASE_DIR.'/lib/crawl_constants.php';

/**
 * Data structure used to store one generation worth of the word document
 * index (inverted index).
 * 
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
 
class IndexShard extends PersistentStructure implements CrawlConstants
{
    /**
     * Stores document id's and links to documents id's together with
     * summary offset information, and number of words in the doc/link
     * The format for a record is 8 bytes for a doc id, 1 bit is
     * a link record flag, 31 bits for the summary offset (byte offset into
     * web archive of the data for this document) and 4 bytes
     * for number of words in doc. In the case of a link, there is
     * an 8 byte link hash followed by the link record flag bit being on,
     * followed by 31 bits for the summary offset, followed by  8 bytes for
     * the hash of the url being pointed to by the link, followed by 8
     * bytes for the hash of "info:url_pointed_to_by_link", followed by 4 bytes
     * for numbers of word in link.
     * @var string
     */
    var $doc_infos;
    /**
     *  Length of $doc_infos as a string
     *  @var int
     */
    var $docids_len;
    /**
     * A string consisting of interwoven linked-lists. A given linked-list
     * store all the documents containing a given word. The format
     * of a record in such a list consists of: 3 byte offset into $doc_infos
     * for the document, followed by 1 byte recording the number of occurrence 
     * of the word in the document, followed by a four byte next pointer into 
     * the $word_docs string of the next record in the linked-list.
     * @var string
     */
    var $word_docs;
    /**
     *  Length of $word_docs as a string
     *  @var int
     */
    var $word_docs_len;
    /**
     * Used to store information about a word in this index shard.
     * $words is an associative array, the key being an 8 byte word hash,
     * the value being a 12 byte record. The first 4 bytes of this record
     * being the offset to the start of the linked-list for that word in
     * $word_docs, the next 4 bytes of this record being the last record
     * for this word in the link-list, and the last 4 bytes recording the
     * number of records in this linked-list.
     *
     * @var array
     */
    var $words;

    /**
     * This is supposed to hold the number of documents that have been stored
     * in earlier shards, prior to the current shard.
     */
    var $generation_offset;
    /**
     * Number of documents (not links) stored in this shard
     * @var int
     */
    var $num_docs;
    /**
     * Number of links (not documents) stored in this shard
     * @var int
     */
    var $num_link_docs;
    /**
     * Number of words stored in total in all documents in this shard
     * @var int
     */
    var $len_all_docs;
    /**
     * Number of words stored in total in all links in this shard
     * @var int
     */
    var $len_all_link_docs;

    /**
     * Used to keep track of whether a record in document infos is for a
     * document or for a link
     */
    const COMPOSITE_ID_FLAG =  0x80000000;

    /**
     * Makes an index shard with the given file name and generation offset
     *
     * @param $fname filename to store the index shard with
     * @param $generation_offset when returning documents from the shard
     *      pretend there ar ethis many earlier documents
     */
    function __construct($fname, $generation_offset = 0)
    {
        parent::__construct($fname, -1);
        $this->generation_offset = $generation_offset;
        $this->word_docs = "";
        $this->word_docs_len = 0;
        $this->words = array();
        $this->docids_len = 0;
        $this->doc_infos = "";
        $this->num_docs = 0;
        $this->num_link_docs = 0;
        $this->len_all_docs = 0;
        $this->len_all_link_docs = 0;
    }

    /**
     * Add a new document to the index shard with the given summary offset.
     * Associate with this document the supplied list of words and word counts.
     * Finally, associate the given meta words with this document.
     *
     * @param string $doc_id id of document to insert
     * @param int $summary_offset its offset into the word archive its data
     *      is stored in
     * @param array $word_counts (word => number of occurences of word) pairs
     *      for each word in the document
     * @param array $meta_ids meta words to be associated with the document
     *      an example meta word would be filetype:pdf for a PDF document.
     */
    function addDocumentWords($doc_id, $summary_offset, $word_counts,
        $meta_ids)
    {
        $is_doc = false;
        $doc_len = 0;
        $link_doc_len = 0;
        if(strlen($doc_id) == 8) { //actual doc case
            $this->doc_infos .= $doc_id . pack("N", $summary_offset);
            $extra_offset = 0;
            $this->num_docs++;
            $is_doc = true;
        } else { //link item
            if(strlen($doc_id) !== 26) {
                return false;
            }
            $id_parts = array(substr($doc_id, 0, 8),
                substr($doc_id, 9, 8), substr($doc_id, 18, 8));
            $this->num_link_docs++;
            $this->doc_infos .= $id_parts[0] . pack("N", 
                ($summary_offset | self::COMPOSITE_ID_FLAG)) .
                $id_parts[1] . $id_parts[2];
            $extra_offset = 16;
        }
        foreach($meta_ids as $meta_id) {
            $word_counts[$meta_id] = 0;
        }
        foreach($word_counts as $word => $occurrences) {
            $word_id = crawlHash($word, true);
            $occurrences = ($occurrences > 255 ) ? 255 : $occurrences & 255;
            $store =  pack("N", ($this->docids_len << 4) + $occurrences);
            $store .= pack("N", $this->word_docs_len);
            if(!isset($this->words[$word_id])) {
                $value = pack("N", $this->word_docs_len);
                $value .= $value.pack("N", 1);
            } else {
                $value = $this->words[$word_id];
                $first_string = substr($value, 0, 4);
                $previous_string = substr($value, 4, 4);
                $count_array = unpack("N", substr($value, 8, 4));
                $count =  $count_array[1];
                if($count == 0x7FFFFFFF) { continue; }
                $count++;
                $value = $first_string . pack("N", $this->word_docs_len) .
                    pack("N", $count);
                $tmp = unpack("N", $previous_string);
                $previous = $tmp[1];
                $previous_info = substr($this->word_docs, $previous, 8);
                $previous_doc_occ = substr($previous_info, 0, 4);
                $offset = $this->word_docs_len - $previous;
                $previous_info = $previous_doc_occ.pack("N", $offset);
                charCopy($previous_info, $this->word_docs, $previous, 8);
            }
            $this->words[$word_id] = $value;
            $this->word_docs .= $store;
            $this->word_docs_len += 8;
            if($occurrences > 0) {
                if($is_doc == true) {
                    $doc_len += $occurrences;
                } else {
                    $link_doc_len += $occurrences;
                }
            }
        }

        $this->len_all_docs += $doc_len;
        $this->len_all_link_docs += $link_doc_len;
        if($is_doc == true)  {
            $this->doc_infos .= pack("N", $doc_len);
        } else {
            $this->doc_infos .= pack("N", $link_doc_len);
        }
        $this->docids_len += 16 + $extra_offset;
    }

    /**
     * Returns the first offset, last offset, and number of documents the
     * word occurred in for this shard. The first offset (similarly, the last
     * offset) is the byte offset into the word_docs string of the first
     * (last) record involving that word.
     *
     * @param string $word_id id of the word one wants to look up
     * @param bool $raw whether the id is our version of base64 encoded or not
     */
    function getWordInfo($word_id, $raw = false)
    {

        if($raw == false) {
            //get rid of out modfied base64 encoding
            $hash = str_replace("_", "/", $word_id);
            $hash = str_replace("-", "+" , $hash);
            $hash .= "=";
            $word_id = base64_decode($hash);

        }

        if(!isset($this->words[$word_id])) {
            return false;
        }
        $first_string = substr($this->words[$word_id], 0, 4);
        $tmp = unpack("N", $first_string);
        $first_offset = $tmp[1];
        $last_string = substr($this->words[$word_id], 4, 4);
        $tmp = unpack("N", $last_string);
        $last_offset = $tmp[1];
        $count_string = substr($this->words[$word_id], 8, 4);
        $tmp = unpack("N", $count_string);
        $count = $tmp[1];


        return array($first_offset, $last_offset, $count);

    }

    /**
     * Returns documents using the word_docs string of records starting
     * at the given offset and using its link-list of records. Traversal of
     * the list stops if an offset larger than $last_offset is seen or
     * $len many doc's have been returned. Since $next_offset is passed by
     * reference the value of $next_offset will point to the next record in
     * the list (if it exists) after thhe function is called.
     *
     * @param int &$next_offset where to start in word docs
     * @param int $last_offset offset at which to stop by
     * @param int $len number of documents desired
     * @return array desired list of doc's and their info
     */
    function getWordSlice(&$next_offset, $last_offset, $len)
    {
        $num_docs_so_far = 0;
        $num_doc_or_links =  ($next_offset > 0) ? $last_offset/$next_offset
            : 1; //very approx
        $results = array();
        do {
            if($next_offset >= $this->word_docs_len) {break;}
            $item = array();
            $doc_string = substr($this->word_docs, $next_offset, 4);
            $tmp = unpack("N", $doc_string);
            $doc_int = $tmp[1];
            $occurrences = $doc_int & 255;
            $doc_index = ($doc_int >> 8);
            $next_string = substr($this->word_docs, $next_offset + 4, 4);
            $tmp = unpack("N", $next_string);
            $old_next_offset = $next_offset;
            $next_offset += $tmp[1];
            $doc_depth = log(10*(($doc_index +1) + 
                $this->generation_offset)*NUM_FETCHERS, 10);
            $item[self::DOC_RANK] = number_format(11 - 
                $doc_depth, PRECISION);
            $doc_loc = $doc_index << 4;
            $doc_info_string = substr($this->doc_infos, $doc_loc,
                12);
            $doc_id = substr($doc_info_string, 0, 8);
            $tmp = unpack("N", substr($doc_info_string, 8, 4));
            $item[self::SUMMARY_OFFSET] = $tmp[1];
            $is_doc = false;
            $skip_stats = false;
            
            if($item[self::SUMMARY_OFFSET] == 0x7FFFFFFF) {
                $skip_stats = true;
                $item[self::DUPLICATE] = true;
            } else if(($tmp[1] & self::COMPOSITE_ID_FLAG) !== 0) {
                //handles link item case
                $item[self::SUMMARY_OFFSET] ^= self::COMPOSITE_ID_FLAG;
                $doc_loc += 12;
                $doc_info_string = substr($this->doc_infos, $doc_loc, 16);
                $doc_id .= ":". 
                    substr($doc_info_string, 0, 8).":".
                    substr($doc_info_string, 8, 8);
                $average_doc_len = ($this->num_link_docs != 0) ? 
                    $this->len_all_link_docs/$this->num_link_docs : 0;
                $num_docs = $this->num_link_docs;
            } else {
                $is_doc = true;
                $average_doc_len = $this->len_all_docs/$this->num_docs;
                $num_docs = $this->num_docs;
            }

            if(!$skip_stats) {
                $tmp = unpack("N",  substr($this->doc_infos, $doc_loc + 12, 4));
                $doc_len = $tmp[1];
                $doc_ratio = ($average_doc_len > 0) ?
                    $doc_len/$average_doc_len : 0;
                $pre_relevance = number_format(
                        3 * $occurrences/
                        ($occurrences + .5 + 1.5* $doc_ratio), 
                        PRECISION);
                $num_term_occurrences = $num_doc_or_links *
                    $num_docs/($this->num_docs + $this->num_link_docs);
                $IDF = ($num_docs - $num_term_occurrences + 0.5) /
                    ($num_term_occurrences + 0.5);
                $item[self::RELEVANCE] = $IDF * $pre_relevance;
                $item[self::SCORE] = $item[self::DOC_RANK] + 
                    .1*$item[self::RELEVANCE];
            }
            $results[$doc_id] = $item;
            $num_docs_so_far ++;
                        
        } while ($next_offset<= $last_offset && $num_docs_so_far < $len
            && $next_offset > $old_next_offset);

        return $results;
    }


    /**
     * Returns $len many documents which contained the word corresponding to
     * $word_id
     *
     * @param string $word_id key to look up documents for
     * @param int number of documents desired back (from start of word linked
     *      list).
     * @return array desired list of doc's and their info
     */
    function getWordSliceById($word_id, $len)
    {
        $results = array();
        if(isset($this->words[$word_id])) {
            list($first_offset, $last_offset,
                $num_docs_or_links) = $this->getWordInfo($word_id, true);
            $results = $this->getWordSlice($first_offset, $last_offset, $len);
        }
        return $results;
    }

    /**
     * Adds the contents of the supplied $index_shard to the current index
     * shard
     *
     * @param object &$index_shard the shard to append to the current shard
     */
    function appendIndexShard(&$index_shard)
    {
        $this->doc_infos .= $index_shard->doc_infos;
        $this->word_docs .= $index_shard->word_docs;
        $old_word_docs_len = $this->word_docs_len;
        $this->word_docs_len += $index_shard->word_docs_len;
        // update doc offsets in word_docs for newly added docs
        for($i = $old_word_docs_len; $i < $this->word_docs_len; $i += 8) {
            $doc_occurrences_string = substr($this->word_docs, $i, 4);
            $tmp = unpack("N", $doc_occurrences_string);
            $num = $tmp[1];
            $num += ($this->docids_len << 4);
            $doc_occurrences_string = pack("N", $num);
            charCopy($doc_occurrences_string, $this->word_docs, $i, 4);
        }

        foreach($index_shard->words as $word_key => $word_docs_offset) {
            $add_first_string = substr($word_docs_offset, 0, 4);
            $tmp = unpack("N", $add_first_string);
            $add_first_offset = $tmp[1];
            $add_last_string = substr($word_docs_offset, 4, 4);
            $tmp = unpack("N", $add_last_string);
            $add_last_offset = $tmp[1];
            $add_count = substr($word_docs_offset, 8, 4);
            $tmp = unpack("N", $add_count);
            $add_count = $tmp[1];
            if(!isset($this->words[$word_key])) {
                $new_word_docs_offset = 
                    pack("N", $old_word_docs_len + $add_first_offset).
                    pack("N", $old_word_docs_len + $add_last_offset). 
                    pack("N", $add_count);
            } else {
                $value = $this->words[$word_key];
                $first_string = substr($value, 0, 4);
                $last_string = substr($value, 4, 4);
                $tmp = unpack("N", $last_string);
                $last_offset = $tmp[1];
                $count_string = substr($value, 8, 4);
                $tmp = unpack("N", $count_string);
                $count = $tmp[1];
                if($count == 0x7FFFFFFF) {

                    continue; 
                }
                $to_new_docs_offset = $add_first_offset
                   + ($old_word_docs_len - $last_offset);
                $to_new_docs_string = pack("N", $to_new_docs_offset);
                charCopy($to_new_docs_string, $this->word_docs, 
                    $last_offset + 4, 4);
                $new_word_docs_offset = $first_string .
                    pack("N", $old_word_docs_len + $add_last_offset) .
                    pack("N", $count + $add_count);
            }
            $this->words[$word_key] = $new_word_docs_offset;
        }

        $this->docids_len += $index_shard->docids_len;
        $this->num_docs += $index_shard->num_docs;
        $this->num_link_docs += $index_shard->num_link_docs;
        $this->len_all_docs += $index_shard->len_all_docs;
        $this->len_all_link_docs += $index_shard->len_all_link_docs;
    }

    /**
     * Changes the summary offsets associated with a set of doc_ids to new 
     * values. This is needed because the fetcher puts documents in a 
     * shard before sending them to a queue_server. It is on the queue_server
     * however where documents are stored in the IndexArchiveBundle and
     * summary offsets are obtained. Thus, the shard needs to be updated at
     * that point.
     *
     * @param array $docid_offsets a set of doc_id offset pairs.
     */
    function changeDocumentOffsets($docid_offsets)
    {
        $docids_len = $this->docids_len;

        for($i = 0 ; $i < $docids_len; $i += $row_len) {
            $row_len = 16;
            $id = substr($this->doc_infos, $i, 8);
            $tmp = unpack("N", substr($this->doc_infos, $i + 8, 4));
            $offset = $tmp[1];
            if($offset == 0x7FFFFFFF) {continue; }//ignore duplicates
            $comp_flag = 0;
            if(($offset & self::COMPOSITE_ID_FLAG) !== 0) {
                //handle link item case
                $row_len += 16;
                $comp_flag = self::COMPOSITE_ID_FLAG;
                $id .= ":".substr($this->doc_infos, $i + 12, 8) . ":" .
                    substr($this->doc_infos, $i + 20, 8);
            }
            $new_offset = (isset($docid_offsets[$id])) ? 
                pack("N", ($docid_offsets[$id] | $comp_flag)) : 
                pack("N", $offset);

            charCopy($new_offset, $this->doc_infos, $i + 8, 4);
        }
    }

    /**
     * Marks a set of urls as duplicates of urls previously seen
     * To do this the url's doc_id has associated with a summary
     * offset of value 0x7FFFFFFF, and its length is set to
     * 0XFFFFFFFF
     *
     * @param array $doc_urls urls to mark as duplicates.
     */
    function markDuplicateDocs($doc_urls)
    {
        foreach($doc_urls as $duplicate) {
            $doc_key = crawlHash($duplicate, true);
            $this->doc_infos .= $doc_key . pack("N", 0x7FFFFFFF).
                pack("N", 0xFFFFFFFF);
            $word_key = crawlHash("info:".$duplicate, true);
            $this->word_docs .= pack("N", ($this->docids_len<< 4)).pack("N",0);
            $tmp = pack("N", $this->word_docs_len);
            $this->words[$word_key] = $tmp.$tmp.pack("N", 0x7FFFFFFF);
            $this->word_docs_len += 8;
            $this->docids_len += 16;
        }

    }

}
