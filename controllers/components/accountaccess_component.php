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
        if(isset($_REQUEST['edit']) && $_REQUEST['edit'] == "true") {
            $data['EDIT_USER'] = true;
        }
        if(isset($_REQUEST['edit_pass'])) {
            if($_REQUEST['edit_pass'] == "true") {
                $data['EDIT_USER'] = true;
                $data['EDIT_PASSWORD'] = true;
            } else {
                $data['EDIT_USER'] = true;
            }
        }
        $data['USERNAME'] = $username;
        $data['NUM_GROUPS'] = $group_model->getGroupsCount(array(), $user_id);
        $data['NUM_SHOWN'] = 5;
        $data['GROUPS'] = $group_model->getGroups(0, $data['NUM_SHOWN'],
            array(), $user_id);
        $num_shown = count($data['GROUPS']);
        for($i = 0; $i < $num_shown; $i++) {
            $search_array = array(array("group_id", "=",
                $data['GROUPS'][$i]['GROUP_ID'], ""));
            $item = $group_model->getGroupItems(0, 1,
                $search_array, $user_id);
            $data['GROUPS'][$i]['NUM_POSTS'] =
                $group_model->getGroupItemCount($search_array, $user_id);
            $data['GROUPS'][$i]['NUM_THREADS'] =
                $group_model->getGroupThreadCount(
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
                        (!isset($_REQUEST['re_type_password']) ||
                        !isset($_REQUEST['new_password']) ||
                        $_REQUEST['re_type_password'] !=
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
                        $signin_model->changePassword($username,
                            $parent->clean($_REQUEST['new_password'],
                            "string"));
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
     *      as well as status messages on performing a given sub activity
     */
    function manageUsers()
    {
        $parent = $this->parent;
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
                        $user_model->addUser($username,
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
        $num_users = $user_model->getUserCount($search_array);
        $data['NUM_TOTAL'] = $num_users;
        $num_show = (isset($_REQUEST['num_show']) &&
            isset($parent->view("admin")->helper("pagingtable")->show_choices[
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
        $data["USERS"] = $user_model->getUsers($data['START_ROW'],
            $num_show, $search_array);
        $data['NEXT_START'] = $data['END_ROW'];
        $data['NEXT_END'] = min($data['NEXT_START'] + $num_show, $num_users);
        $data['PREV_START'] = max(0, $data['START_ROW'] - $num_show);
        $data['PREV_END'] = $data['START_ROW'];
        return $data;
    }
    /**
     *
     *  @param array &$data
     *  @param int $user_id
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
     *
     *  @param array &$data
     *  @param int $user_id
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
     *      etc. as well as status messages on performing a given sub activity
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
                case "addrole":
                    if($role_model->getRoleId($name) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_exists').
                            "</h1>')";
                    } else {
                        $role_model->addRole($name);
                        $data['CURRENT_ROLE']['name'] = "";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_added').
                            "</h1>')";
                   }
                   $data['CURRENT_ROLE']['name'] = "";
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
            }
        }
        $num_roles = $role_model->getRoleCount($search_array);
        $data['NUM_TOTAL'] = $num_roles;
        $num_show = (isset($_REQUEST['num_show']) &&
            isset($parent->view("admin")->helper("pagingtable")->show_choices[
                $_REQUEST['num_show']])) ? $_REQUEST['num_show'] : 50;
        $data['num_show'] = $num_show;
        $data['START_ROW'] = 0;
        if(isset($_REQUEST['start_row'])) {
            $data['START_ROW'] = min(
                max(0, $parent->clean($_REQUEST['start_row'],"int")),
                $num_roles);
        }
        $data['END_ROW'] = min($data['START_ROW'] + $num_show, $num_roles);
        if(isset($_REQUEST['start_row'])) {
            $data['END_ROW'] = max($data['START_ROW'],
                    min($parent->clean($_REQUEST['end_row'],"int"),$num_roles));
        }
        $data["ROLES"] = $role_model->getRoles($data['START_ROW'],
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
        $group_model = $parent->model("group");
        $possible_arguments = array("activateuser",
            "addgroup", "banuser", "changeowner",
            "creategroup", "deletegroup", "deleteuser", "editgroup",
            "inviteusers", "joingroup", "memberaccess",
            "registertype", "reinstateuser", "search", "unsubscribe"
            );

        $data["ELEMENT"] = "managegroups";
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
        $data['visible_users'] = "";
        /* start owner verify code / get current group
           $group_id is only set in this block (except creategroup) and it
           is only not NULL if $group['OWNER_ID'] == $_SESSION['USER_ID'] where
           this is also the only place group loaded using $group_id
        */
        if(isset($_REQUEST['group_id']) && $_REQUEST['group_id'] != "") {
            $group_id = $parent->clean($_REQUEST['group_id'], "string" );
            $group = $group_model->getGroupById($group_id,
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
        $data['USER_FILTER'] = "";

        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "activateuser":
                    $data['FORM_TYPE'] = "editgroup";
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id &&
                        $group_model->checkUserGroup($user_id,
                        $group_id)) {
                        $group_model->updateStatusUserGroup($user_id,
                            $group_id, ACTIVE_STATUS);
                        $this->getGroupUsersData($data);
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
                    if(($add_id = $group_model->getGroupId($name)) > 0 ||
                        $name == "" ) {
                        $register =
                            $group_model->getRegisterType($add_id);
                        if($add_id > 0 && $register && $register != NO_JOIN) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_group_joined').
                                "</h1>')";
                            $join_type = ($register == REQUEST_JOIN &&
                                $_SESSION['USER_ID'] != ROOT_ID) ?
                                INACTIVE_STATUS : ACTIVE_STATUS;
                            $group_model->addUserGroup(
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
                        $group_model->checkUserGroup($user_id,
                        $group_id)) {
                        $group_model->updateStatusUserGroup($user_id,
                            $group_id, BANNED_STATUS);
                        $this->getGroupUsersData($data, $group_id);
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
                        $new_owner = $parent->model("user")->getUser(
                            $new_owner_name);
                        if(isset($new_owner['USER_ID']) ) {
                            if($group_model->checkUserGroup(
                                $new_owner['USER_ID'], $group_id)) {
                                $group_model->changeOwnerGroup(
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
                    if($group_model->getGroupId($name) > 0) {
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
                        $group_model->addGroup($name,
                            $_SESSION['USER_ID'], $_REQUEST['register'],
                            $_REQUEST['member_access']);
                        //one exception to setting $group_id
                        $group_id = $group_model->getGroupId($name);
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
                        $group_model->deleteGroup($group_id);
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
                    if($group_model->deletableUser(
                        $user_id, $group_id)) {
                        $group_model->deleteUserGroup(
                            $user_id, $group_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_user_deleted').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_no_delete_user_group').
                            "</h1>')";
                    }
                    $this->getGroupUsersData($data, $group_id);
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
                    $this->getGroupUsersData($data, $group_id);
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
                            $user = $parent->model("user")->getUser($user_name);
                            if($user) {
                                if(!$group_model->checkUserGroup(
                                    $user['USER_ID'], $group_id)) {
                                    $group_model->addUserGroup(
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
                        $this->getGroupUsersData($data, $group_id);
                    }
                break;
                case "joingroup":
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if($user_id && $group_id &&
                        $group_model->checkUserGroup($user_id,
                        $group_id, INVITED_STATUS)) {
                        $group_model->updateStatusUserGroup($user_id,
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
                        $group_model->checkUserGroup($user_id,
                        $group_id)) {
                        $group_model->updateStatusUserGroup($user_id,
                            $group_id, ACTIVE_STATUS);
                        $this->getGroupUsersData($data, $group_id);
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
                        $group_model->checkUserGroup($user_id,
                        $group_id)) {
                        $group_model->deleteUserGroup($user_id,
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
        $browse = false;
        if(isset($_REQUEST['browse']) && $_REQUEST['browse'] == 'true' &&
            $_REQUEST['arg'] == 'search') {
            $browse = true;
            $data['browse'] = 'true';
        }
        $num_groups = $group_model->getGroupsCount($search_array,
            $current_id, $browse);
        $data['NUM_TOTAL'] = $num_groups;
        $num_show = (isset($_REQUEST['num_show']) &&
            isset($parent->view("admin")->helper("pagingtable")->show_choices[
                $_REQUEST['num_show']])) ? $_REQUEST['num_show'] : 50;
        $data['num_show'] = $num_show;
        $data['START_ROW'] = 0;
        if(isset($_REQUEST['start_row'])) {
            $data['START_ROW'] = min(
                max(0, $parent->clean($_REQUEST['start_row'],"int")),
                $num_groups);
        }
        $data['END_ROW'] = min($data['START_ROW'] + $num_show, $num_groups);
        if(isset($_REQUEST['start_row'])) {
            $data['END_ROW'] = max($data['START_ROW'],
                min($parent->clean($_REQUEST['end_row'],"int"), $num_groups));
        }
        $data["GROUPS"] = $group_model->getGroups($data['START_ROW'],
            $num_show, $search_array, $current_id, $browse);
        $data['NEXT_START'] = $data['END_ROW'];
        $data['NEXT_END'] = min($data['NEXT_START'] + $num_show, $num_groups);
        $data['PREV_START'] = max(0, $data['START_ROW'] - $num_show);
        $data['PREV_END'] = $data['START_ROW'];
        return $data;
    }

    /**
     *
     *  @param array &$data
     *  @param int $group_id
     */
    function getGroupUsersData(&$data, $group_id)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $data['visible_users'] = (isset($_REQUEST['visible_users']) &&
            $_REQUEST['visible_users']=='true') ? 'true' : 'false';
        if($data['visible_users'] == 'false') {
            unset($_REQUEST['user_filter']);
            unset($_REQUEST['user_limit']);
        }
        if(isset($_REQUEST['user_filter'])) {
            $user_filter = $parent->clean(
                $_REQUEST['user_filter'], 'string');
        } else {
            $user_filter = "";
        }
        $data['USER_FILTER'] = $user_filter;
        $data['NUM_USERS_GROUP'] =
            $group_model->countGroupUsers($group_id, $user_filter);
        if(isset($_REQUEST['group_limit'])) {
            $group_limit = min($parent->clean(
                $_REQUEST['group_limit'], 'int'),
                $data['NUM_USERS_GROUP']);
            $group_limit = max($group_limit, 0);
        } else {
            $group_limit = 0;
        }
        $data['GROUP_LIMIT'] = $group_limit;
        $data['GROUP_USERS'] =
            $group_model->getGroupUsers($group_id, $user_filter,
            $group_limit);
    }

    /**
     *  Used by $this->manageGroups to check and clean $_REQUEST variables
     *  related to groups, to check that a user has the correct permissions
     *  if the current group is to be modfied, and if so, to call model to
     *  handle the update
     *
     *  @param array &$data used to add any information messages for the view
     *      about changes or non-changes to the model
     *  @param array &$group current group which might be altered
     *  @param array $update_field which fields in the current group might be
     *      changed. Elements of this array are triples, the name of the
     *      group field, name of the request field to use for data, and an
     *      array of allowed values for the field
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
                if($group[$group_field] != $_REQUEST[$request_field]) {
                    $group[$group_field] =
                        $_REQUEST[$request_field];
                    if(!isset($_REQUEST['change_filter'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_group_updated').
                            "</h1>');";
                    }
                    $changed = true;
                }
            } else if(isset($_REQUEST[$request_field]) &&
                $_REQUEST[$request_field] != "") {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_unknown_access')."</h1>');";
            }
        }
        if($changed) {
            if(!isset($_REQUEST['change_filter'])) {
                $parent->model("group")->updateGroup($group);
            } else {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_group_filter_users').
                    "</h1>');";
            }
        }
    }
}
