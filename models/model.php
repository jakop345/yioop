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

/** Used to manage database connections */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";

/** Used to handle curl and multi curl page requests */
require_once BASE_DIR."/lib/fetch_url.php";

/** Used to load common constants among crawl components */
require_once BASE_DIR."/lib/crawl_constants.php";

define("SCORE_PRECISION", 4);

define("TITLE_LENGTH", 20);
define("MAX_TITLE_LENGTH", 20);

define("SNIPPET_LENGTH_LEFT", 40);
define("SNIPPET_LENGTH_RIGHT", 30);
define("MIN_SNIPPET_LENGTH", 50);


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
    const DEFAULT_DESCRIPTION_LENGTH = 200;

    /** Reference to a DatasourceManager
     *  @var object
     */
    var $db;
    /** Name of the search engine database
     *  @var string
     */
    var $db_name;


    /**
     * Sets up the database manager that will be used and name of the search 
     * engine database
     *
     * @param string $db_name  the name of the database for the search engine
     */
    function __construct($db_name = DB_NAME) 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();

        $this->db->connect();
        $this->db_name = $db_name;

    }


    /**
     * Given an array page summarries, for each summary extracts snippets which 
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
                    substr(strip_tags($page[self::DESCRIPTION]), 0, $end_title).
                    $ellipsis;
                //still no text revert to url
                if(strlen($page[self::TITLE]) == 0 && isset($page[self::URL])) {
                    $page[self::TITLE] = $page[self::URL];
                }
            }

            if($words != NULL) {
                $page[self::TITLE] = 
                    $this->boldKeywords($page[self::TITLE], $words);
                $page[self::DESCRIPTION] = 
                    substr(strip_tags(
                        $page[self::DESCRIPTION]), 0, $description_length);

                $page[self::DESCRIPTION] = 
                    $this->getSnippets($page[self::DESCRIPTION], $words,
                        $description_length);
                $page[self::DESCRIPTION] = 
                    $this->boldKeywords($page[self::DESCRIPTION], $words);

            } else {
                $page[self::DESCRIPTION] = 
                    substr(strip_tags(
                        $page[self::DESCRIPTION]), 0, $description_length);
            }

            $page[self::SCORE] = substr($page[self::SCORE], 0, SCORE_PRECISION);
              
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
     *  @return string a concatenation of the extracted snippets of each word
     */
    function getSnippets($text, $words, $description_length)
    {
        $snippet_string = "";
        $ellipsis = "";
        $len = mb_strlen($text);
        $offset = 0;
        do
        {
            $word_locations = array();
            $new_offset = $offset;
            foreach($words as $word) {
                if($word != "" && $pos = mb_strpos($text, $word, $offset)) {
                    $word_locations[$pos] = $word;
                    if($new_offset < $pos) {
                        $new_offset = $pos;
                    }
                }
            }
            $offset = $new_offset + 1;
            ksort($word_locations);
            $i = 0;


            foreach($word_locations as $pos => $word) {
                $pre_low = ($pos >= SNIPPET_LENGTH_LEFT) ? 
                    $pos - SNIPPET_LENGTH_LEFT: 0;
                if(!($low = mb_strpos($text, " ", $pre_low))) {
                    $low = $pre_low;
                }

                $pre_high = ($pos + SNIPPET_LENGTH_RIGHT <= $len ) ? 
                    $pos + SNIPPET_LENGTH_RIGHT: $len;
                if(!($high = mb_strpos($text, " ", $pre_high))) {
                    $high = $pre_high;
                }

                if( strlen($snippet_string)  < $description_length) {
                    $snippet_string .= 
                        $ellipsis.mb_substr($text, $low, $high - $low);
                    $ellipsis = "...";
                }
            }
        } while(strlen($snippet_string) < $description_length && $offset < $len);

        if(strlen($snippet_string) < MIN_SNIPPET_LENGTH) {
            $snippet_string = $text;
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
                $pattern = '/('.$word.')/i';
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
}
?>
