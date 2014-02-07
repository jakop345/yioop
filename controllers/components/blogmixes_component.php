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
class BlogmixesComponent extends Component
{
    /**
     * Used to handle the blogs and pages activity.
     *
     * This activity allows users to create,edit, and delete blogs.
     * It allows users to add feeditems to blogs, edit and delete feeditems
     * Allows users to add groups to blogs and provides access control to blogs
     * @return array $data information about blogs in the system
     */
    function blogPages()
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $blogpage_model = $parent->model("blogpage");
        $signin_model = $parent->model("signin");
        $locale_model = $parent->model("locale");
        $data["ELEMENT"] = "blogpages";
        $data['SCRIPT'] = "";
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $user = $_SESSION['USER_ID'];
        $group_ids = array();
        if(isset($_REQUEST['selectgroup'])) {
            $select_group =
                 $parent->clean($_REQUEST['selectgroup'], "string");
                 $data['SELECT_GROUP'] = $select_group;
        } else {
            $select_group = "";
        }
        if($_SESSION['USER_ID'] == '1') {
             $groups = $group_model->getGroupList();
             $is_admin = true;
        } else {
            $is_admin = false;
            $groups =
                $group_model->getUserGroups($_SESSION['USER_ID']);
            foreach($groups as $group) {
                array_push($group_ids, $group['GROUP_ID']);
            }
        }
        $data['SOURCE_TYPES'] =
            array(-1 => tl('blogmixes_component_source_type'),
            "blog" => tl('blogmixes_component_blog'),
            "page" => tl('blogmixes_component_page'));
        $recent_blogs =
            $blogpage_model->recentBlog($user, $group_ids, $is_admin);
        $data['RECENT_BLOGS'] = $recent_blogs;
        $base_option = tl('blogmixes_component_select_groupname');
        $data['GROUP_NAMES'][-1] = $base_option;
        foreach($groups as $group) {
            $data['GROUP_NAMES'][$group['GROUP_ID']]= $group['GROUP_NAME'];

            $group_ids[] = $group['GROUP_ID'];
        }
        $possible_arguments = array("addblog", "searchblog", "deleteblog",
            "editblog","editdescription","updatedescription",
            "addblogentry","addfeeditem","deletefeed","updatefeed",
            "editfeed","updateblogusers","addbloggroup",
            "deletebloggroup");
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "addblog":
                    $source_type_flag = false;
                    if(isset($_REQUEST['sourcetype']) &&
                        in_array($_REQUEST['sourcetype'],
                        array_keys($data['SOURCE_TYPES']))) {
                        $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
                        $source_type_flag = true;
                    } else {
                        $data['SOURCE_TYPE'] = -1;
                    }
                    $locales = $locale_model->getLocaleList();
                    $data["LANGUAGES"] = array();
                    foreach($locales as $locale) {
                        $data["LANGUAGES"][$locale['LOCALE_TAG']] =
                        $locale['LOCALE_NAME'];
                    }
                    if(isset($_REQUEST['sourcelocaletag']) &&
                        in_array($_REQUEST['sourcelocaletag'],
                        array_keys($data["LANGUAGES"]))) {
                            $data['SOURCE_LOCALE_TAG'] =
                                $_REQUEST['sourcelocaletag'];
                    } else {
                        $data['SOURCE_LOCALE_TAG'] = DEFAULT_LOCALE;
                    }
                    $must_have =
                        array("title", "description", "sourcelocaletag");
                    $missing_must_have = false;
                    foreach ($must_have as $clean_me) {
                        $data[$clean_me] = (isset($_REQUEST[$clean_me])) ?
                            $parent->clean($_REQUEST[$clean_me], "string" ):"";
                        if(in_array($clean_me, $must_have)
                            && $data[$clean_me] == "" ) {
                            $missing_must_have = true;
                        }
                    }
                    if(!$source_type_flag || $missing_must_have) {
                        break;
                    }
                    if(isset($_SESSION['USER_ID'])) {
                        $user = $_SESSION['USER_ID'];
                    } else {
                        $user = $_SERVER['REMOTE_ADDR'];
                    }
                    if($data['SOURCE_TYPE'] == 'page'){

                    $groupid = $group_model->getGroupId($select_group);
                    if(isset($_REQUEST['selectgroup'])) {
                        $select_group =
                        $parent->clean($_REQUEST['selectgroup'], "string" );
                        $data['SELECT_GROUP'] = $select_group;
                    } else {
                        $select_group = "";
                    }
                    $data['SELECT_GROUP'] = $select_group;
                        $result = $blogpage_model->addPage(
                            $data['title'], $data['description'],
                            $data['SOURCE_TYPE'], $data['SOURCE_LOCALE_TAG'],
                            $user, $select_group);
                        if($result){
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_page_added').
                            "</h1>');";
                    }
                    }else{
                        $user = $_SESSION['USER_ID'];
                        $groupid = $group_model->getGroupId(
                            $select_group);
                        $data['SELECT_GROUP'] = $select_group;
                        $blogpage_model->addBlog(
                            $data['title'], $data['description'], $user,
                            $data['SOURCE_TYPE'], $data['sourcelocaletag'],
                            $select_group);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_blog_added').
                            "</h1>');";
                    }
                    $data["ELEMENT"] = "blogpages";
                    $recent_blogs =
                        $blogpage_model->recentBlog(
                        $user, $group_ids, $is_admin);
                    $data['RECENT_BLOGS'] = $recent_blogs;
                break;

