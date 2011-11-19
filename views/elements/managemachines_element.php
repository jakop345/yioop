<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
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
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Used to draw the admin screen on which admin users can create roles, delete 
 * roles and add and delete roles from users
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManagemachinesElement extends Element
{

    /**
     * renders the screen in which roles can be created, deleted, and added or 
     * deleted from a user
     *
     * @param array $data  contains antiCSRF token, as well as data on 
     *      available roles or which user has what role
     */
    public function render($data) 
    {?>
        <div class="currentactivity">
        <h2><?php e(tl('managemachines_element_add_machine'))?></h2>
        <form id="addRoleForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="manageMachines" />
        <input type="hidden" name="arg" value="addmachine" />

        <table class="nametable">
        <tr><td><label for="machine-url"><?php 
            e(tl('manageroles_element_machineurl'))?></label></td>
            <td><input type="text" id="machine-url" name="machineurl" 
                maxlength="80" class="widefield" /></td><td 
                class="center"><button class="buttonbox" type="submit"><?php 
                e(tl('managemachines_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>

        <h2><?php e(tl('managemachines_element_delete_machine'))?></h2>
        <form id="deleteMachineForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" />
        <input type="hidden" name="a" value="manageMachines" /> 
        <input type="hidden" name="arg" value="deletemachine" />

        <table class="nametable">
         <tr><td><label for="delete-machinename"><?php 
            e(tl('manageusers_element_delete_machinename'))?></label></td>
            <td><?php $this->view->optionsHelper->render(
                "delete-machinename", "selectmachine", 
                $data['MACHINE_NAMES'], "-1"); 
                ?></td><td><button class="buttonbox" type="submit"><?php 
                e(tl('managemachines_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>
        <h2><?php e(tl('managemachines_element_machine_info'))?></h2>
        <?php
        if(isset($data['MACHINES'])) {

             ?>
        <?php
        }
        ?>
        </div>
    <?php 
    }
}
?>
