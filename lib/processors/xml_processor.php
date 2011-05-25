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
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load base class, if needed.
 */
require_once BASE_DIR."/lib/processors/text_processor.php";

/**
 * If XML turns out to be RSS ...
 */
require_once BASE_DIR."/lib/processors/rss_processor.php";

/**
 * If XML turns out to be XHTML ...
 */
require_once BASE_DIR."/lib/processors/html_processor.php";


/**
 * Load so can parse urls
 */
require_once BASE_DIR."/lib/url_parser.php";

 /**
 * Used to create crawl summary information 
 * for XML files (those served as text/xml)
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class XmlProcessor extends TextProcessor
{
    const MAX_DESCRIPTION_LEN = 2000;


    /**
     *  Used to extract the title, description and links from
     *  a string consisting of rss news feed data.
     *
     *  @param string $page   web-page contents
     *  @param string $url   the url where the page contents came from,
     *     used to canonicalize relative links
     *
     *  @return array  a summary of the contents of the page
     *
     */
	var $components = array();
	 
    public function process($page, $url)
    {
        $summary = NULL;
        if(is_string($page)) {
            self::closeDanglingTags($page);

            $dom = self::dom($page);

            $root_name = isset($dom->documentElement->nodeName) ?
                $dom->documentElement->nodeName : "";
            unset($dom);
            switch ($root_name)
            {
                case "rss":
					$rss_processor = new RssProcessor();
                    $summary = $rss_processor->process($page, $url);
                break;
                case "html":
					$html_processor = new HtmlProcessor();
                    $summary = $html_processor->process($page, $url);
                break;
                case "sitemapindex":
					$sitemap_processor = new SitemapProcessor();
                    $summary = $sitemap_processor->process($page, $url);
                break;
                case "urlset":
                    $sitemap_processor = new SitemapProcessor();
                    $summary = $sitemap_processor->process($page, $url);
                break;
                case "svg":
					$svg_processor = new SvgProcessor();
                    $summary = $svg_processor->process($page, $url);
                break;
                default:
                    $summary = parent::process($page, $url);
            }
        }

        return $summary;

    }



    /**
     * Return a document object based on a string containing the contents of 
     * an XML page
     *
     *  @param string $page   a web page
     *
     *  @return object  document object
     */
    static function dom($page) 
    {
        $dom = new DOMDocument();

        @$dom->loadXML($page);

        return $dom;
    }

}

?>
