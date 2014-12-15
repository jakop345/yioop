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
 * View used to draw and allow editing of group feeds when not in the admin view
 * (so activities panel on side is not present.) This is also used to draw
 * group feeds for public feeds when not logged.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */
class GroupView extends View implements CrawlConstants
{
    /** This view is drawn on a web layout
     * @var string
     */
    var $layout = "web";
    /**
     * Draws a minimal container with a GroupElement in it on which a group
     * feed can be drawn
     *
     * @param array $data with fields used for drawing the container and feed
     */
    function renderView($data) {
        $logo = LOGO;
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $append_url = ($logged_in) ?"&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN] : "";
        $base_query = $data['PAGING_QUERY']."$append_url";
        $other_base_query = $data['OTHER_PAGING_QUERY']."$append_url";
        if(MOBILE) {
            $logo = M_LOGO;
        }
        if(PROFILE) {
        ?>
        <div class="top-bar"><?php
            $this->element("signin")->render($data);
        ?>
        </div><?php
        }
        ?>
        <h1 class="group-heading"><a href="./<?php if($logged_in) {
                e('?'.CSRF_TOKEN."=".$data[CSRF_TOKEN]);
            }
            ?>"><img class='logo'
            src="<?php e($logo); ?>" alt="<?php e($this->logo_alt_text);
            ?>" /></a><small> - <?php
        if(isset($data['JUST_THREAD'])) {
            if(isset($data['WIKI_PAGE_NAME'])) {
                e(tl('groupfeed_element_wiki_thread',
                    $data['WIKI_PAGE_NAME']));
            } else {
                e("<a href='$base_query&a=groupFeeds&just_group_id=".
                    $data['PAGES'][0]["GROUP_ID"]."' >".
                    $data['PAGES'][0][self::SOURCE_NAME]."</a> : ".
                    $data['SUBTITLE']);
            }
            if(!MOBILE) {
                e(" [<a href='$base_query&f=rss".
                    "&a=groupFeeds&just_thread=".
                    $data['JUST_THREAD']."'>RSS</a>]");
            }
        } else if(isset($data['JUST_GROUP_ID'])){
            e($data['SUBTITLE']);
            e(" [".tl('group_view_feed'));
            if(!MOBILE && !$logged_in) {
                e("|<a href='$base_query&a=groupFeeds&just_group_id=".
                    $data['JUST_GROUP_ID']."&f=rss' >RSS</a>");
            }
            e("|<a href='$base_query&a=wiki&group_id=".
                $data['JUST_GROUP_ID']."'>" .
                tl('group_view_wiki') . "</a>]");
        } else if(isset($data['JUST_USER_ID'])) {
            e(tl('group_view_user',
                $data['PAGES'][0]["USER_NAME"]));
        } else {
            e(tl('group_view_myfeeds'));
        }
        if(!isset($data['JUST_THREAD']) && !isset($data['JUST_GROUP_ID'])) {
            ?><span style="position:relative;top:5px;" >
            <a href="<?php e($base_query); ?>" ><img
            src="resources/list.png" /></a>
            <a href="<?php e($base_query. '&amp;v=grouped'); ?>" ><img
            src="resources/grouped.png" /></a>
            </span><?php
        }
        ?></small>
        </h1>
        <?php
        if(in_array($data["AD_LOCATION"], array('top', 'both') ) ) { ?>
            <div class="top-adscript group-ad-static"><?php
            e($data['TOP_ADSCRIPT']);
            ?></div>
            <?php
        }
        if(isset($data['ELEMENT'])) {
            $element = $data['ELEMENT'];
            $this->element($element)->render($data);
        }
        $this->element("help")->render($data);
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
            document.location='?a=signout';
        }
        //schedule logout warnings
        var sec = 1000;
        var minute = 60 * sec;
        setTimeout("logoutWarn()", 59 * minute);
        setTimeout("autoLogout()", 60 * minute);

        </script>
        <?php
        }
    }
}
?>
