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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Element responsible for displaying the user account features
 * that someone can modify for their own SeekQuarry/Yioop account.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
class ManageaccountElement extends Element
{
    /**
     * Draws a view with a summary of a user's account together with
     * a form for updating user info such as password as well as with
     * useful links for groups, etc
     *
     * @param array $data anti-CSRF token
     */
    function render($data)
    {
        $token = CSRF_TOKEN . "=". $data[CSRF_TOKEN];
        $settings_url = "?c=settings&amp;$token&amp;return=manageAccount";
        $feed_url = "?c=admin&amp;a=groupFeeds&amp;$token";
        $group_url = "?c=admin&amp;a=manageGroups&amp;$token";
        $mix_url = "?c=admin&amp;a=mixCrawls&amp;$token";
        $crawls_url = "?c=admin&amp;a=manageCrawls&amp;$token";
        $base_url = "?c=admin&amp;a=manageAccount&amp;$token";
        $edit_or_no_url = $base_url .(
            (isset($data['EDIT_USER'])) ? "&amp;edit=false":"&amp;edit=true");
        $edit_or_no_text = (isset($data['EDIT_USER'])) ?
            tl('manageaccount_element_lock') : tl('manageaccount_element_edit');
        $password_or_no_url = $base_url .(
            (isset($data['EDIT_PASSWORD'])) ? "&amp;edit_pass=false":
            "&amp;edit_pass=true");
        $disabled = (isset($data['EDIT_USER'])) ? "" : "disabled='disabled'";
        ?>
        <div class="current-activity">
            <h2><?php e(tl('manageaccount_element_welcome',
                $data['USERNAME'])); ?></h2>
            <p><?php e(tl('manageaccount_element_what_can_do')); ?></p>
            <h2><?php
                e(tl('manageaccount_element_account_details')); ?> <small>[<a
                href="<?php e($edit_or_no_url); ?>"><?php
                e($edit_or_no_text); ?></a>]</small></h2>
            <?php
            if(isset($data['EDIT_PASSWORD']) &&
                AUTHENTICATION_MODE == ZKP_AUTHENTICATION) { ?>
                <form action="#" method="post"
                    onsubmit="registration('new-password','retype-password',
                    'fiat-shamir-modulus')">
                <input type="hidden" name="fiat_shamir_modulus"
                    id="fiat-shamir-modulus"
                    value="<?php e($data['FIAT_SHAMIR_MODULUS']) ?>"/>
                <?php 
            } else { ?>
                <form id="changeUserForm" method="post" action='#'
                    autocomplete="off" enctype="multipart/form-data">
            <?php
            }?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="manageAccount" />
            <input type="hidden" name="arg" value="updateuser" />

