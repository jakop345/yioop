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
 * @author Tarun Ramaswamy tarun.pepira@gmail.com
 * @package seek_quarry
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Register File Types We Handle*/
$INDEXED_FILE_TYPES[] = "xlsx";
$PAGE_PROCESSORS["application/vnd.openxmlformats-officedocument.".
    "spreadsheetml.sheet"] = "XlsxProcessor";
/**
 * Load base class, if needed.
 */
require_once BASE_DIR."/lib/processors/text_processor.php";
/**
 * Load so can parse urls
 */
require_once BASE_DIR."/lib/url_parser.php";
/**
 * For reading potentially incomplete zip archive files
 */
require_once BASE_DIR."/lib/partial_zip_archive.php";
 /**
 * Used to create crawl summary information
 * for xlsx files
 *
 * @author Tarun Ramaswamy
 * @package seek_quarry
 * @subpackage processor
 */
class XlsxProcessor extends TextProcessor
{
    /**
     * Used to extract the title, description and links from
     * a xlsx file.
     *
     * @param string $page contents of xlsx file in zip format
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array  a summary of the contents of the page
     *
     */
    function process($page, $url)
    {
        $summary = NULL;
        // Open a zip archive
        $zip = new PartialZipArchive($page);
        //Count of the sheets in xlsx
        $file_count = 0;
        //Getting the title from xlsx file
        $buf = $zip->getFromName("docProps/app.xml");
        if($buf) {
           $dom = self::dom($buf);
           if($dom !== false) {
               // Get the title
               $summary[self::TITLE] = self::title($dom);
               $file_count = self::sheetCount($dom);
           }
        }
        //Getting the description from xlsx file
        $buf= $zip->getFromName("xl/sharedStrings.xml");
        if($buf) {
            $dom = self::dom($buf);
            if($dom !== false) {
                // Get the description
                $summary[self::DESCRIPTION] = self::description($dom);
            }
            //Getting the language from xlsx file
            $summary[self::LANG] =
                self::calculateLang($summary[self::DESCRIPTION], $url);
        }
        $summary[self::LINKS] = array();
        //Getting links from each worksheet
        for($i = 1; $i<= $file_count; $i++) {
            $buf= $zip->getFromName("xl/worksheets/_rels/sheet" . $i .
                ".xml.rels");
            if($buf) {
                $dom = self::dom($buf);
                if($dom !== false) {
                    // Get the links
                    $summary[self::LINKS] = array_merge(
                        $summary[self::LINKS], self::links($dom, $url));
                }
            }
        }
        return $summary;
    }
    /**
     * Return a document object based on a string containing the contents of
     * a xml file
     *
     * @param string $page   xml document
     *
     * @return object  document object
     */
    static function dom($page)
    {
        $dom = new DOMDocument();

        @$dom->loadXML($page);

        return $dom;
    }
    /**
     * Returns title of a xlsx file from each worksheet
     *
     * @param object $dom   a document object to extract a title from.
     * @return string  a title of the xlsx file
     *
     */
    static function title($dom)
    {
        $properties = $dom->getElementsByTagName("Properties");
        $title = "";
        foreach ($properties as $property) {
            $titles = $property->getElementsByTagName("TitlesOfParts");
            $title = $titles->item(0)->nodeValue;
        }
        return $title;
     }
    /**
     * Returns the count of worksheets in the xlsx file
     *
     * @param object $dom   a document object to extract a title from.
     * @return integer  number of worksheets in the xlsx file
     *
     */
    static function sheetCount($dom)
    {
        $count = 0;
        $properties = $dom->getElementsByTagName("Properties");
        foreach ($properties as $property) {
            $titles = $property->getElementsByTagName("TitlesOfParts");
            foreach ($titles as $vector) {
                $vt_vector = $vector->getElementsByTagName("vector");
                foreach($vt_vector as $vector_attribute) {
                    $count = $vector_attribute->getAttribute("size");
                }
            }
        }
        return $count;
    }
    /**
     * Returns descriptive text concerning a xlsx file based on its document
     * object
     *
     * @param object $dom a document object to extract a description from.
     * @return string a description of the slide
     */
    static function description($dom)
    {
        $xpath = new DOMXPath($dom);

        $sst_tag = $dom->getElementsByTagName("sst");
        $descriptions = "";
        foreach ($sst_tag as $sst_value) {
            $t_tag = $sst_value->getElementsByTagName("si");
            foreach ($t_tag as $t_value) {
                $si_value = $t_value->getElementsByTagName("t");
                $descriptions .= $si_value->item(0)->nodeValue;
            }
        }
        return $descriptions;
    }
    /**
     * Returns up to MAX_LINK_PER_PAGE many links from the supplied
     * dom object where links have been canonicalized according to
     * the supplied $site information.
     *
     * @param object $dom a document object with links on it
     * @param string $sit  a string containing a url
     *
     * @return array links from the $dom object
     */
    static function links($dom, $site)
    {
        $sites = array();
        $hyperlink = "http://schemas.openxmlformats.org/officeDocument/2006/".
            "relationships/hyperlink";
        $i = 0;
        $relationships = $dom->getElementsByTagName("Relationships");
        foreach ($relationships as $relationship) {
            $relations = $relationship->getElementsByTagName("Relationship");
            foreach ($relations as $relation) {
                if( strcmp( $relation->getAttribute('Type'),
                    $hyperlink) == 0 ) {
                    if($i < MAX_LINKS_TO_EXTRACT) {
                        $link = $relation->getAttribute('Target');
                        $url = UrlParser::canonicalLink(
                            $link, $site);
                        if(!UrlParser::checkRecursiveUrl($url)  &&
                            strlen($url) < MAX_URL_LEN) {
                            if(isset($sites[$url])) {
                                $sites[$url] .=" ".$link;
                            } else {
                                $sites[$url] = $link;
                            }
                            $i++;
                        }
                    }
                }
            }
        }
        return $sites;
    }
}
?>
