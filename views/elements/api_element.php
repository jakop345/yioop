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
 * @author Eswara Rajesh Pinapala epinapala@live.com
 * @package seek_quarry
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if (!defined('BASE_DIR')) {
    echo "BAD REQUEST";
    exit();
}
/** Loads common constants for web crawling */
require_once BASE_DIR . "/lib/crawl_constants.php";
/**
 * Element responsible for drawing wiki pages in either admin or wiki view
 * It is also responsible for rendering wiki history pages, and listings of
 * wiki pages available for a group
 *
 * @author Eswara Rajesh Pinapala
 * @package seek_quarry
 * @subpackage element
 */
class ApiElement extends Element implements CrawlConstants
{
    /**
     * Draw a wiki page for group, or, depending on $data['MODE'] a listing
     * of all pages for a group, or the history of revisions of a given page
     * or the edit page form
     *
     * @param array $data fields contain data about the page being
     * displayeed or edited, or the list of pages being displayed.
     */
    function render($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $can_edit = $logged_in && isset($data["CAN_EDIT"]) &&
            $data["CAN_EDIT"];
        $this->renderJsonDocument($data, $can_edit, $logged_in);
    }
    /**
     * Used to send a Wiki content response for reading. If the page does
     * not exist various create/login-to-create etc messages are displayed
     * depending of it the user is logged in. and has write permissions
     * on the group.
     *
     * @param array $data fields PAGE used for page contents
     * @param bool $can_edit whether the current user has permissions to
     *     edit or create this page
     * @param bool $logged_in whethe current user is logged in or not
     */
    function renderJsonDocument($data, $can_edit, $logged_in)
    {
        $out_array = array();
        $http_code = 0;
        if($data["PAGE"]) {
            $out_array["wiki_content"] = html_entity_decode($data['PAGE'],
                ENT_QUOTES, 'UTF-8');
            $out_array['group_id'] = $data['GROUP']['GROUP_ID'];
            $out_array['group_name'] = $data['GROUP']['GROUP_NAME'];
            $out_array['page_id'] = $data['PAGE_ID'];
            $out_array['page_name'] = $data['PAGE_NAME'];
            $http_code = 200;
        } else {
            if(!$logged_in) {
                $out_array["logged_in"] = false;
                $http_code = 401;
            }
        }
        if($can_edit) {
            $out_array["can_edit"] = true;
        }
        if(isset($data['errors']) && count($data['errors']) > 0) {
            $out_array['errors'] = json_encode(
                array_map(
                    function ($string){
                        return html_entity_decode($string, ENT_QUOTES,
                            'UTF-8');
                    }, $data['errors']));
        }
        header("Content-Type: application/json");
        header('X-PHP-Response-Code: ' . $http_code, true, $http_code);
        e(json_encode($out_array));
        exit();
    }
}
