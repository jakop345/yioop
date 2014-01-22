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

/** Loads the base class */
require_once BASE_DIR."/models/model.php";

/** Used for the crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/**
 * This class is used to handle
 * database statements related to User Administration
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class UserModel extends Model
{
    /**
     *
     */
    var $search_table_column_map = array("first"=>"FIRST_NAME",
        "last" => "LAST_NAME", "user" => "USER_NAME", "email"=>"EMAIL",
        "status"=>"STATUS");

    /**
     * Just calls the parent class constructor
     */
    function __construct()
    {
        parent::__construct();
    }


    /**
     *  Get a list of admin activities that a user is allowed to perform.
     *  This includes their name and their associated method.
     *
     *  @param string $user_id  id of user to get activities fors
     */
    function getUserActivities($user_id)
    {
        $this->db->selectDB(DB_NAME);

        $user_id = $this->db->escapeString($user_id);

        $activities = array();
        $status = $this->getUserStatus($user_id);
        if(!$status || in_array($status, array(BANNED_STATUS,
            INACTIVE_STATUS))) {
            return array();
        }
        $locale_tag = getLocaleTag();

        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = '$locale_tag' LIMIT 1";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];

        $sql = "SELECT UR.ROLE_ID AS ROLE_ID, RA.ACTIVITY_ID AS ACTIVITY_ID, ".
            "T.TRANSLATION_ID AS TRANSLATION_ID, A.METHOD_NAME AS METHOD_NAME,".
            " T.IDENTIFIER_STRING AS IDENTIFIER_STRING FROM ACTIVITY A, ".
            " USER_ROLE UR, ROLE_ACTIVITY RA, TRANSLATION T ".
            "WHERE UR.USER_ID = '$user_id' ".
            "AND UR.ROLE_ID=RA.ROLE_ID AND T.TRANSLATION_ID=A.TRANSLATION_ID ".
            "AND RA.ACTIVITY_ID = A.ACTIVITY_ID ORDER BY A.ACTIVITY_ID ASC";

        $result = $this->db->execute($sql);
        $i = 0;
        while($activities[$i] = $this->db->fetchArray($result)) {

            $id = $activities[$i]['TRANSLATION_ID'];

            $sub_sql = "SELECT TRANSLATION AS ACTIVITY_NAME ".
                "FROM TRANSLATION_LOCALE ".
                "WHERE TRANSLATION_ID=$id AND ".
                "LOCALE_ID=$locale_id LIMIT 1";
                // maybe do left join at some point

            $result_sub =  $this->db->execute($sub_sql);
            $translate = $this->db->fetchArray($result_sub);

            if($translate) {
                $activities[$i]['ACTIVITY_NAME'] = $translate['ACTIVITY_NAME'];
            }
            if(!isset($activities[$i]['ACTIVITY_NAME']) ||
                $activities[$i]['ACTIVITY_NAME'] == "") {
                $activities[$i]['ACTIVITY_NAME'] = $this->translateDb(
                    $activities[$i]['IDENTIFIER_STRING'], DEFAULT_LOCALE);
            }
            $i++;
        }
        unset($activities[$i]); //last one will be null

        return $activities;

    }

    /**
     * Returns $_SESSION variable of given user from the last time
     * logged in.
     *
     * @param int $user_id id of user to get session for
     * @return array user's session data
     */
    function getUserSession($user_id)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "SELECT SESSION FROM USER_SESSION ".
            "WHERE USER_ID = '$user_id' LIMIT 1";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        if(isset($row["SESSION"])) {
            return unserialize($row["SESSION"]);
        }
        return NULL;
    }

    /**
     * Stores into DB the $session associative array of given user
     *
     * @param int $user_id id of user to store session for
     * @param array $session session data for the given user
     */
    function setUserSession($user_id, $session)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "DELETE FROM USER_SESSION ".
            "WHERE USER_ID = '$user_id'";
        $this->db->execute($sql);
        $session_string = serialize($session);
        $sql = "INSERT INTO USER_SESSION ".
            "VALUES ('$user_id', '$session_string')";
        $this->db->execute($sql);
    }

    /**
     * Gets all the roles associated with a user id
     *
     * @param string $user_id  the user_id to get roles of
     * @return array of role_ids and their names
     */
    function getUserRoles($user_id)
    {
        $this->db->selectDB(DB_NAME);

        $user_id = $this->db->escapeString($user_id);

        $roles = array();
        $locale_tag = getLocaleTag();

        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = '$locale_tag' LIMIT 1";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];


        $sql = "SELECT UR.ROLE_ID AS ROLE_ID, R.NAME AS ROLE_NAME ".
            " FROM  USER_ROLE UR, ROLE R WHERE UR.USER_ID = '$user_id' ".
            " AND R.ROLE_ID = UR.ROLE_ID";

        $result = $this->db->execute($sql);
        $i = 0;
        while($roles[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($roles[$i]); //last one will be null

        return $roles;
    }

    /**
     *  Returns an array of all user_names
     *
     *  @return array a list of usernames
     */
    function getUserList()
    {
        $this->db->selectDB(DB_NAME);

        $sql = "SELECT USER_NAME FROM USER ORDER BY USER_NAME ASC";
        $result = $this->db->execute($sql);
        $usernames = array();
        while($row = $this->db->fetchArray($result)) {
            $usernames[] = $row['USER_NAME'];
        }
        return $usernames;
    }

    /**
     *  Returns an array of all user_names and user_ids
     *
     *  @return array a list of user
     */
    function getUserIdUsernameList()
    {
        $this->db->selectDB(DB_NAME);
         $sql = "SELECT U.USER_ID AS USER_ID, U.USER_NAME AS USER_NAME ".
            " FROM USER U";
        $result = $this->db->execute($sql);
        $i = 0;
        while($users[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($users[$i]); //last one will be null
        return $users;
    }

    /**
     *
     */
    function getUsers($limit=0, $num=100, $search_array = array())
    {
        $limit = "LIMIT $limit, $num";
        list($where, $order_by) = 
            $this->searchArrayToWhereOrderClauses($search_array);
        $sql = "SELECT USER_NAME, FIRST_NAME, LAST_NAME,
            EMAIL, STATUS FROM USER $where $order_by $limit";
        $result = $this->db->execute($sql);
        $i = 0;
        while($users[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($users[$i]); //last one will be null
        return $users;
    }


    /**
     * Returns the number of users in the user table
     *
     * @return int number of users
     */
    function getUserCount($search_array = array())
    {
        $this->db->selectDB(DB_NAME);
        list($where, $order_by) = 
            $this->searchArrayToWhereOrderClauses($search_array);
        $sql = "SELECT COUNT(*) AS NUM FROM USER $where";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        return $row['NUM'];
    }

    /**
     * get a username by user_id
     *
     * @param string $user_id id of the user
     */
    function getUsername($user_id)
    {
        $user_id = $this->db->escapeString($user_id);
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT USER_NAME FROM USER WHERE USER_ID=$user_id;";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        return $row['USER_NAME'];
    }

    /**
     * get a status of user by user_id
     *
     * @param string $user_id id of the user
     */
    function getUserStatus($user_id)
    {
        $user_id = $this->db->escapeString($user_id);
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT STATUS FROM USER WHERE USER_ID=$user_id;";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        return $row['STATUS'];
    }

    /**
     *
     * @param string $username
     */
    function getUser($username)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT * FROM USER WHERE 
         UPPER(USER_NAME) = UPPER('$username') LIMIT 1";
        $result = $this->db->execute($sql);
        if(!$result) {
            return false;
        }
        $row = $this->db->fetchArray($result);
        return $row;
    }

    /**
     *
     * @param string $email
     * @param string $creation_time
     */
    function getUserByEmailTime($email, $creation_time)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT * FROM USER WHERE UPPER(EMAIL) = UPPER('$email')
            AND CREATION_TIME='$creation_time' LIMIT 1";
        $result = $this->db->execute($sql);
        if(!$result) {
            return false;
        }
        $row = $this->db->fetchArray($result);
        return $row;
    }

    /**
     * get a status of user by user_id
     *
     * @param string $user_id id of the user
     */
    function updateUserStatus($user_id, $status)
    {
        $user_id = $this->db->escapeString($user_id);
        if(!in_array($status, array(ACTIVE_STATUS, INACTIVE_STATUS,
            BANNED_STATUS))) {
            return;
        }
        $this->db->selectDB(DB_NAME);
        $sql = "UPDATE USER SET STATUS=$status WHERE USER_ID=$user_id";
        $this->db->execute($sql);
    }

    /**
     * Add a user with a given username and password to the list of users
     * that can login to the admin panel
     *
     * @param string $username  the username of the user to be added
     * @param string $password  the password of the user to be added
     * @param string $firstname the firstname of the user to be added
     * @param string $lastname the lastname of the user to be added
     * @param string $email the email of the user to be added
     * @return bool whether the operation was successful
     */
    function addUser($username, $password, $firstname='', $lastname='',
        $email='', $status = ACTIVE_STATUS)
    {
        $this->db->selectDB(DB_NAME);
        $creation_time = microTimestamp();
        $sql = "INSERT INTO USER(FIRST_NAME, LAST_NAME, 
            USER_NAME, EMAIL, PASSWORD, STATUS, HASH, CREATION_TIME) VALUES ('".
            $this->db->escapeString($firstname)."', '" .
            $this->db->escapeString($lastname)."', '" .
            $this->db->escapeString($username)."', '" .
            $this->db->escapeString($email)."', '" .
            crawlCrypt($this->db->escapeString($password)) . "','" .
            $this->db->escapeString($status)."', '".
            crawlCrypt($username.AUTH_KEY.$creation_time) . "', '" .
            $creation_time . "') ";
        $result = $this->db->execute($sql);
        if(!$user_id = $this->getUserId($username)) {
            return false;
        }
        $user_id = $this->db->escapeString($user_id);
        $sql = "INSERT INTO USER_GROUP (USER_ID, GROUP_ID) VALUES('".
            $user_id."', '" . PUBLIC_GROUP_ID . "')";
        $result = $this->db->execute($sql);
        $sql = "INSERT INTO USER_ROLE  VALUES ('". $user_id."', 2) ";
        $result_id = $this->db->execute($sql);
        return true;
    }



    /**
     * Deletes a user by username from the list of users that can login to
     * the admin panel
     *
     * @param string $username  the login name of the user to delete
     */
    function deleteUser($username)
    {
        $this->db->selectDB(DB_NAME);
        $userid = $this->db->escapeString($this->getUserId($username));
        if($userid) {
            $sql = "DELETE FROM USER_ROLE WHERE USER_ID='".$userid."'";
            $result = $this->db->execute($sql);
            $sql = "DELETE FROM USER_GROUP WHERE USER_ID='".$userid."'";
            $result = $this->db->execute($sql);
            $sql = "DELETE FROM USER_SESSION WHERE USER_ID='".$userid."'";
            $result = $this->db->execute($sql);
        }
        $sql = "DELETE FROM USER WHERE USER_ID='".$userid."'";;
        $result = $this->db->execute($sql);
    }

    /**
     *
     */
    function updateUser($user)
    {
        $user_id = $user['USER_ID'];
        unset($user['USER_ID']);
        unset($user['USER_NAME']);
        $this->db->selectDB(DB_NAME);
        $sql = "UPDATE USER SET ";
        $comma ="";
        foreach($user as $field=>$value) {
            $sql .= "$comma $field='$value' ";
            $comma = ",";
        }
        $sql .= " WHERE USER_ID=$user_id";
        $this->db->execute($sql);
    }
    /**
     * Adds a role to a given user
     *
     * @param string $userid  the id of the user to add the role to
     * @param string $roleid  the id of the role to add
     */
    function addUserRole($userid, $roleid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "INSERT INTO USER_ROLE VALUES ('".
            $this->db->escapeString($userid)."', '".
            $this->db->escapeString($roleid)."' ) ";
        $result = $this->db->execute($sql);
    }


    /**
     * Deletes a role from a given user
     *
     * @param string $userid  the id of the user to delete the role from
     * @param string $roleid  the id of the role to delete
     */
    function deleteUserRole($userid, $roleid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "DELETE FROM USER_ROLE WHERE USER_ID='".
            $this->db->escapeString($userid)."' AND  ROLE_ID='".
            $this->db->escapeString($roleid)."'";
        $result = $this->db->execute($sql);
    }
}
?>
