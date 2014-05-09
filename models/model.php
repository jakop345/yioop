<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Used to manage database connections */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";

/** Used to handle curl and multi curl page requests */
require_once BASE_DIR."/lib/fetch_url.php";

/**  For crawlHash function  */
require_once BASE_DIR."/lib/utility.php";

/** For checking if a url is on localhost */
require_once BASE_DIR."/lib/url_parser.php";

/** Used to load common constants among crawl components */
require_once BASE_DIR."/lib/crawl_constants.php";

define("SCORE_PRECISION", 4);

define("TITLE_LENGTH", 20);
define("MAX_TITLE_LENGTH", 20);

define("SNIPPET_LENGTH_LEFT", 60);
define("SNIPPET_LENGTH_RIGHT", 50);
define("MIN_SNIPPET_LENGTH", 100);

/**
 *
 * This is a base class for all models
 * in the SeekQuarry search engine. It provides
 * support functions for formatting search results
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage model
 */
class Model implements CrawlConstants
{

    /**
     * Default maximum character length of a search summary
     */
    const DEFAULT_DESCRIPTION_LENGTH = 150;

    /** Reference to a DatasourceManager
     *  @var object
     */
    var $db;
    /** Name of the search engine database
     *  @var string
     */
    var $db_name;
    /**
     * Associative array of page summaries which might be used to
     * override default page summaries if set.
     * @var array
     */
    var $edited_page_summaries = NULL;
    /**
     * @var array
     */
    var $any_fields = array();
    /**
     * @var array
     */
    var $search_table_column_map = array();
    /**
     * Sets up the database manager that will be used and name of the search
     * engine database
     *
     * @param string $db_name  the name of the database for the search engine
     * @param bool $connect whether to connect to the database by default
     *      after making the datasource class
     */
    function __construct($db_name = DB_NAME, $connect = true)
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();
        if($connect) {
            $this->db->connect();
        }
        $this->db_name = $db_name;
    }


    /**
     * Given an array page summaries, for each summary extracts snippets which
     * are related to a set of search words. For each snippet, bold faces the
     * search terms, and then creates a new summary array.
     *
     * @param array $results web pages summaries (these in turn are
     *      arrays!)
     * @param array $words keywords (typically what was searched on)
     * @param int $description_length length of the description
     * @return array summaries which have been snippified and bold faced
     */
    function formatPageResults($results, $words = NULL, $description_length =
        self::DEFAULT_DESCRIPTION_LENGTH)
    {
        if(isset($results['PAGES'])) {
            $pages = $results['PAGES'];
            $num_pages = count($pages);
        } else {
            $output['TOTAL_ROWS'] = 0;
            $output['PAGES'] = NULL;
            return;
        }
        for($i = 0; $i < $num_pages; $i++) {
            $page = $pages[$i];

            if($this->edited_page_summaries != NULL) {

                $url_parts = explode("|", $page[self::URL]);
                if(count($url_parts) > 1) {
                    $url = trim($url_parts[1]);
                } else {
                    $url = $page[self::URL];
                }

                $hash_url = crawlHash($url, true);
                if(isset($this->edited_page_summaries[$hash_url])) {
                    $summary = $this->edited_page_summaries[$hash_url];
                    $page[self::URL] = $url;
                    foreach(array(self::TITLE, self::DESCRIPTION) as $field) {
                        if(isset($summary[$field])) {
                            $page[$field] = $summary[$field];
                        }
                    }

                }
            }
            if(!isset($page[self::TITLE])) {
                $page[self::TITLE] = "";
            }
            $page[self::TITLE] = strip_tags($page[self::TITLE]);

            if(strlen($page[self::TITLE]) == 0 ) {
                $offset =
                    min(mb_strlen($page[self::DESCRIPTION]), TITLE_LENGTH);
                $end_title = mb_strpos($page[self::DESCRIPTION], " ", $offset);
                $ellipsis = "";
                if($end_title > TITLE_LENGTH) {
                    $ellipsis = "...";
                    if($end_title > MAX_TITLE_LENGTH) {
                        $end_title = MAX_TITLE_LENGTH;
                    }
                }
                $page[self::TITLE] =
                    mb_substr(strip_tags($page[self::DESCRIPTION]), 0,
                        $end_title) . $ellipsis;
                //still no text revert to url
                if(strlen($page[self::TITLE]) == 0 && isset($page[self::URL])) {
                    $page[self::TITLE] = $page[self::URL];
                }
            }
            // do a little cleaning on text
            if($words != NULL) {
                $page[self::TITLE] =
                    $this->boldKeywords($page[self::TITLE], $words);

                if(!isset($page[self::IS_FEED])) {
                    $page[self::DESCRIPTION] =
                        $this->getSnippets(strip_tags($page[self::DESCRIPTION]),
                            $words, $description_length);
                }
                $page[self::DESCRIPTION] =
                    $this->boldKeywords($page[self::DESCRIPTION], $words);
            } else {
                $page[self::DESCRIPTION] =
                    mb_substr(strip_tags(
                        $page[self::DESCRIPTION]), 0, $description_length);
            }

            $page[self::SCORE] = mb_substr($page[self::SCORE], 0,
                SCORE_PRECISION);

            $pages[$i] = $page;

        }

        $output['TOTAL_ROWS'] = $results['TOTAL_ROWS'];
        $output['PAGES'] = $pages;

        return $output;
    }


    /**
     * Given a string, extracts a snippets of text related to a given set of
     * key words. For a given word a snippet is a window of characters to its
     * left and right that is less than a maximum total number of characters.
     * There is also a rule that a snippet should avoid ending in the middle of
     * a word
     *
     *  @param string $text haystack to extract snippet from
     *  @param array $words keywords used to make look in haystack
     *  @param string $description_length length of the description desired
     *  @return string a concatenation of the extracted snippets of each word
     */
    function getSnippets($text, $words, $description_length)
    {
        if(mb_strlen($text) < $description_length) {
            return $text;
        }

        $ellipsis = "";
        $out_words = array();
        foreach($words as $word) {
            $out_words = array_merge($out_words, explode(" ", $word));
        }
        $words = array_unique($out_words);
        $start_words = array_filter($words);
        $snippet_string = "";
        $snippet_hash = array();
        $text_sources = explode(".. ", $text);
        foreach($text_sources as $text_source) {
            $len = mb_strlen($text_source);
            $offset = 0;
            $words = $start_words;
            if(strlen($text_source) < MIN_SNIPPET_LENGTH) {
                if(!isset($snippet_hash[$text_source])) {
                    $found = false;
                    foreach($words as $word) {
                        if(mb_stristr($text_source, $word) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if($found) {
                        $snippet_string .= $ellipsis. $text_source;
                        $ellipsis = " ... ";
                        $snippet_hash[$text_source] = true;
                        if(mb_strlen($snippet_string)>= $description_length) {
                            break;
                        }
                    }
                }
                continue;
            }
            $word_locations = array();
            foreach($words as $word) {
                $qword = "/".preg_quote($word)."/ui";
                preg_match_all($qword, $text_source, $positions,
                    PREG_OFFSET_CAPTURE);

                if(isset($positions[0]) && is_array($positions[0])) {
                    $positions = $positions[0];
                    foreach($positions as $position) {
                        $word_locations[] = $position[1];
                    }
                }

            }
            $high = 0;
            sort($word_locations);
            foreach($word_locations as $pos) {
                if($pos < $high) continue;
                $pre_low = max($pos - SNIPPET_LENGTH_LEFT, 0);
                if($pre_low < mb_strlen($text_source)){
                    $low = mb_stripos($text_source, " ", $pre_low);
                }
                if($low > $pos) {
                    $low = $pre_low;
                }
                $pre_high = min($pos + SNIPPET_LENGTH_RIGHT, $len);
                $high = mb_stripos($text_source, " ",
                    max(min($pre_high - 10, 0), min($pos, $len)));
                if($high > $pre_high + 10){
                    $high = $pre_high;
                }
                $cur_snippet = trim(
                    mb_substr($text_source, $low, $high - $low));
                if(!isset($snippet_hash[$cur_snippet])) {
                    $snippet_string .= $ellipsis. $cur_snippet;
                    $ellipsis = " ... ";
                    $snippet_hash[$cur_snippet] = true;
                }
                if(strlen($snippet_string) >= $description_length) break 2;
            }
        }
        return $snippet_string;
    }


    /**
     *  Given a string, wraps in bold html tags a set of key words it contains.
     *
     *  @param string $text haystack string to look for the key words
     *  @param array $words an array of words to bold face
     *
     *  @return string  the resulting string after boldfacing has been applied
     */
    function boldKeywords($text, $words)
    {
        $words = array_unique($words);
        foreach($words as $word) {
            if($word != "" && !stristr($word, "/")) {
                $pattern = '/('.preg_quote($word).')/i';
                $new_text = preg_replace($pattern, '<b>$1</b>', $text);
                $text = $new_text;
            }
        }

        return $text;
    }

    /**
     * Gets a list of all DBMS that work with the search engine
     *
     *  @return array Names of availabledatasources
     */
    function getDbmsList()
    {
        $list = array();
        $data_managers = glob(BASE_DIR.'/models/datasources/*_manager.php');

        foreach($data_managers as $data_manager) {
            $dbms =
                substr($data_manager,
                    strlen(BASE_DIR.'/models/datasources/'), -
                    strlen("_manager.php"));
            if($dbms != 'datasource') {
                $list[] = $dbms;
            }
        }

        return $list;
    }

    /**
     * Returns whether the provided dbms needs a login and password or not
     * (sqlite or sqlite3)
     *
     * @param string $dbms the name of a database management system
     * @return bool true if needs a login and password; false otherwise
     */
    function loginDbms($dbms)
    {
        return !in_array($dbms, array("sqlite", "sqlite3"));
    }


    /**
     * Used to determine if an action involves just one yioop instance on
     * the current local machine or not
     *
     * @param array $machine_urls urls of yioop instances to which the action
     *      applies
     * @param string $index_timestamp if timestamp exists checks if the index
     *      has declared itself to be a no network index.
     * @return bool whether it involves a single local yioop instance (true)
     *      or not (false)
     */
    function isSingleLocalhost($machine_urls, $index_timestamp = -1)
    {
        if($index_timestamp >= 0) {
            $index_archive_name= self::index_data_base_name.$index_timestamp;
            if(file_exists(
                CRAWL_DIR."/cache/$index_archive_name/no_network.txt")){
                return true;
            }
        }
        return count($machine_urls) <= 1 &&
                    UrlParser::isLocalhostUrl($machine_urls[0]);
    }

    /**
     *  Used to get the translation of a string_id stored in the database to
     *  the given locale.
     *
     *  @param string $string_id id to translate
     *  @param string $locale_tag to translate to
     *  @return mixed translation if found, $string_id, otherwise
     */
    function translateDb($string_id, $locale_tag)
    {
        static $lookup = array();
        $db = $this->db;
        if(isset($lookup[$string_id])) {
            return $lookup[$string_id];
        }
        $sql = "
            SELECT TL.TRANSLATION AS TRANSLATION
            FROM TRANSLATION T, LOCALE L, TRANSLATION_LOCALE TL
            WHERE T.IDENTIFIER_STRING = :string_id AND
                L.LOCALE_TAG = :locale_tag AND
                L.LOCALE_ID = TL.LOCALE_ID AND
                T.TRANSLATION_ID = TL.TRANSLATION_ID " . $db->limitOffset(1);
        $result = $db->execute($sql,
            array(":string_id" => $string_id, ":locale_tag" => $locale_tag));
        $row = $db->fetchArray($result);
        if(isset($row['TRANSLATION'])) {
            return $row['TRANSLATION'];
        }
        return $string_id;
    }

    /**
     *  Get the user_id associated with a given username
     *  (In base class as used as an internal method in both signin and
     *   user models)
     *
     *  @param string $username the username to look up
     *  @return string the corresponding userid
     */
    function getUserId($username)
    {
        $db = $this->db;
        $sql = "SELECT USER_ID FROM USERS WHERE
            UPPER(USER_NAME) = UPPER(?) ". $db->limitOffset(1);
        $result = $db->execute($sql, array($username));
        if(!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        $user_id = $row['USER_ID'];
        return $user_id;
    }

    /**
     *  Creates the WHERE and ORDER BY clauses for a query of a Yioop
     *  table such as USERS, ROLE, GROUP, which have associated search web
     *  forms. Searches are case insensitive
     *
     *  @param array $search_array each element of this is a quadruple
     *      name of a field, what comparison to perform, a value to check,
     *      and an order (ascending/descending) to sort by
     *  @param array $any_fields these fields if present in search array
     *      but with value "0" will be skipped as part of the where clause
     *      but will be used for order by clause
     *  @return array string for where clause, string for order by clause
     */
    function searchArrayToWhereOrderClauses($search_array,
        $any_fields = array('status'))
    {
        $db = $this->db;
        $where = "";
        $order_by = "";
        $order_by_comma = "";
        $where_and = "";
        $sort_types = array("ASC", "DESC");
        foreach($search_array as $row) {
            $field_name = $this->search_table_column_map[$row[0]];
            $comparison = $row[1];
            $value = $row[2];
            $sort_dir = $row[3];
            if($value != "" && (!in_array($row[0], $any_fields)
                || $value != "0")) {
                if($where == "") {
                    $where = " WHERE ";
                }
                $where .= $where_and;
                switch($comparison) {
                    case "=":
                         $where .= "$field_name='".
                            $db->escapeString($value)."'";
                    break;
                    case "!=":
                         $where .= "$field_name!='".
                            $db->escapeString($value)."'";
                    break;
                    case "CONTAINS":
                         $where .= "UPPER($field_name) LIKE UPPER('%".
                            $db->escapeString( $value)."%')";
                    break;
                    case "BEGINS WITH":
                         $where .= "UPPER($field_name) LIKE UPPER('".
                            $db->escapeString( $value)."%')";
                    break;
                    case "ENDS WITH":
                         $where .= "UPPER($field_name) LIKE UPPER('%".
                            $db->escapeString( $value)."')";
                    break;
                }
                $where_and = " AND ";
            }
            if(in_array($sort_dir, $sort_types)) {
                if($order_by == "") {
                    $order_by = " ORDER BY ";
                }
                $order_by .= $order_by_comma.$field_name." ".$sort_dir;
                $order_by_comma = ", ";
            }
        }
        return array($where, $order_by);
    }

    /**
     *  Gets a range of rows which match the procided search criteria from
     *  $th provided table
     *
     * @param string $tables
     * @param int $limit
     * @param int $num
     * @param int &$total_rows
     * @param array $search_array
     * @param array $args
     * @return array
     */
    function getRows($limit = 0, $num = 100, &$total,
        $search_array = array(), $args = NULL)
    {
        $db = $this->db;
        $tables = $this->fromCallback($args);
        $limit = $db->limitOffset($limit, $num);
        list($where, $order_by) =
            $this->searchArrayToWhereOrderClauses($search_array,
            $this->any_fields);
        $more_conditions = $this->whereCallback($args);
        if($more_conditions) {
            $add_where = " WHERE ";
            if($where != "") {
                $add_where = " AND ";
            }
            $where .= $add_where. $more_conditions;
        }
        $sql = "SELECT COUNT(*) AS NUM FROM $tables $where";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $total = $row['NUM'];
        $select_columns = $this->selectCallback($args);
        $sql = "SELECT $select_columns FROM ".
            "$tables $where $order_by $limit";
        $result = $db->execute($sql);
        $i = 0;
        $row = array();
        $row_callback = false;
        while($rows[$i] = $db->fetchArray($result)) {
            $rows[$i] = $this->rowCallback($rows[$i], $args);
            $i++;
        }
        unset($rows[$i]); //last one will be null
        $rows = $this->postQueryCallback($rows);
        return $rows;
    }

    /**
     *  @param mixed $args
     */
    function selectCallback($args)
    {
        return "*";
    }
    /**
     *  @param mixed $args
     */
    function fromCallback($args)
    {
        $name = strtoupper(get_class($this));
        $name = substr($name, 0, -strlen("Model"));
        return $name;
    }
    /**
     *  @param mixed $args
     */
    function whereCallback($args)
    {
        return "";
    }
    /**
     *  @param mixed $args
     */
    function rowCallback($row, $args)
    {
        return $row;
    }
    /**
     *  @param mixed $args
     */
    function postQueryCallback($rows)
    {
        return $rows;
    }
}
?>
