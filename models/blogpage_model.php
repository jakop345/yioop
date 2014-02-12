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

/** IndexShards used to store feed indexes*/
require_once BASE_DIR."/lib/index_shard.php";

/** For text manipulation of feeds*/
require_once BASE_DIR."/lib/phrase_parser.php";
/**
 * Used to manage data related to video, news, and other search sources
 * Also, used to manage data about available subsearches seen in SearchView
 *
 * @author Mallika Perepa (Creator), Chris Pollett (rewrite)
 * @package seek_quarry
 * @subpackage model
 */
class BlogpageModel extends Model
{

    /**
     * Just calls the parent class constructor
     */
    function __construct()
    {
        parent::__construct();
    }


    /**
     *  Delete the feed item of a blog from the database using provided string
     *  @param string $guid guid is the unique id for the feeditem
     */
    function deleteFeed($guid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT TITLE FROM FEED_ITEM WHERE GUID = '$guid'";
            $result = $this->db->execute($sql);
            $result1=  $this->db->fetchArray($result);
            $feed_temp = $result1["TITLE"];
        $sql = "DELETE FROM FEED_ITEM WHERE GUID = '$guid'";
        $this->db->execute($sql);
        $sql = "DELETE FROM ACCESS WHERE NAME = '$feed_temp'";
        $this->db->execute($sql);
    }

    /**
     *  Search for a blog from the database.
     *  @param string $title title of the blog name to be searching for
     *  @param string $user id of a user
     *  @param string $groupsIds id of groups
     *  @param bool $is_admin if the user is the admin of the blog or not
     *  @return array $blogs list of all the blogs matched with search title
     */

    function searchBlog($title, $user, $groups_Ids, $is_admin)
    {
        $this->db->selectDB(DB_NAME);
        if($is_admin === true){
            $sql = "SELECT TIMESTAMP,NAME,TYPE FROM MEDIA_SOURCE WHERE NAME
                IN (SELECT NAME FROM ACCESS WHERE NAME LIKE '%$title%')";
        }else{
         $sql = "SELECT TIMESTAMP,NAME,TYPE FROM MEDIA_SOURCE WHERE NAME
            IN (SELECT NAME FROM ACCESS
            WHERE NAME LIKE '%$title%' AND ID = '$user' AND TYPE = 'user')";
        }
        $result = $this->db->execute($sql);
        $i = 0;
        while($blogs[$i] = $this->db->fetchArray($result)) {
            $blogs[$i]['EDITABLE'] = true;
            $i++;
        }
        if($is_admin === false){
            $sql = "SELECT TIMESTAMP,NAME,TYPE from MEDIA_SOURCE WHERE NAME
                IN (SELECT NAME FROM ACCESS
                WHERE NAME LIKE '%$title%' AND ID != '$user'
                AND ID IN ($groups_Ids));";
            $result = $this->db->execute($sql);
            while($blogs[$i] = $this->db->fetchArray($result)) {
                $i++;
            }
        }
        unset($blogs[$i]); //last one will be null
        return $blogs;
    }

    /**
     *  Look for recent five blogs from the database
     *  @param string $user user who created the blogs
     *  @param int $group_ids id's of the groups
     *  @param bool $is_admin value to see if user is the admin of a blog or not
     *  @param int $limit number of recent blogs to be displayed
     *  @return array $blogs lst of 5 most recently update blogs
     */
    function recentBlog($user, $group_ids, $is_admin, $limit = 5)
    {
        $this->db->selectDB(DB_NAME);
        if($is_admin === true){
            $sql = "SELECT TIMESTAMP,NAME,TYPE FROM MEDIA_SOURCE WHERE NAME
                IN (SELECT NAME FROM ACCESS) AND TYPE = 'blog'
                ORDER BY TIMESTAMP DESC LIMIT $limit ";
         }else {
            $sql = "SELECT TIMESTAMP,NAME,TYPE FROM MEDIA_SOURCE WHERE NAME
                IN (SELECT NAME FROM ACCESS WHERE ID = '$user'
                AND TYPE = 'user') AND TYPE = 'blog'
                ORDER BY TIMESTAMP DESC LIMIT $limit ";
        }
        $result = $this->db->execute($sql);
        $i = 0;
        while($blogs[$i] = $this->db->fetchArray($result)) {
            $blognames[$i] = $blogs[$i]['NAME'];
            $blogs[$i]['EDITABLE'] = true;
            $i++;
        }
        if($is_admin === false) {
            $group_string = "'".implode ("','", $group_ids)."'";
            $sql = "SELECT TIMESTAMP,NAME,TYPE FROM MEDIA_SOURCE WHERE NAME
                IN (SELECT NAME FROM ACCESS  WHERE TYPE = 'group' AND 
                ID IN ($group_string)) AND TYPE = 'blog'
                ORDER BY TIMESTAMP DESC LIMIT $limit ";
                $result = $this->db->execute($sql); 
            while($blogs[$i] = $this->db->fetchArray($result)) {
                if(!empty($blognames) && 
                    in_array($blogs[$i]['NAME'], $blognames)){
                    unset($blogs[$i]);
                }
                $i++;
            }
        }
        unset($blogs[$i]); //last one will be null
        return $blogs;
    }

