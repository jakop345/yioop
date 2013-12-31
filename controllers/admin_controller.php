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

class AdminController extends Controller implements CrawlConstants
{
    /**
     * Says which views to load for this controller
     * admin is the main one, signin has the login screen crawlstatus
     * is used to see how many pages crawled by the current crawl
     * @var array
     */
    var $views = array("admin","signin","crawlstatus", "machinestatus");
    /**
     * Says which models to load for this controller.
     * @var array
     */
    var $models = array(
        "signin", "user", "activity", "crawl", "role", "locale", "profile",
        "searchfilters", "source", "machine", "cron", "group", "blogpage");
    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to
     * @var array
     */
    var $activities = array("manageCrawls", "pageOptions",
        "manageClassifiers","resultsEditor", "manageMachines", "manageLocales",
        "crawlStatus","mixCrawls","machineStatus","searchSources","configure",
        "blogPages");
    /**
     *  @var array
     */
    var $components = array("accountaccess");

    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to
     * @var array
     */
    var $new_user_activities = array("manageAccount", "manageGroups",
        "mixCrawls", "blogPages");
    /**
     * An array of activities which are periodically updated within other
     * activities that they live. For example, within manage crawl,
     * the current crawl status is updated every 20 or so seconds.
     * @var array
     */
    var $status_activities = array("crawlStatus", "machineStatus");

