<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2012
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
/** For getting pages from a mirror if decide not to handle ourselves*/
require_once BASE_DIR."/lib/fetch_url.php";
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
    var $models = array("phrase", "crawl", "searchfilters", "machine");
    /**
     * Says which views to load for this controller.
     * The SearchView is used for displaying general search results as well 
     * as the initial search screen; NocacheView
     * is used on a cached web page request that fails; RssView is used
     * to present search results according to the opensearch.org rss results
     * format.
     * @var array
     */
    var $views = array("search",  "nocache", "rss");
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
        $web_flag = true;
        $start_time = microtime();

        if(isset($_REQUEST['f']) && $_REQUEST['f']=='rss' &&
            RSS_ACCESS) {
            $view = "rss";
            $web_flag = false;
        } else if(isset($_REQUEST['f']) && $_REQUEST['f']=='serial' &&
            RSS_ACCESS) {
            $view = "serial";
            $web_flag = false;
        } else if (!WEB_ACCESS) {
            return;
        }
        if(isset($_REQUEST['raw']) && $_REQUEST['raw'] == true) {
            $raw = true;
        } else {
            $raw = false;
        }

        if(isset($_REQUEST['num'])) {
            $results_per_page = $this->clean($_REQUEST['num'], "int");
        } else if(isset($_SESSION['MAX_PAGES_TO_SHOW']) ) {
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

        if($activity == "query" && $this->checkMirrorHandle()) {return; }

        $machine_urls = $this->machineModel->getQueueServerUrls();
        $current_its = $this->crawlModel->getCurrentIndexDatabaseName();

        if(isset($_REQUEST['its']) || isset($_SESSION['its'])) {
            $its = (isset($_REQUEST['its'])) ? $_REQUEST['its'] : 
                $_SESSION['its'];
            $index_time_stamp = $this->clean($its, "int");
        } else {
            $index_time_stamp = $current_its; 
                //use the default crawl index
        }
        if($web_flag) {
            $index_info =  $this->crawlModel->getInfoTimestamp(
                $index_time_stamp, $machine_urls);
            if($index_info == array() || $index_info["COUNT"] == 0) {
                if($index_time_stamp != $current_its) {
                    $index_time_stamp = $current_its;
                    $index_info =  $this->crawlModel->getInfoTimestamp(
                        $index_time_stamp, $machine_urls);
                    if($index_info == array()) { $index_info = NULL; }
                }
            }
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
                        $results_per_page, $limit, $index_time_stamp, $raw); 
                        // calculate the results of a search if there is one
            } else {
                $highlight = true;
                if(!isset($query)) {
                    $query = $_REQUEST['q']; //dirty
                    list(,$query_activity,) = $this->extractActivityQuery();
                    if($query_activity != "query") {$highlight = false;}
                }
                $this->cacheRequestAndOutput($arg,
                    $highlight, $query, $index_time_stamp);
                return;
            }
        }

        $data['its'] = (isset($index_time_stamp)) ? $index_time_stamp : 0;
        if($web_flag && $index_info !== NULL) {
            if(isset($index_info['IS_MIX'])) {
                $data['INDEX_INFO'] = tl('search_controller_mix_info',
                    $index_info['DESCRIPTION']);
            } else {
                $data['INDEX_INFO'] = tl('search_controller_crawl_info',
                    $index_info['DESCRIPTION'], 
                    $index_info['VISITED_URLS_COUNT'],
                    $index_info['COUNT']);
            }
        } else {
            $data['INDEX_INFO'] = "";
        }

        $data['YIOOP_TOKEN'] = $this->generateCSRFToken($user);

        $data['ELAPSED_TIME'] = changeInMicrotime($start_time);
        if ($view != "serial") {
            $this->displayView($view, $data);
        } else {
            echo webencode(serialize($data));
        }
    }

    /**
     * Used to check if there are any mirrors of the current server.
     * If so, it tries to distribute the query requests randomly amongst
     * the mirrors
     */
    function checkMirrorHandle()
    {
        $mirror_table_name = CRAWL_DIR."/".self::mirror_table_name;
        $handled = false;
        if(file_exists($mirror_table_name)) {
            $mirror_table = unserialize(file_get_contents($mirror_table_name));
            $mirrors = array();
            $time = time();
            foreach($mirror_table['machines'] as $entry) {
                if($time - $entry[3] < 2 * MIRROR_NOTIFY_FREQUENCY) {
                    if($entry[0] == "::1") {
                        $entry[0] = "[::1]";
                    }
                    $request = "http://".$entry[0].$entry[1];
                    $mirrors[] = $request;
                }
            }
            $count = count($mirrors);
            if($count > 0 ) {
                mt_srand();
                $rand = mt_rand(0, $count);
                // if ==$count, we'll let the current machine handle it
                if($rand < $count) {
                    $request = $mirrors[$rand]."?".$_SERVER["QUERY_STRING"];
                    echo FetchUrl::getPage($request);
                    $handled = true;
                }
            }
        }
        return $handled;
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
     * @param int $raw ($raw == 0) normal grouping, ($raw == 1)
     *      no grouping but page look-up for links, ($raw == 2) 
     *      no grouping done on data
     *
     * @return array an array of at most results_per_page many search results
     */
    function processQuery($query, $activity, $arg, $results_per_page, 
        $limit = 0, $index_name = 0, $raw = 0) 
    {
        $no_index_given = false;
        if($index_name == 0) {
            $index_name = $this->crawlModel->getCurrentIndexDatabaseName();
            $no_index_given = true;
        }
        $is_mix = $this->crawlModel->isCrawlMix($index_name);
        if($no_index_given && (!$this->phraseModel->indexExists($index_name)
                && !$is_mix)) {
                $data['SCRIPT'] = 
                        "doMessage('<h1 class=\"red\" >".
                        tl('search_controller_no_index_set').
                        "</h1>');";
                return $data;
        }

        $this->phraseModel->index_name = $index_name;
        $this->phraseModel->additional_meta_words = array();
        foreach($this->indexing_plugins as $plugin) {
            $plugin_name = ucfirst($plugin)."Plugin";
            $plugin_obj = new $plugin_name();
            $tmp_meta_words = $plugin_obj->getAdditionalMetaWords();
            $this->phraseModel->additional_meta_words = 
                array_merge($this->phraseModel->additional_meta_words, 
                    $tmp_meta_words);
        }

        $this->crawlModel->index_name = $index_name;

        $original_query = $query;
        $query = preg_replace('/no:cache/', "", $query);

        $use_cache_if_possible = ($original_query == $query) ? true : false;
        if(!isset($_REQUEST['network']) || $_REQUEST['network'] == "true") {
            $queue_servers = $this->machineModel->getQueueServerUrls();
            if($queue_servers != array() && file_exists(
                CRAWL_DIR.'/cache/'.self::index_data_base_name.
                $index_name)) {
                /*  add name_server to look up locations if it has
                    an IndexArchiveBundle of the correct timestamp 
                 */
                array_unshift($queue_servers, NAME_SERVER);
                array_unique($queue_servers);
            }
        } else {

            $queue_servers = array();
        }

        switch($activity)
        {
            case "related":
                $data['QUERY'] = "related:$arg";
                $url = $arg;

                $crawl_item = $this->crawlModel->getCrawlItem($url, 
                    $queue_servers);

                $top_phrases  = 
                    $this->phraseModel->getTopPhrases($crawl_item, 3);
                $top_query = implode(" ", $top_phrases);
                $phrase_results = $this->phraseModel->getPhrasePageResults(
                    $top_query, $limit, $results_per_page, false, NULL,
                    $use_cache_if_possible, $raw, $queue_servers);
                $data['PAGING_QUERY'] = "index.php?c=search&a=related&arg=".
                    urlencode($url);
                
                $data['QUERY'] = urlencode($this->clean($data['QUERY'],
                    "string"));
            break;

            case "query":
            default:
                if(trim($query) != "") {
                    $mix_metas = array("m:", "mix:");
                    foreach($mix_metas as $mix_meta) {
                        $pattern = "/(\s)($mix_meta(\S)+)/";
                        preg_match_all($pattern, $query, $matches);
                        if(isset($matches[2][0]) && !isset($mix_name)) {
                            $mix_name = substr($matches[2][0],
                                strlen($mix_meta));
                            $mix_name = str_replace("+", " ", $mix_name);
                        }
                        $query = preg_replace($pattern, "", $query);
                    }
                    if(isset($mix_name)) {
                        $tmp = $this->crawlModel->getCrawlMixTimestamp(
                            $mix_name);
                        if($tmp != false) {
                            $index_name = $tmp;
                            $is_mix = true;
                        }
                    }
                    if($is_mix) {
                        $mix = $this->crawlModel->getCrawlMix($index_name);
                        $query = 
                            $this->phraseModel->rewriteMixQuery($query, $mix);
                    }
                    $filter = $this->searchfiltersModel->getFilter();
                    $phrase_results = $this->phraseModel->getPhrasePageResults(
                        $query, $limit, $results_per_page, true, $filter,
                        $use_cache_if_possible, $raw, $queue_servers);
                    $query = $original_query;
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

    //*********BEGIN SEARCH API *********
    /**
     * Part of Yioop! Search API. Performs a normal search query and returns
     * associative array of query results
     *
     * @param string $query this can be any query string that could be
     *      entered into the search bar on Yioop! (other than related: and 
     *      cache: queries)
     * @param int $results_per_page number of results to return
     * @param int $limit first result to return from the ordered query results
     * @param int $raw ($raw == 0) normal grouping, ($raw == 1)
     *      no grouping but page look-up for links, ($raw == 2) 
     *      no grouping done on data
     *
     * @return array associative array of results for the query performed
     */
    public function queryRequest($query, $results_per_page, $limit = 0, 
        $raw = 0)
    {
        return (API_ACCESS) ? 
            $this->processQuery($query, "query", "", $results_per_page, 
                $limit, $raw) : NULL;
    }

    /**
     * Part of Yioop! Search API. Performs a related to a given url 
     * search query and returns associative array of query results
     *
     * @param string $url to find related documents for
     * @param int $results_per_page number of results to return
     * @param int $limit first result to return from the ordered query results
     * @param int $raw ($raw == 0) normal grouping, ($raw == 1)
     *      no grouping but page look-up for links, ($raw == 2) 
     *      no grouping done on data
     *
     * @return array associative array of results for the query performed
     */
    public function relatedRequest($url, $results_per_page, $limit = 0, 
        $crawl_time = 0, $raw = 0)
    {
        return (API_ACCESS) ? 
            $this->processQuery("", "related", $url, $results_per_page, 
                $limit, $crawl_time, $raw) : NULL;
    }

    /**
     * Part of Yioop! Search API. Performs a related to a given url 
     * search query and returns associative array of query results
     *
     * @param string $url to get cached page for
     * @param bool $highlight whether to put the search terms in the page
     *      in colored span tags.
     * @param string $terms space separated list of search terms
     * @param string $crawl_time timestamp of crawl to look for cached page in
     *
     * @return string with contents of cached page
     */
    public function cacheRequest($url, $highlight=true, $terms ="", 
        $crawl_time = 0)
    {
        if(!API_ACCESS) return false;
        ob_start();
        $this->cacheRequestAndOutput($url, $highlight, $terms, 
            $crawl_time);
        $cached_page = ob_get_contents();
        ob_end_clean();
        return $cached_page;
    }
    //*********END SEARCH API *********

    /**
     * Used to get and render a cached web page
     *
     * @param string $url the url of the page to find the cached version of
     * @param bool $highlight whether or not to highlight the query terms in
     *      the cached page
     * @param string $terms the list of query terms
     * @param int $crawl_time the timestamp of the crawl to look up the cached
     *      page in
     */
   function cacheRequestAndOutput($url, $highlight=true, $terms ="", 
        $crawl_time = 0)
    {
        global $CACHE;

        $hash_key = crawlHash(
            $terms.$url.serialize($highlight).serialize($crawl_time));
        if(USE_CACHE) {
            if($newDoc = $CACHE->get($hash_key)) {
                echo $newDoc;
                return;
            }
        }
        $queue_servers = $this->machineModel->getQueueServerUrls();
        if($crawl_time == 0) {
            $crawl_time = $this->crawlModel->getCurrentIndexDatabaseName();
        }
        $this->phraseModel->index_name = $crawl_time;
        $this->crawlModel->index_name = $crawl_time;

        $data = array();
        if(!$crawl_item = $this->crawlModel->getCrawlItem($url, 
            $queue_servers)) {
            $this->displayView("nocache", $data);
            return;
        }
        $summary_string = wordwrap($crawl_item[self::TITLE], 80, "\n")."\n\n" .
            wordwrap($crawl_item[self::DESCRIPTION], 80, "\n")."\n\n".
            wordwrap(print_r($crawl_item[self::LINKS], true), 80, "\n");
        $robot_instance = $crawl_item[self::ROBOT_INSTANCE];
        $robot_table_name = CRAWL_DIR."/robot_table.txt";
        $robot_table = array();
        if(file_exists($robot_table_name)) {
            $robot_table = unserialize(file_get_contents($robot_table_name));
        }
        if(!isset($robot_table[$robot_instance])) {
            $data["SUMMARY_STRING"] = $summary_string;
            $this->displayView("nocache", $data);
            return;
        }

        $instance_parts = explode("-", $robot_instance);
        $instance_num = false;
        if(count($instance_parts) > 1) {
            $instance_num = intval($instance_parts[0]);
        }
        $machine = $robot_table[$robot_instance][0];
        $machine_uri = $robot_table[$robot_instance][1];
        $page = $crawl_item[self::HASH];
        $offset = $crawl_item[self::OFFSET];
        $cache_partition = $crawl_item[self::CACHE_PAGE_PARTITION];
        $cache_item = $this->crawlModel->getCacheFile($machine, 
            $machine_uri, $cache_partition, $offset,  $crawl_time, 
            $instance_num);
        if(!isset($cache_item[self::PAGE])) {
            $data["SUMMARY_STRING"] = $summary_string;
            $this->displayView("nocache", $data);
            return;
        }
        $cache_file = $cache_item[self::PAGE];
        if(!stristr($cache_item[self::TYPE], "image")) {

            $meta_words = array('link\:', 'site\:', 'version\:', 'modified\:',
                'filetype\:', 'info\:', '\-', 'os\:', 'server\:', 'date\:',
                'lang\:', 'elink\:',
                'index:', 'ip:', 'i:', 'weight:', 'w:', 'u:');
            foreach($meta_words as $meta_word) {
                $pattern = "/(\s)($meta_word(\S)+)/";
                $terms = preg_replace($pattern, "", $terms);
            }
            $terms = str_replace("'", " ", $terms);
            $terms = str_replace('"', " ", $terms);
            $terms = str_replace('\\', " ", $terms);
            $terms = str_replace('|', " ", $terms);
            $terms = $this->clean($terms, "string");

            $phrase_string = mb_ereg_replace("[[:punct:]]", " ", $terms); 
            $words = mb_split(" ",$phrase_string);
            if(!$highlight) {
                $words = array();
            }
        } else {
            $type = $cache_item[self::TYPE];
            $cache_file = "<html><head><title>Yioop! Cache</title></head>".
                "<body><object data='data:$type;base64,".
                base64_encode($cache_file)."' type='$type' /></body></html>";
            $words = array();
        }
        $date = date ("F d Y H:i:s", $cache_item[self::TIMESTAMP]);

        $dom = new DOMDocument();

        $did_dom = @$dom->loadHTML('<?xml encoding="UTF-8">' . $cache_file);
        foreach ($dom->childNodes as $item)
        if ($item->nodeType == XML_PI_NODE)
            $dom->removeChild($item); // remove hack
        $dom->encoding = "UTF-8"; // insert proper

        $xpath = new DOMXPath($dom);


        $body =  $dom->getElementsByTagName('body')->item(0);
        if($body == false) {
            $body_tags = "<frameset><frame><noscript><img><span><b><i><em>".
                "<strong><h1><h2><h3><h4><h5><h6><p><div>".
                "<a><table><tr><td><th><dt><dir><dl><dd>";
            $cache_file = strip_tags($cache_file, $body_tags);
            $cache_file = "<html><head><title>Yioop! Cache</title></head>".
                "<body>".$cache_file."</body></html>";
            $dom = new DOMDocument();
            @$dom->loadHTML($cache_file);
            $body =  $dom->getElementsByTagName('body')->item(0);
        }
        $first_child = $body->firstChild;
        $summaryNode = $dom->createElement('pre');
        $summaryNode = $body->insertBefore($summaryNode, $first_child);
        $summaryNode->setAttributeNS("","style", "border-color: black; ".
            "border-style:solid; border-width:3px; ".
            "padding: 5px; background-color: white; display:none;");
        $summaryNode->setAttributeNS("","id", "summary-page-id");

        $textNode = $dom->createTextNode($summary_string);
        $summaryNode->appendChild($textNode);

        $scriptNode = $dom->createElement('script');
        $scriptNode = $body->insertBefore($scriptNode, $summaryNode);
        $textNode = $dom->createTextNode("var summaryShow = 'none';");
        $scriptNode->appendChild($textNode);

        $preNode = $dom->createElement('pre');
        $preNode = $body->insertBefore($preNode, $summaryNode);
        $preNode->setAttributeNS("","style", "border-color: black; ".
            "border-style:solid; border-width:3px; ".
            "padding: 5px; background-color: white");
        $divNode = $dom->createElement('div');
        $divNode = $body->insertBefore($divNode, $preNode);
        $divNode->setAttributeNS("","style", "border-color: black; ".
            "border-style:solid; border-width:3px; ".
            "padding: 5px; background-color: white");

        $textNode = $dom->createTextNode(tl('search_controller_cached_version', 
            "$url", $date));
        $divNode->appendChild($textNode);

        if(isset($cache_item[self::HEADER])) {
            $textNode = $dom->createTextNode($cache_item[self::HEADER]."\n");
        } else {
            $textNode = $dom->createTextNode("");
        }

        $preNode->appendChild($textNode);

        $aNode = $dom->createElement("a");
        $aTextNode = $dom->createTextNode(
            tl('search_controller_summary_data'));
        $aNode->setAttributeNS("","onclick", "javascript:".
            "summaryShow=(summaryShow!='block')?'block':'none';".
            "elt=document.getElementById('summary-page-id');".
            "elt.style.display=summaryShow;");
        $aNode->setAttributeNS("","style", "text-decoration: underline; ".
            "cursor: pointer");

        $aNode->appendChild($aTextNode);

        $aNode = $preNode->appendChild($aNode);

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

        if(USE_CACHE) {
            $CACHE->set($hash_key, $newDoc);
        }

        echo $newDoc;
        return;
    }
}
?>
