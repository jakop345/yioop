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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
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
/** For guessing locale and formatting date based on locale guessed*/
require_once BASE_DIR."/lib/locale_functions.php";
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
    var $models = array("phrase", "crawl", "searchfilters", "machine",
        "source", "cron");
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
     * Name of the sub-search currently in use
     * @var string
     */
    var $subsearch_name = "";

    /**
     * The localization identifier for the current subsearch
     * @var string
     */
    var $subsearch_identifier = "";

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
        $start_time = microtime();

        list($subsearches, $no_query) = $this->initializeSubsearches();

        $format_info = $this->initializeResponseFormat();
        if(!$format_info) { return;}
        list($view, $web_flag, $raw, $results_per_page, $limit) = $format_info;

        list($query, $activity, $arg) =
            $this->initializeUserAndDefaultActivity($data);

        if($activity == "query" && $this->mirrorHandle()) {return; }

        list($index_timestamp, $index_info, $save_timestamp) =
            $this->initializeIndexInfo($web_flag, $raw, $data);

        if(isset($_REQUEST['q']) && strlen($_REQUEST['q']) > 0
            || $activity != "query") {
            if($activity != "cache") {
                $this->processQuery($data, $query, $activity, $arg,
                    $results_per_page, $limit, $index_timestamp, $raw,
                    $save_timestamp);
                    // calculate the results of a search if there is one
            } else {
                $ui_array = array("highlight", "yioop_nav", "history",
                    "summaries", "version");
                if(isset($_REQUEST['from_cache'])) {
                    $ui_array[] = "cache_link_referrer";
                }
                if(isset($_REQUEST['hist_open'])) {
                    $ui_array[] = "hist_ui_open";
                }
                $this->cacheRequestAndOutput($arg, $ui_array, $query,
                    $index_timestamp);
                return;
            }
        }

        $data['ELAPSED_TIME'] = changeInMicrotime($start_time);
        if ($view == "serial") {
            if(isset($data["PAGES"])) {
                $count = count($data["PAGES"]);
                for($i = 0; $i < $count; $i++) {
                    unset($data["PAGES"][$i]["OUT_SCORE"]);
                    $data["PAGES"][$i][self::SCORE]= "".
                        round($data["PAGES"][$i][self::SCORE], 3);
                    $data["PAGES"][$i][self::DOC_RANK]= "".
                        round($data["PAGES"][$i][self::DOC_RANK], 3);
                    $data["PAGES"][$i][self::RELEVANCE]= "".
                        round($data["PAGES"][$i][self::RELEVANCE], 3);
                }
            }
            echo serialize($data);
            exit();
        }

        if($web_flag) {
            $this->addSearchViewData($index_info, $no_query, $raw, $view,
                $subsearches, $data);
        }

        $this->displayView($view, $data);

        if(defined("NEWS_MODE") && NEWS_MODE != 'news_off' &&
            count($data['PAGES']) > 0) {
            ignore_user_abort();
            $this->newsUpdate($data);
        }
    }

    /**
     *  Determines how this query is being run and return variables for the view
     *
     *  A query might be run as a web-based where HTML is expected as the
     *  output, an RSS query, an API query, or as a serial query from a
     *  name_server or mirror instance back to one of the other queue servers
     *  in a Yioop installation. A query might also request different numbers
     *  of pages back beginning at different starting points in the result.
     *
     *  @return array consisting of (view to be used to render results,
     *      flag for whether html results should be used, int code for what
     *      kind of group of similar urls should be done on the results,
     *      number of search results to return, start from which result)
     */
    function initializeResponseFormat()
    {
        $view = "search";
        $web_flag = true;
        if(isset($_REQUEST['f']) && $_REQUEST['f']=='rss' &&
            RSS_ACCESS) {
            $view = "rss";
            $web_flag = false;
        } else if(isset($_REQUEST['f']) && $_REQUEST['f']=='serial' &&
            RSS_ACCESS) {
            $view = "serial";
            $web_flag = false;
        } else if (!WEB_ACCESS) {
            return false;
        }
        if(isset($_REQUEST['num'])) {
            $results_per_page = $this->clean($_REQUEST['num'], "int");
        } else if(isset($_SESSION['MAX_PAGES_TO_SHOW']) ) {
            $results_per_page = $_SESSION['MAX_PAGES_TO_SHOW'];
        } else {
            $results_per_page = NUM_RESULTS_PER_PAGE;
        }
        if(isset($_REQUEST['raw'])){
            $raw = max($this->clean($_REQUEST['raw'], "int"), 0);
        } else {
            $raw = 0;
        }
        if(isset($_REQUEST['limit'])) {
            $limit = $this->clean($_REQUEST['limit'], "int");
        } else {
            $limit = 0;
        }
        return array($view, $web_flag, $raw, $results_per_page, $limit);
    }

    /**
     *  Determines if query results are using a subsearch, and if so
     *  initializes them, also it sets up list of subsearches to draw
     *  at top of screen.
     *
     *  @return array (subsearches, no_query) where subsearches is itself
     *      an array of data about each subsearch to draw, and no_query
     *      is a bool flag used in the case of a news subsearch when no query
     *      was entered by the user but still want to display news
     */
    function initializeSubsearches()
    {
        $subsearches = $this->sourceModel->getSubsearches();
        $no_query = false;
        if(isset($_REQUEST["s"])) {
            $search_found = false;
            foreach($subsearches as $search) {
                if($search["FOLDER_NAME"] == $_REQUEST["s"]) {
                    $search_found = true;
                    $this->subsearch_name = $_REQUEST["s"];
                    $this->subsearch_identifier = $search["INDEX_IDENTIFIER"];
                    if(!isset($_REQUEST['num']) && isset($search["PER_PAGE"])) {
                        $_REQUEST['num']= $search["PER_PAGE"];
                    }
                    break;
                }
            }
            if(!$search_found) {
                $pathinfo = pathinfo($_SERVER['SCRIPT_FILENAME']);
                include($pathinfo["dirname"]."/error.php");
                exit();
            }
            if($this->subsearch_name == "news" &&
                (!isset($_REQUEST['q']) || $_REQUEST['q']=="")) {
                $lang = getLocaleTag();
                $lang_parts = explode("-", $lang);
                if(isset($lang_parts[0])){
                    $lang = $lang_parts[0];
                }
                $_REQUEST['q'] = "lang:".$lang;
                $no_query = true;
            }
        }
        return array($subsearches, $no_query);
    }

    /**
     *  Determines the kind of user session that this search request is for
     *
     *  This function is called by @see processRequest(). The user session
     *  might be one without a login, one with a login so need to validate
     *  against to prevent CSRF attacks, just after someone logged out, or
     *  a bot session (googlebot, etc) so remove the query request
     *
     *  @param array &$data that will eventually be sent to the view. We might
     *      update with error messages
     *  @return array consisting of (query based on user info, whether
     *      if a cache request highlighting should be userd, what activity
     *      user wants, any arguments to this activity)
     *
     */
    function initializeUserAndDefaultActivity(&$data)
    {
        $arg = false;
        if(!isset($_REQUEST['a']) || !in_array($_REQUEST['a'],
            $this->activities)) {
            $activity = "query";
        } else {
            $activity = $_REQUEST['a'];
            if($activity == "signout") {
                unset($_SESSION['USER_ID']);
                unset($_REQUEST);
                $user = $_SERVER['REMOTE_ADDR'];
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('search_controller_logout_successful')."</h1>')";
            }
            if(isset($_REQUEST['arg'])) {
                $arg = $_REQUEST['arg'];
            } else {
                $activity = "query";
            }
        }
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
            $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user);
            if($token_okay === false) {
                $user = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
            $token_okay = true;
        }
        if($token_okay && isset($_SESSION["USER_ID"])) {
            $data["ADMIN"] = true;
        } else {
            $data["ADMIN"] = false;
        }
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user);

        if(isset($_REQUEST['q'])) {
            $_REQUEST['q'] = $this->restrictQueryByUserAgent($_REQUEST['q']);
        }

        if($activity == "query") {
            list($query, $activity, $arg) = $this->extractActivityQuery();
        } else {
            $query = isset($_REQUEST['q']) ? $_REQUEST['q'] : "";
            $query = $this->clean($query, "string");
        }

        if(isset($_SESSION['OPEN_IN_TABS'])) {
            $data['OPEN_IN_TABS'] = $_SESSION['OPEN_IN_TABS'];
        } else {
            $data['OPEN_IN_TABS'] = false;
        }

        return array($query, $activity, $arg);
    }

    /**
     *  Determines which crawl or mix timestamp should be in use for this
     *  query. It also determines info and returns associated with this
     *  timestamp.
     *
     *  @param bool $web_flag whether this is a web based query or one from
     *      the search API
     *  @param int  and so should validate against list of known crawls or an
     *      internal (say network) query that doesn't require validation
     *      (faster without).
     *  @param array &$data that will eventually be sent to the view. We set
     *      the 'its' (index_time_stamp) field here
     *  @return array consisting of index timestamp of crawl or mix in use,
     *      $index_info an array of info about that index, and $save_timestamp
     *      timestamp of last savepoint, used if this query is being is the
     *      query for a crawl mix archive crawl.
     */
    function initializeIndexInfo($web_flag, $raw, &$data)
    {
        if(isset($_REQUEST['machine'])) {
            $current_machine = $this->clean($_REQUEST['machine'], 'int');
        } else {
            $current_machine = 0;
        }
        $this->phraseModel->current_machine = $current_machine;
        $this->crawlModel->current_machine = $current_machine;

        $machine_urls = $this->machineModel->getQueueServerUrls();

        $current_its = $this->crawlModel->getCurrentIndexDatabaseName();
        $index_timestamp = $this->getIndexTimestamp();
        $is_mix = false;
        if($index_timestamp != $current_its) {
            if($raw != 1) {
                if($index_timestamp != 0 ) {
                    //validate timestamp against list
                    //(some crawlers replay deleted crawls)
                    $crawls = $this->crawlModel->getCrawlList(false, true,
                        $machine_urls, true);
                    if($this->crawlModel->isCrawlMix($index_timestamp)) {
                        $is_mix = true;
                    }
                    $found_crawl = false;
                    foreach($crawls as $crawl) {
                        if($index_timestamp == $crawl['CRAWL_TIME']) {
                            $found_crawl = true;
                            break;
                        }
                    }
                    if(!$is_mix && ( !$found_crawl && (isset($_REQUEST['q']) ||
                        isset($_REQUEST['arg'])))) {
                        unset($_SESSION['its']);
                        include(BASE_DIR."/error.php");
                        exit();
                    } else if(!$found_crawl && !$is_mix) {
                        unset($_SESSION['its']);
                        $index_timestamp = $current_its;
                    }
                }
            }
        }
        $index_info = NULL;
        if($web_flag && $index_timestamp != 0) {
            $index_info =  $this->crawlModel->getInfoTimestamp(
                $index_timestamp, $machine_urls);
            if($index_info == array() || ((!isset($index_info["COUNT"]) ||
                $index_info["COUNT"] == 0) && !$is_mix)) {
                if($index_timestamp != $current_its) {
                    $index_timestamp = $current_its;
                    $index_info =  $this->crawlModel->getInfoTimestamp(
                        $index_timestamp, $machine_urls);
                    if($index_info == array()) { $index_info = NULL; }
                }
            }
        }

        if(isset($_REQUEST['save_timestamp'])){
            $save_timestamp = $this->clean($_REQUEST['save_timestamp'], 'int');
        } else {
            $save_timestamp = 0;
        }
        $data['its'] = (isset($index_timestamp)) ? $index_timestamp : 0;

        return array($index_timestamp, $index_info, $save_timestamp);
    }

    /**
     * Finds the timestamp of the main crawl or mix to return results from
     * Does not do checking to make sure timestamp exists.
     *
     * @return string current timestamp
     */
    function getIndexTimestamp()
    {
        static $index_timestamp = -1;
        if($index_timestamp != -1) {
            return $index_timestamp;
        }
        if((isset($_REQUEST['its']) || isset($_SESSION['its']))) {
            $its = (isset($_REQUEST['its'])) ? $_REQUEST['its'] :
                $_SESSION['its'];
            $index_timestamp = $this->clean($its, "int");
        } else {
            $index_timestamp = $this->crawlModel->getCurrentIndexDatabaseName();
        }
        return $index_timestamp;
    }

    /**
     * Sometimes robots disobey the statistics page nofollow meta tag.
     * and need to be stopped before they query the whole index
     *
     * @param string $query  the search request string
     * @return string the search request string if not a bot; "" otherwise
     */
    function restrictQueryByUserAgent($query)
    {
        $bots = array("googlebot", "baidu", "naver", "sogou");
        $query_okay = true;
        foreach($bots as $bot) {
            if(!isset($_SERVER["HTTP_USER_AGENT"]) ||
                stristr($_SERVER["HTTP_USER_AGENT"], $bot)) {
                $query_okay = false;
            }
        }
        return ($query_okay) ? $query : "";
    }

    /**
     *  Prepares the array $data so the SearchView can draw search results
     *
     *  @param array $index_info an array of info about that index in use
     *  @param bool $no_query true in the case of a news subsearch when no query
     *      was entered by the user but still want to display news
     *  @param int $raw $raw what kind of grouping of identical results should
     *      be done (0 is default, 1 and higher used for internal queries)
     *  @param string $view name of view class search results are for
     *  @param array $subsearches an array of data about each subsearch to draw
     *      to the view
     *  @param array &$data that will eventually be sent to the view for
     *      rendering. This method adds fields to the array
     */
    function addSearchViewData($index_info, $no_query, $raw, $view,
        $subsearches, &$data)
    {
        if($index_info !== NULL) {
            if(isset($index_info['IS_MIX'])) {
                $data['INDEX_INFO'] = tl('search_controller_mix_info',
                    $index_info['DESCRIPTION']);
            } else {
                if(isset($index_info['DESCRIPTION']) &&
                    isset($index_info['VISITED_URLS_COUNT']) &&
                    isset($index_info['COUNT']) ) {
                    $data['INDEX_INFO'] = tl('search_controller_crawl_info',
                        $index_info['DESCRIPTION'],
                        $index_info['VISITED_URLS_COUNT'],
                        $index_info['COUNT']);
                } else {
                    $data['INDEX_INFO'] = "";
                }
            }
        } else {
            $data['INDEX_INFO'] = "";
        }
        $stats_file = CRAWL_DIR."/cache/".self::statistics_base_name.
                $data['its'].".txt";
        $data["SUBSEARCHES"] = $subsearches;
        if($this->subsearch_name != "" && $this->subsearch_identifier != "") {
            $data["SUBSEARCH"] = $this->subsearch_name;
        }
        $data["HAS_STATISTICS"] = file_exists($stats_file);

        if(!isset($data["RAW"])) {
            $data["RAW"] = $raw;
        }
        if($view == "search" && $data["RAW"] == 0 && isset($data['PAGES'])) {
            $data['PAGES'] = $this->makeMediaGroups($data['PAGES']);
        }
        $data['INCLUDE_SCRIPTS'] = array("suggest");
        if(!isset($data['SCRIPT'])) {
            $data['SCRIPT'] = "";
        }
        $data['SCRIPT'] .= "\nlocal_strings = {'spell':'".
            tl('search_controller_search')."'};";
        $data['SCRIPT'] .= "\ncsrf_name ='".CSRF_TOKEN."';";
        $data['INCLUDE_LOCALE_SCRIPT'] = "locale";

        if($no_query || isset($_REQUEST['no_query'])) {
            $data['NO_QUERY'] = true;
            if(!isset($data['PAGING_QUERY'])) {
                $data['PAGING_QUERY'] = "";
            }
            $data['PAGING_QUERY'] .= "&amp;no_query=true";
        }

    }

    /**
     * Used to check if there are any mirrors of the current server.
     * If so, it tries to distribute the query requests randomly amongst
     * the mirrors
     * @return bool whether or not a mirror of the current site handled it
     */
    function mirrorHandle()
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
     * @param array &$data an array of view data that will be updated to include
     *      at most results_per_page many search results
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
     * @param int $raw ($raw == 0) normal grouping, $raw > 0
     *      no grouping done on data. If $raw == 1 no summary returned (used
     *      with f=serial, end user probably does not want)
     *      In this case, will get offset, generation, etc so could later lookup
     * @param mixed $save_timestamp if this timestamp is nonzero, then save
     *      iterate position, so can resume on future queries that make
     *      use of the timestamp. $save_time_stamp may also be in the format
     *      of string timestamp-query_part to handle networked queries involving
     *      presentations
     */
    function processQuery(&$data, $query, $activity, $arg, $results_per_page,
        $limit = 0, $index_name = 0, $raw = 0, $save_timestamp = 0)
    {
        $no_index_given = false;
        if($index_name == 0) {
            $index_name = $this->crawlModel->getCurrentIndexDatabaseName();
            if(!$index_name) {
                $pattern = "/(\s)((i:|index:)(\S)+)/";
                $indexes = preg_grep($pattern, array($query));
                if(isset($indexes[0])) {
                    $index_name = $indexes[0];
                } else {
                    $no_index_given = true;
                }
            }
        }
        $is_mix = $this->crawlModel->isCrawlMix($index_name);
        if($no_index_given) {
            $data["ERROR"] = tl('search_controller_no_index_set');
            $data['SCRIPT'] =
                    "doMessage('<h1 class=\"red\" >".
                    tl('search_controller_no_index_set').
                    "</h1>');";
            $data['RAW'] = $raw;
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
        list($query, $raw, $use_network, $use_cache_if_possible,
            $guess_semantics) =
                $this->calculateControlWords($query, $raw, $is_mix,
                $index_name);
        $index_archive_name= self::index_data_base_name.$index_name;
        if(file_exists( CRAWL_DIR."/cache/$index_archive_name/no_network.txt")){
            $_REQUEST['network'] = false;
            //if default index says no network queries then no network queries
        }
        if($use_network &&
            (!isset($_REQUEST['network']) || $_REQUEST['network'] == "true")) {
            $queue_servers = $this->machineModel->getQueueServerUrls();
        } else {
            $queue_servers = array();
        }
        if(isset($_REQUEST['guess']) &&  $_REQUEST['guess'] == "false") {
            $guess_semantics = false;
        }

        switch($activity)
        {
            case "related":
                $data['QUERY'] = "related:$arg";
                $url = $arg;
                $crawl_item = $this->crawlModel->getCrawlItem($url,
                    $queue_servers);
                $top_phrases  =
                    $this->getTopPhrases($crawl_item, 3, $index_name);
                $top_query = implode(" ", $top_phrases);
                $filter = $this->searchfiltersModel->getFilter();
                $this->phraseModel->editedPageSummaries =
                    $this->searchfiltersModel->getEditedPageSummaries();
                $phrase_results = $this->phraseModel->getPhrasePageResults(
                    $top_query, $limit, $results_per_page, false, $filter,
                    $use_cache_if_possible, $raw, $queue_servers,
                    $guess_semantics, $save_timestamp);
                $data['PAGING_QUERY'] = "?c=search&amp;".
                    "a=related&amp;arg=".urlencode($url);
                if(isset($this->subsearch_name) && $this->subsearch_name !="") {
                    $data['PAGING_QUERY'] .= "&amp;s=".
                        $this->subsearch_name;
                }

                $data['QUERY'] = urlencode($this->clean($data['QUERY'],
                    "string"));
            break;

            case "query":
            default:
                if(trim($query) != "") {
                    $filter = $this->searchfiltersModel->getFilter();
                    $this->phraseModel->editedPageSummaries =
                        $this->searchfiltersModel->getEditedPageSummaries();
                    $phrase_results = $this->phraseModel->getPhrasePageResults(
                        $query, $limit, $results_per_page, true, $filter,
                        $use_cache_if_possible, $raw, $queue_servers,
                        $guess_semantics, $save_timestamp);
                    $query = $original_query;
                }
                $data['PAGING_QUERY'] = "?q=".urlencode($query);
                if(isset($this->subsearch_name) && $this->subsearch_name !="") {
                    $data['PAGING_QUERY'] .= "&amp;s=".
                        $this->subsearch_name;
                }
                $data['QUERY'] = urlencode($this->clean($query,"string"));

            break;
        }

        $data['VIDEO_SOURCES'] = $this->sourceModel->getMediaSources("video");
        $data['RAW'] = $raw;
        $data['PAGES'] = (isset($phrase_results['PAGES'])) ?
             $phrase_results['PAGES']: array();
        $data['SAVE_POINT'] = (isset($phrase_results["SAVE_POINT"])) ?
             $phrase_results["SAVE_POINT"]: array( 0 => 1);
        $data['TOTAL_ROWS'] = (isset($phrase_results['TOTAL_ROWS'])) ?
            $phrase_results['TOTAL_ROWS'] : 0;
        $data['LIMIT'] = $limit;
        $data['RESULTS_PER_PAGE'] = $results_per_page;
    }

    /**
     *  Extracts from the query string any control words:
     *  mix:, m:, raw:, no: and returns an array consisting
     *  of the query with these words removed, and then variables
     *  for their values.
     *
     *  @param string $query original query string
     *  @param bool $raw the $_REQUEST['raw'] value
     *  @param bool if the current index name is that of a crawl mix
     *  @param string $index_name timestamp of current mix or index
     *
     *  @return array ($query, $raw, $use_network,
     *      $use_cache_if_possible, $guess_semantics)
     */
    function calculateControlWords($query, $raw, $is_mix, $index_name)
    {
        $original_query = $query;
        if(trim($query) != "") {
            if($this->subsearch_identifier != "") {
                $replace = " {$this->subsearch_identifier}";
                $query = preg_replace('/\|/', "$replace |", $query);
                $query .= " $replace";
            }
        }
        $query = " $query";
        $mix_metas = array("m:", "mix:");
        foreach($mix_metas as $mix_meta) {
            $pattern = "/(\s)($mix_meta(\S)+)/";
            preg_match_all($pattern, $query, $matches);
            if(isset($matches[2][0]) && !isset($mix_name)) {
                $mix_name = substr($matches[2][0],
                    strlen($mix_meta));
                $mix_name = str_replace("+", " ", $mix_name);
                break; // only one mix and can't be nested
            }
        }
        $query = preg_replace($pattern, "", $query);
        if(isset($mix_name)) {
            if(is_numeric($mix_name)) {
                $is_mix = true;
                $index_name = $mix_name;
            } else {
                $tmp = $this->crawlModel->getCrawlMixTimestamp(
                    $mix_name);
                if($tmp != false) {
                    $index_name = $tmp;
                    $is_mix = true;
                }
            }
        }
        if($is_mix) {
            $mix = $this->crawlModel->getCrawlMix($index_name);
            $query =
                $this->phraseModel->rewriteMixQuery($query, $mix);
        }

        $pattern = "/(\s)(raw:(\S)+)/";
        preg_match_all($pattern, $query, $matches);
        if(isset($matches[2][0])) {
            $raw = substr($matches[2][0], 4);
            $raw = ($raw > 0) ? 2 : 0;
        }
        $query = preg_replace($pattern, "", $query);
        $original_query = $query;
        $query = preg_replace('/no:cache/', "", $query);
        $use_cache_if_possible = ($original_query == $query) ? true : false;
        $network_work_query = $query;
        $query = preg_replace('/no:network/', "", $query);
        $use_network = ($network_work_query == $query) ? true : false;
        $guess_query = $query;
        $query = preg_replace('/no:guess/', "", $query);
        $guess_semantics = ($guess_query == $query) ? true : false;

        return array($query, $raw, $use_network,
            $use_cache_if_possible, $guess_semantics);
    }

    /**
     *  If news_update time has passed, then updates news feeds associated with
     *  this Yioop instance
     *
     *  @param array $data used by view to render itself. In this case, if there
     *      is a problem updating the news then we will flash a message
     *  @param bool $no_news_process if true than assume news_updater.php is
     *      not running. If false, assume being run from news_updater.php so
     *      update news_process cron time.
     */
    function newsUpdate(&$data, $no_news_process = true)
    {
        if(!defined(SUBSEARCH_LINK)|| !SUBSEARCH_LINK) {
            $data["LOG_MESSAGES"] =
                "No news update as SUBSEARCH_LINK define false.";
            return;
        }
        $time = time();
        $rss_feeds = $this->sourceModel->getMediaSources("rss");
        if(!$rss_feeds || count($rss_feeds) == 0) {
            $data["LOG_MESSAGES"] =
                "No news update as no news feeds.";
            return;
        }
        $news_process = false;
        if($no_news_process) {
            $cron_time = $this->cronModel->getCronTime("news_process");
            $delta = $time - $cron_time;
            if($delta < SourceModel::ONE_HOUR && $delta != 0) {
                $data["LOG_MESSAGES"] = "News process running.";
                return;
            }
        } else {
            $this->cronModel->updateCronTime("news_process", true);
            $news_process = true;
        }

        $data["LOG_MESSAGES"] = "";
        $cron_time = $this->cronModel->getCronTime("news_delete");
        $delta = $time - $cron_time;
        if($delta == 0) {
            $this->cronModel->updateCronTime("news_delete", true);
        }
        $start_delete_time = $this->cronModel->getCronTime("news_start_delete");
        $start_delta = $time - $start_delete_time;
        if($start_delta == 0) {
            $this->cronModel->updateCronTime("news_start_delete", true);
        }
        // news lock is two minutes > max_execution_time = 30 seconds web script
        $lock_time = $this->cronModel->getCronTime("news_lock");
        $lock_delta = $time - $lock_time;
        if($lock_delta == 0) {$lock_delta = 2 * SourceModel::TWO_MINUTES; }

        /*  every 3 hours everything older than a week and rebuild index
            do this every four hours so news articles tend to stay in order
         */
        if($delta > 3 * SourceModel::ONE_HOUR &&
          $start_delta > SourceModel::ONE_HOUR/12 &&
          $lock_delta > SourceModel::TWO_MINUTES) {
            $this->cronModel->updateCronTime("news_lock");
            $this->cronModel->updateCronTime("news_start_delete", true);
            $data["LOG_MESSAGES"] .=
                "Deleting feed items and rebuild shard...\n";
            if($this->sourceModel->deleteFeedItems(SourceModel::ONE_WEEK,
                $news_process)) {
                $this->cronModel->updateCronTime("news_delete", true);
                $data["LOG_MESSAGES"] .= "... shard rebuilt\n";
            }
            $this->cronModel->saveCronTable();
            return;
        }
        $update_cron_time = $this->cronModel->getCronTime("news_update");
        $try_cron_time = $this->cronModel->getCronTime("news_try_again");

        $delta = $time - max($update_cron_time, $try_cron_time);
        // each 15 minutes try to re-get feeds that have no items
        if((($delta > SourceModel::ONE_HOUR/4 &&
            $delta < SourceModel::ONE_HOUR) || $delta == 0) &&
            $lock_delta > SourceModel::TWO_MINUTES) {
            $this->cronModel->updateCronTime("news_lock");
            $this->cronModel->updateCronTime("news_try_again", true);
            $this->sourceModel->updateFeedItems(SourceModel::ONE_WEEK,
                true, $news_process);
            $data["LOG_MESSAGES"] .= "Re-trying feeds with no items\n";
            $this->cronModel->saveCronTable();
            return;
        }

        $delta = $time - $update_cron_time;
        // every hour get items from twenty feeds whose newest items are oldest
        if(($delta > SourceModel::ONE_HOUR || $delta == 0)
            && $lock_delta > SourceModel::TWO_MINUTES) {
            $this->cronModel->updateCronTime("news_lock");
            $this->cronModel->updateCronTime("news_update", true);
            $data["LOG_MESSAGES"] .= "Performing news feeds update\n";
            if(!$this->sourceModel->updateFeedItems(
                SourceModel::ONE_WEEK, false, $news_process)) {
                $data["LOG_MESSAGES"] .= "News feeds update failed.\n";
            }
        }
        $this->cronModel->saveCronTable();
        if($data["LOG_MESSAGES"] == "") {
            $data["LOG_MESSAGES"] = "No updates needed.";
        }
    }

    /**
     * Groups search result pages together which have thumbnails
     * from an array of search pages. Grouped thumbnail pages stored at array
     * index of first thumbnail found, non thumbnail pages stored where were
     * before
     *
     * @param $pages an array of search result pages to group those pages
     *      with thumbs within
     * @return array $pages after the grouping has been done
     */
    function makeMediaGroups($pages)
    {
        $first_image = -1;
        $first_feed_item = -1;
        $out_pages = array();
        foreach($pages as $page) {
            if(isset($page[self::THUMB]) && $page[self::THUMB] != 'NULL') {
                if($first_image == -1) {
                    $first_image = count($out_pages);
                    $out_pages[$first_image]['IMAGES'] = array();
                }
                $out_pages[$first_image]['IMAGES'][] = $page;
            } else if(isset($page[self::IS_FEED]) && $page[self::IS_FEED]) {
                if($first_feed_item == -1) {
                    $first_feed_item = count($out_pages);
                    $out_pages[$first_feed_item]['FEEDS'] = array();
                }
                $out_pages[$first_feed_item]['FEEDS'][] = $page;
            } else {
                $out_pages[] = $page;
            }
        }
        return $out_pages;
    }


    /**
     * Given a page summary extract the words from it and try to find documents
     * which match the most relevant words. The algorithm for "relevant" is
     * pretty weak. For now we pick the $num many words whose ratio
     * of number of occurences in crawl item/ number of occurences in all
     * documents is the largest
     *
     * @param string $crawl_item a page summary
     * @param int $num number of key phrase to return
     * @param int $index_name the timestamp of an index to use, if 0 then
     *      default used
     * @return array  an array of most selective key phrases
     */
    function getTopPhrases($crawl_item, $num, $crawl_time = 0)
    {
        $queue_servers = $this->machineModel->getQueueServerUrls();
        if($crawl_time == 0) {
            $crawl_time = $this->crawlModel->getCurrentIndexDatabaseName();
        }
        $this->phraseModel->index_name = $crawl_time;
        $this->crawlModel->index_name = $crawl_time;

        $phrase_string =
            PhraseParser::extractWordStringPageSummary($crawl_item);

        $crawl_item[self::LANG] = (isset($crawl_item[self::LANG])) ?
            $crawl_item[self::LANG] : DEFAULT_LOCALE;

        $page_word_counts =
            PhraseParser::extractPhrasesAndCount($phrase_string,
                $crawl_item[self::LANG]);
        $words = array_keys($page_word_counts);

        $word_counts = $this->crawlModel->countWords($words, $queue_servers);

        $word_ratios = array();
        foreach($page_word_counts as $word => $count) {
            $word_ratios[$word] =
                (isset($word_counts[$word]) && $word_counts[$word] > 0) ?
                $count/$word_counts[$word] : 0;
            /*discard cases where word only occurs in one doc as want
              to find related relevant documents */
            if($word_ratios[$word] == 1) $word_ratios[$word] = 0;
        }

        uasort($word_ratios, "greaterThan");

        $top_phrases = array_keys($word_ratios);
        $top_phrases = array_slice($top_phrases, 0, $num);

        return $top_phrases;

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

        $query = (isset($_REQUEST['q'])) ? $_REQUEST['q'] : "";
        $query = mb_ereg_replace("(\s)+", " ", $query);
        $query = mb_ereg_replace("\s:", ":", $query);
        $query = mb_ereg_replace(":\s", ":", $query);

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

        return array($out_query, $activity, $arg);
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

        if(!isset($node->childNodes->length) || 
            get_class($node) != 'DOMElement') {
            return $node;
        }
        for($k = 0; $node->childNodes->length; $k++)  {
            if(!$node->childNodes->item($k)) { break; }

            $clone = $node->childNodes->item($k)->cloneNode(true);

            if($clone->nodeType == XML_TEXT_NODE) {
                $text = $clone->textContent;

                foreach($words as $word) {
                    //only mark string of length at least 2
                    if(mb_strlen($word) > 1) {
                        $mark_prefix = crawlHash($word);
                        if(stristr($mark_prefix, $word) !== false) {
                            $mark_prefix = preg_replace(
                            "/\b$word\b/i", '', $mark_prefix);
                        }
                        $text = preg_replace(
                            "/\b$word\b/i", $mark_prefix.'$0', $text);
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

    /**
     * Make relative links canonical with respect to provided $url
     * for links appear within the Dom node.
     *
     * @param object $node dom node to fix links for
     * @param string $url url to use to canonicalize links
     * @return object updated dom node
     */
    function canonicalizeLinks($node, $url)
    {
        if(!isset($node->childNodes->length) || 
            get_class($node) != 'DOMElement') {
            return $node;
        }
        for($k = 0; $k < $node->childNodes->length; $k++) {
            if(!$node->childNodes->item($k)) { break; }
            $clone = $node->childNodes->item($k)->cloneNode(true);
            $tag_name = (isset($clone->tagName) ) ? $clone->tagName : "-1";
            if(in_array($tag_name, array("a", "link"))) {
                if($clone->hasAttribute("href")) {
                    $href = $clone->getAttribute("href");
                    if($href !="" && $href[0] != "#") {
                        $href = UrlParser::canonicalLink($href, $url, false);
                    }

                    /*
                        Modify non-link tag urls so that they are looked up in
                        the cache before going to the live site
                     */
                    if($tag_name != "link" && ($href =="" || $href[0] != "#")) {
                        $href = urlencode($href);
                        $href = $href."&from_cache=true";
                        $crawl_time = $this->getIndexTimestamp();
                        $href = $this->baseLink()."&a=cache&q&arg".
                            "=$href&its=$crawl_time";
                    }

                    $clone->setAttribute("href", $href);
                    //an anchor might have an img tag within it so recurses
                    $clone = $this->canonicalizeLinks($clone, $url);
                    $node->replaceChild($clone, $node->childNodes->item($k));
                }
            } else if (in_array($tag_name, array("img", "object",
                "script"))) {
                if($clone->hasAttribute("src")) {
                    $src = $clone->getAttribute("src");
                    $src = UrlParser::canonicalLink($src, $url, false);
                    $clone->setAttribute("src", $src);
                    $node->replaceChild($clone, $node->childNodes->item($k));
                }
            } else {
                if($tag_name != -1) {
                    $clone = $this->canonicalizeLinks($clone, $url);
                    if(is_object($clone)) {
                        $node->replaceChild($clone,
                            $node->childNodes->item($k));
                    }
                }
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
     * @param int $grouping ($grouping == 0) normal grouping of links
     *      with associated document, ($grouping > 0)
     *      no grouping done on data
     * @param int $save_timestamp if this timestamp is nonzero, then save
     *      iterate position, so can resume on future queries that make
     *      use of the timestamp
     *
     * @return array associative array of results for the query performed
     */
    function queryRequest($query, $results_per_page, $limit = 0,
        $grouping = 0, $save_timestamp = 0)
    {
        if(!API_ACCESS) {return NULL; }
        $grouping = ($grouping > 0 ) ? 2 : 0;

        $data = array();
        $this->processQuery($data, $query, "query", "", $results_per_page,
                $limit, 0, $grouping, $save_timestamp);
        return $data;
    }

    /**
     * Query timestamps can be used to save an iteration position in a
     * a set of query results. This method allows one to delete
     * the supplied save point.
     *
     * @param int $save_timestamp deletes a previously query saved timestamp
     */
    function clearQuerySavepoint($save_timestamp)
    {
        $this->phraseModel->clearQuerySavePoint($save_timestamp);
    }

    /**
     * Part of Yioop! Search API. Performs a related to a given url
     * search query and returns associative array of query results
     *
     * @param string $url to find related documents for
     * @param int $results_per_page number of results to return
     * @param int $limit first result to return from the ordered query results
     * @param int $grouping ($grouping == 0) normal grouping of links
     *      with associated document, ($grouping > 0)
     *      no grouping done on data
     * @param int $save_timestamp if this timestamp is nonzero, then save
     *      iterate position, so can resume on future queries that make
     *      use of the timestamp
     *
     * @return array associative array of results for the query performed
     */
    function relatedRequest($url, $results_per_page, $limit = 0,
        $crawl_time = 0, $grouping = 0, $save_timestamp = 0)
    {
        if(!API_ACCESS) {return NULL; }
        $grouping = ($grouping > 0 ) ? 2 : 0;
        $data = array();
        $this->processQuery($data, "", "related", $url, $results_per_page,
            $limit, $crawl_time, $grouping, $save_timestamp);
        return $data;
    }

    /**
     * Part of Yioop! Search API. Performs a related to a given url
     * search query and returns associative array of query results
     *
     * @param string $url to get cached page for
     * @param array $ui_flags array of  ui features which
     *      should be added to the cache page. For example, "highlight"
     *      would way search terms should be highlighted, "history"
     *      says add history navigation for all copies of this cache page in
     *      yioop system.
     * @param string $terms space separated list of search terms
     * @param string $crawl_time timestamp of crawl to look for cached page in
     * @return string with contents of cached page
     */
    function cacheRequest($url, $ui_flags = array(), $terms ="",
        $crawl_time = 0)
    {
        if(!API_ACCESS) return false;
        ob_start();
        $this->cacheRequestAndOutput($url, $ui_flags, $terms,
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
     * @param array $ui_flags array of  ui features which
     *      should be added to the cache page. For example, "highlight"
     *      would say search terms should be highlighted, "history"
     *      says add history navigation for all copies of this cache page in
     *      yioop system. "summaries" says add a toggle headers and extracted
     *      summaries link. "cache_link_referrer" says a link on a cache page
     *      referred us to the current cache request
     * @param int $crawl_time the timestamp of the crawl to look up the cached
     *      page in
     */
   function cacheRequestAndOutput($url, $ui_flags = array(), $terms ="",
        $crawl_time = 0)
    {
        global $CACHE, $IMAGE_TYPES;

        $flag = 0;
        $crawl_item = NULL;
        $all_future_times = array();
        $all_crawl_times = array();
        $all_past_times = array();

        //Check if the URL is from a cached page
        $cached_link = (in_array("cache_link_referrer", $ui_flags)) ?
            true : false;

        $hash_key = crawlHash(
            $terms.$url.serialize($ui_flags).serialize($crawl_time));
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

        //Get all crawl times
        $crawl_times = array();
        $all_crawl_details = $this->crawlModel->getCrawlList(false, true,
            $queue_servers);
        foreach($all_crawl_details as $crawl_details) {
            if($crawl_details['CRAWL_TIME'] != 0) {
                array_push($crawl_times, $crawl_details['CRAWL_TIME']);
            }
        }
        for($i=0; $i < count($crawl_times); $i++) {
            $crawl_times[$i] = intval($crawl_times[$i]);
        }
        asort($crawl_times);
        //Get int value of current crawl time for comparison
        $crawl_time_int = intval($crawl_time);

        /* Search for all crawl times containing the cached
          version of the page for $url for multiple and single queue servers */

        list($network_crawl_times, $network_crawl_items) = $this->
            getCrawlItems($url, $crawl_times, $queue_servers);

        $nonnet_crawl_times = array_diff($crawl_times,
            $network_crawl_times);
        if(count($nonnet_crawl_times) > 0) {
            list($nonnet_crawl_times, $nonnet_crawl_items) = $this->
                getCrawlItems($url, $nonnet_crawl_times, NULL);
        } else {
            $nonnet_crawl_items = array();
        }
        $nonnet_crawl_times = array_diff($nonnet_crawl_times,
            $network_crawl_times);
        $all_crawl_times = array_values(array_merge($nonnet_crawl_times,
            $network_crawl_times));
        sort($all_crawl_times, SORT_STRING);

        //Get past and future crawl times
        foreach($all_crawl_times as $time) {
            if($time >= $crawl_time_int) {
                array_push($all_future_times, $time);
            } else {
                array_push($all_past_times, $time);
            }
        }

        /*Get the nearest timestamp (future or past)
         *Check in future first and if not found, check in past
         */
        if(!empty($all_future_times)) {
            $crawl_time = array_shift($all_future_times);
            array_push($all_future_times, $crawl_time);
            sort($all_future_times, SORT_STRING);
            if(in_array($crawl_time, $network_crawl_times)){
                $queue_servers = $network_crawl_items['queue_servers'];
            } else {
                $queue_servers = $nonnet_crawl_items['queue_servers'];
            }
        } else if(!empty($all_past_times)) {
            $crawl_time = array_pop($all_past_times);
            array_push($all_past_times, $crawl_time);
            sort($all_past_times, SORT_STRING);
            if(in_array($crawl_time, $network_crawl_times)){
                $queue_servers = $network_crawl_items['queue_servers'];
            } else {
                $queue_servers = $nonnet_crawl_items['queue_servers'];
            }
        }
        $this->phraseModel->index_name = $crawl_time;
        $this->crawlModel->index_name = $crawl_time;
        $crawl_item = $this->crawlModel->getCrawlItem($url, $queue_servers);
        // A crawl item is able to override the default UI_FLAGS
        if(isset($crawl_item[self::UI_FLAGS]) &&
            is_string($crawl_item[self::UI_FLAGS])) {
            $ui_flags = explode(",", $crawl_item[self::UI_FLAGS]);
        }
        $data = array();

        if($crawl_item == NULL) {
            if($cached_link == true) {
                header("Location: $url");
            } else {
                $this->displayView("nocache", $data);
                return;
            }
        }

        $check_fields = array(self::TITLE, self::DESCRIPTION, self::LINKS);
        foreach($check_fields as $field) {
            $crawl_item[$field] = (isset($crawl_item[$field])) ?
                $crawl_item[$field] : "";
        }

        $summary_string = $this->crawlItemSummary($crawl_item);

        $robot_instance = $crawl_item[self::ROBOT_INSTANCE];
        $robot_table_name = CRAWL_DIR."/".self::robot_table_name;
        $robot_table = array();
        if(file_exists($robot_table_name)) {
            $robot_table = unserialize(file_get_contents($robot_table_name));
        }
        if(isset($robot_table[$robot_instance])) {
            $machine = $robot_table[$robot_instance][0];
            $machine_uri = $robot_table[$robot_instance][1];
        } else {
            //guess we are in a single machine setting
            $machine = UrlParser::getHost(NAME_SERVER);
            if($machine[4] == 's') { // start with https://
                $machine = substr($machine, 8);
            } else { // start with http://
                $machine = substr($machine, 7);
            }
            $machine_uri = WEB_URI;
        }
        $instance_parts = explode("-", $robot_instance);
        $instance_num = false;
        if(count($instance_parts) > 1) {
            $instance_num = intval($instance_parts[0]);
        }
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
        if(isset($crawl_item[self::ROBOT_METAS]) &&
                (in_array("NOARCHIVE", $crawl_item[self::ROBOT_METAS]) ||
                in_array("NONE", $crawl_item[self::ROBOT_METAS])) ) {
            $cache_file = "<div>'.
                tl('search_controller_no_archive_page').'</div>";
        } else {
            $cache_file = $cache_item[self::PAGE];
        }
        if(isset($crawl_item[self::THUMB])) {
            $cache_file = $this->imageCachePage($url, $cache_item, $cache_file,
                $queue_servers);
            unset($ui_flags["highlight"]);
        }
        if(isset($crawl_item[self::KEYWORD_LINKS])) {
            $cache_item[self::KEYWORD_LINKS] = $crawl_item[self::KEYWORD_LINKS];
        }

        if(in_array('yioop_nav', $ui_flags) && !(isset($_SERVER['_']) &&
            stristr($_SERVER['_'], 'hhvm')) || //HipHop and Dom cause problems
            (isset($_SERVER['SERVER_SOFTWARE']) && 
            $_SERVER['SERVER_SOFTWARE'] == "HPHP"))) {
            $newDoc = $this->formatCachePage($cache_item, $cache_file, $url,
                $summary_string, $crawl_time, $all_crawl_times, $terms,
                $ui_flags);
        } else {
            $newDoc = $cache_file;
        }
        if(USE_CACHE) {
            $CACHE->set($hash_key, $newDoc);
        }
        echo $newDoc;
    }

    /**
     *  Makes an HTML web page for an image cache item
     *
     *  @param string $url original url of the image
     *  @param array $cache_item details about the image item
     *  @param string $cache_file string with image
     *  @param $queue_servers machines used by yioop for the current index
     *      cache item is from. Used to find out urls on which image occurred
     *  @return string an HTML page with the image embedded as a data url
     */
    function imageCachePage($url, $cache_item, $cache_file, $queue_servers)
    {
        $inlinks = $this->phraseModel->getPhrasePageResults(
            "link:$url", 0, 1, true, NULL, false, 0, $queue_servers);
        $in_url = isset($inlinks["PAGES"][0][self::URL]) ?
            $inlinks["PAGES"][0][self::URL] : "";
        $type = $cache_item[self::TYPE];
        $loc_url = ($in_url == "") ? $url : $in_url;
        $cache_file = "<html><head><title>Yioop! Cache</title></head>".
            "<body><object onclick=\"document.location='$loc_url'\"".
            " data='data:$type;base64,".
            base64_encode($cache_file)."' type='$type' />";
        if($loc_url != $url) {
            $cache_file .= "<p>".tl('search_controller_original_page').
                "<br /><a href='$loc_url'>$loc_url</a></p>";
        }
        $cache_file .= "</body></html>";
        return $cache_file;
    }

    /**
     * Generates a string representation of a crawl item suitable for
     * for output in a cache page
     *
     * @param array $crawl_item summary information of a web page (title,
     *      description, etc)
     * @return string suitable string formatting of item
     */
    function crawlItemSummary($crawl_item)
    {
        $summary_string =
            tl('search_controller_extracted_title')."\n\n".
            wordwrap($crawl_item[self::TITLE], 80, "\n")."\n\n" .
            tl('search_controller_extracted_description')."\n\n".
            wordwrap($crawl_item[self::DESCRIPTION], 80, "\n")."\n\n".
            tl('search_controller_extracted_links')."\n\n".
            wordwrap(print_r($crawl_item[self::LINKS], true), 80, "\n");
        if(isset($crawl_item[self::ROBOT_PATHS])) {
            if(isset($crawl_item[self::ROBOT_PATHS][self::ALLOWED_SITES])) {
                $summary_string =
                    tl('search_controller_extracted_allow_paths')."\n\n".
                    wordwrap(print_r($crawl_item[self::ROBOT_PATHS][
                        self::ALLOWED_SITES], true),  80, "\n");
            }
            if(isset($crawl_item[self::ROBOT_PATHS][self::DISALLOWED_SITES])) {
                $summary_string =
                    tl('search_controller_extracted_disallow_paths')."\n\n".
                    wordwrap(print_r($crawl_item[self::ROBOT_PATHS][
                        self::DISALLOWED_SITES], true),  80, "\n");
            }
            if(isset($crawl_item[self::CRAWL_DELAY])) {
                $summary_string =
                    tl('search_controller_crawl_delay')."\n\n".
                    wordwrap(print_r($crawl_item[self::CRAWL_DELAY], true),
                        80, "\n") ."\n\n". $summary_string;
            }
        }
        return $summary_string;
    }

    /**
     * Formats a cache of a web page (adds history ui and highlight keywords)
     *
     * @param array $cache_item details meta information about the cache page
     * @param string $cache_file contains current web page before formatting
     * @param string $url that cache web page was originally from
     * @param string $summary_string summary data that was extracted from the
     *      web page to be put in the actually inverted index
     * @param int $crawl_time timestamp of crawl cache page was from
     * @param array $all_crawl_times timestamps of all crawl times currently
     *      in Yioop system
     * @param string $terms from orginal query responsible for cache request
     * @param array $ui_flags array of  ui features which
     *      should be added to the cache page. For example, "highlight"
     *      would way search terms should be highlighted, "history"
     *      says add history navigation for all copies of this cache page in
     *      yioop system.
     * return string of formatted cached page
     */
    function formatCachePage($cache_item, $cache_file, $url,
        $summary_string, $crawl_time, $all_crawl_times, $terms, $ui_flags)
    {
        //Check if it the URL is from the UI
        $hist_ui_open = in_array("hist_ui_open", $ui_flags) ? true : false;

        $date = date ("F d Y H:i:s", $cache_item[self::TIMESTAMP]);

        $meta_words = $this->phraseModel->meta_words_list;
        foreach($meta_words as $meta_word) {
            $pattern = "/(\b)($meta_word(\S)+)/";
            $terms = preg_replace($pattern, "", $terms);
        }
        $terms = str_replace("'", " ", $terms);
        $terms = str_replace('"', " ", $terms);
        $terms = str_replace('\\', " ", $terms);
        $terms = str_replace('|', " ", $terms);
        $terms = $this->clean($terms, "string");

        $phrase_string = mb_ereg_replace("[[:punct:]]", " ", $terms);
        $words = mb_split(" ", $phrase_string);
        if(!in_array("highlight", $ui_flags)) {
            $words = array();
        }

        $dom = new DOMDocument();

        $did_dom = @$dom->loadHTML('<?xml encoding="UTF-8">' . $cache_file);
        foreach ($dom->childNodes as $item) {
            if($item->nodeType == XML_PI_NODE)
                $dom->removeChild($item); // remove hack
        }
        $dom->encoding = "UTF-8"; // insert proper

        $head = $dom->getElementsByTagName('head')->item(0);

        if(is_object($head)) {
            // add a noindex nofollow robot directive to page
            $head_first_child = $head->firstChild;
            $robot_node = $dom->createElement('meta');
            $robot_node = $head->insertBefore($robot_node, $head_first_child);
            $robot_node->setAttribute("name", "ROBOTS");
            $robot_node->setAttribute("content", "NOINDEX,NOFOLLOW");
            $comment = $dom->createComment(
                tl('search_controller_cache_comment'));
            $comment = $head->insertBefore($comment, $robot_node);
            // make link and script links absolute
            $head = $this->canonicalizeLinks($head, $url);
        } else {
            $body_tags = "<frameset><frame><noscript><img><span><b><i><em>".
                "<strong><h1><h2><h3><h4><h5><h6><p><div>".
                "<a><table><tr><td><th><dt><dir><dl><dd>";
            $cache_file = strip_tags($cache_file, $body_tags);
            $cache_file = "<html><head><title>Yioop! Cache</title></head>".
                "<body>".$cache_file."</body></html>";
            $dom = new DOMDocument();
            @$dom->loadHTML($cache_file);
        }
        $body =  $dom->getElementsByTagName('body')->item(0);
        //make tags in body absolute
        $body = $this->canonicalizeLinks($body, $url);
        $first_child = $body->firstChild;
        $text_align = (getLocaleDirection() == 'ltr') ? "left" : "right";
        // add information about what was extracted from page
        if(in_array("summaries", $ui_flags)) {
            $summary_toggle_node = $this->createSummaryAndToggleNodes($dom,
                $text_align, $body, $summary_string, $cache_item);
        } else {
            $summary_toggle_node = $first_child;
        }
        if(isset($cache_item[self::KEYWORD_LINKS]) &&
            count($cache_item[self::KEYWORD_LINKS]) > 0) {
            $keyword_node = $this->createDomBoxNode($dom, $text_align,
                "zIndex: 1");
            $text_node = $dom->createTextNode("Z@key_links@Z");
            $keyword_node->appendChild($text_node);
            $keyword_node = $body->insertBefore($keyword_node,
                $summary_toggle_node);
            $set_key_links = true;
        } else {
            $keyword_node = $summary_toggle_node;
            $set_key_links = false;
        }

        if(in_array("version", $ui_flags)) {
            $version_node =
                $this->createDomBoxNode($dom, $text_align, "zIndex: 1");
            $textNode = $dom->createTextNode(
                tl('search_controller_cached_version', "Z@url@Z", $date));
            $version_node->appendChild($textNode);
            $brNode = $dom->createElement('br');
            $version_node->appendChild($brNode);
            $this->addCacheJavascriptTags($dom, $version_node);
            $version_node = $body->insertBefore($version_node, $keyword_node);
        } else {
            $version_node = $keyword_node;
        }

        //UI for showing history
        if(in_array("history", $ui_flags)) {
            $history_node = $this->historyUI($crawl_time, $all_crawl_times,
                $version_node, $dom, $terms, $hist_ui_open, $url);
        } else {
            $history_node = $dom->createElement('div');
        }

        if($history_node) {
            $version_node->appendChild($history_node);
        }

        $body = $this->markChildren($body, $words, $dom);

        $new_doc = $dom->saveHTML();
        if(substr($url, 0, 7) != "record:") {
            $url = "<a href='$url'>$url</a>";
        }
        $new_doc = str_replace("Z@url@Z", $url, $new_doc);
        $colors = array("yellow", "orange", "gray", "cyan");
        $color_count = count($colors);

        $i = 0;
        foreach($words as $word) {
            //only mark string of length at least 2
            if(mb_strlen($word) > 1) {
                $mark_prefix = crawlHash($word);
                if(stristr($mark_prefix, $word) !== false) {
                    $mark_prefix = preg_replace(
                    "/$word/i", '', $mark_prefix);
                }
                $match = $mark_prefix.$word;
                $new_doc = preg_replace("/$match/i",
                    '<span style="background-color:'.
                    $colors[$i].'">$0</span>', $new_doc);
                $i = ($i + 1) % $color_count;
                $new_doc = preg_replace("/".$mark_prefix."/", "", $new_doc);
            }
        }
        if($set_key_links) {
            $new_doc = $this->addKeywordLinks($new_doc, $cache_item);
        }

        return $new_doc;
    }

    /**
     *  Function used to add links for keyword searches in keyword_links
     *  array of $cache_item to the text of the $web_page we are going to
     *  display the cache of as part of a pache page request
     *
     *  @param string $web_page to add links to
     *  @param array $cache_item original cache item web page generated from
     *  @return string modified web page
     */
    function addKeywordLinks($web_page, &$cache_item)
    {
        $base = $this->baseLink()."&its=".$this->getIndexTimestamp();
        $link_list = "<ul>";
        foreach($cache_item[self::KEYWORD_LINKS] as $keywords => $text) {
            $keywords = urlencode($keywords);
            $link_list .= "<li><a href='$base&q=$keywords' rel='nofollow'>".
                "$text</a></li>";
        }
        $link_list .= "</ul>";
        $web_page = str_replace("Z@key_links@Z", $link_list, $web_page);
        return $web_page;
    }

    /**
     *  Creates the toggle link and hidden div for extracted header and
     *  summary element on cache pages
     *
     * @param DOMDocument $dom used to create new nodes to add to body object
     *      for page
     * @param string $text_align whether rtl or ltr language
     * @param DOMElement $body represent body of cached page
     * @param string $summary_string header and summary that were extraced
     * @param array $cache_item contains infor about the cached item
     * @return DOMElement a div node with toggle link and hidden div
     */
    function createSummaryAndToggleNodes($dom, $text_align, $body,
        $summary_string, $cache_item)
    {
        $first_child = $body->firstChild;
        $summaryNode = $this->createDomBoxNode($dom, $text_align,
            "display:none;", 'pre');
        $summaryNode->setAttributeNS("","id", "summary-page-id");
        $summaryNode = $body->insertBefore($summaryNode, $first_child);

        if(isset($cache_item[self::HEADER])) {
            $summary_string = $cache_item[self::HEADER]."\n".
                $summary_string;
        }
        $textNode = $dom->createTextNode($summary_string);
        $summaryNode->appendChild($textNode);

        $scriptNode = $dom->createElement('script');
        $scriptNode = $body->insertBefore($scriptNode, $summaryNode);
        $textNode = $dom->createTextNode("var summary_show = 'none';");
        $scriptNode->appendChild($textNode);

        $aDivNode = $this->createDomBoxNode($dom, $text_align);
        $aNode = $dom->createElement("a");
        $aTextNode =
            $dom->createTextNode(tl('search_controller_header_summaries'));
        $toggle_code = "javascript:".
            "summary_show = (summary_show != 'block') ? 'block' : 'none';".
            "summary_pid = elt('summary-page-id');".
            "summary_pid.style.display = summary_show;";
        $aNode->setAttributeNS("", "onclick", $toggle_code);
        $aNode->setAttributeNS("", "style", "zIndex: 1;".
            "text-decoration: underline; cursor: pointer");

        $aNode->appendChild($aTextNode);

        $aDivNode->appendChild($aNode);
        $body->insertBefore($aDivNode, $summaryNode);
        return $aDivNode;
    }

    /**
     * Creates a bordered tag (usually div) in which to put meta content on a
     * page when it is displayed
     *
     * @param DOMDocument $dom representing cache page
     * @param string $text_align whether doc is ltr or rtl
     * @param string $more_styles any additional styles for box
     * @param string $tag base tag of box (default div)
     * @return DOMElement of styled box
     */
    function createDomBoxNode($dom, $text_align, $more_styles="", $tag="div")
    {
        $divNode = $dom->createElement($tag);
        $divNode->setAttributeNS("","style", "border-color: black; ".
            "border-style:solid; border-width:3px; margin-bottom:10px;".
            "padding: 5px; background-color: white; ".
            "text-align:$text_align; $more_styles");
        return $divNode;
    }

    /**
     *  Get crawl items based on queue server setting.
     *
     *  @param string $url is the URL of the cached page
     *  @param array $crawl_times is an array storing crawl times for all
     *      indexes
     *  @param array $queue_servers is an array containing URLs for queue
     *      servers
     *  @return array($all_crawl_times, $all_crawl_items) is an array containing
     *      an array of crawl times and an array of their respective crawl items
     */
    function getCrawlItems($url, $crawl_times, $queue_servers)
    {
        $all_crawl_times = array();
        $all_crawl_items = array();
        $all_crawl_items['queue_servers'] = $queue_servers;
        foreach($crawl_times as $time) {
            $crawl_time = (string)$time;
            $this->crawlModel->index_name = $crawl_time;
            $crawl_item = $this->crawlModel->getCrawlItem($url, $queue_servers);
            if($crawl_item != NULL) {
                array_push($all_crawl_times, $crawl_time);
                $all_crawl_items[$crawl_time] = $crawl_item;
            }
        }

        return array($all_crawl_times, $all_crawl_items);
    }

    /**
     *  User Interface for history feature
     *
     *  @param long $crawl_time is the crawl time
     *  @param array $all_crawl_times is an array storing all crawl time
     *  @param DOMElement $divNode is the section that contains the History UI
     *  @param DOMDocument $dom is the DOM of the cached page
     *  @param string $terms is a string containing query terms
     *  @param boolean $hist_ui_open is a flag to check if History UI should be
     *      open by default
     *  @param string $url is the URL of the page
     *
     *  @return DOMElement the section containing the options for
     *      selecting year and month
     */
    function historyUI($crawl_time, $all_crawl_times, $divNode, $dom, $terms,
        $hist_ui_open, $url)
    {
        //Guess locale for date localization
        $locale_type = guessLocale();

        //Create data structure that stores years months and associated links
        list($time_ds, $years, $months) = $this->
            createHistoryDataStructure($all_crawl_times, $locale_type, $url);

        //Access to history feature
        $this->toggleHistory($months, $divNode, $dom);

        //Display current year, current month and their respective links
        $current_date = formatDateByLocale($crawl_time, $locale_type);
        $current_components = explode(" ", $current_date);
        $current_year = $current_components[2];
        $current_month = $current_components[0];

        //UI for viewing links by selecting year and month
        $d1 = $this->viewLinksByYearMonth($years, $months, $current_year,
        $current_month, $time_ds, $dom);

        /*create divs for all year.month pairs and populate with links
         */
        $d1 = $this->createLinkDivs($time_ds, $current_year, $current_month,
            $d1, $dom, $url, $years, $hist_ui_open, $terms, $crawl_time);

        return $d1;
    }

    /**
     * The history toggle displays the year and month associated with
     * the timestamp at which the page was cached.
     * @param array months is an array storing months
     * @param DOMElement $divNode is the section that contains the History UI
     * @param DOMDocument $dom is the DOM of the cached page
     */
    function toggleHistory($months, $divNode, $dom)
    {
        $month_json = json_encode($months);
        $historyLink = $dom->createElement('a');
        $historyLink->setAttributeNS("", "id", "#history");
        $historyLink->setAttributeNS("", "months", $month_json);
        $historyLabel = $dom->createTextNode(tl('search_controller_history'));
        $historyLink->appendChild($historyLabel);
        $divNode->appendChild($historyLink);
        $historyLink->setAttributeNS('', 'style',
            "text-decoration: underline; cursor: pointer");
    }

    /**
     * Creates a data structure for storing years, months and associated
     * timestamp components
     * @param array $all_crawl_times is an array storing all crawl time
     * @param string $locale_type is the locale tag
     * @param string $url is the URL for the cached page
     * @return array $results is an array storing years array, months array
     * and the combined data structure for the History UI
     */
    function createHistoryDataStructure($all_crawl_times, $locale_type, $url)
    {
        $years = array();
        $months = array();
        $time_components = array();
        $time_ds = array();

        //Initialize data structure
        if(!empty($all_crawl_times)){
            foreach($all_crawl_times as $cache_time) {
                $date_time_string =
                    formatDateByLocale($cache_time, $locale_type);
                $time_components = explode(" ", $date_time_string);
                $time_ds[$time_components[2]][$time_components[0]] = null;
            }
        }
        //Populate data structure
        if(!empty($all_crawl_times)){
            foreach($all_crawl_times as $cache_time){
                $date_time_string =
                    formatDateByLocale($cache_time, $locale_type);
                $time_components = explode(" ", $date_time_string);
                if(!in_array($time_components[2], $years)) {
                    array_push($years, $time_components[2]);
                }
                if(!in_array($time_components[0], $months)) {
                    array_push($months, $time_components[0]);
                }
                $temp = "$time_components[0] $time_components[1] ".
                        "$time_components[3] $url $cache_time";
                if($time_ds[$time_components[2]][$time_components[0]]
                    === null) {
                    $time_ds[$time_components[2]][$time_components[0]] =
                        array($temp);
                } else {
                    array_push($time_ds[$time_components[2]]
                        [$time_components[0]], $temp);
                }
            }
        }

        $results = array();
        array_push($results, $time_ds);
        array_push($results, $years);
        array_push($results, $months);

        return $results;
    }

    /**
     * Create divs for links based on all (year, month) combinations
     * @param array $time_ds is the data structure for History UI
     * @param string $current_year is the year associated with the timestamp
     * of the cached page
     * @param string $current_month is the month associated with the timestamp
     * of the cached page
     * @param DOMElement $d1 is the section that contains options for years and
     * months
     * @param DOMDocument $dom is the DOM for the cached page
     * @param string $url is the URL for the cached page
     * @param array years is an array storing years associated with all indexes
     * @param boolean $hist_ui_open checks if the History UI state should be
     *      open
     * @param string $terms is a string containing the query terms
     * @param long $crawl_time is the crawl time for the cached page
     * @return DOMElement $d1 is the section containing the options for
     * selecting year and month
     */
    function createLinkDivs($time_ds, $current_year, $current_month, $d1, $dom,
        $url, $years, $hist_ui_open, $terms, $crawl_time)
    {
        $yrs = array_keys($time_ds);
        foreach($years as $yr) {
            $mths = array_keys($time_ds[$yr]);
            foreach($mths as $mth) {
                $yeardiv = $dom->createElement("div");
                $yeardiv->setAttributeNS("", "id", "#$yr$mth");
                $yeardiv->setAttributeNS("", "style", "display:none");
                if($hist_ui_open === true){
                    if(!strcmp($yr, $current_year) &&
                        !strcmp($mth, $current_month)) {
                        $yeardiv->setAttributeNS("", "style", "display:block");
                        $d1->setAttributeNS("", "style",
                            "display:block");
                    }
                }
                $yeardiv = $d1->appendChild($yeardiv);
                $list_dom = $dom->createElement("ul");
                $yeardiv->appendChild($list_dom);
                foreach($time_ds[$yr][$mth] as $entries) {
                    $list_item = $dom->createElement('li');
                    $arr = explode(" ", $entries);
                    $url_encoded = urlencode($arr[3]);
                    $link_text = $dom->createTextNode("$arr[0] $arr[1] ".
                            "$arr[2]");
                    $link = $this->baseLink()."&a=cache&".
                        "q=$terms&arg=$url_encoded&its=$arr[4]&hist_open=true";
                    $link_dom = $dom->createElement("a");
                        $link_dom->setAttributeNS("", "href", $link);
                    if($arr[4] == $crawl_time) {
                        $bold = $dom->createElement('b');
                        $bold->appendChild($link_text);
                        $link_dom->appendChild($bold);
                    } else {
                        $link_dom->appendChild($link_text);
                    }
                    $list_item->appendChild($link_dom);
                    $list_dom->appendChild($list_item);
                }
            }
        }
        return $d1;
    }

    /**
     * Used to create the base link for links to be displayed on caches
     * of web pages this link points to yioop because links on cache pages
     * are to other cache pages
     *
     * @return string desired base link
     */
    function baseLink()
    {
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $user = $_SERVER['REMOTE_ADDR'];
        } else {
            $user = "127.0.0.1";
        }
        $csrf_token = $this->generateCSRFToken($user);
        $link = "?".CSRF_TOKEN."=$csrf_token&c=search";
        return $link;
    }

    /**
     * Display links based on selected year and month in History UI
     * @param array years is an array storing years associated with all indexes
     * @param array months is an array storing months
     * @param string $current_year is the year associated with the timestamp
     * of the cached page
     * @param string $current_month is the month associated with the timestamp
     * of the cached page
     * @param array $time_ds is the data structure for History UI
     * @param DOMDocument $dom is the DOM for the cached page
     * @return DOMElement $d1 is the section containing the options for
     * selecting year and month
     */
    function viewLinksByYearMonth($years, $months, $current_year,
        $current_month, $time_ds, $dom)
    {
        $year_json = json_encode($years);
        $month_json = json_encode($months);
        $d1 = $dom->createElement('div');
        $d1->setAttributeNS("", "id", "#d1");
        $d1->setAttributeNS("", "years", $year_json);
        $d1->setAttributeNS("", "months", $month_json);
        $title = $dom->createElement('span');
        $title->setAttributeNS("", "style", "color:green;");
        $title_text = $dom->createTextNode(
            tl('search_controller_all_cached'));
        $br = $dom->createElement('br');
        $title->appendChild($title_text);
        $d1->appendChild($title);
        $d1->appendChild($br);
        $s1 = $dom->createElement('span');
        $y = $dom->createElement('select');
        $y->setAttributeNS("", "id", "#year");
        $m = $dom->createElement('select');
        $m->setAttributeNS("", "id", "#month");
        foreach($years as $year) {
            $o = $dom->createElement('option');
            $o->setAttributeNS("", "id", "#$year");
            if(strcmp($year, $current_year) == 0){
                $o->setAttributeNS("", "selected", "selected");
                $months = array_keys($time_ds[$year]);
                foreach($months as $month) {
                    $p = $dom->createElement('option');
                    $p->setAttributeNS("", "id", "#$month");
                    if(strcmp($month, $current_month) == 0){
                        $p->setAttributeNS("", "selected", "selected");
                    }
                    $mt = $dom->createTextNode($month);
                    $p->appendChild($mt);
                    $m->appendChild($p);
                }
            }
            $yt = $dom->createTextNode($year);
            $o->appendChild($yt);
            $y->appendChild($o);
        }
        $yl = $dom->createTextNode(tl('search_controller_year'));
        $ml = $dom->createTextNode(tl('search_controller_month'));
        $s1->appendChild($yl);
        $s1->appendChild($y);
        $s1->appendChild($ml);
        $s1->appendChild($m);
        $d1->appendChild($s1);
        $d1->setAttributeNS("", "style", "display:none");
        $this->addCacheJavascriptTags($dom, $d1);

        return $d1;
    }

    /**
     * Add to supplied node subnodes containing script tags for javascript
     * libraries used to display cache pages
     *
     * @param DOMDocument $dom used to create new nodes
     * @param DomElement &$node what to add script node to
     */
    function addCacheJavascriptTags($dom, &$node)
    {
        $script = $dom->createElement("script");
        $script->setAttributeNS("","src", NAME_SERVER."/scripts/basic.js");
        $node->appendChild($script);
        $script = $dom->createElement("script");
        $script->setAttributeNS("","src", NAME_SERVER."/scripts/history.js");
        $node->appendChild($script);
    }
}
?>
