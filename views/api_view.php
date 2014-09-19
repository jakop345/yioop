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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * View used to draw and allow editing of wiki page when not in the admin view
 * (so activities panel on side is not present.) This is also used to draw
 * wiki pages for public groups when not logged.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */
class ApiView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    var $layout = "api";

    /**
     * Draws a minimal container with a WikiElement in it on which a group
     * wiki page can be drawn
     *
     * @param array $data with fields used for drawing the container and page
     */
    function renderView($data)
    {
        $logo = LOGO;
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $can_edit = $logged_in && isset($data["CAN_EDIT"]) && $data["CAN_EDIT"];
        $base_query = "?c=group&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN] . "&amp;group_id=".
                $data["GROUP"]["GROUP_ID"];
        $feed_base_query = "?c=group&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN] . "&amp;just_group_id=".
                $data["GROUP"]["GROUP_ID"];
        if(!$logged_in) {
            $base_query = "?c=group&amp;group_id=". $data["GROUP"]["GROUP_ID"];
            $feed_base_query =
                "?c=group&amp;just_group_id=". $data["GROUP"]["GROUP_ID"];
        }
        if(MOBILE) {
            $logo = M_LOGO;
        }
        
        $this->element("api")->render($data);
    }
}
?>
