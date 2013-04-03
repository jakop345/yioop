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
 * Pdo DatasourceManager
 *
 * This is concrete class, implementing
 * the abstract class DatasourceManager
 * for any PDO accessible DBMS. Method explanations
 * are from the parent class.

 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage datasource_manager
 */
class PdoManager extends DatasourceManager
{
    /**
     *  Used to hold the PDO database object
     *  @var resource
     */
    var $pdo = NULL;

    /**
     * The number of rows affected by the last exec
     * @var int
     */
    var $num_affected = 0;

    /** {@inheritdoc} */
    function __construct()
    {
        parent::__construct();
    }

    /** {@inheritdoc} */
    function connect($db_host = DB_HOST, $db_user = DB_USER,
        $db_password = DB_PASSWORD)
    {
        $this->pdo = new PDO($db_host, $db_user, $db_password);
        return $this->pdo;
    }

    /** {@inheritdoc} */
    function selectDB($db_name)
    {
        /* In this case $db_host in connect would also specify the database
           so do nothing
         */
        return true;
    }

    /** {@inheritdoc} */
    function disconnect()
    {
        unset($this->pdo);
        $this->pdo = NULL;
    }

    /** {@inheritdoc} */
    function exec($sql)
    {
        if(stristr($sql, "SELECT ")) {
            $result = $this->pdo->query($sql);
            $this->num_affected = 0;
        } else {
            $this->num_affected = $this->pdo->exec($sql);
            $result = $this->num_affected + 1;
        }
        return $result;
    }

    /** {@inheritdoc} */
    function affectedRows()
    {
        return $this->num_affected;
    }


    /** {@inheritdoc} */
    function insertID()
    {
        return $this->pdo->lastInsertId();
    }

    /** {@inheritdoc} */
    function fetchArray($result)
    {
        $row = $result->fetch(PDO::FETCH_ASSOC);

        return $row;
    }


    /** {@inheritdoc} */
    function escapeString($str)
    {
        return substr($this->pdo->quote($str), 1, -1);
        /*
            pdo->quote adds quotes around string rather than
            just escape. As existing code then adds an additional
            pair of quotes we need to strip inner quotes
        */
    }


}

?>
