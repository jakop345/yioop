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
 * Summaries and word document list stored in WebArchiveBundle's so load it
 */
require_once 'web_archive_bundle.php'; 
/**
 * Bloom Filter used by BloomFilterBundle
 */
require_once 'bloom_filter_file.php';
/**
 * Used to check if a page already stored in the WebArchiveBundle
 */
require_once 'bloom_filter_bundle.php';
/**
 * Used for crawlLog and crawlHash
 */
require_once 'utility.php';
/** 
 *Loads common constants for web crawling
 */
require_once 'crawl_constants.php';

/**
 * Enumerative interface for common constants between WordIterator and
 * IndexArchiveBundle
 *
 * These constants are used as fields in arrays. They are negative to 
 * distinguish them from normal array elements 0, 1, 2... However, this
 * means you need to be slightly careful if you try to sort the array
 * as this might screw things up
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */
interface IndexingConstants
{
    const COUNT = -1;
    const END_BLOCK = -2;
    const LIST_OFFSET = -3;
    const POINT_BLOCK = -4;
    const PARTIAL_COUNT = -5;
    const NAME = -6;
} 


/**
 * Callback function used to set the offsets into the archive file from
 * the particular word info in the header block of a WordArchive
 *
 * @param array $data
 * @param array $objects
 * @param string $offset_field
 */
function setOffsetPointers($data, &$objects, $offset_field)
{
    $count = count($objects);

    for($i = 0 ; $i < $count ; $i++ ) {
        if(isset($objects[$i][$offset_field]) ) {
            $offset = $objects[$i][$offset_field];
            foreach($objects[$i] as $word_key_and_block_num => $docs_info) {
                $tmp = explode(":", $word_key_and_block_num);
                if(isset($tmp[1]) ) {
                    list($word_key, $block_num) = $tmp;
                    if(strcmp($word_key, "offset") != 0) {
                        if(($block_num +1)*BLOCK_SIZE < 
                            COMMON_WORD_THRESHOLD) {
                            $data[$word_key][$block_num] = $offset;
                        } else if(isset(
                            $docs_info[IndexingConstants::POINT_BLOCK])) {
                            $data[$word_key][IndexingConstants::LIST_OFFSET] = 
                                $offset;
                        } 
                    }
                }
            }

        }
    }

    return $data;
}

