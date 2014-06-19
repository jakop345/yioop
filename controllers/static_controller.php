<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**Load base controller class, if needed. */
require_once BASE_DIR."/controllers/controller.php";
/**
 * This controller is  used by the Yioop web site to display
 * PUBLIC_GROUP_ID pages more like static forward facing pages.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */
class StaticController extends Controller
{
    /**
     * Says which activities (roughly methods invoke from the web)
     * this controller will respond to
     * @var array
     */
    var $activities = array("showPage");
    /**
     * This is the main entry point for handling people arriving to view
     * a static page. It determines which page to draw and class the view
     * to draw it.
     */
    function processRequest()
    {
        $data = array();
        $view = "static";
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        if(isset($_REQUEST['a'])) {
            if(in_array($_REQUEST['a'], $this->activities)) {
                $activity = $_REQUEST['a'];
            } else {
                $activity = "showPage";
            }
        } else {
            $activity = "showPage";
        }
        $data['VIEW'] = $view;
        $data = array_merge($data, $this->call($activity));
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user);
        $this->displayView($view, $data);
    }
    /**
     * This activity is used to display one a PUBLIC_GROUP_ID pages used
     * by the Yioop Web Site
     *
     * @return array $data has title and page contents of the static page to
     *     display
     */
    function showPage()
    {
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $data = array();
        if(isset($_REQUEST['p'])) {
            $page = $this->clean($_REQUEST['p'], "string");
            $page = preg_replace("@(\.\.|\/)@", "", $page);
        } else {
            $page = "404";
        }
        $page_string = $this->getPage($page);
        if($page_string == "") {
            $page = "404";
            $page_string = $this->getPage($page);
        }
        if(strpos($page_string, "`") !== false){
            if(isset($data["INCLUDE_SCRIPTS"])) {
                $data["INCLUDE_SCRIPTS"] = array();
            }
            $data["INCLUDE_SCRIPTS"][] = "math";
        }
        $data['page'] = $page;
        $page_parts = explode("END_HEAD_VARS", $page_string);
        $static_view = $this->view("static");
        $static_view->head_objects[$page] = array();
        if(count($page_parts) > 1) {
            $static_view->page_objects[$page]  = $page_parts[1];
            $head_lines = preg_split("/\s\n/", $page_parts[0]);
            foreach($head_lines as $line) {
                $semi_pos =  (strpos($line, ";")) ? strpos($line, ";"):
                    strlen($line);
                $line = substr($line, 0, $semi_pos);
                $line_parts = explode("=",$line);
                if(count($line_parts) == 2) {
                    $static_view->head_objects[$page][
                         trim(addslashes($line_parts[0]))] =
                            addslashes(trim($line_parts[1]));
                }
            }
        } else {
            $static_view->page_objects[$page] = $page_parts[0];
        }
        if(isset($_SESSION['value'])) {
            $data['value'] = $this->clean($_SESSION['value'], "string");
        }
        if((isset($static_view->head_objects[$data['page']]['title']))) {
            $data["subtitle"]=" - ".
                $static_view->head_objects[$data['page']]['title'];
            $static_view->head_objects[$data['page']]['title'] = "Yioop!".
                $data["subtitle"];
        } else {
            $data["subtitle"] = "";
        }
        return $data;
    }
    /**
     * Used to read in a PUBLIC_GROUP_ID wiki page that will be presented
     * to non-logged in visitors to the site.
     *
     * @param string $page_name name of file less extension to read in
     * @return string text of page
     */
    function getPage($page_name)
    {
        $locale_tag = getLocaleTag();
        $page_info = $this->model("group")->getPageInfoByName(
            PUBLIC_GROUP_ID, $page_name, $locale_tag, "read");
        $page_string = isset($page_info["PAGE"]) ? $page_info["PAGE"] : "";
        if(!$page_string && $locale_tag != DEFAULT_LOCALE) {
            //fallback to default locale for translation
            $page_info = $group_model->getPageInfoByName(
                $group_id, $page_name, DEFAULT_LOCALE, "read");
            $page_string = $page_info["PAGE"];
        }
        return $page_string;
    }
}
?>
