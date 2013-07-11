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
 * @subpackage datasource_manager
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Loads base datasource class if necessary
 */
require_once "datasource_manager.php";


/**
 * Mysql DatasourceManager
 *
 * This is concrete class, implementing
 * the abstract class DatasourceManager
 * for the MySql DBMS. Method explanations
 * are from the parent class. Originally,
 * it was implemented using php mysql_ interface.
 * In July, 2013, it was rewritten to use 
 * mysqli_ interface as the former interface was
 * deprecated. This was a minimal rewrite and
 * does not yet use the more advanced features
 * of mysqli_
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage datasource_manager
 */
class MysqlManager extends DatasourceManager
{
    /** Used when to quote column names of db names that contain a
     *  a keyword or special character
     *  @var string
     */
    var $special_quote = "`";

    /**
     * Connection to DB opened by this MysqlManager instance
     * @var resource
     */
    var $conn;

    /** {@inheritdoc} */
    function __construct()
    {
        parent::__construct();
        $this->conn = NULL;
    }

    /** {@inheritdoc} */
    function connect($db_host = DB_HOST, $db_user = DB_USER,
        $db_password = DB_PASSWORD)
    {
        $this->conn = mysqli_connect($db_host, $db_user, $db_password);
        return $this->conn;
    }

    /** {@inheritdoc} */
    function selectDB($db_name)
    {
        return mysqli_select_db($this->conn, $db_name);
    }

    /** {@inheritdoc} */
    function disconnect()
    {
        mysqli_close($this->conn);
    }

    /** {@inheritdoc} */
    function exec($sql)
    {
        $result = mysqli_query($this->conn, $sql);

        return $result;
    }

    /** {@inheritdoc} */
    function affectedRows()
    {
        return mysqli_affected_rows($this->conn);
    }


    /** {@inheritdoc} */
    function insertID()
    {
        return mysqli_insert_id($this->conn);
    }

    /** {@inheritdoc} */
    function fetchArray($result)
    {
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);

        return $row;
    }


    /** {@inheritdoc} */
    function escapeString($str)
    {
        return mysqli_real_escape_string($this->conn, $str);
    }


}

?>
