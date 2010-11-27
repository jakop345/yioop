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
     *
     * @var string
     */
    var $word_docs;
    /**
     *  Length of $word_docs as a string
     *  @var int
     */
    var $word_docs_len;

    /**
     *
     * @var array
     */
    var $words;

    /**
     * Stores length of the words array in the shard on disk. Only set if
     * we're in $read_only_from_disk mode
     *
     * @var int
     */
     var $words_len;

    /**
     *
     * @var array
     */
    var $prefixes;

    /**
     *
     * @var int
     */
    var $prefixes_len;

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
     * File handle for a shard if we are going to use it in read mode
     * and not completely load it.
     *
     * @var resource
     */
    var $fh;

    /**
     * An cached array of disk blocks for an index shard that has not
     * been completely loaded into memory.
     * @var array
     */
    var $blocks;

    /**
     * Flag used to determined if this shard is going to be largely kept on
     * disk and to be in read only mode. Otherwise, shard will assume to
     * be completely held in memory and be read/writable.
     * @var bool
     */
    var $read_only_from_disk;

    /**
     * @var bool
     */
    var $word_docs_packed;
    
    /**
     * Used to keep track of whether a record in document infos is for a
     * document or for a link
     */
    const COMPOSITE_ID_FLAG =  0x80000000;

    /**
     * Size in bytes of one block in IndexShard
     */
    const SHARD_BLOCK_SIZE = 4096;

    /**
     * Header Length of an IndexShard (sum of its non-variable length fields)
     */
    const HEADER_LENGTH = 36;

    /**
     * Length of a Word entry in bytes in the shard
     */
    const WORD_ITEM_LEN = 16;

    /**
     * Length of one posting ( a doc offset occurrence pair) in a posting list
     */
    const POSTING_LEN = 4;

    /**
     * Makes an index shard with the given file name and generation offset
     *
     * @param string $fname filename to store the index shard with
     * @param int $generation_offset when returning documents from the shard
     *      pretend there ar ethis many earlier documents
     * @param bool $read_only_from_disk used to determined if this shard is 
     *      going to be largely kept on disk and to be in read only mode. 
     *      Otherwise, shard will assume to be completely held in memory and be 
     *      read/writable.
     */
    function __construct($fname, $generation_offset = 0, 
        $read_only_from_disk = false)
    {
        parent::__construct($fname, -1);
        $this->generation_offset = $generation_offset;
        $this->word_docs = "";
        $this->words_len = 0;
        $this->word_docs_len = 0;
        $this->words = array();
        $this->docids_len = 0;
        $this->doc_infos = "";
        $this->num_docs = 0;
        $this->num_link_docs = 0;
        $this->len_all_docs = 0;
        $this->len_all_link_docs = 0;
        $this->blocks = array();
        $this->fh = NULL;
        $this->read_only_from_disk = $read_only_from_disk;
        $this->word_docs_packed = false;
    }

    /**
     * Add a new document to the index shard with the given summary offset.
     * Associate with this document the supplied list of words and word counts.
     * Finally, associate the given meta words with this document.
     *
     * @param string $doc_id id of document to insert
     * @param int $summary_offset its offset into the word archive its data
     *      is stored in
     * @param array $word_counts (word => number of occURRENCes of word) pairs
     *      for each word in the document
     * @param array $meta_ids meta words to be associated with the document
     *      an example meta word would be filetype:pdf for a PDF document.
     */
    function addDocumentWords($doc_id, $summary_offset, $word_counts,
        $meta_ids)
    {
        if($this->word_docs_packed == true) {
            $this->unpackWordDocs();
        }
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
            $store =  $this->packPosting($this->docids_len >> 4, $occurrences);
            if(!isset($this->words[$word_id])) {
                $this->words[$word_id] = $store;
            } else if($this->words[$word_id] != 
                pack("N", self::DUPLICATE_FLAG)) {
                $this->words[$word_id] .= $store;
            }
            if($occurrences > 0) {
                if($is_doc == true) {
                    $doc_len += $occurrences;
                } else {
                    $link_doc_len += $occurrences;
                }
            }
            $this->word_docs_len += self::POSTING_LEN;
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
     * @return array first offset, last offset, count
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

        if($this->read_only_from_disk) {
            return $this->getWordInfoDisk($word_id);
        }
        return $this->getWordInfoLoaded($word_id);



    }

    /**
     * Returns documents using the word_docs string (either as stored
     * on disk or completely read in) of records starting
     * at the given offset and using its link-list of records. Traversal of
     * the list stops if an offset larger than $last_offset is seen or
     * $len many doc's have been returned. Since $next_offset is passed by
     * reference the value of $next_offset will point to the next record in
     * the list (if it exists) after the function is called.
     *
     * @param int &$next_offset where to start in word docs
     * @param int $last_offset offset at which to stop by
     * @param int $len number of documents desired
     * @return array desired list of doc's and their info
     */
    function getPostingsSlice(&$next_offset, $last_offset, $len)
    {
        if(!$this->read_only_from_disk && !$this->word_docs_packed) {
            $this->packWordDocs();
        }
        $num_docs_so_far = 0;
        $num_doc_or_links =  ($next_offset > 0) ? 
            ($last_offset - $next_offset) >> 2
            : 1; 
        $results = array();
        do {
            if($next_offset >= $this->word_docs_len) {break;}
            $item = array();
            $posting = $this->getWordDocsSubstring($next_offset, 4);
            list($doc_index, $occurrences) = $this->unpackPosting($posting);
            $old_next_offset = $next_offset;
            $next_offset += 4;
            $doc_depth = log(10*(($doc_index +1) + 
                $this->generation_offset)*NUM_FETCHERS, 10);
            $item[self::DOC_RANK] = number_format(11 - 
                $doc_depth, PRECISION);
            $doc_loc = $doc_index << 4;
            $doc_info_string = $this->getDocInfoSubstring($doc_loc, 12);
            $doc_id = substr($doc_info_string, 0, 8);
            $tmp = unpack("N", substr($doc_info_string, 8, 4));
            $item[self::SUMMARY_OFFSET] = $tmp[1];
            $is_doc = false;
            $skip_stats = false;
            
            if($item[self::SUMMARY_OFFSET] == self::DUPLICATE_FLAG ||
                $item[self::SUMMARY_OFFSET] == self::NEEDS_OFFSET_FLAG) {
                $skip_stats = true;
                $item[self::DUPLICATE] = true;
            } else if(($tmp[1] & self::COMPOSITE_ID_FLAG) !== 0) {
                //handles link item case
                $item[self::SUMMARY_OFFSET] ^= self::COMPOSITE_ID_FLAG;
                $doc_loc += 12;
                $doc_info_string = $this->getDocInfoSubstring($doc_loc, 16);
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
                $tmp = unpack("N",$this->getDocInfoSubstring($doc_loc + 12, 4));
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
                    0.1*$item[self::RELEVANCE];
            }
            $results[$doc_id] = $item;
            $num_docs_so_far ++;
                        
        } while ($next_offset<= $last_offset && $num_docs_so_far < $len
            && $next_offset > $old_next_offset);
        return $results;
    }

    /**
     *
     */
     function nextPostingOffsetDocOffset($start_offset, $end_offset,
        $doc_offset) {

        $doc_index = $doc_offset >> 4;
        $current = floor($start_offset/self::POSTING_LEN);
        $end = floor($end_offset/self::POSTING_LEN);
        $low = $current;
        $high = $end;
        $stride = 1;
        $gallop_phase = true;

        do {
            $posting = $this->getWordDocsSubstring($current*self::POSTING_LEN, 
                self::POSTING_LEN);
            list($post_doc_index, ) = $this->unpackPosting($posting);
            if($doc_index == $post_doc_index) {
                return $current * self::POSTING_LEN;
            } else if($doc_index < $post_doc_index) {
                if($low == $current) {
                    return $current * self::POSTING_LEN;
                } else if($gallop_phase) {
                    $gallop_phase = false;
                }
                $high = $current;
                $current = (($low + $high) >> 1);
            } else {
                $low = $current;
                if($gallop_phase) {
                    $current += $stride;
                    $stride <<= 1;
                } else {
                    if($current + 1 == $high) {
                        $current++;
                        $low = $current;
                    }
                    $current = (($low + $high) >> 1);
                }
            }

        } while($current <= $end);

        return false;
     }

    /**
     *
     */
    function docOffsetFromPostingOffset($offset) {
        $posting = $this->getWordDocsSubstring($offset, self::POSTING_LEN);
        list($doc_index, ) = $this->unpackPosting($posting);
        return ($doc_index << 4);
    }

    /**
     * Returns $len many documents which contained the word corresponding to
     * $word_id (only wordk for loaded shards)
     *
     * @param string $word_id key to look up documents for
     * @param int number of documents desired back (from start of word linked
     *      list).
     * @return array desired list of doc's and their info
     */
    function getPostingsSliceById($word_id, $len)
    {
        $results = array();
        if(isset($this->words[$word_id])) {
            list($first_offset, $last_offset,
                $num_docs_or_links) = $this->getWordInfo($word_id, true);
            $results = $this->getPostingsSlice($first_offset, $last_offset, $len);
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
        if($this->word_docs_packed == true) {
            $this->unpackWordDocs();
        }
        if($index_shard->word_docs_packed == true) {
            $index_shard->unpackWordDocs();
        }
        $this->doc_infos .= $index_shard->doc_infos;

        foreach($index_shard->words as $word_id => $postings) {
            $postings_len = strlen($postings);
            // update doc offsets for newly added docs
            for($i = 0; $i < $postings_len; $i += self::POSTING_LEN) {
                $num = $this->unpackInt(substr($postings, $i, 4));
                if($num != self::DUPLICATE_FLAG) {
                    $num += ($this->docids_len << 4);
                    charCopy(pack("N", $num), $postings, $i, 4);
                }
            }
            $dup = pack("N", self::DUPLICATE_FLAG);
            if(!isset($this->words[$word_id])) {
                $this->words[$word_id] = $postings;
                $this->word_docs_len += $postings_len;
            } else if($this->words[$word_id] == $dup 
                || $postings == $dup) {
                $old_word_docs_len = strlen(
                    $this->words[$word_id]);
                $this->words[$word_id] = $dup;
                $this->word_docs_len -= $old_word_docs_len;
                $this->word_docs_len += strlen($dup);
            } else {
                $this->words[$word_id] .= $postings;
                $this->word_docs_len += $postings_len;
            }
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
            if($offset == self::DUPLICATE_FLAG) {continue; }//ignore duplicates
                //notice don't ignore NEEDS_OFFSET_FLAG
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
     * offset of value 0x7FFFFFFF (CrawlConstants::DUPLICATE_FLAG), and its 
     * length is set to 0XFFFFFFFF
     *
     * @param array $doc_urls urls to mark as duplicates.
     */
    function markDuplicateDocs($doc_urls)
    {
        foreach($doc_urls as $duplicate) {
            $doc_key = crawlHash($duplicate, true);
            $this->doc_infos .= $doc_key . pack("N", self::DUPLICATE_FLAG).
                pack("N", 0xFFFFFFFF);
            $word_id = crawlHash("info:".$duplicate, true);
            $this->words[$word_id] = 
                pack("N", $this->docids_len);
            $this->docids_len += 16;
        }

    }

    /**
     *  Save the IndexShard to its filename 
     */
    public function save()
    {
        $this->prepareWordsAndPrefixes();
        $header =  pack("N", $this->prefixes_len) .
            pack("N", $this->words_len) .
            pack("N", $this->word_docs_len) .
            pack("N", $this->docids_len) . 
            pack("N", $this->generation_offset) .
            pack("N", $this->num_docs) .
            pack("N", $this->num_link_docs) .
            pack("N", $this->len_all_docs) .
            pack("N", $this->len_all_link_docs);
        $fh = fopen($this->filename, "wb");
        fwrite($fh, $header);
        fwrite($fh, $this->prefixes);
        $this->packWordDocs($fh);
        fwrite($fh, $this->word_docs);
        fwrite($fh, $this->doc_infos);
        fclose($fh);
    }

    /**
     *   
     */
    function prepareWordsAndPrefixes()
    {
        $this->words_len = count($this->words) * IndexShard::WORD_ITEM_LEN;
        ksort($this->words, SORT_STRING);
        $blank = pack("N", 0xFFFFFFFF).pack("N", 0xFFFFFFFF);
        $tmp = array();
        $offset = 0;
        $num_words = 0;
        $old_prefix = false;
        $word_item_len = IndexShard::WORD_ITEM_LEN;
        foreach($this->words as $first => $rest) {
            $prefix = (ord($first[0]) << 8) + ord($first[1]);
            if($old_prefix === $prefix) {
                $num_words++;
            } else {
                if($old_prefix !== false) {
                    $tmp[$old_prefix] = pack("N", $offset) .
                        pack("N", $num_words);
                    $offset += $num_words * $word_item_len;
                }
                $old_prefix = $prefix;
                $num_words = 1;
            }
        }
        $tmp[$old_prefix] = pack("N", $offset) . pack("N", $num_words);
        $num_prefixes = 2 << 16;
        $this->prefixes = "";
        for($i = 0; $i < $num_prefixes; $i++) {
            if(isset($tmp[$i])) {
                $this->prefixes .= $tmp[$i];
            } else {
                $this->prefixes .= $blank;
            }
        }
        $this->prefixes_len = strlen($this->prefixes);
    }

    function packWordDocs($fh = null)
    {
        $this->word_docs_len = 0;
        $this->word_docs = "";
        foreach($this->words as $word_id => $postings) {
            $len = strlen($postings);
            $out = pack("N", $this->word_docs_len).pack("N", $len);
            $this->word_docs .= $postings;
            $this->word_docs_len += $len;
            $this->words[$word_id] = $out;
            if($fh != null) {
                fwrite($fh, $word_id . $out);
            }
        }
        $this->word_docs_packed = true;
    }

    /**
     *
     * This method is memory expensive as briefly have two copies of what's
     * in word_docs
     */
    function unpackWordDocs()
    {
        foreach($this->words as $word_id => $postings_info) {
            $offset = $this->unpackInt(substr($postings_info, 0, 4));
            $len = $this->unpackInt(substr($postings_info, 4, 4));
            $postings = substr($this->word_docs, $offset, $len);
            $this->words[$word_id] = $postings;
        }
        unset($this->word_docs);
        $this->word_docs_packed = false;
    }

    /**
     *
     */
     function packPosting($doc_index, $occurrences)
     {
        return pack("N", ($doc_index << 8) + $occurrences);
     }

    /**
     *
     */
     function unpackPosting($posting)
     {
        $doc_int = $this->unpackInt($posting);
        $occurrences = $doc_int & 255;
        $doc_index = ($doc_int >> 8);
        return array($doc_index, $occurrences);
     }

    /**
     * Returns the first offset, last offset, and number of documents the
     * word occurred in for this shard. The first offset (similarly, the last
     * offset) is the byte offset into the word_docs string of the first
     * (last) record involving that word. This method assumes the word data
     * for the $word_id has been written to disk. It reads in only the 
     * pages from disk needed to retrieve this disk-based data.
     *
     * @param string $word_id id of the word one wants to look up
     * @return array first offset, last offset, count
     */
    function getWordInfoDisk($word_id)
    {
        $this->getShardHeader();
        $word_item_len = self::WORD_ITEM_LEN;
        $prefix = (ord($word_id[0]) << 8) + ord($word_id[1]);
        $prefix_info = $this->getShardSubstring(
            self::HEADER_LENGTH + 8*$prefix, 8);
        $blank = pack("N", 0xFFFFFFFF).pack("N", 0xFFFFFFFF);
        if($prefix_info == $blank) {
            return false;
        }
        $offset = $this->unpackInt(substr($prefix_info, 0, 4));

        $high = $this->unpackInt(substr($prefix_info, 4, 4)) - 1;

        $start = self::HEADER_LENGTH + $this->prefixes_len  + $offset;
        $low = 0;
        $check_loc = (($low + $high) >> 1);
        do {
            $old_check_loc = $check_loc;
            $word_string = $this->getShardSubstring($start + 
                $check_loc * $word_item_len, $word_item_len);
            if($word_string == false) {return false;}
            $id = substr($word_string, 0, 8);
            $cmp = strcmp($word_id, $id);
            if($cmp === 0) {
                return $this->getWordInfoFromString(substr($word_string, 8));
            } else if ($cmp < 0) {
                $high = $check_loc;
                $check_loc = (($low + $check_loc) >> 1);
            } else {
                if($check_loc + 1 == $high) {
                    $check_loc++;
                }
                $low = $check_loc;
                $check_loc = (($high + $check_loc) >> 1);
            }
        } while($old_check_loc != $check_loc);

        return false;
    }

    /**
     * Returns the first offset, last offset, and number of documents the
     * word occurred in for this shard. The first offset (similarly, the last
     * offset) is the byte offset into the word_docs string of the first
     * (last) record involving that word. This method assumes the word data
     * is stored in the $this->words array.
     *
     * @param string $word_id id of the word one wants to look up
     * @return array first offset, last offset, count
     */
    function getWordInfoLoaded($word_id)
    {
        if(!isset($this->words[$word_id])) {
            return false;
        }
        if(!$this->word_docs_packed){
            $this->packWordDocs();
        }
        return $this->getWordInfoFromString(
            $this->words[$word_id]);
    }

    /**
     * Converts $str into 3 ints for a first offset into word_docs,
     * a last offset into word_docs, and a count of number of docs
     * with that word.
     *
     * @return array of these three int's
     */
    function getWordInfoFromString($str)
    {
        $first_offset = self::unpackInt(substr($str, 0, 4));
        $len = self::unpackInt(substr($str, 4, 4));
        $last_offset = $first_offset + $len - self::POSTING_LEN;
        $count = $len >> 2;

        return array($first_offset, $last_offset, $count);
    }

    /**
     * From disk gets $len many bytes starting from $offset in the word_docs
     * strings 
     *
     * @param $offset byte offset to begin getting data out of disk-based
     *      word_docs
     * @param $len number of bytes to get
     * @return desired string
     */
    function getWordDocsSubstring($offset, $len)
    {
        if($this->read_only_from_disk) {
            $base_offset = self::HEADER_LENGTH + 
                $this->prefixes_len + $this->words_len;
            return $this->getShardSubstring($base_offset + $offset, $len);
        }
        return substr($this->word_docs, $offset, $len);
    }

    /**
     * From disk gets $len many bytes starting from $offset in the doc_infos
     * strings 
     *
     * @param $offset byte offset to begin getting data out of disk-based
     *      doc_infos
     * @param $len number of bytes to get
     * @return desired string
     */
    function getDocInfoSubstring($offset, $len)
    {
        if($this->read_only_from_disk) {
            $base_offset = self::HEADER_LENGTH + $this->prefixes_len +
                $this->words_len + $this->word_docs_len;
            return $this->getShardSubstring($base_offset + $offset, $len);
        }
        return substr($this->doc_infos, $offset, $len);
    }

    /**
     *  Gets from Disk Data $len many bytes beginning at $offset from the
     *  current IndexShard
     *
     * @param int $offset byte offset to start reading from
     * @param int $len number of bytes to read
     * @return string data fromthat location  in the shard
     */
    function getShardSubstring($offset, $len)
    {
        $block_offset = (($offset >> 12) << 12);
        $start_loc = $offset - $block_offset;
        $substring = "";
        
        do {
            $data = $this->readBlockShardAtOffset($block_offset);
            if($data == false) {return $substring;}
            $block_offset += self::SHARD_BLOCK_SIZE;
            $substring .= substr($data, $start_loc);
            $start_loc = 0;
        } while (strlen($substring) < $len);
        return substr($substring, 0, $len);
    }

    /**
     * Reads SHARD_BLOCK_SIZE from the current IndexShard's file beginning
     * at byte offset $bytes
     *
     * @param $bytes byte offset to start reading from
     * @return &string data fromIndexShard file
     */
    function &readBlockShardAtOffset($bytes)
    {
        global $MEMCACHE;
        if(isset($this->blocks[$bytes])) {
            return $this->blocks[$bytes];
        } else if (USE_MEMCACHE && ($this->blocks[$bytes] = 
            $MEMCACHE->get("Block$bytes:".$this->filename)) != false) {
            return $this->blocks[$bytes];
        }
        if($this->fh === NULL) {
            $this->fh = fopen($this->filename, "rb");
            if($this->fh === false) return false;
        }
        fseek($this->fh, $bytes, SEEK_SET);
        $this->blocks[$bytes] = fread($this->fh, self::SHARD_BLOCK_SIZE);
        if(USE_MEMCACHE) {
            $MEMCACHE->set("Block$bytes:".$this->filename, 
                $this->blocks[$bytes]);
        }
        return $this->blocks[$bytes];
    }

    /**
     * If not already loaded, reads in from disk the fixed-length'd field 
     * variables of this IndexShard ($this->words_len, 
     * $this->word_docs_len, etc)
     */
    function getShardHeader()
    {
        if(isset($this->num_docs) && $this->num_docs > 0) {
            return; // if $this->num_docs > 0 assume have read in
        }
        $info_block = & $this->readBlockShardAtOffset(0);
        $header = substr($info_block, 0, self::HEADER_LENGTH);
        self::headerToShardFields($header, $this);
    }

    
    /**
     *  Load an IndexShard from a file
     *
     *  @param string the name of the file to load the IndexShard from
     *  @return object the IndexShard loaded
     */
    public static function load($fname)
    {
        $shard = new IndexShard($fname);
        $fh = fopen($fname, "rb");
        $header = fread($fh, self::HEADER_LENGTH);
        self::headerToShardFields($header, $shard);
        fread($fh, $shard->prefixes_len );
        $words = fread($fh, $shard->words_len);
        $shard->word_docs = fread($fh, $shard->word_docs_len);
        $shard->doc_infos = fread($fh, $shard->docids_len);
        fclose($fh);

        $pre_words_array = str_split($words, self::WORD_ITEM_LEN);
        unset($words);
        array_walk($pre_words_array, 'IndexShard::makeWords', $shard);

        return $shard;
    }


    /**
     *  Split a header string into a shards field variable
     *
     *  @param string $header a string with packed shard header data
     *  @param object &shard IndexShard to put data into
     */
    static function headerToShardFields($header, &$shard)
    {
        $header_array = str_split($header, 4);
        $header_data = array_map('IndexShard::unpackInt', $header_array);
        $shard->prefixes_len = $header_data[0];
        $shard->words_len = $header_data[1];
        $shard->word_docs_len = $header_data[2];
        $shard->docids_len = $header_data[3];
        $shard->generation_offset = $header_data[4];
        $shard->num_docs = $header_data[5];
        $shard->num_link_docs = $header_data[6];
        $shard->len_all_docs = $header_data[7];
        $shard->len_all_link_docs = $header_data[8];
    }
    
    /**
     * Callback function for load method. Unpacks an int from a 4 char string
     *
     * @param string $str where to extract int from
     * @return int extracted integer
     */
    static function unpackInt($str)
    {
        $tmp = unpack("N", $str);
        return $tmp[1];
    }


    /**
     * Callback function for load method. splits a word_key . word_info string
     * into an entry in the passed shard $shard->words[word_key] = $word_info.
     *
     * @param string &value  the word_key . word_info string
     * @param int $key index in array - we don't use
     * @param object $shard IndexShard to add the entry to word table for
     */
    static function makeWords(&$value, $key, &$shard)
    {
        $shard->words[substr($value, 0, 8)] = substr($value, 8, 8);
    }

}
