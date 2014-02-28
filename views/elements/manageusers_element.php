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
     * Draws a screen in which an admin can add users, delete users,
     * and manipulate user roles.
     *
     * @param array $data info about current users and current roles, CSRF token
     */
    function render($data)
    {
    ?>
        <div class="current-activity">
        <?php
        if($data['FORM_TYPE'] == "search") {
            $this->renderSearchForm($data);
        } else {
            $this->renderUserForm($data);
        }
        $data['TABLE_TITLE'] = tl('manageusers_element_users');
        $data['ACTIVITY'] = 'manageUsers';
        $data['VIEW'] = $this->view;
        $this->view->helper("pagingtable")->render($data);

        $default_accounts = array(ROOT_ID, PUBLIC_USER_ID);
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
            $stretch = (MOBILE) ? 1 :2;
            foreach($data['USERS'] as $user) {
                echo "<tr>";
                foreach($user as $colname => $user_column) {
                    if($colname == "USER_ID" || (
                        MOBILE && !in_array($colname, $mobile_columns))) {
                        continue;
                    }
                    if(strlen($user_column) > $stretch * NAME_TRUNCATE_LEN) {
                        $user_column = substr($user_column, 0,
                            $stretch * NAME_TRUNCATE_LEN)."..";
                    }
                    if(strcmp($colname,"STATUS") == 0) {
                        ?><td>
                        <?php
                        if(in_array($user['USER_ID'], $default_accounts)) {
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
                        $this->view->helper("options")->render(
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
                <td><?php
                    if($user['USER_ID'] == PUBLIC_USER_ID) {
                        e('<span class="gray">'.
                            tl('manageusers_element_edit').'</span>');
                    } else {?>
                        <a href="<?php e($edit_url . 'user_name='.
                        $user['USER_NAME']); ?>"><?php
                        e(tl('manageusers_element_edit'));
                        ?></a></td>
                        <?php
                    } ?>
                <td><?php
                    if(in_array($user['USER_ID'], $default_accounts)) {
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
        <tr><th class="table-label"><label for="user-name"><?php
            e(tl('manageusers_element_username'))?>:</label></th>
            <td><input type="text" id="user-name"
                name="user_name"  maxlength="80"
                value="<?php e($data['CURRENT_USER']['user_name']); ?>"
                class="narrow-field" <?php
                if($edituser) {
                    e(' disabled="disabled" ');
                }
                ?> /></td></tr>
        <tr><th class="table-label"><label for="first-name"><?php
                e(tl('manageusers_element_firstname')); ?>:</label></th>
            <td><input type="text" id="first-name"
                name="first_name"  maxlength="80"
                value="<?php e($data['CURRENT_USER']['first_name']); ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label for="last-name"><?php
                e(tl('manageusers_element_lastname')); ?>:</label></th>
            <td><input type="text" id="last-name"
                name="last_name"  maxlength="80"
                value="<?php e($data['CURRENT_USER']['last_name']); ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label for="e-mail"><?php
                e(tl('manageusers_element_email')); ?>:</label></th>
            <td><input type="text" id="e-mail"
                name="email"  maxlength="80"
                value="<?php e($data['CURRENT_USER']['email']); ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label
                for="update-userstatus-currentuser"><?php
                e(tl('manageusers_element_status')); ?>:</label></th>
            <td><?php
                if($data['CURRENT_USER']['user_name'] == 'root') {
                    e("<div class='light-gray-box'><span class='gray'>".
                        $data['STATUS_CODES'][$data['CURRENT_USER']['status']].
                        "</span></div><input type='hidden' name='status' ".
                        "value='".$data['CURRENT_USER']['status']."' />");
                } else {
                    $this->view->helper("options")->render(
                        "update-userstatus-currentuser",
                        "status", $data['STATUS_CODES'],
                        $data['CURRENT_USER']['status']);
                }?></td></tr>
        <?php
        if($edituser) {
        ?>
            <tr><th class="table-label" style="vertical-align:top"><?php
                    e(tl('manageusers_element_roles')); ?>:</th>
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
                            "'>".tl('manageusers_element_delete')."</a></td>");
                    }
                    e("</tr>");
                }
                ?>
                </table>
                <?php $this->view->helper("options")->render("add-userrole",
                        "selectrole", $data['AVAILABLE_ROLES'],
                        $data['SELECT_ROLE']); ?>
                </div>
                </td></tr>
            <tr><th class="table-label" style="vertical-align:top"><?php
                    e(tl('manageusers_element_groups')); ?>:</th>
                <td><div class='light-gray-box'><table><?php
                foreach($data['SELECT_GROUPS'] as $group_array) {
                    e("<tr><td><b>".
                        $group_array['GROUP_NAME'].
                        "</b></td>");
                    e("<td><a href='?c=admin&amp;a=manageUsers".
                        "&amp;arg=deleteusergroup&amp;selectgroup=".
                        $group_array['GROUP_ID']);
                    e("&amp;user_name=".$data['CURRENT_USER']['user_name'].
                        "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                        "'>".tl('manageusers_element_delete')."</a></td>");
                }
                ?>
                </table>
                <?php $this->view->helper("options")->render("add-usergroup",
                        "selectgroup", $data['AVAILABLE_GROUPS'],
                        $data['SELECT_GROUP']); ?>
                </div>
                </td></tr>
        <?php
        }
        ?>
        <tr><th class="table-label"><label for="pass-word"><?php
             e(tl('manageusers_element_password'))?>:</label></th>
            <td><input type="password" id="pass-word"
                name="password" maxlength="80"
                value="<?php e($data['CURRENT_USER']['password']); ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label for="retype-password"><?php
                e(tl('manageusers_element_retype_password'))?>:</label></th>
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
        <input type="hidden" name="arg" value="search" />
        <table class="name-table">
        <tr><td class="table-label"><label for="user-name"><?php
            e(tl('manageusers_element_username'))?>:</label>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
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
                $this->view->helper("options")->render(
                    "user-sort", "user_sort",
                    $data['SORT_TYPES'],
                    $data['user_sort']);
            ?></td></tr>
        <tr><td class="table-label"><label for="first-name"><?php
                e(tl('manageusers_element_firstname')); ?>:</label>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
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
                $this->view->helper("options")->render(
                    "first-sort", "first_sort",
                    $data['SORT_TYPES'],
                    $data['first_sort']);
            ?></td></tr>
        <tr><td class="table-label"><label for="last-name"><?php
                e(tl('manageusers_element_lastname')); ?>:</label>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
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
                $this->view->helper("options")->render(
                    "last-sort", "last_sort",
                    $data['SORT_TYPES'],
                    $data['last_sort']);
            ?></td></tr>
        <tr><td class="table-label"><label for="e-mail"><?php
                e(tl('manageusers_element_email')); ?>:</label>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
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
                $this->view->helper("options")->render(
                    "email-sort", "email_sort",
                    $data['SORT_TYPES'],
                    $data['email_sort']);
            ?></td></tr>
        <tr><td class="table-label"><label for="search-userstatus-user"><?php
                e(tl('manageusers_element_status')); ?>:</label>
            <?php
                e($item_sep);
                $this->view->helper("options")->render(
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
            $this->view->helper("options")->render(
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
                $this->view->helper("options")->render(
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