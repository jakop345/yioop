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
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** 
 *Loads base class for iterating
 */
require_once BASE_DIR.
    '/lib/archive_bundle_iterators/archive_bundle_iterator.php';

/**
 * Used to iterate through the records of a collection of arc files stored in
 * a WebArchiveBundle folder. Arc is the file format of the Internet Archive 
 * http://www.archive.org/web/researcher/ArcFileFormat.php. Iteration would be 
 * for the purpose making an index of these records
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
class ArcArchiveBundleIterator implements CrawlConstants
{
    /**
     * The number of arc files in this arc archive bundle
     *  @var int
     */
    var $num_partitions;

    /**
     *  Counting in glob order for this arc archive bundle directory, the 
     *  current active file number of the arc file being process.
     *
     *  @var int
     */
    var $current_partition_num;
    /**
        current byte offset into the current arc file
     *  @var int
     */
    var $current_offset;
    /**
     *  Array of filenames of arc files in this directory (glob order)
     *  @var array
     */
    var $partitions;
    /**
     *  File handle for current arc file
     *  @var resource
     */
    var $fh;

    /**
     * Creates a arc archive iterator with the given parameters.
     *
     * @param string $iterate_timestamp timestamp of the arc archive bundle to 
     *      iterate  over the pages of
     * @param string $result_timestamp timestamp of the arc archive bundle
     *      results are being stored in
     */
    function __construct($iterate_timestamp, $result_timestamp)
    {
        $this->iterate_timestamp = $iterate_timestamp;
        $this->result_timestamp = $result_timestamp;
        $archive_name = CRAWL_DIR.'/cache/'.self::archive_base_name.
            $iterate_timestamp;
        $this->partitions = array();
        foreach(glob("$archive_name/*.arc.gz") as $filename) { 
            $this->partitions[] = $filename;
        }
        $this->num_partitions = count($this->partitions);

        if(file_exists("$archive_name/iterate_status.txt")) {
            $info = unserialize(file_get_contents(
                "$archive_name/iterate_status.txt"));
            $this->end_of_iterator = $info['end_of_iterator'];
            $this->current_partition_num = $info['current_partition_num'];
            $this->current_offset = $info['current_offset'];
            $this->fh=gzopen(
                $this->partitions[$this->current_partition_num], "rb");
            gzseek($this->fh, $this->current_offset);
        } else {
            $this->reset();
        }



    }

    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return bool false we assume arc files were crawled according to 
     *      OPIC and so we use the default doc_depth to estimate page importance
     */
    function weight(&$site) 
    {
        return false;
    }

    /**
     * Resets the iterator to the start of the archive bundle
     */
    function reset()
    {
        $this->current_partition_num = -1;
        $this->end_of_iterator = false;
        $this->current_offset = 0;
        $this->fh = NULL;
        $archive_name = CRAWL_DIR.'/cache/'.self::archive_base_name.
            $this->result_timestamp;
        @unlink("$archive_name/iterate_status.txt");
    }

    /**
     * Gets the next at most $num many docs from the iterator. It might return
     * less than $num many documents if the partition changes or the end of the
     * bundle is reached.
     *
     * @param int $num number of docs to get
     * @return array associative arrays for $num pages
     */
    function nextPages($num)
    {
        $pages = array();
        for($i = 0; $i < $num; $i++) {
            $page = $this->nextPage();
            if(!$page) {
                if(is_resource($this->fh)) {
                    gzclose($this->fh);
                }
                $this->current_partition_num++;
                if($this->current_partition_num >= $this->num_partitions) {
                    $this->end_of_iterator = true;
                    break;
                }
                $this->fh = gzopen(
                    $this->partitions[$this->current_partition_num], "rb");
            } else {
                $pages[] = $page;
            }
        }
        if(is_resource($this->fh)) {
            $this->current_offset = gztell($this->fh);
        }

        $archive_name = CRAWL_DIR.'/cache/'.self::archive_base_name.
            $this->result_timestamp;
        $info = array();
        $info['end_of_iterator'] = $this->end_of_iterator;
        $info['current_partition_num'] = $this->current_partition_num;
        $info['current_offset'] = $this->current_offset;
        file_put_contents("$archive_name/iterate_status.txt",
            serialize($info));
        return $pages;
    }

    
    /**
     * Gets the next doc from the iterator
     * @return array associative array for doc
     */
    function nextPage()
    {
        if(!is_resource($this->fh)) return NULL;
        do {
            if(!$page_info = gzgets($this->fh) ) return NULL;
            $info_parts = explode(" ", $page_info);
            $num_parts = count($info_parts);
            $length = $info_parts[$num_parts - 1];

            if(!$object = gzread($this->fh, $length + 1)) return NULL;
        } while(substr($page_info, 0, 3) == 'dns' ||
            substr($page_info, 0, 8) == 'filedesc'); 
                //ignore dns entries in arc and ignore first record
        $site = array();
        $site[self::URL] = $info_parts[0];
        $site[self::IP_ADDRESSES] = array($info_parts[1]);
        $site[self::TIMESTAMP] = date("U", strtotime($info_parts[2]));
        $site[self::TYPE] = $info_parts[3];
        $site_contents = FetchUrl::parseHeaderPage($object);
        $site = array_merge($site, $site_contents);
        $site[self::HASH] = FetchUrl::computePageHash($site[self::PAGE]);
        $site[self::WEIGHT] = 1;
        return $site;
    }

}
?>
