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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads the base class */
require_once BASE_DIR."/models/model.php";

/**
 * Used to manage data related to video, news, and other search sources
 * Also, used to manage data about available subsearches seen in SearchView
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class SourceModel extends Model
{

    /** Mamimum number of feeds to download in one try */
    const MAX_FEEDS_ONE_GO = 20;

    /** Number of seconds in a two minutes*/
    const TWO_MINUTES = 120;

    /** Number of seconds in a day*/
    const ONE_DAY = 86400;

    /** Number of seconds in a week*/
    const ONE_WEEK = 604800;

    /** Number of seconds in an hour */
    const ONE_HOUR = 3600;

    /** Maximum number of tries to completely copy over old shard on delete */
    const MAX_COPY_TRIES = 5;

    /** Maximum length of time update/delete news scripts can run in seconds*/
    const MAX_EXECUTION_TIME = 10;

    /**
     * Just calls the parent class constructor
     */
    function __construct()
    {
        parent::__construct();
    }


    /**
     *  Returns a list of media sources such as (video, rss sites) and their
     *  URL and thumb url formats, etc
     *
     *  @param string $source_type the particular kind of media source to return
     *      for example, video
     *  @param bool $has_feed_no_items if true returns only those items which
     *      have not feed_items associated with them.
     *  @return array a list of web sites which are either video or news sites
     */
    function getMediaSources($source_type = "", $has_no_feed_items = false)
    {
        $sources = array();
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT M.* FROM MEDIA_SOURCE M";
        if($source_type !="") {
            $sql .= " WHERE TYPE='$source_type'";
        }
        if($has_no_feed_items) {
            if($source_type == "") {
                $sql .= " WHERE ";
            } else {
                $sql .= " AND ";
            }
            $sql .= " NOT EXISTS
                (SELECT * FROM FEED_ITEM F
                WHERE F.SOURCE_NAME = M.NAME)";
        }
        $i = 0;
        $result = $this->db->execute($sql);
        while($sources[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($sources[$i]); //last one will be null

        return $sources;

    }

    /**
     *  Used to add a new video, rss, or other sources to Yioop
     *
     *  @param string $name
     *  @param string $source_type whether video, rss, etc
     *  @param string $source_url url regex of resource (video) or actual
     *      resource (rss). Not quite a real regex you add {} to the
     *      location in the url where the name of the particular video
     *      should go http://www.youtube.com/watch?v={}&
     *      (anything after & is ignored, so between = and & will be matched
     *      as the name of a video)
     *  @param string $thumb_url regex of where to get thumbnails for videos
     *      based on match of $source_url, for example,
     *      http://img.youtube.com/vi/{}/2.jpg
     * @param string $language the locale tag for the media source (rss)
     */
    function addMediaSource($name, $source_type, $source_url, $thumb_url,
        $language = DEFAULT_LOCALE)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "INSERT INTO MEDIA_SOURCE VALUES ('".time()."','".
            $this->db->escapeString($name)."','".
            $this->db->escapeString($source_type)."','".
            $this->db->escapeString($source_url)."','".
            $this->db->escapeString($thumb_url)."','".
            $this->db->escapeString($language)."')";

        $this->db->execute($sql);
    }

    /**
     * Deletes the media source whose id is the given timestamp
     *
     * @param int $timestamp of media source to be deleted
     */
    function deleteMediaSource($timestamp)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT * FROM MEDIA_SOURCE WHERE TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        if($result) {
            $row = $this->db->fetchArray($result);
            if(isset($row['TYPE']) && $row['TYPE'] == "rss") {
                if($row['NAME'] != "") {
                    $sql = "DELETE FROM FEED_ITEM WHERE SOURCE_NAME='".
                        $this->db->escapeString($row['NAME'])."'";
                    $this->db->execute($sql);
                }
            }
        }
        $sql = "DELETE FROM MEDIA_SOURCE WHERE TIMESTAMP='$timestamp'";

        $this->db->execute($sql);
    }

    /**
     * Returns a list of the subsearches used by the current Yioop instances
     * including their names translated to the current locale
     *
     * @return array associative array containing subsearch info name in locale,
     *     folder name, index, number of results per page
     */
    function getSubsearches()
    {
        $subsearches = array();
        $db = $this->db;
        $db->selectDB(DB_NAME);
        $locale_tag = getLocaleTag();

        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = '$locale_tag' LIMIT 0, 1";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);

        $locale_id = $row['LOCALE_ID'];
        $sql = "SELECT S.LOCALE_STRING AS LOCALE_STRING, ".
            "S.FOLDER_NAME AS FOLDER_NAME, ".
            " S.PER_PAGE AS PER_PAGE, ".
            " S.INDEX_IDENTIFIER AS INDEX_IDENTIFIER, ".
            " T.TRANSLATION_ID AS TRANSLATION_ID FROM ".
            " SUBSEARCH S, TRANSLATION T WHERE  ".
            " T.IDENTIFIER_STRING = S.LOCALE_STRING";
        $i = 0;
        $result = $db->execute($sql);
        while($subsearches[$i] = $db->fetchArray($result)) {
            $id = $subsearches[$i]["TRANSLATION_ID"];
            $sub_sql = "SELECT TRANSLATION AS SUBSEARCH_NAME ".
                "FROM TRANSLATION_LOCALE ".
                " WHERE TRANSLATION_ID=$id AND LOCALE_ID=$locale_id LIMIT 0, 1";
                // maybe do left join at some point

            $result_sub =  $db->execute($sub_sql);
            $translate = false;
            if($result_sub) {
                $translate = $db->fetchArray($result_sub);
            }
            if($translate) {
                $subsearches[$i]['SUBSEARCH_NAME'] =
                    $translate['SUBSEARCH_NAME'];
            } else {
                $subsearches[$i]['SUBSEARCH_NAME'] = $this->translateDb(
                    $subsearches[$i]['LOCALE_STRING'], DEFAULT_LOCALE);
            }
            $i++;
        }
        unset($subsearches[$i]); //last one will be null
        return $subsearches;
    }

    /**
     * Adds a new subsearch to the list of subsearches. This are displayed
     * at the top od the Yioop search pages.
     *
     * @param string $folder_name name of subsearch in terms of urls
     *      (not translated name that appears in the subsearch bar)
     * @param string $index_identifier timestamp of crawl or mix to be
     *      used for results of subsearch
     * @param int $per_page number of search results per page when this
     *      subsearch is used
     */
    function addSubsearch($folder_name, $index_identifier, $per_page)
    {
        $this->db->selectDB(DB_NAME);
        $locale_string = "db_subsearch_".$folder_name;


        $sql = "INSERT INTO SUBSEARCH VALUES ('".
            $this->db->escapeString($locale_string)."','".
            $this->db->escapeString($folder_name)."','".
            $this->db->escapeString($index_identifier)."','".
            $this->db->escapeString($per_page)."')";

        $this->db->execute($sql);

        $sql = "INSERT INTO TRANSLATION VALUES ('".
            time()."','".
            $this->db->escapeString($locale_string)."')";
        $this->db->execute($sql);
    }


    /**
     * Deletes a subsearch from the subsearch table and removes its
     * associated translations
     *
     * @param string $folder_name of subsearch to delete
     */
    function deleteSubsearch($folder_name)
    {
        $this->db->selectDB(DB_NAME);
        $locale_string = "db_subsearch_".$folder_name;

        $sql = "SELECT * FROM TRANSLATION WHERE IDENTIFIER_STRING =".
            "'$locale_string'";
        $result = $this->db->execute($sql);
        if(isset($result)) {
            $row = $this->db->fetchArray($result);
            if(isset($row["TRANSLATION_ID"])) {
                $translation_id = $row["TRANSLATION_ID"];
                $sql = "DELETE FROM TRANSLATION_LOCALE WHERE ".
                    "TRANSLATION_ID='$translation_id'";
                $this->db->execute($sql);
            }
        }
        $sql = "DELETE FROM SUBSEARCH WHERE FOLDER_NAME='$folder_name'";
        $this->db->execute($sql);

        $sql = "DELETE FROM TRANSLATION WHERE IDENTIFIER_STRING='".
            $locale_string."'";
        $this->db->execute($sql);
    }

    /**
     *  For each feed source downloads the feeds, checks which items are
     *  not in the database, adds them and updates the inverted index for feeds
     *
     *  @param int $age how many seconds old records should be ignored
     *  @param bool $try_again whether to update everything or just those
     *      feeds for which we have no items
     *  @param bool $news_process whether this is being called from
     *      new_update daemon or being done by the web app
     *  @return bool whether feed item update was successful
     */
    function updateFeedItems($age = self::ONE_WEEK, $try_again = false,
        $news_process = false)
    {
        $time = time();
        $this->db->selectDB(DB_NAME);
        $feed_shard_name = WORK_DIRECTORY."/feeds/index";
        $feed_shard = NULL;
        $feeds_one_go = self::MAX_FEEDS_ONE_GO;
        if(file_exists($feed_shard_name)) {
            $feed_shard = IndexShard::load($feed_shard_name);
        }
        if(!$feed_shard) {
            @unlink($feed_shard_name); //maybe index corrupted?
            $feed_shard =  new IndexShard($feed_shard_name);
        }
        if(!$feed_shard) {
            return false;
        }
        if($try_again) {
            $feeds = $this->getMediaSources("rss", $try_again);
        } else {
            $feeds = array();
            $sql = "SELECT COUNT(*) AS CNT FROM MEDIA_SOURCE WHERE TYPE='rss'";
            $result = $this->db->execute($sql);
            $row = $this->db->fetchArray($result);
            $num_feeds = (isset($row['CNT'])) ? $row['CNT'] : 0;
            $num_bins = floor($num_feeds/$feeds_one_go) + 1;
            $hour = date('H', $time);
            $current_bin = $hour % $num_bins;
            $limit = $current_bin * $feeds_one_go;
            $sql = "SELECT * FROM MEDIA_SOURCE WHERE TYPE='rss' LIMIT ".
                "$limit, $feeds_one_go";
            $result = $this->db->execute($sql);
            $i = 0;
            while($feeds[$i] = $this->db->fetchArray($result)) {
                $i++;
            }
            unset($feeds[$i]); //last one will be null
        }
        $feeds = FetchUrl::getPages($feeds, false, 0, NULL, "SOURCE_URL",
            CrawlConstants::PAGE, true, NULL, true);
        $feed_items = array();
        foreach($feeds as $feed) {
            $dom = new DOMDocument();
            @$dom->loadXML($feed[CrawlConstants::PAGE]);
            $lang = "";
            if(!isset($feed["LANGUAGE"]) || $feed["LANGUAGE"] == "") {
                $languages = $dom->getElementsByTagName('language');
                if($languages && is_object($languages) &&
                    is_object($languages->item(0))) {
                    $lang = $languages->item(0)->textContent;
                    $sql = "UPDATE MEDIA_SOURCE SET LANGUAGE='$lang' WHERE ".
                        "TIMESTAMP='".$feed['TIMESTAMP']."'";
                    $this->db->execute($sql);
                }
            } else if(isset($feed["LANGUAGE"]) && $feed["LANGUAGE"] != "") {
                $lang = $feed["LANGUAGE"];
            }

            $nodes = $dom->getElementsByTagName('item');
            $rss_elements = array("title" => "title",
                "description" => "description", "link" =>"link",
                "guid" => "guid", "pubDate" => "pubDate");
            if($nodes->length == 0) {
                // maybe we're dealing with atom rather than rss
                $nodes = $dom->getElementsByTagName('entry');
                $rss_elements = array(
                    "title" => "title", "description" => "summary",
                    "link" => "link", "guid" => "id", "pubDate" => "updated");
            }
            $max_time = min(self::MAX_EXECUTION_TIME,
                ini_get('max_execution_time')/3);
            crawlLog("Updating {$feed['NAME']}...");
            $num_added = 0;
            foreach($nodes as $node) {
                $item = array();
                foreach($rss_elements as $db_element => $feed_element) {
                    $tag_node = $node->getElementsByTagName(
                            $feed_element)->item(0);
                    $element_text = (is_object($tag_node)) ?
                        $tag_node->nodeValue: "";
                    if($feed_element == "link" && $element_text == "") {
                        $element_text = $tag_node->getAttribute("href");
                    }
                    $item[$db_element] = strip_tags($element_text);
                }
                $did_add = $this->addFeedItemIfNew($item, $feed_shard,
                    $feed['NAME'], $lang, $age);
                if($did_add) {
                    $num_added++;
                }
                if(!$news_process && (time() - $time > $max_time)) {
                    break 2; // running out of time better save shard
                }
            }
            crawlLog("...added $num_added news items.");
        }
        $feed_shard->save();
        return true;
    }

    /**
     * Copies all feeds items newer than $age to a new shard, then deletes
     * old index shard and database entries older than $age. Finally sets copied
     * shard to be active. If this method is going to take max_execution_time/2
     * it returns false, so an additional job can be schedules; otherwise
     * it returns true
     *
     * @param int $age how many seconds old records should be deleted
     * @param bool $news_process whether this is being called from
     *      new_update daemon or being done by the web app
     * @return bool whether job executed to complete
     */
    function deleteFeedItems($age, $news_process = false)
    {
        $time = time();
        $feed_shard_name = WORK_DIRECTORY."/feeds/index";
        $prune_shard_name = WORK_DIRECTORY."/feeds/prune_index";
        $prune_info_file = WORK_DIRECTORY."/feeds/prune_info.txt";
        $has_prune_shard = file_exists($prune_shard_name);
        $has_prune_info = file_exists($prune_info_file);
        $info = array();
        if(!$has_prune_shard || !$has_prune_info) {
            @unlink($prune_shard_name);
            @unlink($prune_info_file);
            $prune_shard =  new IndexShard($prune_shard_name);
            $info['start_pubdate'] = $time;
            $info['copy_tries'] = 0;
        }
        if($has_prune_shard && $has_prune_info) {
            $info = unserialize(file_get_contents($prune_info_file));
            if(!isset($info['start_pubdate'])) {
                @unlink($prune_info_file);
                return false;
            }
            $prune_shard = IndexShard::load($prune_shard_name);
            if(!$prune_shard) {
                @unlink($prune_shard_name); //maybe index corrupted?
                $prune_shard =  new IndexShard($prune_shard_name);
                return false;
            }
        }
        $too_old = $time - $age;
        if(!$prune_shard) {
            return false;
        }

        $pre_feeds = $this->getMediaSources("rss");
        if(!$pre_feeds) { return false; }
        $feeds = array();
        foreach($pre_feeds as $pre_feed) {
            if(!isset($pre_feed['NAME'])) continue;
            $feeds[$pre_feed['NAME']] = $pre_feed;
        }
        $db = $this->db;

        // we now rebuild the inverted index with the remaining items
        $sql = "SELECT * FROM FEED_ITEM ".
            "WHERE PUBDATE < {$info['start_pubdate']} AND ".
            "PUBDATE >= $too_old ".
            "ORDER BY PUBDATE DESC";
        $result = $db->execute($sql);
        if($result) {
            $completed = true;
            $max_time = min(self::MAX_EXECUTION_TIME,
                ini_get('max_execution_time')/3);
            while($item = $db->fetchArray($result)) {
                if(!isset($item['SOURCE_NAME'])) continue;
                $source_name = $item['SOURCE_NAME'];
                if(isset($feeds[$source_name])) {
                    $lang = $feeds[$source_name]['LANGUAGE'];
                } else {
                    $lang = "";
                }
                $phrase_string = $item["TITLE"] . " ". $item["DESCRIPTION"];
                $word_lists = PhraseParser::extractPhrasesInLists(
                    $phrase_string, $lang);
                $raw_guid = unbase64Hash($item["GUID"]);
                $doc_keys = crawlHash($item["LINK"], true) .
                    $raw_guid."d". substr(crawlHash(
                    UrlParser::getHost($item["LINK"])."/",true), 1);
                $meta_ids = $this->calculateMetas($lang, $item['PUBDATE'],
                    $source_name, $item["GUID"]);
                $prune_shard->addDocumentWords($doc_keys, $item['PUBDATE'],
                    $word_lists, $meta_ids, PhraseParser::$materialized_metas, 
                    true, false);
                if(!$news_process && (time() - $time > $max_time)) {
                    $info['start_pubdate'] = $item['PUBDATE'];
                    $info['copy_tries']++;
                    if($info['copy_tries'] < self::MAX_COPY_TRIES) {
                        $completed = false;
                    } else {
                        $completed = true;
                        $too_old = $item['PUBDATE'] - 1;
                    }
                    break; // running out of time better save progress
                }
            }
        }
        $prune_shard->save();
        @chmod($prune_shard_name, 0777);
        @chmod($feed_shard_name, 0777);
        if(!$completed) {
            file_put_contents($prune_info_file, serialize($info));
            chmod($prune_info_file, 0777);
            return false;
        }

        @rename($prune_shard_name, $feed_shard_name);
        @chmod($feed_shard_name, 0777);
        $sql = "DELETE FROM FEED_ITEM WHERE PUBDATE < '$too_old'";
        $db->execute($sql);
        @unlink($prune_info_file);

        return true;
    }

    /**
     * Adds words extracted feed data in $item to $feed_shard and
     * adds $item to db if it isn't already there
     *
     * @param array $item data from a single news feed item
     * @param object $feed_shard index_shard to stored extracted words in
     * @param string $source_name string name of the news feed $item was found
     *  on
     * @param int $age how many seconds old records should be ignored
     * @param string $lang locale-tag of the news feed
     * @return bool whether an item was added
     */
    function addFeedItemIfNew($item, $feed_shard, $source_name, $lang, $age)
    {
        if(!isset($item["link"]) || !isset($item["title"]) ||
            !isset($item["description"])) return false;
        if(!isset($item["guid"]) || $item["guid"] == "") {
            $item["guid"] = crawlHash($item["link"]);
        } else {
            $item["guid"] = crawlHash($item["guid"]);
        }
        $raw_guid = unbase64Hash($item["guid"]);
        if(!isset($item["pubDate"]) || $item["pubDate"] == "") {
            $item["pubDate"] = time();
        } else {
            $item["pubDate"] = strtotime($item["pubDate"]);
        }
        if(time() - $item["pubDate"] > $age) {
            return false;
        }
        $sql = "SELECT COUNT(*) AS NUMBER FROM FEED_ITEM WHERE GUID=".
            "'{$item["guid"]}'";
        $db = $this->db;
        $result = $db->execute($sql);
        if($result) {
            $row = $db->fetchArray($result);
            if($row["NUMBER"] > 0) {
                return false;
            }
        } else {
            return false;
        }
        $sql = "INSERT INTO FEED_ITEM VALUES ('{$item['guid']}',
            '".$db->escapeString($item['title'])."', '".
            $db->escapeString($item['link'])."', '".
            $db->escapeString($item['description'])."',
            '{$item['pubDate']}',
            '".$db->escapeString($source_name)."')";
        $result = $db->execute($sql);
        if(!$result) return false;
        $phrase_string = $item["title"] . " ". $item["description"];
        $word_lists = PhraseParser::extractPhrasesInLists(
            $phrase_string, $lang);
        $doc_keys = crawlHash($item["link"], true) .
            $raw_guid."d". substr(crawlHash(
            UrlParser::getHost($item["link"])."/",true), 1);
        $meta_ids = $this->calculateMetas($lang, $item['pubDate'],
            $source_name, $item["guid"]);
        $feed_shard->addDocumentWords($doc_keys, $item['pubDate'], $word_lists,
            $meta_ids, PhraseParser::$materialized_metas, true, false);
        return true;
    }

    /**
     *  Used to calculate the meta words for RSS feed items
     *
     *  @param string $lang the locale_tag of the feed item
     *  @param int $pubdate UNIX timestamp publication date of item
     *  @param string $source_name the name of the news feed
     *  @param string $guid the guid of the news item
     *
     *  @return array $meta_ids meta words found
     */
    function calculateMetas($lang, $pubdate, $source_name, $guid)
    {
        $meta_ids = array("media:news", "media:news:".urlencode($source_name),
            "guid:".strtolower($guid));
        $meta_ids[] = 'date:'.date('Y', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m-d', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m-d-H', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m-d-H-i', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m-d-H-i-s', $pubdate);
        if($lang != "") {
            $lang_parts = explode("-", $lang);
            $meta_ids[] = 'lang:'.$lang_parts[0];
            if(isset($lang_parts[1])){
                $meta_ids[] = 'lang:'.$lang;
            }
        }
        return $meta_ids;
    }
}
 ?>
