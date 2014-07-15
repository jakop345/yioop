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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage processor
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/** Register File Types We Handle*/
$add_extensions = array("asp", "aspx", "cgi", "cfm", "cfml", "do", "htm",
    "html", "jsp", "php", "pl", "py", "shtml");
if(!isset($INDEXED_FILE_TYPES)) {
    $INDEXED_FILE_TYPES = array();
}
$INDEXED_FILE_TYPES = array_merge($INDEXED_FILE_TYPES, $add_extensions);
$PAGE_PROCESSORS["text/html"] = "HtmlProcessor";
$PAGE_PROCESSORS["text/asp"] = "HtmlProcessor";
$PAGE_PROCESSORS["application/xhtml+xml"] = "HtmlProcessor";
/**
 * Load base class, if needed.
 */
require_once BASE_DIR."/lib/processors/text_processor.php";
/**
 * Load so can parse urls
 */
require_once BASE_DIR."/lib/url_parser.php";
/**
* Get the centroid summary
*/
require_once BASE_DIR."/lib/centroid_summarizer.php";
/**
 * For guessing language from charset
 */
require_once BASE_DIR."/lib/locale_functions.php";
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
    /**
     * Maximum number of characters in a title
     */
    const MAX_TITLE_LEN = 100;
    /**
     * Used to extract the title, description and links from
     * a string consisting of webpage data.
     *
     * @param string $page web-page contents
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array  a summary of the contents of the page
     *
     */
    function process($page, $url)
    {
        $summary = NULL;
        if(is_string($page)) {
            $page = preg_replace('@<script[^>]*?>.*?</script>@si', ' ', $page);
            $page = preg_replace('/\&nbsp\;|\&rdquo\;|\&ldquo\;|\&mdash\;/si',
                ' ',$page);
            $dom_page = preg_replace(
                '@<style[^>]*?>.*?</style>@si', ' ', $page);
            $dom = self::dom($dom_page);
            if($dom !== false ) {
                $summary[self::ROBOT_METAS] = self::getMetaRobots($dom);
                $summary[self::TITLE] = self::title($dom);
                if($summary[self::TITLE] == "") {
                    $summary[self::TITLE] = self::crudeTitle($dom_page);
                }
                $summary[self::LANG] = self::lang($dom,
                    $summary[self::TITLE], $url);
                if($this->summarizer_option == self::CENTROID_SUMMARIZER) {
                    $summary_cloud = CentroidSummarizer::getCentroidSummary(
                        $dom_page, $summary[self::LANG]);
                    $summary[self::DESCRIPTION] = $summary_cloud[0];
                    $summary[self::WORD_CLOUD] = $summary_cloud[1];
                } else {
                    $summary[self::DESCRIPTION] = self::description($dom,
                        $dom_page);
                }
                $crude = false;
                if(trim($summary[self::DESCRIPTION]) == "") {
                    $summary[self::DESCRIPTION] = self::crudeDescription(
                        $dom_page);
                    $crude = true;
                }
                $summary[self::LINKS] = self::links($dom, $url);
                if($summary[self::LINKS] == array()) {
                    $summary[self::LINKS] = parent::extractHttpHttpsUrls(
                        $page);
                }
                $location = self::location($dom);
                if($location) {
                    $summary[self::LINKS][$location] = "location:".$url;
                    $summary[self::LOCATION] = true;
                    $summary[self::DESCRIPTION] .= $url." => ".$location;
                    if(!$summary[self::TITLE]) {
                        $summary[self::TITLE] = $url;
                    }
                }
                if(!$crude && !$location) {
                    $location = self::relCanonical($dom);
                    if($location) {
                        $summary[self::LINKS] = array();
                        $summary[self::LINKS][$location] = "location:".$url;
                        $summary[self::LOCATION] = true;
                        if(!$summary[self::DESCRIPTION]) {
                            $summary[self::DESCRIPTION] .=$url." => ".$location;
                        }
                        if(!$summary[self::TITLE]) {
                            $summary[self::TITLE] = $url;
                        }
                    }
                }
                $summary[self::PAGE] = $page;
                if(strlen($summary[self::DESCRIPTION] . $summary[self::TITLE])
                    == 0 && count($summary[self::LINKS]) == 0 && !$location) {
                    /*maybe not html? treat as text with messed up tags
                        still try to get urls
                     */
                    $summary_text = parent::process(strip_tags($page), $url);
                    foreach($summary as $field => $value) {
                        if(($value == "" || $value == array() ) &&
                            isset($summary_text[$field])) {
                            $summary[$field] = $summary_text[$field];
                        }
                    }
                }
            } else if( $dom == false ) {
                $summary = parent::process($page, $url);
            }
        }
        return $summary;
    }
    /**
     * Return a document object based on a string containing the contents of
     * a web page
     *
     * @param string $page   a web page
     *
     * @return object  document object
     */
    static function dom($page)
    {
        /*
             first do a crude check to see if we have at least an <html> tag
             otherwise try to make a simplified html document from what we got
         */
        if(!stristr($page, "<html")) {
            $head_tags = "<title><meta><base>";
            $head = strip_tags($page, $head_tags);
            $body_tags = "<frameset><frame><noscript><img><span><b><i><em>".
                "<strong><h1><h2><h3><h4><h5><h6><p><div>".
                "<a><table><tr><td><th><dt><dir><dl><dd>";
            $body = strip_tags($page, $body_tags);
            $page = "<html><head>$head</head><body>$body</body></html>";
        }
        $dom = new DOMDocument();
        //this hack modified from php.net
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $page);
        foreach ($dom->childNodes as $item)
        if ($item->nodeType == XML_PI_NODE)
            $dom->removeChild($item); // remove hack
        $dom->encoding = "UTF-8"; // insert proper
        return $dom;
    }
    /**
     * Get any NOINDEX, NOFOLLOW, NOARCHIVE, NONE, info out of any robot
     * meta tags.
     *
     * @param object $dom - a document object to check the meta tags for
     *
     * @return array of robot meta instructions
     */
    static function getMetaRobots($dom)
    {
        $xpath = new DOMXPath($dom);
        // we use robot rather than robots just in case people forget the s
        $robots_check = "contains(translate(@name,".
            "'abcdefghijklmnopqrstuvwxyz'," .
            " 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'),'ROBOT')";
        $metas = $xpath->evaluate("/html/head//meta[$robots_check]");
        $found_metas = array();
        foreach($metas as $meta) {
            $content = $meta->getAttribute('content');
            $robot_metas = explode(",", $content);
            foreach($robot_metas as $robot_meta) {
                $found_metas[] = strtoupper(trim($robot_meta));
            }
        }
        return $found_metas;
    }
    /**
     * Determines the language of the html document by looking at the root
     * language attribute. If that fails $sample_text is used to try to guess
     * the language
     *
     * @param object $dom  a document object to check the language of
     * @param string $sample_text sample text to try guess the language from
     * @param string $url url of web-page as a fallback look at the country
     *     to figure out language
     *
     * @return string language tag for guessed language
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
        if($lang == NULL) {
            //baidu doesn't have a lang attribute but does say encoding
            $xpath = new DOMXPath($dom);
            $charset_check = "contains(translate(@http-equiv,".
                "'abcdefghijklmnopqrstuvwxyz'," .
                " 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'),'CONTENT-TYPE')";
            $metas = $xpath->evaluate("/html/head//meta[$charset_check]");
            $found_metas = array();
            foreach($metas as $meta) {
                $content = $meta->getAttribute('content');
                $charset_metas = explode("=", $content);
                if(isset($charset_metas[1])) {
                    $charset = strtoupper($charset_metas[1]);
                    $lang = guessLangEncoding($charset);
                    return $lang;
                }
            }
            $lang = self::calculateLang($sample_text, $url);
        }
        return $lang;
    }
    /**
     * Returns title of a webpage based on its document object
     *
     * @param object $dom   a document object to extract a title from.
     * @return string  a title of the page
     *
     */
    static function title($dom)
    {
        $xpath = new DOMXPath($dom);
        $titles = $xpath->evaluate("/html//title");
        $title = "";
        foreach($titles as $pre_title) {
                $title .= $pre_title->nodeValue;
        }
        if($title == "") {
            $title_parts = array("/html//h1", "/html//h2", "/html//h3",
                "/html//h4", "/html//h5", "/html//h6");
            foreach($title_parts as $part) {
                $doc_nodes = $xpath->evaluate($part);
                foreach($doc_nodes as $node) {
                    $title .= " .. ".$node->nodeValue;
                    if(strlen($title) >
                        self::MAX_TITLE_LEN) { break 2;}
                }
            }
        }
        $title = substr($title, 0, self::MAX_TITLE_LEN);
        return $title;
    }
    /**
     * Returns title of a webpage based on crude regex match,
     *     used as a fall back if dom parsing did not work.
     *
     * @param string $page to extract title from
     * @return string  a title of the page
     */
    static function crudeTitle($page)
    {
        $title = parent::getBetweenTags($page, 0, "<title", "</title");
        return strip_tags("<title".$title[1]."</title>");
    }
    /**
     * Returns summary of body of a web page based on crude regex matching
     *     used as a fall back if dom parsing did not work.
     *
     * @param string $page to extract description from
     * @return string  a title of the page
     */
    static function crudeDescription($page)
    {
        $body = parent::getBetweenTags($page, 0, "<body", "</body");
        $body = strip_tags("<body".$body[1]."</body>");
        if($body == "") { return $body; }
        $body_parts = preg_split("/\s+/", $body);
        $body = implode(" ", $body_parts);
        return mb_substr($body, 0, self::$max_description_len);
    }
    /**
     * Returns descriptive text concerning a webpage based on its document
     * object
     *
     * @param object $dom   a document object to extract a description from.
     * @param string $page original page string to extract description from
     * @return string a description of the page
     */
    static function description($dom, $page)
    {
        $xpath = new DOMXPath($dom);
        $metas = $xpath->evaluate("/html//meta");
        $description = "";
        //look for a meta tag with a description
        foreach($metas as $meta) {
            if(stristr($meta->getAttribute('name'), "description")) {
                $description .= " .. ".$meta->getAttribute('content');
            }
        }
        if(self::$max_description_len > 2 * MAX_DESCRIPTION_LEN) {
            /* if don't need to summarize much, take meta description
               from above code, then concatenate body of doc
               after stripping tags, return result
             */
            $description .= "\n".self::crudeDescription($page);
            return $description;
        }
        /*
          concatenate the contents of then additional dom elements up to
          the limit of description length. Choose tags in order of likely
          importance to this doc
        */
        $page_parts = array("/html//p[1]",
            "/html//div[1]", "/html//p[2]", "/html//div[2]", "/html//p[3]",
            "/html//div[3]", "/html//p[4]", "/html//div[4]",
            "/html//td", "/html//li", "/html//dt", "/html//dd",
            "/html//pre", "/html//a", "/html//article",
            "/html//section", "/html//cite");
        $para_data = array();
        $len = 0;
        foreach($page_parts as $part) {
            $doc_nodes = $xpath->evaluate($part);
            foreach($doc_nodes as $node) {
                if($part == "/html//a") {
                    $content = $node->getAttribute('href')." = ";
                    $add_len  = min(self::$max_description_len / 2,
                        mb_strlen($content));
                    $para_data[$add_len][] = mb_substr($content, 0, $add_len);
                }
                $node_text = self::domNodeToString($node);
                $add_len  = min(self::$max_description_len / 2,
                    mb_strlen($node_text));
                $para_data[$add_len][] = mb_substr($node_text, 0, $add_len);
                $len += $add_len;

                if($len > self::$max_description_len) { break 2;}
                if(in_array($part, array("/html//p[1]", "/html//div[1]",
                    "/html//div[2]", "/html//p[2]", "/html//p[3]",
                    "/html//div[3]", "/html//div[4]", "/html//p[4]"))) break;
            }
        }
        krsort($para_data);
        foreach($para_data as $add_len => $data) {
            if(!isset($first_len)) {
                $first_len = $add_len;
            }
            foreach($data as $datum) {
                $description .= " .. ". $datum;
            }
            if($first_len > 3 * $add_len) break;
        }
        $description = preg_replace("/(\s)+/u", " ",  $description);

        return $description;
    }
    /**
     * Extracts are location of refresh urls from the meta tags of html page
     * in site
     *
     * @param object $dom document object version of web page
     * @return mixed refresh or location url if found, false otherwise
     */
    static function location($dom)
    {
        $xpath = new DOMXPath($dom);
        //Look for Refresh or Location
        $metas = $xpath->evaluate("/html//meta");
        foreach($metas as $meta) {
            if(stristr($meta->getAttribute('http-equiv'), "refresh") ||
               stristr($meta->getAttribute('http-equiv'), "location")) {
                $urls = explode("=", $meta->getAttribute('content'));
                if(isset($urls[1]) && !UrlParser::checkRecursiveUrl($urls[1]) &&
                    strlen($urls[1]) < MAX_URL_LENGTH) {
                    $url = @trim($urls[1]);
                    return $url;
                }
            }
        }
        return false;
    }
    /**
     * If a canonical link element 
     * (https://en.wikipedia.org/wiki/Canonical_link_element)
     * is in $dom, then this function extracts it
     *
     *
     * @param object $dom document object version of web page
     * @return mixed refresh or location url if found, false otherwise
     */
    static function relCanonical($dom)
    {
        $xpath = new DOMXPath($dom);
        //Look for Refresh or Location
        $links = $xpath->evaluate("/html/head/link");
        foreach($links as $link) {
            if(stristr($link->getAttribute('rel'), "canonical") ) {
                $url = trim($link->getAttribute('href'));
                if(!UrlParser::checkRecursiveUrl($url) &&
                    strlen($url) < MAX_URL_LENGTH) {
                    return $url;
                }
            }
        }
        return false;
    }
    /**
     * Returns up to MAX_LINKS_TO_EXTRACT many links from the supplied
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
        $i = 0;
        $hrefs = $xpath->evaluate("/html/body//a");
        foreach($hrefs as $href) {
            if($i < MAX_LINKS_TO_EXTRACT) {
                $rel = $href->getAttribute("rel");
                if($rel == "" || !stristr($rel, "nofollow")) {
                    $url = UrlParser::canonicalLink(
                        $href->getAttribute('href'), $site);
                    $len = strlen($url);
                    if(!UrlParser::checkRecursiveUrl($url)  &&
                        $len < MAX_URL_LENGTH && $len > 4) {
                        $text = $href->nodeValue;
                        if(isset($sites[$url])) {
                            $sites[$url] .=" .. ".
                                preg_replace("/\s+/", " ", strip_tags($text));
                            $sites[$url] = mb_substr($sites[$url], 0,
                                2* MAX_LINKS_WORD_TEXT);
                        } else {
                            $sites[$url] = preg_replace("/\s+/", " ",
                                strip_tags($text));
                            $sites[$url] = mb_substr($sites[$url], 0,
                                2* MAX_LINKS_WORD_TEXT);
                        }
                       $i++;
                    }
                }
            }
        }
        $frames = $xpath->evaluate("/html/frameset/frame|/html/body//iframe");
        foreach($frames as $frame) {
            if($i < MAX_LINKS_TO_EXTRACT) {
                $url = UrlParser::canonicalLink(
                    $frame->getAttribute('src'), $site);
                $len = strlen($url);
                if(!UrlParser::checkRecursiveUrl($url)
                    && $len < MAX_URL_LENGTH && $len > 4) {
                    if(isset($sites[$url]) ) {
                        $sites[$url] .=" .. HTMLframe";
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
            if($i < MAX_LINKS_TO_EXTRACT) {
                $alt = $img->getAttribute('alt');
                if(strlen($alt) < 1) { continue; }
                $url = UrlParser::canonicalLink(
                    $img->getAttribute('src'), $site);
                $len = strlen($url);
                if(!UrlParser::checkRecursiveUrl($url)
                    && $len < MAX_URL_LENGTH && $len > 4) {
                    if(isset($sites[$url])) {
                        $sites[$url] .=" .. ".$alt;
                        $sites[$url] = mb_substr($sites[$url], 0,
                            2* MAX_LINKS_WORD_TEXT);
                    } else {
                        $sites[$url] =$alt;
                        $sites[$url] = mb_substr($sites[$url], 0,
                            2* MAX_LINKS_WORD_TEXT);
                    }
                    $i++;
                }
            }
        }
       return $sites;
    }
    /**
     * This returns the text content of a node but with spaces
     * where tags were (unlike just using textContent)
     *
     * @param object $node a DOMNode
     * @return string its text content with spaces
     */
    static function domNodeToString($node)
    {
        $text = $node->ownerDocument->saveHTML($node);
        $text = html_entity_decode($text);
        $text = preg_replace('/\</', ' <', $text);
        return strip_tags($text);
    }
}
?>
