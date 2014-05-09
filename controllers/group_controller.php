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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Load base controller class if needed */
require_once BASE_DIR."/controllers/controller.php";
/** Loads common constants for web crawling */
require_once BASE_DIR."/lib/crawl_constants.php";
/** Need get host for search filter admin */
require_once BASE_DIR."/lib/url_parser.php";
/** Used in rule parser test in page options */
require_once BASE_DIR."/lib/page_rule_parser.php";
/** Used to create, update, and delete user-trained classifiers. */
require_once BASE_DIR."/lib/classifiers/classifier.php";
/** Loads crawl_daemon to manage news_updater */
require_once BASE_DIR."/lib/crawl_daemon.php";
/** get processors for different file types */
foreach(glob(BASE_DIR."/lib/processors/*_processor.php") as $filename) {
    require_once $filename;
}
/**
 * Controller used to handle admin functionalities such as
 * modify login and password, CREATE, UPDATE,DELETE operations
 * for users, roles, locale, and crawls
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */

class GroupController extends Controller implements CrawlConstants
{
    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to (note: more activities will be loaded from
     * components)
     * @var array
     */
    var $activities = array("feed", "wiki");

    /**
     *
     */
    function processRequest()
    {
        $data = array();

        if(!PROFILE) {
            return $this->configureRequest();
        }
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $data['SCRIPT'] = "";
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user);
        $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user);

        if(!$token_okay) {
            $keep_fields = array("a","f","just_group_id","just_user_id",
                "just_thread", "limit", "num");
            $request = $_REQUEST;
            $_REQUEST = array();
            foreach($keep_fields as $field) {
                if(isset($request[$field])) {
                    $_REQUEST[$field] =
                        $this->clean($request[$field], "string");
                }
            }
            $_REQUEST["c"] = "group";
        }
        $data = array_merge($data, $this->processSession());
        if(!isset($data['REFRESH'])) {
            $view = "group";
        } else {
            $view = $data['REFRESH'];
        }
        if(isset($_REQUEST['f']) &&
            in_array($_REQUEST['f'], array("rss", "json", "serial"))) {
            $this->setupViewFormatOutput($_REQUEST['f'], $view, $data);
        }
        $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        $this->displayView($view, $data);
    }

    /**
     *
     */
    function processSession()
    {
        if(isset($_REQUEST['a']) &&
            in_array($_REQUEST['a'], $this->activities)) {
            $activity = $_REQUEST['a'];
        } else {
            $activity = "groupFeeds";
        }
        $data = $this->call($activity);
        $data['ACTIVITY_CONTROLLER'] = "group";
        $data['ACTIVITY_METHOD'] = $activity; //for settings controller
        if(!is_array($data)) {
            $data = array();
        }
        return $data;
    }

    /**
     *
     */
    function setupViewFormatOutput($format, &$view, &$data)
    {
        $data["QUERY"] = "groups:feed";
        if(isset($data["JUST_GROUP_ID"])) {
            $data["QUERY"] = "groups:just_group_id:".$data["JUST_GROUP_ID"];
        }
        if(isset($data["JUST_USER_ID"])) {
            $data["QUERY"] = "groups:just_user_id:".$data["JUST_USER_ID"];
        }
        if(isset($data["JUST_THREAD"])) {
            $data["QUERY"] = "groups:just_thread:".$data["JUST_THREAD"];
        }
        $data["its"] = 0;
        $num_pages = count($data["PAGES"]);
        $base_query = $data['PAGING_QUERY']."&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN]."&amp;";
        for($i = 0; $i < $num_pages; $i++) {
            $data["PAGES"][$i][self::URL] = BASE_URL. $base_query .
                "just_thread=". $data["PAGES"][$i]['PARENT_ID'];
        }
        switch($format)
        {
            case "rss":
                $view = "rss";
            break;
            case "json":
                $out_data = array();
                $out_data["language"] = getLocaleTag();
                $out_data["link"] = 
                    NAME_SERVER."?f=$format&amp;q={$data['QUERY']}";
                $out_data["totalResults"] = $data['TOTAL_ROWS'];
                $out_data["startIndex"] = $data['LIMIT'];
                $out_data["itemsPerPage"] = $data['RESULTS_PER_PAGE'];
                foreach($data['PAGES'] as $page) {
                    $item = array();
                    $item["title"] = $page[self::TITLE];
                    if(!isset($page[self::TYPE]) ||
                    (isset($page[self::TYPE])
                    && $page[self::TYPE] != "link")) {
                        $item["link"] = $page[self::URL];
                    } else {
                        $item["link"] = strip_tags($page[self::TITLE]);
                    }
                    $item["description"] = strip_tags($page[self::DESCRIPTION]);
                    if(isset($page[self::THUMB])
                    && $page[self::THUMB] != 'NULL') {
                        $item["thumb"] = $page[self::THUMB];
                    }
                    if(isset($page[self::TYPE])) {
                        $item["type"] = $page[self::TYPE];
                    }
                    $out_data['item'][] =$item;
                }
                e(json_encode($out_data));
                exit();
            break;
            case "serial":
                e(serialize($out_data));
                exit();
            break;
        }
    }

    /**
     *
     */
    function wiki()
    {
        $data = array();
        return $data;
    }
}
?>