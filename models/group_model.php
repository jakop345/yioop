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
 * @author Mallika Perepa, Chris Pollett
 * @package seek_quarry
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads base model class if necessary*/
require_once BASE_DIR."/models/model.php";
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/**
 * This is class is used to handle
 * db results related to Group Administration. Groups are collections of
 * users who might access a common blog and set of pages
 *
 * @author Mallika Perepa (creator), Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */

class GroupModel extends Model
{
    var $search_table_column_map = array("group_id"=>"G.GROUP_ID",
        "name"=>"G.GROUP_NAME", "owner"=>"O.OWNER_NAME",
        "register"=>"G.REGISTER_TYPE", "access"=>"G.MEMBER_ACCESS",
        "status"=>"UG.STATUS");
        
    /**
     *  {@inheritdoc}
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     *
     *  @param string $group_id  the group_id to get users for
     */
    function getGroupUsers($group_id)
    {
        $this->db->selectDB(DB_NAME);
        $group_id = $this->db->escapeString($group_id);
        $users = array();
        $sql = "SELECT UG.USER_ID, U.USER_NAME, UG.GROUP_ID, G.OWNER_ID,".
            " UG.STATUS ".
            " FROM USER_GROUP UG, USER U, GROUPS G".
            " where UG.GROUP_ID = '$group_id' AND UG.USER_ID = U.USER_ID AND" .
            " G.GROUP_ID = UG.GROUP_ID";
        $result = $this->db->execute($sql);
        $i = 0;
        while($users[$i] = $this->db->fetchArray($result)) {
           $i++;
        }
        unset($users[$i]); //last one will be null
        return $users;
    }

    /**
     *  Add a groupname to the database using provided string
     *
     *  @param string $group_name  the groupname to be added
     */
    function addGroup($group_name, $user_id, $register = REQUEST_JOIN,
        $member=GROUP_READ)
    {
        $this->db->selectDB(DB_NAME);
        $timestamp = microTimestamp();

        $sql = 
            "INSERT INTO GROUPS (GROUP_NAME, CREATED_TIME, OWNER_ID,
                REGISTER_TYPE, MEMBER_ACCESS)
            VALUES ('".$this->db->escapeString($group_name)."',
            $timestamp, '".$this->db->escapeString($user_id)."',
            '".$this->db->escapeString($register)."',
            '".$this->db->escapeString($member)."');";
        $this->db->execute($sql);

