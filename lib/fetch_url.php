<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * 
 * Code used to manage HTTP requests from one or more URLS
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */ 

require_once BASE_DIR."/lib/crawl_constants.php";

class FetchUrl implements CrawlConstants
{

    /**
     *  Make multi_curl requests for an array of sites with urls
     *
     *  @param array $sites  an array containing urls of pages to request
     *  @param bool $timer  flag, true means print timing statistics to log
     *  @param string $key  the component of $sites[$i] that has the value of a url to get
     *                defaults to URL
     *  @param string $value  component of $sites[$i] in which to store the page that was gotten
     *  @param string $hash  component of $sites[$i] in which to store a hash of page for de-deuplication
     *                 purposes
     * 
     *  @return array an updated array with the contents of those pages
     */ 

    public static function getPages($sites, $timer = false, $key=CrawlConstants::URL, $value=CrawlConstants::PAGE, $hash=CrawlConstants::HASH)
    {
        static $ex_cnt = 0;

        $agent_handler = curl_multi_init(); 

        $active = NULL;

        $start_time = microtime();

        //Set-up requests
        for($i = 0; $i < count($sites); $i++) {
            $sites[$i][0] = curl_init();

            curl_setopt($sites[$i][0], CURLOPT_USERAGENT, USER_AGENT);
            curl_setopt($sites[$i][0], CURLOPT_URL, $sites[$i][$key]);
            curl_setopt($sites[$i][0], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($sites[$i][0], CURLOPT_MAXREDIRS, 5);
            curl_setopt($sites[$i][0], CURLOPT_AUTOREFERER, true);
            curl_setopt($sites[$i][0], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($sites[$i][0], CURLOPT_CONNECTTIMEOUT, PAGE_TIMEOUT);
            curl_setopt($sites[$i][0], CURLOPT_TIMEOUT, PAGE_TIMEOUT);
            curl_setopt($sites[$i][0], CURLOPT_HTTPHEADER, array('Range: bytes=0-'.PAGE_RANGE_REQUEST));
            curl_multi_add_handle($agent_handler, $sites[$i][0]);
        }
        if($timer) {
            crawlLog("  Init Get Pages ".(changeInMicrotime($start_time)));
        }
        $start_time = microtime();
        $start = time();

        //Wait for responses
        do {
            $mrc = @curl_multi_exec($agent_handler, $active);
        } while (time() - $start < PAGE_TIMEOUT && $mrc == CURLM_CALL_MULTI_PERFORM );

        if(time() - $start > PAGE_TIMEOUT) {crawlLog("  TIMED OUT!!!");}

        while (time()-$start < PAGE_TIMEOUT && $active && $mrc == CURLM_OK) {
            if (curl_multi_select($agent_handler, 1) != -1) {
                do {
                     $mrc = @curl_multi_exec($agent_handler, $active);
                } while (time()-$start < PAGE_TIMEOUT && $mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        if($timer) {
            crawlLog("  Page Request time ".(changeInMicrotime($start_time)));
        }
        $start_time = microtime();

        //Process returned pages
        for($i = 0; $i < count($sites); $i++) {
            if($sites[$i][0]) { 

                // Get Data and Message Code
                $content = @curl_multi_getcontent($sites[$i][0]);
                $sites[$i][self::HTTP_CODE] = curl_getinfo($sites[$i][0], CURLINFO_HTTP_CODE);
                if(!$sites[$i][self::HTTP_CODE]) {
                    $sites[$i][self::HTTP_CODE] = curl_error($sites[$i][0]);
                }

                // Store Data into our $sites array, create a hash for deduplication purposes
                if(isset($content)) {
                    $sites[$i][$value] = mb_substr($content, 0, PAGE_RANGE_REQUEST);
                    //to do dedup we strip script, noscript, and style tags as well as their content, then we strip tags, get rid of whitespace and hash
                    $strip_array = array('@<script[^>]*?>.*?</script>@si', '@<noscript[^>]*?>.*?</noscript>@si', '@<style[^>]*?>.*?</style>@si');
                    $dedup_string = preg_replace($strip_array, '', $sites[$i][$value]);
                    $dedup_string = preg_replace('/\W+/', '', strip_tags($dedup_string));
                    $sites[$i][$hash] = crawlHash($dedup_string);

                }

                //Get Time, Mime type and Character encoding
                $sites[$i][self::TIMESTAMP] = time();

                $type_parts = explode(";", curl_getinfo($sites[$i][0], CURLINFO_CONTENT_TYPE));

                $sites[$i][self::TYPE] = trim($type_parts[0]);
                if(isset($type_parts[1])) {
                    $encoding_parts = explode("charset=", $type_parts[1]);
                    if(isset($encoding_parts[1])) {
                        $sites[$i][self::ENCODING] = mb_strtoupper(trim($encoding_parts[1])); //hopefuly safe to trust encoding sent
                    }
                } else {
                    $sites[$i][self::ENCODING] = mb_detect_encoding($content, 'auto');
                }


                curl_multi_remove_handle($agent_handler, $sites[$i][0]);
                // curl_close($sites[$i][0]);
            } //end big if

        } //end for

        if($timer) {
            crawlLog("  Get Page Content time ".(changeInMicrotime($start_time)));
        }
        curl_multi_close($agent_handler);

        return $sites;
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
