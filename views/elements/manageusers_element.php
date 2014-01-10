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
 *  Element responsible for drawing the activity screen for User manipulation
 *  in the AdminView.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManageusersElement extends Element
{

    /**
     * draws a screen in which an admin can add users, delete users,
     * and manipulate user roles.
     *
     * @param array $data info about current users and current roles, CSRF token
     */
    function render($data)
    {
    ?>
        <div class="current-activity">
        <?php
        if($data['FORM_TYPE'] == "searchusers") {
            $this->renderSearchForm($data);
        } else {
            $this->renderUserForm($data);
        }
        if(MOBILE) {
            $this->mobileTitleNumUserControls($data);
        } else {
            $this->desktopTitleNumUserControls($data);
        }
        ?>
        <table class="role-table">
            <tr>
                <th><?php e(tl('manageusers_element_username'));?></th>
                <?php if(!MOBILE) { ?>
                <th><?php e(tl('manageusers_element_firstname'));?></th>
                <th><?php e(tl('manageusers_element_lastname'));?></th>
                <th><?php e(tl('manageusers_element_email'));?></th>
                <?php } ?>
                <th><?php e(tl('manageusers_element_status'));?></th>
                <th colspan='2'><?php 
                    e(tl('manageusers_element_actions'));?></th>
            </tr>
        <?php
            $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                "&amp;a=manageUsers";
            $delete_url = $base_url . "&amp;arg=deleteuser&amp;";
            $edit_url = $base_url . "&amp;arg=edituser&amp;";
            $mobile_columns = array('USER_NAME', 'STATUS');
            foreach($data['USERS'] as $user) {
                echo "<tr>";
                foreach($user as $colname => $user_column) {
                    if(MOBILE && !in_array($colname, $mobile_columns)) {
                        continue;
                    }
                    if(strcmp($colname,"STATUS") == 0) {
                        ?><td>
                        <?php
                        if($user['USER_NAME'] == 'root') {
                            e("<span class='gray'>&nbsp;&nbsp;".
                                $data['STATUS_CODES'][$user['STATUS']].
                                "</span>");
                        } else {
                        ?>
                        <form  method="get" action='#' >
                        <input type="hidden" name="c" value="admin" />
                        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" 
                            value="<?php e($data[CSRF_TOKEN]); ?>" />
                        <input type="hidden" name="a" value="manageUsers" />
                        <input type="hidden" name="arg" value="updatestatus" />
                        <input type="hidden" name="user_name" value="<?php
                            e($user['USER_NAME']); ?>" />
                        <?php
                        $this->view->optionsHelper->render(
                            "update-userstatus-{$user['USER_NAME']}",
                            "userstatus", $data['STATUS_CODES'],
                            $user['STATUS'], true);
                        ?>
                        </form>
                        <?php
                        }
                        ?>
                        </td>
                        <?php
                    } else {
                        echo "<td>$user_column</td>";
                    }
                }
                ?>
                <td><a href="<?php e($edit_url . 'user_name='.
                    $user['USER_NAME']); ?>"><?php
                    e(tl('manageusers_element_edit'));
                    ?></a></td>
                <td><?php
                    if($user['USER_NAME'] == 'root') {
                        e('<span class="gray">'.
                            tl('manageusers_element_delete').'</span>');
                    } else {
                    ?>
                        <a href="<?php e($delete_url . 'user_name='.
                        $user['USER_NAME']); ?>"><?php 
                        e(tl('manageusers_element_delete'));
                    }?></a></td>
                </tr>
            <?php
            }
        ?>
        </table>
        <script type="text/javascript">
        function submitViewUserRole()
        {
            elt('viewUserRoleForm').submit();
        }
        </script>
        </div>
        <?php
    }

    /**
     *
     */
    function mobileTitleNumUserControls($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageUsers";
        ?>
        <h2><?php e(tl('manageusers_element_users')); ?>&nbsp;&nbsp;[<a 
                href="<?php e($base_url . '&amp;arg=searchusers');
                ?>"><?php e(tl('manageusers_element_search'));?></a>]</h2>
        <div>
            <form  method="get" action='#' >
            <?php
            $bound_url = $base_url."&amp;arg=".$data['FORM_TYPE'];
            if(isset($data['CURRENT_USER']['user_name']) && 
                $data['CURRENT_USER']['user_name'] != "") {
                $bound_url .="&amp;user_name=".$data['CURRENT_USER'][
                    'user_name'];
            } ?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" 
                value="<?php e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="manageUsers" />
            <?php
            e("<b>".tl('manageusers_element_show')."</b>");
            $this->view->optionsHelper->render(
                "users-show", "users_show", $data['USERS_SHOW_CHOICES'],
                $data['users_show'], true);
            e("<br />");
            if($data['START_ROW'] > 0) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['PREV_START']); ?>&amp;end_row=<?php 
                    e($data['PREV_END']); ?>&amp;users_show=<?php 
                    e($data['users_show'].$data['PAGING']); ?>">&lt;&lt;</a>
                <?php
            }
            e("<b>".tl('manageusers_element_row_range', $data['START_ROW'],
                $data['END_ROW'], $data['NUM_USERS'])."</b>");
            if($data['END_ROW'] < $data['NUM_USERS']) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['NEXT_START']); ?>&amp;end_row=<?php 
                    e($data['NEXT_END']); ?>&amp;users_show=<?php 
                    e($data['users_show'].$data['PAGING']); ?>" >&gt;&gt;</a>
                <?php
            }
            ?>
            </form>
        </div>
        <?php
    }

    /**
     *
     */
    function desktopTitleNumUserControls($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageUsers";
        ?>
        <div class="table-margin float-opposite">
            <form  method="get" action='#' >
            <?php
            $bound_url = $base_url."&amp;arg=".$data['FORM_TYPE'];
            if(isset($data['CURRENT_USER']['user_name']) && 
                $data['CURRENT_USER']['user_name'] != "") {
                $bound_url .="&amp;user_name=".$data['CURRENT_USER'][
                    'user_name'];
            }
            if($data['START_ROW'] > 0) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['PREV_START']); ?>&amp;end_row=<?php 
                    e($data['PREV_END']); ?>&amp;users_show=<?php 
                    e($data['users_show'].$data['PAGING']); ?>">&lt;&lt;</a>
                <?php
            }
            e("<b>".tl('manageusers_element_row_range', $data['START_ROW'],
                $data['END_ROW'], $data['NUM_USERS'])."</b>");
            if($data['END_ROW'] < $data['NUM_USERS']) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['NEXT_START']); ?>&amp;end_row=<?php 
                    e($data['NEXT_END']); ?>&amp;users_show=<?php 
                    e($data['users_show'].$data['PAGING']); ?>" >&gt;&gt;</a>
                <?php
            }
            ?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" 
                value="<?php e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="manageUsers" />
            <?php
                e("<b>".tl('manageusers_element_show')."</b>");
                $this->view->optionsHelper->render(
                    "users-show", "users_show", $data['USERS_SHOW_CHOICES'],
                    $data['users_show'], true);
            ?>
            [<a href="<?php e($base_url . '&amp;arg=searchusers');
                ?>"><?php e(tl('manageusers_element_search'));?></a>]
            </form>
        </div>
        <h2><?php e(tl('manageusers_element_users')); ?></h2>
        <?php
    }

    /**
     *  Draws the add user and edit user forms
     *
     *  @param array $data consists of values of user fields set
     *      so far as well as values of the drops downs on the form
     */
    function renderUserForm($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageUsers";
        $edituser = ($data['FORM_TYPE'] == "edituser") ? true: false;
        if($edituser) {
            e("<div class='float-opposite'><a href='$base_url'>".
                tl('manageusers_element_adduser_form')."</a></div>");
            e("<h2>".tl('manageusers_element_user_info'). "</h2>");
        } else {
            e("<h2>".tl('manageusers_element_add_user'). "</h2>");
        }
        ?>
        <form id="userForm" method="post" action='#' autocomplete="off">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageUsers" />
        <input type="hidden" name="arg" value="<?php 
            e($data['FORM_TYPE']);?>" />
        <table class="name-table">
        <tr><td><label for="user-name"><?php
            e(tl('manageusers_element_username'))?>:</label></td>
            <td><input type="text" id="user-name"
                name="user_name"  maxlength="80"
                value="<?php e($data['CURRENT_USER']['user_name']); ?>"
                class="narrow-field" <?php
                if($edituser) {
                    e(' disabled="disabled" ');
                }
                ?> /></td></tr>
        <tr><td><label for="first-name"><?php
                e(tl('manageusers_element_firstname')); ?>:</label></td>
            <td><input type="text" id="first-name"
                name="first_name"  maxlength="80"
                value="<?php e($data['CURRENT_USER']['first_name']); ?>"
                class="narrow-field"/></td></tr>
        <tr><td><label for="last-name"><?php
                e(tl('manageusers_element_lastname')); ?>:</label></td>
            <td><input type="text" id="last-name"
                name="last_name"  maxlength="80"
                value="<?php e($data['CURRENT_USER']['last_name']); ?>"
                class="narrow-field"/></td></tr>
        <tr><td><label for="e-mail"><?php
                e(tl('manageusers_element_email')); ?>:</label></td>
            <td><input type="text" id="e-mail"
                name="email"  maxlength="80"
                value="<?php e($data['CURRENT_USER']['email']); ?>"
                class="narrow-field"/></td></tr>
        <tr><td><label for="update-userstatus-currentuser"><?php
                e(tl('manageusers_element_status')); ?>:</label></td>
            <td><?php
                if($data['CURRENT_USER']['user_name'] == 'root') {
                    e("<div class='light-gray-box'><span class='gray'>".
                        $data['STATUS_CODES'][$data['CURRENT_USER']['status']].
                        "</span></div><input type='hidden' name='status' ".
                        "value='".$data['CURRENT_USER']['status']."' />");
                } else {
                    $this->view->optionsHelper->render(
                        "update-userstatus-currentuser",
                        "status", $data['STATUS_CODES'],
                        $data['CURRENT_USER']['status']);
                }?></td></tr>
        <?php
        if($edituser) {
        ?>
            <tr><td style="vertical-align:top"><?php
                    e(tl('manageusers_element_roles')); ?>:</td>
                <td><div class='light-gray-box'><table><?php
                foreach($data['SELECT_ROLES'] as $role_array) {
                    e("<tr><td><b>".
                        $role_array['ROLE_NAME'].
                        "</b></td>");
                    if($data['CURRENT_USER']['user_name'] == 'root' &&
                        $role_array['ROLE_NAME'] == 'Admin') {
                        e("<td><span class='gray'>".
                            tl('manageusers_element_delete')."</span></td>");
                    } else {
                        e("<td><a href='?c=admin&amp;a=manageUsers".
                            "&amp;arg=deleteuserrole&amp;selectrole=".
                            $role_array['ROLE_ID']);
                        e("&amp;user_name=".$data['CURRENT_USER']['user_name'].
                            "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                            "'>Delete</a></td>");
                    }
                    e("</tr>");
                }
                ?>
                </table>
                <?php $this->view->optionsHelper->render("add-userrole",
                        "selectrole", $data['AVAILABLE_ROLES'],
                        $data['SELECT_ROLE']); ?>
                </div>
                </td></tr>
            <tr><td style="vertical-align:top"><?php
                    e(tl('manageusers_element_groups')); ?>:</td>
                <td><div class='light-gray-box'><table><?php
                foreach($data['SELECT_GROUPS'] as $group_array) {
                    e("<td><a href='?c=admin&amp;a=manageUsers".
                        "&amp;arg=deleteusergroup&amp;selectgroup=".
                        $group_array['GROUP_ID']);
                    e("&amp;user_name=".$data['CURRENT_USER']['user_name'].
                        "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                        "'>".tl('manageusers_element_delete')."</a></td>");
                }
                ?>
                </table>
                <?php $this->view->optionsHelper->render("add-usergroup",
                        "selectgroup", $data['AVAILABLE_GROUPS'],
                        $data['SELECT_GROUP']); ?>
                </div>
                </td></tr>
        <?php
        }
        ?>
        <tr><td><label for="pass-word"><?php
             e(tl('manageusers_element_password'))?>:</label></td>
            <td><input type="password" id="pass-word"
                name="password" maxlength="80"
                value="<?php e($data['CURRENT_USER']['password']); ?>"
                class="narrow-field"/></td></tr>
        <tr><td><label for="retype-password"><?php
                e(tl('manageusers_element_retype_password'))?>:</label></td>
            <td><input type="password" id="retype-password"
                name="retypepassword" maxlength="80"
                value="<?php e($data['CURRENT_USER']['password']); ?>"
                class="narrow-field"/></td></tr>

        <tr><td></td><td class="center"><button class="button-box"
            type="submit"><?php e(tl('manageusers_element_save'));
            ?></button></td>
        </tr>
        </table>
        </form>
        <?php
    }

    /**
     *  Draws the search for users forms
     *
     *  @param array $data consists of values of user fields set
     *      so far as well as values of the drops downs on the form
     */
    function renderSearchForm($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageUsers";
        e("<div class='float-opposite'><a href='$base_url'>".
            tl('manageusers_element_adduser_form')."</a></div>");
        e("<h2>".tl('manageusers_element_search_user'). "</h2>");
        $item_sep = (MOBILE) ? "<br />" : "</td><td>";
        ?>
        <form id="userForm" method="post" action='#' autocomplete="off">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageUsers" />
        <input type="hidden" name="arg" value="<?php
            e($data['FORM_TYPE']);?>" />
        <table class="name-table">
        <tr><td><label for="user-name"><b><?php
            e(tl('manageusers_element_username'))?>:</b></label>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "user-comparison", "user_comparison", 
                    $data['COMPARISON_TYPES'],
                    $data['user_comparison']);
                e($item_sep);
            ?><input type="text" id="user-name"
                name="user_name"  maxlength="80"
                value="<?php e($data['user_name']); ?>"
                class="narrow-field"  />
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "user-sort", "user_sort", 
                    $data['SORT_TYPES'],
                    $data['user_sort']);
            ?></td></tr>
        <tr><td><label for="first-name"><b><?php
                e(tl('manageusers_element_firstname')); ?>:</b></label>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "first-comparison", "first_comparison", 
                    $data['COMPARISON_TYPES'],
                    $data['first_comparison']);
                e($item_sep);
            ?><input type="text" id="first-name"
                name="first_name"  maxlength="80"
                value="<?php e($data['first_name']); ?>"
                class="narrow-field"/>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "first-sort", "first_sort", 
                    $data['SORT_TYPES'],
                    $data['first_sort']);
            ?></td></tr>
        <tr><td><label for="last-name"><b><?php
                e(tl('manageusers_element_lastname')); ?>:</b></label>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "last-comparison", "last_comparison", 
                    $data['COMPARISON_TYPES'],
                    $data['last_comparison']);
                e($item_sep);
            ?><input type="text" id="last-name"
                name="last_name"  maxlength="80"
                value="<?php e($data['last_name']); ?>"
                class="narrow-field"/>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "last-sort", "last_sort", 
                    $data['SORT_TYPES'],
                    $data['last_sort']);
            ?></td></tr>
        <tr><td><label for="e-mail"><b><?php
                e(tl('manageusers_element_email')); ?>:</b></label>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "email-comparison", "email_comparison", 
                    $data['COMPARISON_TYPES'],
                    $data['email_comparison']);
                e($item_sep);
            ?><input type="text" id="e-mail"
                name="email_name" maxlength="80"
                value="<?php e($data['email_name']); ?>"
                class="narrow-field"/>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "email-sort", "email_sort", 
                    $data['SORT_TYPES'],
                    $data['email_sort']);
            ?></td></tr>
        <tr><td><label for="search-userstatus-user"><b><?php
                e(tl('manageusers_element_status')); ?>:</b></label>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "status-comparison", "status_comparison", 
                    $data['STATUS_COMPARISON_TYPES'],
                    $data['status_comparison']);
            ?>
            <style type="text/css">
            #status-comparison {
                width:100%;
            }
            </style>
            <?php
            e($item_sep);
            $this->view->optionsHelper->render(
                "search-userstatus-user",
                "status_name", $data['STATUS_CODES'],
                $data['status_name']);
            ?>
            <style type="text/css">
            #search-userstatus-user {
                width:100%
            }
            </style>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "status-sort", "status_sort", 
                    $data['SORT_TYPES'],
                    $data['status_sort']);
            ?></td></tr>
        <tr><?php if(!MOBILE) {?><td></td><td></td> <?php } ?>
            <td <?php if(!MOBILE) {
                    ?>class="center" <?php 
                }
                ?>><button class="button-box"
                type="submit"><?php e(tl('manageusers_element_search'));
                ?></button></td>
        </tr>
        </table>
        </form>
        <?php
    }
}
?>
