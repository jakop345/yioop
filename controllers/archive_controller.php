<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
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
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Load base controller class if needed */
require_once BASE_DIR."/controllers/controller.php";
/** We look cached web pages up in a WebArchiveBundle, so load this class */
require_once BASE_DIR."/lib/web_archive_bundle.php";
/** Loads common constants for web crawling */
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 * Fetcher machines also act as archives for complete caches of web pages, 
 * this controller is used to handle access to these web page caches
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class ArchiveController extends Controller implements CrawlConstants
{
    /**
     * This controller does not make use of any models
     * @var array
     */
    var $models = array();
    /**
     * This controller does not make use of any views
     * @var array
     */
    var $views = array();
    /**
     * The only legal activity this controller will accept is a request 
     * for the cache of a web page
     * @var array
     */
    var $activities = array("cache");

    /**
     * Main method for this controller to handle requests. It first checks 
     * the request is valid, and then handles the corresponding activity
     *
     * For this controller the only activity is to handle a cache request
     */
    function processRequest() 
    {

        $data = array();


        /* do a quick test to see if this is a request seems like from a 
           legitimate machine
         */
        if(!$this->checkRequest()) {return; }

        $activity = $_REQUEST['a'];
        $this->$activity();

    }


    /**
     * Retrieves the requested page from the WebArchiveBundle and echo it page, 
     * base64 encoded
     */
    function cache()
    {
        $web_archive = new WebArchiveBundle(
            CRAWL_DIR.'/cache/'.self::archive_base_name.
                $_REQUEST['crawl_time']);
        $page = $web_archive->getPage($_REQUEST['offset'], 
            $_REQUEST['partition']);

        echo base64_encode(serialize($page));
    }


}
?>
