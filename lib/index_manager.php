<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
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
     * Open IndexArchiveBundle's managed by this manager
     * @var array
     */
    static $indexes = array();
    /**
     * Used to cache word lookup of posting list locations for a given
     * index
     * @var array
     */
    static $dictionary = array();
    /**
     * Returns a reference to the managed copy of an IndexArchiveBundle object
     * with a given timestamp or an IndexShard in the case where
     * $index_name == "feed" (for handling news feeds)
     *
     * @param string $index_name timestamp of desired IndexArchiveBundle
     * @return object the desired IndexArchiveBundle reference
     */
    static function getIndex($index_name)
    {
        $index_name = trim($index_name); //trim to fix postgres quirkiness
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
                $tmp = new IndexArchiveBundle(
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
     * Returns the version of the index, so that Yioop can determine
     * how to do word lookup.The only major change to the format was
     * when word_id's went from 8 to 20 bytes which happened around Unix
     * time 1369754208.
     *
     * @param string $index_name unix timestamp of index
     * @return int 0 - if the orginal format for Yioop indexes; 1 -if 20 byte
     *     word_id format
     */
    static function getVersion($index_name)
    {
        if(intval($index_name) < VERSION_0_TIMESTAMP) {
            return 0;
        }
        $tmp_index = self::getIndex($index_name);
        if(isset($tmp_index->version)) {
            return $tmp_index->version;
        }
        return 1;
    }
    /**
     * Gets an array posting list positions for each shard in the
     * bundle $index_name for the word id $hash
     *
     * @param string $index_name bundle to look $hash in
     * @param string $hash hash of phrasse or word to look up in bundle
     *     dictionary
     * @param int $shift if $hash is for a phrase, how many low order
     *     bits of word id to discard
     * @param string $mask if $hash is for a word, after the 9th byte what
     *     meta word mask should be applied to the 20 byte hash
     * @param int $threshold after the number of results exceeds this amount
     *     stop looking for more dictionary entries.
     * @return array sequence of four tuples:
     *     (index_shard generation, posting_list_offset, length, exact id
     *      that match $hash)
     */
    static function getWordInfo($index_name, $hash, $shift = 0, $mask = "",
       $threshold = -1)
    {
       $index = IndexManager::getIndex($index_name);
       if(!$index->dictionary) {
            $tmp = array();
            if((!defined('NO_FEEDS') || !NO_FEEDS)
               && file_exists(WORK_DIRECTORY."/feeds/index")) {
               //NO_FEEDS defined true in statistic_controller.php
               
                $use_feeds = true;
                $feed_shard = IndexManager::getIndex("feed");
                $feed_info = $feed_shard->getWordInfo($hash, true, $shift);
                if(is_array($feed_info)) {
                    $tmp[-1] = array(-1, $feed_info[0],
                        $feed_info[1], $feed_info[2], $feed_info[3]);
                }
            }
            IndexManager::$dictionary[$index_name][$hash][$shift][
                $mask][$threshold] = $tmp;
           return IndexManager::$dictionary[$index_name][$hash][$shift][
                $mask][$threshold];
       }
       $len = strlen($mask);
        if($len > 0) {
            $pre_hash = substr($hash, 0, 8).
                "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        } else {
            $pre_hash = $hash;
        }
       if(!isset(IndexManager::$dictionary[$index_name][$hash][$shift][
            $mask][$threshold])) {
           $tmp = array();
           $test_mask = "";
           if(isset(IndexManager::$dictionary[$index_name][$pre_hash][
                $shift])) {
               foreach(IndexManager::$dictionary[$index_name][$pre_hash][
                    $shift] as $test_mask => $data) {
                   $mask_len = strlen($test_mask);
                   if($mask_len > $len) {continue; }
                   $mask_found = true;
                   for($k = 0; $k < $mask_len; $k++) {
                       if(ord($test_mask[$k]) > 0 &&
                           $test_mask[$k] != $mask[$k]) {
                           $mask_found = false;
                           break;
                       }
                   }
                   if($mask_found && isset(
                       IndexManager::$dictionary[$index_name][$pre_hash][
                            $shift][$test_mask][$threshold]) ) {
                       $info = IndexManager::$dictionary[$index_name][$pre_hash
                            ][$shift][$test_mask][$threshold];
                       $out_info = array();
                       foreach($info as $record) {
                           $id = $record[4];
                           $add_flag = true;
                           if($mask != "") {
                               for($k = 0; $k < $len; $k++) {
                                   $loc = 8 + $k;
                                   if(ord($mask[$k]) > 0 && isset($id[$loc]) &&
                                       $id[$loc] != $hash[$loc]) {
                                       $add_flag = false;
                                       break;
                                   }
                               }
                           }
                           if($add_flag) {
                               $out_info[$record[0]] = $record;
                           }
                       }
                       IndexManager::$dictionary[$index_name][$hash][$shift
                           ][$mask][$threshold] = $out_info;
                       return $out_info;
                   }
               }
           }
           if((!defined('NO_FEEDS') || !NO_FEEDS)
               && file_exists(WORK_DIRECTORY."/feeds/index")) {
               //NO_FEEDS defined true in statistic_controller.php
                $use_feeds = true;
                $feed_shard = IndexManager::getIndex("feed");
                $feed_info = $feed_shard->getWordInfo($hash, true, $shift);
                if(is_array($feed_info)) {
                    $tmp[-1] = array(-1, $feed_info[0],
                        $feed_info[1], $feed_info[2], $feed_info[3]);
                }
           }
           IndexManager::$dictionary[$index_name][$hash][$shift][$mask][
               $threshold] = $tmp +
               $index->dictionary->getWordInfo($hash, true, $shift, $mask,
               $threshold);
       }
       return IndexManager::$dictionary[$index_name][$hash][$shift][$mask][
           $threshold];
    }
    /**
     * Returns the number of document that a given term or phrase appears in
     * in the given index
     *
     * @param string $term_or_phrase what to look up in the indexes dictionary
     *     no  mask is used for this look up
     * @param string $index_name index to look up term or phrase in
     * @param int $threshold if set and positive then once threshold many
     *     documents are found the search for more documents to add to the
     *     total is stopped
     * @return int number of documents
     */
    static function numDocsTerm($term_or_phrase, $index_name, $threshold = -1)
    {
        $index = IndexManager::getIndex($index_name);
        if(!$index->dictionary) {
            return false;
        }
        $pos = -1;
        $total_num_docs = 0;
        $hashes = allCrawlHashPaths($term_or_phrase, array(), array(), true);
        if(!is_array($hashes)) {
            $hashes = array($hashes);
        }
        foreach($hashes as $hash) {
            if(is_array($hash)) {
                $dictionary_info =
                    IndexManager::getWordInfo($index_name, $hash[0],
                        $hash[1], $hash[2], $threshold);
            } else {
                $dictionary_info =
                    IndexManager::getWordInfo($index_name, $hash);
            }
            $num_generations = count($dictionary_info);
            $start = (isset($dictionary_info[-1])) ? -1 : 0;
            $end = ($start == -1) ? $num_generations - 1: $num_generations;
            for($i = $start; $i < $end; $i++) {
                list(, , , $num_docs) = $dictionary_info[$i];
                $total_num_docs += $num_docs;
                if($threshold > 0 && $total_num_docs > $threshold) {
                    return $total_num_docs;
                }
            }
        }
        return $total_num_docs;
    }
}
?>
