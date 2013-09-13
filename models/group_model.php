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
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
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
* @author Mallika Perepa
* @package seek_quarry
* @subpackage model
*/

class GroupModel extends Model
{
    /**
    *  {@inheritdoc}
    */

       function __construct()
    {
        parent::__construct();
    }
    /**
    *  Get the users  (name, method, id) that a given group contains
    *
    *  @param string $group_id  the group_id to get users for
    */
    function getGroupUsers($group_id)
    {
        $this->db->selectDB(DB_NAME);
        $group_id = $this->db->escapeString($group_id);
        $users = array();
        $sql = "SELECT UG.USER_ID, U.USER_NAME, UG.GROUP_ID, G.CREATER_ID".
        " FROM USER_GROUPS UG, USER U, GROUPS G".
        " where UG.GROUP_ID = '$group_id' AND UG.USER_ID = U.USER_ID AND".
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
    *  Get the activities  (name, method, id) that a given group can perform
    *
    *  @param string $group_id  the group_id to get activities for
    */
       function getGroupActivities($group_id)
    {
        $this->db->selectDB(DB_NAME);
        $group_id = $this->db->escapeString($group_id);
        $activities= array();
        $locale_tag = getLocaleTag();
        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = '$locale_tag' LIMIT 1";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];
        $sql= "SELECT A.ACTIVITY_ID, T.TRANSLATION, G.GROUP_ID FROM ".
           "TRANSLATION_LOCALE T, ACTIVITY A, GROUP_PERMISSIONS G ".
           "where G.GROUP_ID = '$group_id' AND T.LOCALE_ID = '$locale_id' ".
            "AND A.ACTIVITY_ID = G.ACTIVITY_ID AND ".
             "A.TRANSLATION_ID = T.TRANSLATION_ID";
        $result = $this->db->execute($sql);
            $i = 0;
        while($activities[$i] = $this->db->fetchArray($result)) {
              $i++;
        }
          unset($activities[$i]); //last one will be null
          return $activities;
    }
    /**
    * Gets all the roles associated with a group id
    *
    * @param string $group_id  the group_id to get roles of
    * @return array of role_ids and their names
    */
    function getGroupRoles($group_id)
    {
        $this->db->selectDB(DB_NAME);

        $group_id = $this->db->escapeString($group_id);
        $roles = array();
        $locale_tag = getLocaleTag();
        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = '$locale_tag' LIMIT 1";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];
        $sql = "SELECT GR.GROUP_ID, GR.ROLE_ID, R.NAME AS ROLE_NAME ".
            "FROM GROUP_ROLES GR, ROLE R ".
            "where GR.GROUP_ID = '$group_id' and  GR.ROLE_ID= R.ROLE_ID ";
        $result = $this->db->execute($sql);
        $i = 0;
        while($roles[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($roles[$i]); //last one will be null
        return $roles;
    }
     function getLocaleTag()
    {
       return $this->locale_tag;
    }
    /**
    *  Add a groupname to the database using provided string
    *
    *  @param string $groupname  the groupname to be added
    */
    function addGroup($groupname, $userid)
    {
     $this->db->selectDB(DB_NAME);
     $date = new DateTime();
     $timestamp = $date->getTimestamp();
     $sql = "INSERT INTO GROUPS (GROUP_NAME,CREATER_ID,CREATED_TIME) VALUES ('".
         $this->db->escapeString($groupname)."', $userid, $timestamp);";
         $this->db->execute($sql);

     $this->db->selectDB(DB_NAME);
     $sql = "SELECT G.GROUP_ID AS GROUP_ID FROM ".
    " GROUPS G WHERE G.GROUP_NAME = '".$this->db->escapeString($groupname)."' ";
      $result = $this->db->execute($sql);
      if(!$row = $this->db->fetchArray($result) ) {
            $last_id = -1;
        }
     $last_id = $row['GROUP_ID'];
     $sql= "INSERT INTO USER_GROUPS (USER_ID,GROUP_ID) VALUES
               ($userid, $last_id)";
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
     "GROUPS G WHERE G.GROUP_NAME = '".$this->db->escapeString($groupname)."' ";
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
        $sql = "DELETE FROM GROUPS WHERE GROUP_ID='".
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
    *  Get a list of all groups which are created by the user_id. Group names
    *  are not localized since these are
    *  created by end user admins of the search engine
    *
    *  @return array an array of group_id, group_name pairs
    */
    function getGroupListbyUser($userid)
    {
        $this->db->selectDB(DB_NAME);
        $groups = array();
        $sql = "SELECT UG.GROUP_ID AS GROUP_ID, UG.USER_ID AS USER_ID,".
        " G.GROUP_NAME AS GROUP_NAME FROM USER_GROUPS UG, GROUPS G".
        " where USER_ID = $userid and UG.GROUP_ID = G.GROUP_ID";
        $result = $this->db->execute($sql);
        $i = 0;
        while($groups[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }
    /**
    *  Get a list of all groups which are created by the creater_id. Group names
    *  are not  localized since these are
    *  created by end user admins of the search engine
    *
    *  @return array an array of group_id, group_name pairs
    */
     function getGroupListbyCreater($userid)
    {
        $this->db->selectDB(DB_NAME);
        $groups = array();
        $sql = "SELECT G.GROUP_ID AS GROUP_ID, G.CREATER_ID AS USER_ID,".
        "G.GROUP_NAME AS GROUP_NAME ".
        " FROM GROUPS G where CREATER_ID = $userid";
        $result = $this->db->execute($sql);
        $i = 0;
        while($groups[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($groups[$i]); //last one will be null
        return $groups;
    }
    /**
    *  To update the CREATER_ID of a group
    *
    *  @param string $groupid  the group id  to transfer admin privileges
    *  @param string $userid the id of the user who becomes the admin of group
    */
    function updateUserGroup($userid, $group_id)
    {
      $this->db->selectDB(DB_NAME);
      $sql = "UPDATE GROUPS SET CREATER_ID= $userid WHERE GROUP_ID= $group_id;";
            $this->db->execute($sql);
    }
    /**
    *  Add an allowed user to an existing group
    *
    *  @param string $groupid  the group id of the group to add the user to
    *  @param string $userid the id of the user to add
    */
    function addUserGroup($groupid, $userid)
    {
        $this->db->selectDB(DB_NAME);
            $sql = "INSERT INTO USER_GROUPS VALUES ('".
            $this->db->escapeString($userid)."', '".
            $this->db->escapeString($groupid)."')";
        $this->db->execute($sql);
    }
    /**
    *  Delete a user by its userid
    *
    *  @param string $userid - the userid of the user to delete
    */
    function deleteUserGroup($groupid, $userid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM USER_GROUPS WHERE USER_ID='".
            $this->db->escapeString($userid)."' AND GROUP_ID='".
            $this->db->escapeString($groupid)."'";
        $this->db->execute($sql);
    }
    /**
    * Adds a role to a given user
    *
    * @param string $userid  the id of the user to add the role to
    * @param string $roleid  the id of the role to add
    */
    function addGroupRole($groupid, $roleid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "INSERT INTO GROUP_ROLES  VALUES ('".
            $this->db->escapeString($groupid)."', '".
            $this->db->escapeString($roleid)."' ) ";
        $result = $this->db->execute($sql);
    }
    /**
    * Deletes a role from a given user
    *
    * @param string $userid  the id of the user to delete the role from
    * @param string $roleid  the id of the role to delete
    */
    function deleteGroupRole($groupid, $roleid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM GROUP_ROLES WHERE GROUP_ID='".
            $this->db->escapeString($groupid)."' AND  ROLE_ID='".
            $this->db->escapeString($roleid)."'";
        $result = $this->db->execute($sql);
    }
    }
?>