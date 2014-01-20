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
    var $activities = array("signin", "manageAccount", "manageUsers",
        "manageRoles", "manageGroups");

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
            $parent->signinModel->getUserId($_REQUEST['username']);
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
        $possible_arguments = array("changepassword","changeemail");
        $data["ELEMENT"] = "manageaccountElement";
        $data['SCRIPT'] = "";
        $data['MESSAGE'] = "";
        $old_email = $parent->signinModel->getEmail($_SESSION['USER_ID']);
        $data["OLD_EMAIL"] = $old_email;
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "changepassword":
                    if($_REQUEST['re_type_password'] !=
                            $_REQUEST['new_password']){
                        $data["MESSAGE"] =
                            tl('accountaccess_component_passwords_dont_match');
                        $data['SCRIPT'] .=
                            "doMessage('<h1 class=\"red\" >". $data["MESSAGE"].
                            "</h1>')";
                        return $data;
                    }
                    $username =
                        $parent->signinModel->getUserName($_SESSION['USER_ID']);
                    $result = $parent->signinModel->checkValidSignin($username,
                        $parent->clean($_REQUEST['old_password'], "string") );
                    if(!$result) {
                        $data["MESSAGE"] =
                            tl('accountaccess_component_invalid_old_password');
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            $data["MESSAGE"]."</h1>')";
                        return $data;
                    }
                    $parent->signinModel->changePassword($username,
                        $parent->clean($_REQUEST['new_password'], "string"));
                    $data["MESSAGE"] =
                        tl('accountaccess_component_change_password');
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        $data["MESSAGE"]."</h1>')";
                break;

                case "changeemail":
                   if($_REQUEST['re_type_email'] != $_REQUEST['new_email']) {
                        $data["MESSAGE"] =
                            tl('accountaccess_component_emails_dont_match');
                        $data['SCRIPT'] .=
                            "doMessage('<h1 class=\"red\" >". $data["MESSAGE"].
                            "</h1>')";
                        return $data;
                    }
                    $username =
                        $parent->signinModel->getUserName($_SESSION['USER_ID']);
                    $result = $parent->signinModel->checkValidEmail($username,
                        $parent->clean($_REQUEST['old_email'], "string") );
                    if(!$result) {
                        $data["MESSAGE"] =
                            tl('accountaccess_component_invalid_old_email');
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            $data["MESSAGE"]."</h1>')";
                        return $data;
                    }
                    $parent->signinModel->changeEmail($username,
                        $parent->clean($_REQUEST['new_email'], "string"));
                    $data["MESSAGE"] = tl('accountaccess_component_change_email'
                        );
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
     *      as well as status messages on performing a given sub activity
     */
    function manageUsers()
    {
        $parent = $this->parent;
        $possible_arguments = array("adduser", 'edituser', 'searchusers',
            "deleteuser", "adduserrole", "deleteuserrole",
            "addusergroup", "deleteusergroup", "updatestatus");

        $data["ELEMENT"] = "manageusersElement";
        $data['SCRIPT'] = "";
        $data['STATUS_CODES'] = array(
            ACTIVE_STATUS => tl('accountaccess_component_active_status'),
            INACTIVE_STATUS => tl('accountaccess_component_inactive_status'),
            BANNED_STATUS => tl('accountaccess_component_banned_status'),
        );
        $data['USERS_SHOW_CHOICES'] = array(
            10 => 10, 20 => 20, 50 => 50, 100 => 100, 200=> 200
        );
        $data['COMPARISON_TYPES'] = array(
            "=" => tl('accountaccess_component_equal'),
            "!=" => tl('accountaccess_component_not_equal'),
            "CONTAINS" => tl('accountaccess_component_contains'),
            "BEGINS WITH" => tl('accountaccess_component_begins_with'),
            "ENDS WITH" => tl('accountaccess_component_ends_with'),
        );
        $data['STATUS_COMPARISON_TYPES'] = array(
            "=" => tl('accountaccess_component_equal'),
            "!=" => tl('accountaccess_component_not_equal'),
        );
        $data['SORT_TYPES'] = array(
            "NONE" => tl('accountaccess_component_no_sort'),
            "ASC" => tl('accountaccess_component_sort_ascending'),
            "DESC" => tl('accountaccess_component_sort_descending'),
        );
        $data['FORM_TYPE'] = "adduser";
        $search_array = array();
        $username = "";
        if(isset($_REQUEST['user_name'])) {
            $username = $parent->clean($_REQUEST['user_name'], "string" );
        }
        if($username == "" && isset($_REQUEST['arg']) && $_REQUEST['arg'] 
            != "searchusers") {
            unset($_REQUEST['arg']);
        }
        if(isset($_REQUEST['arg']) && $_REQUEST['arg'] == 'edituser') {
            if(isset($_REQUEST['selectrole']) && $_REQUEST['selectrole'] >= 0) {
                $_REQUEST['arg'] = "adduserrole";
            }
            if(isset($_REQUEST['selectgroup'])&&$_REQUEST['selectgroup'] >= 0) {
                $_REQUEST['arg'] = "addusergroup";
            }
        }
        $userid = -1;
        if($username != "") {
            $userid = $parent->signinModel->getUserId($username);
            $data['SELECT_USER'] = $username;
            if($userid) {
                $data = array_merge($data, $this->getRoleArrays($userid));
                $data = array_merge($data, $this->getGroupArrays($userid));
            }
            $select_role = "-1";
            if(isset($data['SELECT_ROLE'])) {
                $select_role = $data['SELECT_ROLE'];
            }
            $role_ids = array();
            if(isset($data['ROLE_IDS'])) {
                $role_ids = $data['ROLE_IDS'];
            }
            $select_group = "-1";
            if(isset($data['SELECT_GROUP'])) {
                $select_group = $data['SELECT_GROUP'];
            }
            $group_ids = array();
            if(isset($data['GROUP_IDS'])) {
                $group_ids = $data['GROUP_IDS'];
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
                    } else if($parent->signinModel->getUserId($username) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_exists').
                            "</h1>')";
                    } else if(!isset($data['STATUS_CODES'][
                        $_REQUEST['status']])) {
                        $_REQUEST['status'] = INACTIVE_STATUS;
                    } else {
                        $parent->userModel->addUser($username,
                            $parent->clean($_REQUEST['password'], "string"),
                            $parent->clean($_REQUEST['first_name'], "string"),
                            $parent->clean($_REQUEST['last_name'], "string"),
                            $parent->clean($_REQUEST['email'], "string"),
                            $_REQUEST['status']
                            );
                        $data['USER_NAMES'][$username] = $username;
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_added').
                            "</h1>')";
                    }
                break;
                case "edituser":
                    $data['FORM_TYPE'] = "edituser";
                    $user = $parent->userModel->getUser($username);
                    $update = false;
                    $error = false;
                    foreach($data['CURRENT_USER'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if(isset($_REQUEST[$field]) && $field != 'user_name') {
                            if($field != "password" || ($_REQUEST["password"]
                                != md5("password") && $_REQUEST["password"] ==
                                $_REQUEST["retypepassword"])) {
                                $user[$upper_field] = $parent->clean(
                                    $_REQUEST[$field], "string");
                                $data['CURRENT_USER'][$field] = 
                                    $user[$upper_field];
                                $update = true;
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
                        $parent->userModel->updateUser($user);
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_updated').
                            "</h1>');";
                    }
                break;
                case "deleteuser":
                    $data['SELECT_ROLE'] = -1;
                    unset($data['AVAILABLE_ROLES']);
                    unset($data['SELECT_ROLES']);
                    if(!($parent->signinModel->getUserId($username) > 0)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ).
                            "</h1>')";
                    } else {
                        $parent->userModel->deleteUser($username);
                        unset($data['USER_NAMES'][$username]);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_deleted') .
                                "</h1>')";
                    }
                break;

                case "adduserrole":
                    if( $userid <= 0 ) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>')";
                    } else  if(!in_array($select_role, $role_ids)) {
                        $data['FORM_TYPE'] = "edituser";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                        $user = $parent->userModel->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                    } else if(!isset($data['AVAILABLE_ROLES'][$select_role])){
                        $data['FORM_TYPE'] = "edituser";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_already_added'
                            ). "</h1>')";
                        $user = $parent->userModel->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $user = $parent->userModel->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                        $parent->userModel->addUserRole($userid, $select_role);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_added').
                            "</h1>');\n";
                        unset($data['AVAILABLE_ROLES'][$select_role]);
                        $data['SELECT_ROLE'] = -1;
                        $data['SELECT_ROLES'] =
                            $parent->userModel->getUserRoles($userid);
                    }
                break;

                case "addusergroup":

                    if( $userid <= 0 ) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>')";
                    } else if(!in_array($select_group, $group_ids)) {
                        $data['FORM_TYPE'] = "edituser";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_doesnt_exists'
                            ). "</h1>')";
                        $user = $parent->userModel->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                    } else if(!isset($data['AVAILABLE_GROUPS'][$select_group])){
                        $data['FORM_TYPE'] = "edituser";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_already_added'
                            ). "</h1>')";
                        $user = $parent->userModel->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $user = $parent->userModel->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                        $parent->groupModel->addUserGroup($userid,
                            $select_group);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_added').
                            "</h1>');\n";
                        unset($data['AVAILABLE_GROUPS'][$select_group]);
                        $data['SELECT_GROUP'] = -1;
                        $data['SELECT_GROUPS'] =
                            $parent->groupModel->getUserGroups($userid);
                    }
                break;

                case "deleteuserrole":
                    if($userid <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>');\n";
                    } else if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                                ). "</h1>');\n";
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $user = $parent->userModel->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                        $parent->userModel->deleteUserRole($userid,
                            $select_role);
                        $data['SELECT_ROLES'] =
                            $parent->userModel->getUserRoles($userid);
                        $data['AVAILABLE_ROLES'][$select_role] =
                            $data['SELECT_ROLENAME'];
                        $data['SELECT_ROLE'] = -1;
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_deleted') .
                            "</h1>');\n";
                    }
                break;

                case "deleteusergroup":
                    if($userid <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>');\n";
                    } else if(!in_array($select_group, $group_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_doesnt_exists'
                                ). "</h1>');\n";
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $user = $parent->userModel->getUser($username);
                        foreach($user as $field => $value) {
                            $data['CURRENT_USER'][strtolower($field)] = $value;
                        }
                        $parent->groupModel->deleteUserGroup($userid,
                            $select_group);
                        $data['SELECT_GROUPS'] =
                            $parent->groupModel->getUserGroups($userid);
                        $data['AVAILABLE_GROUPS'][$select_group] =
                            $data['SELECT_GROUPNAME'];
                        $data['SELECT_GROUP'] = -1;
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_group_deleted') .
                            "</h1>');\n";
                    }
                break;

                case "updatestatus":
                    $userid = $parent->signinModel->getUserId($username);
                    if(!isset($data['STATUS_CODES'][$_REQUEST['userstatus']]) ||
                        $userid == 1) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>');\n";
                    } else {
                        $parent->userModel->updateUserStatus($userid,
                            $_REQUEST['userstatus']);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_userstatus_updated') .
                            "</h1>');\n";
                    }
                break;

                case "searchusers":
                    $data['STATUS_CODES'][0] = 
                        tl('accountaccess_component_any_status');
                    ksort($data['STATUS_CODES']);
                    $data["FORM_TYPE"] = "searchusers";
                    $comparison_fields = array('user',
                        'first', 'last', 'email', 'status');
                    $paging = "";
                    foreach($comparison_fields as $comparison_start) {
                        $comparison = $comparison_start."_comparison";
                        $comparison_types = ($comparison == 'status_comparison')
                            ? 'STATUS_COMPARISON_TYPES' : 'COMPARISON_TYPES';
                        $data[$comparison] = (isset($_REQUEST[$comparison]) &&
                            isset($data[$comparison_types][
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
                        $field_name = $field."_name";
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
            }
        }
        $num_users = $parent->userModel->getUserCount($search_array);
        $data['NUM_USERS'] = $num_users;
        $users_show = (isset($_REQUEST['users_show']) && 
            isset($data['USERS_SHOW_CHOICES'][$_REQUEST['users_show']])) ?
            $parent->clean($_REQUEST['users_show'], 'int') : 50;
        $data['users_show'] = $users_show;
        $data['START_ROW'] = 0;
        if(isset($_REQUEST['start_row'])) {
            $data['START_ROW'] = min(
                max(0, $parent->clean($_REQUEST['start_row'],"int")),
                $num_users);
        }
        $data['END_ROW'] = min($data['START_ROW'] + $users_show, $num_users);
        if(isset($_REQUEST['start_row'])) {
            $data['END_ROW'] = max($data['START_ROW'],
                    min($parent->clean($_REQUEST['end_row'],"int"),$num_users));
        }
        $data["USERS"] = $parent->userModel->getUsers($data['START_ROW'],
            $users_show, $search_array);
        $data['NEXT_START'] = $data['END_ROW'];
        $data['NEXT_END'] = min($data['NEXT_START'] + $users_show, $num_users);
        $data['PREV_START'] = max(0, $data['START_ROW'] - $users_show);
        $data['PREV_END'] = $data['START_ROW'];
        return $data;
    }

    /**
     * Sets up the arrays to hold the dropdown for the roles a given user could
     * add (AVAILABLE_ROLES) to those that they already have
     *
     * @param int $userid the id of the user we are adding roles for
     */
    function getRoleArrays($userid)
    {
        $parent = $this->parent;
        $data['SELECT_ROLES'] = $parent->userModel->getUserRoles($userid);
        $all_roles = $parent->roleModel->getRoleList();
        $role_ids = array();
        if(isset($_REQUEST['selectrole'])) {
            $select_role = $parent->clean($_REQUEST['selectrole'], "string");
        } else {
            $select_role = "";
        }
        $select_rolename = "";
        foreach($all_roles as $role) {
            $role_ids[] = $role['ROLE_ID'];
            if($select_role == $role['ROLE_ID']) {
                $select_rolename = $role['ROLE_NAME'];
            }
        }
        $select_role_ids = array();
        foreach($data['SELECT_ROLES'] as $role) {
            $select_role_ids[] = $role['ROLE_ID'];
        }
        $available_roles = array();
        $tmp = array();
        foreach($all_roles as $role) {
            if(!in_array($role['ROLE_ID'], $select_role_ids) &&
                !isset($tmp[$role['ROLE_ID']])) {
                $tmp[$role['ROLE_ID']] = true;
                $available_roles[] = $role;
            }
        }
        $data['ROLE_IDS'] = $role_ids;
        $data['AVAILABLE_ROLES'][-1] =
            tl('accountaccess_component_add_role');

        foreach($available_roles as $role) {
            $data['AVAILABLE_ROLES'][$role['ROLE_ID']]= $role['ROLE_NAME'];
        }

        if($select_role != "") {
            $data['SELECT_ROLE'] = $select_role;
            $data['SELECT_ROLENAME'] = $select_rolename;
        } else {
            $data['SELECT_ROLE'] = -1;
        }
        return $data;
    }

    /**
     * Sets up the arrays to hold the dropdown for the groups a given user could
     * add (AVAILABLE_GROUPS) to those that they already have
     *
     * @param int $userid the id of the user we are adding groups to
     */
    function getGroupArrays($userid)
    {
        $parent = $this->parent;
        $data = array();
        $data['SELECT_GROUPS'] =
            $parent->groupModel->getUserGroups($userid);
        $all_groups = $parent->groupModel->getGroupList();
        $group_ids = array();
        if(isset($_REQUEST['selectgroup'])) {
            $select_group = $parent->clean($_REQUEST['selectgroup'],"string");
        } else {
            $select_group = "";
        }
        $select_groupname = "";
        foreach($all_groups as $group) {
            $group_ids[] = $group['GROUP_ID'];
            if($select_group == $group['GROUP_ID']) {
                $select_groupname = $group['GROUP_NAME'];
            }
        }
        $data['GROUP_IDS'] = $group_ids;
        $select_group_ids = array();
        foreach($data['SELECT_GROUPS'] as $group) {
            $select_group_ids[] = $group['GROUP_ID'];
        }
        $available_groups = array();
        $tmp = array();
        foreach($all_groups as $group) {
            if(!in_array($group['GROUP_ID'], $select_group_ids) &&
                !isset($tmp[$group['GROUP_ID']])) {
                $tmp[$group['GROUP_ID']] = true;
                $available_groups[] = $group;
            }
        }

        $data['AVAILABLE_GROUPS'][-1] =
            tl('accountaccess_component_add_group');

        foreach($available_groups as $group) {
            $data['AVAILABLE_GROUPS'][$group['GROUP_ID']]= $group['GROUP_NAME'];
        }

        if($select_group != "") {
            $data['SELECT_GROUP'] = $select_group;
            $data['SELECT_GROUPNAME'] = $select_groupname;
        } else {
            $data['SELECT_GROUP'] = -1;
        }

        return $data;
    }
    /**
     * Used to handle the manage role activity.
     *
     * This activity allows new roles to be added, old roles to be
     * deleted and allows activities to be added to/deleted from a role
     *
     * @return array $data information about roles in the system, activities,
     *      etc. as well as status messages on performing a given sub activity
     *
     */
    function manageRoles()
    {
        $parent = $this->parent;
        $possible_arguments = array("addactivity", "addrole",
                "deleteactivity","deleterole", "editrole", "searchroles");
        $data["ELEMENT"] = "managerolesElement";
        $data['SCRIPT'] = "";
        $data['ROLES_SHOW_CHOICES'] = array(
            10 => 10, 20 => 20, 50 => 50, 100 => 100, 200=> 200
        );
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
            $role_id = $parent->roleModel->getRoleId($name);
            $data['ROLE_ACTIVITIES'] =
                $parent->roleModel->getRoleActivities($role_id);
            $all_activities = $parent->activityModel->getActivityList();
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
                case "addrole":
                    if($parent->roleModel->getRoleId($name) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_exists').
                            "</h1>')";
                    } else {
                        $parent->roleModel->addRole($name);
                        $data['CURRENT_ROLE']['name'] = "";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_added').
                            "</h1>')";
                   }
                   $data['CURRENT_ROLE']['name'] = "";
                break;

                case "deleterole":
                    if(($role_id = $parent->roleModel->getRoleId($name)) <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                    } else {
                        $parent->roleModel->deleteRole($role_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_deleted').
                            "</h1>')";
                    }
                    $data['CURRENT_ROLE']['name'] = "";
                break;

                case "editrole":
                    $data['FORM_TYPE'] = "editrole";
                    $role = $parent->roleModel->getRole($name);
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
                        $parent->roleModel->updateRole($role);
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_role_updated').
                            "</h1>');";
                    }
                break;
                case "searchroles":
                    $data['FORM_TYPE'] = "searchroles";
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

                case "addactivity":
                    $data['FORM_TYPE'] = "editrole";
                    if(($role_id = $parent->roleModel->getRoleId($name)) <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                    } else if(!in_array($select_activity, $activity_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ). "</h1>')";
                    } else {
                        $parent->roleModel->addActivityRole(
                            $role_id, $select_activity);
                        unset($data['AVAILABLE_ACTIVITIES'][$select_activity]);
                        $data['ROLE_ACTIVITIES'] =
                            $parent->roleModel->getRoleActivities($role_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_activity_added').
                            "</h1>')";
                    }
                break;

                case "deleteactivity":
                   $data['FORM_TYPE'] = "editrole";
                   if(($role_id = $parent->roleModel->getRoleId($name)) <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                    } else if(!in_array($select_activity, $activity_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ). "</h1>')";
                    } else {
                        $parent->roleModel->deleteActivityRole(
                            $role_id, $select_activity);
                        $data['ROLE_ACTIVITIES'] =
                            $parent->roleModel->getRoleActivities($role_id);
                        $data['AVAILABLE_ACTIVITIES'][$select_activity] =
                            $activity_names[$select_activity];
                        $data['SELECT_ACTIVITY'] = -1;
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_activity_deleted').
                            "</h1>')";
                    }
                break;
            }
        }
        $num_roles = $parent->roleModel->getRoleCount($search_array);
        $data['NUM_ROLES'] = $num_roles;
        $roles_show = (isset($_REQUEST['roles_show']) && 
            isset($data['ROLES_SHOW_CHOICES'][$_REQUEST['roles_show']])) ?
            $parent->clean($_REQUEST['roles_show'], 'int') : 50;
        $data['roles_show'] = $roles_show;
        $data['START_ROW'] = 0;
        if(isset($_REQUEST['start_row'])) {
            $data['START_ROW'] = min(
                max(0, $parent->clean($_REQUEST['start_row'],"int")),
                $num_roles);
        }
        $data['END_ROW'] = min($data['START_ROW'] + $roles_show, $num_roles);
        if(isset($_REQUEST['start_row'])) {
            $data['END_ROW'] = max($data['START_ROW'],
                    min($parent->clean($_REQUEST['end_row'],"int"),$num_roles));
        }
        $data["ROLES"] = $parent->roleModel->getRoles($data['START_ROW'],
            $roles_show, $search_array);
        $data['NEXT_START'] = $data['END_ROW'];
        $data['NEXT_END'] = min($data['NEXT_START'] + $roles_show, $num_roles);
        $data['PREV_START'] = max(0, $data['START_ROW'] - $roles_show);
        $data['PREV_END'] = $data['START_ROW'];
        return $data;
    }

    /**
     * Used to handle the manage group activity.
     *
     * This activity allows new groups to be created out of a set of users.
     * It allows admin rights for the group to be transfered and it allows roles
     * to be added to a group. One can also delete groups and roles from groups.
     *
     * @return array $data information about groups in the system
     */
    function manageGroups()
    {
        $parent = $this->parent;
        $possible_arguments = array("addgroup", "deletegroup",
            "viewgroups", "selectgroup", "adduser", "deleteuser",
            "addrole", "deleterole", "updategroup");

        $data["ELEMENT"] = "managegroupsElement";
        $data['SCRIPT'] =
            "selectGroup = elt('select-group'); selectGroup.onchange =".
            " submitViewGroups;";

        if(isset($_REQUEST['groupname'])) {
            $groupname = $parent->clean($_REQUEST['groupname'], "string" );
        }

        if($_SESSION['USER_ID'] == '1') {
            $groups = $parent->groupModel->getGroupList();
        } else {
            $groups = $parent->groupModel->getUserGroups(
                $_SESSION['USER_ID']);
        }

        $group_ids = array();
        $base_option = tl('accountaccess_component_select_groupname');
        $data['GROUP_NAMES'] = array();
        $data['GROUP_NAMES'][-1] = $base_option;

        if(isset($_REQUEST['groupname'])) {
            $groupname = $parent->clean($_REQUEST['groupname'], "string" );
        }

        foreach($groups as $group) {
            $data['GROUP_NAMES'][$group['GROUP_ID']]= $group['GROUP_NAME'];
            $group_ids[] = $group['GROUP_ID'];
        }

        if($_SESSION['USER_ID'] == '1') {
            $groups = $parent->groupModel->getGroupList();
        } else {
            $groups = $parent->groupModel->getGroupListbyCreator(
                $_SESSION['USER_ID']);
        }

        $group_ids = array();
        $base_option = tl('accountaccess_component_select_groupname');
        $data['DELETE_GROUP_NAMES'] = array();
        $data['DELETE_GROUP_NAMES'][-1] = $base_option;

        if(isset($_REQUEST['groupname'])) {
            $groupname = $parent->clean($_REQUEST['groupname'], "string" );
        }

        foreach($groups as $deletegroup) {
            $data['DELETE_GROUP_NAMES'][$deletegroup['GROUP_ID']]=
                $deletegroup['GROUP_NAME'];
            $group_ids[] = $deletegroup['GROUP_ID'];
        }

        $data['SELECT_GROUP'] = -1;
        $data['SELECT_USER'] = -1;

        if(isset($_REQUEST['selectgroup'])) {
            $select_group = $parent->clean($_REQUEST['selectgroup'], "string" );
        } else {
            $select_group = "";
        }

        if(isset($_REQUEST['arg']) && !($_REQUEST['arg'] == 'deletegroup'
            || $_REQUEST['arg'] == 'addgroup')) {

            $data['SELECT_GROUP'] = $select_group;
            $grouproles = $parent->groupModel->getGroupRoles($select_group);
            $data['GROUP_ROLES'] = $grouproles;
            $rolenames = $parent->roleModel->getRoleList();
            $base_option = tl('accountaccess_component_select_rolename');

            if(isset($_REQUEST['arg']) && !($_REQUEST['arg'] == 'deletegroup'
                || $_REQUEST['arg'] == 'addgroup')) {
                $data['ROLE_NAMES'] = array();
                $role_ids = array();
                $data['ROLE_NAMES'][-1] = $base_option;

                foreach($rolenames as $rolename) {
                    $data['ROLE_NAMES'][$rolename['ROLE_ID']] =
                        $rolename['ROLE_NAME'];
                    $role_ids[] = $rolename['ROLE_ID'];
                }
            }

            if(isset($_REQUEST['selectrole'])) {
                $select_role = $parent->clean($_REQUEST['selectrole'],"string");
            } else {
                $select_role = "";
            }

            $usergroups = $parent->groupModel->getGroupUsers($select_group);
            $data['GROUP_USERS'] =$usergroups;

            if(isset($_REQUEST['selectuser'])) {
                $select_user = $parent->clean($_REQUEST['selectuser'],"string");
            } else {
                $select_user = "";
            }

            if($select_group != "-1") {
                $usernames = $parent->userModel->getUserIdUsernameList();
                $base_option = tl('accountaccess_component_select_username');
                $data['USER_NAMES'] = array();
                $user_ids = array();
                $data['USER_NAMES'][-1] = $base_option;
                foreach($usernames as $user) {
                    $user_ids[] = $user['USER_ID'];
                    if($select_user == $user['USER_ID']) {
                        $select_username = $user['USER_NAME'];
                    }
                }
                foreach($usernames as $username) {
                    $data['USER_NAMES'] [$username['USER_ID']] =
                        $username['USER_NAME'];
                    $user_ids[] = $username['USER_ID'];
                }
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

        if(!isset($_REQUEST['arg']) || !in_array($_REQUEST['arg'],
            $possible_arguments)) {
            return $data;
        }
        $data['SELECT_ROLE'] = -1;
        switch($_REQUEST['arg'])
        {
            case "addgroup":
                $data['SELECT_GROUP'] = -1;
                if($parent->groupModel->getGroupId($groupname) > 0) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_groupname_exists').
                        "</h1>')";
                    return $data;
                }
                $parent->groupModel->addGroup(
                    $groupname, $_SESSION['USER_ID']);
                $groupid = $parent->groupModel->getGroupId($groupname);
                $data['GROUP_NAMES'][$groupid] = $groupname;
                $data['DELETE_GROUP_NAMES'][$groupid] = $groupname;
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_groupname_added').
                    "</h1>')";
            break;
            case "deletegroup":
                if($select_group == -1){
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                  tl('accountaccess_component_groupname_doesnt_exists').
                    "</h1>')";
                }else{
                 $parent->groupModel->deleteGroup($select_group);
                unset($data['GROUP_NAMES'][$select_group]);
                unset($data['DELETE_GROUP_NAMES'][$select_group]);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_groupname_deleted')."</h1>')";
                }
            break;
            case "adduser":
                if(!in_array($select_user, $user_ids)) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_username_doesnt_exists').
                        "</h1>')";
                    return $data;
                }
                $parent->groupModel->addUserGroup($select_user, $select_group);
                unset($data['AVAILABLE_USERS'][$select_user]);
                $data['GROUP_USERS'] =
                    $parent->groupModel->getGroupUsers($select_group);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_user_added')."</h1>')";
            break;
            case "deleteuser":
                $parent->groupModel->deleteUserGroup(
                    $select_user, $select_group);
                $data['GROUP_USERS'] =
                    $parent->groupModel->getGroupUsers($select_group);
                $data['AVAILABLE_USERS'][$select_user] =
                    $usernames[$select_user];
                $data['SELECT_USER'] = -1;
                unset($data['GROUP_NAMES'][$select_group]);
                unset($data['GROUP_USERS']);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_user_deleted')."</h1>')";
            break;
            case "addrole":
                if(!in_array($select_group, $group_ids)) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_groupname_doesnt_exists').
                        "</h1>')";
                    return $data;
                }
                if(!in_array($select_role, $role_ids)) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_rolename_doesnt_exists').
                        "</h1>')";
                    return $data;
                }
                $parent->groupModel->addGroupRole($select_group, $select_role);
                unset($data['AVAILABLE_ROLES'][$select_role]);
                $data['GROUP_ROLES'] =
                    $parent->groupModel->getGroupRoles($select_group);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_rolename_added')."</h1>')";
            break;
            case "deleterole":
                if(!in_array($select_group, $group_ids)) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_groupname_doesnt_exists').
                        "</h1>')";
                    return $data;
                }
                if(!in_array($select_role, $role_ids)) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_rolename_doesnt_exists').
                        "</h1>')";
                    return $data;
                }
                $parent->groupModel->deleteGroupRole($select_group, $select_role
                    );
                $data['GROUP_ROLES'] =
                    $parent->groupModel->getGroupRoles($select_group);
                if(isset($rolenames[$select_role])) {
                    $data['AVAILABLE_ACTIVITIES'][$select_role] =
                        $rolenames[$select_role];
                }
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_role_deleted')."</h1>')";
            break;
            case "updategroup":
                $parent->groupModel->updateUserGroup($select_user, $select_group
                    );
            break;
        }
        return $data;
    }

}
