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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**Load base controller class, if needed. */
require_once BASE_DIR."/controllers/controller.php";
/** To extract words from the query*/
require_once BASE_DIR."/lib/phrase_parser.php";
/** Get the crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Loads common constants for web crawling */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Controller used to handle search requests to SeekQuarry
 * search site. Used to both get and display
 * search results.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class SearchController extends Controller implements CrawlConstants
{
    /**
     * Says which models to load for this controller.
     * PhraseModel is used to extract words from the query; CrawlModel 
     * is used for cached web page requests
     * @var array
     */
    var $models = array("phrase", "crawl");
    /**
     * Says which views to load for this controller.
     * The SearchView is used for displaying general search results as well 
     * as the initial search screen; NocacheView
     * is used on a cached web page request that fails
     * @var array
     */
    var $views = array("search",  "nocache");
    /**
     * Says which activities (roughly methods invoke from the web) this 
     * controller will respond to
     * @var array
     */
    var $activities = array("query", "cache", "related", "signout");

    /**
     * This is the main entry point for handling a search request.
     *
     * ProcessRequest determines the type of search request (normal request , 
     * cache request, or related request), or if its a 
     * user is returning from the admin panel via signout. It then calls the 
     * appropriate method to handle the given activity.Finally, it draw the 
     * search screen.
     */
    function processRequest() 
    {
        $data = array();
        $view = "search";
        $start_time = microtime();

        if(isset($_SESSION['MAX_PAGES_TO_SHOW']) ) {
            $results_per_page = $_SESSION['MAX_PAGES_TO_SHOW'];
        } else {
            $results_per_page = NUM_RESULTS_PER_PAGE;
        }

        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
            $token_okay = $this->checkCSRFToken('YIOOP_TOKEN', $user);
            if($token_okay === false) {
                unset($_SESSION['USER_ID']);
                $user = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            $user = $_SERVER['REMOTE_ADDR']; 
        } 


        if(isset($_REQUEST['a'])) {
            if(in_array($_REQUEST['a'], $this->activities)) {

                $activity = $_REQUEST['a'];

                if($activity == "signout") {
                    unset($_SESSION['USER_ID']);
                    $user = $_SERVER['REMOTE_ADDR'];
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('search_controller_logout_successful')."</h1>')";
                }

                if(isset($_REQUEST['arg'])) {
                    $arg = $_REQUEST['arg'];
                } else {
                    $activity = "query";
                }
            } else {
                $activity = "query";
            }
        } else {
            $activity = "query";
        }
        if(isset($_REQUEST['its']) || isset($_SESSION['its'])) {
            $its = (isset($_REQUEST['its'])) ? $_REQUEST['its'] : 
                $_SESSION['its'];
            $index_time_stamp = $this->clean($its, "int");
        } else {
            $index_time_stamp = 0; //use the default crawl index
        }
        if(isset($_REQUEST['q']) && strlen($_REQUEST['q']) > 0 
            || $activity != "query") {
            if($activity == "query") {
                $activity_array = $this->extractActivityQuery();
                $query = $activity_array[0]; // dirty
                $activity = $activity_array[1];
                $arg = $activity_array[2]; 
            }

            if($activity != "cache") {
                if(!isset($query)) {
                    $query = NULL;
                }
                if(isset($_REQUEST['limit'])) {
                    $limit = $this->clean($_REQUEST['limit'], "int");
                } else {
                    $limit = 0;
                }
                $data = 
                    $this->processQuery(
                        $query, $activity, $arg, 
                        $results_per_page, $limit, $index_time_stamp); 
                        // calculate the results of a search if there is one
            } else {
                $highlight = true;
                if(!isset($query)) {
                    $query = $_REQUEST['q']; //dirty
                    list(,$query_activity,) = $this->extractActivityQuery();
                    if($query_activity != "query") {$highlight = false;}
                }
                $summary_offset = NULL;
                if(isset($_REQUEST['so'])) {
                    $summary_offset = $this->clean($_REQUEST['so'], "int");
                }
                $this->cacheRequest($query, $arg, $summary_offset, $highlight,
                    $index_time_stamp);
            }
        }

        $data['its'] = (isset($index_time_stamp)) ? $index_time_stamp : 0;

        $data['YIOOP_TOKEN'] = $this->generateCSRFToken($user);


        $data['ELAPSED_TIME'] = changeInMicrotime($start_time);
        $this->displayView($view, $data);
    }

    /**
     * Searches the database for the most relevant pages for the supplied search
     * terms. Renders the results to the HTML page.
     *
     * @param string $query a string containing the words to search on
     * @param string $activity besides a straight search for words query, 
     *      one might have other searches, such as a search for related pages.
     *      this argument says what kind of search to do.
     * @param string $arg for a search other than a straight word query this 
     *      argument provides auxiliary information on how to conduct the
     *      search. For instance on a related web page search, it might provide 
     *      the url of the site with which to perform the related search.
     * @param int $results_per_page the maixmum number of search results 
     *      that can occur on a page
     * @param int $limit the first page of all the pages with the query terms
     *      to return. For instance, if 10 then the tenth highest ranking page
     *      for those query terms will be return, then the eleventh, etc.
     * @param int $index_name the timestamp of an index to use, if 0 then 
     *      default used
     * @return array an array of at most results_per_page many search results
     */
    function processQuery($query, $activity, $arg, $results_per_page, 
        $limit = 0, $index_name = 0) 
    {
        if($index_name == 0) {
            $index_name = $this->crawlModel->getCurrentIndexDatabaseName();
        }

        $this->phraseModel->index_name = $index_name;
        $this->crawlModel->index_name = $index_name;
        
        switch($activity)
        {
            case "related":
            $data['QUERY'] = "related:$arg";
                $url = $arg;
                $summary_offset = NULL;
                if(isset($_REQUEST['so'])) {
                    $summary_offset = $this->clean($_REQUEST['so'], "int");
                }
                if($summary_offset === NULL) {
                    $summary_offset = 
                        $this->phraseModel->lookupSummaryOffset($url);
                }
                $crawl_item = $this->crawlModel->getCrawlItem(
                    crawlHash($url), $summary_offset);

                $top_phrases  = 
                    $this->phraseModel->getTopPhrases($crawl_item, 3);
                $top_query = implode(" ", $top_phrases);
                $phrase_results = $this->phraseModel->getPhrasePageResults(
                    $top_query, $limit, $results_per_page, false);
                $data['PAGING_QUERY'] = "index.php?c=search&a=related&arg=".
                    urlencode($url)."&so=$summary_offset";
            break;

            case "query":
            default:
                if(trim($query) != "") { 
                    $phrase_results = $this->phraseModel->getPhrasePageResults(
                        $query, $limit, $results_per_page);
                }
                $data['PAGING_QUERY'] = "index.php?q=".urlencode($query);
                $data['QUERY'] = urlencode($this->clean($query,"string"));

            break;
        }

        $data['PAGES'] = (isset($phrase_results['PAGES'])) ?
             $phrase_results['PAGES']: array();
        $data['TOTAL_ROWS'] = (isset($phrase_results['TOTAL_ROWS'])) ? 
            $phrase_results['TOTAL_ROWS'] : 0;
        $data['LIMIT'] = $limit;
        $data['RESULTS_PER_PAGE'] = $results_per_page;


        return $data;

    }

    /**
     * This method is responsible for parsing out the kind of query 
     * from the raw query string
     *
     * This method parses the raw query string for query activities. 
     * It parses the name of each activity and its argument
     *
     * @return array list of search activities parsed out of the search string
     */
    function extractActivityQuery() {

        $query = mb_ereg_replace("(\s)+", " ", $_REQUEST['q']);
        $query = mb_ereg_replace("\s:\s", ":", $_REQUEST['q']);
        
        $query_parts = mb_split(" ", $query);
        $count = count($query_parts);
            
        $out_query = "";
        $activity = "query";
        $arg = "";
        $space = "";
        for($i = 0; $i < $count; $i++) {
            foreach($this->activities as $a_activity) {
                $in_pos = mb_strpos($query_parts[$i], "$a_activity:");

                if($in_pos !== false &&  $in_pos == 0) {

                    $out_query = "";
                    $activity = $a_activity;
                    $arg = mb_substr($query_parts[$i], strlen("$a_activity:"));
                    continue;
                }
            }
            $out_query .= $space.$query_parts[$i];
            $space = " "; 
        }

        $activity_array = array($out_query, $activity, $arg);

        return $activity_array; 
    }

    /**
     * Used to get and render a cached web page
     *
     * @param string $query the list of query terms
     * @param string $url the url of the page to find the cached version of
     * @param bool $highlight whether or not to highlight the query terms in
     *      the cached page
     * @param int $crawl_time the timestamp of the crawl to look up the cached
     *      page in
     */
    function cacheRequest($query, $url, $summary_offset,
        $highlight=true, $crawl_time = 0)
    {

        if($crawl_time == 0) {
            $crawl_time = $this->crawlModel->getCurrentIndexDatabaseName();
        }

        $this->phraseModel->index_name = $crawl_time;
        $this->crawlModel->index_name = $crawl_time;
        if($summary_offset === NULL) {
            $summary_offset = $this->phraseModel->lookupSummaryOffset($url);
        }

        if(!$crawl_item = $this->crawlModel->getCrawlItem(crawlHash($url), 
            $summary_offset)) {

            $this->displayView("nocache", $data);
            exit();
        }

        $data = array();
        $machine = $crawl_item[self::MACHINE];
        $machine_uri = $crawl_item[self::MACHINE_URI];
        $page = $crawl_item[self::HASH];
        $offset = $crawl_item[self::OFFSET];
        $cache_item = $this->crawlModel->getCacheFile($machine, 
            $machine_uri, $page, $offset, $crawl_time);

        $cache_file = $cache_item[self::PAGE];

        $request = $cache_item['REQUEST'];

        $meta_words = array('link\:', 'site\:', 
            'filetype\:', 'info\:', '\-', 
            'index:', 'i:', 'weight:', 'w:');
        foreach($meta_words as $meta_word) {
            $pattern = "/(\s)($meta_word(\S)+)/";
            $query = preg_replace($pattern, "", $query);
        }
        $query = str_replace("'", " ", $query);
        $query = str_replace('"', " ", $query);
        $query = str_replace('\\', " ", $query);
        $query = str_replace('|', " ", $query);
        $query = $this->clean($query, "string");

        $page_url = $url;

        $phrase_string = mb_ereg_replace("[[:punct:]]", " ", $query); 
        $words = mb_split(" ",$phrase_string);
        if(!$highlight) {
            $words = array();
        }

        $date = date ("F d Y H:i:s", $cache_item[self::TIMESTAMP]);

        $dom = new DOMDocument();

        $did_dom = @$dom->loadHTML($cache_file);

        $xpath = new DOMXPath($dom);


        $body =  $dom->getElementsByTagName('body')->item(0);
        if($body == false) {
            $cache_file = "<html><head><title>Yioop! Cache</title></head>".
                "<body>".htmlentities($cache_file)."</body></html>";
            $dom = new DOMDocument();
            @$dom->loadHTML($cache_file);
            $body =  $dom->getElementsByTagName('body')->item(0);
        }
        $first_child = $body->firstChild;

        $divNode = $dom->createElement('div');
        $divNode = $body->insertBefore($divNode, $first_child);
        $divNode->setAttributeNS("","style", "border-color: black; ".
            "border-style:solid; border-width:3px; ".
            "padding: 5px; background-color: white");

        $textNode = $dom->createTextNode(tl('search_controller_cached_version', 
            "$page_url", $date));
        $textNode = $divNode->appendChild($textNode);

        $body = $this->markChildren($body, $words, $dom);

        $newDoc = $dom->saveHTML();

        $colors = array("yellow", "orange", "grey", "cyan");
        $color_count = count($colors);

        $i = 0;
        foreach($words as $word) {
            //only mark string of length at least 2
            if(strlen($word) > 1) {
                $mark_prefix = crawlHash($word);
                if(stristr($mark_prefix, $word) !== false) {
                    $mark_prefix = preg_replace(
                    "/$word/i", '', $mark_prefix);
                }
                $match = $mark_prefix.$word;
                $newDoc = preg_replace("/$match/i", 
                    '<span style="background-color:'.
                    $colors[$i].'">$0</span>', $newDoc);
                $i = ($i + 1) % $color_count;
                $newDoc = preg_replace("/".$mark_prefix."/", "", $newDoc);
            }
        }


        echo $newDoc;
        exit();
    }


    /**
     *  Used in rendering a cached web page to highlight the search terms.
     * 
     *  @param object $node DOM object to mark html elements of
     *  @param array $words an array of words to be highlighted
     *  @param object $dom a DOM object for the whole document
     *  @return object the node modified to now have highlighting
     */
    function markChildren($node, $words, $dom)
    {

        if(!isset($node->childNodes->length)) {
            return $node;
        }
        for($k = 0; $node->childNodes->length; $k++) 
        {
            if(!$node->childNodes->item($k)) { break; }
            
            $clone = $node->childNodes->item($k)->cloneNode(true);

            if($clone->nodeType == XML_TEXT_NODE) { 
                $text = $clone->textContent;

                foreach($words as $word) {
                    //only mark string of length at least 2
                    if(strlen($word) > 1) {
                        $mark_prefix = crawlHash($word);
                        if(stristr($mark_prefix, $word) !== false) {
                            $mark_prefix = preg_replace(
                            "/$word/i", '', $mark_prefix);
                        }
                        $text = preg_replace(
                            "/$word/i", $mark_prefix.'$0', $text);
                    }
                }

                $textNode =  $dom->createTextNode($text);
                $node->replaceChild($textNode, $node->childNodes->item($k));
            } else {
                $clone = $this->markChildren($clone, $words, $dom);

                $node->replaceChild($clone, $node->childNodes->item($k));

            }
        }
        
        return $node;
    }

}
?>