            <table class="name-table">
            <tr>
            <td rowspan="8"><img class='user-icon' id='current-icon'
                src="<?php e($data['USER']['USER_ICON']); ?>" alt="<?php
                    e(tl('manageaccounts_element_icon')); ?>" /><?php
                if(isset($data['EDIT_USER'])) {
                    ?>
                    <div id='upload-info'></div>
                    <input type="file" class='icon-upload' id='user-icon'
                        name='user_icon' onchange="checkUploadIcon()" />
                    <?php
                }
                ?></td>
            <th class="table-label"><label for="user-name"><?php
                e(tl('manageusers_element_username'))?>:</label></th>
                <td><input type="text" id="user-name"
                    name="user_name"  maxlength="80"
                    value="<?php e($data['USER']['USER_NAME']); ?>"
                    class="narrow-field" disabled="disabled" /></td>
                    </tr>
            <tr><th class="table-label"><label for="first-name"><?php
                    e(tl('manageusers_element_firstname')); ?>:</label></th>
                <td><input type="text" id="first-name"
                    name="FIRST_NAME"  maxlength="80"
                    value="<?php e($data['USER']['FIRST_NAME']); ?>"
                    class="narrow-field" <?php e($disabled);?> /></td></tr>
            <tr><th class="table-label"><label for="last-name"><?php
                    e(tl('manageusers_element_lastname')); ?>:</label></th>
                <td><input type="text" id="last-name"
                    name="LAST_NAME"  maxlength="80"
                    value="<?php e($data['USER']['LAST_NAME']); ?>"
                    class="narrow-field" <?php e($disabled);?> /></td></tr>
            <tr><th class="table-label"><label for="e-mail"><?php
                    e(tl('manageusers_element_email')); ?>:</label></th>
                <td><input type="text" id="e-mail"
                    name="EMAIL"  maxlength="80" <?php e($disabled);?>
                    value="<?php e($data['USER']['EMAIL']); ?>"
                    class="narrow-field"/></td></tr>
            <?php if(isset($data['EDIT_USER'])) { ?>
            <tr><th class="table-label"><label for="password"><a href="<?php
                e($password_or_no_url);?>"><?php
                e(tl('manageaccount_element_password'))?></a></label></th>
                <td><input type="password" id="password"
                    name="password"  maxlength="80" class="narrow-field"/>
                </td></tr>
                <?php if(isset($data['EDIT_PASSWORD'])) { ?>
                <tr><th class="table-label"><label for="new-password"><?php
                    e(tl('manageaccount_element_new_password'))?></label></th>
                    <td><input type="password" id="new-password"
                        name="new_password"  maxlength="80"
                        class="narrow-field"/>
                    </td></tr>
                <tr><th class="table-label"><label for="retype-password"><?php
                    e(tl('manageaccount_element_retype_password'));
                    ?></label></th>
                    <td><input type="password" id="retype-password"
                        name="retype_password"  maxlength="80"
                        class="narrow-field" />
                    </td></tr>
                <?php
                }
                ?>
            <tr><td></td>
                <td class="center"><button
                    class="button-box" type="submit"><?php
                    e(tl('manageaccount_element_save')); ?></button></td></tr>
            <?php
            } ?>
            </table>
            </form>
            <p>[<a href="<?php e($settings_url); ?>"><?php
                e(tl('manageaccount_element_search_lang_settings')); ?></a>]</p>
            <?php
            if(isset($data['CRAWL_MANAGER']) && $data['CRAWL_MANAGER']) {
                ?>
                <h2><?php
                e(tl('manageaccount_element_crawl_and_index')); ?></h2>
                <p><?php e(tl('manageaccount_element_crawl_info')); ?></p>
                <p><?php e(tl('manageaccount_element_num_crawls',
                    $data["CRAWLS_RUNNING"], $data["NUM_CLOSED_CRAWLS"]));?></p>
                <p>[<a href="<?php e($crawls_url); ?>"><?php
                    e(tl('manageaccount_element_manage_crawls'));
                    ?></a>]</p>
                <?php
            }
            ?>
            <h2><?php
                e(tl('manageaccount_element_groups_and_feeds'))?></h2>
            <p><?php e(tl('manageaccount_element_group_info')); ?></p>
            <p><?php if($data['NUM_GROUPS'] > 1 || $data['NUM_GROUPS'] == 0) {
                e(tl('manageaccount_element_num_groups',
                    $data['NUM_GROUPS']));
            } else {
                e(tl('manageaccount_element_num_group',
                    $data['NUM_GROUPS']));
            }?></p>
            <?php
            foreach($data['GROUPS'] as $group) {
                ?>
                <div class="access-result">
                    <div><b><a href="<?php
                    e($feed_url.'&amp;just_group_id='.$group['GROUP_ID']); ?>"
                    rel="nofollow"><?php e($group['GROUP_NAME']);
                    ?></a> (<?php e(tl('manageaccount_element_group_stats',
                        $group['NUM_POSTS'], $group['NUM_THREADS']) ); ?>)</b>
                    </div>
                    <div class="slight-pad">
                    <b><?php
                    e(tl('manageaccount_element_last_post')); ?></b>
                    <a href="<?php
                    e($feed_url.'&amp;just_thread='.$group['THREAD_ID']); ?>"
                    ><?php e($group['ITEM_TITLE']); ?></a>
                    </div>
                </div>
                <?php
            }
            ?>
            <p>[<a href="<?php e($group_url); ?>"><?php
                e(tl('manageaccount_element_manage_all_groups'));
                ?></a>] [<a href="<?php e($feed_url); ?>"><?php
                e(tl('manageaccount_element_go_to_group_feed')); ?></a>]</p>
            <h2><?php
                e(tl('manageaccount_element_crawl_mixes'))?></h2>
            <p><?php e(tl('manageaccount_element_mixes_info')); ?></p>
            <p><?php if($data['NUM_MIXES'] > 1 || $data['NUM_MIXES'] == 0) {
                e(tl('manageaccount_element_num_mixes',
                    $data['NUM_MIXES']));
            } else {
                e(tl('manageaccount_element_num_mix',
                    $data['NUM_MIXES']));
            }?></p>
            <p>[<a href="<?php e($mix_url); ?>"><?php
                e(tl('manageaccount_element_manage_mixes'));
                ?></a>]</p>
        </div>
        <script type="text/javascript">
        function checkUploadIcon()
        {
            var max_icon_size = <?php e(THUMB_SIZE) ?>;
            var upload_icon = elt('user-icon').files[0];
            var upload_info = elt('upload-info');
            if(upload_icon.type != 'image/png' &&
                upload_icon.type != 'image/jpeg' &&
                upload_icon.type != 'image/gif') {
                doMessage('<h1 class=\"red\" ><?php
                    e(tl("manageaccount_element_invalid_filetype")); ?></h1>');
                elt('user-icon').files[0] = NULL;
                return;
            }
            if(upload_icon.size > max_icon_size) {
                doMessage('<h1 class=\"red\" ><?php
                    e(tl("manageaccount_element_file_too_big")); ?></h1>');
                elt('user-icon').files[0] = NULL;
                return;
            }
            setDisplay('current-icon', false);
            upload_info.className = "upload-info";
            upload_info.innerHTML = upload_icon.names;
        }
        </script>
        <?php
    }
}
?>
