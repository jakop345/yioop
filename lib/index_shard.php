<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
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
 * @copyright 2009, 2010, 2011
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
 * This data structure consists of three main components a word entries,
 * word_doc entries, and document entries.
 *
 * Word entries are described in the documentation for the words field.
 *
 * Word-doc entries are described in the documentation for the word_docs field
 *
 * Document entries are described in the documentation for the doc_infos field
 * 
 * IndexShards also have two access modes a $read_only_from_disk mode and 
 * a loaded in memory mode. Loaded in memory mode is mainly for writing new
 * data to the shard. When in memory, data in the shard can also be in one of 
 * two states packed or unpacked. Roughly, when it is in a packed state it is 
 * ready to be serialized to disk; when it is an unpacked state it methods 
 * for adding data can be used.
 *
 * Serialized on disk, a shard has a header with document statistics followed
 * by the a prefix index into the words component, followed by the word
 * component itself, then the word-docs component, and finally the document
 * component.
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
     * The format for a record is 4 byte offset, followed by
     * 3 bytes for the document length, followed by 1 byte containing
     * the number of 8 byte doc key strings that make up the doc id (2 for
     * a doc, 3 for a link), followed by the doc key strings themselves.
     * In the case of a document the first doc key string has a hash of the
     * url, the second a hash a tag stripped version of the document. 
     * In the case of a link, the keys are a unique identifier for the link 
     * context, followed by  8 bytes for
     * the hash of the url being pointed to by the link, followed by 8
     * bytes for the hash of "info:url_pointed_to_by_link".
     * @var string
     */
    var $doc_infos;
    /**
     *  Length of $doc_infos as a string
     *  @var int
     */
    var $docids_len;

    /**
     * This string is non-empty when shard is loaded and in its packed state.
     * It consists of a sequence of posting records. Each posting
     * consists of a offset into the document entries structure
     * for a document containing the word this is the posting for,
     * as well as the number of occurrences of that word in that document.
     * @var string
     */
    var $word_docs;
    /**
     *  Length of $word_docs as a string
     *  @var int
     */
    var $word_docs_len;

    /**
     * Stores the array of word entries for this shard
     * In the packed state, word entries consist of the word id, 
     * a generation number, an offset into the word_docs structure 
     * where the posting list for that word begins,
     * and a length of this posting list. In the unpacked state
     * each entry is a string of all the posting items for that word
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
     * An array representing offsets into the words dictionary of the index of 
     * the first occurrence of a two byte prefix of a word_id. 
     *
     * @var array
     */
    var $prefixes;

    /**
     * Length of the prefix index into the dictionary of the shard
     *
     * @var int
     */
    var $prefixes_len;

    /**
     * This is supposed to hold the number of earlier shards, prior to the 
     * current shard.
     * @var int
     */
    var $generation;

    /**
     * This is supposed to hold the number of documents that a given shard can
     * hold.
     * @var int
     */
    var $num_docs_per_generation;

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
     * Keeps track of the packed/unpacked state of the word_docs list
     *
     * @var bool
     */
    var $word_docs_packed;

    /**
     * Keeps track of the length of the shard as a file
     *
     * @var int
     */
    var $file_len;
    
    /**
     * Used to keep track of whether a record in document infos is for a
     * document or for a link
     */
    const LINK_FLAG =  0x800000;
    /**
     * Used to keep track of whether a record in document infos is for a
     * document of mimetype image or not
     */
    const IMAGE_FLAG =  0x400000;

    /**
     * Size in bytes of one block in IndexShard
     */
    const SHARD_BLOCK_SIZE = 4096;

    /**
     * Header Length of an IndexShard (sum of its non-variable length fields)
     */
    const HEADER_LENGTH = 40;

    /**
     * Length of a Word entry in bytes in the shard
     */
    const WORD_ITEM_LEN = 20;

    /**
     * Length of a word entry's key in bytes
     */
    const WORD_KEY_LEN = 8;

    /**
     * Length of a key in a DOC ID.
     */
    const DOC_KEY_LEN = 8;

    /**
     * Length of one posting ( a doc offset occurrence pair) in a posting list
     */
    const POSTING_LEN = 4;

    /**
     *  Represents an empty prefix item
     */
    const BLANK = "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF";

    /**
     * Makes an index shard with the given file name and generation offset
     *
     * @param string $fname filename to store the index shard with
     * @param int $generation when returning documents from the shard
     *      pretend there ar ethis many earlier documents
     * @param bool $read_only_from_disk used to determined if this shard is 
     *      going to be largely kept on disk and to be in read only mode. 
     *      Otherwise, shard will assume to be completely held in memory and be 
     *      read/writable.
     */
    function __construct($fname, $generation = 0, 
        $num_docs_per_generation = NUM_DOCS_PER_GENERATION,
        $read_only_from_disk = false)
    {
        parent::__construct($fname, -1);
        $this->generation = $generation;
        $this->num_docs_per_generation = $num_docs_per_generation;
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
     * @param string $doc_keys a string of concatenated keys for a document 
     *      to insert. Each key is assumed to be a string of DOC_KEY_LEN many 
     *      bytes. This whole set of keys is viewed as fixing one document.
     * @param int $summary_offset its offset into the word archive the
     *      document's data is stored in
     * @param array $word_counts (word => number of occurrences of word) pairs
     *      for each word in the document
     * @param array $meta_ids meta words to be associated with the document
     *      an example meta word would be filetype:pdf for a PDF document.
     * @param array $is_doc flag used to indicate if what is being sored is
     *      a document or a link to a document
     * @return bool success or failure of performing the add
     */
    function addDocumentWords($doc_keys, $summary_offset, $word_counts,
        $meta_ids, $is_doc = false, $is_image = false)
    {
        if($this->word_docs_packed == true) {
            $this->unpackWordDocs();
        }

        $doc_len = 0;
        $link_doc_len = 0;
        $len_key = strlen($doc_keys);
        $num_keys = floor($len_key/self::DOC_KEY_LEN);

        if($num_keys * self::DOC_KEY_LEN != $len_key) return false;

        if($num_keys % 2 == 0 ) {
            $doc_keys .= self::BLANK; //want to keep docids_len divisible by 16
        }

        $summary_offset_string = packInt($summary_offset);
        $added_len = strlen($summary_offset_string);
        $this->doc_infos .= $summary_offset_string;

        if($is_doc) { 
            $this->num_docs++;
        } else { //link item
            $this->num_link_docs++;
        }
        foreach($meta_ids as $meta_id) {
            $word_counts[$meta_id] = 0;
        }
        foreach($word_counts as $word => $occurrences) {
            $word_id = crawlHash($word, true);
            $occurrences = ($occurrences > 255 ) ? 255 : $occurrences & 255;
            //using $this->docids_len divisible by 16
            $store =  $this->packPosting($this->docids_len >> 4, $occurrences);
            if(!isset($this->words[$word_id])) {
                $this->words[$word_id] = $store;
            } else {
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
        $flags = ($is_doc) ? 0 : self::LINK_FLAG;
        $flags +=  (($is_image) ? self::IMAGE_FLAG : 0);

        $len_num_keys = $this->packPosting(($flags + $doc_len), $num_keys);

        $this->doc_infos .=  $len_num_keys;
        $added_len += strlen($len_num_keys);
        $this->doc_infos .= $doc_keys;
        $added_len += strlen($doc_keys);
        $this->docids_len += $added_len;

        return true;
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
     * @param int $start_offset of the current posting list for query term
     *      used in calculating BM25F.
     * @param int &$next_offset where to start in word docs
     * @param int $last_offset offset at which to stop by
     * @param int $len number of documents desired
     * @return array desired list of doc's and their info
     */
    function getPostingsSlice($start_offset, &$next_offset, $last_offset, $len)
    {
        if(!$this->read_only_from_disk && !$this->word_docs_packed) {
            $this->packWordDocs();
        }
        $num_docs_so_far = 0;
        $results = array();
        $end = min($this->word_docs_len, $last_offset);

        do {
            if($next_offset > $end) {break;}
            $doc_id = 
                $this->makeItem(
                    $item, $start_offset, $next_offset, $last_offset);
            $results[$doc_id] = $item;
            $num_docs_so_far ++;

            $old_next_offset = $next_offset;
            $next_offset += self::POSTING_LEN;
        } while ($next_offset<= $last_offset && $num_docs_so_far < $len
            && $next_offset > $old_next_offset);

        return $results;
    }

    /**
     * Stores in the supplied item document statistics (suumary offset, 
     * relevance, doc rank, and score) for the the document
     * pointed to by $current_offset, based on the the posting lists 
     * starting and ending offset (hence num docs with word), and the
     * number of occurrences of the word. Returns the doc_id of the document
     *
     * @param array &$item a reference to an array to store statistic in
     * @param int $starting_offset offset into word_docs for start of posting
     *      list
     * @param int $current_offset offset into word_docs for the document to
     *      calculate statistics for
     * @param int $last_offset offset into word_docs for end of posting
     *      list
     * @param int $occurs number of occurrences of the current word in 
     *   the document
     *
     * @return string $doc_id of document pointed to by $current_offset
     */
    function makeItem(&$item, $start_offset, $current_offset, $last_offset,
        $occurs = 0)
    {
        $num_doc_or_links =  floor(($last_offset - $start_offset) /
            self::POSTING_LEN); 
        $posting = $this->getWordDocsSubstring($current_offset, 
            self::POSTING_LEN);

        list($doc_index, $occurrences) = $this->unpackPosting($posting);
        if($occurrences < $occurs) {
            $occurrences = $occurs;
        }

        $doc_depth = log(10*(($doc_index +1) + 
            $this->num_docs_per_generation*$this->generation)*NUM_FETCHERS, 10);
        $item[self::DOC_RANK] = number_format(11 - 
            $doc_depth, PRECISION);
        $doc_loc = $doc_index << 4;
        $doc_info_string = $this->getDocInfoSubstring($doc_loc, 
            self::DOC_KEY_LEN);
        $item[self::SUMMARY_OFFSET] = unpackInt(
            substr($doc_info_string, 0, 4));
        list($doc_len, $num_keys) = 
            $this->unpackPosting(substr($doc_info_string, 4));
        $item[self::GENERATION] = $this->generation;
        $is_doc = (($doc_len & self::LINK_FLAG) == 0) ? true : false;
        if(!$is_doc) {$doc_len -= self::LINK_FLAG; }
        $item[self::IS_DOC] = $is_doc;
        $is_image = (($doc_len & self::IMAGE_FLAG) == 0) ? false : true;
        if($is_image) {$doc_len -= self::IMAGE_FLAG; }
        $item[self::IS_IMAGE] = $is_image;
        $skip_stats = false;
        
        if($item[self::SUMMARY_OFFSET] == self::NEEDS_OFFSET_FLAG) {
            $skip_stats = true;
            $item[self::RELEVANCE] = 0;
            $item[self::SCORE] = $item[self::DOC_RANK];
        } else if($is_doc) {
            $average_doc_len = $this->len_all_docs/$this->num_docs;
            $num_docs = $this->num_docs;
        } else {
            $average_doc_len = ($this->num_link_docs != 0) ? 
                $this->len_all_link_docs/$this->num_link_docs : 0;
            $num_docs = $this->num_link_docs;
        }
        $doc_id = $this->getDocInfoSubstring(
            $doc_loc + self::DOC_KEY_LEN, $num_keys * self::DOC_KEY_LEN);

        if(!$skip_stats) {
            $doc_ratio = ($average_doc_len > 0) ?
                $doc_len/$average_doc_len : 0;
            $pre_relevance = number_format(
                    3 * $occurrences/
                    ($occurrences + .5 + 1.5* $doc_ratio), 
                    PRECISION);

            $num_term_occurrences = $num_doc_or_links *
                $num_docs/($this->num_docs + $this->num_link_docs);

            $IDF = log(($num_docs - $num_term_occurrences + 0.5) /
                ($num_term_occurrences + 0.5));

            $item[self::RELEVANCE] = .5* $IDF * $pre_relevance;

            $item[self::SCORE] = $item[self::DOC_RANK] + 
                + $item[self::RELEVANCE];
        }

        return $doc_id;

    }

    /**
     * Finds the first posting offset between $start_offset and $end_offset
     * of a posting that has a doc_offset bigger than or equal to $doc_offset
     * This is implemented using a galloping search (double offset till
     * get larger than binary search).
     *
     *  @param int $start_offset first posting to consider
     *  @param int $end_offset last posting before give up
     *  @param int $doc_offset document offset we want to be greater than or 
     *      equalt to
     *
     *  @return int offset to next posting
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
                    if($current > $end ) {
                        $current = $end;
                        $gallop_phase = false;
                    }
                } else if($current >= $end) {
                    return false;
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
     * Given an offset of a posting into the word_docs string, looks up
     * the posting there and computes the doc_offset stored in it.
     *
     *  @param int $offset byte/char offset into the word_docs string
     *  @return int a document byte/char offset into the doc_infos string
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
            $results = $this->getPostingsSlice($first_offset, 
                $first_offset, $last_offset, $len);
        }
        return $results;
    }

    /**
     * Adds the contents of the supplied $index_shard to the current index
     * shard
     *
     * @param object $index_shard the shard to append to the current shard
     */
    function appendIndexShard($index_shard)
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
                $num = unpackInt(substr($postings, $i, self::POSTING_LEN));
                $num += ($this->docids_len << 4);
                charCopy(packInt($num), $postings, $i, self::POSTING_LEN);
            }
            if(!isset($this->words[$word_id])) {
                $this->words[$word_id] = $postings;
                $this->word_docs_len += $postings_len;
            } else  {
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
            $doc_info_string = $this->getDocInfoSubstring($i, 
                self::DOC_KEY_LEN);
            $offset = unpackInt(
                substr($doc_info_string, 0, self::POSTING_LEN));
            list($doc_len, $num_keys) = 
                $this->unpackPosting(substr($doc_info_string, 
                    self::POSTING_LEN, self::POSTING_LEN));
            $key_count = ($num_keys % 2 == 0) ? $num_keys + 2: $num_keys + 1;
            $row_len = self::DOC_KEY_LEN * ($key_count);

            $id = substr($this->doc_infos, $i + self::DOC_KEY_LEN, 
                $num_keys * self::DOC_KEY_LEN);

            $new_offset = (isset($docid_offsets[$id])) ? 
                packInt($docid_offsets[$id]) : 
                packInt($offset);

            charCopy($new_offset, $this->doc_infos, $i, self::POSTING_LEN);

        }
    }


    /**
     *  Save the IndexShard to its filename 
     */
    public function save()
    {
        if($this->word_docs_packed == true){
            $this->unpackWordDocs();
        }
        $this->prepareWordsAndPrefixes();
        $header =  pack("N", $this->prefixes_len) .
            pack("N", $this->words_len) .
            pack("N", $this->word_docs_len) .
            pack("N", $this->docids_len) . 
            pack("N", $this->generation) .
            pack("N", $this->num_docs_per_generation) .
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
     * Computes the prefix string index for the current words array.
     * This index gives offsets of the first occurrences of the lead two char's
     * of a word_id in the words array.
     */
    function prepareWordsAndPrefixes()
    {
        $this->words_len = count($this->words) * IndexShard::WORD_ITEM_LEN;
        ksort($this->words, SORT_STRING);
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
                    $tmp[$old_prefix] = packInt($offset) .
                        pack("N", $num_words);
                    $offset += $num_words * $word_item_len;
                }
                $old_prefix = $prefix;
                $num_words = 1;
            }
        }
        $tmp[$old_prefix] = packInt($offset) . packInt($num_words);
        $num_prefixes = 2 << 16;
        $this->prefixes = "";
        for($i = 0; $i < $num_prefixes; $i++) {
            if(isset($tmp[$i])) {
                $this->prefixes .= $tmp[$i];
            } else {
                $this->prefixes .= self::BLANK;
            }
        }
        $this->prefixes_len = strlen($this->prefixes);
    }

    /**
     * Posting lists are initially stored associated with a word as a key
     * value pair. This function makes one long concatenated string out of
     * them, word_docs, and changes the words dictionarys from pairs
     * word_id, posting list to triples word_id, start_offset, end_offset where
     * offsets are into this concatenated word_docs string. Finally, if
     * a file handle is given it write the word dictionary out to the file as
     * a long string.
     *
     * @param resource $fh a file handle to write the dictionary to, if desired
     */
    function packWordDocs($fh = null)
    {
        $this->word_docs_len = 0;
        $this->word_docs = "";
        foreach($this->words as $word_id => $postings) {
            $len = strlen($postings);
            /* 
                we back generation info to make it easier to build the global
                dictionary
            */
            $out = packInt($this->generation)
                . packInt($this->word_docs_len)
                . packInt($len);
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
     * Takes the word docs string and splits it into posting lists which are
     * assigned to particular words in the words dictionary array.
     * This method is memory expensive as it briefly has essentially 
     * two copies of what's in word_docs.
     */
    function unpackWordDocs()
    {
        foreach($this->words as $word_id => $postings_info) {
            //we are ignoring the first two bytes which contains generation info
            $offset = unpackInt(substr($postings_info, 4, 4));
            $len = unpackInt(substr($postings_info, 8, 4));
            $postings = substr($this->word_docs, $offset, $len);
            $this->words[$word_id] = $postings;
        }
        unset($this->word_docs);
        $this->word_docs_packed = false;
    }

    /**
     * Makes an packed integer string from a docindex and the number of
     * occurences of a word in the document with that docindex.
     *
     * @param int $doc_index index (i.e., a count of which document it
     *      is rather than a byte offet) of a document in the document string
     * @param int $occurrences number of times a word occurred in that doc
     * @return string a packed integer containing these two pieces of info.
     */
     function packPosting($doc_index, $occurrences)
     {
        return packInt(($doc_index << 8) + $occurrences);
     }

    /**
     * Given a packed integer string, uses the top three bytes to calculate
     * a doc_index of a document in the shard, and uses the low order byte
     * to computer a number of occurences of a word in that document.
     *
     * @param string $posting a doc index occurence pair coded as packed integer
     * @return array consistring of integer doc_index and an integer number of
     *      occurences 
     */
     function unpackPosting($posting)
     {
        $doc_int = unpackInt($posting);
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
        if($prefix_info == self::BLANK) {
            return false;
        }
        $offset = unpackInt(substr($prefix_info, 0, 4));

        $high = unpackInt(substr($prefix_info, 4, 4)) - 1;

        $start = self::HEADER_LENGTH + $this->prefixes_len  + $offset;
        $low = 0;
        $check_loc = (($low + $high) >> 1);
        do {
            $old_check_loc = $check_loc;
            $word_string = $this->getShardSubstring($start + 
                $check_loc * $word_item_len, $word_item_len);
            if($word_string == false) {return false;}
            $id = substr($word_string, 0, self::WORD_KEY_LEN);
            $cmp = strcmp($word_id, $id);
            if($cmp === 0) {
                return $this->getWordInfoFromString(
                    substr($word_string, self::WORD_KEY_LEN));
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
     * @param string $str 
     * @param bool $include_generation 
     * @return array of these three or four int's
     */
    static function getWordInfoFromString($str, $include_generation = false)
    {
        $generation = unpackInt(substr($str, 0, 4));
        $first_offset = unpackInt(substr($str, 4, 4));
        $len = unpackInt(substr($str, 8, 4));
        $last_offset = $first_offset + $len - self::POSTING_LEN;
        $count = floor($len / self::POSTING_LEN);
        if( $include_generation) {
            return array($generation, $first_offset, $last_offset, $count);
        }
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
        $block_offset = (floor($offset/self::SHARD_BLOCK_SIZE) *
            self::SHARD_BLOCK_SIZE);
        $start_loc = $offset - $block_offset;
        $substring = "";
        do {
            $data = $this->readBlockShardAtOffset($block_offset);
            if($data === false) {return $substring;}
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
     * @param int $bytes byte offset to start reading from
     * @return &string data fromIndexShard file
     */
    function &readBlockShardAtOffset($bytes)
    {
        global $MEMCACHE;
        $false = false;
        if(isset($this->blocks[$bytes])) {
            return $this->blocks[$bytes];
        } else if (!defined("NO_CACHE") && USE_MEMCACHE && 
            ($this->blocks[$bytes] = 
            $MEMCACHE->get("Block$bytes:".$this->filename)) != false) {
            return $this->blocks[$bytes];
        }
        if($this->fh === NULL) {
            $this->fh = fopen($this->filename, "rb");
            if($this->fh === false) return false;
            $this->file_len = filesize($this->filename);
        }
        if($bytes >= $this->file_len) {
            
            return $false;
        }
        $seek = fseek($this->fh, $bytes, SEEK_SET);
        if($seek < 0) {
            return $false;
        }
        $this->blocks[$bytes] = fread($this->fh, self::SHARD_BLOCK_SIZE);
        if(!defined("NO_CACHE") && USE_MEMCACHE) {
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
        $shard->word_docs_packed = true;
        return $shard;
    }


    /**
     *  Split a header string into a shards field variable
     *
     *  @param string $header a string with packed shard header data
     *  @param object shard IndexShard to put data into
     */
    static function headerToShardFields($header, $shard)
    {
        $header_array = str_split($header, 4);
        $header_data = array_map('unpackInt', $header_array);
        $shard->prefixes_len = $header_data[0];
        $shard->words_len = $header_data[1];
        $shard->word_docs_len = $header_data[2];
        $shard->docids_len = $header_data[3];
        $shard->generation = $header_data[4];
        $shard->num_docs_per_generation = $header_data[5];
        $shard->num_docs = $header_data[6];
        $shard->num_link_docs = $header_data[7];
        $shard->len_all_docs = $header_data[8];
        $shard->len_all_link_docs = $header_data[9];
    }

    /**
     * Callback function for load method. splits a word_key . word_info string
     * into an entry in the passed shard $shard->words[word_key] = $word_info.
     *
     * @param string &value  the word_key . word_info string
     * @param int $key index in array - we don't use
     * @param object $shard IndexShard to add the entry to word table for
     */
    static function makeWords(&$value, $key, $shard)
    {
        $shard->words[substr($value, 0, self::WORD_KEY_LEN)] = 
            substr($value, self::WORD_KEY_LEN, 
                self::WORD_ITEM_LEN - self::WORD_KEY_LEN);
    }

}
