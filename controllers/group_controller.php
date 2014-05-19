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
/** For Wiki Parsing */
require_once BASE_DIR."/lib/wiki_parser.php";
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
    var $activities = array("groupFeeds", "wiki");

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
            $user_id = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
        } else {
            $user_id = $_SERVER['REMOTE_ADDR'];
        }
        $data['SCRIPT'] = "";
        $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user_id);
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user_id);
        if(!$token_okay) {
            $keep_fields = array("a","f","just_group_id","just_user_id",
                "just_thread", "limit", "num", "arg", "page_name");
            $request = $_REQUEST;
            $_REQUEST = array();
            foreach($keep_fields as $field) {
                if(isset($request[$field])) {
                    if($field == "arg" && $request[$field] != "read") {
                        continue;
                    }
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
        if($data['ACTIVITY_METHOD'] == "wiki") {
            if(isset($data["VIEW"]) && !isset($data['REFRESH'])) {
                $view = $data["VIEW"];
            }
        } else if(isset($_REQUEST['f']) &&
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
        $data["VIEW"] = "wiki";
        $data["SCRIPT"] = "";
        $group_model = $this->model("group");
        $locale_tag = getLocaleTag();
        $data['CURRENT_LOCALE_TAG'] = $locale_tag;
        if(isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
        } else {
            $user_id = $_SERVER['REMOTE_ADDR'];
        }
        $clean_array = array(
            "group_id" => "int",
            "page_name" => "string",
            "page" => "string",
            "edit_reason" => "string",
            "filter" => 'string',
            "limit" => 'int',
            "num" => 'int',
            "page_id" => 'int',
            "show" => 'int',
            "diff" => 'int',
            "diff1" => 'int',
            "diff2" => 'int',
            "revert" => 'int'
        );
        $last_care_missing = 2;
        $missing_fields = false;
        $i = 0;
        foreach($clean_array as $field => $type) {
            if(isset($_REQUEST[$field])) {
                $$field = $this->clean($_REQUEST[$field], $type);
            } else if($i < $last_care_missing) {
                $$field = false;
                $missing_fields = true;
            }
            $i++;
        }
        if(isset($_REQUEST['group_id'])) {
            $group_id = $this->clean($_REQUEST['group_id'], "int");
        } else {
            $group_id = PUBLIC_GROUP_ID;
        }
        $group = $group_model->getGroupById($group_id, $user_id);
        $data["CAN_EDIT"] = false;
        $data["MODE"] = "read";
        if(!$group) {
            $group_id = PUBLIC_GROUP_ID;
            $group = $group_model->getGroupById($group_id, $user_id);
        } else {
            if($group["OWNER_ID"] == $user_id ||
                ($group["STATUS"] == ACTIVE_STATUS &&
                $group["MEMBER_ACCESS"] == GROUP_READ_WRITE)) {
                $data["CAN_EDIT"] = true;
            }
        }
        $read_address = "?c=group&amp;a=wiki&amp;arg=read&amp;group_id=".
            "$group_id&amp;page_name=";
        if(isset($_REQUEST["arg"])) {
            switch($_REQUEST["arg"])
            {
                case "edit":
                    if(!$data["CAN_EDIT"]) { continue; }
                    $data["MODE"] = "edit";
                    if($missing_fields) {
                        $data['SCRIPT'] .=
                            "doMessage('<h1 class=\" red\" >". 
                            tl("group_controller_missing_fields").
                            "</h1>')";
                    } else if(!$missing_fields && isset($page)) {
                        $group_model->setPageName($user_id,
                            $group_id, $page_name, $page,
                            $locale_tag, $edit_reason,
                            tl('group_controller_page_created', $page_name),
                            tl('group_controller_page_discuss_here'),
                            $read_address);
                        $data['SCRIPT'] .=
                            "doMessage('<h1 class=\"red\" >".
                            tl("group_controller_page_saved").
                            "</h1>')";
                    }
                break;
                case "history":
                    if(!$data["CAN_EDIT"] || !$page_id) { 
                        continue;
                    }
                    $data["MODE"] = "history";
                    $data["PAGE_NAME"] = "history";
                    $limit = isset($limit) ? $limit : 0;
                    $num = (isset($_SESSION["MAX_PAGES_TO_SHOW"])) ?
                       $_SESSION["MAX_PAGES_TO_SHOW"] :
                       DEFAULT_ADMIN_PAGING_NUM;
                    $default_history = true;
                    if(isset($show)) {
                        $page_info = $group_model->getHistoryPage(
                            $page_id, $show);
                        if($page_info) {
                            $data["MODE"] = "show";
                            $default_history = false;
                            $data["PAGE_NAME"] = $page_info["PAGE_NAME"];
                            $parser = new WikiParser($read_address);
                            $parsed_page = $parser->parse($page_info["PAGE"]);
                            $data["PAGE_ID"] = $page_id;
                            $data[CSRF_TOKEN] = 
                                $this->generateCSRFToken($user_id);
                            $history_link = "?c=group&amp;a=wiki&amp;".
                                CSRF_TOKEN.'='.$data[CSRF_TOKEN].
                                '&amp;arg=history&amp;page_id='.
                                $data['PAGE_ID'];
                            $data["PAGE"] =
                                "<div>&nbsp;</div>".
                                "<div class='black-box back-dark-gray'>".
                                "<div class='float-opposite'>".
                                "<a href='$history_link'>".
                                tl("group_controller_back") . "</a></div>".
                                tl("group_controller_history_page",
                                $data["PAGE_NAME"], date("c", $show)) .
                                "</div>" . $parsed_page;
                            $data["DISCUSS_THREAD"] = 
                                $page_info["DISCUSS_THREAD"];
                        }
                    } else if(isset($diff) && $diff &&
                        isset($diff1) && isset($diff2)) {
                        $page_info1 = $group_model->getHistoryPage(
                            $page_id, $diff1);
                        $page_info2 = $group_model->getHistoryPage(
                            $page_id, $diff2);
                        $data["MODE"] = "diff";
                        $default_history = false;
                        $data["PAGE_NAME"] = $page_info2["PAGE_NAME"];
                        $data["PAGE_ID"] = $page_id;
                        $data[CSRF_TOKEN] = 
                            $this->generateCSRFToken($user_id);
                        $history_link = "?c=group&amp;a=wiki&amp;".
                            CSRF_TOKEN.'='.$data[CSRF_TOKEN].
                            '&amp;arg=history&amp;page_id='.
                            $data['PAGE_ID'];
                        $out_diff = "<div>--- {$data["PAGE_NAME"]}\t".
                            "''$diff1''\n";
                        $out_diff .= "<div>+++ {$data["PAGE_NAME"]}\t".
                            "''$diff2''\n";
                        $out_diff .= diff($page_info1["PAGE"],
                            $page_info2["PAGE"], true);
                        $data["PAGE"] =
                            "<div>&nbsp;</div>".
                            "<div class='black-box back-dark-gray'>".
                            "<div class='float-opposite'>".
                            "<a href='$history_link'>".
                            tl("group_controller_back") . "</a></div>".
                            tl("group_controller_diff_page",
                            $data["PAGE_NAME"], date("c", $diff1),
                            date("c", $diff2)) .
                            "</div>" . "$out_diff";
                    } else if(isset($revert)) {
                        $page_info = $group_model->getHistoryPage(
                            $page_id, $revert);
                        if($page_info) {
                            $group_model->setPageName($user_id,
                                $group_id, $page_info["PAGE_NAME"], 
                                $page_info["PAGE"],
                                $locale_tag,
                                tl('group_controller_page_revert_to',
                                date('c', $revert)), "", "", $read_address);
                            $data['SCRIPT'] .=
                                "doMessage('<h1 class=\"red\" >".
                                tl("group_controller_page_reverted").
                                "</h1>')";
                        } else {
                            $data['SCRIPT'] .=
                                "doMessage('<h1 class=\"red\" >".
                                tl("group_controller_revert_error").
                                "</h1>')";
                        }
                    }
                    if($default_history) {
                        $data["LIMIT"] = $limit;
                        $data["RESULTS_PER_PAGE"] = $num;
                        list($data["TOTAL_ROWS"], $data["PAGE_NAME"],
                            $data["HISTORY"]) = 
                            $group_model->getPageHistoryList($page_id, $limit,
                            $num);
                        if((!isset($diff1) || !isset($diff2))) {
                            $data['diff1'] = $data["HISTORY"][0]["PUBDATE"];
                            $data['diff2'] = $data["HISTORY"][0]["PUBDATE"];
                            if(count($data["HISTORY"]) > 1) {
                                $data['diff2'] = $data["HISTORY"][1]["PUBDATE"];
                            }
                        }
                    }
                    $data['page_id'] = $page_id;
                break;
                case "pages":
                    $data["MODE"] = "pages";
                    $limit =isset($limit) ? $limit : 0;
                    $num = (isset($_SESSION["MAX_PAGES_TO_SHOW"])) ?
                       $_SESSION["MAX_PAGES_TO_SHOW"] :
                       DEFAULT_ADMIN_PAGING_NUM;
                    if(!isset($filter)) {
                        $filter = "";
                    }
                    if(isset($page_name)) {
                        $data['PAGE_NAME'] = $page_name;
                    }
                    $data["LIMIT"] = $limit;
                    $data["RESULTS_PER_PAGE"] = $num;
                    $data["FILTER"] = $filter;
                    $search_page_info = false;
                    if($filter != "") {
                        $search_page_info = $group_model->getPageInfoByName(
                            $group_id, $filter, $locale_tag, "read");
                    }
                    if(!$search_page_info) {
                        list($data["TOTAL_ROWS"], $data["PAGES"]) =
                            $group_model->getPageList(
                            $group_id, $locale_tag, $filter, $limit,
                            $num);
                        if($data["TOTAL_ROWS"] == 0) {
                            $data["MODE"] = "read";
                            $page_name = $filter;
                        }
                    } else {
                        $data["MODE"] = "read";
                        $page_name = $filter;
                    }
                break;
            }
        }
        if(!$page_name) {
            $page_name = tl('group_controller_main');
        }
        $data["GROUP"] = $group;
        if(in_array($data["MODE"], array("read", "edit"))) {
            if(!isset($data["PAGE"]) || !$data['PAGE']) {
                $data["PAGE_NAME"] = $page_name;
                if(isset($search_page_info) && $search_page_info) {
                    $page_info = $search_page_info;
                } else {
                    $page_info = $group_model->getPageInfoByName($group_id,
                        $page_name, $locale_tag, $data["MODE"]);
                }
                $data["PAGE"] = $page_info["PAGE"];
                $data["PAGE_ID"] = $page_info["ID"];
                $data["DISCUSS_THREAD"] = $page_info["DISCUSS_THREAD"];
            }
            if(!$data["PAGE"] && $locale_tag != DEFAULT_LOCALE) {
                //fallback to default locale for translation
                $page_info = $group_model->getPageInfoByName(
                    $group_id, $page_name, DEFAULT_LOCALE, $data["MODE"]);
                $data["PAGE"] = $page_info["PAGE"];
                $data["PAGE_ID"] = $page_info["ID"];
                $data["DISCUSS_THREAD"] = $page_info["DISCUSS_THREAD"];
            }
            $document_parts = explode("\nEND_HEAD_VARS\n", $data["PAGE"]);
            if(count($document_parts) > 1) {
                $head = $document_parts[0];
                $data["PAGE"] = $document_parts[1];
            }
            if($data['MODE'] == "read" && strpos($data["PAGE"], "`") !== false){
                if(isset($data["INCLUDE_SCRIPTS"])) {
                    $data["INCLUDE_SCRIPTS"] = array();
                }
                $data["INCLUDE_SCRIPTS"][] = "math";
            }
        }
        return $data;
    }
}
?>
