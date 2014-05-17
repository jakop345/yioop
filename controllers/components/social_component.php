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
class SocialComponent extends Component implements CrawlConstants
{
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
            INACTIVE_STATUS => tl('social_component_request_join'),
            INVITED_STATUS => tl('social_component_invited'),
            ACTIVE_STATUS => tl('social_component_active_status'),
            BANNED_STATUS => tl('social_component_banned_status')
        );
        $data['REGISTER_CODES'] = array(
            NO_JOIN => tl('social_component_no_join'),
            REQUEST_JOIN => tl('social_component_by_request'),
            PUBLIC_JOIN => tl('social_component_public_join')
        );
        $data['ACCESS_CODES'] = array(
            GROUP_PRIVATE => tl('social_component_private'),
            GROUP_READ => tl('social_component_read'),
            GROUP_READ_COMMENT => tl('social_component_read_comment'),
            GROUP_READ_WRITE => tl('social_component_read_write')
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
                    if(($add_id = $group_model->getGroupId($name)) > 0) {
                        $register =
                            $group_model->getRegisterType($add_id);
                        if($add_id > 0 && $register && $register != NO_JOIN) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('social_component_group_joined').
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
                            tl('social_component_groupname_unavailable').
                                "</h1>')";
                        }
                    } else if ($name != ""){
                        $data['FORM_TYPE'] = "creategroup";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_name_available').
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
                            tl('social_component_user_banned').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_user_banned').
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
                                    tl('social_component_owner_changed').
                                    "</h1>')";
                                $data['FORM_TYPE'] = "changegroup";
                                $data['CURRENT_GROUP'] = array("name" => "",
                                    "id" => "", "owner" =>"", "register" => -1,
                                    "member_access" => -1);
                            } else {
                                $data['SCRIPT'] .=
                                    "doMessage('<h1 class=\"red\" >".
                                    tl('social_component_not_in_group').
                                    "</h1>')";
                            }
                        } else {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('social_component_not_a_user').
                                "</h1>')";
                        }
                    }
                break;
                case "creategroup":
                    if($group_model->getGroupId($name) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_groupname_exists').
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
                            tl('social_component_groupname_added').
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
                          tl('social_component_groupname_doesnt_exists').
                            "</h1>')";
                    } else if(($group &&
                        $group['OWNER_ID'] == $_SESSION['USER_ID']) ||
                        $_SESSION['USER_ID'] == ROOT_ID) {
                        $group_model->deleteGroup($group_id);
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_group_deleted').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_delete_group').
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
                            tl('social_component_user_deleted').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_delete_user_group').
                            "</h1>')";
                    }
                    $this->getGroupUsersData($data, $group_id);
                break;
                case "editgroup":
                    if(!$group_id) { break;}
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
                                tl('social_component_users_invited').
                                "</h1>')";
                        } else {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('social_component_no_users_invited').
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
                            tl('social_component_joined').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_unsubscribe').
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
                            tl('social_component_user_reinstated').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_user_reinstated').
                            "</h1>')";
                    }
                break;
                case "search":
                    $data['ACCESS_CODES'][INACTIVE_STATUS * 10] =
                        tl('social_component_request_join');
                    $data['ACCESS_CODES'][INVITED_STATUS * 10] =
                        tl('social_component_invited');
                    $data['ACCESS_CODES'][BANNED_STATUS * 10] =
                        tl('social_component_banned_status');
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                        array('name', 'owner', 'register', 'access'),
                        array('register', 'access'));
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
                            tl('social_component_unsubscribe').
                            "</h1>')";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_unsubscribe').
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
        $parent->pagingLogic($data, $group_model,
            "GROUPS", DEFAULT_ADMIN_PAGING_NUM, $search_array, "",
            array($current_id, $browse));
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
                tl('social_component_no_permission')."</h1>');";
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
                            tl('social_component_group_updated').
                            "</h1>');";
                    }
                    $changed = true;
                }
            } else if(isset($_REQUEST[$request_field]) &&
                $_REQUEST[$request_field] != "") {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('social_component_unknown_access')."</h1>');";
            }
        }
        if($changed) {
            if(!isset($_REQUEST['change_filter'])) {
                $parent->model("group")->updateGroup($group);
            } else {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('social_component_group_filter_users').
                    "</h1>');";
            }
        }
    }
    /**
     *  Used to support requests related to posting, editing, modifying,
     *  and deleting group feed items.
     *
     *  @return array $data fields to be used by GroupfeedElement
     */
    function groupFeeds()
    {
        $parent = $this->parent;
        $controller_name = 
            (get_class($parent) == "AdminController") ? "admin" : "group";
        $data["CONTROLLER"] = $controller_name;
        $other_controller_name = (get_class($parent) == "AdminController")
            ? "group" : "admin";
        $group_model = $parent->model("group");
        $user_model = $parent->model("user");
        $data["ELEMENT"] = "groupfeed";
        $data['SCRIPT'] = "";
        if(isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
        } else {
            $user_id = PUBLIC_GROUP_ID;
        }
        
        $username = $user_model->getUsername($user_id);
        if(isset($_REQUEST['num'])) {
            $results_per_page = $parent->clean($_REQUEST['num'], "int");
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
            "just_group_id" => "int", "just_thread" => "int",
            "just_user_id" => "int");
        foreach($clean_array as $field => $type) {
            $$field = ($type == "string") ? "" : 0;
            if(isset($_REQUEST[$field])) {
                $$field = $parent->clean($_REQUEST[$field], $type);
            }
        }
        $possible_arguments = array("addcomment", "deletepost", "newthread",
            "updatepost", "status");
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
                            tl('social_component_comment_error').
                            "</h1>');";
                        break;
                    }
                    if(!$description) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_comment').
                            "</h1>');";
                        break;
                    }
                    $parent_id = $parent->clean($_REQUEST['parent_id'], "int");
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group =
                        $group_model->getGroupById($group_id,
                        $user_id);
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        $group["MEMBER_ACCESS"] != GROUP_READ_COMMENT &&
                        $group["MEMBER_ACCESS"] != GROUP_READ_WRITE &&
                        $user_id != ROOT_ID)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_post_access').
                            "</h1>');";
                        break;
                    }
                    if($parent_id >= 0) {
                        $parent_item = $group_model->getGroupItem($parent_id);
                        if(!$parent_item) {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('social_component_no_post_access').
                                "</h1>');";
                            break;
                        }
                    } else {
                        $parent_item = array(
                            'TITLE' => tl('social_component_join_group',
                                $username, $group['GROUP_NAME']),
                            'DESCRIPTION' =>
                                tl('social_component_join_group_detail',
                                    date("r", $group['JOIN_DATE']),
                                    $group['GROUP_NAME']),
                            'ID' => -$group_id,
                            'PARENT_ID' => -$group_id,
                            'GROUP_ID' => $group_id
                        );
                    }
                    $title = "-- ".$parent_item['TITLE'];
                    $group_model->addGroupItem($parent_item["ID"],
                        $group_id, $user_id, $title, $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_comment_added'). "</h1>');";
                break;
                case "deletepost":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['post_id'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_delete_error').
                            "</h1>');";
                        break;
                    }
                    $post_id = $parent->clean($_REQUEST['post_id'], "int");
                    $success=$group_model->deleteGroupItem($post_id, $user_id);
                    if($success) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_item_deleted').
                            "</h1>');";
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_item_deleted').
                            "</h1>');";
                    }
                break;
                case "newthread":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['group_id'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_comment_error').
                            "</h1>');";
                        break;
                    }
                    $group_id =$parent->clean($_REQUEST['group_id'], "int");
                    if(!$description || !$title) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_need_title_description').
                            "</h1>');";
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
                            tl('social_component_no_post_access').
                            "</h1>');";
                        break;
                    }
                    $group_model->addGroupItem(0,
                        $group_id, $user_id, $title, $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_thread_created'). "</h1>');";
                break;
                case "status":
                    $data['REFRESH'] = "feedstatus";
                break;
                case "updatepost":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['post_id'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_comment_error').
                            "</h1>');";
                        break;
                    }
                    if(!$description || !$title) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_need_title_description').
                            "</h1>');";
                        break;
                    }
                    $post_id =$parent->clean($_REQUEST['post_id'], "int");
                    $items = $group_model->getGroupItems(0, 1, array(
                        array("post_id", "=", $post_id, "")), $user_id);
                    if(isset($items[0])) {
                        $item = $items[0];
                    } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_update_access').
                            "</h1>');";
                        break;
                    }
                    $group_id = $item['GROUP_ID'];
                    $group =  $group_model->getGroupById($group_id, $user_id);
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        $group["MEMBER_ACCESS"] != GROUP_READ_WRITE &&
                        $user_id != ROOT_ID)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_update_access').
                            "</h1>')";
                        break;
                    }
                    $group_model->updateGroupItem($post_id, $title,
                        $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_post_updated'). "</h1>');";
                break;
            }
        }
        $groups_count = 0;
        $page = array();
        if(!$just_user_id && (!$just_thread || $just_thread < 0)) {
            $search_array = array(
                array("group_id", "=", max(-$just_thread, $just_group_id), ""),
                array("access", "!=", GROUP_PRIVATE, ""),
                array("status", "=", ACTIVE_STATUS, ""),
                array("join_date", "=","", "DESC"));
            $groups = $group_model->getRows(
                0, $limit + $results_per_page, $groups_count,
                $search_array, array($user_id, false));
            $pages = array();
            foreach($groups as $group) {
                $page = array();
                $page[self::TITLE] = tl('social_component_join_group',
                    $username, $group['GROUP_NAME']);
                $page[self::DESCRIPTION] =
                    tl('social_component_join_group_detail',
                        date("r", $group['JOIN_DATE']), $group['GROUP_NAME']);
                $page['ID'] = -$group['GROUP_ID'];
                $page['PARENT_ID'] = -$group['GROUP_ID'];
                $page['USER_NAME'] = "";
                $page['USER_ID'] = "";
                $page['GROUP_ID'] = $group['GROUP_ID'];
                $page[self::SOURCE_NAME] = $group['GROUP_NAME'];
                $page['MEMBER_ACCESS'] = $group['MEMBER_ACCESS'];
                if($group['OWNER_ID'] == $user_id || $user_id == ROOT_ID) {
                    $page['MEMBER_ACCESS'] = GROUP_READ_WRITE;
                }
                $page['PUBDATE'] = $group['JOIN_DATE'];
                $pages[$group['JOIN_DATE']] = $page;
            }
        }
        if($just_thread) {
            $thread_parent = $group_model->getGroupItem($just_thread);
            if(isset($thread_parent["TYPE"]) &&
                $thread_parent["TYPE"] == WIKI_GROUP_ITEM) {
                $page_info = $group_model->getPageInfoByThread($just_thread);
                if(isset($page_info["PAGE_NAME"])) {
                    $data["WIKI_PAGE_NAME"] = $page_info["PAGE_NAME"];
                    $data["WIKI_QUERY"] = "?c={$data['CONTROLLER']}&amp;".
                        "a=wiki&amp;arg=edit&amp;page_name=".
                        $page_info['PAGE_NAME']."&amp;locale_tag=".
                        $page_info["LOCALE_TAG"]."&amp;group_id=".
                        $page_info["GROUP_ID"];
                }
            }
        }
        $search_array = array(
            array("parent_id", "=", $just_thread, ""),
            array("group_id", "=", $just_group_id, ""),
            array("user_id", "=", $just_user_id, ""),
            array('pub_date', "=", "", "DESC"));
        $for_group = ($just_group_id) ? $just_group_id : -1;
        $item_count = $group_model->getGroupItemCount($search_array, $user_id,
            $for_group);
        $group_items = $group_model->getGroupItems(0,
            $limit + $results_per_page, $search_array, $user_id, $for_group);
        $recent_found = false;
        $time = time();
        $j = 0;
        foreach($group_items as $item) {
            $page = $item;
            $page[self::TITLE] = $page['TITLE'];
            unset($page['TITLE']);
            $description = $page['DESCRIPTION'];
            //start code for sharing crawl mixes
            preg_match_all("/\[\[([^\:\n]+)\:mix(\d+)\]\]/", $description,
                $matches);
            $num_matches = count($matches[0]);
            for($i = 0; $i < $num_matches; $i++) {
                $match = preg_quote($matches[0][$i]);
                $match = str_replace("@","\@", $match);
                $replace = "<a href='?c=admin&amp;a=mixCrawls".
                    "&amp;arg=importmix&amp;".CSRF_TOKEN."=".
                    $parent->generateCSRFToken($user_id).
                    "&amp;timestamp={$matches[2][$i]}'>".
                    $matches[1][$i]."</a>";
                $description = preg_replace("@".$match."@u", $replace,
                    $description);
                $page["NO_EDIT"] = true;
            }
            //end code for sharing crawl mixes
            $page[self::DESCRIPTION] = $description;
            unset($page['DESCRIPTION']);
            $page[self::SOURCE_NAME] = $page['GROUP_NAME'];
            unset($page['GROUP_NAME']);
            if($item['OWNER_ID'] == $user_id || $user_id == ROOT_ID) {
                $page['MEMBER_ACCESS'] = GROUP_READ_WRITE;
            }
            if(!$recent_found && $time - $item["PUBDATE"] <
                5 * self::ONE_MINUTE) {
                $recent_found = true;
                $data['SCRIPT'] .= 'doUpdate();';
            }
            $pages[$item["PUBDATE"] . "$j"] = $page;
            $j++;
        }
        krsort($pages);
        $data['SUBTITLE'] = "";
        if($just_thread != "" && isset($page[self::TITLE])) {
            $title = $page[self::TITLE];
            $data['SUBTITLE'] = trim($title, "\- \t\n\r\0\x0B");
            $data['ADD_PAGING_QUERY'] = "&amp;just_thread=$just_thread";
            $data['JUST_THREAD'] = $just_thread;
        }
        if($just_group_id && isset($page[self::SOURCE_NAME])) {
            $data['SUBTITLE'] = $page[self::SOURCE_NAME];
            $data['ADD_PAGING_QUERY'] = "&amp;just_group_id=$just_group_id";
            $data['JUST_GROUP_ID'] = $just_group_id;
        }
        if($just_user_id && isset($page["USER_NAME"])) {
            $data['SUBTITLE'] = $page["USER_NAME"];
            $data['ADD_PAGING_QUERY'] = "&amp;just_user_id=$just_user_id";
            $data['JUST_USER_ID'] = $just_user_id;
        }
        $pages = array_slice($pages, $limit , $results_per_page - 1);
        $data['TOTAL_ROWS'] = $item_count + $groups_count;
        $data['LIMIT'] = $limit;
        $data['RESULTS_PER_PAGE'] = $results_per_page;
        $data['PAGES'] = $pages;
        $data['PAGING_QUERY'] = "?c=$controller_name&amp;a=groupFeeds";
        $data['OTHER_PAGING_QUERY'] =
            "?c=$other_controller_name&amp;a=groupFeeds";
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
        $user_model = $parent->model("user");
        $possible_arguments = array(
            "createmix", "deletemix", "editmix", "index", "importmix",
            "search", "sharemix");
        $data["ELEMENT"] = "mixcrawls";
        $user_id = $_SESSION['USER_ID'];

        $data['mix_default'] = 0;
        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if($num_machines <  1 || ($num_machines ==  1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = NULL;
        }
        $crawls = $crawl_model->getCrawlList(false, true, $machine_urls);
        $data['available_crawls'][0] = tl('social_component_select_crawl');
        $data['available_crawls'][1] = tl('social_component_default_crawl');
        $data['SCRIPT'] = "c = [];c[0]='".
            tl('social_component_select_crawl')."';";
        $data['SCRIPT'] .= "c[1]='".
            tl('social_component_default_crawl')."';";
        foreach($crawls as $crawl) {
            $data['available_crawls'][$crawl['CRAWL_TIME']] =
                $crawl['DESCRIPTION'];
            $data['SCRIPT'] .= 'c['.$crawl['CRAWL_TIME'].']="'.
                $crawl['DESCRIPTION'].'";';
        }
        $search_array = array();
        $can_manage_crawls = $user_model->isAllowedUserActivity(
                $_SESSION['USER_ID'], "manageCrawls");
        $data['PAGING'] = "";
        $data['FORM_TYPE'] = "";
        if(isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch($_REQUEST['arg'])
            {
                case "createmix":
                    $mix['TIMESTAMP'] = time();
                    if(isset($_REQUEST['NAME'])) {
                        $mix['NAME'] = $parent->clean($_REQUEST['NAME'],
                            'string');
                    } else {
                        $mix['NAME'] = tl('social_component_unnamed');
                    }
                    $mix['FRAGMENTS'] = array();
                    $mix['OWNER_ID'] = $user_id;
                    $mix['PARENT'] = -1;
                    $crawl_model->setCrawlMix($mix);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_mix_created')."</h1>');";
                break;
                case "deletemix":
                    if(!isset($_REQUEST['timestamp'])||
                        !$crawl_model->isMixOwner($_REQUEST['timestamp'],
                            $user_id)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_mix_invalid_timestamp').
                            "</h1>');";
                        return $data;
                    }
                    $crawl_model->deleteCrawlMix($_REQUEST['timestamp']);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_mix_deleted')."</h1>');";
                break;
                case "editmix":
                    //$data passed by reference
                    $this->editMix($data);
                break;
                case "importmix":
                    $import_success = true;
                    if(!isset($_REQUEST['timestamp'])) {
                        $import_success = false;
                    }
                    $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
                    $mix = $crawl_model->getCrawlMix($timestamp);
                    if(!$mix) {
                        $import_success = false;
                    }
                    if(!$import_success) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_mix_doesnt_exists').
                            "</h1>');";
                        return $data;
                    }
                    $mix['PARENT'] = $mix['TIMESTAMP'];
                    $mix['OWNER_ID'] = $user_id;
                    $mix['TIMESTAMP'] = time();
                    $crawl_model->setCrawlMix($mix);

                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_mix_imported')."</h1>');";
                break;
                case "index":
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_set_index')."</h1>');";
                    $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
                    if($can_manage_crawls) {
                        $crawl_model->setCurrentIndexDatabaseName(
                            $timestamp);
                    } else {
                        $_SESSION['its'] = $timestamp;
                        $user_model->setUserSession($user_id, $_SESSION);
                    }
                break;
                case "search":
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                        array('name'));
                break;
                case "sharemix":
                    if(!$parent->checkCSRFTime(CSRF_TOKEN)) {
                        break;
                    }
                    if(!isset($_REQUEST['group_name'])) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_comment_error').
                            "</h1>');";
                        break;
                    }
                    if(!isset($_REQUEST['timestamp']) ||
                        !$crawl_model->isMixOwner($_REQUEST['timestamp'],
                            $user_id)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_invalid_timestamp').
                            "</h1>');";
                        break;
                    }
                    $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
                    $group_model = $parent->model("group");
                    $group_name =
                        $parent->clean($_REQUEST['group_name'], "string");
                    $group_id = $group_model->getGroupId($group_name);
                    $group = NULL;
                    if($group_id) {
                        $group =
                            $group_model->getGroupById($group_id,
                            $user_id);
                    }
                    if(!$group || ($group["OWNER_ID"] != $user_id &&
                        $group["MEMBER_ACCESS"] != GROUP_READ_WRITE &&
                        $user_id != ROOT_ID)) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('social_component_no_post_access').
                            "</h1>');";
                        break;
                    }
                    $user_name = $user_model->getUserName($user_id);
                    $title = tl('social_component_share_title',
                        $user_name);
                    $description = tl('social_component_share_description',
                        $user_name,"[[{$mix['NAME']}:mix{$mix['TIMESTAMP']}]]");
                    $group_model->addGroupItem(0,
                        $group_id, $user_id, $title, $description);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_thread_created'). "</h1>');";
                break;
            }
        }
        $parent->pagingLogic($data, $crawl_model, "available_mixes",
            DEFAULT_ADMIN_PAGING_NUM, $search_array, "", true);

        if(!$can_manage_crawls && isset($_SESSION['its'])) {
            $crawl_time = $_SESSION['its'];
        } else {
            $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
        }
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
     * @param array $data info about the fragments and their contents for a
     *      particular crawl mix (changed by this method)
     */
    function editMix(&$data)
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $data["leftorright"] =
            (getLocaleDirection() == 'ltr') ? "right": "left";
        $data["ELEMENT"] = "editmix";
        $user_id = $_SESSION['USER_ID'];

        $mix = array();
        $timestamp = 0;
        if(isset($_REQUEST['timestamp'])) {
            $timestamp = $parent->clean($_REQUEST['timestamp'], "int");
        } else if (isset($_REQUEST['mix']['TIMESTAMP'])) {
            $timestamp = $parent->clean($_REQUEST['mix']['TIMESTAMP'], "int");
        }
        if(!$crawl_model->isMixOwner($timestamp, $user_id)) {
            $data["ELEMENT"] = "mixcrawls";
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('social_component_mix_not_owner').
                "</h1>');";
            return;
        }
        $mix = $crawl_model->getCrawlMix($timestamp);
        $owner_id = $mix['OWNER_ID'];
        $parent_id = $mix['PARENT'];
        $data['MIX'] = $mix;
        $data['INCLUDE_SCRIPTS'] = array("mix");

        //set up an array of translation for javascript-land
        $data['SCRIPT'] .= "tl = {".
            'social_component_add_crawls:"'.
                tl('social_component_add_crawls') .
            '",' . 'social_component_num_results:"'.
                tl('social_component_num_results').'",'.
            'social_component_del_frag:"'.
                tl('social_component_del_frag').'",'.
            'social_component_weight:"'.
                tl('social_component_weight').'",'.
            'social_component_name:"'.tl('social_component_name').'",'.
            'social_component_add_keywords:"'.
                tl('social_component_add_keywords').'",'.
            'social_component_actions:"'.
                tl('social_component_actions').'",'.
            'social_component_add_query:"'.
                tl('social_component_add_query').'",'.
            'social_component_delete:"'.tl('social_component_delete').'"'.
            '};';
        //clean and save the crawl mix sent from the browser
        if(isset($_REQUEST['update']) && $_REQUEST['update'] ==
            "update") {
            $mix = $_REQUEST['mix'];
            $mix['TIMESTAMP'] = $timestamp;
            $mix['OWNER_ID']= $owner_id;
            $mix['PARENT'] = $parent_id;
            $mix['NAME'] =$parent->clean($mix['NAME'],
                "string");
            $comp = array();
            $save_mix = false;
            if(isset($mix['FRAGMENTS'])) {
                if($mix['FRAGMENTS'] != NULL && count($mix['FRAGMENTS']) <
                    MAX_MIX_FRAGMENTS) {
                    foreach($mix['FRAGMENTS'] as $fragment_id=>$fragment_data) {
                        if(isset($fragment_data['RESULT_BOUND'])) {
                            $mix['FRAGMENTS'][$fragment_id]['RESULT_BOUND'] =
                                $parent->clean($fragment_data['RESULT_BOUND'],
                                    "int");
                        } else {
                            $mix['FRAGMENTS']['RESULT_BOUND'] = 0;
                        }
                        if(isset($fragment_data['COMPONENTS'])) {
                            $comp = array();
                            foreach($fragment_data['COMPONENTS'] as $component){
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
                            $mix['FRAGMENTS'][$fragment_id]['COMPONENTS']=$comp;
                        } else {
                            $mix['FRAGMENTS'][$fragment_id]['COMPONENTS'] =
                                array();
                        }
                    }
                    $save_mix = true;
                } else if(count($mix['FRAGMENTS']) >= MAX_MIX_FRAGMENTS) {
                    $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_too_many_fragments')."</h1>');";
                } else {
                    $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
                }
            } else {
                $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
            }
            if($save_mix) {
                $data['MIX'] = $mix;
                $crawl_model->setCrawlMix($mix);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('social_component_mix_saved')."</h1>');";
            }
        }

        $data['SCRIPT'] .= 'fragments = [';
        $not_first = "";
        foreach($mix['FRAGMENTS'] as $fragment_id => $fragment_data) {
            $data['SCRIPT'] .= $not_first.'{';
            $not_first= ",";
            if(isset($fragment_data['RESULT_BOUND'])) {
                $data['SCRIPT'] .= "num_results:".
                    $fragment_data['RESULT_BOUND'];
            } else {
                $data['SCRIPT'] .= "num_results:1 ";
            }
            $data['SCRIPT'] .= ", components:[";
            if(isset($fragment_data['COMPONENTS'])) {
                $comma = "";
                foreach($fragment_data['COMPONENTS'] as $component) {
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
        $data['SCRIPT'] .= ']; drawFragments();';
    }
}