/**
 * Used to iterate through the documents associated with a word in
 * an IndexArchiveBundle. It also makes it easy to get the summaries
 * of these documents and restrict the documents by additional words.
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
class WordIterator implements IndexingConstants, CrawlConstants
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
     * The number of documents already iterated over
     * @var int
     */
    var $seen_docs;
    /**
     * @var int
     */
    var $restricted_seen_docs;
    /**
     * The number of documents in the current block before filtering
     * by restricted words
     * @var int
     */
    var $count_block_unfiltered;
    /**
     * Estimate of the number of documents that this iterator can return
     * @var int
     */
    var $num_docs;

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
     * 
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
     * First document that should be returned 
     * amongst all of the documents associated with the
     * iterator's $word_key
     * @var int
     */
    var $limit;

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
    public function __construct($word_key, $index, $limit = 0, $info_block = NULL)
    {
        $this->word_key = $word_key;
        $this->index = $index;
        $this->limit = $limit;
        $this->reset($info_block);
    }

    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     *
     * @param object $info_block the header block in the index WebArchiveBundle
     *      for the word this iterator iterates over. If not NULL, this saves
     *      the time to load it. If not it will be loaded, but this will be
     *      slower.
     */
    public function reset($info_block = NULL)
    {
        $this->restricted_seen_docs = 0;
        $this->count_block_unfiltered = 0;

        $partition = 
            WebArchiveBundle::selectPartition($this->word_key, 
                $this->index->num_partitions_index);

        if($info_block == NULL) {
        	    $this->info_block = 
        	        $this->index->getPhraseIndexInfo($this->word_key);
        } else {
            $this->info_block = $info_block; 
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
            

        }

        $this->seen_docs = $count_till_generation - 
            $this->info_block[self::COUNT];
        $this->initGeneration();


    }

    /**
     * Sets up the iterator to iterate through the current generation.
     *
     * @return bool whether the initialization succeeds
     */
    public function initGeneration()
    {

        if($this->info_block !== NULL) {
            $info_block = $this->index->getPhraseIndexInfo(
                $this->word_key, $this->info_block['CURRENT_GENERATION_INDEX'], 
                $this->info_block);
            if($info_block === NULL) {
                return false;
            }
            $this->info_block = $info_block;
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
     * Gets the block of doc summaries associated with the current doc
     * pointer and which match the array of additional word restrictions
     * @param array $restrict_phrases an array of additional words or phrases
     *      to see if contained in summary
     * @return array doc summaries that match
     */
    public function currentDocsWithWord($restrict_phrases = NULL)
    {
        if($this->num_generations <= 
            $this->info_block['CURRENT_GENERATION_INDEX']) {
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
                    $pages = $doc_block[
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
                        $pages = 
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
            $this->count_block_unfiltered = count($pages);

            if($restrict_phrases != NULL) {

                 $out_pages = array();
                 if(count($pages) > 0 ) {
                     foreach($pages as $doc_key => $doc_info) {

                         if(isset($doc_info[self::SUMMARY_OFFSET])) {

                             $page = $this->index->getPage(
                                $doc_key, $doc_info[self::SUMMARY_OFFSET]);
                             /* build a string out of title, links, 
                                and description
                              */
                             $page_string = mb_strtolower(
                                PhraseParser::extractWordStringPageSummary(
                                    $page));

                             $found = true;
                             foreach($restrict_phrases as $phrase) {
                                 if(mb_strpos($page_string, $phrase) 
                                    === false) {
                                     $found = false;
                                 }
                             }
                             if($found == true) {
                                 $out_pages[$doc_key] = $doc_info;
                             }
                         }
                     }
                 }
                 $pages = $out_pages;
            }
            return $pages;
        } else {
            return -1;
        }
    }

    /**
     * Get the current block of doc summaries for the word iterator and advances
     * the current pointer to the next block
     *
     * @param array $restrict_phrases additional words to restrict doc summaries
     *      returned
     * @return array doc summaries matching the $restrict_phrases
     */
    public function nextDocsWithWord($restrict_phrases = NULL)
    {
        $doc_block = $this->currentDocsWithWord($restrict_phrases);
        if($this->seen_docs <  $this->limit) {
            $this->seen_docs = $this->count_block_unfiltered + $this->limit;
        } else {
        	    $this->seen_docs += $this->count_block_unfiltered;
        }
        $this->restricted_seen_docs += count($doc_block);
        if($doc_block == -1 || !is_array($doc_block)) {
            return NULL;
        }

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

        return $doc_block;

    }

}

/**
 * Encapsulates a set of web page summaries and an inverted word-index of terms
 * from these summaries which allow one to search for summaries containing a 
 * particular word.
 *
 * The basic file structures for an IndexArchiveBundle are:
 * <ol> 
 * <li>A WebArchiveBundle for web page summaries.</li>
 * <li>A set of WebArchiveBundles for the inverted index. Each such bundle
 * is called a <b>generation</b>. These bundles have name index0, index1,...
 * The file generations.txt keeps track of what is the current generation
 * and how many words have been stored in it. A given generation can
 * hold NUM_WORDS_PER_GENERATION words amongst all its partitions. After which 
 * the next generation begins. In a given generation, a word is stored in
 * the partition that its hash key hashes to. The same word may appear in
 * several generations. The info block for a partition for a particular
 * generation contains objects for each word of the generation that hashed 
 * to that partition. Each such word object contains a count of the number
 * of documents it occurred in for that generation. It also has an
 * array of block_pointers to blocks of size BLOCK_SIZE. These blocks contains
 * documents that the word occurred in, the score for the occurrence, and
 * an offset into the summary file for that document. If the total number of
 * documents is not a multiple of BLOCK_SIZE the remaining documents are stored
 * directly in the word's info block object. If, in a given generation, a
 * word occurs more than COMMON_WORD_THRESHOLD many times then the word object
 * uses a LIST_OFFSET pointer to point to a linked list in the partition of
 * addtional blocks of documents for that word.
 * </li>
 * <li>For each partition and for all generations a BloomFilterFile is used
 * to keep track of which words appear in which generations for a 
 * particular partition. These filters are stored in a folder within the
 * IndexArchiveBundle called index_filters. When a word and documents
 * containing it are stored in an IndexArchiveBundle, its word_key (its has) is 
 * stored in the filter for the partition its word_key hash to. Further
 * if the current generation is i, then work_ket concatenated with i is
 * also stored in this same filter.</li>
 * </ol>
 *
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage library
 */
class IndexArchiveBundle implements IndexingConstants, CrawlConstants
{
    /**
     * Used to keep track of the time to perform various operations
     * in this IndexArchiveBundle
     * @var array
     */
    var $diagnostics;
    /**
     * Folder name to use for this IndexArchiveBundle
     * @var string
     */
    var $dir_name;
    /**
     * A short text name for this IndexArchiveBundle
     * @var string
     */
    var $description;
    /**
     * Number of partitions in the summaries WebArchiveBundle
     * @int
     */
    var $num_partitions_summaries;
    /**
     * Number of partitions in the inverted word index 
     * (same for each generation)
     * @int
     */
    var $num_partitions_index;
    /**
     * structure contains info about the current generation:
     * its index (ACTIVE), and the number of words it contains
     * (NUM_WORDS).
     * @array
     */
    var $generation_info;
    /**
     * Number of words before a new generation is started
     * @int
     */
    var $num_words_per_generation;
    /**
     * WebArchiveBundle for web page summaries
     * @object
     */
    var $summaries;
    /**
     * WebArchiveBundle for inverted word index
     * @object
     */
    var $index;
    /**
     * Bloom Filters used to figure out which words are in which generations for
     * given paritions
     * @object
     */
    var $index_partition_filters;

    /**
     * Makes or initializes an IndexArchiveBundle with the provided parameters
     *
     * @param string $dir_name folder name to store this bundle
     * @param int $filter_size size of a Bloom filter for the word index
     *      partition filters as wells as for the page_exists_filters in
     *      the WebArchiveBundles
     * @param int $num_partitions_summaries number of WebArchive partitions
     *      to use in the summmaries WebArchiveBundle
     * @param int $num_partitions_index number of WebArchive partitions
     *      to use in the index WebArchiveBundle
     * @param string $description a short text name for this IndexArchiveBundle 
     */
    public function __construct($dir_name, $filter_size = -1, 
        $num_partitions_summaries = NULL, $num_partitions_index = NULL, 
        $description = NULL)
    {

        $this->dir_name = $dir_name;
        $index_archive_exists = false;

        if(!is_dir($this->dir_name)) {
            mkdir($this->dir_name);
            mkdir($this->dir_name."/index_filters");
        } else {
            $index_archive_exists = true;

        }

        if(file_exists($this->dir_name."/generation.txt")) {
            $this->generation_info = unserialize(
                file_get_contents($this->dir_name."/generation.txt"));
        } else {
            $this->generation_info['ACTIVE'] = 0;
            $this->generation_info['NUM_WORDS'] = 0;
            file_put_contents($this->dir_name."/generation.txt", 
                serialize($this->generation_info));
        }
        $this->summaries = new WebArchiveBundle($dir_name."/summaries",
            $filter_size, $num_partitions_summaries, $description);
        $this->num_partitions_summaries = $this->summaries->num_partitions;

        $this->index = new WebArchiveBundle(
            $dir_name."/index".$this->generation_info['ACTIVE'], -1, 
            $num_partitions_index);
        $this->num_partitions_index = $this->index->num_partitions;
        $this->description = $this->summaries->description;

        $this->num_words_per_generation = NUM_WORDS_PER_GENERATION;

    }

    /**
     * Add the array of $pages to the summaries WebArchiveBundle pages being 
     * stored in the partition according to the $key_field and the field used 
     * to store the resulting offsets given by $offset_field.
     *
     * @param string $key_field field used to select partition
     * @param string $offset_field field used to record offsets after storing
     * @param array &$pages data to store
     * @return array $pages adjusted with offset field
     */
    public function addPages($key_field, $offset_field, $pages)
    {
        $result = $this->summaries->addPages($key_field, $offset_field, $pages);

        return $result;
    }

    /**
     * Adds the provided mini inverted index data to the IndexArchiveBundle
     *
     * @param array $index_data a mini inverted index of word_key=>doc data
     *      to add to this IndexArchiveBundle
     */
    public function addIndexData($index_data)
    {

        $out_data = array();

        if(!count($index_data) > 0) return;

        /* Arrange the words according to the partitions they are in
         */

        $this->diagnostics['SELECT_TIME'] = 0;
        $this->diagnostics['INFO_BLOCKS_TIME'] = 0;
        $this->diagnostics['ADD_FILTER_TIME'] = 0;
        $this->diagnostics['ADD_OBJECTS_TIME'] = 0;
        $start_time = microtime();
        foreach($index_data as $word_key => $docs_info) {

            $partition = WebArchiveBundle::selectPartition(
                 $word_key, $this->num_partitions_index);
            $out_data[$partition][$word_key] = $docs_info;

        }
        $this->diagnostics['SELECT_TIME'] += changeInMicrotime($start_time);

        /* for each partition add the word data for the partition to the 
           partition web archive
         */
        $cnt = 0;
        foreach($out_data as $partition => $word_data) {
            $this->addPartitionWordData($partition, $word_data);
            $cnt++;
        }
        file_put_contents($this->dir_name."/generation.txt", 
            serialize($this->generation_info));
        $out_data = NULL; 
        gc_collect_cycles();

        crawlLog("**ADD INDEX DIAGNOSTIC INFO...");
        crawlLog("**Time calculating select partition functions ".
            $this->diagnostics['SELECT_TIME']);
        crawlLog("**Time reading info blocks ".
            $this->diagnostics['INFO_BLOCKS_TIME']);
        crawlLog("**Time adding objects to index ".
            $this->diagnostics['ADD_OBJECTS_TIME']);
        crawlLog("**Time adding to filters ".
            $this->diagnostics['ADD_FILTER_TIME']);
        crawlLog("**Number of partitions ".$cnt);

    }

    /**
     * Adds the mini-inverted index data that to a particular partition.
     * It is assume the word keys in this data would hash to the destined
     * index partitions
     *
     * @param int $partition WebArchive in the index WebArchiveBundle of the
     *      current generation to write to
     * @param array &$word_data what to wrtie
     * @param bool $overwrite whether to signal that all data in prior 
     * generations associated with keys that are being inserted should be 
     * ignored (for instance, multi-word search are partially computed and
     * added to the index. If these get recomputed we might want to ignore
     * prior work. )
     */
    public function addPartitionWordData($partition, 
        &$word_data, $overwrite = false)
    {
        $start_time = microtime();

        $block_data = $this->readPartitionInfoBlock($partition);

        if(isset($this->diagnostics['INFO_BLOCKS_TIME'])) {
            $this->diagnostics['INFO_BLOCKS_TIME'] += 
                changeInMicrotime($start_time);
        }
        
        if($block_data == NULL) {
            $block_data[self::NAME] = $partition;
        }

        //update counts set-up add link to offset linked lists
        $out_data = array();
        $out_data[0] = array();

        $this->initPartitionIndexFilter($partition);

        foreach($word_data as $word_key => $docs_info) {
            $start_time = microtime();

            $this->addPartitionIndexFilter($partition, $word_key);
            $this->addPartitionIndexFilter(
                $partition, $word_key . $this->generation_info['ACTIVE']);
            if(isset($this->diagnostics['ADD_FILTER_TIME'])) {
                $this->diagnostics['ADD_FILTER_TIME'] += 
                    changeInMicrotime($start_time);
            }

            if(!isset($block_data[$word_key]) || $overwrite == true) {
                unset($block_data[$word_key]);
                $block_data[$word_key][self::COUNT] = 0;
                $block_data[$word_key][self::END_BLOCK] = array();
                $block_data[$word_key][self::LIST_OFFSET] = NULL;
                $unfilled_block_num = 0;

            } else {
                $unfilled_block_num = 
                    floor($block_data[$word_key][self::COUNT] / BLOCK_SIZE);
            }

            $cnt = count($docs_info);
            $block_data[$word_key][self::COUNT] += $cnt;

            $tmp = 
                array_merge($block_data[$word_key][self::END_BLOCK],$docs_info);
            uasort($tmp, "scoreOrderCallback");
            $add_cnt = count($tmp);
            $num_blocks = floor($add_cnt / BLOCK_SIZE);
            $block_data[$word_key][self::END_BLOCK] = 
                array_slice($tmp, $num_blocks*BLOCK_SIZE);

            $first_common_flag = true;
            $min_common = NULL;
            $slice_cnt = $num_blocks - 1;
            for($i = $unfilled_block_num + $num_blocks - 1; 
                $i >= $unfilled_block_num ; $i--) {
                $out_data[0][$word_key .":". $i] = 
                    array_slice($tmp, $slice_cnt*BLOCK_SIZE, BLOCK_SIZE);
                if(($i+1)*BLOCK_SIZE > COMMON_WORD_THRESHOLD) {
                    $min_common = $i;
                    if($first_common_flag) {
                        if(isset($block_data[$word_key][self::LIST_OFFSET])) {
                            $out_data[0][$word_key .":". $i][self::LIST_OFFSET]=
                                $block_data[$word_key][self::LIST_OFFSET];
                        } else {
                            $out_data[0][$word_key .":". $i][self::LIST_OFFSET]=
                                NULL;
                        }
                        $first_common_flag = false;
                    } else {
                        $out_data[0][$word_key .":". $i][self::LIST_OFFSET] = 
                            NULL; // next in list is in same block
                    }
                }

                $slice_cnt--;
            }
            if($min_common !== NULL) {
                $out_data[0][$word_key .":". $min_common][self::POINT_BLOCK] = 0; 
                // this index needs to point to previous block with word
            }

        }

        $start_time = microtime();
        $this->index->addObjectsPartition("offset", $partition, 
            $out_data, $block_data, "setOffsetPointers", false);

        if(isset($this->diagnostics['ADD_OBJECTS_TIME'])) {
            $this->diagnostics['ADD_OBJECTS_TIME'] += 
                changeInMicrotime($start_time);
        }


        if($this->generation_info['NUM_WORDS']>$this->num_words_per_generation){
            $index_filter_size = $this->index->filter_size;
            $this->generation_info['ACTIVE']++;
            $this->generation_info['NUM_WORDS'] = 0;
            $this->index = new WebArchiveBundle(
                $this->dir_name."/index".$this->generation_info['ACTIVE'], 
                $index_filter_size, $this->num_partitions_index);
            file_put_contents(
                $this->dir_name."/generation.txt", 
                serialize($this->generation_info));
        }

    }

    /**
     * Adds the provided $word_key to the BloomFilter for the given partition
     *
     * @param int $partition whose Bloom Filter we want to add the word_key to
     * @param string $word_key the key to add
     * @return bool whether the add was successful
     */
    public function addPartitionIndexFilter($partition, $word_key)
    {
        if($this->initPartitionIndexFilter($partition) === false) {
            return false;
        }
        if(!$this->index_partition_filters[$partition]->contains($word_key)) {
            $this->generation_info['NUM_WORDS']++;
            $this->index_partition_filters[$partition]->add($word_key);
        }
        
        return true;
    }

    /**
     * Initializes or constructs the Bloom filter assocaited with a partition
     * @param int $partition index of desired partition
     * @return bool whether the operation was successful
     */
    public function initPartitionIndexFilter($partition)
    {
        if(!isset($this->index_partition_filters[$partition])) {
            if(file_exists($this->dir_name.
                "/index_filters/partition$partition.ftr")) {
                $this->index_partition_filters[$partition] = 
                    BloomFilterFile::load(
                        $this->dir_name .
                        "/index_filters/partition$partition.ftr");
            } else {
                $filter_size = $this->num_words_per_generation;
                $this->index_partition_filters[$partition] = 
                    new BloomFilterFile(
                        $this->dir_name .
                        "/index_filters/partition$partition.ftr", $filter_size);
            }
        }
        return true;
    }

    /**
     * Gets doc summaries of documents containing a given word and meeting the
     * additional provided criteria
     * @param string $word_key the word to iterate over to get document results
     *      of
     * @param int $limit number of first document in order to return
     * @param int $num number of documents to return summaries of
     * @param array $restrict_phrases additional words and phrase to store
     *      further restrict the search 
     * @param string $phrase_key a hash of the word and restricted phrases to
     *      store the results of the look up
     * @param array $phrase_info info block of the word
     * @return array document summaries
     */
    public function getSummariesByHash($word_key, $limit, $num, 
        $restrict_phrases = NULL, $phrase_key = NULL, $phrase_info = NULL)
    {
        if($phrase_key ==  NULL) {
            $phrase_key = $word_key;
        }

        if($phrase_info == NULL) {
            $phrase_info = $this->getPhraseIndexInfo($phrase_key);
        }

        if($phrase_info == NULL || (isset($phrase_info[self::PARTIAL_COUNT]) 
            && $phrase_info[self::PARTIAL_COUNT] < $limit + $num)) {
            $this->addPhraseIndex(
                $word_key, $restrict_phrases, $phrase_key, $limit + $num);
        }

        $iterator = new WordIterator($phrase_key, $this, $limit, $phrase_info);

        $num_retrieved = 0;
        $pages = array();

         while(is_array($next_docs = $iterator->nextDocsWithWord()) && 
            $num_retrieved < $num) {
             $num_docs_in_block = count($next_docs);

             foreach($next_docs as $doc_key => $doc_info) {
                 if(isset($doc_info[self::SUMMARY_OFFSET])) {
                     $page = $this->getPage(
                        $doc_key, $doc_info[self::SUMMARY_OFFSET]);
                     $pages[] = array_merge($doc_info, $page);
                     $num_retrieved++;
                 }
                 if($num_retrieved >=  $num) {
                     break 2;
                 }
             }
         }
        $results['TOTAL_ROWS'] = $iterator->num_docs;
        $results['PAGES'] = $pages;
        return $results;
    }

    /**
     * Gets the page out of the summaries WebArchiveBundle with the given 
     * key and offset
     *
     * The $key determines the partition WebArchive, the $offset give the
     * byte offset within that archive.
     * @param string $key hash to use to look up WebArchive partition
     * @param int $offset byte offset in partition of desired page
     * @return array desired page
     */
    public function getPage($key, $offset)
    {
        return $this->summaries->getPage($key, $offset);
    }

    /**
     * Returns a block of documents a word occur in. The doc block looked up
     * is at a given offset into the word's partition WebArchive for a given
     * generation. This is used when the word occurs more the 
     * COMMON_WORD_THRESHOLD many times in a generation
     *
     * @param string $word_key hash of word whose doc block we are looking up
     * @param int $offset byte offset into word's partition WebArchive for the
     *      supplied generation
     * @param int $generation which generation to look up the doc block of
     * @return array the desired doc block
     */
    public function getWordDocBlock($word_key, $offset, $generation = -1)
    {
        if($generation == -1) {
            return $this->index->getPage($word_key, $offset);
        } else {
            $archive = 
                new WebArchiveBundle($this->dir_name."/index".$generation);
            return $archive->getPage($word_key, $offset);
        }
    }

    /**
     * Gets a page using in WebArchive $partition of the word index 
     * using the provided byte $offset and using existing $file_handle 
     * if possible.
     *
     * @param int $partition which WebArchive to look in
     * @param int $offset byte offset of page data
     * @param resource $file_handle file handle resource of $partition archive
     * @return array desired page
     */
    public function getPageByPartition($partition, $offset, $file_handle = NULL)
    {
        return $this->index->getPageByPartition(
            $partition, $offset, $file_handle);
    }

    /**
     * Adds the given summary to the summary exists filter bundle
     *
     * @param string $key_field field of page with hash of page content
     * @param array $page summary of page
     */
    public function addPageFilter($key_field, $page)
    {
        $this->summaries->addPageFilter($key_field, $page);
    }

    /**
     * Looks at the $field key of elements of pages and computes an array
     * consisting of $field values which are not in
     * the page_exists_filter_bundle of the summaries bundle
     *
     * @param array $pages set of page data to start from
     * @param string $key_field field to check against filter bundle
     * @return mixed false if filter empty; desired array otherwise
     */
    public function differenceContainsPages(&$page_array, $field_name = NULL)
    {
        return $this->summaries->differencePagesFilter(
            $page_array, $field_name);
    }

    /**
     * Forces the data in the page exists filter bundle of summaries 
     * to be save to disk, forces each index partition summary to be saved
     */
    public function forceSave()
    {
        $this->summaries->forceSave();
        for($i = 0; $i < $this->num_partitions_index; $i++) {
            if(isset($this->index_partition_filters[$i]) &&
                $this->index_partition_filters[$i] != NULL) {
                $this->index_partition_filters[$i]->save();
            }
        }
    }

    /**
     * Computes statistics for the provided phrase_key. 
     * These include an estimate of the total number of documents it occurs in,
     * as well as which generations it occurs in, and what are its info block
     * looks like in the current generation
     *
     * @param string $phrase_key what to compute statistics for
     * @param int $generation_index the current generation
     * @param array $info_block info_block of the phrase_key (will look up
     *      if not provided)
     * @return array info for this $phrase_key
     */
    public function getPhraseIndexInfo(
        $phrase_key, $generation_index = 0, $info_block = NULL)
    {

        $partition = 
            WebArchiveBundle::selectPartition(
                $phrase_key, $this->num_partitions_index);
        $info = array();

        if($info_block == NULL) {

            if(!$this->initPartitionIndexFilter($partition)) {
                return NULL;
            }
            $filter = & $this->index_partition_filters[$partition];

            if(!$filter->contains($phrase_key)) {
                return NULL;
            }

            $active_generation = $this->generation_info['ACTIVE'];

            $min_generation = 0;
            for($i = 0; $i <= $active_generation; $i++) {
                if($filter->contains($phrase_key . $i)) {
                    if($filter->contains("delete". $phrase_key . $i)) {
                        $info['GENERATIONS'] = array(); 
                        //truncate all previously seen
                    } else {
                        $info['GENERATIONS'][] = $i;
                    }
                }
            }
            $num_generations = count($info['GENERATIONS']);
            if($num_generations == 0) {
                return NULL;
            }

            $sample_size = min($num_generations, SAMPLE_GENERATIONS);
            $sum_count = 0;
            for($i = 0; $i < $sample_size; $i++) {
                $block_info = 
                    $this->readPartitionInfoBlock(
                        $partition, $info['GENERATIONS'][$i]);
                $sum_count += $block_info[$phrase_key][self::COUNT];
            }

            $info['TOTAL_COUNT'] = 
                ceil(($sum_count*$num_generations)/$sample_size); 
                // this is an estimate
        } else {
            $info['TOTAL_COUNT'] = $info_block['TOTAL_COUNT'];
            $info['GENERATIONS'] = $info_block['GENERATIONS'];
        }

        $block_info = $this->readPartitionInfoBlock(
            $partition, $info['GENERATIONS'][$generation_index]);
        $phrase_info = $block_info[$phrase_key];

        $info['CURRENT_GENERATION_INDEX'] = $generation_index;

        if(isset($phrase_info)) {
            $phrase_info['CURRENT_GENERATION_INDEX'] = 
                $info['CURRENT_GENERATION_INDEX'];
            $phrase_info['TOTAL_COUNT'] = $info['TOTAL_COUNT'];
            $phrase_info['GENERATIONS'] = $info['GENERATIONS'];
            return $phrase_info;
        } else {
            return NULL;
        }

    }

    /**
     * Sets the information associated with a word in the inverted index
     *
     * @param string $phrase_key
     * @param array $info
     */
    public function setPhraseIndexInfo($phrase_key, $info)
    {
        $partition = WebArchiveBundle::selectPartition(
            $phrase_key, $this->num_partitions_index);

        $partition_block_data = $this->readPartitionInfoBlock($partition);

        if($partition_block_data == NULL || !is_array($partition_block_data)) {
            $partition_block_data = array();
        }

        $partition_block_data[$phrase_key] = $info;

        $this->writePartitionInfoBlock($partition, $partition_block_data);

    }

    /**
     * Adds the supplied phrase to the IndexArchiveBundle.
     *
     * The most selective word in the phrase is $word_key, the additional
     * words are in $restrict_phrases, the hash of the phrase to add is 
     * $phrase_key, and if the will be a lot of results compute at least
     * the first $num_needed.
     *
     * @param string $word_key hash of most selective word in phrase 
     * @param array $restrict_phrases additional words in phrase
     * @param string $phrase_key hash of phrase to add
     * @param $num_needed minimum number of doc results to save if possible
     */
    public function addPhraseIndex($word_key, $restrict_phrases, 
        $phrase_key, $num_needed)
    {
        if($phrase_key == NULL) {
            return;
        }

        $partition = 
            WebArchiveBundle::selectPartition($phrase_key, 
                $this->num_partitions_index);

        $iterator = new WordIterator($word_key, $this);
        $current_count = 0;
        $buffer = array();
        $word_data = array();
        $partial_flag = false;
        $first_time = true;

        while(is_array($next_docs = 
            $iterator->nextDocsWithWord($restrict_phrases))) {
            $buffer = array_merge($buffer, $next_docs);
            $cnt = count($buffer);

            if($cnt > COMMON_WORD_THRESHOLD) {
                $word_data[$phrase_key] = 
                    array_slice($buffer, 0, COMMON_WORD_THRESHOLD);

                $this->addPartitionWordData($partition, $word_data, $first_time);
                $first_time = false;
                $buffer = array_slice($buffer, COMMON_WORD_THRESHOLD); 
                $current_count += COMMON_WORD_THRESHOLD;

                if($current_count > $num_needed) { 
                    /* notice $num_needed only plays a role when 
                      greater than COMMON_WORD_THRESHOLD
                     */
                    $partial_flag = true;
                    break;
                }
             }
        }

        $word_data[$phrase_key] = $buffer;

        $this->addPartitionIndexFilter(
            $partition, 
            "delete". $phrase_key . ($this->generation_info['ACTIVE'] - 1));

        $this->addPartitionWordData($partition, $word_data);
        $this->addPartitionIndexFilter($partition, $phrase_key);
        $this->addPartitionIndexFilter($partition, $phrase_key . 
            $this->generation_info['ACTIVE']);
        $this->index_partition_filters[$partition]->save();
        file_put_contents($this->dir_name."/generation.txt", 
            serialize($this->generation_info));

        $block_info = $this->readPartitionInfoBlock($partition);
        $info = $block_info[$phrase_key];
        $current_count += count($buffer);
        if($partial_flag) {
            $info[self::PARTIAL_COUNT] = $current_count;
            $info[self::COUNT] = 
                floor($current_count*$iterator->num_docs/$iterator->seen_docs);
            $this->setPhraseIndexInfo($phrase_key, $info);
        }
    }

    /**
     * Computes the words which appear in the fewest or most documents
     *
     * @param array $word_keys keys of words to select amongst
     * @param int $num number of words from the above set to return
     * @param string $comparison callback function name for how to compare words
     * @return array the $num most documents or $num least document words
     */
    public function getSelectiveWords($word_keys, $num, $comparison="lessThan") 
        //lessThan is in utility.php
    {
        $words_array = array();
        if(!is_array($word_keys) || count($word_keys) < 1) { return NULL;}

        foreach($word_keys as $word_key) {
            $info = $this->getPhraseIndexInfo($word_key);
            if(isset($info['TOTAL_COUNT'])) {
                $words_array[$word_key] = $info['TOTAL_COUNT'];
            } else {
                $words_array[$word_key] = 0;
            }
        }

        uasort( $words_array, $comparison); 
        
        return array_slice($words_array, 0, $num);
    }

    /**
     * Reads the info block of $partition index WebArchive
     *
     * @param int $partition WebArchive to read from
     * @return array data in its info block
     */
    public function readPartitionInfoBlock($partition, $generation = -1)
    {
        if($generation == -1) {
            return $this->index->readPartitionInfoBlock($partition);
        } else {
            $archive = new WebArchiveBundle(
                $this->dir_name."/index".$generation);
            return $archive->readPartitionInfoBlock($partition);
        }

    }

    /**
     * Write $data into the info block of the $partition index WebArchive
     *
     * @param int $partition WebArchive to write into
     * @param array $data what to write
     */
    public function writePartitionInfoBlock($partition, $data)
    {
        $this->index->writePartitionInfoBlock($partition, $data);
    }

    /**
     * Gets the description, count of summaries, and number of partions of the
     * summaries store in the supplied directory
     *
     * @param string path to a directory containing a summaries WebArchiveBundle
     * @return array summary of the given archive
     */
    public static function getArchiveInfo($dir_name)
    {
        return WebArchiveBundle::getArchiveInfo($dir_name."/summaries");
    }


}
?>

