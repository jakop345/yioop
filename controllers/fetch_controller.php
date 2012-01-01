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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Load base controller class if needed */
require_once BASE_DIR."/controllers/controller.php";
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * This class handles data coming to a queue_server from a fetcher
 * Basically, it receives the data from the fetcher and saves it into
 * various files for later processing by the queue server.
 * This class can also be used by a fetcher to get status information.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class FetchController extends Controller implements CrawlConstants
{ 
    /**
     * No models used by this controller
     * @var array
     */
    var $models = array();
    /**
     * Load FetchView to return results to fetcher
     * @var array
     */
    var $views = array("fetch");
    /**
     * These are the activities supported by this controller
     * @var array
     */
    var $activities = array("schedule", "update", "crawlTime");

    /**
     * Checks that the request seems to be coming from a legitimate fetcher then
     * determines which activity the fetcher is requesting and calls that
     * activity for processing.
     *
     */
    function processRequest() 
    {
        $data = array();

        /* do a quick test to see if this is a request seems like 
           from a legitimate machine
         */
        if(!$this->checkRequest()) {return; }

        $activity = $_REQUEST['a'];
        $robot_table_name = CRAWL_DIR."/".self::robot_table_name;
        $robot_table = array();
        if(file_exists($robot_table_name)) {
            $robot_table = unserialize(file_get_contents($robot_table_name));
        }
        if(isset($_REQUEST['robot_instance'])) {
            $robot_table[$this->clean($_REQUEST['robot_instance'], "string")] = 
                array($_SERVER['REMOTE_ADDR'], $_REQUEST['machine_uri'],
                time());
            file_put_contents($robot_table_name, serialize($robot_table));
        }
        if(in_array($activity, $this->activities)) {$this->$activity();}
    }

    /**
     * Checks if there is a schedule of sites to crawl available and
     * if so present it to the requesting fetcher, and then delete it.
     */
    function schedule()
    {
        $view = "fetch";

        if(isset($_REQUEST['crawl_time'])) {;
            $crawl_time = $this->clean($_REQUEST['crawl_time'], 'int');
        } else {
            $crawl_time = "";
        }
        // set up query
        $data = array();
        $schedule_filename = CRAWL_DIR."/schedules/".
            self::schedule_name."$crawl_time.txt";

        if(file_exists($schedule_filename)) {
            $data['MESSAGE'] = file_get_contents($schedule_filename);
            unlink($schedule_filename);
        } else {
            $info = array();
            $info[self::STATUS] = self::NO_DATA_STATE;
            $data['MESSAGE'] = base64_encode(serialize($info))."\n";
        }

        $this->displayView($view, $data);
    }

    /**
     * Processes Robot, To Crawl, and Index data sent from a fetcher
     * Acknowledge to the fetcher if this data was received okay.
     */
    function update()
    {
        $view = "fetch";
        $address = str_replace(".", "-", $_SERVER['REMOTE_ADDR']); 
        $address = str_replace(":", "_", $address);
        $time = time();
        $day = floor($time/86400);

        $info_flag = false;
        if(isset($_REQUEST['robot_data'])) {
            $this->addScheduleToScheduleDirectory(self::robot_data_base_name, 
                $_REQUEST['robot_data']);
            $info_flag = true;
        }
        if(isset($_REQUEST['schedule_data'])) {
            $this->addScheduleToScheduleDirectory(self::schedule_data_base_name,
                $_REQUEST['schedule_data']);
            $info_flag = true;
        }
        if(isset($_REQUEST['index_data'])) {
            $this->addScheduleToScheduleDirectory(self::index_data_base_name,
                $_REQUEST['index_data']);
            $info_flag = true;
        }

        if($info_flag == true) {
            $info =array();
            $info[self::MEMORY_USAGE] = memory_get_peak_usage();
            $info[self::STATUS] = self::CONTINUE_STATE;
            if(file_exists(CRAWL_DIR."/schedules/crawl_status.txt")) {
                $change =false;
                $crawl_status = unserialize(
                    file_get_contents(CRAWL_DIR."/schedules/crawl_status.txt"));
                if(isset($_REQUEST['fetcher_peak_memory'])) {
                    if(!isset($crawl_status['FETCHER_MEMORY']) ||
                        $_REQUEST['fetcher_peak_memory'] > 
                        $crawl_status['FETCHER_PEAK_MEMORY']
                    ) {
                        $crawl_status['FETCHER_PEAK_MEMORY'] = 
                            $_REQUEST['fetcher_peak_memory'];
                        $change = true;
                    }
                    
                }
                if(!isset($crawl_status['WEBAPP_PEAK_MEMORY']) ||
                    $info[self::MEMORY_USAGE] > 
                    $crawl_status['WEBAPP_PEAK_MEMORY']) {
                    $crawl_status['WEBAPP_PEAK_MEMORY'] = 
                        $info[self::MEMORY_USAGE];
                    $change = true;
                }
                if($change == true) {
                    file_put_contents(CRAWL_DIR."/schedules/crawl_status.txt",
                        serialize($crawl_status));
                }
                $info[self::CRAWL_TIME] = $crawl_status['CRAWL_TIME'];
            } else {
                $info[self::CRAWL_TIME] = 0;
            }

            $info[self::MEMORY_USAGE] = memory_get_peak_usage();
            $data = array();
            $data['MESSAGE'] = serialize($info);

            $this->displayView($view, $data);
        }
    }

    /**
     * Adds a file with contents $data and with name containing $address and 
     * $time to a subfolder $day of a folder $dir
     *
     * @param string $schedule_name the name of the kind of schedule being saved
     * @param string &$data_string encoded, compressed, serialized data the 
     *      schedule is to contain
     */
    function addScheduleToScheduleDirectory($schedule_name, &$data_string)
    {
        $dir = CRAWL_DIR."/schedules/".$schedule_name.$_REQUEST['crawl_time'];

        $address = str_replace(".", "-", $_SERVER['REMOTE_ADDR']); 
        $address = str_replace(":", "_", $address);
        $time = time();
        $day = floor($time/86400);

        if(!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }

        $dir .= "/$day";
        if(!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }

        $data_hash = crawlHash($data_string);
        file_put_contents($dir."/At".$time."From".$address.
            "WithHash$data_hash.txt", $data_string);
    }

    /**
     * Returns the time in seconds from the start of the current epoch of the 
     * active crawl if it exists; 0 otherwise
     * 
     * @return int  time of active crawl
     */
    function crawlTime()
    {
        $info = array();
        $info[self::STATUS] = self::CONTINUE_STATE;
        $view = "fetch";

        if(file_exists(CRAWL_DIR."/schedules/crawl_status.txt")) {
            $crawl_status = unserialize(file_get_contents(
                CRAWL_DIR."/schedules/crawl_status.txt"));
            $info[self::CRAWL_TIME] = (isset($crawl_status["CRAWL_TIME"])) ?
                $crawl_status["CRAWL_TIME"] : 0;
        } else {
            $info[self::CRAWL_TIME] = 0;
        }

        $data = array();
        $data['MESSAGE'] = serialize($info);

        $this->displayView($view, $data);
    }

}
?>
