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
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Reads in constants used as enums used for storing web sites
 */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * 
 * Code used to manage HTTP requests from one or more URLS
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
class FetchUrl implements CrawlConstants
{

    /**
     * Make multi_curl requests for an array of sites with urls
     *
     * @param array $sites  an array containing urls of pages to request
     * @param bool $timer  flag, true means print timing statistics to log
     * @param string $key  the component of $sites[$i] that has the value of 
     *      a url to get defaults to URL
     * @param string $value component of $sites[$i] in which to store the 
     *      page that was gotten
     * @param string $hash component of $sites[$i] in which to store a hash 
     *      of page for de-deuplication purposes
     * 
     *  @return array an updated array with the contents of those pages
     */ 

    public static function getPages($sites, $timer = false, 
        $key=CrawlConstants::URL, $value=CrawlConstants::PAGE, 
        $hash=CrawlConstants::HASH)
    {
        static $ex_cnt = 0;

        $agent_handler = curl_multi_init(); 

        $active = NULL;

        $start_time = microtime();

        //Set-up requests
        for($i = 0; $i < count($sites); $i++) {
            if(isset($sites[$i][$key])) {
                $sites[$i][0] = curl_init();
                $ip_holder[$i] = fopen(CRAWL_DIR."/tmp$i.txt", 'w+');
                curl_setopt($sites[$i][0], CURLOPT_USERAGENT, USER_AGENT);
                curl_setopt($sites[$i][0], CURLOPT_URL, $sites[$i][$key]);
                curl_setopt($sites[$i][0], CURLOPT_VERBOSE, true);
                curl_setopt($sites[$i][0], CURLOPT_STDERR, $ip_holder[$i]);
                curl_setopt($sites[$i][0], CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($sites[$i][0], CURLOPT_MAXREDIRS, 5);
                curl_setopt($sites[$i][0], CURLOPT_AUTOREFERER, true);
                curl_setopt($sites[$i][0], CURLOPT_RETURNTRANSFER, true);
                curl_setopt($sites[$i][0], CURLOPT_CONNECTTIMEOUT, PAGE_TIMEOUT);
                curl_setopt($sites[$i][0], CURLOPT_TIMEOUT, PAGE_TIMEOUT);
                curl_setopt($sites[$i][0], CURLOPT_HEADER, true);
                curl_setopt($sites[$i][0], CURLOPT_HTTPHEADER, 
                    array('Range: bytes=0-'.PAGE_RANGE_REQUEST));
                curl_multi_add_handle($agent_handler, $sites[$i][0]);
            }
        }
        if($timer) {
            crawlLog("  Init Get Pages ".(changeInMicrotime($start_time)));
        }
        $start_time = microtime();
        $start = time();

        //Wait for responses
        do {
            $mrc = @curl_multi_exec($agent_handler, $active);
        } while (time() - $start < PAGE_TIMEOUT && 
            $mrc == CURLM_CALL_MULTI_PERFORM );

        if(time() - $start > PAGE_TIMEOUT) {crawlLog("  TIMED OUT!!!");}

        while (time()-$start < PAGE_TIMEOUT && $active && $mrc == CURLM_OK) {
            if (curl_multi_select($agent_handler, 1) != -1) {
                do {
                     $mrc = @curl_multi_exec($agent_handler, $active);
                } while (time()-$start < PAGE_TIMEOUT && 
                    $mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        if($timer) {
            crawlLog("  Page Request time ".(changeInMicrotime($start_time)));
        }
        $start_time = microtime();

        //Process returned pages
        for($i = 0; $i < count($sites); $i++) {
            if(isset($ip_holder[$i]) ) {
                $ip_addresses = self::getCurlIp($ip_holder[$i]);
                fclose($ip_holder[$i]);
            }
            if(isset($sites[$i][0]) && $sites[$i][0]) { 
                // Get Data and Message Code
                $content = @curl_multi_getcontent($sites[$i][0]);

                if(isset($content)) {
                    $site = self::parseHeaderPage($content, $value);
                    $sites[$i] = array_merge($sites[$i], $site);
                    /* 
                       Store Data into our $sites array, create a hash for 
                       deduplication purposes
                     */
                    $sites[$i][$hash] = 
                        self::computePageHash($sites[$i][$value]);

                }

                $sites[$i][self::HTTP_CODE] = 
                    curl_getinfo($sites[$i][0], CURLINFO_HTTP_CODE);
                if(!$sites[$i][self::HTTP_CODE]) {
                    $sites[$i][self::HTTP_CODE] = curl_error($sites[$i][0]);
                }
                if($ip_addresses) {
                    $sites[$i][self::IP_ADDRESSES] = $ip_addresses;
                } else {
                    $sites[$i][self::IP_ADDRESSES] = array("0.0.0.0");
                }

                //Get Time, Mime type and Character encoding
                $sites[$i][self::TIMESTAMP] = time();

                $type_parts = 
                    explode(";", curl_getinfo($sites[$i][0], 
                        CURLINFO_CONTENT_TYPE));

                $sites[$i][self::TYPE] = trim($type_parts[0]);
                if(isset($type_parts[1])) {
                    $encoding_parts = explode("charset=", $type_parts[1]);
                    if(isset($encoding_parts[1])) {
                        $sites[$i][self::ENCODING] = 
                            mb_strtoupper(trim($encoding_parts[1])); 
                                //hopefully safe to trust encoding sent
                    }
                } else {
                    $sites[$i][self::ENCODING] = 
                        mb_detect_encoding($content, 'auto');
                }


                curl_multi_remove_handle($agent_handler, $sites[$i][0]);
                // curl_close($sites[$i][0]);
            } //end big if

        } //end for

        if($timer) {
            crawlLog("  Get Page Content time ".
                (changeInMicrotime($start_time)));
        }
        curl_multi_close($agent_handler);

        return $sites;
    }

    /**
     * Computes a hash of a string containing page data for use in
     * deduplication of pages with similar content
     *
     *  @param string &$page  web page data
     *  @return string 8 byte hash to identify page contents
     */
    public static function computePageHash(&$page)
    {
        /* to do dedup we strip script, noscript, and style tags 
           as well as their content, then we strip tags, get rid 
           of whitespace and hash
         */
        $strip_array = 
            array('@<script[^>]*?>.*?</script>@si', 
                '@<noscript[^>]*?>.*?</noscript>@si', 
                '@<style[^>]*?>.*?</style>@si');
        $dedup_string = preg_replace(
            $strip_array, '', $page);
        $dedup_string_old = preg_replace(
            '/\W+/', '', $dedup_string);
        $dedup_string = strip_tags($dedup_string_old);
        if($dedup_string == "") {
            $dedup_string = $dedup_string_old;
        }
        $dedup_string = preg_replace(
            '/\W+/', '', $dedup_string);

        return crawlHash($dedup_string, true);
    }

    /**
     *  Splits an http response document into the http headers sent
     *  and the web page returned. Parses out useful information from
     *  the header and return an array of these two parts and the useful info.
     *
     *  @param string &$header_and_page
     *  @param string $value
     *  @return array info array consisting of a header, page for an http
     *      response, as well as parsed from the header the server, server
     *      version, operating system, encoding, and date information.
     */
    public static function parseHeaderPage(&$header_and_page, 
        $value=CrawlConstants::PAGE)
    {
        $new_offset = 0;
        // header will include all redirect headers
        do {
            $CRLFCRLF = strpos($header_and_page, "\x0D\x0A\x0D\x0A", 
                $new_offset);
            $LFLF = strpos($header_and_page, "\x0A\x0A", $new_offset);
            //either two CRLF (what spec says) or two LF's to be safe
            $old_offset = $new_offset;
            $header_offset = ($CRLFCRLF > 0) ? $CRLFCRLF : $LFLF;
            $new_offset = ($CRLFCRLF > 0) ? $header_offset + 4 
                : $header_offset + 2;
            $redirect_pos = strpos($header_and_page, 'Location:', $old_offset);
        } while($redirect_pos !== false && $redirect_pos < $new_offset);

        $site = array();
        $site[CrawlConstants::HEADER] = 
            substr($header_and_page, 0, $header_offset);
        $site[$value] = ltrim(substr($header_and_page, $header_offset));

        $lines = explode("\n", $site[CrawlConstants::HEADER]);
        $first_line = array_shift($lines);
        $response = preg_split("/(\s+)/", $first_line);
        $site[CrawlConstants::HTTP_CODE] = @trim($response[1]);
        foreach($lines as $line) {
            $line = trim($line);
            if(stristr($line, 'Server:')) {
                $server_parts = explode("Server:", $line);
                $server_name_parts = @explode("/", $server_parts[1]);
                $site[CrawlConstants::SERVER] = @trim($server_name_parts[0]);
                if(isset($server_name_parts[1])) {
                    $version_parts = explode("(", $server_name_parts[1]);
                    $site[CrawlConstants::SERVER_VERSION] = 
                        @trim($version_parts[0]);
                    if(isset($version_parts[1])) {
                        $os_parts = explode(")", $version_parts[1]);
                        $site[CrawlConstants::OPERATING_SYSTEM] =
                            @trim($os_parts[0]);
                    }
                }
            }
            if(stristr($line, 'charset=')) {
                $line_parts = explode("charset=", $line);
                $site[CrawlConstants::ENCODING] = @trim($line_parts[1]);
            }
            if(stristr($line, 'Last-Modified:')) {
                $line_parts = explode("Last-Modified:", $line);
                $site[CrawlConstants::MODIFIED] = 
                    strtotime(@trim($line_parts[1]));
            }

        }
        if(!isset($site[CrawlConstants::ENCODING]) ) {
            $site[CrawlConstants::ENCODING] =
                mb_detect_encoding($site[$value], 'auto');
        }
        if(!isset($site[CrawlConstants::SERVER]) ) {
            $site[CrawlConstants::SERVER] = "unknown";
        }
        return $site;
    }

    /**
     * Computes the IP address from a file pointer assumed to be pointing 
     * at STDERR output from a curl request
     *
     * @param resource $fp a file pointer to STDERR of a curl request
     * @return string IPv4 address as a string of dot separated quads.
     */
    static function getCurlIp($fp) 
    {
        rewind($fp);
        $str = fread($fp, 8192);
        if (preg_match_all('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', 
            $str, $matches)) {
            return array_unique($matches[0]);
        } else {
            return false;
        }
    }


    /**
     *  Make a curl request for the provide url
     *
     *  @param string $site  url of page to request
     *  @param string $post_data  any data to be POST'd to the URL
     * 
     *  @return string the contents of what the curl request fetched
     */
    public static function getPage($site, $post_data = NULL) 
    {

        $agent = curl_init();
        curl_setopt($agent, CURLOPT_USERAGENT, USER_AGENT);
        curl_setopt($agent, CURLOPT_URL, $site);
        curl_setopt($agent, CURLOPT_AUTOREFERER, true);
        curl_setopt($agent, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($agent, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($agent, CURLOPT_FAILONERROR, true);

        curl_setopt($agent, CURLOPT_TIMEOUT, PAGE_TIMEOUT);
        curl_setopt($agent, CURLOPT_CONNECTTIMEOUT, PAGE_TIMEOUT);
        if($post_data != NULL) {
            curl_setopt($agent, CURLOPT_POST, true);
            curl_setopt($agent, CURLOPT_POSTFIELDS, $post_data);
        }

        $response = curl_exec($agent);

        return $response;
    }
}
?>
