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
 * Load so can parse urls
 */
require_once BASE_DIR."/lib/url_parser.php";

 /**
 * Used to create crawl summary information 
 * for HTML files
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class HtmlProcessor extends TextProcessor
{
    const MAX_DESCRIPTION_LEN = 2000;

    /**
     *  Used to extract the title, description and links from
     *  a string consisting of webpage data.
     *
     *  @param string $page web-page contents
     *  @param string $url the url where the page contents came from,
     *     used to canonicalize relative links
     *
     *  @return array  a summary of the contents of the page
     *
     */
    function process($page, $url, $encoding)
    {
        $summary = NULL;
        if(is_string($page)) {
            $page = preg_replace('/>/', '> ', $page);
            $page = preg_replace('@<script[^>]*?>.*?</script>@si', 
                ' ', $page);
            $dom = self::dom($page, $encoding);
            if($dom !== false && self::checkMetaRobots($dom)) {
                $summary[self::TITLE] = self::title($dom);
                $summary[self::DESCRIPTION] = self::description($dom);
                $summary[self::LANG] = self::lang($dom, 
                    $summary[self::DESCRIPTION], $url);
                $summary[self::LINKS] = self::links($dom, $url);
                $summary[self::PAGE] = $page;
                if(strlen($summary[self::DESCRIPTION] . $summary[self::TITLE])
                    == 0 && count($summary[self::LINKS]) == 0) {
                    //maybe not html? treat as text still try to get urls
                    $summary = parent::process($page, $url, $encoding);
                }
            } else if( $dom == false ) {
                $summary = parent::process($page, $url, $encoding);
            }
        }

        return $summary;

    }



    /**
     * Return a document object based on a string containing the contents of 
     * a web page
     *
     *  @param string $page   a web page
     *
     *  @return object  document object
     */
    static function dom($page, $encoding) 
    {
        /* 
             first do a crude check to see if we have at least an <html> tag
             otherwise try to make a simplified html document from what we got
         */
        if(!stristr($page, "<html")) {
            $head_tags = "<title><meta><base>";
            $head = strip_tags($page, $head_tags);
            $body_tags = "<frameset><frame>".
                "<h1><h2><h3><h4><h5><h6><p><div>".
                "<a><table><tr><td><th>";
            $body = strip_tags($page, $body_tags);
            $page = "<html><head>$head</head><body>$body</body>";
        }
        $dom = new DOMDocument();

        //this hack modified from php.net
        @$dom->loadHTML('<?xml encoding="'.$encoding.'">' . $page);

        foreach ($dom->childNodes as $item)
        if ($item->nodeType == XML_PI_NODE)
            $dom->removeChild($item); // remove hack
        $dom->encoding = $encoding; // insert proper

        return $dom;
    }

    /**
     * Check if there is a meta tag in the supplied document object that 
     * forbids robots from crawling the page corresponding to the dom object.
     *
     *  @param object $dom - a document object to check the meta tags for
     * 
     *  @return bool true or false depending on the result of the check
     */
    static function checkMetaRobots($dom) 
    {
        $xpath = new DOMXPath($dom);
        // we use robot rather than robots just in case people forget the s
        $robots_check = "contains(translate(@name,".
            "'abcdefghijklmnopqrstuvwxyz'," .
            " 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'),'ROBOT')";
        $metas = $xpath->evaluate("/html/head//meta[$robots_check]");

        foreach($metas as $meta) {
            // don't crawl if either noindex or nofollow
            if(mb_stristr($meta->getAttribute('content'),"NOINDEX") || 
                mb_stristr($meta->getAttribute('content'), "NOFOLLOW"))
                { return false; }
        }

        return true;
    }

    /**
     *  Determines the language of the html document by looking at the root
     *  language attribute. If that fails $sample_text is used to try to guess
     *  the language
     *
     *  @param object $dom  a document object to check the language of
     *  @param string $sample_text sample text to try guess the language from
     *  @param string $url url of web-page as a fallback look at the country
     *      to figure out language
     *
     *  @return string language tag for guessed language
     */
    static function lang($dom, $sample_text = NULL, $url = NULL)
    {

        $htmls = $dom->getElementsByTagName("html");
        $lang = NULL;
        foreach($htmls as $html) {
            $lang = $html->getAttribute('lang');
            if($lang != NULL) {
                return $lang;
            }
        }

        if($lang == NULL){
            $lang = self::calculateLang($sample_text, $url);
        }
        return $lang;
    }

    /**
     *  Returns html head title of a webpage based on its document object
     *
     *  @param object $dom   a document object to extract a title from.
     *  @return string  a title of the page 
     *
     */
    static function title($dom) 
    {
        $sites = array();

        $xpath = new DOMXPath($dom);
        $titles = $xpath->evaluate("/html//title");

        $title = "";

        foreach($titles as $pre_title) {
            $title .= $pre_title->textContent;
        }

        return $title;
    }

    /**
     * Returns descriptive text concerning a webpage based on its document 
     * object
     *
     * @param object $dom   a document object to extract a description from.
     * @return string a description of the page 
     */
    static function description($dom) {
        $sites = array();

        $xpath = new DOMXPath($dom);

        $metas = $xpath->evaluate("/html//meta");

        $description = "";

        //look for a meta tag with a description
        foreach($metas as $meta) {
            if(mb_stristr($meta->getAttribute('name'), "description")) {
                $description .= " ".$meta->getAttribute('content');
            }
        }

        /*
          concatenate the contents of then additional dom elements up to
          the limit of description length
        */
        $page_parts = array("/html//h1", "/html//h2", "/html//h3",
            "/html//h4", "/html//h5", "/html//h6", "/html//p[1]",
            "/html//div[1]", "/html//p[2]", "/html//div[2]", 
            "/html//td", "/html//li", "/html//a");
        foreach($page_parts as $part) {
            $doc_nodes = $xpath->evaluate($part);
            foreach($doc_nodes as $node) {
                $description .= " ".$node->textContent;
                if(strlen($description) > self::MAX_DESCRIPTION_LEN) { break 2;}
            }
        }
        $description = mb_ereg_replace("(\s)+", " ",  $description);

        return $description;
    }

    /**
     * Returns up to MAX_LINK_PER_PAGE many links from the supplied
     * dom object where links have been canonicalized according to
     * the supplied $site information.
     * 
     * @param object $dom   a document object with links on it
     * @param string $site   a string containing a url
     * 
     * @return array   links from the $dom object
     */ 
    static function links($dom, $site) 
    {
        $sites = array();

        $xpath = new DOMXPath($dom);
        $base_refs = $xpath->evaluate("/html//base");
        if($base_refs->item(0)) {
            $tmp_site = $base_refs->item(0)->getAttribute('href');
            if(strlen($tmp_site) > 0) {
                $site = UrlParser::canonicalLink($tmp_site, $site);
            }
        }

        $hrefs = $xpath->evaluate("/html/body//a");

        $i = 0;

        foreach($hrefs as $href) {
            if($i < MAX_LINKS_PER_PAGE) {
                $rel = $href->getAttribute("rel");
                if($rel == "" || !stristr($rel, "nofollow")) {
                    $url = UrlParser::canonicalLink(
                        $href->getAttribute('href'), $site);
                    if(!UrlParser::checkRecursiveUrl($url)  && 
                        strlen($url) < MAX_URL_LENGTH) {
                        if(isset($sites[$url])) { 
                            $sites[$url] .=" ".strip_tags($href->textContent);
                        } else {
                            $sites[$url] = strip_tags($href->textContent);
                        }

                       $i++;
                    }
                }
            }

        }

        $frames = $xpath->evaluate("/html/frameset/frame|/html/body//iframe");
        foreach($frames as $frame) {
            if($i < MAX_LINKS_PER_PAGE) {
                $url = UrlParser::canonicalLink(
                    $frame->getAttribute('src'), $site);

                if(!UrlParser::checkRecursiveUrl($url) 
                    && strlen($url) < MAX_URL_LENGTH) {
                    if(isset($sites[$url]) ) { 
                        $sites[$url] .=" HTMLframe";
                    } else {
                        $sites[$url] = "HTMLframe";
                    }

                    $i++;
                }
            }
        }

        $imgs = $xpath->evaluate("/html/body//img[@alt]");

        $i = 0;

        foreach($imgs as $img) {
            if($i < MAX_LINKS_PER_PAGE) {
                $alt = $img->getAttribute('alt');

                if(strlen($alt) < 1) { continue; }

                $url = UrlParser::canonicalLink(
                    $img->getAttribute('src'), $site);
                if(!UrlParser::checkRecursiveUrl($url) 
                    && strlen($url) < MAX_URL_LENGTH) {
                    if(isset($sites[$url]) ) { 
                        $sites[$url] .=" ".$alt;
                    } else {
                        $sites[$url] = $alt;
                    }

                    $i++;
                }
            }

        }

       return $sites;
    }

}

?>
