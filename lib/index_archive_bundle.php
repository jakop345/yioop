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
 *Loads common constants for word indexing
 */
require_once 'indexing_constants.php';


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
     * @param string $description a text name/serialized info about this
     * IndexArchiveBundle 
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
        $this->summaries->initCountIfNotExists("VISITED_URLS_COUNT");

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
     * @param int $visited_urls_count number to add to the count of visited urls
     *      (visited urls is a smaller number than the total count of objects
     *      stored in the index).
     * @return array $pages adjusted with offset field
     */
    public function addPages($key_field, $offset_field, $pages, 
        $visited_urls_count)
    {
        $result = $this->summaries->addPages($key_field, $offset_field, $pages);
        $this->summaries->addCount($visited_urls_count, "VISITED_URLS_COUNT");
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
            uasort($tmp, "docRankOrderCallback");
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
                $out_data[
                    0][$word_key .":". $min_common][self::POINT_BLOCK] = 0;
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

            if($filter == NULL || !$filter->contains($phrase_key)) {
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
                return NULL;
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