                case "searchblog":
                    if(!isset($_REQUEST['title'])) { break; }

                    $data["ELEMENT"] = "blogpages";
                    $data['SELECT_GROUP'] = "";
                    $data['description'] = "";
                    $is_blogs_empty = false;
                    $data['title'] = $parent->clean($_REQUEST['title'],
                        "string");
                    if($data['title'] != "") {
                        $blogs =
                            $blogpage_model->searchBlog(
                                $data['title'],
                                $user, $group_ids, $is_admin);
                        $data['BLOGS'] = $blogs;
                        if(empty($blogs)){ 
                            $is_blogs_empty = true;
                        }
                    } else {
                        $is_blogs_empty = true;
                    }
                    if($is_blogs_empty) {
                        $data["ELEMENT"] = "createblogpages";
                        $source_type_flag = false;
                        if(isset($_REQUEST['sourcetype']) &&
                            in_array($_REQUEST['sourcetype'],
                            array_keys($data['SOURCE_TYPES']))) {
                            $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
                            $source_type_flag = true;
                        } else {
                            $data['SOURCE_TYPE'] = -1;
                        }
                        $locales = $locale_model->getLocaleList();
                        $data["LANGUAGES"] = array();
                        foreach($locales as $locale) {
                            $data["LANGUAGES"][$locale['LOCALE_TAG']] =
                                $locale['LOCALE_NAME'];
                        }
                        if(isset($_REQUEST['sourcelocaletag']) && in_array(
                            $_REQUEST['sourcelocaletag'],
                            array_keys($data["LANGUAGES"]))
                            ) {
                            $data['SOURCE_LOCALE_TAG'] =
                                $_REQUEST['sourcelocaletag'];
                        } else {
                            $data['SOURCE_LOCALE_TAG'] = DEFAULT_LOCALE;
                        }
                    }
                break;

                case "deleteblog":
                    if(!isset($_REQUEST['id'])) { break; }

                    $timestamp = $parent->clean($_REQUEST['id'], "string" );
                    $title = "";
                    if(isset($_REQUEST['title'])) {
                        $title = $parent->clean($_REQUEST['title'], "string" );
                    }
                    $blogpage_model->deleteBlog($timestamp, $title,
                        $user);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_blog_deleted').
                        "</h1>');";
                    $data['RECENT_BLOGS'] =  $blogpage_model->recentBlog(
                        $user, $group_ids, $is_admin);
                break;

                case "editblog":
                    if(!isset($_REQUEST['id'])) { break; }

