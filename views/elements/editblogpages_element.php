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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Element responsible for displaying the user account features
 *  that someone can modify for their own SeekQuarry/Yioop account.
 *  For now, you can only change your password
 *
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage element
 */
class EditBlogPagesElement extends Element
{
    /**
     *  Used to draw forms related to manipulating blog posts
     *
     *  @param array $data hold data for various dropdowns related to who
     *      can see a post, the description of the blog, and the blog items
     */
    function render($data)
    {
        $pre_base_url = "?".CSRF_TOKEN."=".$data[CSRF_TOKEN]."&amp;c=admin";
        $base_url = $pre_base_url . "&amp;a=blogPages";
        $localize_url = $pre_base_url . "&amp;a=manageLocales".
            "&amp;arg=editlocale&amp;selectlocale=".$data['LOCALE_TAG'];
        if(isset($data['EDIT_BLOGS'])) { ?>
            <div id="transfer">
                <div class="overlay">
                    <form  method="post" action='#'>
                        <input type="hidden" name="c" value="admin"/>
                        <input type="hidden" name="<?php
                            e(CSRF_TOKEN); ?>" value="<?php
                            e($data[CSRF_TOKEN]); ?>"/>
                        <input type="hidden" name="arg"
                            value="updateblogusers"/>
                        <input type="hidden" name="blogName" value=
                            "<?php e($data['EDIT_BLOGS']['NAME']); ?>"/>
                        <?php
                        if(isset($data['EDIT_BLOGS']['BLOG_USERS'])) {
                            foreach($data['EDIT_BLOGS']['BLOG_USERS'] as $blog){
                                ?><input type="radio" name="selectuser"
                                value="<?php e($blog['USER_ID']); ?>">
                                <?php e($blog['USER_NAME']); ?><br/>
                            <?php
                            }
                        } ?>
                        <hr/>
                        <input type="submit" value="submit" onclick=
                            "return confirm('<?php 
                            e(tl('managegroups_element_transfer_admin'))?>');"/>
                        <input type="button" value="cancel"
                            onclick="closeOverlay();"/>
                    </form>
                </div>
            </div>
            <?php
        } ?><div class="current-activity">
            <h2><?php e(tl('editblogpages_element_edit_blogpages')); ?></h2>
            <?php
            if(isset($data['IS_OWNER']) &&  $data['IS_OWNER'] === true) {
                if(isset($data['EDIT_BLOGS'])) { 
                    ?>[<?php
                    $edit_url =
                    $base_url.'&amp;arg=editdescription&amp;id='.
                    $data['EDIT_BLOGS']['TIMESTAMP'];
                    ?><a href = "<?php e($edit_url); ?>"><?php
                    e(tl('editblogpages_element_edit_settings'));?></a>]
                    [<a href="#" onclick='return showOverlay();'><?php
                    e(tl('editblogpages_element_transfer')); ?></a>]
                    <?php
                }
             }
            if(isset($data['IS_EDIT_DESC']) &&
                $data['IS_EDIT_DESC'] === true) { ?>
                <form method="post" action='#'>
                    <input type="hidden" name="c" value="admin" />
                    <input type="hidden" name="<?php e(CSRF_TOKEN); ?>"
                     value="<?php e($data[CSRF_TOKEN]); ?>" />
                    <input type="hidden" name="a" value="blogPages" />
                    <input type="hidden" name="arg" value="addbloggroup" />
                    <table class="name-table">
                        <tr>
                            <th><label for="select-group"><?php
                                e(tl('createblogpages_element_add_group'))
                                ?></label></th>
                            <?php if(isset($data['GROUP_NAMES']) ) {
                            ?><td><?php $this->view->helper("options")->render(
                                "select-group",
                                "selectgroup", $data['GROUP_NAMES'],
                                isset($data['SELECT_GROUP']));
                            ?></td>
                            <td>
                                <button class="button-box" type="submit"><?php
                                e(tl('editblgpages_element_submit'));
                                ?></button>
                            </td>
                       <?php }
                       ?></tr>
                    </table>
                    <?php 
                    if(isset($data['BLOG_GROUP'])) {
                        if(count($data['BLOG_GROUP']) > 0) { ?>
                            <table class = "role-table">
                            <?php
                            foreach($data['BLOG_GROUP'] as $blog_group) {
                                 if($blog_group['ID'] != -1) {
                                    ?><tr>
                                    <td><?php e($blog_group['GROUP_NAME']); 
                                    ?></td>
                                    <?php $delete_url = $base_url.
                                    '&amp;arg=deletebloggroup&amp;id=' .
                                    $blog_group['TIMESTAMP'].'&amp;gid='.
                                    $blog_group['ID']
                                    ?><td>
                                    <a href="<?php e($delete_url); ?>"><?php
                                    e(tl('editblogpages_element_deleteblog'));
                                    ?></a>
                                        </td>
                                    </tr>
                                <?php
                                }
                            }
                        }
                    } ?>
                    </table>
                </form>
        <?php
        } ?>
        <form method="post" action='#'>
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>"/>
            <input type="hidden" name="a" value="blogPages"/>
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>"/>
            <?php if(isset($data['IS_EDIT_DESC']) &&
                $data['IS_EDIT_DESC'] === true) { ?>
                <input type="hidden" name="arg" value="updatedescription"/>
            <?php }
            if(isset($data['IS_EDIT_FEED']) &&
                $data['IS_EDIT_FEED']===true) { ?>
                <input type="hidden" name="arg" value="updatefeed"/>
            <?php }
            if(isset($data['IS_ADD_BLOG']) &&
                $data['IS_ADD_BLOG']===true) {
                ?><input type="hidden" name="arg" value="addfeeditem"/>
            <?php } if(isset($data['IS_EDIT_DESC']) &&
                $data['IS_EDIT_DESC']===true) {
                ?><table class="name-table">
                    <tr><td><label for="source-type"><b><?php
                            e(tl('editblogpages_element_sourcetype'))?></b>
                        </label></td>
                        <td>
                        <?php if(isset($data['EDIT_BLOGS'])) { ?>
                        <?php $this->view->helper("options")->render(
                            "source-type",
                            "sourcetype", $data['SOURCE_TYPES'],
                        $data['EDIT_BLOGS']['TYPE']);?></td></tr>
                    <tr><th><label for="source-name"><?php
                        e(tl('editblogpages_element_typename')); ?></label></th>
                        <td><input type="text" id="source-name" name="title"
                        value = "<?php e($data['EDIT_BLOGS']['NAME']); ?>"
                        maxlength="80" class="wide-field" /></td>
                    </tr>
                    <tr><th id="locale-text">
                            <label for="source-locale-tag"><?php
                                e(tl('searchsources_element_locale_tag'))
                            ?></label>
                        </th>
                        <td>
                        <?php $this->view->helper("options")->render(
                            "source-locale-tag", "sourcelocaletag",
                            $data['LANGUAGES'], $data['SOURCE_LOCALE_TAG']); ?>
                        </td>
                    </tr>
                </table>
                <div><label for="descriptionfield"><b><?php
                    e(tl('editblogpages_element_description'));
                    ?></b></label></div>
                <textarea class="tall-text-area" id="descriptionfield"
                    name="description"><?php
                        e($data['EDIT_BLOGS']['DESCRIPTION']);
                    ?></textarea>
                <div class="center slight-pad"><button class="button-box"
                    type="submit"><?php
                    e(tl('editblogpages_element_save_page')); ?></button></div>
                <?php
            }
        } else {
            ?><table class="name-table">
                <tr><th><label><?php
                    e(tl('editblogpages_element_sourcetype'))?></label>
                    </th><td><?php if(isset($data['EDIT_BLOGS'])) {
                    ?><p><?php e($data['EDIT_BLOGS']['TYPE']); ?></p><?php }
                        ?></td>
                </tr>
                <tr><th><label><?php
                    e(tl('editblogpages_element_typename')); ?></label></th>
                    <td><?php if(isset($data['EDIT_BLOGS'])) {
                        ?><p><?php e($data['EDIT_BLOGS']['NAME']); ?></p>
                    </td>
                </tr>
                <tr><th id="locale-text"><label><?php
                    e(tl('searchsources_element_locale_tag'))?></label></th>
                    <td>
                        <p><?php e($data['SOURCE_LOCALE_TAG']); ?></p>
                    </td>
                </tr>
                <tr><th><label><?php
                        e(tl('editblogpages_element_description'));?></label>
                    </th>
                    <td><?php if(isset($data['EDIT_BLOGS'])) {
                        ?><p><?php e($data['EDIT_BLOGS']['DESCRIPTION']);?></p>
                    <?php } ?></td>
                </tr><?php } ?></table>

            <?php
            if(isset($data['IS_ADD_BLOG']) && $data['IS_ADD_BLOG']===true) {
                ?><div class="top-margin">
                <label for="source-name">
                    <b><?php e(tl('editblogpages_element_feeditems'));?></b>
                </label>
                <input type="text" id="source-name" name="title_entry"
                    value = "" maxlength="80" class="wide-field"/>
                <br/><br/>
                <textarea class="small-text-area" id="descriptionfield"
                    name="description"></textarea>
                <div class="center slight-pad">
                    <button class="button-box" type="submit"><?php
                        e(tl('editblogpages_element_save_page'));
                    ?></button>
                </div>
            </div>
            <?php
            } ?>

            <?php if(isset($data['IS_EDIT_FEED']) &&
                $data['IS_EDIT_FEED']===true) { ?>
                <table class="name-table">
                <tr><th><label for="source-name"><?php
                e(tl('editblogpages_element_feed_title')); ?></label></th><td>
                <input type="text" id="source-name" name="title"
                    value = "<?php if(isset($data['FEED_ITEMS'])
                    ){e($data['FEED_ITEMS'][0]['TITLE']);}else{e('');} ?>"
                    maxlength="80" class="wide-field" /></td></tr></table>
                <div class="top-margin"><label for="descriptionfield"><b><?php
                  e(tl('editblogpages_element_feeditems'));?></b></label></div>
                <textarea class="tall-text-area" id="descriptionfield"
                    name="description"><?php if(isset($data['FEED_ITEMS'])){
                    e($data['FEED_ITEMS'][0]['DESCRIPTION']); }
                    else { e("");}?></textarea>
                <div class="center slight-pad"><button class="button-box"
                    type="submit"><?php
                    e(tl('editblogpages_element_save_page'));
                    ?></button></div>
            <?php
            } else if (!isset($data['IS_ADD_BLOG']) ||
                $data['IS_ADD_BLOG'] !== true) {
            ?>[<a href = "<?php e($base_url.'&amp;arg=addblogentry&amp;id='.
                        $data['EDIT_BLOGS']['TIMESTAMP']); ?>"><?php
                  e(tl('editblogpages_element_add_blogentry'));?></a>]
            <?php if(isset($data['FEED_ITEMS']) &&
                count($data['FEED_ITEMS']) > 0) { ?>
                <div class="top-margin"><label><b>
                    <?php e(tl('editblogpages_element_feeditems'));?></b>
                    </label>
                </div>
                <table class="search-sources-table">
                    <tr>
                        <th><?php e(tl('editblogpages_element_title'));?></th>
                        <th><?php e(tl('editblogpages_element_description'));
                        ?></th>
                        <th><?php e(tl('editblogpages_element_action'));?></th>
                    </tr>
                    <?php foreach($data['FEED_ITEMS'] as $feed_items) {
                    ?><tr>
                        <td><?php e($feed_items['TITLE']);?></td>
                        <td><?php e($feed_items['DESCRIPTION']); ?></td>
                        <td>
                            <?php if($feed_items['IS_OWNER'] === true || (
                            isset($data['IS_OWNER']) &&
                            $data['IS_OWNER'] === true)) { ?><?php
                                $edit_blog_url = $base_url .
                                '&amp;arg=editfeed&amp;id='.
                                    $data['EDIT_BLOGS']['TIMESTAMP'].'&amp;
                                    fid=' . $feed_items['GUID']; 
                                ?><a href="<?php e($edit_blog_url); ?>"><?php
                                e(tl('editblogpages_element_editblog')); ?></a>
                         <?php } ?></td>
                        <td>
                            <?php if($feed_items['IS_OWNER'] === true ||
                            isset($data['IS_OWNER']) === true) { ?><?php 
                                $delete_url = $base_url.
                                '&amp;arg=deletefeed&amp;id=' .
                                $feed_items['GUID'];
                                ?><a href="<?php e($delete_url); ?>"><?php
                                e(tl('editblogpages_element_deleteblog'));
                                ?></a>
                        <?php } ?></td>
                    </tr>
                    <?php } ?></table>
                <?php
                }
            }
        }
        ?></form></div>
        <script type="text/javascript">
        function showOverlay()
        {
            elt('transfer').style.visibility = "visible";
        }
        function closeOverlay()
        {
            elt('transfer').style.visibility = "hidden";
        }
        </script>
    <?php
    }
}
?>
