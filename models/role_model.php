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
 * db results related to Role Administration
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class RoleModel extends Model
{
    /**
     * Used to map between search role form variables and database columns
     * @var array
     */
    var $search_table_column_map = array("name"=>"NAME");
    /** {@inheritdoc} */
    function selectCallback($args = NULL)
    {
        return "NAME";
    }
    /**
     *  Get the activities  (name, method, id) that a given role can perform
     *
     *  @param string $role_id  the rolid_id to get activities for
     */
    function getRoleActivities($role_id)
    {
        $db = $this->db;
        $activities = array();
        $locale_tag = getLocaleTag();
        $sql = "SELECT LOCALE_ID FROM LOCALE WHERE LOCALE_TAG = ? " .
            $db->limitOffset(1);
        $result = $db->execute($sql, array($locale_tag));
        $row = $db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];
        $sql = "SELECT DISTINCT R.ROLE_ID AS ROLE_ID, ".
            "RA.ACTIVITY_ID AS ACTIVITY_ID, ".
            "A.METHOD_NAME AS METHOD_NAME, ".
            "T.IDENTIFIER_STRING AS IDENTIFIER_STRING, ".
            "T.TRANSLATION_ID AS TRANSLATION_ID FROM ".
            "ROLE R, ROLE_ACTIVITY RA, ACTIVITY A, TRANSLATION T ".
            "WHERE  R.ROLE_ID = ? AND ".
            "R.ROLE_ID = RA.ROLE_ID AND T.TRANSLATION_ID = A.TRANSLATION_ID ".
            "AND RA.ACTIVITY_ID = A.ACTIVITY_ID";
        $result = $db->execute($sql, array($role_id));
        $i = 0;
        $sub_sql = "SELECT TRANSLATION AS ACTIVITY_NAME ".
            "FROM TRANSLATION_LOCALE ".
            "WHERE TRANSLATION_ID=? AND LOCALE_ID=? " . $db->limitOffset(1);
            // maybe do left join at some point
        while($activities[$i] = $db->fetchArray($result)) {
            $id = $activities[$i]['TRANSLATION_ID'];
            $result_sub =  $db->execute($sub_sql, array($id, $locale_id));
            $translate = $db->fetchArray($result_sub);

            if($translate) {
                $activities[$i]['ACTIVITY_NAME'] = $translate['ACTIVITY_NAME'];
            } else {
                $activities[$i]['ACTIVITY_NAME'] =
                    $activities['IDENTIFIER_STRING'];
            }
            $i++;
        }
        unset($activities[$i]); //last one will be null
        return $activities;
    }
    /**
     * Gets all the roles associated with a user id
     *
     * @param string $user_id  the user_id to get roles of
     * @return array of role_ids and their names
     */
    function getUserRoles($user_id, $filter, $limit,
        $num = NUM_RESULTS_PER_PAGE)
    {
        $db = $this->db;
        $user_id = $db->escapeString($user_id);
        $roles = array();
        $locale_tag = getLocaleTag();
        $limit = $db->limitOffset($limit, $num);
        $like = "";
        $param_array = array($user_id);
        if($filter != "") {
            $like = "AND R.NAME LIKE ?";
            $param_array[] = "%".$filter."%";
        }
        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = ? ". $db->limitOffset(1);
        $result = $db->execute($sql, array($locale_tag));
        $row = $db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];
        $sql = "SELECT UR.ROLE_ID AS ROLE_ID, R.NAME AS ROLE_NAME ".
            " FROM  USER_ROLE UR, ROLE R WHERE UR.USER_ID = ? ".
            " AND R.ROLE_ID = UR.ROLE_ID $like  ORDER BY R.NAME ASC $limit";
        $result = $db->execute($sql, $param_array);
        $i = 0;
        while($roles[$i] = $db->fetchArray($result)) {
            $i++;
        }
        unset($roles[$i]); //last one will be null
        return $roles;
    }
    /**
     *  Get a count of the number of groups that a user_id has.
     *
     *  @param int $user_id to get roles for
     *  @param string $filter to LIKE filter groups
     *  @return int number of roles of the filtered type for the user
     */
    function countUserRoles($user_id, $filter="")
    {
        $db = $this->db;
        $users = array();
        $like = "";
        $param_array = array($user_id);
        if($filter != "") {
            $like = "AND R.NAME LIKE ?";
            $param_array[] = "%".$filter."%";
        }
        $sql = "SELECT COUNT(*) AS NUM ".
            " FROM USER_ROLE UR, ROLE R".
            " WHERE UR.USER_ID = ? AND UR.ROLE_ID = R.ROLE_ID $like";
        $result = $db->execute($sql, $param_array);
        if($result) {
            $row = $db->fetchArray($result);
        }
        return $row['NUM'];
    }
    /**
     *   Check is a user given by $user_id has the role $role_id
     *
     *   @param int $user_id user to look up
     *   @param int $role_id role to check if member of
     *   @return bool whether or not has role
     */
    function checkUserRole($user_id, $role_id)
    {
        $db = $this->db;
        $params = array($user_id, $role_id);
        $sql = "SELECT COUNT(*) AS NUM FROM USER_ROLE UR WHERE
            UR.USER_ID=? AND UR.ROLE_ID=?";
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
     * Adds a role to a given user
     *
     * @param string $userid  the id of the user to add the role to
     * @param string $roleid  the id of the role to add
     */
    function addUserRole($user_id, $role_id)
    {
        $sql = "INSERT INTO USER_ROLE VALUES (?, ?) ";
        $result = $this->db->execute($sql, array($user_id, $role_id));
    }
    /**
     * Deletes a role from a given user
     *
     * @param string $userid  the id of the user to delete the role from
     * @param string $roleid  the id of the role to delete
     */
    function deleteUserRole($user_id, $role_id)
    {
        $sql = "DELETE FROM USER_ROLE WHERE USER_ID=? AND  ROLE_ID=?";
        $result = $this->db->execute($sql, array($user_id, $role_id));
    }
    /**
     * Get role id associated with role_name (so role_names better be unique)
     *
     * @param string $role_name to use to look up a role_id
     * @return string  role_id corresponding to the rolename.
     */
    function getRoleId($role_name)
    {
        $db = $this->db;
        $sql = "SELECT R.ROLE_ID AS ROLE_ID FROM ROLE R WHERE R.NAME = ? ";
        $result = $db->execute($sql, array($role_name));
        if(!$row = $db->fetchArray($result) ) {
            return -1;
        }
        return $row['ROLE_ID'];
    }
    /**
     * Return the role_id and role_name row corresponding to a given rolename
     * @param string $rolename
     * @return array row in role table corresponding to $rolenames
     */
    function getRole($rolename)
    {
        $db = $this->db;
        $sql = "SELECT * FROM ROLE WHERE UPPER(NAME) = UPPER(?) " .
            $db->limitOffset(1);
        $result = $db->execute($sql, array($rolename));
        if(!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }
    /**
     *  Add a rolename to the database using provided string
     *
     *  @param string $role_name  the rolename to be added
     */
    function addRole($role_name)
    {
        $sql = "INSERT INTO ROLE (NAME) VALUES (?)";
        $this->db->execute($sql, array($role_name));
    }
    /**
     *  Add an allowed activity to an existing role
     *
     *  @param string $role_id  the role id of the role to add the activity to
     *  @param string $activity_id the id of the acitivity to add
     */
    function addActivityRole($role_id, $activity_id)
    {
        $sql = "INSERT INTO ROLE_ACTIVITY VALUES (?, ?)";
        $this->db->execute($sql, array($role_id, $activity_id));
    }
    /**
     *  Delete a role by its roleid
     *
     *  @param string $role_id - the roleid of the role to delete
     */
    function deleteRole($role_id)
    {
        $sql = "DELETE FROM ROLE_ACTIVITY WHERE ROLE_ID=?";
        $this->db->execute($sql, array($role_id));
        $sql = "DELETE FROM ROLE WHERE ROLE_ID=?";
        $this->db->execute($sql, array($role_id));
    }
    /**
     *  Remove an allowed activity from a role
     *
     *  @param string $role_id  the roleid of the role to be modified
     *  @param string $activity_id  the activityid of the activity to remove
     */
    function deleteActivityRole($role_id, $activity_id)
    {
        $sql = "DELETE FROM ROLE_ACTIVITY WHERE ROLE_ID=? AND ACTIVITY_ID=?";
        $this->db->execute($sql, array($role_id, $activity_id));
    }
}
 ?>