    /**
     *  Get the users list to transfer the admin rights
     *  @param string $blog is the title of the blog
     *  @return array $users list of users associated with the blog
     */
    function getBlogUsers($blog)
    {
        $this->db->selectDB(DB_NAME);
        $blog_name = $blog["NAME"];
        $sql = "SELECT ID FROM ACCESS WHERE TYPE = 'group'
            AND NAME = '$blog_name'";
            $result = $this->db->execute($sql);
        $i = 0;
        $k = 0;
        $result = $this->db->execute($sql);
        $users = array();
        $dup_users = array();
        while($groups_row[$i] = $this->db->fetchArray($result)) {
            $group_id = $groups_row[$i]['ID'];
            $group_id = $this->db->escapeString($group_id);
            $sql = "SELECT UG.USER_ID, U.USER_NAME" .
                " FROM USER_GROUP UG, USER U, GROUPS G".
                " WHERE UG.GROUP_ID = '$group_id' 
                AND UG.USER_ID = U.USER_ID AND" .
                " G.GROUP_ID = UG.GROUP_ID";
            $user = $this->db->execute($sql);
            $j = 0;
            while($users_row[$j] = $this->db->fetchArray($user)) {
                $user_name = $users_row[$j]['USER_NAME'];
                $user_id = $users_row[$j]['USER_ID'];
                if(!in_array($user_name, $dup_users)){
                    $users[$k]['USER_NAME'] = $user_name;
                    $users[$k]['USER_ID'] = $user_id;
                    $k++;
                    array_push($dup_users, $user_name);
                }
                $j++;
            }
            $i++;
        }
        unset($users[$k]); //last one will be null
        return $users;
    }

    /**
     *  Update the users list after the transfer the admin rights has been done
     *  @param string $blog_name is the title of the blog
     *  @param string $select_user is the new admin of the blog
     */
    function updateBlogUsers($select_user, $blog_name)
    {
       $this->db->selectDB(DB_NAME);
       $sql = "UPDATE ACCESS SET ID = '$select_user' 
            WHERE TYPE = 'user' AND NAME = '$blog_name'";
       $result = $this->db->execute($sql);
    }

