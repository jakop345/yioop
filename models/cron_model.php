<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads base model class if necessary*/
require_once BASE_DIR."/models/model.php";
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Used to fetches web pages to get statuses of individual machines*/
require_once BASE_DIR."/lib/fetch_url.php";

/**
 * Used to remember the last time the web app ran periodic activities
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class CronModel extends Model 
{


    /**
     *  {@inheritdoc}
     */
    function __construct() 
    {
        parent::__construct();
    }

    /**
     *  Returns the timestamp of last time cron run
     *
     *  @return int a Unix timestamp
     */
    function getCronTime()
    {
        $this->db->selectDB(DB_NAME);

        $result = @$this->db->execute("SELECT TIMESTAMP FROM CRON_TIME");
        if($result) {
            $row = $this->db->fetchArray($result);
        } else {
            sleep(3);
        }
        $timestamp = (isset($row['TIMESTAMP'])) ? $row['TIMESTAMP'] : 0;

        return $timestamp;

    }

    /**
     * Updates the Cron timestamp to the current time.
     */
    function updateCronTime()
    {
        $this->db->selectDB(DB_NAME);
        $this->db->execute("DELETE FROM CRON_TIME");
        $this->db->execute("INSERT INTO CRON_TIME VALUES ('".time()."')");
    }

}

 ?>
