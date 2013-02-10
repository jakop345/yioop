<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *Loads base class for iterating
 */
require_once BASE_DIR.
    '/lib/archive_bundle_iterators/text_archive_bundle_iterator.php';

/**
 * Used to iterate through the records of a collection of warc files stored in
 * a WebArchiveBundle folder. Warc is the newer file format of the 
 * Internet Archive and other for digital preservation:
 * http://www.digitalpreservation.gov/formats/fdd/fdd000236.shtml
 * http://archive-access.sourceforge.net/warc/
 * Iteration is done for the purpose making an index of these records
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
class WarcArchiveBundleIterator extends TextArchiveBundleIterator
    implements CrawlConstants
{
    /**
     * Creates an warc archive iterator with the given parameters.
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
            'file_extension' => 'warc.gz',
            'encoding' => 'UTF-8',
            'start_delimiter' => '/WARC/');
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
        $indexable_records = array('response', 'resource');
        do {
            $page_info = $this->getWarcHeaders();
            if($page_info == NULL || !isset(
                $page_info[self::SIZE])) {
                return NULL;
            }
            $length = intval($page_info[self::SIZE]);
            $page_info[self::SIZE] = $length;
            $header_and_page = ltrim($this->gzFileRead($length + 2));
            $this->gzFileGets();
            $this->gzFileGets();
            if(!$header_and_page) { return NULL; }
        } while(!in_array($page_info['warc-type'], $indexable_records) ||
            substr($page_info[self::URL], 0, 4) == 'dns:');
                //ignore warcinfo, request, metadata, revisit, etc. records
        if($no_process) { 
            return $header_and_page; 
        }
        unset($page_info['line']);
        unset($page_info['warc-type']);
        $site = $page_info;
        $site_contents = FetchUrl::parseHeaderPage($header_and_page);
        $site = array_merge($site, $site_contents);
        $site[self::HASH] = FetchUrl::computePageHash($site[self::PAGE]);
        $site[self::WEIGHT] = 1;
        if(!isset($site[self::TYPE])) {
            $site[self::TYPE] = "text/plain";
        }
        return $site;
    }

    /**
     * Used to parse the header portion of a WARC record
     *
     * @return array fields of WARC record mapped to their Yioop equivalents.
     *      Also, return 'line' the last line and 'warc-type' the kind of
     *      record.
     */
    function getWarcHeaders()
    {
        $warc_headers = array();
        $warc_fields = array( 'warc-type' => 'warc-type', 
            'warc-target-uri' => self::URL, 'warc-date' => self::TIMESTAMP,
            'warc-ip-address' => self::IP_ADDRESSES,
            'content-length' => self::SIZE, 'warc-record-id' => self::WARC_ID,
            'warc-trec-id' => self::WARC_ID);
        $field = "start-record";
        do {
            $line = $this->gzFileGets();
            if(substr($line, 0, 5) == "WARC/") {
                continue;
            }
            if(trim($line) == "") { return NULL; }
            $warc_headers['line'] = $line;
            list($field, $value) = explode(":", $line, 2);
            $field = strtolower(trim($field));
            $value = trim($value);
            if(isset($warc_fields[$field])) {
                if($field == 'warc-date') {
                    $value = date("U", strtotime($value));
                }
                if($warc_fields[$field] == self::IP_ADDRESSES) {
                    $warc_headers[self::IP_ADDRESSES] = array($value);
                } else {
                    $warc_headers[$warc_fields[$field]] = $value;
                }
            }
        } while(strcmp($field, 'content-length') != 0);
        return $warc_headers;
    }
}
?>
