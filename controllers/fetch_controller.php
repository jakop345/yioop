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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
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
        $this->$activity();
    }

    /**
     *
     */
    function schedule()
    {
        $view = "fetch";

        // set up query
        $data = array();
        $schedule_filename = CRAWL_DIR."/schedules/schedule.txt";

        if(file_exists($schedule_filename)) {
            $data['MESSAGE'] = file_get_contents($schedule_filename);
            unlink($schedule_filename);
        } else {
            $info = array();
            $info[self::STATUS] = self::NO_DATA_STATE;
            $data['MESSAGE'] = serialize($info);
        }

        $this->displayView($view, $data);
    }

    /**
     *
     */
    function update()
    {
        $view = "fetch";
         
        if(isset($_REQUEST['found'])) {
            $info =array();
            $sites = unserialize(gzuncompress(
                base64_decode(urldecode($_REQUEST['found']))));

            $address = str_replace(".", "-", $_SERVER['REMOTE_ADDR']); 
            $address = str_replace(":", "_", $address);
            $time = time();
            $day = floor($time/86400);


            $this->addRobotSchedules($sites, $address, $day, $time); 
            $this->addToCrawlSchedules($sites, $address, $day, $time);
            $this->addToIndexSchedules($sites, $address, $day, $time);

            $info[self::STATUS] = self::CONTINUE_STATE;
            if(file_exists(CRAWL_DIR."/schedules/crawl_status.txt")) {
                $crawl_status = unserialize(
                    file_get_contents(CRAWL_DIR."/schedules/crawl_status.txt"));
                $info[self::CRAWL_TIME] = $crawl_status['CRAWL_TIME'];
            } else {
                $info[self::CRAWL_TIME] = 0;
            }     

            $data = array();
            $data['MESSAGE'] = serialize($info);

            $this->displayView($view, $data);
        }
    }

    /**
     *  @param &array $sites
     *  @param string $address
     *  @param string $day
     *  @param string $time
     */
    function addToIndexSchedules(&$sites, $address, $day, $time)
    {
        if(isset($sites[self::SEEN_URLS])) {
            $index_sites[self::SEEN_URLS] = $sites[self::SEEN_URLS];
        }
        $sites[self::SEEN_URLS] = NULL;

        $index_sites[self::MACHINE] = $_SERVER['REMOTE_ADDR'];
        $index_sites[self::MACHINE_URI] = $_REQUEST['machine_uri'];
        if(isset($sites[self::INVERTED_INDEX])) {
            $index_sites[self::INVERTED_INDEX] = $sites[self::INVERTED_INDEX];
        }
        $index_dir =  
            CRAWL_DIR."/schedules/".self::index_data_base_name.
                $_REQUEST['crawl_time'];

        $this->addScheduleToScheduleDirectory(
            $index_dir, $index_sites, $address, $day, $time);
        $sites[self::INVERTED_INDEX] = NULL;
    }

    /**
     *  @param &array $sites
     *  @param string $address
     *  @param string $day
     *  @param string $time
     */
    function addToCrawlSchedules(&$sites, $address, $day, $time)
    {
        $base_dir =  CRAWL_DIR."/schedules/".
            self::schedule_data_base_name.$_REQUEST['crawl_time'];
        $scheduler_info = array();

        if(isset($sites[self::TO_CRAWL])) {
            $scheduler_info[self::TO_CRAWL] = $sites[self::TO_CRAWL];
        }
        
        $scheduler_info[self::MACHINE] = $_SERVER['REMOTE_ADDR'];

        if(isset($sites[self::SCHEDULE_TIME])) {
            $scheduler_info[self::SCHEDULE_TIME] = $sites[self::SCHEDULE_TIME];
        }

        if(isset($sites[self::SEEN_URLS])) {
            $seen_sites = $sites[self::SEEN_URLS];
            $num_seen = count($seen_sites);

            for($i = 0; $i < $num_seen; $i++) {
                $scheduler_info[self::SEEN_URLS][$i] = 
                    $seen_sites[$i][self::URL];
            }
        }
        $this->addScheduleToScheduleDirectory(
            $base_dir, $scheduler_info, $address, $day, $time);
        $sites[self::TO_CRAWL] = NULL;
    }

    /**
     *  @param &array $sites
     *  @param string $address
     *  @param string $day
     *  @param string $time
     */
    function addRobotSchedules(&$sites, $address, $day, $time)
    {
        $robot_dir =  CRAWL_DIR."/schedules/".
            self::robot_data_base_name.$_REQUEST['crawl_time'];
        if(isset($sites[self::ROBOT_TXT])) {
            $data = $sites[self::ROBOT_TXT];
        } else {
            $data = array();
        }
        $this->addScheduleToScheduleDirectory(
            $robot_dir, $data, $address, $day, $time);
        $sites[self::ROBOT_TXT] = NULL;
    }


    /**
     *
     *  @param string $dir
     *  @param &array $data
     *  @param string $address
     *  @param string $day
     *  @param string $time
     */
    function addScheduleToScheduleDirectory($dir, &$data, $address, $day, $time)
    {
        if(!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }

        $dir .= "/$day";
        if(!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        
        $data_string = serialize($data);
        $data_hash = crawlHash($data_string);
        file_put_contents(
            $dir."/At".$time."From".$address.
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
        if(file_exists(CRAWL_DIR."/schedules/crawl_status.txt")) {
            $crawl_status = unserialize(file_get_contents(
                CRAWL_DIR."/schedules/crawl_status.txt"));
        $info[self::CRAWL_TIME] = $crawl_status[self::CRAWL_TIME];
        } else {
            $info[self::CRAWL_TIME] = 0;
        }

        $data = array();
        $data['MESSAGE'] = serialize($info);

        $this->displayView($view, $data);
    }

}
?>
