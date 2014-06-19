<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
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
 * Component of the Yioop control panel used to handle activitys for
 * managing accounts, users, roles, and groups. i.e., Settings of users
 * and groups, what roles and groups a user has, what roles and users
 * a group has, and what activities make up a role. It is used by
 * AdminController
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage component
 */
class AccountaccessComponent extends Component
{

    /**
     * This method is data to signin a user and initialize the data to be
     * display in a view
     *
     * @return array empty array of data to show so far in view
     */
    function signin()
    {
        $parent = $this->parent;
        $data = array();
        $_SESSION['USER_ID'] =
            $parent->model("signin")->getUserId($_REQUEST['username']);
        return $data;
    }

    /**
     * Used to handle the change current user password admin activity
     *
     * @return array $data SCRIPT field contains success or failure message
     */
    function manageAccount()
    {
        $parent = $this->parent;
        $signin_model = $parent->model("signin");
        $group_model = $parent->model("group");
        $user_model = $parent->model("user");
        $crawl_model = $parent->model("crawl");
        $possible_arguments = array("updateuser");
        $data["ELEMENT"] = "manageaccount";
        $data['SCRIPT'] = "";
        $data['MESSAGE'] = "";
        $user_id = $_SESSION['USER_ID'];
        $username = $signin_model->getUserName($user_id);
        $data["USER"] = $user_model->getUser($username);
        $data["CRAWL_MANAGER"] = false;
        if($user_model->isAllowedUserActivity($user_id, "manageCrawls")) {
            $data["CRAWL_MANAGER"] = true;
            $machine_urls = $parent->model("machine")->getQueueServerUrls();
            list($stalled, $status, $recent_crawls) =
                $crawl_model->combinedCrawlInfo($machine_urls);
            $data = array_merge($data, $status);
            $data["CRAWLS_RUNNING"] = 0;
            $data["NUM_CLOSED_CRAWLS"] = count($recent_crawls);
            if(isset($data['CRAWL_TIME']) && $data["CRAWL_TIME"] != 0) {
                $data["CRAWLS_RUNNING"] = 1;
                $data["NUM_CLOSED_CRAWLS"]--;
            }
        }
        if(isset($_REQUEST['edit']) && $_REQUEST['edit'] == "true") {
            $data['EDIT_USER'] = true;
        }
        if(isset($_REQUEST['edit_pass'])) {
            if($_REQUEST['edit_pass'] == "true") {
                $data['EDIT_USER'] = true;
                $data['EDIT_PASSWORD'] = true;
                $data['AUTHENTICATION_MODE'] = AUTHENTICATION_MODE;
                $data['FIAT_SHAMIR_MODULUS'] = FIAT_SHAMIR_MODULUS;
                $data['INCLUDE_SCRIPTS'] = array("sha1", "zkp", "big_int");
            } else {
                $data['EDIT_USER'] = true;
            }
        }
        $data['USERNAME'] = $username;
        $data['NUM_SHOWN'] = 5;
        $data['GROUPS'] = $group_model->getRows(0,
            $data['NUM_SHOWN'], $data['NUM_GROUPS'], array(),
            array($user_id, false));
        $num_shown = count($data['GROUPS']);
        for($i = 0; $i < $num_shown; $i++) {
            $search_array = array(array("group_id", "=",
                $data['GROUPS'][$i]['GROUP_ID'], ""),
                array("pub_date", "", "", "DESC"));
            $item = $group_model->getGroupItems(0, 1,
                $search_array, $user_id);
            $data['GROUPS'][$i]['NUM_POSTS'] =
                $group_model->getGroupItemCount($search_array, $user_id);
            $data['GROUPS'][$i]['NUM_THREADS'] =
                $group_model->getGroupItemCount($search_array, $user_id,
                $data['GROUPS'][$i]['GROUP_ID']);
            if(isset($item[0]['TITLE'])) {
                $data['GROUPS'][$i]["ITEM_TITLE"] = $item[0]['TITLE'];
                $data['GROUPS'][$i]["THREAD_ID"] = $item[0]['PARENT_ID'];
            } else {
                $data['GROUPS'][$i]["ITEM_TITLE"] =
                    tl('accountaccess_component_no_posts_yet');
                $data['GROUPS'][$i]["THREAD_ID"] = -1;
            }
        }
        $data['NUM_SHOWN'] = $num_shown;
        $data['NUM_MIXES'] = count($crawl_model->getMixList($user_id));
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "updateuser":
                    if(isset($data['EDIT_PASSWORD']) &&
                        (!isset($_REQUEST['retype_password']) ||
                        !isset($_REQUEST['new_password']) ||
                        $_REQUEST['retype_password'] !=
                            $_REQUEST['new_password'])){
                        $data["MESSAGE"] =
                            tl('accountaccess_component_passwords_dont_match');
                        $data['SCRIPT'] .=
                            "doMessage('<h1 class=\"red\" >". $data["MESSAGE"].
                            "</h1>')";
                        return $data;
                    }
                    $result = $signin_model->checkValidSignin($username,
                        $parent->clean($_REQUEST['password'], "string") );
                    if(!$result) {
                        $data["MESSAGE"] =
                            tl('accountaccess_component_invalid_password');
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            $data["MESSAGE"]."</h1>')";
                        return $data;
                    }
                    if(isset($data['EDIT_PASSWORD'])) {
                        if(AUTHENTICATION_MODE == ZKP_AUTHENTICATION) {
                            $signin_model->changePasswordZKP($username,
                                $parent->clean($_REQUEST['new_password'],
                                "string"));
                        } else {
                            $signin_model->changePassword($username,
                                $parent->clean($_REQUEST['new_password'],
                                "string"));
                        }
                    }
                    $user = array();
                    $user['USER_ID'] = $user_id;
                    $fields = array("EMAIL", "FIRST_NAME", "LAST_NAME");
                    foreach($fields as $field) {
                        if(isset($_REQUEST[$field])) {
                            $user[$field] = $parent->clean(
                                $_REQUEST[$field], "string");
                            $data['USER'][$field] =  $user[$field];
                        }
                    }
                    $user_model->updateUser($user);
                    $data["MESSAGE"] =
                        tl('accountaccess_component_user_updated');
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        $data["MESSAGE"]."</h1>')";
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
     *     as well as status messages on performing a given sub activity
     */
    function manageUsers()
    {
        $parent = $this->parent;;
        if(AUTHENTICATION_MODE == ZKP_AUTHENTICATION) {
            $_SESSION['SALT_VALUE'] = rand(0, 1);
            $_SESSION['AUTH_COUNT'] = 1;
            $data['INCLUDE_SCRIPTS'] = array("sha1", "zkp", "big_int");
            $data['AUTHENTICATION_MODE'] = ZKP_AUTHENTICATION;
            $data['FIAT_SHAMIR_MODULUS'] = FIAT_SHAMIR_MODULUS;
        } else {
            $data['AUTHENTICATION_MODE'] = NORMAL_AUTHENTICATION;
            unset($_SESSION['SALT_VALUE']);
        }
        $signin_model = $parent->model("signin");
        $user_model = $parent->model("user");
        $group_model = $parent->model("group");
        $role_model = $parent->model("role");
        $possible_arguments = array("adduser", 'edituser', 'search',
            "deleteuser", "adduserrole", "deleteuserrole",
            "addusergroup", "deleteusergroup", "updatestatus");

        $data["ELEMENT"] = "manageusers";
        $data['SCRIPT'] = "";
        $data['STATUS_CODES'] = array(
            ACTIVE_STATUS => tl('accountaccess_component_active_status'),
            INACTIVE_STATUS => tl('accountaccess_component_inactive_status'),
            BANNED_STATUS => tl('accountaccess_component_banned_status'),
        );
        $data['MEMBERSHIP_CODES'] = array(
            INACTIVE_STATUS => tl('accountaccess_component_request_join'),
            INVITED_STATUS => tl('accountaccess_component_invited'),
            ACTIVE_STATUS => tl('accountaccess_component_active_status'),
            BANNED_STATUS => tl('accountaccess_component_banned_status')
        );
        $data['FORM_TYPE'] = "adduser";
        $search_array = array();
        $username = "";
        if(isset($_REQUEST['user_name'])) {
            $username = $parent->clean($_REQUEST['user_name'], "string" );
        }
        if($username == "" && isset($_REQUEST['arg']) && $_REQUEST['arg']
            != "search") {
            unset($_REQUEST['arg']);
        }
        $select_group = isset($_REQUEST['selectgroup']) ?
            $parent->clean($_REQUEST['selectgroup'],"string") : "";
        $select_role = isset($_REQUEST['selectrole']) ?
            $parent->clean($_REQUEST['selectrole'],"string") : "";
        if(isset($_REQUEST['arg']) && $_REQUEST['arg'] == 'edituser') {
            if($select_role != "") {
                $_REQUEST['arg'] = "adduserrole";
            } else if($select_group != ""){
                $_REQUEST['arg'] = "addusergroup";
            }
        }
        $user_id = -1;
        $data['visible_roles'] = 'false';
        $data['visible_groups'] = 'false';
        if($username != "") {
            $user_id = $signin_model->getUserId($username);
            if($user_id) {
                $this->getUserRolesData($data, $user_id);
                $this->getUserGroupsData($data, $user_id);
            }
        }
        $data['CURRENT_USER'] = array("user_name" => "", "first_name" => "",
            "last_name" => "", "email" => "", "status" => "", "password" => "",
            "repassword" => "");
        $data['PAGING'] = "";
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "adduser":
                    if($_REQUEST['retypepassword'] != $_REQUEST['password']) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_passwords_dont_match').
                            "</h1>')";
                    } else if($signin_model->getUserId($username) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_exists').
                            "</h1>')";
                    } else if(!isset($data['STATUS_CODES'][
                        $_REQUEST['status']])) {
                        $_REQUEST['status'] = INACTIVE_STATUS;
                    } else {
                        $norm_password = "";
                        $zkp_password = "";
                        if(AUTHENTICATION_MODE == ZKP_AUTHENTICATION) {
                            $zkp_password = 
                                $parent->clean($_REQUEST['password'], "string");
                        } else {
                            $norm_password = 
                                $parent->clean($_REQUEST['password'], "string");
                        }
                        $user_model->addUser($username, $norm_password,
                            $parent->clean($_REQUEST['first_name'], "string"),
                            $parent->clean($_REQUEST['last_name'], "string"),
                            $parent->clean($_REQUEST['email'], "string"),
                            $_REQUEST['status'], $zkp_password
                        );
                        $data['USER_NAMES'][$username] = $username;
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_added').
                            "</h1>')";
                    }
                break;
                case "edituser":
                    $data['FORM_TYPE'] = "edituser";
                    $user = $user_model->getUser($username);
                    $update = false;
                    $error = false;
                    if($user["USER_ID"] == PUBLIC_USER_ID) {
                        $data['FORM_TYPE'] = "adduser";
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_cant_edit_public_user').
                            "</h1>');";
                        break;
                    }
                    foreach($data['CURRENT_USER'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if(isset($_REQUEST[$field]) && $field != 'user_name') {
                            if($field != "password" || ($_REQUEST["password"]
                                != md5("password") && $_REQUEST["password"] ==
                                $_REQUEST["retypepassword"])) {
                                $tmp = $parent->clean(
                                    $_REQUEST[$field], "string");
                                if($tmp != $user[$upper_field]) {
                                    $user[$upper_field] = $tmp;
                                     if(AUTHENTICATION_MODE ==
                                        ZKP_AUTHENTICATION && $upper_field
                                        == "PASSWORD") {
                                        $user["ZKP_PASSWORD"] = $tmp;
                                        $user[$upper_field] ='';
                                    }
                                    if(!isset($_REQUEST['change_filter'])) {
                                        $update = true;
                                    }
                                }
                                $data['CURRENT_USER'][$field] =
                                    $user[$upper_field];
                            } else if($_REQUEST["password"] !=
                                $_REQUEST["retypepassword"]) {
                                $error = true;
                                break;
                            }
                        } else if (isset($user[$upper_field])){
                            if($field != "password" &&
                                $field != "retypepassword") {
                                $data['CURRENT_USER'][$field] =
                                    $user[$upper_field];
                            }
                        }
                    }
                    $data['CURRENT_USER']['password'] = md5("password");
                    $data['CURRENT_USER']['retypepassword'] = md5("password");
                    if($error) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_passwords_dont_match').
                            "</h1>')";
                    } else if($update) {
                        $user_model->updateUser($user);
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_updated').
                            "</h1>');";
                    } else if(isset($_REQUEST['change_filter'])) {
                        if($_REQUEST['change_filter'] == "group") {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_user_filter_group').
                                "</h1>');";
                        } else {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_user_filter_role').
                                "</h1>');";
                        }
                    }
                    $data['CURRENT_USER']['id'] = $user_id;
                break;
                case "deleteuser":
                    $user_id =
                        $signin_model->getUserId($username);
                    if($user_id <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ).
                            "</h1>')";
                    } else if(in_array(
                        $user_id, array(ROOT_ID, PUBLIC_USER_ID))){
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_cant_delete_builtin'
                                ).
                            "</h1>')";
                    } else {
                        $user_model->deleteUser($username);
                        unset($data['USER_NAMES'][$username]);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_deleted') .
                                "</h1>')";
                    }
                break;
                case "adduserrole":
                    $found_user = true;
                    if( $user_id <= 0 ) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>')";
                        $found_user = false;
                    } else  if(!($role_id = $role_model->getRoleId(
                        $select_role))) {
                        $data['FORM_TYPE'] = "edituser";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                    } else if($role_model->checkUserRole($user_id,
                        $role_id)) {
                        $data['FORM_TYPE'] = "edituser";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_already_added'
                            ). "</h1>')";
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $role_model->addUserRole($user_id, $role_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_added').
                            "</h1>');\n";
                        $this->getUserRolesData($data, $user_id);
                    }
                    if($found_user) {
                        $user = $user_model->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                    }
                break;
                case "addusergroup":
                    $found_user = true;
                    if( $user_id <= 0 ) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>')";
                        $found_user = false;
                    } else if(!($group_id = $group_model->getGroupId(
                        $select_group))) {
                        $data['FORM_TYPE'] = "edituser";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_doesnt_exists'
                            ). "</h1>')";
                    } else if($group_model->checkUserGroup($user_id,
                        $group_id)){
                        $data['FORM_TYPE'] = "edituser";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_already_added'
                            ). "</h1>')";
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $group_model->addUserGroup($user_id,
                            $group_id);
                        $this->getUserGroupsData($data, $user_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_added').
                            "</h1>');\n";
                    }
                    if($found_user) {
                        $user = $user_model->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                    }
                break;
                case "deleteuserrole":
                    $found_user = true;
                    if($user_id <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>');\n";
                        $found_user = false;
                    } else if(!($role_model->checkUserRole($user_id,
                        $select_role))) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                                ). "</h1>');\n";
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $role_model->deleteUserRole($user_id,
                            $select_role);
                        $this->getUserRolesData($data, $user_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_deleted') .
                            "</h1>');\n";
                    }
                    if($found_user) {
                        $user = $user_model->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                    }
                break;
                case "deleteusergroup":
                    $found_user = true;
                    if($user_id <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>');\n";
                        $found_user = false;
                    } else if(!($group_model->checkUserGroup($user_id,
                        $select_group))) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_doesnt_exists'
                                ). "</h1>');\n";
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $group_model->deleteUserGroup($user_id,
                            $select_group);
                        $this->getUserGroupsData($data, $user_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_group_deleted') .
                            "</h1>');\n";
                    }
                    if($found_user) {
                        $user = $user_model->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                    }
                break;
                case "search":
                    $data["FORM_TYPE"] = "search";
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                        array('user', 'first', 'last', 'email', 'status'),
                        array('status'), "_name");
                break;
                case "updatestatus":
                    $user_id = $signin_model->getUserId($username);
                    if(!isset($data['STATUS_CODES'][$_REQUEST['userstatus']]) ||
                        $user_id == 1) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>');\n";
                    } else {
                        $user_model->updateUserStatus($user_id,
                            $_REQUEST['userstatus']);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_userstatus_updated') .
                            "</h1>');\n";
                    }
                break;
            }
        }
        $parent->pagingLogic($data, $user_model, "USERS",
            DEFAULT_ADMIN_PAGING_NUM, $search_array, "");
        return $data;
    }
    /**
     * Uses $_REQUEST and $user_id to look up all the roles that a user
     * has subject to $_REQUEST['role_limit'] and $_REQUEST['role_filter'].
     * Information about these roles is added as fields to
     * $data[NUM_USER_ROLES'] and $data['USER_ROLES']
     *
     * @param array& $data data for the manageUsers view.
     * @param int $user_id user to look up roles for
     */
    function getUserRolesData(&$data, $user_id)
    {
        $parent = $this->parent;
        $role_model = $parent->model("role");
        $data['visible_roles'] = (isset($_REQUEST['visible_roles']) &&
            $_REQUEST['visible_roles']=='true') ? 'true' : 'false';
        if($data['visible_roles'] == 'false') {
            unset($_REQUEST['role_filter']);
            unset($_REQUEST['role_limit']);
        }
        if(isset($_REQUEST['role_filter'])) {
            $role_filter = $parent->clean(
                $_REQUEST['role_filter'], 'string');
        } else {
            $role_filter = "";
        }
        $data['ROLE_FILTER'] = $role_filter;
        $data['NUM_USER_ROLES'] =
            $role_model->countUserRoles($user_id, $role_filter);
        if(isset($_REQUEST['role_limit'])) {
            $role_limit = min($parent->clean(
                $_REQUEST['role_limit'], 'int'),
                $data['NUM_USER_ROLES']);
            $role_limit = max($role_limit, 0);
        } else {
            $role_limit = 0;
        }
        $data['ROLE_LIMIT'] = $role_limit;
        $data['USER_ROLES'] =
            $role_model->getUserRoles($user_id, $role_filter,
            $role_limit);
    }
    /**
     * Uses $_REQUEST and $user_id to look up all the groups that a user
     * belongs to subject to $_REQUEST['group_limit'] and
     * $_REQUEST['group_filter']. Information about these roles is added as
     * fields to $data[NUM_USER_GROUPS'] and $data['USER_GROUPS']
     *
     * @param array& $data data for the manageUsers view.
     * @param int $user_id user to look up roles for
     */
    function getUserGroupsData(&$data, $user_id)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $data['visible_groups'] = (isset($_REQUEST['visible_groups']) &&
            $_REQUEST['visible_groups']=='true') ? 'true' : 'false';
        if($data['visible_groups'] == 'false') {
            unset($_REQUEST['group_filter']);
            unset($_REQUEST['group_limit']);
        }
        if(isset($_REQUEST['group_filter'])) {
            $group_filter = $parent->clean(
                $_REQUEST['group_filter'], 'string');
        } else {
            $group_filter = "";
        }
        $data['GROUP_FILTER'] = $group_filter;
        $data['NUM_USER_GROUPS'] =
            $group_model->countUserGroups($user_id, $group_filter);
        if(isset($_REQUEST['group_limit'])) {
            $group_limit = min($parent->clean(
                $_REQUEST['group_limit'], 'int'),
                $data['NUM_USER_GROUPS']);
            $group_limit = max($group_limit, 0);
        } else {
            $group_limit = 0;
        }
        $data['GROUP_LIMIT'] = $group_limit;
        $data['USER_GROUPS'] =
            $group_model->getUserGroups($user_id, $group_filter,
            $group_limit);
    }
    /**
     * Used to handle the manage role activity.
     *
     * This activity allows new roles to be added, old roles to be
     * deleted and allows activities to be added to/deleted from a role
     *
     * @return array $data information about roles in the system, activities,
     *     etc. as well as status messages on performing a given sub activity
     *
     */
    function manageRoles()
    {
        $parent = $this->parent;
        $role_model = $parent->model("role");
        $possible_arguments = array("addactivity", "addrole",
                "deleteactivity","deleterole", "editrole", "search");
        $data["ELEMENT"] = "manageroles";
        $data['SCRIPT'] = "";
        $data['FORM_TYPE'] = "addrole";

        $search_array = array();
        $data['CURRENT_ROLE'] = array("name" => "");
        $data['PAGING'] = "";
        if(isset($_REQUEST['arg']) && $_REQUEST['arg'] == 'editrole') {
            if(isset($_REQUEST['selectactivity']) &&
                $_REQUEST['selectactivity'] >= 0) {
                $_REQUEST['arg'] = "addactivity";
            }
        }
        if(isset($_REQUEST['name'])) {
            $name = $parent->clean($_REQUEST['name'], "string" );
             $data['CURRENT_ROLE']['name'] = $name;
        } else {
            $name = "";
        }
        if($name != "" ) {
            $role_id = $role_model->getRoleId($name);
            $data['ROLE_ACTIVITIES'] =
                $role_model->getRoleActivities($role_id);
            $all_activities = $parent->model("activity")->getActivityList();
            $activity_ids = array();
            $activity_names = array();
            foreach($all_activities as $activity) {
                $activity_ids[] = $activity['ACTIVITY_ID'];
                $activity_names[$activity['ACTIVITY_ID']] =
                    $activity['ACTIVITY_NAME'];
            }

            $available_activities = array();
            $role_activity_ids = array();
            foreach($data['ROLE_ACTIVITIES'] as $activity) {
                $role_activity_ids[] = $activity["ACTIVITY_ID"];
            }
            $tmp = array();
            foreach($all_activities as $activity) {
                if(!in_array($activity["ACTIVITY_ID"], $role_activity_ids) &&
                    !isset($tmp[$activity["ACTIVITY_ID"]])) {
                    $tmp[$activity["ACTIVITY_ID"]] = true;
                    $available_activities[] = $activity;
                }
            }
            $data['AVAILABLE_ACTIVITIES'][-1] =
                tl('accountaccess_component_select_activityname');
            foreach($available_activities as $activity) {
                $data['AVAILABLE_ACTIVITIES'][$activity['ACTIVITY_ID']] =
                    $activity['ACTIVITY_NAME'];
            }
            if(isset($_REQUEST['selectactivity'])) {
                $select_activity =
                    $parent->clean($_REQUEST['selectactivity'], "int" );
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
                case "addactivity":
                    $data['FORM_TYPE'] = "editrole";
                    if(($role_id = $role_model->getRoleId($name)) <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                    } else if(!in_array($select_activity, $activity_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ). "</h1>')";
                    } else {
                        $role_model->addActivityRole(
                            $role_id, $select_activity);
                        unset($data['AVAILABLE_ACTIVITIES'][$select_activity]);
                        $data['ROLE_ACTIVITIES'] =
                            $role_model->getRoleActivities($role_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_activity_added').
                            "</h1>')";
                    }
                break;
                case "addrole":
                    if($name != "" && $role_model->getRoleId($name) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_exists').
                            "</h1>')";
                    } else if($name != "") {
                        $role_model->addRole($name);
                        $data['CURRENT_ROLE']['name'] = "";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_added').
                            "</h1>')";
                   }
                   $data['CURRENT_ROLE']['name'] = "";
                break;
                case "deleteactivity":
                   $data['FORM_TYPE'] = "editrole";
                   if(($role_id = $role_model->getRoleId($name)) <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                    } else if(!in_array($select_activity, $activity_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ). "</h1>')";
                    } else {
                        $role_model->deleteActivityRole(
                            $role_id, $select_activity);
                        $data['ROLE_ACTIVITIES'] =
                            $role_model->getRoleActivities($role_id);
                        $data['AVAILABLE_ACTIVITIES'][$select_activity] =
                            $activity_names[$select_activity];
                        $data['SELECT_ACTIVITY'] = -1;
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_activity_deleted').
                            "</h1>')";
                    }
                break;
                case "deleterole":
                    if(($role_id = $role_model->getRoleId($name)) <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                    } else {
                        $role_model->deleteRole($role_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_deleted').
                            "</h1>')";
                    }
                    $data['CURRENT_ROLE']['name'] = "";
                break;
                case "editrole":
                    $data['FORM_TYPE'] = "editrole";
                    $role = false;
                    if($name) {
                        $role = $role_model->getRole($name);
                    }
                    if($role === false) {
                        $data['FORM_TYPE'] = "addrole";
                        break;
                    }
                    $update = false;
                    foreach($data['CURRENT_ROLE'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if(isset($_REQUEST[$field]) && $field != 'name') {
                            $role[$upper_field] = $parent->clean(
                                $_REQUEST[$field], "string");
                            $data['CURRENT_ROLE'][$field] =
                                $role[$upper_field];
                            $update = true;
                        } else if (isset($role[$upper_field])){
                            $data['CURRENT_ROLE'][$field] =
                                $role[$upper_field];
                        }
                    }
                    if($update) {
                        $role_model->updateRole($role);
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_role_updated').
                            "</h1>');";
                    }
                break;
                case "search":
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                        array('name'));
                break;
            }
        }
        $parent->pagingLogic($data, $role_model, "ROLES",
            DEFAULT_ADMIN_PAGING_NUM, $search_array, "");
        return $data;
    }
}
