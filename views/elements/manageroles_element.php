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
     *      available roles or which activity has what role
     */
    function render($data)
    { ?>
        <div class="current-activity">
        <?php
        if($data['FORM_TYPE'] == "searchroles") {
            $this->renderSearchForm($data);
        } else {
            $this->renderRoleForm($data);
        }
        if(MOBILE) {
            $this->mobileTitleNumRoleControls($data);
        } else {
            $this->desktopTitleNumRoleControls($data);
        }
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
            $delete_url = $base_url . "&amp;arg=deleterole&amp;";
            $edit_url = $base_url . "&amp;arg=editrole&amp;";
            foreach($data['ROLES'] as $role) {
                echo "<tr>";
                foreach($role as $colname => $role_column) {
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
        </div>
    <?php
    }
    /**
     *  Draws the heading before the role table as well as the controls
     *  for what role to see (mobile phone case).
     *
     *  @param array $data needed for dropdown values for number of roles to
     *      display
     */
    function mobileTitleNumRoleControls($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageRoles";
        ?>
        <h2><?php e(tl('manageroles_element_roles')); ?>&nbsp;&nbsp;[<a 
                href="<?php e($base_url . '&amp;arg=searchroles');
                ?>"><?php e(tl('manageroles_element_search'));?></a>]</h2>
        <div>
            <form  method="get" action='#' >
            <?php
            $bound_url = $base_url."&amp;arg=".$data['FORM_TYPE'];
            if(isset($data['CURRENT_ROLE']['name']) && 
                $data['CURRENT_role']['name'] != "") {
                $bound_url .="&amp;name=".$data['CURRENT_ROLE'][
                    'name'];
            } ?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" 
                value="<?php e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="manageRoles" />
            <?php
            e("<b>".tl('manageroles_element_show')."</b>");
            $this->view->optionsHelper->render(
                "roles-show", "roles_show", $data['ROLES_SHOW_CHOICES'],
                $data['roles_show'], true);
            e("<br />");
            if($data['START_ROW'] > 0) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['PREV_START']); ?>&amp;end_row=<?php 
                    e($data['PREV_END']); ?>&amp;roles_show=<?php 
                    e($data['roles_show'].$data['PAGING']); ?>">&lt;&lt;</a>
                <?php
            }
            e("<b>".tl('manageroles_element_row_range', $data['START_ROW'],
                $data['END_ROW'], $data['NUM_ROLES'])."</b>");
            if($data['END_ROW'] < $data['NUM_ROLES']) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['NEXT_START']); ?>&amp;end_row=<?php 
                    e($data['NEXT_END']); ?>&amp;roles_show=<?php 
                    e($data['roles_show'].$data['PAGING']); ?>" >&gt;&gt;</a>
                <?php
            }
            ?>
            </form>
        </div>
        <?php
    }

    /**
     *  Draws the heading before the role table as well as the controls
     *  for what role to see (desktop, laptop, tablet case).
     *
     *  @param array $data needed for dropdown values for number of roles to
     *      display
     */
    function desktopTitleNumRoleControls($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageRoles";
        ?>
        <h2><?php e(tl('manageroles_element_roles')); ?></h2>

        <div>
            <form  method="get" action='#' >
            <?php
            $bound_url = $base_url."&amp;arg=".$data['FORM_TYPE'];
            if(isset($data['CURRENT_ROLE']['name']) && 
                $data['CURRENT_ROLE']['name'] != "") {
                $bound_url .="&amp;name=".$data['CURRENT_ROLE'][
                    'name'];
            }
            if($data['START_ROW'] > 0) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['PREV_START']); ?>&amp;end_row=<?php 
                    e($data['PREV_END']); ?>&amp;roles_show=<?php 
                    e($data['roles_show'].$data['PAGING']); ?>">&lt;&lt;</a>
                <?php
            }
            e("<b>".tl('manageroles_element_row_range', $data['START_ROW'],
                $data['END_ROW'], $data['NUM_ROLES'])."</b>");
            if($data['END_ROW'] < $data['NUM_ROLES']) {
                ?>
                <a href="<?php e($bound_url); ?>&amp;start_row=<?php
                    e($data['NEXT_START']); ?>&amp;end_row=<?php 
                    e($data['NEXT_END']); ?>&amp;roles_show=<?php 
                    e($data['roles_show'].$data['PAGING']); ?>" >&gt;&gt;</a>
                <?php
            }
            ?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" 
                value="<?php e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="manageRoles" />
            <?php
                e("<b>".tl('manageroles_element_show')."</b>");
                $this->view->optionsHelper->render(
                    "roles-show", "roles_show", $data['ROLES_SHOW_CHOICES'],
                    $data['roles_show'], true);
            ?>
            [<a href="<?php e($base_url . '&amp;arg=searchroles');
                ?>"><?php e(tl('manageroles_element_search'));?></a>]
            </form>
        </div>
        <?php
    }

    /**
     *  Draws the add role and edit role forms
     *
     *  @param array $data consists of values of role fields set
     *      so far as well as values of the drops downs on the form
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
        <tr><td class="table-label"><label for="role-name"><?php
            e(tl('manageroles_element_rolename'))?></label>:</td>
            <td><input type="text" id="role-name"
                name="name"  maxlength="80"
                value="<?php e($data['CURRENT_ROLE']['name']); ?>"
                class="narrow-field" <?php
                if($editrole) {
                    e(' disabled="disabled" ');
                }
                ?> /></td></tr>
        <?php
        if($editrole) {
        ?>
            <tr><td class="table-label" style="vertical-align:top"><?php
                    e(tl('manageroles_element_role_activities')); ?>:</td>
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
                        $this->view->optionsHelper->render("add-roleactivity",
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
     *  Draws the search for roles forms
     *
     *  @param array $data consists of values of role fields set
     *      so far as well as values of the drops downs on the form
     */
    function renderSearchForm($data)
    {
        $base_url = "?c=admin&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;a=manageRoles";
        e("<div class='float-opposite'><a href='$base_url'>".
            tl('manageroles_element_addrole_form')."</a></div>");
        e("<h2>".tl('manageroles_element_search_role'). "</h2>");
        $item_sep = (MOBILE) ? "<br />" : "</td><td>";
        ?>
        <form id="roleForm" method="post" action='#' autocomplete="off">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageRoles" />
        <input type="hidden" name="arg" value="<?php
            e($data['FORM_TYPE']);?>" />
        <table class="name-table">
        <tr><td class="table-label"><label for="role-name"><?php
            e(tl('manageroles_element_rolename'))?>:</label>
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "name-comparison", "name_comparison", 
                    $data['COMPARISON_TYPES'],
                    $data['name_comparison']);
                e($item_sep);
            ?><input type="text" id="role-name"
                name="name"  maxlength="80"
                value="<?php e($data['name']); ?>"
                class="narrow-field"  />
            <?php
                e($item_sep);
                $this->view->optionsHelper->render(
                    "name-sort", "name_sort", 
                    $data['SORT_TYPES'],
                    $data['name_sort']);
            ?></td></tr>
        <tr><?php if(!MOBILE) {?><td></td><td></td> <?php } ?>
            <td <?php if(!MOBILE) {
                    ?>class="center" <?php 
                }
                ?>><button class="button-box"
                type="submit"><?php e(tl('manageroles_element_search'));
                ?></button></td>
        </tr>
        </table>
        </form>
        <?php
    }
}
?>
