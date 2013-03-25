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

/** Wikipedia is usually bzip2 compressed*/
require_once BASE_DIR.'/lib/bzip2_block_iterator.php';

/**
 * Used to define the styles we put on cache wiki pages
 */
define('WIKI_PAGE_STYLES', <<<EOD
<style type="text/css">
table.wikitable
{
    background:white;
    border:1px #aaa solid;
    border-collapse: scollapse
    margin:1em 0;
}
table.wikitable > tr > th,table.wikitable > tr > td,
table.wikitable > * > tr > th,table.wikitable > * > tr > td
{
    border:1px #aaa solid;
    padding:0.2em;
}
table.wikitable > tr > th,
table.wikitable > * > tr > th
{
    text-align:center;
    background:white;
    font-weight:bold
}
table.wikitable > caption
{
    font-weight:bold;
}
</style>
EOD
);

/**
 * Used to iterate through a collection of .xml.bz2  media wiki files
 * stored in a WebArchiveBundle folder. Here these media wiki files contain the
 * kinds of documents used by wikipedia. Iteration would be
 * for the purpose making an index of these records
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage iterator
 * @see WebArchiveBundle
 */
class MediaWikiArchiveBundleIterator extends TextArchiveBundleIterator
    implements CrawlConstants
{

   /**
    * Used to store the hash of the last page processed. If see hash
    * assume there was a problem with processing all the regexes in the
    * last nextPage call and so this time do less regexes.
    * @var string
    */
    var $last_hash = "";

    /**
     * Creates a media wiki archive iterator with the given parameters.
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
        $ini = array( 'compression' => 'bzip2',
            'file_extension' => 'bz2',
            'encoding' => 'UTF-8',
            'start_delimiter' => '@page@');
        parent::__construct($iterate_timestamp, $iterate_dir,
            $result_timestamp, $result_dir, $ini);
        $this->switch_partition_callback_name = "readMediaWikiHeader";
    }


    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return int a 4-bit number based on the log_2 size - 10 of the wiki
     *      entry (@see nextPage).
     */
    function weight(&$site)
    {
        return min($site[self::WEIGHT], 15);
    }

    /**
     * Reads the siteinfo tag of the mediawiki xml file and extract data that
     * will be used in constructing page summaries.
     */
    function readMediaWikiHeader()
    {
        $this->header = array();
        $site_info = $this->getNextTagData("siteinfo");
        $found_lang =
            preg_match('/lang\=\"(.*)\"/', $this->remainder, $matches);
        if($found_lang) {
            $this->header['lang'] = $matches[1];
        }
        if($site_info === false) {
            $this->bz2_iterator = NULL;
            return false;
        }
        $dom = new DOMDocument();
        @$dom->loadXML($site_info);
        $this->header['sitename'] = $this->getTextContent($dom,
            "/siteinfo/sitename");
        $pre_host_name =
            $this->getTextContent($dom, "/siteinfo/base");
        $this->header['base_address'] = substr($pre_host_name, 0,
            strrpos($pre_host_name, "/") + 1);
        $url_parts = @parse_url($this->header['base_address']);
        $this->header['ip_address'] = gethostbyname($url_parts['host']);
        return true;
    }

    /**
     * Used to initialize the arrays of match/replacements used to format
     * wikimedia syntax into HTML (not perfectly since we are only doing
     * regexes)
     */
    function initializeSubstitutions()
    {
        $base_address = $this->header['base_address'];

        $substitutions = array(
            array('/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ),
            array('/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ),
            array('/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ),
            array('/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ),
            array('/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ),
            array('/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ),
            array('/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"),
            array('/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"),
            array('/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"),
            array('/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"),
            array('/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"),
            array('/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"),
            array('/(\A|\n)=\s*([^=]+)\s*=/',
                "\n<h1 id='$2'>$2</h1><hr />"),
            array('/(\A|\n)==\s*([^=]+)\s*==/',
                "\n<h2 id='$2'>$2</h2><hr />"),
            array('/(\A|\n)===\s*([^=]+)\s*===/',
                "\n<h3 id='$2'>$2</h3>"),
            array('/(\A|\n)====\s*([^=]+)\s*====/',
                "\n<h4 id='$2'>$2</h4>"),
            array('/(\A|\n)=====\s*([^=]+)\s*=====/',
                "\n<h5 id='$2'>$2</h5>"),
            array('/(\A|\n)======\s*([^=]+)\s*======/',
                "\n<h6 id='$2'>$2</h6>"),
            array("/{{Main\s*\|(.+?)(\|.+)?}}/i",
                "<div class='indent'>(<a href=\"".
                $base_address."$1\">$1</a>)</div>"),
            array("/{{See also\s*\|(.+?)(\|.+)?}}/i",
                "<div class='indent'>(<a href=\"".
                $base_address."$1\">$1</a>)</div>"),
            array("/{{See\s*\|(.+?)(\|.+)?}}/i",
                "<div class='indent'>(<a href=\"".
                $base_address."$1\">$1</a>)</div>"),
            array("/{{\s*Related articles\s*\|(.+?)\|(.+?)}}/si",
                "<div class='indent'>(<a href=\"".
                $base_address . "$1\">$1?</a>)</div>"),
            array("/\[\[Image:(.+?)(right\|)(.+?)\]\]/s",
                "[[Image:$1$3]]"),
            array("/\[\[Image:(.+?)(left\|)(.+?)\]\]/s",
                "[[Image:$1$3]]"),
            array("/\[\[Image:(.+?)(\|left)\]\]/s",
                "[[Image:$1]]"),
            array("/\[\[Image:(.+?)(\|right)\]\]/s",
                "[[Image:$1]]"),
            array("/\[\[Image:(.+?)(thumb\|)(.+?)\]\]/s",
                "[[Image:$1$3]]"),
            array("/\[\[Image:(.+?)(live\|)(.+?)\]\]/s",
                "[[Image:$1$3]]"),
            array("/\[\[Image:(.+?)(\s*\d*\s*(px|in|cm|".
                "pt|ex|em)\s*\|)(.+?)\]\]/s","[[Image:$1$4]]"),
            array("/\[\[Image:([^\|]+?)\]\]/s",
                "(<a href=\"{$base_address}File:$1\" >Image:$1</a>)"),
            array("/\[\[Image:(.+?)\|(.+?)\]\]/s",
                "(<a href=\"{$base_address}File:$1\">Image:$2</a>)"),
            array("/\[\[File:(.+?)\|(right\|)?thumb(.+?)\]\]/s",
                "(<a href=\"{$base_address}File:$1\">Image:$1</a>)"),
            array("/{{Redirect2?\|([^{}\|]+)\|([^{}\|]+)\|([^{}\|]+)}}/i",
                "<div class='indent'>\"$1\". ($2 &rarr;<a href=\"".
                $base_address."$3\">$3</a>)</div>"),
            array("/{{Redirect\|([^{}\|]+)}}/i", 
                "<div class='indent'>\"$1\". (<a href=\"".
                $base_address. "$1_(disambiguation)\">$1???</a>)</div>"),
            array("/#REDIRECT:\s+\[\[(.+?)\]\]/",
                "<a href='{$base_address}$1'>$1</a>"),
            array('/\[\[([^\[\]]+?)\|([^\[\]]+?)\]\]/s',
                "<a href=\"{$base_address}$1\">$2</a>"),
            array('/\[\[([^\[\]]+?)\]\]/s',
                "<a href=\"{$base_address}$1\">$1</a>"),
            array("/\[(http[^\s\]]+)\s+(.+?)\]/s",
                "[<a href=\"$1\">$2</a>]"),
            array("/\[(http[^\]\s]+)\s*\]/","(<a href=\"$1\">&rarr;</a>)"), 
            array("/'''''(.+?)'''''/s", "<b><i>$1</i></b>"),
            array("/'''(.+?)'''/s", "<b>$1</b>"),
            array("/''(.+?)''/s", "<i>$1</i>"),
            array('/{{smallcaps\|(.+?)}}/s', "<small>$1</small>"),
            array('/{{Hatnote\|(.+?)}}/si', "($1)"),
            array("/{{pp-(.+?)}}/s", ""),
            array("/{{bot(.*?)}}/si", ""),
            array('/{{Infobox.*?\n}}/si', ""),
            array('/{{Clear.*?\n}}/si', ""),
            array("/{{dablink\|(.+?)}}/si", "($1)"),
            array("/{{clarify\|(.+?)}}/si", ""),
            array("/{{fraction\|(.+?)\|(.+?)}}/si", "<small>$1/$2</small>"),
            array("/{{lang[\||\-](.+?)\|(.+?)}}/si", "$1 &rarr; $2"),
            array("/{{convert\|(.+?)\|(.+?)\|(.+?)}}/si", "$1$2"),
            array("/{{IPA-(.+?)\|(.+?)}}/si", "(IPA $2)"),
            array('/(\A|\n)\*(.+)(\n|\Z)/', "\n<li>$2</li>\n"),
            array('/(\A|\n)\*(.+)(\n|\Z)/', "\n<li>$2</li>\n"),
            array('/(\A|[^>])\n<li>/', "$1\n<ul>\n<li>"),
            array('@</li>\n(\Z|[^<])@', "</li>\n</ul>\n$1"),
            array('@</li>\n<li>\*@', "\n**"),
            array('/\n\*\*(.+?)(\n|\Z)/s', "\n<li>$1</li>\n"),
            array('/\n\*\*(.+?)(\n|\Z)/s', "\n<li>$1</li>\n"),
            array('/([^>])\n<li>/', "$1\n<ul>\n<li>"),
            array('@</li></li>@', "</li>\n</ul>\n</li>"),
            array('/(\A|\n)\#(.+?)(\n|\Z)/s', "\n<li>$2</li>\n"),
            array('/(\A|\n)\#(.+?)(\n|\Z)/s', "\n<li>$2</li>\n"),
            array('/(\A|[^>])\n<li>/', "$1\n<ol>\n<li>"),
            array('@</li>\n(\Z|[^<])@',
                "</li>\n</ol>\n$1"),
            array('@</li>\n<li>\#@', "\n##"),
            array('/\n\#\#(.+?)(\n|\Z)/s', "\n<li>$1</li>\n"),
            array('/\n\#\#(.+?)(\n|\Z)/s', "\n<li>$1</li>\n"),
            array('/([^>])\n<li>/', "$1\n<ol>\n<li>"),
            array('@</li></li>@', "</li>\n</ol>\n</li>"),
            array('@</li>\n<li>\*@', "\n**"),
            array('/\n\*\*(.+?)(\n|\Z)/s', "\n<li>$1</li>\n"),
            array('/\n\*\*(.+?)(\n|\Z)/s', "\n<li>$1</li>\n"),
            array('/([^>])\n<li>/', "$1\n<ul>\n<li>"),
            array('@</li></li>@', "</li>\n</ul>\n</li>"),
            array('@</li>\n<li>\#@', "\n##"),
            array('/\n\#\#(.+?)(\n|\Z)/s', "\n<li>$1</li>\n"),
            array('/\n\#\#(.+?)(\n|\Z)/s', "\n<li>$1</li>\n"),
            array('/([^>])\n<li>/', "$1\n<ol>\n<li>"),
            array('@</li></li>@', "</li>\n</ol>\n</li>"),
            array('/(\A|\n);([^:]+):([^\n]+)/',
                "<dl><dt>$2</dt>\n<dd>$3</dd></dl>\n"),
            array('/(\A|\n)----/', "$1<hr />"),
            array('/(\A|\n)[ \t]([^\n]+)/', "$1<pre>\n$2</pre>"),
            array('/(\A|\n):\s+([^\n]+)/', "$1<div class='indent'>$2</div>"),
            array('/(.+?)(\n\n|\Z)/s', "<div>$1</div>"),
            array('/<\/pre>\n<pre>/', ""),
            array('/{{[^}]*}}/s', ""),
        );

        $this->matches = array();
        $this->replaces = array();
        foreach($substitutions as $substitution) {
            list($this->matches[], $this->replaces[]) = $substitution;
        }
    }

    /**
     * Restores the internal state from the file iterate_status.txt in the
     * result dir such that the next call to nextPages will pick up from just
     * after the last checkpoint. We also reset up our regex substitutions
     *
     * @return array the data serialized when saveCheckpoint was called
     */
    function restoreCheckPoint()
    {
        $info = parent::restoreCheckPoint();
        if(isset($info["last_hash"])) {
            $this->last_hash = $info["last_hash"];
        }
        if(!$this->iterate_dir) { // do on client not name server
            $this->initializeSubstitutions();
        }
        return $info;
    }

    /**
     * Stores the current progress to the file iterate_status.txt in the result
     * dir such that a new instance of the iterator could be constructed and
     * return the next set of pages without having to process all of the pages
     * that came before. Each iterator should make a call to saveCheckpoint
     * after extracting a batch of pages.
     * @param array $info any extra info a subclass wants to save
     */
    function saveCheckPoint($info=array())
    {
        $info["last_hash"] = $this->last_hash;
        parent::saveCheckPoint($info);
    }

    /**
     * Gets the text content of the first dom node satisfying the
     * xpath expression $path in the dom document $dom
     *
     * @param object $dom DOMDocument to get the text from
     * @param $path xpath expression to find node with text
     *
     * @return string text content of the given node if it exists
     */
    function getTextContent($dom, $path)
    {
        $xpath = new DOMXPath($dom);
        $objects = $xpath->evaluate($path);
        if($objects  && is_object($objects) && $objects->item(0) != NULL ) {
            return $objects->item(0)->textContent;
        }
        return "";
    }


    /**
     * Gets the next doc from the iterator
     * @param bool $no_process do not do any processing on page data
     * @return array associative array for doc or string if no_process true
     */
    function nextPage($no_process = false)
    {
        static $minimal_regexes = false;
        static $first_call = true;
        if($first_call) {
            $this->initializeSubstitutions();
        }
        $page_info = $this->getNextTagData("page");
        if($no_process) { return $page_info; }
        $dom = new DOMDocument();
        @$dom->loadXML($page_info);
        $site = array();

        $pre_url = $this->getTextContent($dom, "/page/title");
        $pre_url = str_replace(" ", "_", $pre_url);
        $site[self::URL] = $this->header['base_address'].$pre_url;
        $site[self::IP_ADDRESSES] = array($this->header['ip_address']);
        $pre_timestamp = $this->getTextContent($dom,
            "/page/revision/timestamp");
        $site[self::MODIFIED] = date("U", strtotime($pre_timestamp));
        $site[self::TIMESTAMP] = time();
        $site[self::TYPE] = "text/html";
        $site[self::HEADER] = "mediawiki_bundle_iterator extractor";
        $site[self::HTTP_CODE] = 200;
        $site[self::ENCODING] = "UTF-8";
        $site[self::SERVER] = "unknown";
        $site[self::SERVER_VERSION] = "unknown";
        $site[self::OPERATING_SYSTEM] = "unknown";
        $site[self::PAGE] = "<html lang='".$this->header['lang']."' >\n".
            "<head><title>$pre_url</title>\n".
            WIKI_PAGE_STYLES . "\n</head>\n".
            "<body><h1>$pre_url</h1>\n";
        $pre_page = $this->getTextContent($dom, "/page/revision/text");
        $current_hash = crawlHash($pre_page);
  /*      if($this->last_hash == $current_hash) {
            $minimal_regexes = true;
        }
        $this->last_hash = $current_hash;*/
        if($first_call) {
            $this->saveCheckPoint(); //ensure we remember to advance one on fail
            $first_call = false;
        }
        $toc = $this->makeTableOfContents($pre_page);
        if(!$minimal_regexes) {
            list($pre_page, $references) = $this->makeReferences($pre_page);
            $pre_page = preg_replace_callback('/(\A|\n){\|(.*?)\n\|}/s',
                "makeTableCallback", $pre_page);
            $pre_page = preg_replace($this->matches, $this->replaces,$pre_page);
            $pre_page = preg_replace("/{{Other uses}}/i", 
                    "<div class='indent'>\"$1\". (<a href='".
                    $site[self::URL]. "_(disambiguation)'>$pre_url</a>)</div>",
                    $pre_page);
            $pre_page = preg_replace_callback("/((href=)\"([^\"]+)\")/",
                "fixLinksCallback", $pre_page);
            $pre_page = $this->insertReferences($pre_page, $references);
        }
        $pre_page = $this->insertTableOfContents($pre_page, $toc);
        $site[self::PAGE] .= $pre_page;
        $site[self::PAGE] .= "\n</body>\n</html>";

        $site[self::HASH] = FetchUrl::computePageHash($site[self::PAGE]);
        $site[self::WEIGHT] = ceil(max(
            log(strlen($site[self::PAGE]) + 1, 2) - 10, 1));
        return $site;
    }

    /**
     * Used to make a table of contents for a wiki page based on the
     * level two headings on that page.
     *
     *  @param string $page a wiki document
     *  @return string HTML table of contents to be inserted after wiki
     *      page processed
     */
    function makeTableOfContents($page)
    {
        $toc= "";
        $matches = array();
        preg_match_all('/(\A|\n)==\s*([^=]+)\s*==/', $page, $matches);
        if(isset($matches[2]) && count($matches[2] > 2)) {
            $toc .= "<div style='border: 1px ridge #000; width:280px;".
                "background-color:#EEF; padding: 3px; margin:6px;'><ol>\n";
            foreach($matches[2] as $section) {
                $toc .= "<li><a href='#".addslashes($section)."'>".
                    $section."</a></li>\n";
            }
            $toc .= "</ol></div>\n";
        }
        return $toc;
    }

    /**
     * Used to make a reference list for a wiki page based on the
     * cite tags on that page.
     *
     *  @param string $page a wiki document
     *  @return string HTML reference list to be inserted after wiki
     *      page processed
     */
    function makeReferences($page)
    {
        $base_address = $this->header['base_address'];
        $references= "\n";
        $matches = array();
        preg_match_all('/{{v?cite(.+?)}}/si', $page, $matches);
        citeCallback(NULL, 1);
        $page = preg_replace_callback('/{{v?cite?a?t?i?o?n?(.+?)}}/si',
            "citeCallback", $page);
        if(isset($matches[1])) {
            $i = 1;
            $wiki_fields = array("title", "publisher", "author", "journal",
                "book", "quote");
            foreach($matches[1] as $reference) {
                $ref_parts = explode("|", $reference);
                $references .= "<div id=\"ref_$i\">$i.".
                    "<a href=\"#cite_$i\">^</a>.";
                if(count($ref_parts) > 0) {
                    $ref_data = array();
                    $type = trim(strtolower($ref_parts[0]));
                    array_shift($ref_parts);
                    foreach($ref_parts as $part) {
                        $part_parts = explode("=", $part);
                        if(isset($part_parts[1])){
                            $field = strtolower(trim($part_parts[0]));
                            $value = trim($part_parts[1]);
                            if(in_array($field, $wiki_fields)) {
                                $value = preg_replace($this->matches,
                                    $this->replaces, $value);
                                $value = strip_tags($value, 
                                    '<a><b><i><span><img>');
                            }
                            $ref_data[$field] = $value;
                        }
                    }
                    if(!isset($ref_data['author']) && isset($ref_data['last'])
                        && isset($ref_data['first'])) {
                        $ref_data['author'] = $ref_data['last'].", ".
                            $ref_data['first'];
                    }
                    if(isset($ref_data['authorlink']) ) {
                        if(!isset($ref_data['author'])) {
                            $ref_data['author'] = $ref_data['authorlink'];
                        }
                        $ref_data['author'] = "<a href=\"$base_address".
                            $ref_data['author']."\">{$ref_data['author']}</a>";
                    }
                    if(!isset($ref_data['title']) && isset($ref_data['url'])) {
                        $ref_data['title'] = $ref_data['url'];
                    }
                    if(isset($ref_data['title']) && isset($ref_data['url'])) {
                        $ref_data['title'] = "<a href=\"{$ref_data['url']}\">".
                            "{$ref_data['title']}</a>";
                    }
                    if(isset($ref_data['quote'])) {
                        $references .= '"'.$ref_data['quote'].'". ';
                    }
                    if(isset($ref_data['author'])) {
                        $references .= $ref_data['author'].". ";
                    }
                    if(isset($ref_data['title'])) {
                        $references .= '"'.$ref_data['title'].'". ';
                    }

                    if(isset($ref_data['accessdate']) &&
                        !isset($ref_data['archivedate'])) {
                        $references .= '('.$ref_data['accessdate'].') ';
                    }
                    if(isset($ref_data['archivedate'])) {
                        if(isset($ref_data['archiveurl'])) {
                            $ref_data['archivedate'] = "<a href=\"".
                                $ref_data['archiveurl']."\">".
                                $ref_data['archivedate']."</a>";
                        }
                        $references .= '('.$ref_data['archivedate'].') ';
                    }
                    if(isset($ref_data['journal'])) {
                         $references .= "<i>{$ref_data['journal']}</i> ";
                    }
                    if(isset($ref_data['location'])) {
                         $references .= $ref_data['location'].". ";
                    }
                    if(isset($ref_data['publisher'])) {
                         $references .= $ref_data['publisher'].". ";
                    }
                    if(isset($ref_data['doi'])) {
                         $references .= "doi:".$ref_data['doi'].". ";
                    }
                    if(isset($ref_data['isbn'])) {
                         $references .= "ISBN:".$ref_data['isbn'].". ";
                    }
                    if(isset($ref_data['jstor'])) {
                         $references .= "JSTOR:".$ref_data['jstor'].". ";
                    }
                    if(isset($ref_data['oclc'])) {
                         $references .= "OCLC:".$ref_data['oclc'].". ";
                    }
                    if(isset($ref_data['volume'])) {
                         $references .= "<b>".$ref_data['volume'].
                            "</b> ";
                    }
                    if(isset($ref_data['issue'])) {
                         $references .= "#".$ref_data['issue'].". ";
                    }
                    if(isset($ref_data['date'])) {
                         $references .= $ref_data['date'].". ";
                    }
                    if(isset($ref_data['year'])) {
                         $references .= $ref_data['year'].". ";
                    }
                    if(isset($ref_data['page'])) {
                         $references .= "p.".$ref_data['page'].". ";
                    }
                    if(isset($ref_data['pages'])) {
                         $references .= "pp.".$ref_data['pages'].". ";
                    }
                }
                $references .="</div>\n";
                $i++;
            }
        }
        return array($page, $references);
    }

    /**
     *  After regex processing has been done on a wiki page this function
     *  inserts into the resulting page a table of contents just before
     *  the first h2 tag, then returns the result page
     *
     *  @param string $page page in which to insert table of contents
     *  @param string $toc HTML table of contents
     *  @return string resulting page after insert
     */
    function insertTableOfContents($page, $toc)
    {
        $pos = strpos($page, "<h2");
        if($pos !== false) {
            $start = substr($page, 0, $pos);
            $end = substr($page, $pos);
            $page = $start . $toc. $end;
        }
        return $page;
    }

    /**
     *  After regex processing has been done on a wiki page this function
     *  inserts into the resulting page a reference at
     *  {{reflist locations, then returns the result page
     *
     *  @param string $page page in which to insert the reference lists
     *  @param string $toc HTML table of contents
     *  @return string resulting page after insert
     */
    function insertReferences($page, $references)
    {
        $page = preg_replace('/{{reflist(.+?)}}/si', $references, $page);

        return $page;
    }

}

/**
 * Callback used by a preg_replace_callback in nextPage to make a table
 * @param array $matches of table cells
 */
function makeTableCallback($matches)
{
    $table = str_replace("\n!","\n|#",$matches[2]);
    $table = str_replace("!!","\n||#",$table);
    $row_data = explode("|", $table);
    $first = true;
    $out = $matches[1];
    $state = "";
    $type = "td";
    $old_type = "td";
    foreach($row_data as $item) {
        if($first) {
            $item = trim(str_replace("\n", " ", $item));
            $out .= "<table $item>\n<tr>";
            $first = false;
            $old_line = true;
            continue;
        }
        if($item == "" || $item[0] == "-") {
            if($out[strlen($out) - 2] != "r") {
                $out .= "</$old_type>";
            }
            $out .= "</tr>\n<tr>";
            continue;
        }

        if($item[0] == "+") {
            $type= "caption";
            $old_type = $type;
            $item = substr($item, 1);
        }
        if($item[0] == "#") {
            $type= "th";
            $old_type = $type;
            $item = substr($item, 1);
        }
        $trim_item = trim($item);
        $start = substr($trim_item, 0, 5);
        if($start == "align" || $start== "colsp" ||$start == "style" ||
            $start == "scope" ||  $start== "rowsp"|| $start == "valig") {
            $old_type = $type;
            $state = str_replace("\n", " ", $trim_item);
            continue;
        }
        if($out[strlen($out) - 2] != "r") {
            $out .= "</$old_type>";
        }
        $out .= "<$type $state>\n$trim_item";
        $state = "";
        $old_type = $type;
        $type = "td";
    }
    $out .= "</$old_type></tr></table>";
    return $out;
}

/**
 * Used to convert {{cite }} to a numbered link to a citation
 *
 * @param array $matches from regular expression to check for {{cite }}
 * @param int init used to initialize counter for citations
 * @return string a HTML link to citation in current document
 */
function citeCallback($matches, $init = -1)
{
    static $ref_count = 1;
    if($init != -1) {
        $ref_count = $init;
        return "";
    }
    $out = "<sup>(<a id=\"cite_$ref_count\" ".
        "href=\"#ref_$ref_count\">$ref_count</a>)</sup>";
    $ref_count++;
    return $out;
}

/**
 * Used to changes spaces to underscores in links generated from our earlier
 * matching rules
 *
 * @param array $matches from regular expression to check for links
 * @return string result of correcting link
 */
function fixLinksCallback($matches)
{
    $out = $matches[2].'"'.str_replace(" ", "_", $matches[3]).'"';
    return $out;
}

?>
