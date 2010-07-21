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

/** Load base controller class if needed */
require_once BASE_DIR."/controllers/controller.php";
/** Loads common constants for web crawling */
require_once BASE_DIR."/lib/crawl_constants.php";

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
     * Says which models to load for this controller
     * admin is the main one, sighin has the login screen crawlstatus
     * is used to see how many pages crawled by the current crawl
     * @var array
     */
    var $views = array("admin", "signin", "crawlstatus");
    /**
     * Says which views to load for this controller.
     * @var array
     */
    var $models = array(
        "signin", "user", "activity", "crawl", "role", "locale", "profile");
    /**
     * Says which activities (roughly methods invoke from the web) this 
     * controller will respond to
     * @var array
     */
    var $activities = array("signin", "manageAccount", 
        "manageUsers", "manageCrawl", "manageRoles", 
        "manageLocales", "crawlStatus", "configure");

    /**
     * This is the main entry point for handling requests to administer the
     * Yioop/SeekQuarry site
     *
     * ProcessRequest determines the type of request (signin , manageAccount, 
     * etc) is being made.  It then calls the appropriate method to handle the 
     * given activity.Finally, it draws the relevant admin screen
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

        $data['YIOOP_TOKEN'] = $this->generateCSRFToken($user);
        $token_okay = $this->checkCSRFToken('YIOOP_TOKEN', $user);

        if($token_okay) {
            if(isset($_SESSION['USER_ID']) && !isset($_REQUEST['u'])) {
                $data = array_merge($data, $this->processSession());
                if(!isset($data['REFRESH'])) {
                    $view = "admin";
                } else {
                    $view = "crawlstatus";
                }
             } else if ($this->checkSignin()){
                $_SESSION['USER_ID'] = $this->signinModel->getUserId(
                    $this->clean($_REQUEST['u'], "string"));
                $data['YIOOP_TOKEN'] = $this->generateCSRFToken(
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
        $data['YIOOP_TOKEN'] = $this->generateCSRFToken("config");
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
        if(!PROFILE) {
            $activity = "configure";
        } else if(isset($_REQUEST['a']) &&  
            in_array($_REQUEST['a'], $this->activities)) {
            $activity = $_REQUEST['a'];
        } else {
            $activity = "manageAccount";
        }

        $allowed = false;
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
        }

        //for now we allow anyone to get crawlStatus
        if($activity == "crawlStatus" || $allowed) {
            $data = $this->$activity();
            $data['ACTIVITIES'] = $allowed_activities;
        }
        if($activity != "crawlStatus") {
            $data['CURRENT_ACTIVITY'] = 
                $this->activityModel->getActivityNameFromMethodName($activity);
        }
        return $data;
    }

    /**
     * This method is data to signin a user and initialize the data to be 
     * display in a view
     *
     * @return array empty array of data to show so far in view
     */
    function signin()
    {
        $data = array();
        $_SESSION['USER_ID'] = 
            $this->signinModel->getUserId($_REQUEST['username']);
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
        $data['REFRESH'] = true;

        $crawl_time = $this->crawlModel->getCurrentIndexDatabaseName();
        if(isset($crawl_time) ) {
            $data['CURRENT_INDEX'] = (int)$crawl_time;
        } else {
            $data['CURRENT_INDEX'] = -1;
        }

        if(file_exists(CRAWL_DIR."/schedules/crawl_status.txt")) {

            if(filemtime(
                CRAWL_DIR."/schedules/crawl_status.txt") + 1200 > time()) { 
                //assume if status not updated for 20min crawl not active
                $crawl_status = 
                    unserialize(file_get_contents(
                        CRAWL_DIR."/schedules/crawl_status.txt"));
                $data = array_merge($data, $crawl_status);
            }
        }
        $data['RECENT_CRAWLS'] = $this->crawlModel->getCrawlList();
        if(isset($data['CRAWL_TIME'])) { 
            //erase from previous crawl list any active crawl
            $num_crawls = count($data['RECENT_CRAWLS']);
            for($i = 0; $i < $num_crawls; $i++) {
                if($data['RECENT_CRAWLS'][$i]['CRAWL_TIME'] == 
                    $data['CRAWL_TIME']) {
                    $data['RECENT_CRAWLS'][$i] = false;
                }
            }
            $data['RECENT_CRAWLS']= array_filter($data['RECENT_CRAWLS']);
        }

        return $data;
    }

    /**
     * Used to handle the change current user password admin activity
     *
     * @return array $data SCRIPT field contains success or failure message
     */
    function manageAccount()
    {
        $possible_arguments = array("changepassword");

        $data["ELEMENT"] = "manageaccountElement";
        $data['SCRIPT'] = "";

        if(isset($_REQUEST['arg']) && 
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "changepassword":
                    if($_REQUEST['retypepassword'] != $_REQUEST['newpassword']){
                        $data['SCRIPT'] .= 
                            "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_passwords_dont_match').
                            "</h1>')";
                        return $data;
                    }
                    $username = 
                        $this->signinModel->getUserName($_SESSION['USER_ID']);
                    $result = $this->signinModel->checkValidSignin($username, 
                    $this->clean($_REQUEST['oldpassword'], "string") );
                    if(!$result) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_invalid_old_password').
                            "</h1>')";
                        return $data;
                    }
                    $this->signinModel->changePassword($username, 
                        $this->clean($_REQUEST['newpassword'], "string"));
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_change_password')."</h1>')";
                break;
                }
        }

        return $data;
    }

    /**
     * Used to handle the manage user activity. 
     *
     * This activity allows new users to be added, old users to be 
     * deleted and allows roles to be added to/deleted from a user
     *
     * @return array $data infomation about users of the system, roles, etc.
     *      as well as status messages on performing a given sub activity
     */
    function manageUsers()
    {
        $possible_arguments = array("adduser", 
            "deleteuser", "adduserrole", "deleteuserrole");

        $data["ELEMENT"] = "manageusersElement";
        $data['SCRIPT'] = 
            "selectUser = elt('select-user'); ".
            "selectUser.onchange = submitViewUserRole;";

        $usernames = $this->userModel->getUserList();
        if(isset($_REQUEST['username'])) {
            $username = $this->clean($_REQUEST['username'], "string" );
        }
        $base_option = tl('admin_controller_select_username');
        $data['USER_NAMES'] = array();
        $data['USER_NAMES'][""] = $base_option;

        foreach($usernames as $name) {
            $data['USER_NAMES'][$name]= $name;
        }

        if(isset($_REQUEST['selectuser'])) {
            $select_user = $this->clean($_REQUEST['selectuser'], "string" );
        } else {
            $select_user = "";
        }
        if($select_user != "" ) {
            $userid = $this->signinModel->getUserId($select_user);
            $data['SELECT_USER'] = $select_user;
            $data['SELECT_ROLES'] = $this->userModel->getUserRoles($userid);
            $all_roles = $this->roleModel->getRoleList();
            $role_ids = array();
            if(isset($_REQUEST['selectrole'])) {
                $select_role = $this->clean($_REQUEST['selectrole'], "string" );
            } else {
                $select_role = "";
            }
            
            foreach($all_roles as $role) {
                $role_ids[] = $role['ROLE_ID'];
                if($select_role == $role['ROLE_ID']) {
                    $select_rolename = $role['ROLE_NAME'];
                }
            } 
            
            $available_roles = array_diff_assoc(
                $all_roles, $data['SELECT_ROLES']);


            $data['AVAILABLE_ROLES'][-1] = 
                tl('admin_controller_select_rolename');

            foreach($available_roles as $role) {
                $data['AVAILABLE_ROLES'][$role['ROLE_ID']]= $role['ROLE_NAME'];
            }

            if($select_role != "") {
                $data['SELECT_ROLE'] = $select_role;
            } else {
                $data['SELECT_ROLE'] = -1;
            }
        } else {
            $data['SELECT_USER'] = -1;
        }

        if(isset($_REQUEST['arg']) && 
            in_array($_REQUEST['arg'], $possible_arguments)) {

            switch($_REQUEST['arg'])
            {
                case "adduser":
                    $data['SELECT_ROLE'] = -1;
                    unset($data['AVAILABLE_ROLES']);
                    unset($data['SELECT_ROLES']);
                    if($_REQUEST['retypepassword'] != $_REQUEST['password']) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_passwords_dont_match').
                            "</h1>')";
                        return $data;
                    }

                    if($this->signinModel->getUserId($username) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_username_exists')."</h1>')";
                        return $data;
                    }
                    $this->userModel->addUser($username, 
                        $this->clean($_REQUEST['password'], "string"));
                    $data['USER_NAMES'][$username] = $username;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_username_added')."</h1>')";
                break;

                case "deleteuser":
                    $data['SELECT_ROLE'] = -1;
                    unset($data['AVAILABLE_ROLES']);
                    unset($data['SELECT_ROLES']);
                    if(!($this->signinModel->getUserId($username) > 0)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_username_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $this->userModel->deleteUser($username);
                    unset($data['USER_NAMES'][$username]);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_username_deleted')."</h1>')";

                break;

                case "adduserrole":
                    if( $userid <= 0 ) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_username_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_rolename_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $this->userModel->addUserRole($userid, $select_role);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_rolename_added').
                        "</h1>')";
                    unset($data['AVAILABLE_ROLES'][$select_role]);
                    $data['SELECT_ROLE'] = -1;
                    $data['SELECT_ROLES'] = 
                        $this->userModel->getUserRoles($userid);
                break;

                case "deleteuserrole":
                    if($userid <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_username_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_rolename_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $this->userModel->deleteUserRole($userid, $select_role);
                    $data['SELECT_ROLES'] = 
                        $this->userModel->getUserRoles($userid);
                    $data['AVAILABLE_ROLES'][$select_role] = $select_rolename;
                    $data['SELECT_ROLE'] = -1;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_rolename_deleted')."</h1>')";
                break;
            }
        }

        return $data;
    }

    /**
     * Used to handle the manage role activity. 
     *
     * This activity allows new roles to be added, old roles to be 
     * deleted and allows activities to be added to/deleted from a role
     *
     * @return array $data infomation about roles in the system, activities,etc.
     *      as well as status messages on performing a given sub activity
     *
     */
    function manageRoles()
    {
        $possible_arguments = 
            array("addrole", "deleterole", "addactivity", "deleteactivity");

        $data["ELEMENT"] = "managerolesElement";
        $data['SCRIPT'] = 
            "selectRole = elt('select-role'); selectRole.onchange =".
            " submitViewRoleActivities;";

        $roles = $this->roleModel->getRoleList();
        $role_ids = array();
        $base_option = tl('admin_controller_select_rolename');
        $data['ROLE_NAMES'] = array();
        $data['ROLE_NAMES'][-1] = $base_option;
        if(isset($_REQUEST['rolename'])) {
            $rolename = $this->clean($_REQUEST['rolename'], "string" );
        }
        foreach($roles as $role) {
            $data['ROLE_NAMES'][$role['ROLE_ID']]= $role['ROLE_NAME'];
            $role_ids[] = $role['ROLE_ID'];
        }
        $data['SELECT_ROLE'] = -1;


        if(isset($_REQUEST['selectrole'])) {
            $select_role = $this->clean($_REQUEST['selectrole'], "string" );
        } else {
            $select_role = "";
        }
        
        if($select_role != "" ) {
            $data['SELECT_ROLE'] = $select_role;
            $data['ROLE_ACTIVITIES'] = 
                $this->roleModel->getRoleActivities($select_role);
            $all_activities = $this->activityModel->getActivityList();
            $activity_ids = array();
            $activity_names = array();
            foreach($all_activities as $activity) {
                $activity_ids[] = $activity['ACTIVITY_ID'];
                $activity_names[$activity['ACTIVITY_ID']] = 
                    $activity['ACTIVITY_NAME'];
            }

            $available_activities = 
                array_diff_assoc($all_activities, $data['ROLE_ACTIVITIES']);
            $data['AVAILABLE_ACTIVITIES'][-1] = 
                tl('admin_controller_select_activityname');


            foreach($available_activities as $activity) {
                $data['AVAILABLE_ACTIVITIES'][$activity['ACTIVITY_ID']] = 
                    $activity['ACTIVITY_NAME'];
            }

            if(isset($_REQUEST['selectactivity'])) {
                $select_activity = 
                    $this->clean($_REQUEST['selectactivity'], "int" );

            } else {
                $select_activity = "";
            }
            if($select_activity != "") {
                $data['SELECT_ACTIVITY'] = $select_activity;
            } else {
                $data['SELECT_ACTIVITY'] = -1;
            }

        } 
        if(isset($_REQUEST['arg']) && 
            in_array($_REQUEST['arg'], $possible_arguments)) {
            
            switch($_REQUEST['arg'])
            {
                case "addrole":
                    unset($data['ROLE_ACTIVITIES']);
                    unset($data['AVAILABLE_ACTIVITIES']);
                    $data['SELECT_ROLE'] = -1;
                    if($this->roleModel->getRoleId($rolename) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_rolename_exists').
                            "</h1>')";
                        return $data;
                    }

                    $this->roleModel->addRole($rolename);
                    $roleid = $this->roleModel->getRoleId($rolename);
                    $data['ROLE_NAMES'][$roleid] = $rolename;

                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_rolename_added').
                        "</h1>')";
                break;

                case "deleterole":
                    $data['SELECT_ROLE'] = -1;
                    unset($data['ROLE_ACTIVITIES']);
                    unset($data['AVAILABLE_ACTIVITIES']);

                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_rolename_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $this->roleModel->deleteRole($select_role);
                    unset($data['ROLE_NAMES'][$select_role]);

                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_rolename_deleted')."</h1>')";
                break;

                case "addactivity":
                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_rolename_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    if(!in_array($select_activity, $activity_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_activityname_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $this->roleModel->addActivityRole(
                        $select_role, $select_activity);
                    unset($data['AVAILABLE_ACTIVITIES'][$select_activity]);
                    $data['ROLE_ACTIVITIES'] = 
                        $this->roleModel->getRoleActivities($select_role);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_activity_added')."</h1>')";
                break;

                case "deleteactivity":
                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_rolename_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    
                    if(!in_array($select_activity, $activity_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_activityname_doesnt_exists').
                            "</h1>')";
                        return $data;
                    }
                    $this->roleModel->deleteActivityRole(
                        $select_role, $select_activity);
                    $data['ROLE_ACTIVITIES'] = 
                        $this->roleModel->getRoleActivities($select_role);
                    $data['AVAILABLE_ACTIVITIES'][$select_activity] = 
                        $activity_names[$select_activity];
                    $data['SELECT_ACTIVITY'] = -1;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_activity_deleted')."</h1>')";
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
    function manageCrawl()
    {
        $possible_arguments = 
            array("start", "resume", "delete", "stop", "index", "options");

        $data["ELEMENT"] = "managecrawlElement";
        $data['SCRIPT'] = "doUpdate();";

        if(isset($_REQUEST['arg']) && 
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "start":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_starting_new_crawl')."</h1>')";

                    $info = array();
                    $info[self::STATUS] = "NEW_CRAWL";
                    $info[self::CRAWL_TIME] = time();
                    $seed_info = $this->crawlModel->getSeedInfo();

                    $info[self::CRAWL_ORDER] = 
                        $seed_info['general']['crawl_order'];
                    $info[self::RESTRICT_SITES_BY_URL] = 
                        $seed_info['general']['restrict_sites_by_url'];
                    $info[self::ALLOWED_SITES] = 
                        $seed_info['allowed_sites']['url'];
                    $info[self::DISALLOWED_SITES] = 
                        $seed_info['disallowed_sites']['url'];

                    if(isset($_REQUEST['description'])) {
                        $description = 
                            $this->clean($_REQUEST['description'], "string");
                    } else {
                        $description = tl('admin_controller_no_description');
                    }
                    $info['DESCRIPTION'] = $description;

                    $info_string = serialize($info);
                    file_put_contents(
                        CRAWL_DIR."/schedules/queue_server_messages.txt", 
                        $info_string);

                    $scheduler_info[self::SEEN_URLS] = array();

                    foreach ($seed_info['seed_sites']['url'] as $site) {
                        $scheduler_info[self::TO_CRAWL][] = array($site, 1.0);
                    }
                    $scheduler_info[self::ROBOT_TXT] = array();
                    $scheduler_string = serialize($scheduler_info);
                    @unlink(CRAWL_DIR."/schedules/schedule.txt");
                    file_put_contents(
                        CRAWL_DIR."/schedules/ScheduleDataStartCrawl.txt", 
                        $scheduler_string);

                break;

                case "stop":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_stop_crawl')."</h1>')";

                    $info = array();
                    $info[self::STATUS] = "STOP_CRAWL";
                    $info_string = serialize($info);
                    file_put_contents(
                        CRAWL_DIR."/schedules/queue_server_messages.txt", 
                        $info_string);

                break;

                case "resume":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_resume_crawl')."</h1>')";

                    $info = array();
                    $info[self::STATUS] = "RESUME_CRAWL";
                    $info[self::CRAWL_TIME] = 
                        $this->clean($_REQUEST['timestamp'], "int");
                    $info_string = serialize($info);
                    file_put_contents(
                        CRAWL_DIR."/schedules/queue_server_messages.txt", 
                        $info_string);

                break;

                case "delete":
                    if(isset($_REQUEST['timestamp'])) {
                         $timestamp = 
                            $this->clean($_REQUEST['timestamp'], "int");
                         $this->crawlModel->db->unlinkRecursive(
                            CRAWL_DIR.'/cache/'.self::index_data_base_name .
                            $timestamp, true);
                         $this->crawlModel->db->unlinkRecursive(
                            CRAWL_DIR.'/schedules/'.self::index_data_base_name .
                            $timestamp, true);
                         $this->crawlModel->db->unlinkRecursive(
                            CRAWL_DIR.'/schedules/' .
                            self::schedule_data_base_name.$timestamp, true);
                         $this->crawlModel->db->unlinkRecursive(
                            CRAWL_DIR.'/schedules/'.self::robot_data_base_name.
                            $timestamp, true);
                         
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
                    $data["leftorright"] = 
                        (getLocaleDirection() == 'ltr') ? "right": "left";
                    $data["ELEMENT"] = "crawloptionsElement";
                    $seed_info = $this->crawlModel->getSeedInfo();
                    $data['available_crawl_orders'] = array(
                        self::BREADTH_FIRST => 
                            tl('admin_controller_breadth_first'), 
                        self::PAGE_IMPORTANCE => 
                            tl('admin_controller_page_importance'));
                    $update_flag = false;
                    if(isset($_REQUEST['crawl_order']) && 
                        in_array($_REQUEST['crawl_order'], 
                            array_keys($data['available_crawl_orders']))) {

                        $seed_info['general']['crawl_order'] = 
                            $_REQUEST['crawl_order'];
                        $update_flag = true;
                    }
                    $data['crawl_order'] = $seed_info['general']['crawl_order'];
                    
                    if(isset($_REQUEST['posted'])) {
                        $seed_info['general']['restrict_sites_by_url'] = 
                            (isset($_REQUEST['restrict_sites_by_url'])) ?
                            true : false;
                        $update_flag = true;
                    }
                    $data['restrict_sites_by_url'] = 
                        $seed_info['general']['restrict_sites_by_url'];
                    $site_types = 
                        array('allowed_sites','disallowed_sites', 'seed_sites');
                    foreach($site_types as $type) {
                        if(isset($_REQUEST[$type])) {
                            $seed_info[$type]['url'] = 
                                $this->convertStringCleanUrlsArray(
                                $_REQUEST[$type]);
                        }
                        $data[$type] = $this->convertArrayCleanLines(
                            $seed_info[$type]['url']);
                    }
                    $data['TOGGLE_STATE'] = 
                        ($data['restrict_sites_by_url']) ? 
                        "checked='checked'" : "";
                    $data['SCRIPT'] = "setDisplay('toggle', ".
                        "'{$data['restrict_sites_by_url']}');";
                    if($update_flag) {
                        $this->crawlModel->setSeedInfo($seed_info);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_update_seed_info')."</h1>');";
                    }

 
                default:

            }
        }

        return $data;
    }

    /**
     * Cleans a potentially tainted set of user input which are presented as
     * an array of lines. This is used to handle data from the crawl options
     * textareas.
     *
     * @param array $arr the array of lines to be cleaned
     * @return string a concatenated string of cleaned lines
     */
    function convertArrayCleanLines($arr)
    {
        $output = "";
        foreach($arr as $line) {
            $output .= trim($this->clean($line, 'string'))."\n";
        }
        return $output;
    }

    /**
     * Cleans a string consisting of lines of urls into an array of urls. This
     * is used in handling data from the crawl options text areas.
     *
     * @param string $str contains the url data
     * @return $url an array of urls
     */
    function convertStringCleanUrlsArray($str)
    {
        $pre_urls = preg_split("/(\s)+/", $str);
        $urls = array();
        foreach($pre_urls as $url) {
            $pre_url = $this->clean($url, "string");
            if(strlen($url) > 0) {
                $urls[] =$pre_url;
            }
        }
        return $urls;
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
                    $data["leftorright"] = 
                        (getLocaleDirection() == 'ltr') ? "right": "left";
                    $data["ELEMENT"] = "editlocalesElement";
                    $data['CURRENT_LOCALE_NAME'] = 
                        $data['LOCALE_NAMES'][$select_locale];
                    $data['CURRENT_LOCALE_TAG'] = $select_locale;
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

                break;
            }
        }
        

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

        $languages = $this->localeModel->getLocaleList();
        foreach($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] = 
                $language['LOCALE_NAME'];
        }
        if(isset($_POST['lang'])) {
            $data['lang'] = $this->clean($_POST['lang'], "string");
            $profile['DEFAULT_LOCALE'] = $data['lang'];
            setLocaleObject($data['lang']);
        }

        $data["ELEMENT"] = "configureElement";
        $data['SCRIPT'] = "";
        
        $data['PROFILE'] = false;


        if(isset($_REQUEST['WORK_DIRECTORY'])) {
            $data['WORK_DIRECTORY'] = 
                $this->clean($_REQUEST['WORK_DIRECTORY'], "string");
            $data['PROFILE'] = true;
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
                    $data['SCRIPT'] .= 
                        "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_configure_work_dir_set').
                        "</h1>');setTimeout(".
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
                        if($this->profileModel->updateProfile(
                            $data['WORK_DIRECTORY'], array(), $profile)) {
                            if($this->profileModel->setWorkDirectoryConfigFile(
                                $data['WORK_DIRECTORY'])) {
                                $data['SCRIPT'] .= 
                                    "doMessage('<h1 class=\"red\" >".
                             tl('admin_controller_configure_work_profile_made').
                                    "</h1>');";
                            } else {
                                $data['PROFILE'] = false;
                                $data['SCRIPT'] .= 
                                    "doMessage('<h1 class=\"red\" >".
                             tl('admin_controller_configure_no_set_config').
                                    "</h1>');" .
                                    "setTimeout('window.location.href= ".
                                    "window.location.href', 3000);";
                            }
                        } else {
                            $this->profileModel->setWorkDirectoryConfigFile(
                                $data['WORK_DIRECTORY']);
                            $data['PROFILE'] = false;
                            $data['SCRIPT'] .= 
                                "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_configure_no_create_profile').
                                "</h1>'); setTimeout('window.location.href=".
                                "window.location.href', 3000);";
                        }
                    } else {
                        $this->profileModel->setWorkDirectoryConfigFile(
                            $data['WORK_DIRECTORY']);
                        $data['SCRIPT'] .= 
                            "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_configure_work_dir_invalid').
                                "</h1>');".
                            "setTimeout('window.location.href=".
                            "window.location.href', 3000);";
                        $data['PROFILE'] = false;
                    }
                } else {
                    $this->profileModel->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    $data['SCRIPT'] .= 
                        "doMessage('<h1 class=\"red\" >".
                            tl('admin_controller_configure_work_dir_invalid').
                            "</h1>');" .
                        "setTimeout('window.location.href=".
                        "window.location.href', 3000);";
                    $data['PROFILE'] = false;
                }
            break;
            case "profile":
                foreach($this->profileModel->profile_fields as $field) {
                    if(isset($_POST[$field])) {
                        if($field != "ROBOT_DESCRIPTION") {
                            $clean_field = 
                                $this->clean($_POST[$field], "string");
                        } else {
                            $clean_field = $_POST[$field];
                        }
                        $data[$field] = $clean_field;
                        $profile[$field] = $data[$field];
                    }
                    if(!isset($data[$field])) {
                        $data[$field] = "";
                    }
                }
                $data['DEBUG_LEVEL'] = 0;
                $data['DEBUG_LEVEL'] |= 
                    (isset($_POST["ERROR_INFO"])) ? ERROR_INFO : 0;
                $data['DEBUG_LEVEL'] |= 
                    (isset($_POST["QUERY_INFO"])) ? QUERY_INFO : 0;
                $data['DEBUG_LEVEL'] |= 
                    (isset($_POST["TEST_INFO"])) ? TEST_INFO : 0;
                $profile['DEBUG_LEVEL'] = $data['DEBUG_LEVEL'];
                
                $old_profile = 
                    $this->profileModel->getProfile($data['WORK_DIRECTORY']);
                
                $db_problem = false;
                if((isset($profile['DBMS']) && 
                    $profile['DBMS'] != $old_profile['DBMS']) || 
                    (isset($profile['DB_NAME']) && 
                    $profile['DB_NAME'] != $old_profile['DB_NAME']) ||
                    (isset($profile['DB_URL']) && 
                    $profile['DB_URL'] != $old_profile['DB_URL'])) {
                    
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
                    $data['SCRIPT'] .= 
                        "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_configure_no_change_db').
                        "</h1>');";
                    $data['DBMS'] = $old_profile['DBMS'];
                    $data['DB_NAME'] = $old_profile['DB_NAME'];
                    $data['DB_URL'] = $old_profile['DB_URL'];
                    $data['DB_USER'] = $old_profile['DB_USER'];
                    $data['DB_PASSWORD'] = $old_profile['DB_PASSWORD'];
                    break;
                }

                if($this->profileModel->updateProfile(
                $data['WORK_DIRECTORY'], $profile, $old_profile)) {
                    $data['SCRIPT'] = 
                        "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_configure_profile_change').
                        "</h1>');";
                        
                        if($old_profile['DEBUG_LEVEL'] != 
                            $profile['DEBUG_LEVEL']) {
                            $data['SCRIPT'] .= 
                                "setTimeout('window.location.href=\"".
                                "?c=admin&a=configure&YIOOP_TOKEN=".
                                $_REQUEST['YIOOP_TOKEN']."\"', 3*sec);";
                        }
                } else {
                    $data['PROFILE'] = false;
                    $data['SCRIPT'] .= 
                        "doMessage('<h1 class=\"red\" >".
                        tl('admin_controller_configure_no_change_profile').
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
                } else {
                    $data['WORK_DIRECTORY'] = "";
                    $data['PROFILE'] = false;
                }
        }

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

            if(!isset($data['ROBOT_DESCRIPTION']) || 
                strlen($data['ROBOT_DESCRIPTION']) == "") {
                $data['ROBOT_DESCRIPTION'] = 
                    tl('admin_controller_describe_robot');
            }
            $data['SCRIPT'] .= 
                "elt('database-system').onchange = function () {" .
                "setDisplay('login-dbms',".
                "self.logindbms[elt('database-system').value]);};" .
                "setDisplay('login-dbms', ".
                "logindbms[elt('database-system').value]);\n";

        }
        $data['SCRIPT'] .= 
            "elt('locale').onchange = ".
            "function () { elt('configureProfileForm').submit();};\n";

        return $data;
    }
}
?>
