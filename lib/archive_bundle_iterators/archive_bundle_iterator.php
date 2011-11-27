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
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Abstract class used to model iterating documents indexed in 
 * an WebArchiveBundle or set of such bundles. 
 *
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
abstract class ArchiveBundleIterator implements CrawlConstants
{


    /**
     * Timestamp of the archive that is being iterated over
     * @var int
     */
     var $iterate_timestamp;

    /**
     * Timestamp of the archive that is being used to store results in
     * @var int
     */
     var $result_timestamp;

    /**
     * Whether or not the iterator still has more documents
     * @var bool
     */
     var $end_of_iterator;

    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return mixed a 4-bit number or false if iterator doesn't uses default
     *      ranking method
     */
    abstract function weight(&$site);
    /**
     * Gets the next $num many docs from the iterator
     * @param int $num number of docs to get
     * @return array associative arrays for $num pages
     */
    abstract function nextPages($num);

    /**
     * Resets the iterator to the start of the archive bundle
     */
    abstract function reset();
}
?>
