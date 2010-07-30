<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Loads in the common constant used by all classes related to crawling
 */
require_once BASE_DIR."/lib/crawl_constants.php";


/**
 * Base class common to all processors used to create crawl summary information 
 * that involves basically text data
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class TextProcessor implements CrawlConstants
{

    /**
     * Computes a summary based on a text string of a document
     *
     * @param string $page text string of a document
     * @param string $url location the document came from, not used by 
     *      TextProcessor at this point. Some of its subclasses override
     *      this method and use url to produce complete links for
     *      relative links within a document
     * @return array a summary of (title, description,links, and content) of 
     *      the information in $page
     */
    static function process($page, $url)
    {
        if(is_string($page)) {
            $summary[self::TITLE] = "";
            $summary[self::DESCRIPTION] = mb_substr($page, 0, 400);
            $summary[self::LINKS] = array();
            $summary[self::PAGE] = "<html><body><pre>$page</pre></body></html>";
        }
        return $summary;
    }

    /**
     * Gets the text between two tags in a document starting at the current
     * position.
     *
     * @param string $string document to extract text from
     * @param int $cur_pos current location to look if can extract text
     * @param string $start_tag starting tag that we want to extract after
     * @param string $end_tag ending tag that we want to extract until
     * @return array pair consisting of when in the document we are after
     *      the end tag, together with the data between the two tags
     */
    static function getBetweenTags($string, $cur_pos, $start_tag, $end_tag) 
    {
        $len = strlen($string);
        if(($between_start = strpos($string, $start_tag, $cur_pos)) === 
            false ) {
            return array($len, "");
        }

        $between_start  += strlen($start_tag);
        if(($between_end = strpos($string, $end_tag, $between_start)) === 
            false ) {
            $between_end = $len;
        }

        $cur_pos = $between_end + strlen($end_tag);

        $between_string = substr($string, $between_start, 
            $between_end - $between_start);
        return array($cur_pos, $between_string);

    }

}

?>
