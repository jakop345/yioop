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
     *
     *  @param string $name
     *  @param string $source_type
     *  @param string $source_url
     *  @param string $thumb_url
     *  @return
     */
    function addMediaSource($name, $source_type, $source_url, $thumb_url)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "INSERT INTO MEDIA_SOURCE VALUES ('".time()."','".
            $this->db->escapeString($name)."','".
            $this->db->escapeString($source_type)."','".
            $this->db->escapeString($source_url)."','".
            $this->db->escapeString($thumb_url)."')";

        $this->db->execute($sql);
    }

    /**
     *
     * @param int $timestamp
     */
    function deleteMediaSource($timestamp)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "DELETE FROM MEDIA_SOURCE WHERE TIMESTAMP='$timestamp'";

        $this->db->execute($sql);
    }

    /**
     *
     * @return array
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
        while($subsearches[$i] = $this->db->fetchArray($result)) {
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
     *
     * @param string $folder_name
     * @param string $index_identifier
     * @param int $per_page
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
     *
     * @param string $folder_name
     */
    function deleteSubsearch($folder_name)
    {
        $this->db->selectDB(DB_NAME);
        $locale_string = "db_subsearch_".$folder_name;

        $sql = "SELECT * FROM TRANSLATION WHERE IDENTIFIER_STRING =".
            "'$locale_string'";
        $result = $this->db->execute($sql);
        if(isset($result)) {
            $row = $db->fetchArray($result);
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
     *
     */
    function updateFeeds()
    {
        $feed_shard_name = WORK_DIRECTORY."/feeds/index";
        if(!file_exists($feed_shard_name)) {
            $feed_shard =  new IndexShard($feed_shard_name);
        } else {
            $feed_shard = IndexShard::load($feed_shard_name);
        }
        $feeds = $this->getMediaSources("rss");

        $feeds = FetchUrl::getPages($feeds, false, 0, NULL, "SOURCE_URL",
            CrawlConstants::PAGE, true);
        $feed_items = array();

        foreach($feeds as $feed) {
            $dom = new DOMDocument();
            @$dom->loadXML($feed[CrawlConstants::PAGE]);
            $xpath = new DOMXPath($dom);
            $languages = $xpath->evaluate("/rss/channel/language");
            if($languages && is_object($languages) && 
                is_object($languages->item(0))) {
                $lang = $languages->item(0)->textContent;
            } else {
                $lang = DEFAULT_LOCALE;
            }
            $xpath = new DOMXPath($dom);
            $path = "/rss/channel/item";
            $nodes = $xpath->evaluate($path);
            $rss_elements = array("title", "description", "link", "guid",
                "pubDate");
            foreach($nodes as $node) {
                $elements = $node->childNodes;
                $item = array();
                foreach($elements as $element) {
                    if(in_array($element->nodeName, $rss_elements)) {
                        $item[$element->nodeName] = strip_tags(
                            $element->textContent);
                    }
                }
                $this->addFeedItemIfNew($item, $feed_shard, 
                    $feed['NAME'], $lang);
            }
        }
        $feed_shard->save();
    }

    /**
     *
     */
    function addFeedItemIfNew($item, &$feed_shard, $source_name, $lang)
    {
        if(!isset($item["link"]) || !isset($item["title"]) ||
            !isset($item["description"])) return;
        if(!isset($item["guid"])) {
            $item["guid"] = crawlHash($item["link"]);
        } else {
            $item["guid"] = crawlHash($item["guid"]);
        }
        $raw_guid = unbase64Hash($item["guid"]);
        if(!isset($item["pudDate"])) {
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
        $feed_shard->addDocumentWords($doc_keys, $item['pubDate'], $word_lists,
            $meta_ids, true, false);
    }
}
 ?>