                    $timestamp = $parent->clean($_REQUEST['id'], "string");
                    $data["ELEMENT"] = "editblogpages";
                    $source_type_flag = false;
                    if(isset($_REQUEST['sourcetype']) && in_array(
                        $_REQUEST['sourcetype'],
                        array_keys($data['SOURCE_TYPES']))
                        ) {
                        $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
                        $source_type_flag = true;
                    } else {
                        $data['SOURCE_TYPE'] = -1;
                    }
                    $locales = $locale_model->getLocaleList();
                    $data["LANGUAGES"] = array();
                    foreach($locales as $locale) {
                        $data["LANGUAGES"][$locale['LOCALE_TAG']]
                            = $locale['LOCALE_NAME'];
                    }
                    if(isset($_REQUEST['sourcelocaletag']) && in_array(
                        $_REQUEST['sourcelocaletag'],
                        array_keys($data["LANGUAGES"]))) {
                        $data['SOURCE_LOCALE_TAG'] =
                            $_REQUEST['sourcelocaletag'];
                    } else {
                        $data['SOURCE_LOCALE_TAG'] = DEFAULT_LOCALE;
                    }
                    $edit_blogs= $blogpage_model->editBlog($timestamp);
                    $blog_users =
                        $blogpage_model->getBlogUsers($edit_blogs[0]);
                    $edit_blogs[0]['BLOG_USERS'] = $blog_users;
                    $data['EDIT_BLOGS'] = $edit_blogs[0];
                    $title = $edit_blogs['0']['NAME'];
                    if(isset($_SESSION['USER_ID'])) {
                        $user = $_SESSION['USER_ID'];
                    } else {
                        $user = $_SERVER['REMOTE_ADDR'];
                    }
                    $user = $_SESSION['USER_ID'];
                    $feed_items = $blogpage_model->getFeed($title, $user);
                    $data['FEED_ITEMS'] = $feed_items;
                    if(isset($_SESSION['USER_ID'])) {
                        $username = $signin_model->getUserName(
                            $_SESSION['USER_ID']);
                        $data['USER_NAME'] = $username;
                        $owner_id = $blogpage_model->
                            getBlogOwner($timestamp);
                        if($owner_id == $user || $user == '1'){
                            $data['IS_OWNER'] = true;
                        }
                    }
                break;

                case "editdescription":
                    if(!isset($_REQUEST['id'])) { break; }
                    $timestamp = $parent->clean($_REQUEST['id'], "string" );
                    $data["ELEMENT"] = "editblogpages";
                    $source_type_flag = false;
                    if(isset($_REQUEST['sourcetype']) && in_array(
                       $_REQUEST['sourcetype'],
                       array_keys($data['SOURCE_TYPES']))
                       ) {
                       $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
                       $source_type_flag = true;
                    } else {
                        $data['SOURCE_TYPE'] = -1;
                    }
                    $locales = $locale_model->getLocaleList();
                    $data["LANGUAGES"] = array();
                    foreach($locales as $locale) {
                        $data["LANGUAGES"][$locale['LOCALE_TAG']] =
                            $locale['LOCALE_NAME'];
                    }
                    if(isset($_REQUEST['sourcelocaletag']) && in_array(
                        $_REQUEST['sourcelocaletag'],
                        array_keys($data["LANGUAGES"]))) {
                        $data['SOURCE_LOCALE_TAG'] =
                        $_REQUEST['sourcelocaletag'];
                    } else {
                        $data['SOURCE_LOCALE_TAG'] = DEFAULT_LOCALE;
                    }
                    $blog_group = $blogpage_model->
                        getBlogGroup($timestamp);
                    $data['BLOG_GROUP'] = $blog_group;
                    $edit_blogs = $blogpage_model->editBlog($timestamp);
                    $data['EDIT_BLOGS'] = $edit_blogs[0];
                    $data['IS_EDIT_DESC'] = true;
                break;

