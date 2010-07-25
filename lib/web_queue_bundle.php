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
 * We use a variety of bloom filters for handling robots.txt data
 */
require_once 'bloom_filter_file.php';
/**
 * Data on which urls we've already crawled is stored in a bloom filter bundle
 */
require_once 'bloom_filter_bundle.php';
/**
 * Priority queue is used to store a 8 byte ids of urls to crawl next
 */
require_once 'priority_queue.php';
/**
 * Hash table is used to store for each id in the priority queue an offset into
 * a web archive for that urls id actual complete url
 */
require_once 'hash_table.php';
/**
 * Urls are stored in a web archive using a filter that does no compression
 */
require_once 'non_compressor.php';
/**
 *  Used to store to crawl urls
 */
require_once 'web_archive.php';
/**
 *  Used for the crawlHash function
 */
require_once 'utility.php'; 

/**
 * Encapsulates the data structures needed to have a queue of urls to crawl 
 * next
 * 
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 
class WebQueueBundle implements Notifier
{

    /**
     * The folder name of this WebQueueBundle
     * @var string
     */
    var $dir_name;
    /**
     *
     * @var int
     */
    var $filter_size;
    /**
     *
     * @var int
     */
    var $num_urls_ram;
    /**
     *
     * @var int
     */
    var $min_or_max; 
    /**
     *
     * @var object
     */
    var $to_crawl_queue;
    /**
     *
     * @var object
     */
    var $to_crawl_table;
    /**
     *
     * @var int
     */
    var $hash_rebuild_count;
    /**
     *
     * @var int
     */
    var $max_hash_ops_before_rebuild;
    /**
     *
     * @var object
     */
    var $to_crawl_archive;

    var $url_exists_filter_bundle;
    /**
     *
     * @var object
     */
    var $got_robottxt_filter;
    /**
     *
     * @var object
     */
    var $dissallowed_robot_filter;
    /**
     *
     * @var object
     */
    var $crawl_delay_filter;

    /**
     *
     */
    const max_url_archive_offset = 1000000000;

    /**
     *
     */
    function __construct($dir_name, 
        $filter_size, $num_urls_ram, $min_or_max) 
    {
        $this->dir_name = $dir_name;
        $this->filter_size = $filter_size;
        $this->num_urls_ram = $num_urls_ram;
        $this->min_or_max = $min_or_max;

        if(!file_exists($this->dir_name)) {
            mkdir($this->dir_name);
        }

        /*
            if we are resuming a crawl we discard the old priority queue and 
            associated hash table and archive new queue data will be read in 
            from any existing schedule
        */
        // set up the priority queue... stores (hash(url), weight) pairs.
        $this->to_crawl_queue = new PriorityQueue($dir_name."/queue.dat", 
            $num_urls_ram, 8, $min_or_max, $this);

        /* set up the hash table... stores (hash(url), offset into url archive, i
          ndex in priority queue) triples.
         */

        /*to ensure we can always insert into table, because of how deletions 
          work we will periodically want to
          rebuild our table we will also want to give a little more than the 
          usual twice the number we want to insert slack
        */
        $this->to_crawl_table = $this->constructHashTable(
            $dir_name."/hash_table.dat", 4*$num_urls_ram);

        /* set up url archive, used to store the full text of the urls which 
           are on the priority queue
         */
        if(file_exists($dir_name."/url_archive")) {
            unlink($dir_name."/url_archive");
        }
        $this->to_crawl_archive = new WebArchive(
            $dir_name."/url_archive", new NonCompressor());

        //filter bundle to check if we have already visited a URL
        $this->url_exists_filter_bundle = new BloomFilterBundle(
            $dir_name."/UrlExistsFilterBundle", $filter_size);

        //timestamp for robot filters (so can delete if get too old)
        if(!file_exists($dir_name."/robot_timestamp.txt")) {
            file_put_contents($dir_name."/robot_timestamp.txt", time());
        }

        //filter to check if we have already have a copy of a robot.txt file
        if(file_exists($dir_name."/got_robottxt.ftr")) {
            $this->got_robottxt_filter = BloomFilterFile::load(
                $dir_name."/got_robottxt.ftr");

        } else {
            $this->got_robottxt_filter = new BloomFilterFile(
                $dir_name."/got_robottxt.ftr", $filter_size);
        }

        //filter with disallowed robots.txt paths
        if(file_exists($dir_name."/dissallowed_robot.ftr")) {
            $this->dissallowed_robot_filter = 
                BloomFilterFile::load($dir_name."/dissallowed_robot.ftr");

        } else {
            $this->dissallowed_robot_filter = 
                new BloomFilterFile(
                    $dir_name."/dissallowed_robot.ftr", $filter_size);
        }
      
        //filter to check for and determine crawl delay
        if(file_exists($dir_name."/crawl_delay.ftr")) {
            $this->crawl_delay_filter = 
                BloomFilterFile::load($dir_name."/crawl_delay.ftr");

        } else {
            $this->crawl_delay_filter = 
                new BloomFilterFile($dir_name."/crawl_delay.ftr", $filter_size);
        }
    }

    /**
     *
     */
    function addUrlsQueue(&$url_pairs)
    {
        $add_urls = array();
        $count = count($url_pairs);
        if( $count < 1) return;
        for($i = 0; $i < $count; $i++) {
            $add_urls[$i][0] = & $url_pairs[$i][0];
        }
        
        $objects = $this->to_crawl_archive->addObjects("offset", $add_urls);

        for($i = 0; $i < $count; $i++) {
            $url = & $url_pairs[$i][0];
            $weight = $url_pairs[$i][1];
            if(isset($objects[$i]['offset'])) {
                $offset = $objects[$i]['offset'];

                $data = pack('N', $offset).pack("N", 0);

                if($this->insertHashTable(crawlHash($url, true), $data)) {
                    /* 
                       we will change 0 to priority queue index in the 
                       notify callback
                     */
                    $loc = $this->to_crawl_queue->insert(
                        crawlHash($url, true), $weight);
                } else {
                    echo "Error inserting $url into hash table !!";
                }
            } else {
                echo "Error inserting $url into web archive !!";
            }
        }
        
        if(isset($offset) && $offset > self::max_url_archive_offset) {
             $this->rebuildUrlTable();
        }
    }

    /**
     *
     */
    function containsUrlQueue(&$url)
    {
        $hash_url = crawlHash($url, true);
        $lookup_url = $this->lookupHashTable($hash_url);
        return ($lookup_url == false) ? false : true;
    }

    /**
     *
     */
    function adjustQueueWeight(&$url, $delta)
    {
        $hash_url = crawlHash($url, true);
        $data = $this->lookupHashTable($hash_url);
        if($data !== false)
        {
            $queue_index_array = unpack('N', substr($data, 4 , 4));
            $queue_index = $queue_index_array[1];
    
            $this->to_crawl_queue->adjustWeight($queue_index, $delta);
        } else {
          echo "Can't adjust weight. Not in queue $url\n";
        }
    }

    /**
     *
     */
    function removeQueue($url)
    {
        $hash_url = crawlHash($url, true);
        $data = $this->lookupHashTable($hash_url);

        if(!$data) {
            echo "Not in queue $url\n";
            return;
        }

        $queue_index_array = unpack('N', substr($data, 4 , 4));
        $queue_index = $queue_index_array[1];

        $this->to_crawl_queue->poll($queue_index);

        $this->deleteHashTable($hash_url);

    }

    /**
     *
     */
    function peekQueue($i = 1, $fh = NULL)
    {
        $tmp = $this->to_crawl_queue->peek($i);
        if(!$tmp) {
            echo "web queue peek error on index $i\n";
            return false;
        }

        list($hash_url, $weight) = $tmp;

        $data = $this->lookupHashTable($hash_url);
        if($data === false ) {
            echo "web queue hash lookup error $hash_url \n";
            return false;
        }

        $offset_array = unpack('N', substr($data, 0 , 4));
        $offset = $offset_array[1];
        $url_obj = $this->to_crawl_archive->getObjects($offset, 1, true, $fh);

        if(isset($url_obj[0][1][0])) {
            $url = $url_obj[0][1][0];
        } else {
            $url = "LOOKUP ERROR";
        }
        return array($url, $weight);
    }

    /**
     *
     */
    function printContents()
    {
        $count = $this->to_crawl_queue->count;

        for($i = 1; $i <= $count; $i++) {
            list($url, $weight) = $this->peekQueue($i);
            print "$i URL: $url WEIGHT:$weight\n";
        }
    }

    function getContents()
    {
        $count = $this->to_crawl_queue->count;
        $contents = array();
        for($i = 1; $i <= $count; $i++) {
            $contents[] = $this->peekQueue($i);
        }
        return $contents;
    }

    /**
     *
     */
    function normalize($new_total = NUM_URLS_QUEUE_RAM)
    {
        $this->to_crawl_queue->normalize();
    }

    //Filter and Filter Bundle Methods   

    /**
     *
     */
    function openUrlArchive($mode = "r")
    {
        return $this->to_crawl_archive->open($mode);
    }

    /**
     *
     */
    function closeUrlArchive($fh)
    {
        $this->to_crawl_archive->close($fh);
    }

    /**
     *
     */
    function addSeenUrlFilter($url)
    {
        $this->url_exists_filter_bundle->add($url);
    }

    /**
     *
     */
    function differenceSeenUrls(&$url_array, $field_name = NULL)
    {
        $this->url_exists_filter_bundle->differenceFilter(
            $url_array, $field_name);
    }

    /**
     *
     */
    function addGotRobotTxtFilter($host)
    {
        $this->got_robottxt_filter->add($host);
    }

    /**
     *
     */
    function containsGotRobotTxt($host)
    {
        return $this->got_robottxt_filter->contains($host);
    }

    /**
     *
     */
    function addDisallowedRobotFilter($host)
    {
        $this->dissallowed_robot_filter->add($host);
    }

    /**
     *
     */
    function containsDisallowedRobot($host_path)
    {
        return $this->dissallowed_robot_filter->contains($host_path);
    }

    /**
     *
     */
    function getRobotTxtAge()
    {

        $creation_time = intval(
            file_get_contents($this->dir_name."/robot_timestamp.txt"));

        return (time() - $creation_time);
    }

    /**
     *
     */
    function setCrawlDelay($host, $value)
    {
        $this->crawl_delay_filter->add("-1".$host); 
            //used to say a crawl delay has been set

        for($i = 0; $i < 8; $i++) {
            if(($value & 1) == 1) {
                $this->crawl_delay_filter->add("$i".$host);
            }
            $value = $value >> 1;
        }
    }

    /**
     *
     */
    function getCrawlDelay($host)
    {
        if(!$this->crawl_delay_filter->contains("-1".$host)) {
            return -1;
        }

        $value = 0;
        for($i = 0; $i < 8; $i++) {
            if($this->crawl_delay_filter->contains("$i".$host)) {
                $value += (2 << $i);
            }
        }

        return $value;
    }

    /**
     *
     */
    function constructHashTable($name, $num_values)
    {
        $this->hash_rebuild_count = 0;
        $this->max_hash_ops_before_rebuild = floor($num_values/4);
        return new HashTable($name, $num_values, 8, 8);
    }

    /**
     *
     */
    function lookupHashTable($key)
    {
        return $this->to_crawl_table->lookup($key);
    }

    /**
     *
     */
    function deleteHashTable($value)
    {
        $this->to_crawl_table->delete($value);
        $this->hash_rebuild_count++;
        if($this->hash_rebuild_count > $this->max_hash_ops_before_rebuild) {
            $this->rebuildHashTable();
        }
    }

    /**
     *
     */
    function insertHashTable($key, $value)
    {
        $this->hash_rebuild_count++;
        if($this->hash_rebuild_count > $this->max_hash_ops_before_rebuild) {
            $this->rebuildHashTable();
        }
        return $this->to_crawl_table->insert($key, $value);
    }

    /**
     *
     */
    function rebuildHashTable()
    {
        crawlLog("Rebuilding Hash table");
        $num_values = $this->to_crawl_table->num_values;
        $tmp_table = $this->constructHashTable(
            $this->dir_name."/tmp_table.dat", $num_values);
        $null = $this->to_crawl_table->null;
        $deleted = $this->to_crawl_table->deleted;
        
        for($i = 0; $i < $num_values; $i++) {
            list($key, $value) = $this->to_crawl_table->getEntry($i);
            if(strcmp($key, $null) != 0 
                && strcmp($key, $deleted) != 0) {
                $tmp_table->insert($key, $value);
            }
        }
        
        $this->to_crawl_table = NULL;
        gc_collect_cycles();
        if(file_exists($this->dir_name."/hash_table.dat")) {
            unlink($this->dir_name."/hash_table.dat");
            if(file_exists($this->dir_name."/tmp_table.dat")) {
                rename($this->dir_name."/tmp_table.dat", 
                    $this->dir_name."/hash_table.dat");
            }
        }
        $tmp_table->filename = $this->dir_name."/hash_table.dat";
        $this->to_crawl_table = $tmp_table; 
    }
    
   /**
    * Since offsets are integers, even if the queue is kept relatively small, 
    * periodically we will need to rebuild the archive for storing urls.
    */
    function rebuildUrlTable()
    {
        crawlLog("Rebuilding URL table");
        $dir_name = $this->dir_name;

        $count = $this->to_crawl_queue->count;
        $tmp_archive = 
            new WebArchive($dir_name."/tmp_archive", new NonCompressor());
        
        for($i = 1; $i <= $count; $i++) {

            list($url, $weight) = $this->peekQueue($i);
            $url_container = array(array($url));
            $objects = $tmp_archive->addObjects("offset", $url_container);
            if(isset($objects[0]['offset'])) {
                $offset = $objects[0]['offset'];
            } else {
                echo "Error inserting $url into rebuild url archive file \n";
                continue;
            }
            
            $hash_url = crawlHash($url, true);
            $data = $this->lookupHashTable($hash_url);
            $queue_index_array = unpack('N', substr($data, 4 , 4));
            $queue_index = $queue_index_array[1];
            $data = pack('N', $offset).pack("N", $queue_index);

            $this->insertHashTable(crawlHash($url, true), $data);
        }
        
        $this->to_crawl_archive = NULL;
        gc_collect_cycles();
        unlink($dir_name."/url_archive");
        rename($dir_name."/tmp_archive", $dir_name."/url_archive"); 
        $tmp_archive->filename = $dir_name."/url_archive";
        $this->to_crawl_archive =  $tmp_archive; 

    }

    /**
     *
     */
    function emptyRobotFilters()
    {
        unlink($this->dir_name."/got_robottxt.ftr");  
        unlink($this->dir_name."/dissallowed_robot.ftr");
        unlink($this->dir_name."/crawl_delay.ftr");
        $this->crawl_delay_table = array();

        file_put_contents($this->dir_name."/robot_timestamp.txt", time());

        $this->got_robottxt_filter = NULL;
        $this->dissallowed_robot_filter = NULL;
        $this->crawl_delay_filter = NULL;
        gc_collect_cycles();

        $this->got_robottxt_filter = 
            new BloomFilterFile(
                $this->dir_name."/got_robottxt.ftr", $this->filter_size);
        $this->dissallowed_robot_filter = 
            new BloomFilterFile(
                $this->dir_name."/dissallowed_robot.ftr", $this->filter_size);
        $this->crawl_delay_filter = 
            new BloomFilterFile(
                $this->dir_name."/crawl_delay.ftr", $this->filter_size);
    }

    /**
     *
     */
    function notify($index, $data)
    {
        $hash_url = $data[0];
        $value = $this->lookupHashTable($hash_url);
        if($value !== false) {
            $packed_offset = substr($value, 0 , 4);
            $data = $packed_offset.pack("N", $index);

            $this->insertHashTable($hash_url, $data);
        } else {
            echo "NOTIFY LOOKUP FAILED. INDEX WAS $index. DATA WAS ".
                bin2hex($data[0])."\n";
          
        }
    }

}
?>
