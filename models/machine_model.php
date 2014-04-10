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
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Used to fetches web pages to get statuses of individual machines*/
require_once BASE_DIR."/lib/fetch_url.php";

/**
 * This is class is used to handle
 * db results related to Machine Administration
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class MachineModel extends Model
{

    /**
     *  Returns all the machine names stored in the DB
     *
     *  @return array machine names
     */
    function getMachineList()
    {
        $machines = array();

        $sql = "SELECT * FROM MACHINE ORDER BY NAME DESC";

        $result = $this->db->execute($sql);
        $i = 0;
        while($machines[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($machines[$i]); //last one will be null

        return $machines;

    }

    /**
     * Gets a range of the machine names stored in the DB
     *
     * @param int $limit
     * @param int $num
     * @return array
     */
    function getMachines($limit = 0, $num = 10)
    {
        $db = $this->db;
        $machines = array();
        $limit = $db->limitOffset($limit, $num);
        $sql = "SELECT * FROM MACHINE ORDER BY NAME DESC $limit";
        $result = $db->execute($sql);
        $i = 0;
        while($machines[$i] = $db->fetchArray($result)) {
            $i++;
        }
        unset($machines[$i]); //last one will be null
        return $machines;
    }

    /**
     * Returns the number of machines in the user table
     *
     * @param array $search_array
     * @return int number of roles
     */
    function getMachineCount()
    {
        $db = $this->db;
        $sql = "SELECT COUNT(*) AS NUM FROM MACHINE";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        return $row['NUM'];
    }

    /**
     *  Returns urls for all the queue_servers stored in the DB
     *
     *  @param string a crawl_time of a crawl to see the machines used in
     *      that crawl
     *  @return array machine names
     */
    function getQueueServerUrls($crawl_time = 0)
    {
        static $machines = array();
        $db = $this->db;
        if(isset($machines[$crawl_time])) {
            return $machines[$crawl_time];
        }
        $network_crawl_file = CRAWL_DIR."/cache/".self::network_base_name.
                    $crawl_time.".txt";
        if($crawl_time != 0 && file_exists($network_crawl_file)) {
            $info = unserialize(file_get_contents($network_crawl_file));
            if(isset($info["MACHINE_URLS"])) {
                $machines[$crawl_time] = $info["MACHINE_URLS"];
                return $info["MACHINE_URLS"];
            }
        }
        $sql = "SELECT URL FROM MACHINE WHERE HAS_QUEUE_SERVER > 0 ".
            "ORDER BY NAME DESC";

        $result = $db->execute($sql);
        $i = 0;

        $machines[$crawl_time] =array();
        while($row = $db->fetchArray($result)) {
            $machines[$crawl_time][$i] = $row["URL"];
            $i++;
        }
        unset($machines[$crawl_time][$i]); //last one will be null
        return $machines[$crawl_time];
    }

    /**
     *  Add a rolename to the database using provided string
     *
     *  @param string $name  the name of the machine to be added
     *  @param string $url the url of this machine
     *  @param boolean $has_queue_server - whether this machine is running a
     *      queue_server
     *  @param int $num_fetchers - how many managed fetchers are on this
     *      machine.
     *  @param string $parent - if this machine replicates some other machine
     *      then the name of the parent
     */
    function addMachine($name, $url, $has_queue_server, $num_fetchers,
        $parent = "")
    {
        $db = $this->db;
        $has_string = ($has_queue_server) ? $has_string = "1" :
            $has_string = "0";
        $sql = "INSERT INTO MACHINE VALUES (?, ?, ?, ?, ?)";
        $this->db->execute($sql, array($name, $url, $has_string, $num_fetchers,
            $parent));
    }

    /**
     *  Delete a machine by its name
     *
     *  @param string name - the name of the machine to delete
     */
    function deleteMachine($machine_name)
    {
        $sql = "DELETE FROM MACHINE WHERE NAME=?";
        $this->db->execute($sql, array($machine_name));

    }

    /**
     * Returns the statuses of machines in the machine table of their
     * fetchers and queue_server as well as the name and url's of these machines
     *
     * @return array  a list of machines, together with all their properties
     *  and the statuses of their fetchers and queue_servers
     */
    function getMachineStatuses()
    {
        $machines = $this->getMachineList();
        $num_machines = count($machines);
        $time = time();
        $session = md5($time . AUTH_KEY);
        for($i = 0; $i < $num_machines; $i++) {
            $machines[$i][CrawlConstants::URL] =
                $machines[$i]["URL"] ."?c=machine&a=statuses&time=$time".
                "&session=$session";
        }
        $statuses = FetchUrl::getPages($machines, true);
        for($i = 0; $i < $num_machines; $i++) {
            foreach($statuses as $status) {
                if($machines[$i][CrawlConstants::URL] ==
                    $status[CrawlConstants::URL]) {
                    $machines[$i]["STATUSES"] =
                        json_decode($status[CrawlConstants::PAGE], true);
                }
            }
        }
        $sql = "SELECT * FROM ACTIVE_FETCHER";
        $result = $this->db->execute($sql);
        if(!$result) return $machines;
        $active_fetchers = array();
        while($row = $this->db->fetchArray($result)) {
            for($i = 0; $i < $num_machines; $i++) {
                if($machines[$i]['NAME'] == $row['NAME']) {
                    if(!isset($machines[$i]["STATUSES"]["fetcher"][
                        $row['FETCHER_ID']])) {
                        $machines[$i]["STATUSES"]["fetcher"][
                        $row['FETCHER_ID']] = 0;
                    }
                }
            }
        }
        $name_server_statuses = CrawlDaemon::statuses();
        $machines['NAME_SERVER']['news_updater'] = 0;
        if(isset($name_server_statuses['news_updater'])) {
            $machines['NAME_SERVER']['news_updater'] = 1;
        }
        return $machines;
    }

    /**
     *  Get either a fetcher or queue_server log for a machine
     *
     *  @param string name  the name of the machine to get the log file for
     *  @param int $fetcher_num  if a fetcher, which instance on the machine
     *  @param bool whether the requested machine is a mirror of another machine
     *  @return string containing the last MachineController::LOG_LISTING_LEN
     *      bytes of the log record
     */
    function getLog($machine_name,
        $fetcher_num = NULL, $filter="", $is_mirror = false)
    {
        $time = time();
        $session = md5($time . AUTH_KEY);
        $news = ($machine_name == "news");
        if($news) {
            $row = array();
            $row["URL"] = NAME_SERVER;
        } else {
            $sql = "SELECT URL FROM MACHINE WHERE NAME='$machine_name'";

            $result = $this->db->execute($sql);
            $row = $this->db->fetchArray($result);
        }
        if($row) {
            $url = $row["URL"]. "?c=machine&a=log&time=$time".
                "&session=$session&f=$filter";
            if($fetcher_num !== NULL) {
                $url .= "&fetcher_num=$fetcher_num";
            }
            if($is_mirror) {
                $url .= "&mirror=true";
            }
            if($news) {
                $url .= "&news=true";
            }
            $log_data = urldecode(json_decode(FetchUrl::getPage($url)));
        } else {
            $log_data = "";
        }
        return $log_data;
    }

    /**
     * Used to start or stop a queue_server, fetcher, mirror instance on
     * a machine managed by the current one
     *
     * @param string $machine_name name of machine
     * @param bool whether the requested machine is a mirror of another machine
     *
     */
    function update($machine_name, $action, $fetcher_num = NULL,
        $is_mirror = false)
    {
        $db = $this->db;
        $value = ($action == "start") ? "true" : "false";
        $time = time();
        $session = md5($time . AUTH_KEY);
        $db->execute("BEGIN");
        $sql = "SELECT URL FROM MACHINE WHERE NAME=?";
        $result = $db->execute($sql, array($machine_name));
        $row = $db->fetchArray($result);
        if($row) {
            $url = $row["URL"]. "?c=machine&a=update&time=$time".
                "&session=$session";
            if($fetcher_num !== NULL) {
                $url .= "&fetcher[$fetcher_num]=$value";
                $sql = "DELETE FROM ACTIVE_FETCHER WHERE NAME=? AND
                    FETCHER_ID=?";
                $db->execute($sql, array($machine_name, $fetcher_num));
                if($action == "start") {
                    $sql = "INSERT INTO ACTIVE_FETCHER VALUES (?, ?)";
                }
                $db->execute($sql, array($machine_name, $fetcher_num));
            } else if($is_mirror) {
                $url .= "&mirror=$value";
            } else {
                $url .= "&queue_server=$value";
            }
            echo FetchUrl::getPage($url);
        }
        $db->execute("COMMIT");
    }

    /**
     * Used to restart any fetchers which the user turned on, but which
     * happened to have crashed. (Crashes are usually caused by CURL or
     * memory issues)
     */
    function restartCrashedFetchers()
    {
        $machines =  $this->getMachineStatuses();
        foreach($machines as $machine) {
            if(isset($machine["STATUSES"]["fetcher"])) {
                $fetchers = $machine["STATUSES"]["fetcher"];
                foreach($fetchers as $id => $status) {
                    if($status === 0) {
                        $this->update($machine["NAME"], "start", $id, false);
                    }
                }
            }
        }
    }
}

 ?>
