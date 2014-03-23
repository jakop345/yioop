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
 * @subpackage iterator
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *Loads base class for iterating
 */
require_once BASE_DIR.
    '/lib/archive_bundle_iterators/text_archive_bundle_iterator.php';

/**
 * Used to iterate through the records of a collection of arc files stored in
 * a WebArchiveBundle folder. Arc is the file format of the Internet Archive
 * http://www.archive.org/web/researcher/ArcFileFormat.php. Iteration would be
 * for the purpose making an index of these records
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
class ArcArchiveBundleIterator extends TextArchiveBundleIterator
    implements CrawlConstants
{
    /**
     * Creates an arc archive iterator with the given parameters.
     *
     * @param string $iterate_timestamp timestamp of the arc archive bundle to
     *      iterate  over the pages of
     * @param string $iterate_dir folder of files to iterate over
     * @param string $result_timestamp timestamp of the arc archive bundle
     *      results are being stored in
     * @param string $result_dir where to write last position checkpoints to
     */
    function __construct($iterate_timestamp, $iterate_dir,
        $result_timestamp, $result_dir)
    {
        $ini = array( 'compression' => 'gzip',
            'file_extension' => 'arc.gz',
            'encoding' => 'UTF-8',
            'start_delimiter' => '/dns|filedesc/');
        parent::__construct($iterate_timestamp, $iterate_dir,
            $result_timestamp, $result_dir, $ini);
    }

    /**
     * Gets the next doc from the iterator
     * @param bool $no_process do not do any processing on page data
     * @return array associative array for doc or string if no_process true
     */
    function nextPage($no_process = false)
    {
        if(!$this->checkFileHandle() ) { return NULL; }
        do {
            $page_info = $this->fileGets();
            if(trim($page_info) == "") { return NULL; }
            $info_parts = explode(" ", $page_info);
            $num_parts = count($info_parts);
            $length = intval($info_parts[$num_parts - 1]);

            $header_and_page = $this->fileRead($length + 1);
            if(!$header_and_page) { return NULL; }
        } while(substr($page_info, 0, 3) == 'dns' ||
            substr($page_info, 0, 8) == 'filedesc');
                //ignore dns entries in arc and ignore first record
        if($no_process) { return $header_and_page; }
        $site = array();
        $site[self::URL] = $info_parts[0];
        $site[self::IP_ADDRESSES] = array($info_parts[1]);
        $site[self::TIMESTAMP] = date("U", strtotime($info_parts[2]));
        $site[self::TYPE] = $info_parts[3];
        $site_contents = FetchUrl::parseHeaderPage($header_and_page);
        $site = array_merge($site, $site_contents);
        $site[self::HASH] = FetchUrl::computePageHash($site[self::PAGE]);
        $site[self::WEIGHT] = 1;
        return $site;
    }
}
?>