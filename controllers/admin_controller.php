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

class AdminController extends Controller implements CrawlConstants
{
    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to (note: more activities will be loaded from
     * components)
     * @var array
     */
    var $activities = array("crawlStatus", "machineStatus");

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
        $data['SCRIPT'] = "";
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
            } else if (!isset($_SESSION['REMOTE_ADDR'])) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('admin_controller_need_cookies')."</h1>');";
                unset($_SESSION['USER_ID']);
            } else if ($this->checkSignin()){
                $user_id = $this->model("signin")->getUserId(
                    $this->clean($_REQUEST['u'], "string"));
                $session = $this->model("user")->getUserSession($user_id);
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
                if(isset($data['INACTIVE'])) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_account_not_active')."</h1>');";
                    $view = "signin";
                    unset($_SESSION['USER_ID']);
                }
                $view = "admin";
            } else {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('admin_controller_login_failed')."</h1>');";
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
            $data['SCRIPT'] .= "var u; if ((u = elt('username')) && u.focus) ".
               "u.focus();";
        }
        $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
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
        $result = $this->model("signin")->checkValidSignin(
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
        $activity_model = $this->model("activity");
        if(!PROFILE) {
            $allowed_activities = array( array(
                "ACTIVITY_NAME" =>
                $activity_model->getActivityNameFromMethodName($activity),
                'METHOD_NAME' => $activity));
            $allowed = true;
        } else {
            $allowed_activities =
                 $this->model("user")->getUserActivities($_SESSION['USER_ID']);
        }
        if($allowed_activities == array()) {
            $data['INACTIVE'] = true;
            return $data;
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
                $activity_model->getActivityNameFromMethodName($activity);
        }

        $data['COMPONENT_ACTIVITIES'] = array();
        $component_translations = array(
            "accountaccess" => tl('admin_controller_account_access'),
            "blogmixes" => tl('blogmixes_component_blogs_pages_mixes'),
            "crawl" => tl('admin_controller_crawl_settings'),
            "system" => tl('admin_controller_system_settings')
        );
        if(isset($data["ACTIVITIES"])) {
            foreach($this->component_activities as $component => $activities) {
                foreach($data["ACTIVITIES"] as $activity) {
                    if(in_array($activity['METHOD_NAME'], $activities)) {
                        $data['COMPONENT_ACTIVITIES'][
                            $component_translations[$component]][] =
                            $activity;
                    }
                }
            }
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
        $crawl_model = $this->model("crawl");

        $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
        if(isset($crawl_time) ) {
            $data['CURRENT_INDEX'] = (int)$crawl_time;
        } else {
            $data['CURRENT_INDEX'] = -1;
        }

        $machine_urls = $this->model("machine")->getQueueServerUrls();
        list($stalled, $status, $data['RECENT_CRAWLS']) =
            $crawl_model->combinedCrawlInfo($machine_urls);

        if($stalled) {
            $crawl_model->sendStopCrawlMessage($machine_urls);
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
        $this->pagingLogic($data, null, 'RECENT_CRAWLS', 'RECENT_CRAWLS',
            50);
        return $data;
    }

    /**
     * Gets data from the machine model concerning the on/off states
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
        $data['MACHINES'] = $this->model("machine")->getMachineStatuses();
        $profile =  $this->model("profile")->getProfile(WORK_DIRECTORY);
        $data['NEWS_MODE'] = isset($profile['NEWS_MODE']) ?
            $profile['NEWS_MODE']: "";
        if($profile['NEWS_MODE'] == "news_process" &&
            $data['MACHINES']['NAME_SERVER']["news_updater"] == 0) {
            // try to restart news server if dead
            CrawlDaemon::start("news_updater", 'none', "", -1);
        }
        return $data;
    }


    /**
     * Used to update the yioop installation profile based on $_REQUEST data
     *
     * @param array &$data field data to be sent to the view
     * @param array &$profile used to contain the current and updated profile
     *      field values
     * @param array $check_box_fields fields whose data comes from a html
     *      checkbox
     */
    function updateProfileFields(&$data, &$profile, $check_box_fields = array())
    {
        foreach($this->model("profile")->profile_fields as $field) {
            if(isset($_REQUEST[$field])) {
                if($field != "ROBOT_DESCRIPTION" &&
                    $field != "MEMCACHE_SERVERS" &&
                    $field != "PROXY_SERVERS") {
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
                if($field == "MEMCACHE_SERVERS" || $field == "PROXY_SERVERS") {
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
