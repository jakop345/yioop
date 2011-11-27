<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2012
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
require_once BASE_DIR.'/lib/compressors/non_compressor.php';

/**
 *  Used to store to crawl urls
 */
require_once 'web_archive.php';
/**
 *  Used for the crawlHash function
 */
require_once 'utility.php'; 

/**
 * Encapsulates the data structures needed to have a queue of to crawl urls
 * 
 * <pre>
 * (hash of url, weights) are stored in a PriorityQueue, 
 * (hash of url, index in PriorityQueue, offset of url in WebArchive) is stored
 * in a HashTable
 * urls are stored in a WebArchive in an uncompressed format
 * </pre>
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
     * Number items that can be stored in a partition of the page exists filter
     * bundle 
     * @var int
     */
    var $filter_size;
    /**
     * number of entries the priority queue used by this web queue bundle
     * can store
     * @var int
     */
    var $num_urls_ram;
    /**
     * whether polling the first element of the priority queue returns the
     * smallest or largest weighted element. This is set to a constant specified
     * in PriorityQueue
     * @var int
     */
    var $min_or_max; 
    /**
     * the PriorityQueue used by this WebQueueBundle
     * @var object
     */
    var $to_crawl_queue;
    /**
     * the HashTable used by this WebQueueBundle
     * @var object
     */
    var $to_crawl_table;
    /**
     * Current count of the number of non-read operation performed on the
     * WebQueueBundles's hash table since the last time it was rebuilt.
     * @var int
     */
    var $hash_rebuild_count;
    /**
     * Number of non-read operations on the hash table before it needs to be
     * rebuilt.
     * @var int
     */
    var $max_hash_ops_before_rebuild;
    /**
     * WebArchive used to store urls that are to be crawled
     * @var object
     */
    var $to_crawl_archive;

    /**
     * BloomFilter used to keep track of which urls we've already seen
     * @var object
     */
    var $url_exists_filter_bundle;
    /**
     * BloomFilter used to store which hosts whose robots.txt file we 
     * have already download
     * @var object
     */
    var $got_robottxt_filter;
    /**
     * BloomFilter used to store dissallowed to crawl host paths
     * @var object
     */
    var $dissallowed_robot_filter;
    /**
     * BloomFilter used to keep track of crawl delay in seconds for a given 
     * host
     * @var object
     */
    var $crawl_delay_filter;

    /**
     * The largest offset for the url WebArchive before we rebuild it.
     * Entries are never deleted from the url WebArchive even if they are
     * deleted from the priority queue. So when we pass this value we
     * make a new WebArchive containing only those urls which are still in
     * the queue.
     */
    const max_url_archive_offset = 1000000000;

    /**
     * Makes a WebQueueBundle with the provided parameters
     *
     * @param string $dir_name folder name used by this WebQueueBundle
     * @param int $filter_size size of each partition in the page exists
     *      BloomFilterBundle
     * @param int $num_urls_ram number of entries in ram for the priority queue
     * @param string $min_or_max when the priority queue maintain the heap
     *      property with respect to the least or the largest weight
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
            $num_urls_ram, 8, $min_or_max, $this, 0);

        /* set up the hash table... stores (hash(url), offset into url archive, 
          index in priority queue) triples.
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
        $url_archive_name = $dir_name."/url_archive". 
            NonCompressor::fileExtension();
        if(file_exists($url_archive_name)) {
            unlink($url_archive_name);
        }
        $this->to_crawl_archive = new WebArchive(
            $url_archive_name, new NonCompressor());

        //timestamp for robot filters (so can delete if get too old)
        if(!file_exists($dir_name."/url_timestamp.txt")) {
            file_put_contents($dir_name."/url_timestamp.txt", time());
        }

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
     * Adds an array of (url, weight) pairs to the WebQueueBundle.
     *
     * @param array $url_pairs a list of pairs to add
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

                $data = packInt($offset).packInt(0);

                if($this->insertHashTable(crawlHash($url, true), $data)) {
                    /* 
                       we will change 0 to priority queue index in the 
                       notify callback
                     */
                    $loc = $this->to_crawl_queue->insert(
                        crawlHash($url, true), $weight);
                } else {
                    crawlLog("Error inserting $url into hash table !!");
                }
            } else {
                crawlLog("Error inserting $url into web archive !!");
            }
        }
        
        if(isset($offset) && $offset > self::max_url_archive_offset) {
             $this->rebuildUrlTable();
        }
    }

    /**
     * Check is the url queue already contains the given url
     * @param string $url what to check
     * @return bool whether it is contained in the queue yet or not
     */
    function containsUrlQueue(&$url)
    {
        $hash_url = crawlHash($url, true);
        $lookup_url = $this->lookupHashTable($hash_url);
        return ($lookup_url == false) ? false : true;
    }

    /**
     * Adjusts the weight of the given url in the priority queue by amount delta
     *
     * In a page importance crawl. a given web page casts its votes on who
     * to crawl next by splitting its crawl money amongst its child links.
     * This entails a mechanism for adusting weights of elements in the 
     * priority queue periodically is necessary. This function is used to
     * solve this problem.
     *
     * @param string $url url whose weight in queue we want to adjust
     * @param float $delta change in weight (usually positive).
     */
    function adjustQueueWeight(&$url, $delta)
    {
        $hash_url = crawlHash($url, true);
        $data = $this->lookupHashTable($hash_url);
        if($data !== false)
        {
            $queue_index = unpackInt(substr($data, 4 , 4));

            $this->to_crawl_queue->adjustWeight($queue_index, $delta);
        } else {
          crawlLog("Can't adjust weight. Not in queue $url");
        }
    }

    /**
     * Removes a url from the priority queue.
     *
     * This method would typical be called during a crawl after the given
     * url is scheduled to be crawled. It only deletes the item from
     * the bundles priority queue and hash table -- not from the web archive.
     *
     * @param string $url the url or hash of url to delete
     * @param bool $isHash flag to say whether or not is the hash of a url
     */
    function removeQueue($url, $isHash = false)
    {
        if($isHash == true) {
            $hash_url = $url;
        } else {
            $hash_url = crawlHash($url, true);
        }
        $data = $this->lookupHashTable($hash_url);

        if(!$data) {
            crawlLog("Not in queue $url");
            return;
        }

        $queue_index = unpackInt(substr($data, 4 , 4));

        $this->to_crawl_queue->poll($queue_index);

        $this->deleteHashTable($hash_url);

    }

    /**
     * Gets the url and weight of the ith entry in the priority queue
     * @param int $i entry to look up
     * @param resource $fh a file handle to the WebArchive for urls
     * @return mixed false on error, otherwise the ordered pair in an array
     */
    function peekQueue($i = 1, $fh = NULL)
    {
        $tmp = $this->to_crawl_queue->peek($i);
        if(!$tmp) {
            crawlLog("web queue peek error on index $i");
            return false;
        }

        list($hash_url, $weight) = $tmp;

        $data = $this->lookupHashTable($hash_url);
        if($data === false ) {
            crawlLog("web queue hash lookup error $hash_url");
            return false;
        }

        $offset = unpackInt(substr($data, 0 , 4));

        $url_obj = $this->to_crawl_archive->getObjects($offset, 1, true, $fh);


        if(isset($url_obj[0][1][0])) {
            $url = $url_obj[0][1][0];
        } else {
            $url = "LOOKUP ERROR";
        }
        return array($url, $weight);
    }

    /**
     * Pretty prints the contents of the queue bundle in order
     */
    function printContents()
    {
        $count = $this->to_crawl_queue->count;

        for($i = 1; $i <= $count; $i++) {
            list($url, $weight) = $this->peekQueue($i);
            print "$i URL: $url WEIGHT:$weight\n";
        }
    }

    /**
     * Gets the contents of the queue bundle as an array of ordered url,weight
     * pairs
     * @return array a list of ordered url, weight pairs
     */
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
     * Makes the weight sum of the to-crawl priority queue sum to $new_total
     * @param int $new_total amount weights should sum to. All weights will be
     *      scaled by the same factor.
     */
    function normalize($new_total = NUM_URLS_QUEUE_RAM)
    {
        $this->to_crawl_queue->normalize();
    }

    //Filter and Filter Bundle Methods   

    /**
     * Opens the url WebArchive associated with this queue bundle in the
     * given read/write mode
     * @param string $mode the read/write mode to open the archive with
     * @return resource a file handle to the WebArchive file
     */
    function openUrlArchive($mode = "r")
    {
        return $this->to_crawl_archive->open($mode);
    }

    /**
     * Closes a file handle to the url WebArchive
     * @param resource $fh a valid handle to the url WebArchive file
     */
    function closeUrlArchive($fh)
    {
        $this->to_crawl_archive->close($fh);
    }

    /**
     * Adds the supplied url to the url_exists_filter_bundle
     * @param string $url url to add
     */
    function addSeenUrlFilter($url)
    {
        $this->url_exists_filter_bundle->add($url);
    }

    /**
     * Removes all url objects from $url_array which have been seen
     * @param array &$url_array objects to check if have been seen
     * @param array $field_names an array of components of a url_array element 
     * which 
     *      contains a url to check if seen
     */
    function differenceSeenUrls(&$url_array, $field_names = NULL)
    {
        $this->url_exists_filter_bundle->differenceFilter(
            $url_array, $field_names);
    }

    /**
     * Adds the supplied $host to the got_robottxt_filter
     * @param string $host url to add
     */
    function addGotRobotTxtFilter($host)
    {
        $this->got_robottxt_filter->add($host);
    }

    /**
     * Checks if we have a fresh copy of robots.txt info for $host
     * @param string $host url to check
     * @return bool whether we do or not
     */
    function containsGotRobotTxt($host)
    {
        return $this->got_robottxt_filter->contains($host);
    }

    /**
     * Adds a new entry to the disallowed robot host path Bloom filter
     * @param string $host_path the path on host that is excluded. For example
     * http://somewhere.com/bob disallows bob on somewhere.com
     */
    function addDisallowedRobotFilter($host_path)
    {
        $this->dissallowed_robot_filter->add($host_path);
    }

    /**
     * Checks if the given $host_path is disallowed by the host's
     * robots.txt info.
     * @param string $host_path host path to check
     * @return bool whether it was disallowed or nots
     */
    function containsDisallowedRobot($host_path)
    {
        return $this->dissallowed_robot_filter->contains($host_path);
    }

    /**
     * Gets the timestamp of the oldest robot data still stored in
     * the queue bundle
     * @return int a Unix timestamp
     */
    function getRobotTxtAge()
    {

        $creation_time = intval(
            file_get_contents($this->dir_name."/robot_timestamp.txt"));

        return (time() - $creation_time);
    }

    /**
     * Gets the timestamp of the oldest url filter data still stored in
     * the queue bundle
     * @return int a Unix timestamp
     */
    function getUrlFilterAge()
    {

        $creation_time = intval(
            file_get_contents($this->dir_name."/url_timestamp.txt"));

        return (time() - $creation_time);
    }

    /**
     * Sets the Crawl-delay of $host to passes $value in seconds
     *
     * @param string $host a host to set the Crawl-delay for
     * @param int $value a delay in seconds up to 255
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
     * Gets the Crawl-delay of $host from the crawl delay bloom filter
     *
     * @param string $host site to check for a Crawl-delay
     * @return int the crawl-delay in seconds or -1 if $host has no delay
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
     * Mainly, a Factory style wrapper around the HashTable's constructor.
     * However, this function also sets up a rebuild frequency. It is used
     * as part of the process of keeping the to crawl table from having too
     * many entries
     *
     * @param string $name filename to store the hash table persistently
     * @param int $num_values size of HashTable's arraya
     * @return object the newly built hash table
     * @see rebuildHashTable()
     */
    function constructHashTable($name, $num_values)
    {
        $this->hash_rebuild_count = 0;
        $this->max_hash_ops_before_rebuild = floor($num_values/4);
        return new HashTable($name, $num_values, 8, 8);
    }

    /**
     * Looks up $key in the to-crawl hash table
     *
     * @param string $key the things to look up
     * @return mixed would be string if the value is being returned, 
     *      otherwise, false if the key is not found 
     */
    function lookupHashTable($key)
    {
        return $this->to_crawl_table->lookup($key);
    }

    /**
     * Removes an entries from the to crawl hash table
     * @param string $key usually a hash of a url
     */
    function deleteHashTable($key)
    {
        $this->to_crawl_table->delete($key);
        $this->hash_rebuild_count++;
        if($this->hash_rebuild_count > $this->max_hash_ops_before_rebuild) {
            $this->rebuildHashTable();
        }
    }

    /**
     * Inserts the $key, $value pair into this web queue's to crawl table
     *
     * @param string $key intended to be a hash of a url
     * @param string $value intended to be offset into a webarchive for urls
     *      together with an index into the priority queue
     * @return bool whether the insert was a success or not
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
     * Makes a new HashTable without deleted rows
     *
     * The hash table in Yioop is implemented using open addressing. i.e.,
     * We store key value pair in the table itself and if there is a collision
     * we look for the next available slot. Two codes are use to indicate
     * space available in the table. One to indicate empty never used, the
     * other used to indicate empty but previously used and deleted. The reason
     * you need two codes is to ensure that if somebody inserted an item B,
     * it hashes to the same value as A and we move to the next empty slot,
     * to store B, then if we delete A we should still be able to lookup B.
     * The problem is as the table gets reused a lot, it tends to fill up
     * with a lot of deleted entries making lookup times get more and more
     * linear in the hash table size. By rebuilding the table we mitigate
     * against this problem. By choosing the rebuild frequency appropriately,
     * the amortized cost of this operation is only O(1).
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
        $tmp_archive_name = $dir_name."/tmp_archive" .
            NonCompressor::fileExtension();
        $url_archive_name = $dir_name."/url_archive" .
            NonCompressor::fileExtension();
        $tmp_archive = 
            new WebArchive($tmp_archive_name, new NonCompressor());
        
        for($i = 1; $i <= $count; $i++) {

            list($url, $weight) = $this->peekQueue($i);
            $url_container = array(array($url));
            $objects = $tmp_archive->addObjects("offset", $url_container);
            if(isset($objects[0]['offset'])) {
                $offset = $objects[0]['offset'];
            } else {
                crawlLog("Error inserting $url into rebuild url archive file");
                continue;
            }
            
            $hash_url = crawlHash($url, true);
            $data = $this->lookupHashTable($hash_url);
            $queue_index = unpackInt(substr($data, 4 , 4));
            $data = packInt($offset).packInt($queue_index);

            $this->insertHashTable(crawlHash($url, true), $data);
        }
        
        $this->to_crawl_archive = NULL;
        gc_collect_cycles();
        unlink($url_archive_name);
        rename($tmp_archive_name, $url_archive_name); 
        $tmp_archive->filename = $url_archive_name;
        $this->to_crawl_archive =  $tmp_archive; 

    }

    /**
     * Delete the Bloom filters used to store robots.txt file info.
     * This is called roughly once a days so that robots files will be
     * reloaded and so the policies used won't be too old.
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
     * Empty the crawled url filter for this web queue bundle; resets the
     * the timestamp of the last time this filter was emptied.
     */
    function emptyUrlFilter()
    {
        file_put_contents($this->dir_name."/url_timestamp.txt", time());

        $this->url_exists_filter_bundle->reset();
    }

    /**
     * Callback which is called when an item in the priority queue changes 
     * position. The position is updated in the hash table.
     * The priority queue stores (hash of url, weight). The hash table
     * stores (hash of url, web_archive offset to url, index priority queue).
     *
     * @param int $index new index in priority queue
     * @param array $data (hash url, weight)
     */
    function notify($index, $data)
    {
        $hash_url = $data[0];
        $value = $this->lookupHashTable($hash_url);
        if($value !== false) {
            $packed_offset = substr($value, 0 , 4);
            $data = $packed_offset.packInt($index);

            $this->insertHashTable($hash_url, $data);
        } else {
            crawlLog("NOTIFY LOOKUP FAILED. INDEX WAS $index. DATA WAS ".
                bin2hex($data[0]));
          
        }
    }

}
?>
