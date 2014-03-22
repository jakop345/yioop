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
 * @author Mallika Perepa (Creator), Chris Pollett (rewrote)
 * @package seek_quarry
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Used to draw the admin screen on which users can create groups, delete
 * groups and add and delete users and roles to a group
 *
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage element
 */
class ManagegroupsElement extends Element
{
    /**
     * Renders the screen in which groups can be created, deleted, and added or
     * deleted
     *
     * @param array $data  contains antiCSRF token, as well as data on
     *      available groups or which user is in what group
     */
    function render($data)
    {
        ?>
        <div class="current-activity" >
        <?php
        switch($data['FORM_TYPE'])
        {
            case "changeowner":
                $this->renderChangeOwnerForm($data);
            break;
            case "inviteusers":
                $this->renderInviteUsersForm($data);
            break;
            case "search":
                $this->renderSearchForm($data);
            break;
            default:
                $this->renderGroupsForm($data);
        }

        $data['TABLE_TITLE'] = tl('managegroups_element_groups');
        $data['ACTIVITY'] = 'manageGroups';
        $data['VIEW'] = $this->view;
        $data['NO_FLOAT_TABLE'] = true;
        $this->view->helper("pagingtable")->render($data);
        ?>
        <table class="role-table table-margin">
            <tr>
                <th><?php e(tl('managegroups_element_groupname'));?></th>
                <th><?php e(tl('managegroups_element_groupowner'));?></th>
                <?php if(!MOBILE) { ?>
                <th><?php e(tl('managegroups_element_registertype'));?></th>
                <?php } ?>
                <th><?php e(tl('managegroups_element_memberaccess'));?></th>
                <th colspan='2'><?php
                    e(tl('managegroups_element_actions'));?></th>
            </tr>
        <?php
            $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                "&amp;a=manageGroups";
            $is_root = ($_SESSION['USER_ID'] == ROOT_ID);
            $delete_url = $base_url . "&amp;arg=deletegroup&amp;";
            $unsubscribe_url = $base_url . "&amp;arg=unsubscribe&amp;";
            $join_url = $base_url . "&amp;arg=joingroup&amp;";
            $edit_url = $base_url . "&amp;arg=editgroup&amp;";
            $transfer_url = $base_url . "&amp;arg=changeowner&amp;";
            $mobile_columns = array('GROUP_NAME', 'OWNER');
            $ignore_columns = array("GROUP_ID", "OWNER_ID", "JOIN_DATE");
            $access_columns = array("MEMBER_ACCESS");
            $dropdown_columns = array("MEMBER_ACCESS", "REGISTER_TYPE");
            $choice_arrays = array(
                "MEMBER_ACCESS" => array("ACCESS_CODES", "memberaccess"),
                "REGISTER_TYPE" =>  array("REGISTER_CODES", "registertype"),
            );
            $stretch = (MOBILE) ? 1 :2;
            foreach($data['GROUPS'] as $group) {
                echo "<tr>";
                foreach($group as $col_name => $group_column) {
                    if(in_array($col_name, $ignore_columns) || (
                        MOBILE && !in_array($col_name, $mobile_columns))) {
                        continue;
                    }
                    if(in_array($col_name, $mobile_columns)) {
                        if(strlen($group_column) >$stretch * NAME_TRUNCATE_LEN){
                            $group_column =substr($group_column, 0,
                                $stretch * NAME_TRUNCATE_LEN)."..";
                        }
                    }
                    if($col_name == "STATUS") {
                        $group_column =
                            $data['MEMBERSHIP_CODES'][$group[$col_name]];
                        if($group['STATUS'] == ACTIVE_STATUS) {
                            continue;
                        }
                    }
                    if($col_name == "MEMBER_ACCESS" &&
                        $group['STATUS'] != ACTIVE_STATUS) {
                        continue;
                    }
                    if(in_array($col_name, $dropdown_columns)) {
                        ?><td>
                        <?php
                        $choice_array = $choice_arrays[$col_name][0];
                        $arg_name = $choice_arrays[$col_name][1];

                        if($group['GROUP_ID'] == PUBLIC_GROUP_ID ||
                            $group["OWNER_ID"] != $_SESSION['USER_ID']) {
                            e("<span class='gray'>".
                                $data[$choice_array][$group[$col_name]].
                                "</span>");
                        } else {
                        ?>
                        <form  method="get" action='#' >
                        <input type="hidden" name="c" value="admin" />
                        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>"
                            value="<?php e($data[CSRF_TOKEN]); ?>" />
                        <input type="hidden" name="a" value="manageGroups" />
                        <input type="hidden" name="arg" value="<?php
                            e($arg_name); ?>" />
                        <input type="hidden" name="group_id" value="<?php
                            e($group['GROUP_ID']); ?>" />
                        <?php
                        $this->view->helper("options")->render(
                            "update-$arg_name-{$group['GROUP_ID']}",
                            $arg_name, $data[$choice_array],
                            $group[$col_name], true);
                        ?>
                        </form>
                        <?php
                        }
                        ?>
                        </td>
                        <?php
                    } else if($col_name == 'OWNER' && ($is_root ||
                        $group["OWNER_ID"] == $_SESSION['USER_ID'])) {
                        if($group['GROUP_ID'] == PUBLIC_GROUP_ID) {
                            e("<td><b>$group_column</b></td>");
                        } else {
                            e("<td><b><a href='".$transfer_url."group_id=".
                                $group['GROUP_ID']."'>$group_column".
                                "</a></b></td>");
                        }
                    } else {
                        e("<td>$group_column</td>");
                    }
                }
                ?>
                <td><?php
                    if($group['OWNER_ID']!=$_SESSION['USER_ID']||
                        $group['GROUP_NAME'] == 'Public') {
                        if($group['STATUS'] == INVITED_STATUS) {
                            ?><a href="<?php e($join_url . 'group_id='.
                                $group['GROUP_ID'].'&amp;user_id=' .
                                $_SESSION['USER_ID']); ?>"><?php
                                e(tl('managegroups_element_join'));
                            ?></a><?php
                        } else {
                            e('<span class="gray">'.
                                tl('managegroups_element_edit').'</span>');
                        }
                    } else {
                        ?><a href="<?php e($edit_url . 'group_id='.
                            $group['GROUP_ID']); ?>"><?php
                            e(tl('managegroups_element_edit'));
                    }?></a></td>
                <td><?php
                    if($group['GROUP_NAME'] == 'Public') {
                        e('<span class="gray">'.
                            tl('managegroups_element_delete').'</span>');
                    } else if($_SESSION['USER_ID']!=$group['OWNER_ID']) {?>
                        <a href="<?php e($unsubscribe_url . 'group_id='.
                            $group['GROUP_ID'].'&amp;user_id=' .
                            $_SESSION['USER_ID']); ?>"><?php
                            if($group['STATUS'] == INVITED_STATUS) {
                                e(tl('managegroups_element_decline'));
                            } else {
                                e(tl('managegroups_element_unsubscribe'));
                            }
                        ?></a></td><?php
                    }  else {?>
                        <a href="<?php e($delete_url . 'group_id='.
                        $group['GROUP_ID']); ?>"><?php
                        e(tl('managegroups_element_delete'));
                    }?></a></td>
                </tr>
            <?php
            }
        ?>
        </table>
        <?php if(MOBILE) { ?>
            <div class="clear">&nbsp;</div>
        <?php } ?>
        </div>
        <?php
    }


    /**
     *  Draws the add groups and edit groups forms
     *
     *  @param array $data consists of values of groups fields set
     *      so far as well as values of the drops downs on the form
     */
    function renderGroupsForm($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageGroups";
        $editgroup = ($data['FORM_TYPE'] == "editgroup") ? true: false;
        $creategroup = ($data['FORM_TYPE'] == "creategroup") ? true: false;
        if($editgroup) {
            e("<div class='float-opposite'><a href='$base_url'>".
                tl('managegroups_element_addgroup_form')."</a></div>");
            e("<h2>".tl('managegroups_element_group_info'). "</h2>");
        } else if($creategroup) {
            e("<h2>".tl('managegroups_element_create_group'). "</h2>");
        } else {
            e("<h2>".tl('managegroups_element_add_group'). "</h2>");
        }

        ?>
        <form id="addGroupForm" method="post" action='./'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="arg" value="<?php
            e($data['FORM_TYPE']);?>" />
        <input type="hidden" name="group_id" value="<?php
            e($data['CURRENT_GROUP']['id']); ?>" />
        <table class="name-table">
        <tr><th class="table-label"><label for="group-name"><?php
            e(tl('managegroups_element_groupname'))?></label>:</th>
            <td><input type="text" id="group-name"
                name="name"  maxlength="80"
                value="<?php e($data['CURRENT_GROUP']['name']); ?>"
                class="narrow-field" <?php
                if($editgroup) {
                    e(' disabled="disabled" ');
                }
                ?> /></td></tr>
        <?php if($creategroup || $editgroup) { ?>
            <tr><th class="table-label"><label for="register-type"><?php
                e(tl('managegroups_element_register'))?></label>:</th>
                <td><?php
                    $this->view->helper("options")->render(
                        "register-type", "register", $data["REGISTER_CODES"],
                         $data['CURRENT_GROUP']['register']);
                    ?></td></tr>
            <tr><th class="table-label"><label for="member-access"><?php
                e(tl('managegroups_element_memberaccess'))?></label>:</th>
                <td><?php
                    $this->view->helper("options")->render(
                        "member-access", "member_access", $data["ACCESS_CODES"],
                        $data['CURRENT_GROUP']['member_access']);
                    ?></td></tr>
        <?php
        }
        if($editgroup) {
        ?>
            <tr><th class="table-label" style="vertical-align:top"><?php
                    e(tl('managegroups_element_group_users')); ?>:</th>
                <td><div class='light-gray-box'><table><?php
                $stretch = (MOBILE) ? 1 :2;
                foreach($data['GROUP_USERS'] as $user_array) {
                    $action_url = $base_url."&amp;user_id=" .
                        $user_array['USER_ID'] . "&amp;group_id=".
                        $data['CURRENT_GROUP']['id'];
                    $out_name = $user_array['USER_NAME'];
                    if(strlen($out_name) > $stretch * NAME_TRUNCATE_LEN) {
                        $out_name =substr($out_name, 0,
                            $stretch * NAME_TRUNCATE_LEN)."..";
                    }
                    e("<tr><td><b>".
                        $out_name.
                        "</b></td>");
                    if($data['CURRENT_GROUP']['owner'] ==
                        $user_array['USER_NAME']) {
                        e("<td>".
                            $data['MEMBERSHIP_CODES'][$user_array['STATUS']] .
                            "</td>");
                        e("<td>" . tl('managegroups_element_groupowner') .
                            "</td><td><span class='gray'>".
                            tl('managegroups_element_delete')."</span></td>");
                    } else {
                        e("<td>".$data['MEMBERSHIP_CODES'][
                            $user_array['STATUS']]);
                        e("</td>");
                        switch($user_array['STATUS'])
                        {
                            case INACTIVE_STATUS:
                                e("<td><a href='$action_url".
                                    "&amp;arg=activateuser'>".
                                    tl('managegroups_element_activate').
                                    '</a></td>');
                            break;
                            case ACTIVE_STATUS:
                                e("<td><a href='$action_url".
                                    "&amp;arg=banuser'>".
                                    tl('managegroups_element_ban').
                                    '</a></td>');
                            break;
                            case BANNED_STATUS:
                                e("<td><a href='$action_url".
                                    "&amp;arg=reinstateuser'>".
                                    tl('managegroups_element_unban')
                                    .'</a></td>');
                            break;
                            default:
                            e("<td></td>");
                            break;
                        }

                        e("<td><a href='$action_url&amp;arg=deleteuser'>".
                            tl('managegroups_element_delete')."</a></td>");
                    }
                    e("</tr>");
                }
                $center = (MOBILE) ? "" : 'class="center"';
                if(isset($data['NUM_USERS_GROUP']) && 
                    $data['NUM_USERS_GROUP'] > NUM_RESULTS_PER_PAGE) {
                    $limit = isset($data['GROUP_LIMIT']) ? $data['GROUP_LIMIT']:
                        0;
                ?>
                    <tr>
                    <td class="right"><?php
                        if($limit >= NUM_RESULTS_PER_PAGE) {
                            ?><a href='<?php e(
                            "$action_url&amp;arg=editgroup&amp;group_limit=".
                            ($limit - NUM_RESULTS_PER_PAGE)); ?>'
                            >&lt;&lt;</a><?php
                        }
                        ?>
                        </td>
                        <td colspan="2" class="center">
                            <form method="GET" action="."><input type="hidden"
                                name="change_filter" value="true"
                            /><input
                            class="very-narrow-field center" name="user_filter"
                            type="text" max-length="10" value='<?php
                            e($data['USER_FILTER']); ?>' /><br />
                            <button type="submit"><?php
                            e(tl('managegroups_element_filter')); ?></button>
                            </form>
                            </td>
                        <td class="left"><?php
                        if($data['NUM_USERS_GROUP'] >= $limit +
                            NUM_RESULTS_PER_PAGE) {
                            ?><a href='<?php e(
                            "$action_url&amp;arg=editgroup&amp;group_limit=".
                            ($limit + NUM_RESULTS_PER_PAGE)); ?>'>&gt;&gt;</a>
                        <?php
                        }
                        ?>
                        </td>
                    </tr>
                <?php
                }
                ?>
                <tr>
                <td colspan="4" <?php e($center); ?>>&nbsp;&nbsp;[<?php
                e("<a href='$action_url&amp;arg=inviteusers'>".
                    tl('managegroups_element_invite')."</a>");
                ?>]&nbsp;&nbsp;</td>
                </tr>
                </table>
                </div>
                </td></tr>
        <?php
        }
        ?>
        <tr><td></td><td class="center"><button class="button-box"
            type="submit"><?php e(tl('managegroups_element_save'));
            ?></button></td>
        </tr>
        </table>
        </form>
        <?php
    }

    /**
     *  Draws form used to invite users to the current group
     *  @param array $data from the admin controller with a
     *      'CURRENT_GROUP' field providing information about the
     *      current group as well as info about the current CSRF_TOKEN
     */
    function renderInviteUsersForm($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageGroups&amp;arg=editgroup&amp;group_id=".
            $data['CURRENT_GROUP']['id'];
        ?>
        <div class='float-opposite'><a href='<?php e($base_url); ?>'><?php
            e(tl('managegroups_element_group_info')); ?></a></div>
        <h2><?php e(tl('managegroups_element_invite_users_group')); ?></h2>
        <form id="addGroupForm" method="post" action='./'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="arg" value="<?php
            e($data['FORM_TYPE']);?>" />
        <input type="hidden" name="group_id" value="<?php
            e($data['CURRENT_GROUP']['id']); ?>" />
        <div>
        <b><label for="group-name"><?php
            e(tl('managegroups_element_groupname'))?></label>:</b>
            <input type="text" id="group-name"
                name="name"  maxlength="80"
                value="<?php e($data['CURRENT_GROUP']['name']); ?>"
                class="narrow-field" disabled="disabled" />
        </div>
        <div>
        <b><label for="users-names"><?php
            e(tl('managegroups_element_usernames')); ?></label></b>
        </div>
        <?php $center = (!MOBILE) ? 'class="center"' : ""; ?>
        <div <?php e($center); ?>>
        <textarea class="short-text-area" id='users-names'
            name='users_names'></textarea>
        <button class="button-box"
            type="submit"><?php e(tl('managegroups_element_invite'));
            ?></button>
        </form>
        </div>
        <?php
    }


    /**
     *  Draws the form used to change the owner of a group
     *  @param array $data from the admin controller with a
     *      'CURRENT_GROUP' field providing information about the
     *      current group as well as info about the current CSRF_TOKEN
     */
    function renderChangeOwnerForm($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageGroups";
        ?>
        <div class='float-opposite'><a href='<?php e($base_url); ?>'><?php
            e(tl('managegroups_element_addgroup_form')); ?></a></div>
        <h2><?php e(tl('managegroups_element_transfer_group_owner')); ?></h2>
        <form id="addGroupForm" method="post" action='./'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="arg" value="<?php
            e($data['FORM_TYPE']);?>" />
        <input type="hidden" name="group_id" value="<?php
            e($data['CURRENT_GROUP']['id']); ?>" />
        <table class="name-table">
        <tr>
            <th class="table-label"><label for="group-name"><?php
                e(tl('managegroups_element_groupname'))?></label>:</th>
            <td><input type="text" id="group-name"
                name="name"  maxlength="80"
                value="<?php e($data['CURRENT_GROUP']['name']); ?>"
                class="narrow-field" disabled="disabled" /></td>
        </tr>
        <tr>
            <th class="table-label"><label for="new-owner"><?php
                e(tl('managegroups_element_new_group_owner')); ?></label>:</th>
            <td><input type="text"  id='new-owner'
                name='new_owner' maxlength="80" class="narrow-field" /></td>
        </tr>
        <tr>
            <th>&nbsp;</th><td>&nbsp;<button class="button-box"
                type="submit"><?php e(tl('managegroups_element_change_owner'));
                ?></button></td>
        </tr>
        </table>
        </form>
        <?php
    }

    /**
     *  Draws the search for roles forms
     *
     *  @param array $data consists of values of role fields set
     *      so far as well as values of the drops downs on the form
     */
    function renderSearchForm($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageGroups";
        e("<div class='float-opposite'><a href='$base_url'>".
            tl('managegroups_element_addgroup_form')."</a></div>");
        e("<h2>".tl('managegroups_element_search_group'). "</h2>");
        $item_sep = (MOBILE) ? "<br />" : "</td><td>";
        ?>
        <form id="searchForm" method="post" action='./' autocomplete="off">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="arg" value="search" />
        <table class="name-table">
        <tr><td class="table-label"><label for="group-name"><?php
            e(tl('managegroups_element_groupname'))?>:</label>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
                    "name-comparison", "name_comparison",
                    $data['COMPARISON_TYPES'],
                    $data['name_comparison']);
                e($item_sep);
            ?><input type="text" id="group-name"
                name="name"  maxlength="80"
                value="<?php e($data['name']); ?>"
                class="narrow-field"  />
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
                    "name-sort", "name_sort",
                    $data['SORT_TYPES'],
                    $data['name_sort']);
            ?></td></tr>
        <tr><td class="table-label"><label for="owner-name"><?php
            e(tl('managegroups_element_groupowner')); ?>:</label>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
                    "owner-comparison", "owner_comparison",
                    $data['COMPARISON_TYPES'],
                    $data['owner_comparison']);
                e($item_sep);
            ?><input type="text" id="owner-name"
                name="owner"  maxlength="80"
                value="<?php e($data['owner']); ?>"
                class="narrow-field"  />
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
                    "owner-sort", "owner_sort",
                    $data['SORT_TYPES'],
                    $data['owner_sort']);
            ?></td></tr>
        <tr><td class="table-label"><label for="search-registertype"><?php
                e(tl('managegroups_element_registertype')); ?>:</label>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
                    "register-comparison", "register_comparison",
                    $data['DROPDOWN_COMPARISON_TYPES'],
                    $data['register_comparison']);
            ?>
            <style type="text/css">
            #register-comparison {
                width:100%;
            }
            </style>
            <?php
            e($item_sep);
            $this->view->helper("options")->render(
                "search-registertype",
                "register", $data['REGISTER_CODES'],
                $data['register']);
            ?>
            <style type="text/css">
            #search-registertype {
                width:100%
            }
            </style>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
                    "register-sort", "register_sort",
                    $data['SORT_TYPES'],
                    $data['register_sort']);
            ?></td></tr>
        <tr><td class="table-label"><label for="search-groupaccess"><?php
                e(tl('manageusers_element_member_access')); ?>:</label>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
                    "access-comparison", "access_comparison",
                    $data['DROPDOWN_COMPARISON_TYPES'],
                    $data['access_comparison']);
            ?>
            <style type="text/css">
            #access-comparison {
                width:100%;
            }
            </style>
            <?php
            e($item_sep);
            $this->view->helper("options")->render(
                "search-groupaccess",
                "access", $data['ACCESS_CODES'],
                $data['access']);
            ?>
            <style type="text/css">
            #search-groupaccess {
                width:100%
            }
            </style>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
                    "access-sort", "access_sort",
                    $data['SORT_TYPES'],
                    $data['access_sort']);
            ?></td></tr>
        <tr><?php if(!MOBILE) {?><td></td><td></td> <?php } ?>
            <td <?php if(!MOBILE) {
                    ?>class="center" <?php
                }
                ?>><button class="button-box"
                type="submit"><?php e(tl('managegroups_element_search'));
                ?></button></td>
        </tr>
        </table>
        </form>
        <?php
    }
}
?>
