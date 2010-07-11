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
 *
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
     *
     */
    public static function process($page, $url)
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
     *
     */
    public static function getBetweenTags($string, $cur_pos, $start_tag, $end_tag) 
    {
        $len = strlen($string);
        if(($between_start = strpos($string, $start_tag, $cur_pos)) === false ) {
            return array($len, "");
        }

        $between_start  += strlen($start_tag);
        if(($between_end = strpos($string, $end_tag, $between_start)) === false ) {
            $between_end = $len;
        }

        $cur_pos = $between_end + strlen($end_tag);

        $between_string = substr($string, $between_start, $between_end - $between_start);
        return array($cur_pos, $between_string);

    }

}

?>
