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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage datasource_manager
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Loads base datasource class if necessary
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
     * Used to hold the PDO database object
     * @var resource
     */
    var $pdo = NULL;
    /**
     * The number of rows affected by the last exec
     * @var int
     */
    var $num_affected = 0;
    /**
     * If DBMS is one like postgres which lower cases table names that aren't
     * in quotes that this field has the name of the database;
     * otherwise, false.
     * @var mixed
     */
    var $to_upper_dbms;
    /**
     * {@inheritDoc}
     *
     * @param string $db_host the hostname of where the database is located
     *     (not used in all dbms's)
     * @param string $db_user the user to connect as
     * @param string $db_password the password of the user to connect as
     * @param string $db_name the name of the database on host we are
     * connecting to
     * @return mixed return false if not successful and some kind of
     *     connection object/identifier otherwise
     */
    function connect($db_host = DB_HOST, $db_user = DB_USER,
        $db_password = DB_PASSWORD, $db_name = DB_NAME)
    {
        try {
            $this->pdo = new PDO($db_host, $db_user, $db_password);
        } catch (PDOException $e) {
            $this->pdo = false;
            crawlLog('Connection failed: ' . $e->getMessage());
        }

        $this->to_upper_dbms = false;
        if(stristr($db_host, 'PGSQL')) {
            $this->to_upper_dbms = 'PGSQL';
        }
        return $this->pdo;
    }
    /** {@inheritDoc} */
    function disconnect()
    {
        unset($this->pdo);
        $this->pdo = NULL;
    }
    /**
     * {@inheritDoc}
     *
     * @param string $sql  SQL statement to execute
     * @param array $params bind_name => value values to interpolate into
     *      the $sql to be executes
     * @return mixed false if query fails, resource or true otherwise
     */
    function exec($sql, $params = array())
    {
        static $last_sql = NULL;
        static $statement = NULL;
        $is_select = strtoupper(substr(ltrim($sql), 0, 6)) == "SELECT";
        if($last_sql != $sql) {
            $statement = NULL;//garbage collect so don't sqlite lock
        }
        if($params) {
            if(!$statement) {
                $statement = $this->pdo->prepare($sql);
            }
            $result = $statement->execute($params);
            $this->num_affected = $statement->rowCount();
            if($result) {
                if($is_select) {
                    $result = $statement;
                } else {
                    $result = $this->num_affected;
                }
            }
        } else {
            if($is_select) {
                $result = $this->pdo->query($sql);
                $this->num_affected = 0;
            } else {
                $this->num_affected = $this->pdo->exec($sql);
                $result = $this->num_affected + 1;
            }
        }
        $last_sql = $sql;
        return $result;
    }
    /** {@inheritDoc} */
    function affectedRows()
    {
        return $this->num_affected;
    }
    /**
     * {@inheritDoc}
     *
     * @param string $table_name of table of last insert
     * @return string  the ID of the insert
     */
    function insertID($table_name = "")
    {
        if($table_name && $this->to_upper_dbms == "PGSQL") {
            $table_name .= "_ID_SEQ";
            $id = $this->pdo->lastInsertId($table_name);
            if(!$id) {
                //if sequence number somehow was renamed
                $id = $this->pdo->lastInsertId($table_name."1");
            }
            return $id;
        }
        return $this->pdo->lastInsertId();
    }
    /**
     * {@inheritDoc}
     *
     * @param resource $result   result set reference of a query
     * @return array the next row from the result set as an
     *      associative array in the form column_name => value
     */
    function fetchArray($result)
    {
        if(!$result) {
            return false;
        }
        $row = $result->fetch(PDO::FETCH_ASSOC);
        if(!$this->to_upper_dbms || !$row) {
            return $row;
        }
        $out_row = array();
        foreach($row as $field => $value) {
            $out_row[strtoupper($field)] = $value;
        }
        return $out_row;
    }
    /**
     * {@inheritDoc}
     *
     * @param string $str  string to escape
     * @return string a string which is safe to insert into the db
     */
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