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
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Used to load common constants among crawl components */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */

class WikiParser implements CrawlConstants
{
    /**
     * Used to initialize the arrays of match/replacements used to format
     * wikimedia syntax into HTML (not perfectly since we are only doing
     * regexes)
     *
     * @param string $base_address base url for link substitutions
     */
    function __construct($base_address = "")
    {
        $substitutions = array(
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
            array('/{{center\|(.+?)}}/s', "<div class='center'>$1</div>"),
            array('/{{left\|(.+?)}}/s', "<div class='align-left'>$1</div>"),
            array('/{{right\|(.+?)}}/s', "<div class='align-right'>$1</div>"),
            array('/{{smallcaps\|(.+?)}}/s', "<small>$1</small>"),
            array('/{{Hatnote\|(.+?)}}/si', "($1)"),
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
            array('/\r/', ""),
        );

        $this->matches = array();
        $this->replaces = array();
        $this->base_address = $base_address;
        foreach($substitutions as $substitution) {
            list($this->matches[], $this->replaces[]) = $substitution;
        }
    }


    /**
     *
     */
    function parse($document)
    {
        $toc = $this->makeTableOfContents($document);
        list($document, $references) = $this->makeReferences($document);
        $document = preg_replace_callback('/(\A|\n){\|(.*?)\n\|}/s',
            "makeTableCallback", $document);
        if(strlen($document) < PAGE_RANGE_REQUEST) {
            $document = substr($document, 0, PAGE_RANGE_REQUEST);
        }
        $document = preg_replace($this->matches, $this->replaces, $document);
        $document = preg_replace_callback("/((href=)\"([^\"]+)\")/",
            "fixLinksCallback", $document);
        $doc_parts = preg_split("/\n\n+/", $document);
        $document = "";
        $space_like = array(" ", "\t");
        foreach($doc_parts as $part) {
            if(trim($part) == "") {
                continue;
            }
            $start = substr($part, 0, 2);
            if(in_array($start[0], $space_like)) {
                $document .= "\n<pre>\n".ltrim($part)."\n</pre>\n";
            } else if($start == ": " || $start == ":\t") {
                $document .= "\n<div class='indent'>\n".substr($part, 2).
                    "\n</div>\n";
            } else {
                $document .= "\n<div>\n".$part. "\n</div>\n";
            }
        }
        $document = $this->insertReferences($document, $references);
        $document = $this->insertTableOfContents($document, $toc);
        return $document;
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
        $min_sections_for_toc = 4;
        preg_match_all('/(\A|\n)==\s*([^=]+)\s*==/', $page, $matches);
        if(isset($matches[2]) && count($matches[2]) >= $min_sections_for_toc) {
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
        $base_address = $this->base_address;
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
                crawlTimeoutLog("..Making wiki references outer..");
                if(count($ref_parts) > 0) {
                    $ref_data = array();
                    $type = trim(strtolower($ref_parts[0]));
                    array_shift($ref_parts);
                    foreach($ref_parts as $part) {
                        crawlTimeoutLog("..Making wiki references inner..");
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
        crawlTimeoutLog("..Making Wiki Tables..");
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
