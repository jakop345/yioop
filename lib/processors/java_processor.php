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
 * @author Snigdha Rao Parvatneni
 * @package seek_quarry
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/** Register File Types We Handle*/
$add_extensions = array("java");
if(!isset($INDEXED_FILE_TYPES)) {
    $INDEXED_FILE_TYPES = array();
}
$INDEXED_FILE_TYPES = array_merge($INDEXED_FILE_TYPES, $add_extensions);
$add_types = array(
    "text/java" => "JavaProcessor"
);
if(!isset($PAGE_PROCESSORS)) {
    $PAGE_PROCESSORS = array();
}
$PAGE_PROCESSORS =  array_merge($PAGE_PROCESSORS, $add_types);
/**
 * Load the base class
 */
require_once BASE_DIR."/lib/processors/page_processor.php";
/**
 * So can extract parts of the URL if need to guess lang
 */
require_once BASE_DIR."/lib/url_parser.php";
/**
 * Parent class common to all processors used to create crawl summary
 * information  that involves basically text data
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class JavaProcessor extends TextProcessor
{
    /**
     * Computes a summary based on a text string of a document
     *
     * @param string $page text string of a document
     * @param string $url location the document came from, not used by
     *      JavaProcessor at this point. Some of its subclasses override
     *      this method and use url to produce complete links for
     *      relative links within a document
     *
     * @return array a summary of (title, description,links, and content) of
     *      the information in $page
     */
    function process($page, $url)
    {
        $summary = NULL;
        if(is_string($page)) {
            $summary[self::TITLE] = "";
            $summary[self::DESCRIPTION] = $page;
            $summary[self::LANG] = self::calculateLang(
                $summary[self::DESCRIPTION], $url);
            $summary[self::LINKS] = self::extractHttpHttpsUrls($page);
            $summary[self::PAGE] = "<html><body><div><pre>".
                strip_tags($page)."</pre></div></body></html>";
        }
        return $summary;
    }
    /**
     *  Tries to determine the language of the document by looking at the
     *  $sample_text and $url provided
     *  the language
     *  @param string $sample_text sample text to try guess the language from
     *  @param string $url url of web-page as a fallback look at the country
     *      to figure out language
     *
     *  @return string language tag for guessed language
     */
    static function calculateLang($sample_text = NULL, $url = NULL)
    {
        if($url != NULL) {
            $lang = UrlParser::getDocumentType($url);
        }
        return $lang;
    }
}
?>
