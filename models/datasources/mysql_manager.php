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
 * @subpackage datasource_manager
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Loads base datasource class if necessary
 */
require_once BASE_DIR."/models/datasources/pdo_manager.php";


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
class MysqlManager extends PdoManager
{
    /** Used when to quote column names of db names that contain a
     *  a keyword or special character
     *  @var string
     */
    var $special_quote = "`";

    /** {@inheritdoc} */
    function connect($db_host = DB_HOST, $db_user = DB_USER,
        $db_password = DB_PASSWORD, $db_name = DB_NAME)
    {
        $host_parts = explode(":", $db_host);
        $db_port_string = "";
        if(isset($host_parts[1])) {
            $db_host = $host_parts[0];
            $db_port_string = ";port=".$host_parts[1];
        }
        $db_name_string = "";
        if($db_name != "") {
            $db_name_string = ";dbname=".$db_name;
        }
        $this->pdo = new PDO("mysql:host={$db_host}".
            $db_port_string.$db_name_string,
            $db_user, $db_password);
        return $this->pdo;
    }
}

?>
