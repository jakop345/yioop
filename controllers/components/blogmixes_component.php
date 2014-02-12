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
     *
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
            "just_group_id" => "int", "just_thread" => "int");
        foreach($clean_array as $field => $type) {
            $$field = ($type == "string") ? "" : 0;
            if(isset($_REQUEST[$field])) {
                $$field = $parent->clean($_REQUEST[$field], $type);
            }
        }
        $possible_arguments = array("addcomment","newthread");
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
                            "</h1>')";
                        break;
                    }
                    if(!$description) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_no_comment').
                            "</h1>')";
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
                            "</h1>')";
                        break;
                    }
                    if($parent_id >= 0) {
                        $parent_item = $group_model->getGroupItem($parent_id);
                        if(!$parent_item) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('blogmixes_component_no_post_access').
                                "</h1>')";
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
                            'ID' => -1,
                            'PARENT_ID' => -1,
                            'GROUP_ID' => $group_id
                        );
                    }
                    $title = "-- ".$parent_item['TITLE'];
                    $group_model->addGroupItem($parent_item["ID"], 
                        $group_id, $title, $description);
                break;

                case "newthread":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['group_id'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_comment_error').
                            "</h1>')";
                        break;
                    }
                    if(!$description || !$title) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_need_title_description').
                            "</h1>')";
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
                            "</h1>')";
                        break;
                    }
                    $group_model->addGroupItem(0,
                        $group_id, $title, $description);
                break;
            }
        }
        $groups_count = 0;
        if(!$just_thread) {
            $search_array = array(
                array("group_id", "=", $just_group_id, ""),
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
                $page['ID'] = -1;
                $page['PARENT_ID'] = -1;
                $page['USER_NAME'] = "";
                $page['GROUP_ID'] = $group['GROUP_ID'];
                $page[self::SOURCE_NAME] = $group['GROUP_NAME'];
                $page['MEMBER_ACCESS'] = $group['MEMBER_ACCESS'];
                if($group['OWNER_ID'] == $user_id) {
                    $page['MEMBER_ACCESS'] = GROUP_READ_WRITE;
                }
                $pages[$group['JOIN_DATE']] = $page;
            }
        }
        $search_array = array(
            array("parent_id", "=", $just_thread, ""),
            array("group_id", "=", $just_group_id, ""),
            array('pub_date', "=", "", "DESC"));
        $item_count = $group_model->getGroupItemCount($search_array, $user_id);
        $group_items = $group_model->getGroupItems(0,
            $limit + $results_per_page, $search_array, $user_id);
        foreach($group_items as $item) {
            $page = $item;
            $page[self::TITLE] = $page['TITLE'];
            unset($page['TITLE']);
            $page[self::DESCRIPTION] = $page['DESCRIPTION'];
            unset($page['DESCRIPTION']);
            $page[self::SOURCE_NAME] = $page['GROUP_NAME'];
            unset($page['GROUP_NAME']);
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
        $possible_arguments = array(
            "createmix", "deletemix", "editmix", "index");

        $data["ELEMENT"] = "mixcrawls";

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
        $mixes = $crawl_model->getMixList(true);
        if(count($mixes) > 0 ) {
            $data['available_mixes']= $mixes;
            $mix_ids = array();
            foreach($mixes as $mix) {
                $mix_ids[] = $mix['MIX_TIMESTAMP'];
            }
        }

        $mix = array();
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "createmix":
                    $mix['MIX_TIMESTAMP'] = time();
                    if(isset($_REQUEST['MIX_NAME'])) {
                        $mix['MIX_NAME'] = $parent->clean($_REQUEST['MIX_NAME'],
                            'string');
                    } else {
                        $mix['MIX_NAME'] = tl('blogmixes_component_unnamed');
                    }
                    $mix['GROUPS'] = array();
                    $crawl_model->setCrawlMix($mix);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_mix_created')."</h1>');";

                case "editmix":
                    //$data passed by reference
                    $this->editMix($data, $mix_ids, $mix);
                break;

                case "index":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_set_index')."</h1>')";

                    $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
                    $crawl_model->setCurrentIndexDatabaseName(
                        $timestamp);
                break;

                case "deletemix":
                    if(!isset($_REQUEST['timestamp'])|| !isset($mix_ids) ||
                        !in_array($_REQUEST['timestamp'], $mix_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_mix_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $crawl_model->deleteCrawlMix($_REQUEST['timestamp']);
                    $data['available_mixes'] =
                        $crawl_model->getMixList(true);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_mix_deleted')."</h1>')";
                break;
            }
        }

        $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
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
     * @return array $data info about the groups and their contents for a
     *      particular crawl mix
     */
    function editMix(&$data, &$mix_ids, $mix)
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $data["leftorright"] =
            (getLocaleDirection() == 'ltr') ? "right": "left";
        $data["ELEMENT"] = "editmix";

        if(isset($_REQUEST['timestamp'])) {
            $mix = $crawl_model->getCrawlMix(
                $_REQUEST['timestamp']);
        }
        $data['MIX'] = $mix;
        $data['INCLUDE_SCRIPTS'] = array("mix");

        //set up an array of translation for javascript-land
        $data['SCRIPT'] .= "tl = {".
            'blogmixes_component_add_crawls:"'.
                tl('blogmixes_component_add_crawls') .
            '",' . 'blogmixes_component_num_results:"'.
                tl('blogmixes_component_num_results').'",'.
            'blogmixes_component_del_grp:"'.
                tl('blogmixes_component_del_grp').'",'.
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
            $mix['MIX_TIMESTAMP'] =
                $parent->clean($mix['MIX_TIMESTAMP'], "int");
            $mix['MIX_NAME'] =$parent->clean($mix['MIX_NAME'],
                "string");
            $comp = array();
            if(isset($mix['GROUPS'])) {

                if($mix['GROUPS'] != NULL) {
                    foreach($mix['GROUPS'] as $group_id => $group_data) {
                        if(isset($group_data['RESULT_BOUND'])) {
                            $mix['GROUPS'][$group_id]['RESULT_BOUND'] =
                                $parent->clean($group_data['RESULT_BOUND'],
                                    "int");
                        } else {
                            $mix['GROUPS']['RESULT_BOUND'] = 0;
                        }
                        if(isset($group_data['COMPONENTS'])) {
                            $comp = array();
                            foreach($group_data['COMPONENTS'] as $component) {
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
                            $mix['GROUPS'][$group_id]['COMPONENTS'] = $comp;
                        } else {
                            $mix['GROUPS'][$group_id]['COMPONENTS'] = array();
                        }
                    }
                } else {
                    $mix['COMPONENTS'] = array();
                }

            } else {
                $mix['GROUPS'] = $data['MIX']['GROUPS'];
            }

            $data['MIX'] = $mix;
            $crawl_model->setCrawlMix($mix);
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('blogmixes_component_mix_saved')."</h1>');";
        }

        $data['SCRIPT'] .= 'groups = [';
        $not_first = "";
        foreach($mix['GROUPS'] as $group_id => $group_data) {
            $data['SCRIPT'] .= $not_first.'{';
            $not_first= ",";
            if(isset($group_data['RESULT_BOUND'])) {
                $data['SCRIPT'] .= "num_results:".$group_data['RESULT_BOUND'];
            } else {
                $data['SCRIPT'] .= "num_results:1 ";
            }
            $data['SCRIPT'] .= ", components:[";
            if(isset($group_data['COMPONENTS'])) {
                $comma = "";
                foreach($group_data['COMPONENTS'] as $component) {
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
        $data['SCRIPT'] .= ']; drawGroups();';
    }
}
