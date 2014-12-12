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
/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";
/**
 * Element responsible for draw the feeds a user is subscribed to
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
class GroupfeedElement extends Element implements CrawlConstants
{
    /**
     * Draws the Feeds for the Various Groups a User is a associated with.
     *
     * @param array $data feed items should be prepared by the controller
     *     and stored in the $data['PAGES'] variable.
     *     makes use of the CSRF token for anti CSRF attacks
     */
    function render($data)
    {
        $is_admin = ($data["CONTROLLER"] == "admin");
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $append_url = ($logged_in) ?"&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN] : "";
        $arrows = ($is_admin) ? "expand.png" : "collapse.png";
        $is_status = isset($data['STATUS']);
        $base_query = $data['PAGING_QUERY'] . $append_url;
        if(isset($data["WIKI_QUERY"])) {
            $wiki_query = $data["WIKI_QUERY"] . $append_url;
        }
        $paging_query = $base_query;
        $other_paging_query = $data['OTHER_PAGING_QUERY']."&amp;".
            CSRF_TOKEN."=".$data[CSRF_TOKEN];
        if(isset($data['ADD_PAGING_QUERY'])) {
            $paging_query .= $data['ADD_PAGING_QUERY'];
            $other_paging_query .= $data['ADD_PAGING_QUERY'];
        }
        if(isset($data['LIMIT'])) {
            $other_paging_query .= "&limit=".$data['LIMIT'];
        }
        if(isset($data['RESULTS_PER_PAGE'])) {
            $other_paging_query .= "&num=".$data['RESULTS_PER_PAGE'];
        }
        if(!$is_status) {
            if(!MOBILE &&($is_admin || $logged_in)) {
                if(isset($data['JUST_GROUP_ID'])){
                    $other_paging_query .= "&amp;just_group_id="
                        . $data['JUST_GROUP_ID'];
                }
                ?>
                <div class="float-same admin-collapse sidebar"><a
                href="<?php e($other_paging_query);
                if(isset($data['MODE']) && $data['MODE'] == 'grouped'){
                    e("&amp;v=grouped");
                }
                ?>" ><?php
                e("<img src='resources/" . $arrows . "'/>"); ?></a></div>
                <div class="float-same admin-collapse"><?php
                if(isset($data['SUBSCRIBE_LINK'])) {
                    if($data['SUBSCRIBE_LINK'] == PUBLIC_JOIN) {
                        e('[<a href="'.$paging_query.'&amp;arg=addgroup">'.
                        tl('groupfeed_element_add_group').
                        '</a>]');
                    } else if ($data['SUBSCRIBE_LINK'] != NO_JOIN) {
                        e('[<a href="'.$paging_query.'&amp;arg=addgroup">'.
                        tl('groupfeed_element_request_add').
                        '</a>]');
                    }
                }
                ?></div>
            <?php
            }
            ?>
            <div id="feedstatus" <?php if($is_admin) {
                e(' class="current-activity" ');
                } else {
                e(' class="small-margin-current-activity" ');
                }?> >
        <?php
        }
        if(isset($data['SUBTITLE']) &&
            $data['SUBTITLE'] != "" && $logged_in) { ?>
            <div class="float-opposite">
            <?php
            if(isset($data["WIKI_PAGE_NAME"])) { ?>
                [<a href="<?php e($wiki_query) ?>"><?php
                    e(tl('groupfeed_element_wiki_page'))?></a>]
                [<a href="<?php e($base_query) ?>"><?php
                    e(tl('groupfeed_element_back'))?></a>]
            <?php } else {?>
                    <a href="<?php e($base_query) ?>"><?php
                        e(tl('groupfeed_element_back'))?></a>
            <?php } ?>
            </div>
        <?php
        }
        if($is_admin) {
            ?>
            <h2><?php
            if(!isset($data['SUBTITLE']) || $data['SUBTITLE'] == "") {
                e(tl('groupfeed_element_group_activity'));
            } else {
                if(isset($data['JUST_THREAD'])) {
                    if(isset($data['WIKI_PAGE_NAME'])) {
                        e(tl('groupfeed_element_wiki_thread',
                            $data['WIKI_PAGE_NAME']));
                    } else {
                        e("<a href='$base_query&just_group_id=".
                            $data['PAGES'][0]["GROUP_ID"]."'>".
                            $data['PAGES'][0][self::SOURCE_NAME]. "</a> :".
                            $data['SUBTITLE']);
                        if(!MOBILE) {
                            $group_base_query = ($is_admin) ?
                                $other_paging_query : $base_query;
                            e(" [<a href='$group_base_query&f=rss".
                                "&just_thread=".
                                $data['JUST_THREAD']."'>RSS</a>]");
                        }
                    }
                } else if(isset($data['JUST_GROUP_ID'])){
                    $manage_groups = "?c={$data['CONTROLLER']}&amp;".
                        CSRF_TOKEN."=".$data[CSRF_TOKEN]."&amp;a=manageGroups";
                    e( $data['SUBTITLE']);
                    e(" [".tl('groupfeed_element_feed')."|".
                        "<a href='?c={$data['CONTROLLER']}&".CSRF_TOKEN."=".
                        $data[CSRF_TOKEN]."&amp;a=wiki&group_id=".
                        $data['JUST_GROUP_ID']."'>" .
                        tl('group_view_wiki') . "</a>]");
                } else if(isset($data['JUST_USER_ID'])) {
                    e(tl('groupfeed_element_user',
                        $data['PAGES'][0]["USER_NAME"]));
                } else {
                    if(isset($data['SUBTITLE'])) e("[{$data['SUBTITLE']}]");
                }
            }
            if(!isset($data['JUST_THREAD']) && !isset($data['JUST_GROUP_ID'])) {
                ?><span style="position:relative;top:5px;" >
                <a href="<?php e($paging_query. '&amp;v=ungrouped'); ?>" ><img
                src="resources/list.png" /></a>
                <a href="<?php e($paging_query. '&amp;v=grouped'); ?>" ><img
                src="resources/grouped.png" /></a>
                </span><?php
            }
            ?>
            <?php
        }
        ?></h2>
        <div>
        &nbsp;
        </div>
        <?php
        if(isset($data['MODE']) && $data['MODE'] == 'grouped') {
            $this->renderGroupedView($paging_query, $data);
            $page = false;
        } else {
            $page = $this->renderUngroupedView($logged_in, $base_query,
                $paging_query, $data);
        }
        $data['FRAGMENT'] = "";
        if(isset($data['JUST_THREAD']) && $logged_in && $page &&
            isset($data['GROUP_STATUS']) &&
            $data['GROUP_STATUS'] == ACTIVE_STATUS) {
            $data['FRAGMENT'] = '#result-'.$page['ID'];
            ?>
            <div class='button-group-result'>
            <button class="button-box" onclick='comment_form(<?php
                    e("\"add-comment\", ".
                        "{$data['PAGES'][0]['PARENT_ID']},".
                        "{$data['PAGES'][0]['GROUP_ID']}"); ?>)'><?php
                    e(tl('groupfeed_element_comment'));?></button>
            <div id='add-comment'></div>
            </div>
            <?php
        }
        $this->view->helper("pagination")->render($paging_query,
            $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        ?>
        </div>
        <?php
        if(!$is_status) {
            $this->renderScripts($data);
        }
    }
    /**
     * Used to draw group feeds items when we are grouping feeds items by group
     *
     * @param string $paging_query stem for all links
     *      drawn in view
     * @param array &$data fields used to draw the queue
     */
    function renderGroupedView($paging_query, &$data)
    {
        foreach ($data['GROUPS'] as $group) {
            e("<div class=\"access-result\">" .
                "<div><b>" .
                "<a href=\"$paging_query&amp;just_group_id=" .
                $group['GROUP_ID'] . "&amp;v=grouped" . "\" " .
                "rel=\"nofollow\">" .
                $group['GROUP_NAME'] . "</a> " .
                "[<a href=\"$paging_query&amp;group_id=" .
                $group['GROUP_ID'] . "&amp;a=wiki\">" .
                tl('manageaccount_element_group_wiki') . "</a>] " .
                "(" . tl('manageaccount_element_group_stats',
                        $group['NUM_POSTS'],
                        $group['NUM_THREADS']) . ")</b>" .
                "</div>" .
                "<div class=\"slight-pad\">" .
                "<b>" . tl('manageaccount_element_last_post')
                . "</b> " .
                "<a href=\"$paging_query&amp;just_thread=" .
                $group['THREAD_ID'] . "\">" .
                $group['ITEM_TITLE'] . "</a>" .
                "</div>" .
                "</div>");
            $data['TOTAL_ROWS'] = $data['NUM_GROUPS'];
        }
    }
    /**
     * Used to draw feed items as a combined thread of all groups
     *
     * @param bool $logged_in where or not the session is of a logged in user
     * @param string $base_query url that serves as the stem for all links
     *      drawn in view
     * @param string $paging_query base_query concatenated with limit and num
     * @param array &$data fields used to draw the queue
     * @return array $page last feed item processed
     */
    function renderUngroupedView($logged_in, $base_query, $paging_query, &$data)
    {
        $open_in_tabs = $data['OPEN_IN_TABS'];
        $time = time();
        $can_comment = array(GROUP_READ_COMMENT, GROUP_READ_WRITE,
            GROUP_READ_WIKI);
        $start_thread = array(GROUP_READ_WRITE, GROUP_READ_WIKI);
        if(!isset($data['GROUP_STATUS']) ||
            $data['GROUP_STATUS'] != ACTIVE_STATUS) {
            $can_comment = array();
            $start_thread = array();
        }
        $page = array();
        if(isset($data['PAGES'][0]["MEMBER_ACCESS"]) &&
            in_array($data['PAGES'][0]["MEMBER_ACCESS"], $can_comment)) {
            if(isset($data['JUST_THREAD'])) {
                ?>
                <div class='button-group-result'>
                <button class="button-box" onclick='comment_form(<?php
                        e("\"add-comment\", ".
                            "{$data['PAGES'][0]['PARENT_ID']},".
                            "{$data['PAGES'][0]['GROUP_ID']}"); ?>)'><?php
                        e(tl('groupfeed_element_comment'));?></button>
                <div></div>
                </div>
                <?php
            } else if(isset($data['JUST_GROUP_ID']) &&
                in_array($data['PAGES'][0]["MEMBER_ACCESS"], $start_thread)) {
                ?>
                <div class='button-group-result'>
                <button class="button-box" onclick='start_thread_form(<?php
                        e("\"add-comment\", ".
                            "{$data['PAGES'][0]['GROUP_ID']}"); ?>)'><?php
                        e(tl('groupfeed_element_start_thread'));?></button>
                <div id='add-comment'></div>
                </div>
                <?php
            }
        }
        if(isset($data['NO_POSTS_YET'])) {
            if(isset($data['NO_POSTS_START_THREAD'])) {
                //no read case where no posts yet
                ?>
                <div class='button-group-result'>
                <button class="button-box" onclick='start_thread_form(<?php
                        e("\"add-comment\", ".
                            "{$data['JUST_GROUP_ID']}"); ?>)'><?php
                        e(tl('groupfeed_element_start_thread'));?></button>
                <div id='add-comment'></div>
                </div>
                <?php
            }
            ?>
            <p class="red"><?php e(tl('groupfeed_element_no_posts_yet')); ?></p>
            <?php
        }
        if(isset($data['NO_POSTS_IN_THREAD'])) {
            ?>
            <p class="red"><?php
                e(tl('groupfeed_element_thread_no_exist')); ?></p>
            <?php
        }
        foreach($data['PAGES'] as $page) {
            $pub_date = $page['PUBDATE'];
            $pub_date = $this->view->helper("feeds")->getPubdateString(
                $time, $pub_date);
            $encode_source = urlencode(
                urlencode($page[self::SOURCE_NAME]));
            ?>
            <div class='group-result'>
            <?php
            $subsearch = (isset($data["SUBSEARCH"])) ? $data["SUBSEARCH"] :
                "";
            $edit_list = ($page['ID'] == $page['PARENT_ID']) ?
                $start_thread : $can_comment;
            if(in_array($page["MEMBER_ACCESS"], $edit_list) &&
                !isset($data['JUST_GROUP_ID']) &&
                isset($_SESSION['USER_ID']) &&
                (($page['USER_ID'] != "" &&
                $page['USER_ID'] == $_SESSION['USER_ID']) ||
                $_SESSION['USER_ID'] == ROOT_ID ||
                $_SESSION['USER_ID'] == $page['OWNER_ID']) &&
                isset($page['TYPE']) && $page['TYPE'] != WIKI_GROUP_ITEM) {
                ?>
                <div class="float-opposite" ><?php
                if(!isset($page['NO_EDIT'])) {
                    ?>[<a href="javascript:update_post_form(<?php
                    e($page['ID']); ?>)"><?php
                    e(tl('groupfeed_element_edit')); ?></a>]<?php
                }
                ?>
                [<a href="<?php e($paging_query.'&amp;arg=deletepost&amp;'.
                    "post_id=".$page['ID']); ?>" title="<?php
                    e(tl('groupfeed_element_delete')); ?>">X</a>]
                </div>
            <?php
            }
            ?>
            <div id='result-<?php e($page['ID']); ?>' >
            <div class="float-same center" >
            <img class="feed-user-icon" src="<?php
                e($page['USER_ICON']); ?>" /><br />
            <a class="feed-user-link echo-link" rel='nofollow'
                href="<?php e($base_query.
                "&amp;just_user_id=".$page['USER_ID']);?>" ><?php
                e($page['USER_NAME']); ?></a>
            </div>
            <div class="feed-item-body">
            <h2><a href="<?php e($base_query . "&amp;just_thread=".
                $page['PARENT_ID']);?>" rel="nofollow"
                id='title<?php e($page['ID']);?>' <?php
                if($open_in_tabs) { ?> target="_blank" <?php }
                ?>><?php e($page[self::TITLE]); ?></a><?php
                if(isset($page['NUM_POSTS'])) {
                    e(" (");
                    e(tl('groupfeed_element_num_posts',
                        $page['NUM_POSTS']));
                    if(!MOBILE &&
                        $data['RESULTS_PER_PAGE'] < $page['NUM_POSTS']) {
                        $thread_query = $base_query . "&amp;just_thread=".
                            $page['PARENT_ID'];
                        $this->view->helper("pagination")->render($thread_query,
                            0, $data['RESULTS_PER_PAGE'], $page['NUM_POSTS'],
                            true);
                    }
                    e(", " . tl('groupfeed_element_num_views',
                        $page['NUM_VIEWS']));
                    e(") ");
                } else if (!isset($data['JUST_GROUP_ID'])) {
                    if(isset($page["VOTE_ACCESS"]) &&
                        $page["VOTE_ACCESS"] == UP_DOWN_VOTING_GROUP ) {
                        e(' (+'.$page['UPS'].'/'.($page['UPS'] +
                            $page['DOWNS']).')');
                    } else if(isset($page["VOTE_ACCESS"]) &&
                        $page["VOTE_ACCESS"] == UP_VOTING_GROUP) {
                        e(' (+'.$page['UPS'].')');
                    }
                }
                ?>.
                <?php e("<span class='gray'> - $pub_date</span>");
                ?>
            <b><a class="gray-link" rel='nofollow' href="<?php e($base_query.
                "&amp;just_group_id=".$page['GROUP_ID']);?>" ><?php
                e($page[self::SOURCE_NAME]."</a></b>");
                if(!isset($data['JUST_GROUP_ID']) &&
                        in_array($page["MEMBER_ACCESS"], $start_thread) ) {
                    ?>
                    <a  class='gray-link' href='javascript:start_thread_form
                    (<?php
                        e("{$page['ID']},"."{$page['GROUP_ID']},\"".
                        tl('groupfeed_element_start_thread_in_group',
                            $page[self::SOURCE_NAME])); ?>")' title='<?php
                        e(tl('groupfeed_element_start_thread_in_group',
                            $page[self::SOURCE_NAME]));?>'><img
                      class="new-thread-icon" src='resources/new_thread.png'
                            /></a>
                    <?php } ?>
                </a></h2>
            <?php
            if(!isset($data['JUST_GROUP_ID'])) {
                $description = isset($page[self::DESCRIPTION]) ?
                    $page[self::DESCRIPTION] : "";?>
                <div id='description<?php e($page['ID']);?>'><?php
                    e($description); ?></div>
                <?php
                if(!isset($page['NO_EDIT']) && isset($page['OLD_DESCRIPTION'])){
                    ?>
                    <div id='old-description<?php e($page['ID']);?>'
                        class='none'><?php
                        e($page['OLD_DESCRIPTION']); ?></div>
                    <?php
                }
                if($logged_in && isset($page["VOTE_ACCESS"]) &&
                    in_array($page["VOTE_ACCESS"], array(UP_DOWN_VOTING_GROUP,
                        UP_VOTING_GROUP))) {
                    ?>
                    <div class="gray"><b>
                    <?php
                    e(tl('groupfeed_element_post_vote'));
                    $up_vote = $paging_query."&amp;post_id=".$page['ID'].
                        "&amp;arg=upvote&amp;group_id=".$page['GROUP_ID'];
                    $down_vote = $paging_query."&amp;post_id=".$page['ID'].
                        "&amp;arg=downvote&amp;group_id=".$page['GROUP_ID'];
                    if($page["VOTE_ACCESS"] == UP_DOWN_VOTING_GROUP) {
                        ?>
                        <button onclick='window.location="<?php
                            e($up_vote); ?>"'>+</button><button
                            onclick='window.location="<?php
                            e($down_vote); ?>"'>-</button>
                        <?php
                    } else if($page["VOTE_ACCESS"] == UP_VOTING_GROUP) {
                        ?>
                        <button onclick='window.location="<?php
                            e($up_vote); ?>"'>+</button>
                        <?php
                    }
                    ?>
                    </b></div>
                    <?php
                }
            } else if(isset($page['LAST_POSTER']) ){ ?>
                <div id='description<?php e($page['ID']);?>'><?php
                $recent_date = $this->view->helper("feeds"
                    )->getPubdateString($time, $page['RECENT_DATE']);
                e("<b>".tl('groupfeed_element_last_post_info')."</b> ".
                    $recent_date." - <a href='".$base_query.
                    "&amp;just_user_id=".$page['LAST_POSTER_ID']."'>".
                    $page['LAST_POSTER'] . "</a>");
                    ?></div>
            <?php
            }
            ?>
            <div class="float-opposite">
                <?php if(!isset($data['JUST_GROUP_ID']) &&
                    in_array($page["MEMBER_ACCESS"], $can_comment) &&
                    !isset($data['JUST_THREAD'])){?>
                    <a href='javascript:comment_form(<?php
                    e("{$page['ID']}, {$page['PARENT_ID']}, ".
                        "{$page['GROUP_ID']}"); ?>)'><?php
                    e(tl('groupfeed_element_comment'));?></a>.<?php
                }
                ?>
            </div>
            </div>
            </div>
            <div id='<?php e($page["ID"]); ?>'></div>
            </div>
            <div>
            &nbsp;
            </div>
            <?php
        } //end foreach
        return $page;
    }
    /**
     * Used to render the Javascript that appears at the non-status updating
     * portion of the footer of this element.
     *
     * @param array $data contains arguments needs to draw urls correctly.
     */
    function renderScripts($data)
    {
        if($data['LIMIT'] + $data['RESULTS_PER_PAGE'] == $data['TOTAL_ROWS']){
            $data['LIMIT'] += $data['RESULTS_PER_PAGE'] - 1;
        }
        $paging_query = $data['PAGING_QUERY']."&amp;".CSRF_TOKEN."=".
            $data[CSRF_TOKEN];
        if(isset($data['ADD_PAGING_QUERY'])) {
            $paging_query .= $data['ADD_PAGING_QUERY'];
        }
        $limit_hidden = "";
        if(isset($data['LIMIT'])) {
            $paging_query .= "&limit=".$data['LIMIT'];
        }
        $num_hidden = "";
        if(isset($data['RESULTS_PER_PAGE'])) {
            $paging_query .= "&num=".$data['RESULTS_PER_PAGE'];
        }
        $just_fields = array("LIMIT" => "limit", "RESULTS_PER_PAGE" => "num",
            "JUST_THREAD" => 'just_thread', "JUST_USER_ID" => "just_user_id",
            "JUST_GROUP_ID" => "just_group_id");
        $hidden_form = "\n";
        foreach($just_fields as $field => $form_field) {
            if(isset($data[$field])) {
                $hidden_form .= "'<input type=\"hidden\" ".
                    "name=\"$form_field\" value=\"{$data[$field]}\" />' +\n";
            }
        }
        ?>
        <script type="text/javascript"><?php
            $clear = (MOBILE) ? " clear" : "";
        ?>
        var updateId = null;
        function comment_form(id, parent_id, group_id)
        {
            clearInterval(updateId);
            tmp = '<div class="comment<?php e($clear); ?>"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length);
            if(start_elt != tmp) {
                elt(id).innerHTML =
                    tmp +
                    '<form action="./<?php e($data['FRAGMENT']);
                    ?>" >' + <?php e($hidden_form); ?>
                    '<input type="hidden" name="c" value="<?php
                        e($data['CONTROLLER']) ?>" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="addcomment" />' +
                    '<input type="hidden" name="parent_id" value="' +
                        parent_id + '" />' +
                    '<input type="hidden" name="group_id" value="' +
                        group_id + '" />' +
                    '<input type="hidden" name="<?php e(CSRF_TOKEN); ?>" '+
                    'value="<?php e($data[CSRF_TOKEN]); ?>" />' +
                    '<h2><b><label for="comment-'+ id +'" ><?php
                        e(tl("groupfeed_element_add_comment"));
                    ?></label></b></h2>'+
                    '<textarea class="short-text-area" '+
                    'id="comment-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading,'+
                    '!wikibtn-slide" '+
                    '></textarea>' +
                    '<button class="button-box float-opposite" ' +
                    'type="submit"><?php e(tl("groupfeed_element_save"));
                    ?></button>' +
                    '<div>&nbsp;</div>'+
                    '</form>';
                var comment_id = 'comment-' + id;
                editorize(comment_id);
                elt(comment_id).focus();
            } else {
                elt(id).innerHTML = "";
            }
        }

        function start_thread_form(id, group_id, group_name)
        {
            clearInterval(updateId);
            tmp = '<div class="thread<?php e($clear); ?>"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length)
            if(start_elt != tmp) {
                var group_header = "";
                if(typeof(group_name) !== 'undefined') {
                    group_header = '<h2><b>' + group_name + '</b></h2>';
                }
                elt(id).innerHTML =
                    tmp +
                    '<br />'
                    +'<form action="./<?php e($data['FRAGMENT']);
                    ?>" >' + <?php e($hidden_form); ?>
                    '<input type="hidden" name="c" value="<?php
                        e($data['CONTROLLER']) ?>" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="newthread" />' +
                    '<input type="hidden" name="group_id" value="' +
                        group_id + '" />' +
                    '<input type="hidden" name="<?php e(CSRF_TOKEN); ?>" '+
                    'value="<?php e($data[CSRF_TOKEN]); ?>" />' +
                    group_header+
                    '<p><b><label for="title-'+ id +'" ><?php
                        e(tl("groupfeed_element_subject"));
                    ?></label></b></p>' +
                    '<p><input type="text" id="title-'+ id +'" '+
                    'name="title" value="" '+
                    ' maxlength="<?php e(TITLE_LEN); ?>" '+
                    'class="wide-field"/></p>' +
                    '<p><b><label for="description-'+ id +'" ><?php
                        e(tl("groupfeed_element_post"));
                    ?></label></b></p>' +
                    '<textarea class="short-text-area" '+
                    'id="description-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading,' +
                    '!wikibtn-slide" ></textarea>' +
                    '<button class="button-box float-opposite" ' +
                    'type="submit"><?php e(tl("groupfeed_element_save"));
                    ?></button>' +
                    '<div>&nbsp;</div>'+
                    '</form>';
                editorize('description-'+ id);
            } else {
                elt(id).innerHTML = "";
            }
        }

        function update_post_form(id)
        {
            clearInterval(updateId);
            var title = elt('title'+id).innerHTML;
            var description = elt('old-description'+id).innerHTML;
            var tmp = '<div class="update<?php e($clear); ?>"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length)
            if(start_elt != tmp) {
                setDisplay('result-'+id, false);
                elt(id).innerHTML =
                    tmp +
                    '<form action="./<?php e($data['FRAGMENT']);
                    ?>" >' + <?php e($hidden_form); ?>
                    '<input type="hidden" name="c" value="<?php
                        e($data['CONTROLLER']) ?>" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="updatepost" />' +
                    '<input type="hidden" name="post_id" value="' +
                        id + '" />' +
                    '<input type="hidden" name="<?php e(CSRF_TOKEN); ?>" '+
                    'value="<?php e($data[CSRF_TOKEN]); ?>" />' +
                    '<h2><b><?php
                        e(tl("groupfeed_element_edit_post"));
                    ?></b></h2>'+
                    '<p><b><label for="title-'+ id +'" ><?php
                        e(tl("groupfeed_element_subject"));
                    ?></label></b></p>' +
                    '<p><input type="text" name="title" value="'+title+'" '+
                    ' maxlength="<?php e(TITLE_LEN);
                    ?>" class="wide-field"/></p>' +
                    '<p><b><label for="description-'+ id +'" ><?php
                        e(tl("groupfeed_element_post"));
                    ?></label></b></p>' +
                    '<textarea class="short-text-area" '+
                    'id="description-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading,' +
                    '!wikibtn-slide" >' + description + '</textarea>'+
                    '<button class="button-box float-opposite" ' +
                    'type="submit"><?php e(tl("groupfeed_element_save"));
                    ?></button>' +
                    '<div>&nbsp;</div>'+
                    '</form>';
                editorize('description-'+ id);
            } else {
                elt(id).innerHTML = "";
                setDisplay('result-'+id, true);
            }
        }
        function feedStatusUpdate()
        {
            var startUrl = "<?php e(html_entity_decode(
                $paging_query).'&arg=status'); ?>";
            var feedTag = elt('feedstatus');
            getPage(feedTag, startUrl);
            elt('feedstatus').style.backgroundColor = "#EEE";
            setTimeout("resetBackground()", 0.5*sec);
        }

        function clearUpdate()
        {
             clearInterval(updateId);
             var feedTag = elt('feedstatus');
             feedTag.innerHTML= "<h2 class='red'><?php
                e(tl('groupfeed_element_no_longer_update'))?></h2>";
        }
        function resetBackground()
        {
             elt('feedstatus').style.backgroundColor = "#FFF";
        }
        function doUpdate()
        {
             var sec = 1000;
             var minute = 60*sec;
             updateId = setInterval("feedStatusUpdate()", 15*sec);
             setTimeout("clearUpdate()", 20*minute + sec);
        }
        </script>
        <?php
    }
}
?>
