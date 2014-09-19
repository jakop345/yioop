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
 * Used to draw the admin screen on which admin users can create roles, delete
 * roles and add and delete activitiess from roles
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
class ManagerolesElement extends Element
{
    /**
     * renders the screen in which roles can be created, deleted, and activities
     * can be added to and deleted from a selected roles
     *
     * @param array $data  contains antiCSRF token, as well as data on
     *     available roles or which activity has what role
     */
    function render($data)
    { ?>
        <div class="current-activity">
        <?php
        if($data['FORM_TYPE'] == "search") {
            $this->renderSearchForm($data);
        } else {
            $this->renderRoleForm($data);
        }
        $data['TABLE_TITLE'] = tl('manageroles_element_roles');
        $data['ACTIVITY'] = 'manageRoles';
        $data['VIEW'] = $this->view;
        $data['NO_FLOAT_TABLE'] = true;
        $this->view->helper("pagingtable")->render($data);
        ?>
        <table class="role-table table-margin">
            <tr>
                <th><?php e(tl('manageroles_element_rolename'));?></th>
                <th colspan='2'><?php
                    e(tl('manageroles_element_actions'));?></th>
            </tr>
        <?php
            $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                "&amp;a=manageRoles";
            if(isset($data['START_ROW'])) {
                $base_url .= "&amp;start_row=".$data['START_ROW'].
                    "&amp;end_row=".$data['END_ROW'].
                    "&amp;num_show=".$data['NUM_SHOW'];
            }
            $delete_url = $base_url . "&amp;arg=deleterole&amp;";
            $edit_url = $base_url . "&amp;arg=editrole&amp;";
            $stretch = (MOBILE) ? 1 :2;
            foreach($data['ROLES'] as $role) {
                echo "<tr>";
                foreach($role as $colname => $role_column) {
                        if(strlen($role_column) > $stretch * NAME_TRUNCATE_LEN){
                            $role_column =substr($role_column, 0,
                                 $stretch * NAME_TRUNCATE_LEN)."..";
                        }
                        echo "<td>$role_column</td>";
                }
                ?>
                <td><a href="<?php e($edit_url . 'name='.
                    $role['NAME']); ?>"><?php
                    e(tl('manageroles_element_edit'));
                    ?></a></td>
                <td><?php
                    if($role['NAME'] == 'Admin' || $role['NAME'] == 'User') {
                        e('<span class="gray">'.
                            tl('manageroles_element_delete').'</span>');
                    } else {
                    ?>
                        <a href="<?php e($delete_url . 'name='.
                        $role['NAME']); ?>"><?php
                        e(tl('manageroles_element_delete'));
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
     * Draws the add role and edit role forms
     *
     * @param array $data consists of values of role fields set
     *     so far as well as values of the drops downs on the form
     */
    function renderRoleForm($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageRoles";
        $editrole = ($data['FORM_TYPE'] == "editrole") ? true: false;
        if($editrole) {
            e("<div class='float-opposite'><a href='$base_url'>".
                tl('manageroles_element_addrole_form')."</a></div>");
            e("<h2>".tl('manageroles_element_role_info'). "</h2>");
        } else {
            e("<h2>".tl('manageroles_element_add_role'). "</h2>");
        }
        ?>
        <form id="addRoleForm" method="post" action='#'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageRoles" />
        <input type="hidden" name="arg" value="<?php
            e($data['FORM_TYPE']);?>" />
        <table class="name-table">
        <tr><th class="table-label"><label for="role-name"><?php
            e(tl('manageroles_element_rolename'))?></label>:</th>
            <th><input type="text" id="role-name"
                name="name"  maxlength="<?php e(NAME_LEN); ?>"
                value="<?php e($data['CURRENT_ROLE']['name']); ?>"
                class="narrow-field" <?php
                if($editrole) {
                    e(' disabled="disabled" ');
                }
                ?> /></th></tr>
        <?php
        if($editrole) {
        ?>
            <tr><th class="table-label" style="vertical-align:top"><?php
                    e(tl('manageroles_element_role_activities')); ?>:</th>
                <td><div class='light-gray-box'><table><?php
                foreach($data['ROLE_ACTIVITIES'] as $activity_array) {
                    e("<tr><td><b>".
                        $activity_array['ACTIVITY_NAME'].
                        "</b></td>");
                    if($data['CURRENT_ROLE']['name'] == 'Admin' ||
                        $activity_array['METHOD_NAME'] == 'manageAccount') {
                        e("<td><span class='gray'>".
                            tl('manageroles_element_delete')."</span></td>");
                    } else {
                        e("<td><a href='?c=admin&amp;a=manageRoles".
                            "&amp;arg=deleteactivity&amp;selectactivity=".
                            $activity_array['ACTIVITY_ID']);
                        e("&amp;name=".$data['CURRENT_ROLE']['name'].
                            "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                            "'>".tl('manageroles_element_delete')."</a></td>");
                    }
                    e("</tr>");
                }
                ?>
                </table>
                <?php
                    if(count($data['AVAILABLE_ACTIVITIES']) > 1) {
                        $this->view->helper("options")->render(
                            "add-roleactivity",
                            "selectactivity", $data['AVAILABLE_ACTIVITIES'],
                            $data['SELECT_ACTIVITY']);
                    }
                ?>
                </div>
                </td></tr>
        <?php
        }
        ?>
        <tr><td></td><td class="center"><button class="button-box"
            type="submit"><?php e(tl('manageroles_element_save'));
            ?></button></td>
        </tr>
        </table>
        </form>
        <?php
    }
    /**
     * Draws the search for roles forms
     *
     * @param array $data consists of values of role fields set
     *     so far as well as values of the drops downs on the form
     */
    function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageRoles";
        $view = $this->view;
        $title = tl('manageroles_element_search_role');
        $return_form_name = tl('manageroles_element_addrole_form');
        $fields = array(
            tl('manageroles_element_rolename') => "name",
        );
        $view->helper("searchform")->render($data, $controller, $activity,
                $view, $title, $return_form_name, $fields);
    }
}
?>
