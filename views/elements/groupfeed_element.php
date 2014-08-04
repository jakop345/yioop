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
        $arrows = ($is_admin) ? "&lt;&lt;" : "&gt;&gt;";
        $is_status = isset($data['STATUS']);
        $base_query = "./".$data['PAGING_QUERY'] . $append_url;
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
            if($is_admin || $logged_in) { ?>
                <div class="float-same admin-collapse">[<a
                href="<?php e($other_paging_query) ?>" ><?php
                e($arrows); ?></a>]</div>
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
        if($data['SUBTITLE'] != "" && $logged_in) { ?>
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
            if($data['SUBTITLE'] == "") {
                e(tl('groupfeed_element_recent_activity'));
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
                    e("[{$data['SUBTITLE']}]");
                }
            }
            ?>
            </h2>
            <?php
        }
        ?>
        <div>
        &nbsp;
        </div>
        <?php
        $open_in_tabs = $data['OPEN_IN_TABS'];
        $time = time();
        $can_comment = array(GROUP_READ_COMMENT, GROUP_READ_WRITE);
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
                $data['PAGES'][0]["MEMBER_ACCESS"] == GROUP_READ_WRITE) {
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
            if($page["MEMBER_ACCESS"] == GROUP_READ_WRITE &&
                !isset($data['JUST_GROUP_ID']) &&
                $page['USER_ID'] != "" && isset($_SESSION['USER_ID']) &&
                ($page['USER_ID'] == $_SESSION['USER_ID'] ||
                $_SESSION['USER_ID'] == ROOT_ID) &&
                $page['TYPE'] != WIKI_GROUP_ITEM) {
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
            <a class="gray-link" rel='nofollow' href="<?php e($base_query.
                "&amp;just_group_id=".$page['GROUP_ID']);?>" ><?php
                e($page[self::SOURCE_NAME]."</a>"
                ."<span class='gray'> - $pub_date</span>");
             ?></h2>
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
                    !isset($data['JUST_THREAD'])){ ?>
                    <a href='javascript:comment_form(<?php
                    e("{$page['ID']}, {$page['PARENT_ID']}, ".
                        "{$page['GROUP_ID']}"); ?>)'><?php
                    e(tl('groupfeed_element_comment'));?></a>.<?php
                    if(!isset($data['JUST_GROUP_ID']) &&
                        $page["MEMBER_ACCESS"] == GROUP_READ_WRITE) {
                    ?>
                        <a href='javascript:start_thread_form(<?php
                        e("{$page['ID']},".
                            "{$page['GROUP_ID']}"); ?>)'><?php
                            e(tl('groupfeed_element_start_thread'));?></a>.
                    <?php
                    }
                }
                ?>
            </div>
            </div>
            <div id='<?php e($page["ID"]); ?>'></div>
            </div>
            </div>
            <div>
            &nbsp;
            </div>
            <?php
            } //end foreach
            $data['FRAGMENT'] = "";
            if(isset($data['JUST_THREAD']) && $logged_in) {
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
     * Used to render the Javascript that appears at the non-status updating
     * portion of the footer of this element.
     *
     * @param array $data contains arguments needs to draw urls correctly.
     */
    function renderScripts($data)
    {
        if($data['LIMIT'] + $data['RESULTS_PER_PAGE'] == $data['TOTAL_ROWS']) {
            $data['LIMIT'] += $data['RESULTS_PER_PAGE'];
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
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading" '+
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

        function start_thread_form(id, group_id)
        {
            clearInterval(updateId);
            tmp = '<div class="thread<?php e($clear); ?>"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length)
            if(start_elt != tmp) {
                elt(id).innerHTML =
                    tmp +
                    '<form action="./<?php e($data['FRAGMENT']);
                    ?>" >' + <?php e($hidden_form); ?>
                    '<input type="hidden" name="c" value="<?php
                        e($data['CONTROLLER']) ?>" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="newthread" />' +
                    '<input type="hidden" name="group_id" value="' +
                        group_id + '" />' +
                    '<input type="hidden" name="<?php e(CSRF_TOKEN); ?>" '+
                    'value="<?php e($data[CSRF_TOKEN]); ?>" />' +
                    '<h2><b><?php
                        e(tl("groupfeed_element_start_thread"));
                    ?></b></h2>'+
                    '<p><b><label for="title-'+ id +'" ><?php
                        e(tl("groupfeed_element_subject"));
                    ?></label></b></p>' +
                    '<p><input type="text" id="title-'+ id +'" '+
                    'name="title" value="" '+
                    ' maxlength="80" class="wide-field"/></p>' +
                    '<p><b><label for="description-'+ id +'" ><?php
                        e(tl("groupfeed_element_post"));
                    ?></label></b></p>' +
                    '<textarea class="short-text-area" '+
                    'id="description-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading" '+
                    '></textarea>' +
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
                    ' maxlength="80" class="wide-field"/></p>' +
                    '<p><b><label for="description-'+ id +'" ><?php
                        e(tl("groupfeed_element_post"));
                    ?></label></b></p>' +
                    '<textarea class="short-text-area" '+
                    'id="description-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading" '+
                    '>' + description + '</textarea>'+
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