    /**
     *  Retrieve the feed items of a blog
     *  @param string $title title of the blog 
     *  @param int $user id of the user
     *  @return array $blogs returns feeds of a blog based on title and user id.
     */
    function getFeed($title, $user)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT guid, title, description FROM FEED_ITEM
            WHERE source_name='".
            $this->db->escapeString($title)."'";
            $result = $this->db->execute($sql);
        $i = 0;
        while($blogs[$i] = $this->db->fetchArray($result)) {
             $sql = "SELECT ID FROM ACCESS WHERE NAME = '".
                $this->db->escapeString($blogs[$i]['TITLE'])."'";
                $feed_result = $this->db->execute($sql);
            if($row = $this->db->fetchArray($feed_result) ) {
                if ($row['ID'] == $user || $user == 1){
                    $blogs[$i]['IS_OWNER'] = true; 
                } else { $blogs[$i]['IS_OWNER'] = false;}
            }
            $i++;
        }
        unset($blogs[$i]); //last one will be null
        return $blogs;
    }

    /**
     *  Retrieve the feed items descrption related to a blog
     *  @param string $guid guid of the description
     *  @return array $blogs a particular feed item to edit the details
     */
    function getFeedByGUID($guid)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT guid,title,description FROM FEED_ITEM WHERE guid = '".
            $this->db->escapeString($guid)."'";
        $result = $this->db->execute($sql);
        $i = 0;
        while($blogs[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($blogs[$i]); //last one will be null
        return $blogs;
    }



    /**
     *  Update the feeditem description after performing edit on feeditem
     *  @param string $description description of the feeditem
     *  @param string $guid guid of the feeditem
     *  @param $title title of the feed item
     */
    function updateFeed($guid, $title, $description)
    {
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT TITLE FROM FEED_ITEM WHERE GUID = '$guid'";
        $result=$this->db->execute($sql);
            $temp_title =  $this->db->fetchArray($result);
            $old_title = $temp_title["TITLE"];
        $sql = "UPDATE FEED_ITEM SET TITLE = '".
            $this->db->escapeString($title)."',
            DESCRIPTION = '".
                $this->db->escapeString($description)."'
            WHERE guid ='$guid'";
        $result=$this->db->execute($sql);
        $sql = "UPDATE ACCESS SET NAME = '".
            $this->db->escapeString($title)."'
            WHERE NAME = '$old_title'";
        $result=$this->db->execute($sql);
    }

    /**
     *  add a feeditem to a blog
     *  @param string $description description of the feeditem
     *  @param string $title title of the blog
     *  @param id $user id of the user
     *  @param string $title_entry title of the feed item
     */
    function addEntry($timestamp, $title_entry, $description, $title, $user)
    {
        $this->db->selectDB(DB_NAME);
        $timestampe = time();
        $sql = "SELECT NAME FROM MEDIA_SOURCE WHERE timestamp = '$timestamp'";
        $result = $this->db->execute($sql);
        $row = $this->db->fetchArray($result);
        $title = $row['NAME'];
        $sql = "INSERT INTO ACCESS(NAME,ID, TYPE) VALUES ('".
            $this->db->escapeString($title_entry)."','".
            $user."','user')";
        $this->db->execute($sql);
        $guid = uniqid();
        $sql = "INSERT INTO FEED_ITEM(GUID,TITLE,DESCRIPTION,
            PUBDATE,SOURCE_NAME) VALUES ('$guid','".
            $this->db->escapeString($title_entry)."','".
            $this->db->escapeString($description)."','".
            $timestampe."','".
            $this->db->escapeString($title)."')";
        $this->db->execute($sql);
    }

    /**
     * create a page and stores the file with .thtml extension 
     * in the user's work directory.
     * @param string $title - title of the page
     * @param string $description description of the page
     * @param string $source_type whether it is a blog or a page
     * @param string $language language of the page
     * @param int $user id of the user
     * @param int $select_group id of the group
     * @return bool file input/output result
     */
    function addPage($title, $description, $source_type, $language, $user,
        $select_group)
    {
        $this->db->selectDB(DB_NAME);
        $timestamp = time();

        $sql = "INSERT INTO MEDIA_SOURCE(TIMESTAMP, NAME, TYPE,
                    LANGUAGE) VALUES ($timestamp,'".
                $this->db->escapeString($title)."','".
                $this->db->escapeString($source_type)."','".
                $this->db->escapeString($language)."')";
        $this->db->execute($sql);

        $sql = "INSERT INTO ACCESS (NAME, ID,TYPE) VALUES ('".
                $this->db->escapeString($title)."','".
                $user."','user')";
        $this->db->execute($sql);

        $sql = "INSERT INTO ACCESS( NAME, ID, TYPE) VALUES ('".
                $this->db->escapeString($title)."','".
                $select_group ."', 'group')";
        $this->db->execute($sql);

        $n = array();
        $n[] = "title=$title";
        $n[] = "";
        $n[] = "description=$description";
        $n[] = "END_HEAD_VARS";
        $n[] = "<p>$description</p>";
        $out = implode("\n", $n);
        $path = LOCALE_DIR."/".DEFAULT_LOCALE."/pages/$title.thtml";
        if(file_put_contents($path, $out) !== false) {
            return true;
        }
        return false;
    }
    
    /**
     * Checks whether a page is accessible to a user
     * @param int $user_id id of the logged in user
     * @param string $title title of the page
     * @return bool returns true if the page is accessible to the user or not
     */
    function isPageAccessible($user_id, $title)
    {
        if($user_id == ROOT_ID){
            return true;
        }
        if($user_id == $_SERVER['REMOTE_ADDR']) {
            $user_id = PUBLIC_USER_ID;
        }
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT USER_ID FROM USER_GROUP WHERE USER_ID ='$user_id'
            AND GROUP_ID IN (SELECT ID FROM ACCESS WHERE NAME = '".
            $this->db->escapeString($title)."' AND TYPE = 'group')";
        if(($result = $this->db->execute($sql)) &&
            ($row = $this->db->fetchArray($result))) {
            return true;
        }
        $num_rows = 0;
        return false;
    }
}
?>

