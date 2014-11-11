<?php

/*
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
 *  @author Eswara Rajesh Pinapala epinapala@live.com
 *  @package seek_quarry
 *  @subpackage element
 *  @license http://www.gnu.org/licenses/ GPL3
 *  @link http://www.seekquarry.com/
 *  @copyright 2009 - 2014
 *  @filesource
 */

/**
 * Description of ApiController
 *
 * @author epinapala
 */
if (!defined('BASE_DIR')) {
    echo "BAD REQUEST";
    exit();
}
/** Load base controller class if needed */
require_once BASE_DIR . "/controllers/controller.php";
/** Loads common constants for web crawling */
require_once BASE_DIR . "/lib/crawl_constants.php";
/** For Wiki Parsing */
require_once BASE_DIR . "/lib/wiki_parser.php";

/**
 * Controller used to handle user group activities outside of
 * the admin panel setting. This either could be because the admin panel
 * is "collapsed" or because the request concerns a wiki page.
 *
 * @author Eswara Rajesh Pinapala
 * @package seek_quarry
 * @subpackage controller
 */
class ApiController extends Controller implements CrawlConstants {

    /**
     * Says which activities (roughly methods invoke for the api) this
     * controller will respond to (note: more activities will be loaded from
     * components)
     * @var array
     */
    var $activities = array("wiki");

    /**
     * Used to process requests related to user group activities outside of
     * the admin panel setting. This either could be because the admin panel
     * is "collapsed" or because the request concerns a wiki page.
     */
    function processRequest() {
        $data = array();
        if (!PROFILE) {
            return $this->configureRequest();
        }
        if (isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
        } else {
            $user_id = $_SERVER['REMOTE_ADDR'];
        }
        $data['SCRIPT'] = "";
        $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user_id);

        $data = array_merge($data, $this->processSession());

        if (isset($data["VIEW"])) {
            $view = $data["VIEW"];
        } else {
            $view = 'api';
        }
        $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        $this->displayView($view, $data);
    }

    /**
     * Used to perform the actual activity call to be done by the
     * api_controller.
     * processSession is called from @see processRequest, which does some
     * cleaning of fields if the CSRFToken is not valid. It is more likely
     * that that api_controller may be involved in such requests as it can
     * be invoked either when a user is logged in or not and for users with and
     * without accounts. processSession makes sure the $_REQUEST'd activity is
     * valid (or falls back to groupFeeds) then calls it. If someone uses
     * the Settings link to change the language or default number of feed
     * elements to view, this method sets up the $data variable so that
     * the back/cancel button on that page works correctly.
     */
    function processSession() {
        if (isset($_REQUEST['a']) &&
                in_array($_REQUEST['a'], $this->activities)) {
            $activity = $this->clean($_REQUEST['a'],"string");
        } else {
            $activity = "groupFeeds";
        }
        $data = $this->call($activity);
        $data['ACTIVITY_CONTROLLER'] = "group";
        $data['PAGE_TITLE'] = $this->clean($_REQUEST['page_name'],"string");
        $data['ACTIVITY_METHOD'] = $activity; //for settings controller
        if (!is_array($data)) {
            $data = array();
        }
        return $data;
    }
}

?>
