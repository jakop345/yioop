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
 * Summaries and word document list stored in WebArchiveBundle's so load it
 */
require_once 'web_archive_bundle.php'; 
/**
 * Used to store word index
 */
require_once 'index_shard.php';
/**
 * Used to store word dictionary
 */
require_once 'index_dictionary.php';
/**
 * Used for crawlLog and crawlHash
 */
require_once 'utility.php';
/** 
 *Loads common constants for web crawling
 */
require_once 'crawl_constants.php';


/**
 * Encapsulates a set of web page summaries and an inverted word-index of terms
 * from these summaries which allow one to search for summaries containing a 
 * particular word.
 *
 * The basic file structures for an IndexArchiveBundle are:
 * <ol> 
 * <li>A WebArchiveBundle for web page summaries.</li>
 * <li>A IndexDictionary containing all the words stored in the bundle.
 * Each word entry in the dictionary contains starting and ending
 * offsets for documents containing that word for some particular IndexShard
 * generation.</li>
 * <li>A set of index shard generations. These generations
 *  have names index0, index1,... A shard has word entries, word doc entries
 *  and document entries. For more information see the index shard 
 * documentation.
 * </li>
 * <li>
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
class IndexArchiveBundle implements CrawlConstants
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
     * @var int
     */
    var $num_partitions_summaries;

    /**
     * structure contains info about the current generation:
     * its index (ACTIVE), and the number of words it contains
     * (NUM_WORDS).
     * @var array
     */
    var $generation_info;
    /**
     * Number of docs before a new generation is started
     * @var int
     */
    var $num_docs_per_generation;
    /**
     * WebArchiveBundle for web page summaries
     * @var object
     */
    var $summaries;

    /**
     * IndexDictionary for all shards in the IndexArchiveBundle
     * This contains entries of the form (word, num_shards with word,
     * posting list info 0th shard containing the word, 
     * posting list info 1st shard containing the word, ...) 
     * @var object
     */
    var $dictionary;

    /**
     * Index Shard for current generation inverted word index
     * @var object
     */
    var $current_shard;

    /**
     * Makes or initializes an IndexArchiveBundle with the provided parameters
     *
     * @param string $dir_name folder name to store this bundle
     * @param int $num_partitions_summaries number of WebArchive partitions
     *      to use in the summmaries WebArchiveBundle
     * @param string $description a text name/serialized info about this
     *      IndexArchiveBundle 
     */
    public function __construct($dir_name, $read_only_archive = true,
        $description = NULL, $num_docs_per_generation = NUM_DOCS_PER_GENERATION)
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
            $read_only_archive, -1, $description);
        $this->summaries->initCountIfNotExists("VISITED_URLS_COUNT");

        $this->description = $this->summaries->description;

        $this->num_docs_per_generation = $num_docs_per_generation;

        $this->dictionary = new IndexDictionary($this->dir_name."/dictionary");

    }

    /**
     * Add the array of $pages to the summaries WebArchiveBundle pages being 
     * stored in the partition $generation and the field used 
     * to store the resulting offsets given by $offset_field.
     *
     * @param int $generation field used to select partition
     * @param string $offset_field field used to record offsets after storing
     * @param array &$pages data to store
     * @param int $visited_urls_count number to add to the count of visited urls
     *      (visited urls is a smaller number than the total count of objects
     *      stored in the index).
     */
    public function addPages($generation, $offset_field, &$pages, 
        $visited_urls_count)
    {
        $this->summaries->setWritePartition($generation);
        $this->summaries->addPages($offset_field, $pages);
        $this->summaries->addCount($visited_urls_count, "VISITED_URLS_COUNT");
    }

    /**
     * Adds the provided mini inverted index data to the IndexArchiveBundle
     * Expects initGenerationToAdd to be called before, so generation is correct
     *
     * @param object $index_shard a mini inverted index of word_key=>doc data
     *      to add to this IndexArchiveBundle
     */
    public function addIndexData($index_shard)
    {

        crawlLog("**ADD INDEX DIAGNOSTIC INFO...");
        $start_time = microtime();

        $this->getActiveShard()->appendIndexShard($index_shard);
        crawlLog("Append Index Shard: Memory usage:".memory_get_usage() .
          " Time: ".(changeInMicrotime($start_time)));
    }

    /**
     * Determines based on its size, if index_shard should be added to
     * the active generation or in a new generation should be started.
     * If so, a new generation is started, the old generation is saves, and
     * the dictionary of the old shard is copied to the bundles dictionary
     * and a log-merge performed if needed
     *
     * @param object $index_shard a mini inverted index of word_key=>doc data
     * @return int the active generation after the check and possible change has
     *      been performed
     */
    public function initGenerationToAdd($index_shard)
    {
        $current_num_docs = $this->getActiveShard()->num_docs;
        $add_num_docs = $index_shard->num_docs;
        if($current_num_docs + $add_num_docs > $this->num_docs_per_generation){
            $switch_time = microtime();
            $this->saveAndAddCurrentShardDictionary();
            //Set up new shard
            $this->generation_info['ACTIVE']++;
            $this->generation_info['CURRENT'] = 
                $this->generation_info['ACTIVE'];
            $current_index_shard_file = $this->dir_name."/index".
                $this->generation_info['ACTIVE'];
            $this->current_shard = new IndexShard(
                $current_index_shard_file, $this->generation_info['ACTIVE'], 
                    $this->num_docs_per_generation);
            file_put_contents($this->dir_name."/generation.txt", 
                serialize($this->generation_info));
            crawlLog("Switch Shard time:".changeInMicrotime($switch_time));
        }

        return $this->generation_info['ACTIVE'];
    }

    function saveAndAddCurrentShardDictionary()
    {
        // Save current shard dictionary to main dictionary
        $this->forceSave();
        $current_index_shard_file = $this->dir_name."/index".
            $this->generation_info['ACTIVE'];
        /* want to do the copying of dictionary as files to conserve memory
           in case merge tiers after adding to dictionary
        */
        $this->current_shard = new IndexShard(
            $current_index_shard_file, $this->generation_info['ACTIVE'],  
                $this->num_docs_per_generation, true);
        $this->dictionary->addShardDictionary($this->current_shard);
    }

    /**
     * Sets the current shard to be the active shard (the active shard is
     * what we call the last (highest indexed) shard in the bundle. Then
     * returns a reference to this shard
     * @return object last shard in the bundle
     */
     public function getActiveShard()
     {
        if($this->setCurrentShard($this->generation_info['ACTIVE'])) {
            return $this->getCurrentShard();
        } else if(!isset($this->current_shard) ) {
            $current_index_shard_file = $this->dir_name."/index".
                $this->generation_info['CURRENT'];
            $this->current_shard = new IndexShard($current_index_shard_file,
                $this->generation_info['CURRENT'], $num_docs_per_generation);
        }
        return $this->current_shard;
     }

    /**
     * Returns the shard which is currently being used to read word-document
     * data from the bundle. If one wants to write data to the bundle use
     * getActiveShard() instead. The point of this method is to allow
     * for lazy reading of the file associated with the shard.
     *
     * @return object the currently being index shard
     */
     public function getCurrentShard()
     {
        if(!isset($this->current_shard)) {
            if(!isset($this->generation_info['CURRENT'])) {
                $this->generation_info['CURRENT'] = 
                    $this->generation_info['ACTIVE'];
            }
            $current_index_shard_file = $this->dir_name."/index".
                $this->generation_info['CURRENT'];
                
            if(file_exists($current_index_shard_file)) {
                if(isset($this->generation_info['DISK_BASED']) &&
                    $this->generation_info['DISK_BASED'] == true) {
                    $this->current_shard =new IndexShard(
                        $current_index_shard_file,
                        $this->generation_info['CURRENT']*
                        $this->num_docs_per_generation, true);
                    $this->current_shard->getShardHeader();
                    $this->current_shard->read_only_from_disk = true;
                } else {
                    $this->current_shard = 
                        IndexShard::load($current_index_shard_file);
                }
            } else {
                $this->current_shard = new IndexShard($current_index_shard_file,
                    $this->generation_info['CURRENT'],
                    $this->num_docs_per_generation);
            }
        }
        return $this->current_shard;
     }

    /**
     * Sets the current shard to be the $i th shard in the index bundle.
     *
     * @param $i which shard to set the current shard to be
     * @param $disk_based whether to read the whole shard in before using or
     *      leave it on disk except for pages need and use memcache
     */
     public function setCurrentShard($i, $disk_based = false)
     {
        $this->generation_info['DISK_BASED'] = $disk_based;
        if(isset($this->generation_info['CURRENT']) && 
            ($i == $this->generation_info['CURRENT'] ||
            $i > $this->generation_info['ACTIVE'])) {
            return false;
        } else {
            $this->generation_info['CURRENT'] = $i;
            unset($this->current_shard);
            return true;
        }
     }

    /**
     * Gets the page out of the summaries WebArchiveBundle with the given 
     * offset and generation
     *
     * @param int $offset byte offset in partition of desired page
     * @param int $generation which generation WebArchive to look up in
     *      defaults to the same number as the current shard
     * @return array desired page
     */
    public function getPage($offset, $generation = -1)
    {
        if($generation == -1 ) {
            $generation = $this->generation_info['CURRENT'];
        }
        return $this->summaries->getPage($offset, $generation);
    }

    /**
     * Forces the current shard to be saved
     */
    public function forceSave()
    {
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
            $tmp = $this->dictionary->getWordInfo($word_key);
            if($tmp === false) {
                $words_array[$word_key] = 0;
            } else {
                $count = 0;
                foreach($tmp as $entry) {
                    $count += $entry[3];
                }
                $words_array[$word_key] = $count;
            }
        }

        uasort( $words_array, $comparison);
        
        return array_slice($words_array, 0, $num);
    }


    /**
     * Gets the description, count of summaries, and number of partitions of the
     * summaries store in the supplied directory. If the file arctype.txt
     * exist, this is view as a dummy index archive for the sole purpose of
     * allowing conversions of downloaded data such as arc files into
     * Yioop! format.
     *
     * @param string path to a directory containing a summaries WebArchiveBundle
     * @return array summary of the given archive
     */
    public static function getArchiveInfo($dir_name)
    {
        if(file_exists($dir_name."/arc_description.txt")) {
            $crawl = array();
            $info = array();
            $crawl['DESCRIPTION'] = substr(
                file_get_contents($dir_name."/arc_description.txt"), 0, 256);
            $crawl['ARCFILE'] = true;
            $info['VISITED_URLS_COUNT'] = 0;
            $info['COUNT'] = 0;
            $info['NUM_DOCS_PER_PARTITION'] = 0;
            $info['WRITE_PARTITION'] = 0;
            $info['DESCRIPTION'] = serialize($crawl);

            return $info;
        }
        return WebArchiveBundle::getArchiveInfo($dir_name."/summaries");
    }


}
?>
