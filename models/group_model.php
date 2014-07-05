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
/** For formatting wiki */
require_once BASE_DIR."/lib/wiki_parser.php";
/**
 * This is class is used to handle
 * db results related to Group Administration. Groups are collections of
 * users who might access a common blog/news feed and set of pages. This
 * method also controls adding and deleting entries to a group feed and
 * does limited access control checks of these operations.
 *
 * @author Mallika Perepa (creator), Chris Pollett (rewrite)
 * @package seek_quarry
 * @subpackage model
 */
class GroupModel extends Model
{
    /**
     * Associations of the form
     *     name of field for web forms => database column names/abbreviations
     * In this case, things will in general map to the GROUPS, or USER_GROUP
     * or GROUP_ITEM tables in the Yioop database
     * @var array
     */
    var $search_table_column_map = array("access"=>"G.MEMBER_ACCESS",
        "group_id"=>"G.GROUP_ID", "post_id" => "GI.ID",
        "join_date"=>"UG.JOIN_DATE",
        "name"=>"G.GROUP_NAME", "owner"=>"O.USER_NAME",
        "pub_date" => "GI.PUBDATE", "parent_id"=>"GI.PARENT_ID",
        "register"=>"G.REGISTER_TYPE", "status"=>"UG.STATUS",
        "user_id"=>"P.USER_ID");
    /**
     * These fields if present in $search_array (used by @see getRows() ),
     * but with value "0", will be skipped as part of the where clause
     * but will be used for order by clause
     * @var array
     */
    var $any_fields = array("access", "register");
    /**
     * Used to determine the select clause for GROUPS table when do query
     * to marshal group objects for the controller mainly in mangeGroups
     * @param mixed $args We use $args[1] to say whether in browse mode or not.
     *     browse mode is for groups a user could join rather than ones already
     *     joined
     */
    function selectCallback($args)
    {
        if(count($args) < 2) {
            return "*";
        }
        list($user_id, $browse, ) = $args;
        if($browse) {
            $join_date = "";
            $status = "";
        } else {
            $join_date = ", UG.JOIN_DATE AS JOIN_DATE";
            $status = " UG.STATUS AS STATUS,";
        }
        $select = "DISTINCT G.GROUP_ID AS GROUP_ID,
            G.GROUP_NAME AS GROUP_NAME, G.OWNER_ID AS OWNER_ID,
            O.USER_NAME AS OWNER, REGISTER_TYPE, $status
            G.MEMBER_ACCESS $join_date";
        return $select;
    }
    /**
     * {@inheritDoc}
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables (in this case none)
     */
    function fromCallback($args)
    {
        return "GROUPS G, USER_GROUP UG, USERS O";
    }
    /**
     * Used to restrict getRows in which rows it returns. Rows in this
     * case corresponding to Yioop groups. The restrictions added are to
     * restrict to those group available to a given user_id and whether or
     * not the user wants groups subscribed to, or groups that could be
     * subscribed to
     *
     * @param array $args first two elements are the $user_id of the user
     *     and the $browse flag which says whether or not user is browing
     *     through all groups to which he could subscribe and read or
     *     just those groups to which he is alrady subscribed.
     * @return string a SQL WHERE clause suitable to perform the above
     *     restrictions
     */
    function whereCallback($args)
    {
        $db = $this->db;
        if(count($args) < 2) {
            return "";
        }
        list($user_id, $browse, ) = $args;
        if($browse) {
            $where =
                " UG.GROUP_ID=G.GROUP_ID AND G.OWNER_ID=O.USER_ID AND NOT ".
                "EXISTS (SELECT * FROM USER_GROUP UG2 WHERE UG2.USER_ID = ".
                $db->escapeString($user_id)." AND UG2.GROUP_ID = G.GROUP_ID)";
        } else {
            $where = " UG.USER_ID='".$db->escapeString($user_id).
                "' AND  UG.GROUP_ID=G.GROUP_ID AND G.OWNER_ID=O.USER_ID";
        }
        return $where;
    }
    /**
     * Get an array of users that belong to a group
     *
     * @param string $group_id  the group_id to get users for
     * @param string $filter to LIKE filter users
     * @param int $limit first user to get
     * @param int $num number of users to return
     * @return array of USERS rows
     */
    function getGroupUsers($group_id, $filter, $limit,
        $num = NUM_RESULTS_PER_PAGE)
    {
        $db = $this->db;
        $limit = $db->limitOffset($limit, $num);
        $like = "";
        $param_array = array($group_id);
        if($filter != "") {
            $like = "AND U.USER_NAME LIKE ?";
            $param_array[] = "%".$filter."%";
        }
        $users = array();
        $sql = "SELECT UG.USER_ID, U.USER_NAME, UG.GROUP_ID, G.OWNER_ID,".
            " UG.STATUS ".
            " FROM USER_GROUP UG, USERS U, GROUPS G".
            " WHERE UG.GROUP_ID = ? AND UG.USER_ID = U.USER_ID AND" .
            " G.GROUP_ID = UG.GROUP_ID $like $limit";
        $result = $db->execute($sql, $param_array);
        $i = 0;
        while($users[$i] = $db->fetchArray($result)) {
           $i++;
        }
        unset($users[$i]); //last one will be null
        return $users;
    }
    /**
     * Get the number of users which belong to a group and whose user_name
     * matches a filter
     *
     * @param int $group_id id of the group to get a count of
     * @param string $filter to filter usernames by
     * @return int count of matching users
     */
    function countGroupUsers($group_id, $filter="")
    {
        $db = $this->db;
        $users = array();
        $like = "";
        $param_array = array($group_id);
        if($filter != "") {
            $like = "AND U.USER_NAME LIKE ?";
            $param_array[] = "%".$filter."%";
        }
        $sql = "SELECT COUNT(*) AS NUM ".
            " FROM USER_GROUP UG, USERS U".
            " WHERE UG.GROUP_ID = ? AND UG.USER_ID = U.USER_ID $like";
        $result = $db->execute($sql, $param_array);
        if($result) {
            $row = $db->fetchArray($result);
        }
        return $row['NUM'];
    }
    /**
     * Add a groupname to the database using provided string
     *
     * @param string $group_name  the groupname to be added
     * @param int $user_id user identifier of who owns the group
     * @param int $register flag that says what kinds of registration are
     *      allowed for this group NO_JOIN, REQUEST_JOIN, PUBLIC_JOIN
     * @param int $member flag that says how members other than the owner can
     *      access this group GROUP_READ, GROUP_READ_COMMENT (can comment
     *      on threads but not start. i.e., a blog), GROUP_READ_WRITE
     */
    function addGroup($group_name, $user_id, $register = REQUEST_JOIN,
        $member = GROUP_READ)
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
        return $last_id;
    }
    /**
     * Takes the passed associated array $group representing changes
     * fields of a GROUPS row, and executes an UPDATE statement to persist
     * those changes fields to the database.
     *
     * @param array $group associative array with a GROUP_ID as well as the
     *     fields to update
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
     * Check is a user given by $user_id belongs to a group given
     * by $group_id. If the field $status is sent then check if belongs
     * to the group with $status access (active, invited, request, banned)
     *
     * @param int $user_id user to look up
     * @param int $group_id group to check if member of
     * @param int $status membership type
     * @return bool whether or not is a member
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
     * Change the status of a user in a group
     *
     * @param int $user_id of user to change
     * @param int $group_id of group to change status for
     * @param int $status what the new status should be
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
     * Delete a group from the database and any associated data in
     * GROUP_ITEM and USER_GROUP tables.
     *
     * @param string $group_id id of the group to delete
     */
    function deleteGroup($group_id)
    {
        $db = $this->db;
        $params = array($group_id);
        $sql = "DELETE FROM GROUPS WHERE GROUP_ID=?";
        $db->execute($sql, $params);
        $sql = "DELETE FROM GROUP_ITEM WHERE GROUP_ID=?";
        $db->execute($sql, $params);
        $sql = "DELETE FROM GROUP_PAGE WHERE GROUP_ID=?";
        $db->execute($sql, $params);
        $sql = "DELETE FROM GROUP_PAGE_HISTORY WHERE GROUP_ID=?";
        $db->execute($sql, $params);
    }
    /**
     * Return the type of the registration for a group given by $group_id
     * This says who is allowed to register for the group (i.e., is it
     *  by invitation only, by request, or anyone can join)
     *
     * @param int $group_id which group to find the type of
     * @return int the numeric code for the registration type
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
     * Returns information about the group with id $group_id provided
     * that the requesting user $user_id has access to it
     *
     * @param int $group_id id of group to look up
     * @param int $user_id user asking for group info
     * @return array row from group table or false (if no access or doesn't
     *     exists)
     */
    function getGroupById($group_id, $user_id)
    {
        $db = $this->db;
        $group = $this->getRows(0, 1,
            $total_rows, array(array("group_id","=", $group_id, "")), $user_id);
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
            FROM GROUPS G, USERS O, USER_GROUP UG $where " .
            $db->limitOffset(1);
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
    * Get a list of all groups which user_id belongs to. Group names
    * are not localized since these are
    * created by end user admins of the search engine
    *
    * @param int $user_id to get groups for
    * @param string $filter to LIKE filter groups
    * @param int $limit first user to get
    * @param int $num number of users to return
    * @return array an array of group_id, group_name pairs
    */
    function getUserGroups($user_id, $filter, $limit,
        $num = NUM_RESULTS_PER_PAGE)
    {
        $db = $this->db;
        $groups = array();
        $limit = $db->limitOffset($limit, $num);
        $like = "";
        $param_array = array($user_id);
        if($filter != "") {
            $like = "AND G.GROUP_NAME LIKE ?";
            $param_array[] = "%".$filter."%";
        }
        $sql = "SELECT UG.GROUP_ID AS GROUP_ID, UG.USER_ID AS USER_ID," .
            " G.GROUP_NAME AS GROUP_NAME, UG.STATUS AS STATUS ".
            " FROM USER_GROUP UG, GROUPS G" .
            " WHERE USER_ID = ? AND UG.GROUP_ID = G.GROUP_ID $like ".
            " ORDER BY G.GROUP_NAME $limit";
        $result = $db->execute($sql, $param_array);
        $i = 0;
        while($groups[$i] = $db->fetchArray($result)) {
            $i++;
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }
    /**
     * Get a count of the number of groups to which user_id belongs.
     *
     * @param int $user_id to get groups for
     * @param string $filter to LIKE filter groups
     * @return int number of groups of the filtered type for the user
     */
    function countUserGroups($user_id, $filter="")
    {
        $db = $this->db;
        $users = array();
        $like = "";
        $param_array = array($user_id);
        if($filter != "") {
            $like = "AND G.GROUP_NAME LIKE ?";
            $param_array[] = "%".$filter."%";
        }
        $sql = "SELECT COUNT(*) AS NUM ".
            " FROM USER_GROUP UG, GROUPS G".
            " WHERE UG.USER_ID = ? AND UG.GROUP_ID = G.GROUP_ID $like";
        $result = $db->execute($sql, $param_array);
        if($result) {
            $row = $db->fetchArray($result);
        }
        return $row['NUM'];
    }
    /**
     * To update the OWNER_ID of a group
     *
     * @param string $group_id  the group id  to transfer admin privileges
     * @param string $user_id the id of the user who becomes the admin of group
     */
    function changeOwnerGroup($user_id, $group_id)
    {
        $db = $this->db;
        $sql = "UPDATE GROUPS SET OWNER_ID=? WHERE GROUP_ID=?";
        $db->execute($sql, array($user_id, $group_id));
    }
    /**
     * Add an allowed user to an existing group
     *
     * @param string $user_id the id of the user to add
     * @param string $group_id  the group id of the group to add the user to
     * @param int $status what should be the membership status of the added
     *      user. Should be one of ACTIVE_STATUS, INACTIVE_STATUS,
     *      BANNED_STATUS, INVITED_STATUS
     */
    function addUserGroup($user_id, $group_id, $status = ACTIVE_STATUS)
    {
        $join_date = time();
        $db = $this->db;
        $sql = "INSERT INTO USER_GROUP VALUES (?, ?, ?, ?)";
        $db->execute($sql, array($user_id, $group_id, $status, $join_date));
    }
    /**
     * Checks if a user belongs to a group but is not the owner of that group
     * Such a user could be deleted from the group
     *
     * @param int $user_id which user to look up
     * @param int $group_id which group to look up for
     * @return bool where user is deletable
     */
    function deletableUser($user_id, $group_id)
    {
        $db = $this->db;
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
     * Delete a user from a group by userid an groupid
     *
     * @param string $user_id  the userid of the user to delete
     * @param string $group_id  the group id of the group to delete
     */
    function deleteUserGroup($user_id, $group_id)
    {
        $db = $this->db;
        $sql = "DELETE FROM USER_GROUP WHERE USER_ID=? AND GROUP_ID=?";
        $db->execute($sql, array($user_id, $group_id));
    }
    /**
     * Returns the GROUP_FEED item with the given id
     *
     * @param int $item_id the item to get info about
     * @return array row from GROUP_FEED table
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
     * Creates a new group item
     *
     * @param int $parent_id thread id to use for the item
     * @param int $group_id what group the item should be added to
     * @param int $user_id of user making the post
     * @param string $title title of the group feed item
     * @param string $description actual content of the post
     * @param int $type flag saying what kind of group item this is. One of
     *      STANDARD_GROUP_ITEM, WIKI_GROUP_ITEM (used for threads discussing
     *      a wiki page)
     * @return int $id of item added
     */
    function addGroupItem($parent_id, $group_id, $user_id, $title,
        $description, $type= STANDARD_GROUP_ITEM)
    {
        $db = $this->db;
        $join_date = time();
        $now = time();
        $sql = "INSERT INTO GROUP_ITEM (PARENT_ID, GROUP_ID, USER_ID, TITLE,
            DESCRIPTION, PUBDATE, TYPE) VALUES (?, ?, ?, ?, ?, ?, ? )";
        $db->execute($sql, array($parent_id, $group_id, $user_id, $title,
            $description, $now, $type));
        $id = $db->insertID("GROUP_ITEM");
        if($parent_id == 0) {
            $sql = "UPDATE GROUP_ITEM SET PARENT_ID=? WHERE ID=?";
            $db->execute($sql, array($id, $id));
        }
        return $id;
    }
    /**
     * Updates a group feed item's title and description. This assumes
     * the given item already exists.
     *
     * @param int $id which item to change
     * @param string $title the new title
     * @param string $description the new description
     */
    function updateGroupItem($id, $title, $description)
    {
        $db = $this->db;
        $sql = "UPDATE GROUP_ITEM SET TITLE=?, DESCRIPTION=? WHERE ID=?";
        $db->execute($sql, array($title, $description, $id));
    }
    /**
     * Removes a group feed item from the GROUP_ITEM table.
     *
     * @param int $post_id of item to remove
     * @param int $user_id the id of the person trying to perform the
     *     removal. If not root, or the original creator of the item,
     *     the item won'r be removed
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
     * Gets the group feed items visible to a user with $user_id
     * and which match the supplied search criteria found in $search_array,
     * starting from the $limit'th matching item to the $limit+$num item.
     *
     * @param int $limit starting offset group item to display
     * @param int $num number of items from offset to display
     * @param array $search_array each element of this is a quadruple
     *     name of a field, what comparison to perform, a value to check,
     *     and an order (ascending/descending) to sort by
     * @param int $user_id who is making this request to determine which
     * @param int $for_group if this value is set it is a assumed
     *     that group_items are being returned for only one group
     *     and that they should be grouped by thread
     * @return array elements of which represent one group feed item
     */
    function getGroupItems($limit = 0, $num = 100, $search_array = array(),
        $user_id = ROOT_ID, $for_group = -1)
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
        $non_public_where = ($user_id != PUBLIC_GROUP_ID) ?
            " UG.USER_ID='$user_id' AND " :
            " G.REGISTER_TYPE='".PUBLIC_JOIN."' AND ";
        $non_public_status = ($user_id != PUBLIC_GROUP_ID) ?
            " UG.STATUS='".ACTIVE_STATUS."' AND " : "";
        $where .= $add_where. $non_public_where .
            "GI.GROUP_ID=G.GROUP_ID AND GI.GROUP_ID=UG.GROUP_ID AND ((
            $non_public_status
            G.MEMBER_ACCESS IN ('".GROUP_READ."','".GROUP_READ_COMMENT.
            "','".GROUP_READ_WRITE."'))OR
            (G.OWNER_ID = UG.USER_ID))";
        if($for_group >= 0) {
            $group_by = " GROUP BY GI.PARENT_ID";
            $order_by = " ORDER BY E.PUBDATE DESC ";
            $select = "SELECT E.*, I.TITLE AS TITLE, 
                I.DESCRIPTION AS DESCRIPTION,
                I.USER_ID AS USER_ID, II.USER_ID AS LAST_POSTER_ID,
                U.USER_NAME AS USER_NAME, P.USER_NAME AS LAST_POSTER";
            $sub_select = "SELECT DISTINCT MIN(GI.ID) AS ID,
                MAX(GI.ID) AS LAST_ID,
                COUNT(DISTINCT GI.ID) AS NUM_POSTS, GI.PARENT_ID AS PARENT_ID,
                MIN(GI.GROUP_ID) AS GROUP_ID, MAX(GI.PUBDATE) AS PUBDATE,
                MIN(G.OWNER_ID) AS OWNER_ID,
                MIN(G.MEMBER_ACCESS) AS MEMBER_ACCESS,
                MIN(G.GROUP_NAME) AS GROUP_NAME,
                MIN(GI.PUBDATE) AS RECENT_DATE,
                MIN(GI.TYPE) AS TYPE";
            $sub_sql = "$sub_select
                FROM GROUP_ITEM GI, GROUPS G, USER_GROUP UG
                $where $group_by";
            $sql = "$select FROM ($sub_sql) E,
                GROUP_ITEM I, GROUP_ITEM II, USERS U, USERS P
                WHERE E.ID = I.ID AND E.LAST_ID = II.ID AND
                I.USER_ID = U.USER_ID AND II.USER_ID = P.USER_ID
                $order_by $limit";
        } else {
            $where .= " AND P.USER_ID = GI.USER_ID";
            $select = "SELECT DISTINCT GI.ID AS ID,
                GI.PARENT_ID AS PARENT_ID, GI.GROUP_ID AS GROUP_ID,
                GI.TITLE AS TITLE, GI.DESCRIPTION AS DESCRIPTION,
                GI.PUBDATE AS PUBDATE, G.OWNER_ID AS OWNER_ID,
                G.MEMBER_ACCESS AS MEMBER_ACCESS,
                G.GROUP_NAME AS GROUP_NAME, P.USER_NAME AS USER_NAME,
                P.USER_ID AS USER_ID, GI.TYPE AS TYPE ";
            $sql = "$select
                FROM GROUP_ITEM GI, GROUPS G, USER_GROUP UG, USERS P
                $where $order_by $limit";
        }
        $result = $db->execute($sql);
        $i = 0;
        $read_only = ($user_id == PUBLIC_GROUP_ID);
        if($read_only) {
            while($groups[$i] = $db->fetchArray($result)) {
                $groups[$i]["MEMBER_ACCESS"] = GROUP_READ;
                $i++;
            }
        } else {
            while($groups[$i] = $db->fetchArray($result)) {
                $i++;
            }
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }
    /**
     * Gets the number of group feed items visible to a user with $user_id
     * and which match the supplied search criteria found in $search_array
     *
     * @param array $search_array each element of this is a quadruple
     *     name of a field, what comparison to perform, a value to check,
     *     and an order (ascending/descending) to sort by
     * @param int $user_id who is making this request to determine which
     * @param int $for_group if this value is set it is a assumed
     *     that group_items are being returned for only one group
     *     and that the count desrired is over the number of threads in that
     *     group
     * @return int number of items matching the search criteria for the
     *     given user_id
     */
    function getGroupItemCount($search_array = array(), $user_id = ROOT_ID,
        $for_group = -1)
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
        $non_public_where = ($user_id != PUBLIC_GROUP_ID) ?
            " UG.USER_ID='$user_id' AND " :
            " G.REGISTER_TYPE='".PUBLIC_JOIN."' AND ";
        $non_public_status = ($user_id != PUBLIC_GROUP_ID) ?
            " UG.STATUS='".ACTIVE_STATUS."' AND " : "";
        $where .= $add_where. $non_public_where .
            "GI.USER_ID=P.USER_ID AND
            GI.GROUP_ID=G.GROUP_ID AND GI.GROUP_ID=UG.GROUP_ID AND ((
            $non_public_status
            G.MEMBER_ACCESS IN ('".GROUP_READ."','".GROUP_READ_COMMENT.
            "','".GROUP_READ_WRITE."'))OR
            (G.OWNER_ID = UG.USER_ID))";
        if($for_group >= 0) {
            $count_col = " COUNT(DISTINCT GI.PARENT_ID) ";
        } else {
            $count_col = " COUNT(DISTINCT GI.ID) ";
        }
        $sql = "SELECT $count_col AS NUM FROM GROUP_ITEM GI, GROUPS G,
            USER_GROUP UG, USERS P $where";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        return $row['NUM'];
    }
    /**
     * Used to add a wiki page revision by a given user to a wiki page
     * of a given name in a given group viewing the group under a given
     * language. If the page does not exist yet it, and its corresponding
     * discussion thread is created. Two pages are used for storage
     * GROUP_PAGE which contains a parsed to html version of the most recent
     * revision of a wiki page and GROUP_PAGE_HISTORY which contains non-parsed
     * versions of all revisions
     *
     * @param int $user_id identifier of who is adding this revision
     * @param int $group_id which group the wiki page revision if being done in
     * @param string $page_name title of page being revised
     * @param string $page wiki page with potential wiki mark up containing the
     *     revision
     * @param string $locale_tag locale we are adding the revision to
     * @param string $edit_comment user's reason for making the revision
     * @param string $thread_title if this is the first revision, then this
     *     should contain the title for the discussion thread about the
     *     revision
     * @param string $thread_description if this is the first revision, then
     *     this should be the body of the first post in discussion thread
     * @param string $base_address default url to be used in links
     *     on wiki page that use short syntax
     */
    function setPageName($user_id, $group_id, $page_name, $page, $locale_tag,
        $edit_comment, $thread_title, $thread_description, $base_address = "")
    {
        $db = $this->db;
        $pubdate = time();
        $parser = new WikiParser($base_address);
        $parsed_page = $parser->parse($page);
        if($page_id = $this->getPageID($group_id, $page_name, $locale_tag)) {
            $sql = "UPDATE GROUP_PAGE SET PAGE=? WHERE ID = ?";
            $result = $db->execute($sql, array($parsed_page, $page_id));
        } else {
            $discuss_thread = $this->addGroupItem(0, $group_id, $user_id, 
                $thread_title, $thread_description." ".date("r", $pubdate),
                WIKI_GROUP_ITEM);
            $sql = "INSERT INTO GROUP_PAGE (DISCUSS_THREAD, GROUP_ID,
                TITLE, PAGE, LOCALE_TAG) VALUES (?, ?, ?, ?, ?)";
            $result = $db->execute($sql, array($discuss_thread, $group_id,
                $page_name, $parsed_page, $locale_tag));
            $page_id = $db->insertID("GROUP_PAGE");
        }
        $sql = "INSERT INTO GROUP_PAGE_HISTORY (PAGE_ID, EDITOR_ID,
            GROUP_ID, TITLE, PAGE, LOCALE_TAG, PUBDATE, EDIT_COMMENT)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $result = $db->execute($sql, array($page_id, $user_id, $group_id,
            $page_name, $page, $locale_tag, $pubdate, $edit_comment));
    }
    /**
     * Looks up the page_id of a wiki page based on the group it belongs to,
     * its title, and the language it is in (these three things together
     * should uniquely fix a page).
     *
     * @param int $group_id group identifier of group wiki page belongs to
     * @param string $page_name title of wiki page to look up
     * @param string $locale_tag IANA language tag of page to lookup
     * @return mixed $page_id of page if exists, false otherwise
     */
    function getPageId($group_id, $page_name, $locale_tag)
    {
        $db = $this->db;
        $sql = "SELECT ID FROM GROUP_PAGE WHERE GROUP_ID = ?
            AND TITLE=? AND LOCALE_TAG= ?";
        $result = $db->execute($sql, array($group_id, $page_name, $locale_tag));
        if(!$result) { return false; }
        $row = $db->fetchArray($result);
        if($row) {
            return $row["ID"];
        }
        return false;
    }
    /**
     * Return the page id, page string, and discussion thread id of the
     * most recent revision of a wiki page
     *
     * @param int $group_id group identifier of group wiki page belongs to
     * @param string $name title of wiki page to look up
     * @param string $locale_tag IANA language tag of page to lookup
     * @param string $mode if "edit" we assume we are looking up the page
     *     so that it can be edited and so we return the most recent non-parsed
     *     revision of the page. Otherwise, we assume the page is meant to be
     *     read and so we return the variant of the page where wiki markup
     *     has already been replaced with HTML
     * @return array (page_id, page, discussion_id) of desired wiki page
     */
    function getPageInfoByName($group_id, $name, $locale_tag, $mode)
    {
        $db = $this->db;
        if($mode == "edit") {
            $sql = "SELECT HP.PAGE_ID AS ID, HP.PAGE AS PAGE,
                GP.DISCUSS_THREAD AS DISCUSS_THREAD FROM GROUP_PAGE GP,
                GROUP_PAGE_HISTORY HP WHERE GP.GROUP_ID = ?
                AND GP.TITLE = ? AND GP.LOCALE_TAG = ? AND HP.PAGE_ID = GP.ID
                ORDER BY HP.PUBDATE DESC ".$db->limitOffset(0, 1);
        } else {
            $sql = "SELECT ID, PAGE, DISCUSS_THREAD FROM GROUP_PAGE
                WHERE GROUP_ID = ? AND TITLE=? AND LOCALE_TAG = ?";
        }
        $result = $db->execute($sql, array($group_id, $name, $locale_tag));
        if(!$result) { return false; }
        $row = $db->fetchArray($result);
        if(!$row) {
            return false;
        }
        return $row;
    }
    /**
     * Returns the group_id, language, and page name of a wiki page
     *     corresponding to a page discussion thread with id $page_thread_id
     * @param int $page_thread_id the id of a wiki page discussion thread
     *     to look up page info for
     * @return array(group_id, language, and page name) of that wiki page
     */
    function getPageInfoByThread($page_thread_id)
    {
        $db = $this->db;
        $sql = "SELECT GROUP_ID, LOCALE_TAG, TITLE AS PAGE_NAME FROM GROUP_PAGE
            WHERE DISCUSS_THREAD = ?";
        $result = $db->execute($sql, array($page_thread_id));
        if(!$result) { return false; }
        $row = $db->fetchArray($result);
        if(!$row) {
            return false;
        }
        return $row;
    }
    /**
     * Returns the group_id, language, and page name of a wiki page
     *     corresponding to $page_id
     * @param int $page_id to look up page info for
     * @return array(group_id, language, and page name) of that wiki page
     */
    function getPageInfoByPageId($page_id)
    {
        $db = $this->db;
        $sql = "SELECT GROUP_ID, LOCALE_TAG, TITLE AS PAGE_NAME FROM GROUP_PAGE
            WHERE ID = ?";
        $result = $db->execute($sql, array($page_id));
        if(!$result) { return false; }
        $row = $db->fetchArray($result);
        if(!$row) {
            return false;
        }
        return $row;
    }
    /**
     * Returns an historical revision of a wiki page
     *
     * @param int $page_id identifier of wiki page want revision for
     * @param int $pubdate timestamp of revision desired
     * @return array (id, non-parsed wiki page, page_name,
     *     discussion thread id) of page revision
     */
    function getHistoryPage($page_id, $pubdate)
    {
        $db = $this->db;
        $sql = "SELECT HP.PAGE_ID AS ID, HP.PAGE AS PAGE, HP.TITLE AS PAGE_NAME,
            GP.DISCUSS_THREAD AS DISCUSS_THREAD FROM GROUP_PAGE GP,
            GROUP_PAGE_HISTORY HP WHERE HP.PAGE_ID = ?
            AND HP.PUBDATE=? AND HP.PAGE_ID=GP.ID";
        $result = $db->execute($sql, array($page_id, $pubdate));
        if(!$result) { return false; }
        $row = $db->fetchArray($result);
        if(!isset($row["PAGE"])) {
            return false;
        }
        return $row;
    }
    /**
     * Returns a list of revision history info for a wiki page.
     *
     * @param int $page_id indentifier for page want revision history of
     * @param string $limit first row we want from the result set
     * @param string $num number of rows we want starting from the first row
     *     in the result set
     * @return array elements of which are array with the revision date
     *     (PUBDATE), user name, page length, edit reason for the wiki pages
     *     revision
     */
    function getPageHistoryList($page_id, $limit, $num)
    {
        $db = $this->db;
        $sql = "SELECT COUNT(*) AS TOTAL, MIN(H.TITLE) AS PAGE_NAME
            FROM GROUP_PAGE_HISTORY H, USERS U
            WHERE H.PAGE_ID = ? AND
            U.USER_ID= H.EDITOR_ID";
        $page_name = "";
        $result = $db->execute($sql, array($page_id));
        if($result) {
            $row = $db->fetchArray($result);
            $total = ($row) ? $row["TOTAL"] : 0;
            $page_name = ($row) ? $row["PAGE_NAME"] : "";
        }
        $pages = array();
        if($total > 0) {
            $sql = "SELECT H.PUBDATE AS PUBDATE, U.USER_NAME AS USER_NAME,
                LENGTH(H.PAGE) AS PAGE_LEN,
                H.EDIT_COMMENT AS EDIT_REASON FROM GROUP_PAGE_HISTORY H, USERS U
                WHERE H.PAGE_ID = ? AND
                U.USER_ID= H.EDITOR_ID ORDER BY PUBDATE DESC ".
                $db->limitOffset($limit, $num);
            $result = $db->execute($sql, array($page_id));
            $i = 0;
            if($result) {
                while($pages[$i] = $db->fetchArray($result)) {
                    $i++;
                }
                unset($pages[$i]); //last one will be null
            }
        }
        return array($total, $page_name, $pages);
    }
    /**
     * Returns a list of applicable wiki pages of a group
     *
     * @param int $group_id of group want list of wiki pages for
     * @param string $locale_tag language want wiki page list for
     * @param string $filter string we want to filter wiki page title by
     * @param string $limit first row we want from the result set
     * @param string $num number of rows we want starting from the first row
     *     in the result set
     * @return array a pair ($total, $pages) where $total is the total number
     *     of rows that could be returned if $limit and $num not present
     *     $pages is an array each of whose elements is an array corresponding
     *     to one TITLE and the first 100 chars out of a wiki page.
     */
    function getPageList($group_id, $locale_tag, $filter, $limit, $num)
    {
        $db = $this->db;
        $filter_parts = preg_split("/\s+/", $filter);
        $like = "";
        $params = array($group_id, $locale_tag);
        foreach($filter_parts as $part) {
            if($part != "") {
                $like .= " AND UPPER(TITLE) LIKE ? ";
                $params[] = "%$part%";
            }
        }
        $sql = "SELECT COUNT(*) AS TOTAL
            FROM GROUP_PAGE WHERE GROUP_ID = ? AND
            LOCALE_TAG= ? AND LENGTH(PAGE) > 0 $like";
        $result = $db->execute($sql, $params);
        if($result) {
            $row = $db->fetchArray($result);
            $total = ($row) ? $row["TOTAL"] : 0;
        }
        $pages = array();
        if($total > 0) {
            $sql = "SELECT TITLE, PAGE AS DESCRIPTION
                FROM GROUP_PAGE WHERE GROUP_ID = ? AND
                LOCALE_TAG= ? AND LENGTH(PAGE) > 0 
                $like ORDER BY UPPER(TITLE) ASC ".
                $db->limitOffset($limit, $num);
            $result = $db->execute($sql, $params);
            $i = 0;
            if($result) {
                while($pages[$i] = $db->fetchArray($result)) {
                    $pages[$i]['DESCRIPTION'] = substr(
                        $pages[$i]['DESCRIPTION'], 0, 100);
                    $i++;
                }
                unset($pages[$i]); //last one will be null
            }
        }
        return array($total, $pages);
    }
}
?>
