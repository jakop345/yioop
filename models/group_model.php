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
    var $search_table_column_map = array("access"=>"G.MEMBER_ACCESS",
        "group_id"=>"G.GROUP_ID", "post_id" => "GI.ID",
        "join_date"=>"UG.JOIN_DATE",
        "name"=>"G.GROUP_NAME", "owner"=>"O.OWNER_NAME",
        "pub_date" => "GI.PUBDATE", "parent_id"=>"GI.PARENT_ID",
        "register"=>"G.REGISTER_TYPE", "status"=>"UG.STATUS",
        "user_id"=>"P.USER_ID");

    /**
     *
     *  @param string $group_id  the group_id to get users for
     */
    function getGroupUsers($group_id)
    {
        $db = $this->db;
        $users = array();
        $sql = "SELECT UG.USER_ID, U.USER_NAME, UG.GROUP_ID, G.OWNER_ID,".
            " UG.STATUS ".
            " FROM USER_GROUP UG, USERS U, GROUPS G".
            " where UG.GROUP_ID = ? AND UG.USER_ID = U.USER_ID AND" .
            " G.GROUP_ID = UG.GROUP_ID";
        $result = $db->execute($sql, array($group_id));
        $i = 0;
        while($users[$i] = $db->fetchArray($result)) {
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
        $db = $this->db;
        $timestamp = microTimestamp();

        $sql = "INSERT INTO GROUPS (GROUP_NAME, CREATED_TIME, OWNER_ID,
            REGISTER_TYPE, MEMBER_ACCESS) VALUES (?, ?, ?, ?, ?);";
        $db->execute($sql, array($group_name, $timestamp, $user_id, 
            $register, $member));
        $sql = "SELECT G.GROUP_ID AS GROUP_ID FROM ".
            " GROUPS G WHERE G.GROUP_NAME = ?";
        $result = $db->execute($sql, array($group_name));
        if(!$row = $db->fetchArray($result) ) {
            $last_id = -1;
        }
        $last_id = $row['GROUP_ID'];
        $now = time();
        $sql= "INSERT INTO USER_GROUP (USER_ID, GROUP_ID, STATUS,
            JOIN_DATE) VALUES
            ($user_id, $last_id, ".ACTIVE_STATUS.", $now)";
        $db->execute($sql);
    }

    /**
     *
     */
    function updateGroup($group)
    {
        $db = $this->db;
        $group_id = $group['GROUP_ID'];
        unset($group['GROUP_ID']);
        unset($group['GROUP_NAME']);
        unset($group['OWNER']); //column not in table
        unset($group['STATUS']); // column not in table
        unset($group['JOIN_DATE']); // column not in table
        $sql = "UPDATE GROUPS SET ";
        $comma ="";
        $params = array();
        foreach($group as $field => $value) {
            $sql .= "$comma $field=? ";
            $comma = ",";
            $params[] = $value;
        }
        $sql .= " WHERE GROUP_ID=?";
        $params[] = $group_id;
        $db->execute($sql, $params);
    }

    /**
     *
     */
    function checkUserGroup($user_id, $group_id, $status = -1)
    {
        $db = $this->db;
        $params = array($user_id, $group_id);
        $sql = "SELECT COUNT(*) AS NUM FROM USER_GROUP UG WHERE
            UG.USER_ID=? AND UG.GROUP_ID=?";
        if($status >=0) {
            $sql .= " AND STATUS=?";
            $params[] = $status;
        }
        $result = $db->execute($sql, $params);
        if(!$row = $db->fetchArray($result) ) {
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
        $db = $this->db;
        $sql = "UPDATE USER_GROUP SET STATUS=? WHERE
            GROUP_ID=? AND USER_ID=?";
        $db->execute($sql, array($status, $group_id, $user_id));
    }

    /**
     * Get group id associated with groupname (so groupnames better be unique)
     *
     * @param string $group_name to use to look up a group_id
     * @return string  group_id corresponding to the groupname.
     */
    function getGroupId($group_name)
    {
        $db = $this->db;
        $sql = "SELECT G.GROUP_ID AS GROUP_ID FROM ".
            "GROUPS G WHERE G.GROUP_NAME = ? ";
        $result = $db->execute($sql, array($group_name));
        if(!$row = $db->fetchArray($result) ) {
            return -1;
        }
        return $row['GROUP_ID'];
    }

    /**
     *  Delete a group from the database and any associated data in
     *  GROUP_ITEM and USER_GROUP tables.
     *
     *  @param string $group_id id of the group to delete
     */
        function deleteGroup($group_id)
        {
            $db = $this->db;
            $params = array($group_id);
            $sql = "DELETE FROM GROUPS WHERE GROUP_ID=?";
            $db->execute($sql, $params);
            $sql = "DELETE FROM GROUP_ITEM WHERE GROUP_ID=?";
            $db->execute($sql, $params);
            $sql = "DELETE FROM USER_GROUP WHERE GROUP_ID=?";
            $db->execute($sql, $params);
        }
    /**
     *  Get a list of all groups. Group names are not localized since these are
     *  created by end user admins of the search engine
     *
     *  @return array an array of group_id, group_name pairs
     */
    function getGroupList()
    {
        $db = $this->db;
        $groups = array();
        $sql = "SELECT G.GROUP_ID AS GROUP_ID, G.GROUP_NAME AS GROUP_NAME
             FROM GROUPS G";
        $result = $db->execute($sql);
        $i = 0;
        while($groups[$i] = $db->fetchArray($result)) {
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
        $db = $this->db;
        $limit = $db->limitOffset($limit, $num);
        $any_fields = array("access", "register");
        list($where, $order_by) = 
            $this->searchArrayToWhereOrderClauses($search_array, $any_fields);
        $add_where = " WHERE ";
        if($where != "") {
            $add_where = " AND ";
        }
        $where .= $add_where. " UG.USER_ID='".$db->escapeString($user_id).
            "' AND  UG.GROUP_ID=G.GROUP_ID AND G.OWNER_ID=O.USER_ID";
        $sql = "SELECT G.GROUP_ID AS GROUP_ID,
            G.GROUP_NAME AS GROUP_NAME, G.OWNER_ID AS OWNER_ID,
            O.USER_NAME AS OWNER, REGISTER_TYPE, UG.STATUS AS STATUS,
            G.MEMBER_ACCESS, UG.JOIN_DATE AS JOIN_DATE FROM GROUPS G, USERS O,
            USER_GROUP UG
            $where $order_by $limit";
        $result = $db->execute($sql);
        $i = 0;
        while($groups[$i] = $db->fetchArray($result)) {
            $i++;
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }

    /**
     *
     *  @param int $group_id
     *  @return int
     */
    function getRegisterType($group_id)
    {
        $db = $this->db;
        $groups = array();
        $sql = "SELECT REGISTER_TYPE FROM GROUPS G WHERE GROUP_ID=?";
        $result = $db->execute($sql, array($group_id));
        if(!$result) { return false; }
        $row = $db->fetchArray($result);
        if(!$row) { return false; }
        return $row['REGISTER_TYPE'];
    }

    /**
     *  @param int $group_id
     *  @param int $user_id
     *  @return array
     */
    function getGroupById($group_id, $user_id)
    {
        $db = $this->db;
        $group = $this->getGroups(0, 1, array(
            array("group_id","=", $group_id, "")), $user_id);
        $where = " WHERE ";
        $params = array(":group_id" => $group_id);
        if($user_id != ROOT_ID) {
            $where .= " UG.USER_ID = :user_id AND ";
            $params[":user_id"] = $user_id;
        }
        $where .= " UG.GROUP_ID= :group_id".
            " AND  UG.GROUP_ID=G.GROUP_ID AND OWNER_ID = O.USER_ID";
        $sql = "SELECT G.GROUP_ID AS GROUP_ID,
            G.GROUP_NAME AS GROUP_NAME, G.OWNER_ID AS OWNER_ID,
            O.USER_NAME AS OWNER, REGISTER_TYPE, UG.STATUS,
            G.MEMBER_ACCESS AS MEMBER_ACCESS, UG.JOIN_DATE AS JOIN_DATE
            FROM GROUPS G, USERS O, USER_GROUP UG $where " .$db->limitOffset(1);
        $result = $db->execute($sql, $params);
        $group = false;
        if($result) {
            $group = $db->fetchArray($result);
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
        $db = $this->db;
        $any_fields = array("access", "register");
        list($where, $order_by) = 
            $this->searchArrayToWhereOrderClauses($search_array,$any_fields);
        $add_where = " WHERE ";
        if($where != "") {
            $add_where = " AND ";
        }
        $where .= $add_where. " UG.USER_ID='".$db->escapeString($user_id).
            "' AND  UG.GROUP_ID=G.GROUP_ID";
        $sql = "SELECT COUNT(*) AS NUM FROM GROUPS G, USER_GROUP UG
            $where";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        return $row['NUM'];
    }

   /**
    *  Get a list of all groups which are created by the user_id. Group names
    *  are not localized since these are
    *  created by end user admins of the search engine
    *
    *  @param int $user_id to get groups for
    *  @return array an array of group_id, group_name pairs
    */
    function getUserGroups($user_id)
    {
        $db = $this->db;
        $groups = array();
        $sql = "SELECT UG.GROUP_ID AS GROUP_ID, UG.USER_ID AS USER_ID," .
            " G.GROUP_NAME AS GROUP_NAME FROM USER_GROUP UG, GROUPS G" .
            " WHERE USER_ID = ? AND UG.GROUP_ID = G.GROUP_ID";
        $result = $db->execute($sql, array($user_id));
        $i = 0;
        while($groups[$i] = $db->fetchArray($result)) {
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
        $db = $this->db;
        $sql = "UPDATE GROUPS SET OWNER_ID=? WHERE GROUP_ID=?";
        $db->execute($sql, array($user_id, $group_id));
    }

    /**
     *  Add an allowed user to an existing group
     *
     *  @param string $userid the id of the user to add
     *  @param string $groupid  the group id of the group to add the user to
     */
    function addUserGroup($user_id, $group_id, $status = ACTIVE_STATUS)
    {
        $join_date = time();
        $db = $this->db;
        $sql = "INSERT INTO USER_GROUP VALUES (?, ?, ?, ?)";
        $db->execute($sql, array($user_id, $group_id, $status, $join_date));
    }

    /**
     *
     *  @param int $user_id
     *  @param int $group_id
     */
    function deletableUser($user_id, $group_id)
    {
        $sql = "SELECT COUNT(*) AS NUM FROM USER_GROUP UG, GROUPS G WHERE
            UG.USER_ID != G.OWNER_ID AND UG.USER_ID=? AND UG.GROUP_ID=?";
        $result = $db->execute($sql, array($user_id, $group_id));
        if(!$row = $db->fetchArray($result) ) {
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
        $db = $this->db;
        $sql = "DELETE FROM USER_GROUP WHERE USER_ID=? AND GROUP_ID=?";
        $db->execute($sql, array($user_id, $group_id));
    }

    /**
     *  @param int $item_id
     */
    function getGroupItem($item_id)
    {
        $db = $this->db;
        $sql = "SELECT * FROM GROUP_ITEM WHERE ID=? " . $db->limitOffset(1);
        $result = $db->execute($sql, array($item_id));
        if(!$result) { return false; }
        $row = $db->fetchArray($result);
        return $row;
    }

    /**
     *
     *  @param int $parent_id
     *  @param int $group_id
     *  @param int $user_id
     *  @param string $title
     *  @pararm string $description
     */
    function addGroupItem($parent_id, $group_id, $user_id, $title, $description)
    {
        $db = $this->db;
        $join_date = time();
        $now = time();
        $sql = "INSERT INTO GROUP_ITEM(PARENT_ID, GROUP_ID, USER_ID, TITLE,
            DESCRIPTION, PUBDATE) VALUES (?, ?, ?, ?, ?, ? )";
        $db->execute($sql, array($parent_id, $group_id, $user_id, $title,
            $description, $now));
        if($parent_id == 0) {
            $id = $db->insertID();
            $sql = "UPDATE GROUP_ITEM SET PARENT_ID=? WHERE ID=?";
            $db->execute($sql, array($id, $id));
        }
    }

    /**
     *
     *  @param int $post_id
     *  @param string $title
     *  @pararm string $description
     */
    function updateGroupItem($id, $title, $description)
    {
        $db = $this->db;
        $sql = "UPDATE GROUP_ITEM SET TITLE=?, DESCRIPTION=? WHERE ID=?";
        $db->execute($sql, array($title, $description, $id));
    }

    /**
     *
     * @param int $post_id
     * @param int $user_id
     */
    function deleteGroupItem($post_id, $user_id)
    {
        $db = $this->db;
        $params = array($post_id);
        if($user_id == ROOT_ID) {
            $and_where = "";
        } else {
            $and_where = " AND USER_ID=?";
            $params[] = $user_id;
        }
        $sql = "DELETE FROM GROUP_ITEM WHERE ID=? $and_where";
        $db->execute($sql, $params);
        return $db->affectedRows();
    }

    /**
     *
     *  @param int $limit
     *  @param int $num
     *  @param array $search_array
     */
    function getGroupItems($limit=0, $num=100, $search_array = array(),
        $user_id = ROOT_ID)
    {
        $db = $this->db;
        $limit = $db->limitOffset($limit, $num);
        $any_fields = array("access", "register");
        list($where, $order_by) = 
            $this->searchArrayToWhereOrderClauses($search_array, $any_fields);
        $add_where = " WHERE ";
        if($where != "") {
            $add_where = " AND ";
        }
        $user_id = $db->escapeString($user_id);
        $where .= $add_where. " UG.USER_ID='$user_id' AND
            GI.GROUP_ID=G.GROUP_ID AND GI.GROUP_ID=UG.GROUP_ID AND
            UG.USER_ID = U.USER_ID AND ((
            UG.STATUS='".ACTIVE_STATUS."'
            AND G.MEMBER_ACCESS IN ('".GROUP_READ."','".GROUP_READ_WRITE."')) OR
            (G.OWNER_ID = UG.USER_ID)) AND
            P.USER_ID = GI.USER_ID";
        $sql = "SELECT DISTINCT GI.ID AS ID, GI.PARENT_ID AS PARENT_ID,
            GI.GROUP_ID AS GROUP_ID, GI.TITLE AS TITLE,
            GI.DESCRIPTION AS DESCRIPTION, GI.PUBDATE AS PUBDATE, G.OWNER_ID
            AS OWNER_ID, G.MEMBER_ACCESS AS MEMBER_ACCESS,
            G.GROUP_NAME AS GROUP_NAME, P.USER_NAME AS USER_NAME, P.USER_ID AS
            USER_ID
            FROM GROUP_ITEM GI, GROUPS G, USER_GROUP UG, USERS U, USERS P
            $where $order_by $limit";
        $result = $db->execute($sql);
        $i = 0;
        while($groups[$i] = $db->fetchArray($result)) {
            $i++;
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }

    /**
     *  Returns the number of users in the user table
     *
     *  @param array $search_array
     *  @param int $$user_id
     *  @return int number of users
     */
    function getGroupItemCount($search_array = array(), $user_id = ROOT_ID)
    {
        $db = $this->db;
        $any_fields = array("access", "register");
        list($where, $order_by) = 
            $this->searchArrayToWhereOrderClauses($search_array, $any_fields);
        $add_where = " WHERE ";
        if($where != "") {
            $add_where = " AND ";
        }
        $user_id = $db->escapeString($user_id);
        $where .= $add_where. " UG.USER_ID='$user_id' AND
            GI.USER_ID=P.USER_ID AND
            GI.GROUP_ID=G.GROUP_ID AND GI.GROUP_ID=UG.GROUP_ID AND ((
            UG.STATUS='".ACTIVE_STATUS."'
            AND G.MEMBER_ACCESS IN ('".GROUP_READ."','".GROUP_READ_WRITE."')) OR
            (G.OWNER_ID = UG.USER_ID))";
        $sql = "SELECT COUNT(*) AS NUM FROM GROUP_ITEM GI, GROUPS G,
            USER_GROUP UG, USERS P $where";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        return $row['NUM'];
    }
}
?>
