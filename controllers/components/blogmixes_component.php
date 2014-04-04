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
 * @subpackage component
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Provides activities to AdminController related to creating, updating
 *  blogs (and blog entries), static web pages, and crawl mixes.
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @subpackage component
 */
class BlogmixesComponent extends Component implements CrawlConstants
{
    /**
     *  Used to support requests related to posting, editing, modifying,
     *  and deleting group feed items.
     *
     *  @return array $data fields to be used by GroupfeedElement
     */
    function groupFeeds()
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $user_model = $parent->model("user");
        $data["ELEMENT"] = "groupfeed";
        $data['SCRIPT'] = "";
        $user_id = $_SESSION['USER_ID'];
        $username = $user_model->getUsername($user_id);
        if(isset($_REQUEST['num'])) {
            $results_per_page = $this->clean($_REQUEST['num'], "int");
        } else if(isset($_SESSION['MAX_PAGES_TO_SHOW']) ) {
            $results_per_page = $_SESSION['MAX_PAGES_TO_SHOW'];
        } else {
            $results_per_page = NUM_RESULTS_PER_PAGE;
        }
        if(isset($_REQUEST['limit'])) {
            $limit = $parent->clean($_REQUEST['limit'], "int");
        } else {
            $limit = 0;
        }
        if(isset($_SESSION['OPEN_IN_TABS'])) {
            $data['OPEN_IN_TABS'] = $_SESSION['OPEN_IN_TABS'];
        } else {
            $data['OPEN_IN_TABS'] = false;
        }
        $clean_array = array( "title" => "string", "description" => "string",
            "just_group_id" => "int", "just_thread" => "int",
            "just_user_id" => "int");
        foreach($clean_array as $field => $type) {
            $$field = ($type == "string") ? "" : 0;
            if(isset($_REQUEST[$field])) {
                $$field = $parent->clean($_REQUEST[$field], $type);
            }
        }
        $possible_arguments = array("addcomment", "deletepost", "newthread",
            "updatepost", "status");
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "addcomment":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['parent_id']) ||
                        !isset($_REQUEST['group_id'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_comment_error').
                            "</h1>');";
                        break;
                    }
                    if(!$description) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_no_comment').
                            "</h1>');";
                        break;
                    }
                    $parent_id = $parent->clean($_REQUEST['parent_id'], "int");
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group =
                        $group_model->getGroupById($group_id,
                        $user_id);
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        $group["MEMBER_ACCESS"] != GROUP_READ_WRITE &&
                        $user_id != ROOT_ID)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_no_post_access').
                            "</h1>');";
                        break;
                    }
                    if($parent_id >= 0) {
                        $parent_item = $group_model->getGroupItem($parent_id);
                        if(!$parent_item) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('blogmixes_component_no_post_access').
                                "</h1>');";
                            break;
                        }
                    } else {
                        $parent_item = array(
                            'TITLE' => tl('blogmixes_component_join_group',
                                $username, $group['GROUP_NAME']),
                            'DESCRIPTION' =>
                                tl('blogmixes_component_join_group_detail',
                                    date("r", $group['JOIN_DATE']),
                                    $group['GROUP_NAME']),
                            'ID' => -$group_id,
                            'PARENT_ID' => -$group_id,
                            'GROUP_ID' => $group_id
                        );
                    }
                    $title = "-- ".$parent_item['TITLE'];
                    $group_model->addGroupItem($parent_item["ID"],
                        $group_id, $user_id, $title, $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_comment_added'). "</h1>');";
                break;
                case "deletepost":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['post_id'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_delete_error').
                            "</h1>');";
                        break;
                    }
                    $post_id =$parent->clean($_REQUEST['post_id'], "int");
                    $success=$group_model->deleteGroupItem($post_id, $user_id);
                    if($success) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_item_deleted').
                            "</h1>');";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_no_item_deleted').
                            "</h1>');";
                    }
                break;
                case "newthread":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['group_id'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_comment_error').
                            "</h1>');";
                        break;
                    }
                    $group_id =$parent->clean($_REQUEST['group_id'], "int");
                    if(!$description || !$title) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_need_title_description').
                            "</h1>');";
                        break;
                    }
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group =
                        $group_model->getGroupById($group_id,
                        $user_id);
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        $group["MEMBER_ACCESS"] != GROUP_READ_WRITE &&
                        $user_id != ROOT_ID)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_no_post_access').
                            "</h1>');";
                        break;
                    }
                    $group_model->addGroupItem(0,
                        $group_id, $user_id, $title, $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_thread_created'). "</h1>');";
                break;
                case "status":
                    $data['REFRESH'] = "feedstatus";
                break;
                case "updatepost":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['post_id'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_comment_error').
                            "</h1>');";
                        break;
                    }
                    if(!$description || !$title) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_need_title_description').
                            "</h1>');";
                        break;
                    }
                    $post_id =$parent->clean($_REQUEST['post_id'], "int");
                    $items = $group_model->getGroupItems(0, 1, array(
                        array("post_id", "=", $post_id, "")), $user_id);
                    if(isset($items[0])) {
                        $item = $items[0];
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_no_update_access').
                            "</h1>');";
                        break;
                    }
                    $group_id = $item['GROUP_ID'];
                    $group =  $group_model->getGroupById($group_id, $user_id);
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        $group["MEMBER_ACCESS"] != GROUP_READ_WRITE &&
                        $user_id != ROOT_ID)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_no_update_access').
                            "</h1>')";;
                        break;
                    }
                    $group_model->updateGroupItem($post_id, $title,
                        $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_post_updated'). "</h1>');";
                break;
            }
        }
        $groups_count = 0;
        $page = array();
        if(!$just_user_id) {
            $search_array = array(
                array("group_id", "=", max(-$just_thread, $just_group_id), ""),
                array("access", "!=", GROUP_PRIVATE, ""),
                array("status", "=", ACTIVE_STATUS, ""),
                array("join_date", "=","", "DESC"));
            $groups_count = $group_model->getGroupsCount($search_array,
                $user_id);
            $groups = $group_model->getGroups(0, $limit + $results_per_page,
                $search_array, $user_id);
            $pages = array();
            foreach($groups as $group) {
                $page = array();
                $page[self::TITLE] = tl('blogmixes_component_join_group',
                    $username, $group['GROUP_NAME']);
                $page[self::DESCRIPTION] =
                    tl('blogmixes_component_join_group_detail',
                        date("r", $group['JOIN_DATE']), $group['GROUP_NAME']);
                $page['ID'] = -$group['GROUP_ID'];
                $page['PARENT_ID'] = -$group['GROUP_ID'];
                $page['USER_NAME'] = "";
                $page['USER_ID'] = "";
                $page['GROUP_ID'] = $group['GROUP_ID'];
                $page[self::SOURCE_NAME] = $group['GROUP_NAME'];
                $page['MEMBER_ACCESS'] = $group['MEMBER_ACCESS'];
                if($group['OWNER_ID'] == $user_id || $user_id == ROOT_ID) {
                    $page['MEMBER_ACCESS'] = GROUP_READ_WRITE;
                }
                $page['PUBDATE'] = $group['JOIN_DATE'];
                $pages[$group['JOIN_DATE']] = $page;
            }
        }
        $search_array = array(
            array("parent_id", "=", $just_thread, ""),
            array("group_id", "=", $just_group_id, ""),
            array("user_id", "=", $just_user_id, ""),
            array('pub_date', "=", "", "DESC"));
        $item_count = $group_model->getGroupItemCount($search_array, $user_id);
        $group_items = $group_model->getGroupItems(0,
            $limit + $results_per_page, $search_array, $user_id);
        $recent_found = false;
        $time = time();
        foreach($group_items as $item) {
            $page = $item;
            $page[self::TITLE] = $page['TITLE'];
            unset($page['TITLE']);
            $description = $page['DESCRIPTION'];
            preg_match_all("/\[\[([^\:\n]+)\:mix(\d+)\]\]/", $description,
                $matches);
            $num_matches = count($matches[0]);
            for($i = 0; $i < $num_matches; $i++) {
                $match = preg_quote($matches[0][$i]);
                $match = str_replace("@","\@", $match);
                $replace = "<a href='?c=admin&amp;a=mixCrawls".
                    "&amp;arg=importmix&amp;".CSRF_TOKEN."=".
                    $parent->generateCSRFToken($user_id).
                    "&amp;timestamp={$matches[2][$i]}'>".
                    $matches[1][$i]."</a>";
                $description = preg_replace("@".$match."@u", $replace,
                    $description);
                $page["NO_EDIT"] = true;
            }
            $page[self::DESCRIPTION] = $description;
            unset($page['DESCRIPTION']);
            $page[self::SOURCE_NAME] = $page['GROUP_NAME'];
            unset($page['GROUP_NAME']);
            if($item['OWNER_ID'] == $user_id || $user_id == ROOT_ID) {
                $page['MEMBER_ACCESS'] = GROUP_READ_WRITE;
            }
            if(!$recent_found && $time - $item["PUBDATE"] < 
                5 * self::ONE_MINUTE) {
                $recent_found = true;
                $data['SCRIPT'] .= 'doUpdate();';
            }
            $pages[$item["PUBDATE"]] = $page;
        }
        krsort($pages);
        $data['SUBTITLE'] = "";
        if($just_thread != "" && isset($page[self::TITLE])) {
            $title = $page[self::TITLE];
            $data['SUBTITLE'] = trim($title, "\- \t\n\r\0\x0B");
            $data['ADD_PAGING_QUERY'] = "&amp;just_thread=$just_thread";
        }
        if($just_group_id && isset($page[self::SOURCE_NAME])) {
            $data['SUBTITLE'] = $page[self::SOURCE_NAME];
            $data['ADD_PAGING_QUERY'] = "&amp;just_group_id=$just_group_id";
        }
        if($just_user_id && isset($page["USER_NAME"])) {
            $data['SUBTITLE'] = $page["USER_NAME"];
            $data['ADD_PAGING_QUERY'] = "&amp;just_user_id=$just_user_id";
        }
        $pages = array_slice($pages, $limit , $results_per_page - 1);
        $data['TOTAL_ROWS'] = $item_count + $groups_count;
        $data['LIMIT'] = $limit;
        $data['RESULTS_PER_PAGE'] = $results_per_page;
        $data['PAGES'] = $pages;
        $data['PAGING_QUERY'] = "?c=admin&amp;a=groupFeeds";
        return $data;

    }


    /**
     * Handles admin request related to the crawl mix activity
     *
     * The crawl mix activity allows a user to create/edit crawl mixes:
     * weighted combinations of search indexes
     *
     * @return array $data info about available crawl mixes and changes to them
     *      as well as any messages about the success or failure of a
     *      sub activity.
     */
    function mixCrawls()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $user_model = $parent->model("user");
        $possible_arguments = array(
            "createmix", "deletemix", "editmix", "index", "importmix",
            "search", "sharemix");
        $data['COMPARISON_TYPES'] = array(
            "=" => tl('accountaccess_component_equal'),
            "!=" => tl('accountaccess_component_not_equal'),
            "CONTAINS" => tl('accountaccess_component_contains'),
            "BEGINS WITH" => tl('accountaccess_component_begins_with'),
            "ENDS WITH" => tl('accountaccess_component_ends_with'),
        );
        $data['SORT_TYPES'] = array(
            "NONE" => tl('accountaccess_component_no_sort'),
            "ASC" => tl('accountaccess_component_sort_ascending'),
            "DESC" => tl('accountaccess_component_sort_descending'),
        );
        $data["ELEMENT"] = "mixcrawls";
        $user_id = $_SESSION['USER_ID'];

        $data['mix_default'] = 0;
        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if($num_machines <  1 || ($num_machines ==  1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = NULL;
        }
        $crawls = $crawl_model->getCrawlList(false, true, $machine_urls);
        $data['available_crawls'][0] = tl('blogmixes_component_select_crawl');
        $data['available_crawls'][1] = tl('blogmixes_component_default_crawl');
        $data['SCRIPT'] = "c = [];c[0]='".
            tl('blogmixes_component_select_crawl')."';";
        $data['SCRIPT'] .= "c[1]='".
            tl('blogmixes_component_default_crawl')."';";
        foreach($crawls as $crawl) {
            $data['available_crawls'][$crawl['CRAWL_TIME']] =
                $crawl['DESCRIPTION'];
            $data['SCRIPT'] .= 'c['.$crawl['CRAWL_TIME'].']="'.
                $crawl['DESCRIPTION'].'";';
        }
        $search_array = array();
        $can_manage_crawls = $user_model->isAllowedUserActivity(
                $_SESSION['USER_ID'], "manageCrawls");
        $data['PAGING'] = "";
        $data['FORM_TYPE'] = "";
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "createmix":
                    $mix['TIMESTAMP'] = time();
                    if(isset($_REQUEST['NAME'])) {
                        $mix['NAME'] = $parent->clean($_REQUEST['NAME'],
                            'string');
                    } else {
                        $mix['NAME'] = tl('blogmixes_component_unnamed');
                    }
                    $mix['FRAGMENTS'] = array();
                    $mix['OWNER_ID'] = $user_id;
                    $mix['PARENT'] = -1;
                    $crawl_model->setCrawlMix($mix);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_mix_created')."</h1>');";
                break;
                case "deletemix":
                    if(!isset($_REQUEST['timestamp'])||
                        !$crawl_model->isMixOwner($_REQUEST['timestamp'],
                            $user_id)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_mix_invalid_timestamp').
                            "</h1>');";
                        return $data;
                    }
                    $crawl_model->deleteCrawlMix($_REQUEST['timestamp']);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_mix_deleted')."</h1>');";
                break;
                case "editmix":
                    //$data passed by reference
                    $this->editMix($data);
                break;
                case "importmix":
                    $import_success = true;
                    if(!isset($_REQUEST['timestamp'])) {
                        $import_success = false;
                    }
                    $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
                    $mix = $crawl_model->getCrawlMix($timestamp);
                    if(!$mix) {
                        $import_success = false;
                    }
                    if(!$import_success) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_mix_doesnt_exists').
                            "</h1>');";
                        return $data;
                    }
                    $mix['PARENT'] = $mix['TIMESTAMP'];
                    $mix['OWNER_ID'] = $user_id;
                    $mix['TIMESTAMP'] = time();
                    $crawl_model->setCrawlMix($mix);

                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_mix_imported')."</h1>');";
                break;
                case "index":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_set_index')."</h1>');";
                    $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
                    if($can_manage_crawls) {
                        $crawl_model->setCurrentIndexDatabaseName(
                            $timestamp);
                    } else {
                        $_SESSION['its'] = $timestamp;
                        $user_model->setUserSession($user_id, $_SESSION);
                    }
                break;
                case "search":
                    $data['FORM_TYPE'] = "search";
                    $comparison_fields = array('name');
                    $paging = "";
                    foreach($comparison_fields as $comparison_start) {
                        $comparison = $comparison_start."_comparison";
                        $data[$comparison] = (isset($_REQUEST[$comparison]) &&
                            isset($data['COMPARISON_TYPES'][
                            $_REQUEST[$comparison]])) ?$_REQUEST[$comparison] :
                            "=";
                        $paging .= "&amp;$comparison=".
                            urlencode($data[$comparison]);
                    }
                    foreach($comparison_fields as $sort_start) {
                        $sort = $sort_start."_sort";
                        $data[$sort] = (isset($_REQUEST[$sort]) &&
                            isset($data['SORT_TYPES'][
                            $_REQUEST[$sort]])) ?$_REQUEST[$sort] :
                            "NONE";
                        $paging .= "&amp;$sort=".urlencode($data[$sort]);
                    }
                    $search_array = array();
                    foreach($comparison_fields as $field) {
                        $field_name = $field;
                        $field_comparison = $field."_comparison";
                        $field_sort = $field."_sort";
                        $data[$field_name] = (isset($_REQUEST[$field_name])) ?
                            $parent->clean($_REQUEST[$field_name], "string") :
                            "";
                        $search_array[] = array($field,
                            $data[$field_comparison], $data[$field_name],
                            $data[$field_sort]);
                        $paging .= "&amp;$field_name=".
                            urlencode($data[$field_name]);
                    }
                    $data['PAGING'] = $paging;
                break;
                case "sharemix":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['group_name'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_comment_error').
                            "</h1>');";
                        break;
                    }
                    if(!isset($_REQUEST['timestamp']) ||
                        !$crawl_model->isMixOwner($_REQUEST['timestamp'],
                            $user_id)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_invalid_timestamp').
                            "</h1>');";
                        break;
                    }
                    $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
                    $group_model = $parent->model("group");
                    $group_name =
                        $parent->clean($_REQUEST['group_name'], "string");
                    $group_id = $group_model->getGroupId($group_name);
                    $group = NULL;
                    if($group_id) {
                        $group =
                            $group_model->getGroupById($group_id,
                            $user_id);
                    }
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        $group["MEMBER_ACCESS"] != GROUP_READ_WRITE &&
                        $user_id != ROOT_ID)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_no_post_access').
                            "</h1>');";
                        break;
                    }
                    $user_name = $user_model->getUserName($user_id);
                    $title = tl('blogmixes_component_share_title',
                        $user_name);
                    $description = tl('blogmixes_component_share_description',
                        $user_name,"[[{$mix['NAME']}:mix{$mix['TIMESTAMP']}]]");
                    $group_model->addGroupItem(0,
                        $group_id, $user_id, $title, $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_thread_created'). "</h1>');";
                break;
            }
        }
        $num_mixes = $crawl_model->getMixCount($search_array);
        $data['NUM_TOTAL'] = $num_mixes;
        $num_show = (isset($_REQUEST['num_show']) &&
            isset($parent->view("admin")->helper("pagingtable")->show_choices[
                $_REQUEST['num_show']])) ? $_REQUEST['num_show'] : 50;
        $data['num_show'] = $num_show;
        $data['START_ROW'] = 0;
        if(isset($_REQUEST['start_row'])) {
            $data['START_ROW'] = min(
                max(0, $parent->clean($_REQUEST['start_row'],"int")),
                $num_mixes);
        }
        $data['END_ROW'] = min($data['START_ROW'] + $num_show, $num_mixes);
        if(isset($_REQUEST['start_row'])) {
            $data['END_ROW'] = max($data['START_ROW'],
                min($parent->clean($_REQUEST['end_row'],"int"), $num_mixes));
        }
        $data["available_mixes"] = $crawl_model->getMixes($data['START_ROW'],
            $num_show, true, $search_array);
        $data['NEXT_START'] = $data['END_ROW'];
        $data['NEXT_END'] = min($data['NEXT_START'] + $num_show, $num_mixes);
        $data['PREV_START'] = max(0, $data['START_ROW'] - $num_show);
        $data['PREV_END'] = $data['START_ROW'];

        if(!$can_manage_crawls && isset($_SESSION['its'])) {
            $crawl_time = $_SESSION['its'];
        } else {
            $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
        }
        if(isset($crawl_time) ) {
            $data['CURRENT_INDEX'] = (int)$crawl_time;
        } else {
            $data['CURRENT_INDEX'] = -1;
        }

        return $data;
    }

    /**
     * Handles admin request related to the editing a crawl mix activity
     *
     * @param array $data info about the fragments and their contents for a
     *      particular crawl mix (changed by this method)
     */
    function editMix(&$data)
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $data["leftorright"] =
            (getLocaleDirection() == 'ltr') ? "right": "left";
        $data["ELEMENT"] = "editmix";
        $user_id = $_SESSION['USER_ID'];

        $mix = array();
        $timestamp = 0;
        if(isset($_REQUEST['timestamp'])) {
            $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
        } else if (isset($_REQUEST['mix']['TIMESTAMP'])) {
            $timestamp = $parent->clean($_REQUEST['mix']['TIMESTAMP'], "int");
        }
        if(!$crawl_model->isMixOwner($timestamp, $user_id)) {
            $data["ELEMENT"] = "mixcrawls";
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('blogmixes_component_mix_not_owner').
                "</h1>');";
            return;
        }
        $mix = $crawl_model->getCrawlMix($timestamp);
        $owner_id = $mix['OWNER_ID'];
        $parent_id = $mix['PARENT'];
        $data['MIX'] = $mix;
        $data['INCLUDE_SCRIPTS'] = array("mix");

        //set up an array of translation for javascript-land
        $data['SCRIPT'] .= "tl = {".
            'blogmixes_component_add_crawls:"'.
                tl('blogmixes_component_add_crawls') .
            '",' . 'blogmixes_component_num_results:"'.
                tl('blogmixes_component_num_results').'",'.
            'blogmixes_component_del_frag:"'.
                tl('blogmixes_component_del_frag').'",'.
            'blogmixes_component_weight:"'.
                tl('blogmixes_component_weight').'",'.
            'blogmixes_component_name:"'.tl('blogmixes_component_name').'",'.
            'blogmixes_component_add_keywords:"'.
                tl('blogmixes_component_add_keywords').'",'.
            'blogmixes_component_actions:"'.
                tl('blogmixes_component_actions').'",'.
            'blogmixes_component_add_query:"'.
                tl('blogmixes_component_add_query').'",'.
            'blogmixes_component_delete:"'.tl('blogmixes_component_delete').'"'.
            '};';
        //clean and save the crawl mix sent from the browser
        if(isset($_REQUEST['update']) && $_REQUEST['update'] ==
            "update") {
            $mix = $_REQUEST['mix'];
            $mix['TIMESTAMP'] = $timestamp;
            $mix['OWNER_ID']= $owner_id;
            $mix['PARENT'] = $parent_id;
            $mix['NAME'] =$parent->clean($mix['NAME'],
                "string");
            $comp = array();
            $save_mix = false;
            if(isset($mix['FRAGMENTS'])) {
                if($mix['FRAGMENTS'] != NULL && count($mix['FRAGMENTS']) <
                    MAX_MIX_FRAGMENTS) {
                    foreach($mix['FRAGMENTS'] as $fragment_id=>$fragment_data) {
                        if(isset($fragment_data['RESULT_BOUND'])) {
                            $mix['FRAGMENTS'][$fragment_id]['RESULT_BOUND'] =
                                $parent->clean($fragment_data['RESULT_BOUND'],
                                    "int");
                        } else {
                            $mix['FRAGMENTS']['RESULT_BOUND'] = 0;
                        }
                        if(isset($fragment_data['COMPONENTS'])) {
                            $comp = array();
                            foreach($fragment_data['COMPONENTS'] as $component){
                                $row = array();
                                $row['CRAWL_TIMESTAMP'] =
                                    $parent->clean(
                                        $component['CRAWL_TIMESTAMP'], "int");
                                $row['WEIGHT'] = $parent->clean(
                                    $component['WEIGHT'], "float");
                                $row['KEYWORDS'] = $parent->clean(
                                    $component['KEYWORDS'],
                                    "string");
                                $comp[] =$row;
                            }
                            $mix['FRAGMENTS'][$fragment_id]['COMPONENTS']=$comp;
                        } else {
                            $mix['FRAGMENTS'][$fragment_id]['COMPONENTS'] =
                                array();
                        }
                    }
                    $save_mix = true;
                } else if(count($mix['FRAGMENTS']) >= MAX_MIX_FRAGMENTS) {
                    $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_too_many_fragments')."</h1>');";
                } else {
                    $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
                }
            } else {
                $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
            }
            if($save_mix) {
                $data['MIX'] = $mix;
                $crawl_model->setCrawlMix($mix);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('blogmixes_component_mix_saved')."</h1>');";
            }
        }

        $data['SCRIPT'] .= 'fragments = [';
        $not_first = "";
        foreach($mix['FRAGMENTS'] as $fragment_id => $fragment_data) {
            $data['SCRIPT'] .= $not_first.'{';
            $not_first= ",";
            if(isset($fragment_data['RESULT_BOUND'])) {
                $data['SCRIPT'] .= "num_results:".
                    $fragment_data['RESULT_BOUND'];
            } else {
                $data['SCRIPT'] .= "num_results:1 ";
            }
            $data['SCRIPT'] .= ", components:[";
            if(isset($fragment_data['COMPONENTS'])) {
                $comma = "";
                foreach($fragment_data['COMPONENTS'] as $component) {
                    $crawl_ts = $component['CRAWL_TIMESTAMP'];
                    $crawl_name = $data['available_crawls'][$crawl_ts];
                    $data['SCRIPT'] .= $comma." [$crawl_ts, '$crawl_name', ".
                        $component['WEIGHT'].", ";
                    $comma = ",";
                    $keywords = (isset($component['KEYWORDS'])) ?
                        $component['KEYWORDS'] : "";
                    $data['SCRIPT'] .= "'$keywords'] ";
                }
            }
            $data['SCRIPT'] .= "] }";
        }
        $data['SCRIPT'] .= ']; drawFragments();';
    }
}
