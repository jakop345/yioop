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

/**
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class VisitorModel extends Model
{


    /**
     *  {@inheritdoc}
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     *
     *  @param string
     *  @return array 
     */
    function getVisitor($ip_address)
    {
        $this->db->selectDB(DB_NAME);
        $ip_address = $this->db->escapeString($ip_address);
        $sql = "SELECT * FROM VISITOR WHERE ADDRESS='$ip_address' LIMIT 1";
        $result = $this->db->execute($sql);
        if(!$result || !$row = $this->db->fetchArray($result))
        {
            return false;
        }
        $now = time();
        if($row['FORGET_AGE']>0 && $row["END_TIME"]-$now >$row['FORGET_AGE']) {
            $this->removeVisitor($ip_address);
            return false;
        }
        return $row;
    }

    /**
     *
     */
    function removeVisitor($ip_address)
    {
        $ip_address = $this->db->escapeString($ip_address);
        $sql = "DELETE FROM VISITOR WHERE ADDRESS='$ip_address'";
        $this->db->execute($sql);
    }

    /**
     *
     */
    function updateVisitor($ip_address, $page_name, $start_delay = 1,
        $forget_age = self::ONE_WEEK)
    {
        $ip_address = $this->db->escapeString($ip_address);
        $page_name = $this->db->escapeString($page_name);
        $start_delay = $this->db->escapeString($start_delay);
        $forget_age = $this->db->escapeString($forget_age);
        $visitor = $this->getVisitor($ip_address);
        if(!$visitor) {
            $end_time = time() + $start_delay;
            $sql = "INSERT INTO VISITOR VALUES ('$ip_address', '$page_name',
                '$end_time', '$start_delay', '$forget_age')";
            $this->db->execute($sql);
            return;
        }
        $delay = 2 * $visitor['DELAY'];
        $end_time = time() + $delay;
        $sql = "UPDATE VISITOR SET PAGE_NAME='$page_name', DELAY='$delay',
            END_TIME='$end_time', FORGET_AGE='$forget_age'
            WHERE ADDRESS='$ip_address'";
        $this->db->execute($sql);
    }
}

 ?>
