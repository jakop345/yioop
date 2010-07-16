<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Loads base datasource class if necessary
 */
require_once "datasource_manager.php";


/**
 * SQLite3 DatasourceManager
 *
 * This is concrete class, implementing
 * the abstract class DatasourceManager 
 * for the Sqlite3 DBMS (file format not compatible with versions less than 3). 
 * Method explanations are from the parent class. 
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage datasource_manager
 */
class Sqlite3Manager extends DatasourceManager
{
    /**
     *  Stores  the current Sqlite3 DB object
     *  @var object
     */
    var $dbhandle;
    /**
     *  Filename of the Sqlite3 Database
     *  @var string
     */

    /** {@inheritdoc} */
    function __construct() 
    {
        parent::__construct();
        if(!file_exists(CRAWL_DIR."/data")) {
            mkdir(CRAWL_DIR."/data");
            chmod(CRAWL_DIR."/data", 0777);
        }
        $this->dbname = NULL;
    }

    /** 
     * For an Sqlite3 database no connection needs to be made so this 
     * method does nothing
     * {@inheritdoc}
     */
    function connect($db_url = DB_URL, $db_user = DB_USER, 
        $db_password = DB_PASSWORD)
    {
        return true;
    }

    /** {@inheritdoc} */
    function selectDB($db_name) 
    {
        if(strcmp($db_name, $this->dbname) == 0) {
            return $this->dbhandle;
        }

        $this->dbname = $db_name;
        $this->dbhandle = new SQLite3(CRAWL_DIR."/data/$db_name.db", 
            SQLITE3_OPEN_READWRITE |SQLITE3_OPEN_CREATE);
        return $this->dbhandle;
    }

    /** {@inheritdoc} */
    function disconnect() 
    {
        $this->dbhandle->close();
    }

    /** {@inheritdoc} */
    function exec($sql) 
    {
        $result = $this->dbhandle->query($sql);

        return $result;
    }

    /** {@inheritdoc} */
    function affectedRows() 
    {
        return $this->dbhandle->changes();
    }

    /** {@inheritdoc} */
    function insertID() 
    {
        return $this->dbhandle->lastInsertRowID();
    }

    /** {@inheritdoc} */
    function fetchArray($result) 
    {
        $row = $result->fetchArray(SQLITE3_ASSOC);

        return $row;
    }

    /** {@inheritdoc} */
    function escapeString($str) 
    {
        return $this->dbhandle->escapeString($str);
    }


}

?>