        $this->db->selectDB(DB_NAME);
        $sql = "SELECT G.GROUP_ID AS GROUP_ID FROM ".
            " GROUPS G WHERE G.GROUP_NAME = '" .
            $this->db->escapeString($group_name) . "' ";
        $result = $this->db->execute($sql);
        if(!$row = $this->db->fetchArray($result) ) {
            $last_id = -1;
        }
        $last_id = $row['GROUP_ID'];
        $sql= "INSERT INTO USER_GROUP (USER_ID, GROUP_ID, STATUS) VALUES
            ($user_id, $last_id, ".ACTIVE_STATUS.")";
        $this->db->execute($sql);
    }

    /**
     *
     */
    function updateGroup($group)
    {
        $group_id = $group['GROUP_ID'];
        unset($group['GROUP_ID']);
        unset($group['GROUP_NAME']);
        unset($group['OWNER']); //column not in table
        unset($group['STATUS']); // column not in table
        $this->db->selectDB(DB_NAME);
        $sql = "UPDATE GROUPS SET ";
        $comma ="";
        foreach($group as $field => $value) {
            $sql .= "$comma $field='$value' ";
            $comma = ",";
        }
        $sql .= " WHERE GROUP_ID=$group_id";
        $this->db->execute($sql);
    }

    /**
     *
     */
    function checkUserGroup($user_id, $group_id, $status = -1)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT COUNT(*) AS NUM FROM USER_GROUP UG WHERE
            UG.USER_ID='". $this->db->escapeString($user_id) .
            "' AND UG.GROUP_ID='". $this->db->escapeString($group_id) . "'";
        if($status >=0) {
            $sql .= " AND STATUS='".$this->db->escapeString($status)."'";
        }
        $result = $this->db->execute($sql);
        if(!$row = $this->db->fetchArray($result) ) {
            return false;
        }
        if($row['NUM'] <= 0) {
            return false;
        }
        return true;
    }

    /**
     *
     */
    function updateStatusUserGroup($user_id, $group_id, $status)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "UPDATE USER_GROUP SET STATUS='" .
            $this->db->escapeString($status)."' ".
            " WHERE GROUP_ID=$group_id AND USER_ID=$user_id";
        $this->db->execute($sql);
    }

    /**
     * Get group id associated with groupname (so groupnames better be unique)
     *
     * @param string $groupname to use to look up a group_id
     * @return string  group_id corresponding to the groupname.
     */
    function getGroupId($groupname)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT G.GROUP_ID AS GROUP_ID FROM ".
            "GROUPS G WHERE G.GROUP_NAME = '".
            $this->db->escapeString($groupname) . "' ";
        $result = $this->db->execute($sql);
        if(!$row = $this->db->fetchArray($result) ) {
            return -1;
        }
        return $row['GROUP_ID'];
    }

    /**
     *  Delete a groupname to the database using provided string
     *
     *  @param string $groupname  the groupname to be deleted
     */
        function deleteGroup($groupid)
        {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM GROUPS WHERE GROUP_ID='$groupid'";
        $this->db->execute($sql);
        $sql = "DELETE FROM GROUPS WHERE GROUP_ID='" .
            $this->db->escapeString($groupid)."'";
        $this->db->execute($sql);
        }
    /**
     *  Get a list of all groups. Group names are not localized since these are
     *  created by end user admins of the search engine
     *
     *  @return array an array of group_id, group_name pairs
     */
    function getGroupList()
    {
        $this->db->selectDB(DB_NAME);
        $groups = array();
        $sql = "SELECT G.GROUP_ID AS GROUP_ID, G.GROUP_NAME AS GROUP_NAME
             FROM GROUPS G";
        $result = $this->db->execute($sql);
        $i = 0;
        while($groups[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }

    /**
     *
     */
    function getGroups($limit=0, $num=100, $search_array = array(),
        $user_id=ROOT_ID)
    {
        $this->db->selectDB(DB_NAME);
        $limit = "LIMIT $limit, $num";
        $any_fields = array("access", "register");
        list($where, $order_by) = 
            $this->searchArrayToWhereOrderClauses($search_array, $any_fields);
        $add_where = " WHERE ";
        if($where != "") {
            $add_where = " AND ";
        }
        $where .= $add_where. " UG.USER_ID='".$this->db->escapeString($user_id).
            "' AND  UG.GROUP_ID=G.GROUP_ID AND OWNER_ID = O.USER_ID";
        $sql = "SELECT G.GROUP_ID AS GROUP_ID,
            G.GROUP_NAME AS GROUP_NAME, G.OWNER_ID AS OWNER_ID,
            O.USER_NAME AS OWNER, REGISTER_TYPE, UG.STATUS AS STATUS,
            G.MEMBER_ACCESS FROM GROUPS G, USER O,
            USER_GROUP UG
            $where $order_by $limit";
        $result = $this->db->execute($sql);
        $i = 0;
        while($groups[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }

    /**
     *
     */
    function getRegisterType($group_id)
    {
        $this->db->selectDB(DB_NAME);
        $groups = array();
        $sql = "SELECT REGISTER_TYPE
             FROM GROUPS G WHERE GROUP_ID='".$this->db->escapeString($group_id).
             "'";
        $result = $this->db->execute($sql);
        if(!$result) { return false; }
        $row = $this->db->fetchArray($result);
        if(!$row) { return false; }
        return $row['REGISTER_TYPE'];
    }

    /**
     *
     */
    function getGroupById($group_id, $user_id)
    {
        $group = $this->getGroups(0, 1, array(
            array("group_id","=", $group_id, "")), $user_id);
        $where = " WHERE ";
        if($user_id != ROOT_ID) {
            $where .= " UG.USER_ID='".$this->db->escapeString($user_id)."' AND ";
        }
        $where .= " UG.GROUP_ID='".$this->db->escapeString($group_id).
            "' AND  UG.GROUP_ID=G.GROUP_ID AND OWNER_ID = O.USER_ID";
        $sql = "SELECT G.GROUP_ID AS GROUP_ID,
            G.GROUP_NAME AS GROUP_NAME, G.OWNER_ID AS OWNER_ID,
            O.USER_NAME AS OWNER, REGISTER_TYPE, UG.STATUS,
            G.MEMBER_ACCESS FROM GROUPS G, USER O,
            USER_GROUP UG $where LIMIT 1";
        $result = $this->db->execute($sql);
        $group = false;
        if($result) {
            $group = $this->db->fetchArray($result);
        }
        if(!$group) {
            return false;
        }
        return $group;
    }

    /**
     * Returns the number of users in the user table
     *
     * @return int number of users
     */
    function getGroupsCount($search_array = array(), $user_id = ROOT_ID)
    {
        $this->db->selectDB(DB_NAME);
        list($where, $order_by) = 
            $this->searchArrayToWhereOrderClauses($search_array);
        $add_where = " WHERE ";
        if($where != "") {
            $add_where = " AND ";
        }
        $where .= $add_where. " UG.USER_ID='".$this->db->escapeString($user_id).
            "' AND  UG.GROUP_ID=G.GROUP_ID";
        $sql = "SELECT COUNT(*) AS NUM FROM GROUPS G, USER_GROUP UG
            $where";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        return $row['NUM'];
    }

    /**
    *  Get a list of all groups which are created by the user_id. Group names
    *  are not localized since these are
    *  created by end user admins of the search engine
    *
    *  @return array an array of group_id, group_name pairs
    */
    function getUserGroups($userid)
    {
        $this->db->selectDB(DB_NAME);
        $groups = array();
        $sql = "SELECT UG.GROUP_ID AS GROUP_ID, UG.USER_ID AS USER_ID," .
            " G.GROUP_NAME AS GROUP_NAME FROM USER_GROUP UG, GROUPS G" .
            " where USER_ID = $userid AND UG.GROUP_ID = G.GROUP_ID";
        $result = $this->db->execute($sql);
        $i = 0;
        while($groups[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }

    /**
     *  Get a list of all groups which are created by the owner_id. Group
     *  names are not  localized since these are
     *  created by end user admins of the search engine
     *
     *  @return array an array of group_id, group_name pairs
     */
     function getGroupListbyCreator($userid)
    {
        $this->db->selectDB(DB_NAME);
        $groups = array();
        $sql = "SELECT G.GROUP_ID AS GROUP_ID, G.OWNER_ID AS USER_ID," .
            "G.GROUP_NAME AS GROUP_NAME " .
            " FROM GROUPS G WHERE OWNER_ID = $userid";
        $result = $this->db->execute($sql);
        $i = 0;
        while($groups[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }

    /**
     *  To update the OWNER_ID of a group
     *
     *  @param string $groupid  the group id  to transfer admin privileges
     *  @param string $userid the id of the user who becomes the admin of group
     */
    function changeOwnerGroup($user_id, $group_id)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "UPDATE GROUPS SET OWNER_ID=". $this->db->escapeString($user_id).
            " WHERE GROUP_ID=".$this->db->escapeString($group_id);
        $this->db->execute($sql);
    }

    /**
     *  Add an allowed user to an existing group
     *
     *  @param string $userid the id of the user to add
     *  @param string $groupid  the group id of the group to add the user to
     */
    function addUserGroup($user_id, $group_id, $status = ACTIVE_STATUS)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "INSERT INTO USER_GROUP VALUES ('".
            $this->db->escapeString($user_id) . "', '".
            $this->db->escapeString($group_id) . "', '".
            $this->db->escapeString($status) . "')";
        $this->db->execute($sql);
    }

    /**
     *
     */
    function deletableUser($user_id, $group_id)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT COUNT(*) AS NUM FROM USER_GROUP UG, GROUPS G WHERE
            UG.USER_ID != G.OWNER_ID AND UG.USER_ID='".
            $this->db->escapeString($user_id) ."' AND UG.GROUP_ID='".
            $this->db->escapeString($group_id) . "'";
        $result = $this->db->execute($sql);
        if(!$row = $this->db->fetchArray($result) ) {
            return false;
        }
        if($row['NUM'] <= 0) {
            return false;
        }
        return true;
    }

    /**
     *  Delete a user from a group by userid an groupid
     *
     *  @param string $userid  the userid of the user to delete
     *  @param string $groupid  the group id of the group to delete
     */
    function deleteUserGroup($user_id, $group_id)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM USER_GROUP WHERE USER_ID='".
            $this->db->escapeString($user_id) . "' AND GROUP_ID='".
            $this->db->escapeString($group_id) . "'";
        $this->db->execute($sql);
    }
}
?>
