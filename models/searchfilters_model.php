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


/**  For crawlHash */
require_once BASE_DIR."/lib/utility.php";

/** 
 * Loads common constants for web crawling, used for index_data_base_name and 
 * schedule_data_base_name 
 */
require_once BASE_DIR."/lib/crawl_constants.php";


/**
 * This class manages the persistence to disk of a set of urls to be
 * filtered from all search results returned by Yioop!
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage model
 */
class SearchfiltersModel extends Model implements CrawlConstants
{
    /**
     * Directory in which to put filter 
     * @var string
     */
    var $dir_name;
    
    /**
     *  {@inheritdoc}
     */
    function __construct($db_name = DB_NAME) 
    {
        parent::__construct($db_name);
        $this->dir_name = CRAWL_DIR."/search_filters";
        if(!file_exists(CRAWL_DIR."/search_filters")) {
            mkdir($this->dir_name);

            $this->db->setWorldPermissionsRecursive(
                $this->dir_name, true);
        }
    }

    /**
     *  Gets a list of hostnames to be filtered from search results.
     *  This method is suitable for displaying what is to be filtered,
     *  but as it returns full urls it is probably to slow to query and
     *  might also be a larger file to read
     *
     *  @return array $filtered_urls urls to be filtered
     */
    function getUrls()
    {
        $filtered_urls = array();
        if(file_exists($this->dir_name."/urls.txt")) {
            $filtered_urls = unserialize(
                file_get_contents($this->dir_name."/urls.txt"));
        }
        return $filtered_urls;
    }

    /**
     *  Gets a list of hashes of hostnames to be filtered from search results.
     *  This method is suitable for quickly finding a host name in word
     *  iterator to remove from results.
     *
     *  @return array $filtered hashes of urls to be filtered
     */
    function getFilter()
    {
        $filter = false;
        if(file_exists($this->dir_name."/hash_urls.txt")) {
            $filter = unserialize(
                file_get_contents($this->dir_name."/hash_urls.txt"));
        }
        return $filter;
    }

    /**
     *  Sets a list of hostnames to be filtered from search results
     *
     *  @param array $urls to be filtered
     */
    function set($urls)
    {
        $url_count = count($urls);
        file_put_contents($this->dir_name."/urls.txt", serialize($urls));

        $hash_urls = array();
        foreach($urls as $url) {
            $hash_urls[] = substr(crawlHash($url, true), 1);
        }
        file_put_contents($this->dir_name."/hash_urls.txt", 
            serialize($hash_urls));

    }
}
?>
