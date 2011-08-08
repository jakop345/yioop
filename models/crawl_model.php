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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**  For crawlHash function  */
require_once BASE_DIR."/lib/utility.php";
/** 
 * Loads common constants for web crawling, used for index_data_base_name and 
 * schedule_data_base_name 
 */
require_once BASE_DIR."/lib/crawl_constants.php";
/** 
 * Crawl data is stored in an IndexArchiveBundle, 
 * so load the definition of this class
 */
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
     * Stores the name of the current index archive to use to get search 
     * results from
     * @var string
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
     * Get a summary of a document by the generation it is in
     * and its offset into the corresponding WebArchive.
     *
     * @param int $summary_offset offset in $generation WebArchive
     * @param int $generation the index of the WebArchive in the 
     *      IndexArchiveBundle to find the item in.
     * @return array summary data of the matching document
     */
    function getCrawlItem($summary_offset, $generation)
    {
        $index_archive_name = self::index_data_base_name . $this->index_name;

        $index_archive = 
            new IndexArchiveBundle(CRAWL_DIR.'/cache/'.$index_archive_name);

        $summary = $index_archive->getPage($summary_offset, $generation);

        return $summary;
    }


    /**
     * Gets the cached version of a web page from the machine on which it was 
     * fetched.
     *
     * Complete cached versions of web pages typically only live on a fetcher 
     * machine. The queue server machine typically only maintains summaries. 
     * This method makes a REST request of a fetcher machine for a cached page 
     * and get the results back.
     *
     * @param string $machine the ip address of domain name of the machine the 
     *      cached page lives on
     * @param string $machine_uri the path from document root on $machine where 
     *      the yioop scripts live
     * @param int $partition the partition in the WebArchiveBundle the page is
     *       in
     * @param int $offset the offset in bytes into the WebArchive partition in 
     *      the WebArchiveBundle at which the cached page lives.
     * @param string $crawl_time the timestamp of the crawl the cache page is 
     *      from
     * @return array page data of the cached page
     */
    function getCacheFile($machine, $machine_uri, $partition, 
        $offset, $crawl_time) 
    {
        $time = time();
        $session = md5($time . AUTH_KEY);
        if($machine == '::1') { //IPv6 :(
            $machine = "[::1]/"; 
            //used if the fetching and queue serving were on the same machine
        }

        $request= "http://$machine$machine_uri?c=archive&a=cache&time=$time".
            "&session=$session&partition=$partition&offset=$offset".
            "&crawl_time=$crawl_time";
        $tmp = FetchUrl::getPage($request);
        $page = @unserialize(base64_decode($tmp));
        $page['REQUEST'] = $request;

        return $page;
    }


    /**
     * Gets the name (aka timestamp) of the current index archive to be used to 
     * handle search queries
     *
     * @return string the timestamp of the archive
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
     * Sets the IndexArchive that will be used for search results
     *
     * @param $timestamp  the timestamp of the index archive. The timestamp is 
     * when the crawl was started. Currently, the timestamp appears as substring
     * of the index archives directory name
     */
    function setCurrentIndexDatabaseName($timestamp)
    {
        $this->db->selectDB(DB_NAME);
        $this->db->execute("DELETE FROM CURRENT_WEB_INDEX");
        $sql = "INSERT INTO CURRENT_WEB_INDEX VALUES ('".$timestamp."')";
        $this->db->execute($sql);

    }


    /**
     * Gets a list of all index archives of crawls that have been conducted
     * 
     * @param bool $return_arc_bundles whether index bundles used for indexing
     *      arc or other archive bundles should be included in the lsit
     * @param bool $return_recrawls whether index archive bundles generated as
     *      a result of recrawling should be included in the result
     *
     * @return array Available IndexArchiveBundle directories and 
     *      their meta information this meta information includes the time of 
     *      the crawl, its description, the number of pages downloaded, and the 
     *      number of partitions used in storing the inverted index
     */
    function getCrawlList($return_arc_bundles = false, $return_recrawls = false)
    {
        $list = array();
        $dirs = glob(CRAWL_DIR.'/cache/*', GLOB_ONLYDIR);

        foreach($dirs as $dir) {
            if(strlen($pre_timestamp = 
                strstr($dir, self::index_data_base_name)) > 0) {
                $crawl = array();
                $crawl['CRAWL_TIME'] = 
                    substr($pre_timestamp, strlen(self::index_data_base_name));
                $info = IndexArchiveBundle::getArchiveInfo($dir);
                $index_info = unserialize($info['DESCRIPTION']);
                $crawl['DESCRIPTION'] = "";
                if(!$return_arc_bundles && isset($index_info['ARCFILE'])) {
                    continue;
                } else if ($return_arc_bundles
                    && isset($index_info['ARCFILE'])) {
                    $crawl['DESCRIPTION'] = "ARCFILE::";
                }
                if(!$return_recrawls && 
                    isset($index_info[self::CRAWL_TYPE]) && 
                    $index_info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL) {
                    continue;
                } else if($return_recrawls  && 
                    isset($index_info[self::CRAWL_TYPE]) && 
                    $index_info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL) {
                    $crawl['DESCRIPTION'] = "RECRAWL::";
                }
                $crawl['DESCRIPTION'] .= $index_info['DESCRIPTION'];
                $crawl['VISITED_URLS_COUNT'] = 
                    isset($info['VISITED_URLS_COUNT']) ?
                    $info['VISITED_URLS_COUNT'] : 0;
                $crawl['COUNT'] = $info['COUNT'];
                $crawl['NUM_DOCS_PER_PARTITION'] = 
                    $info['NUM_DOCS_PER_PARTITION'];
                $crawl['WRITE_PARTITION'] = $info['WRITE_PARTITION'];
                $list[] = $crawl;
            }
        }

        return $list;
    }

    /**
     * Deletes the crawl with the supplied timestamp if it exists. Also
     * deletes any crawl mixes making use of this crawl
     *
     * @param string $timestamp a Unix timestamp
     */
    function deleteCrawl($timestamp)
    {
        $this->db->unlinkRecursive(
            CRAWL_DIR.'/cache/'.self::index_data_base_name . $timestamp, true);
        $this->db->unlinkRecursive(
            CRAWL_DIR.'/schedules/'.self::index_data_base_name .
            $timestamp, true);
        $this->db->unlinkRecursive(
            CRAWL_DIR.'/schedules/' . self::schedule_data_base_name.$timestamp,
            true);
        $this->db->unlinkRecursive(
            CRAWL_DIR.'/schedules/'.self::robot_data_base_name.
            $timestamp, true);

        $this->db->selectDB(DB_NAME);
        $sql = "SELECT DISTINCT MIX_TIMESTAMP FROM MIX_COMPONENTS WHERE ".
            " CRAWL_TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        $rows = array();
        while($rows[] =  $this->db->fetchArray($result)) ;

        foreach($rows as $row) {
            $this->deleteCrawlMix($row['MIX_TIMESTAMP']);
        }
    }

    /**
     * Gets a list of all mixes of available crawls
     *
     * @param bool $components if false then don't load the factors
     *      that make up the crawl mix, just load the name of the mixes
     *      and their timestamps; otherwise, if true loads everything
     * @return array list of available crawls
     */
    function getMixList($components = false)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT MIX_TIMESTAMP, MIX_NAME FROM CRAWL_MIXES";
        $result = $this->db->execute($sql);

        $rows = array();
        while($row = $this->db->fetchArray($result)) {
            if($components) {
                $mix = $this->getCrawlMix($row['MIX_TIMESTAMP'], true);
                $row['GROUPS'] = $mix['GROUPS'];
            }
            $rows[] = $row;
        }
        return $rows;
    }


    /**
     * Retrieves the weighting component of the requested crawl mix
     *
     * @param string timestamp of the requested crawl mix
     * @param bool $just_components says whether to find the mix name or
     *      just the components array.
     * @return array the crawls and their weights that make up the
     *      requested crawl mix.
     */
    function getCrawlMix($timestamp, $just_components = false)
    {
        $this->db->selectDB(DB_NAME);
        if(!$just_components) {
            $sql = "SELECT MIX_TIMESTAMP, MIX_NAME FROM CRAWL_MIXES WHERE ".
                " MIX_TIMESTAMP='$timestamp'";
            $result = $this->db->execute($sql);
            $mix =  $this->db->fetchArray($result);
        } else {
            $mix = array();
        }
        $sql = "SELECT GROUP_ID, RESULT_BOUND".
            " FROM MIX_GROUPS WHERE ".
            " MIX_TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        $mix['GROUPS'] = array();
        while($row = $this->db->fetchArray($result)) {
            $mix['GROUPS'][$row['GROUP_ID']]['RESULT_BOUND'] = 
                $row['RESULT_BOUND'];
        }
        foreach($mix['GROUPS'] as $group_id => $data) {
            $sql = "SELECT CRAWL_TIMESTAMP, WEIGHT, KEYWORDS ".
                " FROM MIX_COMPONENTS WHERE ".
                " MIX_TIMESTAMP='$timestamp' AND GROUP_ID='$group_id'";
            $result = $this->db->execute($sql);

            $mix['COMPONENTS'] = array();
            $count = 0;
            while($row =  $this->db->fetchArray($result)) {
                $mix['GROUPS'][$group_id]['COMPONENTS'][$count] =$row;
                $count++;
            }
        }
        return $mix;
    }

    function getInfoTimestamp($timestamp, $is_mix = NULL)
    {
        if($is_mix === NULL) {
            $is_mix = $this->isCrawlMix($timestamp);
        }
        $info = array();
        if($is_mix) {
            $this->db->selectDB(DB_NAME);

            $sql = "SELECT MIX_TIMESTAMP, MIX_NAME FROM CRAWL_MIXES WHERE ".
                " MIX_TIMESTAMP='$timestamp'";
            $result = $this->db->execute($sql);
            $mix =  $this->db->fetchArray($result);
            $info['TIMESTAMP'] = $timestamp;
            $info['DESCRIPTION'] = $mix['MIX_NAME'];
            $info['IS_MIX'] = true;
        } else {
            $dir = CRAWL_DIR.'/cache/'.self::index_data_base_name.$timestamp;
            if(file_exists($dir)) {
                $info = IndexArchiveBundle::getArchiveInfo($dir);
                $tmp = unserialize($info['DESCRIPTION']);
                $info['DESCRIPTION'] = $tmp['DESCRIPTION'];
            }
        }

        return $info;
    }

    /**
     * Returns whether the supplied timestamp corresponds to a crawl mix
     *
     * @param string timestamp of the requested crawl mix

     * @return bool true if it does; false otherwise
     */
    function isCrawlMix($timestamp)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "SELECT MIX_TIMESTAMP, MIX_NAME FROM CRAWL_MIXES WHERE ".
            " MIX_TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        if($result) {
            if($mix =  $this->db->fetchArray($result)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Stores in DB the supplied crawl mix object
     *
     * @param array $mix an associative array repreenting the crawl mix object
     */
    function setCrawlMix($mix)
    {
        $this->db->selectDB(DB_NAME);
        //although maybe slower, we first get rid of any old data
        $timestamp = $mix['MIX_TIMESTAMP'];

        $this->deleteCrawlMix($timestamp);

        //next we store the new data
        $sql = "INSERT INTO CRAWL_MIXES VALUES ('$timestamp', '".
            $mix['MIX_NAME']."')";
        $this->db->execute($sql);

        $gid = 0;
        foreach($mix['GROUPS'] as $group_id => $group_data) {

            $sql = "INSERT INTO MIX_GROUPS VALUES ('$timestamp', '$gid', ".
                "'".$group_data['RESULT_BOUND']."')";
            $this->db->execute($sql);
            foreach($group_data['COMPONENTS'] as $component) {
                $sql = "INSERT INTO MIX_COMPONENTS VALUES ('$timestamp', '".
                    $gid."', '".$component['CRAWL_TIMESTAMP']."', '".
                    $component['WEIGHT']."', '" .
                    $component['KEYWORDS']."')";
                $this->db->execute($sql);
            }
            $gid++;
        }
    }

    /**
     * Stores in DB the supplied crawl mix object
     *
     * @param array $mix an associative array repreenting the crawl mix object
     */
    function deleteCrawlMix($timestamp)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM CRAWL_MIXES WHERE MIX_TIMESTAMP='$timestamp'";
        $this->db->execute($sql);
        $sql = "DELETE FROM MIX_GROUPS WHERE MIX_TIMESTAMP='$timestamp'";
        $this->db->execute($sql);
        $sql = "DELETE FROM MIX_COMPONENTS WHERE MIX_TIMESTAMP='$timestamp'";
        $this->db->execute($sql);

    }

    /**
     * Returns the crawl parameters that were used during a given crawl
     *
     * @param string $timestamp timestamp of the crawl to load the crawl
     *      parameters of
     * @return array  the first sites to crawl during the next crawl
     *      restrict_by_url, allowed, disallowed_sites
     */
    function getCrawlSeedInfo($timestamp)
    {
        $dir = CRAWL_DIR.'/cache/'.self::index_data_base_name.$timestamp;
        $seed_info = NULL;
        if(file_exists($dir)) {
            $info = IndexArchiveBundle::getArchiveInfo($dir);
            $index_info = unserialize($info['DESCRIPTION']);
            $seed_info['general']["restrict_sites_by_url"] = 
                $index_info[self::RESTRICT_SITES_BY_URL];
            $seed_info['general']["crawl_type"] = 
                (isset($index_info[self::CRAWL_TYPE])) ?
                $index_info[self::CRAWL_TYPE] : self::WEB_CRAWL;
            $seed_info['general']["crawl_index"] = 
                (isset($index_info[self::CRAWL_INDEX])) ?
                $index_info[self::CRAWL_INDEX] : '';
            $seed_info['general']["crawl_order"] = 
                $index_info[self::CRAWL_ORDER];
            $site_types = array(
                "allowed_sites" => self::ALLOWED_SITES,
                "disallowed_sites" => self::DISALLOWED_SITES,
                "seed_sites" => self::TO_CRAWL
            );
            foreach($site_types as $type => $code) {
                if(isset($index_info[$code])) {
                    $tmp = & $index_info[$code];
                } else {
                    $tmp = array();
                }
                $seed_info[$type]['url'] =  $tmp;
            }
            $seed_info['meta_words'] = array();
            if(isset($index_info[self::META_WORDS]) ) {
                $seed_info['meta_words'] = $index_info[self::META_WORDS];
            }
            if(isset($index_info[self::INDEXING_PLUGINS])) {
                $seed_info['indexing_plugins'] = 
                    $index_info[self::INDEXING_PLUGINS];
            }
        }
        return $seed_info;
    }
    
    /**
     *  Returns the initial sites that a new crawl will start with along with
     *  crawl parameters such as crawl order, allowed and disallowed crawl sites
     *  @param bool $use_default whether or not to use the Yioop! default
     *      crawl.ini file rather than the one created by the user.
     *  @return array  the first sites to crawl during the next crawl
     *      restrict_by_url, allowed, disallowed_sites
     */
    function getSeedInfo($use_default = false)
    {
        if(file_exists(WORK_DIRECTORY."/crawl.ini") && !$use_default) {
            $info = parse_ini_file (WORK_DIRECTORY."/crawl.ini", true);
        } else {
            $info =parse_ini_file (BASE_DIR."/configs/default_crawl.ini", true);
        }

        return $info;

    }

    /**
     * Writes a crawl.ini file with the provided data to the user's 
     * WORK_DIRECTORY
     *
     * @param array $info an array containing information about the crawl
     * such as crawl_order, whether restricted_by_url, seed_sites, 
     * allowed_sites and disallowed_sites
     */
    function setSeedInfo($info)
    {
        if(!isset($info['general']['crawl_index'])) {
            $info['general']['crawl_index']='12345678';
        }
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
        $n[] = "crawl_type = '".$info['general']['crawl_type']."';";
        $n[] = "crawl_index = '".$info['general']['crawl_index']."';";
        $bool_string = 
            ($info['general']['restrict_sites_by_url']) ? "true" : "false";
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
        $n[] = "[meta_words]";
        if(isset($info["meta_words"])) {
            foreach($info["meta_words"] as $word_pattern => $url_pattern) {
                $n[] = "$word_pattern = '$url_pattern';";
            }
            $n[]="";
        }
        //Added by Priya Gangaraju
        //for adding post processors
        $n[] = "[indexing_plugins]";
        if(isset($info["indexing_plugins"])) {
            foreach($info["indexing_plugins"]['plugins'] as $plugin) {
                $n[] = "plugins[] = '$plugin';";
            }
        }//

        $out = implode("\n", $n);
        file_put_contents(WORK_DIRECTORY."/crawl.ini", $out);
    }
}
?>
