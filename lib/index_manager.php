<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";
/**
 * Crawl data is stored in an IndexArchiveBundle,
 * so load the definition of this class
 */
require_once BASE_DIR."/lib/index_archive_bundle.php";
/**
 * For crawlHash
 */
require_once BASE_DIR."/lib/utility.php";
/**
 * Class used to manage open IndexArchiveBundle's while performing
 * a query. Ensures an easy place to obtain references to these bundles
 * and ensures only one object per bundle is instantiated in a Singleton-esque
 * way.
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class IndexManager implements CrawlConstants
{
    /**
     *
     *  @var array
     */
    static $indexes = array();

    static $dictionary = array();
    /**
     *  Returns a reference to the managed copy of an IndexArchiveBundle object
     *  with a given timestamp or an IndexShard in the case where
     *  $index_name == "feed" (for handling news feeds)
     *
     *  @param string $index_name timestamp of desired IndexArchiveBundle
     *  @return object the desired IndexArchiveBundle reference
     */
    static function getIndex($index_name)
    {

        if(!isset(self::$indexes[$index_name])) {
            if($index_name == "feed") {
                $index_file = WORK_DIRECTORY."/feeds/index";
                if(file_exists($index_file)) {
                    self::$indexes[$index_name] = new IndexShard(
                        $index_file, 0, NUM_DOCS_PER_GENERATION, true);
                } else {
                    return false;
                }
            } else {
                $index_archive_name = self::index_data_base_name . $index_name;
                $tmp =
                    new IndexArchiveBundle(
                        CRAWL_DIR.'/cache/'.$index_archive_name);
                if(!$tmp) {
                    return false;
                }
                self::$indexes[$index_name] = $tmp;
                self::$indexes[$index_name]->setCurrentShard(0, true);
            }
        }
        return self::$indexes[$index_name];
    }

    /**
     *
     */
    static function getWordInfo($index_name, $hash, $mask = false)
    {
        $index = IndexManager::getIndex($index_name);
        if(!$index->dictionary) {
            return false;
        }
        if(!isset(IndexManager::$dictionary[$index_name][$hash][$mask])) {
            IndexManager::$dictionary[$index_name][$hash][$mask] =
                $index->dictionary->getWordInfo($hash, true, true, $mask);
        }
        return IndexManager::$dictionary[$index_name][$hash][$mask];
    }

    /**
     *
     */
    static function numDocsTerm($term_or_phrase, $index_name)
    {
        $index = IndexManager::getIndex($index_name);
        if(!$index->dictionary) {
            return false;
        }
        $pos = -1;
        $total_num_docs = 0;
        $hashes = allCrawlHashPaths($term_or_phrase, true);
        if(!is_array($hashes)) {
            $hashes = array($hashes);
        }
        foreach($hashes as $hash) {
            if(is_array($hash)) {
                $dictionary_info = 
                    IndexManager::getWordInfo($index_name, $hash[0],
                        $hash[1]);
            } else {
                $dictionary_info = 
                    IndexManager::getWordInfo($index_name, $hash);
            }
            $num_generations = count($dictionary_info);
            for($i = 0; $i < $num_generations; $i++) {
                list(, , , $num_docs) = $dictionary_info[$i];
                $total_num_docs += $num_docs;
            }
        }
        return $total_num_docs;
    }
}
?>
