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
class WikiView extends View
{
    /** This view is drawn on a web layout
     *  @var string
     */
    var $layout = "web";

    /**
     * Renders the list of admin activities and draws the current activity
     * Renders the Javascript to autologout after an hour
     *
     * @param array $data  what is contained in this array depend on the current
     * admin activity. The $data['ELEMENT'] says which activity to render
     */
    function renderView($data) 
    {
        $logo = "resources/yioop.png";
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $can_edit = $logged_in && isset($data["CAN_EDIT"]) && $data["CAN_EDIT"];
        $base_query = "?c=group&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN] . "&amp;group_id=".
                $data["GROUP"]["GROUP_ID"];
        if(MOBILE) {
            $logo = "resources/m-yioop.png";
        }
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
            } else if($data["MODE"] == "pages") { ?>
                <li class="outer"><span class="gray"><?php e($translation);
                ?></span></li>
                <?php
            } else {
                ?>
                <li class="outer"><a href="<?php e($base_query .
                    '&amp;arg='.$name.'&amp;a=wiki&amp;page_name='.
                    $data['PAGE_NAME']); ?>"><?php
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
        <h1 class="group-heading logo"><a href="./?<?php
            e(CSRF_TOKEN."=".$data[CSRF_TOKEN]); ?>"><img
            src="<?php e($logo); ?>" alt="Yioop!" /></a><small> - <?php
            e($data["GROUP"]["GROUP_NAME"].
                "[<a href='$base_query&amp;a=groupFeeds'>".tl('wiki_view_feed').
                "</a>|".tl('wiki_view_wiki')."]");
            ?></small>
        </h1>
        <?php
        switch($data["MODE"])
        {
            case "edit":
                $this->renderEditPageForm($data);
            break;

            case "pages":
                $this->renderPages($data, $can_edit, $logged_in);
            break;

            case "history":
                $this->renderHistory($data);
            break;

            case "show":
            case "read":
            default:
                $this->renderReadPage($data, $can_edit, $logged_in);
            break;
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

    /**
     * @param array $data
     */
    function renderReadPage($data, $can_edit, $logged_in)
    {
        ?>
        <div class="small-margin-current-activity">
        <?php
        if($data["PAGE"]) {
            e($data["PAGE"]);
        } else if($can_edit) {
            e("<h2>".tl("wiki_view_page_no_exist", $data["PAGE_NAME"]).
                "</h2>");
            e("<p>".tl("wiki_view_create_edit")."</p>");
            e("<p>".tl("wiki_view_use_form_below")."</p>");?>
            <form id="editpageForm" method="get" action='#'>
            <input type="hidden" name="c" value="group" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="edit" />
            <input type="hidden" name="group_id" value="<?php
                e($data['GROUP']['GROUP_ID']); ?>" />
            <input type="text" name="page_name" class="narrow-field"
                value="" />
            <button class="button-box" type="submit"><?php
                e(tl('wiki_element_submit')); ?></button>
            </form>
            <?php
        } else if(!$logged_in) {
            e("<h2>".tl("wiki_view_page_no_exist", $data["PAGE_NAME"]).
                "</h2>");
            e("<p>".tl("wiki_view_signin_edit")."</p>");
        } else {
            e("<h2>".tl("wiki_view_page_no_exist", $data["PAGE_NAME"]).
                "</h2>");
        }
        ?>
        </div>
        <?php
    }

    /**
     * @param array $data
     */
    function renderEditPageForm($data)
    {
        ?>
        <div class="small-margin-current-activity">
        <div class="float-opposite">
        [<a href="?c=group&amp;a=wiki&amp;<?php
            e(CSRF_TOKEN.'='.$data[CSRF_TOKEN].'&amp;arg=history&amp;'.
            'page_id='.$data['PAGE_ID']); ?>"
        ><?php e(tl('wiki_element_history'))?></a>]
        [<a href="?c=group&amp;a=groupFeeds&amp;<?php
            e(CSRF_TOKEN.'='.$data[CSRF_TOKEN].
            '&amp;just_thread='.$data['DISCUSS_THREAD']);?>" ><?php
            e(tl('wiki_element_discuss'))?></a>]
        </div>
        <form id="editpageForm" method="post" action='#'>
            <input type="hidden" name="c" value="group" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="edit" />
            <input type="hidden" name="group_id" value="<?php
                e($data['GROUP']['GROUP_ID']); ?>" />
            <input type="hidden" name="page_name" value="<?php
                e($data['PAGE_NAME']); ?>" />
            <div class="top-margin">
                <b><?php
                e(tl('wiki_element_locale_name',
                    $data['CURRENT_LOCALE_TAG']));
                ?></b><br />
                <label for="page-data"><b><?php
                e(tl('wiki_element_page', $data['PAGE_NAME']));
                ?></b></label>
            </div>
            <textarea class="tall-text-area" name="page" ><?php
                e($data['PAGE']);
            ?></textarea>
            <div class="top-margin">
            <label for="edit-reason"><b><?php
            e(tl('wiki_element_edit_reason'));
            ?></b></label><input type="text" id='edit-reason'
                name="edit_reason" value=""
                maxlength="80" class="wide-field"/>
            </div>
            <div class="top-margin center">
            <button class="button-box" type="submit"><?php
                e(tl('wiki_element_savebutton')); ?></button>
            </div>
        </form>
        </div>
        <?php
    }

    /**
     * @param array $data
     */
    function renderPages($data, $can_edit, $logged_in)
    {
        ?>
        <div class="small-margin-current-activity">
        <?php
        $base_query = "?c=group&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN] . "&amp;group_id=".
                $data["GROUP"]["GROUP_ID"]."&amp;a=wiki";
        $create_query = $base_query . "&amp;arg=edit&amp;page_name=" .
            $data["FILTER"];
        $base_query .= "&amp;arg=read";
        $paging_query = "?c=group&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN] . "&amp;group_id=".
                $data["GROUP"]["GROUP_ID"]."&amp;a=wiki&amp;arg=pages";
        e("<h2>".tl("wiki_view_wiki_page_list",
            $data["GROUP"]["GROUP_NAME"]). "</h2>");
        ?>
        <form id="editpageForm" method="get" action='#'>
        <input type="hidden" name="c" value="group" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="wiki" />
        <input type="hidden" name="arg" value="pages" />
        <input type="hidden" name="group_id" value="<?php
            e($data['GROUP']['GROUP_ID']); ?>" />
        <input type="text" name="filter" class="extra-wide-field"
            placeholder="<?php e(tl("wiki_view_filter_or_create")); 
            ?>" value="<?php e($data['FILTER'])?>" />
        <button class="button-box" type="submit"><?php
            e(tl('wiki_element_search')); ?></button>
        </form>
        <?php
        if($data["FILTER"] != "") {
            e("<a href='$create_query'>".tl("wiki_view_create_page",
                $data['FILTER']) . "</a>");
        }
        ?>
        <div>&nbsp;</div>
        <?php
        foreach($data['PAGES'] as $page) {
            ?>
            <div class='group-result'>
            <a href="<?php e($base_query.'&amp;page_name='.
                $page['TITLE']);?>" ><?php e($page["TITLE"]); ?></a></br />
            <?php e(strip_tags($page["DESCRIPTION"])."..."); ?>
            </div>
            <div>&nbsp;</div>
            <?php
        }
        $this->helper("pagination")->render(
            $paging_query,
            $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        ?>
        </div>
        <?php
    }

    /**
     * @param array $data
     */
    function renderHistory($data)
    {
        ?>
        <div class="small-margin-current-activity">
        <div>&nbsp;</div>
        <?php
        $time = time();
        $feed_helper = $this->helper("feeds");
        $base_query = "?c=group&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN] . "&amp;group_id=".
                $data["GROUP"]["GROUP_ID"]."&amp;a=wiki&amp;arg=history&amp;".
                "page_id=".$data["page_id"];
        $current = $data['HISTORY'][0]["PUBDATE"];
        $first = true;
        foreach($data['HISTORY'] as $item) {
            ?>
            <div class='group-result'>
            <?php
            if($first && $data['LIMIT'] == 0) { ?>
                (<b><?php e(tl("wiki_view_diff_current"));
                    ?></b>| <b><?php e(tl("wiki_view_diff_next")); ?></b>)
                <?php
            } else { ?>
                (<a href="<?php e($base_query.'&amp;diff2='.
                    $current."&amp;diff1=".$item['PUBDATE']);?>" ><?php
                    e(tl("wiki_view_diff_current"));
                    ?></a> | <a href="<?php e($base_query.'&amp;diff2='.
                    $next."&amp;diff1=".$item['PUBDATE']);?>" ><?php
                    e(tl("wiki_view_diff_next"));
                    ?></a>)
                <?php
            }
            e("<a href='$base_query&show={$item['PUBDATE']}'>".
                date("r", $item["PUBDATE"])."</a>. ");
            e(tl("wiki_view_edited_by", $item["USER_NAME"]));
            if(strlen($item["EDIT_REASON"]) > 0) {
                e("<i>{$item["EDIT_REASON"]}</i>. ");
            }
            e(tl("wiki_view_page_len", $item["PAGE_LEN"])." ");
            if($first && $data['LIMIT'] == 0) {
                e("[<b>".tl("wiki_view_revert")."</b>].");
            } else { 
                e("[<a href='$base_query&amp;revert=".$item['PUBDATE'].
                "'>".tl("wiki_view_revert")."</a>].");
            }
            $first = false;
            $next = $item['PUBDATE'];
            ?>
            </div>
            <div>&nbsp;</div>
            <?php
        }
        $this->helper("pagination")->render(
            $base_query,
            $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        ?>
        </div>
        <?php
    }
}
?>
