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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**  For crawlHash function  */
require_once BASE_DIR."/lib/utility.php";
/** Loads common constants for web crawling, used for index_data_base_name and schedule_data_base_name */
require_once BASE_DIR."/lib/crawl_constants.php";
/** Crawl data is stored in an IndexArchiveBundle, so load the definition of this class*/
require_once BASE_DIR."/lib/index_archive_bundle.php";

/**
 * This is class is used to handle
 * db results for a given phrase search
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage model
 */
class CrawlModel extends Model implements CrawlConstants
{
    /**
     *  Stores the name of the current index archive to use to get search results from
     *  var string
     */
    var $index_name;


    /**
     *  {@inheritdoc}
     */
    function __construct($db_name = DB_NAME) 
    {
        parent::__construct($db_name);
    }


    /**
     *  Get a summary of a document by it document id (a string hash value) and its offset
     *
     *  @param string $ukey document id hash string
     *  @param int $summary_offset offset into a partition in a WebArchiveBundle
     *  @return array summary data of the matching document
     */
    function getCrawlItem($ukey, $summary_offset)
    {
        $index_archive_name = self::index_data_base_name . $this->index_name;

        $index_archive = new IndexArchiveBundle(CRAWL_DIR.'/cache/'.$index_archive_name);

        $summary = $index_archive->getPage($ukey, $summary_offset);

        return $summary;
    }


    /**
     *  Gets the cached version of a web page from the machine on which it was fetched.
     *
     *  Complete cached versions of web pages typically only live on a fetcher machine. The
     *  queue server machine typically only maintains summaries. This method makes a REST
     *  request of a fetcher machine for a cached page and get the results back.
     *
     *  @param string $machine the ip address of domain name of the machine the cached page lives on
     *  @param string $machine_uri the path from document root on $machine where the yioop scripts live
     *  @param string $hash the hash that was used to represent the page in the WebArchiveBundle
     *  @param int $offset the offset in bytes into the WebArchive partition in the WebArchiveBundle at which the cached page lives.
     *  @param string $crawl_time the timestamp of the crawl the cache page is from
     *  @return array page data of the cached page
     */
    function getCacheFile($machine, $machine_uri, $hash, $offset, $crawl_time) 
    {
        $time = time();
        $session = md5($time . AUTH_KEY);
        if($machine == '::1') {
            $machine = "localhost"; //used if the fetching and queue serving were on the same machine
        }

        $request= "http://$machine$machine_uri?c=archive&a=cache&time=$time&session=$session&hash=$hash&offset=$offset&crawl_time=$crawl_time";
        $page = @unserialize(base64_decode(FetchUrl::getPage($request)));
        $page['REQUEST'] = $request;

        return $page;
    }


    /**
     *  Gets the name (aka timestamp) of the current index archive to be used to handle search queries
     *
     *  @return string the timestamp of the archive
     */
    function getCurrentIndexDatabaseName()
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT CRAWL_TIME FROM CURRENT_WEB_INDEX";
        $result = $this->db->execute($sql);

        $row =  $this->db->fetchArray($result);
        
        return $row['CRAWL_TIME'];
    }


    /**
     *  Sets the IndexArchive that will be used for search results
     *
     *  @param $timestamp  the timestamp of the index archive. The timestamp is when the crawl was started
     *  Currently, the timestamp appears as substring of the index archives directory name
     */
    function setCurrentIndexDatabaseName($timestamp)
    {
        $this->db->selectDB(DB_NAME);
        $this->db->execute("DELETE FROM CURRENT_WEB_INDEX");
        $sql = "INSERT INTO CURRENT_WEB_INDEX VALUES ('".$timestamp."')";
        $this->db->execute($sql);

    }


    /**
     *  Gets a list of all index archives of crawls that have been conducted
     *
     *  @return array Available IndexArchiveBundle directories and their meta information 
     *  this meta information includes the time of the crawl, its description, the number of
     *  pages downloaded, and the number of partitions used in storing the inverted index
     */
    function getCrawlList()
    {
        $list = array();
        $dirs = glob(CRAWL_DIR.'/cache/*', GLOB_ONLYDIR);

        foreach($dirs as $dir) {
            if(strlen($pre_timestamp = strstr($dir, self::index_data_base_name)) > 0) {
                $crawl = array();
                $crawl['CRAWL_TIME'] = substr($pre_timestamp, strlen(self::index_data_base_name));
                $info = IndexArchiveBundle::getArchiveInfo($dir);
                $crawl['DESCRIPTION'] = $info['DESCRIPTION'];
                $crawl['COUNT'] = $info['COUNT'];
                $crawl['NUM_PARTITIONS'] = $info['NUM_PARTITIONS'];
                $list[] = $crawl;
            }
        }

        return $list;
    }

    /**
     *  Returns the initial sites that a new crawl will start with along with
     *  crawl parameters such as crawl order, allowed and disallowed crawl sites
     *
     *  @return array  the first sites to crawl during the next crawl
     */
    function getSeedInfo()
    {
        $info = parse_ini_file (BASE_DIR."/configs/crawl.ini", true);

        return $info;
    }

    /**
     *  
     */
    function setSeedInfo($info)
    {
        $n = array();
        $n[] = <<<EOT
; ***** BEGIN LICENSE BLOCK ***** 
;  SeekQuarry/Yioop Open Source Pure PHP Search Engine, Crawler, and Indexer
;  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
;
;  This program is free software: you can redistribute it and/or modify
;  it under the terms of the GNU General Public License as published by
;  the Free Software Foundation, either version 3 of the License, or
;  (at your option) any later version.
;
;  This program is distributed in the hope that it will be useful,
;  but WITHOUT ANY WARRANTY; without even the implied warranty of
;  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
;  GNU General Public License for more details.
;
;  You should have received a copy of the GNU General Public License
;  along with this program.  If not, see <http://www.gnu.org/licenses/>.
;  ***** END LICENSE BLOCK ***** 
;
; crawl.ini 
;
; crawl configuration file
;
EOT;
        $n[] = '[general]';
        $n[] = "crawl_order = '".$info['general']['crawl_order']."';";
        $bool_string = ($info['general']['restrict_sites_by_url']) ? "true" : "false";
        $n[] = "restrict_sites_by_url = $bool_string;";
        $n[] = "";
        
        $site_types = array('allowed_sites', 'disallowed_sites', 'seed_sites');
        foreach($site_types as $type) {
            $n[] = "[$type]";
            foreach($info[$type]['url'] as $url) {
                $n[] = "url[] = '$url';";
            }
            $n[]="";
        }

        $out = implode("\n", $n);
        file_put_contents(BASE_DIR."/configs/crawl.ini", $out);
    }
}
?>
