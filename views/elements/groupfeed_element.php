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

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/**
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
class GroupfeedElement extends Element implements CrawlConstants
{
    /**
     *
     *  @param array $data makes use of the CSRF token for anti CSRF attacks
     */
    function render($data)
    {
        ?>
        <div class="current-activity">
            <?php
            $base_query = $data['PAGING_QUERY']."&amp;".CSRF_TOKEN."=".
                $data[CSRF_TOKEN];
            $paging_query = $data['PAGING_QUERY']."&amp;".CSRF_TOKEN."=".
                    $data[CSRF_TOKEN];
            if(isset($data['ADD_PAGING_QUERY'])) {
                $paging_query .= $data['ADD_PAGING_QUERY'];
            }
            if($data['SUBTITLE'] != "") { ?>
                <div class="float-opposite">
                <a href="<?php e($base_query) ?>"><?php
                    e(tl('groupfeed_element_back'))?></a>
                </div>
            <?php
            }
            ?>
            <h2><?php e(tl('groupfeed_element_recent_activity'));
            if($data['SUBTITLE'] != "") {
                e("[{$data['SUBTITLE']}]");
            }
            ?></h2>
            <div>
            &nbsp;
            </div>
            <?php
            $open_in_tabs = $data['OPEN_IN_TABS'];
            foreach($data['PAGES'] as $page) {
                $time = time();
                $pub_date = $time;
                $delta = $time - $pub_date;
                if($delta < self::ONE_DAY) {
                    $num_hours = ceil($delta/self::ONE_HOUR);
                    if($num_hours <= 1) {
                        $pub_date =
                            tl('feeds_helper_view_onehour');
                    } else {
                        $pub_date =
                            tl('feeds_helper_view_hourdate', $num_hours);
                    }
                } else {
                    $pub_date = date("d/m/Y", $pub_date);
                }
                $encode_source = urlencode(
                    urlencode($page[self::SOURCE_NAME]));
                ?>
                <div class='group-result'>
                <?php
                $subsearch = (isset($data["SUBSEARCH"])) ? $data["SUBSEARCH"] :
                    "";
                if($page["MEMBER_ACCESS"] == GROUP_READ_WRITE &&
                    !$page['USER_ID'] == "" &&
                    ($page['USER_ID'] == $_SESSION['USER_ID'] ||
                    $_SESSION['USER_ID'] == ROOT_ID)) {
                    ?>
                    <div class="float-opposite" >[<a
                    href="javascript:update_post_form(<?php
                        e($page['ID']); ?>)"><?php
                        e(tl('groupfeed_element_edit')); ?></a>]
                    [<a href="<?php e($paging_query.'&amp;arg=deletepost&amp;'.
                        "post_id=".$page['ID']); ?>" title="<?php
                        e(tl('groupfeed_element_delete')); ?>">X</a>]</div>
                <?php
                }
                ?>
                <div id='result-<?php e($page['ID']); ?>'>
                <h2><a href="<?php e($base_query . "&amp;just_thread=".
                    $page['PARENT_ID']);?>" rel="nofollow" <?php
                if($open_in_tabs) { ?> target="_blank" <?php }
                ?>><?php e($page[self::TITLE]); ?></a>.
                <a class="gray-link" rel='nofollow' href="<?php e($base_query.
                    "&amp;just_group_id=".$page['GROUP_ID']);?>" ><?php
                    e($page[self::SOURCE_NAME]."</a>"
                    ."<span class='gray'> - $pub_date</span>");
                 ?></h2>
                <p>
                <a class="echo-link" rel='nofollow' href="<?php e($base_query.
                    "&amp;just_user_id=".$page['USER_ID']);?>" ><?php
                    e($page['USER_NAME']); ?></a></p>
                <?php
                $description = isset($page[self::DESCRIPTION]) ?
                    $page[self::DESCRIPTION] : "";?>
                <div><?php e($description); ?></div>
                <div class="float-opposite">
                    <?php if($page["MEMBER_ACCESS"] == GROUP_READ_WRITE) { ?>
                    <a href='javascript:comment_form(<?php
                    e("{$page['ID']}, {$page['PARENT_ID']}, ".
                        "{$page['GROUP_ID']}"); ?>)'><?php
                    e(tl('groupfeed_element_comment'));?></a>.
                    <a href='javascript:start_thread_form(<?php
                    e("{$page['ID']},".
                        "{$page['GROUP_ID']}"); ?>)'><?php
                        e(tl('groupfeed_element_start_thread'));?></a>.
                    <?php
                    }
                    ?>
                </div>
                </div>
                <div id='<?php e($page["ID"]); ?>'></div>
                </div>
            <div>
            &nbsp;
            </div>
            <?php
            } //end foreach
            ?>
            <?php
            $this->view->helper("pagination")->render(
                $paging_query,
                $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
            ?>
        </div>
        <script type="text/javascript">
        function comment_form(id, parent_id, group_id)
        {
            tmp = '<div class="comment"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length)
            if(start_elt != tmp) {
                elt(id).innerHTML =
                    tmp +
                    '<form action="./" >' +
                    '<input type="hidden" name="c" value="admin" />' +
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
                    'id="comment-'+ id +'" name="description" ></textarea>' +
                    '<button class="button-box float-opposite" ' +
                    'type="submit">Save</button>' +
                    '<div>&nbsp;</div>'+
                    '</form>';
            } else {
                elt(id).innerHTML = "";
            }
        }

        function start_thread_form(id, group_id)
        {
            tmp = '<div class="thread"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length)
            if(start_elt != tmp) {
                elt(id).innerHTML =
                    tmp +
                    '<form action="./" >' +
                    '<input type="hidden" name="c" value="admin" />' +
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
                    '<p><input type="text" name="title" value="" '+
                    ' maxlength="80" class="wide-field"/></p>' +
                    '<p><b><label for="description-'+ id +'" ><?php
                        e(tl("groupfeed_element_post"));
                    ?></label></b></p>' +
                    '<textarea class="short-text-area" '+
                    'id="description-'+ id +'" name="description" ></textarea>'+
                    '<button class="button-box float-opposite" ' +
                    'type="submit">Save</button>' +
                    '<div>&nbsp;</div>'+
                    '</form>';
            } else {
                elt(id).innerHTML = "";
            }
        }

        function update_post_form(id)
        {
            tmp = '<div class="update"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length)
            if(start_elt != tmp) {
                setDisplay('result-'+id, false);
                elt(id).innerHTML =
                    tmp +
                    '<form action="./" >' +
                    '<input type="hidden" name="c" value="admin" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="updatepost" />' +
                    '<input type="hidden" name="id" value="' +
                        id + '" />' +
                    '<input type="hidden" name="<?php e(CSRF_TOKEN); ?>" '+
                    'value="<?php e($data[CSRF_TOKEN]); ?>" />' +
                    '<h2><b><?php
                        e(tl("groupfeed_element_edit_post"));
                    ?></b></h2>'+
                    '<p><b><label for="title-'+ id +'" ><?php
                        e(tl("groupfeed_element_subject"));
                    ?></label></b></p>' +
                    '<p><input type="text" name="title" value="" '+
                    ' maxlength="80" class="wide-field"/></p>' +
                    '<p><b><label for="description-'+ id +'" ><?php
                        e(tl("groupfeed_element_post"));
                    ?></label></b></p>' +
                    '<textarea class="short-text-area" '+
                    'id="description-'+ id +'" name="description" ></textarea>'+
                    '<button class="button-box float-opposite" ' +
                    'type="submit">Save</button>' +
                    '<div>&nbsp;</div>'+
                    '</form>';
            } else {
                elt(id).innerHTML = "";
                setDisplay('result-'+id, true);
            }
        }
        </script>
        <?php
    }
}
 ?>
