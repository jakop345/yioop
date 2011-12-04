<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Used to draw the admin screen on which admin users can 
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManagemachinesElement extends Element
{

    /**
     *
     * @param array $data  contains antiCSRF token, as well as data on 
     */
    public function render($data) 
    {?>
        <div class="currentactivity">
        <h2><?php e(tl('managemachines_element_add_machine'))?></h2>
        <form id="addMachineForm" method="post" action=''>
        <input type="hidden" name="c" value="admin" /> 
        <input type="hidden" name="YIOOP_TOKEN" value="<?php 
            e($data['YIOOP_TOKEN']); ?>" /> 
        <input type="hidden" name="a" value="manageMachines" />
        <input type="hidden" name="arg" value="addmachine" />

        <table class="nametable">
        <tr><th><label for="machine-name"><?php 
            e(tl('managemachines_element_machine_name'))?></label></th>
            <td><input type="text" id="machine-name" name="name" 
                maxlength="80" class="widefield" /></td>
        </tr>

        <tr><th><label for="machine-url"><?php 
            e(tl('managemachines_element_machineurl'))?></label></th>
            <td><input type="text" id="machine-url" name="url" 
                maxlength="80" class="widefield" /></td></tr>

        <tr><th><label for="queue-box"><?php 
            e(tl('managemachines_element_has_queueserver'))?></label></th>
            <td><input type="checkbox" id="queue-box" 
                name="has_queue_server" value="true" /></td></tr>
        <tr><th><label for="fetcher-number"><?php 
            e(tl('managemachines_element_num_fetchers'))?></label></th><td>
            <?php $this->view->optionsHelper->render("fetcher-number", 
            "num_fetchers", $data['FETCHER_NUMBERS'],$data['FETCHER_NUMBER']);
            ?></td></tr>
        <tr><td></td><td><button class="buttonbox" type="submit"><?php 
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
         <tr><th><label for="delete-machine-name"><?php 
            e(tl('managemachines_element_machine_name'))?></label></th>
            <td><?php $this->view->optionsHelper->render(
                "delete-machine-name", "name", 
                $data['DELETABLE_MACHINES'], 
                tl('admin_controller_select_machine')); 
                ?></td><td><button class="buttonbox" type="submit"><?php 
                e(tl('managemachines_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>

        <h2><?php e(tl('managemachines_element_machine_info'))?></h2>
        <div id="machinestatus" >
        <p class="red"><?php 
            e(tl('managemachines_element_awaiting_status'))?></p>
        </div>
        <script type="text/javascript" >
        var updateId;
        function machineStatusUpdate()
        {
            var startUrl = "?c=admin&YIOOP_TOKEN=<?php 
                e($data['YIOOP_TOKEN']); ?>&a=machineStatus";
            var machineTag = elt('machinestatus');
            getPage(machineTag, startUrl);
        }

        function clearUpdate()
        {
             clearInterval(updateId );
             var machineTag = elt('machinestatus');
             machine.innerHTML= "<h2 class='red'><?php 
                e(tl('managemachines_element_no_longer_update'))?></h2>";
        }
        function doUpdate()
        {
             var sec = 1000;
             var minute = 60*sec;
             machineStatusUpdate();
             updateId = setInterval("machineStatusUpdate()", 30*sec);
             setTimeout("clearUpdate()", 20*minute + sec);
        }
        </script>

        </div>
    <?php 
    }
}
?>
