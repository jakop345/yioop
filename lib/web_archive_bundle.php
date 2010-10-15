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
 * A WebArchiveBundle is a collection of WebArchive's, so load definition of
 * web archive
 */
require_once 'web_archive.php';
/**
 * Bloom Filter used by BloomFilterBundle
 */
require_once 'bloom_filter_file.php';
/**
 * Used to check if a page already stored in the WebArchiveBundle
 */
require_once 'bloom_filter_bundle.php';
/**
 * Used to compress data stored in WebArchiveBundle
 */
require_once 'gzip_compressor.php';


 
/**
 * A web archive bundle is a collection of web archives which are managed 
 * together.It is useful to split data across several archive files rather than 
 * just store it in one, for both read efficiency and to keep filesizes from 
 * getting too big. In some places we are using 4 byte int's to store file 
 * offsets which restricts the size of the files we can use for wbe archives.
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class WebArchiveBundle 
{

    /**
     * Folder name to use for this WebArchiveBundle
     * @var string
     */
    var $dir_name;
    /**
     * Maximum allowed insert into each BloomFilterFile in the 
     * page_exists_filter_bundle
     * @var int
     */
    var $filter_size;
    /**
     * Used to contain the WebArchive paritions of the bundle
     * @var array
     */
    var $partition = array();
    /**
     * BloomFilterBundle used to keep track of which pages are already in
     * WebArchiveBundle
     * @var object
     */
    var $page_exists_filter_bundle;
    /**
     * Number of WebArchives in the WebArchiveBundle
     * @var int
     */
    var $num_partitions;
    /**
     * Total number of page objects stored by this WebArchiveBundle
     * @var int
     */
    var $count;
    /**
     * A short text name for this WebArchiveBundle
     * @var string
     */
    var $description;
    /**
     * How Compressor object used to compress/uncompress data stored in
     * the bundle
     * @var object
     */
    var $compressor;

    /**
     * Makes or initializes an existing WebArchiveBundle with the given 
     * characteristics
     *
     * @param string $dir_name folder name of the bundle
     * @param int $filter_size number of items that can be stored in
     *      a given BloomFilterFile in the $page_exists_filter_bundle
     * @param int $num_partitions number of WebArchive's in this bundle
     * @param string $description a short text name/description of this
     *      WebArchiveBundle
     * @param string $compressor the Compressor object used to 
     *      compress/uncompress data stored in the bundle
     */
    function __construct($dir_name, $filter_size = -1, 
        $num_partitions = NULL, $description = NULL, 
        $compressor = "GzipCompressor") 
    {
        //filter size = -1 used by web server to not get all partitions created
        
        $this->dir_name = $dir_name;
        $this->filter_size = $filter_size;
        $this->compressor = $compressor;

        $read_only_archive = false;
        if($num_partitions == NULL) {
            $read_only_archive = true;
        }

        if(!is_dir($this->dir_name)) {
            mkdir($this->dir_name);
        }

        //store/read archive description

        if(file_exists($dir_name."/description.txt")) {
            $info = unserialize(
                file_get_contents($this->dir_name."/description.txt"));
        }

        $this->num_partitions = $num_partitions;
        if(isset($info['NUM_PARTITIONS'])) {
            $this->num_partitions = $info['NUM_PARTITIONS'];
        }

        $this->count = 0;
        if(isset($info['COUNT'])) {
            $this->count = $info['COUNT'];
        }
        
        if(isset($info['DESCRIPTION']) ) {
            $this->description = $info['DESCRIPTION'];
        } else {
            $this->description = $description;
            if($this->description == NULL) {
                $this->description = "Archive created without a description";
            }
        }

        $info['DESCRIPTION'] = $this->description;
        $info['NUM_PARTITIONS'] = $this->num_partitions;
        $info['COUNT'] = $this->count;
        if(!$read_only_archive) {
            file_put_contents(
                $this->dir_name."/description.txt", serialize($info));
        }

        /* 
           filter bundle to check if a downloaded page should be put in archive 
           (for de-duplication)
         */
        if($this->filter_size > 0) {
            $this->page_exists_filter_bundle = 
                new BloomFilterBundle($dir_name."/PageExistsFilterBundle", 
                    $filter_size);
        }
    }

    /**
     * Add the array of $pages to the WebArchiveBundle pages being stored in
     * the partition according to the $key_field and the field used to store
     * the resulting offsets given by $offset_field.
     *
     * @param string $key_field field used to select partition
     * @param string $offset_field field used to record offsets after storing
     * @param array &$pages data to store
     * @return array $pages adjusted with offset field
     */
    function addPages($key_field, $offset_field, &$pages)
    {
        $partition_queue = array();
        for($i = 0; $i < $this->num_partitions; $i++) {
            $partition_queue[$i] = array();
        }

        $num_pages = count($pages);
        for($i = 0; $i < $num_pages; $i++) { 
            //we are doing this to preserve the order of the returned array
            $pages[$i]['TMP_INDEX'] = $i;
        }

        foreach($pages as $page) {
            if(isset($page[$key_field])) {
                $this->count++;

                $index = WebArchiveBundle::selectPartition(
                    $page[$key_field], $this->num_partitions);

                $partition_queue[$index][] =  $page;
            }
        }

        $pages_with_offsets = array();
        for($i = 0; $i < $this->num_partitions; $i++) {
            $pages_with_offsets = array_merge($pages_with_offsets, 
                $this->addObjectsPartition(
                    $offset_field, $i, $partition_queue[$i]));
        }

        foreach($pages_with_offsets as $off_page) {
            $pages[$off_page['TMP_INDEX']][$offset_field] = 
                $off_page[$offset_field];
            unset($pages[$off_page['TMP_INDEX']]['TMP_INDEX'] );
        }
        return $pages;
    }

    /**
     * Gets the page out of the WebArchiveBundle with the given key and offset
     *
     * The $key determines the partition WebArchive, the $offset give the
     * byte offset within that archive.
     * @param string $key hash to use to look up WebArchive partition
     * @param int $offset byte offset in partition of desired page
     * @return array desired page
     */
    function getPage($key, $offset)
    {
        $partition = 
            WebArchiveBundle::selectPartition($key, $this->num_partitions);

        return $this->getPageByPartition($partition, $offset);
    }

    /**
     * Gets a page using in WebArchive $partition using the provided byte
     * $offset and using existing $file_handle if possible.
     *
     * @param int $partition which WebArchive to look in
     * @param int $offset byte offset of page data
     * @param resource $file_handle file handle resource of $partition archive
     * @return array desired page
     */
    function getPageByPartition($partition, $offset, $file_handle = NULL)
    {
        $page_array = 
            $this->getPartition($partition)->getObjects(
                $offset, 1, true, $file_handle);

        if(isset($page_array[0][1])) {
            return $page_array[0][1];
        } else {
            return array();
        }
    }

    /**
     * Adds the given page to the page exists filter bundle
     *
     * @param string $key_field field of page with hash of page content
     * @param array &$page contents/summary of page
     * @return bool whether the add succeeded
     */
    function addPageFilter($key_field, &$page)
    {
        if($this->filter_size > 0) {
            $this->page_exists_filter_bundle->add($page[$key_field]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Adds a list of objects to a given WebArchive partition
     *
     * @param string $offset_field field used to store offsets after the
     *      addition
     * @param int $partition WebArchive index to store data into
     * @param array &$objects objects to store
     * @param array $data info header data to write
     * @param string $callback function name of function to call as each
     *      object is stored. Can be used to save offset into $data
     * @param bool $return_flag whether to return modified $objects or not
     * @return mixed adjusted objects or void
     */
    function addObjectsPartition($offset_field, $partition, 
        &$objects, $data = NULL, $callback = NULL, $return_flag = true)
    {
        $num_objects = count($objects);
        $this->addCount($num_objects);

        return $this->getPartition($partition)->addObjects(
            $offset_field, $objects, $data, $callback, $return_flag);
    }

    /**
     * Reads the info block of $partition WebArchive
     *
     * @param int $partition WebArchive to read from
     * @return array data in its info block
     */
    function readPartitionInfoBlock($partition)
    {
        return $this->getPartition($partition)->readInfoBlock();
    }

    /**
     * Write $data into the info block of the $partition WebArchive
     *
     * @param int $partition WebArchive to write into
     * @param array $data what to write
     */
    function writePartitionInfoBlock($partition, &$data)
    {
        $this->getPartition($partition)->writeInfoBlock(NULL, $data);
    }

    /**
     * Looks at the $key_field key of elements of pages and computes an array
     * consisting of $key_field values which are not in
     * the page_exists_filter_bundle
     *
     * @param array $pages set of page data to start from
     * @param string $key_field field to check against filter bundle
     * @return mixed false if filter empty; desired array otherwise
     */
    function differencePageKeysFilter($pages, $key_field)
    {
        if($this->filter_size > 0) {
            $page_array = array();
            
            foreach($pages as $page) {
                $page_array[] = & $page[$key_field];
            }
            
            $this->page_exists_filter_bundle->differenceFilter($page_array);
            return $page_array;
        } else {
            return false;
        }
    }

    /**
     * Looks at the field_name key of elements of page_array and removes any
     * of these which are in the page_exists_filter_bundle
     *
     * @param array &$page_array array to element remove elements from 
     * @param string $field_name field to check against filter bundle
     */
    function differencePagesFilter(&$page_array, $field_name = NULL)
    {
        $this->page_exists_filter_bundle->differenceFilter(
            $page_array, $field_name);
    }

    /**
     * Forces the data in the page exists filter bundle to be save to disk
     */
    function forceSave()
    {
        if($this->filter_size > 0) {
           $this->page_exists_filter_bundle->forceSave();
        }
    }

    /**
     * Gets an object encapsulating the $index the WebArchive partition in
     * this bundle.
     *
     * @param int $index the number of the partition within this bundle to
     *      return
     * @param bool $fast_construct should the constructor of the WebArchive
     *      avoid reading in its info block.
     * @return object the WebArchive file which was requested
     */
    function getPartition($index, $fast_construct = true)
    {
        if(!isset($this->partition[$index])) { 
            //this might not have been open yet
            $create_flag = false;
            if(!file_exists($this->dir_name."/web_archive_".$index)) {
                $create_flag = true;
            }
            $this->partition[$index] = 
                new WebArchive($this->dir_name."/web_archive_".$index, 
                    new $this->compressor(), $fast_construct);
            if($create_flag) {
                chmod($this->dir_name."/web_archive_".$index, 0777);
            }
        }
        
        return $this->partition[$index];
    }

    /**
     * Creates a new counter to be maintain in the description.txt
     * file if the counter doesn't exist, leaves unchanged otherwise
     *
     * @param string $field field of info struct to add a counter for
     */
    function initCountIfNotExists($field = "COUNT")
    {
        $info = 
            unserialize(file_get_contents($this->dir_name."/description.txt"));
        if(!isset($info[$field])) {
            $info[$field] = 0;
        }
        file_put_contents($this->dir_name."/description.txt", serialize($info));
    }

    /**
     * Updates the description file with the current count for the number of
     * items in the WebArchiveBundle. If the $field item is used counts of
     * additional properties (visited urls say versus total urls) can be 
     * maintained.
     *
     * @param int $num number of items to add to current count
     * @param string $field field of info struct to add to the count of
     */
    function addCount($num, $field = "COUNT")
    {
        $info = 
            unserialize(file_get_contents($this->dir_name."/description.txt"));
        $info[$field] += $num;
        file_put_contents($this->dir_name."/description.txt", serialize($info));
    }

    /**
     * Gets information about a WebArchiveBundle out of its description.txt 
     * file
     *
     * @param string $dir_name folder name of the WebArchiveBundle to get info
     *  for
     * @return array containing the name (description) of the WebArchiveBundle,
     *      the number of items stored in it, and the number of WebArchive
     *      file partitions it uses.
     */
    static function getArchiveInfo($dir_name)
    {
        if(!is_dir($dir_name) || !file_exists($dir_name."/description.txt")) {
            $info = array();
            $info['DESCRIPTION'] = 
                "Archive does not exist OR Archive description file not found";
            $info['COUNT'] = 0;
            $info['NUM_PARTITIONS'] = 0;
            return $info;
        }

        $info = unserialize(file_get_contents($dir_name."/description.txt"));

        return $info;

    }

    /**
     * Hashes $value to a WebArchive partition  it should be read/written to, 
     * if a bundle has $num_partitions partitions.
     *
     * @param string $value item to hash
     * @param int $num_partitions number of partitions
     * @return int which partition $value should be written to/read from
     */
    static function selectPartition($value, $num_partitions)
    {

        $hash = substr(md5($value, true), 0, 4);
        $int_array = unpack("N", $hash);
        $seed = $int_array[1];

        mt_srand($seed);
        $index = mt_rand(0, $num_partitions - 1);

        return $index;

    }
}
?>
