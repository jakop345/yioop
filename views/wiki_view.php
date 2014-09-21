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
class WikiView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    var $layout = "web";

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
        $is_admin = ($data["CONTROLLER"] == "admin");
        $use_header = !$is_admin &&
                isset($data['PAGE_HEADER']) && $data['PAGE_HEADER'] &&
                isset($data["HEAD"]['page_type']) &&
                $data["HEAD"]['page_type'] != 'presentation';
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
        if($use_header) {
            e("<div>".$data['PAGE_HEADER']."</div>");
        } else if(!$use_header &&
            (!isset($data['page_type']) || $data['page_type'] != 'presentation')
            ) {
            ?>
            <div class="top-bar">
            <div class="subsearch">
            <ul class="out-list">
            <?php
            $modes = array();
            if($can_edit) {
                $modes = array(
                    "read" => tl('wiki_view_read'),
                    "edit" => tl('wiki_view_edit')
                );
            }
            $modes["pages"] = tl('wiki_view_pages');
            foreach($modes as $name => $translation) {
                if($data["MODE"] == $name) { ?>
                    <li class="outer"><b><?php e($translation); ?></b></li>
                    <?php
                } else if(!isset($data["PAGE_NAME"])||$data["PAGE_NAME"]==""){?>
                    <li class="outer"><span class="gray"><?php e($translation);
                    ?></span></li>
                    <?php
                } else {
                    $append = "";
                    if($name != 'pages') {
                        $append = '&amp;page_name='. $data['PAGE_NAME'];
                    }
                    ?>
                    <li class="outer"><a href="<?php e($base_query .
                        '&amp;arg='.$name.'&amp;a=wiki'.$append); ?>"><?php
                        e($translation); ?></a></li>
                    <?php
                }
            }
            ?>
            </ul>
            </div>
            <?php
            $this->element("signin")->render($data);
            ?>
            </div>
            <div class="current-activity-header">
            <h1 class="group-heading logo"><a href="./<?php
                if($logged_in) {
                    e("?".CSRF_TOKEN."=".$data[CSRF_TOKEN]);
                } ?>"><img
                src="<?php e($logo); ?>" alt="<?php e($this->logo_alt_text);
                    ?>" /></a><small> - <?php e($data["GROUP"]["GROUP_NAME"].
                    "[<a href='$feed_base_query&amp;a=groupFeeds'>".
                    tl('wiki_view_feed').
                    "</a>|".tl('wiki_view_wiki')."]");
                ?></small>
            </h1>
            </div>
            <?php
        }
        $this->element("wiki")->render($data);
        if(!$is_admin &&
            isset($data['PAGE_FOOTER']) && 
            isset($data["HEAD"]['page_type']) &&
            $data["HEAD"]['page_type'] != 'presentation') {
            e("<div class='current-activity-footer clear'>".
                $data['PAGE_FOOTER']."</div>");
        }
        if($logged_in) {
        ?>
        <script type="text/javascript">
        /*
            Used to warn that user is about to be logged out
         */
        function logoutWarn()
        {
            doMessage(
                "<h2 class='red'><?php
                    e(tl('adminview_auto_logout_one_minute')); ?></h2>");
        }
        /*
            Javascript to perform autologout
         */
        function autoLogout()
        {
            document.location='?c=search&a=signout';
        }
        //schedule logout warnings
        var sec = 1000;
        var minute = 60*sec;
        setTimeout("logoutWarn()", 59 * minute);
        setTimeout("autoLogout()", 60 * minute);
        </script>
        <?php
        }
    }
}
?>
