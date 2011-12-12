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
/** Loads url_parser to clean resource name*/
require_once BASE_DIR."/lib/url_parser.php";

/**
 *  Used to serve resources, css, or scripts such as images from APP_DIR
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class ResourceController extends Controller implements CrawlConstants
{ 
    /**
     * No models used by this controller
     * @var array
     */
    var $models = array();
    /**
     * Only outputs JSON data so don't need view
     * @var array
     */
    var $views = array();
    /**
     * These are the activities supported by this controller
     * @var array
     */
    var $activities = array("get");

    /**
     * Checks that the request seems to be coming from a legitimate fetcher then
     * determines which activity the fetcher is requesting and calls that
     * activity for processing.
     *
     */
    function processRequest() 
    {
        $data = array();

        $activity = $_REQUEST['a'];

        if(in_array($activity, $this->activities)) {$this->$activity();}
    }

    /**
     * Gets the resources from APP_DIR/$_REQUEST['f'] mentioned in
     * $_REQUEST['n'] after cleaning
     */
    function get()
    {
        if(!isset($_REQUEST['n']) || !isset($_REQUEST['f'])) return;
        if(in_array($_REQUEST['f'], array("css", "scripts", "resources"))) {
            $folder = $_REQUEST['f'];
        } else {
            return;
        }
        $name = $this->clean($_REQUEST['n'], "string");
        $type = UrlParser::getDocumentType($name);
        $name = UrlParser::getDocumentFilename($name);
        $name = ($type != "") ? "$name.$type" :$name;

        $path = APP_DIR."/$folder/$name";
        $finfo = new finfo(FILEINFO_MIME);
        $mime_type = $finfo->file($path);
        if(file_exists($path)) {
            header("Content-type:$mime_type");
            readfile($path);
        }
    }
}
?>
