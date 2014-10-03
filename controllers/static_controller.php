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
    var $activities = array("showPage", "signout");
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
                if($activity == "signout") {
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('static_controller_logout_successful')."</h1>')";
                    $activity = "showPage";
                }
            } else {
                $activity = "showPage";
            }
        } else {
            $activity = "showPage";
        }
        $data['VIEW'] = $view;
        $data = array_merge($data, $this->call($activity));
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user);
        if(isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
        } else {
            $user_id = $_SERVER['REMOTE_ADDR'];
        }
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
        $static_view = $this->view("static");
        $this->parsePageHeadVars($static_view, $page, $page_string);
        if(isset($_SESSION['value'])) {
            $data['value'] = $this->clean($_SESSION['value'], "string");
        }
        $head_info = $static_view->head_objects[$data['page']];
        if((isset($head_info['title']))) {
            if($head_info['title']) {
                $data["subtitle"] = " - ".$head_info['title'];
            } else {
                $data["subtitle"] = "";
            }
            $static_view->head_objects[$data['page']]['title'] =
                tl('static_controller_complete_title', $head_info['title']);
        } else {
            $data["subtitle"] = "";
        }
        $locale_tag = getLocaleTag();
        $data['CONTROLLER'] = "static";
        $group_model = $this->model("group");
        if(isset($head_info['page_header']) && $head_info['page_header']) {
            $page_header = $group_model->getPageInfoByName(PUBLIC_GROUP_ID,
                $head_info['page_header'], $locale_tag, "read");
            if(isset($page_header['PAGE'])) {
                $header_parts = 
                    explode("END_HEAD_VARS", $page_header['PAGE']);
            }
            $data["PAGE_HEADER"] = (isset($header_parts[1])) ?
                $header_parts[1] : "".$page_header['PAGE'];
            $data["PAGE_HEADER"] = $this->component("social"
                )->dynamicSubstitutions(PUBLIC_GROUP_ID, $data,
                $data["PAGE_HEADER"]);
        }
        if(isset($head_info['page_footer']) && $head_info['page_footer']) {
            $page_footer = $group_model->getPageInfoByName(PUBLIC_GROUP_ID,
                $head_info['page_footer'], $locale_tag, "read");
            if(isset($page_footer['PAGE'])) {
                $footer_parts = 
                    explode("END_HEAD_VARS", $page_footer['PAGE']);
            }
            $data['PAGE_FOOTER'] = (isset($footer_parts[1])) ?
                $footer_parts[1] : "" . $page_footer['PAGE'];
            $data["PAGE_FOOTER"] = $this->component("social"
                )->dynamicSubstitutions(PUBLIC_GROUP_ID, $data,
                $data["PAGE_FOOTER"]);
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
        $group_model = $this->model("group");
        $locale_tag = getLocaleTag();
        $page_info = $group_model->getPageInfoByName(
            PUBLIC_GROUP_ID, $page_name, $locale_tag, "read");
        $page_string = isset($page_info["PAGE"]) ? $page_info["PAGE"] : "";
        if(!$page_string && $locale_tag != DEFAULT_LOCALE) {
            //fallback to default locale for translation
            $page_info = $group_model->getPageInfoByName(
                PUBLIC_GROUP_ID, $page_name, DEFAULT_LOCALE, "read");
            $page_string = $page_info["PAGE"];
        }
        $data['CONTROLLER'] = "static";
        $page_string = $this->component("social")->dynamicSubstitutions(
            PUBLIC_GROUP_ID, $data, $page_string);
        return $page_string;
    }
}
?>
