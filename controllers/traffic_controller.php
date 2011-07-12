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
 * This class handles data coming to a queue_server from a external sources
 * such as a browser extension or by clicking on search result links.
 *
 * @author Chris Pollett, Vijaya Pamidi
 * @package seek_quarry
 * @subpackage controller
 */
class TrafficController extends Controller implements CrawlConstants
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
    var $activities = array("toolbarTraffic");


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

        $result = $this->signinModel->checkValidSignin(
        $this->clean($_POST['u'], "string"), 
        $this->clean($_POST['p'], "string") );
        $activity = $_REQUEST['a'];

        //echo "OK";
        if(in_array($activity, $this->activities)) {$this->$activity();}

    }
    /**
     * Adds a file with contents $data and with name containing $address and 
     * $time to a subfolder $day of a folder $dir
     *
     * @param string &$data_string encoded, compressed, serialized data the 
     *      schedule is to contain
     */

    function toolbarTraffic(&$data_string)
    {
        $toolbar_data = $_POST["b"];
        $time = time();

        $dir = CRAWL_DIR."/schedules/"."ToolbarData";

        //echo "$dir";

        $address = str_replace(".", "-", $_SERVER['REMOTE_ADDR']);
        $address = str_replace(":", "_", $address);
        //$time = time();
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

        $fname= $dir."/At".$time."From".$address."WithHash$data_hash.txt";

        $fh = fopen($fname, "a+");
        fwrite($fh, $toolbar_data);
        fclose($fh);
        //echo "OK TEST";
        return true;

    }
}
?>
