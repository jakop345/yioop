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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/** Loads the base class */
require_once BASE_DIR."/models/model.php";
/** IndexShards used to store feed indexes*/
require_once BASE_DIR."/lib/index_shard.php";
/** For text manipulation of feeds*/
require_once BASE_DIR."/lib/phrase_parser.php";
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
    const MAX_FEEDS_ONE_GO = 100;
    /**
     * Controls which tables and the names of tables
     * underlie the given model and should be used in a getRows call
     * As SourceModel is used for both media sources and subsearches.
     * The underlying table might be MEDIA_SOURCE or it might be SUBSEARCH.
     * The $args variable is a string which is assumed to say which.
     *
     * @param string $args if is "SUBSEARCH" then the SUBSEARCH table will
     *     be used by getRows rather than MEDIA_SOURCE.
     * @return string which table to use
     */
    function fromCallback($args = NULL)
    {
        if($args == "SUBSEARCH") {
            return "SUBSEARCH";
        }
        return "MEDIA_SOURCE";
    }
    /**
     * Returns a list of media sources such as (video, rss sites) and their
     * URL and thumb url formats, etc
     *
     * @param string $source_type the particular kind of media source to return
     *     for example, video
     * @param bool $has_no_feed_items if true returns only those items which
     *     have not feed_items associated with them.
     * @return array a list of web sites which are either video or news sites
     */
    function getMediaSources($source_type = "", $has_no_feed_items = false)
    {
        $db = $this->db;
        $sources = array();
        $params = array();
        $sql = "SELECT M.* FROM MEDIA_SOURCE M";
        if($source_type !="") {
            $sql .= " WHERE TYPE=:type";
            $params = array(":type" => $source_type);
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
        $result = $db->execute($sql, $params);
        while($sources[$i] = $db->fetchArray($result)) {
            $i++;
        }
        unset($sources[$i]); //last one will be null
        return $sources;
    }
    /**
     * Return the media source by the name of the source
     * @param string $timestamp of the media source to look up
     * @return array associative array with SOURCE_NAME, TYPE, SOURCE_URL,
     *     AUX_INFO, and LANGUAGE
     */
    function getMediaSource($timestamp)
    {
        $db = $this->db;
        $sql = "SELECT * FROM MEDIA_SOURCE WHERE TIMESTAMP = ?";
        $result = $db->execute($sql, array($timestamp));
        if(!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }
    /**
     * Used to add a new video, rss, html news, or other sources to Yioop
     *
     * @param string $name
     * @param string $source_type whether video, rss, etc
     * @param string $source_url url regex of resource (video) or actual
     *     resource (rss). Not quite a real regex you add {} to the
     *     location in the url where the name of the particular video
     *     should go http://www.youtube.com/watch?v={}&
     *     (anything after & is ignored, so between = and & will be matched
     *     as the name of a video)
     * @param string $aux_info regex of where to get thumbnails for videos
     *      based on match of $source_url, for example,
     *      http://img.youtube.com/vi/{}/2.jpg
     *      For html news feeds has xpaths to scrape news items from regex
     * @param string $language the locale tag for the media source (rss)
     */
    function addMediaSource($name, $source_type, $source_url, $aux_info,
        $language = DEFAULT_LOCALE)
    {
        $db = $this->db;
        $sql = "INSERT INTO MEDIA_SOURCE VALUES (?,?,?,?,?,?)";

        $db->execute($sql, array(time(), $name, $source_type, $source_url,
            $aux_info, $language));
    }
    /**
     * Used to update the fields stored in a MEDIA_SOURCE row according to
     * an array holding new values
     *
     * @param array $source_info updated values for a MEDIA_SOURCE row
     */
    function updateMediaSource($source_info)
    {
        $timestamp = $source_info['TIMESTAMP'];
        unset($source_info['TIMESTAMP']);
        unset($source_info['NAME']);
        $sql = "UPDATE MEDIA_SOURCE SET ";
        $comma ="";
        $params = array();
        foreach($source_info as $field => $value) {
            $sql .= "$comma $field=? ";
            $comma = ",";
            $params[] = $value;
        }
        $sql .= " WHERE TIMESTAMP=?";
        $params[] = $timestamp;
        $this->db->execute($sql, $params);
    }
    /**
     * Deletes the media source whose id is the given timestamp
     *
     * @param int $timestamp of media source to be deleted
     */
    function deleteMediaSource($timestamp)
    {
        $sql = "SELECT * FROM MEDIA_SOURCE WHERE TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        if($result) {
            $row = $this->db->fetchArray($result);
            if(isset($row['TYPE']) && $row['TYPE'] == "rss") {
                if($row['NAME'] != "") {
                    $sql = "DELETE FROM FEED_ITEM WHERE SOURCE_NAME=?";
                    $this->db->execute($sql, array($row['NAME']));
                }
            }
        }
        $sql = "DELETE FROM MEDIA_SOURCE WHERE TIMESTAMP=?";
        $this->db->execute($sql, array($timestamp));
    }
    /**
     * Returns a list of the subsearches used by the current Yioop instances
     * including their names translated to the current locale
     *
     * @return array associative array containing subsearch info name in locale,
     *    folder name, index, number of results per page
     */
    function getSubsearches()
    {
        $subsearches = array();
        $db = $this->db;
        $locale_tag = getLocaleTag();
        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = ? " . $db->limitOffset(1);
        $result = $db->execute($sql, array($locale_tag));
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
        $sub_sql = "SELECT TRANSLATION AS SUBSEARCH_NAME ".
            "FROM TRANSLATION_LOCALE ".
            " WHERE TRANSLATION_ID=? AND LOCALE_ID=? " . $db->limitOffset(1);
            // maybe do left join at some point
        while($subsearches[$i] = $db->fetchArray($result)) {
            $id = $subsearches[$i]["TRANSLATION_ID"];
            $result_sub =  $db->execute($sub_sql, array($id, $locale_id));
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
     * Return the media source by the name of the source
     * @param string $folder_name 
     * @return array 
     */
    function getSubsearch($folder_name)
    {
        $db = $this->db;
        $sql = "SELECT * FROM SUBSEARCH WHERE FOLDER_NAME = ?";
        $result = $db->execute($sql, array($folder_name));
        if(!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }
    /**
     * Adds a new subsearch to the list of subsearches. This are displayed
     * at the top od the Yioop search pages.
     *
     * @param string $folder_name name of subsearch in terms of urls
     *     (not translated name that appears in the subsearch bar)
     * @param string $index_identifier timestamp of crawl or mix to be
     *     used for results of subsearch
     * @param int $per_page number of search results per page when this
     *     subsearch is used
     */
    function addSubsearch($folder_name, $index_identifier, $per_page)
    {
        $db = $this->db;
        $locale_string = "db_subsearch_".$folder_name;
        $sql = "INSERT INTO SUBSEARCH VALUES (?, ?, ?, ?)";
        $db->execute($sql, array($locale_string, $folder_name,
            $index_identifier, $per_page));
        $sql = "INSERT INTO TRANSLATION VALUES (?, ?)";
        $db->execute($sql, array(time(), $locale_string));
    }
    /**
     * Used to update the fields stored in a SUBSEARCH row according to
     * an array holding new values
     *
     * @param array $search_info updated values for a SUBSEARCH row
     */
    function updateSubsearch($search_info)
    {
        $folder_name = $search_info['FOLDER_NAME'];
        unset($search_info['FOLDER_NAME']);
        $sql = "UPDATE SUBSEARCH SET ";
        $comma ="";
        $params = array();
        foreach($search_info as $field => $value) {
            $sql .= "$comma $field=? ";
            $comma = ",";
            $params[] = $value;
        }
        $sql .= " WHERE FOLDER_NAME=?";
        $params[] = $folder_name;
        $this->db->execute($sql, $params);
    }
    /**
     * Deletes a subsearch from the subsearch table and removes its
     * associated translations
     *
     * @param string $folder_name of subsearch to delete
     */
    function deleteSubsearch($folder_name)
    {
        $db = $this->db;
        $locale_string = "db_subsearch_".$folder_name;
        $sql = "SELECT * FROM TRANSLATION WHERE IDENTIFIER_STRING = ?";
        $result = $db->execute($sql, array($locale_string));
        if(isset($result)) {
            $row = $db->fetchArray($result);
            if(isset($row["TRANSLATION_ID"])) {
                $sql = "DELETE FROM TRANSLATION_LOCALE WHERE ".
                    "TRANSLATION_ID=?";
                $db->execute($sql, array($row["TRANSLATION_ID"]));
            }
        }
        $sql = "DELETE FROM SUBSEARCH WHERE FOLDER_NAME=?";
        $db->execute($sql, array($folder_name));

        $sql = "DELETE FROM TRANSLATION WHERE IDENTIFIER_STRING = ?";
        $db->execute($sql, array($locale_string));
    }
    /**
     * For each feed source downloads the feeds, checks which items are
     * not in the database, adds them. This method does not update
     * the inverted index shard.
     *
     * @param int $age how many seconds old records should be ignored
     * @return bool whether feed item update was successful
     */
    function updateFeedItems($age = self::ONE_WEEK)
    {
        $db = $this->db;
        $time = time();
        $feeds_one_go = self::MAX_FEEDS_ONE_GO;
        $feeds = array();
        $sql = "SELECT COUNT(*) AS CNT FROM MEDIA_SOURCE WHERE
            TYPE='rss' OR TYPE='html'";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $num_feeds = (isset($row['CNT'])) ? $row['CNT'] : 0;
        $num_bins = floor($num_feeds/$feeds_one_go) + 1;
        $hour = date('H', $time);
        $current_bin = $hour % $num_bins;
        $limit = $current_bin * $feeds_one_go;
        $limit = $db->limitOffset($limit, $feeds_one_go);
        $sql = "SELECT * FROM MEDIA_SOURCE WHERE (TYPE='rss'
             OR TYPE='html') $limit";
        $result = $db->execute($sql);
        $i = 0;
        while($feeds[$i] = $this->db->fetchArray($result)) {
            if($feeds[$i]['TYPE'] == 'html') {
                list($feeds[$i]['ITEM_PATH'], $feeds[$i]['TITLE_PATH'],
                    $feeds[$i]['DESCRIPTION_PATH'], $feeds[$i]['LINK_PATH']) =
                    explode("###", html_entity_decode($feeds[$i]['AUX_INFO']));
            }
            $i++;
        }
        unset($feeds[$i]); //last one will be null
        $feeds = FetchUrl::getPages($feeds, false, 0, NULL, "SOURCE_URL",
            CrawlConstants::PAGE, true, NULL, true);
        $feed_items = array();
        $sql = "UPDATE MEDIA_SOURCE SET LANGUAGE=? WHERE TIMESTAMP=?";
        foreach($feeds as $feed) {
            $is_html = ($feed['TYPE'] == 'html') ? true : false;
            $dom = new DOMDocument();
            if($is_html) {
                @$dom->loadHTML($feed[CrawlConstants::PAGE]);
            } else {
                @$dom->loadXML($feed[CrawlConstants::PAGE]);
            }
            $lang = "";
            if($feed['TYPE'] != 'html' &&
                !isset($feed["LANGUAGE"]) || $feed["LANGUAGE"] == "") {
                $languages = $dom->getElementsByTagName('language');
                if($languages && is_object($languages) &&
                    is_object($languages->item(0))) {
                    $lang = $languages->item(0)->textContent;
                    $db->execute($sql, array($lang, $feed['TIMESTAMP']));
                }
            } else if(isset($feed["LANGUAGE"]) && $feed["LANGUAGE"] != "") {
                $lang = $feed["LANGUAGE"];
            } else {
                $lang = DEFAULT_LOCALE;
            }
            if($is_html) {
                $nodes = $this->getTags($dom, $feed['ITEM_PATH']);
                $rss_elements = array("title" => $feed['TITLE_PATH'],
                    "description" => $feed['DESCRIPTION_PATH'],
                    "link" => $feed['LINK_PATH']);
            } else {
                $nodes = $dom->getElementsByTagName('item');
                $rss_elements = array("title" => "title",
                    "description" => "description", "link" =>"link",
                    "guid" => "guid", "pubDate" => "pubDate");
                if($nodes->length == 0) {
                    // maybe we're dealing with atom rather than rss
                    $nodes = $dom->getElementsByTagName('entry');
                    $rss_elements = array(
                        "title" => "title", "description" => "summary",
                        "link" => "link", "guid" => "id",
                        "pubDate" => "updated");
                }
            }
            crawlLog("Updating {$feed['NAME']}...");
            $num_added = 0;
            $num_seen = 0;
            foreach($nodes as $node) {
                $item = array();
                foreach($rss_elements as $db_element => $feed_element) {
                    crawlTimeoutLog("..still adding feed items to index.");
                    if($is_html) {
                        $tag_nodes = $this->getTags($node, $feed_element);
                        if(!isset($tag_nodes[0])) {
                            $tag_node = NULL;
                        } else {
                            $tag_node = $tag_nodes[0];
                        }
                        $element_text = (is_object($tag_node)) ?
                            $tag_node->textContent: "";
                    } else {
                        $tag_node = $node->getElementsByTagName(
                                $feed_element)->item(0);
                        $element_text = (is_object($tag_node)) ?
                            $tag_node->nodeValue: "";
                    }
                    if($db_element == "link" && $tag_node &&
                        ($element_text == "" || $is_html)) {
                        if($is_html) {
                            $element_text =
                               $tag_node->documentElement->getAttribute("href");
                        } else {
                            $element_text = $tag_node->getAttribute("href");
                        }
                        $element_text = UrlParser::canonicalLink($element_text,
                            $feed["SOURCE_URL"]);
                    }
                    $item[$db_element] = strip_tags($element_text);
                }
                $did_add = $this->addFeedItemIfNew($item, $feed['NAME'], $lang,
                    $age);
                if($did_add) {
                    $num_added++;
                }
                $num_seen++;
            }
            crawlLog("...added $num_added news items of $num_seen ".
                "on rss page.");
        }
        return true;
    }
    /**
     *
     * @param DOMDocument $dom
     * @param string $query
     */
    function getTags($dom, $query)
    {
        $dom_xpath = new DOMXPath($dom);
        $tags = $dom_xpath->query($query);
        $i = 0;
        $nodes = array();
        while($item = $tags->item($i)) {
            $tmp_dom = new DOMDocument;
            $tmp_dom->formatOutput = true;
            $node = $tmp_dom->importNode($item, true);
            $tmp_dom->appendChild($node);
            $nodes[] = $tmp_dom;
            $i++;
        }
        return $nodes;
    }
    /**
     * Copies all feeds items newer than $age to a new shard, then deletes
     * old index shard and database entries older than $age. Finally sets copied
     * shard to be active. If this method is going to take max_execution_time/2
     * it returns false, so an additional job can be schedules; otherwise
     * it returns true
     *
     * @param int $age how many seconds old records should be deleted
     * @return bool whether job executed to complete
     */
    function rebuildFeedShard($age)
    {
        $time = time();
        $feed_shard_name = WORK_DIRECTORY."/feeds/index";
        $prune_shard_name = WORK_DIRECTORY."/feeds/prune_index";
        $prune_shard =  new IndexShard($prune_shard_name);
        $too_old = $time - $age;
        if(!$prune_shard) {
            return false;
        }
        $pre_feeds = $this->getMediaSources("rss");
        $pre_feeds = array_merge($pre_feeds, $this->getMediaSources("html"));
        if(!$pre_feeds) { return false; }
        $feeds = array();
        foreach($pre_feeds as $pre_feed) {
            if(!isset($pre_feed['NAME'])) continue;
            $feeds[$pre_feed['NAME']] = $pre_feed;
        }
        $db = $this->db;
        // we now rebuild the inverted index with the remaining items
        $sql = "SELECT * FROM FEED_ITEM ".
            "WHERE PUBDATE >= ? ".
            "ORDER BY PUBDATE DESC";
        $result = $db->execute($sql, array($too_old));
        if($result) {
            $completed = true;
            crawlLog("..still deleting. Making new index of non-pruned items.");
            $i = 0;
            while($item = $db->fetchArray($result)) {
                crawlTimeoutLog("..have added %s non-pruned items to index.",
                    $i);
                $i++;
                if(!isset($item['SOURCE_NAME'])) { continue; }
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
            }
        }
        $prune_shard->save();
        @chmod($prune_shard_name, 0777);
        @chmod($feed_shard_name, 0777);
        @rename($prune_shard_name, $feed_shard_name);
        @chmod($feed_shard_name, 0777);
        $sql = "DELETE FROM FEED_ITEM WHERE PUBDATE < ?";
        $db->execute($sql, array($too_old));
    }
    /**
     * Adds $item to  FEED_ITEM table in db if it isn't already there
     *
     * @param array $item data from a single news feed item
     * @param string $source_name string name of the news feed $item was found
     * on
     * @param int $age how many seconds old records should be ignored
     * @param string $lang locale-tag of the news feed
     * @return bool whether an item was added
     */
    function addFeedItemIfNew($item, $source_name, $lang, $age)
    {
        if(!isset($item["link"]) || !isset($item["title"]) ||
            !isset($item["description"])) { return false; }
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
        $sql = "SELECT COUNT(*) AS NUMBER FROM FEED_ITEM WHERE GUID = ?";
        $db = $this->db;
        $result = $db->execute($sql, array($item["guid"]));
        if($result) {
            $row = $db->fetchArray($result);
            if($row["NUMBER"] > 0) {
                return false;
            }
        } else {
            return true;
        }
        $sql = "INSERT INTO FEED_ITEM VALUES (?, ?, ?, ?, ?, ?)";
        $result = $db->execute($sql, array($item['guid'],
            $item['title'], $item['link'], $item['description'],
            $item['pubDate'], $source_name));
        if(!$result) {
            return false;
        }
        return true;
    }
    /**
     * Used to calculate the meta words for RSS feed items
     *
     * @param string $lang the locale_tag of the feed item
     * @param int $pubdate UNIX timestamp publication date of item
     * @param string $source_name the name of the news feed
     * @param string $guid the guid of the news item
     *
     * @return array $meta_ids meta words found
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
