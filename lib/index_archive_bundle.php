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
 * Used to store word index
 */
require_once 'index_shard.php';
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
 * Encapsulates a set of web page summaries and an inverted word-index of terms
 * from these summaries which allow one to search for summaries containing a 
 * particular word.
 *
 * The basic file structures for an IndexArchiveBundle are:
 * <ol> 
 * <li>A WebArchiveBundle for web page summaries.</li>
 * <li>A set of inverted index generations. These generations
 *  have name index0, index1,...
 * The file generations.txt keeps track of what is the current generation. 
 * A given generation can hold NUM_WORDS_PER_GENERATION words amongst all 
 * its partitions. After which the next generation begins. 
 * </li>
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
     * structure contains info about the current generation:
     * its index (ACTIVE), and the number of words it contains
     * (NUM_WORDS).
     * @array
     */
    var $generation_info;
    /**
     * Number of docs before a new generation is started
     * @int
     */
    var $num_docs_per_generation;
    /**
     * WebArchiveBundle for web page summaries
     * @object
     */
    var $summaries;
    /**
     * Index Shard for current generation inverted word index
     * @object
     */
    var $current_shard;

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
        $num_partitions_summaries = NULL, $description = NULL,
        $num_docs_per_generation = NUM_DOCS_PER_GENERATION)
    {

        $this->dir_name = $dir_name;
        $index_archive_exists = false;

        if(!is_dir($this->dir_name)) {
            mkdir($this->dir_name);

        } else {
            $index_archive_exists = true;

        }

        if(file_exists($this->dir_name."/generation.txt")) {
            $this->generation_info = unserialize(
                file_get_contents($this->dir_name."/generation.txt"));
        } else {
            $this->generation_info['ACTIVE'] = 0;
            file_put_contents($this->dir_name."/generation.txt", 
                serialize($this->generation_info));
        }
        $this->summaries = new WebArchiveBundle($dir_name."/summaries",
            $filter_size, $num_partitions_summaries, $description);
        $this->summaries->initCountIfNotExists("VISITED_URLS_COUNT");

        $this->num_partitions_summaries = $this->summaries->num_partitions;

        $this->description = $this->summaries->description;

        $this->num_docs_per_generation = $num_docs_per_generation;

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
    public function addIndexData($index_shard)
    {

        crawlLog("**ADD INDEX DIAGNOSTIC INFO...");
        $start_time = microtime();
        $current_num_docs = $this->getActiveShard()->num_docs;
        $add_num_docs = $index_shard->num_docs;
        if($current_num_docs + $add_num_docs > $this->num_docs_per_generation){
            $switch_time = microtime();
            $this->forceSave();
            $this->generation_info['ACTIVE']++;
            $this->generation_info['CURRENT'] = 
                $this->generation_info['ACTIVE'];
            $current_index_shard_file = $this->dir_name."/index".
                $this->generation_info['ACTIVE'];
            $this->current_shard = new IndexShard(
                $current_index_shard_file, $this->generation_info['ACTIVE'] * 
                    $this->num_docs_per_generation);
            file_put_contents($this->dir_name."/generation.txt", 
                serialize($this->generation_info));
            crawlLog("Switch Shard time:".changeInMicrotime($switch_time));
        }
        $this->getActiveShard()->appendIndexShard($index_shard);
        crawlLog("Append Index Shard: Memory usage:".memory_get_usage() .
          " Time: ".(changeInMicrotime($start_time)));
    }

    /**
     * Sets the current shard to be the active shard (the active shard is
     * what we call the last (highest indexed) shard in the bundle. Then
     * returns a reference to this shard
     * @return &object last shard in the bundle
     */
     public function &getActiveShard()
     {
        if($this->setCurrentShard($this->generation_info['ACTIVE'])) {
            return $this->getCurrentShard();
        } else if(!isset($this->current_shard) ) {
            $current_index_shard_file = $this->dir_name."/index".
                $this->generation_info['CURRENT'];
            $this->current_shard = new IndexShard($current_index_shard_file,
                $this->generation_info['CURRENT']*$num_docs_per_generation);
        }
        return $this->current_shard;
     }

    /**
     * Returns the shard which is currently being used to read word-document
     * data from the bundle. If one wants to write data to the bundle use
     * getActiveShard() instead. The point of this method is to allow
     * for lazy reading of the file associated with the shard.
     *
     * @return &object the currently being index shard
     */
     public function &getCurrentShard()
     {
        if(!isset($this->current_shard)) {
            if(!isset($this->generation_info['CURRENT'])) {
                $this->generation_info['CURRENT'] = 
                    $this->generation_info['ACTIVE'];
            }
            $current_index_shard_file = $this->dir_name."/index".
                $this->generation_info['CURRENT'];
                
            if(file_exists($current_index_shard_file) ) {
                $this->current_shard = 
                    IndexShard::load($current_index_shard_file);
            } else {
                $this->current_shard = new IndexShard($current_index_shard_file,
                    $this->generation_info['CURRENT']*
                    $this->num_docs_per_generation);
            }
        }
        return $this->current_shard;
     }

    /**
     * Sets the current shard to be the $i th shard in the index bundle.
     *
     * @param $i which shard to set the current shard to be
     */
     public function setCurrentShard($i)
     {
        if(isset($this->generation_info['CURRENT']) && 
            $i == $this->generation_info['CURRENT'] ||
            $i > $this->generation_info['ACTIVE']) {
            return false;
        } else {
            $this->generation_info['CURRENT'] = $i;
            return true;
        }
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
     * to be save to disk, forces the current shard to be saved, the current
     * filter in the index filter bundle to be save
     */
    public function forceSave()
    {
        $this->summaries->forceSave();
        $this->getActiveShard()->save();
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
            $tmp = $this->getCurrentShard()->getWordInfo($word_key);
            if($tmp === false) {
                $words_array[$word_key] = 0;
            } else {
                $words_array[$word_key] = $tmp[2];
            }
        }

        uasort( $words_array, $comparison); 
        
        return array_slice($words_array, 0, $num);
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

