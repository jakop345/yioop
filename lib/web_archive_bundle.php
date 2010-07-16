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
 * Load files we're dependent on if neccesary
 */
require_once 'web_archive.php';
require_once 'bloom_filter_file.php';
require_once 'bloom_filter_bundle.php';
require_once 'gzip_compressor.php';


 
/**
 * 
 * A web archive bundle is a collection of web archives which are managed 
 * together.It is useful to split data across several archive files rather than 
 * just store it in one, for both read efficiency and to keep filesizes from 
 * getting too big. In some places we are using 4 byte int's to store file 
 * offset which restricts the size of the files we can use for wbe archives.
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class WebArchiveBundle 
{

    var $dir_name;
    var $filter_size;
    var $partition = array();
    var $page_exists_filter_bundle;
    var $num_partitions;
    var $count;
    var $description;
    var $compressor;

    /**
     *
     */
    public function __construct($dir_name, $filter_size = -1, 
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
        $info = NULL;
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

        $info = array();

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
     *
     */
    public function addPages($key_field, $offset_field, &$pages)
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
     *
     */
    public function getPage($key, $offset)
    {
        $partition = 
            WebArchiveBundle::selectPartition($key, $this->num_partitions);

        return $this->getPageByPartition($partition, $offset);
    }

    /**
     *
     */
    public function getPageByPartition($partition, $offset, $file_handle = NULL)
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
     *
     */
    public function addPageFilter($key_field, &$page)
    {
        if($this->filter_size > 0) {
            $this->page_exists_filter_bundle->add($page[$key_field]);
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function addObjectsPartition($offset_field, $partition, 
        &$objects, $data = NULL, $callback = NULL, $return_flag = true)
    {
        $num_objects = count($objects);
        $this->addCount($num_objects);

        return $this->getPartition($partition)->addObjects(
            $offset_field, $objects, $data, $callback, $return_flag);
    }

    /**
     *
     */
    public function readPartitionInfoBlock($partition)
    {
        return $this->getPartition($partition)->readInfoBlock();
    }

    /**
     *
     */
    public function writePartitionInfoBlock($partition, &$data)
    {
        $this->getPartition($partition)->writeInfoBlock(NULL, $data);
    }

    /**
     *
     */
    public function differencePageKeysFilter($pages, $key_field)
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
     *
     */
    public function differencePagesFilter(&$page_array, $field_name = NULL)
    {
        $this->page_exists_filter_bundle->differenceFilter(
            $page_array, $field_name);
    }

    /**
     *
     */
    public function forceSave()
    {
        if($this->filter_size > 0) {
           $this->page_exists_filter_bundle->forceSave();
        }
    }

    /**
     *
     */
    public function getPartition($index, $fast_construct = true)
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
     *
     */
    function addCount($num)
    {
        $info = 
            unserialize(file_get_contents($this->dir_name."/description.txt"));
        $info['COUNT'] += $num;
        file_put_contents($this->dir_name."/description.txt", serialize($info));
    }

    /**
     *
     */
    public static function getArchiveInfo($dir_name)
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
     *
     */
    public static function selectPartition($value, $num_partitions)
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
