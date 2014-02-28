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
        $timestamp = time();
        $db = $this->db;

        $sql = "INSERT INTO MEDIA_SOURCE(TIMESTAMP, NAME, TYPE,
            LANGUAGE) VALUES (?, ?, ?, ?)";
        $db->execute($sql, array($timestamp, $title,
            $source_type, $language));

        $sql = "INSERT INTO ACCESS (NAME, ID,TYPE) VALUES (?, ?,'user')";
        $db->execute($sql, array($title, $user));

        $sql = "INSERT INTO ACCESS( NAME, ID, TYPE) VALUES (?, ?, 'group')";
        $db->execute($sql, array($title, $select_group));

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
        $sql = "SELECT USER_ID FROM USER_GROUP WHERE USER_ID =:user_id
            AND GROUP_ID IN (SELECT ID FROM ACCESS WHERE NAME=".
            ":title AND TYPE = 'group')";
        if(($result = $this->db->execute($sql,
            array(":user_id" => $user_id, ":title" => $title))) &&
            ($row = $this->db->fetchArray($result))) {
            return true;
        }
        $num_rows = 0;
        return false;
    }
}
?>
