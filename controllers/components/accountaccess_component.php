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

/**
 * Base component class for all components on
 * the SeekQuarry site. A component consists of a collection of
 * activities and their auxiliary methods can be used by a controller
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
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
        $possible_arguments = array("adduser",
            "deleteuser", "adduserrole", "deleteuserrole");

        $data["ELEMENT"] = "manageusersElement";
        $data['SCRIPT'] =
            "selectUser = elt('select-user'); ".
            "selectUser.onchange = submitViewUserRole;";

        $usernames = $parent->userModel->getUserList();
        if(isset($_REQUEST['username'])) {
            $username = $parent->clean($_REQUEST['username'], "string" );
        }
        $base_option = tl('accountaccess_component_select_username');
        $data['USER_NAMES'] = array();
        $data['USER_NAMES'][""] = $base_option;

        foreach($usernames as $name) {
            $data['USER_NAMES'][$name]= $name;
        }

        if(isset($_REQUEST['selectuser'])) {
            $select_user = $parent->clean($_REQUEST['selectuser'], "string" );
        } else {
            $select_user = "";
        }
        if($select_user != "" ) {
            $userid = $parent->signinModel->getUserId($select_user);
            $data['SELECT_USER'] = $select_user;
            $data['SELECT_ROLES'] = $parent->userModel->getUserRoles($userid);
            $all_roles = $parent->roleModel->getRoleList();
            $role_ids = array();
            if(isset($_REQUEST['selectrole'])) {
                $select_role = $parent->clean($_REQUEST['selectrole'],"string");
            } else {
                $select_role = "";
            }

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

            $data['AVAILABLE_ROLES'][-1] =
                tl('accountaccess_component_select_rolename');

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
                            tl('accountaccess_component_passwords_dont_match').
                            "</h1>')";
                        return $data;
                    }

                    if($parent->signinModel->getUserId($username) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_exists').
                            "</h1>')";
                        return $data;
                    }
                    $parent->userModel->addUser($username,
                        $parent->clean($_REQUEST['password'], "string"));
                    $data['USER_NAMES'][$username] = $username;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_username_added')."</h1>')";
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
                        return $data;
                    }
                    $parent->userModel->deleteUser($username);
                    unset($data['USER_NAMES'][$username]);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_username_deleted') .
                            "</h1>')";
                break;

                case "adduserrole":
                    if( $userid <= 0 ) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>')";
                        return $data;
                    }
                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                        return $data;
                    }
                    $parent->userModel->addUserRole($userid, $select_role);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_rolename_added'). "</h1>')";
                    unset($data['AVAILABLE_ROLES'][$select_role]);
                    $data['SELECT_ROLE'] = -1;
                    $data['SELECT_ROLES'] =
                        $parent->userModel->getUserRoles($userid);
                break;

                case "deleteuserrole":
                    if($userid <= 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_username_doesnt_exists'
                                ). "</h1>')";
                        return $data;
                    }
                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                                ). "</h1>')";
                        return $data;
                    }
                    $parent->userModel->deleteUserRole($userid, $select_role);
                    $data['SELECT_ROLES'] =
                        $parent->userModel->getUserRoles($userid);
                    $data['AVAILABLE_ROLES'][$select_role] = $select_rolename;
                    $data['SELECT_ROLE'] = -1;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_rolename_deleted') .
                        "</h1>')";
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
     * @return array $data information about roles in the system, activities,
     *      etc. as well as status messages on performing a given sub activity
     *
     */
    function manageRoles()
    {
        $parent = $this->parent;
        $possible_arguments =
            array("addrole", "deleterole", "addactivity", "deleteactivity");

        $data["ELEMENT"] = "managerolesElement";
        $data['SCRIPT'] =
            "selectRole = elt('select-role'); selectRole.onchange =".
            " submitViewRoleActivities;";

        $roles = $parent->roleModel->getRoleList();
        $role_ids = array();
        $base_option = tl('accountaccess_component_select_rolename');
        $data['ROLE_NAMES'] = array();
        $data['ROLE_NAMES'][-1] = $base_option;
        if(isset($_REQUEST['rolename'])) {
            $rolename = $parent->clean($_REQUEST['rolename'], "string" );
        }
        foreach($roles as $role) {
            $data['ROLE_NAMES'][$role['ROLE_ID']]= $role['ROLE_NAME'];
            $role_ids[] = $role['ROLE_ID'];
        }
        $data['SELECT_ROLE'] = -1;


        if(isset($_REQUEST['selectrole'])) {
            $select_role = $parent->clean($_REQUEST['selectrole'], "string" );
        } else {
            $select_role = "";
        }

        if($select_role != "" ) {
            $data['SELECT_ROLE'] = $select_role;
            $data['ROLE_ACTIVITIES'] =
                $parent->roleModel->getRoleActivities($select_role);
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
                    unset($data['ROLE_ACTIVITIES']);
                    unset($data['AVAILABLE_ACTIVITIES']);
                    $data['SELECT_ROLE'] = -1;
                    if($parent->roleModel->getRoleId($rolename) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_exists').
                            "</h1>')";
                        return $data;
                    }

                    $parent->roleModel->addRole($rolename);
                    $roleid = $parent->roleModel->getRoleId($rolename);
                    $data['ROLE_NAMES'][$roleid] = $rolename;

                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_rolename_added').
                        "</h1>')";
                break;

                case "deleterole":
                    $data['SELECT_ROLE'] = -1;
                    unset($data['ROLE_ACTIVITIES']);
                    unset($data['AVAILABLE_ACTIVITIES']);

                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ). "</h1>')";
                        return $data;
                    }
                    $parent->roleModel->deleteRole($select_role);
                    unset($data['ROLE_NAMES'][$select_role]);

                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_rolename_deleted').
                        "</h1>')";
                break;

                case "addactivity":
                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                                ). "</h1>')";
                        return $data;
                    }
                    if(!in_array($select_activity, $activity_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ). "</h1>')";
                        return $data;
                    }
                    $parent->roleModel->addActivityRole(
                        $select_role, $select_activity);
                    unset($data['AVAILABLE_ACTIVITIES'][$select_activity]);
                    $data['ROLE_ACTIVITIES'] =
                        $parent->roleModel->getRoleActivities($select_role);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_activity_added')."</h1>')";
                break;

                case "deleteactivity":
                    if(!in_array($select_role, $role_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_doesnt_exists'
                                ). "</h1>')";
                        return $data;
                    }

                    if(!in_array($select_activity, $activity_ids)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ). "</h1>')";
                        return $data;
                    }
                    $parent->roleModel->deleteActivityRole(
                        $select_role, $select_activity);
                    $data['ROLE_ACTIVITIES'] =
                        $parent->roleModel->getRoleActivities($select_role);
                    $data['AVAILABLE_ACTIVITIES'][$select_activity] =
                        $activity_names[$select_activity];
                    $data['SELECT_ACTIVITY'] = -1;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('accountaccess_component_activity_deleted').
                        "</h1>')";
                break;
            }
        }
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
            $groups = $parent->groupModel->getGroupListbyUser(
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
                $usernames = $parent->userModel->getUserListGroups();
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
                $parent->groupModel->addUserGroup(
                    $select_group, $select_user);
                unset($data['AVAILABLE_USERS'][$select_user]);
                $data['GROUP_USERS'] =
                    $parent->groupModel->getGroupUsers($select_group);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('accountaccess_component_user_added')."</h1>')";
            break;
            case "deleteuser":
                $parent->groupModel->deleteUserGroup(
                    $select_group, $select_user);
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
