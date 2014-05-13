<?php
/**
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
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */
class GroupView extends View implements CrawlConstants
{
    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";

    /**
     * @param array $data
     */
    function renderView($data) {
        $logo = "resources/yioop.png";
        $base_query = $data['PAGING_QUERY']."&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN];
        $other_base_query = $data['OTHER_PAGING_QUERY']."&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN];
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        if(MOBILE) {
            $logo = "resources/m-yioop.png";
        }
        if(PROFILE) {
        ?>
        <div class="top-bar"><?php
            $this->element("signin")->render($data);
        ?>
        </div><?php
        }
        ?>
        <h1 class="group-heading logo"><a href="./?<?php
            e(CSRF_TOKEN."=".$data[CSRF_TOKEN]); ?>"><img
            src="<?php e($logo); ?>" alt="Yioop!" /></a><small> - <?php
        if(isset($data['JUST_THREAD'])) {
            e("<a href='$base_query&a=groupFeeds&just_group_id=".
                $data['PAGES'][0]["GROUP_ID"]."' >".
                $data['PAGES'][0][self::SOURCE_NAME]."</a> : ".
                $data['SUBTITLE']);
            if(!MOBILE) {
                e(" [<a href='$base_query&f=rss".
                    "&a=groupFeeds&just_thread=".
                    $data['JUST_THREAD']."'>RSS</a>]");
            }
            if($logged_in) {
                e(" <a href='$other_base_query&a=groupFeeds&just_thread=".
                    $data['JUST_THREAD']."' >&gt;&gt;</a>");
            }
        } else if(isset($data['JUST_GROUP_ID'])){
            e($data['PAGES'][0][self::SOURCE_NAME]);
            e(" [".tl('group_view_feed'));
            if(!MOBILE && !$logged_in) {
                e("|<a href='$base_query&a=groupFeeds&just_group_id=".
                    $data['PAGES'][0]["GROUP_ID"]."&f=rss' >RSS</a>");
            }
            e("|<a href='$base_query&a=wiki&group_id=".
                $data['JUST_GROUP_ID']."'>" .
                tl('group_view_wiki') . "</a>]");
            if($logged_in) {
                e(" <a href='$other_base_query&just_group_id=".
                    $data['PAGES'][0]["GROUP_ID"]."' >&gt;&gt;</a>");
            }
        } else if(isset($data['JUST_USER_ID'])) {
            e(tl('group_view_user',
                $data['PAGES'][0]["USER_NAME"]));
            if($logged_in) {
                e(" <a href='$other_base_query&a=groupFeeds&just_user_id=".
                    $data['PAGES'][0]["USER_ID"]."' >&gt;&gt;</a>");
            }
        } else {
            e(tl('group_view_myfeeds'));
            if($logged_in) {
                e(" <a href='$other_base_query&a=groupFeeds' >&gt;&gt;</a>");
            }
        }
        ?></small>
        </h1>
        <?php
        if(isset($data['ELEMENT'])) {
            $element = $data['ELEMENT'];
            $this->element($element)->render($data);
        }
        if(PROFILE) {
        ?>
        <script type="text/javascript">
        /*
            Used to warn that user is about to be logged out
         */
        function logoutWarn()
        {
            doMessage(
                "<h2 class='red'><?php
                    e(tl('adminview_auto_logout_one_minute'))?></h2>");
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
