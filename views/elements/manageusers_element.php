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
        $edituser= ($data['FORM_TYPE'] == "edituser") ? true: false;
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageUsers";
    ?>
        <div class="current-activity">
        <?php
            if($edituser) {
                e("<div class='float-opposite'><a href='$base_url'>".
                    tl('manageusers_element_adduser_form')."</a></div>");
                e("<h2>".tl('manageusers_element_user_info'). "</h2>");
            } else {
                e("<h2>".tl('manageusers_element_add_user'). "</h2>");
            }?>
        <form id="addUserForm" method="post" action=''>
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
                $this->view->optionsHelper->render(
                    "update-userstatus-currentuser",
                    "status", $data['STATUS_CODES'],
                    $data['CURRENT_USER']['status']);?></td></tr>
        <?php
        if($data["FORM_TYPE"] == 'edituser') {
        ?>
            <tr><td style="vertical-align:top"><?php
                    e(tl('manageusers_element_roles')); ?>:</td>
                <td><div style="border:2px solid black;padding:5px"><table><?php
                foreach($data['SELECT_ROLES'] as $role_array) {
                    e("<tr><td><b>".
                        $role_array['ROLE_NAME'].
                        "</b></td><td><a href='?c=admin&amp;a=manageUsers".
                        "&amp;arg=deleteuserrole&amp;selectrole=".
                        $role_array['ROLE_ID']);
                    e("&amp;user_name=".$data['CURRENT_USER']['user_name'].
                        "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                        "'>Delete</a></td>");
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
                <td><div style="border:2px solid black;padding:5px"><table><?php
                foreach($data['SELECT_GROUPS'] as $group_array) {
                    e("<tr><td><b>".
                        $group_array['GROUP_NAME'].
                        "</b></td><td><a href='?c=admin&amp;a=manageUsers".
                        "&amp;arg=deleteusergroup&amp;selectgroup=".
                        $group_array['GROUP_ID']);
                    e("&amp;user_name=".$data['CURRENT_USER']['user_name'].
                        "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                        "'>Delete</a></td>");
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
        <h2><?php e(tl('manageusers_element_users')); ?></h2>
        <table class="role-table">
            <tr>
                <th><?php e(tl('manageusers_element_username'));?></th>
                <th><?php e(tl('manageusers_element_firstname'));?></th>
                <th><?php e(tl('manageusers_element_lastname'));?></th>
                <th><?php e(tl('manageusers_element_email'));?></th>
                <th><?php e(tl('manageusers_element_status'));?></th>
                <th colspan='2'><?php 
                    e(tl('manageusers_element_actions'));?></th>
            </tr>
        <?php
            $delete_url = $base_url . "&amp;arg=deleteuser&amp;";
            $edit_url = $base_url . "&amp;arg=edituser&amp;";
            foreach($data['USERS'] as $user) {
                echo "<tr>";
                foreach($user as $colname => $user_column) {
                    if(strcmp($colname,"STATUS") == 0) {
                        ?><td>
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
                <td><a href="<?php e($delete_url . 'user_name='.
                    $user['USER_NAME']); ?>"><?php 
                    e(tl('manageusers_element_delete'));
                    ?></a></td>
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
}
?>
