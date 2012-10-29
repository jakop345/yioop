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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
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
     *  @param string $sourcetype the particular kind of media source to return
     *      for example, video
     *  @return array a list of web sites which are either video or news sites
     */
    function getMediaSources($sourcetype = "")
    {
        $sources = array();
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT * FROM MEDIA_SOURCE";
        if($sourcetype !="") {
            $sql .= " WHERE TYPE='$sourcetype'";
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
            "WHERE LOCALE_TAG = '$locale_tag' LIMIT 1";
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
                " WHERE TRANSLATION_ID=$id AND LOCALE_ID=$locale_id LIMIT 1"; 
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
                $subsearches[$i]['SUBSEARCH_NAME'] = 
                    $subsearches[$i]['LOCALE_STRING'];
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
     */
    function updateFeedItems()
    {
        $feed_shard_name = WORK_DIRECTORY."/feeds/index";
        if(!file_exists($feed_shard_name)) {
            $feed_shard =  new IndexShard($feed_shard_name);
        } else {
            $feed_shard = IndexShard::load($feed_shard_name);
        }
        $feeds = $this->getMediaSources("rss");

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
            $rss_elements = array("title", "description", "link", "guid",
                "pubDate");
            foreach($nodes as $node) {
                $item = array();
                foreach($rss_elements as $element) {
                    $tag_node = $node->getElementsByTagName(
                            $element)->item(0);
                    $element_text = (is_object($tag_node)) ?
                        $tag_node->nodeValue: "";
                    $item[$element] = strip_tags($element_text);
                }
                $this->addFeedItemIfNew($item, $feed_shard, 
                    $feed['NAME'], $lang);
            }
        }
        $feed_shard->save();
    }

    /**
     * Deletes all feed items with a publication date older than $age
     * seconds ago from FEED_ITEM
     *
     * @param int $age how many seconds old records should be deleted
     */
    function deleteFeedItems($age)
    {
        // delete old inverted index and rows older than age
        $feed_shard_name = WORK_DIRECTORY."/feeds/index";
        if(file_exists($feed_shard_name)) {
            unlink($feed_shard_name);
        }
        $too_old = time() - $age;
        $pre_feeds = $this->getMediaSources("rss");
        if(!$pre_feeds) return false;
        $feeds = array();
        foreach($pre_feeds as $pre_feed) {
            if(isset($pre_feed['SOURCE_NAME'])) continue;
            $feed[$pre_feed['SOURCE_NAME']] = $pre_feed;
        }
        $db = $this->db;
        $sql = "DELETE FROM FEED_ITEM WHERE PUBDATE < '$too_old'";
        $db->execute($sql);
        // we now rebuild the inverted index with the remaining items
        $feed_shard =  new IndexShard($feed_shard_name);
        $sql = "SELECT * FROM FEED_ITEM";
        $result = $db->execute($sql);

        if($result) {
            while($item = $db->fetchArray($result)) {
                if(!isset($item['SOURCE_NAME'])) continue;
                $source_name = $item['SOURCE_NAME'];
                if(isset($feed[$item['SOURCE_NAME']])) {
                    $lang = $feed[$item['SOURCE_NAME']]['LANGUAGE'];
                } else {
                    $lang = "";
                }
                $phrase_string = $item["TITLE"] . " ". $item["DESCRIPTION"];
                $word_lists = PhraseParser::extractPhrasesInLists(
                    $phrase_string, $lang, true);
                $raw_guid = unbase64Hash($item["GUID"]);
                $doc_keys = crawlHash($item["LINK"], true) . 
                    $raw_guid."d". substr(crawlHash(
                    UrlParser::getHost($item["LINK"])."/",true), 1);
                $meta_ids = array("media:news", "media:news:".
                    urlencode($source_name));
                if($lang != "") {
                    $lang_parts = explode("-", $lang);
                    $meta_ids[] = 'lang:'.$lang_parts[0];
                    if(isset($lang_parts[1])){
                        $meta_ids[] = 'lang:'.$lang;
                    }
                }
                $feed_shard->addDocumentWords($doc_keys, $item['PUBDATE'], 
                    $word_lists, $meta_ids, true, false);
            }
        }
        $feed_shard->save();
    }

    /**
     * Adds words extracted feed data in $item to $feed_shard and
     * adds $item to db if it isn't already there
     *
     * @param array $item data from a single news feed item
     * @param object &$feed_shard index_shard to stored extracted words in
     * @param string $source_name string name of the news feed $item was found
     *  on
     * @param string $lang locale-tag of the news feed
     */
    function addFeedItemIfNew($item, &$feed_shard, $source_name, $lang)
    {
        if(!isset($item["link"]) || !isset($item["title"]) ||
            !isset($item["description"])) return;
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
        $sql = "SELECT COUNT(*) AS NUMBER FROM FEED_ITEM WHERE GUID=".
            "'{$item["guid"]}'";
        $db = $this->db;
        $result = $db->execute($sql);
        if($result) {
            $row = $db->fetchArray($result);
            if($row["NUMBER"] > 0) {
                return;
            }
        } else {
            return;
        }
        $sql = "INSERT INTO FEED_ITEM VALUES ('{$item['guid']}', 
            '".$db->escapeString($item['title'])."', '".
            $db->escapeString($item['link'])."', '".
            $db->escapeString($item['description'])."', 
            '{$item['pubDate']}', 
            '".$db->escapeString($source_name)."')";
        $result = $db->execute($sql);
        if(!$result) return;
        $phrase_string = $item["title"] . " ". $item["description"];
        $word_lists = PhraseParser::extractPhrasesInLists(
            $phrase_string, $lang, true);
        $doc_keys = crawlHash($item["link"], true) . 
            $raw_guid."d". substr(crawlHash(
            UrlParser::getHost($item["link"])."/",true), 1);
        $meta_ids = array("media:news", "media:news:".urlencode($source_name));
        if($lang != "") {
            $lang_parts = explode("-", $lang);
            $meta_ids[] = 'lang:'.$lang_parts[0];
            if(isset($lang_parts[1])){
                $meta_ids[] = 'lang:'.$lang;
            }
        }
        $feed_shard->addDocumentWords($doc_keys, $item['pubDate'], $word_lists,
            $meta_ids, true, false);
    }
}
 ?>
