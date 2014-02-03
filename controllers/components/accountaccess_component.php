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
        $possible_arguments = array("adduser", 'edituser', 'search',
            "deleteuser", "adduserrole", "deleteuserrole",
            "addusergroup", "deleteusergroup", "updatestatus");

        $data["ELEMENT"] = "manageusersElement";
        $data['SCRIPT'] = "";
        $data['STATUS_CODES'] = array(
            ACTIVE_STATUS => tl('accountaccess_component_active_status'),
            INACTIVE_STATUS => tl('accountaccess_component_inactive_status'),
            BANNED_STATUS => tl('accountaccess_component_banned_status'),
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
            != "search") {
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
                    $userid = 
                        $parent->signinModel->getUserId($username);
                    if($userid <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ).
                            "</h1>')";
                    } else if(in_array($userid,array(ROOT_ID, PUBLIC_USER_ID))){
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_cant_delete_builtin'
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

                case "search":
                    $data['STATUS_CODES'][0] = 
                        tl('accountaccess_component_any_status');
                    ksort($data['STATUS_CODES']);
                    $data["FORM_TYPE"] = "search";
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
        $data['NUM_TOTAL'] = $num_users;
        $num_show = (isset($_REQUEST['num_show']) &&
            isset($parent->adminView->pagingtableHelper->show_choices[
                $_REQUEST['num_show']])) ? $_REQUEST['num_show'] : 50;
        $data['num_show'] = $num_show;
        $data['START_ROW'] = 0;
        if(isset($_REQUEST['start_row'])) {
            $data['START_ROW'] = min(
                max(0, $parent->clean($_REQUEST['start_row'],"int")),
                $num_users);
        }
        $data['END_ROW'] = min($data['START_ROW'] + $num_show, $num_users);
        if(isset($_REQUEST['start_row'])) {
            $data['END_ROW'] = max($data['START_ROW'],
                    min($parent->clean($_REQUEST['end_row'],"int"),$num_users));
        }
        $data["USERS"] = $parent->userModel->getUsers($data['START_ROW'],
            $num_show, $search_array);
        $data['NEXT_START'] = $data['END_ROW'];
        $data['NEXT_END'] = min($data['NEXT_START'] + $num_show, $num_users);
        $data['PREV_START'] = max(0, $data['START_ROW'] - $num_show);
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
                "deleteactivity","deleterole", "editrole", "search");
        $data["ELEMENT"] = "managerolesElement";
        $data['SCRIPT'] = "";
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
        $data['NUM_TOTAL'] = $num_roles;
        $num_show = (isset($_REQUEST['num_show']) &&
            isset($parent->adminView->pagingtableHelper->show_choices[
                $_REQUEST['num_show']])) ? $_REQUEST['num_show'] : 50;
        $data['num_show'] = $num_show;
        $data['START_ROW'] = 0;
        if(isset($_REQUEST['start_row'])) {
            $data['START_ROW'] = min(
                max(0, $parent->clean($_REQUEST['start_row'],"int")),
                $num_users);
        }
        $data['END_ROW'] = min($data['START_ROW'] + $num_show, $num_roles);
        if(isset($_REQUEST['start_row'])) {
            $data['END_ROW'] = max($data['START_ROW'],
                    min($parent->clean($_REQUEST['end_row'],"int"),$num_roles));
        }
        $data["ROLES"] = $parent->roleModel->getRoles($data['START_ROW'],
            $num_show, $search_array);
        $data['NEXT_START'] = $data['END_ROW'];
        $data['NEXT_END'] = min($data['NEXT_START'] + $num_show, $num_roles);
        $data['PREV_START'] = max(0, $data['START_ROW'] - $num_show);
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
        $possible_arguments = array("activateuser",
            "addgroup", "banuser", "changeowner",
            "creategroup", "deletegroup", "deleteuser", "editgroup",
            "inviteusers", "joingroup", "memberaccess",
            "registertype", "reinstateuser", "search", "unsubscribe"
            );

        $data["ELEMENT"] = "managegroupsElement";
        $data['SCRIPT'] = "";
        $data['FORM_TYPE'] = "addgroup";
        $data['MEMBERSHIP_CODES'] = array(
            INACTIVE_STATUS => tl('accountaccess_component_request_join'),
            INVITED_STATUS => tl('accountaccess_component_invited'),
            ACTIVE_STATUS => tl('accountaccess_component_active_status'),
            BANNED_STATUS => tl('accountaccess_component_banned_status')
        );
        $data['REGISTER_CODES'] = array(
            NO_JOIN => tl('accountaccess_component_no_join'),
            REQUEST_JOIN => tl('accountaccess_component_by_request'),
            PUBLIC_JOIN => tl('accountaccess_component_public_join')
        );
        $data['ACCESS_CODES'] = array(
            GROUP_PRIVATE => tl('accountaccess_component_private'),
            GROUP_READ => tl('accountaccess_component_read'),
            GROUP_READ_WRITE => tl('accountaccess_component_read_write')
        );
        $data['COMPARISON_TYPES'] = array(
            "=" => tl('accountaccess_component_equal'),
            "!=" => tl('accountaccess_component_not_equal'),
            "CONTAINS" => tl('accountaccess_component_contains'),
            "BEGINS WITH" => tl('accountaccess_component_begins_with'),
            "ENDS WITH" => tl('accountaccess_component_ends_with'),
        );
        $data['DROPDOWN_COMPARISON_TYPES'] = array(
            "=" => tl('accountaccess_component_equal'),
            "!=" => tl('accountaccess_component_not_equal')
        );
        $data['SORT_TYPES'] = array(
            "NONE" => tl('accountaccess_component_no_sort'),
            "ASC" => tl('accountaccess_component_sort_ascending'),
            "DESC" => tl('accountaccess_component_sort_descending'),
        );

        $search_array = array();

        $data['CURRENT_GROUP'] = array("name" => "","id" => "", "owner" =>"",
            "register" => -1, "member_access" => -1);
        $data['PAGING'] = "";
        $name = "";
        /* start owner verify code / get current group
           $group_id is only set in this block (except creategroup) and it 
           is only not NULL if $group['OWNER_ID'] == $_SESSION['USER_ID'] where
           this is also the only place group loaded using $group_id
        */
        if(isset($_REQUEST['group_id']) && $_REQUEST['group_id'] != "") {
            $group_id = $parent->clean($_REQUEST['group_id'], "string" );
            $group = $parent->groupModel->getGroupById($group_id,
                $_SESSION['USER_ID']);
            if($group && ($group['OWNER_ID'] == $_SESSION['USER_ID'] ||
                ($_SESSION['USER_ID'] == ROOT_ID && $_REQUEST['arg'] ==
                "changeowner"))) {
                $name = $group['GROUP_NAME'];
                $data['CURRENT_GROUP']['name'] = $name;
                $data['CURRENT_GROUP']['id'] = $group['GROUP_ID'];
                $data['CURRENT_GROUP']['owner'] = $group['OWNER'];
                $data['CURRENT_GROUP']['register'] = 
                    $group['REGISTER_TYPE'];
                $data['CURRENT_GROUP']['member_access'] = 
                    $group['MEMBER_ACCESS'];
            } else if(!in_array($_REQUEST['arg'], array("deletegroup",
                "joingroup", "unsubscribe"))) {
                $group_id = NULL;
                $group = NULL;
            }
        } else if(isset($_REQUEST['name'])){
            $name = $parent->clean($_REQUEST['name'], "string");
            $data['CURRENT_GROUP']['name'] = $name;
            $group_id = NULL;
            $group = NULL;
        } else {
            $group_id = NULL;
            $group = NULL;
        }
        /* end ownership verify */

        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "activateuser":
                    $data['FORM_TYPE'] = "editgroup";
                    $user_id = (isset($_REQUEST['user_id'])) ? 
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id &&
                        $parent->groupModel->checkUserGroup($user_id,
                        $group_id)) {
                        $parent->groupModel->updateStatusUserGroup($user_id,
                            $group_id, ACTIVE_STATUS);
                        $data['GROUP_USERS'] =
                            $parent->groupModel->getGroupUsers($group_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_activated').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_no_user_activated').
                            "</h1>')";
                    }
                break;
                case "addgroup":
                    if(($add_id = $parent->groupModel->getGroupId($name)) > 0 ||
                        $name =="" ) {
                        $register = 
                            $parent->groupModel->getRegisterType($add_id);
                        if($add_id > 0 && $register && $register != NO_JOIN) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_group_joined').
                                "</h1>')";
                            $join_type = ($register == REQUEST_JOIN &&
                                $_SESSION['USER_ID'] != ROOT_ID) ?
                                INACTIVE_STATUS : ACTIVE_STATUS;
                            $parent->groupModel->addUserGroup(
                                $_SESSION['USER_ID'], $add_id, $join_type);
                        } else {
                            $data['CURRENT_GROUP'] = array("name" => "",
                                "id" => "", "owner" =>"", "register" => -1,
                                "member_access" => -1);
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_unavailable').
                                "</h1>')";
                        }
                    } else {
                        $data['FORM_TYPE'] = "creategroup";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_name_available').
                            "</h1>')";
                    }
                break;
                case "banuser":
                    $data['FORM_TYPE'] = "editgroup";
                    $user_id = (isset($_REQUEST['user_id'])) ? 
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id &&
                        $parent->groupModel->checkUserGroup($user_id, 
                        $group_id)) {
                        $parent->groupModel->updateStatusUserGroup($user_id, 
                            $group_id, BANNED_STATUS);
                        $data['GROUP_USERS'] =
                            $parent->groupModel->getGroupUsers($group_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_banned').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_no_user_banned').
                            "</h1>')";
                    }
                break;
                case "changeowner":
                    $data['FORM_TYPE'] = "changeowner";
                    if(isset($_REQUEST['new_owner'])) {
                        $new_owner_name = $parent->clean($_REQUEST['new_owner'],
                            'string');
                        $new_owner = $parent->userModel->getUser(
                            $new_owner_name);
                        if(isset($new_owner['USER_ID']) ) {
                            if($parent->groupModel->checkUserGroup(
                                $new_owner['USER_ID'], $group_id)) {
                                $parent->groupModel->changeOwnerGroup(
                                    $new_owner['USER_ID'], $group_id);
                                $data['SCRIPT'] .= 
                                    "doMessage('<h1 class=\"red\" >".
                                    tl('accountaccess_component_owner_changed').
                                    "</h1>')";
                                $data['FORM_TYPE'] = "changegroup";
                                $data['CURRENT_GROUP'] = array("name" => "",
                                    "id" => "", "owner" =>"", "register" => -1,
                                    "member_access" => -1);
                            } else {
                                $data['SCRIPT'] .= 
                                    "doMessage('<h1 class=\"red\" >".
                                    tl('accountaccess_component_not_in_group').
                                    "</h1>')";
                            }
                        } else {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_not_a_user').
                                "</h1>')";
                        }
                    }
                break;
                case "creategroup":
                    if($parent->groupModel->getGroupId($name) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_exists').
                            "</h1>')";
                    } else {
                        $group_fields = array(
                            "member_access" => array("ACCESS_CODES",
                                GROUP_READ),
                            "register" => array("REGISTER_CODES",
                                REQUEST_JOIN)
                        );
                        foreach($group_fields as $field => $info) {
                            if(!isset($_REQUEST[$field]) ||
                                !in_array($_REQUEST[$field], 
                                array_keys($data[$info[0]]))) {
                                $_REQUEST[$field] = $info[1];
                            }
                        }
                        $parent->groupModel->addGroup($name,
                            $_SESSION['USER_ID'], $_REQUEST['register'],
                            $_REQUEST['member_access']);
                        //one exception to setting $group_id
                        $group_id = $parent->groupModel->getGroupId($name);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_groupname_added').
                            "</h1>')";
                    }
                    $data['FORM_TYPE'] = "addgroup";
                    $data['CURRENT_GROUP'] = array("name" => "",
                        "id" => "", "owner" =>"", "register" => -1,
                        "member_access" => -1);
                break;
                case "deletegroup":
                    $data['CURRENT_GROUP'] = array("name" => "",
                        "id" => "", "owner" =>"", "register" => -1,
                        "member_access" => -1);
                    if( $group_id <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                          tl('accountaccess_component_groupname_doesnt_exists').
                            "</h1>')";
                    } else if(($group &&
                        $group['OWNER_ID'] == $_SESSION['USER_ID']) ||
                        $_SESSION['USER_ID'] == ROOT_ID) {
                        $parent->groupModel->deleteGroup($group_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_group_deleted').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_no_delete_group').
                            "</h1>')";
                    }
                break;
                case "deleteuser":
                    $data['FORM_TYPE'] = "editgroup";
                    $user_id = (isset($_REQUEST['user_id'])) ? 
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($parent->groupModel->deletableUser(
                        $user_id, $group_id)) {
                        $parent->groupModel->deleteUserGroup(
                            $user_id, $group_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_deleted').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_no_delete_user_group').
                            "</h1>')";
                    }
                    $data['GROUP_USERS'] =
                        $parent->groupModel->getGroupUsers($group_id);
                break;
                case "editgroup":
                    $data['FORM_TYPE'] = "editgroup";
                    $update_fields = array(
                        array('register', 'REGISTER_TYPE','REGISTER_CODES'),
                        array('member_access', 'MEMBER_ACCESS', 'ACCESS_CODES')
                        );
                    $this->updateGroup($data, $group, $update_fields);
                    $data['CURRENT_GROUP']['register'] = 
                        $group['REGISTER_TYPE'];
                    $data['CURRENT_GROUP']['member_access'] = 
                        $group['MEMBER_ACCESS'];
                    $data['GROUP_USERS'] =
                        $parent->groupModel->getGroupUsers($group_id);
                break;
                case "inviteusers":
                    $data['FORM_TYPE'] = "inviteusers";
                    if(isset($_REQUEST['users_names'])) {
                        $users_string = $parent->clean($_REQUEST['users_names'],
                            "string");
                        $pre_user_names = preg_split("/\s+|\,/", $users_string);
                        $users_invited = false;
                        foreach($pre_user_names as $user_name) {
                            $user_name = trim($user_name);
                            $user = $parent->userModel->getUser($user_name);
                            if($user) {
                                if(!$parent->groupModel->checkUserGroup(
                                    $user['USER_ID'], $group_id)) {
                                    $parent->groupModel->addUserGroup(
                                        $user['USER_ID'], $group_id,
                                        INVITED_STATUS);
                                    $users_invited = true;
                                }
                            }
                        }
                        if($users_invited) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_users_invited').
                                "</h1>')";
                        } else {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_no_users_invited').
                                "</h1>')";
                        }
                        $data['FORM_TYPE'] = "editgroup";
                        $data['GROUP_USERS'] =
                            $parent->groupModel->getGroupUsers($group_id);
                    }
                    
                break;
                case "joingroup":
                    $user_id = (isset($_REQUEST['user_id'])) ? 
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id &&
                        $parent->groupModel->checkUserGroup($user_id,
                        $group_id, INVITED_STATUS)) {
                        $parent->groupModel->updateStatusUserGroup($user_id,
                            $group_id, ACTIVE_STATUS);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_joined').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_no_unsubscribe').
                            "</h1>')";
                    }
                break;
                case "memberaccess":
                    $update_fields = array(
                        array('memberaccess', 'MEMBER_ACCESS', 'ACCESS_CODES'));
                    $this->updateGroup($data, $group, $update_fields);
                    $data['CURRENT_GROUP'] = array("name" => "",
                        "id" => "", "owner" =>"", "register" => -1,
                        "member_access" => -1);
                break;
                case "registertype":
                    $update_fields = array(
                        array('registertype', 'REGISTER_TYPE',
                            'REGISTER_CODES'));
                    $this->updateGroup($data, $group, $update_fields);
                    $data['CURRENT_GROUP'] = array("name" => "",
                        "id" => "", "owner" =>"", "register" => -1,
                        "member_access" => -1);
                break;
                case "reinstateuser":
                    $data['FORM_TYPE'] = "editgroup";
                    $user_id = (isset($_REQUEST['user_id'])) ? 
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id &&
                        $parent->groupModel->checkUserGroup($user_id,
                        $group_id)) {
                        $parent->groupModel->updateStatusUserGroup($user_id,
                            $group_id, ACTIVE_STATUS);
                        $data['GROUP_USERS'] =
                            $parent->groupModel->getGroupUsers($group_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_reinstated').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_no_user_reinstated').
                            "</h1>')";
                    }
                break;
                case "search":
                    $data['FORM_TYPE'] = "search";
                    $data['REGISTER_CODES'][0] =
                        tl('accountaccess_component_any');
                    ksort($data['REGISTER_CODES']);
                    $data['ACCESS_CODES'][INACTIVE_STATUS * 10] = 
                        tl('accountaccess_component_request_join');
                    $data['ACCESS_CODES'][INVITED_STATUS * 10] = 
                        tl('accountaccess_component_invited');
                    $data['ACCESS_CODES'][BANNED_STATUS * 10] = 
                        tl('accountaccess_component_banned_status');
                    $data['ACCESS_CODES'][0] =
                        tl('accountaccess_component_any');
                    ksort($data['ACCESS_CODES']);
                    $comparison_fields = array('name', 'owner', 'register',
                        'access');
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
                        if($field_name=='access' && $data[$field_name] >=10) {
                            $search_array[] = array("status",
                                $data[$field_comparison], $data[$field_name]/10,
                                $data[$field_sort]);
                        } else {
                            $search_array[] = array($field,
                                $data[$field_comparison], $data[$field_name],
                                $data[$field_sort]);
                        }
                        $paging .= "&amp;$field_name=".
                            urlencode($data[$field_name]);
                    }
                    $data['PAGING'] = $paging;
                break;
                case "unsubscribe":
                    $user_id = (isset($_REQUEST['user_id'])) ? 
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id &&
                        $parent->groupModel->checkUserGroup($user_id,
                        $group_id)) {
                        $parent->groupModel->deleteUserGroup($user_id,
                            $group_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_unsubscribe').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_no_unsubscribe').
                            "</h1>')";
                    }
                break;
            }
        }
        $current_id = $_SESSION["USER_ID"];
        $num_groups = $parent->groupModel->getGroupsCount($search_array,
            $current_id);
        $data['NUM_TOTAL'] = $num_groups;
        $num_show = (isset($_REQUEST['num_show']) &&
            isset($parent->adminView->pagingtableHelper->show_choices[
                $_REQUEST['num_show']])) ? $_REQUEST['num_show'] : 50;
        $data['num_show'] = $num_show;
        $data['START_ROW'] = 0;
        if(isset($_REQUEST['start_row'])) {
            $data['START_ROW'] = min(
                max(0, $parent->clean($_REQUEST['start_row'],"int")),
                $num_users);
        }
        $data['END_ROW'] = min($data['START_ROW'] + $num_show, $num_groups);
        if(isset($_REQUEST['start_row'])) {
            $data['END_ROW'] = max($data['START_ROW'],
                min($parent->clean($_REQUEST['end_row'],"int"), $num_groups));
        }
        $data["GROUPS"] = $parent->groupModel->getGroups($data['START_ROW'],
            $num_show, $search_array, $current_id);
        $data['NEXT_START'] = $data['END_ROW'];
        $data['NEXT_END'] = min($data['NEXT_START'] + $num_show, $num_groups);
        $data['PREV_START'] = max(0, $data['START_ROW'] - $num_show);
        $data['PREV_END'] = $data['START_ROW'];
        return $data;
    }

    /**
     *
     */
    function updateGroup(&$data, &$group, $update_fields)
    {
        $parent = $this->parent;
        $changed = false;
        if(!isset($group["OWNER_ID"]) ||
            $group["OWNER_ID"] != $_SESSION['USER_ID']) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('accountaccess_component_no_permission')."</h1>');";
            return;
        }
        foreach($update_fields as $row) {
            list($request_field, $group_field, $check_field) = $row;
            if(isset($_REQUEST[$request_field]) &&
                in_array($_REQUEST[$request_field],
                    array_keys($data[$check_field]))) {
                $group[$group_field] =
                    $_REQUEST[$request_field];
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_group_updated')."</h1>');";
                $changed = true;
            } else if(isset($_REQUEST[$request_field]) &&
                $_REQUEST[$request_field] != "") {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_unknown_access')."</h1>');";
            }
        }
        if($changed) {
            $parent->groupModel->updateGroup($group);
        }
    }
}
