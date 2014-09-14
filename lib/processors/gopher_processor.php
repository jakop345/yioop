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

$PAGE_PROCESSORS["text/gopher"] = "GopherProcessor";

/**
 * Load parent class, if needed.
 */
require_once BASE_DIR."/lib/processors/html_processor.php";
 /**
 * Used to create crawl summary information
 * for gopher protocol pages
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage processor
 */
class GopherProcessor extends HtmlProcessor
{
    /**
     * Used to extract the title, description and links from
     * a string consisting of gopher page data.
     *
     * @param string $page gopher contents
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array  a summary of the contents of the page
     *
     */
    function process($page, $url)
    {
        $summary = NULL;
        $lines = explode("\r\n", $page);
        $out_page = "<html><title></title><body>";
        $old_type = "@";
        $okay_types = array("0", "1", "3", "4", "5", "6", "9", "g", "h", "I");
        foreach($lines as $line) {
            if(!isset($line[0])) { continue; }
            $type = $line[0];
            if($type != $old_type) {
                if($type == 'i') {
                    $out_page .= "<div>";
                } else if($old_type == 'i') {
                    $out_page .= "</div>";
                }
            }
            $rest = substr($line, 1);
            $line_parts = explode("\t", $rest);
            if($type == 'i') {
                $out_page .= $line_parts[0]."\n";
            } else if(in_array($type, $okay_types) && count($line_parts) == 4) {
                $scheme = "gopher://";
                $text = $line_parts[0];
                $path = $line_parts[1];
                $host = $line_parts[2];
                $port = $line_parts[3];
                $port_string = "";
                $use_host = false;
                if($port != "70") {
                    $port_string = ":$port";
                }
                if(substr($path, 0, 4) == "URL:") {
                    $link = substr($path, 4);
                } else {
                    $path = "/$type$path";
                    $link = "$scheme$host$port_string$path";
                }
                $out_page .= "<div><a href='$link'>".
                    "$text</a></div>";
            } else {
                $out_page .= "<div>{$line_parts[0]}</div>";
            }
        }
        $out_page .= "</body></html>";
        $summary = parent::process($out_page, $url);
        return $summary;
    }

}
?>