    /**
     * This is the main entry point for handling requests to administer the
     * Yioop/SeekQuarry site
     *
     * ProcessRequest determines the type of request (signin , manageAccount,
     * etc) is being made.  It then calls the appropriate method to handle the
     * given activity. Finally, it draws the relevant admin screen
     */
    function processRequest()
    {
        $data = array();

        if(!PROFILE) {
            return $this->configureRequest();
        }
        $view = "signin";

        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user);
        $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user);
        if($token_okay) {
            if(isset($_SESSION['USER_ID']) && !isset($_REQUEST['u'])) {
                $data = array_merge($data, $this->processSession());
                if(!isset($data['REFRESH'])) {
                    $view = "admin";
                } else {
                    $view = $data['REFRESH'];
                }
            } else if ($this->checkSignin()){
                $user_id = $this->signinModel->getUserId(
                    $this->clean($_REQUEST['u'], "string"));
                $session = $this->userModel->getUserSession($user_id);
                if(is_array($session)) {
                    $_SESSION = $session;
                }
                $_SESSION['USER_ID'] = $user_id;
                $data[CSRF_TOKEN] = $this->generateCSRFToken(
                    $_SESSION['USER_ID']);
                // now don't want to use remote address anymore
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('admin_controller_login_successful')."</h1>')";
                $data = array_merge($data, $this->processSession());
                $view = "admin";
            } else {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('admin_controller_login_failed')."</h1>')";
                unset($_SESSION['USER_ID']);
            }
        } else if($this->checkCSRFToken(CSRF_TOKEN, "config")) {
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('admin_controller_login_to_config')."</h1>')";
        } else if(isset($_REQUEST['a']) &&
            in_array($_REQUEST['a'], $this->status_activities)) {
            e("<p class='red'>".
                tl('admin_controller_status_updates_stopped')."</p>");
            exit();
        }
        if($token_okay && isset($_SESSION["USER_ID"])) {
            $data["ADMIN"] = true;
        } else {
            $data["ADMIN"] = false;
        }
        if($view == 'signin') {
            unset($_SESSION['USER_ID']);
            $data[CSRF_TOKEN] = $this->generateCSRFToken(
                $_SERVER['REMOTE_ADDR']);
            $data['SCRIPT'] = "var u; if ((u = elt('username')) && u.focus) ".
               "u.focus();";
        }
        $this->displayView($view, $data);
    }

    /**
     * If there is no profile/work directory set up then this method
     * get called to by pass any login and go to the configure screen.
     * The configure screen is only displayed if the user is connected
     * from localhost in this case
     */
    function configureRequest()
    {
        $data = $this->processSession();
        $data[CSRF_TOKEN] = $this->generateCSRFToken("config");
        $this->displayView("admin", $data);
    }

    /**
     * Checks whether the user name and password sent presumably by the signin
     * form match a user in the database
     *
     * @return bool whether they do or not
     */
    function checkSignin()
    {
        $result = $this->signinModel->checkValidSignin(
        $this->clean($_REQUEST['u'], "string"),
        $this->clean($_REQUEST['p'], "string") );
        return $result;
    }

    /**
     * Determines the user's current allowed activities and current activity,
     * then calls the method for the latter.
     *
     * This is called from {@link processRequest()} once a user is logged in.
     *
     * @return array $data the results of doing the activity for display in the
     *      view
     */
    function processSession()
    {
        if(!PROFILE || (defined("FIX_NAME_SERVER") && FIX_NAME_SERVER)) {
            $activity = "configure";
        } else if(isset($_REQUEST['a']) &&
            in_array($_REQUEST['a'], $this->activities)) {
            $activity = $_REQUEST['a'];
        } else {
            $activity = "manageAccount";
        }
        $allowed = true;
        if(!PROFILE) {
            $allowed_activities = array( array(
                "ACTIVITY_NAME" =>
                $this->activityModel->getActivityNameFromMethodName($activity),
                'METHOD_NAME' => $activity));
            $allowed = true;
        } else {
            $allowed_activities =
                 $this->userModel->getUserActivities($_SESSION['USER_ID']);
        }
        foreach($allowed_activities as $allowed_activity) {
            if($activity == $allowed_activity['METHOD_NAME']) {
                 $allowed = true;
            }
            if($allowed_activity['METHOD_NAME'] == "manageCrawls" &&
                $activity == "crawlStatus") {
                $allowed = true;
            }
            if($allowed_activity['METHOD_NAME'] == "manageMachines" &&
                $activity == "machineStatus") {
                $allowed = true;
            }
        }

        //for now we allow anyone to get crawlStatus
        if($allowed) {
            $data = $this->call($activity);
            if(!is_array($data)) {
                $data = array();
            }
            $data['ACTIVITIES'] = $allowed_activities;
        }
        if(!in_array($activity, $this->status_activities)) {
            $data['CURRENT_ACTIVITY'] =
                $this->activityModel->getActivityNameFromMethodName($activity);
        }
        return $data;
    }

    /**
     * Used to handle crawlStatus REST activities requesting the status of the
     * current web crawl
     *
     * @return array $data contains crawl status of current crawl as well as
     *      info about prior crawls and which crawl is being used for default
     *      search results
     */
    function crawlStatus()
    {
        $data = array();
        $data['REFRESH'] = "crawlstatus";

        $crawl_time = $this->crawlModel->getCurrentIndexDatabaseName();
        if(isset($crawl_time) ) {
            $data['CURRENT_INDEX'] = (int)$crawl_time;
        } else {
            $data['CURRENT_INDEX'] = -1;
        }

        $machine_urls = $this->machineModel->getQueueServerUrls();
        list($stalled, $status, $data['RECENT_CRAWLS']) =
            $this->crawlModel->combinedCrawlInfo($machine_urls);

        if($stalled) {
            $this->crawlModel->sendStopCrawlMessage($machine_urls);
        }

        $data = array_merge($data, $status);

        $data["CRAWL_RUNNING"] = false;
        if(isset($data['CRAWL_TIME']) && $data["CRAWL_TIME"] != 0) {
            //erase from previous crawl list any active crawl
            $num_crawls = count($data['RECENT_CRAWLS']);
            for($i = 0; $i < $num_crawls; $i++) {
                if($data['RECENT_CRAWLS'][$i]['CRAWL_TIME'] ==
                    $data['CRAWL_TIME']) {
                    $data['RECENT_CRAWLS'][$i] = false;
                }
            }
            $data["CRAWL_RUNNING"] = true;
            $data['RECENT_CRAWLS']= array_filter($data['RECENT_CRAWLS']);
        }
        if(isset($data['RECENT_CRAWLS'][0])) {
            rorderCallback($data['RECENT_CRAWLS'][0], $data['RECENT_CRAWLS'][0],
                'CRAWL_TIME');
            usort($data['RECENT_CRAWLS'], "rorderCallback");
        }

        return $data;
    }

    /**
     * Gets data from the machineModel concerning the on/off states
     * of the machines managed by this Yioop instance and then passes
     * this data the the machinestatus view.
     * @return array $data MACHINES field has information about each
     *      machine managed by this Yioop instance as well the on off
     *      status of its queue_servers and fetchers.
     *      The REFRESH field is used to tell the controller that the
     *      view shouldn't have its own sidemenu.
     */
    function machineStatus()
    {
        $data = array();
        $data['REFRESH'] = "machinestatus";
        $data['MACHINES'] = $this->machineModel->getMachineStatuses();
        $profile =  $this->profileModel->getProfile(WORK_DIRECTORY);
        $data['NEWS_MODE'] = isset($profile['NEWS_MODE']) ?
            $profile['NEWS_MODE']: "";
        return $data;
    }

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
        $data["ELEMENT"] = "blogpagesElement";
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
                 $this->clean($_REQUEST['selectgroup'], "string");
                 $data['SELECT_GROUP'] = $select_group;
        } else {
            $select_group = "";
        }
        if($_SESSION['USER_ID'] == '1') {
             $groups = $this->groupModel->getGroupList();
             $is_admin = true;
        } else {
            $is_admin = false;
            $groups =
                $this->groupModel->getGroupListbyUser($_SESSION['USER_ID']);
            foreach($groups as $group) {
                array_push($group_ids, $group['GROUP_ID']);
            }
        }
        $recent_blogs =
            $this->blogpageModel->recentBlog($user, $group_ids, $is_admin);
        $data['RECENT_BLOGS'] = $recent_blogs;
        $base_option = tl('accountaccess_component_select_groupname');
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
                    $data['SOURCE_TYPES'] =
                        array(-1 => tl('admin_controller_source_type'),
                        "blog" => tl('admin_controller_blog'),
                        "page" => tl('admin_controller_page'));
                    $source_type_flag = false;
                    if(isset($_REQUEST['sourcetype']) &&
                        in_array($_REQUEST['sourcetype'],
                        array_keys($data['SOURCE_TYPES']))) {
                        $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
                        $source_type_flag = true;
                    } else {
                        $data['SOURCE_TYPE'] = -1;
                    }
                    $locales = $this->localeModel->getLocaleList();
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
                            $this->clean($_REQUEST[$clean_me], "string" ) : "";
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

                    $groupid = $this->groupModel->getGroupId($select_group);
                    if(isset($_REQUEST['selectgroup'])) {
                        $select_group =
                        $this->clean($_REQUEST['selectgroup'], "string" );
                        $data['SELECT_GROUP'] = $select_group;
                    } else {
                        $select_group = "";
                    }
                    $data['SELECT_GROUP'] = $select_group;
                        $result = $this->blogpageModel->addPage(
                            $data['title'], $data['description'],
                            $data['SOURCE_TYPE'], $data['SOURCE_LOCALE_TAG'],
                            $user, $select_group);
                        if($result){
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_page_added').
                            "</h1>');";
                    }
                    }else{
                        $user = $_SESSION['USER_ID'];
                        $groupid = $this->groupModel->getGroupId($select_group);
                        $data['SELECT_GROUP'] = $select_group;
                        $this->blogpageModel->addBlog(
                            $data['title'], $data['description'], $user,
                            $data['SOURCE_TYPE'], $data['sourcelocaletag'],
                            $select_group);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_blog_added').
                            "</h1>');";
                    }
                    $data["ELEMENT"] = "blogpagesElement";
                    $recent_blogs =
                        $this->blogpageModel->recentBlog(
                        $user, $group_ids, $is_admin);
                    $data['RECENT_BLOGS'] = $recent_blogs;
                break;

                case "searchblog":
                    if(!isset($_REQUEST['title'])) { break; }

                    $data["ELEMENT"] = "blogpagesElement";
                    $data['SELECT_GROUP'] = "";
                    $data['description'] = "";
                    $is_blogs_empty = false;
                    $data['title'] = $this->clean($_REQUEST['title'],
                        "string");
                    if($data['title'] != "") {
                        $blogs =
                            $this->blogpageModel->searchBlog(
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
                        $data["ELEMENT"] = "createblogpagesElement";
                        $data['SOURCE_TYPES'] =
                            array(-1 => tl('admin_controller_source_type'),
                                "blog" => tl('admin_controller_blog'),
                                "page" => tl('admin_controller_page')
                            );
                        $source_type_flag = false;
                        if(isset($_REQUEST['sourcetype']) &&
                            in_array($_REQUEST['sourcetype'],
                            array_keys($data['SOURCE_TYPES']))) {
                            $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
                            $source_type_flag = true;
                        } else {
                            $data['SOURCE_TYPE'] = -1;
                        }
                        $locales = $this->localeModel->getLocaleList();
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

                    $timestamp = $this->clean($_REQUEST['id'], "string" );
                    $title = "";
                    if(isset($_REQUEST['title'])) {
                        $title = $this->clean($_REQUEST['title'], "string" );
                    }
                    $this->blogpageModel->deleteBlog($timestamp, $title, $user);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_blog_deleted').
                        "</h1>');";
                    $data['RECENT_BLOGS'] =  $this->blogpageModel->recentBlog(
                        $user, $group_ids, $is_admin);
                break;

                case "editblog":
                    if(!isset($_REQUEST['id'])) { break; }

                    $timestamp = $this->clean($_REQUEST['id'], "string" );
                    $data["ELEMENT"] = "editblogpagesElement";
                    $data['SOURCE_TYPES'] = array(
                            -1 => tl('admin_controller_source_type'),
                            "blog" => tl('admin_controller_blog'),
                            "page" => tl('admin_controller_page')
                        );
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
                    $locales = $this->localeModel->getLocaleList();
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
                    $edit_blogs= $this->blogpageModel->editBlog($timestamp);
                    $blog_users =
                    $this->blogpageModel->getBlogUsers($edit_blogs[0]);
                    $edit_blogs[0]['BLOG_USERS'] = $blog_users;
                    $data['EDIT_BLOGS'] = $edit_blogs[0];
                    $title = $edit_blogs['0']['NAME'];
                    if(isset($_SESSION['USER_ID'])) {
                        $user = $_SESSION['USER_ID'];
                    } else {
                        $user = $_SERVER['REMOTE_ADDR'];
                    }
                    $user = $_SESSION['USER_ID'];
                    $feed_items=$this->blogpageModel->getFeed($title, $user);
                    $data['FEED_ITEMS'] = $feed_items;
                    if(isset($_SESSION['USER_ID'])) {
                        $username = $this->signinModel->getUserName(
                            $_SESSION['USER_ID']);
                        $data['USER_NAME'] = $username;
                        $owner_id = $this->blogpageModel->
                            getBlogOwner($timestamp);
                        if($owner_id == $user || $user == '1'){
                            $data['IS_OWNER'] = true;
                        }
                    }
                break;

                case "editdescription":
                    if(!isset($_REQUEST['id'])) { break; }
                    $timestamp = $this->clean($_REQUEST['id'], "string" );
                    $data["ELEMENT"] = "editblogpagesElement";
                    $data['SOURCE_TYPES'] =
                        array(-1 => tl('admin_controller_source_type'),
                            "blog" => tl('admin_controller_blog'),
                            "page" => tl('admin_controller_page'));
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
                    $locales = $this->localeModel->getLocaleList();
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
                    $blog_group = $this->blogpageModel->
                        getBlogGroup($timestamp);
                    $data['BLOG_GROUP'] = $blog_group;
                    $edit_blogs = $this->blogpageModel->editBlog($timestamp);
                    $data['EDIT_BLOGS'] = $edit_blogs[0];
                    $data['IS_EDIT_DESC'] = true;
                break;

                case "updatedescription":
                    if(isset($_REQUEST['id'])) {
                        $timestamp = $this->clean($_REQUEST['id'], "string" );
                    }
                    if(isset($_REQUEST['description'])) {
                        $description =
                            $this->clean($_REQUEST['description'], "string" );
                    }
                    if(isset($_REQUEST['title'])) {
                        $title = $this->clean($_REQUEST['title'], "string" );
                    }

                    $edit_blogs =
                        $this->blogpageModel->editDescription($timestamp,
                        $title, $description);
                    if(!empty($edit_blogs)){
                        $data['EDIT_BLOGS'] = $edit_blogs[0];
                    }
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_blog_updated') . "</h1>');";
                    $data["ELEMENT"] = "blogpagesElement";
                    $recent_blogs =
                    $this->blogpageModel->
                        recentBlog($user, $group_ids, $is_admin);
                    $data['RECENT_BLOGS'] = $recent_blogs;
                break;

                case "addblogentry":
                    if(!isset($_REQUEST['id'])) { break; }
                    $timestamp = $this->clean($_REQUEST['id'], "string" );
                    $data["ELEMENT"] = "editblogpagesElement";
                    $data['SOURCE_TYPES'] =
                        array(-1 => tl('admin_controller_source_type'),
                        "blog" => tl('admin_controller_blog'),
                        "page" => tl('admin_controller_page'));
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
                    $locales = $this->localeModel->getLocaleList();
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
                    $edit_blogs = $this->blogpageModel->editBlog($timestamp);
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
                        $timestamp = $this->clean($_REQUEST['id'], "string" );
                    }
                    $title = "";
                    if(isset($_REQUEST['title'])) {
                        $title = $this->clean($_REQUEST['title'], "string" );
                    }
                    if(isset($_REQUEST['description'])) {
                        $description =
                            $this->clean($_REQUEST['description'], "string" );
                    }
                    if(isset($_REQUEST['title_entry'])) { ;
                        $title_entry =
                            $this->clean($_REQUEST['title_entry'], "string" );
                    }
                    $feed_items= $this->blogpageModel->addEntry($timestamp,
                        $title_entry, $description, $title, $user);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_feed_added').
                        "</h1>');";
                break;

                case "editfeed":
                    if(!isset($_REQUEST['id'])) { break; }

                    $timestamp = $this->clean($_REQUEST['id'], "string" );
                    $data["ELEMENT"] = "editblogpagesElement";
                    $data['SOURCE_TYPES'] =
                        array(-1 => tl('admin_controller_source_type'),
                        "blog" => tl('admin_controller_blog'),
                        "page" => tl('admin_controller_page'));
                    $source_type_flag = false;
                    if(isset($_REQUEST['sourcetype']) &&
                        in_array($_REQUEST['sourcetype'],
                        array_keys($data['SOURCE_TYPES']))) {
                            $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
                            $source_type_flag = true;
                    } else {
                        $data['SOURCE_TYPE'] = -1;
                    }
                    $locales = $this->localeModel->getLocaleList();
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

                    $edit_blogs = $this->blogpageModel->editBlog($timestamp);
                    $data['EDIT_BLOGS'] = $edit_blogs[0];
                    $data['IS_EDIT_FEED'] = true;
                    if(isset($_REQUEST['fid'])) {
                        $guid = $this->clean($_REQUEST['fid'], "string" );
                    }
                    $feed_items = $this->blogpageModel->getFeedByGUID($guid);
                    $data['FEED_ITEMS'] = $feed_items;
                    $owner_id = $this->blogpageModel->getBlogOwner($timestamp);
                    if($owner_id == $user || $user == '1'){
                        $data['IS_OWNER'] = true;
                    }
                break;

                case "updatefeed":
                    if(isset($_REQUEST['id'])) {
                        $timestamp = $this->clean($_REQUEST['id'], "string" );
                    }
                    if(isset($_REQUEST['description'])) {
                        $description =
                            $this->clean($_REQUEST['description'], "string" );
                    }
                    if(isset($_REQUEST['title'])) {
                        $title = $this->clean($_REQUEST['title'], "string" );
                    }
                    if(isset($_REQUEST['fid'])) {
                        $guid = $this->clean($_REQUEST['fid'], "string" );
                    }
                    $update_feeds= $this->blogpageModel->
                        updateFeed ($guid,$title, $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_feed_updated').
                        "</h1>');";
                break;

                case "deletefeed":
                    if(isset($_REQUEST['id'])) {
                        $guid = $this->clean($_REQUEST['id'], "string" );
                        $this->blogpageModel->deleteFeed($guid);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_feed_deleted').
                            "</h1>');";
                    }
                break;

                case "updateblogusers":
                    if(isset($_REQUEST['selectuser'])) {
                        $select_user =
                        $this->clean($_REQUEST['selectuser'], "string" );
                    } else {
                        $select_user = "";
                    }
                    if($select_user != "" ) {
                        $userid = $this->signinModel->getUserId($select_user);
                        $data['SELECT_USER'] = $select_user;

                    }
                    if(isset($_REQUEST['blogName'])) {
                        $blog_name =
                            $this->clean($_REQUEST['blogName'], "string");
                    } else {
                        $blog_name = "";
                    }
                    $this->blogpageModel->
                        updateBlogUsers($select_user, $blog_name);
                    $recent_blogs =
                    $this->blogpageModel->
                        recentBlog($user, $group_ids, $is_admin);
                    $data['RECENT_BLOGS'] = $recent_blogs;
                break;

                case "addbloggroup":
                    if(isset($_REQUEST['id'])) {
                        $timestamp = $this->clean($_REQUEST['id'], "string" );
                    }
                    if(isset($_REQUEST['selectgroup'])) {
                        $select_group =
                        $this->clean($_REQUEST['selectgroup'], "string" );
                        $data['SELECT_GROUP'] = $select_group;
                    } else {
                        $select_group = "";
                    }
                    $groupid = $this->groupModel->getGroupId($select_group);
                    $data['SELECT_GROUP'] = $select_group;
                    $this->blogpageModel->addGroup($timestamp,$select_group);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_group_added').
                        "</h1>');";
                break;

                case "deletebloggroup":
                    if(isset($_REQUEST['id'])) {
                        $timestamp = $this->clean($_REQUEST['id'], "string" );
                    }
                    if(isset($_REQUEST['gid'])) {
                        $groupid = $this->clean($_REQUEST['gid'], "string" );
                    }
                    $this->blogpageModel->deleteGroup($timestamp,$groupid);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_group_deleted').
                        "</h1>');";
                break;
            }
        }
        return $data;
    }

    /**
     * Used to handle the manage crawl activity.
     *
     * This activity allows new crawls to be started, statistics about old
     * crawls to be seen. It allows a user to stop the current crawl or
     * restart an old crawl. It also allows a user to configure the options
     * by which a crawl is conducted
     *
     * @return array $data information and statistics about crawls in the system
     *      as well as status messages on performing a given sub activity

     */
    function manageCrawls()
    {
        $possible_arguments =
            array("start", "resume", "delete", "stop", "index", "options");

        $data["ELEMENT"] = "managecrawlsElement";
        $data['SCRIPT'] = "doUpdate();";

        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {

            $machine_urls = $this->machineModel->getQueueServerUrls();
            $num_machines = count($machine_urls);
            if($num_machines <  1 || ($num_machines ==  1 &&
                UrlParser::isLocalhostUrl($machine_urls[0]))) {
                $machine_urls = NULL;
            }

            switch($_REQUEST['arg'])
            {
                case "start":
                    $this->startCrawl($data, $machine_urls);
                break;

                case "stop":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_stop_crawl')."</h1>')";
                    @unlink(CRAWL_DIR."/schedules/crawl_params.txt");

                    $info = array();
                    $info[self::STATUS] = "STOP_CRAWL";
                    $filename = CRAWL_DIR.
                        "/schedules/name_server_messages.txt";
                    file_put_contents($filename, serialize($info));

                    $this->crawlModel->sendStopCrawlMessage($machine_urls);
                break;

                case "resume":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_resume_crawl')."</h1>')";
                    $crawl_params = array();
                    $crawl_params[self::STATUS] = "RESUME_CRAWL";
                    $crawl_params[self::CRAWL_TIME] =
                        $this->clean($_REQUEST['timestamp'], "int");
                    $seed_info = $this->crawlModel->getCrawlSeedInfo(
                        $crawl_params[self::CRAWL_TIME], $machine_urls);
                    $this->getCrawlParametersFromSeedInfo($crawl_params,
                        $seed_info);
                   /*
                       Write the new crawl parameters to the name server, so
                       that it can pass them along in the case of a new archive
                       crawl.
                    */
                    $filename = CRAWL_DIR.
                        "/schedules/name_server_messages.txt";
                    file_put_contents($filename, serialize($crawl_params));
                    chmod($filename, 0777);
                    $this->crawlModel->sendStartCrawlMessage($crawl_params,
                        NULL, $machine_urls);
                break;

                case "delete":
                    if(isset($_REQUEST['timestamp'])) {
                         $timestamp =
                            $this->clean($_REQUEST['timestamp'], "int");
                         $this->crawlModel->deleteCrawl($timestamp,
                            $machine_urls);

                         $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_delete_crawl_success').
                            "</h1>'); crawlStatusUpdate(); ";
                     } else {
                        $data['SCRIPT'] .= "crawlStatusUpdate(); ".
                            "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_delete_crawl_fail').
                            "</h1>')";
                     }
                break;

                case "index":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_set_index')."</h1>')";

                    $timestamp = $this->clean($_REQUEST['timestamp'], "int");
                    $this->crawlModel->setCurrentIndexDatabaseName($timestamp);
                break;

                case "options":
                    $this->editCrawlOption($data, $machine_urls);
                break;
            }
        }
        return $data;
    }

    /**
     * Called from @see manageCrawls to start a new crawl on the machines
     * $machine_urls. Updates $data array with crawl start message
     *
     * @param array &$data an array of info to supply to AdminView
     * @param array $machine_urls string urls of machines managed by this
     *  Yioop name server on which to perform the crawl
     */
    function startCrawl(&$data, $machine_urls, $seed_info = NULL)
    {
        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('admin_controller_starting_new_crawl')."</h1>')";

        $crawl_params = array();
        $crawl_params[self::STATUS] = "NEW_CRAWL";
        $crawl_params[self::CRAWL_TIME] = time();
        $seed_info = $this->crawlModel->getSeedInfo();
        $this->getCrawlParametersFromSeedInfo($crawl_params, $seed_info);
        if(isset($_REQUEST['description'])) {
            $description = $this->clean($_REQUEST['description'], "string");
        } else {
            $description = tl('admin_controller_no_description');
        }
        $crawl_params['DESCRIPTION'] = $description;
        $crawl_params[self::VIDEO_SOURCES] = array();
        $sources =
            $this->sourceModel->getMediaSources('video');
        foreach($sources as $source) {
            $url = $source['SOURCE_URL'];
            $url_parts = explode("{}", $url);
            $crawl_params[self::VIDEO_SOURCES][] = $url_parts[0];
        }
        if(isset($crawl_params[self::INDEXING_PLUGINS]) &&
            is_array($crawl_params[self::INDEXING_PLUGINS])) {
            foreach($crawl_params[self::INDEXING_PLUGINS] as $plugin_prefix) {
                $plugin_name = $plugin_prefix."Plugin";
                $plugin = new $plugin_name();
                if(method_exists($plugin_name, "loadConfiguration")) {
                    $crawl_params[self::INDEXING_PLUGINS_DATA][$plugin_prefix] =
                        $plugin->loadConfiguration();
                }
            }
        }

        /*
           Write the new crawl parameters to the name server, so
           that it can pass them along in the case of a new archive
           crawl.
        */
        $filename = CRAWL_DIR.
            "/schedules/name_server_messages.txt";
        file_put_contents($filename, serialize($crawl_params));
        chmod($filename, 0777);

        $this->crawlModel->sendStartCrawlMessage($crawl_params,
            $seed_info, $machine_urls);
    }

    /**
     * Reads the parameters for a crawl from an array gotten from a crawl.ini
     * file
     *
     * @param array &$crawl_params parameters to write to queue_server
     * @param array $seed_info data from crawl.ini file
     */
    function getCrawlParametersFromSeedInfo(&$crawl_params, $seed_info)
    {
        $crawl_params[self::CRAWL_TYPE] = $seed_info['general']['crawl_type'];
        $crawl_params[self::CRAWL_INDEX] =
            (isset($seed_info['general']['crawl_index'])) ?
            $seed_info['general']['crawl_index'] : '';
        $crawl_params[self::ARC_DIR]=(isset($seed_info['general']['arc_dir'])) ?
            $seed_info['general']['arc_dir'] : '';
        $crawl_params[self::ARC_TYPE] =
            (isset($seed_info['general']['arc_type'])) ?
            $seed_info['general']['arc_type'] : '';
        $crawl_params[self::CACHE_PAGES] =
            (isset($seed_info['general']['cache_pages'])) ?
            intval($seed_info['general']['cache_pages']) :
            true;
        $crawl_params[self::PAGE_RANGE_REQUEST] =
            (isset($seed_info['general']['page_range_request'])) ?
            intval($seed_info['general']['page_range_request']) :
            PAGE_RANGE_REQUEST;
        $crawl_params[self::MAX_DESCRIPTION_LEN] =
            (isset($seed_info['general']['max_description_len'])) ?
            intval($seed_info['general']['max_description_len']) :
            MAX_DESCRIPTION_LEN;
        $crawl_params[self::PAGE_RECRAWL_FREQUENCY] =
            (isset($seed_info['general']['page_recrawl_frequency'])) ?
            intval($seed_info['general']['page_recrawl_frequency']) :
            PAGE_RECRAWL_FREQUENCY;
        $crawl_params[self::TO_CRAWL] = $seed_info['seed_sites']['url'];
        $crawl_params[self::CRAWL_ORDER] = $seed_info['general']['crawl_order'];
        $crawl_params[self::RESTRICT_SITES_BY_URL] =
            $seed_info['general']['restrict_sites_by_url'];
        $crawl_params[self::ALLOWED_SITES] =
            isset($seed_info['allowed_sites']['url']) ?
            $seed_info['allowed_sites']['url'] : array();
        $crawl_params[self::DISALLOWED_SITES] =
            isset($seed_info['disallowed_sites']['url']) ?
            $seed_info['disallowed_sites']['url'] : array();
        if(isset($seed_info['indexed_file_types']['extensions'])) {
            $crawl_params[self::INDEXED_FILE_TYPES] =
                $seed_info['indexed_file_types']['extensions'];
        }
        if(isset($seed_info['active_classifiers']['label'])) {
            // Note that 'label' is actually an array of active class labels.
            $crawl_params[self::ACTIVE_CLASSIFIERS] =
                $seed_info['active_classifiers']['label'];
        }
        if(isset($seed_info['active_rankers']['label'])) {
            // Note that 'label' is actually an array of active class labels.
            $crawl_params[self::ACTIVE_RANKERS] =
                $seed_info['active_rankers']['label'];
        }
        if(isset($seed_info['indexing_plugins']['plugins'])) {
            $crawl_params[self::INDEXING_PLUGINS] =
                $seed_info['indexing_plugins']['plugins'];
        }
        $crawl_params[self::PAGE_RULES] =
            isset($seed_info['page_rules']['rule']) ?
            $seed_info['page_rules']['rule'] : array();
    }

    /**
     * Called from @see manageCrawls to edit the parameters for the next
     * crawl (or current crawl) to be carried out by the machines
     * $machine_urls. Updates $data array to be supplied to AdminView
     *
     * @param array &$data an array of info to supply to AdminView
     * @param array $machine_urls string urls of machines managed by this
     *  Yioop name server on which to perform the crawl
     */
    function editCrawlOption(&$data, $machine_urls)
    {
        $data["leftorright"] = (getLocaleDirection() == 'ltr') ?
            "right": "left";
        $data["ELEMENT"] = "crawloptionsElement";
        $crawls = $this->crawlModel->getCrawlList(false, false, $machine_urls);
        $indexes = $this->crawlModel->getCrawlList(true, true, $machine_urls);
        $mixes = $this->crawlModel->getMixList(false);
        foreach($mixes as $mix) {
            $tmp = array();
            $tmp["DESCRIPTION"] = "MIX::".$mix["MIX_NAME"];
            $tmp["CRAWL_TIME"] = $mix["MIX_TIMESTAMP"];
            $tmp["ARC_DIR"] = "MIX";
            $tmp["ARC_TYPE"] = "MixArchiveBundle";
            $indexes[] = $tmp;
        }

        $indexes_by_crawl_time = array();
        $update_flag = false;
        $data['available_options'] = array(
            tl('admin_controller_use_below'),
            tl('admin_controller_use_defaults'));
        $data['available_crawl_indexes'] = array();
        $data['options_default'] = tl('admin_controller_use_below');
        foreach($crawls as $crawl) {
            if(strlen($crawl['DESCRIPTION']) > 0 ) {
                $data['available_options'][$crawl['CRAWL_TIME']] =
                    tl('admin_controller_previous_crawl')." ".
                    $crawl['DESCRIPTION'];
            }
        }
        foreach($indexes as $i => $crawl) {
            $data['available_crawl_indexes'][$crawl['CRAWL_TIME']]
                = $crawl['DESCRIPTION'];
            $indexes_by_crawl_time[$crawl['CRAWL_TIME']] =& $indexes[$i];
        }
        $no_further_changes = false;
        $seed_current = $this->crawlModel->getSeedInfo();
        if(isset($_REQUEST['load_option']) &&
            $_REQUEST['load_option'] == 1) {
            $seed_info = $this->crawlModel->getSeedInfo(true);
            if(isset(
                $seed_current['general']['page_range_request'])) {
                $seed_info['general']['page_range_request'] =
                    $seed_current['general']['page_range_request'];
            }
            if(isset(
                $seed_current['general']['page_recrawl_frequency'])
                ){
                $seed_info['general']['page_recrawl_frequency'] =
                $seed_current['general']['page_recrawl_frequency'];
            }
            if(isset(
                $seed_current['general']['max_description_len'])) {
                $seed_info['general']['max_description_len'] =
                    $seed_current['general']['max_description_len'];
            }
            $update_flag = true;
            $no_further_changes = true;
        } else if (isset($_REQUEST['load_option']) &&
            $_REQUEST['load_option'] > 1 ) {
            $timestamp =
                $this->clean($_REQUEST['load_option'], "int");
            $seed_info = $this->crawlModel->getCrawlSeedInfo(
                $timestamp, $machine_urls);
            $update_flag = true;
            $no_further_changes = true;
        } else if(isset($_REQUEST['ts'])) {
            $timestamp =
                $this->clean($_REQUEST['ts'], "int");
            $seed_info = $this->crawlModel->getCrawlSeedInfo(
                $timestamp, $machine_urls);
            $data['ts'] = $timestamp;
        } else {
            $seed_info = $this->crawlModel->getSeedInfo();
        }
        $page_options_properties = array('indexed_file_types',
            'active_classifiers', 'page_rules', 'indexing_plugins');
        //these properties should be changed under page_options not here
        foreach($page_options_properties as $property) {
            if(isset($seed_current[$property])) {
                $seed_info[$property] = $seed_current[$property];
            }
        }

        if(!$no_further_changes && isset($_REQUEST['crawl_indexes'])
            && in_array($_REQUEST['crawl_indexes'],
            array_keys($data['available_crawl_indexes']))) {
            $seed_info['general']['crawl_index'] = $_REQUEST['crawl_indexes'];
            $index_data = $indexes_by_crawl_time[$_REQUEST['crawl_indexes']];
            if(isset($index_data['ARC_DIR'])) {
                $seed_info['general']['arc_dir'] = $index_data['ARC_DIR'];
                $seed_info['general']['arc_type'] = $index_data['ARC_TYPE'];
            } else {
                $seed_info['general']['arc_dir'] = '';
                $seed_info['general']['arc_type'] = '';
            }
            $update_flag = true;
        }
        $data['crawl_index'] =  (isset($seed_info['general']['crawl_index'])) ?
            $seed_info['general']['crawl_index'] : '';
        $data['available_crawl_types'] = array(self::WEB_CRAWL,
            self::ARCHIVE_CRAWL);
        if(!$no_further_changes && isset($_REQUEST['crawl_type']) &&
            in_array($_REQUEST['crawl_type'], $data['available_crawl_types'])) {
            $seed_info['general']['crawl_type'] =
                $_REQUEST['crawl_type'];
            $update_flag = true;
        }
        $data['crawl_type'] = $seed_info['general']['crawl_type'];
        if($data['crawl_type'] == self::WEB_CRAWL) {
            $data['web_crawl_active'] = "active";
            $data['archive_crawl_active'] = "";
        } else {
            $data['archive_crawl_active'] = "active";
            $data['web_crawl_active'] = "";
        }

        $data['available_crawl_orders'] = array(
            self::BREADTH_FIRST =>
                tl('admin_controller_breadth_first'),
            self::PAGE_IMPORTANCE =>
                tl('admin_controller_page_importance'));

        if(!$no_further_changes && isset($_REQUEST['crawl_order'])
            &&  in_array($_REQUEST['crawl_order'],
                array_keys($data['available_crawl_orders']))) {
            $seed_info['general']['crawl_order'] =
                $_REQUEST['crawl_order'];
            $update_flag = true;
        }
        $data['crawl_order'] = $seed_info['general']['crawl_order'];

        if(!$no_further_changes && isset($_REQUEST['posted'])) {
            $seed_info['general']['restrict_sites_by_url'] =
                (isset($_REQUEST['restrict_sites_by_url'])) ?
                true : false;
            $update_flag = true;
        }
        $data['restrict_sites_by_url'] =
            $seed_info['general']['restrict_sites_by_url'];
        $site_types =
            array('allowed_sites' => 'url', 'disallowed_sites' => 'url',
                'seed_sites' => 'url');
        foreach($site_types as $type => $field) {
            if(!$no_further_changes && isset($_REQUEST[$type])) {
                $seed_info[$type][$field] =
                    $this->convertStringCleanArray(
                    $_REQUEST[$type], $field);
                    $update_flag = true;
            }
            if(isset($seed_info[$type][$field])) {
                $data[$type] = $this->convertArrayLines(
                    $seed_info[$type][$field]);
            } else {
                $data[$type] = "";
            }
        }
        $data['TOGGLE_STATE'] =
            ($data['restrict_sites_by_url']) ?
            "checked='checked'" : "";

        $data['SCRIPT'] = "setDisplay('toggle', ".
            "'{$data['restrict_sites_by_url']}');";
        if(!isset($_REQUEST['ts'])) {
            $data['SCRIPT'] .=
            " elt('load-options').onchange = ".
            "function() { if(elt('load-options').selectedIndex !=".
            " 0) { elt('crawloptionsForm').submit();  }};";
        }
        if($data['crawl_type'] == CrawlConstants::WEB_CRAWL) {
            $data['SCRIPT'] .=
                "switchTab('webcrawltab', 'archivetab');";
        } else {
            $data['SCRIPT'] .=
                "switchTab('archivetab', 'webcrawltab');";
        }
        $add_message = "";
        if(isset($_REQUEST['ts']) &&
            isset($_REQUEST['inject_sites'])) {
                $timestamp = $this->clean($_REQUEST['ts'],
                    "string");
                $inject_urls =
                    $this->convertStringCleanArray(
                    $_REQUEST['inject_sites']);
                if($this->crawlModel->injectUrlsCurrentCrawl(
                    $timestamp, $inject_urls, $machine_urls)) {
                    $add_message = "<br />".
                        tl('admin_controller_urls_injected');
                }
        }
        if($update_flag) {
            if(isset($_REQUEST['ts'])) {
                $this->crawlModel->setCrawlSeedInfo($timestamp,
                    $seed_info, $machine_urls);
            } else {
                $this->crawlModel->setSeedInfo($seed_info);
            }
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('admin_controller_update_seed_info').
                "$add_message</h1>');";
        }
        return $data;
    }

    /**
     * Converts an array of lines of strings into a single string with
     * proper newlines, each line having been trimmed and potentially
     * cleaned
     *
     * @param array $arr the array of lines to be process
     * @param string $endline_string what string should be used to indicate
     *      the end of a line
     * @param bool $clean whether to clean each line
     * @return string a concatenated string of cleaned lines
     */
    function convertArrayLines($arr, $endline_string="\n", $clean = false)
    {
        $output = "";
        $eol = "";
        foreach($arr as $line) {
            $output .= $eol;
            $out_line = trim($line);
            if($clean) {
                $out_line = $this->clean($out_line, "string");
            }
            $output .= trim($out_line);
            $eol = $endline_string;
        }
        return $output;
    }
    /**
     * Cleans a string consisting of lines, typically of urls into an array of
     * clean lines. This is used in handling data from the crawl options
     * text areas.
     *
     * @param string $str contains the url data
     * @param string $line_type does additional cleaning depending on the type
     *      of the lines. For instance, if is "url" then a line not beginning
     *      with a url scheme will have http:// prepended.
     * @return $lines an array of clean lines
     */
    function convertStringCleanArray($str, $line_type="url")
    {
        if($line_type == "url") {
            $pre_lines = preg_split("/(\s)+/", $str);
        } else {
            $pre_lines = preg_split('/\n+/', $str);
        }
        $lines = array();
        foreach($pre_lines as $line) {
            $pre_line = trim($this->clean($line, "string"));
            if(strlen($pre_line) > 0) {
                if($line_type == "url") {
                    $start_line = substr($pre_line, 0, 6);
                    if(!in_array($start_line,
                        array("file:/", "http:/", "domain", "https:"))) {
                        $pre_line = "http://". $pre_line;
                    }
                }
                $lines[] = $pre_line;
            }
        }
        return $lines;
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
        $possible_arguments = array(
            "createmix", "deletemix", "editmix", "index");

        $data["ELEMENT"] = "mixcrawlsElement";

        $data['mix_default'] = 0;
        $machine_urls = $this->machineModel->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if($num_machines <  1 || ($num_machines ==  1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = NULL;
        }
        $crawls = $this->crawlModel->getCrawlList(false, true, $machine_urls);
        $data['available_crawls'][0] = tl('admin_controller_select_crawl');
        $data['available_crawls'][1] = tl('admin_controller_default_crawl');
        $data['SCRIPT'] = "c = [];c[0]='".
            tl('admin_controller_select_crawl')."';";
        $data['SCRIPT'] .= "c[1]='".
            tl('admin_controller_default_crawl')."';";
        foreach($crawls as $crawl) {
            $data['available_crawls'][$crawl['CRAWL_TIME']] =
                $crawl['DESCRIPTION'];
            $data['SCRIPT'] .= 'c['.$crawl['CRAWL_TIME'].']="'.
                $crawl['DESCRIPTION'].'";';
        }
        $mixes = $this->crawlModel->getMixList(true);
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
                        $mix['MIX_NAME'] = $this->clean($_REQUEST['MIX_NAME'],
                            'string');
                    } else {
                        $mix['MIX_NAME'] = tl('admin_controller_unnamed');
                    }
                    $mix['GROUPS'] = array();
                    $this->crawlModel->setCrawlMix($mix);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_mix_created')."</h1>');";

                case "editmix":
                    //$data passed by reference
                    $this->editMix($data, $mix_ids, $mix);
                break;

                case "index":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_set_index')."</h1>')";

                    $timestamp = $this->clean($_REQUEST['timestamp'], "int");
                    $this->crawlModel->setCurrentIndexDatabaseName($timestamp);
                break;

                case "deletemix":
                    if(!isset($_REQUEST['timestamp'])|| !isset($mix_ids) ||
                        !in_array($_REQUEST['timestamp'], $mix_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_mix_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $this->crawlModel->deleteCrawlMix($_REQUEST['timestamp']);
                    $data['available_mixes'] =
                        $this->crawlModel->getMixList(true);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_mix_deleted')."</h1>')";
                break;
            }
        }

        $crawl_time = $this->crawlModel->getCurrentIndexDatabaseName();
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
        $data["leftorright"] =
            (getLocaleDirection() == 'ltr') ? "right": "left";
        $data["ELEMENT"] = "editmixElement";

        if(isset($_REQUEST['timestamp'])) {
            $mix = $this->crawlModel->getCrawlMix(
                $_REQUEST['timestamp']);
        }
        $data['MIX'] = $mix;
        $data['INCLUDE_SCRIPTS'] = array("mix");

        //set up an array of translation for javascript-land
        $data['SCRIPT'] .= "tl = {".
            'editmix_element_add_crawls:"'. tl('editmix_element_add_crawls') .
            '",' . 'editmix_element_num_results:"'.
                tl('editmix_element_num_results').'",'.
            'editmix_element_del_grp:"'.tl('editmix_element_del_grp').'",'.
            'editmix_element_weight:"'.tl('editmix_element_weight').'",'.
            'editmix_element_name:"'.tl('editmix_element_name').'",'.
            'editmix_add_keywords:"'.tl('editmix_add_keywords').'",'.
            'editmix_element_actions:"'.tl('editmix_element_actions').'",'.
            'editmix_add_query:"'.tl('editmix_add_query').'",'.
            'editmix_element_delete:"'.tl('editmix_element_delete').'"'.
            '};';
        //clean and save the crawl mix sent from the browser
        if(isset($_REQUEST['update']) && $_REQUEST['update'] ==
            "update") {
            $mix = $_REQUEST['mix'];
            $mix['MIX_TIMESTAMP'] =
                $this->clean($mix['MIX_TIMESTAMP'], "int");
            $mix['MIX_NAME'] =$this->clean($mix['MIX_NAME'],
                "string");
            $comp = array();
            if(isset($mix['GROUPS'])) {

                if($mix['GROUPS'] != NULL) {
                    foreach($mix['GROUPS'] as $group_id => $group_data) {
                        if(isset($group_data['RESULT_BOUND'])) {
                            $mix['GROUPS'][$group_id]['RESULT_BOUND'] =
                                $this->clean($group_data['RESULT_BOUND'],
                                    "int");
                        } else {
                            $mix['GROUPS']['RESULT_BOUND'] = 0;
                        }
                        if(isset($group_data['COMPONENTS'])) {
                            $comp = array();
                            foreach($group_data['COMPONENTS'] as $component) {
                                $row = array();
                                $row['CRAWL_TIMESTAMP'] =
                                    $this->clean($component['CRAWL_TIMESTAMP'],
                                    "int");
                                $row['WEIGHT'] = $this->clean(
                                    $component['WEIGHT'], "float");
                                $row['KEYWORDS'] = $this->clean(
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
            $this->crawlModel->setCrawlMix($mix);
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('admin_controller_mix_saved')."</h1>');";
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

    /**
     * Handles admin request related to controlling file options to be used
     * in a crawl
     *
     * This activity allows a user to specify the page range size to be
     * be used during a crawl as well as which file types can be downloaded
     */
    function pageOptions()
    {
        global $INDEXED_FILE_TYPES;
        $data["ELEMENT"] = "pageoptionsElement";
        $data['SCRIPT'] = "";
        $machine_urls = $this->machineModel->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if($num_machines <  1 || ($num_machines ==  1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = NULL;
        }
        $data['available_options'] = array(
            tl('admin_controller_use_below'),
            tl('admin_controller_use_defaults'));
        $crawls = $this->crawlModel->getCrawlList(false, true, $machine_urls);
        $data['options_default'] = tl('admin_controller_use_below');
        foreach($crawls as $crawl) {
            if(strlen($crawl['DESCRIPTION']) > 0 ) {
                $data['available_options'][$crawl['CRAWL_TIME']] =
                    tl('admin_controller_previous_crawl')." ".
                    $crawl['DESCRIPTION'];
            }
        }
        $seed_info = $this->crawlModel->getSeedInfo();
        $data['RECRAWL_FREQS'] = array(-1=>tl('admin_controller_recrawl_never'),
            1=>tl('admin_controller_recrawl_1day'),
            2=>tl('admin_controller_recrawl_2day'),
            3=>tl('admin_controller_recrawl_3day'),
            7=>tl('admin_controller_recrawl_7day'),
            14=>tl('admin_controller_recrawl_14day'));
        $data['SIZE_VALUES'] = array(10000=>10000, 50000=>50000,
            100000=>100000, 500000=>500000, 1000000=>1000000,
            5000000=>5000000, 10000000=>10000000);
        $data['LEN_VALUES'] = array(2000=>2000, 10000=>10000, 50000=>50000,
            100000=>100000, 500000=>500000, 1000000=>1000000,
            5000000=>5000000, 10000000=>10000000);
        if(!isset($seed_info["indexed_file_types"]["extensions"])) {
            $seed_info["indexed_file_types"]["extensions"] =
                $INDEXED_FILE_TYPES;
        }
        $loaded = false;
        if(isset($_REQUEST['load_option']) &&
            $_REQUEST['load_option'] > 0) {
            if($_REQUEST['load_option'] == 1) {
                $seed_loaded = $this->crawlModel->getSeedInfo(true);
            } else {
                $timestamp = $this->clean($_REQUEST['load_option'], "int");
                $seed_loaded = $this->crawlModel->getCrawlSeedInfo(
                    $timestamp, $machine_urls);
            }
            $copy_options = array("general" => array("page_recrawl_frequency",
                "page_range_request", "max_description_len", "cache_pages"),
                "indexed_file_types" => array("extensions"),
                "indexing_plugins" => array("plugins"));
            foreach($copy_options as $main_option => $sub_options) {
                foreach($sub_options as $sub_option) {
                    if(isset($seed_loaded[$main_option][$sub_option])) {
                        $seed_info[$main_option][$sub_option] =
                            $seed_loaded[$main_option][$sub_option];
                    }
                }
            }
            if(isset($seed_loaded['page_rules'])) {
                $seed_info['page_rules'] =
                    $seed_loaded['page_rules'];
            }
            if(isset($seed_loaded['active_classifiers'])) {
                $seed_info['active_classifiers'] =
                    $seed_loaded['active_classifiers'];
            } else {
                $seed_info['active_classifiers'] = array();
                $seed_info['active_classifiers']['label'] = array();
            }
            $update_flag = true;
            $loaded = true;
        } else {
            $seed_info = $this->crawlModel->getSeedInfo();
            if(isset($_REQUEST["page_recrawl_frequency"]) &&
                in_array($_REQUEST["page_recrawl_frequency"],
                    array_keys($data['RECRAWL_FREQS']))) {
                $seed_info["general"]["page_recrawl_frequency"] =
                    $_REQUEST["page_recrawl_frequency"];
            }
            if(isset($_REQUEST["page_range_request"]) &&
                in_array($_REQUEST["page_range_request"],$data['SIZE_VALUES'])){
                $seed_info["general"]["page_range_request"] =
                    $_REQUEST["page_range_request"];
            }
            if(isset($_REQUEST["max_description_len"]) &&
                in_array($_REQUEST["max_description_len"],$data['LEN_VALUES'])){
                $seed_info["general"]["max_description_len"] =
                    $_REQUEST["max_description_len"];
            }
           if(isset($_REQUEST["cache_pages"]) ) {
                $seed_info["general"]["cache_pages"] = true;
           } else if(isset($_REQUEST['posted'])) {
                //form sent but check box unchecked
                $seed_info["general"]["cache_pages"] = false;
           }

           if(isset($_REQUEST['page_rules'])) {
                $seed_info['page_rules']['rule'] =
                    $this->convertStringCleanArray(
                    $_REQUEST['page_rules'], 'rule');
                    $update_flag = true;
            }
        }
        if(!isset($seed_info["general"]["page_recrawl_frequency"])) {
            $seed_info["general"]["page_recrawl_frequency"] =
                PAGE_RECRAWL_FREQUENCY;
        }
        $data['PAGE_RECRAWL_FREQUENCY'] =
            $seed_info["general"]["page_recrawl_frequency"];
        if(!isset($seed_info["general"]["cache_pages"])) {
            $seed_info["general"]["cache_pages"] = false;
        }
        $data["CACHE_PAGES"] = $seed_info["general"]["cache_pages"];
        if(!isset($seed_info["general"]["page_range_request"])) {
            $seed_info["general"]["page_range_request"] = PAGE_RANGE_REQUEST;
        }
        $data['PAGE_SIZE'] = $seed_info["general"]["page_range_request"];
        if(!isset($seed_info["general"]["max_description_len"])) {
            $seed_info["general"]["max_description_len"] = MAX_DESCRIPTION_LEN;
        }
        $data['MAX_LEN'] = $seed_info["general"]["max_description_len"];

        $data['INDEXING_PLUGINS'] = array();
        $included_plugins = array();
        if(isset($_REQUEST["posted"])) {
            $seed_info['indexing_plugins']['plugins'] =
                (isset($_REQUEST["INDEXING_PLUGINS"])) ?
                $_REQUEST["INDEXING_PLUGINS"] : array();
        }
        $included_plugins =
            (isset($seed_info['indexing_plugins']['plugins'])) ?
                $seed_info['indexing_plugins']['plugins']
                : array();
        foreach($this->indexing_plugins as $plugin) {
            $plugin_name = ucfirst($plugin);
            $data['INDEXING_PLUGINS'][$plugin_name]['checked'] =
                (in_array($plugin_name, $included_plugins)) ?
                "checked='checked'" : "";
            $class_name = $plugin_name."Plugin";
            if(method_exists($class_name, 'configureHandler') &&
                method_exists($class_name, 'configureView')) {
                $data['INDEXING_PLUGINS'][$plugin_name]['configure'] = true;
                $plugin_object = new $class_name();
                $plugin_object->configureHandler($data);
            } else {
                $data['INDEXING_PLUGINS'][$plugin_name]['configure'] = false;
            }
        }

        $profile =  $this->profileModel->getProfile(WORK_DIRECTORY);
        if(!isset($_REQUEST['load_option'])) {
            $data = array_merge($data, $profile);
        } else {
            $this->updateProfileFields($data, $profile,
                array('IP_LINK','CACHE_LINK', 'SIMILAR_LINK', 'IN_LINK',
                    'SIGNIN_LINK', 'SUBSEARCH_LINK','WORD_SUGGEST'));
        }
        $weights = array('TITLE_WEIGHT' => 4,
            'DESCRIPTION_WEIGHT' => 1, 'LINK_WEIGHT' => 2,
            'MIN_RESULTS_TO_GROUP' => 200, 'SERVER_ALPHA' => 1.6);
        $change = false;
        foreach($weights as $weight => $value) {
            if(isset($_REQUEST[$weight])) {
                $data[$weight] = $this->clean($_REQUEST[$weight], 'float', 1
                    );
                $profile[$weight] = $data[$weight];
                $change = true;
            } else if(isset($profile[$weight]) && $profile[$weight] != ""){
                $data[$weight] = $profile[$weight];
            } else {
                $data[$weight] = $value;
                $profile[$weight] = $data[$weight];
                $change = true;
            }
        }
        if($change == true) {
            $this->profileModel->updateProfile(WORK_DIRECTORY, array(),
                $profile);
        }

        $data['INDEXED_FILE_TYPES'] = array();
        $filetypes = array();
        foreach($INDEXED_FILE_TYPES as $filetype) {
            $ison =false;
            if(isset($_REQUEST["filetype"]) && !$loaded) {
                if(isset($_REQUEST["filetype"][$filetype])) {
                    $filetypes[] = $filetype;
                    $ison = true;
                    $change = true;
                }
            } else {
                if(in_array($filetype,
                    $seed_info["indexed_file_types"]["extensions"])) {
                    $filetypes[] = $filetype;
                    $ison = true;
                }
            }
            $data['INDEXED_FILE_TYPES'][$filetype] = ($ison) ?
                "checked='checked'" :'';
        }
        $seed_info["indexed_file_types"]["extensions"] = $filetypes;

        $data['CLASSIFIERS'] = array();
        $data['RANKERS'] = array();
        $active_classifiers = array();
        $active_rankers = array();

        foreach (Classifier::getClassifierList() as $classifier) {
            $label = $classifier->class_label;
            $ison = false;
            if (isset($_REQUEST['classifier']) && !$loaded) {
                if (isset($_REQUEST['classifier'][$label])) {
                    $ison = true;
                }
            } else if ($loaded || !isset($_REQUEST['posted']) &&
                isset($seed_info['active_classifiers']['label'])) {
                if (in_array($label,
                    $seed_info['active_classifiers']['label'])) {
                    $ison = true;
                }
            }
            if ($ison) {
                $data['CLASSIFIERS'][$label] = 'checked="checked"';
                $active_classifiers[] = $label;
            } else {
                $data['CLASSIFIERS'][$label] = '';
            }
            $ison = false;
            if (isset($_REQUEST['ranker']) && !$loaded) {
                if (isset($_REQUEST['ranker'][$label])) {
                    $ison = true;
                }
            } else if ($loaded || !isset($_REQUEST['posted']) &&
                isset($seed_info['active_rankers']['label'])) {
                if (isset($seed_info['active_rankers']['label']) &&
                    in_array($label, $seed_info['active_rankers']['label'])) {
                    $ison = true;
                }
            }
            if ($ison) {
                $data['RANKERS'][$label] = 'checked="checked"';
                $active_rankers[] = $label;
            } else {
                $data['RANKERS'][$label] = '';
            }
        }
        $seed_info['active_classifiers']['label'] = $active_classifiers;
        $seed_info['active_rankers']['label'] = $active_rankers;

        if(isset($seed_info['page_rules']['rule'])) {
            if(isset($seed_info['page_rules']['rule']['rule'])) {
                $data['page_rules'] = $this->convertArrayLines(
                    $seed_info['page_rules']['rule']['rule']);
            } else {
                $data['page_rules'] = $this->convertArrayLines(
                    $seed_info['page_rules']['rule']);
            }
        } else {
            $data['page_rules'] = "";
        }
        $allowed_options = array('crawl_time', 'search_time', 'test_options');
        if(isset($_REQUEST['option_type']) &&
            in_array($_REQUEST['option_type'], $allowed_options)) {
            $data['option_type'] = $_REQUEST['option_type'];
        } else {
            $data['option_type'] = 'crawl_time';
        }
        if($data['option_type'] == 'crawl_time') {
            $data['crawl_time_active'] = "active";
            $data['search_time_active'] = "";
            $data['test_options_active'] = "";
            $data['SCRIPT'] .= "\nswitchTab('crawltimetab',".
                "'searchtimetab', 'testoptionstab')\n";
        } else if($data['option_type'] == 'search_time') {
            $data['search_time_active'] = "active";
            $data['crawl_time_active'] = "";
            $data['test_options_active'] = "";
            $data['SCRIPT'] .= "\nswitchTab('searchtimetab',".
                "'crawltimetab', 'testoptionstab')\n";
        } else {
            $data['search_time_active'] = "";
            $data['crawl_time_active'] = "";
            $data['test_options_active'] = "active";
            $data['SCRIPT'] .= "\nswitchTab('testoptionstab',".
                "'crawltimetab', 'searchtimetab');\n";
        }

        $this->crawlModel->setSeedInfo($seed_info);
        if($change == true && $data['option_type'] != 'test_options') {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('admin_controller_page_options_updated')."</h1>')";
        }
        $test_processors = array(
            "text/html" => "HtmlProcessor",
            "text/asp" => "HtmlProcessor",
            "text/xml" => "XmlProcessor",
            "text/robot" => "RobotProcessor",
            "application/xml" => "XmlProcessor",
            "application/xhtml+xml" => "HtmlProcessor",
            "application/rss+xml" => "RssProcessor",
            "application/atom+xml" => "RssProcessor",
            "text/rtf" => "RtfProcessor",
            "text/plain" => "TextProcessor",
            "text/csv" => "TextProcessor",
            "text/tab-separated-values" => "TextProcessor",
        );
        $data['MIME_TYPES'] = array_keys($test_processors);
        $data['page_type'] = "text/html";
        if(isset($_REQUEST['page_type']) && in_array($_REQUEST['page_type'],
            $data['MIME_TYPES'])) {
            $data['page_type'] = $_REQUEST['page_type'];
        }
        $data['TESTPAGE'] = (isset($_REQUEST['TESTPAGE'])) ?
            $this->clean($_REQUEST['TESTPAGE'], 'string') : "";
        if($data['option_type'] == 'test_options' && $data['TESTPAGE'] !="") {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('admin_controller_page_options_running_tests')."</h1>')";
            $site = array();
            $site[self::ENCODING] = "UTF-8";
            $site[self::URL] = "http://test-site.yioop.com/";
            $site[self::IP_ADDRESSES] = array("1.1.1.1");
            $site[self::HTTP_CODE] = 200;
            $site[self::MODIFIED] = date("U", time());
            $site[self::TIMESTAMP] = time();
            $site[self::TYPE] = "text/html";
            $site[self::HEADER] = "page options test extractor";
            $site[self::SERVER] = "unknown";
            $site[self::SERVER_VERSION] = "unknown";
            $site[self::OPERATING_SYSTEM] = "unknown";
            $site[self::LANG] = 'en';
            $site[self::JUST_METAS] = false;
            if(isset($_REQUEST['page_type']) &&
                in_array($_REQUEST['page_type'], $data['MIME_TYPES'])) {
                $site[self::TYPE] = $_REQUEST['page_type'];
            }
            if($site[self::TYPE] == 'text/html') {
                $site[self::ENCODING] =
                    guessEncodingHtml($_REQUEST['TESTPAGE']);
            }
            $processor_name = $test_processors[$site[self::TYPE]];
            $plugin_processors = array();
            if (isset($seed_info['indexing_plugins']['plugins'])) {
                foreach($seed_info['indexing_plugins']['plugins'] as $plugin) {
                    $plugin_name = $plugin."Plugin";
                    $supported_processors = $plugin_name::getProcessors();
                    foreach($supported_processors as $supported_processor) {
                        if ($supported_processor == $processor_name) {
                            $plugin_processors[] = $plugin_name;
                        }
                    }
                }
            }
            $page_processor = new $processor_name($plugin_processors,
                $seed_info["general"]["max_description_len"]);
            $doc_info = $page_processor->handle($_REQUEST['TESTPAGE'],
                $site[self::URL]);

            if($page_processor != "RobotProcessor" &&
                !isset($doc_info[self::JUST_METAS])) {
                $doc_info[self::LINKS] = UrlParser::pruneLinks(
                    $doc_info[self::LINKS]);
            }
            foreach($doc_info as $key => $value) {
                $site[$key] = $value;
            }
            if(isset($site[self::PAGE])) {
                unset($site[self::PAGE]);
            }
            if(isset($site[self::ROBOT_PATHS])) {
                $site[self::JUST_METAS] = true;
            }
            $reflect = new ReflectionClass("CrawlConstants");
            $crawl_constants = $reflect->getConstants();
            $crawl_keys = array_keys($crawl_constants);
            $crawl_values = array_values($crawl_constants);
            $inverse_constants = array_combine($crawl_values, $crawl_keys);
            $after_process = array();
            foreach($site as $key => $value) {
                $out_key = (isset($inverse_constants[$key])) ?
                    $inverse_constants[$key] : $key;
                $after_process[$out_key] = $value;
            }
            $data["AFTER_PAGE_PROCESS"] = wordwrap($this->clean(
                print_r($after_process, true), "string"), 75, "\n", true);
            $rule_string = implode("\n", $seed_info['page_rules']['rule']);
            $rule_string = html_entity_decode($rule_string, ENT_QUOTES);
            $page_rule_parser =
                new PageRuleParser($rule_string);
            $page_rule_parser->executeRuleTrees($site);
            $after_process = array();
            foreach($site as $key => $value) {
                $out_key = (isset($inverse_constants[$key])) ?
                    $inverse_constants[$key] : $key;
                $after_process[$out_key] = $value;
            }
            $data["AFTER_RULE_PROCESS"] = wordwrap($this->clean(
                print_r($after_process, true), "string"), 75, "\n", true);
            $lang = NULL;
            if(isset($site[self::LANG])) {
                $lang = $site[self::LANG];
            }
            $meta_ids = PhraseParser::calculateMetas($site);
            if(!$site[self::JUST_METAS]) {
                $host_words = UrlParser::getWordsIfHostUrl($site[self::URL]);
                $path_words = UrlParser::getWordsLastPathPartUrl(
                    $site[self::URL]);
                $phrase_string = $host_words." ".$site[self::TITLE] .
                    " ". $path_words . " ". $site[self::DESCRIPTION];
                if($site[self::TITLE] != "" ) {
                    $lang = guessLocaleFromString($site[self::TITLE], $lang);
                } else {
                    $lang = guessLocaleFromString(
                        substr($site[self::DESCRIPTION], 0,
                        AD_HOC_TITLE_LENGTH), $lang);
                }
                $word_lists =
                    PhraseParser::extractPhrasesInLists($phrase_string,
                        $lang);
                $len = strlen($phrase_string);
                if(PhraseParser::computeSafeSearchScore($word_lists, $len) <
                    0.012) {
                    $meta_ids[] = "safe:true";
                    $safe = true;
                } else {
                    $meta_ids[] = "safe:false";
                    $safe = false;
                }
            }
            if(!isset($word_lists)) {
                $word_lists = array();
            }
            $data["EXTRACTED_WORDS"] = wordwrap($this->clean(
                print_r($word_lists, true), "string"), 75, "\n", true);;
            $data["EXTRACTED_META_WORDS"] = wordwrap($this->clean(
                print_r($meta_ids, true), "string"), 75, "\n", true);
        }
        return $data;
    }

    /**
     * Handles admin requests for creating, editing, and deleting classifiers.
     *
     * This activity implements the logic for the page that lists existing
     * classifiers, including the actions that can be performed on them.
     */
    function manageClassifiers()
    {
        $possible_arguments = array('createclassifier', 'editclassifier',
            'finalizeclassifier', 'deleteclassifier');

        $data['ELEMENT'] = 'manageclassifiersElement';
        $data['SCRIPT'] = '';

        $machine_urls = $this->machineModel->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if ($num_machines < 1 || ($num_machines == 1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = NULL;
        }

        $data['leftorright'] =
            (getLocaleDirection() == 'ltr') ? 'right': 'left';

        $classifiers = Classifier::getClassifierList();
        $start_finalizing = false;
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            $label = $this->clean($_REQUEST['class_label'], 'string');
            $label = Classifier::cleanLabel($label);
            switch ($_REQUEST['arg'])
            {
                case 'createclassifier':
                    if (!isset($classifiers[$label])) {
                        $classifier = new Classifier($label);
                        Classifier::setClassifier($classifier);
                        $classifiers[$label] = $classifier;
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('admin_controller_new_classifier').'</h1>\');';
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('admin_controller_classifier_exists').
                            '</h1>\');';
                    }
                    break;

                case 'editclassifier':
                    if (isset($classifiers[$label])) {
                        $data['class_label'] = $label;
                        $this->editClassifier($data, $classifiers,
                            $machine_urls);
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('admin_controller_no_classifier').
                            '</h1>\');';
                    }
                    break;

                case 'finalizeclassifier':
                    /*
                       Finalizing is too expensive to be done directly in the
                       controller that responds to the web request. Instead, a
                       daemon is launched to finalize the classifier
                       asynchronously and save it back to disk when it's done.
                       In the meantime, a flag is set to indicate the current
                       finalizing state.
                     */
                    CrawlDaemon::start("classifier_trainer", $label, '', -1);
                    $classifier = $classifiers[$label];
                    $classifier->finalized = Classifier::FINALIZING;
                    $start_finalizing = true;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                        tl('admin_controller_finalizing_classifier').
                        '</h1>\');';
                    break;

                case 'deleteclassifier':
                    /*
                       In addition to deleting the classifier, we also want to
                       delete the associated crawl mix (if one exists) used to
                       iterate over existing indexes in search of new training
                       examples.
                     */
                    if (isset($classifiers[$label])) {
                        unset($classifiers[$label]);
                        Classifier::deleteClassifier($label);
                        $mix_name = Classifier::getCrawlMixName($label);
                        $mix_time = $this->crawlModel->getCrawlMixTimestamp(
                            $mix_name);
                        if ($mix_time) {
                            $this->crawlModel->deleteCrawlMixIteratorState(
                                $mix_time);
                            $this->crawlModel->deleteCrawlMix($mix_time);
                        }
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('admin_controller_classifier_deleted').
                            '</h1>\');';
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                            tl('admin_controller_no_classifier').
                            '</h1>\');';
                    }
                    break;
            }
        }

        $data['classifiers'] = $classifiers;
        $data['reload'] = false;
        foreach($classifiers as $label => $classifier) {
            if($classifier->finalized == Classifier::FINALIZING) {
                $data['reload'] = true;
                break;
            }
        }
        if($data['reload'] && !$start_finalizing) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                tl('admin_controller_finalizing_classifier'). '</h1>\');';
        }
        return $data;
    }

    /**
     * Handles the particulars of editing a classifier, which includes changing
     * its label and adding training examples.
     *
     * This activity directly handles changing the class label, but not adding
     * training examples. The latter activity is done interactively without
     * reloading the page via XmlHttpRequests, coordinated by the classifier
     * controller dedicated to that task.
     *
     * @param array $data data to be passed on to the view
     * @param array $classifiers map from class labels to their associated
     *     classifiers
     * @param array $machine_urls string urls of machines managed by this
     *     Yioop name server
     */
    function editClassifier(&$data, $classifiers, $machine_urls)
    {
        $data['ELEMENT'] = 'editclassifierElement';
        $data['INCLUDE_SCRIPTS'] = array('classifiers');

        // We want recrawls, but not archive crawls.
        $crawls = $this->crawlModel->getCrawlList(false, true, $machine_urls);
        $data['CRAWLS'] = $crawls;

        $classifier = $classifiers[$data['class_label']];

        if (isset($_REQUEST['update']) && $_REQUEST['update'] == 'update') {
            if (isset($_REQUEST['rename_label'])) {
                $new_label = $this->clean($_REQUEST['rename_label'], 'string');
                $new_label = preg_replace('/[^a-zA-Z0-9_]/', '', $new_label);
                if (!isset($classifiers[$new_label])) {
                    $old_label = $classifier->class_label;
                    $classifier->class_label = $new_label;
                    Classifier::setClassifier($classifier);
                    Classifier::deleteClassifier($old_label);
                    $data['class_label'] = $new_label;
                } else {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                        tl('admin_controller_classifier_exists').
                        '</h1>\');';
                }
            }
        }

        $data['classifier'] = $classifier;

        // Translations for the classification javascript.
        $data['SCRIPT'] .= "window.tl = {".
            'editclassifier_load_failed:"'.
                tl('editclassifier_load_failed').'",'.
            'editclassifier_loading:"'.
                tl('editclassifier_loading').'",'.
            'editclassifier_added_examples:"'.
                tl('editclassifier_added_examples').'",'.
            'editclassifier_label_update_failed:"'.
                tl('editclassifier_label_update_failed').'",'.
            'editclassifier_updating:"'.
                tl('editclassifier_updating').'",'.
            'editclassifier_acc_update_failed:"'.
                tl('editclassifier_acc_update_failed').'",'.
            'editclassifier_na:"'.
                tl('editclassifier_na').'",'.
            'editclassifier_no_docs:"'.
                tl('editclassifier_no_docs').'",'.
            'editclassifier_num_docs:"'.
                tl('editclassifier_num_docs').'",'.
            'editclassifier_in_class:"'.
                tl('editclassifier_in_class').'",'.
            'editclassifier_not_in_class:"'.
                tl('editclassifier_not_in_class').'",'.
            'editclassifier_skip:"'.
                tl('editclassifier_skip').'",'.
            'editclassifier_prediction:"'.
                tl('editclassifier_prediction').'",'.
            'editclassifier_scores:"'.
                tl('editclassifier_scores').'"'.
            '};';

        /*
           We pass along authentication information to the client, so that it
           can authenticate any XmlHttpRequests that it makes in order to label
           documents.
         */
        $time = strval(time());
        $session = md5($time.AUTH_KEY);
        $data['SCRIPT'] .=
            "Classifier.initialize(".
                "'{$data['class_label']}',".
                "'{$session}',".
                "'{$time}');";
    }

    /**
     * Handles admin request related to the search filter activity
     *
     * This activity allows a user to specify hosts whose web pages are to be
     * filtered out the search results
     *
     * @return array $data info about the groups and their contents for a
     *      particular crawl mix
     */
    function resultsEditor()
    {
        $data["ELEMENT"] = "resultseditorElement";
        $data['SCRIPT'] = "";

        if(isset($_REQUEST['disallowed_sites'])) {
            $sites = $this->convertStringCleanArray(
                $_REQUEST['disallowed_sites']);
            $disallowed_sites = array();
            foreach($sites as $site) {
                $site = UrlParser::getHost($site);
                if(strlen($site) > 0) {
                    $disallowed_sites[] = $site."/";
                }
            }
            $data['disallowed_sites'] = implode("\n", $disallowed_sites);
            $this->searchfiltersModel->set($disallowed_sites);
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('admin_controller_results_editor_update')."</h1>')";
        }
        if(!isset($data['disallowed_sites'])) {
            $data['disallowed_sites'] =
                implode("\n", $this->searchfiltersModel->getUrls());
        }
        foreach (array("URL", "TITLE", "DESCRIPTION") as $field) {
            $data[$field] = (isset($_REQUEST[$field])) ?
                $this->clean($_REQUEST[$field], "string") :
                 ((isset($data[$field]) ) ? $data[$field] : "");
        }
        if($data["URL"] != "") {
            $data["URL"] = UrlParser::canonicalLink($data["URL"],"");
        }
        $tmp = tl('admin_controller_edited_pages');
        $data["URL_LIST"] = array ($tmp => $tmp);
        $summaries = $this->searchfiltersModel->getEditedPageSummaries();
        foreach($summaries as $hash => $summary) {
            $data["URL_LIST"][$summary[self::URL]] = $summary[self::URL];
        }
        if(isset($_REQUEST['arg']) ) {
            switch($_REQUEST['arg'])
            {
                case "save_page":
                    $missing_page_field = ($data["URL"] == "") ? true: false;
                    if($missing_page_field) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_results_editor_need_url').
                            "</h1>')";
                    } else {
                        $this->searchfiltersModel->updateResultPage(
                            $data["URL"], $data["TITLE"], $data["DESCRIPTION"]);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_results_editor_page_updated').
                            "</h1>')";
                    }
                break;
                case "load_url":
                    $hash_url = crawlHash($_REQUEST['LOAD_URL'], true);
                    if(isset($summaries[$hash_url])) {
                        $data["URL"] = $this->clean($_REQUEST['LOAD_URL'],
                            "string");
                        $data["TITLE"] = $summaries[$hash_url][self::TITLE];
                        $data["DESCRIPTION"] = $summaries[$hash_url][
                            self::DESCRIPTION];
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_results_editor_page_loaded').
                            "</h1>')";
                    }
                break;
            }
        }

        return $data;
    }


    /**
     * Handles admin request related to the managing the machines which perform
     *  crawls
     *
     * With this activity an admin can add/delete machines to manage. For each
     * managed machine, the admin can stop and start fetchers/queue_servers
     * as well as look at their log files
     *
     * @return array $data MACHINES, their MACHINE_NAMES, data for
     *      FETCHER_NUMBERS drop-down
     */
    function manageMachines()
    {
        $data = array();
        $data["ELEMENT"] = "managemachinesElement";
        $possible_arguments = array("addmachine", "deletemachine",
            "newsmode", "log", "update");
        $data['SCRIPT'] = "doUpdate();";
        $data["leftorright"]=(getLocaleDirection() == 'ltr') ? "right": "left";
        $data['MACHINES'] = array();
        $data['MACHINE_NAMES'] = array();
        $urls = array();
        $data['FETCHER_NUMBERS'] = array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            16 => 16
        );
        $machines = $this->machineModel->getMachineList();
        $tmp = tl('admin_controller_select_machine');
        $data['DELETABLE_MACHINES'] = array(
            $tmp => $tmp
        );
        $data['REPLICATABLE_MACHINES'] = array(
            $tmp => $tmp
        );
        foreach($machines as $machine) {
            $data['MACHINE_NAMES'][] = $machine["NAME"];
            $urls[] = $machine["URL"];
            $data['DELETABLE_MACHINES'][$machine["NAME"]] = $machine["NAME"];
            if(!isset($machine["PARENT"]) || $machine["PARENT"] == "") {
                $data['REPLICATABLE_MACHINES'][$machine["NAME"]]
                    = $machine["NAME"];
            }
        }

        if(!isset($_REQUEST["has_queue_server"]) ||
            isset($_REQUEST['is_replica'])) {
            $_REQUEST["has_queue_server"] = false;
        }
        if(isset($_REQUEST['is_replica'])) {
            $_REQUEST['num_fetchers'] = 0;
        } else {
            $_REQUEST['parent'] = "";
        }
        $request_fields = array(
            "name" => "string",
            "url" => "string",
            "has_queue_server" => "bool",
            "num_fetchers" => "int",
            "parent" => "string"
        );
        $r = array();

        $allset = true;
        foreach($request_fields as $field => $type) {
            if(isset($_REQUEST[$field])) {
                $r[$field] = $this->clean($_REQUEST[$field], $type);
                if($field == "url" && $r[$field][strlen($r[$field])-1]
                    != "/") {
                    $r[$field] .= "/";
                }
            } else {
                $allset = false;
            }
        }
        if(isset($r["num_fetchers"]) &&
            in_array($r["num_fetchers"], $data['FETCHER_NUMBERS'])) {
            $data['FETCHER_NUMBER'] = $r["num_fetchers"];
        } else {
            $data['FETCHER_NUMBER'] = 0;
            if(isset($r["num_fetchers"])) {
                $r["num_fetchers"] = 0;
            }
        }
        $machine_exists = (isset($r["name"]) && in_array($r["name"],
            $data['MACHINE_NAMES']) ) || (isset($r["url"]) &&
            in_array($r["url"], $urls));

        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "addmachine":
                    if($allset == true && !$machine_exists) {
                        $this->machineModel->addMachine(
                            $r["name"], $r["url"], $r["has_queue_server"],
                            $r["num_fetchers"], $r["parent"]);

                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_machine_added').
                            "</h1>');";
                        $data['MACHINE_NAMES'][] = $r["name"];
                        $data['DELETABLE_MACHINES'][$r["name"]] = $r["name"];
                        sort($data['MACHINE_NAMES']);
                    } else if ($allset && $machine_exists ) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_machine_exists').
                            "</h1>');";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_machine_incomplete').
                            "</h1>');";
                    }
                break;

                case "deletemachine":
                    if(!isset($r["name"]) ||
                        !in_array($r["name"], $data['MACHINE_NAMES'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_machine_doesnt_exists').
                            "</h1>');";
                    } else {
                        $machines = $this->machineModel->getMachineStatuses();
                        $service_in_use = false;
                        foreach($machines as $machine) {
                            if($machine['NAME'] == $r["name"]) {
                                if($machine['STATUSES'] != array()) {
                                    $service_in_use = true;
                                    break;
                                } else {
                                    break;
                                }
                            }
                        }
                        if($service_in_use) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                           tl('admin_controller_stop_service_first')."</h1>');";
                            break;
                        }
                        $this->machineModel->deleteMachine($r["name"]);
                        $tmp_array = array($r["name"]);
                        $diff =
                            array_diff($data['MACHINE_NAMES'],  $tmp_array);
                        $data['MACHINE_NAMES'] = array_merge($diff);
                        $tmp_array = array($r["name"] => $r["name"]);
                        $diff =
                            array_diff($data['DELETABLE_MACHINES'], $tmp_array);
                        $data['DELETABLE_MACHINES'] = array_merge($diff);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_machine_deleted')."</h1>');";
                    }
                break;

                case "newsmode":
                    $profile =  $this->profileModel->getProfile(WORK_DIRECTORY);
                    $news_modes = array("news_off", "news_web", "news_process");
                    if(isset($_REQUEST['news_mode']) && in_array(
                        $_REQUEST['news_mode'], $news_modes)) {
                        $profile["NEWS_MODE"] = $_REQUEST['news_mode'];
                        if($profile["NEWS_MODE"] != "news_process") {
                            CrawlDaemon::stop("news_updater", "", false);
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('admin_controller_news_mode_updated').
                                "</h1>');";
                        } else {
                            CrawlDaemon::start("news_updater", 'none', "",
                                -1);
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('admin_controller_news_mode_updated').
                                "</h1>');";
                        }
                        $this->profileModel->updateProfile(
                            WORK_DIRECTORY, array(), $profile);
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_news_update_failed').
                            "</h1>');";
                    }
                break;

                case "log":
                    if(isset($_REQUEST["fetcher_num"])) {
                        $r["fetcher_num"] =
                            $this->clean($_REQUEST["fetcher_num"], "int");
                    }
                    if(isset($_REQUEST["mirror_name"])) {
                        $r["mirror_name"] =
                            $this->clean($_REQUEST["mirror_name"], "string");
                    }
                    if(isset($_REQUEST["time"])) {
                        $data["time"] =
                            $this->clean($_REQUEST["time"], "int") + 30;
                    } else {
                        $data["time"] = 30;
                    }
                    if(isset($_REQUEST["NO_REFRESH"])) {
                        $data["NO_REFRESH"] = $this->clean(
                            $_REQUEST["NO_REFRESH"], "bool");
                    } else {
                        $data["NO_REFRESH"] = false;
                    }
                    $data["ELEMENT"] = "machinelogElement";
                    $filter= "";
                    if(isset($_REQUEST['f'])) {
                        $filter =
                            $this->clean($_REQUEST['f'], "string");
                    }
                    $data['filter'] = $filter;
                    $data["REFRESH_LOG"] = "&time=". $data["time"];
                    $data["LOG_TYPE"] = "";
                    if(isset($r['fetcher_num']) && isset($r['name'])) {
                        $data["LOG_FILE_DATA"] = $this->machineModel->getLog(
                            $r["name"], $r["fetcher_num"], $filter);
                        $data["LOG_TYPE"] = $r['name'].
                            " fetcher ".$r["fetcher_num"];
                        $data["REFRESH_LOG"] .= "&arg=log&name=".$r['name'].
                            "&fetcher_num=".$r['fetcher_num'];
                    } else if(isset($r["mirror_name"])) {
                        $data["LOG_TYPE"] = $r['mirror_name']." mirror";
                        $data["LOG_FILE_DATA"] = $this->machineModel->getLog(
                            $r["mirror_name"], NULL, $filter,  true);
                    } else if(isset($r['name'])) {
                        $data["LOG_TYPE"] = $r['name']." queue_server";
                        if($r['name'] == "news") {
                            $data["LOG_TYPE"] = "Name Server News Updater";
                        }
                        $data["LOG_FILE_DATA"] = $this->machineModel->getLog(
                            $r["name"], NULL, $filter);
                        $data["REFRESH_LOG"] .=
                            "&arg=log&name=".$r['name'];
                    }
                    if($data["time"] >= 1200) {
                        $data["REFRESH_LOG"] = "";
                    }

                    if(!isset($data["LOG_FILE_DATA"])
                        || $data["LOG_FILE_DATA"] == ""){
                        $data["LOG_FILE_DATA"] =
                            tl('admin_controller_no_machine_log');
                    }
                    $lines =array_reverse(explode("\n",$data["LOG_FILE_DATA"]));
                    $data["LOG_FILE_DATA"] = implode("\n", $lines);
                break;

                case "update":
                    if(isset($_REQUEST["fetcher_num"])) {
                        $r["fetcher_num"] =
                            $this->clean($_REQUEST["fetcher_num"], "int");
                    } else {
                        $r["fetcher_num"] = NULL;
                    }
                    $available_actions = array("start", "stop",
                        "mirror_start", "mirror_stop");
                    if(isset($r["name"]) && isset($_REQUEST["action"]) &&
                        in_array($_REQUEST["action"], $available_actions)) {
                        $action = $_REQUEST["action"];
                        $is_mirror = false;
                        if($action == "mirror_start") {
                            $action = "start";
                            $is_mirror = true;
                        } else if ($action == "mirror_stop") {
                            $action = "stop";
                            $is_mirror = true;
                        }
                        $this->machineModel->update($r["name"],
                            $action, $r["fetcher_num"], $is_mirror);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_machine_servers_updated').
                            "</h1>');";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_machine_no_action').
                            "</h1>');";
                    }

                break;

            }
        }
        if(!isset($_REQUEST['arg']) || $_REQUEST['arg'] != 'log') {
            $data['SCRIPT'] .= "toggleReplica(false);";
        }
        return $data;
    }

    /**
     * Handles admin request related to the manage locale activity
     *
     * The manage locale activity allows a user to add/delete locales, view
     * statistics about a locale as well as edit the string for that locale
     *
     * @return array $data info about current locales, statistics for each
     *      locale as well as potentially the currently set string of a
     *      locale and any messages about the success or failure of a
     *      sub activity.
     */
    function manageLocales()
    {
        $possible_arguments = array("addlocale", "deletelocale", "editlocale");

        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "managelocalesElement";

        $data["LOCALES"] = $this->localeModel->getLocaleList();
        $data['LOCALE_NAMES'][-1] = tl('admin_controller_select_localename');

        $locale_ids = array();

        foreach ($data["LOCALES"] as $locale) {
            $data["LOCALE_NAMES"][$locale["LOCALE_TAG"]] =
                $locale["LOCALE_NAME"];
            $locale_ids[] = $locale["LOCALE_TAG"];
        }

        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            if(isset($_REQUEST['localename'])) {
                $localename = $this->clean($_REQUEST['localename'], "string" );
            } else {
                $localename = "";
            }
            if(isset($_REQUEST['localetag'])) {
                $localetag = $this->clean($_REQUEST['localetag'], "string" );
            } else {
                $localetag = "";
            }
            if(isset($_REQUEST['writingmode'])) {
                $writingmode =
                    $this->clean($_REQUEST['writingmode'], "string" );
            } else {
                $writingmode = "";
            }
            if(isset($_REQUEST['selectlocale'])) {
                $select_locale =
                    $this->clean($_REQUEST['selectlocale'], "string" );
            } else {
                $select_locale = "";
            }

            switch($_REQUEST['arg'])
            {
                case "addlocale":
                    $this->localeModel->addLocale(
                        $localename, $localetag, $writingmode);
                    $this->localeModel->extractMergeLocales();
                    $data["LOCALES"] = $this->localeModel->getLocaleList();
                    $data['LOCALE_NAMES'][$localetag] = $localename;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_locale_added')."</h1>')";
                break;

                case "deletelocale":

                    if(!in_array($select_locale, $locale_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_localename_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $this->localeModel->deleteLocale($select_locale);
                    $data["LOCALES"] = $this->localeModel->getLocaleList();
                    unset($data['LOCALE_NAMES'][$select_locale]);

                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_localename_deleted')."</h1>')";
                break;

                case "editlocale":
                    if(!isset($select_locale)) break;
                    $data["leftorright"] =
                        (getLocaleDirection() == 'ltr') ? "right": "left";
                    $data["ELEMENT"] = "editlocalesElement";
                    $data['STATIC_PAGES'][-1]=
                        tl('admin_controller_select_staticpages');
                    $data['STATIC_PAGES'] +=
                        $this->localeModel->getStaticPageList($select_locale);
                    $data['CURRENT_LOCALE_NAME'] =
                        $data['LOCALE_NAMES'][$select_locale];
                    $data['CURRENT_LOCALE_TAG'] = $select_locale;
                    $tmp_pages = $data['STATIC_PAGES'];
                    array_shift($tmp_pages);
                    $page_keys = array_keys($tmp_pages);
                    if(isset($_REQUEST['static_page']) &&
                        in_array($_REQUEST['static_page'], $page_keys)) {
                        $data["ELEMENT"] = "editstaticElement";
                        $data['STATIC_PAGE'] = $_REQUEST['static_page'];
                        if(isset($_REQUEST['PAGE_DATA'])) {
                            $this->localeModel->setStaticPage(
                                $_REQUEST['static_page'],
                                $data['CURRENT_LOCALE_TAG'],
                                $_REQUEST['PAGE_DATA']);
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('admin_controller_staticpage_updated').
                                "</h1>')";
                        }
                        $data['PAGE_NAME'] =
                            $data['STATIC_PAGES'][$data['STATIC_PAGE']];
                        $data['PAGE_DATA'] =
                            $this->localeModel->getStaticPage(
                                $_REQUEST['static_page'],
                                $data['CURRENT_LOCALE_TAG']);
                        /*since page data can contain tags we clean it
                          htmlentities it just before displaying*/
                        $data['PAGE_DATA'] = $this->clean($data['PAGE_DATA'],
                            "string");
                        break;
                    }
                    $data['SCRIPT'] .= "selectPage = elt('static-pages');".
                        "selectPage.onchange = submitStaticPageForm;";
                    if(isset($_REQUEST['STRINGS'])) {
                        $safe_strings = array();
                        foreach($_REQUEST['STRINGS'] as $key => $value) {
                            $clean_key = $this->clean($key, "string" );
                            $clean_value = $this->clean($value, "string" );
                            $safe_strings[$clean_key] = $clean_value;
                        }
                        $this->localeModel->updateStringData(
                            $select_locale, $safe_strings);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_localestrings_updated').
                            "</h1>')";
                    } else {
                        $this->localeModel->extractMergeLocales();
                    }
                    $data['STRINGS'] =
                        $this->localeModel->getStringData($select_locale);
                    $data['DEFAULT_STRINGS'] =
                        $this->localeModel->getStringData(DEFAULT_LOCALE);
                break;
            }
        }
        return $data;
    }

    /**
     * Checks to see if the current machine has php configured in a way
     * Yioop! can run.
     *
     * @return string a message indicatign which required and optional
     *      components are missing; or "Passed" if nothing missing.
     */
     function systemCheck()
     {
        $required_items = array(
            array("name" => "Multi-Curl",
                "check"=>"curl_multi_init", "type"=>"function"),
            array("name" => "GD Graphics Library",
                "check"=>"imagecreate", "type"=>"function"),
            array("name" => "SQLite3 Library",
                "check"=>"SQLite3|PDO", "type"=>"class"),
            array("name" => "Multibyte Character Library",
                "check"=>"mb_internal_encoding", "type"=>"function"),
        );
        $optional_items = array(
         /* as an example of what this array could contain...
            array("name" => "Memcache", "check" => "Memcache",
                "type"=> "class"), */
        );

        $missing_required = "";
        $comma = "";
        foreach($required_items as $item) {
            $check_function = $item["type"]."_exists";
            $check_parts = explode("|", $item["check"]);
            $check_flag = true;
            foreach($check_parts as $check) {
                if($check_function($check)) {
                    $check_flag = false;
                }
            }
            if($check_flag) {
                $missing_required .= $comma.$item["name"];
                $comma = ", ";
            }
        }
        if(!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
            $missing_required .= $comma.tl("admin_controller_php_version");
            $comma = ", ";
        }

        $out = "";
        $br = "";

        if(!is_writable(BASE_DIR."/configs/config.php")) {
            $out .= tl('admin_controller_no_write_config_php');
            $br = "<br />";
        }

        if(defined(WORK_DIRECTORY) && !is_writable(WORK_DIRECTORY)) {
            $out .= $br. tl('admin_controller_no_write_work_dir');
            $br = "<br />";
        }

        if(intval(ini_get("post_max_size")) < 2) {
            $out .= $br. tl('admin_controller_post_size_small');
            $br = "<br />";
        }

        if($missing_required != "") {
            $out .= $br.
                tl('admin_controller_missing_required', $missing_required);
            $br = "<br />";
        }

        $missing_optional = "";
        $comma = "";
        foreach($optional_items as $item) {
            $check_function = $item["type"]."_exists";
            $check_parts = explode("|", $item["check"]);
            $check_flag = true;
            foreach($check_parts as $check) {
                if($check_function($check)) {
                    $check_flag = false;
                }
            }
            if($check_flag) {
                $missing_optional .= $comma.$item["name"];
                $comma = ", ";
            }
        }

        if($missing_optional != "") {
            $out .= $br.
                tl('admin_controller_missing_optional', $missing_optional);
            $br = "<br />";
        }

        if($out == "") {
            $out = tl('admin_controller_check_passed');
        } else {
            $out = "<span class='red'>$out</span>";
        }
        if(file_exists(BASE_DIR."/configs/local_config.php")) {
            $out .= "<br />".tl('admin_controller_using_local_config');
        }

        return $out;

     }

    /**
     * Handles admin request related to the search sources activity
      *
     * The search sources activity allows a user to add/delete search sources
     * for video and news, it also allows a user to control which subsearches
     * appear on the SearchView page
     *
     * @return array $data info about current search sources, and current
     *      sub-searches
     */
    function searchSources()
    {
        $possible_arguments = array("addsource", "deletesource",
            "addsubsearch", "deletesubsearch");

        $data = array();
        $data["ELEMENT"] = "searchsourcesElement";
        $data['SCRIPT'] = "";
        $data['SOURCE_TYPES'] = array(-1 => tl('admin_controller_media_kind'),
            "video" => tl('admin_controller_video'),
            "rss" => tl('admin_controller_rss_feed'));
        $source_type_flag = false;
        if(isset($_REQUEST['sourcetype']) &&
            in_array($_REQUEST['sourcetype'],
            array_keys($data['SOURCE_TYPES']))) {
            $data['SOURCE_TYPE'] = $_REQUEST['sourcetype'];
            $source_type_flag = true;
        } else {
            $data['SOURCE_TYPE'] = -1;
        }
        $machine_urls = $this->machineModel->getQueueServerUrls();
        $search_lists = $this->crawlModel->getCrawlList(false, true,
            $machine_urls);
        $data["SEARCH_LISTS"] = array(-1 =>
            tl('admin_controller_sources_indexes'));
        foreach($search_lists as $item) {
            $data["SEARCH_LISTS"]["i:".$item["CRAWL_TIME"]] =
                $item["DESCRIPTION"];
        }
        $search_lists=  $this->crawlModel->getMixList();
        foreach($search_lists as $item) {
            $data["SEARCH_LISTS"]["m:".$item["MIX_TIMESTAMP"]] =
                $item["MIX_NAME"];
        }
        $n = NUM_RESULTS_PER_PAGE;
        $data['PER_PAGE'] =
            array($n => $n, 2*$n => 2*$n, 5*$n=> 5*$n, 10*$n=>10*$n);
        if(isset($_REQUEST['perpage']) &&
            in_array($_REQUEST['perpage'], array_keys($data['PER_PAGE']))) {
            $data['PER_PAGE_SELECTED'] = $_REQUEST['perpage'];
        } else {
            $data['PER_PAGE_SELECTED'] = NUM_RESULTS_PER_PAGE;
        }
        $locales = $this->localeModel->getLocaleList();
        $data["LANGUAGES"] = array();
        foreach($locales as $locale) {
            $data["LANGUAGES"][$locale['LOCALE_TAG']] = $locale['LOCALE_NAME'];
        }
        if(isset($_REQUEST['sourcelocaletag']) &&
            in_array($_REQUEST['sourcelocaletag'],
                array_keys($data["LANGUAGES"]))) {
            $data['SOURCE_LOCALE_TAG'] =
                $_REQUEST['sourcelocaletag'];
        } else {
            $data['SOURCE_LOCALE_TAG'] = DEFAULT_LOCALE;
        }

        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "addsource":
                    if(!$source_type_flag) break;
                    $must_have = array("sourcename", "sourcetype",
                        'sourceurl');
                    $to_clean = array_merge($must_have,
                        array('sourcethumbnail','sourcelocaletag'));
                    foreach ($to_clean as $clean_me) {
                        $r[$clean_me] = (isset($_REQUEST[$clean_me])) ?
                            $this->clean($_REQUEST[$clean_me], "string" ) : "";
                        if(in_array($clean_me, $must_have) &&
                            $r[$clean_me] == "" ) break 2;
                    }
                    $this->sourceModel->addMediaSource(
                        $r['sourcename'], $r['sourcetype'], $r['sourceurl'],
                        $r['sourcethumbnail'], $r['sourcelocaletag']);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_media_source_added').
                        "</h1>');";
                break;
                case "deletesource":
                    if(!isset($_REQUEST['ts'])) break;
                    $timestamp = $this->clean($_REQUEST['ts'], "string");
                    $this->sourceModel->deleteMediaSource($timestamp);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_media_source_deleted').
                        "</h1>');";
                break;
                case "addsubsearch":
                    $to_clean = array("foldername", 'indexsource');
                    $must_have = $to_clean;
                    foreach ($to_clean as $clean_me) {
                        $r[$clean_me] = (isset($_REQUEST[$clean_me])) ?
                            $this->clean($_REQUEST[$clean_me], "string" ) : "";
                        if(in_array($clean_me, $must_have) &&
                            $r[$clean_me] == "" ) break 2;
                    }
                    $this->sourceModel->addSubsearch(
                        $r['foldername'], $r['indexsource'],
                        $data['PER_PAGE_SELECTED']);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_subsearch_added').
                        "</h1>');";
                break;
                case "deletesubsearch":
                    if(!isset($_REQUEST['fn'])) break;
                    $folder_name = $this->clean($_REQUEST['fn'], "string");
                    $this->sourceModel->deleteSubsearch($folder_name);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_subsearch_deleted').
                        "</h1>');";
                break;
            }
        }
        $data["MEDIA_SOURCES"] = $this->sourceModel->getMediaSources();
        $subsearches = $this->sourceModel->getSubsearches();
        $data["SUBSEARCHES"] = array();
        foreach($subsearches as $search) {
            if(isset($data["SEARCH_LISTS"][$search['INDEX_IDENTIFIER']])) {
                $data["SUBSEARCHES"][] = $search;
            } else {
                $this->sourceModel->deleteSubsearch($search["FOLDER_NAME"]);
            }
        }
        $data['SCRIPT'] .= "source_type = elt('source-type');".
            "source_type.onchange = switchSourceType;".
            "switchSourceType()";
        return $data;
    }

    /**
     * Responsible for handling admin request related to the configure activity
     *
     * The configure activity allows a user to set the work directory for
     * storing data local to this SeekQuarry/Yioop instance. It also allows one
     * to set the default language of the installation, dbms info, robot info,
     * test info, as well as which machine acts as the queue server.
     *
     * @return array $data fields for available language, dbms, etc as well as
     *      results of processing sub activity if any
     */
    function configure()
    {
        $data = array();
        $profile = array();

        $data['SYSTEM_CHECK'] = $this->systemCheck();
        $languages = $this->localeModel->getLocaleList();
        foreach($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] =
                $language['LOCALE_NAME'];
        }
        if(isset($_REQUEST['lang']) && $_REQUEST['lang']) {
            $data['lang'] = $this->clean($_REQUEST['lang'], "string");
            $profile['DEFAULT_LOCALE'] = $data['lang'];
            setLocaleObject($data['lang']);
        }

        $data["ELEMENT"] = "configureElement";
        $data['SCRIPT'] = "";

        $data['PROFILE'] = false;
        if(isset($_REQUEST['WORK_DIRECTORY']) || (defined('WORK_DIRECTORY') &&
            defined('FIX_NAME_SERVER') && FIX_NAME_SERVER) ) {
            if(defined('WORK_DIRECTORY') && defined('FIX_NAME_SERVER')
                && FIX_NAME_SERVER && !isset($_REQUEST['WORK_DIRECTORY'])) {
                $_REQUEST['WORK_DIRECTORY'] = WORK_DIRECTORY;
                $_REQUEST['arg'] = "directory";
                @unlink($_REQUEST['WORK_DIRECTORY']."/profile.php");
            }
            $dir =
                $this->clean($_REQUEST['WORK_DIRECTORY'], "string");
            $data['PROFILE'] = true;
            if(strstr(PHP_OS, "WIN")) {
                //convert to forward slashes so consistent with rest of code
                $dir = str_replace("\\", "/", $dir);
                if($dir[0] != "/" && $dir[1] != ":") {
                    $data['PROFILE'] = false;
                }
            } else if($dir[0] != "/") {
                    $data['PROFILE'] = false;
            }
            if($data['PROFILE'] == false) {
                $data["MESSAGE"] =
                    tl('admin_controller_configure_use_absolute_path');
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    $data["MESSAGE"]. "</h1>');" .
                    "setTimeout('window.location.href= ".
                    "window.location.href', 3000);";
                $data['WORK_DIRECTORY'] = $dir;
                return $data;
            }

            if(strstr($dir."/", BASE_DIR."/")) {
                $data['PROFILE'] = false;
                $data["MESSAGE"] =
                    tl('admin_controller_configure_diff_base_dir');
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    $data["MESSAGE"]. "</h1>');" .
                    "setTimeout('window.location.href= ".
                    "window.location.href', 3000);";
                $data['WORK_DIRECTORY'] = $dir;
                return $data;
            }
            $data['WORK_DIRECTORY'] = $dir;

        } else if (defined("WORK_DIRECTORY") &&  strlen(WORK_DIRECTORY) > 0 &&
            strcmp(realpath(WORK_DIRECTORY), realpath(BASE_DIR)) != 0 &&
            (is_dir(WORK_DIRECTORY) || is_dir(WORK_DIRECTORY."../"))) {
            $data['WORK_DIRECTORY'] = WORK_DIRECTORY;
            $data['PROFILE'] = true;
        }

        $arg = "";
        if(isset($_REQUEST['arg'])) {
            $arg = $_REQUEST['arg'];
        }
        switch($arg)
        {
            case "directory":
                if(!isset($data['WORK_DIRECTORY'])) {break;}
                if($data['PROFILE'] &&
                    file_exists($data['WORK_DIRECTORY']."/profile.php")) {
                    $data = array_merge($data,
                        $this->profileModel->getProfile(
                            $data['WORK_DIRECTORY']));
                    $this->profileModel->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    $data["MESSAGE"] =
                        tl('admin_controller_configure_work_dir_set');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >".
                        $data["MESSAGE"]. "</h1>');setTimeout(".
                        "'window.location.href=window.location.href', 3000);";
                } else if ($data['PROFILE'] &&
                    strlen($data['WORK_DIRECTORY']) > 0) {
                    if($this->profileModel->makeWorkDirectory(
                        $data['WORK_DIRECTORY'])) {
                        $profile['DBMS'] = 'sqlite3';
                        $data['DBMS'] = 'sqlite3';
                        $profile['DB_NAME'] = 'default';
                        $data['DB_NAME'] = 'default';
                        $profile['USER_AGENT_SHORT'] =
                            tl('admin_controller_name_your_bot');
                        $data['USER_AGENT_SHORT'] =
                            $profile['USER_AGENT_SHORT'];
                        $uri = UrlParser::getPath($_SERVER['REQUEST_URI']);
                        $http = (isset($_SERVER['HTTPS'])) ? "https://" :
                            "http://";
                        $profile['NAME_SERVER'] =
                            $http . $_SERVER['SERVER_NAME'] . $uri;
                        $data['NAME_SERVER'] = $profile['NAME_SERVER'];
                        $profile['AUTH_KEY'] = crawlHash(
                            $data['WORK_DIRECTORY'].time());
                        $data['AUTH_KEY'] = $profile['AUTH_KEY'];
                        $robot_instance = str_replace(".", "_",
                            $_SERVER['SERVER_NAME'])."-".time();
                        $profile['ROBOT_INSTANCE'] = $robot_instance;
                        $data['ROBOT_INSTANCE'] = $profile['ROBOT_INSTANCE'];
                        if($this->profileModel->updateProfile(
                            $data['WORK_DIRECTORY'], array(), $profile)) {
                            if((defined('WORK_DIRECTORY') &&
                                $data['WORK_DIRECTORY'] == WORK_DIRECTORY) ||
                                $this->profileModel->setWorkDirectoryConfigFile(
                                $data['WORK_DIRECTORY'])) {
                                $data["MESSAGE"] =
                            tl('admin_controller_configure_work_profile_made');
                                $data['SCRIPT'] .=
                                    "doMessage('<h1 class=\"red\" >".
                                    $data["MESSAGE"]. "</h1>');" .
                                    "setTimeout('window.location.href= ".
                                    "window.location.href', 3000);";
                                $data = array_merge($data,
                                    $this->profileModel->getProfile(
                                        $data['WORK_DIRECTORY']));
                                $data['PROFILE'] = true;
                            } else {
                                $data['PROFILE'] = false;
                        $data["MESSAGE"] =
                            tl('admin_controller_configure_no_set_config');
                                $data['SCRIPT'] .=
                                    "doMessage('<h1 class=\"red\" >".
                                    $data["MESSAGE"] . "</h1>');" .
                                    "setTimeout('window.location.href= ".
                                    "window.location.href', 3000);";
                            }
                        } else {
                            $this->profileModel->setWorkDirectoryConfigFile(
                                $data['WORK_DIRECTORY']);
                            $data['PROFILE'] = false;
                        $data["MESSAGE"] =
                            tl('admin_controller_configure_no_create_profile');
                            $data['SCRIPT'] .=
                                "doMessage('<h1 class=\"red\" >".
                                $data["MESSAGE"].
                                "</h1>'); setTimeout('window.location.href=".
                                "window.location.href', 3000);";
                        }
                    } else {
                        $this->profileModel->setWorkDirectoryConfigFile(
                            $data['WORK_DIRECTORY']);
                        $data["MESSAGE"] =
                            tl('admin_controller_configure_work_dir_invalid');
                        $data['SCRIPT'] .=
                            "doMessage('<h1 class=\"red\" >". $data["MESSAGE"].
                                "</h1>');".
                            "setTimeout('window.location.href=".
                            "window.location.href', 3000);";
                        $data['PROFILE'] = false;
                    }
                } else {
                    $this->profileModel->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    $data["MESSAGE"] =
                        tl('admin_controller_configure_work_dir_invalid');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >". $data["MESSAGE"] .
                            "</h1>');" .
                        "setTimeout('window.location.href=".
                        "window.location.href', 3000);";
                    $data['PROFILE'] = false;
                }
            break;
            case "profile":
                $this->updateProfileFields($data, $profile,
                    array('USE_FILECACHE', 'USE_MEMCACHE', "REGISTRATION_TYPE",
                        "WEB_ACCESS", 'RSS_ACCESS', 'API_ACCESS'));
                $data['DEBUG_LEVEL'] = 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["ERROR_INFO"])) ? ERROR_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["QUERY_INFO"])) ? QUERY_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["TEST_INFO"])) ? TEST_INFO : 0;
                $profile['DEBUG_LEVEL'] = $data['DEBUG_LEVEL'];

                $old_profile =
                    $this->profileModel->getProfile($data['WORK_DIRECTORY']);

                $db_problem = false;
                if((isset($profile['DBMS']) &&
                    $profile['DBMS'] != $old_profile['DBMS']) ||
                    (isset($profile['DB_NAME']) &&
                    $profile['DB_NAME'] != $old_profile['DB_NAME']) ||
                    (isset($profile['DB_HOST']) &&
                    $profile['DB_HOST'] != $old_profile['DB_HOST'])) {
                    if(!$this->profileModel->migrateDatabaseIfNecessary(
                        $profile)) {
                        $db_problem = true;
                    }
                } else if ((isset($profile['DB_USER']) &&
                    $profile['DB_USER'] != $old_profile['DB_USER']) ||
                    (isset($profile['DB_PASSWORD']) &&
                    $profile['DB_PASSWORD'] != $old_profile['DB_PASSWORD'])) {

                    if($this->profileModel->testDatabaseManager(
                        $profile) !== true) {
                        $db_problem = true;
                    }
                }
                if($db_problem) {
                    $data['MESSAGE'] =
                        tl('admin_controller_configure_no_change_db');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >". $data['MESSAGE'].
                        "</h1>');";
                    $data['DBMS'] = $old_profile['DBMS'];
                    $data['DB_NAME'] = $old_profile['DB_NAME'];
                    $data['DB_HOST'] = $old_profile['DB_HOST'];
                    $data['DB_USER'] = $old_profile['DB_USER'];
                    $data['DB_PASSWORD'] = $old_profile['DB_PASSWORD'];
                    break;
                }

                if($this->profileModel->updateProfile(
                    $data['WORK_DIRECTORY'], $profile, $old_profile)) {
                    $data['MESSAGE'] =
                        tl('admin_controller_configure_profile_change');
                    $data['SCRIPT'] =
                        "doMessage('<h1 class=\"red\" >". $data['MESSAGE'].
                        "</h1>');";

                        if($old_profile['DEBUG_LEVEL'] !=
                            $profile['DEBUG_LEVEL']) {
                            $data['SCRIPT'] .=
                                "setTimeout('window.location.href=\"".
                                "?c=admin&amp;a=configure&amp;".CSRF_TOKEN."=".
                                $_REQUEST[CSRF_TOKEN]."\"', 3*sec);";
                        }
                } else {
                    $data['PROFILE'] = false;
                    $data["MESSAGE"] =
                        tl('admin_controller_configure_no_change_profile');
                    $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >". $data["MESSAGE"].
                        "</h1>');";
                    break;
                }

            break;

            default:
                if(isset($data['WORK_DIRECTORY']) &&
                    file_exists($data['WORK_DIRECTORY']."/profile.php")) {
                    $data = array_merge($data,
                        $this->profileModel->getProfile(
                            $data['WORK_DIRECTORY']));
                    $data['MEMCACHE_SERVERS'] = str_replace(
                        "|Z|","\n", $data['MEMCACHE_SERVERS']);
                } else {
                    $data['WORK_DIRECTORY'] = "";
                    $data['PROFILE'] = false;
                }
        }
        $data['advanced'] = "false";
        if($data['PROFILE']) {
            $data['DBMSS'] = array();
            $data['SCRIPT'] .= "logindbms = Array();\n";
            foreach($this->profileModel->getDbmsList() as $dbms) {
                $data['DBMSS'][$dbms] = $dbms;
                if($this->profileModel->loginDbms($dbms)) {
                    $data['SCRIPT'] .= "logindbms['$dbms'] = true;\n";
                } else {
                    $data['SCRIPT'] .= "logindbms['$dbms'] = false;\n";
                }
            }
            $data['REGISTRATION_TYPES'] = array (
                    'disable_registration' => 
                        tl('admin_controller_configure_disable_registration'),
                    'no_activation' => 
                        tl('admin_controller_configure_no_activation'),
                    'email_registration' => 
                        tl('admin_controller_configure_email_activation'),
                    'admin_activation' =>
                        tl('admin_controller_configure_admin_activation'),
                );
             $data['show_mail_info'] = "false";
            if(isset($_REQUEST['REGISTRATION_TYPE']) &&
                in_array($_REQUEST['REGISTRATION_TYPE'], array(
                'email_registration', 'admin_activation'))) {
                $data['show_mail_info'] = "true";
            }
            if(!isset($data['ROBOT_DESCRIPTION']) ||
                strlen($data['ROBOT_DESCRIPTION']) == 0) {
                $data['ROBOT_DESCRIPTION'] =
                    tl('admin_controller_describe_robot');
            } else {
                //since the description might contain tags we apply htmlentities
                $data['ROBOT_DESCRIPTION'] =
                    $this->clean($data['ROBOT_DESCRIPTION'], "string");
            }
            if(!isset($data['MEMCACHE_SERVERS']) ||
                strlen($data['MEMCACHE_SERVERS']) == 0) {
                $data['MEMCACHE_SERVERS'] =
                    "localhost";
            }

            if(isset($_REQUEST['advanced']) && $_REQUEST['advanced']) {
                $data['advanced'] = "true";
            }
            $data['SCRIPT'] .= <<< EOD
    elt('account-registration').onchange = function () {
        var show_mail_info = false;
        no_mail_registration = ['disable_registration', 'no_activation'];
        if(no_mail_registration.indexOf(elt('account-registration').value) 
            < 0) {
            show_mail_info = true;
        }
        setDisplay('registration-info', show_mail_info);
    };
    setDisplay('registration-info', {$data['show_mail_info']});
    elt('database-system').onchange = function () {
        setDisplay('login-dbms', self.logindbms[elt('database-system').value]);
    };
    setDisplay('login-dbms', logindbms[elt('database-system').value]);
    setDisplay('advance-configure', {$data['advanced']});
    setDisplay('advance-robot', {$data['advanced']});
    function toggleAdvance() {
        var advanced = elt('a-settings');
        advanced.value = (advanced.value =='true')
            ? 'false' : 'true';
        var value = (advanced.value == 'true') ? true : false;
        setDisplay('advance-configure', value);
        setDisplay('advance-robot', value);
    }
EOD;
            if(class_exists("Memcache")) {
                $data['SCRIPT'] .= <<< EOD
    elt('use-memcache').onchange = function () {
        setDisplay('filecache', (elt('use-memcache').checked) ? false: true);
        setDisplay('memcache', (elt('use-memcache').checked) ? true : false);
    };
    setDisplay('filecache', (elt('use-memcache').checked) ? false : true);
    setDisplay('memcache', (elt('use-memcache').checked) ? true : false);
EOD;
            }
        }
        $data['SCRIPT'] .=
            "elt('locale').onchange = ".
            "function () { elt('configureProfileForm').submit();};\n";

        return $data;
    }

    function updateProfileFields(&$data, &$profile, $check_box_fields = array())
    {
        foreach($this->profileModel->profile_fields as $field) {
            if(isset($_REQUEST[$field])) {
                if($field != "ROBOT_DESCRIPTION" &&
                    $field != "MEMCACHE_SERVERS") {
                    $clean_field =
                        $this->clean($_REQUEST[$field], "string");
                } else {
                    $clean_field = $_REQUEST[$field];
                }
                if($field == "NAME_SERVER" &&
                    $clean_field[strlen($clean_field) -1] != "/") {
                    $clean_field .= "/";
                }
                $data[$field] = $clean_field;
                $profile[$field] = $data[$field];
                if($field == "MEMCACHE_SERVERS") {
                    $mem_array = preg_split("/(\s)+/", $clean_field);
                    $profile[$field] =
                        $this->convertArrayLines(
                            $mem_array, "|Z|", true);
                }
            }
            if(!isset($data[$field])) {
                $data[$field] = "";
                if(in_array($field, $check_box_fields)) {
                    $profile[$field] = false;
                }
            }
        }
    }
}
?>