                case "updatedescription":
                    if(isset($_REQUEST['id'])) {
                        $timestamp = $parent->clean($_REQUEST['id'], "string" );
                    }
                    if(isset($_REQUEST['description'])) {
                        $description =
                            $parent->clean($_REQUEST['description'], "string" );
                    }
                    if(isset($_REQUEST['title'])) {
                        $title = $parent->clean($_REQUEST['title'], "string" );
                    }

                    $edit_blogs = $blogpage_model->editDescription($timestamp,
                        $title, $description);
                    if(!empty($edit_blogs)){
                        $data['EDIT_BLOGS'] = $edit_blogs[0];
                    }
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_blog_updated') . "</h1>');";
                    $data["ELEMENT"] = "blogpages";
                    $recent_blogs =
                    $blogpage_model->recentBlog($user, $group_ids, $is_admin);
                    $data['RECENT_BLOGS'] = $recent_blogs;
                break;

                case "addblogentry":
                    if(!isset($_REQUEST['id'])) { break; }
                    $timestamp = $parent->clean($_REQUEST['id'], "string" );
                    $data["ELEMENT"] = "editblogpages";
                        $source_type_flag = false;
                    if(isset($_REQUEST['sourcetype']) && in_array(
                        $_REQUEST['sourcetype'],
                        array_keys($data['SOURCE_TYPES']) )
                        ) {
                        $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
                        $source_type_flag = true;
                    } else {
                        $data['SOURCE_TYPE'] = -1;
                    }
                    $locales = $locale_model->getLocaleList();
                    $data["LANGUAGES"] = array();
                    foreach($locales as $locale) {
                        $data["LANGUAGES"][$locale['LOCALE_TAG']] =
                        $locale['LOCALE_NAME'];
                    }
                    if(isset($_REQUEST['sourcelocaletag']) &&
                        in_array($_REQUEST['sourcelocaletag'],
                        array_keys($data["LANGUAGES"]))) {
                            $data['SOURCE_LOCALE_TAG'] =
                            $_REQUEST['sourcelocaletag'];
                    } else {
                       $data['SOURCE_LOCALE_TAG'] = DEFAULT_LOCALE;
                    }
                    $edit_blogs = $blogpage_model->editBlog($timestamp);
                    $data['EDIT_BLOGS'] = $edit_blogs[0];
                    $data['IS_ADD_BLOG'] = true;
                break;

                case "addfeeditem":
                    if(isset($_SESSION['USER_ID'])) {
                        $user = $_SESSION['USER_ID'];
                    } else {
                        $user = $_SERVER['REMOTE_ADDR'];
                    }
                    $user = $_SESSION['USER_ID'];
                    if(isset($_REQUEST['id'])) {
                        $timestamp = $parent->clean($_REQUEST['id'], "string" );
                    }
                    $title = "";
                    if(isset($_REQUEST['title'])) {
                        $title = $parent->clean($_REQUEST['title'], "string" );
                    }
                    if(isset($_REQUEST['description'])) {
                        $description =
                            $parent->clean($_REQUEST['description'], "string" );
                    }
                    if(isset($_REQUEST['title_entry'])) { ;
                        $title_entry =
                            $parent->clean($_REQUEST['title_entry'], "string" );
                    }
                    $feed_items= $blogpage_model->addEntry($timestamp,
                        $title_entry, $description, $title, $user);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_feed_added').
                        "</h1>');";
                break;

                case "editfeed":
                    if(!isset($_REQUEST['id'])) { break; }

                    $timestamp = $parent->clean($_REQUEST['id'], "string" );
                    $data["ELEMENT"] = "editblogpages";
                    $source_type_flag = false;
                    if(isset($_REQUEST['sourcetype']) &&
                        in_array($_REQUEST['sourcetype'],
                        array_keys($data['SOURCE_TYPES']))) {
                            $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
                            $source_type_flag = true;
                    } else {
                        $data['SOURCE_TYPE'] = -1;
                    }
                    $locales = $locale_model->getLocaleList();
                    $data["LANGUAGES"] = array();
                    foreach($locales as $locale) {
                        $data["LANGUAGES"][$locale['LOCALE_TAG']] =
                        $locale['LOCALE_NAME'];
                    }
                    if(isset($_REQUEST['sourcelocaletag']) &&
                        in_array($_REQUEST['sourcelocaletag'],
                        array_keys($data["LANGUAGES"]))) {
                            $data['SOURCE_LOCALE_TAG'] =
                            $_REQUEST['sourcelocaletag'];
                    } else {
                        $data['SOURCE_LOCALE_TAG'] = DEFAULT_LOCALE;
                    }

                    $edit_blogs = $blogpage_model->editBlog($timestamp);
                    $data['EDIT_BLOGS'] = $edit_blogs[0];
                    $data['IS_EDIT_FEED'] = true;
                    if(isset($_REQUEST['fid'])) {
                        $guid = $parent->clean($_REQUEST['fid'], "string" );
                    }
                    $feed_items = $blogpage_model->getFeedByGUID($guid);
                    $data['FEED_ITEMS'] = $feed_items;
                    $owner_id = $blogpage_model->getBlogOwner($timestamp);
                    if($owner_id == $user || $user == '1'){
                        $data['IS_OWNER'] = true;
                    }
                break;

                case "updatefeed":
                    if(isset($_REQUEST['id'])) {
                        $timestamp = $parent->clean($_REQUEST['id'], "string" );
                    }
                    if(isset($_REQUEST['description'])) {
                        $description =
                            $parent->clean($_REQUEST['description'], "string" );
                    }
                    if(isset($_REQUEST['title'])) {
                        $title = $parent->clean($_REQUEST['title'], "string" );
                    }
                    if(isset($_REQUEST['fid'])) {
                        $guid = $parent->clean($_REQUEST['fid'], "string" );
                    }
                    $update_feeds= $blogpage_model->
                        updateFeed($guid,$title, $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_feed_updated').
                        "</h1>');";
                break;

                case "deletefeed":
                    if(isset($_REQUEST['id'])) {
                        $guid = $parent->clean($_REQUEST['id'], "string" );
                        $blogpage_model->deleteFeed($guid);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('blogmixes_component_feed_deleted').
                            "</h1>');";
                    }
                break;

                case "updateblogusers":
                    if(isset($_REQUEST['selectuser'])) {
                        $select_user =
                        $parent->clean($_REQUEST['selectuser'], "string" );
                    } else {
                        $select_user = "";
                    }
                    if($select_user != "" ) {
                        $parent= $signin_model->getUserId($select_user);
                        $data['SELECT_USER'] = $select_user;

                    }
                    if(isset($_REQUEST['blogName'])) {
                        $blog_name =
                            $parent->clean($_REQUEST['blogName'], "string");
                    } else {
                        $blog_name = "";
                    }
                    $blogpage_model->updateBlogUsers($select_user, $blog_name);
                    $recent_blogs =
                    $blogpage_model->recentBlog($user, $group_ids, $is_admin);
                    $data['RECENT_BLOGS'] = $recent_blogs;
                break;

                case "addbloggroup":
                    if(isset($_REQUEST['id'])) {
                        $timestamp = $parent->clean($_REQUEST['id'], "string" );
                    }
                    if(isset($_REQUEST['selectgroup'])) {
                        $select_group =
                        $parent->clean($_REQUEST['selectgroup'], "string" );
                        $data['SELECT_GROUP'] = $select_group;
                    } else {
                        $select_group = "";
                    }
                    $groupid = $group_model->getGroupId($select_group);
                    $data['SELECT_GROUP'] = $select_group;
                    $blogpage_model->addGroup($timestamp,$select_group);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_group_added').
                        "</h1>');";
                break;

                case "deletebloggroup":
                    if(isset($_REQUEST['id'])) {
                        $timestamp = $parent->clean($_REQUEST['id'], "string" );
                    }
                    if(isset($_REQUEST['gid'])) {
                        $groupid = $parent->clean($_REQUEST['gid'], "string" );
                    }
                    $blogpage_model->deleteGroup($timestamp,$groupid);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('blogmixes_component_group_deleted').
                        "</h1>');";
                break;
            }
        }
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
